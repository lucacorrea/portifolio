<?php
namespace App\Models;

class ProductProblem extends BaseModel {
    protected $table = 'produtos_problema';

    public function all($order = "data_registro DESC") {
        $filialId = $this->getFilialContext();
        
        $sql = "SELECT pp.*, p.nome as produto_nome, p.codigo as produto_codigo, u.nome as usuario_nome 
                FROM {$this->table} pp 
                JOIN produtos p ON pp.produto_id = p.id 
                LEFT JOIN usuarios u ON pp.usuario_id = u.id 
                WHERE 1=1";
        
        $params = [];
        if ($filialId) {
            $sql .= " AND pp.filial_id = ?";
            $params[] = $filialId;
        }

        $sql .= " ORDER BY $order";
        
        return $this->query($sql, $params)->fetchAll();
    }

    public function save($data) {
        if (empty($data['usuario_id'])) {
            $data['usuario_id'] = $_SESSION['usuario_id'] ?? null;
        }
        if (empty($data['filial_id'])) {
            $data['filial_id'] = $_SESSION['filial_id'] ?? 1;
        }
        
        return parent::save($data);
    }
    
    public function getStatusLabels() {
        return [
            'pendente' => ['label' => 'Pendente', 'class' => 'warning'],
            'devolvido' => ['label' => 'Devolvido', 'class' => 'primary'],
            'descartado' => ['label' => 'Descartado', 'class' => 'danger'],
            'consertado' => ['label' => 'Consertado', 'class' => 'success']
        ];
    }
}
