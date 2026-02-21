<?php
namespace App\Models;

class Client extends BaseModel {
    protected $table = 'clientes';

    public function search($term) {
        $sql = "SELECT * FROM {$this->table} WHERE nome LIKE ? OR email LIKE ? OR cpf_cnpj LIKE ?";
        $params = ["%{$term}%", "%{$term}%", "%{$term}%"];
        return $this->query($sql, $params)->fetchAll();
    }

    public function create($data) {
        $sql = "INSERT INTO {$this->table} (nome, email, cpf_cnpj, telefone, endereco, filial_id) VALUES (?, ?, ?, ?, ?, ?)";
        return $this->query($sql, [
            $data['nome'],
            $data['email'],
            $data['cpf_cnpj'],
            $data['telefone'],
            $data['endereco'],
            $data['filial_id'] ?? 1
        ]);
    }
}
