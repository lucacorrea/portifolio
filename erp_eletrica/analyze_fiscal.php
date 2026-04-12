<?php
require_once 'config.php';
$db = \App\Config\Database::getInstance()->getConnection();

echo "<pre>";
echo "--- FILIAIS TABLE ---\n";
$filiais = $db->query("SELECT id, nome, cnpj, principal, certificado_pfx, csc_token, csc_id FROM filiais")->fetchAll(PDO::FETCH_ASSOC);
print_r($filiais);

echo "\n--- SEFAZ_CONFIG ---\n";
$sefaz = $db->query("SELECT * FROM sefaz_config LIMIT 1")->fetch(PDO::FETCH_ASSOC);
print_r($sefaz);

$nfce = new \App\Services\NfceService();
echo "\n--- RESOLVED CONFIG FOR ALL BRANCHES ---\n";
foreach ($filiais as $f) {
    echo "Filial ID " . $f['id'] . " (" . $f['nome'] . "):\n";
    print_r($nfce->getConfig($f['id']));
    echo "--------------------------\n";
}
echo "</pre>";
unlink(__FILE__); // Self-destruct for security
