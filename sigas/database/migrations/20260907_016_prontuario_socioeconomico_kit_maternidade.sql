SET NAMES utf8mb4;

-- ============================================================================
-- SIGAS | Prontuário socioeconômico central + solicitações multi-benefício
--       | Fluxo operacional do Kit Maternidade
-- Compatibilidade alvo: MariaDB 11.x
--
-- Princípios:
-- 1. pessoa é única no SIGAS (pessoas.id);
-- 2. formulário socioeconômico é compartilhado entre os módulos;
-- 3. uma pessoa pode possuir várias solicitações simultâneas;
-- 4. aptidão é decisão humana, nunca inferida automaticamente pelo formulário;
-- 5. ANEXO é fonte de consulta/importação, nunca recebe escrita por esta migration.
-- ============================================================================

CREATE TABLE IF NOT EXISTS pessoa_socioeconomico (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    pessoa_id BIGINT UNSIGNED NOT NULL,
    familia_id BIGINT UNSIGNED NULL,

    origem VARCHAR(30) NOT NULL DEFAULT 'sigas',
    anexo_solicitante_id BIGINT UNSIGNED NULL,
    anexo_atualizado_em DATETIME NULL,
    versao_formulario SMALLINT UNSIGNED NOT NULL DEFAULT 1,

    data_entrevista DATETIME NULL,
    entrevistado_por BIGINT UNSIGNED NULL,
    confirmado_em DATETIME NULL,
    confirmado_por BIGINT UNSIGNED NULL,

    escolaridade VARCHAR(120) NULL,
    situacao_trabalho VARCHAR(120) NULL,
    ocupacao VARCHAR(150) NULL,
    renda_individual DECIMAL(12,2) NULL,
    renda_familiar DECIMAL(12,2) NULL,
    renda_per_capita DECIMAL(12,2) NULL,

    grupo_tradicional VARCHAR(100) NULL,
    possui_deficiencia TINYINT(1) NOT NULL DEFAULT 0,
    deficiencia_descricao VARCHAR(255) NULL,

    beneficios_json JSON NULL,
    vulnerabilidades_json JSON NULL,

    tipo_moradia VARCHAR(80) NULL,
    material_moradia VARCHAR(80) NULL,
    numero_comodos SMALLINT UNSIGNED NULL,
    abastecimento_agua VARCHAR(100) NULL,
    energia_eletrica VARCHAR(80) NULL,
    coleta_lixo VARCHAR(80) NULL,
    esgotamento_sanitario VARCHAR(100) NULL,
    area_risco TINYINT(1) NOT NULL DEFAULT 0,
    area_risco_descricao VARCHAR(255) NULL,

    resumo_social TEXT NULL,
    observacoes TEXT NULL,

    criado_por BIGINT UNSIGNED NULL,
    atualizado_por BIGINT UNSIGNED NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uk_pessoa_socioeconomico_pessoa (pessoa_id),
    KEY idx_pessoa_socioeconomico_familia (familia_id),
    KEY idx_pessoa_socioeconomico_origem (origem),
    KEY idx_pessoa_socioeconomico_entrevista (data_entrevista),
    KEY idx_pessoa_socioeconomico_entrevistado (entrevistado_por),
    KEY idx_pessoa_socioeconomico_atualizado_por (atualizado_por),

    CONSTRAINT fk_pessoa_socioeconomico_pessoa
        FOREIGN KEY (pessoa_id) REFERENCES pessoas(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_pessoa_socioeconomico_familia
        FOREIGN KEY (familia_id) REFERENCES familias(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_pessoa_socioeconomico_entrevistado
        FOREIGN KEY (entrevistado_por) REFERENCES usuarios(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_pessoa_socioeconomico_confirmado
        FOREIGN KEY (confirmado_por) REFERENCES usuarios(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_pessoa_socioeconomico_criado_por
        FOREIGN KEY (criado_por) REFERENCES usuarios(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_pessoa_socioeconomico_atualizado_por
        FOREIGN KEY (atualizado_por) REFERENCES usuarios(id)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pessoa_socioeconomico_historico (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    socioeconomico_id BIGINT UNSIGNED NOT NULL,
    pessoa_id BIGINT UNSIGNED NOT NULL,
    origem VARCHAR(30) NOT NULL,
    motivo VARCHAR(255) NULL,
    dados_json JSON NOT NULL,
    usuario_id BIGINT UNSIGNED NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    KEY idx_socio_historico_socio (socioeconomico_id),
    KEY idx_socio_historico_pessoa (pessoa_id, criado_em),
    KEY idx_socio_historico_usuario (usuario_id),

    CONSTRAINT fk_socio_historico_socio
        FOREIGN KEY (socioeconomico_id) REFERENCES pessoa_socioeconomico(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_socio_historico_pessoa
        FOREIGN KEY (pessoa_id) REFERENCES pessoas(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_socio_historico_usuario
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS beneficio_solicitacoes (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    pessoa_id BIGINT UNSIGNED NOT NULL,
    atendimento_id BIGINT UNSIGNED NULL,
    socioeconomico_id BIGINT UNSIGNED NULL,

    modulo VARCHAR(80) NOT NULL,
    beneficio_codigo VARCHAR(80) NOT NULL,
    beneficio_nome VARCHAR(150) NOT NULL,

    status VARCHAR(40) NOT NULL DEFAULT 'solicitado',
    prioridade VARCHAR(30) NOT NULL DEFAULT 'normal',

    setor_origem_id BIGINT UNSIGNED NULL,
    responsavel_usuario_id BIGINT UNSIGNED NULL,

    solicitado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    solicitado_por BIGINT UNSIGNED NULL,

    decisao VARCHAR(30) NULL,
    decisao_motivo TEXT NULL,
    decidido_por BIGINT UNSIGNED NULL,
    decidido_em DATETIME NULL,

    referencia_modulo VARCHAR(80) NULL,
    referencia_tipo VARCHAR(80) NULL,
    referencia_id BIGINT UNSIGNED NULL,
    observacao TEXT NULL,

    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uk_beneficio_solicitacoes_atendimento (atendimento_id),
    KEY idx_beneficio_solicitacoes_pessoa (pessoa_id, solicitado_em),
    KEY idx_beneficio_solicitacoes_modulo (modulo, status),
    KEY idx_beneficio_solicitacoes_responsavel (responsavel_usuario_id, status),
    KEY idx_beneficio_solicitacoes_socio (socioeconomico_id),
    KEY idx_beneficio_solicitacoes_setor (setor_origem_id),
    KEY idx_beneficio_solicitacoes_referencia (referencia_modulo, referencia_tipo, referencia_id),

    CONSTRAINT fk_beneficio_solicitacoes_pessoa
        FOREIGN KEY (pessoa_id) REFERENCES pessoas(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_beneficio_solicitacoes_atendimento
        FOREIGN KEY (atendimento_id) REFERENCES pessoa_atendimentos(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_beneficio_solicitacoes_socio
        FOREIGN KEY (socioeconomico_id) REFERENCES pessoa_socioeconomico(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_beneficio_solicitacoes_setor
        FOREIGN KEY (setor_origem_id) REFERENCES setores(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_beneficio_solicitacoes_responsavel
        FOREIGN KEY (responsavel_usuario_id) REFERENCES usuarios(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_beneficio_solicitacoes_solicitado_por
        FOREIGN KEY (solicitado_por) REFERENCES usuarios(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_beneficio_solicitacoes_decidido_por
        FOREIGN KEY (decidido_por) REFERENCES usuarios(id)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS kit_maternidade_solicitacoes (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    beneficio_solicitacao_id BIGINT UNSIGNED NOT NULL,
    pessoa_id BIGINT UNSIGNED NOT NULL,

    dum DATE NULL,
    dpp DATE NULL,
    idade_gestacional_inicial_semanas TINYINT UNSIGNED NULL,
    prenatal_iniciado TINYINT(1) NOT NULL DEFAULT 0,
    unidade_prenatal VARCHAR(150) NULL,
    gestacao_risco TINYINT(1) NOT NULL DEFAULT 0,
    risco_descricao VARCHAR(500) NULL,
    numero_gestacao SMALLINT UNSIGNED NULL,
    numero_partos SMALLINT UNSIGNED NULL,

    responsavel_tecnico_id BIGINT UNSIGNED NULL,
    acompanhamento_iniciado_em DATETIME NULL,
    acompanhamento_encerrado_em DATETIME NULL,

    status VARCHAR(40) NOT NULL DEFAULT 'solicitado',
    observacao TEXT NULL,

    criado_por BIGINT UNSIGNED NULL,
    atualizado_por BIGINT UNSIGNED NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uk_kit_maternidade_beneficio (beneficio_solicitacao_id),
    KEY idx_kit_maternidade_pessoa (pessoa_id, status),
    KEY idx_kit_maternidade_responsavel (responsavel_tecnico_id, status),
    KEY idx_kit_maternidade_dpp (dpp),
    KEY idx_kit_maternidade_risco (gestacao_risco, status),

    CONSTRAINT fk_kit_maternidade_beneficio
        FOREIGN KEY (beneficio_solicitacao_id) REFERENCES beneficio_solicitacoes(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_kit_maternidade_pessoa
        FOREIGN KEY (pessoa_id) REFERENCES pessoas(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_kit_maternidade_responsavel
        FOREIGN KEY (responsavel_tecnico_id) REFERENCES usuarios(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_kit_maternidade_criado_por
        FOREIGN KEY (criado_por) REFERENCES usuarios(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_kit_maternidade_atualizado_por
        FOREIGN KEY (atualizado_por) REFERENCES usuarios(id)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS kit_maternidade_acompanhamentos (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    kit_solicitacao_id BIGINT UNSIGNED NOT NULL,
    tipo VARCHAR(30) NOT NULL,
    data_evento DATETIME NOT NULL,
    idade_gestacional_semanas TINYINT UNSIGNED NULL,

    participacao VARCHAR(30) NULL,
    risco_identificado TINYINT(1) NOT NULL DEFAULT 0,
    risco_descricao VARCHAR(500) NULL,

    observacao TEXT NULL,
    proxima_acao VARCHAR(255) NULL,
    proxima_acao_em DATETIME NULL,

    usuario_id BIGINT UNSIGNED NOT NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    KEY idx_kit_acomp_solicitacao (kit_solicitacao_id, data_evento),
    KEY idx_kit_acomp_tipo (kit_solicitacao_id, tipo),
    KEY idx_kit_acomp_usuario (usuario_id, data_evento),
    KEY idx_kit_acomp_proxima (proxima_acao_em),

    CONSTRAINT fk_kit_acomp_solicitacao
        FOREIGN KEY (kit_solicitacao_id) REFERENCES kit_maternidade_solicitacoes(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_kit_acomp_usuario
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS kit_maternidade_avaliacoes (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    kit_solicitacao_id BIGINT UNSIGNED NOT NULL,
    resultado VARCHAR(30) NOT NULL,
    parecer_tecnico TEXT NOT NULL,
    pendencias_json JSON NULL,
    usuario_id BIGINT UNSIGNED NOT NULL,
    avaliado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    KEY idx_kit_avaliacoes_solicitacao (kit_solicitacao_id, avaliado_em),
    KEY idx_kit_avaliacoes_resultado (resultado, avaliado_em),
    KEY idx_kit_avaliacoes_usuario (usuario_id),

    CONSTRAINT fk_kit_avaliacoes_solicitacao
        FOREIGN KEY (kit_solicitacao_id) REFERENCES kit_maternidade_solicitacoes(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_kit_avaliacoes_usuario
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS kit_maternidade_entregas (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    kit_solicitacao_id BIGINT UNSIGNED NOT NULL,
    entregue_em DATETIME NOT NULL,
    lote VARCHAR(80) NULL,
    termo_referencia VARCHAR(100) NULL,
    recebedor_nome VARCHAR(150) NOT NULL,
    recebedor_cpf CHAR(11) NULL,
    observacao TEXT NULL,
    entregue_por BIGINT UNSIGNED NOT NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uk_kit_entrega_solicitacao (kit_solicitacao_id),
    KEY idx_kit_entrega_data (entregue_em),
    KEY idx_kit_entrega_usuario (entregue_por),

    CONSTRAINT fk_kit_entrega_solicitacao
        FOREIGN KEY (kit_solicitacao_id) REFERENCES kit_maternidade_solicitacoes(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_kit_entrega_usuario
        FOREIGN KEY (entregue_por) REFERENCES usuarios(id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO permissoes (nome, slug, descricao, modulo, ativo) VALUES
('Visualizar prontuário socioeconômico', 'socioeconomico.visualizar', 'Permite consultar o prontuário socioeconômico central da pessoa.', 'socioeconomico', 1),
('Editar prontuário socioeconômico', 'socioeconomico.editar', 'Permite registrar e atualizar o formulário socioeconômico central.', 'socioeconomico', 1),
('Importar dados socioeconômicos do ANEXO', 'socioeconomico.importar_anexo', 'Permite trazer dados do ANEXO para conferência no SIGAS, sem alterar a base externa.', 'socioeconomico', 1)
ON DUPLICATE KEY UPDATE
    nome = VALUES(nome),
    descricao = VALUES(descricao),
    modulo = VALUES(modulo),
    ativo = VALUES(ativo);

INSERT IGNORE INTO nivel_permissoes (nivel_id, permissao_id)
SELECT n.id, p.id
FROM niveis_acesso n
JOIN permissoes p ON p.slug IN (
    'socioeconomico.visualizar',
    'socioeconomico.editar',
    'socioeconomico.importar_anexo'
)
WHERE n.slug IN ('administrador', 'suporte', 'gestor', 'tecnico')
  AND n.ativo = 1;

INSERT IGNORE INTO nivel_permissoes (nivel_id, permissao_id)
SELECT n.id, p.id
FROM niveis_acesso n
JOIN permissoes p ON p.slug IN (
    'socioeconomico.visualizar',
    'socioeconomico.importar_anexo'
)
WHERE n.slug = 'atendente'
  AND n.ativo = 1;
