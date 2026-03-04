<?php
declare(strict_types=1);
require_once __DIR__ . '/_helpers.php';

try {
  csrf_check($_POST['csrf_token'] ?? null);

  $id = (int)($_POST['id'] ?? 0);
  if ($id <= 0) {
    flash_set('danger', 'ID inválido para excluir.');
    redirect_categorias();
  }

  $pdo = pdo();
  $st = $pdo->prepare("DELETE FROM categorias WHERE id=?");
  $st->execute([$id]);

  flash_set('success', 'Categoria excluída com sucesso!');
  redirect_categorias();

} catch (Throwable $e) {
  fail_page($e->getMessage());
}

?>