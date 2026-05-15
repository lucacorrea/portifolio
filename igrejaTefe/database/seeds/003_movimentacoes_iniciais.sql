-- Movimentacoes iniciais para alimentar dashboard e relatorios.
--
-- Uso:
--   SET @igreja_id := 1;
--   SOURCE database/seeds/003_movimentacoes_iniciais.sql;
--
-- Opcional:
--   SET @usuario_id := 1;
--
-- As entradas deixam contribuinte_nome como NULL, pois o contribuinte
-- nao precisa ser informado.

SET @igreja_id := COALESCE(NULLIF(@igreja_id, 0), 1);
SET @usuario_id := COALESCE(
    NULLIF(@usuario_id, 0),
    (
        SELECT id
        FROM usuarios
        WHERE igreja_id = @igreja_id
          AND ativo = 1
        ORDER BY
            CASE papel
                WHEN 'admin' THEN 1
                WHEN 'tesoureiro' THEN 2
                ELSE 3
            END,
            id
        LIMIT 1
    )
);

SELECT
    CASE
        WHEN NOT EXISTS (SELECT 1 FROM igrejas WHERE id = @igreja_id) THEN 'ERRO: @igreja_id nao existe em igrejas.'
        WHEN @usuario_id IS NULL THEN 'ERRO: nao existe usuario ativo para a igreja informada.'
        WHEN NOT EXISTS (
            SELECT 1
            FROM usuarios
            WHERE id = @usuario_id
              AND igreja_id = @igreja_id
              AND ativo = 1
        ) THEN 'ERRO: @usuario_id nao pertence a igreja informada ou esta inativo.'
        ELSE 'OK: seed de movimentacoes pronto para inserir dados.'
    END AS validacao_seed_movimentacoes;

INSERT INTO categorias (igreja_id, nome, descricao, cor)
SELECT @igreja_id, seed.nome, seed.descricao, seed.cor
FROM (
    SELECT 'Energia eletrica' AS nome, 'Contas de energia eletrica.' AS descricao, '#F79009' AS cor
    UNION ALL SELECT 'Manutencao', 'Reparos, conservacao e servicos gerais.', '#7A5AF8'
    UNION ALL SELECT 'Eventos', 'Despesas com cultos especiais, congressos e eventos.', '#D92D20'
    UNION ALL SELECT 'Missoes', 'Apoio missionario e evangelismo.', '#7F56D9'
    UNION ALL SELECT 'Assistencia social', 'Doacoes, cestas basicas e ajuda emergencial.', '#0E9384'
    UNION ALL SELECT 'Material de culto', 'Itens usados em celebracoes e atividades da igreja.', '#344054'
    UNION ALL SELECT 'Transporte', 'Combustivel, deslocamentos e transporte local.', '#1570EF'
) AS seed
WHERE EXISTS (SELECT 1 FROM igrejas WHERE id = @igreja_id)
ON DUPLICATE KEY UPDATE
    descricao = VALUES(descricao),
    cor = VALUES(cor);

INSERT INTO entradas (
    igreja_id,
    usuario_id,
    tipo,
    valor,
    descricao,
    contribuinte_nome,
    forma_pagamento,
    data_entrada
)
SELECT
    @igreja_id,
    @usuario_id,
    seed.tipo,
    seed.valor,
    seed.descricao,
    NULL,
    seed.forma_pagamento,
    seed.data_entrada
FROM (
    SELECT 'dizimo' AS tipo, 4250.00 AS valor, 'Dizimos recebidos no culto de domingo' AS descricao, 'pix' AS forma_pagamento, DATE_SUB(CURRENT_DATE, INTERVAL 2 DAY) AS data_entrada
    UNION ALL SELECT 'oferta', 1380.50, 'Ofertas gerais da semana', 'dinheiro', DATE_SUB(CURRENT_DATE, INTERVAL 3 DAY)
    UNION ALL SELECT 'dizimo', 3100.00, 'Dizimos via transferencia', 'transferencia', DATE_SUB(CURRENT_DATE, INTERVAL 6 DAY)
    UNION ALL SELECT 'oferta', 2240.00, 'Oferta especial para missoes', 'pix', DATE_SUB(CURRENT_DATE, INTERVAL 8 DAY)
    UNION ALL SELECT 'dizimo', 2860.00, 'Contribuicoes recorrentes do mes', 'cartao', DATE_SUB(CURRENT_DATE, INTERVAL 11 DAY)
    UNION ALL SELECT 'oferta', 980.00, 'Oferta para manutencao do templo', 'pix', DATE_SUB(CURRENT_DATE, INTERVAL 16 DAY)
    UNION ALL SELECT 'dizimo', 7620.00, 'Dizimos consolidados do mes anterior', 'pix', DATE_SUB(DATE_FORMAT(CURRENT_DATE, '%Y-%m-15'), INTERVAL 1 MONTH)
    UNION ALL SELECT 'oferta', 3150.00, 'Ofertas consolidadas do mes anterior', 'dinheiro', DATE_SUB(DATE_FORMAT(CURRENT_DATE, '%Y-%m-20'), INTERVAL 1 MONTH)
    UNION ALL SELECT 'dizimo', 6980.00, 'Dizimos consolidados de dois meses atras', 'transferencia', DATE_SUB(DATE_FORMAT(CURRENT_DATE, '%Y-%m-12'), INTERVAL 2 MONTH)
    UNION ALL SELECT 'oferta', 2490.00, 'Ofertas consolidadas de dois meses atras', 'pix', DATE_SUB(DATE_FORMAT(CURRENT_DATE, '%Y-%m-21'), INTERVAL 2 MONTH)
    UNION ALL SELECT 'dizimo', 8120.00, 'Dizimos consolidados de tres meses atras', 'pix', DATE_SUB(DATE_FORMAT(CURRENT_DATE, '%Y-%m-10'), INTERVAL 3 MONTH)
    UNION ALL SELECT 'oferta', 2760.00, 'Ofertas consolidadas de tres meses atras', 'cartao', DATE_SUB(DATE_FORMAT(CURRENT_DATE, '%Y-%m-18'), INTERVAL 3 MONTH)
    UNION ALL SELECT 'dizimo', 7350.00, 'Dizimos consolidados de quatro meses atras', 'transferencia', DATE_SUB(DATE_FORMAT(CURRENT_DATE, '%Y-%m-11'), INTERVAL 4 MONTH)
    UNION ALL SELECT 'oferta', 2210.00, 'Ofertas consolidadas de quatro meses atras', 'dinheiro', DATE_SUB(DATE_FORMAT(CURRENT_DATE, '%Y-%m-24'), INTERVAL 4 MONTH)
    UNION ALL SELECT 'dizimo', 6890.00, 'Dizimos consolidados de cinco meses atras', 'pix', DATE_SUB(DATE_FORMAT(CURRENT_DATE, '%Y-%m-13'), INTERVAL 5 MONTH)
    UNION ALL SELECT 'oferta', 2050.00, 'Ofertas consolidadas de cinco meses atras', 'pix', DATE_SUB(DATE_FORMAT(CURRENT_DATE, '%Y-%m-23'), INTERVAL 5 MONTH)
) AS seed
WHERE @usuario_id IS NOT NULL
  AND EXISTS (
      SELECT 1
      FROM usuarios
      WHERE id = @usuario_id
        AND igreja_id = @igreja_id
        AND ativo = 1
  )
  AND NOT EXISTS (
      SELECT 1
      FROM entradas e
      WHERE e.igreja_id = @igreja_id
        AND e.tipo = seed.tipo
        AND e.valor = seed.valor
        AND e.descricao = seed.descricao
        AND e.data_entrada = seed.data_entrada
  );

INSERT INTO saidas (
    igreja_id,
    usuario_id,
    categoria_id,
    valor,
    descricao,
    fornecedor,
    forma_pagamento,
    data_saida
)
SELECT
    @igreja_id,
    @usuario_id,
    c.id,
    seed.valor,
    seed.descricao,
    seed.fornecedor,
    seed.forma_pagamento,
    seed.data_saida
FROM (
    SELECT 'Energia eletrica' AS categoria_nome, 742.90 AS valor, 'Conta de energia do templo' AS descricao, 'Amazonas Energia' AS fornecedor, 'boleto' AS forma_pagamento, DATE_SUB(CURRENT_DATE, INTERVAL 4 DAY) AS data_saida
    UNION ALL SELECT 'Manutencao', 1280.00, 'Reparo de iluminacao e pintura', 'Construtora Local', 'pix', DATE_SUB(CURRENT_DATE, INTERVAL 7 DAY)
    UNION ALL SELECT 'Assistencia social', 960.00, 'Cestas basicas e apoio emergencial', 'Familias assistidas', 'dinheiro', DATE_SUB(CURRENT_DATE, INTERVAL 9 DAY)
    UNION ALL SELECT 'Missoes', 1500.00, 'Apoio mensal ao campo missionario', 'Projeto Ribeirinho', 'pix', DATE_SUB(CURRENT_DATE, INTERVAL 13 DAY)
    UNION ALL SELECT 'Eventos', 685.35, 'Itens para encontro de familias', 'Mercado Central', 'cartao', DATE_SUB(CURRENT_DATE, INTERVAL 15 DAY)
    UNION ALL SELECT 'Material de culto', 430.00, 'Revisao de mesa de som e microfones', 'Tecnico de som', 'pix', DATE_SUB(CURRENT_DATE, INTERVAL 18 DAY)
    UNION ALL SELECT 'Transporte', 520.00, 'Deslocamento para visita pastoral', 'Posto local', 'cartao', DATE_SUB(CURRENT_DATE, INTERVAL 20 DAY)
    UNION ALL SELECT 'Manutencao', 2640.00, 'Manutencoes consolidadas do mes anterior', 'Prestadores diversos', 'pix', DATE_SUB(DATE_FORMAT(CURRENT_DATE, '%Y-%m-17'), INTERVAL 1 MONTH)
    UNION ALL SELECT 'Energia eletrica', 815.40, 'Energia do mes anterior', 'Amazonas Energia', 'boleto', DATE_SUB(DATE_FORMAT(CURRENT_DATE, '%Y-%m-22'), INTERVAL 1 MONTH)
    UNION ALL SELECT 'Assistencia social', 1240.00, 'Apoio social do mes anterior', 'Acao comunitaria', 'dinheiro', DATE_SUB(DATE_FORMAT(CURRENT_DATE, '%Y-%m-25'), INTERVAL 1 MONTH)
    UNION ALL SELECT 'Missoes', 1800.00, 'Repasse missionario de dois meses atras', 'Campo missionario', 'pix', DATE_SUB(DATE_FORMAT(CURRENT_DATE, '%Y-%m-15'), INTERVAL 2 MONTH)
    UNION ALL SELECT 'Eventos', 1420.00, 'Evento de familias de dois meses atras', 'Fornecedores locais', 'cartao', DATE_SUB(DATE_FORMAT(CURRENT_DATE, '%Y-%m-24'), INTERVAL 2 MONTH)
    UNION ALL SELECT 'Manutencao', 2100.00, 'Adequacoes do templo de tres meses atras', 'Equipe de manutencao', 'transferencia', DATE_SUB(DATE_FORMAT(CURRENT_DATE, '%Y-%m-16'), INTERVAL 3 MONTH)
    UNION ALL SELECT 'Energia eletrica', 790.00, 'Energia de tres meses atras', 'Amazonas Energia', 'boleto', DATE_SUB(DATE_FORMAT(CURRENT_DATE, '%Y-%m-23'), INTERVAL 3 MONTH)
    UNION ALL SELECT 'Assistencia social', 980.00, 'Assistencia social de quatro meses atras', 'Acao comunitaria', 'dinheiro', DATE_SUB(DATE_FORMAT(CURRENT_DATE, '%Y-%m-19'), INTERVAL 4 MONTH)
    UNION ALL SELECT 'Material de culto', 640.00, 'Materiais para celebracoes de cinco meses atras', 'Fornecedor de artigos religiosos', 'pix', DATE_SUB(DATE_FORMAT(CURRENT_DATE, '%Y-%m-21'), INTERVAL 5 MONTH)
) AS seed
INNER JOIN categorias c
   ON c.igreja_id = @igreja_id
  AND c.nome = seed.categoria_nome
WHERE @usuario_id IS NOT NULL
  AND EXISTS (
      SELECT 1
      FROM usuarios
      WHERE id = @usuario_id
        AND igreja_id = @igreja_id
        AND ativo = 1
  )
  AND NOT EXISTS (
      SELECT 1
      FROM saidas s
      WHERE s.igreja_id = @igreja_id
        AND s.categoria_id = c.id
        AND s.valor = seed.valor
        AND s.descricao = seed.descricao
        AND s.data_saida = seed.data_saida
  );

SELECT
    (SELECT COUNT(*) FROM entradas WHERE igreja_id = @igreja_id) AS total_entradas_igreja,
    (SELECT COUNT(*) FROM saidas WHERE igreja_id = @igreja_id) AS total_saidas_igreja;
