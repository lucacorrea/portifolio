-- SIGAS - Meu Primeiro Emprego
-- Lista oficial/final do Banco do Brasil como fonte de verdade da base ativa.
-- Compatível com MariaDB 10.5+ / 11.x. Pode ser executado novamente.

SET NAMES utf8mb4;

ALTER TABLE pe_candidatos
    ADD COLUMN IF NOT EXISTS lista_final_ativa TINYINT(1) NOT NULL DEFAULT 1 AFTER status,
    ADD COLUMN IF NOT EXISTS lista_final_origem VARCHAR(40) NULL AFTER lista_final_ativa,
    ADD COLUMN IF NOT EXISTS lista_final_importacao_id BIGINT UNSIGNED NULL AFTER lista_final_origem,
    ADD COLUMN IF NOT EXISTS lista_final_sincronizada_em DATETIME NULL AFTER lista_final_importacao_id,
    ADD COLUMN IF NOT EXISTS lista_final_excluido_em DATETIME NULL AFTER lista_final_sincronizada_em,
    ADD COLUMN IF NOT EXISTS lista_final_exclusao_motivo VARCHAR(255) NULL AFTER lista_final_excluido_em,
    ADD INDEX IF NOT EXISTS idx_pe_candidatos_lista_final_ativa (lista_final_ativa),
    ADD INDEX IF NOT EXISTS idx_pe_candidatos_lista_final_importacao (lista_final_importacao_id);

ALTER TABLE pe_pagamento_importacoes
    ADD COLUMN IF NOT EXISTS candidatos_criados INT UNSIGNED NOT NULL DEFAULT 0 AFTER atualizados,
    ADD COLUMN IF NOT EXISTS candidatos_recuperados INT UNSIGNED NOT NULL DEFAULT 0 AFTER candidatos_criados,
    ADD COLUMN IF NOT EXISTS candidatos_excluidos INT UNSIGNED NOT NULL DEFAULT 0 AFTER candidatos_recuperados;
