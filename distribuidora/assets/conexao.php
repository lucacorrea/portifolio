<?php
declare(strict_types=1);

/**
 * assets/php/conexao.php
 * Conexão externa PDO (singleton) via função db(): PDO
 *
 * Ajuste as credenciais abaixo.
 */

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    // ====== AJUSTE AQUI ======
    $DB_HOST = 'localhost';
    $DB_PORT = '3306';
    $DB_NAME = 'u784961086_distribuidora';
    $DB_USER = 'u784961086_distribuidora';
    $DB_PASS = 'Usye7vf2*o';
    // =========================

    // DSN (sem precisar definir collation/charset no SQL; aqui é só conexão)
    $dsn = "mysql:host={$DB_HOST};port={$DB_PORT};dbname={$DB_NAME};charset=utf8mb4";

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4; SET time_zone='-03:00';",
    ];

    $pdo = new PDO($dsn, $DB_USER, $DB_PASS, $options);
    return $pdo;
}