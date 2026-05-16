<?php
require_once 'config.php';
$db = \App\Config\Database::getInstance()->getConnection();
try {
    $stmt = $db->query("DESCRIBE usuarios");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    print_r($columns);
} catch (Exception $e) {
    echo $e->getMessage();
}
