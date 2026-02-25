<?php
require_once 'config.php';
$db = \App\Config\Database::getInstance()->getConnection();

$stmt = $db->query("SHOW TABLES");
$tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

foreach ($tables as $table) {
    try {
        $colsStmt = $db->query("DESCRIBE $table");
        $cols = $colsStmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (in_array('filial_id', $cols)) {
            echo "TABLE: $table -> HAS filial_id\n";
        } else {
            echo "TABLE: $table -> MISSING filial_id\n";
        }
    } catch (Exception $e) {
        echo "TABLE: $table -> ERROR: " . $e->getMessage() . "\n";
    }
}
