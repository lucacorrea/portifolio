<?php
namespace App\Services;

use App\Config\Database;
use PDO;
use Exception;

class MigrationService extends BaseService {
    private $db;

    public function __construct() {
        parent::__construct();
        $this->db = Database::getInstance()->getConnection();
        $this->createMigrationsTable();
    }

    private function createMigrationsTable() {
        $this->db->exec("CREATE TABLE IF NOT EXISTS migrations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            migration VARCHAR(255) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
    }

    public function run() {
        $path = dirname(__DIR__, 3) . '/migrations/*.sql';
        $files = glob($path);
        
        $executed = $this->db->query("SELECT migration FROM migrations")->fetchAll(PDO::FETCH_COLUMN);
        
        foreach ($files as $file) {
            $name = basename($file);
            if (!in_array($name, $executed)) {
                $sql = file_get_contents($file);
                try {
                    $this->db->exec($sql);
                    $stmt = $this->db->prepare("INSERT INTO migrations (migration) VALUES (?)");
                    $stmt->execute([$name]);
                    $this->logAction('migration_run', 'migrations', null, null, ['file' => $name]);
                } catch (Exception $e) {
                    error_log("Error running migration {$name}: " . $e->getMessage());
                    throw $e;
                }
            }
        }
    }
}
