<?php
require_once 'config.php';
$nfceService = new \App\Services\NfceService();
$branchId = $_GET['id'] ?? 1;
$config = $nfceService->getConfig($branchId);
echo "DEBUG CONFIG FOR FILIAL #$branchId:\n";
print_r($config);

$db = \App\Config\Database::getInstance()->getConnection();
$stmt = $db->query("SELECT * FROM filiais WHERE id = $branchId");
echo "\nRAW FILIAL RECORD:\n";
print_r($stmt->fetch());

$stmtGlobal = $db->query("SELECT * FROM sefaz_config LIMIT 1");
echo "\nGLOBAL SEFAZ RECORD:\n";
print_r($stmtGlobal->fetch());
