<?php

declare(strict_types=1);

require_once __DIR__ . '/repository.php';

/**
 * Importação/conciliação de extratos oficiais de pagamentos do Meu Primeiro Emprego.
 *
 * Regra central: o PDF NÃO atualiza dados pessoais do candidato. O CPF validado é usado
 * apenas para localizar um cadastro existente e lançar/conciliar a bolsa paga.
 */

function pe_payment_pdf_schema_ready(): bool
{
    try {
        $pdo = pe_db();
        $pdo->query('SELECT arquivo_hash, convenio_numero, lista_numero, competencia, conciliados, conflitos_financeiros,
                            candidatos_criados, candidatos_recuperados, candidatos_excluidos
                     FROM pe_pagamento_importacoes LIMIT 1');
        $pdo->query('SELECT importacao_id, conciliacao_status, cpf_validado, valor FROM pe_pagamento_importacao_itens LIMIT 1');
        $pdo->query('SELECT lista_final_ativa, lista_final_origem, lista_final_importacao_id,
                            lista_final_sincronizada_em, lista_final_excluido_em, lista_final_exclusao_motivo
                     FROM pe_candidatos LIMIT 1');
        return true;
    } catch (Throwable) {
        return false;
    }
}

function pe_payment_pdf_validate_upload(array $file): array
{
    $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($error !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Falha no upload do PDF. Código: ' . $error);
    }

    $tmp = (string) ($file['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        throw new InvalidArgumentException('Selecione um arquivo PDF válido.');
    }

    $size = (int) ($file['size'] ?? 0);
    if ($size <= 0 || $size > 12 * 1024 * 1024) {
        throw new InvalidArgumentException('O PDF deve ter no máximo 12 MB.');
    }

    $original = mb_substr(basename((string) ($file['name'] ?? 'pagamentos.pdf')), 0, 255, 'UTF-8');
    if (strtolower(pathinfo($original, PATHINFO_EXTENSION)) !== 'pdf') {
        throw new InvalidArgumentException('Formato inválido. Envie um arquivo PDF.');
    }

    $fh = @fopen($tmp, 'rb');
    if (!$fh) {
        throw new RuntimeException('Não foi possível validar o arquivo PDF.');
    }
    $signature = (string) fread($fh, 5);
    fclose($fh);
    if ($signature !== '%PDF-') {
        throw new InvalidArgumentException('O arquivo enviado não possui uma assinatura PDF válida.');
    }

    $mime = '';
    if (function_exists('finfo_open')) {
        $finfo = @finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo) {
            $mime = (string) @finfo_file($finfo, $tmp);
            @finfo_close($finfo);
        }
    }
    if ($mime !== '' && !in_array($mime, ['application/pdf', 'application/x-pdf', 'application/octet-stream'], true)) {
        throw new InvalidArgumentException('O conteúdo do arquivo não foi reconhecido como PDF.');
    }

    return [
        'tmp_name' => $tmp,
        'name' => $original,
        'size' => $size,
        'mime' => $mime ?: 'application/pdf',
        'hash' => hash_file('sha256', $tmp) ?: null,
    ];
}

function pe_payment_pdf_function_disabled(string $function): bool
{
    if (!function_exists($function)) {
        return true;
    }
    $disabled = array_filter(array_map('trim', explode(',', (string) ini_get('disable_functions'))));
    return in_array($function, $disabled, true);
}

function pe_payment_pdf_find_pdftotext(): ?string
{
    foreach ([
        '/usr/bin/pdftotext',
        '/usr/local/bin/pdftotext',
        '/opt/homebrew/bin/pdftotext',
    ] as $candidate) {
        if (is_file($candidate) && is_executable($candidate)) {
            return $candidate;
        }
    }
    return null;
}

function pe_payment_pdf_try_server_text(string $path): ?string
{
    $binary = pe_payment_pdf_find_pdftotext();
    if ($binary === null) {
        return null;
    }

    // Caminho preferencial: proc_open sem shell, com argumentos separados.
    if (!pe_payment_pdf_function_disabled('proc_open')) {
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = @proc_open([$binary, '-layout', $path, '-'], $descriptors, $pipes, null, ['LANG' => 'C.UTF-8']);
        if (is_resource($process)) {
            fclose($pipes[0]);
            stream_set_timeout($pipes[1], 20);
            stream_set_timeout($pipes[2], 20);
            $stdout = stream_get_contents($pipes[1]);
            stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            $exit = proc_close($process);

            if ($exit === 0 && is_string($stdout) && trim($stdout) !== '') {
                if (strlen($stdout) > 4 * 1024 * 1024) {
                    throw new RuntimeException('O texto extraído do PDF excedeu o limite de segurança.');
                }
                return $stdout;
            }
        }
    }

    // Compatibilidade com hospedagens compartilhadas que desabilitam proc_open,
    // mas mantêm shell_exec. Os dois argumentos são escapados e o binário é fixo.
    if (!pe_payment_pdf_function_disabled('shell_exec')) {
        $command = escapeshellarg($binary)
            . ' -layout '
            . escapeshellarg($path)
            . ' - 2>/dev/null';
        $stdout = @shell_exec($command);

        if (is_string($stdout) && trim($stdout) !== '') {
            if (strlen($stdout) > 4 * 1024 * 1024) {
                throw new RuntimeException('O texto extraído do PDF excedeu o limite de segurança.');
            }
            return $stdout;
        }
    }

    return null;
}

function pe_payment_pdf_extract_text(string $pdfPath, ?string $browserText): array
{
    // Quando o navegador já enviou uma extração validada visualmente, ela deve
    // ter prioridade. Isso é essencial em hospedagens cujo pdftotext existe,
    // mas devolve a tabela rotacionada/fora de ordem. Também garante que a
    // confirmação use exatamente o mesmo texto que foi aprovado na pré-análise.
    $browserText = trim((string) $browserText);
    if ($browserText !== '') {
        if (strlen($browserText) > 4 * 1024 * 1024) {
            throw new InvalidArgumentException('O texto extraído do PDF excede o limite permitido.');
        }
        return ['text' => $browserText, 'source' => 'pdfjs-navegador'];
    }

    $serverText = pe_payment_pdf_try_server_text($pdfPath);
    if ($serverText !== null) {
        return ['text' => $serverText, 'source' => 'pdftotext-servidor'];
    }

    throw new RuntimeException('O servidor não possui extrator de PDF e o navegador não enviou o texto do documento. Clique em “Analisar PDF” antes de confirmar.');
}

function pe_payment_pdf_clean_line(string $line): string
{
    $line = str_replace(["\xC2\xA0", "\t"], ' ', $line);
    $line = trim($line);
    return preg_replace('/\s+/u', ' ', $line) ?: $line;
}

function pe_payment_pdf_money_to_float(string $value): float
{
    $value = trim(str_replace(['R$', ' '], '', $value));
    $value = str_replace('.', '', $value);
    $value = str_replace(',', '.', $value);
    return is_numeric($value) ? round((float) $value, 2) : 0.0;
}

function pe_payment_pdf_br_date(?string $date): ?string
{
    $date = trim((string) $date);
    if ($date === '') {
        return null;
    }
    $dt = DateTime::createFromFormat('d/m/Y', $date);
    return $dt && $dt->format('d/m/Y') === $date ? $dt->format('Y-m-d') : null;
}

function pe_payment_pdf_meta_value(string $text, string $pattern): ?string
{
    if (!preg_match($pattern, $text, $match)) {
        return null;
    }
    return isset($match[1]) ? trim(pe_payment_pdf_clean_line((string) $match[1])) : null;
}

function pe_payment_pdf_parse_text(string $text): array
{
    $text = str_replace(["\r\n", "\r", "\f"], ["\n", "\n", "\n"], $text);
    $flat = pe_payment_pdf_clean_line(str_replace("\n", ' ', $text));

    if (stripos($flat, 'Banco do Brasil') === false || stripos($flat, 'Extrato de Lista de Pagamentos') === false) {
        throw new InvalidArgumentException('O PDF não foi reconhecido como “Extrato de Lista de Pagamentos” do Banco do Brasil.');
    }

    $convenioNumero = pe_payment_pdf_meta_value($flat, '/N[uú]mero do conv[eê]nio:\s*(\d+)/iu');
    $convenioNome = pe_payment_pdf_meta_value($flat, '/N[uú]mero do conv[eê]nio:\s*\d+\s+Conv[eê]nio:\s*(.*?)\s+N[uú]mero lista:/iu');
    $listaNumero = pe_payment_pdf_meta_value($flat, '/N[uú]mero lista:\s*(\d+)/iu');
    $listaNome = pe_payment_pdf_meta_value($flat, '/N[uú]mero lista:\s*\d+\s+Lista:\s*(.*?)\s+Estado da lista:/iu');
    $estadoLista = pe_payment_pdf_meta_value($flat, '/Estado da lista:\s*([\p{L}\- ]+?)\s+Data de pagamento:/iu');
    $dataPagamentoBr = pe_payment_pdf_meta_value($flat, '/Data de pagamento:\s*(\d{2}\/\d{2}\/\d{4})/iu');
    $formaPagamento = pe_payment_pdf_meta_value($flat, '/Forma de pagamento:\s*(.*?)\s+Data limite para cr[eé]ditos:/iu');
    $totalPagamentosRaw = pe_payment_pdf_meta_value($flat, '/Total de pagamentos:\s*(\d+)/iu');
    $valorTotalRaw = pe_payment_pdf_meta_value($flat, '/Valor total da lista:\s*R\$\s*([\d.]+,\d{2})/iu');

    $rows = [];
    $seenIdent = [];
    foreach (explode("\n", $text) as $line) {
        $line = pe_payment_pdf_clean_line($line);
        if ($line === '') {
            continue;
        }

        if (!preg_match(
            '/^(\d{6})\s+(\d{8,11})\s+(.+?)\s+R\$\s*([\d.]+,\d{2})\s+(\d{1,6})\s+(\d{1,14})\s+(\d{1,4})\s+(.+?)\s+(\d{2}\/\d{2}\/\d{4})(?:\s+(.*))?$/u',
            $line,
            $m
        )) {
            continue;
        }

        $ident = $m[1];
        if (isset($seenIdent[$ident])) {
            continue;
        }
        $seenIdent[$ident] = true;

        $cpfOriginal = pe_digits($m[2]);
        $cpf = pe_normalize_cpf($cpfOriginal, true);
        $cpfValid = strlen($cpf) === 11 && pe_validate_cpf($cpf);
        $value = pe_payment_pdf_money_to_float($m[4]);
        $date = pe_payment_pdf_br_date($m[9]);

        $rows[] = [
            'n_ident' => $ident,
            'cpf_informado' => $cpfOriginal,
            'cpf' => $cpfValid ? $cpf : null,
            'cpf_valido' => $cpfValid,
            'nome' => trim($m[3]),
            'valor' => $value,
            'agencia' => trim($m[5]),
            'conta' => trim($m[6]),
            'variacao' => trim($m[7]),
            'situacao' => trim($m[8]),
            'data_situacao' => $date,
            'observacao' => isset($m[10]) ? pe_nullable($m[10]) : null,
        ];
    }

    if (!$rows) {
        throw new RuntimeException('Nenhuma linha de pagamento foi reconhecida no PDF. Verifique se o arquivo possui texto pesquisável.');
    }

    $reportedTotal = $totalPagamentosRaw !== null ? (int) $totalPagamentosRaw : null;
    if ($reportedTotal !== null && $reportedTotal > 0 && count($rows) !== $reportedTotal) {
        throw new RuntimeException('A extração do PDF ficou incompleta: o documento informa ' . $reportedTotal . ' pagamentos, mas foram reconhecidos ' . count($rows) . '. Nenhuma atualização foi permitida.');
    }

    $sum = round(array_sum(array_column($rows, 'valor')), 2);
    $reportedValue = $valorTotalRaw !== null ? pe_payment_pdf_money_to_float($valorTotalRaw) : null;
    if ($reportedValue !== null && abs($sum - $reportedValue) > 0.01) {
        throw new RuntimeException('A soma extraída do PDF (' . number_format($sum, 2, ',', '.') . ') não confere com o valor total informado (' . number_format($reportedValue, 2, ',', '.') . '). Nenhuma atualização foi permitida.');
    }

    $paymentDate = pe_payment_pdf_br_date($dataPagamentoBr);

    return [
        'meta' => [
            'banco' => 'Banco do Brasil',
            'convenio_numero' => $convenioNumero,
            'convenio_nome' => $convenioNome,
            'lista_numero' => $listaNumero,
            'lista_nome' => $listaNome,
            'estado_lista' => $estadoLista,
            'data_pagamento' => $paymentDate,
            'forma_pagamento' => $formaPagamento,
            'total_pagamentos' => $reportedTotal ?: count($rows),
            'valor_total' => $reportedValue ?? $sum,
            'competencia_padrao' => $paymentDate ? substr($paymentDate, 0, 7) : null,
        ],
        'rows' => $rows,
    ];
}

function pe_payment_pdf_normalize_name(string $name): string
{
    $name = mb_strtoupper(trim($name), 'UTF-8');
    $name = strtr($name, [
        'Á'=>'A','À'=>'A','Â'=>'A','Ã'=>'A','Ä'=>'A','É'=>'E','È'=>'E','Ê'=>'E','Ë'=>'E',
        'Í'=>'I','Ì'=>'I','Î'=>'I','Ï'=>'I','Ó'=>'O','Ò'=>'O','Ô'=>'O','Õ'=>'O','Ö'=>'O',
        'Ú'=>'U','Ù'=>'U','Û'=>'U','Ü'=>'U','Ç'=>'C',
    ]);
    $name = preg_replace('/[^A-Z0-9 ]+/', ' ', $name) ?: '';
    return trim(preg_replace('/\s+/', ' ', $name) ?: '');
}

function pe_payment_pdf_in_query(PDO $pdo, string $sqlPrefix, array $ids, array $params = []): array
{
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn(int $v): bool => $v > 0)));
    if (!$ids) {
        return [];
    }

    $out = [];
    foreach (array_chunk($ids, 500) as $chunk) {
        $placeholders = [];
        $local = $params;
        foreach ($chunk as $index => $id) {
            $key = 'id_' . $index;
            $placeholders[] = ':' . $key;
            $local[$key] = $id;
        }
        $stmt = $pdo->prepare($sqlPrefix . '(' . implode(',', $placeholders) . ')');
        $stmt->execute($local);
        $out = array_merge($out, $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
    }
    return $out;
}

function pe_payment_pdf_financial_state(?array $grant, float $officialValue, ?string $officialDate): array
{
    if ($grant === null) {
        return ['state' => 'novo_pagamento', 'message' => 'Pagamento ainda não registrado no SIGAS.'];
    }

    $existingValue = round((float) ($grant['valor'] ?? 0), 2);
    $existingStatus = pe_payment_pdf_normalize_name((string) ($grant['status'] ?? ''));
    $existingDate = pe_nullable($grant['data_pagamento'] ?? null);

    if ($existingValue > 0 && abs($existingValue - $officialValue) > 0.01) {
        return ['state' => 'conflito_financeiro', 'message' => 'Já existe bolsa na competência com valor diferente: R$ ' . number_format($existingValue, 2, ',', '.') . '.'];
    }
    if (str_contains($existingStatus, 'CANCEL') || str_contains($existingStatus, 'ESTORN')) {
        return ['state' => 'conflito_financeiro', 'message' => 'A bolsa existente possui status incompatível: ' . (string) ($grant['status'] ?? '') . '.'];
    }
    if ($existingStatus === 'PAGO' && $existingDate && $officialDate && $existingDate !== $officialDate) {
        return ['state' => 'conflito_financeiro', 'message' => 'Já existe pagamento marcado como Pago em outra data (' . date('d/m/Y', strtotime($existingDate)) . ').'];
    }
    if ($existingStatus === 'PAGO' && abs($existingValue - $officialValue) <= 0.01) {
        if (!$officialDate || ($existingDate && $existingDate === $officialDate)) {
            return ['state' => 'ja_conciliado', 'message' => 'Bolsa já registrada como paga nesta competência.'];
        }
        // Se o pagamento já está como Pago mas não possui data, o extrato oficial completa a informação.
        if (!$existingDate && $officialDate) {
            return ['state' => 'atualizar_pagamento', 'message' => 'Bolsa já marcada como paga; a data oficial será preenchida pelo extrato.'];
        }
    }

    return ['state' => 'atualizar_pagamento', 'message' => 'Bolsa existente será conciliada com o pagamento oficial.'];
}

function pe_payment_pdf_analyze(PDO $pdo, array $parsed, string $competence): array
{
    if (!preg_match('/^\d{4}-\d{2}$/', $competence)) {
        throw new InvalidArgumentException('Informe uma competência válida no formato AAAA-MM.');
    }

    $validCpfs = [];
    foreach ($parsed['rows'] as $row) {
        if (!empty($row['cpf'])) {
            $validCpfs[(string) $row['cpf']] = true;
        }
    }

    $candidateMap = [];
    $cpfList = array_keys($validCpfs);
    foreach (array_chunk($cpfList, 500) as $chunkIndex => $chunk) {
        if (!$chunk) {
            continue;
        }
        $placeholders = [];
        $params = [];
        foreach ($chunk as $index => $cpf) {
            $key = 'cpf_' . $chunkIndex . '_' . $index;
            $placeholders[] = ':' . $key;
            $params[$key] = $cpf;
        }

        $stmt = $pdo->prepare(
            'SELECT id, nome, cpf, cpf_informado, status, revisao_status, lista_final_ativa
               FROM pe_candidatos
              WHERE cpf IN (' . implode(',', $placeholders) . ')
              ORDER BY lista_final_ativa DESC, id'
        );
        $stmt->execute($params);

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $candidate) {
            $candidateMap[(string) $candidate['cpf']][] = $candidate;
        }
    }

    // O nome é usado somente para reaproveitar um cadastro local quando não há
    // correspondência por CPF. A lista oficial continua sendo a fonte do CPF.
    $nameMap = [];
    $allCandidates = $pdo->query(
        'SELECT id, nome, cpf, cpf_informado, status, revisao_status, lista_final_ativa
           FROM pe_candidatos
          ORDER BY lista_final_ativa DESC, id'
    )->fetchAll(PDO::FETCH_ASSOC) ?: [];

    foreach ($allCandidates as $candidate) {
        $key = pe_payment_pdf_normalize_name((string) $candidate['nome']);
        if ($key !== '') {
            $nameMap[$key][] = $candidate;
        }
    }

    $resolvedRows = [];
    $usedCandidateIds = [];
    $resolvedCandidateIds = [];

    foreach ($parsed['rows'] as $row) {
        $item = $row + [
            'candidate_id' => null,
            'candidate_name' => null,
            'candidate_status' => null,
            'membership_action' => null,
            'membership_message' => null,
            'name_divergence' => false,
            'ambiguous_candidates' => [],
            'grant_id' => null,
            'match_status' => null,
            'match_message' => null,
        ];

        if (empty($row['cpf'])) {
            $item['membership_action'] = 'cpf_invalido';
            $item['membership_message'] = 'CPF oficial não passou na validação.';
            $item['match_status'] = 'cpf_invalido';
            $item['match_message'] = $item['membership_message'];
            $resolvedRows[] = $item;
            continue;
        }

        $matches = $candidateMap[$row['cpf']] ?? [];
        $candidate = null;

        if (count($matches) === 1) {
            $candidate = $matches[0];
        } elseif (count($matches) > 1) {
            $officialName = pe_payment_pdf_normalize_name((string) $row['nome']);
            $sameName = array_values(array_filter(
                $matches,
                static fn(array $c): bool =>
                    pe_payment_pdf_normalize_name((string) $c['nome']) === $officialName
            ));

            if (count($sameName) === 1) {
                $candidate = $sameName[0];
                $item['ambiguous_candidates'] = array_map(
                    static fn(array $c): array => [
                        'id' => (int) $c['id'],
                        'nome' => (string) $c['nome'],
                        'status' => (string) $c['status'],
                    ],
                    array_values(array_filter(
                        $matches,
                        static fn(array $c): bool => (int) $c['id'] !== (int) $sameName[0]['id']
                    ))
                );
            } else {
                $item['membership_action'] = 'cpf_ambiguo';
                $item['membership_message'] = 'Há mais de um cadastro local com o CPF oficial e não foi possível definir o cadastro principal com segurança.';
                $item['match_status'] = 'cpf_ambiguo';
                $item['match_message'] = $item['membership_message'];
                $item['ambiguous_candidates'] = array_map(
                    static fn(array $c): array => [
                        'id' => (int) $c['id'],
                        'nome' => (string) $c['nome'],
                        'status' => (string) $c['status'],
                    ],
                    $matches
                );
                $resolvedRows[] = $item;
                continue;
            }
        }

        if ($candidate !== null) {
            $candidateId = (int) $candidate['id'];
            $usedCandidateIds[$candidateId] = true;
            $resolvedCandidateIds[$candidateId] = true;
            $item['candidate_id'] = $candidateId;
            $item['candidate_name'] = (string) $candidate['nome'];
            $item['candidate_status'] = (string) $candidate['status'];
            $item['membership_action'] = 'usar_existente';
            $item['membership_message'] = !empty($candidate['lista_final_ativa'])
                ? 'Cadastro localizado pelo CPF oficial.'
                : 'Cadastro anteriormente fora da lista final será reativado pelo CPF oficial.';
            $item['name_divergence'] =
                pe_payment_pdf_normalize_name((string) $candidate['nome'])
                !== pe_payment_pdf_normalize_name((string) $row['nome']);
            $resolvedRows[] = $item;
            continue;
        }

        $nameKey = pe_payment_pdf_normalize_name((string) $row['nome']);
        $nameMatches = array_values(array_filter(
            $nameMap[$nameKey] ?? [],
            static fn(array $c): bool => !isset($usedCandidateIds[(int) $c['id']])
        ));

        if (count($nameMatches) === 1) {
            $candidate = $nameMatches[0];
            $candidateId = (int) $candidate['id'];
            $usedCandidateIds[$candidateId] = true;
            $resolvedCandidateIds[$candidateId] = true;
            $item['candidate_id'] = $candidateId;
            $item['candidate_name'] = (string) $candidate['nome'];
            $item['candidate_status'] = (string) $candidate['status'];
            $item['membership_action'] = 'recuperar_cadastro';
            $item['membership_message'] = 'CPF não batia no SIGAS, mas o nome oficial encontrou um único cadastro. O CPF será corrigido pela lista oficial.';
            $resolvedRows[] = $item;
            continue;
        }

        // Sem vínculo local seguro: a lista oficial cria o cadastro canônico.
        $item['membership_action'] = 'criar_candidato_banco';
        $item['membership_message'] = count($nameMatches) > 1
            ? 'Há mais de um cadastro com o mesmo nome. Um novo cadastro oficial será criado e os antigos ficarão fora da lista final.'
            : 'Não existe cadastro local correspondente. Um novo candidato oficial será criado com nome e CPF do Banco do Brasil.';
        $resolvedRows[] = $item;
    }

    $grantCandidateIds = array_keys($resolvedCandidateIds);
    $grants = [];
    foreach (pe_payment_pdf_in_query(
        $pdo,
        'SELECT id, candidato_id, competencia, valor, status, data_pagamento, observacao
           FROM pe_bolsas
          WHERE competencia = :competencia AND candidato_id IN ',
        $grantCandidateIds,
        ['competencia' => $competence]
    ) as $grant) {
        $grants[(int) $grant['candidato_id']] = $grant;
    }

    $summary = [
        'total' => count($parsed['rows']),
        'cpf_validos' => 0,
        'candidatos_existentes' => 0,
        'candidatos_recuperar' => 0,
        'candidatos_criar' => 0,
        'candidatos_excluir' => 0,
        'ativos_apos_sincronizacao' => 0,
        'prontos' => 0,
        'atualizar_pagamento' => 0,
        'ja_conciliados' => 0,
        'nao_localizados' => 0,
        'ambiguos' => 0,
        'cpf_invalidos' => 0,
        'divergencias_nome' => 0,
        'conflitos_financeiros' => 0,
        'valor_total' => round(array_sum(array_column($parsed['rows'], 'valor')), 2),
    ];

    $rows = [];
    $officialResolvedIds = [];
    foreach ($resolvedRows as $item) {
        $action = (string) $item['membership_action'];

        if ($action === 'cpf_invalido') {
            $summary['cpf_invalidos']++;
            $rows[] = $item;
            continue;
        }

        $summary['cpf_validos']++;

        if ($action === 'cpf_ambiguo') {
            $summary['ambiguos']++;
            $rows[] = $item;
            continue;
        }

        if ($action === 'usar_existente') {
            $summary['candidatos_existentes']++;
            $officialResolvedIds[(int) $item['candidate_id']] = true;
        } elseif ($action === 'recuperar_cadastro') {
            $summary['candidatos_recuperar']++;
            $officialResolvedIds[(int) $item['candidate_id']] = true;
        } elseif ($action === 'criar_candidato_banco') {
            $summary['candidatos_criar']++;
        }

        if (!empty($item['name_divergence'])) {
            $summary['divergencias_nome']++;
        }

        if ($action === 'criar_candidato_banco') {
            $item['match_status'] = 'novo_pagamento';
            $item['match_message'] = $item['membership_message'] . ' O pagamento oficial será registrado após a criação.';
            $summary['prontos']++;
            $rows[] = $item;
            continue;
        }

        $candidateId = (int) $item['candidate_id'];
        $grant = $grants[$candidateId] ?? null;
        $finance = pe_payment_pdf_financial_state(
            $grant,
            (float) $item['valor'],
            $item['data_situacao'] ?: ($parsed['meta']['data_pagamento'] ?? null)
        );

        $item['grant_id'] = $grant ? (int) $grant['id'] : null;
        $item['match_status'] = $finance['state'];
        $item['match_message'] = trim($item['membership_message'] . ' ' . $finance['message']);

        if ($finance['state'] === 'novo_pagamento') {
            $summary['prontos']++;
        } elseif ($finance['state'] === 'atualizar_pagamento') {
            $summary['atualizar_pagamento']++;
        } elseif ($finance['state'] === 'ja_conciliado') {
            $summary['ja_conciliados']++;
        } elseif ($finance['state'] === 'conflito_financeiro') {
            $summary['conflitos_financeiros']++;
        }

        $rows[] = $item;
    }

    // A sincronização final só exclui registros atualmente ativos que não estão
    // ligados a nenhuma pessoa da lista oficial. Cadastros já excluídos não são
    // contados novamente.
    $activeIds = $pdo->query(
        'SELECT id FROM pe_candidatos WHERE lista_final_ativa = 1 ORDER BY id'
    )->fetchAll(PDO::FETCH_COLUMN) ?: [];

    $excludeCount = 0;
    foreach ($activeIds as $candidateId) {
        if (!isset($officialResolvedIds[(int) $candidateId])) {
            $excludeCount++;
        }
    }

    $summary['candidatos_excluir'] = $excludeCount;
    $summary['ativos_apos_sincronizacao'] =
        $summary['candidatos_existentes']
        + $summary['candidatos_recuperar']
        + $summary['candidatos_criar'];

    return [
        'meta' => $parsed['meta'] + ['competencia' => $competence],
        'summary' => $summary,
        'rows' => $rows,
    ];
}

function pe_payment_pdf_log_item(PDO $pdo, int $importId, array $row, string $status, ?string $message, ?int $candidateId, ?string $appliedAt = null): void
{
    $stmt = $pdo->prepare('INSERT INTO pe_pagamento_importacao_itens
        (importacao_id, n_ident, candidato_id, cpf_informado, cpf_validado, nome_banco, valor, agencia, conta, variacao,
         situacao, data_situacao, observacao, conciliacao_status, mensagem, aplicado_em)
        VALUES
        (:importacao_id, :n_ident, :candidato_id, :cpf_informado, :cpf_validado, :nome_banco, :valor, :agencia, :conta, :variacao,
         :situacao, :data_situacao, :observacao, :conciliacao_status, :mensagem, :aplicado_em)');
    $stmt->execute([
        'importacao_id' => $importId,
        'n_ident' => $row['n_ident'],
        'candidato_id' => $candidateId,
        'cpf_informado' => $row['cpf_informado'],
        'cpf_validado' => $row['cpf'],
        'nome_banco' => $row['nome'],
        'valor' => $row['valor'],
        'agencia' => $row['agencia'],
        'conta' => $row['conta'],
        'variacao' => $row['variacao'],
        'situacao' => $row['situacao'],
        'data_situacao' => $row['data_situacao'],
        'observacao' => $row['observacao'],
        'conciliacao_status' => $status,
        'mensagem' => $message,
        'aplicado_em' => $appliedAt,
    ]);
}

function pe_payment_pdf_append_observation(?string $current, string $marker): string
{
    $current = trim((string) $current);
    if ($current === '') {
        return mb_substr($marker, 0, 500, 'UTF-8');
    }
    if (str_contains($current, $marker)) {
        return mb_substr($current, 0, 500, 'UTF-8');
    }
    return mb_substr($current . ' | ' . $marker, 0, 500, 'UTF-8');
}

function pe_payment_pdf_apply(PDO $pdo, array $parsed, string $competence, array $fileMeta, ?string $responsavel, string $source): array
{
    if (!preg_match('/^\d{4}-\d{2}$/', $competence)) {
        throw new InvalidArgumentException('Competência inválida.');
    }
    if (pe_payment_pdf_normalize_name((string) ($parsed['meta']['estado_lista'] ?? '')) !== 'PAGA') {
        throw new RuntimeException('A lista do Banco do Brasil não está marcada como PAGA. A sincronização foi bloqueada.');
    }

    $fileHash = pe_nullable($fileMeta['hash'] ?? null);
    if ($fileHash) {
        $same = $pdo->prepare(
            'SELECT id, criado_em
               FROM pe_pagamento_importacoes
              WHERE arquivo_hash=:hash AND status="Concluída"
              ORDER BY id DESC
              LIMIT 1'
        );
        $same->execute(['hash' => $fileHash]);
        if ($previous = $same->fetch(PDO::FETCH_ASSOC)) {
            throw new RuntimeException(
                'Este mesmo PDF já foi sincronizado na importação #'
                . (int) $previous['id']
                . '. A duplicação foi bloqueada.'
            );
        }
    }

    $analysis = pe_payment_pdf_analyze($pdo, $parsed, $competence);
    $meta = $parsed['meta'];
    $summary = $analysis['summary'];

    if ((int) $summary['cpf_invalidos'] > 0 || (int) $summary['ambiguos'] > 0) {
        throw new RuntimeException(
            'A lista oficial não pode ser aplicada enquanto houver CPF inválido ou CPF ambíguo na base local. '
            . 'Foram encontrados ' . (int) $summary['cpf_invalidos'] . ' CPF(s) inválido(s) e '
            . (int) $summary['ambiguos'] . ' CPF(s) ambíguo(s).'
        );
    }

    $counters = [
        'conciliados' => 0,
        'atualizados' => 0,
        'candidatos_criados' => 0,
        'candidatos_recuperados' => 0,
        'candidatos_excluidos' => 0,
        'ja_conciliados' => 0,
        'nao_localizados' => 0,
        'ambiguos' => 0,
        'cpf_invalidos' => 0,
        'divergencias_nome' => (int) $summary['divergencias_nome'],
        'conflitos_financeiros' => 0,
        'erros' => 0,
    ];

    $pdo->beginTransaction();
    try {
        $header = $pdo->prepare(
            'INSERT INTO pe_pagamento_importacoes
                (arquivo_nome, arquivo_hash, banco, convenio_numero, convenio_nome, lista_numero, lista_nome, estado_lista,
                 data_pagamento, forma_pagamento, competencia, total_pagamentos, valor_total, fonte_extracao, responsavel, status)
             VALUES
                (:arquivo_nome, :arquivo_hash, :banco, :convenio_numero, :convenio_nome, :lista_numero, :lista_nome, :estado_lista,
                 :data_pagamento, :forma_pagamento, :competencia, :total_pagamentos, :valor_total, :fonte_extracao, :responsavel, "Processando")'
        );
        $header->execute([
            'arquivo_nome' => $fileMeta['name'],
            'arquivo_hash' => $fileHash,
            'banco' => $meta['banco'] ?? 'Banco do Brasil',
            'convenio_numero' => $meta['convenio_numero'],
            'convenio_nome' => $meta['convenio_nome'],
            'lista_numero' => $meta['lista_numero'],
            'lista_nome' => $meta['lista_nome'],
            'estado_lista' => $meta['estado_lista'],
            'data_pagamento' => $meta['data_pagamento'],
            'forma_pagamento' => $meta['forma_pagamento'],
            'competencia' => $competence,
            'total_pagamentos' => count($parsed['rows']),
            'valor_total' => $meta['valor_total'],
            'fonte_extracao' => $source,
            'responsavel' => pe_nullable($responsavel),
        ]);
        $importId = (int) $pdo->lastInsertId();

        $activateCandidate = $pdo->prepare(
            'UPDATE pe_candidatos SET
                status="Contemplado",
                lista_final_ativa=1,
                lista_final_origem="Banco do Brasil",
                lista_final_importacao_id=:importacao_id,
                lista_final_sincronizada_em=CURRENT_TIMESTAMP,
                lista_final_excluido_em=NULL,
                lista_final_exclusao_motivo=NULL,
                updated_at=CURRENT_TIMESTAMP
             WHERE id=:id'
        );

        $recoverCandidate = $pdo->prepare(
            'UPDATE pe_candidatos SET
                cpf=:cpf,
                cpf_informado=:cpf_informado,
                cpf_revisado_confirmado=1,
                revisao_cpf=0,
                status="Contemplado",
                lista_final_ativa=1,
                lista_final_origem="Banco do Brasil",
                lista_final_importacao_id=:importacao_id,
                lista_final_sincronizada_em=CURRENT_TIMESTAMP,
                lista_final_excluido_em=NULL,
                lista_final_exclusao_motivo=NULL,
                updated_at=CURRENT_TIMESTAMP
             WHERE id=:id'
        );

        $createCandidate = $pdo->prepare(
            'INSERT INTO pe_candidatos
                (nome, cpf, cpf_informado, status,
                 revisao_status, revisao_cpf, revisao_telefone, revisao_nascimento, cpf_duplicado,
                 cpf_revisado_confirmado, revisao_motivos, revisao_atualizada_em,
                 origem, chave_importacao,
                 lista_final_ativa, lista_final_origem, lista_final_importacao_id, lista_final_sincronizada_em)
             VALUES
                (:nome, :cpf, :cpf_informado, "Contemplado",
                 "Revisar Cadastro", 0, 1, 1, 0,
                 1, :revisao_motivos, CURRENT_TIMESTAMP,
                 "banco_bb", :chave_importacao,
                 1, "Banco do Brasil", :importacao_id, CURRENT_TIMESTAMP)'
        );

        $insertGrant = $pdo->prepare(
            'INSERT INTO pe_bolsas
                (candidato_id, competencia, valor, status, data_pagamento, observacao, registrado_por)
             VALUES
                (:candidato_id, :competencia, :valor, "Pago", :data_pagamento, :observacao, :responsavel)'
        );

        $updateGrant = $pdo->prepare(
            'UPDATE pe_bolsas SET
                valor=:valor,
                status="Pago",
                data_pagamento=:data_pagamento,
                observacao=:observacao,
                registrado_por=:responsavel,
                updated_at=CURRENT_TIMESTAMP
             WHERE id=:id'
        );

        $includedIds = [];

        foreach ($analysis['rows'] as $row) {
            $membershipAction = (string) ($row['membership_action'] ?? '');
            if (in_array($membershipAction, ['cpf_invalido', 'cpf_ambiguo'], true)) {
                throw new RuntimeException(
                    'A sincronização encontrou uma pendência inesperada no N IDENT. '
                    . (string) $row['n_ident']
                    . ': ' . (string) ($row['membership_message'] ?? 'pendência cadastral')
                );
            }

            $candidateId = isset($row['candidate_id']) && $row['candidate_id']
                ? (int) $row['candidate_id']
                : 0;

            if ($membershipAction === 'usar_existente') {
                if ($candidateId <= 0) {
                    throw new RuntimeException('Cadastro existente sem ID no N IDENT. ' . (string) $row['n_ident'] . '.');
                }
                $activateCandidate->execute([
                    'importacao_id' => $importId,
                    'id' => $candidateId,
                ]);
            } elseif ($membershipAction === 'recuperar_cadastro') {
                if ($candidateId <= 0 || empty($row['cpf'])) {
                    throw new RuntimeException('Cadastro a recuperar sem ID/CPF no N IDENT. ' . (string) $row['n_ident'] . '.');
                }
                $recoverCandidate->execute([
                    'cpf' => $row['cpf'],
                    'cpf_informado' => $row['cpf'],
                    'importacao_id' => $importId,
                    'id' => $candidateId,
                ]);
                $counters['candidatos_recuperados']++;
            } elseif ($membershipAction === 'criar_candidato_banco') {
                if (empty($row['cpf'])) {
                    throw new RuntimeException('Não foi possível criar o candidato do N IDENT. ' . (string) $row['n_ident'] . ' sem CPF válido.');
                }

                $createCandidate->execute([
                    'nome' => mb_substr(trim((string) $row['nome']), 0, 160, 'UTF-8'),
                    'cpf' => $row['cpf'],
                    'cpf_informado' => $row['cpf'],
                    'revisao_motivos' => 'Cadastro criado pela lista oficial do Banco do Brasil. Complementar telefone e data de nascimento.',
                    'chave_importacao' => hash(
                        'sha256',
                        'bb-final|'
                        . (string) ($meta['convenio_numero'] ?? '')
                        . '|'
                        . (string) ($meta['lista_numero'] ?? '')
                        . '|'
                        . (string) $row['cpf']
                    ),
                    'importacao_id' => $importId,
                ]);
                $candidateId = (int) $pdo->lastInsertId();
                $counters['candidatos_criados']++;
            } else {
                throw new RuntimeException(
                    'Ação de vínculo oficial não reconhecida no N IDENT. '
                    . (string) $row['n_ident']
                    . ': ' . $membershipAction
                );
            }

            if ($candidateId <= 0) {
                throw new RuntimeException('Não foi possível determinar o candidato do N IDENT. ' . (string) $row['n_ident'] . '.');
            }
            $includedIds[$candidateId] = true;

            $officialDate = $row['data_situacao'] ?: ($meta['data_pagamento'] ?? null);
            $marker =
                'Pagamento BB convênio ' . ($meta['convenio_numero'] ?: '—')
                . ', lista ' . ($meta['lista_numero'] ?: '—')
                . ', N IDENT ' . $row['n_ident'];

            $state = (string) ($row['match_status'] ?? 'novo_pagamento');
            $logStatus = 'Lista oficial';
            $message = trim((string) ($row['match_message'] ?? ''));
            $appliedAt = date('Y-m-d H:i:s');

            // Para cadastro criado no mesmo processamento, a bolsa é sempre nova.
            if ($membershipAction === 'criar_candidato_banco') {
                $state = 'novo_pagamento';
            }

            if ($state === 'novo_pagamento') {
                $insertGrant->execute([
                    'candidato_id' => $candidateId,
                    'competencia' => $competence,
                    'valor' => $row['valor'],
                    'data_pagamento' => $officialDate,
                    'observacao' => mb_substr($marker, 0, 500, 'UTF-8'),
                    'responsavel' => pe_nullable($responsavel),
                ]);
                $counters['conciliados']++;
                $logStatus = $membershipAction === 'criar_candidato_banco'
                    ? 'Cadastro criado + Pago'
                    : ($membershipAction === 'recuperar_cadastro' ? 'Cadastro recuperado + Pago' : 'Aplicado');
            } elseif ($state === 'atualizar_pagamento') {
                $grant = pe_grant_by_id($pdo, (int) $row['grant_id']);
                if (!$grant) {
                    throw new RuntimeException('Bolsa existente não foi encontrada durante a sincronização.');
                }
                $updateGrant->execute([
                    'valor' => $row['valor'],
                    'data_pagamento' => $officialDate,
                    'observacao' => pe_payment_pdf_append_observation($grant['observacao'] ?? null, $marker),
                    'responsavel' => pe_nullable($responsavel),
                    'id' => (int) $grant['id'],
                ]);
                $counters['atualizados']++;
                $logStatus = $membershipAction === 'recuperar_cadastro'
                    ? 'Cadastro recuperado + Atualizado'
                    : 'Atualizado';
            } elseif ($state === 'ja_conciliado') {
                $counters['ja_conciliados']++;
                $logStatus = $membershipAction === 'recuperar_cadastro'
                    ? 'Cadastro recuperado + Já pago'
                    : 'Já conciliado';
            } elseif ($state === 'conflito_financeiro') {
                // A lista oficial define quem pertence à base, mas um conflito financeiro
                // continua protegido: o candidato entra na lista final e o pagamento fica
                // registrado para conferência, sem sobrescrever um dado financeiro conflitante.
                $counters['conflitos_financeiros']++;
                $logStatus = 'Lista oficial + Conflito';
                $appliedAt = null;
            } else {
                throw new RuntimeException('Estado financeiro não reconhecido: ' . $state);
            }

            if (!empty($row['name_divergence'])) {
                $message = trim(
                    $message
                    . ' Divergência de nome: Banco “'
                    . (string) $row['nome']
                    . '” x SIGAS “'
                    . (string) $row['candidate_name']
                    . '”. O nome local foi preservado.'
                );
            }

            pe_payment_pdf_log_item(
                $pdo,
                $importId,
                $row,
                $logStatus,
                pe_nullable($message),
                $candidateId,
                $appliedAt
            );
        }

        if (count($includedIds) !== count($parsed['rows'])) {
            throw new RuntimeException(
                'A sincronização oficial seria incompleta: '
                . count($includedIds)
                . ' candidato(s) resolvido(s) para '
                . count($parsed['rows'])
                . ' registro(s) do Banco. Nenhuma alteração foi confirmada.'
            );
        }

        $params = [
            'importacao_id' => $importId,
            'motivo' => 'Não consta na lista oficial do Banco do Brasil - convênio '
                . (string) ($meta['convenio_numero'] ?? '—')
                . ', lista '
                . (string) ($meta['lista_numero'] ?? '—')
                . '.',
        ];
        $placeholders = [];
        foreach (array_keys($includedIds) as $index => $id) {
            $key = 'incluido_' . $index;
            $placeholders[] = ':' . $key;
            $params[$key] = (int) $id;
        }

        $excludeSql =
            'UPDATE pe_candidatos SET
                lista_final_ativa=0,
                lista_final_importacao_id=:importacao_id,
                lista_final_excluido_em=CURRENT_TIMESTAMP,
                lista_final_exclusao_motivo=:motivo,
                updated_at=CURRENT_TIMESTAMP
             WHERE lista_final_ativa=1
               AND id NOT IN (' . implode(',', $placeholders) . ')';

        $exclude = $pdo->prepare($excludeSql);
        $exclude->execute($params);
        $counters['candidatos_excluidos'] = $exclude->rowCount();

        // Se houver a tabela nova de lotações, encerra somente lotações ativas
        // dos candidatos retirados da lista final. O histórico não é apagado.
        try {
            if ($pdo->query("SHOW TABLES LIKE 'pe_lotacoes'")->fetchColumn()) {
                $endPlacements = $pdo->prepare(
                    'UPDATE pe_lotacoes l
                      INNER JOIN pe_candidatos c ON c.id=l.candidato_id
                       SET l.status="Encerrada",
                           l.data_fim=COALESCE(l.data_fim, :data_fim),
                           l.candidato_ativo_id=NULL,
                           l.observacao=CASE
                               WHEN l.observacao IS NULL OR TRIM(l.observacao)="" THEN :observacao
                               WHEN l.observacao LIKE :observacao_like THEN l.observacao
                               ELSE CONCAT(LEFT(l.observacao, 360), " | ", :observacao)
                           END,
                           l.updated_at=CURRENT_TIMESTAMP
                     WHERE c.lista_final_ativa=0
                       AND c.lista_final_importacao_id=:importacao_id
                       AND l.status="Ativa"'
                );
                $placementNote = 'Encerrada automaticamente: candidato fora da lista oficial do Banco do Brasil.';
                $endPlacements->execute([
                    'data_fim' => $meta['data_pagamento'] ?: date('Y-m-d'),
                    'observacao' => $placementNote,
                    'observacao_like' => '%' . $placementNote . '%',
                    'importacao_id' => $importId,
                ]);
            }
        } catch (Throwable) {
            // A exclusão da base oficial não depende da tabela de lotações.
        }

        // Recalcula as pendências depois de retirar duplicidades que ficaram fora
        // da lista final. Assim um CPF oficial não permanece marcado como duplicado
        // apenas por existir em um cadastro histórico excluído.
        foreach (array_keys($includedIds) as $candidateId) {
            pe_recalculate_review($pdo, (int) $candidateId);
        }

        $finish = $pdo->prepare(
            'UPDATE pe_pagamento_importacoes SET
                conciliados=:conciliados,
                atualizados=:atualizados,
                candidatos_criados=:candidatos_criados,
                candidatos_recuperados=:candidatos_recuperados,
                candidatos_excluidos=:candidatos_excluidos,
                ja_conciliados=:ja_conciliados,
                nao_localizados=:nao_localizados,
                ambiguos=:ambiguos,
                cpf_invalidos=:cpf_invalidos,
                divergencias_nome=:divergencias_nome,
                conflitos_financeiros=:conflitos_financeiros,
                erros=:erros,
                status="Concluída",
                finalizada_em=CURRENT_TIMESTAMP
             WHERE id=:id'
        );
        $finish->execute($counters + ['id' => $importId]);

        $pdo->commit();

        return $counters + [
            'import_id' => $importId,
            'errors_list' => [],
            'total' => count($parsed['rows']),
            'valor_total' => $meta['valor_total'],
            'ativos_lista_final' => count($includedIds),
        ];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function pe_payment_pdf_history(PDO $pdo, int $limit = 8): array
{
    $limit = max(1, min($limit, 50));
    return $pdo->query('SELECT id, arquivo_nome, convenio_numero, lista_numero, competencia, total_pagamentos, valor_total,
        conciliados, atualizados, candidatos_criados, candidatos_recuperados, candidatos_excluidos,
        ja_conciliados, nao_localizados, ambiguos, cpf_invalidos,
        conflitos_financeiros, erros, responsavel, status, criado_em, finalizada_em
        FROM pe_pagamento_importacoes ORDER BY id DESC LIMIT ' . $limit)->fetchAll(PDO::FETCH_ASSOC) ?: [];
}
