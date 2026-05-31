SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS login_auditoria (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    usuario_id BIGINT UNSIGNED NULL,
    email VARCHAR(180) NULL,
    ip VARCHAR(45) NULL,
    user_agent VARCHAR(255) NULL,
    sucesso TINYINT(1) NOT NULL DEFAULT 0,
    motivo VARCHAR(180) NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_login_usuario (usuario_id),
    KEY idx_login_email (email),
    KEY idx_login_criado (criado_em),
    CONSTRAINT fk_login_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


