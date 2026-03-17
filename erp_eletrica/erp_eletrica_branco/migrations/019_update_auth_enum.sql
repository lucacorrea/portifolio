-- Migration 019: Add suprimento and geral to autorizacoes_temporarias tipo
ALTER TABLE autorizacoes_temporarias MODIFY COLUMN tipo ENUM('desconto', 'sangria', 'suprimento', 'geral', 'venda') NOT NULL;
