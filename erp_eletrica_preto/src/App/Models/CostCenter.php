<?php
namespace App\Models;

class CostCenter extends BaseModel {
    protected $table = 'centros_custo';

    public function getAllActive(int $filial_id) {
        return $this->query("SELECT * FROM {$this->table} WHERE filial_id = ? AND ativo = 1 ORDER BY nome ASC", [$filial_id])->fetchAll();
    }

    public function getByType(int $filial_id, string $tipo) {
        return $this->query("SELECT * FROM {$this->table} WHERE filial_id = ? AND tipo = ? AND ativo = 1 ORDER BY nome ASC", [$filial_id, $tipo])->fetchAll();
    }
}
