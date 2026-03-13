<?php
require_once 'config.php';
$db = \App\Config\Database::getInstance()->getConnection();
$stmt = $db->query("DESCRIBE nfe_importadas");
$cols = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($cols, JSON_PRETTY_PRINT);
