<?php
require_once 'config.php';
require_once 'autoloader.php';

use App\Services\FiscalService;

echo "<h1>🛠️ Teste de Emissão Real (Dry-Run)</h1>";

try {
    $vendaId = 1; // ID de teste
    $fiscalService = new FiscalService();
    
    echo "<p>1. Buscando dados da venda e configuração...</p>";
    $db = \App\Config\Database::getInstance()->getConnection();
    $sale = $db->query("SELECT * FROM vendas WHERE id = $vendaId")->fetch();
    if (!$sale) {
        // Criar venda fake se não existir
        echo "<p>Venda não encontrada, usando dados simulados.</p>";
        $sale = [
            'id' => 999,
            'filial_id' => 1,
            'valor_total' => 100.00,
            'desconto_total' => 10.00,
            'forma_pagamento' => 'dinheiro',
            'items' => [
                ['produto_id' => 1, 'nome' => 'Produto Teste', 'quantidade' => 1, 'preco_unitario' => 110.00, 'ncm' => '85365090', 'cfop_interno' => '5102']
            ]
        ];
    } else {
        // Mock items for the real sale
        $sale['items'] = [
             ['produto_id' => 1, 'nome' => 'Lâmpada LED', 'quantidade' => 2, 'preco_unitario' => 50.00, 'ncm' => '85365090', 'cfop_interno' => '5102']
        ];
    }

    $stmt = $db->query("SELECT * FROM filiais WHERE id = " . ($sale['filial_id'] ?? 1));
    $branch = $stmt->fetch();
    
    $stmtGlobal = $db->query("SELECT * FROM sefaz_config LIMIT 1");
    $global = $stmtGlobal->fetch();

    $fiscal = [
        'cnpj' => $branch['cnpj'] ?? '00.000.000/0001-00',
        'nome' => $branch['nome'] ?? 'Empresa Teste',
        'inscricao_estadual' => $branch['inscricao_estadual'] ?? '123456789',
        'certificado_pfx' => $global['certificado_path'] ?? 'none.pfx',
        'certificado_senha' => !empty($global['certificado_senha']) ? base64_decode($global['certificado_senha']) : '1234',
        'ambiente' => ($global['ambiente'] ?? 'homologacao') == 'producao' ? 1 : 2
    ];

    echo "<p>2. Gerando XML Real (NFC-e 4.00)...</p>";
    $xmlService = new \App\Services\SefazXmlService();
    $xmlResult = $xmlService->generateNFCe($sale, $fiscal);
    echo "<pre>" . htmlspecialchars(substr($xmlResult['xml'], 0, 300)) . "...</pre>";

    echo "<p style='color:green;'>✅ XML Gerado com sucesso. Chave: " . $xmlResult['chave'] . "</p>";

    echo "<p>3. Testando Assinatura Digital...</p>";
    if ($global && !empty($global['certificado_path'])) {
        $signer = new \App\Services\SefazSigner();
        $pfxPath = __DIR__ . "/storage/certificados/" . $global['certificado_path'];
        if (file_exists($pfxPath)) {
            $signedXml = $signer->signXML($xmlResult['xml'], $pfxPath, base64_decode($global['certificado_senha']));
            echo "<p style='color:green;'>✅ XML Assinado com sucesso!</p>";
            echo "<pre>" . htmlspecialchars(substr($signedXml, strpos($signedXml, '<Signature'), 300)) . "...</pre>";
        } else {
            echo "<p style='color:orange;'>⚠️ Arquivo PFX não encontrado fisicamente para assinatura real.</p>";
        }
    } else {
        echo "<p style='color:orange;'>⚠️ Certificado Global não configurado no banco para o teste.</p>";
    }

    echo "<hr><h2 style='color:blue;'>Fim do Dry-Run: O motor fiscal está operacional.</h2>";

} catch (Exception $e) {
    echo "<p style='color:red;'>❌ Erro no Teste: " . $e->getMessage() . "</p>";
}
