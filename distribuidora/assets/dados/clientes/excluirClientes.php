<?php
declare(strict_types=1);

@date_default_timezone_set('America/Manaus');
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

require_once __DIR__ . '/../../conexao.php';
require_once __DIR__ . '/./_helpers.php';

require_db_or_die();
$pdo = db();

$data = $_POST ?: read_json_body();

$csrf = (string)($data['_csrf'] ?? '');
csrf_validate_or_die($csrf);

$id = (int)($data['id'] ?? 0);
if ($id <= 0) json_out(['ok' => false, 'msg' => 'ID inválido.'], 422);

try {
  $st = $pdo->prepare("SELECT id FROM clientes WHERE id = :id LIMIT 1");
  $st->execute(['id' => $id]);
  if (!$st->fetchColumn()) json_out(['ok' => false, 'msg' => 'Cliente não encontrado.'], 404);

  // DELETE direto (se preferir “soft delete”, eu ajusto)
  $del = $pdo->prepare("DELETE FROM clientes WHERE id = :id LIMIT 1");
  $del->execute(['id' => $id]);

  json_out(['ok' => true, 'msg' => 'Cliente excluído com sucesso.']);
} catch (Throwable $e) {
  json_out(['ok' => false, 'msg' => 'Erro ao excluir cliente.', 'detail' => $e->getMessage()], 500);
}

?>