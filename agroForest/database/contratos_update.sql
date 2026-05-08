CREATE TABLE IF NOT EXISTS contratos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  cliente_id INT NOT NULL,
  numero VARCHAR(30) NOT NULL UNIQUE,
  titulo VARCHAR(150) NOT NULL,
  valor DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  status ENUM('rascunho','em_assinatura','ativo','pendente','em_analise','vencido','encerrado') NOT NULL DEFAULT 'rascunho',
  inicio DATE NULL,
  fim DATE NULL,
  observacoes TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_contratos_cliente_status (cliente_id, status),
  CONSTRAINT fk_contratos_cliente FOREIGN KEY (cliente_id) REFERENCES clientes(id)
);
