SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS cargos (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    nome VARCHAR(120) NOT NULL,
    slug VARCHAR(120) NOT NULL,
    descricao VARCHAR(255) NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    excluido_em DATETIME NULL DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_cargos_nome (nome),
    UNIQUE KEY uk_cargos_slug (slug),
    KEY idx_cargos_ativo (ativo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Preserva os cargos já existentes nas contas atuais sem criar dados fictícios.
INSERT INTO cargos (nome, slug, descricao, ativo, criado_em)
SELECT
    legado.nome,
    legado.slug,
    'Importado automaticamente dos usuários existentes.',
    1,
    NOW()
FROM (
    SELECT DISTINCT
        TRIM(u.cargo) AS nome,
        CONCAT('legado-', LEFT(SHA2(LOWER(TRIM(u.cargo)), 256), 12)) AS slug
    FROM usuarios u
    WHERE u.excluido_em IS NULL
      AND u.cargo IS NOT NULL
      AND TRIM(u.cargo) <> ''
) legado
LEFT JOIN cargos c
       ON LOWER(TRIM(c.nome)) = LOWER(TRIM(legado.nome))
      AND c.excluido_em IS NULL
WHERE c.id IS NULL;
