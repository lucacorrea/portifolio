<?php
namespace App\Models;

class SaleItem extends BaseModel {
    protected $table = 'vendas_itens';

    public function add($saleId, $productId, $qty, $price) {
        return $this->query(
            "INSERT INTO {$this->table} (venda_id, produto_id, quantidade, preco_unitario) VALUES (?, ?, ?, ?)",
            [$saleId, $productId, $qty, $price]
        );
    }
}
