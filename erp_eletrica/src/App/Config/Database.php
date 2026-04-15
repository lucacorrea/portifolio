<?php
namespace App\Config;

use PDO;
use PDOException;

class Database {
    private static $instance = null;
    private $connection;
    private $connectionType = 'remote'; // 'remote' or 'local'

    private function __construct() {
        // Se estiver rodando no XAMPP local E o sync estiver habilitado,
        // tenta conectar ao remoto primeiro, com fallback para local
        if (defined('IS_LOCAL_SERVER') && IS_LOCAL_SERVER && defined('SYNC_ENABLED') && SYNC_ENABLED) {
            $this->connectWithFailover();
        } else {
            // Modo padrão: conexão direta usando config.php
            $this->connectDirect();
        }
    }

    /**
     * Conexão direta (modo padrão — Hostinger ou XAMPP local)
     */
    private function connectDirect(): void {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $this->connection = new PDO($dsn, DB_USER, DB_PASS);
            $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->connection->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $this->connection->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);
        } catch (PDOException $e) {
            die("Connection error: " . $e->getMessage());
        }
    }

    /**
     * Conexão com failover: Remote → Local
     * Usado quando o sistema está rodando no XAMPP local
     */
    private function connectWithFailover(): void {
        // 1. Tentar banco REMOTO (Hostinger)
        try {
            $dsn = "mysql:host=" . REMOTE_DB_HOST . ";port=" . (defined('REMOTE_DB_PORT') ? REMOTE_DB_PORT : 3306) . ";dbname=" . REMOTE_DB_NAME . ";charset=utf8mb4";
            $this->connection = new PDO($dsn, REMOTE_DB_USER, REMOTE_DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_TIMEOUT => 5, // Timeout curto para failover rápido
                PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
            ]);
            $this->connectionType = 'remote';
            return;
        } catch (PDOException $e) {
            error_log("[DATABASE] Falha ao conectar ao remoto: " . $e->getMessage() . " — Tentando local...");
        }

        // 2. Fallback: banco LOCAL (MariaDB do XAMPP)
        try {
            $dsn = "mysql:host=" . LOCAL_DB_HOST . ";port=" . (defined('LOCAL_DB_PORT') ? LOCAL_DB_PORT : 3306) . ";dbname=" . LOCAL_DB_NAME . ";charset=utf8mb4";
            $this->connection = new PDO($dsn, LOCAL_DB_USER, LOCAL_DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
            ]);
            $this->connectionType = 'local';
            error_log("[DATABASE] ⚠️ Usando banco LOCAL (offline mode)");
            return;
        } catch (PDOException $e) {
            die("Erro crítico: Nenhum banco disponível. Remoto e local falharam.\n" .
                "Local error: " . $e->getMessage());
        }
    }

    public static function getInstance() {
        if (!self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection() {
        return $this->connection;
    }

    /**
     * Retorna o tipo de conexão ativa: 'remote' ou 'local'
     */
    public function getConnectionType(): string {
        return $this->connectionType;
    }

    /**
     * Verifica se está conectado ao banco local (modo offline)
     */
    public function isLocal(): bool {
        return $this->connectionType === 'local';
    }
}

