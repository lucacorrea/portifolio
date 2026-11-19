-- OBRAS360 · Instalação da estrutura e primeiro usuário de suporte
-- Selecione o banco u784961086_obras antes de importar este arquivo no phpMyAdmin.
-- NÃO exige CREATE DATABASE nem privilégios globais.
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE IF NOT EXISTS users (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    username VARCHAR(50) NOT NULL,
    email VARCHAR(190) NOT NULL,
    name VARCHAR(150) NOT NULL,
    role ENUM('suporte','administrador','gestor','operador','consulta') NOT NULL DEFAULT 'consulta',
    password_hash VARCHAR(255) NOT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    must_change_password TINYINT(1) NOT NULL DEFAULT 1,
    failed_attempts INT UNSIGNED NOT NULL DEFAULT 0,
    locked_until DATETIME NULL,
    password_changed_at DATETIME NULL,
    last_login_at DATETIME NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id), UNIQUE KEY uq_users_username (username), UNIQUE KEY uq_users_email (email), KEY idx_users_role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS login_limits (
    key_hash CHAR(64) NOT NULL PRIMARY KEY,
    attempts INT UNSIGNED NOT NULL DEFAULT 0,
    window_start DATETIME NOT NULL,
    blocked_until DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sequences (
    module VARCHAR(40) NOT NULL PRIMARY KEY,
    last_number BIGINT UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS records (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    module VARCHAR(40) NOT NULL,
    code VARCHAR(64) NOT NULL,
    payload LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
    created_by BIGINT UNSIGNED NULL,
    updated_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    PRIMARY KEY (id), UNIQUE KEY uq_module_code (module,code), KEY idx_records_active (module,deleted_at),
    KEY idx_records_created (created_at), CONSTRAINT ck_records_json CHECK (JSON_VALID(payload)),
    CONSTRAINT fk_records_created FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_records_updated FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS milestones (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    title VARCHAR(250) NOT NULL, work VARCHAR(250) NOT NULL, due_date DATE NOT NULL,
    status VARCHAR(40) NOT NULL DEFAULT 'Programado',
    created_by BIGINT UNSIGNED NULL, updated_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id), KEY idx_milestones_date (due_date),
    CONSTRAINT fk_milestones_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS stock_movements (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    material_code VARCHAR(64) NOT NULL, material_name VARCHAR(255) NOT NULL,
    type ENUM('Entrada','Saída') NOT NULL,
    quantity DECIMAL(16,3) NOT NULL,
    balance_before DECIMAL(16,3) NOT NULL,
    balance_after DECIMAL(16,3) NOT NULL,
    note VARCHAR(1000) NOT NULL DEFAULT '',
    created_by BIGINT UNSIGNED NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id), KEY idx_stock_material (material_code), KEY idx_stock_time (created_at),
    CONSTRAINT fk_stock_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS protocol_forwards (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    protocol_code VARCHAR(64) NOT NULL,
    previous_sector VARCHAR(160) NOT NULL DEFAULT '', new_sector VARCHAR(160) NOT NULL,
    dispatch TEXT NOT NULL,
    created_by BIGINT UNSIGNED NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id), KEY idx_protocol_forwards (protocol_code),
    CONSTRAINT fk_protocol_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS attachments (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    module VARCHAR(40) NOT NULL, record_code VARCHAR(64) NOT NULL,
    original_name VARCHAR(240) NOT NULL, stored_name VARCHAR(100) NOT NULL UNIQUE,
    mime VARCHAR(150) NOT NULL, size_bytes BIGINT UNSIGNED NOT NULL,
    uploaded_by BIGINT UNSIGNED NULL,
    uploaded_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, deleted_at DATETIME NULL,
    PRIMARY KEY (id), KEY idx_attachment_record (module,record_code),
    CONSTRAINT fk_attachment_user FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS audit_logs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    actor_id BIGINT UNSIGNED NULL,
    event VARCHAR(80) NOT NULL, module VARCHAR(45) NULL, record_code VARCHAR(64) NULL,
    ip_hash CHAR(64) NULL, metadata LONGTEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id), KEY idx_audit_time (created_at), KEY idx_audit_actor (actor_id),
    CONSTRAINT fk_audit_actor FOREIGN KEY (actor_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Inicialização idempotente da sequência, sem regredir identificadores já existentes.
INSERT IGNORE INTO sequences (module,last_number) VALUES ('obras',0);
INSERT IGNORE INTO sequences (module,last_number) VALUES ('ordens',0);
INSERT IGNORE INTO sequences (module,last_number) VALUES ('fiscalizacao',0);
INSERT IGNORE INTO sequences (module,last_number) VALUES ('projetos',0);
INSERT IGNORE INTO sequences (module,last_number) VALUES ('orcamentos',0);
INSERT IGNORE INTO sequences (module,last_number) VALUES ('materiais',0);
INSERT IGNORE INTO sequences (module,last_number) VALUES ('patrimonio',0);
INSERT IGNORE INTO sequences (module,last_number) VALUES ('protocolos',0);

-- Primeiro acesso: suporte com acesso integral. A senha está armazenada SOMENTE como hash Argon2id.
-- Não use INSERT REPLACE: isso preserva alterações feitas em instalações já existentes.
INSERT INTO users (username,email,name,role,password_hash,active,must_change_password)
SELECT 'suporte','suporte@obras360.local','Suporte OBRAS360','suporte','$argon2id$v=19$m=65536,t=3,p=2$Q0hFVElSTHFlNGp3cjVrMw$nU+VFAjy6KUNflUnUUQsqPxK/8s6lWNIbmNeQdFZqf8',1,1
WHERE NOT EXISTS (SELECT 1 FROM users WHERE username='suporte' OR email='suporte@obras360.local');
