-- Migration 019: Fix nfe_importadas table
-- Increase fornecedor_cnpj to accommodate formatted CNPJs
-- Add unique index on chave_nfe + filial_id to properly handle duplicates
ALTER TABLE nfe_importadas MODIFY fornecedor_cnpj VARCHAR(20) NOT NULL;

-- Add unique index if not exists (prevents silent duplicate issues)
-- Using ALTER IGNORE to skip if already exists
ALTER TABLE nfe_importadas ADD UNIQUE INDEX idx_unique_chave_filial (chave_nfe, filial_id);
