<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/../../conexao.php';

function back(): void {
  header('Location: ../../../inventario.php');
  exit;
}

try {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['flash'] = ['type' => 'danger', 'msg' => 'Requisição inválida.'];
    back();
  }

  $csrf = $_POST['csrf_token'] ?? '';
  if (!$csrf || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], (string)$csrf)) {
    $_SESSION['flash'] = ['type' => 'danger', 'msg' => 'CSRF inválido. Recarregue a página.'];
    back();
  }

  $produtoId = (int)($_POST['produto_id'] ?? 0);
  if ($produtoId <= 0) {
    $_SESSION['flash'] = ['type' => 'danger', 'msg' => 'Produto inválido.'];
    back();
  }

  $raw = $_POST['contagem'] ?? '';
  $raw = is_string($raw) ? trim($raw) : '';

  $contagem = null;
  if ($raw !== '') {
    if (!ctype_digit($raw)) {
      $_SESSION['flash'] = ['type' => 'danger', 'msg' => 'Contagem inválida.'];
      back();
    }
    $contagem = (int)$raw;
    if ($contagem < 0) $contagem = 0;
  }

  $pdo = db();

  $st = $pdo->prepare("SELECT estoque, nome FROM produtos WHERE id = :id LIMIT 1");
  $st->execute([':id' => $produtoId]);
  $p = $st->fetch(PDO::FETCH_ASSOC);

  if (!$p) {
    $_SESSION['flash'] = ['type' => 'danger', 'msg' => 'Produto não encontrado.'];
    back();
  }

  $sistema = (int)($p['estoque'] ?? 0);

  if ($contagem === null) {
    $diferenca = null;
    $situacao = 'NAO_CONFERIDO';
  } else {
    $diferenca = $contagem - $sistema;
    $situacao = ($diferenca === 0) ? 'OK' : 'DIVERGENTE';
  }

  $sql = "
    INSERT INTO inventario_itens (produto_id, contagem, diferenca, situacao, created_at, updated_at)
    VALUES (:produto_id, :contagem, :diferenca, :situacao, NOW(), NOW())
    ON DUPLICATE KEY UPDATE
      contagem = VALUES(contagem),
      diferenca = VALUES(diferenca),
      situacao = VALUES(situacao),
      updated_at = NOW()
  ";
  $q = $pdo->prepare($sql);

  $q->bindValue(':produto_id', $produtoId, PDO::PARAM_INT);

  if ($contagem === null) $q->bindValue(':contagem', null, PDO::PARAM_NULL);
  else $q->bindValue(':contagem', $contagem, PDO::PARAM_INT);

  if ($diferenca === null) $q->bindValue(':diferenca', null, PDO::PARAM_NULL);
  else $q->bindValue(':diferenca', (int)$diferenca, PDO::PARAM_INT);

  $q->bindValue(':situacao', $situacao, PDO::PARAM_STR);

  $q->execute();

  $nome = (string)($p['nome'] ?? '');
  $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Inventário salvo: ' . $nome];
  back();
} catch (Throwable $e) {
  $_SESSION['flash'] = ['type' => 'danger', 'msg' => 'Erro ao salvar inventário.'];
  back();
}

?>