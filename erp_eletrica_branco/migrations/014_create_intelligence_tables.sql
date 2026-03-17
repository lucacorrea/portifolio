-- Migration 014: Inteligência Comercial
CREATE TABLE IF NOT EXISTS produto_curva_abc (
    id INT AUTO_INCREMENT PRIMARY KEY,
    produto_id INT NOT NULL,
    filial_id INT NOT NULL,
    classificacao ENUM('A', 'B', 'C') NOT NULL,
    periodo_referencia VARCHAR(20) NOT NULL, -- Ex: 2024-03
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_prod_filial_per (produto_id, filial_id, periodo_referencia),
    FOREIGN KEY (produto_id) REFERENCES produtos(id) ON DELETE CASCADE,
    FOREIGN KEY (filial_id) REFERENCES filiais(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS alertas_estoque (
    id INT AUTO_INCREMENT PRIMARY KEY,
    produto_id INT NOT NULL,
    filial_id INT NOT NULL,
    tipo ENUM('reposicao') NOT NULL,
    mensagem TEXT NOT NULL,
    status ENUM('ativo', 'resolvido') DEFAULT 'ativo',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_alert_filial (filial_id),
    INDEX idx_alert_prod (produto_id),
    FOREIGN KEY (produto_id) REFERENCES produtos(id) ON DELETE CASCADE,
    FOREIGN KEY (filial_id) REFERENCES filiais(id)
) ENGINE=InnoDB;

-- Permissões Inteligência
INSERT IGNORE INTO permissoes (modulo, acao, descricao) VALUES 
('inteligencia', 'visualizar', 'Ver BI e Inteligência Comercial'),
('inteligencia', 'recalcular', 'Recalcular Curvas e Alertas');
