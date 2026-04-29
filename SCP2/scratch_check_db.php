<?php
$host = 'localhost'; 
$dbname = 'u784961086_procuradoria';
$username = 'u784961086_procuradoria';
$password = '@XeFGMa8';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $stmt = $pdo->query("SELECT DISTINCT analisador FROM processos");
    $names = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "Names in DB:\n";
    foreach ($names as $name) {
        echo "'" . $name . "' (length: " . strlen($name) . ")\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
