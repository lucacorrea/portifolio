<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../../lib/auth_guard.php';
require_once __DIR__ . '/../../../lib/util.php';

require_post();
guard_empresa_user(['super_admin','dono','administrativo']);

/* ================= CONEXÃO ================= */
$pdo = null;
$pathConexao = realpath(__DIR__ . '/../../../conexao/conexao.php');
if ($pathConexao && file_exists($pathConexao)) require_once $pathConexao;

if (!isset($pdo) || !($pdo instanceof PDO)) {
  header('Location: ../pages/lavadores.php?err=1&msg=' . urlencode('Conexão indisponível.'));
  exit;
}

/* ================= CSRF ================= */
// ajuste: usa o mesmo token que você está gerando/guardando no controller/página
$csrfPost = (string)($_POST['csrf'] ?? '');
$csrfSess = (string)($_SESSION['csrf_lavadores'] ?? $_SESSION['csrf_lavadores_list'] ?? '');

if ($csrfSess === '' || $csrfPost === '' || !hash_equals($csrfSess, $csrfPost)) {
  header('Location: ../pages/lavadores.php?err=1&msg=' . urlencode('Token inválido.'));
  exit;
}

/* ================= INPUT ================= */
$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
  header('Location: ../pages/lavadores.php?err=1&msg=' . urlencode('ID inválido.'));
  exit;
}

/* ================= PERFIL / EMPRESA ================= */
$perfil = strtolower((string)($_SESSION['user_perfil'] ?? ''));
$empresaCnpj = preg_replace('/\D+/', '', (string)($_SESSION['user_empresa_cnpj'] ?? ''));

try {
  if ($perfil === 'super_admin') {
    $st = $pdo->prepare("DELETE FROM lavadores_peca WHERE id = :id LIMIT 1");
    $st->execute([':id' => $id]);
  } else {
    if (!preg_match('/^\d{14}$/', $empresaCnpj)) {
      header('Location: ../pages/lavadores.php?err=1&msg=' . urlencode('Empresa não vinculada.'));
      exit;
    }

    $st = $pdo->prepare("DELETE FROM lavadores_peca WHERE id = :id AND empresa_cnpj = :c LIMIT 1");
    $st->execute([':id' => $id, ':c' => $empresaCnpj]);
  }

  if ($st->rowCount() <= 0) {
    header('Location: ../pages/lavadores.php?err=1&msg=' . urlencode('Registro não encontrado ou sem permissão.'));
    exit;
  }

  header('Location: ../pages/lavadores.php?ok=1&msg=' . urlencode('Lavador excluído.'));
  exit;
} catch (Throwable $e) {
  header('Location: ../pages/lavadores.php?err=1&msg=' . urlencode('Erro ao excluir: ' . $e->getMessage()));
  exit;
}
