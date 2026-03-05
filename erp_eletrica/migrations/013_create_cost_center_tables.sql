-- Migration 013: Centro de Custos Avançado
CREATE TABLE IF NOT EXISTS centros_custo (
    id INT AUTO_INCREMENT PRIMARY KEY,
    filial_id INT NOT NULL,
    nome VARCHAR(100) NOT NULL,
    tipo ENUM('fixo', 'variavel') NOT NULL,
    ativo BOOLEAN DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_cc_filial (filial_id),
    FOREIGN KEY (filial_id) REFERENCES filiais(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS lancamentos_custos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    filial_id INT NOT NULL,
    centro_custo_id INT NOT NULL,
    descricao VARCHAR(255) NOT NULL,
    valor DECIMAL(10,2) NOT NULL,
    data_lancamento DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_lc_filial (filial_id),
    INDEX idx_lc_cc (centro_custo_id),
    INDEX idx_lc_data (data_lancamento),
    FOREIGN KEY (filial_id) REFERENCES filiais(id),
    FOREIGN KEY (centro_custo_id) REFERENCES centros_custo(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Adicionar permissões
INSERT IGNORE INTO permissoes (modulo, acao, descricao) VALUES 
('custos', 'visualizar', 'Ver relatórios de custos'),
('custos', 'gerenciar', 'Gerenciar centros e lançamentos de custo');
