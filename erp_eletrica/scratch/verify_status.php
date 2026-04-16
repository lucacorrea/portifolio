<?php
require_once "config/database.php";
$db = \App\Config\Database::getInstance()->getConnection();
$st = $db->query("SELECT id, venda_id, status_sefaz, mensagem FROM nfce_emitidas WHERE venda_id IN (355, 356, 357, 358) ORDER BY venda_id, id DESC");
echo "Registros NFCE:\n";
print_r($st->fetchAll(PDO::FETCH_ASSOC));
