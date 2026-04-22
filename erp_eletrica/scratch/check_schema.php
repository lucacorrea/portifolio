<?php
require_once 'config.php';
$stmt = $pdo->query("PRAGMA table_info(usuarios)");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
