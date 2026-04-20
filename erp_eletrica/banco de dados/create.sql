ALTER TABLE usuarios
    ADD COLUMN is_temp_admin TINYINT(1) NOT NULL DEFAULT 0 AFTER ativo,
    ADD COLUMN temp_admin_expires_at DATETIME NULL AFTER is_temp_admin,
    ADD COLUMN temp_admin_created_by INT NULL AFTER temp_admin_expires_at,
    ADD COLUMN passkey_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER auth_type,
    ADD COLUMN passkey_user_handle CHAR(32) DEFAULT NULL AFTER passkey_enabled;

CREATE TABLE IF NOT EXISTS usuario_passkeys (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    credential_id_b64 VARCHAR(255) NOT NULL UNIQUE,
    public_key_b64 LONGTEXT NOT NULL,
    sign_count BIGINT UNSIGNED NOT NULL DEFAULT 0,
    aaguid VARCHAR(80) DEFAULT NULL,
    fmt VARCHAR(50) DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_used_at DATETIME DEFAULT NULL,
    CONSTRAINT fk_usuario_passkeys_usuario
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;