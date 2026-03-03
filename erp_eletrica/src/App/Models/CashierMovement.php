<?php
namespace App\Models;

class CashierMovement extends BaseModel {
    protected $table = 'caixa_movimentacoes';

    public function getByCaixa($caixaId) {
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE caixa_id = ? ORDER BY created_at DESC");
        $stmt->execute([$caixaId]);
        return $stmt->fetchAll();
    }
}
