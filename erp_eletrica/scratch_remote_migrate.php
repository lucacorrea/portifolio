<?php
// temporary script to migrate remote database directly
$host = 'srv1819.hstgr.io';
$port = 3306;
$dbName = 'u784961086_pdv';
$user = 'u784961086_pdv';
$pass = 'Uv$1NhLlkRub';

try {
    echo "Connecting to remote database at $host...\n";
    $dsn = "mysql:host=$host;port=$port;dbname=$dbName;charset=utf8mb4";
    $db = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_TIMEOUT => 10 // 10 seconds timeout
    ]);
    echo "Connected successfully! Running ALTER TABLE queries...\n";
    
    $queries = [
        "ALTER TABLE filiais ADD COLUMN IF NOT EXISTS dados_bancarios VARCHAR(255) NULL AFTER certificado_senha",
        "ALTER TABLE filiais ADD COLUMN IF NOT EXISTS chave_pix VARCHAR(255) NULL AFTER dados_bancarios",
        "ALTER TABLE filiais ADD COLUMN IF NOT EXISTS titular_conta VARCHAR(255) NULL AFTER chave_pix",
        "INSERT IGNORE INTO migrations (migration) VALUES ('048_add_banking_fields_to_filiais.sql')"
    ];
    
    foreach ($queries as $q) {
        echo "Executing: $q\n";
        $db->exec($q);
    }
    echo "Migration completed successfully on remote Hostinger database!\n";
} catch (Exception $e) {
    echo "Error connecting or executing migration: " . $e->getMessage() . "\n";
}
