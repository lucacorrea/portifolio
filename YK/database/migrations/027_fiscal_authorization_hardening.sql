-- Migration 027 - Idempotencia forte e rastreabilidade da autorizacao NF-e/NFC-e.
-- Impede documentacao duplicada da mesma OS por modelos 55 e 65.

SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

ALTER TABLE documentos_fiscais
    DROP INDEX IF EXISTS uq_documento_fiscal_origem_modelo,
    ADD COLUMN IF NOT EXISTS cnf CHAR(8) NULL AFTER numero,
    ADD COLUMN IF NOT EXISTS tentativas SMALLINT UNSIGNED NOT NULL DEFAULT 0 AFTER recibo_sefaz,
    ADD COLUMN IF NOT EXISTS processando_em DATETIME NULL AFTER tentativas,
    ADD COLUMN IF NOT EXISTS reconsulta_apos DATETIME NULL AFTER processando_em,
    ADD COLUMN IF NOT EXISTS cancelamento_protocolo VARCHAR(80) NULL AFTER cancelado_em,
    ADD COLUMN IF NOT EXISTS cancelamento_xml_path VARCHAR(255) NULL AFTER cancelamento_protocolo,
    ADD COLUMN IF NOT EXISTS cancelamento_xml_sha256 CHAR(64) NULL AFTER cancelamento_xml_path,
    ADD UNIQUE KEY uq_documento_fiscal_origem_normal (ordem_servico_id, ambiente, finalidade),
    ADD UNIQUE KEY uq_documento_fiscal_chave (chave),
    ADD KEY idx_documento_fiscal_reconsulta (processamento_status, reconsulta_apos);
