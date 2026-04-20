<?php
require_once 'nfce/config.php';
$res = $pdo->query("SELECT * FROM sefaz_config LIMIT 1")->fetch();
print_r($res);
