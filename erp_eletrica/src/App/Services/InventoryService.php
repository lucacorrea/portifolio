<?php
namespace App\Services;

use App\Models\Product;
use App\Models\StockMovement;
use App\Repositories\BaseRepository;

class InventoryService extends BaseService {
    private $productModel;
    private $movementModel;

    public function __construct() {
        parent::__construct();
        $this->productModel = new Product();
        $this->movementModel = new StockMovement();
    }

    public function recordMovement(array $data) {
        $productId = $data['produto_id'];
        $qty = $data['quantidade'];
        $type = $data['tipo']; // entrada, saida, ajuste
        $reason = $data['motivo'];
        $lote = $data['lote'] ?? null;
        $filialId = $data['filial_id'] ?? ($_SESSION['filial_id'] ?? 1);

        // Validation: Prevent negative stock on manual 'saida'
        if ($type === 'saida' && !$this->productModel->hasEnoughStock($productId, $qty, $filialId)) {
            $stmtProd = $this->db->prepare("SELECT nome FROM produtos WHERE id = ?");
            $stmtProd->execute([$productId]);
            $productName = $stmtProd->fetchColumn();
            throw new \Exception("Saldo insuficiente para realizar a saída do produto: $productName.");
        }

        // Update main stock
        $this->productModel->updateStock($productId, $qty, $type, $filialId);

        // Record detailed stock if lot is provided
        if ($lote) {
            $this->db->prepare("
                INSERT INTO estoque_detalhado (produto_id, deposito_id, lote, quantidade) 
                VALUES (?, ?, ?, ?) 
                ON DUPLICATE KEY UPDATE quantidade = quantidade " . ($type == 'entrada' ? '+' : '-') . " ?
            ")->execute([$productId, $filialId, $lote, $qty, $qty]);
        }

        // Record movement history
        $this->movementModel->record($productId, $filialId, $qty, $type, $reason, $_SESSION['usuario_id']);
        
        $this->logAction('stock_movement', 'produtos', $productId, null, $data);
    }

    public function registerProblem(array $data) {
        $productId = $data['produto_id'];
        $qty = (float)$data['quantidade'];
        $motivo = $data['motivo'];
        $subtrairEstoque = isset($data['subtrair_estoque']) && $data['subtrair_estoque'] == '1';
        $filialId = $_SESSION['filial_id'] ?? 1;

        if ($qty <= 0) throw new \Exception("Quantidade deve ser maior que zero.");

        if ($subtrairEstoque) {
            // Verifica se tem estoque
            if (!$this->productModel->hasEnoughStock($productId, $qty, $filialId)) {
                throw new \Exception("Estoque insuficiente para retirar do saldo.");
            }

            // Realiza a saída do estoque
            $this->productModel->updateStock($productId, $qty, 'saida', $filialId);
            
            // Grava movimentação
            $this->movementModel->record($productId, $filialId, $qty, 'saida', "Avaria/Problema: $motivo", $_SESSION['usuario_id']);
        }

        // Grava na tabela de problemas
        $problemModel = new \App\Models\ProductProblem();
        return $problemModel->save([
            'produto_id' => $productId,
            'filial_id' => $filialId,
            'quantidade' => $qty,
            'motivo' => $motivo,
            'status' => 'pendente',
            'usuario_id' => $_SESSION['usuario_id']
        ]);
    }

    public function getInventorySummary() {
        $filialId = $_SESSION['filial_id'] ?? null;
        $isMatriz = $_SESSION['is_matriz'] ?? false;
        $where = (!$isMatriz && $filialId) ? " WHERE filial_id = $filialId" : "";
        
        return [
            'total_valuation' => $this->db->query("SELECT SUM(preco_custo * quantidade) FROM produtos $where")->fetchColumn(),
            'critical_items' => $this->productModel->getCriticalStock($isMatriz ? null : $filialId),
            'top_categories' => $this->db->query("SELECT categoria, SUM(quantidade) as total FROM produtos $where GROUP BY categoria ORDER BY total DESC")->fetchAll()
        ];
    }
}
