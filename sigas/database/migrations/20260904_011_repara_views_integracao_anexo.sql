-- ============================================================================
-- SIGAS COARI
-- REPARO DAS VIEWS DE INTEGRAÇÃO ANEXO <-> SIGAS
-- Data: 2026-09-04
--
-- CONTEXTO
-- O dump de produção mostrou as views-base de integração reduzidas apenas à
-- coluna CPF, enquanto as views consolidadas continuam esperando 16 colunas.
-- Isso torna o contrato da integração inconsistente e pode fazer o ANEXO deixar
-- de localizar vínculos válidos.
--
-- SEGURANÇA
-- - NÃO altera pessoas, famílias, inscrições, candidatos ou entregas.
-- - NÃO cria vínculos artificiais.
-- - NÃO remove histórico de importação.
-- - Apenas remove e recria as views de leitura em ordem de dependência.
-- ============================================================================

USE `u784961086_sigas`;

SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;
SET collation_connection = 'utf8mb4_unicode_ci';

-- Remover primeiro as views dependentes para não manter referências inválidas.
DROP VIEW IF EXISTS `vw_sigas_integracao_indicadores`;
DROP VIEW IF EXISTS `vw_sigas_integracao_resumo_cpf`;
DROP VIEW IF EXISTS `vw_sigas_integracao_beneficios`;
DROP VIEW IF EXISTS `vw_sigas_integracao_comida_mesa`;
DROP VIEW IF EXISTS `vw_sigas_integracao_comida_mesa_membros`;
DROP VIEW IF EXISTS `vw_sigas_integracao_comida_mesa_responsaveis`;
DROP VIEW IF EXISTS `vw_sigas_integracao_primeiro_emprego`;

-- ============================================================================
-- 1. PRIMEIRO EMPREGO
-- ============================================================================
CREATE VIEW `vw_sigas_integracao_primeiro_emprego` AS
SELECT
    CAST(c.cpf AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AS cpf,
    CAST(c.nome AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AS pessoa_nome,
    CAST('primeiro_emprego' AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AS programa_slug,
    CAST('Coari Meu Primeiro Emprego' AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AS programa_nome,
    CAST(c.id AS UNSIGNED) AS vinculo_id,
    CAST(c.status AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AS status_codigo,
    CAST(
        CASE
            WHEN c.revisao_status IS NOT NULL AND TRIM(c.revisao_status) <> '' THEN c.revisao_status
            WHEN c.cpf_duplicado = 1 THEN 'CPF duplicado'
            WHEN c.revisao_cpf = 1 THEN 'Revisar CPF'
            WHEN c.revisao_telefone = 1 THEN 'Revisar Telefone'
            WHEN c.revisao_nascimento = 1 THEN 'Revisar Nascimento'
            ELSE c.status
        END AS CHAR CHARACTER SET utf8mb4
    ) COLLATE utf8mb4_unicode_ci AS status_label,
    CAST(
        CASE
            WHEN c.cpf IS NULL OR CHAR_LENGTH(TRIM(c.cpf)) <> 11 THEN 'revisar'
            WHEN c.cpf_duplicado = 1
              OR c.revisao_cpf = 1
              OR c.revisao_telefone = 1
              OR c.revisao_nascimento = 1
              OR (c.revisao_status IS NOT NULL AND TRIM(c.revisao_status) <> '') THEN 'revisar'
            WHEN c.status = 'Contemplado' THEN 'ativo'
            WHEN LOWER(c.status) IN ('inativo', 'encerrado', 'desligado', 'cancelado') THEN 'inativo'
            ELSE 'pendente'
        END AS CHAR CHARACTER SET utf8mb4
    ) COLLATE utf8mb4_unicode_ci AS categoria_status,
    CAST(CASE WHEN l.id IS NOT NULL THEN 'Lotado' ELSE 'Não lotado' END AS CHAR CHARACTER SET utf8mb4)
        COLLATE utf8mb4_unicode_ci AS situacao,
    CAST(COALESCE(l.local_atuacao, '') AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AS unidade,
    CAST(COALESCE(l.setor, '') AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AS setor,
    CAST(COALESCE(c.bairro, '') AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AS bairro,
    CAST(c.created_at AS DATETIME) AS data_inicio,
    CAST(c.updated_at AS DATETIME) AS data_atualizacao,
    CAST(c.origem AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AS origem,
    CAST('pe_candidatos' AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AS fonte_tabela
FROM `pe_candidatos` c
LEFT JOIN `pe_lotacoes` l
       ON l.candidato_id = c.id
      AND l.status = 'Ativa'
WHERE c.cpf IS NOT NULL
  AND CHAR_LENGTH(TRIM(c.cpf)) = 11;

-- ============================================================================
-- 2. COMIDA NA MESA - RESPONSÁVEIS
-- ============================================================================
CREATE VIEW `vw_sigas_integracao_comida_mesa_responsaveis` AS
SELECT
    CAST(p.cpf AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AS cpf,
    CAST(p.nome AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AS pessoa_nome,
    CAST('comida_na_mesa' AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AS programa_slug,
    CAST('Coari Comida na Mesa' AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AS programa_nome,
    CAST(i.id AS UNSIGNED) AS vinculo_id,
    CAST(i.status AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AS status_codigo,
    CAST(
        CASE i.status
            WHEN 'ativa' THEN 'Beneficiária ativa'
            WHEN 'em_analise' THEN 'Em análise'
            WHEN 'lista_espera' THEN 'Lista de espera'
            WHEN 'suspensa' THEN 'Suspensa'
            WHEN 'bloqueada' THEN 'Bloqueada'
            WHEN 'encerrada' THEN 'Encerrada'
            ELSE i.status
        END AS CHAR CHARACTER SET utf8mb4
    ) COLLATE utf8mb4_unicode_ci AS status_label,
    CAST(
        CASE i.status
            WHEN 'ativa' THEN 'ativo'
            WHEN 'em_analise' THEN 'pendente'
            WHEN 'lista_espera' THEN 'pendente'
            WHEN 'suspensa' THEN 'restrito'
            WHEN 'bloqueada' THEN 'restrito'
            WHEN 'encerrada' THEN 'inativo'
            ELSE 'pendente'
        END AS CHAR CHARACTER SET utf8mb4
    ) COLLATE utf8mb4_unicode_ci AS categoria_status,
    CAST('Responsável familiar' AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AS situacao,
    CAST(COALESCE(polo.nome, '') AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AS unidade,
    CAST('' AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AS setor,
    CAST(COALESCE(f.bairro, '') AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AS bairro,
    CAST(i.data_inscricao AS DATETIME) AS data_inicio,
    CAST(COALESCE(i.atualizado_em, i.criado_em) AS DATETIME) AS data_atualizacao,
    CAST('responsavel_familiar' AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AS origem,
    CAST('comida_mesa_inscricoes' AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AS fonte_tabela
FROM `comida_mesa_inscricoes` i
INNER JOIN `familias` f ON f.id = i.familia_id
INNER JOIN `pessoas` p ON p.id = f.responsavel_pessoa_id
LEFT JOIN `comida_mesa_polos` polo ON polo.id = i.polo_id
WHERE p.cpf IS NOT NULL
  AND CHAR_LENGTH(TRIM(p.cpf)) = 11;

-- ============================================================================
-- 3. COMIDA NA MESA - OUTROS MEMBROS
-- ============================================================================
CREATE VIEW `vw_sigas_integracao_comida_mesa_membros` AS
SELECT
    CAST(p.cpf AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AS cpf,
    CAST(p.nome AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AS pessoa_nome,
    CAST('comida_na_mesa' AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AS programa_slug,
    CAST('Coari Comida na Mesa' AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AS programa_nome,
    CAST(i.id AS UNSIGNED) AS vinculo_id,
    CAST(i.status AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AS status_codigo,
    CAST(
        CASE i.status
            WHEN 'ativa' THEN 'Beneficiária ativa'
            WHEN 'em_analise' THEN 'Em análise'
            WHEN 'lista_espera' THEN 'Lista de espera'
            WHEN 'suspensa' THEN 'Suspensa'
            WHEN 'bloqueada' THEN 'Bloqueada'
            WHEN 'encerrada' THEN 'Encerrada'
            ELSE i.status
        END AS CHAR CHARACTER SET utf8mb4
    ) COLLATE utf8mb4_unicode_ci AS status_label,
    CAST(
        CASE i.status
            WHEN 'ativa' THEN 'ativo'
            WHEN 'em_analise' THEN 'pendente'
            WHEN 'lista_espera' THEN 'pendente'
            WHEN 'suspensa' THEN 'restrito'
            WHEN 'bloqueada' THEN 'restrito'
            WHEN 'encerrada' THEN 'inativo'
            ELSE 'pendente'
        END AS CHAR CHARACTER SET utf8mb4
    ) COLLATE utf8mb4_unicode_ci AS categoria_status,
    CAST(
        CASE
            WHEN fm.responsavel = 1 THEN 'Responsável familiar'
            WHEN fm.parentesco IS NOT NULL AND TRIM(fm.parentesco) <> '' THEN CONCAT('Membro familiar - ', fm.parentesco)
            ELSE 'Membro familiar'
        END AS CHAR CHARACTER SET utf8mb4
    ) COLLATE utf8mb4_unicode_ci AS situacao,
    CAST(COALESCE(polo.nome, '') AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AS unidade,
    CAST('' AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AS setor,
    CAST(COALESCE(f.bairro, '') AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AS bairro,
    CAST(i.data_inscricao AS DATETIME) AS data_inicio,
    CAST(COALESCE(i.atualizado_em, i.criado_em) AS DATETIME) AS data_atualizacao,
    CAST('membro_familiar' AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AS origem,
    CAST('comida_mesa_inscricoes' AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AS fonte_tabela
FROM `comida_mesa_inscricoes` i
INNER JOIN `familias` f ON f.id = i.familia_id
INNER JOIN `familia_membros` fm ON fm.familia_id = f.id
INNER JOIN `pessoas` p ON p.id = fm.pessoa_id
LEFT JOIN `comida_mesa_polos` polo ON polo.id = i.polo_id
WHERE p.cpf IS NOT NULL
  AND CHAR_LENGTH(TRIM(p.cpf)) = 11
  AND p.id <> f.responsavel_pessoa_id;

-- ============================================================================
-- 4. COMIDA NA MESA CONSOLIDADO
-- ============================================================================
CREATE VIEW `vw_sigas_integracao_comida_mesa` AS
SELECT
    cpf, pessoa_nome, programa_slug, programa_nome, vinculo_id,
    status_codigo, status_label, categoria_status, situacao,
    unidade, setor, bairro, data_inicio, data_atualizacao, origem, fonte_tabela
FROM `vw_sigas_integracao_comida_mesa_responsaveis`
UNION ALL
SELECT
    cpf, pessoa_nome, programa_slug, programa_nome, vinculo_id,
    status_codigo, status_label, categoria_status, situacao,
    unidade, setor, bairro, data_inicio, data_atualizacao, origem, fonte_tabela
FROM `vw_sigas_integracao_comida_mesa_membros`;

-- ============================================================================
-- 5. CONTRATO PRINCIPAL PARA SISTEMAS EXTERNOS
-- ============================================================================
CREATE VIEW `vw_sigas_integracao_beneficios` AS
SELECT
    cpf, pessoa_nome, programa_slug, programa_nome, vinculo_id,
    status_codigo, status_label, categoria_status, situacao,
    unidade, setor, bairro, data_inicio, data_atualizacao, origem, fonte_tabela
FROM `vw_sigas_integracao_primeiro_emprego`
UNION ALL
SELECT
    cpf, pessoa_nome, programa_slug, programa_nome, vinculo_id,
    status_codigo, status_label, categoria_status, situacao,
    unidade, setor, bairro, data_inicio, data_atualizacao, origem, fonte_tabela
FROM `vw_sigas_integracao_comida_mesa`;

-- ============================================================================
-- 6. RESUMO POR CPF
-- ============================================================================
CREATE VIEW `vw_sigas_integracao_resumo_cpf` AS
SELECT
    cpf,
    MAX(pessoa_nome) AS pessoa_nome,
    COUNT(DISTINCT programa_slug) AS quantidade_programas,
    GROUP_CONCAT(DISTINCT programa_nome ORDER BY programa_nome SEPARATOR ' | ') AS programas,
    MAX(CASE WHEN categoria_status = 'ativo' THEN 1 ELSE 0 END) AS possui_ativo,
    MAX(CASE WHEN categoria_status = 'pendente' THEN 1 ELSE 0 END) AS possui_pendente,
    MAX(CASE WHEN categoria_status = 'revisar' THEN 1 ELSE 0 END) AS possui_revisao,
    MAX(CASE WHEN categoria_status = 'restrito' THEN 1 ELSE 0 END) AS possui_restricao
FROM `vw_sigas_integracao_beneficios`
WHERE cpf IS NOT NULL
  AND CHAR_LENGTH(TRIM(cpf)) = 11
GROUP BY cpf;

-- ============================================================================
-- 7. INDICADORES
-- ============================================================================
CREATE VIEW `vw_sigas_integracao_indicadores` AS
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
-- 8. VALIDAÇÃO DO CONTRATO
-- Resultado esperado:
-- - primeiro_emprego: 16 colunas
-- - comida_mesa_responsaveis: 16 colunas
-- - comida_mesa_membros: 16 colunas
-- - comida_mesa: 16 colunas
-- - beneficios: 16 colunas
-- - resumo_cpf: 8 colunas
-- - indicadores: 8 colunas
-- ============================================================================
SELECT
    TABLE_NAME,
    COUNT(*) AS quantidade_colunas
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME IN (
      'vw_sigas_integracao_primeiro_emprego',
      'vw_sigas_integracao_comida_mesa_responsaveis',
      'vw_sigas_integracao_comida_mesa_membros',
      'vw_sigas_integracao_comida_mesa',
      'vw_sigas_integracao_beneficios',
      'vw_sigas_integracao_resumo_cpf',
      'vw_sigas_integracao_indicadores'
  )
GROUP BY TABLE_NAME
ORDER BY TABLE_NAME;

-- Caso real usado para validar a regularização de CPF feita em produção.
SELECT
    cpf,
    pessoa_nome,
    programa_slug,
    programa_nome,
    vinculo_id,
    status_codigo,
    status_label,
    categoria_status,
    situacao
FROM `vw_sigas_integracao_beneficios`
WHERE cpf = '76560090272';
