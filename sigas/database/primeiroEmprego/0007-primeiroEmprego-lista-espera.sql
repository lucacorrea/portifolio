-- SIGAS - Meu Primeiro Emprego
-- 0007 - Identificação das importações da Lista de espera
-- Compatível com MariaDB 10.5+ / 11.x. Pode ser executado novamente.

SET NAMES utf8mb4;

ALTER TABLE pe_importacoes
    ADD COLUMN IF NOT EXISTS tipo_importacao VARCHAR(30) NOT NULL DEFAULT 'candidatos' AFTER arquivo_hash,
    ADD INDEX IF NOT EXISTS idx_pe_importacoes_tipo (tipo_importacao);

UPDATE pe_importacoes
SET tipo_importacao = 'candidatos'
WHERE tipo_importacao IS NULL OR tipo_importacao = '';
