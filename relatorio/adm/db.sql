;enE|fxB^F3

SET NAMES utf8mb4;

-- =========================
-- USUÁRIOS
-- =========================
CREATE TABLE IF NOT EXISTS usuarios (
  id               BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nome             VARCHAR(150) NOT NULL,
  email            VARCHAR(190) NOT NULL,
  senha_hash       VARCHAR(255) NOT NULL,
  ativo            TINYINT(1) NOT NULL DEFAULT 1,
  ultimo_login_em  DATETIME NULL,
  criado_em        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em    DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,

  UNIQUE KEY uq_usuarios_email (email),
  KEY idx_usuarios_ativo (ativo),
  KEY idx_usuarios_nome (nome)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================
-- PERFIS
-- =========================
CREATE TABLE IF NOT EXISTS perfis (
  id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  codigo     VARCHAR(50) NOT NULL,   -- ADMIN / OPERADOR
  nome       VARCHAR(100) NOT NULL,
  descricao  VARCHAR(255) NULL,

  UNIQUE KEY uq_perfis_codigo (codigo),
  KEY idx_perfis_nome (nome)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================
-- VÍNCULO USUÁRIO x PERFIL (sem FK)
-- =========================
CREATE TABLE IF NOT EXISTS usuario_perfis (
  id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  usuario_id BIGINT UNSIGNED NOT NULL,
  perfil_id  BIGINT UNSIGNED NOT NULL,
  criado_em  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

  UNIQUE KEY uq_usuario_perfil (usuario_id, perfil_id),
  KEY idx_up_usuario (usuario_id),
  KEY idx_up_perfil (perfil_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Perfis padrão
INSERT IGNORE INTO perfis (codigo, nome, descricao) VALUES
('ADMIN', 'Administrador', 'Acesso total ao sistema'),
('OPERADOR', 'Operador', 'Somente preencher dados');
