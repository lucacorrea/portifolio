CREATE DATABASE IF NOT EXISTS fluxpay_saas
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE fluxpay_saas;

CREATE TABLE IF NOT EXISTS planos (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    preco DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    limite_clientes INT UNSIGNED DEFAULT NULL,
    limite_usuarios INT UNSIGNED DEFAULT NULL,
    whatsapp_ativo TINYINT(1) NOT NULL DEFAULT 1,
    leitura_comprovante TINYINT(1) NOT NULL DEFAULT 1,
    relatorios_avancados TINYINT(1) NOT NULL DEFAULT 1,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS empresas (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    plano_id INT UNSIGNED DEFAULT NULL,
    nome VARCHAR(150) NOT NULL,
    cnpj VARCHAR(20) DEFAULT NULL,
    email VARCHAR(150) DEFAULT NULL,
    telefone VARCHAR(30) DEFAULT NULL,
    status ENUM('teste','ativa','bloqueada','cancelada') NOT NULL DEFAULT 'teste',
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_empresas_cnpj (cnpj),
    INDEX idx_empresas_plano (plano_id),
    INDEX idx_empresas_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS assinaturas (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT UNSIGNED NOT NULL,
    plano_id INT UNSIGNED NOT NULL,
    status ENUM('teste','ativa','vencida','cancelada','bloqueada') NOT NULL DEFAULT 'teste',
    valor DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    data_inicio DATE NOT NULL,
    data_vencimento DATE NOT NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_assinaturas_empresa (empresa_id),
    INDEX idx_assinaturas_status (status),
    INDEX idx_assinaturas_vencimento (data_vencimento)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS usuarios (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT UNSIGNED DEFAULT NULL,
    nome VARCHAR(120) NOT NULL,
    email VARCHAR(150) NOT NULL,
    documento VARCHAR(20) DEFAULT NULL,
    documento_tipo ENUM('cpf','cnpj') DEFAULT NULL,
    senha VARCHAR(255) NOT NULL,
    tipo ENUM('platform_admin','empresa_admin','operador') NOT NULL DEFAULT 'operador',
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    ultimo_login DATETIME DEFAULT NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_usuarios_email (email),
    UNIQUE KEY uq_usuarios_documento (documento),
    INDEX idx_usuarios_empresa (empresa_id),
    INDEX idx_usuarios_tipo (tipo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS clientes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT UNSIGNED NOT NULL,
    nome VARCHAR(150) NOT NULL,
    telefone VARCHAR(30) DEFAULT NULL,
    email VARCHAR(150) DEFAULT NULL,
    documento VARCHAR(30) DEFAULT NULL,
    quantidade_veiculos INT UNSIGNED DEFAULT 0,
    valor_mensalidade DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    dia_vencimento TINYINT UNSIGNED DEFAULT 10,
    status ENUM('ativo','bloqueado','cancelado','pendente') NOT NULL DEFAULT 'ativo',
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_clientes_empresa (empresa_id),
    INDEX idx_clientes_status (status),
    INDEX idx_clientes_telefone (telefone)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cobrancas (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT UNSIGNED NOT NULL,
    cliente_id INT UNSIGNED NOT NULL,
    referencia CHAR(7) NOT NULL COMMENT 'Formato YYYY-MM',
    tipo ENUM('mensalidade','parcelada') NOT NULL DEFAULT 'mensalidade',
    descricao VARCHAR(160) DEFAULT NULL,
    grupo_parcelamento_id CHAR(32) DEFAULT NULL,
    numero_parcela SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    total_parcelas SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    valor_total DECIMAL(10,2) DEFAULT NULL,
    valor_entrada DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    valor DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    data_vencimento DATE NOT NULL,
    status ENUM('Em aberto','Paga','Vencida','Cancelada') NOT NULL DEFAULT 'Em aberto',
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_cobranca_empresa_cliente_ref (empresa_id, cliente_id, referencia),
    INDEX idx_cobrancas_empresa (empresa_id),
    INDEX idx_cobrancas_cliente (cliente_id),
    INDEX idx_cobrancas_tipo (tipo),
    INDEX idx_cobrancas_grupo (grupo_parcelamento_id),
    INDEX idx_cobrancas_status (status),
    INDEX idx_cobrancas_vencimento (data_vencimento)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pagamentos (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT UNSIGNED NOT NULL,
    cliente_id INT UNSIGNED NOT NULL,
    cobranca_id INT UNSIGNED DEFAULT NULL,
    valor_pago DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    data_pagamento DATE NOT NULL,
    forma_pagamento VARCHAR(50) DEFAULT 'PIX',
    comprovante_arquivo VARCHAR(255) DEFAULT NULL,
    observacao TEXT DEFAULT NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_pagamentos_empresa (empresa_id),
    INDEX idx_pagamentos_cliente (cliente_id),
    INDEX idx_pagamentos_cobranca (cobranca_id),
    INDEX idx_pagamentos_data (data_pagamento)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS configuracoes_automacao (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT UNSIGNED NOT NULL,
    empresa_nome VARCHAR(150) NOT NULL,
    empresa_cnpj VARCHAR(20) DEFAULT NULL,
    automacao_ativa TINYINT(1) NOT NULL DEFAULT 1,
    dia_vencimento_padrao TINYINT UNSIGNED DEFAULT 10,
    bloquear_apos_dias INT UNSIGNED DEFAULT 7,
    pix_nome_recebedor VARCHAR(150) DEFAULT NULL,
    pix_tipo_chave VARCHAR(30) DEFAULT NULL,
    pix_chave VARCHAR(150) DEFAULT NULL,
    mensagem_10_dias TEXT DEFAULT NULL,
    mensagem_5_dias TEXT DEFAULT NULL,
    mensagem_dia_vencimento TEXT DEFAULT NULL,
    mensagem_7_dias_atraso TEXT DEFAULT NULL,
    status_cliente_apos_atraso ENUM('ativo','bloqueado') DEFAULT 'bloqueado',
    gemini_api_key VARCHAR(255) DEFAULT NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_config_empresa (empresa_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS whatsapp_envios (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT UNSIGNED NOT NULL,
    cliente_id INT UNSIGNED DEFAULT NULL,
    cobranca_id INT UNSIGNED DEFAULT NULL,
    telefone VARCHAR(30) NOT NULL,
    mensagem TEXT NOT NULL,
    tipo VARCHAR(50) DEFAULT NULL,
    status_envio ENUM('pendente','enviado','falhou') NOT NULL DEFAULT 'pendente',
    retorno_api TEXT DEFAULT NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_whatsapp_empresa (empresa_id),
    INDEX idx_whatsapp_cliente (cliente_id),
    INDEX idx_whatsapp_cobranca_tipo (empresa_id, cobranca_id, tipo),
    INDEX idx_whatsapp_criado (criado_em),
    INDEX idx_whatsapp_status (status_envio)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS whatsapp_conexoes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT UNSIGNED NOT NULL,
    instancia_nome VARCHAR(100) NOT NULL,
    telefone_conectado VARCHAR(30) DEFAULT NULL,
    status ENUM('desconectado','conectando','conectado','erro') NOT NULL DEFAULT 'desconectado',
    qr_code TEXT DEFAULT NULL,
    qr_code_imagem MEDIUMTEXT DEFAULT NULL,
    pairing_code VARCHAR(40) DEFAULT NULL,
    ultimo_erro TEXT DEFAULT NULL,
    ultima_sincronizacao DATETIME DEFAULT NULL,
    conectado_em DATETIME DEFAULT NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_whatsapp_conexoes_empresa (empresa_id),
    UNIQUE KEY uq_whatsapp_conexoes_instancia (instancia_nome),
    INDEX idx_whatsapp_conexoes_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS whatsapp_comprovantes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT UNSIGNED NOT NULL,
    cliente_id INT UNSIGNED DEFAULT NULL,
    cobranca_id INT UNSIGNED DEFAULT NULL,
    mensagem_id VARCHAR(120) NOT NULL,
    remetente VARCHAR(30) NOT NULL,
    mime_type VARCHAR(80) DEFAULT NULL,
    valor_detectado DECIMAL(10,2) DEFAULT NULL,
    data_detectada DATE DEFAULT NULL,
    status_processamento ENUM('recebido','pendente','confirmado','recusado','erro') NOT NULL DEFAULT 'recebido',
    motivo TEXT DEFAULT NULL,
    resposta_ia TEXT DEFAULT NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_whatsapp_comprovante_msg (empresa_id, mensagem_id),
    INDEX idx_whatsapp_comprovantes_empresa (empresa_id),
    INDEX idx_whatsapp_comprovantes_cliente (cliente_id),
    INDEX idx_whatsapp_comprovantes_cobranca (cobranca_id),
    INDEX idx_whatsapp_comprovantes_status (status_processamento),
    INDEX idx_whatsapp_comprovantes_criado (criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS suporte_chamados (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT UNSIGNED NOT NULL,
    usuario_id INT UNSIGNED DEFAULT NULL,
    assunto VARCHAR(160) NOT NULL,
    categoria ENUM('financeiro','tecnico','acesso','automacao','outro') NOT NULL DEFAULT 'outro',
    prioridade ENUM('baixa','media','alta','urgente') NOT NULL DEFAULT 'media',
    status ENUM('aberto','em_atendimento','aguardando_empresa','resolvido','fechado') NOT NULL DEFAULT 'aberto',
    ultima_resposta_origem ENUM('empresa','admin') NOT NULL DEFAULT 'empresa',
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    fechado_em DATETIME DEFAULT NULL,
    INDEX idx_suporte_chamados_empresa (empresa_id),
    INDEX idx_suporte_chamados_status (status),
    INDEX idx_suporte_chamados_prioridade (prioridade),
    INDEX idx_suporte_chamados_criado (criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS suporte_mensagens (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    chamado_id INT UNSIGNED NOT NULL,
    empresa_id INT UNSIGNED NOT NULL,
    usuario_id INT UNSIGNED DEFAULT NULL,
    autor_tipo ENUM('empresa','admin') NOT NULL,
    autor_nome VARCHAR(120) NOT NULL,
    mensagem TEXT NOT NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_suporte_mensagens_chamado (chamado_id),
    INDEX idx_suporte_mensagens_empresa (empresa_id),
    INDEX idx_suporte_mensagens_criado (criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
