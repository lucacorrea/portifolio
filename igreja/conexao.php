<?php
$host = "localhost";
$db   = "u784961086_igreja_membros";
$user = "u784961086_igreja_membros";
$pass = "G/q4fb2j^5W";
$charset = "utf8mb4";

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";

$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    die("Erro na conexão: " . $e->getMessage());
}
?>
