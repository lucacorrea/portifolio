<?php

declare(strict_types=1);

/**
 * Exportador XLSX sem dependências externas.
 *
 * O arquivo XLSX é um pacote ZIP de documentos XML. Esta implementação
 * gera somente o subconjunto necessário para relatórios tabulares do SIGAS,
 * evitando dependência de PhpSpreadsheet/ZipArchive na hospedagem.
 */
final class ComidaMesaExcelExporter
{
    /** @var list<array{name:string,title:string,subtitle:string,headers:list<string>,rows:list<list<mixed>>,formats:list<string>}> */
    private array $sheets = [];

    /**
     * @param list<string> $headers
     * @param list<list<mixed>> $rows
     * @param list<string> $formats text|number|currency|percent
     */
    public function addSheet(
        string $name,
        string $title,
        string $subtitle,
        array $headers,
        array $rows,
        array $formats = []
    ): self {
        if ($headers === []) {
            throw new InvalidArgumentException('A planilha precisa possuir ao menos uma coluna.');
        }

        $safeName = $this->sheetName($name);
        $existing = array_column($this->sheets, 'name');
        $base = $safeName;
        $suffix = 2;
        while (in_array($safeName, $existing, true)) {
            $candidate = mb_substr($base, 0, max(1, 28 - strlen((string) $suffix))) . ' ' . $suffix;
            $safeName = $this->sheetName($candidate);
            $suffix++;
        }

        $columnCount = count($headers);
        $normalizedRows = [];
        foreach ($rows as $row) {
            $row = array_values($row);
            if (count($row) < $columnCount) {
                $row = array_pad($row, $columnCount, null);
            } elseif (count($row) > $columnCount) {
                $row = array_slice($row, 0, $columnCount);
            }
            $normalizedRows[] = $row;
        }

        $this->sheets[] = [
            'name' => $safeName,
            'title' => trim($title),
            'subtitle' => trim($subtitle),
            'headers' => array_map('strval', array_values($headers)),
            'rows' => $normalizedRows,
            'formats' => array_values($formats),
        ];

        return $this;
    }

    public function download(string $filename): never
    {
        if ($this->sheets === []) {
            throw new RuntimeException('Nenhuma aba foi adicionada ao arquivo Excel.');
        }

        $filename = $this->safeFilename($filename);
        if (!str_ends_with(strtolower($filename), '.xlsx')) {
            $filename .= '.xlsx';
        }

        $tmp = tempnam(sys_get_temp_dir(), 'sigas_xlsx_');
        if ($tmp === false) {
            throw new RuntimeException('Não foi possível criar o arquivo temporário da exportação.');
        }

        try {
            $zip = new ComidaMesaZipWriter();
            $this->writePackage($zip);
            $zip->save($tmp);

            if (headers_sent()) {
                throw new RuntimeException('A exportação não pode ser iniciada porque a resposta HTTP já foi enviada.');
            }

            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Content-Length: ' . (string) filesize($tmp));
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
            header('Pragma: no-cache');
            header('X-Content-Type-Options: nosniff');

            $handle = fopen($tmp, 'rb');
            if ($handle === false) {
                throw new RuntimeException('Não foi possível abrir o arquivo exportado.');
            }
            fpassthru($handle);
            fclose($handle);
        } finally {
            @unlink($tmp);
        }

        exit;
    }

    private function writePackage(ComidaMesaZipWriter $zip): void
    {
        $sheetCount = count($this->sheets);

        $zip->add('[Content_Types].xml', $this->contentTypes($sheetCount));
        $zip->add('_rels/.rels', $this->rootRelationships());
        $zip->add('docProps/app.xml', $this->appProperties());
        $zip->add('docProps/core.xml', $this->coreProperties());
        $zip->add('xl/workbook.xml', $this->workbookXml());
        $zip->add('xl/_rels/workbook.xml.rels', $this->workbookRelationships());
        $zip->add('xl/styles.xml', $this->stylesXml());

        foreach ($this->sheets as $index => $sheet) {
            $zip->add('xl/worksheets/sheet' . ($index + 1) . '.xml', $this->worksheetXml($sheet));
        }
    }

    private function contentTypes(int $sheetCount): string
    {
        $overrides = '';
        for ($i = 1; $i <= $sheetCount; $i++) {
            $overrides .= '<Override PartName="/xl/worksheets/sheet' . $i . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . '<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>'
            . '<Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>'
            . $overrides
            . '</Types>';
    }

    private function rootRelationships(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>'
            . '<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>'
            . '</Relationships>';
    }

    private function workbookXml(): string
    {
        $sheets = '';
        foreach ($this->sheets as $index => $sheet) {
            $sheets .= '<sheet name="' . $this->xml($sheet['name']) . '" sheetId="' . ($index + 1) . '" r:id="rId' . ($index + 1) . '"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<bookViews><workbookView xWindow="0" yWindow="0" windowWidth="24000" windowHeight="14000"/></bookViews>'
            . '<sheets>' . $sheets . '</sheets>'
            . '<calcPr calcId="191029" fullCalcOnLoad="1"/>'
            . '</workbook>';
    }

    private function workbookRelationships(): string
    {
        $rels = '';
        foreach ($this->sheets as $index => $_sheet) {
            $rels .= '<Relationship Id="rId' . ($index + 1) . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . ($index + 1) . '.xml"/>';
        }
        $rels .= '<Relationship Id="rId' . (count($this->sheets) + 1) . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . $rels
            . '</Relationships>';
    }

    private function stylesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<numFmts count="2">'
            . '<numFmt numFmtId="164" formatCode="R$ #,##0.00"/>'
            . '<numFmt numFmtId="165" formatCode="0.00%"/>'
            . '</numFmts>'
            . '<fonts count="4">'
            . '<font><sz val="10"/><name val="Arial"/><family val="2"/></font>'
            . '<font><b/><color rgb="FFFFFFFF"/><sz val="16"/><name val="Arial"/><family val="2"/></font>'
            . '<font><b/><color rgb="FFFFFFFF"/><sz val="10"/><name val="Arial"/><family val="2"/></font>'
            . '<font><color rgb="FF5F6F68"/><sz val="10"/><name val="Arial"/><family val="2"/></font>'
            . '</fonts>'
            . '<fills count="4">'
            . '<fill><patternFill patternType="none"/></fill>'
            . '<fill><patternFill patternType="gray125"/></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FF0F5F36"/><bgColor indexed="64"/></patternFill></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FF177A45"/><bgColor indexed="64"/></patternFill></fill>'
            . '</fills>'
            . '<borders count="2">'
            . '<border><left/><right/><top/><bottom/><diagonal/></border>'
            . '<border><left style="thin"><color rgb="FFD8E2DD"/></left><right style="thin"><color rgb="FFD8E2DD"/></right><top style="thin"><color rgb="FFD8E2DD"/></top><bottom style="thin"><color rgb="FFD8E2DD"/></bottom><diagonal/></border>'
            . '</borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="8">'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            . '<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1" applyAlignment="1"><alignment vertical="center"/></xf>'
            . '<xf numFmtId="0" fontId="3" fillId="0" borderId="0" xfId="0" applyFont="1" applyAlignment="1"><alignment vertical="center"/></xf>'
            . '<xf numFmtId="0" fontId="2" fillId="3" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>'
            . '<xf numFmtId="49" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1" applyAlignment="1"><alignment vertical="top" wrapText="1"/></xf>'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1" applyAlignment="1"><alignment horizontal="right" vertical="top"/></xf>'
            . '<xf numFmtId="164" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1" applyAlignment="1"><alignment horizontal="right" vertical="top"/></xf>'
            . '<xf numFmtId="165" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1" applyAlignment="1"><alignment horizontal="right" vertical="top"/></xf>'
            . '</cellXfs>'
            . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            . '</styleSheet>';
    }

    /** @param array{name:string,title:string,subtitle:string,headers:list<string>,rows:list<list<mixed>>,formats:list<string>} $sheet */
    private function worksheetXml(array $sheet): string
    {
        $headers = $sheet['headers'];
        $rows = $sheet['rows'];
        $columnCount = count($headers);
        $lastColumn = $this->columnName($columnCount);
        $lastRow = 4 + count($rows);

        $widths = $this->columnWidths($headers, $rows);
        $cols = '<cols>';
        foreach ($widths as $index => $width) {
            $col = $index + 1;
            $cols .= '<col min="' . $col . '" max="' . $col . '" width="' . $width . '" customWidth="1"/>';
        }
        $cols .= '</cols>';

        $sheetData = '<sheetData>';
        $sheetData .= '<row r="1" ht="26" customHeight="1">' . $this->inlineCell('A1', $sheet['title'] !== '' ? $sheet['title'] : $sheet['name'], 1) . '</row>';
        $sheetData .= '<row r="2" ht="20" customHeight="1">' . $this->inlineCell('A2', $sheet['subtitle'] !== '' ? $sheet['subtitle'] : ('Gerado em ' . date('d/m/Y H:i')), 2) . '</row>';
        $sheetData .= '<row r="3" ht="8" customHeight="1"></row>';

        $sheetData .= '<row r="4" ht="28" customHeight="1">';
        foreach ($headers as $index => $header) {
            $sheetData .= $this->inlineCell($this->columnName($index + 1) . '4', $header, 3);
        }
        $sheetData .= '</row>';

        foreach ($rows as $rowIndex => $row) {
            $excelRow = 5 + $rowIndex;
            $sheetData .= '<row r="' . $excelRow . '">';
            foreach ($row as $columnIndex => $value) {
                $format = $sheet['formats'][$columnIndex] ?? 'text';
                $cellRef = $this->columnName($columnIndex + 1) . $excelRow;
                $sheetData .= $this->dataCell($cellRef, $value, $format);
            }
            $sheetData .= '</row>';
        }
        $sheetData .= '</sheetData>';

        $mergeCells = '<mergeCells count="2"><mergeCell ref="A1:' . $lastColumn . '1"/><mergeCell ref="A2:' . $lastColumn . '2"/></mergeCells>';
        $filterRef = 'A4:' . $lastColumn . max(4, $lastRow);

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<sheetViews><sheetView workbookViewId="0"><pane ySplit="4" topLeftCell="A5" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
            . '<sheetFormatPr defaultRowHeight="18"/>'
            . $cols
            . $sheetData
            . $mergeCells
            . '<autoFilter ref="' . $filterRef . '"/>'
            . '<pageMargins left="0.3" right="0.3" top="0.5" bottom="0.5" header="0.2" footer="0.2"/>'
            . '<pageSetup orientation="landscape" fitToWidth="1" fitToHeight="0" paperSize="9"/>'
            . '</worksheet>';
    }

    private function dataCell(string $ref, mixed $value, string $format): string
    {
        if ($value === null || $value === '') {
            return '<c r="' . $ref . '" s="4" t="inlineStr"><is><t></t></is></c>';
        }

        if ($format === 'number' && is_numeric($value)) {
            return '<c r="' . $ref . '" s="5"><v>' . $this->numeric($value) . '</v></c>';
        }
        if ($format === 'currency' && is_numeric($value)) {
            return '<c r="' . $ref . '" s="6"><v>' . $this->numeric($value) . '</v></c>';
        }
        if ($format === 'percent' && is_numeric($value)) {
            return '<c r="' . $ref . '" s="7"><v>' . $this->numeric(((float) $value) / 100) . '</v></c>';
        }

        return $this->inlineCell($ref, (string) $value, 4);
    }

    private function inlineCell(string $ref, string $value, int $style): string
    {
        $clean = $this->cleanText($value);
        $preserve = $clean !== trim($clean) ? ' xml:space="preserve"' : '';
        return '<c r="' . $ref . '" s="' . $style . '" t="inlineStr"><is><t' . $preserve . '>' . $this->xml($clean) . '</t></is></c>';
    }

    /** @param list<string> $headers @param list<list<mixed>> $rows @return list<float> */
    private function columnWidths(array $headers, array $rows): array
    {
        $widths = [];
        foreach ($headers as $index => $header) {
            $max = $this->textLength((string) $header);
            $sampleLimit = min(250, count($rows));
            for ($i = 0; $i < $sampleLimit; $i++) {
                $value = $rows[$i][$index] ?? '';
                $length = $this->textLength((string) $value);
                $max = max($max, min($length, 45));
            }
            $widths[] = (float) max(10, min(42, $max + 2));
        }
        return $widths;
    }

    private function columnName(int $number): string
    {
        $name = '';
        while ($number > 0) {
            $number--;
            $name = chr(65 + ($number % 26)) . $name;
            $number = intdiv($number, 26);
        }
        return $name;
    }

    private function sheetName(string $name): string
    {
        $name = preg_replace('~[\\/?:*\[\]]+~u', ' ', trim($name)) ?: 'Planilha';
        $name = preg_replace('/\s+/u', ' ', $name) ?: 'Planilha';
        return $this->textSubstr($name, 0, 31);
    }

    private function safeFilename(string $name): string
    {
        $name = preg_replace('/[^A-Za-z0-9._-]+/', '-', trim($name)) ?: 'comida-na-mesa';
        return trim($name, '-.');
    }

    private function cleanText(string $value): string
    {
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $value) ?? '';
        return $this->textSubstr($value, 0, 32767);
    }

    private function textLength(string $value): int
    {
        return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
    }

    private function textSubstr(string $value, int $start, int $length): string
    {
        return function_exists('mb_substr') ? mb_substr($value, $start, $length, 'UTF-8') : substr($value, $start, $length);
    }

    private function xml(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1 | ENT_SUBSTITUTE, 'UTF-8');
    }

    private function numeric(mixed $value): string
    {
        if (is_int($value)) {
            return (string) $value;
        }
        $float = (float) $value;
        if (!is_finite($float)) {
            return '0';
        }
        return rtrim(rtrim(sprintf('%.10F', $float), '0'), '.');
    }

    private function appProperties(): string
    {
        $titles = '';
        foreach ($this->sheets as $sheet) {
            $titles .= '<vt:lpstr>' . $this->xml($sheet['name']) . '</vt:lpstr>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties" xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes">'
            . '<Application>SIGAS Coari</Application><DocSecurity>0</DocSecurity><ScaleCrop>false</ScaleCrop>'
            . '<HeadingPairs><vt:vector size="2" baseType="variant"><vt:variant><vt:lpstr>Worksheets</vt:lpstr></vt:variant><vt:variant><vt:i4>' . count($this->sheets) . '</vt:i4></vt:variant></vt:vector></HeadingPairs>'
            . '<TitlesOfParts><vt:vector size="' . count($this->sheets) . '" baseType="lpstr">' . $titles . '</vt:vector></TitlesOfParts>'
            . '<Company>Prefeitura de Coari</Company><LinksUpToDate>false</LinksUpToDate><SharedDoc>false</SharedDoc><HyperlinksChanged>false</HyperlinksChanged><AppVersion>1.0</AppVersion>'
            . '</Properties>';
    }

    private function coreProperties(): string
    {
        $now = gmdate('Y-m-d\TH:i:s\Z');
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:dcmitype="http://purl.org/dc/dcmitype/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'
            . '<dc:title>Coari Comida na Mesa</dc:title><dc:creator>SIGAS Coari</dc:creator><cp:lastModifiedBy>SIGAS Coari</cp:lastModifiedBy>'
            . '<dcterms:created xsi:type="dcterms:W3CDTF">' . $now . '</dcterms:created><dcterms:modified xsi:type="dcterms:W3CDTF">' . $now . '</dcterms:modified>'
            . '</cp:coreProperties>';
    }
}

/**
 * Escritor ZIP mínimo em PHP puro para não depender da extensão ext-zip.
 */
final class ComidaMesaZipWriter
{
    /** @var list<array{name:string,data:string,crc:int,compressed:int,uncompressed:int,method:int,time:int,date:int,offset:int}> */
    private array $entries = [];

    private string $body = '';

    public function add(string $name, string $data): void
    {
        $name = str_replace('\\', '/', ltrim($name, '/'));
        if ($name === '' || str_contains($name, '../')) {
            throw new InvalidArgumentException('Nome de arquivo ZIP inválido.');
        }

        [$dosTime, $dosDate] = $this->dosDateTime(time());
        $crc = (int) sprintf('%u', crc32($data));
        $compressedData = function_exists('gzdeflate') ? gzdeflate($data, 6) : false;
        $method = $compressedData === false ? 0 : 8;
        $payload = $compressedData === false ? $data : $compressedData;
        $compressedSize = strlen($payload);
        $uncompressedSize = strlen($data);
        $offset = strlen($this->body);
        $flags = 0x0800;

        $header = pack(
            'VvvvvvVVVvv',
            0x04034b50,
            20,
            $flags,
            $method,
            $dosTime,
            $dosDate,
            $crc,
            $compressedSize,
            $uncompressedSize,
            strlen($name),
            0
        );

        $this->body .= $header . $name . $payload;
        $this->entries[] = [
            'name' => $name,
            'data' => '',
            'crc' => $crc,
            'compressed' => $compressedSize,
            'uncompressed' => $uncompressedSize,
            'method' => $method,
            'time' => $dosTime,
            'date' => $dosDate,
            'offset' => $offset,
        ];
    }

    public function save(string $path): void
    {
        $central = '';
        $flags = 0x0800;

        foreach ($this->entries as $entry) {
            $name = $entry['name'];
            $central .= pack(
                'VvvvvvvVVVvvvvvVV',
                0x02014b50,
                20,
                20,
                $flags,
                $entry['method'],
                $entry['time'],
                $entry['date'],
                $entry['crc'],
                $entry['compressed'],
                $entry['uncompressed'],
                strlen($name),
                0,
                0,
                0,
                0,
                0,
                $entry['offset']
            ) . $name;
        }

        $centralOffset = strlen($this->body);
        $centralSize = strlen($central);
        $count = count($this->entries);
        if ($count > 65535) {
            throw new RuntimeException('Quantidade de arquivos internos excede o limite do exportador ZIP.');
        }

        $end = pack(
            'VvvvvVVv',
            0x06054b50,
            0,
            0,
            $count,
            $count,
            $centralSize,
            $centralOffset,
            0
        );

        $written = file_put_contents($path, $this->body . $central . $end, LOCK_EX);
        if ($written === false) {
            throw new RuntimeException('Não foi possível salvar o arquivo Excel.');
        }
    }

    /** @return array{0:int,1:int} */
    private function dosDateTime(int $timestamp): array
    {
        $d = getdate($timestamp);
        $year = max(1980, (int) $d['year']);
        $dosTime = ((int) $d['hours'] << 11) | ((int) $d['minutes'] << 5) | ((int) floor(((int) $d['seconds']) / 2));
        $dosDate = (($year - 1980) << 9) | ((int) $d['mon'] << 5) | (int) $d['mday'];
        return [$dosTime, $dosDate];
    }
}
