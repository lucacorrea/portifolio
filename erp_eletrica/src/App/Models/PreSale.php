<?php
namespace App\Models;

class PreSale extends BaseModel {
    protected $table = 'pre_vendas';

    public function create($data) {
        $codigo = 'PV-' . strtoupper(substr(uniqid(), -6));
        $hasAvulso = $this->columnExists('nome_cliente_avulso');
        $hasCpfCliente = $this->columnExists('cpf_cliente');
        
        $cols = ['codigo', 'cliente_id', 'usuario_id', 'filial_id', 'valor_total', 'status'];
        $params = [$codigo, $data['cliente_id'] ?? null, $data['usuario_id'], $data['filial_id'], $data['valor_total'], 'pendente'];

        if ($hasAvulso) {
            array_splice($cols, 2, 0, ['nome_cliente_avulso']);
            array_splice($params, 2, 0, [$data['nome_cliente_avulso'] ?? null]);
        }

        if ($hasCpfCliente) {
            if ($hasAvulso) {
                array_splice($cols, 3, 0, ['cpf_cliente']);
                array_splice($params, 3, 0, [$data['cpf_cliente'] ?? null]);
            } else {
                $cols[] = 'cpf_cliente';
                $params[] = $data['cpf_cliente'] ?? null;
            }
        }

        $colList = implode(', ', $cols);
        $placeholders = implode(', ', array_fill(0, count($cols), '?'));
        
        $sql = "INSERT INTO {$this->table} ($colList) VALUES ($placeholders)";
        
        $this->query($sql, $params);
        $preVendaId = $this->db->lastInsertId();

        foreach ($data['items'] as $item) {
            $this->query(
                "INSERT INTO pre_venda_itens (pre_venda_id, produto_id, quantidade, preco_unitario, preco_tier) VALUES (?, ?, ?, ?, ?)",
                [$preVendaId, $item['id'], $item['qty'], $item['price'], $item['price_tier'] ?? 1]
            );
        }

        return ['id' => $preVendaId, 'codigo' => $codigo];
    }

    public function findByCode($code) {
        $nameField = $this->columnExists('nome_cliente_avulso') ? 'pv.nome_cliente_avulso' : "''";
        $sql = "SELECT pv.*, 
                       COALESCE(c.nome, $nameField, 'Consumidor') as cliente_nome,
                       COALESCE(c.cpf_cnpj, pv.cpf_cliente) as cliente_doc
                FROM {$this->table} pv 
                LEFT JOIN clientes c ON pv.cliente_id = c.id
                WHERE pv.codigo = ? AND pv.status = 'pendente'";
        $pv = $this->query($sql, [$code])->fetch();
        
        if ($pv) {
            $pv['itens'] = $this->query("
                SELECT i.*, p.nome as produto_nome, p.unidade, p.imagens,
                       p.preco_venda, p.preco_venda_2, p.preco_venda_3
                FROM pre_venda_itens i 
                JOIN produtos p ON i.produto_id = p.id 
                WHERE i.pre_venda_id = ?
            ", [$pv['id']])->fetchAll();
        }
        return $pv;
    }

    public function getRecent($limit = 10) {
        $nameField = $this->columnExists('nome_cliente_avulso') ? 'pv.nome_cliente_avulso' : 'NULL';
        
        return $this->query("
            SELECT pv.*, 
                   IFNULL(c.nome, $nameField) as cliente_nome, 
                   u.nome as vendedor_nome 
            FROM {$this->table} pv 
            LEFT JOIN clientes c ON pv.cliente_id = c.id 
            LEFT JOIN usuarios u ON pv.usuario_id = u.id 
            ORDER BY pv.created_at DESC LIMIT $limit
        ")->fetchAll();
    }

    public function markAsFinalized($id) {
        return $this->query("UPDATE {$this->table} SET status = 'finalizado' WHERE id = ?", [$id]);
    }

    public function update($id, $data) {
        $hasAvulso = $this->columnExists('nome_cliente_avulso');
        $hasCpfCliente = $this->columnExists('cpf_cliente');
        $hasUpdatedAt = $this->columnExists('updated_at');

        $sets = ['cliente_id = ?', 'valor_total = ?'];
        $params = [$data['cliente_id'] ?? null, $data['valor_total']];

        if ($hasAvulso) {
            $sets[] = 'nome_cliente_avulso = ?';
            $params[] = $data['nome_cliente_avulso'] ?? null;
        }

        if ($hasCpfCliente) {
            $sets[] = 'cpf_cliente = ?';
            $params[] = $data['cpf_cliente'] ?? null;
        }

        if ($hasUpdatedAt) {
            $sets[] = 'updated_at = NOW()';
        }

        $params[] = $id;

        // Update main record
        $sql = "UPDATE {$this->table} SET " . implode(', ', $sets) . " WHERE id = ?";
        $this->query($sql, $params);

        // Delete old items
        $this->query("DELETE FROM pre_venda_itens WHERE pre_venda_id = ?", [$id]);

        // Insert new items
        foreach ($data['items'] as $item) {
            $this->query(
                "INSERT INTO pre_venda_itens (pre_venda_id, produto_id, quantidade, preco_unitario, preco_tier) VALUES (?, ?, ?, ?, ?)",
                [$id, $item['id'], $item['qty'], $item['price'], $item['price_tier'] ?? 1]
            );
        }

        return true;
    }
}
