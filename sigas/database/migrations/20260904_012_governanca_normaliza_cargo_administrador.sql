SET NAMES utf8mb4;

-- Evita confundir cargo/função profissional com o nível de acesso "Administrador".
-- Só renomeia quando não houver usuário atualmente vinculado ao nome legado
-- e quando o nome/slug de destino ainda não estiverem em uso.
UPDATE cargos c
LEFT JOIN usuarios u
       ON u.excluido_em IS NULL
      AND LOWER(TRIM(COALESCE(u.cargo, ''))) = LOWER(TRIM(c.nome))
LEFT JOIN cargos outro
       ON outro.id <> c.id
      AND outro.excluido_em IS NULL
      AND (
          LOWER(TRIM(outro.nome)) = LOWER('Administrador(a) de Sistemas')
          OR LOWER(TRIM(outro.slug)) = 'administrador-sistemas'
      )
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
  AND u.id IS NULL
  AND outro.id IS NULL;
