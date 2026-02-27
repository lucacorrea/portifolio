<?php
declare(strict_types=1);
require_once __DIR__ . '/_helpers.php';

try {
  $pdo = pdo();
  $st = $pdo->query("SELECT id, nome, status, doc, tel, email, endereco, cidade, uf, contato, obs
                     FROM fornecedores
                     ORDER BY id DESC
                     LIMIT 2000");
  $rows = $st->fetchAll(PDO::FETCH_ASSOC);
  json_out(['ok' => true, 'data' => $rows]);
} catch (Throwable $e) {
  json_out(['ok' => false, 'msg' => 'Erro: ' . $e->getMessage()], 500);
}

?>