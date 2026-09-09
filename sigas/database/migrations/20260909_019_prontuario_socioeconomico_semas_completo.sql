SET NAMES utf8mb4;

-- ============================================================================
-- SIGAS | Fidelidade da importação socioeconômica SEMAS / ANEXO
-- Compatibilidade alvo: MariaDB 11.x
--
-- IMPORTANTE:
-- - O ANEXO/SEMAS continua sendo somente leitura.
-- - Esta migration altera apenas o banco do SIGAS.
-- - DDL no MariaDB executa COMMIT implícito; não usar transação explícita.
-- - Todas as adições são idempotentes para permitir reexecução segura.
-- ============================================================================

ALTER TABLE pessoa_socioeconomico
    ADD COLUMN IF NOT EXISTS tempo_moradia_anos SMALLINT UNSIGNED NULL AFTER renda_per_capita,
    ADD COLUMN IF NOT EXISTS tempo_moradia_meses TINYINT UNSIGNED NULL AFTER tempo_moradia_anos,
    ADD COLUMN IF NOT EXISTS renda_mensal_faixa VARCHAR(120) NULL AFTER tempo_moradia_meses,
    ADD COLUMN IF NOT EXISTS renda_mensal_outros VARCHAR(180) NULL AFTER renda_mensal_faixa,
    ADD COLUMN IF NOT EXISTS total_rendimentos DECIMAL(12,2) NULL AFTER renda_mensal_outros,
    ADD COLUMN IF NOT EXISTS total_familias SMALLINT UNSIGNED NULL AFTER total_rendimentos,
    ADD COLUMN IF NOT EXISTS pcd_residencia TINYINT(1) NOT NULL DEFAULT 0 AFTER total_familias,
    ADD COLUMN IF NOT EXISTS total_pcd SMALLINT UNSIGNED NULL AFTER pcd_residencia,
    ADD COLUMN IF NOT EXISTS situacao_imovel_valor DECIMAL(12,2) NULL AFTER area_risco_descricao,
    ADD COLUMN IF NOT EXISTS iluminacao VARCHAR(120) NULL AFTER situacao_imovel_valor,
    ADD COLUMN IF NOT EXISTS destino_lixo VARCHAR(120) NULL AFTER iluminacao,
    ADD COLUMN IF NOT EXISTS entorno VARCHAR(255) NULL AFTER destino_lixo,
    ADD COLUMN IF NOT EXISTS tipificacao VARCHAR(255) NULL AFTER entorno,
    ADD COLUMN IF NOT EXISTS beneficios_detalhes_json JSON NULL AFTER tipificacao;

ALTER TABLE pessoa_socioeconomico_membros
    ADD COLUMN IF NOT EXISTS cpf CHAR(11) NULL AFTER nome,
    ADD COLUMN IF NOT EXISTS nis VARCHAR(32) NULL AFTER cpf,
    ADD COLUMN IF NOT EXISTS rg VARCHAR(40) NULL AFTER nis;
