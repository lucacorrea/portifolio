<?php
require 'config.php';
$db = \App\Config\Database::getInstance()->getConnection();

echo "<pre>";
echo "<strong>--- TESTE DE INSERÇÃO FINAL ---</strong>\n\n";

try {
    // Tenta criar index único caso não exista
    try {
        $db->exec("ALTER TABLE nfe_importadas ADD UNIQUE INDEX uk_chave_acesso (chave_acesso)");
    } catch (\Exception $e) {}

    $stmt = $db->prepare("
        INSERT INTO nfe_importadas (filial_id, chave_acesso, fornecedor_cnpj, fornecedor_nome, numero_nota, data_emissao, valor_total, xml, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pendente')
        ON DUPLICATE KEY UPDATE 
            xml = IF(LENGTH(xml) < ?, ?, xml),
            data_emissao = ?,
            valor_total = ?
    ");
    $xmlVal = "<nfe>NovaTentativaCorrigida</nfe>";
    $dataEmissao = date('Y-m-d H:i:s');
    $stmt->execute([
        $_SESSION['filial_id'] ?? 1,
        '22222222222222222222222222222222222222222222', 
        '12345678901234',
        'Fornecedor Corrigido',
        '99999',
        $dataEmissao,
        500.00,
        $xmlVal,
        strlen($xmlVal),
        $xmlVal,
        $dataEmissao,
        500.00
    ]);
    
    echo "INSERÇÃO BÁSICA COM COLUNAS CORRIGIDAS EXECUTOU CORRETAMENTE!\n";
    echo "Linhas afetadas: " . $stmt->rowCount() . "\n\n";
    
    $q = $db->query("SELECT id, chave_acesso, xml FROM nfe_importadas WHERE chave_acesso = '22222222222222222222222222222222222222222222'");
    print_r($q->fetch(\PDO::FETCH_ASSOC));
    
    // Cleanup
    $db->exec("DELETE FROM nfe_importadas WHERE chave_acesso = '22222222222222222222222222222222222222222222'");

} catch (\Exception $e) {
    echo "Erro persistente: " . $e->getMessage() . "\n";
}

echo "</pre>";

