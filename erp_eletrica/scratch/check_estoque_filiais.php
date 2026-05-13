<?php
require_once 'config.php';
$db = \App\Config\Database::getInstance()->getConnection();
header('Content-Type: application/json');

try {
    $stmt = $db->query("DESCRIBE estoque_filiais");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'table' => 'estoque_filiais',
        'columns' => $columns
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
