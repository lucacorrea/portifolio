<?php
require_once 'config.php';
$db = \App\Config\Database::getInstance()->getConnection();
try {
    $res = $db->query("SELECT * FROM migrations ORDER BY id DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
    print_r($res);
    
    // Check if webauthn table exists
    $tableCheck = $db->query("SHOW TABLES LIKE 'webauthn_credentials'")->fetch();
    echo "Table check: " . ($tableCheck ? "Exists" : "MISSING") . "\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
