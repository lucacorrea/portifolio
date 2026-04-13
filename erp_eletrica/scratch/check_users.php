<?php
require_once 'erp_eletrica/config.php';
$db = \App\Config\Database::getInstance()->getConnection();
$stmt = $db->query("SELECT id, nome, nivel, filial_id FROM usuarios");
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC), JSON_PRETTY_PRINT);
