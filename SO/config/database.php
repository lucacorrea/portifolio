<?php
// config/database.php

$host = '127.0.0.1';
$db_user = 'root';
$db_pass = ''; // Senha padrão do XAMPP é vazia
$dbname = 'sgao';
$schema_file = __DIR__ . '/../database/schema.sql';

try {
    // 1. Conecta ao MySQL (sem o banco ainda)
    $pdo = new PDO("mysql:host=$host", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 2. Cria o banco se não existir
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    
    // 3. Conecta especificamente ao banco sgao
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // 4. Se a tabela 'usuarios' não existir, roda o schema inicial
    $query = $pdo->query("SHOW TABLES LIKE 'usuarios'");
    if (!$query->fetch()) {
        $sql = file_get_contents($schema_file);
        $pdo->exec($sql);
    }

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

} catch (PDOException $e) {
    die("Erro ao conectar ao banco de dados MySQL: " . $e->getMessage());
}

function getPDO() {
    global $pdo;
    return $pdo;
}
