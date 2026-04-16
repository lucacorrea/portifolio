<?php
require_once "config/database.php";
$st = $pdo->query("SHOW COLUMNS FROM sefaz_config");
echo "SEFAZ_CONFIG:\n";
print_r($st->fetchAll(PDO::FETCH_COLUMN));
$st = $pdo->query("SHOW COLUMNS FROM filiais");
echo "\nFILIAIS:\n";
print_r($st->fetchAll(PDO::FETCH_COLUMN));
