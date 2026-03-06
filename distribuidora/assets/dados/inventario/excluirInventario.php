<?php

declare(strict_types=1);
session_start();

require_once __DIR__ . '/../../conexao.php';
require_once __DIR__ . '/./_helpers.php';

$back = '../../../inventario.php';

require_post_or_redirect($back);
csrf_validate_or_redirect($back);

$pdo = db();

$produtoId = (int)($_POST['produto_id'] ?? 0);
if ($produtoId <= 0) {
  flash_set('danger', 'Produto inválido.');
  redirect_to($back);
}

try {
  $st = $pdo->prepare("DELETE FROM inventario_itens WHERE produto_id = ?");
  $st->execute([$produtoId]);

  flash_set('success', 'Lançamento de inventário removido.');
  redirect_to($back);
} catch (Throwable $e) {
  flash_set('danger', 'Erro ao excluir: ' . $e->getMessage());
  redirect_to($back);
}

?>