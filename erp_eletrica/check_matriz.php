<?php
require_once 'config.php';
$db = \App\Config\Database::getInstance()->getConnection();
$matriz = $db->query("SELECT * FROM filiais WHERE principal = 1")->fetch();

echo "<pre>";
if ($matriz) {
    echo "MATRIZ FOUND (ID: {$matriz['id']}):\n";
    print_r($matriz);
} else {
    echo "ERROR: NO MATRIZ (principal=1) FOUND!\n";
    $all = $db->query("SELECT id, nome, principal FROM filiais")->fetchAll();
    echo "\nAll branches state:\n";
    print_r($all);
}
echo "</pre>";
