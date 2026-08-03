-- Permite registrar a origem das finalizações de OS importadas do SO.
-- A importação nasce em "aguardando_agendamento" e é finalizada sem etapa operacional.

SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

ALTER TABLE ordem_servico_finalizacoes
    MODIFY COLUMN status_origem ENUM(
        'aguardando_agendamento',
        'agendada',
        'em_execucao',
        'aguardando_peca'
    ) NOT NULL DEFAULT 'em_execucao';
