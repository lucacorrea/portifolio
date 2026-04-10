<?php
require_once 'erp_eletrica/config.php';
$stmt = $pdo->query("SHOW COLUMNS FROM vendas");
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC), JSON_PRETTY_PRINT);
?>
