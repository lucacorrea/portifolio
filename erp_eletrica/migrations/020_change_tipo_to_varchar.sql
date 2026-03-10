-- Migration 020: Change autorizacoes_temporarias tipo to VARCHAR to avoid ENUM issues
ALTER TABLE autorizacoes_temporarias MODIFY COLUMN tipo VARCHAR(20) NOT NULL;
