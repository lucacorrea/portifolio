-- Migration 025 - Corrige a unicidade da numeracao para separar NF-e 55 e NFC-e 65.
-- Compatibilidade alvo: MariaDB 10.4.

ALTER TABLE documentos_fiscais
    DROP INDEX IF EXISTS uq_documento_fiscal_numero;

ALTER TABLE documentos_fiscais
    ADD UNIQUE KEY uq_documento_fiscal_numero (ambiente, modelo, serie, numero);
