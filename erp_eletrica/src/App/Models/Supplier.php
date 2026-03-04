<?php
namespace App\Models;

class Supplier extends BaseModel {
    protected $table = 'fornecedores';

    public function all($order = "nome_fantasia ASC") {
        return $this->query("SELECT * FROM {$this->table} ORDER BY $order")->fetchAll();
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
