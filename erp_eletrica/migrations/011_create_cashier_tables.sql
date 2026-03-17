-- Migration 011: Tabelas de Controle de Caixa e Permissões

CREATE TABLE IF NOT EXISTS caixas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    filial_id INT NOT NULL,
    operador_id INT NOT NULL,
    valor_abertura DECIMAL(10,2) NOT NULL,
    valor_fechamento DECIMAL(10,2) NULL,
    status ENUM('aberto', 'fechado') DEFAULT 'aberto',
    data_abertura DATETIME NOT NULL,
    data_fechamento DATETIME NULL,
    observacao TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_caixa_filial (filial_id),
    INDEX idx_caixa_operador (operador_id),
    INDEX idx_caixa_status (status),
    FOREIGN KEY (filial_id) REFERENCES filiais(id),
    FOREIGN KEY (operador_id) REFERENCES usuarios(id)
);

CREATE TABLE IF NOT EXISTS caixa_movimentacoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    caixa_id INT NOT NULL,
    tipo ENUM('sangria', 'suprimento') NOT NULL,
    valor DECIMAL(10,2) NOT NULL,
    motivo VARCHAR(255) NOT NULL,
    operador_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_mov_caixa (caixa_id),
    INDEX idx_mov_operador (operador_id),
    FOREIGN KEY (caixa_id) REFERENCES caixas(id) ON DELETE CASCADE,
    FOREIGN KEY (operador_id) REFERENCES usuarios(id)
);

-- Inserir Permissões para o novo módulo
INSERT IGNORE INTO permissoes (modulo, acao, descricao) VALUES 
('caixa', 'abrir', 'Abrir novo caixa'),
('caixa', 'fechar', 'Fechar caixa aberto'),
('caixa', 'movimentar', 'Registrar sangria e suprimento'),
('caixa', 'visualizar', 'Visualizar histórico e relatórios de caixa');
