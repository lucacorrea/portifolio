<?php

declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    ini_set('session.use_strict_mode', '1');
    session_name('BIAUTOSESSID');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    ]);
    session_start();
}

date_default_timezone_set('America/Manaus');

require_once dirname(__DIR__) . '/conexao.php';
require_once __DIR__ . '/funcoes.php';

$paginaAtual = basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
$paginasPublicas = ['login.php', 'cadastro.php'];

if (!in_array($paginaAtual, $paginasPublicas, true) && empty($_SESSION['usuario_id'])) {
    redirect('login.php');
}
