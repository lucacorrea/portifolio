<?php

namespace App\Models;

use App\Core\Model;

class Cliente extends Model
{
    protected $table = 'clientes';

    public function getAll($limit = 100)
    {
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} ORDER BY nome LIMIT :limit");
        $stmt->bindValue(':limit', (int)$limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function search($term)
    {
        $term = "%$term%";
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE nome LIKE :term OR cpf_cnpj LIKE :term LIMIT 20");
        $stmt->execute(['term' => $term]);
        return $stmt->fetchAll();
    }

    public function getById($id)
    {
        return $this->find($id);
    }
}
