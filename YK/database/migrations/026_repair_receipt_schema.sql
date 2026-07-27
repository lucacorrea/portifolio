-- Migration 026 - Repara colunas exigidas pela emissao de recibos em bancos legados ou parciais.
-- Compatibilidade alvo: MariaDB 10.4, InnoDB, utf8mb4.

SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

ALTER TABLE recibos
    ADD COLUMN IF NOT EXISTS cliente_nome VARCHAR(150) NULL AFTER pagamento_id,
    ADD COLUMN IF NOT EXISTS cliente_documento VARCHAR(20) NULL AFTER cliente_nome,
    ADD COLUMN IF NOT EXISTS os_numero VARCHAR(20) NULL AFTER cliente_documento,
    ADD COLUMN IF NOT EXISTS pagamento_recebido_em DATETIME NULL AFTER os_numero,
    ADD COLUMN IF NOT EXISTS empresa_nome VARCHAR(150) NULL AFTER pagamento_recebido_em,
    ADD COLUMN IF NOT EXISTS empresa_documento VARCHAR(30) NULL AFTER empresa_nome,
    ADD COLUMN IF NOT EXISTS empresa_telefone VARCHAR(30) NULL AFTER empresa_documento,
    ADD COLUMN IF NOT EXISTS empresa_endereco VARCHAR(255) NULL AFTER empresa_telefone,
    ADD COLUMN IF NOT EXISTS empresa_logo VARCHAR(255) NULL AFTER empresa_endereco,
    ADD COLUMN IF NOT EXISTS quantidade_parcelas SMALLINT UNSIGNED NOT NULL DEFAULT 1 AFTER forma_pagamento;

ALTER TABLE ordem_servico_pagamentos
    ADD COLUMN IF NOT EXISTS quantidade_parcelas SMALLINT UNSIGNED NOT NULL DEFAULT 1 AFTER forma_pagamento;
ALTER TABLE recibos
    MODIFY COLUMN quantidade_parcelas SMALLINT UNSIGNED NOT NULL DEFAULT 1;

ALTER TABLE ordem_servico_pagamentos
    MODIFY COLUMN quantidade_parcelas SMALLINT UNSIGNED NOT NULL DEFAULT 1;