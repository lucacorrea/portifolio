-- Migration 029 - Local/ambiente por item de serviço da Ordem de Serviço.
-- Compatibilidade: MariaDB/MySQL compartilhado, InnoDB, utf8mb4.
-- Não altera dados antigos: a aplicação mantém compatibilidade com registros
-- legados onde descricao era utilizada como local do atendimento.

SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

ALTER TABLE ordem_servico_itens
    ADD COLUMN IF NOT EXISTS local_execucao VARCHAR(150) NULL AFTER descricao;
