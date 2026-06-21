SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS categorias (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    empresa_id BIGINT UNSIGNED NOT NULL,
    nome VARCHAR(120) NOT NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_categorias_empresa_nome (empresa_id, nome),
    CONSTRAINT fk_categorias_empresa FOREIGN KEY (empresa_id) REFERENCES empresas(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS produtos (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    empresa_id BIGINT UNSIGNED NOT NULL,
    categoria_id BIGINT UNSIGNED NULL,
    nome VARCHAR(180) NOT NULL,
    sku VARCHAR(80) NULL,
    codigo_barras VARCHAR(80) NULL,
    lote VARCHAR(80) NULL,
    validade DATE NULL,
    quantidade DECIMAL(12,3) NOT NULL DEFAULT 0,
    estoque_minimo DECIMAL(12,3) NOT NULL DEFAULT 0,
    preco_custo DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    preco_venda DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    imagem VARCHAR(255) NULL,
    descricao TEXT NULL,
    marca VARCHAR(150) NULL,
    unidade VARCHAR(20) NULL,
    quantidade_embalagem VARCHAR(50) NULL,
    ncm VARCHAR(10) NULL,
    cest VARCHAR(10) NULL,
    fabricante VARCHAR(150) NULL,
    origem_dados VARCHAR(50) NULL,
    url_imagem_origem VARCHAR(500) NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_produtos_empresa_sku (empresa_id, sku),
    UNIQUE KEY uk_produtos_empresa_codigo_barras (empresa_id, codigo_barras),
    KEY idx_produtos_empresa (empresa_id),
    KEY idx_produtos_categoria (categoria_id),
    KEY idx_produtos_codigo (codigo_barras),
    KEY idx_produtos_validade (validade),
    CONSTRAINT fk_produtos_empresa FOREIGN KEY (empresa_id) REFERENCES empresas(id),
    CONSTRAINT fk_produtos_categoria FOREIGN KEY (categoria_id) REFERENCES categorias(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

