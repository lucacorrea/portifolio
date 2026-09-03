<?php

declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_name('BIAUTOSESSID');

    $cookieSeguro = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    session_set_cookie_params(0, '/', '', $cookieSeguro, true);
    session_start();
}

date_default_timezone_set('America/Manaus');

require_once dirname(__DIR__) . '/conexao.php';
require_once __DIR__ . '/funcoes.php';

$paginaAtual = basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
$paginaPublica = $paginaAtual === 'login.php';

if ($paginaAtual === 'cadastro.php') {
    $totalUsuarios = (int) $pdo->query('SELECT COUNT(*) FROM usuarios WHERE deleted_at IS NULL')->fetchColumn();
    $paginaPublica = $totalUsuarios === 0;
}

if (!$paginaPublica) {
    if (!usuario_logado()) {
        redirect('login.php');
    }

    $agora = time();
    $ultimaAtividade = (int) ($_SESSION['ultima_atividade'] ?? $agora);

    if (($agora - $ultimaAtividade) > 7200) {
        encerrar_sessao();
        redirect('login.php?expirada=1');
    }

    $_SESSION['ultima_atividade'] = $agora;

    $stmt = $pdo->prepare('SELECT id, nome, email, nivel, ativo FROM usuarios WHERE id = ? AND deleted_at IS NULL LIMIT 1');
    $stmt->execute([(int) $_SESSION['usuario_id']]);
    $usuarioAtual = $stmt->fetch();

    if (!$usuarioAtual || (int) $usuarioAtual['ativo'] !== 1) {
        encerrar_sessao();
        redirect('login.php');
    }

    $_SESSION['usuario_nome'] = (string) $usuarioAtual['nome'];
    $_SESSION['usuario_email'] = (string) $usuarioAtual['email'];
    $_SESSION['usuario_nivel'] = (string) $usuarioAtual['nivel'];

    $moduloAtual = modulo_pagina($paginaAtual);

    if (!pode_acessar($moduloAtual)) {
        flash('warning', 'Seu usuário não possui permissão para acessar esta área.');
        redirect('index.php');
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !pode_alterar($moduloAtual)) {
        flash('warning', 'Seu usuário possui acesso somente para consulta nesta área.');
        redirect('index.php');
    }
}
