<?php
namespace App\Services;

use App\Config\Database;
use PDO;
use Exception;

class SyncService extends BaseService {
    private $db;

    public function __construct() {
        parent::__construct();
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Verifica se o servidor na nuvem está acessível
     */
    public function isOnline() {
        if (APP_MODE === 'CLOUD_MATRIZ') return true;

        $url = CLOUD_API_URL;
        $host = parse_url($url, PHP_URL_HOST);
        
        // Tenta primeiro um check via fsockopen rápido (TCP)
        $waitTimeoutInSeconds = 1;
        $fp = @fsockopen($host, 443, $errCode, $errStr, $waitTimeoutInSeconds);
        
        if ($fp) {
           fclose($fp);
           return true;
        }

        // Fallback: Verifica se o DNS resolve (se não resolver, está offline)
        $ip = gethostbyname($host);
        if ($ip === $host) {
            return false; // DNS não resolveu
        }

        return true;
    }

    /**
     * Sincroniza todos os dados pendentes (Vendas, Caixas, Estoque)
     */
    public function syncLocalToCloud() {
        if (!$this->isOnline()) {
            return ['success' => false, 'message' => 'Sem conexão com a nuvem no momento.'];
        }

        $results = [
            'vendas' => $this->syncTable('vendas', 'venda_id'),
            'nfce_emitidas' => $this->syncTable('nfce_emitidas'),
            'caixas' => $this->syncTable('caixas'),
            'caixa_movimentacoes' => $this->syncTable('caixa_movimentacoes'),
            'contas_receber' => $this->syncTable('contas_receber'),
            'movimentacoes_estoque' => $this->syncTable('movimentacoes_estoque'),
        ];

        return ['success' => true, 'results' => $results];
    }

    /**
     * Sincroniza uma tabela específica enviando dados para a nuvem
     */
    private function syncTable($tableName, $relationField = null) {
        $stmt = $this->db->prepare("SELECT * FROM {$tableName} WHERE sync_status = 'pending' LIMIT 50");
        $stmt->execute();
        $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($records)) return 0;

        $syncedCount = 0;
        foreach ($records as $record) {
            // Se for uma venda, anexa os itens
            if ($tableName === 'vendas') {
                $stmtItens = $this->db->prepare("SELECT * FROM vendas_itens WHERE venda_id = ?");
                $stmtItens->execute([$record['id']]);
                $record['itens'] = $stmtItens->fetchAll(PDO::FETCH_ASSOC);
            }

            try {
                if ($this->sendToCloud($tableName, $record)) {
                    $this->markAsSynced($tableName, $record['id']);
                    $syncedCount++;
                }
            } catch (Exception $e) {
                error_log("Erro ao sincronizar {$tableName} ID {$record['id']}: " . $e->getMessage());
                $this->markAsError($tableName, $record['id']);
            }
        }

        return $syncedCount;
    }

    /**
     * Realiza a chamada HTTP POST para a API na nuvem
     */
    private function sendToCloud($tableName, $data) {
        $url = CLOUD_API_URL . '/' . $tableName;
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            'filial_id' => defined('FILIAL_ID') ? FILIAL_ID : 1,
            'data' => $data
        ]));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'X-App-Mode: ' . APP_MODE
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return ($httpCode === 200 || $httpCode === 201);
    }

    private function markAsSynced($table, $id) {
        $stmt = $this->db->prepare("UPDATE {$table} SET sync_status = 'synced' WHERE id = ?");
        $stmt->execute([$id]);
    }

    private function markAsError($table, $id) {
        $stmt = $this->db->prepare("UPDATE {$table} SET sync_status = 'error' WHERE id = ?");
        $stmt->execute([$id]);
    }
}
