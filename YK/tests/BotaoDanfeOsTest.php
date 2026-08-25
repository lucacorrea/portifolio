<?php

declare(strict_types=1);

$page = file_get_contents(
    dirname(__DIR__) . '/pages/ordens-servico.php'
);

if (!is_string($page)) {
    throw new RuntimeException('Arquivo não encontrado.');
}

$checks = [
    '$authorizedDanfeDocument = null',
    '$authorizedDanfceDocument = null',
    "if (\$fiscalModel === '55')",
    "} elseif (\$fiscalModel === '65')",
    'Imprimir DANFE',
    'Imprimir DANFC-e autorizada',
    'nota-fiscal-imprimir.php?id=',
    'NF-e 55 não autorizada',
];

foreach ($checks as $needle) {
    if (!str_contains($page, $needle)) {
        throw new RuntimeException(
            'Alteração esperada ausente: ' . $needle
        );
    }
}

echo "BotaoDanfeOsTest: OK\n";
