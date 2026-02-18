<?php

namespace App\Models;

use App\Core\Model;
use PDO;

class Produto extends Model
{
    protected $table = 'produtos';

    public function getAll($limit = 100)
    {
        $stmt = $this->db->prepare("SELECT p.*, c.nome as categoria_nome FROM {$this->table} p LEFT JOIN categorias c ON p.categoria_id = c.id ORDER BY p.nome LIMIT :limit");
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getById($id)
    {
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE id = :id");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch();
    }

    public function search($term)
    {
        $term = "%$term%";
        $stmt = $this->db->prepare("SELECT p.*, c.nome as categoria_nome FROM {$this->table} p LEFT JOIN categorias c ON p.categoria_id = c.id WHERE p.nome LIKE :term OR p.codigo_interno LIKE :term OR p.codigo_barras LIKE :term LIMIT 20");
        // Using bindParam or execute array. Execute array is safer for keeping code clean here.
        $stmt->execute(['term' => $term]);
        return $stmt->fetchAll();
    }

    public function create(array $data)
    {
        // Using parent create if keys match or custom if special handling needed.
        // The previous code had explicit fields. Let's try to use the parent create if possible, 
        // but here we have many fields. Let's keep specific SQL for safety/validation if needed, 
        // OR better, rely on parent create for simplicity if keys match columns.
        // Current parent create builds query dynamically.
        return parent::create($data);
    }

    public function update($id, array $data)
    {
       return parent::update($id, $data);
    }
    
    public function getEstoque($produto_id)
    {
        $stmt = $this->db->prepare("SELECT e.*, f.nome as filial_nome FROM estoque e JOIN filiais f ON e.filial_id = f.id WHERE e.produto_id = :id");
        $stmt->execute(['id' => $produto_id]);
        return $stmt->fetchAll();
    }
    
    public function getCategories()
    {
        $stmt = $this->db->query("SELECT * FROM categorias ORDER BY nome");
        return $stmt->fetchAll();
    }
}
