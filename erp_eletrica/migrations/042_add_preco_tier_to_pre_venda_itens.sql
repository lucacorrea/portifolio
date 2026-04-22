-- Migration 042: Add preco_tier to pre_venda_itens
ALTER TABLE pre_venda_itens ADD COLUMN preco_tier TINYINT(1) DEFAULT 1;
