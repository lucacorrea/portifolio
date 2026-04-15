<?php
namespace App\Controllers;

class SyncController extends BaseController {

    /**
     * Retorna TODOS os produtos da filial atual para cache offline.
     * Chamado periodicamente pelo offline-bridge.js.
     */
    public function cacheProducts() {
        header('Content-Type: application/json');
        header('Cache-Control: no-store');

        $db = \App\Config\Database::getInstance()->getConnection();
        $filialId = $_SESSION['filial_id'] ?? 1;
        $isMatriz = ($_SESSION['is_matriz'] ?? false) || ((int)$filialId === 1);

        $join = $isMatriz ? "LEFT JOIN" : "INNER JOIN";

        try {
            $sql = "SELECT p.id, p.nome, p.preco_venda, p.unidade, p.imagens, p.codigo,
                           COALESCE(ef.quantidade, 0) as stock_qty
                    FROM produtos p
                    $join estoque_filiais ef ON p.id = ef.produto_id AND ef.filial_id = ?
                    ORDER BY p.nome ASC";

            $stmt = $db->prepare($sql);
            $stmt->execute([$filialId]);
            $products = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            echo json_encode($products);
        } catch (\Exception $e) {
            error_log("[SYNC] Erro ao cachear produtos: " . $e->getMessage());
            echo json_encode([]);
        }
    }

    /**
     * Retorna TODOS os clientes da filial atual para cache offline.
     */
    public function cacheClients() {
        header('Content-Type: application/json');
        header('Cache-Control: no-store');

        $db = \App\Config\Database::getInstance()->getConnection();
        $filialId = $_SESSION['filial_id'] ?? 1;

        try {
            $sql = "SELECT id, nome, cpf_cnpj, telefone, endereco 
                    FROM clientes 
                    WHERE filial_id = ?
                    ORDER BY nome ASC";

            $stmt = $db->prepare($sql);
            $stmt->execute([$filialId]);
            $clients = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            echo json_encode($clients);
        } catch (\Exception $e) {
            error_log("[SYNC] Erro ao cachear clientes: " . $e->getMessage());
            echo json_encode([]);
        }
    }

    /**
     * Processa um lote de operações realizadas offline.
     * Recebe JSON com array de operações (vendas, pré-vendas).
     */
    public function syncBatch() {
        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'error' => 'Método não permitido']);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true);

        if (!$input || !isset($input['operations']) || !is_array($input['operations'])) {
            echo json_encode(['success' => false, 'error' => 'Payload inválido']);
            return;
        }

        $syncService = new \App\Services\SyncService();
        $results = [];
        $allSuccess = true;

        foreach ($input['operations'] as $op) {
            try {
                $result = $syncService->processOperation($op);
                $results[] = $result;
                
                if (!$result['success']) {
                    $allSuccess = false;
                }
            } catch (\Exception $e) {
                $allSuccess = false;
                $results[] = [
                    'success' => false,
                    'temp_id' => $op['temp_id'] ?? null,
                    'type' => $op['type'] ?? 'unknown',
                    'error' => $e->getMessage()
                ];

                error_log("[SYNC] Erro ao processar operação: " . $e->getMessage());
            }
        }

        echo json_encode([
            'success' => $allSuccess,
            'results' => $results,
            'processed_at' => date('c'),
            'total_operations' => count($input['operations']),
            'successful' => count(array_filter($results, fn($r) => $r['success'])),
            'failed' => count(array_filter($results, fn($r) => !$r['success']))
        ]);
    }
}
