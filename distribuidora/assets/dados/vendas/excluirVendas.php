<?php
declare(strict_types=1);
session_start();

require_once __DIR__ . '/../../conexao.php';
require_once __DIR__ . '/../_helpers.php';

$pdo = db();

if (!is_post()) {
  redirect('../../../vendas.php');
}

csrf_validate_or_die('csrf');

$id = to_int(post('id', 0), 0);
if ($id <= 0) {
  flash_set('danger', 'ID inválido.');
  redirect('../../../vendas.php');
}

try {
  $pdo->beginTransaction();

  $st = $pdo->prepare("SELECT id, produto_id, quantidade FROM vendas WHERE id = ?");
  $st->execute([$id]);
  $v = $st->fetch(PDO::FETCH_ASSOC);

  if (!$v) {
    throw new RuntimeException('Venda não encontrada.');
  }

  $produto_id = (int)$v['produto_id'];
  $qtd        = (float)$v['quantidade'];

  // devolve estoque
  $pdo->prepare("UPDATE produtos SET estoque = estoque + ? WHERE id = ?")->execute([$qtd, $produto_id]);

  // exclui venda
  $pdo->prepare("DELETE FROM vendas WHERE id = ?")->execute([$id]);

  $pdo->commit();
  flash_set('success', 'Venda removida e estoque devolvido!');
  redirect('../../../vendas.php');

} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  flash_set('danger', 'Erro ao excluir venda: ' . $e->getMessage());
  redirect('../../../vendas.php');
}

?>