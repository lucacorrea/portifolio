<?php
require_once 'config.php';
$st = $pdo->query("DESCRIBE nfce_emitidas");
print_r($st->fetchAll(PDO::FETCH_ASSOC));
