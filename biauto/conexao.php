<?php

declare(strict_types=1);

$host = 'localhost';
$porta = '3306';
$banco = 'u784961086_bi';
$usuario = 'u784961086_bi';
$senha = '|pE3/=oGP7';
$charset = 'utf8mb4';

$dsn = "mysql:host={$host};port={$porta};dbname={$banco};charset={$charset}";

$opcoes = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

try {
    $pdo = new PDO($dsn, $usuario, $senha, $opcoes);
    $pdo->exec("SET time_zone = '-04:00'");
} catch (PDOException $e) {
    http_response_code(500);
    exit('Erro ao conectar com o banco de dados. Verifique o arquivo conexao.php.');
}
