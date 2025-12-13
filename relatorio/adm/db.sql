;enE|fxB^F3


CREATE TABLE IF NOT EXISTS usuarios (
  id               BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nome             VARCHAR(150) NOT NULL,
  email            VARCHAR(190) NOT NULL,
  senha_hash       VARCHAR(255) NOT NULL,      -- password_hash() no PHP (bcrypt/argon2)
  ativo            TINYINT(1) NOT NULL DEFAULT 1,

  ultimo_login_em  DATETIME NULL,
  criado_em        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em    DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,

  UNIQUE KEY uq_usuarios_email (email),
  KEY idx_usuarios_ativo (ativo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================
-- 2) PERFIS (separar admin de quem só preenche)
-- =========================================
CREATE TABLE IF NOT EXISTS perfis (
  id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  codigo     VARCHAR(50) NOT NULL,   -- ADMIN / OPERADOR
  nome       VARCHAR(100) NOT NULL,
  descricao  VARCHAR(255) NULL,
  UNIQUE KEY uq_perfis_codigo (codigo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS usuario_perfis (
  usuario_id BIGINT UNSIGNED NOT NULL,
  perfil_id  BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (usuario_id, perfil_id),
  KEY idx_up_perfil (perfil_id),
  CONSTRAINT fk_up_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_up_perfil FOREIGN KEY (perfil_id) REFERENCES perfis(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =========================================
CREATE TABLE IF NOT EXISTS login_tentativas (
  id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email       VARCHAR(190) NOT NULL,
  ip          VARCHAR(45)  NULL,
  sucesso     TINYINT(1)   NOT NULL DEFAULT 0,
  criado_em   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_lt_email_data (email, criado_em),
  KEY idx_lt_ip_data (ip, criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

