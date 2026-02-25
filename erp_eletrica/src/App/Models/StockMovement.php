<?php
namespace App\Models;

class StockMovement extends BaseModel {
    protected $table = 'movimentacoes_estoque';

    public function record($productId, $filialId, $qty, $type, $reason, $userId, $refId = null) {
        return $this->query(
            "INSERT INTO {$this->table} (produto_id, deposito_id, quantidade, tipo, motivo, usuario_id, referencia_id) VALUES (?, ?, ?, ?, ?, ?, ?)",
            [$productId, $filialId, $qty, $type, $reason, $userId, $refId]
        );
    }

    public function getHistory($productId = null, $limit = 50) {
        $sql = "SELECT m.*, p.nome as produto_nome, u.nome as usuario_nome 
                FROM {$this->table} m 
                JOIN produtos p ON m.produto_id = p.id 
                LEFT JOIN usuarios u ON m.usuario_id = u.id";
        $params = [];
        if ($productId) {
            $sql .= " WHERE m.produto_id = ?";
            $params[] = $productId;
        }
        $sql .= " ORDER BY m.data_movimento DESC LIMIT $limit";
        return $this->query($sql, $params)->fetchAll();
    }
}
