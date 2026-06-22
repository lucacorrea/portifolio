-- MODELO DE REFERÊNCIA. Ajuste nomes de banco, host e colunas com o DBA.
-- Não coloque a senha real no Git.

-- 1. Criar uma view mínima no banco do SEMTH.
CREATE OR REPLACE VIEW vw_sigas_pessoas_lookup AS
SELECT
    id AS legacy_id,
    REGEXP_REPLACE(cpf, '[^0-9]', '') AS cpf_normalizado,
    nome,
    data_nascimento,
    nis,
    'Ativo' AS situacao_registro,
    COALESCE(updated_at, created_at) AS atualizado_em,
    'SEMTH/SEMAS' AS unidade_origem
FROM solicitantes;

-- 2. Criar usuário técnico exclusivo. Substitua host e senha fora do repositório.
CREATE USER 'sigas_semth_reader'@'HOST_DO_SIGAS'
IDENTIFIED BY 'SENHA_FORTE_FORA_DO_GIT';

-- 3. Permitir somente leitura na view aprovada.
GRANT SELECT ON BANCO_SEMTH.vw_sigas_pessoas_lookup
TO 'sigas_semth_reader'@'HOST_DO_SIGAS';

-- 4. Não conceder acesso de escrita ou acesso amplo ao banco.
-- NÃO executar:
-- GRANT ALL PRIVILEGES ...
-- GRANT INSERT, UPDATE, DELETE, ALTER, DROP ...

FLUSH PRIVILEGES;

-- 5. Testes recomendados com a credencial técnica:
-- Deve funcionar:
-- SELECT * FROM BANCO_SEMTH.vw_sigas_pessoas_lookup LIMIT 1;
--
-- Devem falhar:
-- UPDATE BANCO_SEMTH.solicitantes SET nome = 'Teste' WHERE id = 1;
-- DELETE FROM BANCO_SEMTH.solicitantes WHERE id = 1;
-- INSERT INTO BANCO_SEMTH.solicitantes (...) VALUES (...);
