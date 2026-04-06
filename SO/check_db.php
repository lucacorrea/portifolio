<?php
require_once 'c:/xampp/htdocs/SO/config/database.php';
echo "--- USUARIOS ---\n";
$stmt = $pdo->query("SELECT * FROM usuarios");
while($row = $stmt->fetch()) {
    print_r($row);
}
echo "--- SECRETARIAS ---\n";
$stmt = $pdo->query("SELECT * FROM secretarias");
while($row = $stmt->fetch()) {
    print_r($row);
}
