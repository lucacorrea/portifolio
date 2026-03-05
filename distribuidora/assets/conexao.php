<?php
declare(strict_types=1);

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

/**
 * ./assets/conexao.php
 * PDO simples (singleton) - função db(): PDO
 */

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;

    // ====== AJUSTE AQUI ======
    $host = "localhost";
    $banco = "u784961086_distribuidora";
    $usuario = "u784961086_distribuidora";
    $senha = "Usye7vf2*o";
    // =========================

    $dsn = "mysql:host={$host};dbname={$banco};charset=utf8";

    $pdo = new PDO($dsn, $usuario, $senha, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    /*
    $initFile = __DIR__ . '/dados/db_init.php';
    if (is_file($initFile)) {
        require_once $initFile;
        if (function_exists('db_initialize')) {
            db_initialize($pdo);
        }
    }
    */

    return $pdo;
}

?>