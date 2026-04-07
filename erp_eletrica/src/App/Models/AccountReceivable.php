<?php
namespace App\Models;

class AccountReceivable extends BaseModel {
    protected $table = 'contas_receber';

    public function getSummary() {
        $filialId = $this->getFilialContext();
        $where = $filialId ? " AND filial_id = ?" : "";
        $params = $filialId ? [$filialId] : [];

        return [
            'total_pendente' => $this->query("SELECT SUM(valor) FROM {$this->table} WHERE status = 'pendente' $where", $params)->fetchColumn() ?: 0,
            'recebido_hoje' => $this->query("SELECT SUM(valor) FROM {$this->table} WHERE status = 'pago' AND data_pagamento = CURRENT_DATE $where", $params)->fetchColumn() ?: 0
        ];
    }

    public function getRecent($limit = 20) {
        $filialId = $this->getFilialContext();
        $where = $filialId ? "WHERE cr.filial_id = ?" : "";
        $params = $filialId ? [$filialId] : [];

        return $this->query("
            SELECT cr.*, v.id as venda_id, c.nome as cliente_nome 
            FROM {$this->table} cr 
            LEFT JOIN vendas v ON cr.venda_id = v.id 
            LEFT JOIN clientes c ON v.cliente_id = c.id 
            $where
            ORDER BY cr.data_vencimento ASC LIMIT $limit
        ", $params)->fetchAll();
    }

    public function findWithDetails($id) {
        $filialId = $this->getFilialContext();
        $where = "WHERE cr.id = ?";
        $params = [$id];
        
        if ($filialId && ($_SESSION['usuario_nivel'] ?? '') !== 'master') {
            $where .= " AND cr.filial_id = ?";
            $params[] = $filialId;
        }

        $sql = "
            SELECT cr.*, (cr.valor - cr.valor_pago) as saldo, c.nome as cliente_nome, v.data_venda as data_venda
            FROM {$this->table} cr 
            JOIN clientes c ON cr.cliente_id = c.id 
            LEFT JOIN vendas v ON cr.venda_id = v.id
            $where
        ";
        
        return $this->query($sql, $params)->fetch();
    }

    public function getItems($vendaId) {
        $sql = "
            SELECT vi.*, p.nome as produto_nome, p.unidade
            FROM vendas_itens vi 
            JOIN produtos p ON vi.produto_id = p.id 
            WHERE vi.venda_id = ?
        ";
        return $this->query($sql, [$vendaId])->fetchAll();
    }

    public function paginate($perPage = 15, $currentPage = 1, $order = "data_vencimento ASC", $filters = []) {
        $filialId = $this->getFilialContext();
        $offset = ($currentPage - 1) * $perPage;
        
        $where = "WHERE 1=1";
        $params = [];
        
        if ($filialId) {
            $where .= " AND cr.filial_id = ?";
            $params[] = $filialId;
        }

        // Count total
        $total = $this->query("SELECT COUNT(*) FROM {$this->table} cr $where", $params)->fetchColumn();
        $pages = ceil($total / $perPage);
        
        // Data with joins
        $sql = "
            SELECT cr.*, v.id as venda_id, c.nome as cliente_nome 
            FROM {$this->table} cr 
            LEFT JOIN vendas v ON cr.venda_id = v.id 
            LEFT JOIN clientes c ON v.cliente_id = c.id 
            $where 
            ORDER BY $order 
            LIMIT $perPage OFFSET $offset
        ";
        
        $data = $this->query($sql, $params)->fetchAll();
        
        return [
            'data' => $data,
            'total' => $total,
            'pages' => $pages,
            'current' => $currentPage,
            'per_page' => $perPage
        ];
    }
}
