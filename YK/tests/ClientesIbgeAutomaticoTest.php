<?php

declare(strict_types=1);

$page = file_get_contents(
    dirname(__DIR__) . '/pages/clientes.php'
);

if (!is_string($page)) {
    throw new RuntimeException('pages/clientes.php não encontrado.');
}

$checks = [
    'name="codigo_municipio_ibge"',
    'servicodados.ibge.gov.br/api/v1/localidades/estados/',
    'js-client-uf',
    'js-client-city',
    'js-client-municipality-code',
    '1301209',
    'loadMunicipalitiesForForm',
];

foreach ($checks as $needle) {
    if (!str_contains($page, $needle)) {
        throw new RuntimeException(
            'Alteração ausente: ' . $needle
        );
    }
}

echo "ClientesIbgeAutomaticoTest: OK\n";
