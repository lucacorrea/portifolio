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
    $host   = "localhost";
    $porta  = "3306";
    $banco  = "u784961086_distribuidora";
    $usuario= "u784961086_distribuidora";
    $senha  = "Usye7vf2*o";
    // =========================

    $dsn = "mysql:host={$host};port={$porta};dbname={$banco};charset=utf8mb4";

    $pdo = new PDO($dsn, $usuario, $senha, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);

    // Timezone do Amazonas (America/Manaus = -04:00)
    try {
        $pdo->exec("SET time_zone = '-04:00'");
    } catch (Throwable $e) {
        // ignora se não puder setar
    }

    return $pdo;
}