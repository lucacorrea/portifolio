<?php
declare(strict_types=1);

require_once __DIR__ . '/../../conexao.php';
require_once __DIR__ . '/./_helpers.php';

$pdo = db();

$q = trim((string)($_GET['q'] ?? ''));
$limit = 30;

if ($q === '') {
  json_response(['ok' => true, 'items' => []]);
}

$like = '%' . $q . '%';

$sql = "
  SELECT id, codigo, nome, unidade, preco, estoque, imagem
  FROM produtos
  WHERE status = 'ATIVO'
    AND (codigo LIKE :q OR nome LIKE :q)
  ORDER BY nome ASC
  LIMIT {$limit}
";

$stmt = $pdo->prepare($sql);
$stmt->execute([':q' => $like]);
$items = $stmt->fetchAll();

$out = array_map(function ($r) {
  return [
    'id'      => (int)$r['id'],
    'code'    => (string)$r['codigo'],
    'name'    => (string)$r['nome'],
    'unit'    => (string)($r['unidade'] ?? ''),
    'price'   => (float)($r['preco'] ?? 0),
    'stock'   => (int)($r['estoque'] ?? 0),
    'img'     => (string)($r['imagem'] ?? ''),
  ];
}, $items);

json_response(['ok' => true, 'items' => $out]);

?>