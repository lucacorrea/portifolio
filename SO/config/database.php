<?php
// config/database.php

$host = 'localhost';
$db_user = 'u784961086_so';
$db_pass = 'Y>g39k3ql'; // Senha padrão do XAMPP é vazia
$dbname = 'u784961086_so';
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

    // 5. Auto-migração: Adicionar colunas se não existirem
    try {
        // Coluna arquivo_orcamento
        $query = $pdo->query("SHOW COLUMNS FROM oficios LIKE 'arquivo_orcamento'");
        if (!$query->fetch()) {
            $pdo->exec("ALTER TABLE oficios ADD COLUMN arquivo_orcamento VARCHAR(255) DEFAULT NULL AFTER usuario_id");
        }

        // Coluna arquivo_oficio (Novo)
        $query = $pdo->query("SHOW COLUMNS FROM oficios LIKE 'arquivo_oficio'");
        if (!$query->fetch()) {
            $pdo->exec("ALTER TABLE oficios ADD COLUMN arquivo_oficio VARCHAR(255) DEFAULT NULL AFTER arquivo_orcamento");
        }
    } catch (PDOException $e) {
        // Ignora erros se a tabela ainda não existir (o item 4 cuidará disso)
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
