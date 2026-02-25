<?php
namespace App\Models;

class AccountReceivable extends BaseModel {
    protected $table = 'contas_receber';

    public function getSummary() {
        return [
            'total_pendente' => $this->query("SELECT SUM(valor) FROM {$this->table} WHERE status = 'pendente'")->fetchColumn() ?: 0,
            'recebido_hoje' => $this->query("SELECT SUM(valor) FROM {$this->table} WHERE status = 'pago' AND data_pagamento = CURRENT_DATE")->fetchColumn() ?: 0
        ];
    }

    public function getRecent($limit = 20) {
        return $this->query("
            SELECT cr.*, v.id as venda_id, c.nome as cliente_nome 
            FROM {$this->table} cr 
            LEFT JOIN vendas v ON cr.venda_id = v.id 
            LEFT JOIN clientes c ON v.cliente_id = c.id 
            ORDER BY cr.data_vencimento ASC LIMIT $limit
        ")->fetchAll();
    }
}
