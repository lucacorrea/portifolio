<?php
require_once 'SO/config/database.php';
$stmt = $pdo->query("SHOW COLUMNS FROM usuarios LIKE 'nivel'");
$row = $stmt->fetch();
echo "Column: " . $row['Field'] . "\n";
echo "Type: " . $row['Type'] . "\n";
?>
