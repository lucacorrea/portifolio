-- Migration 041: Add commission information to sale items
ALTER TABLE vendas_itens ADD COLUMN IF NOT EXISTS preco_tier TINYINT(1) DEFAULT 1 AFTER preco_unitario;
ALTER TABLE vendas_itens ADD COLUMN IF NOT EXISTS valor_comissao DECIMAL(10,2) DEFAULT 0.00 AFTER preco_tier;
ALTER TABLE vendas_itens ADD COLUMN IF NOT EXISTS comissao_percentual_aplicado DECIMAL(5,2) DEFAULT 0.00 AFTER valor_comissao;
