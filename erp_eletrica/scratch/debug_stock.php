<?php
require_once __DIR__ . '/nfce/config.php'; // Using this for DB connection
header('Content-Type: application/json');

$debug = [];

// 1. Check current session
session_start();
$debug['session'] = [
    'filial_id' => $_SESSION['filial_id'] ?? 'NULL',
    'is_matriz' => $_SESSION['is_matriz'] ?? 'NULL'
];

// 2. Check recent estoque_filiais entries
$st = $pdo->query("SELECT * FROM estoque_filiais ORDER BY updated_at DESC LIMIT 10");
$debug['recent_stock'] = $st->fetchAll();

// 3. Check recent transfers
$st2 = $pdo->query("SELECT * FROM erp_transferencias ORDER BY data_recebimento DESC LIMIT 5");
$debug['recent_transfers'] = $st2->fetchAll();

echo json_encode($debug, JSON_PRETTY_PRINT);
