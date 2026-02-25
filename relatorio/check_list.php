<?php
require 'assets/php/conexao.php';
$pdo = db();
date_default_timezone_set('America/Manaus');
$pdo->exec("SET time_zone = '-04:00'");

$dia = date('Y-m-d');
echo "Dia PHP: $dia\n";

$st = $pdo->query("SELECT id, feira_id, data_ref, status FROM romaneio_dia ORDER BY id DESC LIMIT 5");
echo "\nÚltimos 5 Romaneios:\n";
print_r($st->fetchAll(PDO::FETCH_ASSOC));

$st = $pdo->prepare("SELECT id FROM romaneio_dia WHERE feira_id = 1 AND data_ref = :d");
$st->execute([':d' => $dia]);
$id = $st->fetchColumn();
echo "\nRomaneio ID para hoje ($dia): " . ($id ?: 'NÃO ENCONTRADO') . "\n";

if ($id) {
    $st = $pdo->prepare("SELECT COUNT(*) FROM romaneio_itens WHERE romaneio_id = :id");
    $st->execute([':id' => $id]);
    echo "Itens no romaneio $id: " . $st->fetchColumn() . "\n";
    
    $st = $pdo->prepare("SELECT p.nome, ri.quantidade_entrada FROM romaneio_itens ri JOIN produtores p ON p.id = ri.produtor_id WHERE ri.romaneio_id = :id");
    $st->execute([':id' => $id]);
    echo "Produtores encontrados:\n";
    print_r($st->fetchAll(PDO::FETCH_ASSOC));
}
