-- Migration 033: Adicionar resumo de fechamento detalhado ao caixa
ALTER TABLE caixas ADD COLUMN resumo_fechamento TEXT NULL;
