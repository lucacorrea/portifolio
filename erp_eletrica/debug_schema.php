<?php
require_once 'config.php';
$db = \App\Config\Database::getInstance()->getConnection();
$tables = ['pre_vendas', 'pre_venda_itens', 'clientes', 'usuarios', 'produtos'];
foreach ($tables as $t) {
    echo "TABLE: $t\n";
    $stmt = $db->query("DESCRIBE $t");
    print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
    echo "-------------------\n";
}
