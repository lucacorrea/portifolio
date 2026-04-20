-- Tabelas extras para login admin com passkey e geração de usuário temporário
-- Compatível com a tabela usuarios já existente

CREATE TABLE IF NOT EXISTS user_passkeys (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    credential_id_b64 TEXT NOT NULL,
    credential_public_key_b64 LONGTEXT NOT NULL,
    user_handle_b64 VARCHAR(255) NOT NULL,
    sign_count BIGINT UNSIGNED NOT NULL DEFAULT 0,
    transports_json TEXT DEFAULT NULL,
    last_used_at DATETIME DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_user_passkeys_credential (credential_id_b64(255)),
    KEY idx_user_passkeys_usuario (usuario_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS usuarios_temporarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_usuario_id INT NOT NULL,
    filial_id INT DEFAULT NULL,
    nome_temporario VARCHAR(100) NOT NULL,
    nivel_temporario ENUM('vendedor','tecnico','gerente','admin','master') NOT NULL DEFAULT 'vendedor',
    codigo_acesso VARCHAR(30) NOT NULL,
    observacao VARCHAR(255) DEFAULT NULL,
    valido_ate DATETIME NOT NULL,
    usado_em DATETIME DEFAULT NULL,
    revogado_em DATETIME DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_usuarios_temporarios_codigo (codigo_acesso),
    KEY idx_usuarios_temporarios_admin (admin_usuario_id),
    KEY idx_usuarios_temporarios_validade (valido_ate)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Índices recomendados na tabela de usuários
ALTER TABLE usuarios ADD INDEX idx_usuarios_email (email);
ALTER TABLE usuarios ADD INDEX idx_usuarios_nivel_ativo (nivel, ativo);
