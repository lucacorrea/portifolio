<?php
declare(strict_types=1);

require_once __DIR__ . '/env.php';
semas_whatsapp_load_env();

$host = semas_whatsapp_env('DB_HOST');
$name = semas_whatsapp_env('DB_NAME');
$user = semas_whatsapp_env('DB_USER');
$pass = semas_whatsapp_env('DB_PASS', '');
$port = semas_whatsapp_env_int('DB_PORT', 3306);

if ($host !== null && $name !== null && $user !== null) {
    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, $name);
    $pdo = new PDO($dsn, $user, (string)$pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} else {
    require_once __DIR__ . '/../../dist/assets/conexao.php';
}

if (!isset($pdo) || !($pdo instanceof PDO)) {
    throw new RuntimeException('Conexao com banco indisponivel.');
}

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
