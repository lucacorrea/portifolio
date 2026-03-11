<?php
require_once 'config.php';

use App\Services\SefazXmlService;

$fiscal = [
    'razao_social' => 'EMPRESA TESTE LTDA',
    'cnpj' => '12.345.678/0001-90',
    'inscricao_estadual' => '111222333',
    'ambiente' => 2,
    'uf' => 'AM',
    'codigo_uf' => '13',
    'codigo_municipio' => '1301209',
    'municipio' => 'MANAUS',
    'logradouro' => 'RUA TESTE',
    'numero' => '100',
    'bairro' => 'CENTRO',
    'cep' => '69000-000',
    'crt' => '1',
    'serie_nfce' => '2'
];

$sale = [
    'id' => 123,
    'valor_total' => 50.00,
    'forma_pagamento' => 'dinheiro',
    'items' => [
        [
            'produto_id' => 1,
            'nome' => 'PRODUTO TESTE',
            'quantidade' => 1,
            'preco_unitario' => 50.00,
            'ncm' => '21069090',
            'cfop_interno' => '5102',
            'origem' => '0'
        ]
    ]
];

try {
    $service = new SefazXmlService();
    $result = $service->generateNFCe($sale, $fiscal);
    
    echo "--- XML GENERATED ---\n";
    echo $result['xml'] . "\n\n";
    
    if (strpos($result['xml'], '<cUF>13</cUF>') !== false &&
        strpos($result['xml'], '<cMunFG>1301209</cMunFG>') !== false &&
        strpos($result['xml'], '<UF>AM</UF>') !== false) {
        echo "VERIFICATION SUCCESS: XML contains dynamic AM/Manaus codes.\n";
    } else {
        echo "VERIFICATION FAILED: XML still contains SP/hardcoded codes or missing dynamic ones.\n";
    }

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
