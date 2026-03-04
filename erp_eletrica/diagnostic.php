<?php
require_once 'config.php';
$db = \App\Config\Database::getInstance()->getConnection();

function checkTable($db, $table) {
    echo "\nTable: $table\n";
    try {
        $stmt = $db->query("DESCRIBE $table");
        while ($row = $stmt->fetch()) {
            echo " - {$row['Field']} ({$row['Type']})\n";
        }
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
    }
}

checkTable($db, 'usuarios');
checkTable($db, 'filiais');
