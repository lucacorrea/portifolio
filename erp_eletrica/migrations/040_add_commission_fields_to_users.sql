-- Migration 040: Add commission fields to usuarios table
ALTER TABLE usuarios ADD COLUMN IF NOT EXISTS comissao_ativa TINYINT(1) DEFAULT 0 AFTER desconto_maximo;
ALTER TABLE usuarios ADD COLUMN IF NOT EXISTS comissao_porcentagem DECIMAL(5,2) DEFAULT 0.00 AFTER comissao_ativa;
