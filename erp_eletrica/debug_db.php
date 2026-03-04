<?php
require_once 'config.php';

try {
    $stmt = $pdo->query("DESCRIBE usuarios");
    $columns = $stmt->fetchAll();
    echo "COLUMNS IN 'usuarios' table:\n";
    foreach ($columns as $col) {
        echo "- {$col['Field']} ({$col['Type']})\n";
    }
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage();
}
