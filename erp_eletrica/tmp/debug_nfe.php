<?php
require_once 'erp_eletrica/config.php';
$db = \App\Config\Database::getInstance()->getConnection();

echo "Checking nfe_importadas table structure:\n";
try {
    $stmt = $db->query("DESCRIBE nfe_importadas");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        print_r($row);
    }
} catch (Exception $e) {
    echo "Error describing table: " . $e->getMessage() . "\n";
}

echo "\nChecking data in nfe_importadas:\n";
try {
    $stmt = $db->query("SELECT id, filial_id, status, fornecedor_nome FROM nfe_importadas LIMIT 10");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        print_r($row);
    }
} catch (Exception $e) {
    echo "Error selecting data: " . $e->getMessage() . "\n";
}
