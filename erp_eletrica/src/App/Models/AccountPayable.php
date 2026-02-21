<?php
namespace App\Models;

class AccountPayable extends BaseModel {
    protected $table = 'contas_pagar';

    public function getSummary() {
        return [
            'total_pendente' => $this->query("SELECT SUM(valor) FROM {$this->table} WHERE status = 'pendente'")->fetchColumn() ?: 0,
            'pago_hoje' => $this->query("SELECT SUM(valor) FROM {$this->table} WHERE status = 'pago' AND data_pagamento = CURRENT_DATE")->fetchColumn() ?: 0
        ];
    }

    public function getRecent($limit = 20) {
        return $this->query("
            SELECT cp.*, cc.nome as centro_custo 
            FROM {$this->table} cp 
            LEFT JOIN centros_custo cc ON cp.centro_custo_id = cc.id 
            ORDER BY cp.data_vencimento ASC LIMIT $limit
        ")->fetchAll();
    }
}
