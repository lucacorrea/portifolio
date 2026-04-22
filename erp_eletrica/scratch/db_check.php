<?php
require_once 'config.php';
try {
    $stmt = $pdo->query("DESCRIBE logs_acesso");
    $cols = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "Columns in logs_acesso: " . implode(', ', $cols) . "\n";
    
    $stmt = $pdo->query("SELECT * FROM logs_acesso ORDER BY id DESC LIMIT 3");
    print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
