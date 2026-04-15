-- ERP Elétrica — Sync Tracking (Camada 2)
-- Adiciona colunas de rastreamento de sincronização nas tabelas transacionais
-- Necessário para a replicação bidirecional entre Hostinger e XAMPP local

-- Tabela para rastrear o estado da sincronização
CREATE TABLE IF NOT EXISTS `sync_state` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `table_name` VARCHAR(100) NOT NULL,
    `last_synced_id` BIGINT DEFAULT 0 COMMENT 'Último ID sincronizado para tabelas com auto_increment',
    `last_synced_at` DATETIME NULL COMMENT 'Timestamp da última sincronização',
    `direction` VARCHAR(30) DEFAULT 'bidirectional',
    `records_synced` INT DEFAULT 0 COMMENT 'Total de registros sincronizados',
    `last_error` TEXT NULL,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_sync_table` (`table_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Rastreia o estado da sincronização por tabela';

-- Tabela para operações pendentes de sync (local → remote)
CREATE TABLE IF NOT EXISTS `pending_sync` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `table_name` VARCHAR(100) NOT NULL,
    `record_id` INT NOT NULL COMMENT 'ID do registro na tabela de origem',
    `operation` VARCHAR(10) NOT NULL COMMENT 'INSERT, UPDATE, DELETE',
    `record_data` JSON NULL COMMENT 'Snapshot dos dados no momento da operação',
    `status` VARCHAR(20) DEFAULT 'pending' COMMENT 'pending, synced, error',
    `error_message` TEXT NULL,
    `retry_count` INT DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `synced_at` DATETIME NULL,
    INDEX `idx_pending_table` (`table_name`),
    INDEX `idx_pending_status` (`status`),
    INDEX `idx_pending_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Fila de operações locais pendentes para sincronizar com o remoto';

-- Adicionar coluna sync_origin nas tabelas transacionais (para saber onde o registro foi criado)
ALTER TABLE `vendas` ADD COLUMN IF NOT EXISTS `sync_origin` VARCHAR(20) DEFAULT 'remote' COMMENT 'remote=Hostinger, local=XAMPP';
ALTER TABLE `vendas` ADD COLUMN IF NOT EXISTS `sync_id` VARCHAR(50) NULL COMMENT 'ID único de sync para evitar duplicatas';

ALTER TABLE `pre_vendas` ADD COLUMN IF NOT EXISTS `sync_origin` VARCHAR(20) DEFAULT 'remote';
ALTER TABLE `pre_vendas` ADD COLUMN IF NOT EXISTS `sync_id` VARCHAR(50) NULL;

ALTER TABLE `caixas` ADD COLUMN IF NOT EXISTS `sync_origin` VARCHAR(20) DEFAULT 'remote';
ALTER TABLE `caixas` ADD COLUMN IF NOT EXISTS `sync_id` VARCHAR(50) NULL;

ALTER TABLE `contas_receber` ADD COLUMN IF NOT EXISTS `sync_origin` VARCHAR(20) DEFAULT 'remote';
ALTER TABLE `contas_receber` ADD COLUMN IF NOT EXISTS `sync_id` VARCHAR(50) NULL;
