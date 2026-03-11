<?php
require_once 'config.php';

try {
    $db = \App\Config\Database::getInstance()->getConnection();
    
    // Check columns in filiais
    $stmt = $db->query("SHOW COLUMNS FROM filiais");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "Columns in 'filiais': " . implode(', ', $columns) . "\n";
    
    if (!in_array('codigo_uf', $columns)) {
        echo "Adding 'codigo_uf' to 'filiais'...\n";
        $db->exec("ALTER TABLE filiais ADD COLUMN codigo_uf INT AFTER uf");
    } else {
        echo "'codigo_uf' already exists in 'filiais'.\n";
    }

    if (!in_array('codigo_municipio', $columns)) {
        echo "Adding 'codigo_municipio' to 'filiais'...\n";
        $db->exec("ALTER TABLE filiais ADD COLUMN codigo_municipio VARCHAR(10) AFTER municipio");
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
