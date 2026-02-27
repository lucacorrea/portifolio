<?php
declare(strict_types=1);
require_once __DIR__ . '/_helpers.php';

try {
  $pdo = pdo();
  $st = $pdo->query("SELECT id, nome, status, doc, tel, email, endereco, cidade, uf, contato, obs, created_at, updated_at
                     FROM fornecedores
                     ORDER BY id DESC");
  $rows = $st->fetchAll(PDO::FETCH_ASSOC);

  header('Content-Type: application/json; charset=utf-8');
  header('Content-Disposition: attachment; filename="fornecedores.json"');
  echo json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
  exit;
} catch (Throwable $e) {
  json_out(['ok' => false, 'msg' => 'Erro: ' . $e->getMessage()], 500);
}

?>