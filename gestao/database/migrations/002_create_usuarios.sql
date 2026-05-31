SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS usuarios (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    empresa_id BIGINT UNSIGNED NOT NULL,
    nome VARCHAR(140) NOT NULL,
    email VARCHAR(180) NOT NULL,
    senha_hash VARCHAR(255) NOT NULL,
    telefone VARCHAR(30) NULL,
    nivel ENUM('admin','gerente','operador','estoquista','leitor') NOT NULL DEFAULT 'operador',
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    ultimo_login_em DATETIME NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_usuarios_email (email),
    KEY idx_usuarios_empresa (empresa_id),
    CONSTRAINT fk_usuarios_empresa FOREIGN KEY (empresa_id) REFERENCES empresas(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


