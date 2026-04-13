-- Migration 036: Hybrid Sync and Contingency (Versão Corrigida)

-- 1. Campos de Sincronismo para tabelas da loja local
-- Usando lógica mais segura para evitar erro de coluna inexistente ou já existente
ALTER TABLE vendas ADD COLUMN IF NOT EXISTS sync_status ENUM('pending', 'synced', 'error') DEFAULT 'pending';
ALTER TABLE vendas ADD COLUMN IF NOT EXISTS sync_uuid VARCHAR(36) NULL;

ALTER TABLE vendas_itens ADD COLUMN IF NOT EXISTS sync_status ENUM('pending', 'synced', 'error') DEFAULT 'pending';

ALTER TABLE pre_vendas ADD COLUMN IF NOT EXISTS sync_status ENUM('pending', 'synced', 'error') DEFAULT 'pending';

ALTER TABLE caixas ADD COLUMN IF NOT EXISTS sync_status ENUM('pending', 'synced', 'error') DEFAULT 'pending';

ALTER TABLE caixa_movimentacoes ADD COLUMN IF NOT EXISTS sync_status ENUM('pending', 'synced', 'error') DEFAULT 'pending';

ALTER TABLE contas_receber ADD COLUMN IF NOT EXISTS sync_status ENUM('pending', 'synced', 'error') DEFAULT 'pending';

ALTER TABLE movimentacoes_estoque ADD COLUMN IF NOT EXISTS sync_status ENUM('pending', 'synced', 'error') DEFAULT 'pending';

-- 2. Campos de Contingência na tabela de emissões NFC-e
ALTER TABLE nfce_emitidas ADD COLUMN IF NOT EXISTS tpEmis INT DEFAULT 1 COMMENT '1-Normal, 9-Offline';
ALTER TABLE nfce_emitidas ADD COLUMN IF NOT EXISTS dhCont DATETIME NULL COMMENT 'Data/Hora entrada em contingencia';
ALTER TABLE nfce_emitidas ADD COLUMN IF NOT EXISTS xJust VARCHAR(255) NULL COMMENT 'Justificativa da contingencia';
ALTER TABLE nfce_emitidas ADD COLUMN IF NOT EXISTS sync_status ENUM('pending', 'synced', 'error') DEFAULT 'pending';
