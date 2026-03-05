<?php
/**
 * Database Configuration for IA Jurídica
 */

define('DB_HOST', 'localhost');
define('DB_NAME', 'u784961086_juridica');
define('DB_USER', 'u784961086_juridica');
define('DB_PASS', '$OeZzwC4n');
define('DB_CHARSET', 'utf8mb4');

function getDatabaseConnection() {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
    $options = [
        PDO::ATTR_ERR_MODE            => PDO::ERR_MODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    try {
        return new PDO($dsn, DB_USER, DB_PASS, $options);
    } catch (\PDOException $e) {
        // In production, log this and show a generic message
        die("Erro de conexão com o banco de dados: " . $e->getMessage());
    }
}
