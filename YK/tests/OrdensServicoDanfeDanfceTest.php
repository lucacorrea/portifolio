<?php

declare(strict_types=1);

$page = file_get_contents(
    dirname(__DIR__) . '/pages/ordens-servico.php'
);

if (!is_string($page)) {
    throw new RuntimeException('Página de OS não encontrada.');
}

$required = [
    '$authorizedDanfeDocument = null',
    '$authorizedDanfceDocument = null',
    'if ($fiscalModel === \'55\')',
    '} elseif ($fiscalModel === \'65\')',
    'Imprimir DANFE',
    'Imprimir DANFC-e',
    'NF-e 55 não autorizada',
    'NFC-e 65 não autorizada',
    'nota-fiscal-imprimir.php?id=',
];

foreach ($required as $needle) {
    if (!str_contains($page, $needle)) {
        throw new RuntimeException(
            'Alteração fiscal ausente: ' . $needle
        );
    }
}

echo "OrdensServicoDanfeDanfceTest: OK\n";
