<?php
require_once __DIR__ . '/config.php';
header('Content-Type: text/plain');

$venda_id = 113;

try {
    $pdo = \App\Config\Database::getInstance()->getConnection();
    
    echo "--- CHECKING SALE #$venda_id ---\n";
    
    // Check Vendas
    $stV = $pdo->prepare("SELECT * FROM vendas WHERE id = ?");
    $stV->execute([$venda_id]);
    $venda = $stV->fetch(PDO::FETCH_ASSOC);
    if ($venda) {
        echo "Sale Found in 'vendas':\n";
        print_r($venda);
    } else {
        echo "Sale NOT FOUND in 'vendas'.\n";
    }
    
    // Check Vendas_itens
    $stI = $pdo->prepare("SELECT * FROM vendas_itens WHERE venda_id = ?");
    $stI->execute([$venda_id]);
    $itens = $stI->fetchAll(PDO::FETCH_ASSOC);
    echo "\nItems in 'vendas_itens': " . count($itens) . "\n";
    print_r($itens);
    
    // Check Itens_venda (just in case they exist)
    try {
        $stI2 = $pdo->prepare("SELECT * FROM itens_venda WHERE venda_id = ?");
        $stI2->execute([$venda_id]);
        $itens2 = $stI2->fetchAll(PDO::FETCH_ASSOC);
        echo "\nItems in 'itens_venda' (Legacy?): " . count($itens2) . "\n";
        print_r($itens2);
    } catch (Exception $e) {
        echo "\nTable 'itens_venda' does not exist.\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
