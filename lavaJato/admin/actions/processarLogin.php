<?php
// autoErp/admin/actions/processarLogin.php

if (session_status() === PHP_SESSION_NONE) session_start();

// Aceita apenas POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../index.php?erro=1'); exit;
}

// Conexão PDO (admin/actions → ../../conexao/conexao.php)
$pathConexao = realpath(__DIR__ . '/../../conexao/conexao.php');
if ($pathConexao === false || !file_exists($pathConexao)) {
    header('Location: ../index.php?erro=2&msg=' . urlencode('Arquivo de conexão não encontrado')); exit;
}
require_once $pathConexao;

// Entrada
$entrada = trim($_POST['usuario'] ?? '');
$senha   = (string)($_POST['senha'] ?? '');
if ($entrada === '' || $senha === '') {
    header('Location: ../index.php?erro=1'); exit;
}

// Normalizações
$asEmail = filter_var($entrada, FILTER_VALIDATE_EMAIL) ? strtolower($entrada) : '__no_email__';
$asCPF   = preg_replace('/\D+/', '', $entrada);
$asCPF   = (strlen($asCPF) === 11) ? $asCPF : '__no_cpf__';
$asNome  = $entrada; // match exato (collation geralmente case-insensitive)

// Busca 1 registro que bata por email OU cpf OU nome, limitado a super_admin ativo
$sql = "SELECT id, nome, email, senha, perfil, status
          FROM usuarios
         WHERE perfil = 'super_admin'
           AND status = 1
           AND (email = :e OR cpf = :c OR nome = :n)
         LIMIT 1";
try {
    $st = $pdo->prepare($sql);
    $st->execute([
        ':e' => $asEmail,
        ':c' => $asCPF,
        ':n' => $asNome,
    ]);
    $user = $st->fetch();

    // Não achou
    if (!$user) {
        header('Location: ../index.php?erro=1'); exit;
    }

    // Senha incorreta
    if (!password_verify($senha, $user['senha'])) {
        header('Location: ../index.php?erro=1'); exit;
    }

    // OK: autentica sessão admin
    session_regenerate_id(true);
    $_SESSION['admin_id']     = (int)$user['id'];
    $_SESSION['admin_nome']   = $user['nome'];
    $_SESSION['admin_email']  = $user['email'];
    $_SESSION['admin_perfil'] = $user['perfil']; // 'super_admin'

    header('Location: ../dashboard.php'); exit;

} catch (Throwable $e) {
    // Não exponha detalhes em produção
    header('Location: ../index.php?erro=2&msg=' . urlencode('Falha inesperada.')); exit;
}
