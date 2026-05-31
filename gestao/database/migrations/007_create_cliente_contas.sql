SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS cliente_contas (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    empresa_id BIGINT UNSIGNED NOT NULL,
    cliente_id BIGINT UNSIGNED NOT NULL,
    venda_id BIGINT UNSIGNED NULL,
    valor_original DECIMAL(12,2) NOT NULL,
    valor_pago DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    saldo_aberto DECIMAL(12,2) NOT NULL,
    vencimento DATE NOT NULL,
    status ENUM('em_aberto','parcial','pago','atrasado','cancelado') NOT NULL DEFAULT 'em_aberto',
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_contas_empresa (empresa_id),
    KEY idx_contas_cliente (cliente_id),
    KEY idx_contas_vencimento (vencimento),
    CONSTRAINT fk_contas_empresa FOREIGN KEY (empresa_id) REFERENCES empresas(id),
    CONSTRAINT fk_contas_cliente FOREIGN KEY (cliente_id) REFERENCES clientes(id),
    CONSTRAINT fk_contas_venda FOREIGN KEY (venda_id) REFERENCES vendas(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cliente_pagamentos (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    conta_id BIGINT UNSIGNED NOT NULL,
    usuario_id BIGINT UNSIGNED NOT NULL,
    valor DECIMAL(12,2) NOT NULL,
    metodo ENUM('pix','credito','debito','dinheiro') NOT NULL,
    novo_vencimento DATE NULL,
    observacao VARCHAR(255) NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_cliente_pagamentos_conta (conta_id),
    CONSTRAINT fk_cliente_pagamentos_conta FOREIGN KEY (conta_id) REFERENCES cliente_contas(id) ON DELETE CASCADE,
    CONSTRAINT fk_cliente_pagamentos_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


