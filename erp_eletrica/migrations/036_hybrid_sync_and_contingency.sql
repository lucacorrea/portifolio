-- Migration 036: Hybrid Sync and Contingency
-- Adiciona suporte a sincronismo híbrido e contingência fiscal

-- 1. Campos de Sincronismo para tabelas da loja local
ALTER TABLE vendas ADD COLUMN sync_status ENUM('pending', 'synced', 'error') DEFAULT 'pending' AFTER status;
ALTER TABLE vendas ADD COLUMN sync_uuid VARCHAR(36) NULL AFTER sync_status;

ALTER TABLE vendas_itens ADD COLUMN sync_status ENUM('pending', 'synced', 'error') DEFAULT 'pending';

ALTER TABLE pre_vendas ADD COLUMN sync_status ENUM('pending', 'synced', 'error') DEFAULT 'pending';

ALTER TABLE caixas ADD COLUMN sync_status ENUM('pending', 'synced', 'error') DEFAULT 'pending';

ALTER TABLE caixa_movimentacoes ADD COLUMN sync_status ENUM('pending', 'synced', 'error') DEFAULT 'pending';

ALTER TABLE contas_receber ADD COLUMN sync_status ENUM('pending', 'synced', 'error') DEFAULT 'pending';

ALTER TABLE movimentacoes_estoque ADD COLUMN sync_status ENUM('pending', 'synced', 'error') DEFAULT 'pending';

-- 2. Campos de Contingência na tabela de emissões NFC-e
ALTER TABLE nfce_emitidas ADD COLUMN tpEmis INT DEFAULT 1 COMMENT '1-Normal, 9-Offline' AFTER ambiente;
ALTER TABLE nfce_emitidas ADD COLUMN dhCont DATETIME NULL COMMENT 'Data/Hora entrada em contingencia' AFTER tpEmis;
ALTER TABLE nfce_emitidas ADD COLUMN xJust VARCHAR(255) NULL COMMENT 'Justificativa da contingencia' AFTER dhCont;
ALTER TABLE nfce_emitidas ADD COLUMN sync_status ENUM('pending', 'synced', 'error') DEFAULT 'pending';

-- 3. Configurações de Identificação Única (UUID)
-- Nota: Usaremos UUIDs para garantir que registros criados offline não colidam na nuvem.
-- Se já existem dados, opcionalmente gerar UUIDs para eles (ignorado aqui para simplicidade de migração).

-- 4. Ajuste de Incremento para o modo LOCAL (Exemplo para Filial 1)
-- IMPORTANTE: Isso deve ser rodado apenas no banco da LOJA.
-- ALTER TABLE vendas AUTO_INCREMENT = 1000000;
-- ALTER TABLE clientes AUTO_INCREMENT = 1000000;
-- ALTER TABLE pre_vendas AUTO_INCREMENT = 1000000;
