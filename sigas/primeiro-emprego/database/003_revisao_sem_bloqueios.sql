-- SIGAS / Meu Primeiro Emprego
-- Revisao cadastral sem bloqueio de importacao
-- Execute UMA VEZ no banco que ja esta na hospedagem, depois do 001/002.

SET NAMES utf8mb4;

-- CPF e chave de importacao deixam de ser identificadores unicos.
-- O identificador oficial do candidato passa a ser pe_candidatos.id.
ALTER TABLE pe_candidatos
    DROP INDEX uk_pe_candidatos_cpf,
    DROP INDEX uk_pe_candidatos_chave_importacao;

ALTER TABLE pe_candidatos
    ADD COLUMN revisao_status VARCHAR(40) NULL AFTER status,
    ADD COLUMN revisao_cpf TINYINT(1) NOT NULL DEFAULT 0 AFTER revisao_status,
    ADD COLUMN revisao_telefone TINYINT(1) NOT NULL DEFAULT 0 AFTER revisao_cpf,
    ADD COLUMN revisao_nascimento TINYINT(1) NOT NULL DEFAULT 0 AFTER revisao_telefone,
    ADD COLUMN cpf_duplicado TINYINT(1) NOT NULL DEFAULT 0 AFTER revisao_nascimento,
    ADD COLUMN revisao_motivos LONGTEXT NULL AFTER cpf_duplicado,
    ADD COLUMN revisao_atualizada_em DATETIME NULL AFTER revisao_motivos,
    ADD KEY idx_pe_candidatos_cpf_nao_unico (cpf),
    ADD KEY idx_pe_candidatos_chave_importacao (chave_importacao),
    ADD KEY idx_pe_candidatos_revisao_status (revisao_status),
    ADD KEY idx_pe_candidatos_revisao_cpf (revisao_cpf),
    ADD KEY idx_pe_candidatos_revisao_telefone (revisao_telefone),
    ADD KEY idx_pe_candidatos_revisao_nascimento (revisao_nascimento),
    ADD KEY idx_pe_candidatos_cpf_duplicado (cpf_duplicado);

ALTER TABLE pe_importacoes
    ADD COLUMN pendentes_revisao INT UNSIGNED NOT NULL DEFAULT 0 AFTER avisos;

-- Mantem compatibilidade com historico: a coluna bloqueados continua existindo,
-- mas novas importacoes deste fluxo nao bloqueiam candidatos por qualidade cadastral.

-- Classifica registros que já existiam antes desta atualização.
-- CPF inválido das versões anteriores normalmente já estava preservado em cpf_informado e com cpf = NULL.
UPDATE pe_candidatos
SET
    revisao_cpf = CASE WHEN cpf IS NULL OR cpf = '' THEN 1 ELSE 0 END,
    revisao_telefone = CASE WHEN telefone IS NULL OR telefone = '' OR CHAR_LENGTH(telefone) NOT IN (10,11) THEN 1 ELSE 0 END,
    revisao_nascimento = CASE WHEN data_nascimento IS NULL THEN 1 ELSE 0 END,
    cpf_duplicado = 0;

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
        CASE WHEN revisao_cpf = 1 AND (cpf_informado IS NULL OR cpf_informado = '') THEN 'CPF não informado' END,
        CASE WHEN revisao_cpf = 1 AND cpf_informado IS NOT NULL AND cpf_informado <> '' THEN 'CPF inconsistente' END,
        CASE WHEN revisao_telefone = 1 AND (telefone IS NULL OR telefone = '') THEN 'Telefone não informado' END,
        CASE WHEN revisao_telefone = 1 AND telefone IS NOT NULL AND telefone <> '' THEN 'Telefone fora do padrão' END,
        CASE WHEN revisao_nascimento = 1 THEN 'Data de nascimento não informada' END
    ),
    revisao_atualizada_em = CASE
        WHEN (revisao_cpf + revisao_telefone + revisao_nascimento) > 0 THEN CURRENT_TIMESTAMP
        ELSE NULL
    END;
