<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
  session_start();
}

require_once __DIR__ . '/../../conexao.php';

function pdo(): PDO {
  $pdo = db();
  if (!$pdo instanceof PDO) throw new RuntimeException("db() não retornou PDO.");
  return $pdo;
}

function e(string $s): string {
  return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function only_status(string $s): string {
  $v = strtoupper(trim($s));
  return ($v === 'ATIVO' || $v === 'INATIVO') ? $v : 'ATIVO';
}

// CSRF
function csrf_token(): string {
  if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
  }
  return $_SESSION['csrf_token'];
}

function csrf_check(?string $t): void {
  if (!is_string($t) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $t)) {
    fail("Falha de segurança (CSRF). Recarregue a página e tente novamente.");
  }
}

// Flash messages
function flash_set(string $type, string $msg): void {
  $_SESSION['flash'] = ['type' => $type, 'msg' => $msg];
}

function redirect_fornecedores(): void {
  header("Location: ../../../fornecedores.php");
  exit;
}

function fail(string $msg): void {
  http_response_code(500);
  ?>
  <!doctype html>
  <html lang="pt-BR">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Erro</title>
    <link rel="stylesheet" href="../../../assets/css/bootstrap.min.css">
  </head>
  <body class="bg-light">
    <div class="container py-5">
      <div class="alert alert-danger">
        <strong>Erro:</strong> <?= e($msg) ?>
      </div>
      <a class="btn btn-primary" href="../../../fornecedores.php">Voltar</a>
    </div>
  </body>
  </html>
  <?php
  exit;
}