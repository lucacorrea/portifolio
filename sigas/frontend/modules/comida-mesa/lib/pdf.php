<?php

declare(strict_types=1);

/**
 * Gerador PDF simples e autocontido para relatórios tabulares do módulo
 * Coari Comida na Mesa. Não depende de Composer nem de bibliotecas externas.
 */
final class ComidaMesaSimplePdf
{
    private const PAGE_WIDTH = 841.89;   // A4 paisagem em pontos
    private const PAGE_HEIGHT = 595.28;
    private const MARGIN_X = 24.0;
    private const MARGIN_BOTTOM = 28.0;

    /** @var list<string> */
    private array $pages = [];
    private string $stream = '';
    private float $cursorY = 0.0;
    private int $rowIndex = 0;

    /**
     * @param list<string> $metaLines
     * @param list<array{label:string,width:float,align?:string}> $columns
     */
    public function __construct(
        private readonly string $title,
        private readonly array $metaLines,
        private readonly array $columns,
        private readonly string $footerText = 'SIGAS Coari - SEMAS Coari/AM'
    ) {
        $this->newPage();
    }

    /** @param list<string> $values */
    public function addRow(array $values): void
    {
        $fontSize = 6.6;
        $lineHeight = 8.1;
        $wrapped = [];
        $maxLines = 1;

        foreach ($this->columns as $index => $column) {
            $lines = $this->wrapText((string) ($values[$index] ?? ''), max(10.0, (float) $column['width'] - 6.0), $fontSize, 3);
            $wrapped[$index] = $lines;
            $maxLines = max($maxLines, count($lines));
        }

        $rowHeight = max(18.0, 6.0 + ($maxLines * $lineHeight));

        if (($this->cursorY + $rowHeight) > (self::PAGE_HEIGHT - self::MARGIN_BOTTOM)) {
            $this->newPage();
        }

        if (($this->rowIndex % 2) === 1) {
            $this->fillRect(self::MARGIN_X, $this->cursorY, $this->tableWidth(), $rowHeight, 0.982);
        }

        $x = self::MARGIN_X;
        foreach ($this->columns as $index => $column) {
            $width = (float) $column['width'];
            $align = strtoupper((string) ($column['align'] ?? 'L'));
            $lines = $wrapped[$index] ?? [''];

            foreach ($lines as $lineIndex => $line) {
                $top = $this->cursorY + 4.0 + ($lineIndex * $lineHeight);
                $this->text($x + 3.0, $top, $line, $fontSize, false, $align, $width - 6.0);
            }

            $this->line($x + $width, $this->cursorY, $x + $width, $this->cursorY + $rowHeight, 0.90);
            $x += $width;
        }

        $this->line(self::MARGIN_X, $this->cursorY + $rowHeight, self::MARGIN_X + $this->tableWidth(), $this->cursorY + $rowHeight, 0.88);
        $this->cursorY += $rowHeight;
        $this->rowIndex++;
    }

    public function addEmptyMessage(string $message): void
    {
        if (($this->cursorY + 28.0) > (self::PAGE_HEIGHT - self::MARGIN_BOTTOM)) {
            $this->newPage();
        }

        $this->fillRect(self::MARGIN_X, $this->cursorY, $this->tableWidth(), 28.0, 0.975);
        $this->text(self::MARGIN_X + 8.0, $this->cursorY + 9.0, $message, 8.5, true);
        $this->cursorY += 28.0;
    }

    public function output(string $filename, bool $inline = true): never
    {
        $bytes = $this->build();
        $safeName = preg_replace('/[^A-Za-z0-9._-]+/', '-', $filename) ?: 'relatorio.pdf';

        header('Content-Type: application/pdf');
        header('Content-Length: ' . strlen($bytes));
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: public');
        header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . $safeName . '"');
        echo $bytes;
        exit;
    }

    private function newPage(): void
    {
        if ($this->stream !== '') {
            $this->pages[] = $this->stream;
        }

        $this->stream = '';
        $this->rowIndex = 0;
        $this->cursorY = 22.0;

        $this->text(self::MARGIN_X, $this->cursorY, $this->title, 13.0, true);
        $this->cursorY += 18.0;

        foreach ($this->metaLines as $metaLine) {
            foreach ($this->wrapText($metaLine, $this->tableWidth(), 7.2, 3) as $line) {
                $this->text(self::MARGIN_X, $this->cursorY, $line, 7.2, false);
                $this->cursorY += 9.0;
            }
        }

        $this->cursorY += 3.0;
        $this->line(self::MARGIN_X, $this->cursorY, self::MARGIN_X + $this->tableWidth(), $this->cursorY, 0.72);
        $this->cursorY += 5.0;
        $this->drawTableHeader();
    }

    private function drawTableHeader(): void
    {
        $height = 20.0;
        $this->fillRect(self::MARGIN_X, $this->cursorY, $this->tableWidth(), $height, 0.93);

        $x = self::MARGIN_X;
        foreach ($this->columns as $column) {
            $width = (float) $column['width'];
            $align = strtoupper((string) ($column['align'] ?? 'L'));
            $this->text($x + 3.0, $this->cursorY + 6.0, (string) $column['label'], 6.8, true, $align, $width - 6.0);
            $this->line($x + $width, $this->cursorY, $x + $width, $this->cursorY + $height, 0.83);
            $x += $width;
        }

        $this->line(self::MARGIN_X, $this->cursorY, self::MARGIN_X + $this->tableWidth(), $this->cursorY, 0.83);
        $this->line(self::MARGIN_X, $this->cursorY + $height, self::MARGIN_X + $this->tableWidth(), $this->cursorY + $height, 0.83);
        $this->cursorY += $height;
    }

    private function tableWidth(): float
    {
        $total = 0.0;
        foreach ($this->columns as $column) {
            $total += (float) $column['width'];
        }
        return $total;
    }

    private function text(float $x, float $top, string $text, float $size, bool $bold = false, string $align = 'L', float $width = 0.0): void
    {
        $encoded = $this->pdfString($text);
        $align = strtoupper($align);
        $drawX = $x;

        if ($width > 0.0 && $align !== 'L') {
            $textWidth = $this->estimatedTextWidth($text, $size);
            if ($align === 'R') {
                $drawX = max($x, $x + $width - $textWidth);
            } elseif ($align === 'C') {
                $drawX = max($x, $x + (($width - $textWidth) / 2));
            }
        }

        $baseline = self::PAGE_HEIGHT - $top - $size;
        $font = $bold ? 'F2' : 'F1';
        $this->stream .= "BT /{$font} " . $this->num($size) . " Tf 0 g 1 0 0 1 "
            . $this->num($drawX) . ' ' . $this->num($baseline) . " Tm ({$encoded}) Tj ET\n";
    }

    private function fillRect(float $x, float $top, float $width, float $height, float $gray): void
    {
        $y = self::PAGE_HEIGHT - $top - $height;
        $this->stream .= 'q ' . $this->num($gray) . ' g '
            . $this->num($x) . ' ' . $this->num($y) . ' ' . $this->num($width) . ' ' . $this->num($height)
            . " re f Q\n";
    }

    private function line(float $x1, float $top1, float $x2, float $top2, float $gray): void
    {
        $y1 = self::PAGE_HEIGHT - $top1;
        $y2 = self::PAGE_HEIGHT - $top2;
        $this->stream .= 'q ' . $this->num($gray) . ' G 0.45 w '
            . $this->num($x1) . ' ' . $this->num($y1) . ' m '
            . $this->num($x2) . ' ' . $this->num($y2) . " l S Q\n";
    }

    /** @return list<string> */
    private function wrapText(string $text, float $width, float $fontSize, int $maxLines): array
    {
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
        if ($text === '') {
            return [''];
        }

        $maxChars = max(4, (int) floor($width / max(1.0, $fontSize * 0.50)));
        $words = preg_split('/\s+/u', $text) ?: [$text];
        $lines = [];
        $line = '';

        foreach ($words as $word) {
            if (mb_strlen($word, 'UTF-8') > $maxChars) {
                if ($line !== '') {
                    $lines[] = $line;
                    $line = '';
                }
                while (mb_strlen($word, 'UTF-8') > $maxChars) {
                    $lines[] = mb_substr($word, 0, $maxChars, 'UTF-8');
                    $word = mb_substr($word, $maxChars, null, 'UTF-8');
                    if (count($lines) >= $maxLines) {
                        break 2;
                    }
                }
            }

            $candidate = $line === '' ? $word : $line . ' ' . $word;
            if (mb_strlen($candidate, 'UTF-8') <= $maxChars) {
                $line = $candidate;
                continue;
            }

            if ($line !== '') {
                $lines[] = $line;
            }
            $line = $word;

            if (count($lines) >= $maxLines) {
                break;
            }
        }

        if ($line !== '' && count($lines) < $maxLines) {
            $lines[] = $line;
        }

        if ($lines === []) {
            $lines[] = mb_substr($text, 0, $maxChars, 'UTF-8');
        }

        $joined = implode(' ', $lines);
        if (mb_strlen($joined, 'UTF-8') < mb_strlen($text, 'UTF-8')) {
            $last = count($lines) - 1;
            $limit = max(1, $maxChars - 3);
            $lines[$last] = rtrim(mb_substr($lines[$last], 0, $limit, 'UTF-8')) . '...';
        }

        return array_slice($lines, 0, $maxLines);
    }

    private function estimatedTextWidth(string $text, float $fontSize): float
    {
        return mb_strlen($text, 'UTF-8') * $fontSize * 0.48;
    }

    private function pdfString(string $text): string
    {
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text) ?? $text;
        $encoded = @iconv('UTF-8', 'Windows-1252//TRANSLIT', $text);
        if ($encoded === false) {
            $encoded = preg_replace('/[^\x20-\x7E]/', '?', $text) ?? $text;
        }

        return str_replace(
            ["\\", "(", ")", "\r", "\n"],
            ["\\\\", "\\(", "\\)", '', ' '],
            $encoded
        );
    }

    private function num(float $number): string
    {
        return rtrim(rtrim(number_format($number, 2, '.', ''), '0'), '.');
    }

    private function build(): string
    {
        if ($this->stream !== '') {
            $this->pages[] = $this->stream;
            $this->stream = '';
        }

        if ($this->pages === []) {
            $this->pages[] = '';
        }

        $pageCount = count($this->pages);
        foreach ($this->pages as $index => &$pageStream) {
            $footerTop = self::PAGE_HEIGHT - 17.0;
            $pageStream .= 'q 0.82 G 0.4 w '
                . $this->num(self::MARGIN_X) . ' ' . $this->num(20.0) . ' m '
                . $this->num(self::PAGE_WIDTH - self::MARGIN_X) . ' ' . $this->num(20.0) . " l S Q\n";

            $footerY = 9.0;
            $left = $this->pdfString($this->footerText);
            $right = $this->pdfString('Pagina ' . ($index + 1) . ' de ' . $pageCount);
            $pageStream .= "BT /F1 6.5 Tf 0.25 g 1 0 0 1 "
                . $this->num(self::MARGIN_X) . ' ' . $this->num($footerY) . " Tm ({$left}) Tj ET\n";
            $rightX = self::PAGE_WIDTH - self::MARGIN_X - 72.0;
            $pageStream .= "BT /F1 6.5 Tf 0.25 g 1 0 0 1 "
                . $this->num($rightX) . ' ' . $this->num($footerY) . " Tm ({$right}) Tj ET\n";
        }
        unset($pageStream);

        $objects = [];
        $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';

        $kids = [];
        for ($i = 0; $i < $pageCount; $i++) {
            $kids[] = (6 + ($i * 2)) . ' 0 R';
        }
        $objects[2] = '<< /Type /Pages /Kids [' . implode(' ', $kids) . '] /Count ' . $pageCount . ' >>';
        $objects[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        $objects[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';

        foreach ($this->pages as $index => $pageStream) {
            $contentId = 5 + ($index * 2);
            $pageId = 6 + ($index * 2);
            $objects[$contentId] = '<< /Length ' . strlen($pageStream) . " >>\nstream\n" . $pageStream . "endstream";
            $objects[$pageId] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 '
                . $this->num(self::PAGE_WIDTH) . ' ' . $this->num(self::PAGE_HEIGHT)
                . '] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents '
                . $contentId . ' 0 R >>';
        }

        ksort($objects);
        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [0 => 0];

        foreach ($objects as $id => $object) {
            $offsets[$id] = strlen($pdf);
            $pdf .= $id . " 0 obj\n" . $object . "\nendobj\n";
        }

        $xrefOffset = strlen($pdf);
        $maxId = max(array_keys($objects));
        $pdf .= "xref\n0 " . ($maxId + 1) . "\n";
        $pdf .= "0000000000 65535 f \n";
        for ($id = 1; $id <= $maxId; $id++) {
            $pdf .= sprintf('%010d 00000 n ', $offsets[$id] ?? 0) . "\n";
        }

        $pdf .= "trailer\n<< /Size " . ($maxId + 1) . " /Root 1 0 R >>\n";
        $pdf .= "startxref\n" . $xrefOffset . "\n%%EOF";

        return $pdf;
    }
}
