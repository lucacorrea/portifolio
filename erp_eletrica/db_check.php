<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "--- DB DIAGNOSTIC ---\n";

$confs = [
    'u784961086_pdv' => ['host'=>'localhost', 'user'=>'u784961086_pdv', 'pass'=>'Uv$1NhLlkRub'],
    'u920914488_ERP' => ['host'=>'localhost', 'user'=>'u920914488_ERP', 'pass'=>'N8r=$&Wrs$'],
    'u922223647_erp' => ['host'=>'localhost', 'user'=>'u922223647_erp', 'pass'=>'*V5z7GqLfa~E']
];

foreach ($confs as $dbName => $c) {
    echo "Testing $dbName: ";
    try {
        $pdo = new PDO("mysql:host={$c['host']};dbname=$dbName", $c['user'], $c['pass']);
        echo "SUCCESS. ";
        $q = $pdo->query("SHOW TABLES");
        $tables = $q->fetchAll(PDO::FETCH_COLUMN);
        echo "Tables: " . implode(', ', $tables) . "\n";
        
        if (in_array('vendas', $tables)) {
            $count = $pdo->query("SELECT COUNT(*) FROM vendas")->fetchColumn();
            echo "  - Vendas count: $count\n";
        }
        if (in_array('itens_venda', $tables)) {
            echo "  - Has itens_venda\n";
        }
        if (in_array('vendas_itens', $tables)) {
            echo "  - Has vendas_itens\n";
        }
    } catch (Exception $e) {
        echo "FAILED: " . $e->getMessage() . "\n";
    }
}
