<?php
declare(strict_types=1);
require_once __DIR__ . '/_helpers.php';

function delete_image_if_any(?string $path): void {
  $path = trim((string)$path);
  if ($path === '') return;
  if (!str_starts_with($path, 'images/')) return;

  $abs = __DIR__ . '/../../../' . $path;
  if (is_file($abs)) @unlink($abs);
}

try {
  csrf_check($_POST['csrf_token'] ?? null);

  $id = (int)($_POST['id'] ?? 0);
  if ($id <= 0) {
    flash_set('danger', 'ID inválido para excluir.');
    redirect_produtos();
  }

  $pdo = pdo();

  // pega imagem antes
  $stOld = $pdo->prepare("SELECT imagem FROM produtos WHERE id=? LIMIT 1");
  $stOld->execute([$id]);
  $img = (string)($stOld->fetchColumn() ?? '');

  $st = $pdo->prepare("DELETE FROM produtos WHERE id=?");
  $st->execute([$id]);

  delete_image_if_any($img);

  flash_set('success', 'Produto excluído com sucesso!');
  redirect_produtos();

} catch (Throwable $e) {
  fail_page($e->getMessage());
}

?>