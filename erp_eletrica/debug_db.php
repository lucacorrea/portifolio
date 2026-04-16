<?php
require 'config.php';
$db = \App\Config\Database::getInstance()->getConnection();

$res = [];

// Check MAX(id)
$res['max_id'] = $db->query("SELECT MAX(id) FROM produtos")->fetchColumn();

// Check numeric codes with length <= 6
$res['last_numeric_code'] = $db->query("SELECT codigo FROM produtos WHERE codigo REGEXP '^[0-9]+$' AND LENGTH(codigo) <= 6 ORDER BY CAST(codigo AS UNSIGNED) DESC LIMIT 1")->fetchColumn();

// Check if 12113 exists
$res['exists_12113'] = $db->query("SELECT COUNT(*) FROM produtos WHERE codigo = '12113'")->fetchColumn();

file_put_contents('db_check.json', json_encode($res, JSON_PRETTY_PRINT));
echo "Done\n";
