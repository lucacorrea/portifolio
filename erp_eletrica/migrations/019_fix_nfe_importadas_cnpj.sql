-- Migration 019: Fix nfe_importadas table structure
-- 1. Expand fornecedor_cnpj to allow larger values (CNPJ can come formatted from SEFAZ)
-- 2. Add UNIQUE constraint on chave_nfe (required for INSERT IGNORE to work properly)

ALTER TABLE nfe_importadas MODIFY COLUMN fornecedor_cnpj VARCHAR(20) NOT NULL DEFAULT '';

ALTER TABLE nfe_importadas ADD UNIQUE INDEX uk_chave_nfe (chave_nfe);
