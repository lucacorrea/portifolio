<?php
require 'assets/php/conexao.php';
$pdo = db();
date_default_timezone_set('America/Manaus');
$pdo->exec("SET time_zone = '-04:00'");

$dia = date('Y-m-d');
$feiraId = 1;

$st = $pdo->prepare("SELECT id FROM romaneio_dia WHERE feira_id = :f AND data_ref = :d");
$st->execute([':f' => $feiraId, ':d' => $dia]);
$romId = $st->fetchColumn();

if (!$romId) {
    echo "No romaneio found for today ($dia).\n";
    exit;
}

echo "Romaneio ID: $romId\n\n";

$st = $pdo->prepare("SELECT ri.produtor_id, p.nome, COUNT(*) as items, SUM(ri.quantidade_entrada * ri.preco_unitario_dia) as total 
                     FROM romaneio_itens ri 
                     LEFT JOIN produtores p ON p.id = ri.produtor_id 
                     WHERE ri.romaneio_id = :rom 
                     GROUP BY ri.produtor_id");
$st->execute([':rom' => $romId]);
echo "Data in romaneio_itens:\n";
print_r($st->fetchAll(PDO::FETCH_ASSOC));

$st = $pdo->prepare("SELECT id, nome, documento FROM produtores WHERE feira_id = :f AND ativo = 1");
$st->execute([':f' => $feiraId]);
echo "\nActive Producers in DB:\n";
$prods = $st->fetchAll(PDO::FETCH_ASSOC);
foreach($prods as $p) {
    echo "ID: {$p['id']} | Name: {$p['nome']} | CPF: {$p['documento']}\n";
}
