<?php
namespace App\Models;

class Setting extends BaseModel {
    protected $table = 'configuracoes';

    public function getAll() {
        $data = $this->all();
        $settings = [];
        foreach ($data as $row) {
            $settings[$row['chave']] = $row['valor'];
        }
        return $settings;
    }

    public function save($key, $value) {
        $stmt = $this->db->prepare("INSERT INTO {$this->table} (chave, valor) VALUES (?, ?) ON DUPLICATE KEY UPDATE valor = ?");
        return $stmt->execute([$key, $value, $value]);
    }
}
