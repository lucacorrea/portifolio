CREATE TABLE IF NOT EXISTS fornecedores (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nome VARCHAR(200) NOT NULL,
  status VARCHAR(10) DEFAULT 'ATIVO',
  doc VARCHAR(30),
  tel VARCHAR(30),
  email VARCHAR(190),
  endereco VARCHAR(255),
  cidade VARCHAR(120),
  uf VARCHAR(2),
  contato VARCHAR(120),
  obs TEXT,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE INDEX idx_fornecedores_nome ON fornecedores (nome);
CREATE INDEX idx_fornecedores_status ON fornecedores (status);
CREATE INDEX idx_fornecedores_doc ON fornecedores (doc);


CREATE TABLE IF NOT EXISTS categorias (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nome VARCHAR(200) NOT NULL,
  descricao VARCHAR(320),
  cor VARCHAR(7) DEFAULT '#60a5fa',
  obs TEXT,
  status VARCHAR(10) DEFAULT 'ATIVO',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE INDEX idx_categorias_nome ON categorias (nome);
CREATE INDEX idx_categorias_status ON categorias (status);