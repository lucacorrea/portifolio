-- SIGAS - Meu Primeiro Emprego
-- Estrutura própria de lotações e migração de compatibilidade.
-- Compatível com MariaDB 10.5+ / 11.x.
-- Pode ser executado novamente: a criação é idempotente e a migração evita duplicar lotação ativa.

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS pe_lotacoes (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    candidato_id BIGINT UNSIGNED NOT NULL,
    parceiro_id BIGINT UNSIGNED NULL,
    local_atuacao VARCHAR(180) NOT NULL,
    setor VARCHAR(160) NULL,
    turno_atuacao VARCHAR(30) NULL,
    data_inicio DATE NOT NULL,
    data_fim DATE NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'Ativa',
    origem VARCHAR(30) NOT NULL DEFAULT 'manual',
    observacao VARCHAR(500) NULL,
    registrado_por VARCHAR(160) NULL,
    candidato_ativo_id BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uk_pe_lotacoes_candidato_ativo (candidato_ativo_id),
    KEY idx_pe_lotacoes_candidato (candidato_id),
    KEY idx_pe_lotacoes_parceiro (parceiro_id),
    KEY idx_pe_lotacoes_local (local_atuacao),
    KEY idx_pe_lotacoes_setor (setor),
    KEY idx_pe_lotacoes_status (status),
    KEY idx_pe_lotacoes_inicio (data_inicio),

    FOREIGN KEY (candidato_id)
        REFERENCES pe_candidatos(id)
        ON DELETE CASCADE,

    FOREIGN KEY (parceiro_id)
        REFERENCES pe_parceiros(id)
        ON DELETE SET NULL

) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;

-- Migra somente lotações antigas que ainda estejam exclusivamente na ficha cadastral.
-- Não sobrescreve nem duplica candidato que já tenha uma lotação ativa em pe_lotacoes.
INSERT INTO pe_lotacoes
(
    candidato_id,
    parceiro_id,
    local_atuacao,
    setor,
    turno_atuacao,
    data_inicio,
    data_fim,
    status,
    origem,
    observacao,
    registrado_por,
    candidato_ativo_id
)
SELECT
    f.candidato_id,
    NULL,
    TRIM(f.local_atuacao),
    NULL,
    NULLIF(TRIM(f.turno_atuacao), ''),
    COALESCE(DATE(f.updated_at), CURRENT_DATE()),
    NULL,
    'Ativa',
    'migracao_ficha',
    'Lotação migrada automaticamente da ficha cadastral legada.',
    NULL,
    f.candidato_id
FROM pe_fichas_cadastrais f
WHERE f.local_atuacao IS NOT NULL
  AND TRIM(f.local_atuacao) <> ''
  AND NOT EXISTS (
      SELECT 1
      FROM pe_lotacoes l
      WHERE l.candidato_id = f.candidato_id
        AND l.status = 'Ativa'
  );

-- Mantém candidato com lotação ativa coerente com o fluxo atual do programa.
UPDATE pe_candidatos c
INNER JOIN pe_lotacoes l
    ON l.candidato_id = c.id
   AND l.status = 'Ativa'
SET c.status = 'Contemplado',
    c.updated_at = CURRENT_TIMESTAMP
WHERE c.status <> 'Contemplado';
