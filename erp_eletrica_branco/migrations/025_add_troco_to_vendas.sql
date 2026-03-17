-- Adiciona colunas de valor_recebido e troco na tabela vendas
-- Para exibição do troco no recibo não fiscal (estilo Açaidinhos)

ALTER TABLE vendas ADD COLUMN IF NOT EXISTS valor_recebido DECIMAL(10,2) NULL DEFAULT NULL AFTER forma_pagamento;
ALTER TABLE vendas ADD COLUMN IF NOT EXISTS troco DECIMAL(10,2) NULL DEFAULT NULL AFTER valor_recebido;
