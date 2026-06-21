SET NAMES utf8mb4;

INSERT INTO configuracoes (empresa_id, chave, valor)
SELECT e.id, 'app_name', COALESCE(NULLIF(e.nome_fantasia, ''), e.nome)
FROM empresas e
WHERE NOT EXISTS (
    SELECT 1
    FROM configuracoes c
    WHERE c.empresa_id = e.id
      AND c.chave = 'app_name'
);

INSERT INTO configuracoes (empresa_id, chave, valor)
SELECT e.id, 'app_short_name', LEFT(COALESCE(NULLIF(e.nome_fantasia, ''), e.nome), 40)
FROM empresas e
WHERE NOT EXISTS (
    SELECT 1
    FROM configuracoes c
    WHERE c.empresa_id = e.id
      AND c.chave = 'app_short_name'
);

INSERT INTO configuracoes (empresa_id, chave, valor)
SELECT e.id, 'branding_updated_at', DATE_FORMAT(NOW(), '%Y-%m-%d %H:%i:%s')
FROM empresas e
WHERE NOT EXISTS (
    SELECT 1
    FROM configuracoes c
    WHERE c.empresa_id = e.id
      AND c.chave = 'branding_updated_at'
);
