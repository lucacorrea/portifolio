CREATE TABLE IF NOT EXISTS configuracoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    chave VARCHAR(100) NOT NULL UNIQUE,
    valor TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Initial Settings
INSERT IGNORE INTO configuracoes (chave, valor) VALUES 
('empresa_nome', 'ERP Elétrica'),
('estoque_min_default', '5'),
('msg_orcamento', 'Orçamento válido por 5 dias. Materiais elétricos sujeitos a disponibilidade.');
