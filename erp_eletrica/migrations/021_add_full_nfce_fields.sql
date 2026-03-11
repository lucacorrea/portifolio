-- Migration 021: Full NFC-e Configuration Consistency
-- Normalizes 'filiais' and 'sefaz_config' with all 27 fields from the Açaí system logic

-- 1. Update 'filiais' table
ALTER TABLE filiais
    ADD COLUMN IF NOT EXISTS nome_fantasia VARCHAR(255) AFTER razao_social,
    ADD COLUMN IF NOT EXISTS inscricao_municipal VARCHAR(30) AFTER inscricao_estadual,
    ADD COLUMN IF NOT EXISTS numero_endereco VARCHAR(20) AFTER logradouro,
    ADD COLUMN IF NOT EXISTS cidade VARCHAR(100) AFTER bairro,
    ADD COLUMN IF NOT EXISTS codigo_uf INT AFTER uf,
    ADD COLUMN IF NOT EXISTS regime_tributario TINYINT(1) DEFAULT 1 COMMENT '1=Simples, 2=Excesso, 3=Normal' AFTER ambiente,
    ADD COLUMN IF NOT EXISTS csc VARCHAR(255) AFTER csc_token,
    ADD COLUMN IF NOT EXISTS finalidade TINYINT(1) DEFAULT 1 AFTER finalidade_emissao,
    ADD COLUMN IF NOT EXISTS ind_pres TINYINT(1) DEFAULT 1 AFTER indicador_presenca,
    ADD COLUMN IF NOT EXISTS tipo_impressao TINYINT(1) DEFAULT 4 AFTER tipo_impressao_danfe;

-- 2. Update 'sefaz_config' table to support global defaults for all 27 fields
ALTER TABLE sefaz_config
    ADD COLUMN IF NOT EXISTS cnpj VARCHAR(18) AFTER id,
    ADD COLUMN IF NOT EXISTS razao_social VARCHAR(255) AFTER cnpj,
    ADD COLUMN IF NOT EXISTS nome_fantasia VARCHAR(255) AFTER razao_social,
    ADD COLUMN IF NOT EXISTS inscricao_estadual VARCHAR(30) AFTER nome_fantasia,
    ADD COLUMN IF NOT EXISTS inscricao_municipal VARCHAR(30) AFTER inscricao_estadual,
    ADD COLUMN IF NOT EXISTS cep VARCHAR(10) AFTER inscricao_municipal,
    ADD COLUMN IF NOT EXISTS logradouro VARCHAR(255) AFTER cep,
    ADD COLUMN IF NOT EXISTS numero_endereco VARCHAR(20) AFTER logradouro,
    ADD COLUMN IF NOT EXISTS complemento VARCHAR(100) AFTER numero_endereco,
    ADD COLUMN IF NOT EXISTS bairro VARCHAR(100) AFTER complemento,
    ADD COLUMN IF NOT EXISTS cidade VARCHAR(100) AFTER bairro,
    ADD COLUMN IF NOT EXISTS uf CHAR(2) AFTER cidade,
    ADD COLUMN IF NOT EXISTS codigo_uf INT AFTER uf,
    ADD COLUMN IF NOT EXISTS codigo_municipio VARCHAR(10) AFTER codigo_uf,
    ADD COLUMN IF NOT EXISTS telefone VARCHAR(20) AFTER codigo_municipio,
    ADD COLUMN IF NOT EXISTS regime_tributario TINYINT(1) DEFAULT 1 AFTER certificado_senha,
    ADD COLUMN IF NOT EXISTS serie_nfce INT DEFAULT 1 AFTER regime_tributario,
    ADD COLUMN IF NOT EXISTS ultimo_numero_nfce INT DEFAULT 0 AFTER serie_nfce,
    ADD COLUMN IF NOT EXISTS csc VARCHAR(255) AFTER ultimo_numero_nfce,
    ADD COLUMN IF NOT EXISTS csc_id VARCHAR(50) AFTER csc,
    ADD COLUMN IF NOT EXISTS tipo_emissao TINYINT(1) DEFAULT 1 AFTER csc_id,
    ADD COLUMN IF NOT EXISTS finalidade TINYINT(1) DEFAULT 1 AFTER tipo_emissao,
    ADD COLUMN IF NOT EXISTS ind_pres TINYINT(1) DEFAULT 1 AFTER finalidade,
    ADD COLUMN IF NOT EXISTS tipo_impressao TINYINT(1) DEFAULT 4 AFTER ind_pres,
    ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

-- 3. Cleanup: Rename/Normalize existing fields if necessary to match the 27-field form exactly
-- We'll keep both for a transition period or just use COALESCE in the service.
-- For now, adding the exact names used in the Açaí form is safer for replication.
