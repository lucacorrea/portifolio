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

function flash_set(string $type, string $msg): void {
  $_SESSION['flash'] = ['type' => $type, 'msg' => $msg];
}

function redirect_produtos(): void {
  header("Location: ../../../produtos.php");
  exit;
}

function csrf_token(): string {
  if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
  }
  return $_SESSION['csrf_token'];
}

function csrf_check(?string $t): void {
  if (!is_string($t) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $t)) {
    flash_set('danger', 'Falha de segurança (CSRF). Recarregue e tente novamente.');
    redirect_produtos();
  }
}

function only_status(string $s): string {
  $v = strtoupper(trim($s));
  return ($v === 'ATIVO' || $v === 'INATIVO') ? $v : 'ATIVO';
}

/** "28,90" "28.90" "R$ 1.234,56" => "1234.56" */
function parse_money_to_decimal_string(string $s): string {
  $x = trim($s);
  $x = str_replace(['R$', ' '], '', $x);
  $x = str_replace('.', '', $x);
  $x = str_replace(',', '.', $x);
  if ($x === '' || !is_numeric($x)) return '0.00';
  return number_format((float)$x, 2, '.', '');
}

function fail_page(string $msg): void {
  http_response_code(500);
  ?>
  <!doctype html>
  <html lang="pt-BR">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Erro</title>
    <link rel="stylesheet" href="../../../assets/css/bootstrap.min.css">
    <style>
      .flash-auto-hide{transition:opacity .35s ease, transform .35s ease;}
      .flash-auto-hide.hide{opacity:0;transform:translateY(-6px);pointer-events:none;}
    </style>
  </head>
  <body class="bg-light">
    <div class="container py-5">
      <div class="alert alert-danger flash-auto-hide" id="flashBox">
        <strong>Erro:</strong> <?= e($msg) ?>
      </div>
      <a class="btn btn-primary" href="../../../produtos.php">Voltar</a>
    </div>
    <script>
      (function(){
        const box=document.getElementById('flashBox');
        if(!box) return;
        setTimeout(()=>box.classList.add('hide'),1500);
      })();
    </script>
  </body>
  </html>
  <?php
  exit;
}

?>