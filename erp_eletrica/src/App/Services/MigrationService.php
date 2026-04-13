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
                
                // Divide por ponto e vírgula se houver múltiplos comandos num arquivo
                $queries = array_filter(array_map('trim', explode(';', $sql)));
                
                try {
                    $this->db->beginTransaction();
                    foreach ($queries as $query) {
                        if (empty($query)) continue;
                        
                        try {
                            $this->db->exec($query);
                        } catch (Exception $e) {
                            $msg = $e->getMessage();
                            // Ignora erros de duplicidade que ocorrem se rodar o SQL sem IF NOT EXISTS
                            // 1060: Duplicate column, 1061: Duplicate key, 1022: Duplicate key, 1050: Table already exists
                            if (strpos($msg, '1060') !== false || strpos($msg, '1061') !== false || 
                                strpos($msg, '1022') !== false || strpos($msg, '1050') !== false ||
                                strpos($msg, 'already exists') !== false) {
                                continue;
                            }
                            throw $e;
                        }
                    }
                    
                    $stmt = $this->db->prepare("INSERT INTO migrations (migration) VALUES (?)");
                    $stmt->execute([$name]);
                    $this->db->commit();
                    
                    $this->logAction('migration_run', 'migrations', null, null, ['file' => $name]);
                } catch (Exception $e) {
                    $this->db->rollBack();
                    error_log("Error running migration {$name}: " . $e->getMessage());
                    throw $e;
                }
            }
        }
    }
}
