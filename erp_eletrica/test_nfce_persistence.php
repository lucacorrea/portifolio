<?php
require_once 'config.php';
require_once 'autoloader.php';

use App\Services\NfceService;

echo "<h1>🧪 Verificação de Persistência NFC-e (27 Campos)</h1>";

try {
    $service = new NfceService();
    $db = \App\Config\Database::getInstance()->getConnection();

    // 1. Test data with 27 fields
    $testData = [
        'cnpj' => '12.345.678/0001-99',
        'razao_social' => 'EMPRESA TESTE LTDA',
        'nome_fantasia' => 'TESTE FANTASIA',
        'inscricao_estadual' => '1234567890',
        'inscricao_municipal' => '987654321',
        'cep' => '01001-000',
        'logradouro' => 'Praça da Sé',
        'numero_endereco' => '100',
        'complemento' => 'Sala 10',
        'bairro' => 'Sé',
        'cidade' => 'São Paulo',
        'uf' => 'SP',
        'codigo_uf' => 35,
        'codigo_municipio' => '3550308',
        'telefone' => '(11) 99999-9999',
        'ambiente' => 'homologacao',
        'regime_tributario' => 1,
        'serie_nfce' => 2,
        'ultimo_numero_nfce' => 150,
        'csc' => 'ABC123DEF456',
        'csc_id' => '000001',
        'tipo_emissao' => 1,
        'finalidade' => 1,
        'ind_pres' => 1,
        'tipo_impressao' => 4,
        'certificado_path' => 'test_cert.pfx'
    ];

    echo "<p>Guardando configuração GLOBAL...</p>";
    $service->saveConfig($testData, true);
    
    echo "<p>Recuperando configuração GLOBAL...</p>";
    $stmt = $db->query("SELECT * FROM sefaz_config LIMIT 1");
    $saved = $stmt->fetch(PDO::FETCH_ASSOC);

    $missing = [];
    foreach ($testData as $key => $val) {
        if (!isset($saved[$key])) {
            $missing[] = $key;
        } elseif ($saved[$key] != $val) {
             echo "<p style='color:orange;'>⚠️ Campo '$key' divergiu: Esperado [$val], Recebido [" . $saved[$key] . "]</p>";
        }
    }

    if (empty($missing)) {
        echo "<p style='color:green;'>✅ Todos os campos suportados pela tabela sefaz_config!</p>";
    } else {
        echo "<p style='color:red;'>❌ Campos AUSENTES na tabela: " . implode(', ', $missing) . "</p>";
    }

    echo "<hr><h2>Teste de Mesclagem (Filial vs Global)</h2>";
    // Create a dummy filial 1 if not exists
    $db->exec("INSERT IGNORE INTO filiais (id, nome) VALUES (1, 'Filial Teste')");
    
    $filialData = [
        'id' => 1,
        'serie_nfce' => 55,
        'ultimo_numero_nfce' => 999
    ];
    $service->saveConfig($filialData, false);
    
    $merged = $service->getConfig(1);
    
    if ($merged['serie_nfce'] == 55 && $merged['cnpj'] == '12.345.678/0001-99') {
        echo "<p style='color:green;'>✅ Lógica de mesclagem FUNCIONAL (Filial sobrepõe Global quando definida).</p>";
    } else {
        echo "<p style='color:red;'>❌ Erro na lógica de mesclagem.</p>";
    }

} catch (Exception $e) {
    echo "<p style='color:red;'>❌ Erro no Teste: " . $e->getMessage() . "</p>";
}
