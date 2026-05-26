-- Migration 048: Add banking fields to filiais table
ALTER TABLE filiais ADD COLUMN IF NOT EXISTS dados_bancarios VARCHAR(255) NULL AFTER certificado_senha;
ALTER TABLE filiais ADD COLUMN IF NOT EXISTS chave_pix VARCHAR(255) NULL AFTER dados_bancarios;
ALTER TABLE filiais ADD COLUMN IF NOT EXISTS titular_conta VARCHAR(255) NULL AFTER chave_pix;
