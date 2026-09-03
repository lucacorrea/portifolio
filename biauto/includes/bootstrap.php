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

if (!in_array($paginaAtual, $paginasPublicas, true)) {
    if (!usuario_logado()) {
        redirect('login.php');
    }

    $stmt = $pdo->prepare('SELECT id, nome, email, nivel, ativo FROM usuarios WHERE id = ? AND deleted_at IS NULL LIMIT 1');
    $stmt->execute([(int) $_SESSION['usuario_id']]);
    $usuarioAtual = $stmt->fetch();

    if (!$usuarioAtual || (int) $usuarioAtual['ativo'] !== 1) {
        $_SESSION = [];
        session_destroy();
        redirect('login.php');
    }

    $_SESSION['usuario_nome'] = $usuarioAtual['nome'];
    $_SESSION['usuario_email'] = $usuarioAtual['email'];
    $_SESSION['usuario_nivel'] = $usuarioAtual['nivel'];
}
