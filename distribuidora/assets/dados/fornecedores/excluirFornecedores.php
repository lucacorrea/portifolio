<?php
declare(strict_types=1);
require_once __DIR__ . '/_helpers.php';

try {
  csrf_check($_POST['csrf_token'] ?? null);

  $redirect = (string)($_POST['redirect_to'] ?? '../../../fornecedores.php');

  $id = (int)($_POST['id'] ?? 0);
  if ($id <= 0) {
    flash_set('danger', 'ID inválido para excluir.');
    redirect_to($redirect);
  }

  $pdo = pdo();
  $st = $pdo->prepare("DELETE FROM fornecedores WHERE id=?");
  $st->execute([$id]);

  flash_set('success', 'Fornecedor excluído com sucesso!');
  redirect_to($redirect);

} catch (Throwable $e) {
  fail_page($e->getMessage());
}

?>