<?php

declare(strict_types=1);

/**
 * Leitor seguro de XLSX/CSV sem dependência de PhpSpreadsheet.
 * Lê somente a primeira aba e impõe limites para evitar arquivos abusivos.
 */

function cm_import_clean_text(string $value): string
{
    $value = preg_replace('/^\xEF\xBB\xBF/', '', $value) ?: $value;
    $value = str_replace("\xC2\xA0", ' ', $value);
    $value = trim($value);
    return preg_replace('/[\t\r\n ]+/u', ' ', $value) ?: $value;
}

function cm_import_header_key(string $value): string
{
    $value = mb_strtoupper(cm_import_clean_text($value), 'UTF-8');
    $value = strtr($value, [
        'Á'=>'A','À'=>'A','Â'=>'A','Ã'=>'A','Ä'=>'A','É'=>'E','È'=>'E','Ê'=>'E','Ë'=>'E',
        'Í'=>'I','Ì'=>'I','Î'=>'I','Ï'=>'I','Ó'=>'O','Ò'=>'O','Ô'=>'O','Õ'=>'O','Ö'=>'O',
        'Ú'=>'U','Ù'=>'U','Û'=>'U','Ü'=>'U','Ç'=>'C','º'=>'','ª'=>'',
    ]);
    $value = preg_replace('/[^A-Z0-9]+/', ' ', $value) ?: '';
    return trim(preg_replace('/\s+/', ' ', $value) ?: '');
}

function cm_import_digits(mixed $value): string
{
    return preg_replace('/\D+/', '', (string) ($value ?? '')) ?: '';
}

function cm_import_column_letter(int $number): string
{
    $letter = '';
    while ($number > 0) {
        $number--;
        $letter = chr(65 + ($number % 26)) . $letter;
        $number = intdiv($number, 26);
    }
    return $letter;
}

function cm_import_zip_entry_fallback(string $path, string $entryName, int $maxUncompressedBytes = 33554432): ?string
{
    $size = @filesize($path);
    if ($size === false || $size <= 0 || $size > 12 * 1024 * 1024) {
        throw new RuntimeException('Arquivo XLSX inválido ou acima do limite de segurança de 12 MB.');
    }

    $data = @file_get_contents($path);
    if ($data === false || $data === '') {
        throw new RuntimeException('Não foi possível ler o arquivo XLSX.');
    }

    $eocdPos = strrpos($data, "PK\x05\x06");
    if ($eocdPos === false || strlen($data) < $eocdPos + 22) {
        throw new RuntimeException('Estrutura ZIP do XLSX inválida.');
    }

    $eocd = unpack('vdisk/vcdDisk/ventriesDisk/ventries/VcdSize/VcdOffset/vcommentLen', substr($data, $eocdPos + 4, 18));
    if (!$eocd || (int) $eocd['entries'] > 2500) {
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

            $lh = unpack('vversion/vflags/vcompression/vtime/vdate/Vcrc/VcompSize/VuncompSize/vnameLen/vextraLen', substr($data, $local + 4, 26));
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

function cm_import_xlsx_entry(string $path, string $entryName, int $maxBytes = 33554432): ?string
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

    return cm_import_zip_entry_fallback($path, $entryName, $maxBytes);
}

/** @return list<array<string,mixed>> */
function cm_import_xlsx_rows(string $path): array
{
    $shared = [];
    $sharedXml = cm_import_xlsx_entry($path, 'xl/sharedStrings.xml', 16777216);
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

    $sheetXml = cm_import_xlsx_entry($path, 'xl/worksheets/sheet1.xml', 33554432);
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
                $inner = $cell[2] ?? '';
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
                    $value = $type === 's' && isset($shared[(int) $raw]) ? $shared[(int) $raw] : $raw;
                }
                $row[$column] = cm_import_clean_text((string) $value);
            }
        }
        $rows[] = $row;
    }

    return $rows;
}

/** @return list<array<string,mixed>> */
function cm_import_csv_rows(string $path): array
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
    $delimiter = substr_count($first, ';') >= substr_count($first, ',') ? ';' : ',';
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
            $row[cm_import_column_letter($index + 1)] = cm_import_clean_text((string) $value);
        }
        $rows[] = $row;
    }
    fclose($handle);

    return $rows;
}

/** @return list<array<string,mixed>> */
function cm_import_spreadsheet_rows(string $path, string $originalName): array
{
    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    if ($ext === 'csv') {
        return cm_import_csv_rows($path);
    }
    if ($ext === 'xlsx') {
        return cm_import_xlsx_rows($path);
    }
    throw new InvalidArgumentException('Formato não permitido. Envie XLSX ou CSV.');
}

function cm_import_validate_upload(string $path, string $extension): void
{
    $extension = strtolower($extension);
    $size = @filesize($path);
    if ($size === false || $size <= 0) {
        throw new InvalidArgumentException('Arquivo vazio ou inválido.');
    }
    if ($size > 10 * 1024 * 1024) {
        throw new InvalidArgumentException('O arquivo excede o limite de 10 MB.');
    }

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

function cm_import_excel_date(mixed $value): ?string
{
    $value = cm_import_clean_text((string) ($value ?? ''));
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

    foreach (['d/m/Y', 'd/m/y', 'Y-m-d', 'd-m-Y', 'd.m.Y'] as $format) {
        $date = DateTimeImmutable::createFromFormat('!' . $format, $value);
        $errors = DateTimeImmutable::getLastErrors();
        if ($date !== false && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))) {
            return $date->format('Y-m-d');
        }
    }

    return null;
}
