<?php
namespace App\Models;

class Supplier extends BaseModel {
    protected $table = 'fornecedores';

    public function all($order = "nome_fantasia ASC") {
        return $this->query("SELECT * FROM {$this->table} ORDER BY $order")->fetchAll();
    }

    public function paginateWithNfe($perPage = 10, $page = 1) {
        $offset = ($page - 1) * $perPage;
        
        // Query to get suppliers and count of pending NFe from nfe_importadas
        // We match by CNPJ (cleaned) with COLLATE to avoid collation mismatch errors
        $sql = "SELECT s.*, 
                (SELECT COUNT(*) FROM nfe_importadas n 
                 WHERE n.fornecedor_cnpj COLLATE utf8mb4_general_ci = s.cnpj COLLATE utf8mb4_general_ci AND n.status = 'pendente') as pending_nfe_count
                FROM {$this->table} s
                ORDER BY pending_nfe_count DESC, s.nome_fantasia ASC
                LIMIT $perPage OFFSET $offset";
        
        $data = $this->query($sql)->fetchAll();
        
        $total = $this->query("SELECT COUNT(*) FROM {$this->table}")->fetchColumn();
        
        return [
            'data' => $data,
            'total' => $total,
            'current' => $page,
            'pages' => ceil($total / $perPage)
        ];
    }

    public function save($data) {
        if (!empty($data['id'])) {
            $sql = "UPDATE {$this->table} SET nome_fantasia = ?, cnpj = ?, email = ?, telefone = ?, endereco = ? WHERE id = ?";
            return $this->query($sql, [$data['nome_fantasia'], $data['cnpj'], $data['email'], $data['telefone'], $data['endereco'], $data['id']]);
        } else {
            $sql = "INSERT INTO {$this->table} (nome_fantasia, cnpj, email, telefone, endereco) VALUES (?, ?, ?, ?, ?)";
            return $this->query($sql, [$data['nome_fantasia'], $data['cnpj'], $data['email'], $data['telefone'], $data['endereco']]);
        }
    }
}
