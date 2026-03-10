<?php
require_once __DIR__ . '/acainhadinhos/erp/pdv/nfce/vendor/autoload.php';

// Try to open a PFX file using NFePHP
$pfxFile = glob(__DIR__ . '/storage/certificados/*.pfx')[0] ?? null;

if (!$pfxFile) {
    die("No PFX file found.\n");
}

// Emulate how SefazConfigController reads it
// Wait, we need the password. Let's just pull it from the DB.
require_once __DIR__ . '/config.php';
$db = \App\Config\Database::getInstance()->getConnection();
$stmt = $db->query("SELECT certificado_pfx, certificado_senha FROM filiais WHERE certificado_pfx IS NOT NULL LIMIT 1");
$row = $stmt->fetch();

if (!$row) die("No configured branch with cert.\n");

$password = $row['certificado_senha'];
$pfxPath = __DIR__ . '/storage/certificados/' . $row['certificado_pfx'];
$pfxContent = file_get_contents($pfxPath);

try {
    $cert = \NFePHP\Common\Certificate::readPfx($pfxContent, $password);
    echo "--- CERT ---\n";
    echo substr($cert->certificate, 0, 150) . "...\n";
    echo "--- KEY ---\n";
    echo substr($cert->privateKey, 0, 150) . "...\n";
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
