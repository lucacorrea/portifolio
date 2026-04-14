<?php
// public/lavajato/actions/lavadoresEditar.php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../../lib/auth_guard.php';
require_once __DIR__ . '/../../../lib/util.php';

require_post();
guard_empresa_user(['super_admin','dono','administrativo','caixa']);

// conexão
$pdo = null;
$pathConexao = realpath(__DIR__ . '/../../../conexao/conexao.php');
if ($pathConexao && file_exists($pathConexao)) require_once $pathConexao;
if (!isset($pdo) || !($pdo instanceof PDO)) {
  header('Location: ../pages/lavadores.php?err=1&msg=' . urlencode('Conexão indisponível.'));
  exit;
}

// CSRF
$csrfSess = (string)($_SESSION['csrf_lavadores'] ?? '');
$csrfPost = (string)($_POST['csrf'] ?? '');
if (!$csrfSess || !hash_equals($csrfSess, $csrfPost)) {
  header('Location: ../pages/lavadores.php?err=1&msg=' . urlencode('CSRF inválido.'));
  exit;
}

// dados
$id       = (int)($_POST['id'] ?? 0);
$nome     = trim((string)($_POST['nome'] ?? ''));
$cpf      = trim((string)($_POST['cpf'] ?? ''));
$telefone = trim((string)($_POST['telefone'] ?? ''));
$email    = trim((string)($_POST['email'] ?? ''));
$ativo    = ((string)($_POST['ativo'] ?? '1') === '0') ? 0 : 1;

if ($id <= 0 || $nome === '') {
  header('Location: ../pages/lavadores.php?err=1&msg=' . urlencode('Dados inválidos.'));
  exit;
}

// perfil + empresa
$perfil = strtolower((string)($_SESSION['user_perfil'] ?? ''));
$empresaCnpj = preg_replace('/\D+/', '', (string)($_SESSION['user_empresa_cnpj'] ?? ''));

if ($perfil !== 'super_admin' && !preg_match('/^\d{14}$/', $empresaCnpj)) {
  header('Location: ../pages/lavadores.php?err=1&msg=' . urlencode('Empresa não vinculada ao usuário.'));
  exit;
}

try {
  // super_admin edita por id; demais: por id + empresa_cnpj
  if ($perfil === 'super_admin') {
    $sql = "UPDATE lavadores_peca
            SET nome=:n, cpf=:cpf, telefone=:t, email=:e, ativo=:a
            WHERE id=:id
            LIMIT 1";
    $st = $pdo->prepare($sql);
    $st->execute([
      ':n'   => $nome,
      ':cpf' => ($cpf !== '' ? $cpf : null),
      ':t'   => ($telefone !== '' ? $telefone : null),
      ':e'   => ($email !== '' ? $email : null),
      ':a'   => $ativo,
      ':id'  => $id,
    ]);
  } else {
    $sql = "UPDATE lavadores_peca
            SET nome=:n, cpf=:cpf, telefone=:t, email=:e, ativo=:a
            WHERE id=:id AND empresa_cnpj=:c
            LIMIT 1";
    $st = $pdo->prepare($sql);
    $st->execute([
      ':n'   => $nome,
      ':cpf' => ($cpf !== '' ? $cpf : null),
      ':t'   => ($telefone !== '' ? $telefone : null),
      ':e'   => ($email !== '' ? $email : null),
      ':a'   => $ativo,
      ':id'  => $id,
      ':c'   => $empresaCnpj,
    ]);
  }

  if ($st->rowCount() <= 0) {
    header('Location: ../pages/lavadores.php?err=1&msg=' . urlencode('Nada foi alterado (registro não encontrado ou sem permissão).'));
    exit;
  }

  header('Location: ../pages/lavadores.php?ok=1&msg=' . urlencode('Lavador atualizado com sucesso.'));
  exit;
} catch (Throwable $e) {
  header('Location: ../pages/lavadores.php?err=1&msg=' . urlencode('Erro ao salvar: ' . $e->getMessage()));
  exit;
}
