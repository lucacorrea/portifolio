-- SIGAS - Coari Meu Primeiro Emprego
-- Estruturas operacionais complementares do programa.
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS pe_parceiros (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    nome VARCHAR(180) NOT NULL,
    tipo VARCHAR(60) NULL,
    cnpj VARCHAR(14) NULL,
    responsavel VARCHAR(160) NULL,
    telefone VARCHAR(20) NULL,
    email VARCHAR(160) NULL,
    termo_parceria VARCHAR(120) NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'Ativa',
    observacao TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_pe_parceiros_nome (nome),
    KEY idx_pe_parceiros_status (status),
    KEY idx_pe_parceiros_cnpj (cnpj)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pe_vagas (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    parceiro_id BIGINT UNSIGNED NULL,
    cargo VARCHAR(160) NOT NULL,
    setor VARCHAR(160) NULL,
    quantidade SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    requisitos TEXT NULL,
    escolaridade VARCHAR(100) NULL,
    carga_horaria VARCHAR(40) NULL,
    remuneracao DECIMAL(12,2) NULL,
    prazo DATE NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'Aberta',
    observacao TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_pe_vagas_parceiro (parceiro_id),
    KEY idx_pe_vagas_status (status),
    KEY idx_pe_vagas_prazo (prazo),
    CONSTRAINT fk_pe_vagas_parceiro FOREIGN KEY (parceiro_id) REFERENCES pe_parceiros(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pe_encaminhamentos (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    candidato_id BIGINT UNSIGNED NOT NULL,
    vaga_id BIGINT UNSIGNED NULL,
    parceiro_id BIGINT UNSIGNED NULL,
    data_encaminhamento DATE NOT NULL,
    responsavel VARCHAR(160) NULL,
    retorno TEXT NULL,
    status VARCHAR(40) NOT NULL DEFAULT 'Pendente',
    data_retorno DATE NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_pe_enc_candidato (candidato_id),
    KEY idx_pe_enc_vaga (vaga_id),
    KEY idx_pe_enc_parceiro (parceiro_id),
    KEY idx_pe_enc_status (status),
    CONSTRAINT fk_pe_enc_candidato FOREIGN KEY (candidato_id) REFERENCES pe_candidatos(id) ON DELETE CASCADE,
    CONSTRAINT fk_pe_enc_vaga FOREIGN KEY (vaga_id) REFERENCES pe_vagas(id) ON DELETE SET NULL,
    CONSTRAINT fk_pe_enc_parceiro FOREIGN KEY (parceiro_id) REFERENCES pe_parceiros(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pe_documentos (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    candidato_id BIGINT UNSIGNED NOT NULL,
    tipo VARCHAR(100) NOT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'Pendente',
    validade DATE NULL,
    observacao VARCHAR(500) NULL,
    arquivo_path VARCHAR(255) NULL,
    nome_original VARCHAR(255) NULL,
    mime_type VARCHAR(100) NULL,
    size_bytes INT UNSIGNED NULL,
    registrado_por VARCHAR(160) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_pe_docs_candidato (candidato_id),
    KEY idx_pe_docs_status (status),
    KEY idx_pe_docs_validade (validade),
    CONSTRAINT fk_pe_docs_candidato FOREIGN KEY (candidato_id) REFERENCES pe_candidatos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pe_frequencias (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    candidato_id BIGINT UNSIGNED NOT NULL,
    competencia CHAR(7) NOT NULL,
    dias_previstos SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    presencas SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    faltas SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    percentual DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    status VARCHAR(30) NOT NULL DEFAULT 'Regular',
    observacao VARCHAR(500) NULL,
    registrado_por VARCHAR(160) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_pe_freq_candidato_comp (candidato_id, competencia),
    KEY idx_pe_freq_competencia (competencia),
    KEY idx_pe_freq_status (status),
    CONSTRAINT fk_pe_freq_candidato FOREIGN KEY (candidato_id) REFERENCES pe_candidatos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pe_bolsas (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    candidato_id BIGINT UNSIGNED NOT NULL,
    competencia CHAR(7) NOT NULL,
    valor DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    status VARCHAR(40) NOT NULL DEFAULT 'Em análise',
    data_pagamento DATE NULL,
    observacao VARCHAR(500) NULL,
    registrado_por VARCHAR(160) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_pe_bolsa_candidato_comp (candidato_id, competencia),
    KEY idx_pe_bolsa_competencia (competencia),
    KEY idx_pe_bolsa_status (status),
    CONSTRAINT fk_pe_bolsa_candidato FOREIGN KEY (candidato_id) REFERENCES pe_candidatos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pe_capacitacoes (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    curso VARCHAR(180) NOT NULL,
    instituicao VARCHAR(180) NULL,
    turma VARCHAR(80) NULL,
    carga_horaria SMALLINT UNSIGNED NULL,
    data_inicio DATE NULL,
    data_fim DATE NULL,
    vagas SMALLINT UNSIGNED NULL,
    certificado VARCHAR(30) NOT NULL DEFAULT 'Previsto',
    status VARCHAR(40) NOT NULL DEFAULT 'Planejada',
    observacao TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_pe_cap_status (status),
    KEY idx_pe_cap_inicio (data_inicio)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pe_capacitacao_participantes (
    capacitacao_id BIGINT UNSIGNED NOT NULL,
    candidato_id BIGINT UNSIGNED NOT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'Inscrito',
    frequencia_percentual DECIMAL(5,2) NULL,
    certificado_emitido TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (capacitacao_id, candidato_id),
    KEY idx_pe_cap_part_candidato (candidato_id),
    CONSTRAINT fk_pe_cap_part_cap FOREIGN KEY (capacitacao_id) REFERENCES pe_capacitacoes(id) ON DELETE CASCADE,
    CONSTRAINT fk_pe_cap_part_cand FOREIGN KEY (candidato_id) REFERENCES pe_candidatos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pe_acompanhamentos_programa (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    candidato_id BIGINT UNSIGNED NOT NULL,
    data_acompanhamento DATE NOT NULL,
    tipo VARCHAR(80) NOT NULL,
    resumo TEXT NOT NULL,
    proxima_acao VARCHAR(255) NULL,
    data_proxima_acao DATE NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'Regular',
    responsavel VARCHAR(160) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_pe_acomp_candidato (candidato_id),
    KEY idx_pe_acomp_data (data_acompanhamento),
    KEY idx_pe_acomp_status (status),
    CONSTRAINT fk_pe_acomp_candidato FOREIGN KEY (candidato_id) REFERENCES pe_candidatos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pe_configuracoes (
    chave VARCHAR(100) NOT NULL,
    valor TEXT NULL,
    descricao VARCHAR(255) NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (chave)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO pe_configuracoes (chave, valor, descricao) VALUES
('bolsa_valor_padrao', '800.00', 'Valor padrão da bolsa do programa'),
('frequencia_minima', '75.00', 'Frequência mínima percentual para acompanhamento'),
('municipio_padrao', 'Coari', 'Município padrão do programa')
ON DUPLICATE KEY UPDATE descricao=VALUES(descricao);
