
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS perfis (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    descricao VARCHAR(255) NULL,
    protegido TINYINT(1) NOT NULL DEFAULT 0,
    status ENUM('ativo', 'inativo') NOT NULL DEFAULT 'ativo',
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uk_perfis_nome (nome),
    KEY idx_perfis_status (status)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS usuarios (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    perfil_id INT UNSIGNED NOT NULL,
    nome VARCHAR(150) NOT NULL,
    usuario VARCHAR(80) NOT NULL,
    email VARCHAR(150) NOT NULL,
    senha_hash VARCHAR(255) NOT NULL,
    telefone VARCHAR(30) NULL,

    status ENUM('ativo', 'inativo', 'bloqueado')
        NOT NULL DEFAULT 'ativo',

    deve_alterar_senha TINYINT(1) NOT NULL DEFAULT 0,
    tentativas_falhas INT UNSIGNED NOT NULL DEFAULT 0,
    bloqueado_ate DATETIME NULL,
    ultimo_acesso DATETIME NULL,
    senha_alterada_em DATETIME NULL,

    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uk_usuarios_usuario (usuario),
    UNIQUE KEY uk_usuarios_email (email),
    KEY idx_usuarios_perfil (perfil_id),
    KEY idx_usuarios_status (status),
    KEY idx_usuarios_bloqueado_ate (bloqueado_ate),

    CONSTRAINT fk_usuarios_perfil
        FOREIGN KEY (perfil_id)
        REFERENCES perfis(id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS permissoes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    grupo VARCHAR(100) NOT NULL,
    modulo VARCHAR(100) NOT NULL,
    codigo VARCHAR(150) NOT NULL,
    nome VARCHAR(150) NOT NULL,
    descricao VARCHAR(255) NULL,
    ordem INT UNSIGNED NOT NULL DEFAULT 0,
    status ENUM('ativo', 'inativo') NOT NULL DEFAULT 'ativo',
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY uk_permissoes_codigo (codigo),
    KEY idx_permissoes_grupo (grupo),
    KEY idx_permissoes_modulo (modulo),
    KEY idx_permissoes_status (status),
    KEY idx_permissoes_ordem (ordem)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS perfil_permissoes (
    perfil_id INT UNSIGNED NOT NULL,
    permissao_id INT UNSIGNED NOT NULL,
    concedido_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (perfil_id, permissao_id),

    KEY idx_perfil_permissoes_permissao (permissao_id),

    CONSTRAINT fk_perfil_permissoes_perfil
        FOREIGN KEY (perfil_id)
        REFERENCES perfis(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,

    CONSTRAINT fk_perfil_permissoes_permissao
        FOREIGN KEY (permissao_id)
        REFERENCES permissoes(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;


-- -----------------------------------------------------------------------------
-- Migration: 002_remove_supplier_carrier_permissions.sql
-- -----------------------------------------------------------------------------
START TRANSACTION;

DELETE pp
FROM perfil_permissoes pp
INNER JOIN permissoes p
    ON p.id = pp.permissao_id
WHERE p.modulo IN ('fornecedor', 'transportadora');

DELETE FROM permissoes
WHERE modulo IN ('fornecedor', 'transportadora');

DELETE pp
FROM perfil_permissoes pp
INNER JOIN permissoes p
    ON p.id = pp.permissao_id
WHERE p.codigo IN (
    'funcionario.desativar',
    'funcionario.visualizar_produtividade',
    'funcionario.visualizar_comissao'
);

DELETE FROM permissoes
WHERE codigo IN (
    'funcionario.desativar',
    'funcionario.visualizar_produtividade',
    'funcionario.visualizar_comissao'
);

COMMIT;


-- -----------------------------------------------------------------------------
-- Migration: 003_create_funcionarios_table.sql
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS funcionarios (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    codigo VARCHAR(20) NULL,
    nome VARCHAR(150) NOT NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uk_funcionarios_codigo (codigo),
    KEY idx_funcionarios_nome (nome)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;


-- -----------------------------------------------------------------------------
-- Migration: 004_create_catalog_tables.sql
-- -----------------------------------------------------------------------------
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


-- -----------------------------------------------------------------------------
-- Migration: 005_create_clients_budgets_tables.sql
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS clientes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    codigo VARCHAR(20) NULL,
    tipo_pessoa ENUM('fisica', 'juridica') NOT NULL DEFAULT 'fisica',
    nome VARCHAR(150) NOT NULL,
    documento VARCHAR(20) NULL,
    telefone VARCHAR(30) NULL,
    whatsapp VARCHAR(30) NULL,
    email VARCHAR(150) NULL,
    endereco VARCHAR(150) NULL,
    numero VARCHAR(30) NULL,
    complemento VARCHAR(100) NULL,
    bairro VARCHAR(100) NULL,
    cidade VARCHAR(100) NULL,
    uf CHAR(2) NULL,
    cep VARCHAR(10) NULL,
    observacoes TEXT NULL,
    status ENUM('ativo', 'inativo') NOT NULL DEFAULT 'ativo',
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_clientes_codigo (codigo),
    UNIQUE KEY uk_clientes_documento (documento),
    KEY idx_clientes_nome (nome),
    KEY idx_clientes_tipo_pessoa (tipo_pessoa),
    KEY idx_clientes_cidade (cidade),
    KEY idx_clientes_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS orcamentos (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    numero VARCHAR(20) NULL,
    cliente_id INT UNSIGNED NOT NULL,
    data_emissao DATE NOT NULL,
    validade DATE NOT NULL,
    status ENUM('rascunho', 'enviado', 'aguardando_aprovacao', 'aprovado', 'recusado') NOT NULL DEFAULT 'rascunho',
    observacoes TEXT NULL,
    motivo_recusa TEXT NULL,
    subtotal_servicos DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    subtotal_produtos DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    subtotal_outros DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    desconto DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    acrescimo DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    total DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    aprovado_em DATETIME NULL,
    recusado_em DATETIME NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_orcamentos_numero (numero),
    KEY idx_orcamentos_cliente (cliente_id),
    KEY idx_orcamentos_emissao (data_emissao),
    KEY idx_orcamentos_validade (validade),
    KEY idx_orcamentos_status (status),
    CONSTRAINT fk_orcamentos_cliente FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS orcamento_itens (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    orcamento_id INT UNSIGNED NOT NULL,
    tipo ENUM('servico', 'produto', 'outro') NOT NULL,
    referencia_id INT UNSIGNED NULL,
    descricao VARCHAR(255) NOT NULL,
    unidade VARCHAR(20) NOT NULL DEFAULT 'un',
    quantidade DECIMAL(12,3) NOT NULL DEFAULT 1.000,
    valor_unitario DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    desconto DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    subtotal DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    ordem INT UNSIGNED NOT NULL DEFAULT 0,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_orcamento_itens_orcamento (orcamento_id),
    KEY idx_orcamento_itens_tipo (tipo),
    KEY idx_orcamento_itens_referencia (referencia_id),
    KEY idx_orcamento_itens_ordem (ordem),
    CONSTRAINT fk_orcamento_itens_orcamento FOREIGN KEY (orcamento_id) REFERENCES orcamentos(id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- -----------------------------------------------------------------------------
-- Migration: 006_remove_budget_responsible_prepare_service_orders.sql
-- -----------------------------------------------------------------------------
SET @budget_responsible_fk := (
    SELECT CONSTRAINT_NAME
      FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'orcamentos'
       AND COLUMN_NAME = 'responsavel_id'
       AND REFERENCED_TABLE_NAME IS NOT NULL
     LIMIT 1
);

SET @drop_budget_responsible_fk := IF(
    @budget_responsible_fk IS NULL,
    'SELECT 1',
    CONCAT('ALTER TABLE orcamentos DROP FOREIGN KEY `', REPLACE(@budget_responsible_fk, '`', '``'), '`')
);
PREPARE stmt FROM @drop_budget_responsible_fk;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @budget_responsible_index := (
    SELECT INDEX_NAME
      FROM INFORMATION_SCHEMA.STATISTICS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'orcamentos'
       AND COLUMN_NAME = 'responsavel_id'
       AND INDEX_NAME <> 'PRIMARY'
     ORDER BY INDEX_NAME = 'idx_orcamentos_responsavel' DESC, INDEX_NAME
     LIMIT 1
);

SET @drop_budget_responsible_index := IF(
    @budget_responsible_index IS NULL,
    'SELECT 1',
    CONCAT('ALTER TABLE orcamentos DROP INDEX `', REPLACE(@budget_responsible_index, '`', '``'), '`')
);
PREPARE stmt FROM @drop_budget_responsible_index;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @budget_responsible_column_exists := (
    SELECT COUNT(*)
      FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'orcamentos'
       AND COLUMN_NAME = 'responsavel_id'
);

SET @drop_budget_responsible_column := IF(
    @budget_responsible_column_exists = 0,
    'SELECT 1',
    'ALTER TABLE orcamentos DROP COLUMN responsavel_id'
);
PREPARE stmt FROM @drop_budget_responsible_column;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS ordens_servico (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    numero VARCHAR(20) NULL,
    cliente_id INT UNSIGNED NOT NULL,
    orcamento_id INT UNSIGNED NULL,
    funcionario_principal_id INT UNSIGNED NULL,
    funcionario_apoio_id INT UNSIGNED NULL,
    agendado_inicio DATETIME NULL,
    agendado_fim DATETIME NULL,
    status ENUM('rascunho', 'aberta', 'aguardando_agendamento', 'agendada', 'em_deslocamento', 'em_execucao', 'aguardando_peca', 'finalizada', 'cancelada') NOT NULL DEFAULT 'aberta',
    prioridade ENUM('baixa', 'media', 'alta', 'urgente') NOT NULL DEFAULT 'media',
    observacoes TEXT NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_ordens_servico_numero (numero),
    KEY idx_os_cliente (cliente_id),
    KEY idx_os_orcamento (orcamento_id),
    KEY idx_os_status (status),
    KEY idx_os_prioridade (prioridade),
    KEY idx_os_funcionario_principal (funcionario_principal_id),
    KEY idx_os_funcionario_apoio (funcionario_apoio_id),
    KEY idx_os_agendado_inicio (agendado_inicio),
    KEY idx_os_agendado_fim (agendado_fim),
    CONSTRAINT fk_os_cliente FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_os_orcamento FOREIGN KEY (orcamento_id) REFERENCES orcamentos(id) ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_os_funcionario_principal FOREIGN KEY (funcionario_principal_id) REFERENCES funcionarios(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_os_funcionario_apoio FOREIGN KEY (funcionario_apoio_id) REFERENCES funcionarios(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- -----------------------------------------------------------------------------
-- Migration: 007_complete_service_orders_agenda_week.sql
-- -----------------------------------------------------------------------------
SET @column_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ordens_servico' AND COLUMN_NAME = 'equipamento_tipo');
SET @sql := IF(@column_exists = 0, 'ALTER TABLE ordens_servico ADD COLUMN equipamento_tipo VARCHAR(100) NULL AFTER prioridade', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @column_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ordens_servico' AND COLUMN_NAME = 'equipamento_marca');
SET @sql := IF(@column_exists = 0, 'ALTER TABLE ordens_servico ADD COLUMN equipamento_marca VARCHAR(100) NULL AFTER equipamento_tipo', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @column_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ordens_servico' AND COLUMN_NAME = 'equipamento_modelo');
SET @sql := IF(@column_exists = 0, 'ALTER TABLE ordens_servico ADD COLUMN equipamento_modelo VARCHAR(100) NULL AFTER equipamento_marca', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @column_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ordens_servico' AND COLUMN_NAME = 'equipamento_capacidade');
SET @sql := IF(@column_exists = 0, 'ALTER TABLE ordens_servico ADD COLUMN equipamento_capacidade VARCHAR(100) NULL AFTER equipamento_modelo', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @column_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ordens_servico' AND COLUMN_NAME = 'equipamento_numero_serie');
SET @sql := IF(@column_exists = 0, 'ALTER TABLE ordens_servico ADD COLUMN equipamento_numero_serie VARCHAR(100) NULL AFTER equipamento_capacidade', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @column_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ordens_servico' AND COLUMN_NAME = 'equipamento_ambiente');
SET @sql := IF(@column_exists = 0, 'ALTER TABLE ordens_servico ADD COLUMN equipamento_ambiente VARCHAR(100) NULL AFTER equipamento_numero_serie', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @column_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ordens_servico' AND COLUMN_NAME = 'equipamento_local');
SET @sql := IF(@column_exists = 0, 'ALTER TABLE ordens_servico ADD COLUMN equipamento_local VARCHAR(150) NULL AFTER equipamento_ambiente', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @column_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ordens_servico' AND COLUMN_NAME = 'problema_relatado');
SET @sql := IF(@column_exists = 0, 'ALTER TABLE ordens_servico ADD COLUMN problema_relatado TEXT NULL AFTER equipamento_local', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @column_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ordens_servico' AND COLUMN_NAME = 'problema_identificado');
SET @sql := IF(@column_exists = 0, 'ALTER TABLE ordens_servico ADD COLUMN problema_identificado TEXT NULL AFTER problema_relatado', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @column_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ordens_servico' AND COLUMN_NAME = 'diagnostico');
SET @sql := IF(@column_exists = 0, 'ALTER TABLE ordens_servico ADD COLUMN diagnostico TEXT NULL AFTER problema_identificado', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @column_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ordens_servico' AND COLUMN_NAME = 'solucao');
SET @sql := IF(@column_exists = 0, 'ALTER TABLE ordens_servico ADD COLUMN solucao TEXT NULL AFTER diagnostico', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @column_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ordens_servico' AND COLUMN_NAME = 'recomendacao');
SET @sql := IF(@column_exists = 0, 'ALTER TABLE ordens_servico ADD COLUMN recomendacao TEXT NULL AFTER solucao', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @column_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ordens_servico' AND COLUMN_NAME = 'observacoes_internas');
SET @sql := IF(@column_exists = 0, 'ALTER TABLE ordens_servico ADD COLUMN observacoes_internas TEXT NULL AFTER recomendacao', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @column_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ordens_servico' AND COLUMN_NAME = 'subtotal_servicos');
SET @sql := IF(@column_exists = 0, 'ALTER TABLE ordens_servico ADD COLUMN subtotal_servicos DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER observacoes', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @column_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ordens_servico' AND COLUMN_NAME = 'subtotal_produtos');
SET @sql := IF(@column_exists = 0, 'ALTER TABLE ordens_servico ADD COLUMN subtotal_produtos DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER subtotal_servicos', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @column_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ordens_servico' AND COLUMN_NAME = 'subtotal_outros');
SET @sql := IF(@column_exists = 0, 'ALTER TABLE ordens_servico ADD COLUMN subtotal_outros DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER subtotal_produtos', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @column_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ordens_servico' AND COLUMN_NAME = 'desconto');
SET @sql := IF(@column_exists = 0, 'ALTER TABLE ordens_servico ADD COLUMN desconto DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER subtotal_outros', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @column_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ordens_servico' AND COLUMN_NAME = 'acrescimo');
SET @sql := IF(@column_exists = 0, 'ALTER TABLE ordens_servico ADD COLUMN acrescimo DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER desconto', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @column_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ordens_servico' AND COLUMN_NAME = 'total');
SET @sql := IF(@column_exists = 0, 'ALTER TABLE ordens_servico ADD COLUMN total DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER acrescimo', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @column_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ordens_servico' AND COLUMN_NAME = 'finalizada_em');
SET @sql := IF(@column_exists = 0, 'ALTER TABLE ordens_servico ADD COLUMN finalizada_em DATETIME NULL AFTER total', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @column_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ordens_servico' AND COLUMN_NAME = 'cancelada_em');
SET @sql := IF(@column_exists = 0, 'ALTER TABLE ordens_servico ADD COLUMN cancelada_em DATETIME NULL AFTER finalizada_em', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS ordem_servico_itens (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ordem_servico_id INT UNSIGNED NOT NULL,
    tipo ENUM('servico', 'produto', 'outro') NOT NULL,
    referencia_id INT UNSIGNED NULL,
    descricao VARCHAR(255) NOT NULL,
    unidade VARCHAR(20) NOT NULL DEFAULT 'un',
    quantidade DECIMAL(12,3) NOT NULL DEFAULT 1.000,
    valor_unitario DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    desconto DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    subtotal DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    ordem INT UNSIGNED NOT NULL DEFAULT 0,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_os_itens_ordem_servico (ordem_servico_id),
    KEY idx_os_itens_tipo (tipo),
    KEY idx_os_itens_referencia (referencia_id),
    KEY idx_os_itens_ordem (ordem),
    CONSTRAINT fk_os_itens_ordem_servico FOREIGN KEY (ordem_servico_id) REFERENCES ordens_servico(id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS agenda_lembretes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    titulo VARCHAR(150) NOT NULL,
    descricao TEXT NULL,
    inicio DATETIME NOT NULL,
    fim DATETIME NULL,
    status ENUM('ativo', 'cancelado') NOT NULL DEFAULT 'ativo',
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_agenda_lembretes_inicio (inicio),
    KEY idx_agenda_lembretes_fim (fim),
    KEY idx_agenda_lembretes_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- -----------------------------------------------------------------------------
-- Migration: 008_service_order_execution_financial_flow.sql
-- -----------------------------------------------------------------------------
-- Migration 008 - Fluxo operacional, estoque, financeiro e comprovante de OS.
-- Aplicacao manual na hospedagem, apos backup validado.
-- Ordem sugerida: 001 a 007 ja aplicadas, depois executar este arquivo uma unica vez.
-- Compatibilidade alvo: MariaDB/MySQL compartilhado, InnoDB, utf8mb4.
-- Observacao: nao remove colunas legadas de equipe em ordens_servico.

SET NAMES utf8mb4;

ALTER TABLE ordem_servico_itens
    ADD COLUMN IF NOT EXISTS origem ENUM('orcamento', 'manual', 'finalizacao') NOT NULL DEFAULT 'manual' AFTER tipo,
    ADD COLUMN IF NOT EXISTS orcamento_item_id INT UNSIGNED NULL AFTER referencia_id,
    ADD KEY IF NOT EXISTS idx_os_itens_origem (origem),
    ADD KEY IF NOT EXISTS idx_os_itens_orcamento_item (orcamento_item_id);

ALTER TABLE ordens_servico
    ADD COLUMN IF NOT EXISTS orcamento_liberado TINYINT(1) NOT NULL DEFAULT 0 AFTER orcamento_id,
    ADD COLUMN IF NOT EXISTS ordem_substituta_id INT UNSIGNED NULL AFTER orcamento_liberado,
    ADD COLUMN IF NOT EXISTS valor_aprovado_orcamento DECIMAL(12,2) NULL AFTER total,
    ADD KEY IF NOT EXISTS idx_os_orcamento_operacional (orcamento_id, status, orcamento_liberado),
    ADD KEY IF NOT EXISTS idx_os_substituta (ordem_substituta_id);

CREATE TABLE IF NOT EXISTS ordem_servico_funcionarios (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ordem_servico_id INT UNSIGNED NOT NULL,
    funcionario_id INT UNSIGNED NOT NULL,
    funcao VARCHAR(80) NOT NULL DEFAULT 'Técnico',
    principal TINYINT(1) NOT NULL DEFAULT 0,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    adicionado_por INT UNSIGNED NULL,
    adicionado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    removido_por INT UNSIGNED NULL,
    removido_em DATETIME NULL,
    UNIQUE KEY uq_os_funcionario (ordem_servico_id, funcionario_id),
    KEY idx_os_funcionarios_os (ordem_servico_id, ativo),
    KEY idx_os_funcionarios_funcionario (funcionario_id, ativo),
    KEY idx_os_funcionarios_principal (ordem_servico_id, principal, ativo),
    CONSTRAINT fk_os_funcionarios_os FOREIGN KEY (ordem_servico_id) REFERENCES ordens_servico(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_os_funcionarios_funcionario FOREIGN KEY (funcionario_id) REFERENCES funcionarios(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_os_funcionarios_add_user FOREIGN KEY (adicionado_por) REFERENCES usuarios(id) ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_os_funcionarios_remove_user FOREIGN KEY (removido_por) REFERENCES usuarios(id) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO ordem_servico_funcionarios (ordem_servico_id, funcionario_id, funcao, principal, ativo)
SELECT os.id, os.funcionario_principal_id, 'Responsável técnico', 1, 1
  FROM ordens_servico os
 WHERE os.funcionario_principal_id IS NOT NULL
   AND NOT EXISTS (
       SELECT 1 FROM ordem_servico_funcionarios osf
        WHERE osf.ordem_servico_id = os.id
          AND osf.funcionario_id = os.funcionario_principal_id
   );

INSERT INTO ordem_servico_funcionarios (ordem_servico_id, funcionario_id, funcao, principal, ativo)
SELECT os.id, os.funcionario_apoio_id, 'Técnico', 0, 1
  FROM ordens_servico os
 WHERE os.funcionario_apoio_id IS NOT NULL
   AND os.funcionario_apoio_id <> COALESCE(os.funcionario_principal_id, 0)
   AND NOT EXISTS (
       SELECT 1 FROM ordem_servico_funcionarios osf
        WHERE osf.ordem_servico_id = os.id
          AND osf.funcionario_id = os.funcionario_apoio_id
   );

CREATE TABLE IF NOT EXISTS ordem_servico_cancelamentos (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ordem_servico_id INT UNSIGNED NOT NULL,
    opcao ENUM('definitivo', 'liberar_orcamento', 'criar_substituta') NOT NULL,
    motivo VARCHAR(150) NOT NULL,
    observacao TEXT NULL,
    orcamento_liberado TINYINT(1) NOT NULL DEFAULT 0,
    ordem_substituta_id INT UNSIGNED NULL,
    cancelado_por INT UNSIGNED NOT NULL,
    cancelado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_os_cancelamentos_os (ordem_servico_id),
    KEY idx_os_cancelamentos_substituta (ordem_substituta_id),
    CONSTRAINT fk_os_cancelamentos_os FOREIGN KEY (ordem_servico_id) REFERENCES ordens_servico(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_os_cancelamentos_substituta FOREIGN KEY (ordem_substituta_id) REFERENCES ordens_servico(id) ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_os_cancelamentos_usuario FOREIGN KEY (cancelado_por) REFERENCES usuarios(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ordem_servico_finalizacoes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ordem_servico_id INT UNSIGNED NOT NULL,
    ativa TINYINT(1) NOT NULL DEFAULT 1,
    subtotal_servicos DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    subtotal_produtos DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    subtotal_outros DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    desconto DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    acrescimo DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    total_executado DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    observacao TEXT NULL,
    finalizado_por INT UNSIGNED NOT NULL,
    finalizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_os_finalizacoes_os (ordem_servico_id, ativa),
    CONSTRAINT fk_os_finalizacoes_os FOREIGN KEY (ordem_servico_id) REFERENCES ordens_servico(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_os_finalizacoes_usuario FOREIGN KEY (finalizado_por) REFERENCES usuarios(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ordem_servico_execucao_itens (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ordem_servico_id INT UNSIGNED NOT NULL,
    ordem_servico_item_id INT UNSIGNED NULL,
    tipo ENUM('servico', 'produto', 'outro') NOT NULL,
    referencia_id INT UNSIGNED NULL,
    descricao VARCHAR(255) NOT NULL,
    unidade VARCHAR(20) NOT NULL DEFAULT 'un',
    quantidade DECIMAL(12,3) NOT NULL DEFAULT 1.000,
    valor_unitario DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    desconto DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    subtotal DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    adicional TINYINT(1) NOT NULL DEFAULT 0,
    ordem INT UNSIGNED NOT NULL DEFAULT 0,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_os_execucao_itens_os (ordem_servico_id),
    KEY idx_os_execucao_itens_item (ordem_servico_item_id),
    KEY idx_os_execucao_itens_tipo (tipo),
    CONSTRAINT fk_os_execucao_itens_os FOREIGN KEY (ordem_servico_id) REFERENCES ordens_servico(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_os_execucao_itens_item FOREIGN KEY (ordem_servico_item_id) REFERENCES ordem_servico_itens(id) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS estoque_autorizacoes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ordem_servico_id INT UNSIGNED NOT NULL,
    produto_id INT UNSIGNED NOT NULL,
    quantidade_solicitada DECIMAL(12,3) NOT NULL,
    saldo_disponivel DECIMAL(12,3) NOT NULL,
    quantidade_excedente DECIMAL(12,3) NOT NULL,
    solicitado_por INT UNSIGNED NOT NULL,
    autorizado_por INT UNSIGNED NOT NULL,
    motivo VARCHAR(255) NOT NULL,
    autorizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_estoque_aut_os (ordem_servico_id),
    KEY idx_estoque_aut_produto (produto_id),
    CONSTRAINT fk_estoque_aut_os FOREIGN KEY (ordem_servico_id) REFERENCES ordens_servico(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_estoque_aut_produto FOREIGN KEY (produto_id) REFERENCES produtos(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_estoque_aut_solicitado FOREIGN KEY (solicitado_por) REFERENCES usuarios(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_estoque_aut_autorizado FOREIGN KEY (autorizado_por) REFERENCES usuarios(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS estoque_movimentacoes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    produto_id INT UNSIGNED NOT NULL,
    ordem_servico_id INT UNSIGNED NULL,
    tipo ENUM('entrada', 'saida_os', 'ajuste', 'estorno') NOT NULL,
    quantidade DECIMAL(12,3) NOT NULL,
    saldo_anterior DECIMAL(12,3) NOT NULL,
    saldo_posterior DECIMAL(12,3) NOT NULL,
    autorizacao_id INT UNSIGNED NULL,
    usuario_id INT UNSIGNED NOT NULL,
    observacao VARCHAR(255) NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_estoque_mov_produto (produto_id, criado_em),
    KEY idx_estoque_mov_os (ordem_servico_id),
    CONSTRAINT fk_estoque_mov_produto FOREIGN KEY (produto_id) REFERENCES produtos(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_estoque_mov_os FOREIGN KEY (ordem_servico_id) REFERENCES ordens_servico(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_estoque_mov_aut FOREIGN KEY (autorizacao_id) REFERENCES estoque_autorizacoes(id) ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_estoque_mov_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS caixa_movimentacoes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tipo ENUM('entrada', 'saida', 'estorno_entrada', 'estorno_saida') NOT NULL,
    origem_tipo VARCHAR(40) NOT NULL,
    origem_id INT UNSIGNED NULL,
    descricao VARCHAR(255) NOT NULL,
    forma_pagamento ENUM('dinheiro', 'pix', 'cartao_debito', 'cartao_credito', 'transferencia', 'outro') NULL,
    valor DECIMAL(12,2) NOT NULL,
    data_movimento DATETIME NOT NULL,
    usuario_id INT UNSIGNED NOT NULL,
    estornado_de_id INT UNSIGNED NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_caixa_data (data_movimento),
    KEY idx_caixa_origem (origem_tipo, origem_id),
    KEY idx_caixa_usuario (usuario_id),
    CONSTRAINT fk_caixa_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_caixa_estorno FOREIGN KEY (estornado_de_id) REFERENCES caixa_movimentacoes(id) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ordem_servico_pagamentos (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ordem_servico_id INT UNSIGNED NOT NULL,
    valor DECIMAL(12,2) NOT NULL,
    forma_pagamento ENUM('dinheiro', 'pix', 'cartao_debito', 'cartao_credito', 'transferencia', 'outro') NOT NULL,
    recebido_em DATETIME NOT NULL,
    observacao VARCHAR(255) NULL,
    status ENUM('ativo', 'estornado') NOT NULL DEFAULT 'ativo',
    registrado_por INT UNSIGNED NOT NULL,
    caixa_movimentacao_id INT UNSIGNED NULL,
    estornado_em DATETIME NULL,
    estornado_por INT UNSIGNED NULL,
    motivo_estorno VARCHAR(255) NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_os_pagamentos_os (ordem_servico_id, status),
    KEY idx_os_pagamentos_caixa (caixa_movimentacao_id),
    CONSTRAINT fk_os_pagamentos_os FOREIGN KEY (ordem_servico_id) REFERENCES ordens_servico(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_os_pagamentos_usuario FOREIGN KEY (registrado_por) REFERENCES usuarios(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_os_pagamentos_caixa FOREIGN KEY (caixa_movimentacao_id) REFERENCES caixa_movimentacoes(id) ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_os_pagamentos_estorno_usuario FOREIGN KEY (estornado_por) REFERENCES usuarios(id) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS contas_receber (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ordem_servico_id INT UNSIGNED NOT NULL,
    valor_total DECIMAL(12,2) NOT NULL,
    valor_recebido DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    saldo DECIMAL(12,2) NOT NULL,
    vencimento_em DATE NULL,
    proximo_lembrete_em DATE NULL,
    status ENUM('pendente', 'parcial', 'vencida', 'paga', 'cancelada') NOT NULL DEFAULT 'pendente',
    observacao TEXT NULL,
    criado_por INT UNSIGNED NOT NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_contas_receber_os (ordem_servico_id),
    KEY idx_contas_receber_status_vencimento (status, vencimento_em),
    KEY idx_contas_receber_lembrete (proximo_lembrete_em),
    CONSTRAINT fk_contas_receber_os FOREIGN KEY (ordem_servico_id) REFERENCES ordens_servico(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_contas_receber_usuario FOREIGN KEY (criado_por) REFERENCES usuarios(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS contas_receber_eventos (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    conta_receber_id INT UNSIGNED NOT NULL,
    tipo ENUM('criacao', 'pagamento', 'estorno', 'contato', 'whatsapp', 'lembrete', 'alteracao_vencimento', 'negociacao', 'observacao', 'quitacao') NOT NULL,
    descricao TEXT NOT NULL,
    valor DECIMAL(12,2) NULL,
    data_evento DATETIME NOT NULL,
    usuario_id INT UNSIGNED NOT NULL,
    metadados JSON NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_contas_eventos_conta (conta_receber_id, data_evento),
    CONSTRAINT fk_contas_eventos_conta FOREIGN KEY (conta_receber_id) REFERENCES contas_receber(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_contas_eventos_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS configuracoes_empresa (
    id TINYINT UNSIGNED NOT NULL PRIMARY KEY DEFAULT 1,
    razao_social VARCHAR(150) NULL,
    nome_fantasia VARCHAR(150) NULL,
    documento VARCHAR(30) NULL,
    telefone VARCHAR(30) NULL,
    endereco VARCHAR(255) NULL,
    logo VARCHAR(255) NULL,
    atualizado_por INT UNSIGNED NULL,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_config_empresa_usuario FOREIGN KEY (atualizado_por) REFERENCES usuarios(id) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO configuracoes_empresa (id) VALUES (1);

INSERT IGNORE INTO permissoes (grupo, modulo, codigo, nome, descricao, ordem) VALUES
('Estoque', 'estoque', 'estoque.autorizar_saldo_negativo', 'Autorizar saldo negativo de estoque', 'Permite autorizar baixa de produto acima do saldo disponível.', 860),
('Financeiro', 'contas_receber', 'contas_receber.visualizar', 'Visualizar contas a receber', 'Permite acessar a carteira de contas a receber.', 1570),
('Financeiro', 'contas_receber', 'contas_receber.registrar_pagamento', 'Registrar pagamento de conta', 'Permite registrar pagamentos posteriores de OS.', 1580),
('Financeiro', 'contas_receber', 'contas_receber.alterar_vencimento', 'Alterar vencimento de conta', 'Permite alterar vencimentos de contas a receber.', 1590),
('Financeiro', 'contas_receber', 'contas_receber.configurar_lembrete', 'Configurar lembrete de conta', 'Permite configurar lembretes de cobrança.', 1600),
('Financeiro', 'contas_receber', 'contas_receber.registrar_contato', 'Registrar contato de cobrança', 'Permite registrar contatos com clientes.', 1610),
('Financeiro', 'contas_receber', 'contas_receber.negociar', 'Registrar negociação de conta', 'Permite registrar negociações de saldo pendente.', 1620),
('Financeiro', 'contas_receber', 'contas_receber.estornar_pagamento', 'Estornar pagamento de conta', 'Permite estornar pagamentos preservando histórico.', 1630),
('Ordens de Serviço', 'os', 'os.emitir_comprovante', 'Emitir comprovante de OS', 'Permite emitir comprovante não fiscal de serviço.', 330),
('Ordens de Serviço', 'os', 'os.finalizar_com_pagamento', 'Finalizar OS com pagamento', 'Permite finalizar OS registrando pagamento e saldo pendente.', 340);

INSERT IGNORE INTO perfil_permissoes (perfil_id, permissao_id)
SELECT p.id, pe.id
  FROM perfis p
  JOIN permissoes pe
 WHERE p.nome IN ('Administrador', 'Dono', 'Gerente')
   AND pe.codigo IN (
       'estoque.autorizar_saldo_negativo',
       'contas_receber.visualizar',
       'contas_receber.registrar_pagamento',
       'contas_receber.alterar_vencimento',
       'contas_receber.configurar_lembrete',
       'contas_receber.registrar_contato',
       'contas_receber.negociar',
       'contas_receber.estornar_pagamento',
       'os.emitir_comprovante',
       'os.finalizar_com_pagamento'
   );


-- -----------------------------------------------------------------------------
-- Migration: 009_required_business_adjustments.sql
-- -----------------------------------------------------------------------------
-- Migration 009 - Ajustes obrigatorios de negocio apos a base 008.
-- Aplicacao manual na hospedagem, apos backup validado.
-- Ordem: executar depois de 008_service_order_execution_financial_flow.sql.
-- Compatibilidade alvo: MariaDB/MySQL compartilhado, InnoDB, utf8mb4.

SET NAMES utf8mb4;

ALTER TABLE produtos
    ADD COLUMN IF NOT EXISTS ncm VARCHAR(8) NULL AFTER unidade,
    ADD KEY IF NOT EXISTS idx_produtos_ncm (ncm);

SET @idx_exists := (
    SELECT COUNT(*)
      FROM INFORMATION_SCHEMA.STATISTICS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'ordem_servico_funcionarios'
       AND INDEX_NAME = 'uq_os_funcionario'
);
SET @sql := IF(
    @idx_exists > 0,
    'ALTER TABLE ordem_servico_funcionarios DROP INDEX uq_os_funcionario',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

ALTER TABLE ordem_servico_funcionarios
    ADD KEY IF NOT EXISTS idx_os_funcionarios_historico (ordem_servico_id, funcionario_id, ativo, adicionado_em);

ALTER TABLE ordens_servico
    ADD COLUMN IF NOT EXISTS orcamento_operacional_chave INT UNSIGNED NULL AFTER orcamento_liberado;

UPDATE ordens_servico
   SET orcamento_operacional_chave = CASE
        WHEN orcamento_id IS NOT NULL
         AND (status <> 'cancelada' OR orcamento_liberado = 0)
        THEN orcamento_id
        ELSE NULL
   END;

ALTER TABLE ordens_servico
    ADD UNIQUE KEY IF NOT EXISTS uq_os_orcamento_operacional_unico (orcamento_operacional_chave);

ALTER TABLE estoque_movimentacoes
    MODIFY COLUMN tipo ENUM('saida_os', 'entrada', 'ajuste', 'venda_avulsa', 'estorno') NOT NULL;

ALTER TABLE contas_receber
    MODIFY COLUMN status ENUM('pendente', 'parcial', 'vencida', 'paga', 'estornada', 'cancelada') NOT NULL DEFAULT 'pendente';

ALTER TABLE configuracoes_empresa
    ADD COLUMN IF NOT EXISTS inscricao_estadual VARCHAR(40) NULL AFTER logo,
    ADD COLUMN IF NOT EXISTS inscricao_municipal VARCHAR(40) NULL AFTER inscricao_estadual,
    ADD COLUMN IF NOT EXISTS email VARCHAR(150) NULL AFTER inscricao_municipal;

ALTER TABLE funcionarios
    ADD COLUMN IF NOT EXISTS foto VARCHAR(255) NULL AFTER nome,
    ADD COLUMN IF NOT EXISTS funcao VARCHAR(100) NULL AFTER foto,
    ADD COLUMN IF NOT EXISTS salario DECIMAL(12,2) NULL AFTER funcao,
    ADD COLUMN IF NOT EXISTS endereco VARCHAR(255) NULL AFTER salario,
    ADD COLUMN IF NOT EXISTS telefone_celular VARCHAR(30) NULL AFTER endereco,
    ADD COLUMN IF NOT EXISTS data_nascimento DATE NULL AFTER telefone_celular,
    ADD COLUMN IF NOT EXISTS estado_civil ENUM('Solteiro', 'Casado', 'Divorciado', 'Viuvo', 'Uniao estavel', 'Outro') NULL AFTER data_nascimento,
    ADD COLUMN IF NOT EXISTS sexo ENUM('Masculino', 'Feminino') NULL AFTER estado_civil,
    ADD COLUMN IF NOT EXISTS data_cadastro DATE NULL AFTER sexo,
    ADD COLUMN IF NOT EXISTS data_admissao DATE NULL AFTER data_cadastro,
    ADD COLUMN IF NOT EXISTS banco VARCHAR(100) NULL AFTER data_admissao,
    ADD COLUMN IF NOT EXISTS agencia VARCHAR(30) NULL AFTER banco,
    ADD COLUMN IF NOT EXISTS conta VARCHAR(40) NULL AFTER agencia,
    ADD COLUMN IF NOT EXISTS tipo_conta VARCHAR(30) NULL AFTER conta,
    ADD COLUMN IF NOT EXISTS pix VARCHAR(150) NULL AFTER tipo_conta,
    ADD COLUMN IF NOT EXISTS rg_numero VARCHAR(40) NULL AFTER pix,
    ADD COLUMN IF NOT EXISTS rg_uf CHAR(2) NULL AFTER rg_numero,
    ADD COLUMN IF NOT EXISTS rg_orgao_emissor VARCHAR(30) NULL AFTER rg_uf,
    ADD COLUMN IF NOT EXISTS rg_data_emissao DATE NULL AFTER rg_orgao_emissor,
    ADD COLUMN IF NOT EXISTS cpf_numero VARCHAR(20) NULL AFTER rg_data_emissao,
    ADD COLUMN IF NOT EXISTS titulo_eleitor_numero VARCHAR(40) NULL AFTER cpf_numero,
    ADD COLUMN IF NOT EXISTS titulo_eleitor_uf CHAR(2) NULL AFTER titulo_eleitor_numero,
    ADD COLUMN IF NOT EXISTS titulo_eleitor_secao VARCHAR(20) NULL AFTER titulo_eleitor_uf,
    ADD COLUMN IF NOT EXISTS titulo_eleitor_zona VARCHAR(20) NULL AFTER titulo_eleitor_secao,
    ADD COLUMN IF NOT EXISTS reservista_numero VARCHAR(60) NULL AFTER titulo_eleitor_zona,
    ADD COLUMN IF NOT EXISTS reservista_data_emissao DATE NULL AFTER reservista_numero,
    ADD COLUMN IF NOT EXISTS certidao_nascimento_numero VARCHAR(80) NULL AFTER reservista_data_emissao,
    ADD COLUMN IF NOT EXISTS certidao_nascimento_cidade VARCHAR(100) NULL AFTER certidao_nascimento_numero,
    ADD COLUMN IF NOT EXISTS certidao_nascimento_livro VARCHAR(30) NULL AFTER certidao_nascimento_cidade,
    ADD COLUMN IF NOT EXISTS certidao_nascimento_folha VARCHAR(30) NULL AFTER certidao_nascimento_livro,
    ADD COLUMN IF NOT EXISTS certidao_nascimento_data_emissao DATE NULL AFTER certidao_nascimento_folha,
    ADD COLUMN IF NOT EXISTS carteira_trabalho_numero VARCHAR(40) NULL AFTER certidao_nascimento_data_emissao,
    ADD COLUMN IF NOT EXISTS carteira_trabalho_serie VARCHAR(30) NULL AFTER carteira_trabalho_numero,
    ADD COLUMN IF NOT EXISTS carteira_trabalho_uf CHAR(2) NULL AFTER carteira_trabalho_serie,
    ADD COLUMN IF NOT EXISTS pis_pasep_numero VARCHAR(40) NULL AFTER carteira_trabalho_uf,
    ADD COLUMN IF NOT EXISTS cnh_numero_registro VARCHAR(40) NULL AFTER pis_pasep_numero,
    ADD COLUMN IF NOT EXISTS cnh_categoria VARCHAR(20) NULL AFTER cnh_numero_registro,
    ADD COLUMN IF NOT EXISTS cnh_data_vencimento DATE NULL AFTER cnh_categoria,
    ADD COLUMN IF NOT EXISTS manequim_camisa VARCHAR(30) NULL AFTER cnh_data_vencimento,
    ADD COLUMN IF NOT EXISTS manequim_calca VARCHAR(30) NULL AFTER manequim_camisa,
    ADD COLUMN IF NOT EXISTS manequim_calcado VARCHAR(30) NULL AFTER manequim_calca,
    ADD KEY IF NOT EXISTS idx_funcionarios_cpf (cpf_numero),
    ADD KEY IF NOT EXISTS idx_funcionarios_funcao (funcao);

CREATE TABLE IF NOT EXISTS configuracoes_fiscais (
    id TINYINT UNSIGNED NOT NULL PRIMARY KEY DEFAULT 1,
    ambiente ENUM('homologacao', 'producao') NOT NULL DEFAULT 'homologacao',
    certificado_caminho VARCHAR(255) NULL,
    certificado_senha_ref VARCHAR(255) NULL,
    csc VARCHAR(120) NULL,
    id_csc VARCHAR(40) NULL,
    serie VARCHAR(20) NULL,
    proxima_numeracao INT UNSIGNED NOT NULL DEFAULT 1,
    status ENUM('pendente', 'configurada', 'inativa') NOT NULL DEFAULT 'pendente',
    atualizado_por INT UNSIGNED NULL,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_config_fiscal_usuario FOREIGN KEY (atualizado_por) REFERENCES usuarios(id) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS documentos_fiscais (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    origem_tipo VARCHAR(40) NOT NULL,
    origem_id INT UNSIGNED NULL,
    ambiente ENUM('homologacao', 'producao') NOT NULL,
    serie VARCHAR(20) NOT NULL,
    numero INT UNSIGNED NOT NULL,
    status ENUM('rascunho', 'pendente_configuracao', 'emitida', 'autorizada', 'rejeitada', 'cancelada') NOT NULL DEFAULT 'rascunho',
    chave VARCHAR(80) NULL,
    protocolo VARCHAR(80) NULL,
    xml_path VARCHAR(255) NULL,
    retorno TEXT NULL,
    emitido_por INT UNSIGNED NOT NULL,
    emitido_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_documento_fiscal_numero (ambiente, serie, numero),
    KEY idx_documento_fiscal_origem (origem_tipo, origem_id),
    CONSTRAINT fk_documento_fiscal_usuario FOREIGN KEY (emitido_por) REFERENCES usuarios(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS recibos (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    numero VARCHAR(30) NULL,
    cliente_id INT UNSIGNED NULL,
    ordem_servico_id INT UNSIGNED NULL,
    pagamento_id INT UNSIGNED NULL,
    descricao TEXT NOT NULL,
    valor DECIMAL(12,2) NOT NULL,
    forma_pagamento VARCHAR(40) NULL,
    status ENUM('emitido', 'cancelado') NOT NULL DEFAULT 'emitido',
    emitido_por INT UNSIGNED NOT NULL,
    emitido_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    cancelado_por INT UNSIGNED NULL,
    cancelado_em DATETIME NULL,
    motivo_cancelamento VARCHAR(255) NULL,
    UNIQUE KEY uq_recibos_numero (numero),
    KEY idx_recibos_cliente (cliente_id),
    KEY idx_recibos_os (ordem_servico_id),
    CONSTRAINT fk_recibos_cliente FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_recibos_os FOREIGN KEY (ordem_servico_id) REFERENCES ordens_servico(id) ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_recibos_usuario FOREIGN KEY (emitido_por) REFERENCES usuarios(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_recibos_cancel_usuario FOREIGN KEY (cancelado_por) REFERENCES usuarios(id) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS boletos (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    numero VARCHAR(50) NULL,
    cliente_id INT UNSIGNED NULL,
    conta_receber_id INT UNSIGNED NULL,
    valor DECIMAL(12,2) NOT NULL,
    vencimento_em DATE NOT NULL,
    status ENUM('registrado', 'pendente_retorno', 'pago', 'cancelado', 'vencido') NOT NULL DEFAULT 'registrado',
    linha_digitavel VARCHAR(120) NULL,
    codigo_barras VARCHAR(120) NULL,
    retorno TEXT NULL,
    criado_por INT UNSIGNED NOT NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_boletos_numero (numero),
    KEY idx_boletos_cliente (cliente_id),
    KEY idx_boletos_conta (conta_receber_id),
    CONSTRAINT fk_boletos_cliente FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_boletos_conta FOREIGN KEY (conta_receber_id) REFERENCES contas_receber(id) ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_boletos_usuario FOREIGN KEY (criado_por) REFERENCES usuarios(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO configuracoes_fiscais (id) VALUES (1);

INSERT IGNORE INTO permissoes (grupo, modulo, codigo, nome, descricao, ordem) VALUES
('Financeiro', 'contas_receber', 'contas_receber.baixa_lote', 'Baixa em lote', 'Permite registrar baixa em lote de contas do mesmo cliente.', 1585),
('Funcionários', 'funcionario', 'funcionario.visualizar_salario', 'Visualizar salário', 'Permite visualizar salário de funcionários.', 1040),
('Funcionários', 'funcionario', 'funcionario.editar_salario', 'Editar salário', 'Permite alterar salário de funcionários.', 1050),
('Funcionários', 'funcionario', 'funcionario.visualizar_documentos', 'Visualizar documentos', 'Permite visualizar documentos de funcionários.', 1060),
('Funcionários', 'funcionario', 'funcionario.editar_documentos', 'Editar documentos', 'Permite alterar documentos de funcionários.', 1070),
('Funcionários', 'funcionario', 'funcionario.visualizar_dados_bancarios', 'Visualizar dados bancários', 'Permite visualizar dados bancários de funcionários.', 1080),
('Funcionários', 'funcionario', 'funcionario.editar_dados_bancarios', 'Editar dados bancários', 'Permite alterar dados bancários de funcionários.', 1090),
('Fiscal', 'boleto', 'boleto.registrar_pagamento', 'Registrar pagamento de boleto', 'Permite registrar pagamento interno de boleto sem simular retorno bancário.', 1710);

INSERT IGNORE INTO perfil_permissoes (perfil_id, permissao_id)
SELECT p.id, pe.id
  FROM perfis p
  JOIN permissoes pe
 WHERE p.nome IN ('Administrador', 'Dono', 'Gerente')
   AND pe.codigo IN (
       'contas_receber.baixa_lote',
       'funcionario.visualizar_salario',
       'funcionario.editar_salario',
       'funcionario.visualizar_documentos',
       'funcionario.editar_documentos',
       'funcionario.visualizar_dados_bancarios',
       'funcionario.editar_dados_bancarios',
       'boleto.registrar_pagamento'
   );


-- -----------------------------------------------------------------------------
-- Migration: 010_operational_finance_completion.sql
-- -----------------------------------------------------------------------------
-- Migration 010 - Complementos operacionais e financeiros apos a base 009.
-- Reentrante para aplicação automática após a base 009.
-- Ordem: executar depois de 009_required_business_adjustments.sql.
-- Compatibilidade alvo: MariaDB 10.4 compartilhado, InnoDB, utf8mb4.

SET NAMES utf8mb4;

ALTER TABLE estoque_autorizacoes
    ADD COLUMN IF NOT EXISTS utilizada_em DATETIME NULL AFTER autorizado_em,
    ADD COLUMN IF NOT EXISTS movimentacao_id INT UNSIGNED NULL AFTER utilizada_em,
    ADD KEY IF NOT EXISTS idx_estoque_aut_utilizacao (utilizada_em, movimentacao_id);

SET @fk_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS
     WHERE CONSTRAINT_SCHEMA = DATABASE()
       AND CONSTRAINT_NAME = 'fk_estoque_aut_movimentacao'
);
SET @sql := IF(
    @fk_exists = 0,
    'ALTER TABLE estoque_autorizacoes ADD CONSTRAINT fk_estoque_aut_movimentacao FOREIGN KEY (movimentacao_id) REFERENCES estoque_movimentacoes(id) ON UPDATE CASCADE ON DELETE SET NULL',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS vendas_avulsas (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    numero VARCHAR(30) NULL,
    cliente_id INT UNSIGNED NULL,
    subtotal DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    desconto DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    acrescimo DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    total DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    forma_pagamento ENUM('dinheiro', 'pix', 'cartao_debito', 'cartao_credito', 'transferencia', 'outro') NOT NULL,
    status ENUM('emitida', 'estornada', 'cancelada') NOT NULL DEFAULT 'emitida',
    caixa_movimentacao_id INT UNSIGNED NULL,
    criada_por INT UNSIGNED NOT NULL,
    criada_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    estornada_por INT UNSIGNED NULL,
    estornada_em DATETIME NULL,
    motivo_estorno VARCHAR(255) NULL,
    UNIQUE KEY uq_vendas_avulsas_numero (numero),
    KEY idx_vendas_avulsas_cliente (cliente_id),
    KEY idx_vendas_avulsas_caixa (caixa_movimentacao_id),
    KEY idx_vendas_avulsas_status_data (status, criada_em),
    CONSTRAINT fk_vendas_avulsas_cliente FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_vendas_avulsas_caixa FOREIGN KEY (caixa_movimentacao_id) REFERENCES caixa_movimentacoes(id) ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_vendas_avulsas_usuario FOREIGN KEY (criada_por) REFERENCES usuarios(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_vendas_avulsas_estorno_usuario FOREIGN KEY (estornada_por) REFERENCES usuarios(id) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS venda_avulsa_itens (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    venda_avulsa_id INT UNSIGNED NOT NULL,
    produto_id INT UNSIGNED NOT NULL,
    descricao VARCHAR(255) NOT NULL,
    unidade VARCHAR(20) NOT NULL DEFAULT 'un',
    quantidade DECIMAL(12,3) NOT NULL,
    valor_unitario DECIMAL(12,2) NOT NULL,
    desconto DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    subtotal DECIMAL(12,2) NOT NULL,
    estoque_movimentacao_id INT UNSIGNED NULL,
    ordem INT UNSIGNED NOT NULL DEFAULT 0,
    KEY idx_venda_avulsa_itens_venda (venda_avulsa_id),
    KEY idx_venda_avulsa_itens_produto (produto_id),
    KEY idx_venda_avulsa_itens_movimento (estoque_movimentacao_id),
    CONSTRAINT fk_venda_avulsa_itens_venda FOREIGN KEY (venda_avulsa_id) REFERENCES vendas_avulsas(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_venda_avulsa_itens_produto FOREIGN KEY (produto_id) REFERENCES produtos(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_venda_avulsa_itens_movimento FOREIGN KEY (estoque_movimentacao_id) REFERENCES estoque_movimentacoes(id) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO permissoes (grupo, modulo, codigo, nome, descricao, ordem) VALUES
('Caixa', 'venda_avulsa', 'venda_avulsa.visualizar', 'Visualizar vendas avulsas', 'Permite acessar vendas avulsas do caixa.', 1395),
('Caixa', 'venda_avulsa', 'venda_avulsa.criar', 'Criar venda avulsa', 'Permite registrar venda avulsa com baixa de estoque e entrada no caixa.', 1396),
('Caixa', 'venda_avulsa', 'venda_avulsa.estornar', 'Estornar venda avulsa', 'Permite estornar venda avulsa preservando historico.', 1397);

INSERT IGNORE INTO perfil_permissoes (perfil_id, permissao_id)
SELECT p.id, pe.id
  FROM perfis p
  JOIN permissoes pe
 WHERE p.nome IN ('Administrador', 'Dono', 'Gerente')
   AND pe.codigo IN (
       'venda_avulsa.visualizar',
       'venda_avulsa.criar',
       'venda_avulsa.estornar'
   );


-- -----------------------------------------------------------------------------
-- Migration: 011_service_order_reversal_deletion_receipts.sql
-- -----------------------------------------------------------------------------
-- Migration 011 - Estorno transacional, exclusao logica de OS e recibos de pagamento.
-- Reentrante para aplicação automática depois de 010_operational_finance_completion.sql.
-- Compatibilidade alvo: MariaDB 10.4, InnoDB, utf8mb4.

SET NAMES utf8mb4;

ALTER TABLE ordens_servico
    ADD COLUMN IF NOT EXISTS excluida_em DATETIME NULL AFTER cancelada_em,
    ADD COLUMN IF NOT EXISTS excluida_por INT UNSIGNED NULL AFTER excluida_em,
    ADD COLUMN IF NOT EXISTS motivo_exclusao VARCHAR(255) NULL AFTER excluida_por,
    ADD KEY IF NOT EXISTS idx_os_exclusao (excluida_em);

SET @fk_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'fk_os_exclusao_usuario');
SET @sql := IF(@fk_exists = 0, 'ALTER TABLE ordens_servico ADD CONSTRAINT fk_os_exclusao_usuario FOREIGN KEY (excluida_por) REFERENCES usuarios(id) ON UPDATE CASCADE ON DELETE SET NULL', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

ALTER TABLE ordem_servico_finalizacoes
    ADD COLUMN IF NOT EXISTS status_origem ENUM('agendada', 'em_execucao', 'aguardando_peca') NOT NULL DEFAULT 'em_execucao' AFTER ativa,
    ADD COLUMN IF NOT EXISTS estornado_por INT UNSIGNED NULL AFTER finalizado_em,
    ADD COLUMN IF NOT EXISTS estornado_em DATETIME NULL AFTER estornado_por,
    ADD COLUMN IF NOT EXISTS motivo_estorno VARCHAR(255) NULL AFTER estornado_em,
    ADD COLUMN IF NOT EXISTS finalizacao_ativa_chave INT UNSIGNED
        GENERATED ALWAYS AS (CASE WHEN ativa = 1 THEN ordem_servico_id ELSE NULL END) PERSISTENT,
    ADD UNIQUE KEY IF NOT EXISTS uq_os_finalizacao_ativa (finalizacao_ativa_chave),
    ADD KEY IF NOT EXISTS idx_os_finalizacoes_estorno (estornado_em);

SET @fk_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'fk_os_finalizacoes_estorno_usuario');
SET @sql := IF(@fk_exists = 0, 'ALTER TABLE ordem_servico_finalizacoes ADD CONSTRAINT fk_os_finalizacoes_estorno_usuario FOREIGN KEY (estornado_por) REFERENCES usuarios(id) ON UPDATE CASCADE ON DELETE SET NULL', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

ALTER TABLE ordem_servico_execucao_itens
    ADD COLUMN IF NOT EXISTS finalizacao_id INT UNSIGNED NULL AFTER ordem_servico_id,
    ADD KEY IF NOT EXISTS idx_os_execucao_finalizacao (finalizacao_id);

SET @fk_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'fk_os_execucao_finalizacao');
SET @sql := IF(@fk_exists = 0, 'ALTER TABLE ordem_servico_execucao_itens ADD CONSTRAINT fk_os_execucao_finalizacao FOREIGN KEY (finalizacao_id) REFERENCES ordem_servico_finalizacoes(id) ON UPDATE CASCADE ON DELETE RESTRICT', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

UPDATE ordem_servico_execucao_itens item
JOIN ordem_servico_finalizacoes finalizacao
  ON finalizacao.ordem_servico_id = item.ordem_servico_id
 AND finalizacao.ativa = 1
SET item.finalizacao_id = finalizacao.id
WHERE item.finalizacao_id IS NULL;

ALTER TABLE estoque_movimentacoes
    ADD COLUMN IF NOT EXISTS estornado_de_id INT UNSIGNED NULL AFTER autorizacao_id,
    ADD UNIQUE KEY IF NOT EXISTS uq_estoque_estornado_de (estornado_de_id);

SET @fk_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'fk_estoque_estornado_de');
SET @sql := IF(@fk_exists = 0, 'ALTER TABLE estoque_movimentacoes ADD CONSTRAINT fk_estoque_estornado_de FOREIGN KEY (estornado_de_id) REFERENCES estoque_movimentacoes(id) ON UPDATE CASCADE ON DELETE SET NULL', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

ALTER TABLE caixa_movimentacoes
    ADD UNIQUE KEY IF NOT EXISTS uq_caixa_estornado_de (estornado_de_id);

ALTER TABLE recibos
    ADD COLUMN IF NOT EXISTS cliente_nome VARCHAR(150) NULL AFTER pagamento_id,
    ADD COLUMN IF NOT EXISTS cliente_documento VARCHAR(20) NULL AFTER cliente_nome,
    ADD COLUMN IF NOT EXISTS os_numero VARCHAR(20) NULL AFTER cliente_documento,
    ADD COLUMN IF NOT EXISTS pagamento_recebido_em DATETIME NULL AFTER os_numero,
    ADD COLUMN IF NOT EXISTS empresa_nome VARCHAR(150) NULL AFTER pagamento_recebido_em,
    ADD COLUMN IF NOT EXISTS empresa_documento VARCHAR(30) NULL AFTER empresa_nome,
    ADD COLUMN IF NOT EXISTS empresa_telefone VARCHAR(30) NULL AFTER empresa_documento,
    ADD COLUMN IF NOT EXISTS empresa_endereco VARCHAR(255) NULL AFTER empresa_telefone,
    ADD COLUMN IF NOT EXISTS empresa_logo VARCHAR(255) NULL AFTER empresa_endereco,
    ADD UNIQUE KEY IF NOT EXISTS uq_recibos_pagamento (pagamento_id);

SET @fk_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'fk_recibos_pagamento');
SET @sql := IF(@fk_exists = 0, 'ALTER TABLE recibos ADD CONSTRAINT fk_recibos_pagamento FOREIGN KEY (pagamento_id) REFERENCES ordem_servico_pagamentos(id) ON UPDATE CASCADE ON DELETE RESTRICT', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

INSERT IGNORE INTO permissoes (grupo, modulo, codigo, nome, descricao, ordem) VALUES
('Ordens de Serviço', 'os', 'os.estornar', 'Estornar ordens de serviço', 'Permite desfazer uma finalização compensando estoque, caixa, pagamentos e contas a receber.', 305);

INSERT IGNORE INTO perfil_permissoes (perfil_id, permissao_id)
SELECT perfil.id, permissao.id
  FROM perfis perfil
  JOIN permissoes permissao
 WHERE perfil.nome IN ('Administrador', 'Dono', 'Gerente')
   AND permissao.codigo = 'os.estornar';


-- -----------------------------------------------------------------------------
-- Migration: 012_client_pdf_import_permission.sql
-- -----------------------------------------------------------------------------
-- Permissão dedicada para importação em lote de clientes pelo relatório PDF do A7.
-- Seguro para reexecução e concedido inicialmente apenas ao perfil Administrador.

SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

INSERT INTO permissoes
    (grupo, modulo, codigo, nome, descricao, ordem, status)
VALUES
    ('Clientes', 'cliente', 'cliente.importar', 'Importar clientes',
     'Permite importar clientes em lote a partir do relatório PDF do A7.', 125, 'ativo')
ON DUPLICATE KEY UPDATE
    grupo = VALUES(grupo),
    modulo = VALUES(modulo),
    nome = VALUES(nome),
    descricao = VALUES(descricao),
    ordem = VALUES(ordem),
    status = VALUES(status);

INSERT IGNORE INTO perfil_permissoes (perfil_id, permissao_id)
SELECT perfil.id, permissao.id
FROM perfis perfil
INNER JOIN permissoes permissao ON permissao.codigo = 'cliente.importar'
WHERE perfil.nome = 'Administrador';


-- -----------------------------------------------------------------------------
-- Migration: 013_create_suppliers_accounts_payable.sql
-- -----------------------------------------------------------------------------
-- Migration 013 - Cadastro de fornecedores e contas a pagar manuais.
-- Escopo inicial sem baixa financeira; pagamentos serao tratados em etapa propria.
-- Compatibilidade alvo: MariaDB 10.4 compartilhado, InnoDB, utf8mb4.

SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS fornecedores (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    codigo VARCHAR(20) NULL,
    tipo_pessoa ENUM('fisica', 'juridica') NOT NULL DEFAULT 'juridica',
    nome VARCHAR(150) NOT NULL,
    nome_fantasia VARCHAR(150) NULL,
    documento VARCHAR(20) NULL,
    inscricao_estadual VARCHAR(30) NULL,
    contato VARCHAR(120) NULL,
    telefone VARCHAR(30) NULL,
    whatsapp VARCHAR(30) NULL,
    email VARCHAR(150) NULL,
    cep VARCHAR(10) NULL,
    endereco VARCHAR(180) NULL,
    numero VARCHAR(20) NULL,
    complemento VARCHAR(100) NULL,
    bairro VARCHAR(100) NULL,
    cidade VARCHAR(100) NULL,
    estado CHAR(2) NULL,
    observacao TEXT NULL,
    status ENUM('ativo', 'inativo') NOT NULL DEFAULT 'ativo',
    criado_por INT UNSIGNED NOT NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_fornecedores_codigo (codigo),
    UNIQUE KEY uq_fornecedores_documento (documento),
    KEY idx_fornecedores_nome (nome),
    KEY idx_fornecedores_status (status),
    KEY idx_fornecedores_criado_por (criado_por),
    CONSTRAINT fk_fornecedores_criado_por FOREIGN KEY (criado_por) REFERENCES usuarios(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS contas_pagar (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    codigo VARCHAR(20) NULL,
    fornecedor_id INT UNSIGNED NOT NULL,
    descricao VARCHAR(255) NOT NULL,
    documento VARCHAR(80) NULL,
    data_emissao DATE NULL,
    vencimento_em DATE NOT NULL,
    valor DECIMAL(12,2) NOT NULL,
    status ENUM('pendente', 'paga', 'cancelada') NOT NULL DEFAULT 'pendente',
    observacao TEXT NULL,
    criado_por INT UNSIGNED NOT NULL,
    cancelada_em DATETIME NULL,
    cancelada_por INT UNSIGNED NULL,
    motivo_cancelamento VARCHAR(255) NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_contas_pagar_codigo (codigo),
    UNIQUE KEY uq_contas_pagar_fornecedor_documento (fornecedor_id, documento),
    KEY idx_contas_pagar_fornecedor_status (fornecedor_id, status),
    KEY idx_contas_pagar_status_vencimento (status, vencimento_em),
    KEY idx_contas_pagar_vencimento (vencimento_em),
    KEY idx_contas_pagar_criado_por (criado_por),
    KEY idx_contas_pagar_cancelada_por (cancelada_por),
    CONSTRAINT fk_contas_pagar_fornecedor FOREIGN KEY (fornecedor_id) REFERENCES fornecedores(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_contas_pagar_criado_por FOREIGN KEY (criado_por) REFERENCES usuarios(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_contas_pagar_cancelada_por FOREIGN KEY (cancelada_por) REFERENCES usuarios(id) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO permissoes (grupo, modulo, codigo, nome, descricao, ordem, status) VALUES
('Fornecedores', 'fornecedor', 'fornecedor.visualizar', 'Visualizar fornecedores', 'Permite acessar fornecedores.', 1110, 'ativo'),
('Fornecedores', 'fornecedor', 'fornecedor.criar', 'Criar fornecedores', 'Permite cadastrar fornecedores.', 1120, 'ativo'),
('Fornecedores', 'fornecedor', 'fornecedor.editar', 'Editar fornecedores', 'Permite alterar fornecedores.', 1130, 'ativo'),
('Fornecedores', 'fornecedor', 'fornecedor.desativar', 'Desativar fornecedores', 'Permite inativar ou reativar fornecedores.', 1140, 'ativo'),
('Financeiro', 'contas_pagar', 'contas_pagar.visualizar', 'Visualizar contas a pagar', 'Permite acessar a carteira de contas a pagar.', 1561, 'ativo'),
('Financeiro', 'contas_pagar', 'contas_pagar.criar', 'Criar contas a pagar', 'Permite inserir manualmente contas de fornecedores.', 1562, 'ativo'),
('Financeiro', 'contas_pagar', 'contas_pagar.editar', 'Editar contas a pagar', 'Permite alterar contas a pagar pendentes.', 1563, 'ativo'),
('Financeiro', 'contas_pagar', 'contas_pagar.cancelar', 'Cancelar contas a pagar', 'Permite cancelar contas a pagar preservando a auditoria.', 1564, 'ativo')
ON DUPLICATE KEY UPDATE
    grupo = VALUES(grupo),
    modulo = VALUES(modulo),
    nome = VALUES(nome),
    descricao = VALUES(descricao),
    ordem = VALUES(ordem),
    status = VALUES(status);

INSERT IGNORE INTO perfil_permissoes (perfil_id, permissao_id)
SELECT perfil.id, permissao.id
FROM perfis perfil
INNER JOIN permissoes permissao ON permissao.codigo IN (
    'fornecedor.visualizar',
    'fornecedor.criar',
    'fornecedor.editar',
    'fornecedor.desativar',
    'contas_pagar.visualizar',
    'contas_pagar.criar',
    'contas_pagar.editar',
    'contas_pagar.cancelar'
)
WHERE perfil.nome IN ('Administrador', 'Dono', 'Gerente');


-- -----------------------------------------------------------------------------
-- Migration: 014_create_monthly_commission_goals.sql
-- -----------------------------------------------------------------------------
-- Migration 014 - Meta mensal global de comissao com historico de configuracoes.
-- Reentrante para aplicacao automatica depois de 013_create_suppliers_accounts_payable.sql.
-- Compatibilidade alvo: MariaDB 10.4, InnoDB, utf8mb4.

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS metas_comissao_mensais (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    competencia DATE NOT NULL,
    versao INT UNSIGNED NOT NULL,
    valor_meta DECIMAL(12,2) NOT NULL,
    percentual_comissao DECIMAL(5,2) NOT NULL,
    ativa TINYINT(1) NOT NULL DEFAULT 1,
    criada_por INT UNSIGNED NOT NULL,
    criada_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    desativada_por INT UNSIGNED NULL,
    desativada_em DATETIME NULL,
    configuracao_ativa_chave DATE
        GENERATED ALWAYS AS (CASE WHEN ativa = 1 THEN competencia ELSE NULL END) PERSISTENT,
    UNIQUE KEY uq_meta_comissao_competencia_versao (competencia, versao),
    UNIQUE KEY uq_meta_comissao_competencia_ativa (configuracao_ativa_chave),
    KEY idx_meta_comissao_competencia (competencia, ativa),
    CONSTRAINT chk_meta_comissao_ativa CHECK (ativa IN (0, 1)),
    CONSTRAINT chk_meta_comissao_valor_positivo CHECK (valor_meta > 0),
    CONSTRAINT chk_meta_comissao_percentual CHECK (percentual_comissao > 0 AND percentual_comissao <= 100),
    CONSTRAINT chk_meta_comissao_desativacao
        CHECK ((ativa = 1 AND desativada_em IS NULL)
            OR (ativa = 0 AND desativada_em IS NOT NULL)),
    CONSTRAINT fk_meta_comissao_criada_usuario FOREIGN KEY (criada_por) REFERENCES usuarios(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_meta_comissao_desativada_usuario FOREIGN KEY (desativada_por) REFERENCES usuarios(id) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO permissoes (grupo, modulo, codigo, nome, descricao, ordem) VALUES
('Relatorios', 'relatorio', 'relatorio.comissao.visualizar', 'Visualizar comissoes', 'Permite visualizar metas, producao e comissoes dos funcionarios.', 1855),
('Relatorios', 'relatorio', 'relatorio.meta_comissao.configurar', 'Configurar meta de comissao', 'Permite criar uma nova versao da meta e do percentual mensal de comissao.', 1856);

INSERT IGNORE INTO perfil_permissoes (perfil_id, permissao_id)
SELECT perfil.id, permissao.id
  FROM perfis perfil
  JOIN permissoes permissao
 WHERE perfil.nome IN ('Administrador', 'Dono', 'Gerente')
   AND permissao.codigo = 'relatorio.comissao.visualizar';

INSERT IGNORE INTO perfil_permissoes (perfil_id, permissao_id)
SELECT perfil.id, permissao.id
  FROM perfis perfil
  JOIN permissoes permissao
 WHERE perfil.nome IN ('Administrador', 'Dono')
   AND permissao.codigo = 'relatorio.meta_comissao.configurar';

DELETE perfil_permissao
  FROM perfil_permissoes perfil_permissao
  JOIN perfis perfil ON perfil.id = perfil_permissao.perfil_id
  JOIN permissoes permissao ON permissao.id = perfil_permissao.permissao_id
 WHERE permissao.codigo = 'relatorio.meta_comissao.configurar'
   AND perfil.nome NOT IN ('Administrador', 'Dono');


-- -----------------------------------------------------------------------------
-- Migration: 015_accounts_payable_installments.sql
-- -----------------------------------------------------------------------------
-- Migration 015 - Parcelamento e quitacao auditavel de contas a pagar.
-- Compatibilidade alvo: MariaDB 10.4 compartilhado, InnoDB, utf8mb4.

SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

ALTER TABLE contas_pagar
    ADD COLUMN IF NOT EXISTS tipo_pagamento ENUM('avista', 'parcelado') NOT NULL DEFAULT 'avista' AFTER valor,
    ADD COLUMN IF NOT EXISTS quantidade_parcelas SMALLINT UNSIGNED NOT NULL DEFAULT 1 AFTER tipo_pagamento,
    ADD COLUMN IF NOT EXISTS forma_pagamento ENUM('dinheiro', 'pix', 'boleto', 'cartao_credito', 'cartao_debito', 'transferencia', 'cheque', 'outro') NOT NULL DEFAULT 'outro' AFTER quantidade_parcelas;

ALTER TABLE contas_pagar
    MODIFY COLUMN status ENUM('pendente', 'parcial', 'paga', 'cancelada') NOT NULL DEFAULT 'pendente';

ALTER TABLE caixa_movimentacoes
    MODIFY COLUMN forma_pagamento ENUM('dinheiro', 'pix', 'boleto', 'cartao_credito', 'cartao_debito', 'transferencia', 'cheque', 'outro') NULL;

CREATE TABLE IF NOT EXISTS contas_pagar_parcelas (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    conta_pagar_id INT UNSIGNED NOT NULL,
    numero SMALLINT UNSIGNED NOT NULL,
    vencimento_em DATE NOT NULL,
    valor DECIMAL(12,2) NOT NULL,
    status ENUM('pendente', 'paga', 'cancelada') NOT NULL DEFAULT 'pendente',
    quitada_em DATETIME NULL,
    quitada_por INT UNSIGNED NULL,
    forma_pagamento_quitacao ENUM('dinheiro', 'pix', 'boleto', 'cartao_credito', 'cartao_debito', 'transferencia', 'cheque', 'outro') NULL,
    caixa_movimentacao_id INT UNSIGNED NULL,
    criada_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizada_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_conta_pagar_parcela_numero (conta_pagar_id, numero),
    KEY idx_conta_pagar_parcela_status_vencimento (status, vencimento_em),
    KEY idx_conta_pagar_parcela_quitada_por (quitada_por),
    KEY idx_conta_pagar_parcela_caixa (caixa_movimentacao_id),
    CONSTRAINT fk_conta_pagar_parcela_conta FOREIGN KEY (conta_pagar_id) REFERENCES contas_pagar(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_conta_pagar_parcela_quitada_usuario FOREIGN KEY (quitada_por) REFERENCES usuarios(id) ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_conta_pagar_parcela_caixa FOREIGN KEY (caixa_movimentacao_id) REFERENCES caixa_movimentacoes(id) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS contas_pagar_parcela_eventos (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    parcela_id INT UNSIGNED NOT NULL,
    tipo ENUM('quitacao', 'estorno') NOT NULL,
    forma_pagamento ENUM('dinheiro', 'pix', 'boleto', 'cartao_credito', 'cartao_debito', 'transferencia', 'cheque', 'outro') NULL,
    observacao VARCHAR(255) NULL,
    usuario_id INT UNSIGNED NOT NULL,
    caixa_movimentacao_id INT UNSIGNED NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_conta_pagar_evento_parcela_data (parcela_id, criado_em),
    KEY idx_conta_pagar_evento_usuario (usuario_id),
    KEY idx_conta_pagar_evento_caixa (caixa_movimentacao_id),
    CONSTRAINT fk_conta_pagar_evento_parcela FOREIGN KEY (parcela_id) REFERENCES contas_pagar_parcelas(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_conta_pagar_evento_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_conta_pagar_evento_caixa FOREIGN KEY (caixa_movimentacao_id) REFERENCES caixa_movimentacoes(id) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO contas_pagar_parcelas
    (conta_pagar_id, numero, vencimento_em, valor, status, quitada_em)
SELECT id, 1, vencimento_em, valor,
       CASE status WHEN 'paga' THEN 'paga' WHEN 'cancelada' THEN 'cancelada' ELSE 'pendente' END,
       CASE WHEN status = 'paga' THEN atualizado_em ELSE NULL END
  FROM contas_pagar;

INSERT INTO permissoes (grupo, modulo, codigo, nome, descricao, ordem, status) VALUES
('Financeiro', 'contas_pagar', 'contas_pagar.quitar', 'Quitar parcelas a pagar', 'Permite registrar a quitação individual de parcelas.', 1565, 'ativo'),
('Financeiro', 'contas_pagar', 'contas_pagar.estornar_pagamento', 'Estornar quitação a pagar', 'Permite estornar quitações preservando o histórico.', 1566, 'ativo')
ON DUPLICATE KEY UPDATE nome = VALUES(nome), descricao = VALUES(descricao), ordem = VALUES(ordem), status = VALUES(status);

INSERT IGNORE INTO perfil_permissoes (perfil_id, permissao_id)
SELECT perfil.id, permissao.id
  FROM perfis perfil
  JOIN permissoes permissao ON permissao.codigo IN ('contas_pagar.quitar', 'contas_pagar.estornar_pagamento')
 WHERE perfil.nome IN ('Administrador', 'Dono', 'Gerente');


-- -----------------------------------------------------------------------------
-- Migration: 016_cash_register_pos.sql
-- -----------------------------------------------------------------------------
-- Migration 016 - Sessao operacional de caixa, sangria, suprimento e PDV.
-- Compatibilidade alvo: MariaDB 10.4 compartilhado, InnoDB, utf8mb4.

SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS caixa_sessoes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    codigo VARCHAR(20) NULL,
    status ENUM('aberta', 'fechada') NOT NULL DEFAULT 'aberta',
    valor_abertura DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    observacao_abertura VARCHAR(255) NULL,
    aberto_por INT UNSIGNED NOT NULL,
    aberto_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    saldo_esperado DECIMAL(12,2) NULL,
    saldo_informado DECIMAL(12,2) NULL,
    diferenca DECIMAL(12,2) NULL,
    observacao_fechamento VARCHAR(255) NULL,
    fechado_por INT UNSIGNED NULL,
    fechado_em DATETIME NULL,
    sessao_aberta_chave TINYINT UNSIGNED
        GENERATED ALWAYS AS (CASE WHEN status = 'aberta' THEN 1 ELSE NULL END) PERSISTENT,
    UNIQUE KEY uq_caixa_sessao_codigo (codigo),
    UNIQUE KEY uq_caixa_sessao_aberta (sessao_aberta_chave),
    KEY idx_caixa_sessao_periodo (aberto_em, fechado_em),
    KEY idx_caixa_sessao_aberto_por (aberto_por),
    KEY idx_caixa_sessao_fechado_por (fechado_por),
    CONSTRAINT fk_caixa_sessao_aberto_usuario FOREIGN KEY (aberto_por) REFERENCES usuarios(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_caixa_sessao_fechado_usuario FOREIGN KEY (fechado_por) REFERENCES usuarios(id) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE caixa_movimentacoes
    ADD COLUMN IF NOT EXISTS caixa_sessao_id INT UNSIGNED NULL AFTER id,
    ADD KEY IF NOT EXISTS idx_caixa_mov_sessao_data (caixa_sessao_id, data_movimento);

SET @fk_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS
     WHERE CONSTRAINT_SCHEMA = DATABASE()
       AND CONSTRAINT_NAME = 'fk_caixa_mov_sessao'
);
SET @sql := IF(
    @fk_exists = 0,
    'ALTER TABLE caixa_movimentacoes ADD CONSTRAINT fk_caixa_mov_sessao FOREIGN KEY (caixa_sessao_id) REFERENCES caixa_sessoes(id) ON UPDATE CASCADE ON DELETE RESTRICT',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

ALTER TABLE vendas_avulsas
    ADD COLUMN IF NOT EXISTS caixa_sessao_id INT UNSIGNED NULL AFTER id,
    ADD KEY IF NOT EXISTS idx_vendas_avulsas_sessao (caixa_sessao_id);

SET @fk_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS
     WHERE CONSTRAINT_SCHEMA = DATABASE()
       AND CONSTRAINT_NAME = 'fk_vendas_avulsas_sessao'
);
SET @sql := IF(
    @fk_exists = 0,
    'ALTER TABLE vendas_avulsas ADD CONSTRAINT fk_vendas_avulsas_sessao FOREIGN KEY (caixa_sessao_id) REFERENCES caixa_sessoes(id) ON UPDATE CASCADE ON DELETE RESTRICT',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

ALTER TABLE estoque_movimentacoes
    MODIFY COLUMN tipo ENUM('entrada', 'saida_os', 'saida_venda', 'ajuste', 'estorno') NOT NULL;

INSERT INTO permissoes (grupo, modulo, codigo, nome, descricao, ordem, status) VALUES
('Caixa', 'caixa', 'caixa.visualizar', 'Visualizar Caixa', 'Permite acessar o Caixa.', 1310, 'ativo'),
('Caixa', 'caixa', 'caixa.abrir', 'Abrir Caixa', 'Permite abrir uma sessão operacional de Caixa.', 1320, 'ativo'),
('Caixa', 'caixa', 'caixa.registrar_venda', 'Operar PDV', 'Permite registrar vendas no PDV durante uma sessão aberta.', 1330, 'ativo'),
('Caixa', 'caixa', 'caixa.registrar_recebimento', 'Registrar recebimento', 'Permite registrar recebimentos no Caixa.', 1340, 'ativo'),
('Caixa', 'caixa', 'caixa.suprimento', 'Registrar suprimento', 'Permite adicionar dinheiro à sessão aberta com auditoria.', 1350, 'ativo'),
('Caixa', 'caixa', 'caixa.sangria', 'Registrar sangria', 'Permite retirar dinheiro da sessão aberta com auditoria.', 1360, 'ativo'),
('Caixa', 'caixa', 'caixa.estornar', 'Estornar Caixa', 'Permite estornar movimentações do Caixa.', 1370, 'ativo'),
('Caixa', 'caixa', 'caixa.fechar', 'Fechar Caixa', 'Permite conferir e fechar uma sessão operacional de Caixa.', 1380, 'ativo'),
('Caixa', 'caixa', 'caixa.visualizar_saldo', 'Visualizar saldo', 'Permite visualizar saldos e conferências do Caixa.', 1390, 'ativo')
ON DUPLICATE KEY UPDATE nome = VALUES(nome), descricao = VALUES(descricao), ordem = VALUES(ordem), status = VALUES(status);

INSERT IGNORE INTO perfil_permissoes (perfil_id, permissao_id)
SELECT perfil.id, permissao.id
  FROM perfis perfil
  JOIN permissoes permissao
 WHERE perfil.nome IN ('Administrador', 'Dono', 'Gerente')
   AND permissao.codigo IN (
       'caixa.visualizar', 'caixa.abrir', 'caixa.fechar', 'caixa.sangria',
       'caixa.suprimento', 'caixa.estornar', 'caixa.visualizar_saldo',
       'caixa.registrar_venda', 'venda_avulsa.visualizar',
       'venda_avulsa.criar', 'venda_avulsa.estornar'
   )
   AND permissao.status = 'ativo';


-- -----------------------------------------------------------------------------
-- Migration: 017_secure_fiscal_foundation.sql
-- -----------------------------------------------------------------------------
-- Migration 017 - Fundacao fiscal segura, versionada e separada por ambiente/modelo.
-- Nao habilita emissao: prepara configuracao, certificado, numeracao e auditoria.
-- Compatibilidade alvo: MariaDB 10.4, InnoDB, utf8mb4.

SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS fiscal_certificados (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    arquivo_referencia VARCHAR(255) NOT NULL,
    arquivo_sha256 CHAR(64) NOT NULL,
    certificado_fingerprint_sha256 CHAR(64) NOT NULL,
    certificado_serial VARCHAR(120) NULL,
    titular_cnpj VARCHAR(14) NOT NULL,
    titular_nome VARCHAR(255) NULL,
    valido_de DATETIME NOT NULL,
    valido_ate DATETIME NOT NULL,
    senha_ciphertext VARBINARY(2048) NOT NULL,
    senha_nonce VARBINARY(64) NOT NULL,
    senha_tag VARBINARY(64) NOT NULL,
    cifra_algoritmo VARCHAR(40) NOT NULL DEFAULT 'xchacha20poly1305_ietf',
    chave_versao VARCHAR(30) NOT NULL DEFAULT 'v1',
    status ENUM('ativo', 'substituido', 'revogado', 'expirado') NOT NULL DEFAULT 'ativo',
    criado_por INT UNSIGNED NOT NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    substituido_por INT UNSIGNED NULL,
    substituido_em DATETIME NULL,
    UNIQUE KEY uq_fiscal_certificado_arquivo_sha256 (arquivo_sha256),
    UNIQUE KEY uq_fiscal_certificado_fingerprint (certificado_fingerprint_sha256),
    KEY idx_fiscal_certificado_cnpj_validade (titular_cnpj, valido_ate, status),
    KEY idx_fiscal_certificado_substituto (substituido_por),
    CONSTRAINT fk_fiscal_certificado_criado_usuario FOREIGN KEY (criado_por) REFERENCES usuarios(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_fiscal_certificado_substituto FOREIGN KEY (substituido_por) REFERENCES fiscal_certificados(id) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS fiscal_configuracoes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ambiente ENUM('homologacao', 'producao') NOT NULL,
    modelo ENUM('55', '65') NOT NULL,
    versao INT UNSIGNED NOT NULL,
    uf CHAR(2) NOT NULL,
    schema_versao VARCHAR(20) NOT NULL DEFAULT '4.00',
    qr_code_versao TINYINT UNSIGNED NULL,
    certificado_id INT UNSIGNED NOT NULL,
    csc_id VARCHAR(40) NULL,
    csc_ciphertext VARBINARY(1024) NULL,
    csc_nonce VARBINARY(64) NULL,
    csc_tag VARBINARY(64) NULL,
    csc_algoritmo VARCHAR(40) NULL,
    segredo_chave_versao VARCHAR(30) NULL,
    status ENUM('rascunho', 'validada', 'ativa', 'inativa') NOT NULL DEFAULT 'rascunho',
    criado_por INT UNSIGNED NOT NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ativado_por INT UNSIGNED NULL,
    ativado_em DATETIME NULL,
    desativado_por INT UNSIGNED NULL,
    desativado_em DATETIME NULL,
    configuracao_ativa_chave VARCHAR(32)
        GENERATED ALWAYS AS (CASE WHEN status = 'ativa' THEN CONCAT(ambiente, ':', modelo) ELSE NULL END) PERSISTENT,
    UNIQUE KEY uq_fiscal_configuracao_versao (ambiente, modelo, versao),
    UNIQUE KEY uq_fiscal_configuracao_ativa (configuracao_ativa_chave),
    KEY idx_fiscal_configuracao_consulta (ambiente, modelo, status, versao),
    KEY idx_fiscal_configuracao_certificado (certificado_id),
    CONSTRAINT fk_fiscal_configuracao_certificado FOREIGN KEY (certificado_id) REFERENCES fiscal_certificados(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_fiscal_configuracao_criado_usuario FOREIGN KEY (criado_por) REFERENCES usuarios(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_fiscal_configuracao_ativado_usuario FOREIGN KEY (ativado_por) REFERENCES usuarios(id) ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_fiscal_configuracao_desativado_usuario FOREIGN KEY (desativado_por) REFERENCES usuarios(id) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS fiscal_series (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ambiente ENUM('homologacao', 'producao') NOT NULL,
    modelo ENUM('55', '65') NOT NULL,
    serie SMALLINT UNSIGNED NOT NULL,
    proximo_numero INT UNSIGNED NOT NULL DEFAULT 1,
    ultimo_numero_reservado INT UNSIGNED NULL,
    status ENUM('ativa', 'inativa') NOT NULL DEFAULT 'ativa',
    criado_por INT UNSIGNED NOT NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_por INT UNSIGNED NULL,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_fiscal_serie_ambiente_modelo (ambiente, modelo, serie),
    KEY idx_fiscal_serie_status (ambiente, modelo, status),
    CONSTRAINT fk_fiscal_serie_criado_usuario FOREIGN KEY (criado_por) REFERENCES usuarios(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_fiscal_serie_atualizado_usuario FOREIGN KEY (atualizado_por) REFERENCES usuarios(id) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS fiscal_auditoria (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    entidade_tipo VARCHAR(50) NOT NULL,
    entidade_id INT UNSIGNED NULL,
    acao VARCHAR(80) NOT NULL,
    ambiente ENUM('homologacao', 'producao') NULL,
    modelo ENUM('55', '65') NULL,
    usuario_id INT UNSIGNED NOT NULL,
    detalhes JSON NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_fiscal_auditoria_entidade (entidade_tipo, entidade_id, criado_em),
    KEY idx_fiscal_auditoria_usuario (usuario_id, criado_em),
    KEY idx_fiscal_auditoria_ambiente (ambiente, modelo, criado_em),
    CONSTRAINT fk_fiscal_auditoria_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE produtos
    ADD COLUMN IF NOT EXISTS cest VARCHAR(7) NULL AFTER ncm,
    ADD COLUMN IF NOT EXISTS origem_mercadoria TINYINT UNSIGNED NULL AFTER cest,
    ADD COLUMN IF NOT EXISTS cfop_padrao CHAR(4) NULL AFTER origem_mercadoria,
    ADD COLUMN IF NOT EXISTS cst_icms CHAR(3) NULL AFTER cfop_padrao,
    ADD COLUMN IF NOT EXISTS csosn CHAR(3) NULL AFTER cst_icms,
    ADD COLUMN IF NOT EXISTS cst_pis CHAR(2) NULL AFTER csosn,
    ADD COLUMN IF NOT EXISTS cst_cofins CHAR(2) NULL AFTER cst_pis,
    ADD COLUMN IF NOT EXISTS aliquota_icms DECIMAL(7,4) NULL AFTER cst_cofins,
    ADD COLUMN IF NOT EXISTS aliquota_pis DECIMAL(7,4) NULL AFTER aliquota_icms,
    ADD COLUMN IF NOT EXISTS aliquota_cofins DECIMAL(7,4) NULL AFTER aliquota_pis,
    ADD COLUMN IF NOT EXISTS gtin_tributavel VARCHAR(14) NULL AFTER codigo_barras,
    ADD COLUMN IF NOT EXISTS unidade_tributavel VARCHAR(20) NULL AFTER gtin_tributavel,
    ADD COLUMN IF NOT EXISTS cst_ibs_cbs CHAR(3) NULL AFTER aliquota_cofins,
    ADD COLUMN IF NOT EXISTS classificacao_tributaria_ibs_cbs VARCHAR(6) NULL AFTER cst_ibs_cbs,
    ADD KEY IF NOT EXISTS idx_produtos_cest (cest),
    ADD KEY IF NOT EXISTS idx_produtos_cfop (cfop_padrao),
    ADD KEY IF NOT EXISTS idx_produtos_gtin_tributavel (gtin_tributavel);

ALTER TABLE clientes
    ADD COLUMN IF NOT EXISTS inscricao_estadual VARCHAR(20) NULL AFTER documento,
    ADD COLUMN IF NOT EXISTS indicador_ie ENUM('contribuinte', 'isento', 'nao_contribuinte') NOT NULL DEFAULT 'nao_contribuinte' AFTER inscricao_estadual,
    ADD COLUMN IF NOT EXISTS codigo_municipio_ibge CHAR(7) NULL AFTER cidade,
    ADD KEY IF NOT EXISTS idx_clientes_inscricao_estadual (inscricao_estadual),
    ADD KEY IF NOT EXISTS idx_clientes_municipio_ibge (codigo_municipio_ibge);

ALTER TABLE configuracoes_empresa
    ADD COLUMN IF NOT EXISTS crt TINYINT UNSIGNED NULL AFTER inscricao_municipal,
    ADD COLUMN IF NOT EXISTS cnae_principal CHAR(7) NULL AFTER crt,
    ADD COLUMN IF NOT EXISTS endereco_logradouro VARCHAR(150) NULL AFTER endereco,
    ADD COLUMN IF NOT EXISTS endereco_numero VARCHAR(30) NULL AFTER endereco_logradouro,
    ADD COLUMN IF NOT EXISTS endereco_complemento VARCHAR(100) NULL AFTER endereco_numero,
    ADD COLUMN IF NOT EXISTS endereco_bairro VARCHAR(100) NULL AFTER endereco_complemento,
    ADD COLUMN IF NOT EXISTS endereco_cidade VARCHAR(100) NULL AFTER endereco_bairro,
    ADD COLUMN IF NOT EXISTS endereco_uf CHAR(2) NULL AFTER endereco_cidade,
    ADD COLUMN IF NOT EXISTS endereco_cep VARCHAR(8) NULL AFTER endereco_uf,
    ADD COLUMN IF NOT EXISTS codigo_municipio_ibge CHAR(7) NULL AFTER endereco_cep;

INSERT INTO permissoes (grupo, modulo, codigo, nome, descricao, ordem, status) VALUES
('Fiscal', 'nota_fiscal', 'nota_fiscal.configurar', 'Configurar emissão fiscal', 'Permite criar versões da configuração fiscal de homologação.', 1601, 'ativo'),
('Fiscal', 'nota_fiscal', 'nota_fiscal.gerenciar_credenciais', 'Gerenciar credenciais fiscais', 'Permite cadastrar e substituir certificado e segredos fiscais cifrados.', 1602, 'ativo'),
('Fiscal', 'nota_fiscal', 'nota_fiscal.ativar_producao', 'Ativar emissão em produção', 'Permite ativar explicitamente uma configuração fiscal de produção.', 1603, 'ativo'),
('Fiscal', 'nota_fiscal', 'nota_fiscal.testar_integracao', 'Testar integração fiscal', 'Permite testar certificado, cadastro e comunicação com a SEFAZ.', 1604, 'ativo'),
('Fiscal', 'nota_fiscal', 'nota_fiscal.baixar_xml', 'Baixar XML fiscal', 'Permite baixar XML fiscal autorizado, sujeito a auditoria.', 1605, 'ativo')
ON DUPLICATE KEY UPDATE nome = VALUES(nome), descricao = VALUES(descricao), ordem = VALUES(ordem), status = VALUES(status);

INSERT IGNORE INTO perfil_permissoes (perfil_id, permissao_id)
SELECT perfil.id, permissao.id
  FROM perfis perfil
  JOIN permissoes permissao
 WHERE perfil.nome IN ('Administrador', 'Dono')
   AND permissao.codigo IN (
       'nota_fiscal.configurar', 'nota_fiscal.gerenciar_credenciais',
       'nota_fiscal.ativar_producao', 'nota_fiscal.testar_integracao',
       'nota_fiscal.baixar_xml'
   )
   AND permissao.status = 'ativo';

DELETE perfil_permissao
  FROM perfil_permissoes perfil_permissao
  JOIN perfis perfil ON perfil.id = perfil_permissao.perfil_id
  JOIN permissoes permissao ON permissao.id = perfil_permissao.permissao_id
 WHERE permissao.codigo IN (
       'nota_fiscal.configurar', 'nota_fiscal.gerenciar_credenciais',
       'nota_fiscal.ativar_producao', 'nota_fiscal.testar_integracao',
       'nota_fiscal.baixar_xml'
   )
   AND perfil.nome NOT IN ('Administrador', 'Dono');


-- -----------------------------------------------------------------------------
-- Migration: 018_complete_agenda_reminders.sql
-- -----------------------------------------------------------------------------
-- Migration 018 - Conclusao auditavel de lembretes da Agenda.
-- Mantem cancelamento e conclusao como estados distintos.

ALTER TABLE agenda_lembretes
    MODIFY COLUMN status ENUM('ativo', 'concluido', 'cancelado') NOT NULL DEFAULT 'ativo';

SET @column_exists := (
    SELECT COUNT(*)
      FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'agenda_lembretes'
       AND COLUMN_NAME = 'concluido_em'
);
SET @sql := IF(
    @column_exists = 0,
    'ALTER TABLE agenda_lembretes ADD COLUMN concluido_em DATETIME NULL AFTER status',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @column_exists := (
    SELECT COUNT(*)
      FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'agenda_lembretes'
       AND COLUMN_NAME = 'concluido_por'
);
SET @sql := IF(
    @column_exists = 0,
    'ALTER TABLE agenda_lembretes ADD COLUMN concluido_por INT UNSIGNED NULL AFTER concluido_em, ADD KEY idx_agenda_lembretes_concluido_por (concluido_por)',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_exists := (
    SELECT COUNT(*)
      FROM INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS
     WHERE CONSTRAINT_SCHEMA = DATABASE()
       AND CONSTRAINT_NAME = 'fk_agenda_lembretes_concluido_usuario'
);
SET @sql := IF(
    @fk_exists = 0,
    'ALTER TABLE agenda_lembretes ADD CONSTRAINT fk_agenda_lembretes_concluido_usuario FOREIGN KEY (concluido_por) REFERENCES usuarios(id) ON UPDATE CASCADE ON DELETE SET NULL',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;


-- -----------------------------------------------------------------------------
-- Migration: 019_service_order_payment_receipts_permissions.sql
-- -----------------------------------------------------------------------------
-- Migration 019 - Pagamento idempotente de OS e reparo de permissoes operacionais.
-- Compatibilidade alvo: MariaDB 10.4, InnoDB, utf8mb4.

SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

ALTER TABLE ordem_servico_pagamentos
    ADD COLUMN IF NOT EXISTS payment_token VARCHAR(64) NULL AFTER caixa_movimentacao_id,
    ADD UNIQUE KEY IF NOT EXISTS uq_os_pagamento_token (payment_token);

ALTER TABLE ordem_servico_finalizacoes
    ADD COLUMN IF NOT EXISTS subtotal_servicos_origem DECIMAL(12,2) NULL AFTER status_origem,
    ADD COLUMN IF NOT EXISTS subtotal_produtos_origem DECIMAL(12,2) NULL AFTER subtotal_servicos_origem,
    ADD COLUMN IF NOT EXISTS subtotal_outros_origem DECIMAL(12,2) NULL AFTER subtotal_produtos_origem,
    ADD COLUMN IF NOT EXISTS desconto_origem DECIMAL(12,2) NULL AFTER subtotal_outros_origem,
    ADD COLUMN IF NOT EXISTS acrescimo_origem DECIMAL(12,2) NULL AFTER desconto_origem,
    ADD COLUMN IF NOT EXISTS total_origem DECIMAL(12,2) NULL AFTER acrescimo_origem;

INSERT INTO permissoes (grupo, modulo, codigo, nome, descricao, ordem, status) VALUES
('Ordens de Serviço', 'os', 'os.estornar', 'Estornar ordens de serviço', 'Permite desfazer a finalização compensando estoque, caixa, pagamentos e contas a receber.', 305, 'ativo'),
('Ordens de Serviço', 'os', 'os.excluir', 'Excluir ordens de serviço', 'Permite excluir logicamente ordens de serviço sem apagar o histórico.', 310, 'ativo'),
('Financeiro', 'contas_receber', 'contas_receber.registrar_pagamento', 'Registrar pagamento de conta', 'Permite registrar pagamentos posteriores de OS.', 1580, 'ativo'),
('Fiscal', 'recibo', 'recibo.emitir', 'Emitir recibos', 'Permite emitir recibos vinculados a pagamentos ou avulsos.', 1650, 'ativo')
ON DUPLICATE KEY UPDATE
    grupo = VALUES(grupo),
    modulo = VALUES(modulo),
    nome = VALUES(nome),
    descricao = VALUES(descricao),
    ordem = VALUES(ordem),
    status = VALUES(status);

INSERT IGNORE INTO perfil_permissoes (perfil_id, permissao_id)
SELECT perfil.id, permissao.id
  FROM perfis perfil
  JOIN permissoes permissao
    ON permissao.codigo IN (
        'os.estornar', 'os.excluir',
        'contas_receber.registrar_pagamento', 'recibo.emitir'
    )
 WHERE perfil.nome IN ('Administrador', 'Dono', 'Gerente')
   AND permissao.status = 'ativo';


-- -----------------------------------------------------------------------------
-- Migration: 020_product_soft_deletion.sql
-- -----------------------------------------------------------------------------
-- Migration 020 - Exclusao logica e auditada de produtos.
-- Preserva o historico de estoque, vendas, orcamentos e ordens de servico.

SET NAMES utf8mb4;

ALTER TABLE produtos
    ADD COLUMN IF NOT EXISTS excluido_em DATETIME NULL AFTER status,
    ADD COLUMN IF NOT EXISTS excluido_por INT UNSIGNED NULL AFTER excluido_em,
    ADD COLUMN IF NOT EXISTS motivo_exclusao VARCHAR(255) NULL AFTER excluido_por,
    ADD KEY IF NOT EXISTS idx_produtos_exclusao (excluido_em);

SET @fk_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'fk_produtos_exclusao_usuario');
SET @sql := IF(@fk_exists = 0, 'ALTER TABLE produtos ADD CONSTRAINT fk_produtos_exclusao_usuario FOREIGN KEY (excluido_por) REFERENCES usuarios(id) ON UPDATE CASCADE ON DELETE SET NULL', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

INSERT INTO permissoes (grupo, modulo, codigo, nome, descricao, ordem, status) VALUES
('Produtos', 'produto', 'produto.excluir', 'Excluir produtos', 'Permite excluir logicamente produtos sem apagar o historico.', 760, 'ativo')
ON DUPLICATE KEY UPDATE
    grupo = VALUES(grupo),
    modulo = VALUES(modulo),
    nome = VALUES(nome),
    descricao = VALUES(descricao),
    ordem = VALUES(ordem),
    status = VALUES(status);

INSERT IGNORE INTO perfil_permissoes (perfil_id, permissao_id)
SELECT perfil.id, permissao.id
  FROM perfis perfil
  JOIN permissoes permissao ON permissao.codigo = 'produto.excluir'
 WHERE perfil.nome IN ('Administrador', 'Dono', 'Gerente')
   AND permissao.status = 'ativo';


-- -----------------------------------------------------------------------------
-- Migration: 021_service_order_payment_installments.sql
-- -----------------------------------------------------------------------------
-- Migration 021 - Forma de pagamento e parcelas de recebimentos de OS.
-- Compatibilidade alvo: MariaDB 10.4, InnoDB, utf8mb4.

SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

ALTER TABLE ordem_servico_pagamentos
    MODIFY COLUMN forma_pagamento ENUM(
        'dinheiro', 'pix', 'boleto', 'cartao_debito', 'cartao_credito',
        'transferencia', 'outro'
    ) NOT NULL,
    ADD COLUMN IF NOT EXISTS quantidade_parcelas SMALLINT UNSIGNED NOT NULL DEFAULT 1 AFTER forma_pagamento;

ALTER TABLE recibos
    ADD COLUMN IF NOT EXISTS quantidade_parcelas SMALLINT UNSIGNED NOT NULL DEFAULT 1 AFTER forma_pagamento;


-- -----------------------------------------------------------------------------
-- Migration: 022_master_data_soft_deletion.sql
-- -----------------------------------------------------------------------------
-- Migration 022 - Exclusao logica e auditada de clientes, orcamentos e servicos.
-- Preserva documentos operacionais e historicos sem exigir motivo de exclusao.

SET NAMES utf8mb4;

ALTER TABLE clientes
    ADD COLUMN IF NOT EXISTS excluido_em DATETIME NULL AFTER status,
    ADD COLUMN IF NOT EXISTS excluido_por INT UNSIGNED NULL AFTER excluido_em,
    ADD KEY IF NOT EXISTS idx_clientes_exclusao (excluido_em);

ALTER TABLE orcamentos
    ADD COLUMN IF NOT EXISTS excluido_em DATETIME NULL AFTER recusado_em,
    ADD COLUMN IF NOT EXISTS excluido_por INT UNSIGNED NULL AFTER excluido_em,
    ADD KEY IF NOT EXISTS idx_orcamentos_exclusao (excluido_em);

ALTER TABLE servicos
    ADD COLUMN IF NOT EXISTS excluido_em DATETIME NULL AFTER status,
    ADD COLUMN IF NOT EXISTS excluido_por INT UNSIGNED NULL AFTER excluido_em,
    ADD KEY IF NOT EXISTS idx_servicos_exclusao (excluido_em);

SET @fk_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'fk_clientes_exclusao_usuario');
SET @sql := IF(@fk_exists = 0, 'ALTER TABLE clientes ADD CONSTRAINT fk_clientes_exclusao_usuario FOREIGN KEY (excluido_por) REFERENCES usuarios(id) ON UPDATE CASCADE ON DELETE SET NULL', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @fk_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'fk_orcamentos_exclusao_usuario');
SET @sql := IF(@fk_exists = 0, 'ALTER TABLE orcamentos ADD CONSTRAINT fk_orcamentos_exclusao_usuario FOREIGN KEY (excluido_por) REFERENCES usuarios(id) ON UPDATE CASCADE ON DELETE SET NULL', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @fk_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'fk_servicos_exclusao_usuario');
SET @sql := IF(@fk_exists = 0, 'ALTER TABLE servicos ADD CONSTRAINT fk_servicos_exclusao_usuario FOREIGN KEY (excluido_por) REFERENCES usuarios(id) ON UPDATE CASCADE ON DELETE SET NULL', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

INSERT INTO permissoes (grupo, modulo, codigo, nome, descricao, ordem, status) VALUES
('Clientes', 'cliente', 'cliente.excluir', 'Excluir clientes', 'Permite excluir logicamente clientes sem apagar o historico.', 150, 'ativo'),
('Orçamentos', 'orcamento', 'orcamento.excluir', 'Excluir orçamentos', 'Permite excluir logicamente orcamentos sem apagar o historico.', 480, 'ativo'),
('Serviços', 'servico', 'servico.excluir', 'Excluir serviços', 'Permite excluir logicamente servicos sem apagar o historico.', 940, 'ativo')
ON DUPLICATE KEY UPDATE
    grupo = VALUES(grupo),
    modulo = VALUES(modulo),
    nome = VALUES(nome),
    descricao = VALUES(descricao),
    ordem = VALUES(ordem),
    status = VALUES(status);

INSERT IGNORE INTO perfil_permissoes (perfil_id, permissao_id)
SELECT perfil.id, permissao.id
  FROM perfis perfil
  JOIN permissoes permissao ON permissao.codigo IN ('cliente.excluir', 'orcamento.excluir', 'servico.excluir')
 WHERE perfil.nome IN ('Administrador', 'Dono', 'Gerente')
   AND permissao.status = 'ativo';


-- -----------------------------------------------------------------------------
-- Migration: 023_repair_receipt_permissions.sql
-- -----------------------------------------------------------------------------
-- Migration 023 - Repara permissoes de recibo em bancos atualizados por migrations.
-- Garante que recibos pagos aparecam nos menus sem depender dos seeds de instalacao nova.

SET NAMES utf8mb4;

INSERT INTO permissoes (grupo, modulo, codigo, nome, descricao, ordem, status) VALUES
('Fiscal', 'recibo', 'recibo.visualizar', 'Visualizar recibos', 'Permite consultar o historico de recibos.', 1640, 'ativo'),
('Fiscal', 'recibo', 'recibo.emitir', 'Emitir recibos', 'Permite emitir recibos vinculados a pagamentos ou avulsos.', 1650, 'ativo'),
('Fiscal', 'recibo', 'recibo.reimprimir', 'Reimprimir recibos', 'Permite abrir e reimprimir recibos emitidos.', 1660, 'ativo'),
('Fiscal', 'recibo', 'recibo.cancelar', 'Cancelar recibos', 'Permite cancelar recibos preservando o historico.', 1670, 'ativo')
ON DUPLICATE KEY UPDATE
    grupo = VALUES(grupo), modulo = VALUES(modulo), nome = VALUES(nome),
    descricao = VALUES(descricao), ordem = VALUES(ordem), status = 'ativo';

INSERT IGNORE INTO perfil_permissoes (perfil_id, permissao_id)
SELECT perfil.id, permissao.id
  FROM perfis perfil
  JOIN permissoes permissao ON permissao.codigo IN (
      'recibo.visualizar', 'recibo.emitir', 'recibo.reimprimir', 'recibo.cancelar'
  )
 WHERE perfil.nome IN ('Administrador', 'Dono', 'Gerente')
   AND permissao.status = 'ativo';

INSERT IGNORE INTO perfil_permissoes (perfil_id, permissao_id)
SELECT perfil.id, permissao.id
  FROM perfis perfil
  JOIN permissoes permissao ON permissao.codigo IN (
      'recibo.visualizar', 'recibo.emitir', 'recibo.reimprimir'
  )
 WHERE perfil.nome = 'Recep??o'
   AND permissao.status = 'ativo';


-- -----------------------------------------------------------------------------
-- Migration: 024_budget_audit_so_integration.sql
-- -----------------------------------------------------------------------------
-- Migration 024 - Auditoria imutável de orçamento e integração idempotente com o SO.
SET NAMES utf8mb4;

ALTER TABLE perfis
    ADD COLUMN IF NOT EXISTS codigo VARCHAR(80) NULL AFTER nome,
    ADD UNIQUE KEY IF NOT EXISTS uk_perfis_codigo (codigo);

UPDATE perfis
   SET codigo = 'suporte'
 WHERE codigo IS NULL
   AND LOWER(TRIM(nome)) = 'suporte';

ALTER TABLE orcamentos
    ADD COLUMN IF NOT EXISTS criado_por_usuario_id INT UNSIGNED NULL,
    ADD COLUMN IF NOT EXISTS criado_por_nome_snapshot VARCHAR(150) NULL,
    ADD COLUMN IF NOT EXISTS criado_por_perfil_id_snapshot INT UNSIGNED NULL,
    ADD COLUMN IF NOT EXISTS criado_por_perfil_codigo_snapshot VARCHAR(80) NULL,
    ADD COLUMN IF NOT EXISTS criado_por_perfil_nome_snapshot VARCHAR(100) NULL,
    ADD COLUMN IF NOT EXISTS criado_por_suporte TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS aprovado_por_usuario_id INT UNSIGNED NULL,
    ADD COLUMN IF NOT EXISTS aprovado_por_nome_snapshot VARCHAR(150) NULL,
    ADD COLUMN IF NOT EXISTS aprovado_por_perfil_id_snapshot INT UNSIGNED NULL,
    ADD COLUMN IF NOT EXISTS aprovado_por_perfil_codigo_snapshot VARCHAR(80) NULL,
    ADD COLUMN IF NOT EXISTS aprovado_por_perfil_nome_snapshot VARCHAR(100) NULL,
    ADD COLUMN IF NOT EXISTS so_evento_uuid CHAR(36) NULL,
    ADD COLUMN IF NOT EXISTS so_aquisicao_id BIGINT UNSIGNED NULL,
    ADD COLUMN IF NOT EXISTS so_aquisicao_numero VARCHAR(50) NULL,
    ADD COLUMN IF NOT EXISTS so_codigo_entrega VARCHAR(50) NULL,
    ADD COLUMN IF NOT EXISTS so_aquisicao_status VARCHAR(50) NULL,
    ADD COLUMN IF NOT EXISTS so_sincronizado_em DATETIME NULL,
    ADD KEY IF NOT EXISTS idx_orcamentos_criado_por (criado_por_usuario_id),
    ADD KEY IF NOT EXISTS idx_orcamentos_aprovado_por (aprovado_por_usuario_id),
    ADD KEY IF NOT EXISTS idx_orcamentos_criado_por_suporte (criado_por_suporte),
    ADD UNIQUE KEY IF NOT EXISTS uk_orcamentos_so_evento_uuid (so_evento_uuid),
    ADD UNIQUE KEY IF NOT EXISTS uk_orcamentos_so_aquisicao_id (so_aquisicao_id),
    ADD CONSTRAINT fk_orcamentos_criado_por_usuario FOREIGN KEY (criado_por_usuario_id)
        REFERENCES usuarios(id) ON UPDATE CASCADE ON DELETE SET NULL,
    ADD CONSTRAINT fk_orcamentos_aprovado_por_usuario FOREIGN KEY (aprovado_por_usuario_id)
        REFERENCES usuarios(id) ON UPDATE CASCADE ON DELETE SET NULL;

CREATE TABLE IF NOT EXISTS orcamento_integracoes_so (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    orcamento_id INT UNSIGNED NOT NULL,
    evento_uuid CHAR(36) NOT NULL,
    tipo_evento VARCHAR(80) NOT NULL,
    status ENUM('pendente', 'processando', 'sincronizado', 'falhou') NOT NULL DEFAULT 'pendente',
    tentativas INT UNSIGNED NOT NULL DEFAULT 0,
    requisicao_json LONGTEXT NULL,
    resposta_json LONGTEXT NULL,
    http_status SMALLINT UNSIGNED NULL,
    so_aquisicao_id BIGINT UNSIGNED NULL,
    so_aquisicao_numero VARCHAR(50) NULL,
    so_codigo_entrega VARCHAR(50) NULL,
    so_status VARCHAR(50) NULL,
    ultimo_erro VARCHAR(1000) NULL,
    ultima_tentativa_em DATETIME NULL,
    proxima_tentativa_em DATETIME NULL,
    sincronizado_em DATETIME NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_integracao_so_orcamento (orcamento_id),
    UNIQUE KEY uk_integracao_so_evento (evento_uuid),
    KEY idx_integracao_so_status_tentativa (status, proxima_tentativa_em),
    CONSTRAINT fk_integracao_so_orcamento FOREIGN KEY (orcamento_id)
        REFERENCES orcamentos(id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO permissoes (grupo, modulo, codigo, nome, descricao, ordem, status) VALUES
('Orçamentos', 'orcamento', 'orcamento.integracao_so.visualizar', 'Visualizar integração SO', 'Permite consultar a situação da integração com o SO.', 1230, 'ativo'),
('Orçamentos', 'orcamento', 'orcamento.integracao_so.reprocessar', 'Reprocessar integração SO', 'Permite reenviar uma integração de orçamento com o SO.', 1240, 'ativo')
ON DUPLICATE KEY UPDATE nome = VALUES(nome), descricao = VALUES(descricao), status = 'ativo';

INSERT IGNORE INTO perfil_permissoes (perfil_id, permissao_id)
SELECT perfil.id, permissao.id
  FROM perfis perfil
  JOIN permissoes permissao ON permissao.codigo IN ('orcamento.integracao_so.visualizar', 'orcamento.integracao_so.reprocessar')
 WHERE perfil.nome IN ('Administrador', 'Dono', 'Gerente')
   AND permissao.status = 'ativo';


