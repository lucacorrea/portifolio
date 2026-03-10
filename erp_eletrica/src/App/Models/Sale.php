<?php
namespace App\Models;

class Sale extends BaseModel {
    protected $table = 'vendas';

    public function create($data) {
        $hasAvulso    = $this->columnExists('nome_cliente_avulso');
        $hasTipoNota  = $this->columnExists('tipo_nota');

        $cols   = ['cliente_id', 'usuario_id', 'filial_id', 'valor_total', 'desconto_total', 'autorizado_por', 'forma_pagamento', 'status'];
        $params = [
            $data['cliente_id'] ?? null,
            $data['usuario_id'],
            $data['filial_id'],
            $data['valor_total'],
            $data['desconto_total'] ?? 0,
            $data['autorizado_por'] ?? null,
            $data['forma_pagamento'],
            'concluido'
        ];

        if ($hasAvulso) {
            array_splice($cols, 1, 0, ['nome_cliente_avulso']);
            array_splice($params, 1, 0, [$data['nome_cliente_avulso'] ?? null]);
        }

        if ($hasTipoNota) {
            $cols[]   = 'tipo_nota';
            $params[] = $data['tipo_nota'] ?? 'nao_fiscal';
        }

        $placeholders = implode(', ', array_fill(0, count($cols), '?'));
        $colList      = implode(', ', $cols);

        $sql = "INSERT INTO {$this->table} ($colList) VALUES ($placeholders)";
        $this->query($sql, $params);
        return $this->db->lastInsertId();
    }

    public function getRecent($limit = 10) {
        $filialId = $this->getFilialContext();
        $where = $filialId ? "WHERE v.filial_id = ?" : "";
        $params = $filialId ? [$filialId] : [];
        $nameField = $this->columnExists('nome_cliente_avulso') ? 'v.nome_cliente_avulso' : 'NULL';

        return $this->query("
            SELECT v.*, IFNULL(c.nome, $nameField) as cliente_nome, u.nome as vendedor_nome 
            FROM {$this->table} v 
            LEFT JOIN clientes c ON v.cliente_id = c.id 
            LEFT JOIN usuarios u ON v.usuario_id = u.id 
            $where
            ORDER BY v.data_venda DESC LIMIT $limit
        ", $params)->fetchAll();
    }

    public function getRecentPaginated($page = 1, $perPage = 4) {
        $offset = ($page - 1) * $perPage;
        $filialId = $this->getFilialContext();
        $where = $filialId ? "WHERE v.filial_id = ?" : "";
        $params = $filialId ? [$filialId] : [];
        $nameField = $this->columnExists('nome_cliente_avulso') ? 'v.nome_cliente_avulso' : 'NULL';

        return $this->query("
            SELECT v.*, IFNULL(c.nome, $nameField) as cliente_nome, u.nome as vendedor_nome 
            FROM {$this->table} v 
            LEFT JOIN clientes c ON v.cliente_id = c.id 
            LEFT JOIN usuarios u ON v.usuario_id = u.id 
            $where
            ORDER BY v.data_venda DESC LIMIT $perPage OFFSET $offset
        ", $params)->fetchAll();
    }

    public function getTotalCount() {
        $filialId = $this->getFilialContext();
        if ($filialId) {
            return $this->query("SELECT COUNT(*) FROM {$this->table} WHERE filial_id = ?", [$filialId])->fetchColumn();
        }
        return $this->query("SELECT COUNT(*) FROM {$this->table}")->fetchColumn();
    }

    public function findById($id) {
        $nameField = $this->columnExists('nome_cliente_avulso') ? 'v.nome_cliente_avulso' : 'NULL';
        $sale = $this->query("
            SELECT v.*, IFNULL(c.nome, $nameField) as cliente_nome, u.nome as vendedor_nome 
            FROM {$this->table} v 
            LEFT JOIN clientes c ON v.cliente_id = c.id 
            LEFT JOIN usuarios u ON v.usuario_id = u.id 
            WHERE v.id = ?
        ", [$id])->fetch();

        if ($sale) {
            $sale['itens'] = $this->query("
                SELECT i.*, p.nome as produto_nome, p.unidade 
                FROM vendas_itens i 
                JOIN produtos p ON i.produto_id = p.id 
                WHERE i.venda_id = ?
            ", [$id])->fetchAll();
        }
        return $sale;
    }

    public function updateStatus($id, $status) {
        return $this->query("UPDATE {$this->table} SET status = ? WHERE id = ?", [$status, $id]);
    }
}
