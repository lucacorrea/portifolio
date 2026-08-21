-- SIGAS - Coari Meu Primeiro Emprego
-- Importação e conciliação de extratos oficiais de pagamento em PDF.
-- Pode ser executado em base já existente após as migrations anteriores.

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS pe_pagamento_importacoes (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    arquivo_nome VARCHAR(255) NOT NULL,
    arquivo_hash CHAR(64) NULL,
    banco VARCHAR(80) NOT NULL DEFAULT 'Banco do Brasil',
    convenio_numero VARCHAR(40) NULL,
    convenio_nome VARCHAR(180) NULL,
    lista_numero VARCHAR(40) NULL,
    lista_nome VARCHAR(180) NULL,
    estado_lista VARCHAR(40) NULL,
    data_pagamento DATE NULL,
    forma_pagamento VARCHAR(80) NULL,
    competencia CHAR(7) NOT NULL,
    total_pagamentos INT UNSIGNED NOT NULL DEFAULT 0,
    valor_total DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    conciliados INT UNSIGNED NOT NULL DEFAULT 0,
    atualizados INT UNSIGNED NOT NULL DEFAULT 0,
    ja_conciliados INT UNSIGNED NOT NULL DEFAULT 0,
    nao_localizados INT UNSIGNED NOT NULL DEFAULT 0,
    ambiguos INT UNSIGNED NOT NULL DEFAULT 0,
    cpf_invalidos INT UNSIGNED NOT NULL DEFAULT 0,
    divergencias_nome INT UNSIGNED NOT NULL DEFAULT 0,
    conflitos_financeiros INT UNSIGNED NOT NULL DEFAULT 0,
    erros INT UNSIGNED NOT NULL DEFAULT 0,
    fonte_extracao VARCHAR(40) NULL,
    responsavel VARCHAR(160) NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'Processando',
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    finalizada_em TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (id),
    KEY idx_pe_pag_import_hash (arquivo_hash),
    KEY idx_pe_pag_import_comp (competencia),
    KEY idx_pe_pag_import_lista (convenio_numero, lista_numero),
    KEY idx_pe_pag_import_status (status),
    KEY idx_pe_pag_import_criado (criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pe_pagamento_importacao_itens (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    importacao_id BIGINT UNSIGNED NOT NULL,
    n_ident VARCHAR(20) NOT NULL,
    candidato_id BIGINT UNSIGNED NULL,
    cpf_informado VARCHAR(32) NULL,
    cpf_validado VARCHAR(11) NULL,
    nome_banco VARCHAR(180) NOT NULL,
    valor DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    agencia VARCHAR(20) NULL,
    conta VARCHAR(30) NULL,
    variacao VARCHAR(10) NULL,
    situacao VARCHAR(50) NULL,
    data_situacao DATE NULL,
    observacao VARCHAR(500) NULL,
    conciliacao_status VARCHAR(40) NOT NULL,
    mensagem VARCHAR(700) NULL,
    aplicado_em DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_pe_pag_item_import_ident (importacao_id, n_ident),
    KEY idx_pe_pag_item_candidato (candidato_id),
    KEY idx_pe_pag_item_cpf (cpf_validado),
    KEY idx_pe_pag_item_status (conciliacao_status),
    CONSTRAINT fk_pe_pag_item_importacao
        FOREIGN KEY (importacao_id) REFERENCES pe_pagamento_importacoes(id) ON DELETE CASCADE,
    CONSTRAINT fk_pe_pag_item_candidato
        FOREIGN KEY (candidato_id) REFERENCES pe_candidatos(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
