-- Igreja Tefe Financeiro - core schema
-- MySQL 8+
--
-- This migration is intentionally non-destructive. It creates tables when
-- missing and preserves historical financial data by avoiding cascading deletes.

SET NAMES utf8mb4;
SET time_zone = '+00:00';

CREATE TABLE IF NOT EXISTS igrejas (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    nome VARCHAR(180) NOT NULL,
    cnpj VARCHAR(20) NULL,
    email VARCHAR(180) NULL,
    telefone VARCHAR(30) NULL,
    status ENUM('ativa', 'inativa') NOT NULL DEFAULT 'ativa',
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uk_igrejas_cnpj (cnpj),
    KEY idx_igrejas_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS usuarios (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    igreja_id BIGINT UNSIGNED NOT NULL,
    nome VARCHAR(180) NOT NULL,
    email VARCHAR(180) NOT NULL,
    senha_hash VARCHAR(255) NOT NULL,
    papel ENUM('admin', 'tesoureiro', 'visualizador') NOT NULL DEFAULT 'admin',
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    ultimo_login_em TIMESTAMP NULL DEFAULT NULL,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uk_usuarios_igreja_email (igreja_id, email),
    UNIQUE KEY uk_usuarios_id_igreja (id, igreja_id),
    KEY idx_usuarios_igreja (igreja_id),
    KEY idx_usuarios_ativo (ativo),
    KEY idx_usuarios_papel (papel),

    CONSTRAINT fk_usuarios_igreja
        FOREIGN KEY (igreja_id) REFERENCES igrejas (id)
        ON UPDATE RESTRICT
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS categorias (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    igreja_id BIGINT UNSIGNED NOT NULL,
    nome VARCHAR(120) NOT NULL,
    descricao TEXT NULL,
    cor CHAR(7) NOT NULL DEFAULT '#155EEF',
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uk_categorias_igreja_nome (igreja_id, nome),
    UNIQUE KEY uk_categorias_id_igreja (id, igreja_id),
    KEY idx_categorias_igreja_ativo (igreja_id, ativo),

    CONSTRAINT fk_categorias_igreja
        FOREIGN KEY (igreja_id) REFERENCES igrejas (id)
        ON UPDATE RESTRICT
        ON DELETE RESTRICT,
    CONSTRAINT chk_categorias_cor_hex
        CHECK (cor REGEXP '^#[0-9A-Fa-f]{6}$')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS entradas (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    igreja_id BIGINT UNSIGNED NOT NULL,
    usuario_id BIGINT UNSIGNED NOT NULL,
    tipo ENUM('dizimo', 'oferta') NOT NULL,
    valor DECIMAL(12,2) NOT NULL,
    descricao TEXT NULL,
    contribuinte_nome VARCHAR(180) NULL,
    forma_pagamento ENUM('dinheiro', 'pix', 'cartao', 'transferencia', 'outro') NULL,
    data_entrada DATE NOT NULL,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    KEY idx_entradas_igreja_data (igreja_id, data_entrada),
    KEY idx_entradas_igreja_tipo (igreja_id, tipo),
    KEY idx_entradas_usuario (usuario_id),
    KEY idx_entradas_usuario_igreja (usuario_id, igreja_id),

    CONSTRAINT fk_entradas_igreja
        FOREIGN KEY (igreja_id) REFERENCES igrejas (id)
        ON UPDATE RESTRICT
        ON DELETE RESTRICT,
    CONSTRAINT fk_entradas_usuario_igreja
        FOREIGN KEY (usuario_id, igreja_id) REFERENCES usuarios (id, igreja_id)
        ON UPDATE RESTRICT
        ON DELETE RESTRICT,
    CONSTRAINT chk_entradas_valor_positivo
        CHECK (valor > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS saidas (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    igreja_id BIGINT UNSIGNED NOT NULL,
    usuario_id BIGINT UNSIGNED NOT NULL,
    categoria_id BIGINT UNSIGNED NOT NULL,
    valor DECIMAL(12,2) NOT NULL,
    descricao TEXT NULL,
    fornecedor VARCHAR(180) NULL,
    forma_pagamento ENUM('dinheiro', 'pix', 'cartao', 'transferencia', 'boleto', 'outro') NULL,
    data_saida DATE NOT NULL,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    KEY idx_saidas_igreja_data (igreja_id, data_saida),
    KEY idx_saidas_igreja_categoria (igreja_id, categoria_id),
    KEY idx_saidas_usuario (usuario_id),
    KEY idx_saidas_usuario_igreja (usuario_id, igreja_id),
    KEY idx_saidas_categoria_igreja (categoria_id, igreja_id),

    CONSTRAINT fk_saidas_igreja
        FOREIGN KEY (igreja_id) REFERENCES igrejas (id)
        ON UPDATE RESTRICT
        ON DELETE RESTRICT,
    CONSTRAINT fk_saidas_usuario_igreja
        FOREIGN KEY (usuario_id, igreja_id) REFERENCES usuarios (id, igreja_id)
        ON UPDATE RESTRICT
        ON DELETE RESTRICT,
    CONSTRAINT fk_saidas_categoria_igreja
        FOREIGN KEY (categoria_id, igreja_id) REFERENCES categorias (id, igreja_id)
        ON UPDATE RESTRICT
        ON DELETE RESTRICT,
    CONSTRAINT chk_saidas_valor_positivo
        CHECK (valor > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS logs_auditoria (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    igreja_id BIGINT UNSIGNED NOT NULL,
    usuario_id BIGINT UNSIGNED NULL,
    acao VARCHAR(80) NOT NULL,
    tabela_afetada VARCHAR(80) NOT NULL,
    registro_id BIGINT UNSIGNED NULL,
    dados_anteriores JSON NULL,
    dados_novos JSON NULL,
    ip VARCHAR(45) NULL,
    user_agent VARCHAR(255) NULL,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    KEY idx_logs_igreja_data (igreja_id, criado_em),
    KEY idx_logs_usuario (usuario_id),
    KEY idx_logs_tabela_registro (tabela_afetada, registro_id),

    CONSTRAINT fk_logs_igreja
        FOREIGN KEY (igreja_id) REFERENCES igrejas (id)
        ON UPDATE RESTRICT
        ON DELETE RESTRICT,
    CONSTRAINT fk_logs_usuario
        FOREIGN KEY (usuario_id) REFERENCES usuarios (id)
        ON UPDATE RESTRICT
        ON DELETE SET NULL,
    CONSTRAINT chk_logs_dados_anteriores_json
        CHECK (dados_anteriores IS NULL OR JSON_VALID(dados_anteriores)),
    CONSTRAINT chk_logs_dados_novos_json
        CHECK (dados_novos IS NULL OR JSON_VALID(dados_novos))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tentativas_login (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    email VARCHAR(180) NOT NULL,
    ip VARCHAR(45) NOT NULL,
    sucesso TINYINT(1) NOT NULL DEFAULT 0,
    user_agent VARCHAR(255) NULL,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    KEY idx_tentativas_login_email_data (email, criado_em),
    KEY idx_tentativas_login_ip_data (ip, criado_em),
    KEY idx_tentativas_login_sucesso (sucesso)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
