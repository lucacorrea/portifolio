<?php
require_once 'config.php';
header('Content-Type: application/json');

$res = [];

try {
    $db = \App\Config\Database::getInstance()->getConnection();
    
    // Check Session
    $res['session'] = $_SESSION;
    
    // Check sefaz_config
    $stmt = $db->query("SELECT * FROM sefaz_config");
    $res['sefaz_config'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Check filiais
    $stmt = $db->query("SELECT * FROM filiais");
    $res['filiais'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Check tables existence
    $tables = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    $res['tables'] = $tables;
    
    if (in_array('sefaz_config', $tables)) {
        $res['columns_sefaz'] = $db->query("DESCRIBE sefaz_config")->fetchAll(PDO::FETCH_ASSOC);
    }
    
} catch (Exception $e) {
    $res['error'] = $e->getMessage();
}

echo json_encode($res, JSON_PRETTY_PRINT);
