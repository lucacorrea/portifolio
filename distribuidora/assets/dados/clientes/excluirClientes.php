<?php
declare(strict_types=1);

@date_default_timezone_set('America/Manaus');
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

require_once __DIR__ . '/../../conexao.php';
require_once __DIR__ . '/_helpers.php';

require_db_or_die();
$pdo = db();

if (!is_post()) redirect(url_here('../../../clientes.php'));

csrf_validate_or_die();
$return = safe_return_to(post_str('return_to', url_here('clientes.php')));

$id = post_int('id', 0);
if ($id <= 0) { flash_set('flash_err', 'ID inválido.'); redirect($return); }

try {
  $st = $pdo->prepare("SELECT id FROM clientes WHERE id = :id LIMIT 1");
  $st->execute(['id' => $id]);
  if (!$st->fetchColumn()) {
    flash_set('flash_err', 'Cliente não encontrado.');
    redirect($return);
  }

  $del = $pdo->prepare("DELETE FROM clientes WHERE id = :id LIMIT 1");
  $del->execute(['id' => $id]);

  flash_set('flash_ok', 'Cliente excluído com sucesso.');
  redirect($return);
} catch (Throwable $e) {
  flash_set('flash_err', 'Erro ao excluir cliente: ' . $e->getMessage());
  redirect($return);
}

?>