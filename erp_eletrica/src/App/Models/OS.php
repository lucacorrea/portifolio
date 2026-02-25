<?php
namespace App\Models;

class OS extends BaseModel {
    protected $table = 'os';

    public function getActive() {
        $filialId = $this->getFilialContext();
        $where = "WHERE os.status NOT IN ('concluido', 'cancelado')";
        $params = [];
        
        if ($filialId) {
            $where .= " AND os.filial_id = ?";
            $params[] = $filialId;
        }

        return $this->query("
            SELECT os.*, c.nome as cliente_nome 
            FROM {$this->table} os 
            JOIN clientes c ON os.cliente_id = c.id 
            $where
            ORDER BY os.created_at DESC
        ", $params)->fetchAll();
    }

    public function findWithDetails($id) {
        $filialId = $this->getFilialContext();
        $sql = "
            SELECT os.*, c.nome as cliente_nome, c.telefone as cliente_fone, c.email as cliente_email
            FROM {$this->table} os 
            JOIN clientes c ON os.cliente_id = c.id 
            WHERE os.id = ?
        ";
        $params = [$id];
        
        if ($filialId) {
            $sql .= " AND os.filial_id = ?";
            $params[] = $filialId;
        }

        $os = $this->query($sql, $params)->fetch();
        
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
        $data['filial_id'] = $data['filial_id'] ?? ($_SESSION['filial_id'] ?? 1);
        $data['numero_os'] = $data['numero_os'] ?? ('OS' . date('Ymd') . rand(100, 999));
        $data['status'] = $data['status'] ?? 'orcamento';
        $data['data_abertura'] = date('Y-m-d');
        
        return parent::create($data);
    }
}
