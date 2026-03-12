<?php
require_once 'config.php';
$db = \App\Config\Database::getInstance()->getConnection();
$stmt = $db->query("SHOW TABLES");
while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
    echo $row[0] . "\n";
}
