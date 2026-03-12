<?php
require_once 'nfce/config.php';
try {
    $st = $pdo->query("DESCRIBE vendas");
    $cols = $st->fetchAll(PDO::FETCH_COLUMN);
    echo "Columns in 'vendas': " . implode(", ", $cols) . "\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
