<?php
require_once 'nfce/config.php';
$res = $pdo->query("SELECT id, nome FROM filiais WHERE principal = 1")->fetch();
print_r($res);
