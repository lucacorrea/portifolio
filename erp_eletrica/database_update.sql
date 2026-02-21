-- ERP Elétrica - Database Expansion Script

-- 1. Enhance Users Table
CREATE TABLE IF NOT EXISTS usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    senha VARCHAR(255) NOT NULL,
    nivel ENUM('admin', 'gerente', 'vendedor', 'tecnico') DEFAULT 'vendedor',
    avatar VARCHAR(255) DEFAULT 'default_avatar.png',
    ativo TINYINT(1) DEFAULT 1,
    last_login DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Ensure columns exist for existing tables
ALTER TABLE usuarios ADD COLUMN IF NOT EXISTS avatar VARCHAR(255) DEFAULT 'default_avatar.png' AFTER nivel;
ALTER TABLE usuarios ADD COLUMN IF NOT EXISTS ativo TINYINT(1) DEFAULT 1 AFTER avatar;
ALTER TABLE usuarios ADD COLUMN IF NOT EXISTS last_login DATETIME AFTER ativo;
ALTER TABLE usuarios MODIFY COLUMN nivel ENUM('admin', 'gerente', 'vendedor', 'tecnico') DEFAULT 'vendedor';

-- 2. Enhance Clients Table
ALTER TABLE clientes 
ADD COLUMN IF NOT EXISTS rg_ie VARCHAR(20) AFTER cpf_cnpj,
ADD COLUMN IF NOT EXISTS tipo ENUM('fisica', 'juridica') DEFAULT 'fisica' AFTER nome,
ADD COLUMN IF NOT EXISTS contato_nome VARCHAR(100) AFTER email,
ADD COLUMN IF NOT EXISTS whatsapp VARCHAR(20) AFTER telefone,
ADD COLUMN IF NOT EXISTS lat VARCHAR(50) AFTER endereco,
ADD COLUMN IF NOT EXISTS lng VARCHAR(50) AFTER endereco;

-- 3. Enhance Products Table
ALTER TABLE produtos
ADD COLUMN IF NOT EXISTS ncm VARCHAR(10) AFTER codigo,
ADD COLUMN IF NOT EXISTS unidade VARCHAR(10) DEFAULT 'UN' AFTER nome,
ADD COLUMN IF NOT EXISTS peso DECIMAL(10,3) DEFAULT 0.000 AFTER unidade,
ADD COLUMN IF NOT EXISTS dimensoes VARCHAR(100) AFTER peso,
ADD COLUMN IF NOT EXISTS imagens TEXT AFTER descricao,
ADD COLUMN IF NOT EXISTS preco_venda_atacado DECIMAL(10,2) AFTER preco_venda,
ADD COLUMN IF NOT EXISTS tipo_produto ENUM('simples', 'composto') DEFAULT 'simples' AFTER categoria;

-- 4. Product Kits (Composed Products)
CREATE TABLE IF NOT EXISTS produto_kits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    produto_pai_id INT,
    produto_filho_id INT,
    quantidade DECIMAL(10,3),
    FOREIGN KEY (produto_pai_id) REFERENCES produtos(id) ON DELETE CASCADE,
    FOREIGN KEY (produto_filho_id) REFERENCES produtos(id) ON DELETE CASCADE
);

-- 5. Multiple Warehouses
CREATE TABLE IF NOT EXISTS depositos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    localizacao VARCHAR(255),
    principal TINYINT(1) DEFAULT 0
);

-- 6. Stock by Warehouse & Batch
CREATE TABLE IF NOT EXISTS estoque_detalhado (
    id INT AUTO_INCREMENT PRIMARY KEY,
    produto_id INT,
    deposito_id INT,
    lote VARCHAR(50),
    validade DATE,
    quantidade DECIMAL(10,3) DEFAULT 0,
    FOREIGN KEY (produto_id) REFERENCES produtos(id) ON DELETE CASCADE,
    FOREIGN KEY (deposito_id) REFERENCES depositos(id) ON DELETE CASCADE
);

-- 7. Stock Movement History
CREATE TABLE IF NOT EXISTS movimentacao_estoque (
    id INT AUTO_INCREMENT PRIMARY KEY,
    produto_id INT,
    deposito_id INT,
    quantidade DECIMAL(10,3),
    tipo ENUM('entrada', 'saida', 'ajuste', 'transferencia') NOT NULL,
    motivo VARCHAR(255),
    usuario_id INT,
    referencia_id INT, -- ID da OS, Pedido de Compra, etc.
    data_movimento TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (produto_id) REFERENCES produtos(id),
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
);

-- 8. Enhance OS Table
ALTER TABLE os MODIFY COLUMN status ENUM('orcamento', 'aprovado', 'em_andamento', 'aguardando_peca', 'concluido', 'entregue', 'cancelado') DEFAULT 'orcamento';
ALTER TABLE os ADD COLUMN IF NOT EXISTS tecnico_id INT AFTER cliente_id;
ALTER TABLE os ADD COLUMN IF NOT EXISTS checklist_tecnico JSON DEFAULT NULL AFTER descricao;
ALTER TABLE os ADD COLUMN IF NOT EXISTS observacoes_internas TEXT AFTER checklist_tecnico;
ALTER TABLE os ADD COLUMN IF NOT EXISTS data_previsao DATE AFTER data_abertura;
ALTER TABLE os ADD COLUMN IF NOT EXISTS estoque_baixado TINYINT(1) DEFAULT 0 AFTER valor_total;

-- 9. OS Logs/History
CREATE TABLE IF NOT EXISTS os_historico (
    id INT AUTO_INCREMENT PRIMARY KEY,
    os_id INT,
    status_anterior VARCHAR(50),
    status_novo VARCHAR(50),
    observacao TEXT,
    usuario_id INT,
    data_historico TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (os_id) REFERENCES os(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
);

-- 10. Financial: Accounts Payable & Cost Centers
CREATE TABLE IF NOT EXISTS centros_custo (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    descricao TEXT
);

CREATE TABLE IF NOT EXISTS contas_pagar (
    id INT AUTO_INCREMENT PRIMARY KEY,
    fornecedor_id INT, -- Podemos criar tabela fornecedores depois ou usar clientes adaptado
    centro_custo_id INT,
    descricao VARCHAR(255),
    valor DECIMAL(10,2),
    data_vencimento DATE,
    data_pagamento DATE,
    status ENUM('pendente', 'pago', 'cancelado') DEFAULT 'pendente',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (centro_custo_id) REFERENCES centros_custo(id)
);

-- 11. Suppliers (Fornecedores)
CREATE TABLE IF NOT EXISTS fornecedores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome_fantasia VARCHAR(100) NOT NULL,
    razao_social VARCHAR(100),
    cnpj VARCHAR(20) UNIQUE,
    telefone VARCHAR(20),
    email VARCHAR(100),
    site VARCHAR(100),
    endereco TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 12. Multi-Branch Support (Filiais)
CREATE TABLE IF NOT EXISTS filiais (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    cnpj VARCHAR(20) UNIQUE,
    endereco TEXT,
    telefone VARCHAR(20),
    email VARCHAR(100),
    principal TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Add filial_id to core tables
ALTER TABLE usuarios ADD COLUMN IF NOT EXISTS filial_id INT AFTER id;
ALTER TABLE os ADD COLUMN IF NOT EXISTS filial_id INT AFTER id;
ALTER TABLE clientes ADD COLUMN IF NOT EXISTS filial_id INT AFTER id;
ALTER TABLE produtos ADD COLUMN IF NOT EXISTS filial_id INT AFTER id;
ALTER TABLE contas_pagar ADD COLUMN IF NOT EXISTS filial_id INT AFTER id;

-- Seed Default Branch
INSERT IGNORE INTO filiais (nome, principal) VALUES ('Matriz', 1);

-- 13. Stock Transfers between branches
CREATE TABLE IF NOT EXISTS transferencias_estoque (
    id INT AUTO_INCREMENT PRIMARY KEY,
    produto_id INT,
    origem_filial_id INT,
    destino_filial_id INT,
    quantidade DECIMAL(10,3),
    usuario_id INT,
    status ENUM('pendente', 'concluido', 'cancelado') DEFAULT 'pendente',
    data_transferencia TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (produto_id) REFERENCES produtos(id),
    FOREIGN KEY (origem_filial_id) REFERENCES filiais(id),
    FOREIGN KEY (destino_filial_id) REFERENCES filiais(id),
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
);
-- 14. Sales Module (Vendas)
CREATE TABLE IF NOT EXISTS vendas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cliente_id INT,
    usuario_id INT,
    filial_id INT,
    valor_total DECIMAL(10,2) NOT NULL,
    forma_pagamento ENUM('dinheiro', 'pix', 'cartao_credito', 'cartao_debito', 'boleto') NOT NULL,
    status ENUM('orcamento', 'concluido', 'cancelado') DEFAULT 'concluido',
    data_venda TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (cliente_id) REFERENCES clientes(id),
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
    FOREIGN KEY (filial_id) REFERENCES filiais(id)
);

CREATE TABLE IF NOT EXISTS vendas_itens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    venda_id INT,
    produto_id INT,
    quantidade DECIMAL(10,3) NOT NULL,
    preco_unitario DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (venda_id) REFERENCES vendas(id) ON DELETE CASCADE,
    FOREIGN KEY (produto_id) REFERENCES produtos(id)
);
