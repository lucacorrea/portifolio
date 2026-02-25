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
        $filialId = $data['filial_id'] ?? 1;

        // Update main stock
        $this->productModel->updateStock($productId, $qty, $type);

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

    public function getInventorySummary() {
        return [
            'total_valuation' => $this->db->query("SELECT SUM(preco_custo * quantidade) FROM produtos")->fetchColumn(),
            'critical_items' => $this->productModel->getCriticalStock(),
            'top_categories' => $this->db->query("SELECT categoria, SUM(quantidade) as total FROM produtos GROUP BY categoria ORDER BY total DESC")->fetchAll()
        ];
    }
}
