-- Migration 029: Create fiados_pagamentos table for tracking debt payment history

CREATE TABLE IF NOT EXISTS fiados_pagamentos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    fiado_id INT NOT NULL,
    valor DECIMAL(10,2) NOT NULL,
    metodo VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_fiado_id (fiado_id),
    FOREIGN KEY (fiado_id) REFERENCES contas_receber(id) ON DELETE CASCADE
);
