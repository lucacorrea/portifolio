-- Migration 023: Adiciona campos fiscais em vendas_itens e tipo_nota em vendas
-- Necessário para integridade de dados SEFAZ e toggle Nota Fiscal / Não Fiscal

-- 1. tipo_nota na tabela vendas (fiscal ou nao_fiscal)
ALTER TABLE vendas
    ADD COLUMN IF NOT EXISTS tipo_nota ENUM('fiscal','nao_fiscal') NOT NULL DEFAULT 'nao_fiscal' AFTER status;

-- 2. Campos fiscais em vendas_itens (espelhando o produto no momento da venda)
ALTER TABLE vendas_itens
    ADD COLUMN IF NOT EXISTS ncm          VARCHAR(10)   NULL COMMENT 'NCM 8 dígitos' AFTER preco_unitario,
    ADD COLUMN IF NOT EXISTS cean         VARCHAR(14)   NULL DEFAULT 'SEM GTIN'      AFTER ncm,
    ADD COLUMN IF NOT EXISTS cest         VARCHAR(7)    NULL                          AFTER cean,
    ADD COLUMN IF NOT EXISTS cfop         VARCHAR(4)    NULL DEFAULT '5102'           AFTER cest,
    ADD COLUMN IF NOT EXISTS origem       TINYINT       NULL DEFAULT 0                AFTER cfop,
    ADD COLUMN IF NOT EXISTS csosn        VARCHAR(3)    NULL DEFAULT '102'            AFTER origem,
    ADD COLUMN IF NOT EXISTS unidade      VARCHAR(6)    NULL DEFAULT 'UN'             AFTER csosn;

-- Índice para facilitar relatórios fiscais por nota
CREATE INDEX IF NOT EXISTS idx_vendas_tipo_nota ON vendas(tipo_nota);
