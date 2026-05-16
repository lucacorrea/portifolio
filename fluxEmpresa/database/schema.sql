CREATE DATABASE IF NOT EXISTS fluxempresa_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE fluxempresa_db;

CREATE TABLE empresas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(255) NOT NULL,
    documento VARCHAR(30) NULL,
    email VARCHAR(255) NULL,
    telefone VARCHAR(30) NULL,
    whatsapp VARCHAR(30) NULL,
    endereco TEXT NULL,
    segmento VARCHAR(100) NULL,
    logo VARCHAR(255) NULL,
    cor_principal VARCHAR(20) DEFAULT '#0f766e',
    ativo TINYINT(1) DEFAULT 1,
    criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NULL
);

CREATE TABLE usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT NULL,
    nome VARCHAR(255) NOT NULL,
    email VARCHAR(255) NULL,
    usuario VARCHAR(100) NOT NULL UNIQUE,
    senha VARCHAR(255) NOT NULL,
    perfil ENUM('SUPER_ADMIN','ADMIN_EMPRESA','OPERADOR','FINANCEIRO') NOT NULL,
    ativo TINYINT(1) DEFAULT 1,
    ultimo_login DATETIME NULL,
    criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NULL,
    FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE SET NULL
);

CREATE TABLE clientes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT NOT NULL,
    nome VARCHAR(255) NOT NULL,
    documento VARCHAR(30) NULL,
    email VARCHAR(255) NULL,
    telefone VARCHAR(30) NULL,
    whatsapp VARCHAR(30) NULL,
    endereco TEXT NULL,
    observacoes TEXT NULL,
    ativo TINYINT(1) DEFAULT 1,
    criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NULL,
    FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE,
    INDEX idx_clientes_empresa (empresa_id)
);

CREATE TABLE produtos_servicos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT NOT NULL,
    tipo ENUM('PRODUTO','SERVICO') NOT NULL,
    nome VARCHAR(255) NOT NULL,
    categoria VARCHAR(120) NULL,
    unidade VARCHAR(30) DEFAULT 'UN',
    valor_padrao DECIMAL(15,2) DEFAULT 0,
    descricao TEXT NULL,
    ativo TINYINT(1) DEFAULT 1,
    criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NULL,
    FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE,
    INDEX idx_produtos_empresa (empresa_id)
);

CREATE TABLE solicitacoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT NOT NULL,
    cliente_id INT NOT NULL,
    numero VARCHAR(50) NOT NULL,
    titulo VARCHAR(255) NOT NULL,
    descricao TEXT NULL,
    status ENUM('RASCUNHO','ORCAMENTO','ENVIADO','APROVADO','EM_EXECUCAO','CONCLUIDO','CANCELADO') DEFAULT 'RASCUNHO',
    valor_total DECIMAL(15,2) DEFAULT 0,
    prazo_entrega DATE NULL,
    criado_por INT NOT NULL,
    criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NULL,
    FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE,
    FOREIGN KEY (cliente_id) REFERENCES clientes(id),
    FOREIGN KEY (criado_por) REFERENCES usuarios(id),
    UNIQUE KEY uk_solicitacao_empresa_numero (empresa_id, numero),
    INDEX idx_solicitacoes_empresa_status (empresa_id, status)
);

CREATE TABLE solicitacao_itens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    solicitacao_id INT NOT NULL,
    produto_servico_id INT NULL,
    descricao VARCHAR(255) NOT NULL,
    quantidade DECIMAL(10,2) NOT NULL,
    unidade VARCHAR(30) DEFAULT 'UN',
    valor_unitario DECIMAL(15,2) NOT NULL,
    subtotal DECIMAL(15,2) NOT NULL,
    FOREIGN KEY (solicitacao_id) REFERENCES solicitacoes(id) ON DELETE CASCADE,
    FOREIGN KEY (produto_servico_id) REFERENCES produtos_servicos(id) ON DELETE SET NULL,
    INDEX idx_itens_solicitacao (solicitacao_id)
);

CREATE TABLE orcamentos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT NOT NULL,
    solicitacao_id INT NOT NULL,
    numero VARCHAR(50) NOT NULL,
    validade DATE NULL,
    observacoes TEXT NULL,
    pdf_path VARCHAR(255) NULL,
    status ENUM('GERADO','ENVIADO','APROVADO','RECUSADO') DEFAULT 'GERADO',
    criado_por INT NOT NULL,
    criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE,
    FOREIGN KEY (solicitacao_id) REFERENCES solicitacoes(id) ON DELETE CASCADE,
    FOREIGN KEY (criado_por) REFERENCES usuarios(id),
    UNIQUE KEY uk_orcamento_empresa_numero (empresa_id, numero)
);

CREATE TABLE execucoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT NOT NULL,
    solicitacao_id INT NOT NULL,
    responsavel_id INT NULL,
    status ENUM('NAO_INICIADA','EM_ANDAMENTO','CONCLUIDA','CANCELADA') DEFAULT 'NAO_INICIADA',
    descricao_execucao TEXT NULL,
    data_inicio DATETIME NULL,
    data_conclusao DATETIME NULL,
    criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NULL,
    FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE,
    FOREIGN KEY (solicitacao_id) REFERENCES solicitacoes(id) ON DELETE CASCADE,
    FOREIGN KEY (responsavel_id) REFERENCES usuarios(id) ON DELETE SET NULL
);

CREATE TABLE execucao_anexos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT NOT NULL,
    execucao_id INT NOT NULL,
    nome_original VARCHAR(255) NOT NULL,
    caminho VARCHAR(255) NOT NULL,
    mime_type VARCHAR(120) NULL,
    tamanho_bytes INT NULL,
    criado_por INT NOT NULL,
    criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE,
    FOREIGN KEY (execucao_id) REFERENCES execucoes(id) ON DELETE CASCADE,
    FOREIGN KEY (criado_por) REFERENCES usuarios(id)
);

CREATE TABLE pagamentos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT NOT NULL,
    solicitacao_id INT NOT NULL,
    valor_total DECIMAL(15,2) NOT NULL DEFAULT 0,
    valor_pago DECIMAL(15,2) NOT NULL DEFAULT 0,
    forma_pagamento VARCHAR(80) NULL,
    status ENUM('PENDENTE','PARCIAL','PAGO','ATRASADO','CANCELADO') DEFAULT 'PENDENTE',
    data_pagamento DATETIME NULL,
    observacoes TEXT NULL,
    criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NULL,
    FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE,
    FOREIGN KEY (solicitacao_id) REFERENCES solicitacoes(id) ON DELETE CASCADE
);

CREATE TABLE logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT NULL,
    usuario_id INT NULL,
    acao VARCHAR(120) NOT NULL,
    detalhes TEXT NULL,
    ip VARCHAR(45) NULL,
    user_agent VARCHAR(255) NULL,
    criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE SET NULL,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
);
