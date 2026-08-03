-- Repara instalações que exibem Configurações, mas não possuem todos os
-- campos usados pelo salvamento isolado por empresa.

SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

ALTER TABLE configuracoes_empresa
    MODIFY COLUMN id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    ADD COLUMN IF NOT EXISTS inscricao_estadual VARCHAR(40) NULL AFTER documento,
    ADD COLUMN IF NOT EXISTS inscricao_municipal VARCHAR(40) NULL AFTER inscricao_estadual,
    ADD COLUMN IF NOT EXISTS email VARCHAR(150) NULL AFTER inscricao_municipal,
    ADD COLUMN IF NOT EXISTS crt TINYINT UNSIGNED NULL AFTER inscricao_municipal,
    ADD COLUMN IF NOT EXISTS cnae_principal CHAR(7) NULL AFTER crt,
    ADD COLUMN IF NOT EXISTS endereco_logradouro VARCHAR(150) NULL AFTER endereco,
    ADD COLUMN IF NOT EXISTS endereco_numero VARCHAR(30) NULL AFTER endereco_logradouro,
    ADD COLUMN IF NOT EXISTS endereco_complemento VARCHAR(100) NULL AFTER endereco_numero,
    ADD COLUMN IF NOT EXISTS endereco_bairro VARCHAR(100) NULL AFTER endereco_complemento,
    ADD COLUMN IF NOT EXISTS endereco_cidade VARCHAR(100) NULL AFTER endereco_bairro,
    ADD COLUMN IF NOT EXISTS endereco_uf CHAR(2) NULL AFTER endereco_cidade,
    ADD COLUMN IF NOT EXISTS endereco_cep VARCHAR(8) NULL AFTER endereco_uf,
    ADD COLUMN IF NOT EXISTS codigo_municipio_ibge CHAR(7) NULL AFTER endereco_cep,
    ADD UNIQUE INDEX IF NOT EXISTS uq_configuracoes_empresa_empresa (empresa_id);
