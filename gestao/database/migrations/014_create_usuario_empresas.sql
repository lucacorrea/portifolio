SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS usuario_empresas (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    usuario_id BIGINT UNSIGNED NOT NULL,
    empresa_id BIGINT UNSIGNED NOT NULL,
    nivel ENUM('admin','gerente','operador','estoquista','leitor') NOT NULL,
    principal TINYINT(1) NOT NULL DEFAULT 0,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_usuario_empresa (usuario_id, empresa_id),
    KEY idx_usuario_empresas_usuario (usuario_id, ativo),
    KEY idx_usuario_empresas_empresa (empresa_id, ativo),
    CONSTRAINT fk_usuario_empresas_usuario
        FOREIGN KEY (usuario_id)
        REFERENCES usuarios(id)
        ON DELETE RESTRICT,
    CONSTRAINT fk_usuario_empresas_empresa
        FOREIGN KEY (empresa_id)
        REFERENCES empresas(id)
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO usuario_empresas (
    usuario_id,
    empresa_id,
    nivel,
    principal,
    ativo
)
SELECT
    u.id,
    u.empresa_id,
    u.nivel,
    1,
    u.ativo
FROM usuarios u
INNER JOIN empresas e ON e.id = u.empresa_id
WHERE NOT EXISTS (
    SELECT 1
    FROM usuario_empresas ue
    WHERE ue.usuario_id = u.id
      AND ue.empresa_id = u.empresa_id
);
