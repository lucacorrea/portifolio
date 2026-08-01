CREATE TABLE IF NOT EXISTS produtos (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    codigo VARCHAR(20) NULL,
    nome VARCHAR(150) NOT NULL,
    descricao TEXT NULL,
    categoria VARCHAR(100) NULL,
    fabricante VARCHAR(100) NULL,
    unidade VARCHAR(20) NOT NULL DEFAULT 'un',
    codigo_barras VARCHAR(100) NULL,
    preco_custo DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    preco_venda DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    estoque DECIMAL(12,3) NOT NULL DEFAULT 0.000,
    estoque_minimo DECIMAL(12,3) NOT NULL DEFAULT 0.000,
    localizacao VARCHAR(100) NULL,
    status ENUM('ativo', 'inativo') NOT NULL DEFAULT 'ativo',
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uk_produtos_codigo (codigo),
    UNIQUE KEY uk_produtos_codigo_barras (codigo_barras),
    KEY idx_produtos_nome (nome),
    KEY idx_produtos_categoria (categoria),
    KEY idx_produtos_status (status)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS servicos (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    codigo VARCHAR(20) NULL,
    nome VARCHAR(150) NOT NULL,
    categoria VARCHAR(100) NULL,
    equipamentos_compativeis VARCHAR(255) NULL,
    duracao_minutos INT UNSIGNED NOT NULL DEFAULT 0,
    valor DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    descricao TEXT NULL,
    status ENUM('ativo', 'inativo') NOT NULL DEFAULT 'ativo',
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uk_servicos_codigo (codigo),
    KEY idx_servicos_nome (nome),
    KEY idx_servicos_categoria (categoria),
    KEY idx_servicos_status (status)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;
