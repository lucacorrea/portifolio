<?php
namespace App\Models;

class CostCenter extends BaseModel {
    protected $table = 'centros_custo';

    public function getAllActive(int $filial_id) {
        return $this->where(['filial_id' => $filial_id, 'ativo' => 1]);
    }

    public function getByType(int $filial_id, string $tipo) {
        return $this->where(['filial_id' => $filial_id, 'tipo' => $tipo, 'ativo' => 1]);
    }
}
