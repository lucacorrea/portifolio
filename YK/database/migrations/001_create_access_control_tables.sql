-- Migration inicial de controle de acesso.
-- Compatibilidade: MySQL 5.7+ e MariaDB 10.4+.
-- Seguro para reexecucao: usa CREATE TABLE IF NOT EXISTS e nao remove dados.

SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS perfis (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    descricao VARCHAR(255) NULL,
    protegido TINYINT(1) NOT NULL DEFAULT 0,
    status ENUM('ativo', 'inativo') NOT NULL DEFAULT 'ativo',
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uk_perfis_nome (nome),
    KEY idx_perfis_status (status)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS usuarios (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    perfil_id INT UNSIGNED NOT NULL,
    nome VARCHAR(150) NOT NULL,
    usuario VARCHAR(80) NOT NULL,
    email VARCHAR(150) NOT NULL,
    senha_hash VARCHAR(255) NOT NULL,
    telefone VARCHAR(30) NULL,

    status ENUM('ativo', 'inativo', 'bloqueado')
        NOT NULL DEFAULT 'ativo',

    deve_alterar_senha TINYINT(1) NOT NULL DEFAULT 0,
    tentativas_falhas INT UNSIGNED NOT NULL DEFAULT 0,
    bloqueado_ate DATETIME NULL,
    ultimo_acesso DATETIME NULL,
    senha_alterada_em DATETIME NULL,

    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uk_usuarios_usuario (usuario),
    UNIQUE KEY uk_usuarios_email (email),
    KEY idx_usuarios_perfil (perfil_id),
    KEY idx_usuarios_status (status),
    KEY idx_usuarios_bloqueado_ate (bloqueado_ate),

    CONSTRAINT fk_usuarios_perfil
        FOREIGN KEY (perfil_id)
        REFERENCES perfis(id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS permissoes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    grupo VARCHAR(100) NOT NULL,
    modulo VARCHAR(100) NOT NULL,
    codigo VARCHAR(150) NOT NULL,
    nome VARCHAR(150) NOT NULL,
    descricao VARCHAR(255) NULL,
    ordem INT UNSIGNED NOT NULL DEFAULT 0,
    status ENUM('ativo', 'inativo') NOT NULL DEFAULT 'ativo',
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY uk_permissoes_codigo (codigo),
    KEY idx_permissoes_grupo (grupo),
    KEY idx_permissoes_modulo (modulo),
    KEY idx_permissoes_status (status),
    KEY idx_permissoes_ordem (ordem)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS perfil_permissoes (
    perfil_id INT UNSIGNED NOT NULL,
    permissao_id INT UNSIGNED NOT NULL,
    concedido_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (perfil_id, permissao_id),

    KEY idx_perfil_permissoes_permissao (permissao_id),

    CONSTRAINT fk_perfil_permissoes_perfil
        FOREIGN KEY (perfil_id)
        REFERENCES perfis(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,

    CONSTRAINT fk_perfil_permissoes_permissao
        FOREIGN KEY (permissao_id)
        REFERENCES permissoes(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;
