<?php
declare(strict_types=1);

require_once __DIR__ . '/../../conexao.php';
require_once __DIR__ . '/./_helpers.php';

function backInv(): void { redirect_to('../../../inventario.php'); }

try {
  require_post_or_redirect('../../../inventario.php');
  csrf_validate_or_redirect('../../../inventario.php');

  $produtoId = (int)($_POST['produto_id'] ?? 0);
  if ($produtoId <= 0) {
    flash_set('danger', 'Produto inválido.');
    backInv();
  }

  $pdo = db();

  $st = $pdo->prepare("SELECT nome FROM produtos WHERE id = :id LIMIT 1");
  $st->execute([':id' => $produtoId]);
  $p = $st->fetch(PDO::FETCH_ASSOC);
  $nome = $p ? (string)$p['nome'] : 'Produto';

  $del = $pdo->prepare("DELETE FROM inventario_itens WHERE produto_id = :id");
  $del->execute([':id' => $produtoId]);

  flash_set('success', 'Registro do inventário removido: ' . $nome);
  backInv();
} catch (Throwable $e) {
  flash_set('danger', 'Erro ao excluir do inventário.');
  backInv();
}

?>