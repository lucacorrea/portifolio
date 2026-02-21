<?php
namespace App\Models;

class PreSale extends BaseModel {
    protected $table = 'pre_vendas';

    public function create($data) {
        $codigo = 'PV-' . strtoupper(substr(uniqid(), -6));
        $sql = "INSERT INTO {$this->table} (codigo, cliente_id, usuario_id, filial_id, valor_total, status) 
                VALUES (?, ?, ?, ?, ?, ?)";
        $params = [
            $codigo,
            $data['cliente_id'] ?? null,
            $data['usuario_id'],
            $data['filial_id'],
            $data['valor_total'],
            'pendente'
        ];
        $this->query($sql, $params);
        $preVendaId = $this->db->lastInsertId();

        foreach ($data['items'] as $item) {
            $this->query(
                "INSERT INTO pre_venda_itens (pre_venda_id, produto_id, quantidade, preco_unitario) VALUES (?, ?, ?, ?)",
                [$preVendaId, $item['id'], $item['qty'], $item['price']]
            );
        }

        return ['id' => $preVendaId, 'codigo' => $codigo];
    }

    public function findByCode($code) {
        $pv = $this->query("SELECT * FROM {$this->table} WHERE codigo = ? AND status = 'pendente'", [$code])->fetch();
        if ($pv) {
            $pv['itens'] = $this->query("
                SELECT i.*, p.nome as produto_nome, p.unidade, p.imagens 
                FROM pre_venda_itens i 
                JOIN produtos p ON i.produto_id = p.id 
                WHERE i.pre_venda_id = ?
            ", [$pv['id']])->fetchAll();
        }
        return $pv;
    }

    public function getRecent($limit = 10) {
        return $this->query("
            SELECT pv.*, c.nome as cliente_nome, u.nome as vendedor_nome 
            FROM {$this->table} pv 
            LEFT JOIN clientes c ON pv.cliente_id = c.id 
            LEFT JOIN usuarios u ON pv.usuario_id = u.id 
            ORDER BY pv.created_at DESC LIMIT $limit
        ")->fetchAll();
    }

    public function markAsFinalized($id) {
        return $this->query("UPDATE {$this->table} SET status = 'finalizado' WHERE id = ?", [$id]);
    }
}
