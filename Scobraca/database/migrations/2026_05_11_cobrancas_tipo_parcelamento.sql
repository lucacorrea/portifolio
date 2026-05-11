-- Execute uma única vez em bases já existentes antes de usar cobrança parcelada.
-- Em instalações novas, estas colunas já estão em database/schema_saas.sql.

ALTER TABLE cobrancas
    DROP INDEX uq_cobranca_empresa_cliente_ref;

ALTER TABLE cobrancas
    ADD COLUMN tipo ENUM('mensalidade','parcelada') NOT NULL DEFAULT 'mensalidade' AFTER referencia,
    ADD COLUMN descricao VARCHAR(160) DEFAULT NULL AFTER tipo,
    ADD COLUMN grupo_parcelamento_id CHAR(32) DEFAULT NULL AFTER descricao,
    ADD COLUMN numero_parcela SMALLINT UNSIGNED NOT NULL DEFAULT 1 AFTER grupo_parcelamento_id,
    ADD COLUMN total_parcelas SMALLINT UNSIGNED NOT NULL DEFAULT 1 AFTER numero_parcela,
    ADD COLUMN valor_total DECIMAL(10,2) DEFAULT NULL AFTER total_parcelas,
    ADD COLUMN valor_entrada DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER valor_total;

ALTER TABLE cobrancas
    ADD INDEX idx_cobranca_empresa_cliente_ref (empresa_id, cliente_id, referencia),
    ADD INDEX idx_cobrancas_tipo (tipo),
    ADD INDEX idx_cobrancas_grupo (grupo_parcelamento_id);
