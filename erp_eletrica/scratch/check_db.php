<?php
require_once 'config.php';
$db = \App\Config\Database::getInstance()->getConnection();
$stmt = $db->query("SELECT id, chave_acesso, LENGTH(xml) as len, LEFT(xml, 50) as snippet FROM nfe_importadas ORDER BY id DESC LIMIT 5");
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($results, JSON_PRETTY_PRINT);
