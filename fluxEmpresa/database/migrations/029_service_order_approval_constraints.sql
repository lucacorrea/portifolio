/* =====================================================================
 * MIGRATION 029 — COMPLEMENTO DA APROVAÇÃO DE ORDEM DE SERVIÇO
 * =====================================================================
 *
 * Compatibilidade:
 *   MariaDB 10.4+
 *
 * Objetivos:
 *   1. Garantir as colunas de aprovação e rejeição da OS.
 *   2. Criar os índices usados por filtros, relatórios e auditoria.
 *   3. Criar as chaves estrangeiras dos usuários responsáveis.
 *   4. Permanecer segura para reexecução.
 *
 * Observações:
 *   - Não consulta information_schema.
 *   - Não altera o status operacional da OS.
 *   - Não aprova automaticamente registros antigos.
 *   - Não dispara integração com o SO.
 *   - A coluna correta de criação da OS é criado_em.
 * ===================================================================== */

SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;


/* =====================================================================
 * 1. COLUNAS DE APROVAÇÃO
 * ===================================================================== */

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


/* =====================================================================
 * 2. ÍNDICES
 * ===================================================================== */

/*
 * Índice usado pela listagem e pelos filtros de aprovação da empresa.
 */
CREATE INDEX IF NOT EXISTS idx_os_empresa_aprovacao
ON ordens_servico (
    empresa_id,
    aprovacao_status,
    criado_em
);


/*
 * Índices usados para auditoria dos usuários responsáveis.
 */
CREATE INDEX IF NOT EXISTS idx_os_aprovada_por
ON ordens_servico (
    aprovada_por
);


CREATE INDEX IF NOT EXISTS idx_os_rejeitada_por
ON ordens_servico (
    rejeitada_por
);


/* =====================================================================
 * 3. CHAVES ESTRANGEIRAS
 * ===================================================================== */

/*
 * Usuário que aprovou a ordem de serviço.
 *
 * ON DELETE SET NULL mantém o histórico da OS caso o usuário
 * seja removido futuramente.
 */
ALTER TABLE ordens_servico
    ADD FOREIGN KEY IF NOT EXISTS fk_os_aprovada_por (
        aprovada_por
    )
    REFERENCES usuarios(id)
    ON UPDATE CASCADE
    ON DELETE SET NULL;


/*
 * Usuário que rejeitou a ordem de serviço.
 */
ALTER TABLE ordens_servico
    ADD FOREIGN KEY IF NOT EXISTS fk_os_rejeitada_por (
        rejeitada_por
    )
    REFERENCES usuarios(id)
    ON UPDATE CASCADE
    ON DELETE SET NULL;


/* =====================================================================
 * 4. NORMALIZAÇÃO SEGURA
 * ===================================================================== */

/*
 * Garante coerência para registros pendentes.
 *
 * Não altera OS aprovadas ou rejeitadas.
 */
UPDATE ordens_servico
SET
    aprovada_em = NULL,
    aprovada_por = NULL,
    rejeitada_em = NULL,
    rejeitada_por = NULL,
    motivo_rejeicao = NULL
WHERE aprovacao_status = 'pendente';


/*
 * Em uma OS aprovada, os dados de rejeição não devem permanecer.
 */
UPDATE ordens_servico
SET
    rejeitada_em = NULL,
    rejeitada_por = NULL,
    motivo_rejeicao = NULL
WHERE aprovacao_status = 'aprovada';


/*
 * Em uma OS rejeitada, os dados de aprovação não devem permanecer.
 */
UPDATE ordens_servico
SET
    aprovada_em = NULL,
    aprovada_por = NULL
WHERE aprovacao_status = 'rejeitada';


/* =====================================================================
 * 5. VERIFICAÇÃO COMPATÍVEL COM HOSPEDAGEM COMPARTILHADA
 * ===================================================================== */

SHOW COLUMNS
FROM ordens_servico
WHERE Field IN (
    'aprovacao_status',
    'aprovada_em',
    'aprovada_por',
    'rejeitada_em',
    'rejeitada_por',
    'motivo_rejeicao',
    'criado_em'
);


SHOW INDEX
FROM ordens_servico;


SHOW CREATE TABLE ordens_servico;