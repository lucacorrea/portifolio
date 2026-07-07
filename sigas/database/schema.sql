SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS setores (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    nome VARCHAR(150) NOT NULL,
    slug VARCHAR(80) NOT NULL,
    descricao VARCHAR(255) NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    excluido_em DATETIME NULL DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_setores_slug (slug),
    KEY idx_setores_ativo (ativo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS niveis_acesso (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    nome VARCHAR(100) NOT NULL,
    slug VARCHAR(80) NOT NULL,
    descricao VARCHAR(255) NULL,
    prioridade SMALLINT UNSIGNED NOT NULL DEFAULT 100,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_niveis_acesso_slug (slug),
    KEY idx_niveis_acesso_ativo (ativo),
    KEY idx_niveis_acesso_prioridade (prioridade)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS permissoes (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    nome VARCHAR(120) NOT NULL,
    slug VARCHAR(120) NOT NULL,
    descricao VARCHAR(255) NULL,
    modulo VARCHAR(80) NOT NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_permissoes_slug (slug),
    KEY idx_permissoes_modulo (modulo),
    KEY idx_permissoes_ativo (ativo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS nivel_permissoes (
    nivel_id BIGINT UNSIGNED NOT NULL,
    permissao_id BIGINT UNSIGNED NOT NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (nivel_id, permissao_id),
    CONSTRAINT fk_nivel_permissoes_nivel
        FOREIGN KEY (nivel_id) REFERENCES niveis_acesso (id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_nivel_permissoes_permissao
        FOREIGN KEY (permissao_id) REFERENCES permissoes (id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS usuarios (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    setor_id BIGINT UNSIGNED NULL,
    setor_solicitado_id BIGINT UNSIGNED NULL,
    nivel_id BIGINT UNSIGNED NULL,
    nome VARCHAR(150) NOT NULL,
    cpf CHAR(11) NOT NULL,
    matricula VARCHAR(60) NULL,
    cargo VARCHAR(120) NULL,
    email VARCHAR(180) NOT NULL,
    telefone VARCHAR(30) NULL,
    senha_hash VARCHAR(255) NOT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'pendente',
    precisa_trocar_senha TINYINT(1) NOT NULL DEFAULT 0,
    tentativas_login SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    bloqueado_ate DATETIME NULL,
    ultimo_login_em DATETIME NULL,
    ultimo_login_ip VARCHAR(45) NULL,
    aprovado_por BIGINT UNSIGNED NULL,
    aprovado_em DATETIME NULL,
    rejeitado_por BIGINT UNSIGNED NULL,
    rejeitado_em DATETIME NULL,
    motivo_rejeicao VARCHAR(255) NULL,
    observacao_interna TEXT NULL,
    versao_autorizacao INT UNSIGNED NOT NULL DEFAULT 1,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    excluido_em DATETIME NULL DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_usuarios_cpf (cpf),
    UNIQUE KEY uk_usuarios_email (email),
    UNIQUE KEY uk_usuarios_matricula (matricula),
    KEY idx_usuarios_setor (setor_id),
    KEY idx_usuarios_setor_solicitado (setor_solicitado_id),
    KEY idx_usuarios_nivel (nivel_id),
    KEY idx_usuarios_status (status),
    KEY idx_usuarios_aprovado_por (aprovado_por),
    KEY idx_usuarios_rejeitado_por (rejeitado_por),
    CONSTRAINT fk_usuarios_setor
        FOREIGN KEY (setor_id) REFERENCES setores (id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_usuarios_setor_solicitado
        FOREIGN KEY (setor_solicitado_id) REFERENCES setores (id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_usuarios_nivel
        FOREIGN KEY (nivel_id) REFERENCES niveis_acesso (id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_usuarios_aprovado_por
        FOREIGN KEY (aprovado_por) REFERENCES usuarios (id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_usuarios_rejeitado_por
        FOREIGN KEY (rejeitado_por) REFERENCES usuarios (id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS auditoria (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    usuario_id BIGINT UNSIGNED NULL,
    usuario_alvo_id BIGINT UNSIGNED NULL,
    acao VARCHAR(120) NOT NULL,
    modulo VARCHAR(80) NOT NULL,
    descricao VARCHAR(255) NULL,
    dados_anteriores JSON NULL,
    dados_novos JSON NULL,
    ip VARCHAR(45) NULL,
    user_agent VARCHAR(255) NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_auditoria_usuario (usuario_id),
    KEY idx_auditoria_usuario_alvo (usuario_alvo_id),
    KEY idx_auditoria_acao (acao),
    KEY idx_auditoria_modulo (modulo),
    KEY idx_auditoria_criado_em (criado_em),
    CONSTRAINT fk_auditoria_usuario
        FOREIGN KEY (usuario_id) REFERENCES usuarios (id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_auditoria_usuario_alvo
        FOREIGN KEY (usuario_alvo_id) REFERENCES usuarios (id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sessoes_usuarios (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    usuario_id BIGINT UNSIGNED NOT NULL,
    identificador VARCHAR(128) NOT NULL,
    ip VARCHAR(45) NULL,
    user_agent VARCHAR(255) NULL,
    ultimo_acesso_em DATETIME NOT NULL,
    expira_em DATETIME NOT NULL,
    revogada_em DATETIME NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_sessoes_usuarios_identificador (identificador),
    KEY idx_sessoes_usuarios_usuario (usuario_id),
    KEY idx_sessoes_usuarios_expira (expira_em),
    KEY idx_sessoes_usuarios_revogada (revogada_em),
    CONSTRAINT fk_sessoes_usuarios_usuario
        FOREIGN KEY (usuario_id) REFERENCES usuarios (id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS arquivos (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    usuario_id BIGINT UNSIGNED NULL,
    setor_id BIGINT UNSIGNED NULL,
    tipo VARCHAR(40) NOT NULL,
    finalidade VARCHAR(120) NOT NULL,
    nome_original VARCHAR(255) NOT NULL,
    nome_armazenado VARCHAR(120) NOT NULL,
    caminho_relativo VARCHAR(255) NOT NULL,
    mime_type VARCHAR(120) NOT NULL,
    extensao VARCHAR(12) NOT NULL,
    tamanho BIGINT UNSIGNED NOT NULL,
    hash_arquivo CHAR(64) NOT NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_arquivos_usuario (usuario_id),
    KEY idx_arquivos_setor (setor_id),
    KEY idx_arquivos_tipo (tipo),
    KEY idx_arquivos_finalidade (finalidade),
    KEY idx_arquivos_ativo (ativo),
    KEY idx_arquivos_hash (hash_arquivo),
    CONSTRAINT fk_arquivos_usuario
        FOREIGN KEY (usuario_id) REFERENCES usuarios (id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_arquivos_setor
        FOREIGN KEY (setor_id) REFERENCES setores (id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
