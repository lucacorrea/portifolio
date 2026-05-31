-- Banco de dados do L&J Caixa Premium
-- Compatível com MySQL/MariaDB da Hostinger.
-- Ordem recomendada:
-- 1. Crie o banco no painel da hospedagem.
-- 2. Importe este arquivo no phpMyAdmin.
-- 3. Ajuste backend/config/database.php com usuário, senha e nome do banco.

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS empresas (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    nome VARCHAR(180) NOT NULL,
    nome_fantasia VARCHAR(180) NULL,
    cpf_cnpj VARCHAR(20) NULL,
    telefone VARCHAR(30) NULL,
    endereco VARCHAR(255) NULL,
    logo VARCHAR(255) NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_empresas_ativo (ativo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS usuarios (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    empresa_id BIGINT UNSIGNED NOT NULL,
    nome VARCHAR(140) NOT NULL,
    email VARCHAR(180) NOT NULL,
    senha_hash VARCHAR(255) NOT NULL,
    telefone VARCHAR(30) NULL,
    nivel ENUM('admin','gerente','operador','estoquista','leitor') NOT NULL DEFAULT 'operador',
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    ultimo_login_em DATETIME NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_usuarios_email (email),
    KEY idx_usuarios_empresa (empresa_id),
    KEY idx_usuarios_nivel (nivel),
    KEY idx_usuarios_ativo (ativo),
    CONSTRAINT fk_usuarios_empresa FOREIGN KEY (empresa_id) REFERENCES empresas(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS login_auditoria (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    usuario_id BIGINT UNSIGNED NULL,
    email VARCHAR(180) NULL,
    ip VARCHAR(45) NULL,
    user_agent VARCHAR(255) NULL,
    sucesso TINYINT(1) NOT NULL DEFAULT 0,
    motivo VARCHAR(180) NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_login_usuario (usuario_id),
    KEY idx_login_email (email),
    KEY idx_login_criado (criado_em),
    CONSTRAINT fk_login_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_produtos_empresa_sku (empresa_id, sku),
    KEY idx_produtos_empresa (empresa_id),
    KEY idx_produtos_categoria (categoria_id),
    KEY idx_produtos_codigo (codigo_barras),
    KEY idx_produtos_validade (validade),
    KEY idx_produtos_estoque (quantidade, estoque_minimo),
    CONSTRAINT fk_produtos_empresa FOREIGN KEY (empresa_id) REFERENCES empresas(id),
    CONSTRAINT fk_produtos_categoria FOREIGN KEY (categoria_id) REFERENCES categorias(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS clientes (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    empresa_id BIGINT UNSIGNED NOT NULL,
    nome VARCHAR(180) NOT NULL,
    telefone VARCHAR(30) NULL,
    cpf_cnpj VARCHAR(20) NULL,
    endereco VARCHAR(255) NULL,
    observacao TEXT NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_clientes_empresa (empresa_id),
    KEY idx_clientes_nome (nome),
    KEY idx_clientes_telefone (telefone),
    CONSTRAINT fk_clientes_empresa FOREIGN KEY (empresa_id) REFERENCES empresas(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
    KEY idx_vendas_status (status),
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
    KEY idx_pagamentos_metodo (metodo),
    CONSTRAINT fk_pagamentos_venda FOREIGN KEY (venda_id) REFERENCES vendas(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
    KEY idx_contas_status (status),
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
    KEY idx_cliente_pagamentos_usuario (usuario_id),
    CONSTRAINT fk_cliente_pagamentos_conta FOREIGN KEY (conta_id) REFERENCES cliente_contas(id) ON DELETE CASCADE,
    CONSTRAINT fk_cliente_pagamentos_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS configuracoes (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    empresa_id BIGINT UNSIGNED NOT NULL,
    chave VARCHAR(120) NOT NULL,
    valor TEXT NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_config_empresa_chave (empresa_id, chave),
    CONSTRAINT fk_config_empresa FOREIGN KEY (empresa_id) REFERENCES empresas(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO empresas (id, nome, nome_fantasia, telefone, endereco, ativo)
VALUES (1, 'L&J Soluções Tech', 'L&J Caixa', '(97) 99999-0000', 'Coari - AM', 1)
ON DUPLICATE KEY UPDATE nome = VALUES(nome);

-- Usuário inicial:
-- E-mail: admin@ljsolucoestech.com.br
-- Senha: Admin@123
-- Troque após o primeiro acesso.
INSERT INTO usuarios (id, empresa_id, nome, email, senha_hash, nivel, ativo)
VALUES
(1, 1, 'Administrador', 'admin@ljsolucoestech.com.br', '$2y$12$CVdQ49rtTQ7UJF9inzfV5udXRpJV0bXzm5iWrI1kSxd5hmvDlNp72', 'admin', 1)
ON DUPLICATE KEY UPDATE email = VALUES(email);

INSERT INTO categorias (empresa_id, nome) VALUES
(1, 'Laticínios'),
(1, 'Mercearia'),
(1, 'Higiene')
ON DUPLICATE KEY UPDATE nome = VALUES(nome);

INSERT INTO configuracoes (empresa_id, chave, valor) VALUES
(1, 'comprovante_modo', 'perguntar'),
(1, 'comprovante_modelo', 'detalhado'),
(1, 'alerta_validade_dias', '7'),
(1, 'prazo_divida_dias', '30'),
(1, 'bloquear_produto_vencido', '1'),
(1, 'bloquear_estoque_negativo', '1')
ON DUPLICATE KEY UPDATE valor = VALUES(valor);

SET FOREIGN_KEY_CHECKS = 1;
