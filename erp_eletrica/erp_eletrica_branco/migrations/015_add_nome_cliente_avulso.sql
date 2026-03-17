-- Migration 015: Add nome_cliente_avulso to pre_vendas
ALTER TABLE pre_vendas ADD COLUMN nome_cliente_avulso VARCHAR(255) NULL;
