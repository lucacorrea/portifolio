<?php
// app/models/PreSale.php

class PreSale extends Model {
    
    public function create($data, $items) {
        try {
            $this->db->beginTransaction();

            $sql = "INSERT INTO pre_vendas (filial_id, cliente_id, vendedor_id, total, status) VALUES (?, ?, ?, ?, 'aberta')";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $data['filial_id'], 
                $data['cliente_id'], 
                $data['vendedor_id'], 
                $data['total']
            ]);
            
            $pre_venda_id = $this->db->lastInsertId();

            $sqlItem = "INSERT INTO pre_venda_itens (pre_venda_id, produto_id, quantidade, preco_unitario, subtotal) VALUES (?, ?, ?, ?, ?)";
            $stmtItem = $this->db->prepare($sqlItem);

            foreach ($items as $item) {
                $stmtItem->execute([
                    $pre_venda_id,
                    $item['produto_id'],
                    $item['quantidade'],
                    $item['preco_unitario'],
                    $item['subtotal']
                ]);
            }

            $this->db->commit();
            return $pre_venda_id;

        } catch (Exception $e) {
            $this->db->rollBack();
            return false;
        }
    }
    
    public function getLastSales($limit = 20) {
        $sql = "SELECT pv.*, c.nome as cliente_nome, u.nome as vendedor_nome 
                FROM pre_vendas pv 
                LEFT JOIN clientes c ON pv.cliente_id = c.id 
                LEFT JOIN usuarios u ON pv.vendedor_id = u.id 
                ORDER BY pv.created_at DESC LIMIT $limit";
        return $this->db->query($sql)->fetchAll();
    }
}
