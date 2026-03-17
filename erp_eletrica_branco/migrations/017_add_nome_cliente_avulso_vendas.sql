-- Migration 017: Add nome_cliente_avulso to vendas
ALTER TABLE vendas ADD COLUMN nome_cliente_avulso VARCHAR(255) NULL AFTER cliente_id;
