-- ROLLBACK PARCIAL - nao execute se ja houver CPFs/chaves duplicados gravados.
-- Antes de recriar UNIQUE, saneie os duplicados.
ALTER TABLE pe_candidatos
    DROP INDEX idx_pe_candidatos_cpf_nao_unico,
    DROP INDEX idx_pe_candidatos_chave_importacao,
    DROP INDEX idx_pe_candidatos_revisao_status,
    DROP INDEX idx_pe_candidatos_revisao_cpf,
    DROP INDEX idx_pe_candidatos_revisao_telefone,
    DROP INDEX idx_pe_candidatos_revisao_nascimento,
    DROP INDEX idx_pe_candidatos_cpf_duplicado,
    DROP COLUMN revisao_status,
    DROP COLUMN revisao_cpf,
    DROP COLUMN revisao_telefone,
    DROP COLUMN revisao_nascimento,
    DROP COLUMN cpf_duplicado,
    DROP COLUMN revisao_motivos,
    DROP COLUMN revisao_atualizada_em;

ALTER TABLE pe_importacoes DROP COLUMN pendentes_revisao;
