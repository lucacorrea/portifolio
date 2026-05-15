-- Default categories for one igreja.
--
-- Set @igreja_id before running this file when needed:
--   SET @igreja_id := 1;
--   SOURCE database/seeds/001_categorias_padrao.sql;
--
-- The seed is idempotent and does not reactivate categories intentionally
-- disabled by the user.

SET @igreja_id := COALESCE(@igreja_id, 1);

INSERT INTO categorias (igreja_id, nome, descricao, cor)
SELECT @igreja_id, seed.nome, seed.descricao, seed.cor
FROM (
    SELECT 'Aluguel' AS nome, 'Custos de aluguel ou uso do templo.' AS descricao, '#155EEF' AS cor
    UNION ALL SELECT 'Agua', 'Contas de agua e saneamento.', '#0F8F66'
    UNION ALL SELECT 'Energia eletrica', 'Contas de energia eletrica.', '#F79009'
    UNION ALL SELECT 'Manutencao', 'Reparos, conservacao e servicos gerais.', '#7A5AF8'
    UNION ALL SELECT 'Limpeza', 'Materiais e servicos de limpeza.', '#12B76A'
    UNION ALL SELECT 'Eventos', 'Despesas com cultos especiais, congressos e eventos.', '#D92D20'
    UNION ALL SELECT 'Missoes', 'Apoio missionario e evangelismo.', '#7F56D9'
    UNION ALL SELECT 'Assistencia social', 'Doacoes, cestas basicas e ajuda emergencial.', '#0E9384'
    UNION ALL SELECT 'Material de culto', 'Itens usados em celebracoes e atividades da igreja.', '#344054'
    UNION ALL SELECT 'Transporte', 'Combustivel, deslocamentos e transporte local.', '#1570EF'
    UNION ALL SELECT 'Outros', 'Despesas nao classificadas nas categorias anteriores.', '#667085'
) AS seed
WHERE EXISTS (
    SELECT 1
    FROM igrejas
    WHERE igrejas.id = @igreja_id
)
ON DUPLICATE KEY UPDATE
    descricao = VALUES(descricao),
    cor = VALUES(cor);

