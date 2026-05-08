<?php
require_once 'config.php';
$db = \App\Config\Database::getInstance()->getConnection();
header('Content-Type: application/json');

try {
    $stmt = $db->query("DESCRIBE produtos");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $hasPrecoVariavel = false;
    foreach($columns as $col) {
        if ($col['Field'] === 'preco_variavel') $hasPrecoVariavel = true;
    }
    
    echo json_encode([
        'success' => true,
        'has_preco_variavel' => $hasPrecoVariavel,
        'columns' => $columns
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
