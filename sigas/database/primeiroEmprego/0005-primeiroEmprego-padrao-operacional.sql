-- SIGAS — Meu Primeiro Emprego
-- Complemento do padrão operacional 2026.
-- Adiciona sigla própria aos órgãos/instituições parceiras.
-- MariaDB 10.5+ / 11.x.

SET NAMES utf8mb4;

ALTER TABLE pe_parceiros
    ADD COLUMN IF NOT EXISTS sigla VARCHAR(30) NULL AFTER nome;

CREATE INDEX IF NOT EXISTS idx_pe_parceiros_sigla
    ON pe_parceiros (sigla);

-- Padroniza apenas espaços/capitalização das siglas que já tenham sido informadas.
-- Não inventa siglas para registros existentes.
UPDATE pe_parceiros
SET sigla = UPPER(TRIM(sigla))
WHERE sigla IS NOT NULL
  AND TRIM(sigla) <> '';
