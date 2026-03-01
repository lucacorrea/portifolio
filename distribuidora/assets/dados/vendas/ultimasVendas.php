<?php
declare(strict_types=1);

require_once __DIR__ . '/../../conexao.php';
require_once __DIR__ . '/./_helpers.php';

$pdo = db();

try {
  $stmt = $pdo->query("SELECT id, data, total, created_at, canal FROM vendas ORDER BY id DESC LIMIT 10");
  $rows = $stmt->fetchAll();
} catch (Throwable $e) {
  json_response(['ok' => false, 'msg' => 'Tabela vendas não encontrada. Rode o SQL de criação.'], 500);
}

$items = array_map(function ($r) {
  return [
    'id'    => (int)$r['id'],
    'date'  => (string)$r['created_at'],
    'data'  => (string)$r['data'],
    'total' => (float)$r['total'],
    'canal' => (string)$r['canal'],
  ];
}, $rows);

$next = 1;
try {
  $next = (int)$pdo->query("SELECT COALESCE(MAX(id),0)+1 FROM vendas")->fetchColumn();
} catch (Throwable $e) {}

json_response(['ok' => true, 'items' => $items, 'next' => $next]);

?>