<?php
declare(strict_types=1);

/**
 * Conexão PDO (MySQL/MariaDB)
 * Uso:
 *   require __DIR__ . '/conexao.php';
 *   $pdo = db();
 */

function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    // ✅ Ajuste aqui para o seu ambiente
    $DB_HOST = 'localhost';
    $DB_NAME = 'SEU_BANCO';
    $DB_USER = 'root';
    $DB_PASS = '';
    $DB_PORT = '3306';

    $dsn = "mysql:host={$DB_HOST};port={$DB_PORT};dbname={$DB_NAME};charset=utf8mb4";

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // lança exceções
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // fetch padrão em array assoc
        PDO::ATTR_EMULATE_PREPARES   => false,                  // prepara de verdade
    ];

    try {
        $pdo = new PDO($dsn, $DB_USER, $DB_PASS, $options);

        // Opcional: definir timezone do MySQL para bater com seu PHP
        $pdo->exec("SET time_zone = '-03:00'");

        return $pdo;

    } catch (PDOException $e) {
        // Em produção, você pode trocar por log e mensagem genérica
        http_response_code(500);
        die("Erro ao conectar no banco: " . $e->getMessage());
    }
}
