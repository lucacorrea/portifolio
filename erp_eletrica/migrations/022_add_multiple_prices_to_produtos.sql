-- Migration 022: Adds 3 Price tiers for products
-- Adiciona preco_venda_2 e preco_venda_3 na tabela de produtos

ALTER TABLE produtos ADD COLUMN IF NOT EXISTS preco_venda_2 DECIMAL(10,2) AFTER preco_venda;
ALTER TABLE produtos ADD COLUMN IF NOT EXISTS preco_venda_3 DECIMAL(10,2) AFTER preco_venda_2;
