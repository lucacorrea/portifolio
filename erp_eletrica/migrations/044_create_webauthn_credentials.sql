-- Migração 044: Create webauthn_credentials table
CREATE TABLE IF NOT EXISTS webauthn_credentials (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    credential_id VARBINARY(255) NOT NULL,
    public_key TEXT NOT NULL,
    user_handle VARBINARY(64) NOT NULL,
    signature_counter INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_used_at TIMESTAMP NULL,
    device_name VARCHAR(100),
    UNIQUE(credential_id),
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
);
