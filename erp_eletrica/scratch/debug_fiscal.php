<?php
require_once __DIR__ . '/nfce/config.php';
header('Content-Type: application/json');

$debug = [
    'vendaId' => $vendaId,
    'empresaId' => $empresaId,
    'global_count' => count($global),
    'filial_count' => count($filial),
    'final_password_empty' => empty(PFX_PASSWORD),
    'final_path' => PFX_PATH,
    'fiscal_raw' => $fiscal
];

echo json_encode($debug, JSON_PRETTY_PRINT);
