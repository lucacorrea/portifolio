<?php
namespace App\Models;

class Sale extends BaseModel {
    protected $table = 'vendas';

    public function create($data) {
        $sql = "INSERT INTO {$this->table} (cliente_id, usuario_id, filial_id, valor_total, forma_pagamento, status) 
                VALUES (?, ?, ?, ?, ?, ?)";
        $params = [
            $data['cliente_id'],
            $data['usuario_id'],
            $data['filial_id'],
            $data['valor_total'],
            $data['forma_pagamento'],
            'concluido'
        ];
        $this->query($sql, $params);
        return $this->db->lastInsertId();
    }

    public function getRecent($limit = 10) {
        return $this->query("
            SELECT v.*, c.nome as cliente_nome, u.nome as vendedor_nome 
            FROM {$this->table} v 
            LEFT JOIN clientes c ON v.cliente_id = c.id 
            LEFT JOIN usuarios u ON v.usuario_id = u.id 
            ORDER BY v.data_venda DESC LIMIT $limit
        ")->fetchAll();
    }

    public function getRecentPaginated($page = 1, $perPage = 4) {
        $offset = ($page - 1) * $perPage;
        return $this->query("
            SELECT v.*, c.nome as cliente_nome, u.nome as vendedor_nome 
            FROM {$this->table} v 
            LEFT JOIN clientes c ON v.cliente_id = c.id 
            LEFT JOIN usuarios u ON v.usuario_id = u.id 
            ORDER BY v.data_venda DESC LIMIT $perPage OFFSET $offset
        ")->fetchAll();
    }

    public function getTotalCount() {
        return $this->query("SELECT COUNT(*) FROM {$this->table}")->fetchColumn();
    }

    public function findById($id) {
        $sale = $this->query("
            SELECT v.*, c.nome as cliente_nome, u.nome as vendedor_nome 
            FROM {$this->table} v 
            LEFT JOIN clientes c ON v.cliente_id = c.id 
            LEFT JOIN usuarios u ON v.usuario_id = u.id 
            WHERE v.id = ?
        ", [$id])->fetch();

        if ($sale) {
            $sale['itens'] = $this->query("
                SELECT i.*, p.nome as produto_nome, p.unidade 
                FROM vendas_itens i 
                JOIN produtos p ON i.produto_id = p.id 
                WHERE i.venda_id = ?
            ", [$id])->fetchAll();
        }
        return $sale;
    }

    public function updateStatus($id, $status) {
        return $this->query("UPDATE {$this->table} SET status = ? WHERE id = ?", [$status, $id]);
    }
}
