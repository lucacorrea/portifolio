<?php
require_once __DIR__ . '/../config.php';
$db = \App\Config\Database::getInstance()->getConnection();
$table = 'pre_vendas';
$sql = "PRAGMA table_info($table)";
$stmt = $db->query($sql);
$columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "Colunas de $table:\n";
foreach ($columns as $col) {
    echo "- " . $col['name'] . " (" . $col['type'] . ")\n";
}

$table2 = 'pre_venda_itens';
$sql2 = "PRAGMA table_info($table2)";
$stmt2 = $db->query($sql2);
$columns2 = $stmt2->fetchAll(PDO::FETCH_ASSOC);
echo "\nColunas de $table2:\n";
foreach ($columns2 as $col) {
    echo "- " . $col['name'] . " (" . $col['type'] . ")\n";
}
