SET NAMES utf8mb4;

START TRANSACTION;

UPDATE niveis_acesso
SET descricao =
    'Superusuário técnico com acesso global aos módulos operacionais, usuários, auditoria, configurações e diagnósticos.'
WHERE slug = 'suporte';

INSERT IGNORE INTO nivel_permissoes (
    nivel_id,
    permissao_id
)
SELECT
    n.id,
    p.id
FROM niveis_acesso AS n
CROSS JOIN permissoes AS p
WHERE n.slug = 'suporte'
  AND n.ativo = 1
  AND p.ativo = 1;

COMMIT;
