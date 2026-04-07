<?php
require_once 'config.php';
$id = 253;
$st = $pdo->prepare("SELECT id, venda_id, chave, protocolo, status_sefaz, mensagem FROM nfce_emitidas WHERE venda_id = ?");
$st->execute([$id]);
$rows = $st->fetchAll(PDO::FETCH_ASSOC);
echo "Dados na nfce_emitidas para venda_id $id:\n";
print_r($rows);
