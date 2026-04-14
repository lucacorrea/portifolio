-- Migration 032: Controle de Estoque Multi-Filial e Sistema B2B de Transferências

-- 1. Criação do Mapeamento de Estoque por Filial (Isolamento de Armazéns)
CREATE TABLE IF NOT EXISTS estoque_filiais (
    id INT AUTO_INCREMENT PRIMARY KEY,
    produto_id INT NOT NULL,
    filial_id INT NOT NULL,
    quantidade DECIMAL(10,3) DEFAULT 0,
    estoque_minimo DECIMAL(10,3) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_produto_filial (produto_id, filial_id),
    CONSTRAINT fk_estoque_produto FOREIGN KEY (produto_id) REFERENCES produtos(id) ON DELETE CASCADE,
    CONSTRAINT fk_estoque_filial_ref FOREIGN KEY (filial_id) REFERENCES filiais(id) ON DELETE CASCADE
);

-- Popula o armazém da Matriz (Filial 1) com todo o estoque global atual
INSERT IGNORE INTO estoque_filiais (produto_id, filial_id, quantidade, estoque_minimo)
SELECT id, (SELECT id FROM filiais WHERE principal = 1 LIMIT 1), quantidade, estoque_minimo 
FROM produtos;

-- 2. Tabela Master de Requisições e Remessas B2B
CREATE TABLE IF NOT EXISTS transferencias_estoque (
    id INT AUTO_INCREMENT PRIMARY KEY,
    codigo_transferencia VARCHAR(20) NOT NULL UNIQUE,
    tipo VARCHAR(50) NOT NULL DEFAULT 'transferencia', -- 'solicitacao' ou 'transferencia'
    origem_filial_id INT NOT NULL,
    destino_filial_id INT NOT NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'pendente', -- pendente, aprovada, em_transito, recusada, concluida
    valor_total_custo DECIMAL(10,2) DEFAULT 0,
    observacoes TEXT,
    usuario_id INT NOT NULL,
    data_solicitacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    data_aprovacao TIMESTAMP NULL,
    data_envio TIMESTAMP NULL,
    data_recebimento TIMESTAMP NULL,
    CONSTRAINT fk_transf_origem FOREIGN KEY (origem_filial_id) REFERENCES filiais(id),
    CONSTRAINT fk_transf_destino FOREIGN KEY (destino_filial_id) REFERENCES filiais(id)
);

-- 3. Cesta de Itens do Malote
CREATE TABLE IF NOT EXISTS transferencias_itens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    transferencia_id INT NOT NULL,
    produto_id INT NOT NULL,
    quantidade_solicitada DECIMAL(10,3) NOT NULL,
    quantidade_enviada DECIMAL(10,3) DEFAULT 0,
    quantidade_recebida DECIMAL(10,3) DEFAULT 0,
    valor_custo_unitario DECIMAL(10,2) DEFAULT 0,
    CONSTRAINT fk_transf_item FOREIGN KEY (transferencia_id) REFERENCES transferencias_estoque(id) ON DELETE CASCADE,
    CONSTRAINT fk_transf_produto FOREIGN KEY (produto_id) REFERENCES produtos(id)
);
