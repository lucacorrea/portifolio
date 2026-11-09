SET NAMES utf8mb4;

-- ============================================================================
-- SIGAS | Acesso individual + rastreabilidade da pessoa
-- Compatibilidade alvo: MariaDB 11.x
--
-- IMPORTANTE:
-- DDL no MariaDB executa COMMIT implícito. Por isso esta migration não usa
-- START TRANSACTION/COMMIT como se CREATE/ALTER TABLE fossem reversíveis.
-- Todas as operações estruturais abaixo são idempotentes para permitir nova
-- execução caso uma etapa posterior falhe.
-- ============================================================================

-- Exceções individuais de ações. O nível continua sendo a regra padrão;
-- esta tabela registra somente diferenças explícitas para uma pessoa usuária.
CREATE TABLE IF NOT EXISTS usuario_permissao_excecoes (
    usuario_id BIGINT UNSIGNED NOT NULL,
    permissao_id BIGINT UNSIGNED NOT NULL,
    permitido TINYINT(1) NOT NULL,
    motivo VARCHAR(500) NULL,
    atualizado_por BIGINT UNSIGNED NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (usuario_id, permissao_id),
    KEY idx_usuario_permissao_excecoes_permissao (permissao_id),
    KEY idx_usuario_permissao_excecoes_permitido (permitido),
    KEY idx_usuario_permissao_excecoes_atualizado_por (atualizado_por),
    CONSTRAINT fk_usuario_permissao_excecoes_usuario
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_usuario_permissao_excecoes_permissao
        FOREIGN KEY (permissao_id) REFERENCES permissoes(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_usuario_permissao_excecoes_operador
        FOREIGN KEY (atualizado_por) REFERENCES usuarios(id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Um atendimento representa uma jornada da pessoa para uma finalidade concreta.
-- A pessoa não pertence a um setor: registramos onde começou e onde está agora.
CREATE TABLE IF NOT EXISTS pessoa_atendimentos (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    pessoa_id BIGINT UNSIGNED NOT NULL,
    protocolo VARCHAR(48) NOT NULL,
    finalidade VARCHAR(180) NOT NULL,
    beneficio_modulo VARCHAR(80) NULL,
    setor_origem_id BIGINT UNSIGNED NULL,
    modulo_origem VARCHAR(80) NOT NULL,
    setor_atual_id BIGINT UNSIGNED NULL,
    modulo_atual VARCHAR(80) NOT NULL,
    usuario_abertura_id BIGINT UNSIGNED NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'aberto',
    referencia_modulo VARCHAR(80) NULL,
    referencia_tipo VARCHAR(80) NULL,
    referencia_id BIGINT UNSIGNED NULL,
    observacao VARCHAR(500) NULL,
    aberto_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    concluido_em DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_pessoa_atendimentos_protocolo (protocolo),
    UNIQUE KEY uk_pessoa_atendimentos_referencia (referencia_modulo, referencia_tipo, referencia_id),
    KEY idx_pessoa_atendimentos_pessoa (pessoa_id, aberto_em),
    KEY idx_pessoa_atendimentos_status (status),
    KEY idx_pessoa_atendimentos_setor_origem (setor_origem_id),
    KEY idx_pessoa_atendimentos_setor_atual (setor_atual_id),
    KEY idx_pessoa_atendimentos_modulo_atual (modulo_atual),
    KEY idx_pessoa_atendimentos_fila_atual (setor_atual_id, modulo_atual, status),
    CONSTRAINT fk_pessoa_atendimentos_pessoa
        FOREIGN KEY (pessoa_id) REFERENCES pessoas(id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_pessoa_atendimentos_setor_origem
        FOREIGN KEY (setor_origem_id) REFERENCES setores(id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_pessoa_atendimentos_setor_atual
        FOREIGN KEY (setor_atual_id) REFERENCES setores(id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_pessoa_atendimentos_usuario_abertura
        FOREIGN KEY (usuario_abertura_id) REFERENCES usuarios(id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Se uma versão anterior desta migration chegou a criar a tabela sem o índice
-- composto da fila atual, a reexecução corrige a estrutura sem duplicar índice.
SET @sigas_has_fila_atual_index := (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'pessoa_atendimentos'
      AND INDEX_NAME = 'idx_pessoa_atendimentos_fila_atual'
);

SET @sigas_sql := IF(
    @sigas_has_fila_atual_index = 0,
    'ALTER TABLE pessoa_atendimentos ADD INDEX idx_pessoa_atendimentos_fila_atual (setor_atual_id, modulo_atual, status)',
    'SELECT 1'
);
PREPARE sigas_stmt FROM @sigas_sql;
EXECUTE sigas_stmt;
DEALLOCATE PREPARE sigas_stmt;

CREATE TABLE IF NOT EXISTS pessoa_movimentacoes (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    atendimento_id BIGINT UNSIGNED NOT NULL,
    pessoa_id BIGINT UNSIGNED NOT NULL,
    tipo VARCHAR(40) NOT NULL,
    setor_origem_id BIGINT UNSIGNED NULL,
    setor_destino_id BIGINT UNSIGNED NULL,
    modulo_origem VARCHAR(80) NULL,
    modulo_destino VARCHAR(80) NULL,
    usuario_id BIGINT UNSIGNED NULL,
    observacao VARCHAR(500) NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_pessoa_movimentacoes_atendimento (atendimento_id, criado_em),
    KEY idx_pessoa_movimentacoes_pessoa (pessoa_id, criado_em),
    KEY idx_pessoa_movimentacoes_setor_destino (setor_destino_id),
    KEY idx_pessoa_movimentacoes_usuario (usuario_id),
    CONSTRAINT fk_pessoa_movimentacoes_atendimento
        FOREIGN KEY (atendimento_id) REFERENCES pessoa_atendimentos(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_pessoa_movimentacoes_pessoa
        FOREIGN KEY (pessoa_id) REFERENCES pessoas(id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_pessoa_movimentacoes_setor_origem
        FOREIGN KEY (setor_origem_id) REFERENCES setores(id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_pessoa_movimentacoes_setor_destino
        FOREIGN KEY (setor_destino_id) REFERENCES setores(id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_pessoa_movimentacoes_usuario
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- Primeiro Emprego -> pessoa central
--
-- Usamos information_schema em vez de depender de ADD COLUMN/INDEX IF NOT
-- EXISTS. Isso deixa a reexecução previsível no MariaDB e também evita erro em
-- ambientes onde a coluna/índice já tenham sido aplicados manualmente.
-- ============================================================================

SET @sigas_has_pe_pessoa_column := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'pe_candidatos'
      AND COLUMN_NAME = 'pessoa_id'
);

SET @sigas_sql := IF(
    @sigas_has_pe_pessoa_column = 0,
    'ALTER TABLE pe_candidatos ADD COLUMN pessoa_id BIGINT UNSIGNED NULL AFTER id',
    'SELECT 1'
);
PREPARE sigas_stmt FROM @sigas_sql;
EXECUTE sigas_stmt;
DEALLOCATE PREPARE sigas_stmt;

SET @sigas_has_pe_pessoa_index := (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'pe_candidatos'
      AND INDEX_NAME = 'idx_pe_candidatos_pessoa_id'
);

SET @sigas_sql := IF(
    @sigas_has_pe_pessoa_index = 0,
    'ALTER TABLE pe_candidatos ADD INDEX idx_pe_candidatos_pessoa_id (pessoa_id)',
    'SELECT 1'
);
PREPARE sigas_stmt FROM @sigas_sql;
EXECUTE sigas_stmt;
DEALLOCATE PREPARE sigas_stmt;

SET @sigas_has_pe_pessoa_fk := (
    SELECT COUNT(*)
    FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
      AND TABLE_NAME = 'pe_candidatos'
      AND CONSTRAINT_NAME = 'fk_pe_candidatos_pessoa'
      AND CONSTRAINT_TYPE = 'FOREIGN KEY'
);

SET @sigas_sql := IF(
    @sigas_has_pe_pessoa_fk = 0,
    'ALTER TABLE pe_candidatos ADD CONSTRAINT fk_pe_candidatos_pessoa FOREIGN KEY (pessoa_id) REFERENCES pessoas(id) ON DELETE SET NULL ON UPDATE CASCADE',
    'SELECT 1'
);
PREPARE sigas_stmt FROM @sigas_sql;
EXECUTE sigas_stmt;
DEALLOCATE PREPARE sigas_stmt;

-- Backfill conservador.
-- Só vincula automaticamente quando:
-- 1) o CPF já está normalizado em 11 dígitos;
-- 2) o candidato não está marcado para revisar CPF;
-- 3) o candidato não está marcado como CPF duplicado;
-- 4) esse CPF aparece uma única vez em pe_candidatos.
-- Assim um conflito histórico nunca une duas pessoas por automação.
DROP TEMPORARY TABLE IF EXISTS tmp_sigas_pe_cpf_unicos;
CREATE TEMPORARY TABLE tmp_sigas_pe_cpf_unicos
ENGINE=MEMORY
AS
SELECT cpf
FROM pe_candidatos
WHERE cpf IS NOT NULL
  AND CHAR_LENGTH(cpf) = 11
GROUP BY cpf
HAVING COUNT(*) = 1;

ALTER TABLE tmp_sigas_pe_cpf_unicos
    ADD PRIMARY KEY (cpf);

UPDATE pe_candidatos c
INNER JOIN tmp_sigas_pe_cpf_unicos u ON u.cpf = c.cpf
INNER JOIN pessoas p ON p.cpf = c.cpf
SET c.pessoa_id = p.id
WHERE c.pessoa_id IS NULL
  AND COALESCE(c.revisao_cpf, 0) = 0
  AND COALESCE(c.cpf_duplicado, 0) = 0;

DROP TEMPORARY TABLE IF EXISTS tmp_sigas_pe_cpf_unicos;

-- ============================================================================
-- Governança
-- ============================================================================
INSERT INTO permissoes (nome, slug, descricao, modulo, ativo) VALUES
(
    'Gerenciar exceções individuais de acesso',
    'governanca.excecoes_usuario',
    'Permite alterar módulos e ações específicas para um usuário sem modificar o nível inteiro.',
    'governanca',
    1
)
ON DUPLICATE KEY UPDATE
    nome = VALUES(nome),
    descricao = VALUES(descricao),
    modulo = VALUES(modulo),
    ativo = VALUES(ativo);

INSERT IGNORE INTO nivel_permissoes (nivel_id, permissao_id)
SELECT n.id, p.id
FROM niveis_acesso n
INNER JOIN permissoes p ON p.slug = 'governanca.excecoes_usuario'
WHERE n.slug IN ('administrador', 'suporte');
