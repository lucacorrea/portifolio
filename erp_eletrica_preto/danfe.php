<?php
require_once __DIR__ . '/config.php';
checkAuth();

$chave = $_GET['chave'] ?? null;
if (!$chave) {
    die("Chave não fornecida.");
}

$controller = new \App\Controllers\FiscalController();
$_GET['action'] = 'danfe_nfce';
$controller->danfe_nfce();
