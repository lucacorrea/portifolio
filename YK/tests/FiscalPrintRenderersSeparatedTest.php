<?php

declare(strict_types=1);

$service = file_get_contents(
    dirname(__DIR__) . '/src/Fiscal/Service/FiscalDocumentPrintService.php'
);
$danfe = file_get_contents(
    dirname(__DIR__) . '/src/Fiscal/Print/ReadableDanfe.php'
);
$danfce = file_get_contents(
    dirname(__DIR__) . '/src/Fiscal/Print/ReadableDanfce.php'
);

if (!is_string($service) || !is_string($danfe) || !is_string($danfce)) {
    throw new RuntimeException('Arquivos de impressão não encontrados.');
}

$checks = [
    'new ReadableDanfe' => $service,
    'new ReadableDanfce' => $service,
    'if ($model === \'55\')' => $service,
    '} elseif ($model === \'65\')' => $service,
    'TOP_MARGIN_MM = 8' => $danfe,
    'LEFT_MARGIN_MM = 8' => $danfe,
    'setDefaultFont(\'arial\')' => $danfe,
    '\'A4\'' => $danfe,
    'QR_SIZE_MM = 30.0' => $danfce,
    'FONT_PRODUCT_CODE = 9' => $danfce,
    'setMargins(5)' => $danfce,
];

foreach ($checks as $needle => $source) {
    if (!str_contains($source, $needle)) {
        throw new RuntimeException(
            'Configuração esperada ausente: ' . $needle
        );
    }
}

echo "FiscalPrintRenderersSeparatedTest: OK\n";
