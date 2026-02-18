<?php

namespace App\Core;

use PDO;
use PDOException;

class Database
{
    private static $instance = null;
    private $pdo;

    private function __construct()
    {
        // Load config from a secure location or define here for now (to be moved to env)
        $config = require __DIR__ . '/../../config/database.php';
        
        // Handle both array return and variable definition styles for backward compatibility if needed
        if ($config === 1) { 
             // If require returns 1, it means the file assigned variables. 
             // We'll rely on global constants or re-parse. 
             // For this new structure, we should return an array in the config file.
             // But to avoid breaking existing, let's create a new config/config.php
        }
        
        // Hardcoding for the transition step, will move to config file
        $host = 'localhost';
        $db   = 'u784961086_pdv';
        $user = 'u784961086_pdv';
        $pass = 'h?o3JYzu1E';
        $charset = 'utf8mb4';

        $dsn = "mysql:host=$host;dbname=$db;charset=$charset";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            $this->pdo = new PDO($dsn, $user, $pass, $options);
            $this->pdo->exec("SET time_zone = '-03:00'");
        } catch (PDOException $e) {
            throw new PDOException($e->getMessage(), (int)$e->getCode());
        }
    }

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection()
    {
        return $this->pdo;
    }
}
