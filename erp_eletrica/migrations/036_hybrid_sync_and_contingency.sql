-- Migration 036: Hybrid Sync and Contingency (Sintaxe Padrão)

-- 1. Campos de Sincronismo para tabelas da loja local
ALTER TABLE vendas ADD COLUMN sync_status ENUM('pending', 'synced', 'error') DEFAULT 'pending';
ALTER TABLE vendas ADD COLUMN sync_uuid VARCHAR(36) NULL;

ALTER TABLE vendas_itens ADD COLUMN sync_status ENUM('pending', 'synced', 'error') DEFAULT 'pending';

ALTER TABLE pre_vendas ADD COLUMN sync_status ENUM('pending', 'synced', 'error') DEFAULT 'pending';

ALTER TABLE caixas ADD COLUMN sync_status ENUM('pending', 'synced', 'error') DEFAULT 'pending';

ALTER TABLE caixa_movimentacoes ADD COLUMN sync_status ENUM('pending', 'synced', 'error') DEFAULT 'pending';

ALTER TABLE contas_receber ADD COLUMN sync_status ENUM('pending', 'synced', 'error') DEFAULT 'pending';

ALTER TABLE movimentacoes_estoque ADD COLUMN sync_status ENUM('pending', 'synced', 'error') DEFAULT 'pending';

-- 2. Campos de Contingência na tabela de emissões NFC-e
ALTER TABLE nfce_emitidas ADD COLUMN tpEmis INT DEFAULT 1 COMMENT '1-Normal, 9-Offline';
ALTER TABLE nfce_emitidas ADD COLUMN dhCont DATETIME NULL COMMENT 'Data/Hora entrada em contingencia';
ALTER TABLE nfce_emitidas ADD COLUMN xJust VARCHAR(255) NULL COMMENT 'Justificativa da contingencia';
ALTER TABLE nfce_emitidas ADD COLUMN sync_status ENUM('pending', 'synced', 'error') DEFAULT 'pending';
