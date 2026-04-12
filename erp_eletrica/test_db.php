<?php
require 'config.php';
$db = \App\Config\Database::getInstance()->getConnection();

echo "<pre>";
echo "<strong>--- TESTE DE BANCO DE DADOS (SEFAZ) ---</strong>\n\n";

try {
    $stmt = $db->prepare("
        INSERT INTO nfe_importadas (filial_id, chave_nfe, fornecedor_cnpj, fornecedor_nome, numero_nota, data_emissao, valor_total, xml_conteudo, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pendente')
        ON DUPLICATE KEY UPDATE 
            xml_conteudo = IF(LENGTH(xml_conteudo) < ?, ?, xml_conteudo),
            data_emissao = ?,
            valor_total = ?
    ");
    $xmlVal = "<nfe>TesteVazio</nfe>";
    $dataEmissao = date('Y-m-d H:i:s');
    $stmt->execute([
        $_SESSION['filial_id'] ?? 1,
        '11111111111111111111111111111111111111111111', 
        '12345678901234',
        'Fornecedor Teste API',
        '12345',
        $dataEmissao,
        99.99,
        $xmlVal,
        strlen($xmlVal),
        $xmlVal,
        $dataEmissao,
        99.99
    ]);
    
    echo "INSERÇÃO BÁSICA EXECUTOU CORRETAMENTE!\n";
    echo "Linhas afetadas: " . $stmt->rowCount() . "\n\n";
    
    // Verificando colunas
    echo "Estrutura da Tabela nfe_importadas:\n";
    $q = $db->query("DESCRIBE nfe_importadas");
    print_r($q->fetchAll(\PDO::FETCH_ASSOC));
    
    // Limpando rastro
    $db->exec("DELETE FROM nfe_importadas WHERE chave_nfe = '11111111111111111111111111111111111111111111'");

} catch (\Exception $e) {
    echo "ERRO DE INSERÇÃO: " . $e->getMessage() . "\n";
}

echo "\n--- FIM DO TESTE ---\n";
echo "</pre>";
