SET NAMES utf8mb4;

-- Evita confundir cargo/função profissional com o nível de acesso "Administrador".
-- Só renomeia quando não houver usuário atualmente vinculado ao nome legado.
UPDATE cargos c
SET
    c.nome = 'Administrador(a) de Sistemas',
    c.slug = 'administrador-sistemas',
    c.descricao = CASE
        WHEN c.descricao IS NULL OR TRIM(c.descricao) = ''
            THEN 'Administração técnica de sistemas. Este cargo não concede nível Administrador no SIGAS.'
        ELSE c.descricao
    END
WHERE LOWER(TRIM(c.nome)) = 'administrador do sistema'
  AND c.excluido_em IS NULL
  AND NOT EXISTS (
      SELECT 1
      FROM usuarios u
      WHERE u.excluido_em IS NULL
        AND LOWER(TRIM(COALESCE(u.cargo, ''))) = LOWER(TRIM(c.nome))
  )
  AND NOT EXISTS (
      SELECT 1
      FROM cargos outro
      WHERE outro.id <> c.id
        AND outro.excluido_em IS NULL
        AND (
            LOWER(TRIM(outro.nome)) = LOWER('Administrador(a) de Sistemas')
            OR LOWER(TRIM(outro.slug)) = 'administrador-sistemas'
        )
  );
