CREATE TABLE IF NOT EXISTS configuracoes_automacao (
    id                                      INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    empresa_nome                            VARCHAR(150) NOT NULL,
    empresa_cnpj                            VARCHAR(20) DEFAULT NULL,
    empresa_telefone                        VARCHAR(20) DEFAULT NULL,
    empresa_email                           VARCHAR(150) DEFAULT NULL,
    empresa_endereco                        VARCHAR(255) DEFAULT NULL,

    automacao_ativa                         TINYINT(1) NOT NULL DEFAULT 1,

    dia_vencimento_padrao                   TINYINT UNSIGNED NOT NULL DEFAULT 10,
    bloquear_apos_dias                      INT NOT NULL DEFAULT 7,

    pix_nome_recebedor                      VARCHAR(150) NOT NULL,
    pix_tipo_chave                          VARCHAR(30) NOT NULL,
    pix_chave                               VARCHAR(255) NOT NULL,

    mensagem_10_dias                        TEXT NOT NULL,
    mensagem_5_dias                         TEXT NOT NULL,
    mensagem_dia_vencimento                 TEXT NOT NULL,
    mensagem_7_dias_atraso                  TEXT NOT NULL,

    status_cliente_apos_atraso              VARCHAR(30) NOT NULL DEFAULT 'Pendente',
    status_cliente_apos_bloqueio            VARCHAR(30) NOT NULL DEFAULT 'Bloqueado',
    gemini_api_key                          VARCHAR(255) DEFAULT NULL,

    atualizado_em                           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    criado_em                               DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);


CREATE TABLE IF NOT EXISTS clientes (
    id                                      INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    nome                                    VARCHAR(150) NOT NULL,
    cpf                                     VARCHAR(20) DEFAULT NULL,
    telefone                                VARCHAR(20) DEFAULT NULL,
    email                                   VARCHAR(150) DEFAULT NULL,
    endereco                                VARCHAR(255) DEFAULT NULL,

    mensalidade                             DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    dia_vencimento                          TINYINT UNSIGNED NOT NULL DEFAULT 10,
    forma_pagamento                         VARCHAR(30) NOT NULL DEFAULT 'PIX',

    qtd_veiculos                            INT NOT NULL DEFAULT 1,
    tipo_veiculo                            VARCHAR(50) DEFAULT NULL,
    status                                  VARCHAR(30) NOT NULL DEFAULT 'Ativo',
    mensagem_automatica                     TINYINT(1) NOT NULL DEFAULT 1,
    whatsapp_principal                      VARCHAR(20) DEFAULT NULL,
    observacoes                             TEXT DEFAULT NULL,

    criado_em                               DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em                           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_nome (nome),
    INDEX idx_status (status),
    INDEX idx_telefone (telefone)
);


CREATE TABLE IF NOT EXISTS whatsapp_envios (
    id                                      INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    cliente_id                              INT UNSIGNED NOT NULL,
    telefone                                VARCHAR(20) NOT NULL,
    mensagem                                TEXT NOT NULL,
    status_envio                            VARCHAR(20) NOT NULL DEFAULT 'pendente',
    resposta_api                            TEXT DEFAULT NULL,
    criado_em                               DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_cliente_id (cliente_id),
    INDEX idx_status_envio (status_envio),
    INDEX idx_criado_em (criado_em)
);

CREATE TABLE IF NOT EXISTS pagamentos (
    id                                      INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    cliente_id                              INT UNSIGNED NOT NULL,
    valor                                   DECIMAL(10,2) NOT NULL,
    data_pagamento                          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    forma_pagamento                         VARCHAR(30) NOT NULL DEFAULT 'PIX',
    status                                  VARCHAR(30) NOT NULL DEFAULT 'Confirmado',
    comprovante_url                         VARCHAR(255) DEFAULT NULL,
    mensagem_id                             VARCHAR(100) DEFAULT NULL,
    referencia_mes                          VARCHAR(7) DEFAULT NULL, -- Ex: 05/2026
    observacoes                             TEXT DEFAULT NULL,

    INDEX idx_cliente_id (cliente_id),
    INDEX idx_data_pagamento (data_pagamento),
    INDEX idx_referencia (referencia_mes)
);

CREATE TABLE IF NOT EXISTS cobrancas (
    id                                      INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    cliente_id                              INT UNSIGNED NOT NULL,
    referencia                              VARCHAR(7) NOT NULL, -- Ex: 05/2026
    valor                                   DECIMAL(10,2) NOT NULL,
    data_vencimento                         DATE NOT NULL,
    status                                  VARCHAR(20) NOT NULL DEFAULT 'Em aberto', -- Em aberto, Paga, Vencida, Cancelada
    observacoes                             TEXT DEFAULT NULL,
    criado_em                               DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em                           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_cliente_id (cliente_id),
    INDEX idx_referencia (referencia),
    INDEX idx_status (status),
    INDEX idx_vencimento (data_vencimento)
);


CREATE TABLE IF NOT EXISTS usuarios (
    id                                      INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    username                                VARCHAR(100) NOT NULL UNIQUE,
    email                                   VARCHAR(150) NOT NULL UNIQUE,
    senha                                   VARCHAR(255) NOT NULL,
    criado_em                               DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);
