<?php
date_default_timezone_set('America/Manaus');
$host = 'localhost';        // Nome do host (geralmente 'localhost')
$dbname = 'u784961086_semas';  // Nome do seu banco de dados
$user = 'u784961086_semas';          // Usuário do banco
$pass = 'OCEb8;Uy';            // Senha do banco

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    // Configura o PDO para lançar exceções em caso de erro
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erro na conexão: " . $e->getMessage());
}
?>