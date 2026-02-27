<?php
declare(strict_types=1);
require_once __DIR__ . '/_helpers.php';

try {
  $pdo = pdo();
  $st = $pdo->query("SELECT id, nome, descricao, cor, obs, status, created_at, updated_at
                     FROM categorias
                     ORDER BY id DESC");
  $rows = $st->fetchAll(PDO::FETCH_ASSOC);

  header('Content-Type: application/json; charset=utf-8');
  header('Content-Disposition: attachment; filename="categorias.json"');
  echo json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
  exit;

} catch (Throwable $e) {
  fail_page($e->getMessage());
}

?>