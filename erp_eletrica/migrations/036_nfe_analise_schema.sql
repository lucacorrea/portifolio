-- Migration 036: NFe Analysis Schema and Product Mapping
-- Adicionar status 'em_analise' à tabela nfe_importadas
ALTER TABLE nfe_importadas MODIFY COLUMN status ENUM('pendente', 'importada', 'em_analise') DEFAULT 'pendente';

-- Tabela para itens pendentes de análise (extraídos do XML)
CREATE TABLE IF NOT EXISTS nfe_analise_itens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nfe_id INT NOT NULL,
    codigo_fornecedor VARCHAR(50) NOT NULL,
    nome_item VARCHAR(255) NOT NULL,
    unidade VARCHAR(10),
    quantidade DECIMAL(15,4) NOT NULL,
    valor_unitario DECIMAL(15,4) NOT NULL,
    ncm VARCHAR(10),
    ean VARCHAR(20),
    produto_id INT DEFAULT NULL, -- ID do produto vinculado no sistema
    status ENUM('pendente', 'vinculado', 'novo', 'ignorado') DEFAULT 'pendente',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (nfe_id) REFERENCES nfe_importadas(id) ON DELETE CASCADE,
    FOREIGN KEY (produto_id) REFERENCES produtos(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabela para mapeamento histórico entre fornecedor e produto interno
CREATE TABLE IF NOT EXISTS produto_fornecedor_map (
    id INT AUTO_INCREMENT PRIMARY KEY,
    fornecedor_cnpj VARCHAR(20) NOT NULL,
    codigo_fornecedor VARCHAR(50) NOT NULL,
    produto_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE INDEX idx_forn_prod (fornecedor_cnpj, codigo_fornecedor),
    FOREIGN KEY (produto_id) REFERENCES produtos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
