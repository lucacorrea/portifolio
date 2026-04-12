<?php
require_once 'config.php';
$db = \App\Config\Database::getInstance()->getConnection();
$results = $db->query("SHOW INDEX FROM filiais")->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($results, JSON_PRETTY_PRINT);
