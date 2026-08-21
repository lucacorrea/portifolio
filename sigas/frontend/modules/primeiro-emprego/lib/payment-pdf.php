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
        $pdo->query('SELECT arquivo_hash, convenio_numero, lista_numero, competencia, conciliados, conflitos_financeiros FROM pe_pagamento_importacoes LIMIT 1');
        $pdo->query('SELECT importacao_id, conciliacao_status, cpf_validado, valor FROM pe_pagamento_importacao_itens LIMIT 1');
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

function pe_payment_pdf_try_server_text(string $path): ?string
{
    if (!function_exists('proc_open')) {
        return null;
    }

    $disabled = array_filter(array_map('trim', explode(',', (string) ini_get('disable_functions'))));
    if (in_array('proc_open', $disabled, true)) {
        return null;
    }

    $binary = null;
    foreach (['/usr/bin/pdftotext', '/usr/local/bin/pdftotext'] as $candidate) {
        if (is_file($candidate) && is_executable($candidate)) {
            $binary = $candidate;
            break;
        }
    }
    if ($binary === null) {
        return null;
    }

    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];

    $process = @proc_open([$binary, '-layout', $path, '-'], $descriptors, $pipes, null, ['LANG' => 'C.UTF-8']);
    if (!is_resource($process)) {
        return null;
    }

    fclose($pipes[0]);
    stream_set_timeout($pipes[1], 15);
    stream_set_timeout($pipes[2], 15);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exit = proc_close($process);

    if ($exit !== 0 || !is_string($stdout) || trim($stdout) === '') {
        return null;
    }

    if (strlen($stdout) > 4 * 1024 * 1024) {
        throw new RuntimeException('O texto extraído do PDF excedeu o limite de segurança.');
    }

    return $stdout;
}

function pe_payment_pdf_extract_text(string $pdfPath, ?string $browserText): array
{
    $serverText = pe_payment_pdf_try_server_text($pdfPath);
    if ($serverText !== null) {
        return ['text' => $serverText, 'source' => 'pdftotext-servidor'];
    }

    $browserText = trim((string) $browserText);
    if ($browserText === '') {
        throw new RuntimeException('O servidor não possui extrator de PDF e o navegador não enviou o texto do documento. Clique em “Analisar PDF” antes de confirmar.');
    }
    if (strlen($browserText) > 4 * 1024 * 1024) {
        throw new InvalidArgumentException('O texto extraído do PDF excede o limite permitido.');
    }

    return ['text' => $browserText, 'source' => 'pdfjs-navegador'];
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
            $validCpfs[$row['cpf']] = true;
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
        $stmt = $pdo->prepare('SELECT id, nome, cpf, cpf_informado, status, revisao_status FROM pe_candidatos WHERE cpf IN (' . implode(',', $placeholders) . ') ORDER BY id');
        $stmt->execute($params);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $candidate) {
            $candidateMap[(string) $candidate['cpf']][] = $candidate;
        }
    }

    // Sugestão por nome é apenas informativa. Nunca vincula automaticamente sem CPF exato.
    $nameMap = [];
    $needSuggestions = count($parsed['rows']) > 0;
    if ($needSuggestions) {
        $all = $pdo->query('SELECT id, nome, cpf, cpf_informado, status FROM pe_candidatos ORDER BY id')->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($all as $candidate) {
            $key = pe_payment_pdf_normalize_name((string) $candidate['nome']);
            if ($key !== '') {
                $nameMap[$key][] = $candidate;
            }
        }
    }

    $matchedIds = [];
    foreach ($candidateMap as $matches) {
        if (count($matches) === 1) {
            $matchedIds[] = (int) $matches[0]['id'];
        }
    }

    $grants = [];
    foreach (pe_payment_pdf_in_query(
        $pdo,
        'SELECT id, candidato_id, competencia, valor, status, data_pagamento, observacao FROM pe_bolsas WHERE competencia = :competencia AND candidato_id IN ',
        $matchedIds,
        ['competencia' => $competence]
    ) as $grant) {
        $grants[(int) $grant['candidato_id']] = $grant;
    }

    $summary = [
        'total' => count($parsed['rows']),
        'cpf_validos' => 0,
        'prontos' => 0,
        'atualizar_pagamento' => 0,
        'ja_conciliados' => 0,
        'nao_localizados' => 0,
        'ambiguos' => 0,
        'cpf_invalidos' => 0,
        'divergencias_nome' => 0,
        'conflitos_financeiros' => 0,
        'sugestoes_nome' => 0,
        'valor_total' => round(array_sum(array_column($parsed['rows'], 'valor')), 2),
    ];

    $rows = [];
    foreach ($parsed['rows'] as $row) {
        $item = $row + [
            'candidate_id' => null,
            'candidate_name' => null,
            'candidate_status' => null,
            'match_status' => null,
            'match_message' => null,
            'name_divergence' => false,
            'suggestion' => null,
            'grant_id' => null,
        ];

        if (empty($row['cpf'])) {
            $summary['cpf_invalidos']++;
            $item['match_status'] = 'cpf_invalido';
            $item['match_message'] = 'CPF do PDF não passou na validação.';
        } else {
            $summary['cpf_validos']++;
            $matches = $candidateMap[$row['cpf']] ?? [];
            if (count($matches) === 0) {
                $summary['nao_localizados']++;
                $item['match_status'] = 'nao_localizado';
                $item['match_message'] = 'CPF não localizado em pe_candidatos.';
                $nameKey = pe_payment_pdf_normalize_name((string) $row['nome']);
                $nameMatches = $nameMap[$nameKey] ?? [];
                if (count($nameMatches) === 1) {
                    $candidate = $nameMatches[0];
                    $item['suggestion'] = [
                        'id' => (int) $candidate['id'],
                        'nome' => (string) $candidate['nome'],
                        'cpf' => $candidate['cpf'] ?: $candidate['cpf_informado'],
                        'status' => (string) $candidate['status'],
                    ];
                    $summary['sugestoes_nome']++;
                }
            } elseif (count($matches) > 1) {
                $summary['ambiguos']++;
                $item['match_status'] = 'cpf_ambiguo';
                $item['match_message'] = 'Mais de um cadastro possui este CPF; requer revisão manual.';
                $item['ambiguous_candidates'] = array_map(static fn(array $c): array => [
                    'id' => (int) $c['id'],
                    'nome' => (string) $c['nome'],
                    'status' => (string) $c['status'],
                ], $matches);
            } else {
                $candidate = $matches[0];
                $candidateId = (int) $candidate['id'];
                $item['candidate_id'] = $candidateId;
                $item['candidate_name'] = (string) $candidate['nome'];
                $item['candidate_status'] = (string) $candidate['status'];
                $item['name_divergence'] = pe_payment_pdf_normalize_name((string) $candidate['nome']) !== pe_payment_pdf_normalize_name((string) $row['nome']);
                if ($item['name_divergence']) {
                    $summary['divergencias_nome']++;
                }

                $grant = $grants[$candidateId] ?? null;
                $finance = pe_payment_pdf_financial_state($grant, (float) $row['valor'], $row['data_situacao'] ?: ($parsed['meta']['data_pagamento'] ?? null));
                $item['grant_id'] = $grant ? (int) $grant['id'] : null;
                $item['match_status'] = $finance['state'];
                $item['match_message'] = $finance['message'];

                if ($finance['state'] === 'novo_pagamento') {
                    $summary['prontos']++;
                } elseif ($finance['state'] === 'atualizar_pagamento') {
                    $summary['atualizar_pagamento']++;
                } elseif ($finance['state'] === 'ja_conciliado') {
                    $summary['ja_conciliados']++;
                } elseif ($finance['state'] === 'conflito_financeiro') {
                    $summary['conflitos_financeiros']++;
                }
            }
        }

        $rows[] = $item;
    }

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
        throw new RuntimeException('A lista do Banco do Brasil não está marcada como PAGA. A conciliação foi bloqueada.');
    }

    $fileHash = pe_nullable($fileMeta['hash'] ?? null);
    if ($fileHash) {
        $same = $pdo->prepare('SELECT id, criado_em FROM pe_pagamento_importacoes WHERE arquivo_hash=:hash AND status="Concluída" ORDER BY id DESC LIMIT 1');
        $same->execute(['hash' => $fileHash]);
        if ($previous = $same->fetch(PDO::FETCH_ASSOC)) {
            throw new RuntimeException('Este mesmo PDF já foi conciliado na importação #' . (int) $previous['id'] . '. A duplicação foi bloqueada.');
        }
    }

    $analysis = pe_payment_pdf_analyze($pdo, $parsed, $competence);
    $meta = $parsed['meta'];
    $summary = $analysis['summary'];

    $counters = [
        'conciliados' => 0,
        'atualizados' => 0,
        'ja_conciliados' => 0,
        'nao_localizados' => 0,
        'ambiguos' => 0,
        'cpf_invalidos' => 0,
        'divergencias_nome' => (int) $summary['divergencias_nome'],
        'conflitos_financeiros' => 0,
        'erros' => 0,
    ];
    $errors = [];

    $pdo->beginTransaction();
    try {
        $header = $pdo->prepare('INSERT INTO pe_pagamento_importacoes
            (arquivo_nome, arquivo_hash, banco, convenio_numero, convenio_nome, lista_numero, lista_nome, estado_lista,
             data_pagamento, forma_pagamento, competencia, total_pagamentos, valor_total, fonte_extracao, responsavel, status)
            VALUES
            (:arquivo_nome, :arquivo_hash, :banco, :convenio_numero, :convenio_nome, :lista_numero, :lista_nome, :estado_lista,
             :data_pagamento, :forma_pagamento, :competencia, :total_pagamentos, :valor_total, :fonte_extracao, :responsavel, "Processando")');
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

        $markContemplado = $pdo->prepare('UPDATE pe_candidatos SET status="Contemplado", updated_at=CURRENT_TIMESTAMP WHERE id=:id');
        $insertGrant = $pdo->prepare('INSERT INTO pe_bolsas
            (candidato_id, competencia, valor, status, data_pagamento, observacao, registrado_por)
            VALUES (:candidato_id, :competencia, :valor, "Pago", :data_pagamento, :observacao, :responsavel)');
        $updateGrant = $pdo->prepare('UPDATE pe_bolsas SET
            valor=:valor, status="Pago", data_pagamento=:data_pagamento, observacao=:observacao,
            registrado_por=:responsavel, updated_at=CURRENT_TIMESTAMP
            WHERE id=:id');

        foreach ($analysis['rows'] as $row) {
            $pdo->exec('SAVEPOINT pe_payment_pdf_row');
            try {
                $state = (string) $row['match_status'];
                $candidateId = $row['candidate_id'] ? (int) $row['candidate_id'] : null;
                $message = (string) ($row['match_message'] ?? '');
                $appliedAt = null;
                $logStatus = '';

                if ($state === 'cpf_invalido') {
                    $counters['cpf_invalidos']++;
                    $logStatus = 'CPF inválido';
                } elseif ($state === 'nao_localizado') {
                    $counters['nao_localizados']++;
                    $logStatus = 'Não localizado';
                    if (!empty($row['suggestion']['id'])) {
                        $message .= ' Sugestão por nome: candidato #' . (int) $row['suggestion']['id'] . ' - ' . (string) $row['suggestion']['nome'] . '.';
                    }
                } elseif ($state === 'cpf_ambiguo') {
                    $counters['ambiguos']++;
                    $logStatus = 'CPF ambíguo';
                } elseif ($candidateId) {
                    // O PDF oficial confirma participação no programa, mas nunca altera dados pessoais.
                    $markContemplado->execute(['id' => $candidateId]);
                    $officialDate = $row['data_situacao'] ?: ($meta['data_pagamento'] ?? null);
                    $marker = 'Pagamento BB convênio ' . ($meta['convenio_numero'] ?: '—') . ', lista ' . ($meta['lista_numero'] ?: '—') . ', N IDENT ' . $row['n_ident'];

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
                        $logStatus = 'Aplicado';
                        $appliedAt = date('Y-m-d H:i:s');
                    } elseif ($state === 'atualizar_pagamento') {
                        $grant = pe_grant_by_id($pdo, (int) $row['grant_id']);
                        if (!$grant) {
                            throw new RuntimeException('Bolsa existente não foi encontrada durante a conciliação.');
                        }
                        $updateGrant->execute([
                            'valor' => $row['valor'],
                            'data_pagamento' => $officialDate,
                            'observacao' => pe_payment_pdf_append_observation($grant['observacao'] ?? null, $marker),
                            'responsavel' => pe_nullable($responsavel),
                            'id' => (int) $grant['id'],
                        ]);
                        $counters['atualizados']++;
                        $logStatus = 'Atualizado';
                        $appliedAt = date('Y-m-d H:i:s');
                    } elseif ($state === 'ja_conciliado') {
                        $counters['ja_conciliados']++;
                        $logStatus = 'Já conciliado';
                        $appliedAt = date('Y-m-d H:i:s');
                    } elseif ($state === 'conflito_financeiro') {
                        $counters['conflitos_financeiros']++;
                        $logStatus = 'Conflito financeiro';
                    } else {
                        throw new RuntimeException('Estado de conciliação não reconhecido: ' . $state);
                    }
                } else {
                    throw new RuntimeException('Linha sem candidato e sem classificação de pendência.');
                }

                if (!empty($row['name_divergence'])) {
                    $message = trim($message . ' Divergência de nome: Banco “' . $row['nome'] . '” x SIGAS “' . $row['candidate_name'] . '”.');
                }

                pe_payment_pdf_log_item($pdo, $importId, $row, $logStatus, pe_nullable($message), $candidateId, $appliedAt);
            } catch (Throwable $rowError) {
                $pdo->exec('ROLLBACK TO SAVEPOINT pe_payment_pdf_row');
                $counters['erros']++;
                $errors[] = ['n_ident' => $row['n_ident'], 'message' => $rowError->getMessage()];
                pe_payment_pdf_log_item($pdo, $importId, $row, 'Erro', $rowError->getMessage(), null, null);
            }
        }

        $finish = $pdo->prepare('UPDATE pe_pagamento_importacoes SET
            conciliados=:conciliados, atualizados=:atualizados, ja_conciliados=:ja_conciliados,
            nao_localizados=:nao_localizados, ambiguos=:ambiguos, cpf_invalidos=:cpf_invalidos,
            divergencias_nome=:divergencias_nome, conflitos_financeiros=:conflitos_financeiros, erros=:erros,
            status="Concluída", finalizada_em=CURRENT_TIMESTAMP
            WHERE id=:id');
        $finish->execute($counters + ['id' => $importId]);

        $pdo->commit();

        return $counters + [
            'import_id' => $importId,
            'errors_list' => $errors,
            'total' => count($parsed['rows']),
            'valor_total' => $meta['valor_total'],
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
        conciliados, atualizados, ja_conciliados, nao_localizados, ambiguos, cpf_invalidos,
        conflitos_financeiros, erros, responsavel, status, criado_em, finalizada_em
        FROM pe_pagamento_importacoes ORDER BY id DESC LIMIT ' . $limit)->fetchAll(PDO::FETCH_ASSOC) ?: [];
}
