<?php
declare(strict_types=1);
session_start();

require __DIR__ . '/../../assets/php/conexao.php';
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  header('Location: ../../index.php');
  exit;
}

$email = trim((string)($_POST['email'] ?? ''));
$senha = (string)($_POST['senha'] ?? '');

if ($email === '' || $senha === '') {
  $_SESSION['flash_erro'] = 'Informe e-mail e senha.';
  header('Location: ../../index.php');
  exit;
}

$stmt = $pdo->prepare("SELECT id, nome, email, senha_hash, ativo
                       FROM usuarios
                       WHERE email = :email
                       LIMIT 1");
$stmt->execute([':email' => $email]);
$user = $stmt->fetch();

if (!$user || (int)$user['ativo'] !== 1 || !password_verify($senha, $user['senha_hash'])) {
  $_SESSION['flash_erro'] = 'E-mail ou senha inválidos (ou usuário inativo).';
  header('Location: ../../index.php');
  exit;
}

$pstmt = $pdo->prepare("SELECT p.codigo
                        FROM usuario_perfis up
                        JOIN perfis p ON p.id = up.perfil_id
                        WHERE up.usuario_id = :uid");
$pstmt->execute([':uid' => (int)$user['id']]);
$perfis = $pstmt->fetchAll(PDO::FETCH_COLUMN) ?: [];

session_regenerate_id(true);
$_SESSION['usuario_logado'] = true;
$_SESSION['usuario_id']     = (int)$user['id'];
$_SESSION['usuario_nome']   = (string)$user['nome'];
$_SESSION['usuario_email']  = (string)$user['email'];
$_SESSION['perfis']         = $perfis;

$pdo->prepare("UPDATE usuarios SET ultimo_login_em = NOW() WHERE id = :id")
    ->execute([':id' => (int)$user['id']]);

if (in_array('ADMIN', $perfis, true)) {
  header('Location: ../../painel/adm/index.php');
} else {
  header('Location: ../../painel/operador/index.php');
}
exit;
