<?php

declare(strict_types=1);

/**
 * Conexão com o banco de dados do BIAUTO.
 *
 * Trabalho acadêmico: as configurações ficam neste arquivo para facilitar
 * a instalação e apresentação do projeto em ambiente local/XAMPP.
 */

$host = 'localhost';
$porta = 3306;
$banco = 'biauto';
$usuario = 'root';
$senha = '';
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
