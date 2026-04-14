<?php
declare(strict_types=1);

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../../lib/auth_guard.php';
guard_empresa_user(['dono', 'administrativo', 'caixa', 'estoque']);

/* ===================== CONEXÃO ===================== */
$pdo = null;
$pathCon = realpath(__DIR__ . '/../../../conexao/conexao.php');
if ($pathCon && file_exists($pathCon)) {
  require_once $pathCon;
}

if (!isset($pdo) || !($pdo instanceof PDO)) {
  http_response_code(500);
  die('Conexão indisponível.');
}

/* ===================== DADOS ===================== */
$id = (int)($_POST['id'] ?? 0);

/* força sempre dinheiro */
$forma = 'dinheiro';

/* ===================== VALIDAÇÃO ===================== */
if ($id <= 0) {
  $_SESSION['flash_err'] = 'Dados inválidos para finalizar.';
  header('Location: ../pages/lavagensLista.php');
  exit;
}

/* ===================== PROCESSAMENTO ===================== */
try {
  $sql = "
    UPDATE lavagens_peca
       SET status = 'concluida',
           forma_pagamento = :forma,
           checkout_at = NOW()
     WHERE id = :id
       AND status = 'aberta'
  ";

  $stmt = $pdo->prepare($sql);
  $stmt->execute([
    ':forma' => $forma,
    ':id'    => $id,
  ]);

  if ($stmt->rowCount() < 1) {
    $_SESSION['flash_err'] = 'Não foi possível finalizar. Talvez já esteja concluída ou cancelada.';
  } else {
    $_SESSION['flash_ok'] = 'Lavagem finalizada com sucesso!';
  }
} catch (Throwable $e) {
  $_SESSION['flash_err'] = 'Erro ao finalizar: ' . $e->getMessage();
}

/* ===================== REDIRECT ===================== */
header('Location: ../pages/lavagensLista.php');
exit;