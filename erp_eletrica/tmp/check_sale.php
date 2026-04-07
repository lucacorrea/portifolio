<?php
require_once 'config.php';
$id = 253;
$st = $pdo->prepare("SELECT * FROM notas_fiscais WHERE venda_id = ?");
$st->execute([$id]);
$rows = $st->fetchAll(PDO::FETCH_ASSOC);
echo "Resultados para venda_id $id:\n";
print_r($rows);

$st2 = $pdo->prepare("SELECT id, status, tipo_nota FROM vendas WHERE id = ?");
$st2->execute([$id]);
echo "\nStatus da venda $id:\n";
print_r($st2->fetch(PDO::FETCH_ASSOC));
