-- SIGAS - Coari Meu Primeiro Emprego
-- Atualizacao operacional de candidatos/importacao/revisao.
-- Compativel com MariaDB 10.5+ / 11.x. Pode ser executada novamente.

SET NAMES utf8mb4;

ALTER TABLE pe_candidatos
    DROP INDEX IF EXISTS uk_pe_candidatos_cpf,
    DROP INDEX IF EXISTS uk_pe_candidatos_chave_importacao,
    ADD COLUMN IF NOT EXISTS revisao_status VARCHAR(40) NULL AFTER status,
    ADD COLUMN IF NOT EXISTS revisao_cpf TINYINT(1) NOT NULL DEFAULT 0 AFTER revisao_status,
    ADD COLUMN IF NOT EXISTS revisao_telefone TINYINT(1) NOT NULL DEFAULT 0 AFTER revisao_cpf,
    ADD COLUMN IF NOT EXISTS revisao_nascimento TINYINT(1) NOT NULL DEFAULT 0 AFTER revisao_telefone,
    ADD COLUMN IF NOT EXISTS cpf_duplicado TINYINT(1) NOT NULL DEFAULT 0 AFTER revisao_nascimento,
    ADD COLUMN IF NOT EXISTS cpf_revisado_confirmado TINYINT(1) NOT NULL DEFAULT 0 AFTER cpf_duplicado,
    ADD COLUMN IF NOT EXISTS telefone_revisado_confirmado TINYINT(1) NOT NULL DEFAULT 0 AFTER cpf_revisado_confirmado,
    ADD COLUMN IF NOT EXISTS nascimento_revisado_confirmado TINYINT(1) NOT NULL DEFAULT 0 AFTER telefone_revisado_confirmado,
    ADD COLUMN IF NOT EXISTS cpf_duplicado_confirmado TINYINT(1) NOT NULL DEFAULT 0 AFTER nascimento_revisado_confirmado,
    ADD COLUMN IF NOT EXISTS revisao_motivos LONGTEXT NULL AFTER cpf_duplicado_confirmado,
    ADD COLUMN IF NOT EXISTS revisao_atualizada_em DATETIME NULL AFTER revisao_motivos,
    ADD COLUMN IF NOT EXISTS revisao_revisado_por VARCHAR(160) NULL AFTER revisao_atualizada_em,
    ADD COLUMN IF NOT EXISTS revisao_revisado_em DATETIME NULL AFTER revisao_revisado_por,
    ADD INDEX IF NOT EXISTS idx_pe_candidatos_cpf_nao_unico (cpf),
    ADD INDEX IF NOT EXISTS idx_pe_candidatos_chave_importacao (chave_importacao),
    ADD INDEX IF NOT EXISTS idx_pe_candidatos_revisao_status (revisao_status),
    ADD INDEX IF NOT EXISTS idx_pe_candidatos_revisao_cpf (revisao_cpf),
    ADD INDEX IF NOT EXISTS idx_pe_candidatos_revisao_telefone (revisao_telefone),
    ADD INDEX IF NOT EXISTS idx_pe_candidatos_revisao_nascimento (revisao_nascimento),
    ADD INDEX IF NOT EXISTS idx_pe_candidatos_cpf_duplicado (cpf_duplicado);

ALTER TABLE pe_importacoes
    ADD COLUMN IF NOT EXISTS pendentes_revisao INT UNSIGNED NOT NULL DEFAULT 0 AFTER avisos;

CREATE TABLE IF NOT EXISTS pe_revisoes_cadastrais (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    candidato_id BIGINT UNSIGNED NOT NULL,
    cpf_anterior VARCHAR(32) NULL,
    cpf_novo VARCHAR(32) NULL,
    telefone_anterior VARCHAR(20) NULL,
    telefone_novo VARCHAR(20) NULL,
    nascimento_anterior DATE NULL,
    nascimento_novo DATE NULL,
    confirmou_cpf TINYINT(1) NOT NULL DEFAULT 0,
    confirmou_telefone TINYINT(1) NOT NULL DEFAULT 0,
    confirmou_nascimento TINYINT(1) NOT NULL DEFAULT 0,
    confirmou_cpf_duplicado TINYINT(1) NOT NULL DEFAULT 0,
    observacao VARCHAR(500) NULL,
    revisado_por VARCHAR(160) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_pe_revisoes_candidato (candidato_id),
    KEY idx_pe_revisoes_data (created_at),
    CONSTRAINT fk_pe_revisoes_candidato
        FOREIGN KEY (candidato_id) REFERENCES pe_candidatos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Classificacao inicial de registros antigos ainda nao revisados.
UPDATE pe_candidatos
SET
    revisao_cpf = CASE
        WHEN cpf_revisado_confirmado = 1 THEN 0
        WHEN cpf IS NULL OR cpf = '' THEN 1
        ELSE 0
    END,
    revisao_telefone = CASE
        WHEN telefone_revisado_confirmado = 1 THEN 0
        WHEN telefone IS NULL OR telefone = '' OR CHAR_LENGTH(telefone) NOT IN (10, 11) THEN 1
        ELSE 0
    END,
    revisao_nascimento = CASE
        WHEN nascimento_revisado_confirmado = 1 THEN 0
        WHEN data_nascimento IS NULL THEN 1
        ELSE 0
    END;

-- Marca CPFs validamente armazenados que aparecem em mais de um candidato.
UPDATE pe_candidatos c
JOIN (
    SELECT cpf
    FROM pe_candidatos
    WHERE cpf IS NOT NULL AND cpf <> ''
    GROUP BY cpf
    HAVING COUNT(*) > 1
) d ON d.cpf = c.cpf
SET c.cpf_duplicado = 1;

UPDATE pe_candidatos c
LEFT JOIN (
    SELECT cpf
    FROM pe_candidatos
    WHERE cpf IS NOT NULL AND cpf <> ''
    GROUP BY cpf
    HAVING COUNT(*) > 1
) d ON d.cpf = c.cpf
SET c.cpf_duplicado = 0
WHERE d.cpf IS NULL;

UPDATE pe_candidatos
SET revisao_cpf = 1
WHERE cpf_duplicado = 1 AND cpf_duplicado_confirmado = 0;

UPDATE pe_candidatos
SET
    revisao_status = CASE
        WHEN (revisao_cpf + revisao_telefone + revisao_nascimento) = 0 THEN NULL
        WHEN (revisao_cpf + revisao_telefone + revisao_nascimento) > 1 THEN 'Revisar Cadastro'
        WHEN revisao_cpf = 1 THEN 'Revisar CPF'
        WHEN revisao_telefone = 1 THEN 'Revisar Telefone'
        ELSE 'Revisar Data de Nascimento'
    END,
    revisao_motivos = CONCAT_WS(' | ',
        CASE WHEN revisao_cpf = 1 AND cpf_duplicado = 1 AND cpf_duplicado_confirmado = 0 THEN 'CPF duplicado com outro cadastro' END,
        CASE WHEN revisao_cpf = 1 AND cpf_duplicado = 0 AND (cpf_informado IS NULL OR cpf_informado = '') THEN 'CPF não informado' END,
        CASE WHEN revisao_cpf = 1 AND cpf_duplicado = 0 AND cpf_informado IS NOT NULL AND cpf_informado <> '' THEN 'CPF inconsistente' END,
        CASE WHEN revisao_telefone = 1 AND (telefone IS NULL OR telefone = '') THEN 'Telefone não informado' END,
        CASE WHEN revisao_telefone = 1 AND telefone IS NOT NULL AND telefone <> '' THEN 'Telefone fora do padrão' END,
        CASE WHEN revisao_nascimento = 1 THEN 'Data de nascimento não informada' END
    ),
    revisao_atualizada_em = CASE
        WHEN (revisao_cpf + revisao_telefone + revisao_nascimento) > 0 THEN CURRENT_TIMESTAMP
        ELSE NULL
    END;
