-- Tabela de Secretarias
CREATE TABLE IF NOT EXISTS secretarias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(255) NOT NULL,
    codigo_acesso VARCHAR(100) UNIQUE NOT NULL, -- Código para login da secretaria
    responsavel VARCHAR(255),
    email VARCHAR(255),
    criado_em DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Tabela de Fornecedores
CREATE TABLE IF NOT EXISTS fornecedores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(255) NOT NULL,
    cnpj VARCHAR(20) UNIQUE,
    contato VARCHAR(255),
    telefone VARCHAR(20),
    criado_em DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Tabela de Usuários (Internos: Prefeitura/Suporte/Funcionario)
CREATE TABLE IF NOT EXISTS usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(255) NOT NULL,
    usuario VARCHAR(100) UNIQUE NOT NULL,
    senha VARCHAR(255) NOT NULL,
    nivel ENUM('SUPORTE', 'ADMIN', 'FUNCIONARIO') NOT NULL,
    criado_em DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS oficios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    numero VARCHAR(50) UNIQUE NOT NULL, -- Ex: OF-2026-0001
    secretaria_id INT NOT NULL,
    local VARCHAR(150) DEFAULT NULL,
    justificativa TEXT,
    status ENUM('ENVIADO', 'APROVADO', 'REPROVADO', 'ARQUIVADO') DEFAULT 'ENVIADO',
    usuario_id INT NOT NULL, -- Quem cadastrou
    arquivo_orcamento VARCHAR(255) DEFAULT NULL, -- Caminho do anexo
    criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (secretaria_id) REFERENCES secretarias(id),
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
);
-- Itens do Ofício
CREATE TABLE IF NOT EXISTS itens_oficio (
    id INT AUTO_INCREMENT PRIMARY KEY,
    oficio_id INT NOT NULL,
    produto VARCHAR(255) NOT NULL,
    quantidade DECIMAL(10,2) NOT NULL,
    unidade VARCHAR(20) DEFAULT 'UN',
    FOREIGN KEY (oficio_id) REFERENCES oficios(id) ON DELETE CASCADE
);

-- Tabela de Aquisições
CREATE TABLE IF NOT EXISTS aquisicoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    numero_aq VARCHAR(50) UNIQUE NOT NULL, -- Ex: AQ-2026-0001
    codigo_entrega VARCHAR(50) UNIQUE NOT NULL, -- Ex: ENT-2026-AB12C
    oficio_id INT NOT NULL,
    fornecedor_id INT NOT NULL,
    valor_total DECIMAL(15,2) DEFAULT 0,
    responsavel_entrega VARCHAR(255),
    status ENUM('AGUARDANDO ENTREGA', 'FINALIZADO') DEFAULT 'AGUARDANDO ENTREGA',
    data_finalizacao DATETIME,
    usuario_id_finalizou INT,
    criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (oficio_id) REFERENCES oficios(id),
    FOREIGN KEY (fornecedor_id) REFERENCES fornecedores(id),
    FOREIGN KEY (usuario_id_finalizou) REFERENCES usuarios(id)
);

-- Itens da Aquisição (Pode herdar do ofício, mas permite ajuste de valor)
CREATE TABLE IF NOT EXISTS itens_aquisicao (
    id INT AUTO_INCREMENT PRIMARY KEY,
    aquisicao_id INT NOT NULL,
    produto VARCHAR(255) NOT NULL,
    quantidade DECIMAL(10,2) NOT NULL,
    valor_unitario DECIMAL(15,2) NOT NULL,
    FOREIGN KEY (aquisicao_id) REFERENCES aquisicoes(id) ON DELETE CASCADE
);

-- Logs do Sistema
CREATE TABLE IF NOT EXISTS logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT,
    secretaria_id INT,
    acao VARCHAR(100) NOT NULL,
    detalhes TEXT,
    ip VARCHAR(45),
    criado_em DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Dados Iniciais
INSERT INTO secretarias (nome, codigo_acesso, responsavel) VALUES 
('Saúde', 'SAUDE2026', 'Dr. Carlos Oliveira'),
('Educação', 'EDUCA2026', 'Profa. Maria Silva'),
('Obras', 'OBRAS2026', 'Eng. Roberto Santos'),
('Assistência Social', 'SOCIAL2026', 'Dra. Ana Paula');

INSERT INTO fornecedores (nome, cnpj, contato) VALUES 
('Papelaria Central', '12.345.678/0001-01', 'João Papel'),
('Supermercado Silva', '22.333.444/0001-02', 'Sr. Silva'),
('Construtora Rocha', '33.444.555/0001-03', 'Ricardo Rocha');

-- Senhas padrão: '123'
-- Hash gerado por password_hash('123', PASSWORD_DEFAULT)
INSERT INTO usuarios (nome, usuario, senha, nivel) VALUES 
('Suporte Técnico', 'suporte', '$2y$10$0Aw4ie.N1y1atk5dLMohYOVQGOKS04fQK95ggn6HN2AGMhvGqbv2O', 'SUPORTE'),
('Prefeito Administrativo', 'admin', '$2y$10$0Aw4ie.N1y1atk5dLMohYOVQGOKS04fQK95ggn6HN2AGMhvGqbv2O', 'ADMIN'),
('Recepcionista Central', 'func', '$2y$10$0Aw4ie.N1y1atk5dLMohYOVQGOKS04fQK95ggn6HN2AGMhvGqbv2O', 'FUNCIONARIO');
INSERT INTO itens_aquisicao (aquisicao_id, produto, quantidade, valor_unitario) VALUES
