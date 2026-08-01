SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

ALTER TABLE ordens_servico
    ADD COLUMN IF NOT EXISTS aprovacao_status ENUM(
        'pendente',
        'aprovada',
        'rejeitada'
    ) NOT NULL DEFAULT 'pendente'
    AFTER prioridade,

    ADD COLUMN IF NOT EXISTS aprovada_em DATETIME NULL
    AFTER aprovacao_status,

    ADD COLUMN IF NOT EXISTS aprovada_por INT UNSIGNED NULL
    AFTER aprovada_em,

    ADD COLUMN IF NOT EXISTS rejeitada_em DATETIME NULL
    AFTER aprovada_por,

    ADD COLUMN IF NOT EXISTS rejeitada_por INT UNSIGNED NULL
    AFTER rejeitada_em,

    ADD COLUMN IF NOT EXISTS motivo_rejeicao VARCHAR(500) NULL
    AFTER rejeitada_por;