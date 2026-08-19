<?php

declare(strict_types=1);

$page = file_get_contents(
    dirname(__DIR__) . '/pages/ordens-servico.php'
);

if (!is_string($page)) {
    throw new RuntimeException(
        'Página de ordens de serviço não encontrada.'
    );
}

$checks = [
    '$authorizedDanfeDocument = null' => $page,
    '$authorizedDanfceDocument = null' => $page,
    "\$fiscalModel === '55'" => $page,
    "\$fiscalModel === '65'" => $page,
    'Imprimir DANFE autorizada' => $page,
    'Imprimir DANFC-e autorizada' => $page,
    'NF-e 55 não autorizada' => $page,
    'NFC-e 65 não autorizada' => $page,
    'nota-fiscal-imprimir.php?id=' => $page,
];

foreach ($checks as $needle => $source) {
    if (!str_contains($source, $needle)) {
        throw new RuntimeException(
            'Configuração esperada ausente: ' . $needle
        );
    }
}

echo "OsDanfeDanfceButtonsTest: OK\n";
