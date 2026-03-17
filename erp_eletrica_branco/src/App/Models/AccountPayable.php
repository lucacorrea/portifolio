<?php
namespace App\Models;

class AccountPayable extends BaseModel {
    protected $table = 'contas_pagar';

    public function getSummary() {
        $filialId = $this->getFilialContext();
        $where = $filialId ? " AND filial_id = ?" : "";
        $params = $filialId ? [$filialId] : [];

        return [
            'total_pendente' => $this->query("SELECT SUM(valor) FROM {$this->table} WHERE status = 'pendente' $where", $params)->fetchColumn() ?: 0,
            'pago_hoje' => $this->query("SELECT SUM(valor) FROM {$this->table} WHERE status = 'pago' AND data_pagamento = CURRENT_DATE $where", $params)->fetchColumn() ?: 0
        ];
    }

    public function getRecent($limit = 20) {
        $filialId = $this->getFilialContext();
        $where = $filialId ? "WHERE cp.filial_id = ?" : "";
        $params = $filialId ? [$filialId] : [];

        return $this->query("
            SELECT cp.* 
            FROM {$this->table} cp 
            $where
            ORDER BY cp.data_vencimento ASC LIMIT $limit
        ", $params)->fetchAll();
    }
}
