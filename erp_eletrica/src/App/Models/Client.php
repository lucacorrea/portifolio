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

    public function getLTV($id) {
        $sql = "SELECT SUM(valor_total) FROM vendas WHERE cliente_id = ? AND status = 'concluido'";
        return $this->query($sql, [$id])->fetchColumn() ?: 0;
    }

    public function getPurchaseHistory($id) {
        $sql = "SELECT * FROM vendas WHERE cliente_id = ? ORDER BY data_venda DESC";
        return $this->query($sql, [$id])->fetchAll();
    }

    public function getStats($id) {
        $ltv = $this->getLTV($id);
        $count = $this->query("SELECT COUNT(*) FROM vendas WHERE cliente_id = ?", [$id])->fetchColumn();
        $avg = $count > 0 ? $ltv / $count : 0;
        
        $segment = 'Ocasional';
        if ($ltv > 5000) $segment = 'VIP';
        elseif ($ltv > 1000) $segment = 'Regular';

        return [
            'ltv' => $ltv,
            'total_pedidos' => $count,
            'ticket_medio' => $avg,
            'segmento' => $segment
        ];
    }
}
