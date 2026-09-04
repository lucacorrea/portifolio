-- ============================================================================
-- SIGAS COARI
-- Correção das views de integração ANEXO <-> SIGAS
-- Data: 2026-09-04
--
-- MOTIVO
-- As views consolidadas de integração apresentavam erro MariaDB #1271
-- (Illegal mix of collations for operation 'UNION').
--
-- OBJETIVO
-- Normalizar explicitamente todas as colunas textuais para utf8mb4_unicode_ci,
-- preservando a integração somente leitura e as regras atuais dos módulos.
--
-- SEGURANÇA
-- - Não altera dados de pessoas, famílias, inscrições ou candidatos.
-- - Não cria triggers.
-- - Não grava dados no ANEXO.
-- - Apenas recria as views usadas pela camada de integração.
-- - Pode ser executado novamente.
-- ============================================================================

USE `u784961086_sigas`;

SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;
SET collation_connection = 'utf8mb4_unicode_ci';

-- ============================================================================
-- 1. PRIMEIRO EMPREGO
-- ============================================================================

CREATE OR REPLACE VIEW `vw_sigas_integracao_primeiro_emprego` AS
SELECT
    CONVERT(c.cpf USING utf8mb4) COLLATE utf8mb4_unicode_ci AS cpf,
    CONVERT(c.nome USING utf8mb4) COLLATE utf8mb4_unicode_ci AS pessoa_nome,

    CONVERT('primeiro_emprego' USING utf8mb4) COLLATE utf8mb4_unicode_ci AS programa_slug,
    CONVERT('Coari Meu Primeiro Emprego' USING utf8mb4) COLLATE utf8mb4_unicode_ci AS programa_nome,

    CAST(c.id AS UNSIGNED) AS vinculo_id,

    CONVERT(c.status USING utf8mb4) COLLATE utf8mb4_unicode_ci AS status_codigo,

    CONVERT(
        CASE
            WHEN c.revisao_status IS NOT NULL
                 AND TRIM(c.revisao_status) <> ''
                THEN c.revisao_status
            WHEN c.cpf_duplicado = 1
                THEN 'CPF duplicado'
            WHEN c.revisao_cpf = 1
                THEN 'Revisar CPF'
            WHEN c.revisao_telefone = 1
                THEN 'Revisar Telefone'
            WHEN c.revisao_nascimento = 1
                THEN 'Revisar Nascimento'
            ELSE c.status
        END
        USING utf8mb4
    ) COLLATE utf8mb4_unicode_ci AS status_label,

    CONVERT(
        CASE
            WHEN c.cpf IS NULL OR CHAR_LENGTH(TRIM(c.cpf)) <> 11
                THEN 'revisar'
            WHEN c.cpf_duplicado = 1
                 OR c.revisao_cpf = 1
                 OR c.revisao_telefone = 1
                 OR c.revisao_nascimento = 1
                 OR (
                        c.revisao_status IS NOT NULL
                        AND TRIM(c.revisao_status) <> ''
                    )
                THEN 'revisar'
            WHEN c.status = 'Contemplado'
                THEN 'ativo'
            WHEN LOWER(c.status) IN ('inativo', 'encerrado', 'desligado', 'cancelado')
                THEN 'inativo'
            ELSE 'pendente'
        END
        USING utf8mb4
    ) COLLATE utf8mb4_unicode_ci AS categoria_status,

    CONVERT(
        CASE
            WHEN l.id IS NOT NULL THEN 'Lotado'
            ELSE 'Não lotado'
        END
        USING utf8mb4
    ) COLLATE utf8mb4_unicode_ci AS situacao,

    CONVERT(COALESCE(l.local_atuacao, '') USING utf8mb4) COLLATE utf8mb4_unicode_ci AS unidade,
    CONVERT(COALESCE(l.setor, '') USING utf8mb4) COLLATE utf8mb4_unicode_ci AS setor,
    CONVERT(COALESCE(c.bairro, '') USING utf8mb4) COLLATE utf8mb4_unicode_ci AS bairro,

    CAST(c.created_at AS DATETIME) AS data_inicio,
    CAST(c.updated_at AS DATETIME) AS data_atualizacao,

    CONVERT(c.origem USING utf8mb4) COLLATE utf8mb4_unicode_ci AS origem,
    CONVERT('pe_candidatos' USING utf8mb4) COLLATE utf8mb4_unicode_ci AS fonte_tabela

FROM `pe_candidatos` c
LEFT JOIN `pe_lotacoes` l
       ON l.candidato_id = c.id
      AND l.status = 'Ativa'
WHERE c.cpf IS NOT NULL
  AND TRIM(c.cpf) <> '';

-- ============================================================================
-- 2. COMIDA NA MESA - RESPONSÁVEL FAMILIAR
-- ============================================================================

CREATE OR REPLACE VIEW `vw_sigas_integracao_comida_mesa_responsaveis` AS
SELECT
    CONVERT(p.cpf USING utf8mb4) COLLATE utf8mb4_unicode_ci AS cpf,
    CONVERT(p.nome USING utf8mb4) COLLATE utf8mb4_unicode_ci AS pessoa_nome,

    CONVERT('comida_na_mesa' USING utf8mb4) COLLATE utf8mb4_unicode_ci AS programa_slug,
    CONVERT('Coari Comida na Mesa' USING utf8mb4) COLLATE utf8mb4_unicode_ci AS programa_nome,

    CAST(i.id AS UNSIGNED) AS vinculo_id,

    CONVERT(i.status USING utf8mb4) COLLATE utf8mb4_unicode_ci AS status_codigo,

    CONVERT(
        CASE i.status
            WHEN 'ativa'        THEN 'Beneficiária ativa'
            WHEN 'em_analise'   THEN 'Em análise'
            WHEN 'lista_espera' THEN 'Lista de espera'
            WHEN 'suspensa'     THEN 'Suspensa'
            WHEN 'bloqueada'    THEN 'Bloqueada'
            ELSE i.status
        END
        USING utf8mb4
    ) COLLATE utf8mb4_unicode_ci AS status_label,

    CONVERT(
        CASE i.status
            WHEN 'ativa'        THEN 'ativo'
            WHEN 'em_analise'   THEN 'pendente'
            WHEN 'lista_espera' THEN 'pendente'
            WHEN 'suspensa'     THEN 'restrito'
            WHEN 'bloqueada'    THEN 'restrito'
            ELSE 'pendente'
        END
        USING utf8mb4
    ) COLLATE utf8mb4_unicode_ci AS categoria_status,

    CONVERT('Responsável familiar' USING utf8mb4) COLLATE utf8mb4_unicode_ci AS situacao,
    CONVERT(COALESCE(polo.nome, '') USING utf8mb4) COLLATE utf8mb4_unicode_ci AS unidade,
    CONVERT('' USING utf8mb4) COLLATE utf8mb4_unicode_ci AS setor,
    CONVERT(COALESCE(f.bairro, '') USING utf8mb4) COLLATE utf8mb4_unicode_ci AS bairro,

    CAST(i.data_inscricao AS DATETIME) AS data_inicio,
    CAST(COALESCE(i.atualizado_em, i.criado_em) AS DATETIME) AS data_atualizacao,

    CONVERT('responsavel_familiar' USING utf8mb4) COLLATE utf8mb4_unicode_ci AS origem,
    CONVERT('comida_mesa_inscricoes' USING utf8mb4) COLLATE utf8mb4_unicode_ci AS fonte_tabela

FROM `comida_mesa_inscricoes` i
INNER JOIN `familias` f
        ON f.id = i.familia_id
INNER JOIN `pessoas` p
        ON p.id = f.responsavel_pessoa_id
LEFT JOIN `comida_mesa_polos` polo
       ON polo.id = i.polo_id
WHERE p.cpf IS NOT NULL
  AND TRIM(p.cpf) <> '';

-- ============================================================================
-- 3. COMIDA NA MESA - DEMAIS MEMBROS DA FAMÍLIA
--
-- O responsável principal é excluído desta view para evitar duplicidade no
-- UNION ALL da visão consolidada.
-- ============================================================================

CREATE OR REPLACE VIEW `vw_sigas_integracao_comida_mesa_membros` AS
SELECT
    CONVERT(p.cpf USING utf8mb4) COLLATE utf8mb4_unicode_ci AS cpf,
    CONVERT(p.nome USING utf8mb4) COLLATE utf8mb4_unicode_ci AS pessoa_nome,

    CONVERT('comida_na_mesa' USING utf8mb4) COLLATE utf8mb4_unicode_ci AS programa_slug,
    CONVERT('Coari Comida na Mesa' USING utf8mb4) COLLATE utf8mb4_unicode_ci AS programa_nome,

    CAST(i.id AS UNSIGNED) AS vinculo_id,

    CONVERT(i.status USING utf8mb4) COLLATE utf8mb4_unicode_ci AS status_codigo,

    CONVERT(
        CASE i.status
            WHEN 'ativa'        THEN 'Beneficiária ativa'
            WHEN 'em_analise'   THEN 'Em análise'
            WHEN 'lista_espera' THEN 'Lista de espera'
            WHEN 'suspensa'     THEN 'Suspensa'
            WHEN 'bloqueada'    THEN 'Bloqueada'
            ELSE i.status
        END
        USING utf8mb4
    ) COLLATE utf8mb4_unicode_ci AS status_label,

    CONVERT(
        CASE i.status
            WHEN 'ativa'        THEN 'ativo'
            WHEN 'em_analise'   THEN 'pendente'
            WHEN 'lista_espera' THEN 'pendente'
            WHEN 'suspensa'     THEN 'restrito'
            WHEN 'bloqueada'    THEN 'restrito'
            ELSE 'pendente'
        END
        USING utf8mb4
    ) COLLATE utf8mb4_unicode_ci AS categoria_status,

    CONVERT(
        CASE
            WHEN fm.responsavel = 1 THEN 'Responsável familiar'
            WHEN fm.parentesco IS NOT NULL AND TRIM(fm.parentesco) <> ''
                THEN CONCAT('Membro familiar - ', fm.parentesco)
            ELSE 'Membro familiar'
        END
        USING utf8mb4
    ) COLLATE utf8mb4_unicode_ci AS situacao,

    CONVERT(COALESCE(polo.nome, '') USING utf8mb4) COLLATE utf8mb4_unicode_ci AS unidade,
    CONVERT('' USING utf8mb4) COLLATE utf8mb4_unicode_ci AS setor,
    CONVERT(COALESCE(f.bairro, '') USING utf8mb4) COLLATE utf8mb4_unicode_ci AS bairro,

    CAST(i.data_inscricao AS DATETIME) AS data_inicio,
    CAST(COALESCE(i.atualizado_em, i.criado_em) AS DATETIME) AS data_atualizacao,

    CONVERT('membro_familiar' USING utf8mb4) COLLATE utf8mb4_unicode_ci AS origem,
    CONVERT('comida_mesa_inscricoes' USING utf8mb4) COLLATE utf8mb4_unicode_ci AS fonte_tabela

FROM `comida_mesa_inscricoes` i
INNER JOIN `familias` f
        ON f.id = i.familia_id
INNER JOIN `familia_membros` fm
        ON fm.familia_id = f.id
INNER JOIN `pessoas` p
        ON p.id = fm.pessoa_id
LEFT JOIN `comida_mesa_polos` polo
       ON polo.id = i.polo_id
WHERE p.cpf IS NOT NULL
  AND TRIM(p.cpf) <> ''
  AND p.id <> f.responsavel_pessoa_id;

-- ============================================================================
-- 4. COMIDA NA MESA - VISÃO CONSOLIDADA
-- ============================================================================

CREATE OR REPLACE VIEW `vw_sigas_integracao_comida_mesa` AS
SELECT
    cpf,
    pessoa_nome,
    programa_slug,
    programa_nome,
    vinculo_id,
    status_codigo,
    status_label,
    categoria_status,
    situacao,
    unidade,
    setor,
    bairro,
    data_inicio,
    data_atualizacao,
    origem,
    fonte_tabela
FROM `vw_sigas_integracao_comida_mesa_responsaveis`

UNION ALL

SELECT
    cpf,
    pessoa_nome,
    programa_slug,
    programa_nome,
    vinculo_id,
    status_codigo,
    status_label,
    categoria_status,
    situacao,
    unidade,
    setor,
    bairro,
    data_inicio,
    data_atualizacao,
    origem,
    fonte_tabela
FROM `vw_sigas_integracao_comida_mesa_membros`;

-- ============================================================================
-- 5. VIEW PRINCIPAL USADA PELO ANEXO
-- ============================================================================

CREATE OR REPLACE VIEW `vw_sigas_integracao_beneficios` AS
SELECT
    cpf,
    pessoa_nome,
    programa_slug,
    programa_nome,
    vinculo_id,
    status_codigo,
    status_label,
    categoria_status,
    situacao,
    unidade,
    setor,
    bairro,
    data_inicio,
    data_atualizacao,
    origem,
    fonte_tabela
FROM `vw_sigas_integracao_primeiro_emprego`

UNION ALL

SELECT
    cpf,
    pessoa_nome,
    programa_slug,
    programa_nome,
    vinculo_id,
    status_codigo,
    status_label,
    categoria_status,
    situacao,
    unidade,
    setor,
    bairro,
    data_inicio,
    data_atualizacao,
    origem,
    fonte_tabela
FROM `vw_sigas_integracao_comida_mesa`;

-- ============================================================================
-- 6. RESUMO POR CPF
-- ============================================================================

CREATE OR REPLACE VIEW `vw_sigas_integracao_resumo_cpf` AS
SELECT
    cpf,
    MAX(pessoa_nome) AS pessoa_nome,
    COUNT(DISTINCT programa_slug) AS quantidade_programas,
    GROUP_CONCAT(
        DISTINCT programa_nome
        ORDER BY programa_nome
        SEPARATOR ' | '
    ) AS programas,
    MAX(CASE WHEN categoria_status = 'ativo' THEN 1 ELSE 0 END) AS possui_ativo,
    MAX(CASE WHEN categoria_status = 'pendente' THEN 1 ELSE 0 END) AS possui_pendente,
    MAX(CASE WHEN categoria_status = 'revisar' THEN 1 ELSE 0 END) AS possui_revisao,
    MAX(CASE WHEN categoria_status = 'restrito' THEN 1 ELSE 0 END) AS possui_restricao
FROM `vw_sigas_integracao_beneficios`
WHERE cpf IS NOT NULL
  AND CHAR_LENGTH(TRIM(cpf)) = 11
GROUP BY cpf;

-- ============================================================================
-- 7. INDICADORES DOS PROGRAMAS
-- ============================================================================

CREATE OR REPLACE VIEW `vw_sigas_integracao_indicadores` AS
SELECT
    programa_slug,
    programa_nome,
    COUNT(*) AS total_vinculos,
    COUNT(DISTINCT cpf) AS total_cpfs,
    SUM(CASE WHEN categoria_status = 'ativo' THEN 1 ELSE 0 END) AS ativos,
    SUM(CASE WHEN categoria_status = 'pendente' THEN 1 ELSE 0 END) AS pendentes,
    SUM(CASE WHEN categoria_status = 'revisar' THEN 1 ELSE 0 END) AS revisar,
    SUM(CASE WHEN categoria_status = 'restrito' THEN 1 ELSE 0 END) AS restritos
FROM `vw_sigas_integracao_beneficios`
GROUP BY programa_slug, programa_nome;

-- ============================================================================
-- 8. VALIDAÇÃO TÉCNICA
--
-- Estas consultas apenas leem as views e devem executar sem erro #1271.
-- ============================================================================

SELECT COUNT(*) AS total_primeiro_emprego
FROM `vw_sigas_integracao_primeiro_emprego`;

SELECT COUNT(*) AS total_comida_mesa
FROM `vw_sigas_integracao_comida_mesa`;

SELECT COUNT(*) AS total_vinculos_integracao
FROM `vw_sigas_integracao_beneficios`;

SELECT COUNT(*) AS total_cpfs_integrados
FROM `vw_sigas_integracao_resumo_cpf`;
