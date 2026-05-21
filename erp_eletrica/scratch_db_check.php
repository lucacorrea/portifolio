<?php
require_once 'config.php';
$db = \App\Config\Database::getInstance()->getConnection();
try {
    echo "--- CLIENTES ---\n";
    $stmt = $db->query("DESCRIBE clientes");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    print_r($columns);

    echo "\n--- PRE_VENDAS ---\n";
    $stmt = $db->query("DESCRIBE pre_vendas");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    print_r($columns);
} catch (Exception $e) {
    echo $e->getMessage();
}

