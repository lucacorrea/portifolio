CREATE TABLE usuarios (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nome VARCHAR(120) NOT NULL,
  email VARCHAR(160) NOT NULL UNIQUE,
  senha VARCHAR(255) NOT NULL,
  nivel ENUM('recepcao','administrativo','dono') NOT NULL DEFAULT 'recepcao',
  ativo TINYINT(1) NOT NULL DEFAULT 1,
  ultimo_login DATETIME NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE clientes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nome VARCHAR(150) NOT NULL,
  cpf_cnpj VARCHAR(30) DEFAULT NULL,
  telefone VARCHAR(30) DEFAULT NULL,
  email VARCHAR(150) DEFAULT NULL,
  endereco VARCHAR(255) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE tipos_servicos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nome VARCHAR(120) NOT NULL,
  categoria VARCHAR(80) NOT NULL,
  setor_responsavel ENUM('recepcao','administrativo','dono') NOT NULL DEFAULT 'administrativo',
  prazo_padrao VARCHAR(40) DEFAULT NULL,
  valor_base DECIMAL(10,2) DEFAULT NULL,
  descricao TEXT NULL,
  documentos_necessarios TEXT NULL,
  status ENUM('ativo','em_revisao','inativo') NOT NULL DEFAULT 'ativo',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE protocolos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  numero VARCHAR(30) NOT NULL UNIQUE,
  cliente_id INT NOT NULL,
  tipo_servico VARCHAR(120) NOT NULL,
  prioridade ENUM('normal','media','alta','urgente') NOT NULL DEFAULT 'normal',
  descricao TEXT NOT NULL,
  observacoes TEXT NULL,
  status ENUM('aberto','encaminhado','em_analise','orcado','concluido','cancelado') NOT NULL DEFAULT 'aberto',
  setor_atual ENUM('recepcao','administrativo','dono') NOT NULL DEFAULT 'recepcao',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_protocolos_cliente FOREIGN KEY (cliente_id) REFERENCES clientes(id)
);

CREATE TABLE orcamentos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  protocolo_id INT NOT NULL,
  valor DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  descricao TEXT NULL,
  status ENUM('rascunho','enviado','aprovado','reprovado') NOT NULL DEFAULT 'rascunho',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_orcamentos_protocolo FOREIGN KEY (protocolo_id) REFERENCES protocolos(id)
);

INSERT INTO usuarios (nome, email, senha, nivel, ativo) VALUES
('Recepção Agro Forest', 'recepcao@agroforest.test', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi', 'recepcao', 1),
('Administrativo Agro Forest', 'administrativo@agroforest.test', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi', 'administrativo', 1),
('Dono Agro Forest', 'dono@agroforest.test', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi', 'dono', 1);
