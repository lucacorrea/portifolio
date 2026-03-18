-- Migration 030: Adiciona campos de pagamento parcial à tabela de contas_receber
ALTER TABLE contas_receber ADD COLUMN IF NOT EXISTS valor_pago DECIMAL(10,2) DEFAULT 0.00 AFTER valor;
ALTER TABLE contas_receber ADD COLUMN IF NOT EXISTS saldo DECIMAL(10,2) DEFAULT 0.00 AFTER valor_pago;

-- Sincronizar saldo inicial para registros existentes
UPDATE contas_receber SET saldo = (valor - valor_pago) WHERE saldo = 0 OR saldo IS NULL;
