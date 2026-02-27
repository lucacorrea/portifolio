<?php
declare(strict_types=1);
require_once __DIR__ . '/_helpers.php';

try {
  $body = read_json_body();
  if (!csrf_ok($body['csrf'] ?? null)) json_out(['ok'=>false,'msg'=>'CSRF inválido. Recarregue a página.'], 403);

  $id = (int)($body['id'] ?? 0);
  if ($id <= 0) json_out(['ok'=>false,'msg'=>'ID inválido.'], 400);

  $pdo = pdo();
  $st = $pdo->prepare("DELETE FROM fornecedores WHERE id=?");
  $st->execute([$id]);

  json_out(['ok' => true]);
} catch (Throwable $e) {
  json_out(['ok' => false, 'msg' => 'Erro: ' . $e->getMessage()], 500);
}

?>