<?php
class SimplePdf {
  private array $objects = [];
  private array $pages = [];
  private string $fontObj = '5 0 R';

  public function addPage(array $lines, string $title = 'Documento') : void {
    $content = "BT\n/F1 12 Tf\n50 790 Td\n";
    foreach ($lines as $index => $line) {
      $safe = $this->escape($line);
      if ($index === 0) {
        $content .= "/F1 18 Tf ($safe) Tj\n0 -28 Td\n/F1 11 Tf\n";
      } else {
        $content .= "($safe) Tj\n0 -18 Td\n";
      }
    }
    $content .= "ET";
    $this->pages[] = $content;
  }

  public function output(string $filePath) : void {
    $this->objects = [];
    $this->objects[] = '<< /Type /Catalog /Pages 2 0 R >>';
    $kids = [];
    $pageObjects = [];
    $contentObjects = [];
    $objNum = 3;
    foreach ($this->pages as $content) {
      $pageObj = $objNum++;
      $contentObj = $objNum++;
      $kids[] = $pageObj . ' 0 R';
      $pageObjects[] = [$pageObj, "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 5 0 R >> >> /Contents {$contentObj} 0 R >>"];
      $contentObjects[] = [$contentObj, "<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream"];
    }
    $pagesObj = '<< /Type /Pages /Kids [' . implode(' ', $kids) . '] /Count ' . count($kids) . ' >>';
    $fontObj = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';

    $all = [];
    $all[1] = $this->objects[0];
    $all[2] = $pagesObj;
    $all[5] = $fontObj;
    foreach ($pageObjects as [$num, $obj]) { $all[$num] = $obj; }
    foreach ($contentObjects as [$num, $obj]) { $all[$num] = $obj; }
    ksort($all);

    $pdf = "%PDF-1.4\n";
    $offsets = [0];
    foreach ($all as $num => $obj) {
      $offsets[$num] = strlen($pdf);
      $pdf .= "{$num} 0 obj\n{$obj}\nendobj\n";
    }
    $xref = strlen($pdf);
    $max = max(array_keys($all));
    $pdf .= "xref\n0 " . ($max + 1) . "\n";
    $pdf .= "0000000000 65535 f \n";
    for ($i = 1; $i <= $max; $i++) {
      $pdf .= sprintf('%010d 00000 n ', $offsets[$i] ?? 0) . "\n";
    }
    $pdf .= "trailer\n<< /Size " . ($max + 1) . " /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF";
    file_put_contents($filePath, $pdf);
  }

  private function escape(string $text): string {
    $text = iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', $text);
    return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
  }
}
