<?php

declare(strict_types=1);

$renderer = file_get_contents(
    dirname(__DIR__)
    . '/src/Fiscal/Print/ReadableDanfce.php'
);

$service = file_get_contents(
    dirname(__DIR__)
    . '/src/Fiscal/Service/FiscalDocumentPrintService.php'
);

if (!is_string($renderer) || !is_string($service)) {
    throw new RuntimeException(
        'Arquivos de impressão não encontrados.'
    );
}

$checks = [
    'QR_SIZE_MM = 30.0' => $renderer,
    'FONT_NORMAL = 8' => $renderer,
    'FONT_PRODUCT_CODE = 9' => $renderer,
    "'style' => 'B'" => $renderer,
    'setFont(\'arial\')' => $renderer,
    'protected function blocoVIII' => $renderer,
    'new ReadableDanfce' => $service,
    "['100', '120', '150']" => $service,
];

foreach ($checks as $needle => $source) {
    if (!str_contains($source, $needle)) {
        throw new RuntimeException(
            'Configuração esperada não encontrada: '
            . $needle
        );
    }
}

echo "ReadableDanfcePatchTest: OK\n";
