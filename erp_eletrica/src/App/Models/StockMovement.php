<?php
namespace App\Models;

class StockMovement extends BaseModel {
    protected $table = 'movimentacao_estoque';

    public function record($productId, $filialId, $qty, $type, $reason, $userId, $refId = null) {
        return $this->query(
            "INSERT INTO {$this->table} (produto_id, deposito_id, quantidade, tipo, motivo, usuario_id, referencia_id) VALUES (?, ?, ?, ?, ?, ?, ?)",
            [$productId, $filialId, $qty, $type, $reason, $userId, $refId]
        );
    }

    public function getHistory($filters = [], $limit = 50) {
        $filialId = $this->getFilialContext();
        
        $sql = "SELECT m.*, p.nome as produto_nome, p.codigo as produto_codigo, u.nome as usuario_nome 
                FROM {$this->table} m 
                JOIN produtos p ON m.produto_id = p.id 
                LEFT JOIN usuarios u ON m.usuario_id = u.id 
                WHERE 1=1";
        
        $params = [];
        
        if ($filialId) {
            $sql .= " AND m.deposito_id = ?";
            $params[] = $filialId;
        }

        if (!empty($filters['produto_id'])) {
            $sql .= " AND m.produto_id = ?";
            $params[] = $filters['produto_id'];
        }

        if (!empty($filters['desde'])) {
            $sql .= " AND m.data_movimento >= ?";
            $params[] = $filters['desde'] . ' 00:00:00';
        }

        if (!empty($filters['ate'])) {
            $sql .= " AND m.data_movimento <= ?";
            $params[] = $filters['ate'] . ' 23:59:59';
        }

        $sql .= " ORDER BY m.data_movimento DESC";
        
        if ($limit) {
            $sql .= " LIMIT $limit";
        }

        return $this->query($sql, $params)->fetchAll();
    }
}
