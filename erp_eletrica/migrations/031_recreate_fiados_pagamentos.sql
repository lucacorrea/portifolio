-- Migration 031: Re-create fiados_pagamentos if it was skipped or corrupted
CREATE TABLE IF NOT EXISTS fiados_pagamentos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    fiado_id INT NOT NULL,
    valor DECIMAL(10,2) NOT NULL,
    metodo VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_fiado_id (fiado_id)
) ENGINE=InnoDB;

-- Also double check columns for contas_receber (redundant safety)
ALTER TABLE contas_receber ADD COLUMN IF NOT EXISTS valor_pago DECIMAL(10,2) DEFAULT 0.00 AFTER valor;
ALTER TABLE contas_receber ADD COLUMN IF NOT EXISTS saldo DECIMAL(10,2) DEFAULT 0.00 AFTER valor_pago;
