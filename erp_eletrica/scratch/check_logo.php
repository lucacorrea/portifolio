<?php
require_once "config/database.php";
$db = \App\Config\Database::getInstance()->getConnection();
$st = $db->query("SELECT * FROM filiais LIMIT 1");
$row = $st->fetch(PDO::FETCH_ASSOC);
echo "Colunas filiais: " . implode(', ', array_keys($row)) . "\n";
echo "Exemplo: "; print_r($row);
