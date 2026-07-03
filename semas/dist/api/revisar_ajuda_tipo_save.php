<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth/authGuard.php';
auth_guard();

@date_default_timezone_set('America/Manaus');
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

header('Content-Type: application/json; charset=utf-8');

function pdo_conn(): PDO {
  $con1 = __DIR__ . '/../assets/php/conexao.php';
  if (file_exists($con1)) {
    require_once $con1;
    // prefer a $pdo provided by the file
    if (isset($pdo) && $pdo instanceof PDO) {
      $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
      return $pdo;
    }
    // call db() only if it's callable using a dynamic call to avoid static undefined-function errors
    $call = 'db';
    if (is_callable($call)) {
      $pdo = $call();
      $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
      return $pdo;
    }
  }
  $con2 = __DIR__ . '/../assets/conexao.php';
  if (file_exists($con2)) {
    require_once $con2;
    if (isset($pdo) && $pdo instanceof PDO) {
      $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
      return $pdo;
    }
  }
  throw new RuntimeException('Conexão não encontrada.');
}

$in = json_decode(file_get_contents('php://input'), true) ?: [];
$csrf = (string)($in['csrf'] ?? '');
$id   = (int)($in['id'] ?? 0);
$tipo = (int)($in['tipo'] ?? 0);

if (empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $csrf)) {
  echo json_encode(['ok'=>false,'msg'=>'CSRF inválido.']);
  exit;
}
if ($id <= 0 || $tipo <= 0) {
  echo json_encode(['ok'=>false,'msg'=>'Dados inválidos.']);
  exit;
}

try {
  $pdo = pdo_conn();

  // valida tipo existe
  $st = $pdo->prepare("SELECT id FROM ajudas_tipos WHERE id=? LIMIT 1");
  $st->execute([$tipo]);
  if (!$st->fetchColumn()) {
    echo json_encode(['ok'=>false,'msg'=>'Tipo não existe.']);
    exit;
  }

  // atualiza só se ainda estiver sem tipo
  $up = $pdo->prepare("
    UPDATE solicitantes
    SET ajuda_tipo_id = ?
    WHERE id = ?
      AND (ajuda_tipo_id IS NULL OR ajuda_tipo_id=0)
    LIMIT 1
  ");
  $up->execute([$tipo, $id]);

  echo json_encode(['ok'=>true]);
} catch (Throwable $e) {
  echo json_encode(['ok'=>false,'msg'=>'Erro: '.$e->getMessage()]);
}
