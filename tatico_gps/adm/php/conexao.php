<?php
declare(strict_types=1);

$host = 'localhost';
$dbname = 'u784961086_tatico';
$user = 'u784961086_tatico';
$pass = '~X8Tm@S7t';

try {
    $pdo = new PDO(
        "mysql:host={$host};dbname={$dbname};charset=utf8mb4",
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
} catch (PDOException $e) {
    die('Erro de conexão com o banco: ' . $e->getMessage());
}

?>