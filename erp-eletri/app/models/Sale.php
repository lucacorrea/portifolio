<?php
// app/models/Sale.php

class Sale extends Model {
    
    public function getPreSale($id) {
        $stmt = $this->db->prepare("SELECT pv.*, c.nome as cliente_nome, u.nome as vendedor_nome 
                                   FROM pre_vendas pv 
                                   LEFT JOIN clientes c ON pv.cliente_id = c.id 
                                   LEFT JOIN usuarios u ON pv.vendedor_id = u.id 
                                   WHERE pv.id = :id AND pv.status = 'aberta'");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch();
    }

    public function getPreSaleItems($id) {
        $stmt = $this->db->prepare("SELECT pvi.*, p.nome as produto_nome, p.codigo_interno 
                                   FROM pre_venda_itens pvi 
                                   JOIN produtos p ON pvi.produto_id = p.id 
                                   WHERE pvi.pre_venda_id = :id");
        $stmt->execute(['id' => $id]);
        return $stmt->fetchAll();
    }

    public function finalize($preSaleId, $paymentData, $userId, $filialId) {
        try {
            $this->db->beginTransaction();

            // 1. Get Pre-Sale info
            $preSale = $this->getPreSale($preSaleId);
            if (!$preSale) throw new Exception("Pré-venda não encontrada ou já finalizada.");

            // 2. Create Sale Record
            $sql = "INSERT INTO vendas (filial_id, cliente_id, vendedor_id, caixa_id, pre_venda_id, total, forma_pagamento, desconto, acrescimo, observacoes) 
                    VALUES (:filial, :cliente, :vendedor, :caixa, :prevenda, :total, :pagamento, :desconto, :acrescimo, :obs)";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'filial' => $filialId,
                'cliente' => $preSale['cliente_id'],
                'vendedor' => $preSale['vendedor_id'],
                'caixa' => $userId,
                'prevenda' => $preSaleId,
                'total' => $paymentData['total_final'],
                'pagamento' => $paymentData['method'],
                'desconto' => $paymentData['discount'] ?? 0,
                'acrescimo' => 0,
                'obs' => ''
            ]);
            $saleId = $this->db->lastInsertId();

            // 3. Move Items and Update Stock
            $items = $this->getPreSaleItems($preSaleId);
            $sqlItem = "INSERT INTO venda_itens (venda_id, produto_id, quantidade, preco_unitario, subtotal) VALUES (?, ?, ?, ?, ?)";
            $stmtItem = $this->db->prepare($sqlItem);

            $sqlStock = "UPDATE estoque SET quantidade = quantidade - ? WHERE produto_id = ? AND filial_id = ?";
            $stmtStock = $this->db->prepare($sqlStock);

            $sqlMov = "INSERT INTO movimentacoes_estoque (produto_id, filial_id, usuario_id, tipo, quantidade, motivo) VALUES (?, ?, ?, 'venda', ?, ?)";
            $stmtMov = $this->db->prepare($sqlMov);

            foreach ($items as $item) {
                // Add to Sale Items
                $stmtItem->execute([$saleId, $item['produto_id'], $item['quantidade'], $item['preco_unitario'], $item['subtotal']]);

                // Deduct Stock
                $stmtStock->execute([$item['quantidade'], $item['produto_id'], $filialId]);

                // Record Movement
                $stmtMov->execute([$item['produto_id'], $filialId, $userId, $item['quantidade'], "Venda #$saleId"]);
            }

            // 4. Update Pre-Sale Status
            $this->db->prepare("UPDATE pre_vendas SET status = 'finalizada' WHERE id = ?")->execute([$preSaleId]);

            // 5. Add to Cash Flow (Fluxo de Caixa)
            // Assuming this is a cash drawer operation
            if ($paymentData['method'] == 'Dinheiro' || $paymentData['method'] == 'Misto') {
                // Simplification for now
                $sqlCaixa = "INSERT INTO fluxo_caixa (filial_id, caixa_id, tipo, valor, observacao) VALUES (?, ?, 'suprimento', ?, ?)";
                 $this->db->prepare($sqlCaixa)->execute([$filialId, $userId, $paymentData['total_final'], "Venda #$saleId"]);
            }

            $this->db->commit();
            return $saleId;

        } catch (Exception $e) {
            $this->db->rollBack();
            return false; // In a real app, return error message
        }
    }
}
