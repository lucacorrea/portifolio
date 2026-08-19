-- Execute APENAS se o banco já foi criado com a versão anterior de 001_primeiro_emprego.sql.
-- Se a instalação é nova e você executou o 001 atual deste pacote, NÃO execute este arquivo.

ALTER TABLE pe_importacoes
    ADD COLUMN arquivo_hash CHAR(64) NULL AFTER arquivo_nome,
    ADD COLUMN bloqueados INT UNSIGNED NOT NULL DEFAULT 0 AFTER atualizados,
    ADD COLUMN avisos INT UNSIGNED NOT NULL DEFAULT 0 AFTER bloqueados,
    ADD COLUMN marcar_como_contemplados TINYINT(1) NOT NULL DEFAULT 0 AFTER erros,
    ADD COLUMN responsavel VARCHAR(160) NULL AFTER marcar_como_contemplados,
    ADD KEY idx_pe_importacoes_hash (arquivo_hash);

CREATE TABLE IF NOT EXISTS pe_importacao_itens (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    importacao_id BIGINT UNSIGNED NOT NULL,
    linha INT UNSIGNED NOT NULL,
    candidato_id BIGINT UNSIGNED NULL,
    status VARCHAR(20) NOT NULL,
    nome VARCHAR(160) NOT NULL,
    cpf_informado VARCHAR(32) NULL,
    cpf_validado VARCHAR(11) NULL,
    data_nascimento DATE NULL,
    setor_informado VARCHAR(180) NULL,
    mensagem TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_pe_importacao_linha (importacao_id, linha),
    KEY idx_pe_importacao_itens_status (status),
    KEY idx_pe_importacao_itens_candidato (candidato_id),
    CONSTRAINT fk_pe_importacao_itens_importacao FOREIGN KEY (importacao_id) REFERENCES pe_importacoes(id) ON DELETE CASCADE,
    CONSTRAINT fk_pe_importacao_itens_candidato FOREIGN KEY (candidato_id) REFERENCES pe_candidatos(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
