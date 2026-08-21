<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

function pe_xml_decode(string $value): string
{
    return html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_XML1, 'UTF-8');
}

/**
 * Lê uma entrada de ZIP sem extrair arquivos para o disco.
 * É um fallback seguro para servidores sem ZipArchive e cobre o necessário para XLSX.
 */
function pe_zip_entry_fallback(string $path, string $entryName, int $maxUncompressedBytes = 33554432): ?string
{
    $size = @filesize($path);
    if ($size === false || $size <= 0 || $size > 12 * 1024 * 1024) {
        throw new RuntimeException('Arquivo XLSX inválido ou acima do limite de segurança.');
    }

    $data = @file_get_contents($path);
    if ($data === false || $data === '') {
        throw new RuntimeException('Não foi possível ler o arquivo XLSX.');
    }

    $eocdPos = strrpos($data, "PK\x05\x06");
    if ($eocdPos === false || strlen($data) < $eocdPos + 22) {
        throw new RuntimeException('Estrutura ZIP do XLSX inválida.');
    }

    $eocd = unpack(
        'vdisk/vcdDisk/ventriesDisk/ventries/VcdSize/VcdOffset/vcommentLen',
        substr($data, $eocdPos + 4, 18)
    );
    if (!$eocd || (int) $eocd['entries'] > 2000) {
        throw new RuntimeException('Estrutura XLSX excede o limite permitido.');
    }

    $pos = (int) $eocd['cdOffset'];
    $total = (int) $eocd['entries'];
    $dataLen = strlen($data);

    for ($i = 0; $i < $total; $i++) {
        if ($pos < 0 || $pos + 46 > $dataLen || substr($data, $pos, 4) !== "PK\x01\x02") {
            break;
        }

        $h = unpack(
            'vversionMade/vversionNeeded/vflags/vcompression/vtime/vdate/Vcrc/VcompSize/VuncompSize/vnameLen/vextraLen/vcommentLen/vdiskStart/vintAttr/VextAttr/VlocalOffset',
            substr($data, $pos + 4, 42)
        );
        if (!$h) {
            break;
        }

        $nameLen = (int) $h['nameLen'];
        $extraLen = (int) $h['extraLen'];
        $commentLen = (int) $h['commentLen'];
        $name = substr($data, $pos + 46, $nameLen);

        if ($name === $entryName) {
            if (((int) $h['flags'] & 0x1) !== 0) {
                throw new RuntimeException('XLSX protegido por senha não é suportado.');
            }
            $uncompressedSize = (int) $h['uncompSize'];
            $compressedSize = (int) $h['compSize'];
            if ($uncompressedSize < 0 || $uncompressedSize > $maxUncompressedBytes || $compressedSize < 0) {
                throw new RuntimeException('Conteúdo XLSX excede o limite de segurança.');
            }

            $local = (int) $h['localOffset'];
            if ($local < 0 || $local + 30 > $dataLen || substr($data, $local, 4) !== "PK\x03\x04") {
                throw new RuntimeException('Entrada XLSX inválida.');
            }
            $lh = unpack(
                'vversion/vflags/vcompression/vtime/vdate/Vcrc/VcompSize/VuncompSize/vnameLen/vextraLen',
                substr($data, $local + 4, 26)
            );
            if (!$lh) {
                throw new RuntimeException('Cabeçalho XLSX inválido.');
            }
            $start = $local + 30 + (int) $lh['nameLen'] + (int) $lh['extraLen'];
            if ($start < 0 || $start + $compressedSize > $dataLen) {
                throw new RuntimeException('Conteúdo XLSX truncado.');
            }
            $compressed = substr($data, $start, $compressedSize);
            $method = (int) $h['compression'];
            if ($method === 0) {
                $out = $compressed;
            } elseif ($method === 8) {
                $out = @gzinflate($compressed);
                if ($out === false) {
                    throw new RuntimeException('Falha ao descompactar a planilha XLSX.');
                }
            } else {
                throw new RuntimeException('Método de compactação XLSX não suportado.');
            }
            if (strlen($out) > $maxUncompressedBytes) {
                throw new RuntimeException('Conteúdo XLSX excede o limite de segurança.');
            }
            return $out;
        }

        $pos += 46 + $nameLen + $extraLen + $commentLen;
    }

    return null;
}

function pe_xlsx_entry(string $path, string $entryName, int $maxBytes = 33554432): ?string
{
    if (class_exists('ZipArchive')) {
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new RuntimeException('Não foi possível abrir a planilha XLSX.');
        }
        $stat = $zip->statName($entryName);
        if ($stat !== false && isset($stat['size']) && (int) $stat['size'] > $maxBytes) {
            $zip->close();
            throw new RuntimeException('Conteúdo XLSX excede o limite de segurança.');
        }
        $content = $zip->getFromName($entryName);
        $zip->close();
        return $content === false ? null : $content;
    }

    if (!function_exists('gzinflate')) {
        throw new RuntimeException('O servidor não possui ZipArchive nem suporte zlib para ler XLSX.');
    }
    return pe_zip_entry_fallback($path, $entryName, $maxBytes);
}

function pe_xlsx_rows(string $path): array
{
    $shared = [];
    $sharedXml = pe_xlsx_entry($path, 'xl/sharedStrings.xml', 16777216);
    if ($sharedXml !== null && preg_match_all('/<si\b[^>]*>(.*?)<\/si>/si', $sharedXml, $matches)) {
        foreach ($matches[1] as $item) {
            $text = '';
            if (preg_match_all('/<t\b[^>]*>(.*?)<\/t>/si', $item, $texts)) {
                foreach ($texts[1] as $part) {
                    $text .= html_entity_decode($part, ENT_QUOTES | ENT_XML1, 'UTF-8');
                }
            }
            $shared[] = $text;
        }
    }

    $sheetXml = pe_xlsx_entry($path, 'xl/worksheets/sheet1.xml', 33554432);
    if ($sheetXml === null) {
        throw new RuntimeException('A primeira aba da planilha não foi encontrada.');
    }

    $rows = [];
    if (!preg_match_all('/<row\b[^>]*\br="(\d+)"[^>]*>(.*?)<\/row>/si', $sheetXml, $rowMatches, PREG_SET_ORDER)) {
        return $rows;
    }
    if (count($rowMatches) > 20000) {
        throw new RuntimeException('A planilha excede o limite de 20.000 linhas por importação.');
    }

    foreach ($rowMatches as $rowMatch) {
        $row = ['__row' => (int) $rowMatch[1]];
        if (preg_match_all('/<c\b([^>]*?)(?:\/\>|>(.*?)<\/c>)/si', $rowMatch[2], $cellMatches, PREG_SET_ORDER)) {
            if (count($cellMatches) > 100) {
                throw new RuntimeException('A planilha possui mais de 100 colunas em uma linha e foi rejeitada.');
            }
            foreach ($cellMatches as $cell) {
                $attrs = $cell[1];
                $inner = isset($cell[2]) ? $cell[2] : '';
                if (!preg_match('/\br="([A-Z]+)\d+"/i', $attrs, $ref)) {
                    continue;
                }
                $column = strtoupper($ref[1]);
                $type = preg_match('/\bt="([^"]+)"/i', $attrs, $tm) ? $tm[1] : '';
                $value = '';
                if ($type === 'inlineStr' && preg_match_all('/<t\b[^>]*>(.*?)<\/t>/si', $inner, $texts)) {
                    foreach ($texts[1] as $part) {
                        $value .= html_entity_decode($part, ENT_QUOTES | ENT_XML1, 'UTF-8');
                    }
                } elseif (preg_match('/<v>(.*?)<\/v>/si', $inner, $vm)) {
                    $raw = html_entity_decode($vm[1], ENT_QUOTES | ENT_XML1, 'UTF-8');
                    if ($type === 's' && isset($shared[(int) $raw])) {
                        $value = $shared[(int) $raw];
                    } else {
                        $value = $raw;
                    }
                }
                $row[$column] = pe_import_clean_text((string) $value);
            }
        }
        $rows[] = $row;
    }
    return $rows;
}

function pe_csv_rows(string $path): array
{
    $handle = fopen($path, 'rb');
    if (!$handle) {
        throw new RuntimeException('Não foi possível abrir o CSV.');
    }
    $first = fgets($handle);
    if ($first === false) {
        fclose($handle);
        return [];
    }
    $semicolon = substr_count($first, ';');
    $comma = substr_count($first, ',');
    $delimiter = $semicolon >= $comma ? ';' : ',';
    rewind($handle);
    $rows = [];
    $number = 0;
    while (($data = fgetcsv($handle, 0, $delimiter)) !== false) {
        $number++;
        if ($number > 20000) {
            fclose($handle);
            throw new RuntimeException('A planilha excede o limite de 20.000 linhas por importação.');
        }
        $row = ['__row' => $number];
        foreach ($data as $index => $value) {
            if ($index >= 100) {
                break;
            }
            $row[pe_column_letter($index + 1)] = pe_import_clean_text((string) $value);
        }
        $rows[] = $row;
    }
    fclose($handle);
    return $rows;
}

function pe_column_letter(int $number): string
{
    $letter = '';
    while ($number > 0) {
        $number--;
        $letter = chr(65 + ($number % 26)) . $letter;
        $number = (int) floor($number / 26);
    }
    return $letter;
}

function pe_spreadsheet_rows(string $path, string $originalName): array
{
    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    if ($ext === 'csv') {
        return pe_csv_rows($path);
    }
    if ($ext === 'xlsx') {
        return pe_xlsx_rows($path);
    }
    throw new InvalidArgumentException('Formato não permitido. Envie XLSX ou CSV.');
}

function pe_import_clean_text(string $value): string
{
    $value = preg_replace('/^\xEF\xBB\xBF/', '', $value) ?: $value;
    $value = str_replace("\xC2\xA0", ' ', $value);
    $value = trim($value);
    return preg_replace('/[\t\r\n ]+/u', ' ', $value) ?: $value;
}

function pe_header_key(string $value): string
{
    $value = strtoupper(pe_import_clean_text($value));
    $value = strtr($value, [
        'Á'=>'A','À'=>'A','Â'=>'A','Ã'=>'A','Ä'=>'A','É'=>'E','È'=>'E','Ê'=>'E','Ë'=>'E',
        'Í'=>'I','Ì'=>'I','Î'=>'I','Ï'=>'I','Ó'=>'O','Ò'=>'O','Ô'=>'O','Õ'=>'O','Ö'=>'O',
        'Ú'=>'U','Ù'=>'U','Û'=>'U','Ü'=>'U','Ç'=>'C','º'=>'','ª'=>'',
    ]);
    $value = preg_replace('/[^A-Z0-9]+/', ' ', $value) ?: '';
    return trim(preg_replace('/\s+/', ' ', $value) ?: '');
}

function pe_excel_date($value): ?string
{
    $value = trim((string) $value);
    if ($value === '') {
        return null;
    }
    if (is_numeric($value)) {
        $serial = (float) $value;
        if ($serial > 20000 && $serial < 80000) {
            $unix = (int) round(($serial - 25569) * 86400);
            return gmdate('Y-m-d', $unix);
        }
    }
    foreach (['d/m/Y', 'd/m/y', 'Y-m-d', 'd-m-Y'] as $format) {
        $date = DateTime::createFromFormat($format, $value);
        if ($date && $date->format($format) === $value) {
            return $date->format('Y-m-d');
        }
    }
    return null;
}

function pe_import_identity_key(string $name, ?string $birth): string
{
    return pe_header_key($name) . '|' . ($birth ?: '');
}

function pe_import_full_key(string $name, ?string $birth, ?string $responsavel, ?string $bairro, ?string $endereco): string
{
    return hash('sha256',
        pe_header_key($name) . '|' . ($birth ?: '') . '|' .
        pe_header_key((string) $responsavel) . '|' . pe_header_key((string) $bairro) . '|' .
        pe_header_key((string) $endereco)
    );
}

function pe_import_review_meta(array $issues): array
{
    $fields = [];
    $labels = [];
    foreach ($issues as $issue) {
        $field = isset($issue['field']) ? (string) $issue['field'] : '';
        $label = isset($issue['label']) ? trim((string) $issue['label']) : '';
        if ($field !== '') {
            $fields[$field] = true;
        }
        if ($label !== '' && !in_array($label, $labels, true)) {
            $labels[] = $label;
        }
    }

    $cpf = isset($fields['cpf']);
    $phone = isset($fields['telefone']);
    $birth = isset($fields['nascimento']);
    $issueCount = count($labels);
    $status = null;

    if ($issueCount > 1) {
        $status = 'Revisar Cadastro';
    } elseif ($issueCount === 1) {
        if ($cpf) {
            $status = 'Revisar CPF';
        } elseif ($phone) {
            $status = 'Revisar Telefone';
        } elseif ($birth) {
            $status = 'Revisar Data de Nascimento';
        } else {
            $status = 'Revisar Cadastro';
        }
    }

    return [
        'status' => $status,
        'revisao_cpf' => $cpf ? 1 : 0,
        'revisao_telefone' => $phone ? 1 : 0,
        'revisao_nascimento' => $birth ? 1 : 0,
        'motivos' => $labels,
    ];
}

function pe_import_add_issue(array &$row, string $code, string $field, string $label): void
{
    if (!isset($row['issues']) || !is_array($row['issues'])) {
        $row['issues'] = [];
    }
    foreach ($row['issues'] as $issue) {
        if (($issue['code'] ?? '') === $code) {
            return;
        }
    }
    $row['issues'][] = ['code' => $code, 'field' => $field, 'label' => $label];
}

function pe_import_apply_review_meta(array &$row): void
{
    $meta = pe_import_review_meta(isset($row['issues']) && is_array($row['issues']) ? $row['issues'] : []);
    $row['revisao_status'] = $meta['status'];
    $row['revisao_cpf'] = $meta['revisao_cpf'];
    $row['revisao_telefone'] = $meta['revisao_telefone'];
    $row['revisao_nascimento'] = $meta['revisao_nascimento'];
    $row['revisao_motivos'] = $meta['motivos'];
}

function pe_prepare_import(array $rows): array
{
    if (count($rows) < 2) {
        throw new InvalidArgumentException('A planilha não possui linhas suficientes.');
    }

    $headerRow = $rows[0];
    $headerMap = [];
    foreach ($headerRow as $column => $value) {
        if ($column === '__row') {
            continue;
        }
        $key = pe_header_key((string) $value);
        if ($key !== '') {
            $headerMap[$key] = $column;
        }
    }

    $aliases = [
        'nome' => ['NOME'],
        'data_nascimento' => ['DATA NASC', 'DATA NASCIMENTO', 'DATA DE NASCIMENTO'],
        'responsavel' => ['RESPONSAVEL', 'RESPONSAVEL FAMILIAR'],
        'bairro' => ['BAIRRO'],
        'endereco' => ['ENDERECO', 'ENDEREÇO'],
        'telefone' => ['TELEFONE', 'CONTATO'],
        'cpf' => ['CPF'],
        'idade' => ['IDADE'],
        'setor' => ['SETOR', 'LOCAL', 'LOCAL DE ATUACAO'],
    ];

    $columns = [];
    foreach ($aliases as $field => $options) {
        foreach ($options as $option) {
            $normalized = pe_header_key($option);
            if (isset($headerMap[$normalized])) {
                $columns[$field] = $headerMap[$normalized];
                break;
            }
        }
    }
    if (empty($columns['nome'])) {
        throw new InvalidArgumentException('Cabeçalho NOME não encontrado.');
    }

    $prepared = [];
    $cpfRows = [];
    $summary = [
        'total' => 0,
        'cpf_validos' => 0,
        'cpf_invalidos' => 0,
        'cpf_vazios' => 0,
        'telefones_invalidos' => 0,
        'telefones_vazios' => 0,
        'datas_invalidas' => 0,
        'setor_preenchido' => 0,
        'setor_vazio' => 0,
        'cpf_duplicados' => 0,
        'pendentes_revisao' => 0,
        'revisar_cpf' => 0,
        'revisar_telefone' => 0,
        'revisar_nascimento' => 0,
        'revisar_cadastro' => 0,
        'sem_pendencia' => 0,
        'com_avisos' => 0,
        'bloqueados' => 0,
    ];

    foreach (array_slice($rows, 1) as $row) {
        $get = function (string $field) use ($columns, $row): string {
            return isset($columns[$field], $row[$columns[$field]])
                ? pe_import_clean_text((string) $row[$columns[$field]])
                : '';
        };

        $rowNumber = (int) ($row['__row'] ?? 0);
        $name = $get('nome');
        if ($name === '') {
            continue;
        }
        $summary['total']++;

        $birthRaw = $get('data_nascimento');
        $birth = pe_excel_date($birthRaw);
        $cpfOriginal = pe_digits($get('cpf'));
        $cpfCandidate = $cpfOriginal;
        if ($cpfCandidate !== '' && strlen($cpfCandidate) < 11) {
            $cpfCandidate = str_pad($cpfCandidate, 11, '0', STR_PAD_LEFT);
        }
        $cpfValid = strlen($cpfCandidate) === 11 && pe_validate_cpf($cpfCandidate);
        $phone = pe_digits($get('telefone'));
        $setor = pe_nullable($get('setor'));

        $item = [
            'row' => $rowNumber,
            'nome' => $name,
            'data_nascimento' => $birth,
            'data_nascimento_informada' => $birthRaw === '' ? null : $birthRaw,
            'responsavel' => pe_nullable($get('responsavel')),
            'bairro' => pe_nullable($get('bairro')),
            'endereco' => pe_nullable($get('endereco')),
            'telefone' => $phone === '' ? null : $phone,
            'telefone_informado' => pe_nullable($get('telefone')),
            'cpf' => $cpfValid ? $cpfCandidate : null,
            'cpf_informado' => $cpfOriginal === '' ? null : $cpfOriginal,
            'idade_informada' => pe_nullable($get('idade')),
            'setor' => $setor,
            'chave_importacao' => pe_import_full_key($name, $birth, pe_nullable($get('responsavel')), pe_nullable($get('bairro')), pe_nullable($get('endereco'))),
            'identity_key' => pe_import_identity_key($name, $birth),
            'issues' => [],
            'cpf_duplicado' => 0,
            'blocked' => false,
        ];

        if ($birthRaw === '') {
            pe_import_add_issue($item, 'nascimento_nao_informado', 'nascimento', 'Data de nascimento não informada');
            $summary['datas_invalidas']++;
        } elseif (!$birth) {
            pe_import_add_issue($item, 'nascimento_invalido', 'nascimento', 'Data de nascimento inválida');
            $summary['datas_invalidas']++;
        }

        if ($cpfOriginal === '') {
            pe_import_add_issue($item, 'cpf_nao_informado', 'cpf', 'CPF não informado');
            $summary['cpf_vazios']++;
        } elseif (!$cpfValid) {
            pe_import_add_issue($item, 'cpf_inconsistente', 'cpf', 'CPF inconsistente');
            $summary['cpf_invalidos']++;
        } else {
            $summary['cpf_validos']++;
        }

        if ($phone === '') {
            pe_import_add_issue($item, 'telefone_nao_informado', 'telefone', 'Telefone não informado');
            $summary['telefones_vazios']++;
        } elseif (!in_array(strlen($phone), [10, 11], true)) {
            pe_import_add_issue($item, 'telefone_fora_padrao', 'telefone', 'Telefone fora do padrão');
            $summary['telefones_invalidos']++;
        }

        if ($setor === null) {
            $summary['setor_vazio']++;
        } else {
            $summary['setor_preenchido']++;
        }

        $prepared[] = $item;
        $idx = count($prepared) - 1;
        if ($cpfValid) {
            if (!isset($cpfRows[$cpfCandidate])) {
                $cpfRows[$cpfCandidate] = [];
            }
            $cpfRows[$cpfCandidate][] = $idx;
        }
    }

    // CPF repetido nunca bloqueia. Todos entram e todos os envolvidos ficam marcados para revisão.
    foreach ($cpfRows as $cpf => $indices) {
        if (count($indices) < 2) {
            continue;
        }
        foreach ($indices as $idx) {
            $prepared[$idx]['cpf_duplicado'] = 1;
            pe_import_add_issue($prepared[$idx], 'cpf_duplicado', 'cpf', 'CPF duplicado na lista');
        }
    }

    $warningsFlat = [];
    foreach ($prepared as &$item) {
        pe_import_apply_review_meta($item);
        if ((int) $item['cpf_duplicado'] === 1) {
            $summary['cpf_duplicados']++;
        }
        if ($item['revisao_status'] === null) {
            $summary['sem_pendencia']++;
        } else {
            $summary['pendentes_revisao']++;
            $summary['com_avisos']++;
            if ($item['revisao_status'] === 'Revisar CPF') {
                $summary['revisar_cpf']++;
            } elseif ($item['revisao_status'] === 'Revisar Telefone') {
                $summary['revisar_telefone']++;
            } elseif ($item['revisao_status'] === 'Revisar Data de Nascimento') {
                $summary['revisar_nascimento']++;
            } elseif ($item['revisao_status'] === 'Revisar Cadastro') {
                $summary['revisar_cadastro']++;
            }
        }

        foreach ($item['issues'] as $issue) {
            $warningsFlat[] = [
                'row' => $item['row'],
                'message' => $issue['label'],
                'value' => $item['revisao_status'] ?: 'OK',
            ];
        }
    }
    unset($item);

    return [
        'rows' => $prepared,
        'warnings' => $warningsFlat,
        'columns' => $columns,
        'summary' => $summary,
    ];
}

function pe_import_item_log(PDO $pdo, array $data): void
{
    $stmt = $pdo->prepare('INSERT INTO pe_importacao_itens
        (importacao_id, linha, candidato_id, status, nome, cpf_informado, cpf_validado, data_nascimento, setor_informado, mensagem)
        VALUES (:importacao_id, :linha, :candidato_id, :status, :nome, :cpf_informado, :cpf_validado, :data_nascimento, :setor_informado, :mensagem)');
    $stmt->execute($data);
}

function pe_import_prepared(PDO $pdo, array $prepared, string $filename, array $options = []): array
{
    $markContemplados = !empty($options['mark_contemplados']);
    $fileHash = isset($options['file_hash']) ? pe_nullable($options['file_hash']) : null;
    $responsavel = isset($options['responsavel']) ? pe_nullable($options['responsavel']) : null;

    $imported = 0;
    $updated = 0;
    $blocked = 0;
    $reviewPending = 0;
    $warningCount = 0;
    $errors = [];
    $previousRows = [];
    $previousImportId = 0;
    $hasImportType = function_exists('pe_import_type_column_exists') && pe_import_type_column_exists($pdo);

    if ($fileHash) {
        $sameSql = 'SELECT id, total_linhas, importados, atualizados, bloqueados, erros, criado_em
            FROM pe_importacoes WHERE arquivo_hash = :hash AND status = "Concluída"';
        if ($hasImportType) {
            $sameSql .= ' AND tipo_importacao = "candidatos"';
        }
        $sameSql .= ' ORDER BY id DESC LIMIT 1';
        $same = $pdo->prepare($sameSql);
        $same->execute(['hash' => $fileHash]);
        $previous = $same->fetch();
        if ($previous) {
            $previousImportId = (int) $previous['id'];
            if ((int) $previous['bloqueados'] === 0 && (int) $previous['erros'] === 0
                && ((int) $previous['importados'] + (int) $previous['atualizados']) >= (int) $previous['total_linhas']) {
                throw new RuntimeException('Esta mesma planilha já foi importada integralmente na importação #' . $previousImportId . '. O sistema impediu uma duplicação acidental.');
            }

            $doneRows = $pdo->prepare('SELECT linha, candidato_id FROM pe_importacao_itens
                WHERE importacao_id = :id AND candidato_id IS NOT NULL AND status IN ("Importado", "Atualizado")');
            $doneRows->execute(['id' => $previousImportId]);
            foreach ($doneRows->fetchAll() as $doneRow) {
                $previousRows[(int) $doneRow['linha']] = (int) $doneRow['candidato_id'];
            }
        }
    }

    $pdo->beginTransaction();
    try {
        if ($hasImportType) {
            $log = $pdo->prepare('INSERT INTO pe_importacoes
                (arquivo_nome, arquivo_hash, tipo_importacao, total_linhas, status, marcar_como_contemplados, responsavel)
                VALUES (:arquivo, :hash, "candidatos", :total, "Processando", :marcar, :responsavel)');
        } else {
            $log = $pdo->prepare('INSERT INTO pe_importacoes
                (arquivo_nome, arquivo_hash, total_linhas, status, marcar_como_contemplados, responsavel)
                VALUES (:arquivo, :hash, :total, "Processando", :marcar, :responsavel)');
        }
        $log->execute([
            'arquivo' => $filename,
            'hash' => $fileHash,
            'total' => count($prepared),
            'marcar' => $markContemplados ? 1 : 0,
            'responsavel' => $responsavel,
        ]);
        $importId = (int) $pdo->lastInsertId();

        $findExistingCpf = $pdo->prepare('SELECT id FROM pe_candidatos WHERE cpf = :cpf');
        $markExistingCpfDuplicate = $pdo->prepare('UPDATE pe_candidatos SET
            revisao_cpf = 1,
            cpf_duplicado = 1,
            cpf_duplicado_confirmado = 0,
            revisao_status = CASE
                WHEN revisao_telefone = 1 OR revisao_nascimento = 1 THEN "Revisar Cadastro"
                ELSE "Revisar CPF"
            END,
            revisao_motivos = CASE
                WHEN revisao_motivos IS NULL OR revisao_motivos = "" THEN "CPF duplicado com outro cadastro"
                WHEN revisao_motivos NOT LIKE "%CPF duplicado%" THEN CONCAT(revisao_motivos, " | CPF duplicado com outro cadastro")
                ELSE revisao_motivos
            END,
            revisao_atualizada_em = CURRENT_TIMESTAMP
            WHERE cpf = :cpf');

        $insert = $pdo->prepare('INSERT INTO pe_candidatos
            (nome, data_nascimento, responsavel_familiar, bairro, endereco, telefone,
             cpf, cpf_informado, chave_importacao, status,
             revisao_status, revisao_cpf, revisao_telefone, revisao_nascimento, cpf_duplicado, revisao_motivos, revisao_atualizada_em,
             origem, importacao_id)
            VALUES
            (:nome, :data_nascimento, :responsavel, :bairro, :endereco, :telefone,
             :cpf, :cpf_informado, :chave, :status,
             :revisao_status, :revisao_cpf, :revisao_telefone, :revisao_nascimento, :cpf_duplicado, :revisao_motivos, :revisao_atualizada_em,
             "importacao", :importacao_id)');

        $refreshExisting = $pdo->prepare('UPDATE pe_candidatos SET
            nome=:nome,
            data_nascimento=:data_nascimento,
            responsavel_familiar=:responsavel,
            bairro=:bairro,
            endereco=:endereco,
            telefone=:telefone,
            cpf=:cpf,
            cpf_informado=:cpf_informado,
            status=CASE WHEN :mark_contemplado=1 THEN "Contemplado" ELSE status END,
            revisao_status=:revisao_status,
            revisao_cpf=:revisao_cpf,
            revisao_telefone=:revisao_telefone,
            revisao_nascimento=:revisao_nascimento,
            cpf_duplicado=:cpf_duplicado,
            revisao_motivos=:revisao_motivos,
            revisao_atualizada_em=:revisao_atualizada_em,
            updated_at=CURRENT_TIMESTAMP
            WHERE id=:id');

        $profile = $pdo->prepare('INSERT INTO pe_fichas_cadastrais (candidato_id, local_atuacao)
            VALUES (:id, :setor)
            ON DUPLICATE KEY UPDATE local_atuacao=COALESCE(VALUES(local_atuacao), local_atuacao), updated_at=CURRENT_TIMESTAMP');

        foreach ($prepared as $originalRow) {
            $pdo->exec('SAVEPOINT pe_import_row');
            try {
                $row = $originalRow;
                $rowNumber = (int) ($row['row'] ?? 0);

                // Conflito contra qualquer outro candidato já existente: sinaliza, nunca mescla.
                if (!empty($row['cpf'])) {
                    $findExistingCpf->execute(['cpf' => $row['cpf']]);
                    $existingIds = array_map('intval', $findExistingCpf->fetchAll(PDO::FETCH_COLUMN));
                    $resumeId = isset($previousRows[$rowNumber]) ? (int) $previousRows[$rowNumber] : 0;
                    $otherIds = array_values(array_filter($existingIds, function ($id) use ($resumeId) {
                        return $resumeId <= 0 || (int) $id !== $resumeId;
                    }));
                    if ($otherIds) {
                        $row['cpf_duplicado'] = 1;
                        pe_import_add_issue($row, 'cpf_duplicado_banco', 'cpf', 'CPF duplicado com outro cadastro');
                        pe_import_apply_review_meta($row);
                        $markExistingCpfDuplicate->execute(['cpf' => $row['cpf']]);
                    }
                }

                $issues = isset($row['issues']) && is_array($row['issues']) ? $row['issues'] : [];
                $warningCount += count($issues);
                if (!empty($row['revisao_status'])) {
                    $reviewPending++;
                }
                $revisaoMotivos = empty($row['revisao_motivos']) ? null : implode(' | ', $row['revisao_motivos']);
                $reviewDate = empty($row['revisao_status']) ? null : date('Y-m-d H:i:s');

                $candidateId = 0;
                $itemStatus = 'Importado';

                // Se a mesma planilha já teve carga parcial, não duplica as linhas que já entraram.
                // Apenas aplica nelas a nova classificação de revisão e continua as linhas que faltavam.
                if (isset($previousRows[$rowNumber]) && $previousRows[$rowNumber] > 0) {
                    $candidateId = (int) $previousRows[$rowNumber];
                    $refreshExisting->execute([
                        'nome' => $row['nome'],
                        'data_nascimento' => $row['data_nascimento'],
                        'responsavel' => $row['responsavel'],
                        'bairro' => $row['bairro'],
                        'endereco' => $row['endereco'],
                        'telefone' => $row['telefone'],
                        'cpf' => $row['cpf'],
                        'cpf_informado' => $row['cpf_informado'],
                        'mark_contemplado' => $markContemplados ? 1 : 0,
                        'revisao_status' => $row['revisao_status'],
                        'revisao_cpf' => (int) $row['revisao_cpf'],
                        'revisao_telefone' => (int) $row['revisao_telefone'],
                        'revisao_nascimento' => (int) $row['revisao_nascimento'],
                        'cpf_duplicado' => (int) $row['cpf_duplicado'],
                        'revisao_motivos' => $revisaoMotivos,
                        'revisao_atualizada_em' => $reviewDate,
                        'id' => $candidateId,
                    ]);
                    $updated++;
                    $itemStatus = 'Atualizado';
                } else {
                    $sourceKey = hash('sha256', (string) $row['chave_importacao'] . '|import:' . $importId . '|row:' . $rowNumber);
                    $insert->execute([
                        'nome' => $row['nome'],
                        'data_nascimento' => $row['data_nascimento'],
                        'responsavel' => $row['responsavel'],
                        'bairro' => $row['bairro'],
                        'endereco' => $row['endereco'],
                        'telefone' => $row['telefone'],
                        'cpf' => $row['cpf'],
                        'cpf_informado' => $row['cpf_informado'],
                        'chave' => $sourceKey,
                        'status' => $markContemplados ? 'Contemplado' : 'Importado',
                        'revisao_status' => $row['revisao_status'],
                        'revisao_cpf' => (int) $row['revisao_cpf'],
                        'revisao_telefone' => (int) $row['revisao_telefone'],
                        'revisao_nascimento' => (int) $row['revisao_nascimento'],
                        'cpf_duplicado' => (int) $row['cpf_duplicado'],
                        'revisao_motivos' => $revisaoMotivos,
                        'revisao_atualizada_em' => $reviewDate,
                        'importacao_id' => $importId,
                    ]);
                    $candidateId = (int) $pdo->lastInsertId();
                    $imported++;
                }

                if (!empty($row['setor'])) {
                    $profile->execute(['id' => $candidateId, 'setor' => $row['setor']]);
                }

                $messageParts = [];
                if (!empty($row['revisao_status'])) {
                    $messageParts[] = $row['revisao_status'];
                }
                if ($revisaoMotivos) {
                    $messageParts[] = $revisaoMotivos;
                }
                if ($previousImportId > 0 && $itemStatus === 'Atualizado') {
                    $messageParts[] = 'Linha recuperada da importação parcial #' . $previousImportId;
                }

                pe_import_item_log($pdo, [
                    'importacao_id' => $importId,
                    'linha' => $rowNumber,
                    'candidato_id' => $candidateId,
                    'status' => $itemStatus,
                    'nome' => $row['nome'],
                    'cpf_informado' => $row['cpf_informado'],
                    'cpf_validado' => $row['cpf'],
                    'data_nascimento' => $row['data_nascimento'],
                    'setor_informado' => $row['setor'],
                    'mensagem' => $messageParts ? implode(' — ', $messageParts) : 'Cadastro sem pendência de revisão',
                ]);
            } catch (Throwable $rowError) {
                $pdo->exec('ROLLBACK TO SAVEPOINT pe_import_row');
                $errors[] = ['row' => (int) ($originalRow['row'] ?? 0), 'message' => $rowError->getMessage()];
                pe_import_item_log($pdo, [
                    'importacao_id' => $importId,
                    'linha' => (int) ($originalRow['row'] ?? 0),
                    'candidato_id' => null,
                    'status' => 'Erro',
                    'nome' => (string) ($originalRow['nome'] ?? 'Linha sem nome'),
                    'cpf_informado' => $originalRow['cpf_informado'] ?? null,
                    'cpf_validado' => $originalRow['cpf'] ?? null,
                    'data_nascimento' => $originalRow['data_nascimento'] ?? null,
                    'setor_informado' => $originalRow['setor'] ?? null,
                    'mensagem' => $rowError->getMessage(),
                ]);
            }
        }

        $done = $pdo->prepare('UPDATE pe_importacoes SET
            importados=:importados, atualizados=:atualizados, bloqueados=0,
            avisos=:avisos, pendentes_revisao=:pendentes_revisao, erros=:erros,
            status="Concluída", finalizada_em=CURRENT_TIMESTAMP
            WHERE id=:id');
        $done->execute([
            'importados' => $imported,
            'atualizados' => $updated,
            'avisos' => $warningCount,
            'pendentes_revisao' => $reviewPending,
            'erros' => count($errors),
            'id' => $importId,
        ]);

        $pdo->commit();

        return [
            'imported' => $imported,
            'updated' => $updated,
            'blocked' => 0,
            'review_pending' => $reviewPending,
            'warnings' => $warningCount,
            'errors' => $errors,
            'import_id' => $importId,
            'resumed_from' => $previousImportId,
        ];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function pe_validate_spreadsheet_upload(string $path, string $extension): void
{
    $extension = strtolower($extension);
    $fh = @fopen($path, 'rb');
    if (!$fh) {
        throw new RuntimeException('Não foi possível validar o arquivo enviado.');
    }
    $head = (string) fread($fh, 4096);
    fclose($fh);

    if ($extension === 'xlsx') {
        if (substr($head, 0, 2) !== 'PK') {
            throw new InvalidArgumentException('O arquivo possui extensão XLSX, mas não é uma planilha Excel válida.');
        }
        return;
    }
    if ($extension === 'csv') {
        if (strpos($head, "\0") !== false) {
            throw new InvalidArgumentException('O CSV enviado contém dados binários e foi rejeitado.');
        }
        return;
    }
    throw new InvalidArgumentException('Formato não permitido. Envie XLSX ou CSV.');
}
