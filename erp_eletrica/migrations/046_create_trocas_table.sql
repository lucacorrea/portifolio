CREATE TABLE IF NOT EXISTS trocas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    venda_id INT NOT NULL,
    item_original_id INT NOT NULL,
    produto_original_id INT NOT NULL,
    quantidade_original DECIMAL(10,2) NOT NULL,
    preco_original DECIMAL(10,2) NOT NULL,
    produto_novo_id INT NOT NULL,
    quantidade_nova DECIMAL(10,2) NOT NULL,
    preco_novo DECIMAL(10,2) NOT NULL,
    diferenca_valor DECIMAL(10,2) NOT NULL,
    usuario_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
