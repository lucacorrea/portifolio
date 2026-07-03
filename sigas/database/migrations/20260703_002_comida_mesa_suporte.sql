SET NAMES utf8mb4;

START TRANSACTION;

INSERT IGNORE INTO nivel_permissoes (
    nivel_id,
    permissao_id
)
SELECT
    n.id,
    p.id
FROM niveis_acesso AS n
INNER JOIN permissoes AS p
    ON p.modulo = 'comida_mesa'
   AND p.ativo = 1
WHERE n.slug IN ('administrador', 'suporte')
  AND n.ativo = 1;

COMMIT;
