<?php
namespace App\Models;

class AccountReceivablePayment extends BaseModel {
    protected $table = 'fiados_pagamentos';

    public function findByFiado($fiadoId) {
        return $this->query("SELECT * FROM {$this->table} WHERE fiado_id = ? ORDER BY created_at DESC", [$fiadoId])->fetchAll();
    }

    public function getSumByFiado($fiadoId) {
        return $this->query("SELECT SUM(valor) FROM {$this->table} WHERE fiado_id = ?", [$fiadoId])->fetchColumn() ?: 0;
    }
}
