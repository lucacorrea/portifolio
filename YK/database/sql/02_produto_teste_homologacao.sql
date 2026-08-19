-- SOMENTE PARA O PRODUTO DEDICADO DE TESTE EM HOMOLOGAÇÃO.
-- NÃO use este UPDATE como classificação automática de produtos reais.
--
-- Perfil técnico:
-- PIS/COFINS 99 zerado para o cenário CRT 1 implementado no emissor.
-- IBS/CBS 000 + cClassTrib 000001 usa a regra técnica padrão 2026
-- do catálogo fiscal.

UPDATE produtos
SET
    cst_pis = '99',
    aliquota_pis = 0.0000,
    cst_cofins = '99',
    aliquota_cofins = 0.0000,
    cst_ibs_cbs = '000',
    classificacao_tributaria_ibs_cbs = '000001'
WHERE nome = 'MOTOR VENTILADOR TESTE HOMOLOGAÇÃO'
  AND excluido_em IS NULL
LIMIT 1;

SELECT
    id,
    codigo,
    nome,
    cst_pis,
    aliquota_pis,
    cst_cofins,
    aliquota_cofins,
    cst_ibs_cbs,
    classificacao_tributaria_ibs_cbs
FROM produtos
WHERE nome = 'MOTOR VENTILADOR TESTE HOMOLOGAÇÃO'
  AND excluido_em IS NULL;
