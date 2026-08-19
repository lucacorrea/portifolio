-- SIGAS - ATUALIZACAO COMPLETA DO PRIMEIRO EMPREGO
-- Para banco existente. Execute no banco atual do SIGAS.

-- SIGAS - Coari Meu Primeiro Emprego
-- Atualizacao operacional de candidatos/importacao/revisao.
-- Compativel com MariaDB 10.5+ / 11.x. Pode ser executada novamente.

SET NAMES utf8mb4;

ALTER TABLE pe_candidatos
    DROP INDEX IF EXISTS uk_pe_candidatos_cpf,
    DROP INDEX IF EXISTS uk_pe_candidatos_chave_importacao,
    ADD COLUMN IF NOT EXISTS revisao_status VARCHAR(40) NULL AFTER status,
    ADD COLUMN IF NOT EXISTS revisao_cpf TINYINT(1) NOT NULL DEFAULT 0 AFTER revisao_status,
    ADD COLUMN IF NOT EXISTS revisao_telefone TINYINT(1) NOT NULL DEFAULT 0 AFTER revisao_cpf,
    ADD COLUMN IF NOT EXISTS revisao_nascimento TINYINT(1) NOT NULL DEFAULT 0 AFTER revisao_telefone,
    ADD COLUMN IF NOT EXISTS cpf_duplicado TINYINT(1) NOT NULL DEFAULT 0 AFTER revisao_nascimento,
    ADD COLUMN IF NOT EXISTS cpf_revisado_confirmado TINYINT(1) NOT NULL DEFAULT 0 AFTER cpf_duplicado,
    ADD COLUMN IF NOT EXISTS telefone_revisado_confirmado TINYINT(1) NOT NULL DEFAULT 0 AFTER cpf_revisado_confirmado,
    ADD COLUMN IF NOT EXISTS nascimento_revisado_confirmado TINYINT(1) NOT NULL DEFAULT 0 AFTER telefone_revisado_confirmado,
    ADD COLUMN IF NOT EXISTS cpf_duplicado_confirmado TINYINT(1) NOT NULL DEFAULT 0 AFTER nascimento_revisado_confirmado,
    ADD COLUMN IF NOT EXISTS revisao_motivos LONGTEXT NULL AFTER cpf_duplicado_confirmado,
    ADD COLUMN IF NOT EXISTS revisao_atualizada_em DATETIME NULL AFTER revisao_motivos,
    ADD COLUMN IF NOT EXISTS revisao_revisado_por VARCHAR(160) NULL AFTER revisao_atualizada_em,
    ADD COLUMN IF NOT EXISTS revisao_revisado_em DATETIME NULL AFTER revisao_revisado_por,
    ADD INDEX IF NOT EXISTS idx_pe_candidatos_cpf_nao_unico (cpf),
    ADD INDEX IF NOT EXISTS idx_pe_candidatos_chave_importacao (chave_importacao),
    ADD INDEX IF NOT EXISTS idx_pe_candidatos_revisao_status (revisao_status),
    ADD INDEX IF NOT EXISTS idx_pe_candidatos_revisao_cpf (revisao_cpf),
    ADD INDEX IF NOT EXISTS idx_pe_candidatos_revisao_telefone (revisao_telefone),
    ADD INDEX IF NOT EXISTS idx_pe_candidatos_revisao_nascimento (revisao_nascimento),
    ADD INDEX IF NOT EXISTS idx_pe_candidatos_cpf_duplicado (cpf_duplicado);

ALTER TABLE pe_importacoes
    ADD COLUMN IF NOT EXISTS pendentes_revisao INT UNSIGNED NOT NULL DEFAULT 0 AFTER avisos;

CREATE TABLE IF NOT EXISTS pe_revisoes_cadastrais (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    candidato_id BIGINT UNSIGNED NOT NULL,
    cpf_anterior VARCHAR(32) NULL,
    cpf_novo VARCHAR(32) NULL,
    telefone_anterior VARCHAR(20) NULL,
    telefone_novo VARCHAR(20) NULL,
    nascimento_anterior DATE NULL,
    nascimento_novo DATE NULL,
    confirmou_cpf TINYINT(1) NOT NULL DEFAULT 0,
    confirmou_telefone TINYINT(1) NOT NULL DEFAULT 0,
    confirmou_nascimento TINYINT(1) NOT NULL DEFAULT 0,
    confirmou_cpf_duplicado TINYINT(1) NOT NULL DEFAULT 0,
    observacao VARCHAR(500) NULL,
    revisado_por VARCHAR(160) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_pe_revisoes_candidato (candidato_id),
    KEY idx_pe_revisoes_data (created_at),
    CONSTRAINT fk_pe_revisoes_candidato
        FOREIGN KEY (candidato_id) REFERENCES pe_candidatos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Classificacao inicial de registros antigos ainda nao revisados.
UPDATE pe_candidatos
SET
    revisao_cpf = CASE
        WHEN cpf_revisado_confirmado = 1 THEN 0
        WHEN cpf IS NULL OR cpf = '' THEN 1
        ELSE 0
    END,
    revisao_telefone = CASE
        WHEN telefone_revisado_confirmado = 1 THEN 0
        WHEN telefone IS NULL OR telefone = '' OR CHAR_LENGTH(telefone) NOT IN (10, 11) THEN 1
        ELSE 0
    END,
    revisao_nascimento = CASE
        WHEN nascimento_revisado_confirmado = 1 THEN 0
        WHEN data_nascimento IS NULL THEN 1
        ELSE 0
    END;

-- Marca CPFs validamente armazenados que aparecem em mais de um candidato.
UPDATE pe_candidatos c
JOIN (
    SELECT cpf
    FROM pe_candidatos
    WHERE cpf IS NOT NULL AND cpf <> ''
    GROUP BY cpf
    HAVING COUNT(*) > 1
) d ON d.cpf = c.cpf
SET c.cpf_duplicado = 1;

UPDATE pe_candidatos c
LEFT JOIN (
    SELECT cpf
    FROM pe_candidatos
    WHERE cpf IS NOT NULL AND cpf <> ''
    GROUP BY cpf
    HAVING COUNT(*) > 1
) d ON d.cpf = c.cpf
SET c.cpf_duplicado = 0
WHERE d.cpf IS NULL;

UPDATE pe_candidatos
SET revisao_cpf = 1
WHERE cpf_duplicado = 1 AND cpf_duplicado_confirmado = 0;

UPDATE pe_candidatos
SET
    revisao_status = CASE
        WHEN (revisao_cpf + revisao_telefone + revisao_nascimento) = 0 THEN NULL
        WHEN (revisao_cpf + revisao_telefone + revisao_nascimento) > 1 THEN 'Revisar Cadastro'
        WHEN revisao_cpf = 1 THEN 'Revisar CPF'
        WHEN revisao_telefone = 1 THEN 'Revisar Telefone'
        ELSE 'Revisar Data de Nascimento'
    END,
    revisao_motivos = CONCAT_WS(' | ',
        CASE WHEN revisao_cpf = 1 AND cpf_duplicado = 1 AND cpf_duplicado_confirmado = 0 THEN 'CPF duplicado com outro cadastro' END,
        CASE WHEN revisao_cpf = 1 AND cpf_duplicado = 0 AND (cpf_informado IS NULL OR cpf_informado = '') THEN 'CPF não informado' END,
        CASE WHEN revisao_cpf = 1 AND cpf_duplicado = 0 AND cpf_informado IS NOT NULL AND cpf_informado <> '' THEN 'CPF inconsistente' END,
        CASE WHEN revisao_telefone = 1 AND (telefone IS NULL OR telefone = '') THEN 'Telefone não informado' END,
        CASE WHEN revisao_telefone = 1 AND telefone IS NOT NULL AND telefone <> '' THEN 'Telefone fora do padrão' END,
        CASE WHEN revisao_nascimento = 1 THEN 'Data de nascimento não informada' END
    ),
    revisao_atualizada_em = CASE
        WHEN (revisao_cpf + revisao_telefone + revisao_nascimento) > 0 THEN CURRENT_TIMESTAMP
        ELSE NULL
    END;

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
