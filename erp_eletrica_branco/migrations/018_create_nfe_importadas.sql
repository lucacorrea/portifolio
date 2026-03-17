-- Migration 018: Create nfe_importadas table
CREATE TABLE IF NOT EXISTS nfe_importadas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    filial_id INT NOT NULL,
    chave_nfe VARCHAR(44) NOT NULL,
    fornecedor_cnpj VARCHAR(14) NOT NULL,
    fornecedor_nome VARCHAR(255) NOT NULL,
    numero_nota VARCHAR(20) NOT NULL,
    data_emissao DATETIME NOT NULL,
    valor_total DECIMAL(15,2) NOT NULL,
    xml_conteudo LONGTEXT,
    status ENUM('pendente', 'importada') DEFAULT 'pendente',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_filial (filial_id),
    INDEX idx_chave (chave_nfe),
    FOREIGN KEY (filial_id) REFERENCES filiais(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
