<?php
declare(strict_types=1);

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

    return $pdo;
}

?>