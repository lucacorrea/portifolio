SET NAMES utf8mb4;

START TRANSACTION;

CREATE TABLE IF NOT EXISTS pessoas (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    nome VARCHAR(150) NOT NULL,
    cpf CHAR(11) NOT NULL,
    nis VARCHAR(14) NULL,
    rg VARCHAR(30) NULL,
    data_nascimento DATE NULL,
    telefone VARCHAR(30) NULL,
    email VARCHAR(180) NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'ativo',
    criado_por BIGINT UNSIGNED NULL,
    atualizado_por BIGINT UNSIGNED NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_pessoas_cpf (cpf),
    KEY idx_pessoas_nis (nis),
    KEY idx_pessoas_status (status),
    KEY idx_pessoas_criado_por (criado_por),
    KEY idx_pessoas_atualizado_por (atualizado_por),
    CONSTRAINT fk_pessoas_criado_por
        FOREIGN KEY (criado_por) REFERENCES usuarios (id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_pessoas_atualizado_por
        FOREIGN KEY (atualizado_por) REFERENCES usuarios (id)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS familias (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    codigo VARCHAR(30) NOT NULL,
    responsavel_pessoa_id BIGINT UNSIGNED NOT NULL,
    zona VARCHAR(20) NULL,
    logradouro VARCHAR(180) NULL,
    numero VARCHAR(30) NULL,
    complemento VARCHAR(120) NULL,
    bairro VARCHAR(120) NULL,
    comunidade VARCHAR(150) NULL,
    ponto_referencia VARCHAR(255) NULL,
    cep VARCHAR(12) NULL,
    quantidade_membros SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    renda_familiar DECIMAL(12,2) NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'ativo',
    criado_por BIGINT UNSIGNED NULL,
    atualizado_por BIGINT UNSIGNED NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_familias_codigo (codigo),
    UNIQUE KEY uk_familias_responsavel (responsavel_pessoa_id),
    KEY idx_familias_status (status),
    KEY idx_familias_criado_por (criado_por),
    KEY idx_familias_atualizado_por (atualizado_por),
    CONSTRAINT fk_familias_responsavel
        FOREIGN KEY (responsavel_pessoa_id) REFERENCES pessoas (id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_familias_criado_por
        FOREIGN KEY (criado_por) REFERENCES usuarios (id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_familias_atualizado_por
        FOREIGN KEY (atualizado_por) REFERENCES usuarios (id)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS familia_membros (
    familia_id BIGINT UNSIGNED NOT NULL,
    pessoa_id BIGINT UNSIGNED NOT NULL,
    parentesco VARCHAR(60) NULL,
    responsavel TINYINT(1) NOT NULL DEFAULT 0,
    renda_mensal DECIMAL(12,2) NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (familia_id, pessoa_id),
    KEY idx_familia_membros_pessoa (pessoa_id),
    CONSTRAINT fk_familia_membros_familia
        FOREIGN KEY (familia_id) REFERENCES familias (id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_familia_membros_pessoa
        FOREIGN KEY (pessoa_id) REFERENCES pessoas (id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS comida_mesa_polos (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    nome VARCHAR(150) NOT NULL,
    slug VARCHAR(100) NOT NULL,
    endereco VARCHAR(255) NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_comida_mesa_polos_slug (slug),
    KEY idx_comida_mesa_polos_ativo (ativo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO comida_mesa_polos (nome, slug, endereco, ativo)
VALUES
    ('Polo Centro', 'centro', NULL, 1),
    ('Polo São Sebastião', 'sao-sebastiao', NULL, 1),
    ('Polo Itamarati', 'itamarati', NULL, 1),
    ('Polo Zona Rural', 'zona-rural', NULL, 1)
ON DUPLICATE KEY UPDATE
    nome = VALUES(nome),
    endereco = VALUES(endereco),
    ativo = VALUES(ativo);

CREATE TABLE IF NOT EXISTS comida_mesa_inscricoes (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    familia_id BIGINT UNSIGNED NOT NULL,
    polo_id BIGINT UNSIGNED NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'em_analise',
    prioridade VARCHAR(20) NOT NULL DEFAULT 'normal',
    data_inscricao DATE NOT NULL,
    data_aprovacao DATETIME NULL,
    aprovado_por BIGINT UNSIGNED NULL,
    motivo_suspensao VARCHAR(255) NULL,
    observacao TEXT NULL,
    criado_por BIGINT UNSIGNED NULL,
    atualizado_por BIGINT UNSIGNED NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_comida_mesa_inscricoes_familia (familia_id),
    KEY idx_comida_mesa_inscricoes_status (status),
    KEY idx_comida_mesa_inscricoes_polo (polo_id),
    KEY idx_comida_mesa_inscricoes_data (data_inscricao),
    KEY idx_comida_mesa_inscricoes_aprovado_por (aprovado_por),
    KEY idx_comida_mesa_inscricoes_criado_por (criado_por),
    KEY idx_comida_mesa_inscricoes_atualizado_por (atualizado_por),
    CONSTRAINT fk_comida_mesa_inscricoes_familia
        FOREIGN KEY (familia_id) REFERENCES familias (id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_comida_mesa_inscricoes_polo
        FOREIGN KEY (polo_id) REFERENCES comida_mesa_polos (id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_comida_mesa_inscricoes_aprovado_por
        FOREIGN KEY (aprovado_por) REFERENCES usuarios (id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_comida_mesa_inscricoes_criado_por
        FOREIGN KEY (criado_por) REFERENCES usuarios (id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_comida_mesa_inscricoes_atualizado_por
        FOREIGN KEY (atualizado_por) REFERENCES usuarios (id)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS comida_mesa_competencias (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    ano SMALLINT UNSIGNED NOT NULL,
    mes TINYINT UNSIGNED NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'planejada',
    inicio_entregas DATE NULL,
    fim_entregas DATE NULL,
    observacao VARCHAR(255) NULL,
    criado_por BIGINT UNSIGNED NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_comida_mesa_competencias_ano_mes (ano, mes),
    KEY idx_comida_mesa_competencias_status (status),
    KEY idx_comida_mesa_competencias_criado_por (criado_por),
    CONSTRAINT fk_comida_mesa_competencias_criado_por
        FOREIGN KEY (criado_por) REFERENCES usuarios (id)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS comida_mesa_entregas (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    inscricao_id BIGINT UNSIGNED NOT NULL,
    competencia_id BIGINT UNSIGNED NOT NULL,
    polo_id BIGINT UNSIGNED NOT NULL,
    recebedor_nome VARCHAR(150) NOT NULL,
    recebedor_cpf CHAR(11) NULL,
    recebedor_parentesco VARCHAR(60) NULL,
    entregue_por BIGINT UNSIGNED NOT NULL,
    entregue_em DATETIME NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'entregue',
    observacao VARCHAR(255) NULL,
    cancelada_por BIGINT UNSIGNED NULL,
    cancelada_em DATETIME NULL,
    motivo_cancelamento VARCHAR(255) NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_comida_mesa_entrega_mensal (inscricao_id, competencia_id),
    KEY idx_comida_mesa_entregas_competencia (competencia_id),
    KEY idx_comida_mesa_entregas_polo (polo_id),
    KEY idx_comida_mesa_entregas_entregue_em (entregue_em),
    KEY idx_comida_mesa_entregas_status (status),
    KEY idx_comida_mesa_entregas_entregue_por (entregue_por),
    KEY idx_comida_mesa_entregas_cancelada_por (cancelada_por),
    CONSTRAINT fk_comida_mesa_entregas_inscricao
        FOREIGN KEY (inscricao_id) REFERENCES comida_mesa_inscricoes (id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_comida_mesa_entregas_competencia
        FOREIGN KEY (competencia_id) REFERENCES comida_mesa_competencias (id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_comida_mesa_entregas_polo
        FOREIGN KEY (polo_id) REFERENCES comida_mesa_polos (id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_comida_mesa_entregas_entregue_por
        FOREIGN KEY (entregue_por) REFERENCES usuarios (id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_comida_mesa_entregas_cancelada_por
        FOREIGN KEY (cancelada_por) REFERENCES usuarios (id)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS comida_mesa_documentos (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    inscricao_id BIGINT UNSIGNED NOT NULL,
    arquivo_id BIGINT UNSIGNED NOT NULL,
    tipo VARCHAR(60) NOT NULL,
    descricao VARCHAR(255) NULL,
    enviado_por BIGINT UNSIGNED NOT NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_comida_mesa_documentos_arquivo (arquivo_id),
    KEY idx_comida_mesa_documentos_inscricao (inscricao_id),
    KEY idx_comida_mesa_documentos_tipo (tipo),
    KEY idx_comida_mesa_documentos_enviado_por (enviado_por),
    CONSTRAINT fk_comida_mesa_documentos_inscricao
        FOREIGN KEY (inscricao_id) REFERENCES comida_mesa_inscricoes (id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_comida_mesa_documentos_arquivo
        FOREIGN KEY (arquivo_id) REFERENCES arquivos (id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_comida_mesa_documentos_enviado_por
        FOREIGN KEY (enviado_por) REFERENCES usuarios (id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS comida_mesa_historico (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    inscricao_id BIGINT UNSIGNED NOT NULL,
    usuario_id BIGINT UNSIGNED NULL,
    acao VARCHAR(100) NOT NULL,
    descricao VARCHAR(255) NULL,
    dados_anteriores JSON NULL,
    dados_novos JSON NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_comida_mesa_historico_inscricao (inscricao_id),
    KEY idx_comida_mesa_historico_usuario (usuario_id),
    KEY idx_comida_mesa_historico_acao (acao),
    KEY idx_comida_mesa_historico_criado_em (criado_em),
    CONSTRAINT fk_comida_mesa_historico_inscricao
        FOREIGN KEY (inscricao_id) REFERENCES comida_mesa_inscricoes (id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_comida_mesa_historico_usuario
        FOREIGN KEY (usuario_id) REFERENCES usuarios (id)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO permissoes (nome, slug, descricao, modulo, ativo)
VALUES
    ('Visualizar Comida na Mesa', 'comida_mesa.visualizar', 'Permite visualizar o módulo Comida na Mesa.', 'comida_mesa', 1),
    ('Consultar CPF no Comida na Mesa', 'comida_mesa.consultar_cpf', 'Permite consultar CPF para operações do programa.', 'comida_mesa', 1),
    ('Cadastrar no Comida na Mesa', 'comida_mesa.cadastrar', 'Permite cadastrar famílias e inscrições do programa.', 'comida_mesa', 1),
    ('Editar Comida na Mesa', 'comida_mesa.editar', 'Permite editar cadastros e inscrições do programa.', 'comida_mesa', 1),
    ('Registrar entrega do Comida na Mesa', 'comida_mesa.entregar', 'Permite registrar entregas do programa.', 'comida_mesa', 1),
    ('Cancelar entrega do Comida na Mesa', 'comida_mesa.cancelar_entrega', 'Permite cancelar entregas do programa.', 'comida_mesa', 1),
    ('Visualizar documentos do Comida na Mesa', 'comida_mesa.documentos_visualizar', 'Permite visualizar documentos vinculados ao programa.', 'comida_mesa', 1),
    ('Enviar documentos do Comida na Mesa', 'comida_mesa.documentos_enviar', 'Permite enviar documentos vinculados ao programa.', 'comida_mesa', 1),
    ('Visualizar histórico do Comida na Mesa', 'comida_mesa.historico_visualizar', 'Permite visualizar histórico do programa.', 'comida_mesa', 1),
    ('Gerenciar competências do Comida na Mesa', 'comida_mesa.competencias_gerenciar', 'Permite gerenciar competências do programa.', 'comida_mesa', 1),
    ('Gerenciar polos do Comida na Mesa', 'comida_mesa.polos_gerenciar', 'Permite gerenciar polos do programa.', 'comida_mesa', 1)
ON DUPLICATE KEY UPDATE
    nome = VALUES(nome),
    descricao = VALUES(descricao),
    modulo = VALUES(modulo),
    ativo = VALUES(ativo);

INSERT IGNORE INTO nivel_permissoes (nivel_id, permissao_id)
SELECT n.id, p.id
FROM niveis_acesso n
INNER JOIN permissoes p ON p.slug IN (
    'comida_mesa.visualizar',
    'comida_mesa.consultar_cpf',
    'comida_mesa.cadastrar',
    'comida_mesa.editar',
    'comida_mesa.entregar',
    'comida_mesa.cancelar_entrega',
    'comida_mesa.documentos_visualizar',
    'comida_mesa.documentos_enviar',
    'comida_mesa.historico_visualizar',
    'comida_mesa.competencias_gerenciar',
    'comida_mesa.polos_gerenciar'
)
WHERE n.slug IN ('administrador', 'gestor');

INSERT IGNORE INTO nivel_permissoes (nivel_id, permissao_id)
SELECT n.id, p.id
FROM niveis_acesso n
INNER JOIN permissoes p ON p.slug IN (
    'comida_mesa.visualizar',
    'comida_mesa.consultar_cpf',
    'comida_mesa.cadastrar',
    'comida_mesa.editar',
    'comida_mesa.entregar',
    'comida_mesa.documentos_visualizar',
    'comida_mesa.documentos_enviar',
    'comida_mesa.historico_visualizar'
)
WHERE n.slug = 'tecnico';

INSERT IGNORE INTO nivel_permissoes (nivel_id, permissao_id)
SELECT n.id, p.id
FROM niveis_acesso n
INNER JOIN permissoes p ON p.slug IN (
    'comida_mesa.visualizar',
    'comida_mesa.consultar_cpf',
    'comida_mesa.cadastrar',
    'comida_mesa.entregar',
    'comida_mesa.documentos_visualizar',
    'comida_mesa.documentos_enviar',
    'comida_mesa.historico_visualizar'
)
WHERE n.slug = 'atendente';

INSERT IGNORE INTO nivel_permissoes (nivel_id, permissao_id)
SELECT n.id, p.id
FROM niveis_acesso n
INNER JOIN permissoes p ON p.slug IN (
    'comida_mesa.visualizar',
    'comida_mesa.documentos_visualizar',
    'comida_mesa.historico_visualizar'
)
WHERE n.slug = 'leitura';

COMMIT;
