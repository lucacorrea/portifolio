<?php

declare(strict_types=1);

$service = file_get_contents(
    dirname(__DIR__)
    . '/src/Fiscal/Service/FiscalDocumentService.php'
);

$builder = file_get_contents(
    dirname(__DIR__)
    . '/src/Fiscal/Service/FiscalDocumentXmlBuilder.php'
);

$page = file_get_contents(
    dirname(__DIR__)
    . '/pages/ordens-servico.php'
);

if (
    !is_string($service)
    || !is_string($builder)
    || !is_string($page)
) {
    throw new RuntimeException(
        'Arquivos da correção não encontrados.'
    );
}

$checks = [
    'NFC-e - modelo 65' => $service,
    'NF-e - modelo 55' => $service,
    'hasUsableOptionalNfceAddress' => $builder,
    '$model === \'55\'' => $builder,
    'Imprimir DANFE' => $page,
    'Imprimir DANFC-e' => $page,
    'NF-e de peças' => $page,
    'NFC-e de peças' => $page,
];

foreach ($checks as $needle => $source) {
    if (!str_contains($source, $needle)) {
        throw new RuntimeException(
            'Regra esperada ausente: '
            . $needle
        );
    }
}

if (
    str_contains(
        $service,
        'Complete ou remova o endereço parcial do consumidor da NFC-e.'
    )
) {
    throw new RuntimeException(
        'A validação antiga que bloqueava NFC-e por endereço parcial ainda existe.'
    );
}

echo "ClienteNfeNfceCompatibilityTest: OK\n";
