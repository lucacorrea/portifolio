<?php
declare(strict_types=1);

namespace Sigesp\Core;

use PDO;
use PDOException;
use RuntimeException;

final class Database
{
    private static ?PDO $connection = null;
    public static function connection(): PDO
    {
        if (self::$connection instanceof PDO) return self::$connection;
        $config = require dirname(__DIR__, 2) . '/config/database.php';
        try {
            return self::$connection = new PDO($config['dsn'], $config['username'], $config['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]);
        } catch (PDOException $e) {
            throw new RuntimeException('Não foi possível conectar ao banco de dados. Verifique a configuração e o driver PDO.', 0, $e);
        }
    }
}
