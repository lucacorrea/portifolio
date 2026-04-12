<?php
require_once 'config.php';
$db = \App\Config\Database::getInstance()->getConnection();

echo "--- VENDA 317 ---\n";
$stmtV = $db->prepare("SELECT id, tipo_nota, status, valor_total FROM vendas WHERE id = ?");
$stmtV->execute([317]);
print_r($stmtV->fetch(PDO::FETCH_ASSOC));

echo "\n--- NFC-E EMITIDAS PARA VENDA 317 ---\n";
$stmtN = $db->prepare("SELECT * FROM nfce_emitidas WHERE venda_id = ?");
$stmtN->execute([317]);
print_r($stmtN->fetchAll(PDO::FETCH_ASSOC));
