<?php
declare(strict_types=1);
session_start();

require_once __DIR__ . '/../../conexao.php';
require_once __DIR__ . '/./_helpers.php';

$back = '../../../saidas.php';

require_post_or_redirect($back);
csrf_validate_or_redirect($back);

$pdo = db();
$id  = (int)($_POST['id'] ?? 0);

if ($id <= 0) {
  flash_set('danger', 'ID inválido.');
  redirect_to($back);
}

try {
  $pdo->beginTransaction();

  $st = $pdo->prepare("SELECT id, produto_id, qtd FROM saidas WHERE id = ? FOR UPDATE");
  $st->execute([$id]);
  $row = $st->fetch(PDO::FETCH_ASSOC);
  if (!$row) throw new RuntimeException('Saída não encontrada.');

  $pid = (int)$row['produto_id'];
  $qtd = (float)$row['qtd'];

  // devolve estoque
  $upd = $pdo->prepare("UPDATE produtos SET estoque = estoque + ? WHERE id = ?");
  $upd->execute([$qtd, $pid]);

  // exclui
  $del = $pdo->prepare("DELETE FROM saidas WHERE id = ?");
  $del->execute([$id]);

  $pdo->commit();
  flash_set('success', 'Saída excluída e estoque devolvido!');
  redirect_to($back);

} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  flash_set('danger', 'Erro ao excluir: ' . $e->getMessage());
  redirect_to($back);
}

?>