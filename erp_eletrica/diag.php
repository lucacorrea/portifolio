<?php
require_once 'config.php';
$db = \App\Config\Database::getInstance()->getConnection();

echo "--- SEFAZ CONFIG ---\n";
$stmt = $db->query("SELECT id, ambiente, certificado_path FROM sefaz_config LIMIT 1");
print_r($stmt->fetch(PDO::FETCH_ASSOC));

echo "\n--- FILIAIS ---\n";
$stmt = $db->query("SELECT id, nome, cnpj FROM filiais");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

echo "\n--- WRITE TEST ---\n";
$testFile = __DIR__ . '/storage/write_test.txt';
if (file_put_contents($testFile, "test " . date('Y-m-d H:i:s'))) {
    echo "Write test SUCCESSFUL at $testFile\n";
} else {
    echo "Write test FAILED at $testFile\n";
    $err = error_get_last();
    print_r($err);
}
