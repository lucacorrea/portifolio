<?php
namespace App\Models;

class Client extends BaseModel {
    protected $table = 'clientes';

    public function search($term) {
        $filialId = $this->getFilialContext();
        $sql = "SELECT * FROM {$this->table} WHERE (nome LIKE ? OR email LIKE ? OR cpf_cnpj LIKE ?)";
        $params = ["%{$term}%", "%{$term}%", "%{$term}%"];
        
        if ($filialId) {
            $sql .= " AND filial_id = ?";
            $params[] = $filialId;
        }
        
        return $this->query($sql, $params)->fetchAll();
    }

    public function create($data) {
        // Now partially handled by BaseModel, but kept for explicit session context if needed
        if (!isset($data['filial_id'])) {
            $data['filial_id'] = $_SESSION['filial_id'] ?? 1;
        }
        return parent::create($data);
    }
}
