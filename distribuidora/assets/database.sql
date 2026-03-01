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


CREATE TABLE IF NOT EXISTS produtos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  codigo VARCHAR(50) NOT NULL,
  nome VARCHAR(255) NOT NULL,
  categoria_id INT,
  fornecedor_id INT,
  unidade VARCHAR(50),
  preco DECIMAL(10,2) DEFAULT 0,
  estoque INT DEFAULT 0,
  minimo INT DEFAULT 0,
  status VARCHAR(10) DEFAULT 'ATIVO',
  obs VARCHAR(255),
  imagem VARCHAR(255),
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE UNIQUE INDEX ux_produtos_codigo ON produtos (codigo);
CREATE INDEX idx_produtos_categoria ON produtos (categoria_id);
CREATE INDEX idx_produtos_fornecedor ON produtos (fornecedor_id);
CREATE INDEX idx_produtos_status ON produtos (status);


CREATE TABLE IF NOT EXISTS inventario_itens (
  id INT AUTO_INCREMENT PRIMARY KEY,
  produto_id INT NOT NULL,
  contagem INT NULL,
  diferenca INT NULL,
  situacao VARCHAR(20) NOT NULL DEFAULT 'NAO_CONFERIDO',
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_inventario_produto (produto_id),
  INDEX idx_situacao (situacao),
  INDEX idx_produto (produto_id)
);


CREATE TABLE IF NOT EXISTS entradas (
  id INT AUTO_INCREMENT PRIMARY KEY,
  data DATE NOT NULL,
  nf VARCHAR(60) NOT NULL,
  fornecedor_id INT NOT NULL,
  produto_id INT NOT NULL,
  unidade VARCHAR(40) NOT NULL,
  qtd INT NOT NULL DEFAULT 0,
  custo DECIMAL(10,2) NOT NULL DEFAULT 0,
  total DECIMAL(10,2) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_data (data),
  INDEX idx_nf (nf),
  INDEX idx_fornecedor (fornecedor_id),
  INDEX idx_produto (produto_id)
);


CREATE TABLE IF NOT EXISTS saidas (
  id INT AUTO_INCREMENT PRIMARY KEY,
  data DATE NOT NULL,
  pedido VARCHAR(60) NOT NULL,
  cliente VARCHAR(180) NOT NULL,
  canal VARCHAR(20) NOT NULL,
  pagamento VARCHAR(30) NOT NULL,
  produto_id INT NOT NULL,
  unidade VARCHAR(40) NOT NULL,
  qtd DECIMAL(10,3) NOT NULL DEFAULT 0,
  preco DECIMAL(10,2) NOT NULL DEFAULT 0,
  total DECIMAL(10,2) NOT NULL DEFAULT 0,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL,
  INDEX (data),
  INDEX (produto_id),
  INDEX (pedido),
  INDEX (cliente)
);


CREATE TABLE IF NOT EXISTS vendas (
  id INT AUTO_INCREMENT PRIMARY KEY,
  data DATE NOT NULL,
  cliente VARCHAR(180) NULL,
  canal VARCHAR(20) NOT NULL DEFAULT 'PRESENCIAL', -- PRESENCIAL | DELIVERY
  endereco VARCHAR(255) NULL,
  obs VARCHAR(255) NULL,

  desconto_tipo VARCHAR(10) NOT NULL DEFAULT 'PERC', -- PERC | VALOR
  desconto_valor DECIMAL(10,2) NOT NULL DEFAULT 0,
  taxa_entrega DECIMAL(10,2) NOT NULL DEFAULT 0,

  subtotal DECIMAL(10,2) NOT NULL DEFAULT 0,
  total DECIMAL(10,2) NOT NULL DEFAULT 0,

  pagamento_mode VARCHAR(10) NOT NULL DEFAULT 'UNICO', -- UNICO | MULTI
  pagamento VARCHAR(30) NOT NULL DEFAULT 'DINHEIRO',   -- DINHEIRO | PIX | CARTAO | BOLETO | MULTI
  pagamento_json TEXT NULL, -- detalhes (valores/partes/troco)

  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_vendas_data ON vendas (data);
CREATE INDEX idx_vendas_cliente ON vendas (cliente);
CREATE INDEX idx_vendas_canal ON vendas (canal);