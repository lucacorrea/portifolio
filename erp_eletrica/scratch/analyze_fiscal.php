<?php
require_once 'config.php';
$db = \App\Config\Database::getInstance()->getConnection();
$filiais = $db->query("SELECT id, nome, cnpj, principal FROM filiais")->fetchAll(PDO::FETCH_ASSOC);
echo "LIST OF BRANCHES (FILIAIS):\n";
print_r($filiais);

$sefaz = $db->query("SELECT * FROM sefaz_config LIMIT 1")->fetch(PDO::FETCH_ASSOC);
echo "\nGLOBAL SEFAZ CONFIG:\n";
print_r($sefaz);

$nfce = new \App\Services\NfceService();
echo "\nRESOLVED CONFIG FOR BRANCH #1 (MATRIZ):\n";
print_r($nfce->getConfig(1));

echo "\nRESOLVED CONFIG FOR BRANCH #2 (OR OTHER):\n";
$stmt = $db->query("SELECT id FROM filiais WHERE principal = 0 LIMIT 1");
$other = $stmt->fetch();
if ($other) {
    print_r($nfce->getConfig($other['id']));
} else {
    echo "No non-principal branches found.";
}
