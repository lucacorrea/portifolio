SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS vendas (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    empresa_id BIGINT UNSIGNED NOT NULL,
    usuario_id BIGINT UNSIGNED NOT NULL,
    cliente_id BIGINT UNSIGNED NULL,
    numero_venda VARCHAR(30) NOT NULL,
    status ENUM('finalizada','pendente','cancelada','em_aberto') NOT NULL DEFAULT 'finalizada',
    subtotal DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    desconto DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    acrescimo DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    total DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    motivo_cancelamento VARCHAR(255) NULL,
    cancelada_por BIGINT UNSIGNED NULL,
    cancelada_em DATETIME NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_vendas_empresa_numero (empresa_id, numero_venda),
    KEY idx_vendas_empresa_data (empresa_id, criado_em),
    KEY idx_vendas_usuario (usuario_id),
    KEY idx_vendas_cliente (cliente_id),
    CONSTRAINT fk_vendas_empresa FOREIGN KEY (empresa_id) REFERENCES empresas(id),
    CONSTRAINT fk_vendas_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
    CONSTRAINT fk_vendas_cliente FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE SET NULL,
    CONSTRAINT fk_vendas_cancelada_por FOREIGN KEY (cancelada_por) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS venda_itens (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    venda_id BIGINT UNSIGNED NOT NULL,
    produto_id BIGINT UNSIGNED NULL,
    produto_nome VARCHAR(180) NOT NULL,
    lote VARCHAR(80) NULL,
    validade DATE NULL,
    quantidade DECIMAL(12,3) NOT NULL,
    preco_unitario DECIMAL(12,2) NOT NULL,
    subtotal DECIMAL(12,2) NOT NULL,
    PRIMARY KEY (id),
    KEY idx_venda_itens_venda (venda_id),
    KEY idx_venda_itens_produto (produto_id),
    CONSTRAINT fk_venda_itens_venda FOREIGN KEY (venda_id) REFERENCES vendas(id) ON DELETE CASCADE,
    CONSTRAINT fk_venda_itens_produto FOREIGN KEY (produto_id) REFERENCES produtos(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pagamentos (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    venda_id BIGINT UNSIGNED NOT NULL,
    metodo ENUM('pix','credito','debito','dinheiro','conta_cliente','misto') NOT NULL,
    valor DECIMAL(12,2) NOT NULL,
    valor_recebido DECIMAL(12,2) NULL,
    troco DECIMAL(12,2) NULL,
    status ENUM('pago','pendente','estornado') NOT NULL DEFAULT 'pago',
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_pagamentos_venda (venda_id),
    CONSTRAINT fk_pagamentos_venda FOREIGN KEY (venda_id) REFERENCES vendas(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


