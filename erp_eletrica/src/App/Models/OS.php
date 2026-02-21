<?php
namespace App\Models;

class OS extends BaseModel {
    protected $table = 'os';

    public function getActive() {
        return $this->query("
            SELECT os.*, c.nome as cliente_nome 
            FROM {$this->table} os 
            JOIN clientes c ON os.cliente_id = c.id 
            WHERE os.status NOT IN ('concluido', 'cancelado')
            ORDER BY os.created_at DESC
        ")->fetchAll();
    }

    public function findWithDetails($id) {
        $os = $this->query("
            SELECT os.*, c.nome as cliente_nome, c.telefone as cliente_fone, c.email as cliente_email
            FROM {$this->table} os 
            JOIN clientes c ON os.cliente_id = c.id 
            WHERE os.id = ?
        ", [$id])->fetch();
        
        if ($os) {
            $os['itens'] = $this->query("
                SELECT i.*, p.nome as produto_nome 
                FROM itens_os i 
                JOIN produtos p ON i.produto_id = p.id 
                WHERE i.os_id = ?
            ", [$id])->fetchAll();
        }
        
        return $os;
    }

    public function create($data) {
        $sql = "INSERT INTO {$this->table} (numero_os, cliente_id, data_abertura, status, descricao, valor_total) 
                VALUES (?, ?, CURRENT_DATE, ?, ?, ?)";
        $params = [
            'OS' . date('Ymd') . rand(100, 999),
            $data['cliente_id'],
            'orcamento',
            $data['descricao'],
            0
        ];
        $this->query($sql, $params);
        return $this->db->lastInsertId();
    }
}
