-- Migration 021: Adicionar cEAN aos produtos para integração fiscal
-- Adiciona o campo cEAN (GTIN) ausente na tabela de produtos

ALTER TABLE produtos ADD COLUMN IF NOT EXISTS cean VARCHAR(14) DEFAULT 'SEM GTIN' AFTER ncm;
