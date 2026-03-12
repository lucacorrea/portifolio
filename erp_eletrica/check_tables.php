<?php
require_once 'config.php';
$db = \App\Config\Database::getInstance()->getConnection();
$stmt = $db->query("DESCRIBE pre_vendas");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo $row['Field'] . " - " . $row['Type'] . "\n";
}
echo "---\n";
$stmt = $db->query("DESCRIBE vendas");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo $row['Field'] . " - " . $row['Type'] . "\n";
}
