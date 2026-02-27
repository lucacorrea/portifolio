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

  $pdo = db();

  $st = $pdo->prepare("SELECT nome FROM produtos WHERE id = :id LIMIT 1");
  $st->execute([':id' => $produtoId]);
  $p = $st->fetch(PDO::FETCH_ASSOC);
  $nome = $p ? (string)$p['nome'] : 'Produto';

  $del = $pdo->prepare("DELETE FROM inventario_itens WHERE produto_id = :id");
  $del->execute([':id' => $produtoId]);

  $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Registro do inventário removido: ' . $nome];
  back();
} catch (Throwable $e) {
  $_SESSION['flash'] = ['type' => 'danger', 'msg' => 'Erro ao excluir do inventário.'];
  back();
}

?>