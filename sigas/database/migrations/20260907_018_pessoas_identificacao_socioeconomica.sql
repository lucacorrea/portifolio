SET NAMES utf8mb4;

-- Identificação complementar da pessoa central.
-- Campos compartilhados por todos os módulos; nenhum deles decide aptidão de benefício.
ALTER TABLE pessoas
    ADD COLUMN IF NOT EXISTS genero VARCHAR(30) NULL AFTER data_nascimento,
    ADD COLUMN IF NOT EXISTS cor_raca VARCHAR(40) NULL AFTER genero,
    ADD COLUMN IF NOT EXISTS estado_civil VARCHAR(40) NULL AFTER cor_raca,
    ADD COLUMN IF NOT EXISTS naturalidade VARCHAR(120) NULL AFTER estado_civil,
    ADD COLUMN IF NOT EXISTS nacionalidade VARCHAR(80) NULL AFTER naturalidade,
    ADD COLUMN IF NOT EXISTS rg_emissao DATE NULL AFTER rg,
    ADD COLUMN IF NOT EXISTS rg_uf CHAR(2) NULL AFTER rg_emissao,
    ADD COLUMN IF NOT EXISTS whatsapp VARCHAR(30) NULL AFTER telefone;
