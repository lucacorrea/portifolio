-- Add audit fields to vendas table
ALTER TABLE vendas ADD COLUMN IF NOT EXISTS desconto_total DECIMAL(10,2) DEFAULT 0.00 AFTER valor_total;
ALTER TABLE vendas ADD COLUMN IF NOT EXISTS autorizado_por INT NULL AFTER usuario_id;
ALTER TABLE vendas ADD CONSTRAINT fk_vendas_autorizado_por FOREIGN KEY (autorizado_por) REFERENCES usuarios(id) ON DELETE SET NULL;
