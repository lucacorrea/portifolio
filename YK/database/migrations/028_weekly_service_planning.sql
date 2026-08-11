-- Migration 028
-- Planejamento semanal separado das Ordens de Serviço.
-- A OS será criada somente após confirmação explícita.
-- Compatibilidade: MariaDB 10.4+, InnoDB, utf8mb4.

SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS servicos_semanais (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    codigo VARCHAR(20) NULL,

    cliente_id INT UNSIGNED NOT NULL,
    servico_id INT UNSIGNED NOT NULL,

    prioridade ENUM(
        'baixa',
        'media',
        'alta',
        'urgente'
    ) NOT NULL DEFAULT 'media',

    local_servico VARCHAR(150) NULL,

    agendado_inicio DATETIME NOT NULL,
    agendado_fim DATETIME NOT NULL,

    funcionario_principal_id INT UNSIGNED NULL,
    funcionario_apoio_id INT UNSIGNED NULL,

    observacao TEXT NULL,

    status ENUM(
        'aguardando_confirmacao',
        'confirmado',
        'cancelado'
    ) NOT NULL DEFAULT 'aguardando_confirmacao',

    ordem_servico_id INT UNSIGNED NULL,

    confirmado_em DATETIME NULL,
    confirmado_por INT UNSIGNED NULL,

    cancelado_em DATETIME NULL,
    cancelado_por INT UNSIGNED NULL,
    motivo_cancelamento VARCHAR(255) NULL,

    criado_por INT UNSIGNED NOT NULL,

    criado_em DATETIME NOT NULL
        DEFAULT CURRENT_TIMESTAMP,

    atualizado_em DATETIME NOT NULL
        DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uq_servicos_semanais_codigo (
        codigo
    ),

    UNIQUE KEY uq_servicos_semanais_ordem (
        ordem_servico_id
    ),

    KEY idx_servicos_semanais_status_data (
        status,
        agendado_inicio
    ),

    KEY idx_servicos_semanais_periodo (
        agendado_inicio,
        agendado_fim
    ),

    KEY idx_servicos_semanais_cliente (
        cliente_id
    ),

    KEY idx_servicos_semanais_servico (
        servico_id
    ),

    KEY idx_servicos_semanais_principal (
        funcionario_principal_id
    ),

    KEY idx_servicos_semanais_apoio (
        funcionario_apoio_id
    ),

    KEY idx_servicos_semanais_criado_por (
        criado_por
    ),

    KEY idx_servicos_semanais_confirmado_por (
        confirmado_por
    ),

    KEY idx_servicos_semanais_cancelado_por (
        cancelado_por
    ),

    CONSTRAINT fk_servico_semanal_cliente
        FOREIGN KEY (cliente_id)
        REFERENCES clientes (id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    CONSTRAINT fk_servico_semanal_catalogo
        FOREIGN KEY (servico_id)
        REFERENCES servicos (id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    CONSTRAINT fk_servico_semanal_principal
        FOREIGN KEY (funcionario_principal_id)
        REFERENCES funcionarios (id)
        ON UPDATE CASCADE
        ON DELETE SET NULL,

    CONSTRAINT fk_servico_semanal_apoio
        FOREIGN KEY (funcionario_apoio_id)
        REFERENCES funcionarios (id)
        ON UPDATE CASCADE
        ON DELETE SET NULL,

    CONSTRAINT fk_servico_semanal_ordem
        FOREIGN KEY (ordem_servico_id)
        REFERENCES ordens_servico (id)
        ON UPDATE CASCADE
        ON DELETE SET NULL,

    CONSTRAINT fk_servico_semanal_criado_usuario
        FOREIGN KEY (criado_por)
        REFERENCES usuarios (id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    CONSTRAINT fk_servico_semanal_confirmado_usuario
        FOREIGN KEY (confirmado_por)
        REFERENCES usuarios (id)
        ON UPDATE CASCADE
        ON DELETE SET NULL,

    CONSTRAINT fk_servico_semanal_cancelado_usuario
        FOREIGN KEY (cancelado_por)
        REFERENCES usuarios (id)
        ON UPDATE CASCADE
        ON DELETE SET NULL
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;