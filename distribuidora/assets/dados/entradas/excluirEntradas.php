<?php
declare(strict_types=1);

require_once __DIR__ . '/../../conexao.php';
require_once __DIR__ . '/./_helpers.php';

function backPage(): void { redirect_to('../../../entradas.php'); }

try {
  require_post_or_redirect('../../../entradas.php');
  csrf_validate_or_redirect('../../../entradas.php');

  $id = (int)($_POST['id'] ?? 0);
  if ($id <= 0) {
    flash_set('danger', 'ID inválido.');
    backPage();
  }

  $pdo = db();
  $pdo->beginTransaction();

  $st = $pdo->prepare("SELECT id, produto_id, qtd, nf FROM entradas WHERE id = :id LIMIT 1");
  $st->execute([':id' => $id]);
  $row = $st->fetch(PDO::FETCH_ASSOC);

  if (!$row) {
    $pdo->rollBack();
    flash_set('danger', 'Entrada não encontrada.');
    backPage();
  }

  $produtoId = (int)$row['produto_id'];
  $qtd = (int)$row['qtd'];
  $nf = (string)$row['nf'];

  // exclui
  $del = $pdo->prepare("DELETE FROM entradas WHERE id = :id");
  $del->execute([':id' => $id]);

  // estorna estoque
  $stk = $pdo->prepare("UPDATE produtos SET estoque = estoque - :qtd WHERE id = :id");
  $stk->execute([':qtd' => $qtd, ':id' => $produtoId]);

  $pdo->commit();
  flash_set('success', 'Entrada removida: ' . $nf);
  backPage();

} catch (Throwable $e) {
  if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
  flash_set('danger', 'Erro ao excluir entrada.');
  backPage();
}

?>