-- =========================================================
-- SIGO - Sistema Integrado de Gestão de Ofícios
-- Banco inicial - MySQL 8+ / MariaDB compatível
-- =========================================================

CREATE DATABASE IF NOT EXISTS sigo
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE sigo;

SET NAMES utf8mb4;
SET time_zone = '-04:00';

-- =========================================================
-- 1. SETORES
-- =========================================================
CREATE TABLE IF NOT EXISTS setores (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nome VARCHAR(120) NOT NULL,
    sigla VARCHAR(30) DEFAULT NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uk_setores_nome (nome),
    KEY idx_setores_ativo (ativo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================
-- 2. PESSOAS
-- Pessoas que podem receber/ficar responsáveis por ofícios
-- PIN deve ser salvo com password_hash() no PHP.
-- =========================================================
CREATE TABLE IF NOT EXISTS pessoas (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    setor_id INT UNSIGNED DEFAULT NULL,
    nome VARCHAR(150) NOT NULL,
    cargo VARCHAR(120) DEFAULT NULL,
    email VARCHAR(190) DEFAULT NULL,
    telefone VARCHAR(30) DEFAULT NULL,
    pin_hash VARCHAR(255) DEFAULT NULL,
    pode_receber TINYINT(1) NOT NULL DEFAULT 1,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    KEY idx_pessoas_nome (nome),
    KEY idx_pessoas_setor (setor_id),
    KEY idx_pessoas_ativo (ativo),
    CONSTRAINT fk_pessoas_setor
        FOREIGN KEY (setor_id) REFERENCES setores(id)
        ON UPDATE CASCADE
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================
-- 3. USUÁRIOS DO SISTEMA
-- Senha deve ser salva com password_hash() no PHP.
-- =========================================================
CREATE TABLE IF NOT EXISTS usuarios (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    pessoa_id INT UNSIGNED DEFAULT NULL,
    nome VARCHAR(150) NOT NULL,
    email VARCHAR(190) NOT NULL,
    senha_hash VARCHAR(255) NOT NULL,
    nivel ENUM('ADMIN','ACOMPANHAMENTO','SUPORTE') NOT NULL DEFAULT 'ACOMPANHAMENTO',
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    ultimo_login DATETIME DEFAULT NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uk_usuarios_email (email),
    UNIQUE KEY uk_usuarios_pessoa (pessoa_id),
    KEY idx_usuarios_nivel (nivel),
    KEY idx_usuarios_ativo (ativo),
    CONSTRAINT fk_usuarios_pessoa
        FOREIGN KEY (pessoa_id) REFERENCES pessoas(id)
        ON UPDATE CASCADE
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================
-- 4. ÓRGÃOS / SECRETARIAS DE ORIGEM
-- =========================================================
CREATE TABLE IF NOT EXISTS orgaos (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    sigla VARCHAR(30) DEFAULT NULL,
    nome VARCHAR(180) NOT NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uk_orgaos_nome (nome),
    KEY idx_orgaos_sigla (sigla),
    KEY idx_orgaos_ativo (ativo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================
-- 5. LOCAIS FÍSICOS
-- Onde o documento físico está guardado.
-- =========================================================
CREATE TABLE IF NOT EXISTS locais (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nome VARCHAR(120) NOT NULL,
    descricao VARCHAR(255) DEFAULT NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uk_locais_nome (nome),
    KEY idx_locais_ativo (ativo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================
-- 6. OFÍCIOS
-- Registro atual do documento.
-- O histórico completo fica em movimentacoes.
-- =========================================================
CREATE TABLE IF NOT EXISTS oficios (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

    protocolo VARCHAR(50) NOT NULL,
    numero_oficio VARCHAR(60) NOT NULL,
    orgao_origem_id INT UNSIGNED DEFAULT NULL,

    assunto VARCHAR(500) NOT NULL,
    data_oficio DATE DEFAULT NULL,
    recebido_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    quantidade_folhas SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    recebido_protocolo_por VARCHAR(150) DEFAULT NULL,

    responsavel_atual_id INT UNSIGNED DEFAULT NULL,
    local_atual_id INT UNSIGNED DEFAULT NULL,

    status ENUM(
        'AGUARDANDO_ENCAMINHAMENTO',
        'AGUARDANDO_RECEBIMENTO',
        'RECEBIDO',
        'EM_ANDAMENTO',
        'DEVOLVIDO',
        'CONCLUIDO',
        'ARQUIVADO'
    ) NOT NULL DEFAULT 'AGUARDANDO_ENCAMINHAMENTO',

    observacoes TEXT DEFAULT NULL,
    cadastrado_por_usuario_id INT UNSIGNED DEFAULT NULL,

    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uk_oficios_protocolo (protocolo),

    KEY idx_oficios_numero (numero_oficio),
    KEY idx_oficios_orgao (orgao_origem_id),
    KEY idx_oficios_status (status),
    KEY idx_oficios_responsavel (responsavel_atual_id),
    KEY idx_oficios_local (local_atual_id),
    KEY idx_oficios_recebido_em (recebido_em),
    KEY idx_oficios_cadastrado_por (cadastrado_por_usuario_id),

    CONSTRAINT fk_oficios_orgao
        FOREIGN KEY (orgao_origem_id) REFERENCES orgaos(id)
        ON UPDATE CASCADE
        ON DELETE SET NULL,

    CONSTRAINT fk_oficios_responsavel
        FOREIGN KEY (responsavel_atual_id) REFERENCES pessoas(id)
        ON UPDATE CASCADE
        ON DELETE SET NULL,

    CONSTRAINT fk_oficios_local
        FOREIGN KEY (local_atual_id) REFERENCES locais(id)
        ON UPDATE CASCADE
        ON DELETE SET NULL,

    CONSTRAINT fk_oficios_cadastrado_por
        FOREIGN KEY (cadastrado_por_usuario_id) REFERENCES usuarios(id)
        ON UPDATE CASCADE
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================
-- 7. MOVIMENTAÇÕES / HISTÓRICO
-- Esta tabela nunca deve ser "reescrita".
-- Cada mudança importante gera uma nova linha.
-- =========================================================
CREATE TABLE IF NOT EXISTS movimentacoes (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    oficio_id BIGINT UNSIGNED NOT NULL,

    tipo ENUM(
        'CADASTRO',
        'ENCAMINHAMENTO',
        'CONFIRMACAO_RECEBIMENTO',
        'MUDANCA_LOCAL',
        'MUDANCA_STATUS',
        'DEVOLUCAO',
        'CONCLUSAO',
        'ARQUIVAMENTO',
        'OBSERVACAO'
    ) NOT NULL,

    usuario_id INT UNSIGNED DEFAULT NULL,

    pessoa_origem_id INT UNSIGNED DEFAULT NULL,
    pessoa_destino_id INT UNSIGNED DEFAULT NULL,

    local_origem_id INT UNSIGNED DEFAULT NULL,
    local_destino_id INT UNSIGNED DEFAULT NULL,

    status_anterior VARCHAR(40) DEFAULT NULL,
    status_novo VARCHAR(40) DEFAULT NULL,

    observacao TEXT DEFAULT NULL,

    ip VARCHAR(45) DEFAULT NULL,
    user_agent VARCHAR(255) DEFAULT NULL,

    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    KEY idx_mov_oficio (oficio_id),
    KEY idx_mov_tipo (tipo),
    KEY idx_mov_usuario (usuario_id),
    KEY idx_mov_pessoa_origem (pessoa_origem_id),
    KEY idx_mov_pessoa_destino (pessoa_destino_id),
    KEY idx_mov_criado_em (criado_em),

    CONSTRAINT fk_mov_oficio
        FOREIGN KEY (oficio_id) REFERENCES oficios(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,

    CONSTRAINT fk_mov_usuario
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
        ON UPDATE CASCADE
        ON DELETE SET NULL,

    CONSTRAINT fk_mov_pessoa_origem
        FOREIGN KEY (pessoa_origem_id) REFERENCES pessoas(id)
        ON UPDATE CASCADE
        ON DELETE SET NULL,

    CONSTRAINT fk_mov_pessoa_destino
        FOREIGN KEY (pessoa_destino_id) REFERENCES pessoas(id)
        ON UPDATE CASCADE
        ON DELETE SET NULL,

    CONSTRAINT fk_mov_local_origem
        FOREIGN KEY (local_origem_id) REFERENCES locais(id)
        ON UPDATE CASCADE
        ON DELETE SET NULL,

    CONSTRAINT fk_mov_local_destino
        FOREIGN KEY (local_destino_id) REFERENCES locais(id)
        ON UPDATE CASCADE
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================
-- 8. RECEBIMENTOS
-- Confirmação feita pela pessoa no Recebimento Rápido.
-- A movimentação de ENCAMINHAMENTO é única por confirmação.
-- =========================================================
CREATE TABLE IF NOT EXISTS recebimentos (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    oficio_id BIGINT UNSIGNED NOT NULL,
    pessoa_id INT UNSIGNED NOT NULL,
    movimentacao_encaminhamento_id BIGINT UNSIGNED NOT NULL,
    movimentacao_confirmacao_id BIGINT UNSIGNED DEFAULT NULL,

    pin_validado TINYINT(1) NOT NULL DEFAULT 1,
    ip VARCHAR(45) DEFAULT NULL,
    user_agent VARCHAR(255) DEFAULT NULL,
    confirmado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uk_recebimento_encaminhamento (movimentacao_encaminhamento_id),
    KEY idx_recebimentos_oficio (oficio_id),
    KEY idx_recebimentos_pessoa (pessoa_id),
    KEY idx_recebimentos_confirmado_em (confirmado_em),

    CONSTRAINT fk_recebimentos_oficio
        FOREIGN KEY (oficio_id) REFERENCES oficios(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,

    CONSTRAINT fk_recebimentos_pessoa
        FOREIGN KEY (pessoa_id) REFERENCES pessoas(id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    CONSTRAINT fk_recebimentos_mov_encaminhamento
        FOREIGN KEY (movimentacao_encaminhamento_id) REFERENCES movimentacoes(id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    CONSTRAINT fk_recebimentos_mov_confirmacao
        FOREIGN KEY (movimentacao_confirmacao_id) REFERENCES movimentacoes(id)
        ON UPDATE CASCADE
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================
-- 9. ANEXOS
-- PDFs e imagens digitalizadas.
-- =========================================================
CREATE TABLE IF NOT EXISTS anexos (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    oficio_id BIGINT UNSIGNED NOT NULL,
    usuario_id INT UNSIGNED DEFAULT NULL,

    nome_original VARCHAR(255) NOT NULL,
    nome_arquivo VARCHAR(255) NOT NULL,
    caminho VARCHAR(500) NOT NULL,
    mime_type VARCHAR(120) DEFAULT NULL,
    tamanho_bytes BIGINT UNSIGNED DEFAULT NULL,

    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    KEY idx_anexos_oficio (oficio_id),
    KEY idx_anexos_usuario (usuario_id),

    CONSTRAINT fk_anexos_oficio
        FOREIGN KEY (oficio_id) REFERENCES oficios(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,

    CONSTRAINT fk_anexos_usuario
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
        ON UPDATE CASCADE
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================
-- DADOS INICIAIS
-- =========================================================

INSERT INTO setores (nome, sigla)
VALUES
    ('Administração', 'ADM'),
    ('Gabinete', 'GAB'),
    ('Assessoria', 'ASS'),
    ('Arquivo', 'ARQ')
ON DUPLICATE KEY UPDATE nome = VALUES(nome);

INSERT INTO locais (nome, descricao)
VALUES
    ('Administração', 'Mesa/setor responsável pelo controle inicial dos ofícios'),
    ('Gabinete', 'Gabinete da Casa Civil'),
    ('Assessoria', 'Setor de assessoria'),
    ('Arquivo Central', 'Local de arquivamento físico definitivo')
ON DUPLICATE KEY UPDATE descricao = VALUES(descricao);

INSERT INTO orgaos (sigla, nome)
VALUES
    ('SEMAS', 'Secretaria Municipal de Assistência Social'),
    ('SEMED', 'Secretaria Municipal de Educação'),
    ('SEINFRA', 'Secretaria Municipal de Infraestrutura'),
    ('SEFAZ', 'Secretaria Municipal de Fazenda')
ON DUPLICATE KEY UPDATE sigla = VALUES(sigla);

-- =========================================================
-- VIEW ÚTIL PARA A TELA DE RECEBIMENTO RÁPIDO
-- =========================================================
CREATE OR REPLACE VIEW vw_oficios_aguardando_recebimento AS
SELECT
    o.id,
    o.protocolo,
    o.numero_oficio,
    o.assunto,
    o.recebido_em,
    p.id AS pessoa_id,
    p.nome AS pessoa_nome,
    org.sigla AS orgao_sigla,
    org.nome AS orgao_nome,
    l.nome AS local_atual
FROM oficios o
LEFT JOIN pessoas p
    ON p.id = o.responsavel_atual_id
LEFT JOIN orgaos org
    ON org.id = o.orgao_origem_id
LEFT JOIN locais l
    ON l.id = o.local_atual_id
WHERE o.status = 'AGUARDANDO_RECEBIMENTO';

-- =========================================================
-- VIEW ÚTIL PARA DASHBOARD / LISTAGEM
-- =========================================================
CREATE OR REPLACE VIEW vw_oficios_completos AS
SELECT
    o.id,
    o.protocolo,
    o.numero_oficio,
    o.assunto,
    o.data_oficio,
    o.recebido_em,
    o.quantidade_folhas,
    o.status,
    o.observacoes,

    org.id AS orgao_id,
    org.sigla AS orgao_sigla,
    org.nome AS orgao_nome,

    p.id AS responsavel_id,
    p.nome AS responsavel_nome,

    l.id AS local_id,
    l.nome AS local_nome,

    o.criado_em,
    o.atualizado_em
FROM oficios o
LEFT JOIN orgaos org
    ON org.id = o.orgao_origem_id
LEFT JOIN pessoas p
    ON p.id = o.responsavel_atual_id
LEFT JOIN locais l
    ON l.id = o.local_atual_id;
