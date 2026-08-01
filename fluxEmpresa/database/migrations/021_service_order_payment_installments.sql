-- Migration 021 - Forma de pagamento e parcelas de recebimentos de OS.
-- Compatibilidade alvo: MariaDB 10.4, InnoDB, utf8mb4.

SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

ALTER TABLE ordem_servico_pagamentos
    MODIFY COLUMN forma_pagamento ENUM(
        'dinheiro', 'pix', 'boleto', 'cartao_debito', 'cartao_credito',
        'transferencia', 'outro'
    ) NOT NULL,
    ADD COLUMN IF NOT EXISTS quantidade_parcelas SMALLINT UNSIGNED NOT NULL DEFAULT 1 AFTER forma_pagamento;

ALTER TABLE recibos
    ADD COLUMN IF NOT EXISTS quantidade_parcelas SMALLINT UNSIGNED NOT NULL DEFAULT 1 AFTER forma_pagamento;
