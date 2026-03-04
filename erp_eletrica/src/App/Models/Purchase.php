<?php
namespace App\Models;

class Purchase extends BaseModel {
    protected $table = 'compras';

    public function create($data) {
        $sql = "INSERT INTO {$this->table} (fornecedor_id, usuario_id, valor_total) VALUES (?, ?, ?)";
        $this->query($sql, [$data['fornecedor_id'], $data['usuario_id'], $data['valor_total']]);
        return $this->db->lastInsertId();
    }

    public function getRecent($limit = 10) {
        return $this->query("
            SELECT c.*, f.nome_fantasia as fornecedor_nome 
            FROM {$this->table} c 
            LEFT JOIN fornecedores f ON c.fornecedor_id = f.id 
            ORDER BY c.data_compra DESC LIMIT $limit
        ")->fetchAll();
    }
}
