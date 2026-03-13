-- Migration 028: Add cpf_cliente to pre_vendas and vendas
-- This column is required for NFC-e and standalone customer identification

ALTER TABLE pre_vendas ADD COLUMN cpf_cliente VARCHAR(20) NULL AFTER nome_cliente_avulso;
ALTER TABLE vendas ADD COLUMN cpf_cliente VARCHAR(20) NULL AFTER nome_cliente_avulso;
