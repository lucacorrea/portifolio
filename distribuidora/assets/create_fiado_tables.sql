-- Create clientes table
CREATE TABLE IF NOT EXISTS clientes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nome VARCHAR(255) NOT NULL,
  cpf VARCHAR(20),
  telefone VARCHAR(20),
  endereco TEXT,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE INDEX idx_clientes_nome ON clientes (nome);
CREATE INDEX idx_clientes_cpf ON clientes (cpf);

-- Create fiados table
CREATE TABLE IF NOT EXISTS fiados (
  id INT AUTO_INCREMENT PRIMARY KEY,
  venda_id INT NOT NULL,
  cliente_id INT NOT NULL,
  valor_total DECIMAL(10,2) NOT NULL,
  valor_pago DECIMAL(10,2) DEFAULT 0.00,
  valor_restante DECIMAL(10,2) NOT NULL,
  status VARCHAR(20) DEFAULT 'ABERTO', -- ABERTO | PAGO | PARCIAL
  data_vencimento DATE,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (venda_id) REFERENCES vendas(id),
  FOREIGN KEY (cliente_id) REFERENCES clientes(id)
);

CREATE INDEX idx_fiados_cliente ON fiados (cliente_id);
CREATE INDEX idx_fiados_status ON fiados (status);
