SET NAMES utf8mb4;
SET time_zone = '-04:00';
SET FOREIGN_KEY_CHECKS = 0;
 
CREATE DATABASE IF NOT EXISTS `auto` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
 use `auto`;

CREATE TABLE IF NOT EXISTS schema_migrations (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    migration VARCHAR(190) NOT NULL,
    applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_schema_migrations_migration (migration)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS usuarios (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    nome VARCHAR(160) NOT NULL,
    email VARCHAR(190) NOT NULL,
    senha_hash VARCHAR(255) NOT NULL,
    nivel ENUM('admin','gerente','atendente','mecanico','leitor') NOT NULL DEFAULT 'atendente',
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    ultimo_login_em DATETIME NULL,
    senha_alterada_em DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_usuarios_email (email),
    KEY idx_usuarios_ativo_nivel (ativo, nivel),
    KEY idx_usuarios_deleted_at (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS login_tentativas (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    identificador VARCHAR(190) NOT NULL,
    ip VARCHAR(45) NOT NULL,
    sucesso TINYINT(1) NOT NULL DEFAULT 0,
    user_agent VARCHAR(500) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_login_identificador_data (identificador, created_at),
    KEY idx_login_ip_data (ip, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS clientes (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tipo ENUM('PF','PJ') NOT NULL DEFAULT 'PF',
    nome_razao VARCHAR(190) NOT NULL,
    cpf_cnpj VARCHAR(20) NULL,
    rg_ie VARCHAR(30) NULL,
    telefone VARCHAR(30) NULL,
    whatsapp VARCHAR(30) NULL,
    email VARCHAR(190) NULL,
    cep VARCHAR(10) NULL,
    logradouro VARCHAR(190) NULL,
    numero VARCHAR(30) NULL,
    complemento VARCHAR(120) NULL,
    bairro VARCHAR(120) NULL,
    cidade VARCHAR(120) NULL,
    uf CHAR(2) NULL,
    observacoes TEXT NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    created_by BIGINT UNSIGNED NULL,
    updated_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_clientes_cpf_cnpj (cpf_cnpj),
    KEY idx_clientes_nome (nome_razao),
    KEY idx_clientes_telefone (telefone),
    KEY idx_clientes_ativo (ativo),
    KEY idx_clientes_deleted_at (deleted_at),
    CONSTRAINT fk_clientes_created_by FOREIGN KEY (created_by) REFERENCES usuarios(id) ON DELETE SET NULL,
    CONSTRAINT fk_clientes_updated_by FOREIGN KEY (updated_by) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS veiculos (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    cliente_id BIGINT UNSIGNED NOT NULL,
    placa VARCHAR(10) NOT NULL,
    marca VARCHAR(100) NOT NULL,
    modelo VARCHAR(120) NOT NULL,
    versao VARCHAR(120) NULL,
    ano_fabricacao SMALLINT UNSIGNED NULL,
    ano_modelo SMALLINT UNSIGNED NULL,
    cor VARCHAR(60) NULL,
    combustivel ENUM('gasolina','etanol','flex','diesel','gnv','eletrico','hibrido','outro') NULL,
    chassi VARCHAR(40) NULL,
    renavam VARCHAR(30) NULL,
    km_atual INT UNSIGNED NULL,
    observacoes TEXT NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_veiculos_placa (placa),
    UNIQUE KEY uq_veiculos_chassi (chassi),
    UNIQUE KEY uq_veiculos_renavam (renavam),
    KEY idx_veiculos_cliente (cliente_id),
    KEY idx_veiculos_marca_modelo (marca, modelo),
    KEY idx_veiculos_deleted_at (deleted_at),
    CONSTRAINT fk_veiculos_cliente FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mecanicos (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    usuario_id BIGINT UNSIGNED NULL,
    nome VARCHAR(160) NOT NULL,
    cpf VARCHAR(14) NULL,
    telefone VARCHAR(30) NULL,
    email VARCHAR(190) NULL,
    especialidades VARCHAR(500) NULL,
    comissao_percentual DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_mecanicos_usuario (usuario_id),
    UNIQUE KEY uq_mecanicos_cpf (cpf),
    KEY idx_mecanicos_nome (nome),
    KEY idx_mecanicos_ativo (ativo),
    CONSTRAINT fk_mecanicos_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS servicos (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    nome VARCHAR(160) NOT NULL,
    categoria VARCHAR(100) NULL,
    descricao TEXT NULL,
    tempo_estimado_min INT UNSIGNED NULL,
    valor_base DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_servicos_nome (nome),
    KEY idx_servicos_categoria (categoria),
    KEY idx_servicos_ativo (ativo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS fornecedores (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    nome_razao VARCHAR(190) NOT NULL,
    cpf_cnpj VARCHAR(20) NULL,
    contato VARCHAR(160) NULL,
    telefone VARCHAR(30) NULL,
    email VARCHAR(190) NULL,
    observacoes TEXT NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_fornecedores_cpf_cnpj (cpf_cnpj),
    KEY idx_fornecedores_nome (nome_razao),
    KEY idx_fornecedores_ativo (ativo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pecas (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    fornecedor_id BIGINT UNSIGNED NULL,
    codigo VARCHAR(80) NULL,
    codigo_barras VARCHAR(80) NULL,
    nome VARCHAR(190) NOT NULL,
    marca VARCHAR(100) NULL,
    unidade VARCHAR(20) NOT NULL DEFAULT 'UN',
    estoque_atual DECIMAL(12,3) NOT NULL DEFAULT 0.000,
    estoque_minimo DECIMAL(12,3) NOT NULL DEFAULT 0.000,
    custo_medio DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    preco_venda DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    localizacao VARCHAR(120) NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_pecas_codigo (codigo),
    UNIQUE KEY uq_pecas_codigo_barras (codigo_barras),
    KEY idx_pecas_nome (nome),
    KEY idx_pecas_fornecedor (fornecedor_id),
    KEY idx_pecas_estoque (estoque_atual, estoque_minimo),
    KEY idx_pecas_ativo (ativo),
    CONSTRAINT fk_pecas_fornecedor FOREIGN KEY (fornecedor_id) REFERENCES fornecedores(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS estoque_movimentos (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    peca_id BIGINT UNSIGNED NOT NULL,
    tipo ENUM('entrada','saida','ajuste_positivo','ajuste_negativo','devolucao') NOT NULL,
    quantidade DECIMAL(12,3) NOT NULL,
    custo_unitario DECIMAL(12,2) NULL,
    origem_tipo VARCHAR(50) NULL,
    origem_id BIGINT UNSIGNED NULL,
    observacao VARCHAR(500) NULL,
    usuario_id BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_estoque_peca_data (peca_id, created_at),
    KEY idx_estoque_origem (origem_tipo, origem_id),
    KEY idx_estoque_usuario (usuario_id),
    CONSTRAINT fk_estoque_peca FOREIGN KEY (peca_id) REFERENCES pecas(id) ON DELETE RESTRICT,
    CONSTRAINT fk_estoque_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS orcamentos (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    numero VARCHAR(30) NOT NULL,
    cliente_id BIGINT UNSIGNED NOT NULL,
    veiculo_id BIGINT UNSIGNED NOT NULL,
    status ENUM('rascunho','enviado','aguardando_aprovacao','aprovado','recusado','expirado','convertido') NOT NULL DEFAULT 'rascunho',
    validade_ate DATE NULL,
    observacoes TEXT NULL,
    subtotal_servicos DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    subtotal_pecas DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    desconto DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    total DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    aprovado_em DATETIME NULL,
    recusado_em DATETIME NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_orcamentos_numero (numero),
    KEY idx_orcamentos_cliente (cliente_id),
    KEY idx_orcamentos_veiculo (veiculo_id),
    KEY idx_orcamentos_status_data (status, created_at),
    CONSTRAINT fk_orcamentos_cliente FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE RESTRICT,
    CONSTRAINT fk_orcamentos_veiculo FOREIGN KEY (veiculo_id) REFERENCES veiculos(id) ON DELETE RESTRICT,
    CONSTRAINT fk_orcamentos_created_by FOREIGN KEY (created_by) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS orcamento_servicos (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    orcamento_id BIGINT UNSIGNED NOT NULL,
    servico_id BIGINT UNSIGNED NULL,
    descricao VARCHAR(255) NOT NULL,
    quantidade DECIMAL(12,3) NOT NULL DEFAULT 1.000,
    valor_unitario DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    total DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    PRIMARY KEY (id),
    KEY idx_orcamento_servicos_orcamento (orcamento_id),
    CONSTRAINT fk_orcamento_servicos_orcamento FOREIGN KEY (orcamento_id) REFERENCES orcamentos(id) ON DELETE CASCADE,
    CONSTRAINT fk_orcamento_servicos_servico FOREIGN KEY (servico_id) REFERENCES servicos(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS orcamento_pecas (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    orcamento_id BIGINT UNSIGNED NOT NULL,
    peca_id BIGINT UNSIGNED NULL,
    descricao VARCHAR(255) NOT NULL,
    quantidade DECIMAL(12,3) NOT NULL DEFAULT 1.000,
    valor_unitario DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    total DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    PRIMARY KEY (id),
    KEY idx_orcamento_pecas_orcamento (orcamento_id),
    CONSTRAINT fk_orcamento_pecas_orcamento FOREIGN KEY (orcamento_id) REFERENCES orcamentos(id) ON DELETE CASCADE,
    CONSTRAINT fk_orcamento_pecas_peca FOREIGN KEY (peca_id) REFERENCES pecas(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ordens_servico (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    numero VARCHAR(30) NOT NULL,
    cliente_id BIGINT UNSIGNED NOT NULL,
    veiculo_id BIGINT UNSIGNED NOT NULL,
    orcamento_id BIGINT UNSIGNED NULL,
    mecanico_responsavel_id BIGINT UNSIGNED NULL,
    status ENUM('aberta','em_diagnostico','aguardando_aprovacao','em_servico','aguardando_peca','aguardando_retirada','finalizada','cancelada') NOT NULL DEFAULT 'aberta',
    km_entrada INT UNSIGNED NULL,
    km_saida INT UNSIGNED NULL,
    data_entrada DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    previsao_entrega DATETIME NULL,
    data_finalizacao DATETIME NULL,
    data_entrega DATETIME NULL,
    relato_cliente TEXT NULL,
    diagnostico TEXT NULL,
    observacoes TEXT NULL,
    subtotal_servicos DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    subtotal_pecas DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    desconto DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    acrescimo DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    total DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    created_by BIGINT UNSIGNED NULL,
    updated_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_ordens_numero (numero),
    UNIQUE KEY uq_ordens_orcamento (orcamento_id),
    KEY idx_ordens_cliente (cliente_id),
    KEY idx_ordens_veiculo (veiculo_id),
    KEY idx_ordens_mecanico (mecanico_responsavel_id),
    KEY idx_ordens_status_entrada (status, data_entrada),
    CONSTRAINT fk_ordens_cliente FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE RESTRICT,
    CONSTRAINT fk_ordens_veiculo FOREIGN KEY (veiculo_id) REFERENCES veiculos(id) ON DELETE RESTRICT,
    CONSTRAINT fk_ordens_orcamento FOREIGN KEY (orcamento_id) REFERENCES orcamentos(id) ON DELETE SET NULL,
    CONSTRAINT fk_ordens_mecanico FOREIGN KEY (mecanico_responsavel_id) REFERENCES mecanicos(id) ON DELETE SET NULL,
    CONSTRAINT fk_ordens_created_by FOREIGN KEY (created_by) REFERENCES usuarios(id) ON DELETE SET NULL,
    CONSTRAINT fk_ordens_updated_by FOREIGN KEY (updated_by) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ordem_servico_mecanicos (
    ordem_servico_id BIGINT UNSIGNED NOT NULL,
    mecanico_id BIGINT UNSIGNED NOT NULL,
    papel VARCHAR(80) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (ordem_servico_id, mecanico_id),
    KEY idx_os_mecanicos_mecanico (mecanico_id),
    CONSTRAINT fk_os_mecanicos_os FOREIGN KEY (ordem_servico_id) REFERENCES ordens_servico(id) ON DELETE CASCADE,
    CONSTRAINT fk_os_mecanicos_mecanico FOREIGN KEY (mecanico_id) REFERENCES mecanicos(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ordem_servico_servicos (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    ordem_servico_id BIGINT UNSIGNED NOT NULL,
    servico_id BIGINT UNSIGNED NULL,
    mecanico_id BIGINT UNSIGNED NULL,
    descricao VARCHAR(255) NOT NULL,
    quantidade DECIMAL(12,3) NOT NULL DEFAULT 1.000,
    valor_unitario DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    total DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    status ENUM('pendente','em_execucao','concluido','cancelado') NOT NULL DEFAULT 'pendente',
    iniciado_em DATETIME NULL,
    concluido_em DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_os_servicos_os (ordem_servico_id),
    KEY idx_os_servicos_mecanico (mecanico_id),
    CONSTRAINT fk_os_servicos_os FOREIGN KEY (ordem_servico_id) REFERENCES ordens_servico(id) ON DELETE CASCADE,
    CONSTRAINT fk_os_servicos_servico FOREIGN KEY (servico_id) REFERENCES servicos(id) ON DELETE SET NULL,
    CONSTRAINT fk_os_servicos_mecanico FOREIGN KEY (mecanico_id) REFERENCES mecanicos(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ordem_servico_pecas (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    ordem_servico_id BIGINT UNSIGNED NOT NULL,
    peca_id BIGINT UNSIGNED NULL,
    descricao VARCHAR(255) NOT NULL,
    quantidade DECIMAL(12,3) NOT NULL DEFAULT 1.000,
    custo_unitario DECIMAL(12,2) NULL,
    valor_unitario DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    total DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    baixada_estoque TINYINT(1) NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    KEY idx_os_pecas_os (ordem_servico_id),
    KEY idx_os_pecas_peca (peca_id),
    CONSTRAINT fk_os_pecas_os FOREIGN KEY (ordem_servico_id) REFERENCES ordens_servico(id) ON DELETE CASCADE,
    CONSTRAINT fk_os_pecas_peca FOREIGN KEY (peca_id) REFERENCES pecas(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ordem_servico_historico (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    ordem_servico_id BIGINT UNSIGNED NOT NULL,
    usuario_id BIGINT UNSIGNED NULL,
    status_anterior VARCHAR(50) NULL,
    status_novo VARCHAR(50) NULL,
    acao VARCHAR(120) NOT NULL,
    observacao TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_os_historico_os_data (ordem_servico_id, created_at),
    CONSTRAINT fk_os_historico_os FOREIGN KEY (ordem_servico_id) REFERENCES ordens_servico(id) ON DELETE CASCADE,
    CONSTRAINT fk_os_historico_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pagamentos (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    ordem_servico_id BIGINT UNSIGNED NOT NULL,
    forma ENUM('dinheiro','pix','cartao_credito','cartao_debito','transferencia','boleto','outro') NOT NULL,
    status ENUM('pendente','pago','parcial','estornado','cancelado') NOT NULL DEFAULT 'pendente',
    valor DECIMAL(12,2) NOT NULL,
    pago_em DATETIME NULL,
    parcelas SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    referencia VARCHAR(120) NULL,
    observacao VARCHAR(500) NULL,
    usuario_id BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_pagamentos_os (ordem_servico_id),
    KEY idx_pagamentos_status_data (status, pago_em),
    CONSTRAINT fk_pagamentos_os FOREIGN KEY (ordem_servico_id) REFERENCES ordens_servico(id) ON DELETE RESTRICT,
    CONSTRAINT fk_pagamentos_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS configuracoes (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    chave VARCHAR(120) NOT NULL,
    valor TEXT NULL,
    tipo ENUM('string','integer','decimal','boolean','json') NOT NULL DEFAULT 'string',
    descricao VARCHAR(255) NULL,
    updated_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_configuracoes_chave (chave),
    CONSTRAINT fk_configuracoes_updated_by FOREIGN KEY (updated_by) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS auditoria (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    usuario_id BIGINT UNSIGNED NULL,
    entidade VARCHAR(80) NOT NULL,
    entidade_id BIGINT UNSIGNED NULL,
    acao VARCHAR(60) NOT NULL,
    dados_anteriores LONGTEXT NULL,
    dados_novos LONGTEXT NULL,
    ip VARCHAR(45) NULL,
    user_agent VARCHAR(500) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_auditoria_entidade (entidade, entidade_id),
    KEY idx_auditoria_usuario_data (usuario_id, created_at),
    KEY idx_auditoria_data (created_at),
    CONSTRAINT fk_auditoria_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO configuracoes (chave, valor, tipo, descricao)
VALUES
    ('empresa.nome', 'Bianka Oficina Mecânica', 'string', 'Nome exibido da oficina'),
    ('empresa.cnpj', '', 'string', 'CNPJ da oficina'),
    ('empresa.telefone', '', 'string', 'Telefone da oficina'),
    ('empresa.endereco', '', 'string', 'Endereço da oficina'),
    ('empresa.cidade', 'Coari', 'string', 'Cidade da oficina'),
    ('empresa.uf', 'AM', 'string', 'UF da oficina'),
    ('empresa.timezone', 'America/Manaus', 'string', 'Fuso horário da aplicação'),
    ('os.prefixo', 'OS', 'string', 'Prefixo da ordem de serviço'),
    ('orcamento.prefixo', 'ORC', 'string', 'Prefixo do orçamento'),
    ('os.permitir_desconto', '1', 'boolean', 'Permite descontos em OS e orçamentos'),
    ('os.exigir_mecanico', '0', 'boolean', 'Exige mecânico responsável na OS'),
    ('estoque.controlar_minimo', '1', 'boolean', 'Controla e destaca estoque mínimo'),
    ('estoque.permitir_negativo', '0', 'boolean', 'Permite saldo de estoque negativo')
ON DUPLICATE KEY UPDATE chave = VALUES(chave);

INSERT INTO schema_migrations (migration)
VALUES
    ('20260902_001_initial_schema'),
    ('20260903_002_biauto_complete_schema')
ON DUPLICATE KEY UPDATE migration = VALUES(migration);

SET FOREIGN_KEY_CHECKS = 1;
