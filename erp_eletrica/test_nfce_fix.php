<?php
// test_nfce_fix.php
require_once __DIR__ . '/autoloader.php';

use App\Services\SefazXmlService;

$xmlService = new SefazXmlService();

$saleData = [
    'id' => 123,
    'valor_total' => 100.00,
    'desconto_total' => 10.00,
    'forma_pagamento' => 'pix',
    'cliente_id' => 1,
    'nome_cliente_avulso' => null,
    'cliente_nome' => 'JOAO TESTE',
    'cpf_cnpj' => '12345678901',
    'items' => [
        [
            'produto_id' => 1,
            'nome' => 'PRODUTO TESTE',
            'quantidade' => 1,
            'preco_unitario' => 110.00,
            'ncm' => '21069090',
            'cfop_interno' => '5102',
            'unidade' => 'UN',
            'origem' => 0
        ]
    ]
];

$fiscal = [
    'codigo_uf' => '35',
    'ambiente' => 2,
    'serie_nfce' => 1,
    'cnpj' => '12345678000199',
    'razao_social' => 'EMPRESA TESTE',
    'logradouro' => 'RUA TESTE',
    'municipio' => 'SAO PAULO',
    'uf' => 'SP',
    'codigo_municipio' => '3550308',
    'inscricao_estadual' => '123456789',
    'crt' => '1'
];

$result = $xmlService->generateNFCe($saleData, $fiscal);
$xml = $result['xml'];

echo "XML gerado com sucesso.\n";

// Verificando <dest>
if (strpos($xml, '<dest>') !== false && strpos($xml, '<xNome>JOAO TESTE</xNome>') !== false) {
    echo "[OK] Elemento <dest> e <xNome> encontrados no XML.\n";
} else {
    echo "[ERRO] Elemento <dest> ou <xNome> NÃO encontrados no XML.\n";
}

// Verificando <tPag> para 'pix' (deve ser '17')
if (strpos($xml, '<tPag>17</tPag>') !== false) {
    echo "[OK] Forma de pagamento 'pix' mapeada corretamente para '17'.\n";
} else {
    echo "[ERRO] Forma de pagamento 'pix' NÃO mapeada corretamente.\n";
}

$saleData['forma_pagamento'] = 'cartao credito';
$result = $xmlService->generateNFCe($saleData, $fiscal);
$xml = $result['xml'];
if (strpos($xml, '<tPag>03</tPag>') !== false) {
    echo "[OK] Forma de pagamento 'cartao credito' mapeada corretamente para '03'.\n";
} else {
    echo "[ERRO] Forma de pagamento 'cartao credito' NÃO mapeada corretamente.\n";
}

echo "\n--- FIM DO TESTE ---\n";
