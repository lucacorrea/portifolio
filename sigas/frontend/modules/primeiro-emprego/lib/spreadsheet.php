<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

function pe_xml_decode(string $value): string
{
    return html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_XML1, 'UTF-8');
}

function pe_xlsx_rows(string $path): array
{
    if (!class_exists('ZipArchive')) {
        throw new RuntimeException('Para importar XLSX, habilite a extensão PHP ZipArchive. Como alternativa, salve a planilha em CSV UTF-8 e importe o CSV.');
    }
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) {
        throw new RuntimeException('Não foi possível abrir a planilha XLSX.');
    }

    $shared = [];
    $sharedXml = $zip->getFromName('xl/sharedStrings.xml');
    if ($sharedXml !== false && preg_match_all('/<si\b[^>]*>(.*?)<\/si>/si', $sharedXml, $matches)) {
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

    $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
    $zip->close();
    if ($sheetXml === false) {
        throw new RuntimeException('A primeira aba da planilha não foi encontrada.');
    }

    $rows = [];
    if (!preg_match_all('/<row\b[^>]*\br="(\d+)"[^>]*>(.*?)<\/row>/si', $sheetXml, $rowMatches, PREG_SET_ORDER)) {
        return $rows;
    }
    foreach ($rowMatches as $rowMatch) {
        $row = ['__row' => (int) $rowMatch[1]];
        if (preg_match_all('/<c\b([^>]*?)(?:\/\>|>(.*?)<\/c>)/si', $rowMatch[2], $cellMatches, PREG_SET_ORDER)) {
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
                $row[$column] = trim((string) $value);
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
        $row = ['__row' => $number];
        foreach ($data as $index => $value) {
            $row[pe_column_letter($index + 1)] = trim((string) $value);
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

function pe_header_key(string $value): string
{
    $value = strtoupper(trim($value));
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
    foreach (['d/m/Y', 'd/m/y', 'Y-m-d'] as $format) {
        $date = DateTime::createFromFormat($format, $value);
        if ($date) {
            return $date->format('Y-m-d');
        }
    }
    return null;
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
    $warnings = [];
    foreach (array_slice($rows, 1) as $row) {
        $get = function (string $field) use ($columns, $row): string {
            return isset($columns[$field], $row[$columns[$field]]) ? trim((string) $row[$columns[$field]]) : '';
        };
        $name = $get('nome');
        if ($name === '') {
            continue;
        }
        $cpfOriginal = $get('cpf');
        $cpf = pe_normalize_cpf($cpfOriginal, true);
        $cpfValid = $cpf !== '' && pe_validate_cpf($cpf);
        $birth = pe_excel_date($get('data_nascimento'));
        $phone = pe_digits($get('telefone'));
        $rowNumber = (int) ($row['__row'] ?? 0);

        if ($cpfOriginal !== '' && !$cpfValid) {
            $warnings[] = ['row' => $rowNumber, 'message' => 'CPF inconsistente; será preservado como informado, sem CPF validado.', 'value' => $cpfOriginal];
        }
        if ($phone !== '' && !in_array(strlen($phone), [10, 11], true)) {
            $warnings[] = ['row' => $rowNumber, 'message' => 'Telefone fora do padrão de 10/11 dígitos.', 'value' => $get('telefone')];
        }
        if (!$birth) {
            $warnings[] = ['row' => $rowNumber, 'message' => 'Data de nascimento ausente ou inválida.', 'value' => $get('data_nascimento')];
        }

        $key = hash('sha256', pe_header_key($name) . '|' . ($birth ?: '') . '|' . pe_header_key($get('responsavel')) . '|' . pe_header_key($get('bairro')) . '|' . pe_header_key($get('endereco')));
        $prepared[] = [
            'row' => $rowNumber,
            'nome' => $name,
            'data_nascimento' => $birth,
            'responsavel' => pe_nullable($get('responsavel')),
            'bairro' => pe_nullable($get('bairro')),
            'endereco' => pe_nullable($get('endereco')),
            'telefone' => $phone === '' ? null : $phone,
            'cpf' => $cpfValid ? $cpf : null,
            'cpf_informado' => pe_nullable($cpfOriginal === '' ? null : pe_digits($cpfOriginal)),
            'setor' => pe_nullable($get('setor')),
            'chave_importacao' => $key,
        ];
    }
    return ['rows' => $prepared, 'warnings' => $warnings, 'columns' => $columns];
}

function pe_import_prepared(PDO $pdo, array $prepared, string $filename): array
{
    $imported = 0;
    $updated = 0;
    $errors = [];
    $pdo->beginTransaction();
    try {
        $log = $pdo->prepare('INSERT INTO pe_importacoes (arquivo_nome, total_linhas, status) VALUES (:arquivo, :total, "Processando")');
        $log->execute(['arquivo' => $filename, 'total' => count($prepared)]);
        $importId = (int) $pdo->lastInsertId();

        $findCpf = $pdo->prepare('SELECT id FROM pe_candidatos WHERE cpf = :cpf LIMIT 1');
        $findKey = $pdo->prepare('SELECT id FROM pe_candidatos WHERE chave_importacao = :chave LIMIT 1');
        $insert = $pdo->prepare('INSERT INTO pe_candidatos
            (nome, data_nascimento, responsavel_familiar, bairro, endereco, telefone, cpf, cpf_informado, chave_importacao, status, origem, importacao_id)
            VALUES (:nome, :data_nascimento, :responsavel, :bairro, :endereco, :telefone, :cpf, :cpf_informado, :chave, :status, "importacao", :importacao_id)');
        $update = $pdo->prepare('UPDATE pe_candidatos SET
            nome=:nome, data_nascimento=COALESCE(:data_nascimento,data_nascimento), responsavel_familiar=COALESCE(:responsavel,responsavel_familiar),
            bairro=COALESCE(:bairro,bairro), endereco=COALESCE(:endereco,endereco), telefone=COALESCE(:telefone,telefone),
            cpf=COALESCE(:cpf,cpf), cpf_informado=COALESCE(:cpf_informado,cpf_informado), chave_importacao=:chave,
            origem="importacao", importacao_id=:importacao_id, updated_at=CURRENT_TIMESTAMP WHERE id=:id');
        $profile = $pdo->prepare('INSERT INTO pe_fichas_cadastrais (candidato_id, local_atuacao)
            VALUES (:id, :setor) ON DUPLICATE KEY UPDATE local_atuacao=COALESCE(VALUES(local_atuacao), local_atuacao), updated_at=CURRENT_TIMESTAMP');
        $markContemplado = $pdo->prepare('UPDATE pe_candidatos SET status="Contemplado", updated_at=CURRENT_TIMESTAMP WHERE id=:id');

        foreach ($prepared as $row) {
            try {
                $id = 0;
                if (!empty($row['cpf'])) {
                    $findCpf->execute(['cpf' => $row['cpf']]);
                    $id = (int) $findCpf->fetchColumn();
                }
                if ($id <= 0) {
                    $findKey->execute(['chave' => $row['chave_importacao']]);
                    $id = (int) $findKey->fetchColumn();
                }

                $params = [
                    'nome' => $row['nome'], 'data_nascimento' => $row['data_nascimento'], 'responsavel' => $row['responsavel'],
                    'bairro' => $row['bairro'], 'endereco' => $row['endereco'], 'telefone' => $row['telefone'], 'cpf' => $row['cpf'],
                    'cpf_informado' => $row['cpf_informado'], 'chave' => $row['chave_importacao'], 'importacao_id' => $importId,
                ];
                if ($id > 0) {
                    $params['id'] = $id;
                    $update->execute($params);
                    $updated++;
                } else {
                    $params['status'] = $row['setor'] ? 'Contemplado' : 'Importado';
                    $insert->execute($params);
                    $id = (int) $pdo->lastInsertId();
                    $imported++;
                }
                if (!empty($row['setor'])) {
                    $profile->execute(['id' => $id, 'setor' => $row['setor']]);
                    $markContemplado->execute(['id' => $id]);
                }
            } catch (Throwable $rowError) {
                $errors[] = ['row' => $row['row'], 'message' => $rowError->getMessage()];
            }
        }

        $done = $pdo->prepare('UPDATE pe_importacoes SET importados=:importados, atualizados=:atualizados, erros=:erros, status="Concluída", finalizada_em=CURRENT_TIMESTAMP WHERE id=:id');
        $done->execute(['importados' => $imported, 'atualizados' => $updated, 'erros' => count($errors), 'id' => $importId]);
        $pdo->commit();
        return ['imported' => $imported, 'updated' => $updated, 'errors' => $errors, 'import_id' => $importId];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}
