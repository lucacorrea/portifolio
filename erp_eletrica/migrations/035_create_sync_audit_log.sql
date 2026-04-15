-- ERP Elétrica — Sync Audit Log
-- Registra todas as operações sincronizadas do modo offline
-- Permite rastreabilidade completa de vendas/pré-vendas feitas sem internet

CREATE TABLE IF NOT EXISTS `sync_audit_log` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `operation_type` VARCHAR(30) NOT NULL COMMENT 'sale, presale',
    `temp_id` VARCHAR(50) NULL COMMENT 'ID temporário gerado offline (OFF-xxx)',
    `real_id` INT NULL COMMENT 'ID real no banco após sincronização',
    `status` VARCHAR(20) NOT NULL DEFAULT 'pending' COMMENT 'pending, success, error, conflict',
    `error_message` TEXT NULL COMMENT 'Mensagem de erro (se houver)',
    `payload` JSON NULL COMMENT 'Dados completos da operação offline',
    `session_data` JSON NULL COMMENT 'Dados da sessão do operador no momento offline',
    `stock_warnings` JSON NULL COMMENT 'Alertas de estoque (se item tinha qtd insuficiente)',
    `synced_at` DATETIME NULL COMMENT 'Timestamp de quando foi sincronizado',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_sync_type` (`operation_type`),
    INDEX `idx_sync_status` (`status`),
    INDEX `idx_sync_temp_id` (`temp_id`),
    INDEX `idx_sync_synced_at` (`synced_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci 
COMMENT='Log de auditoria de operações sincronizadas do modo offline';

-- Adicionar valor 'contingencia' ao tipo_nota das vendas (para NFC-e offline)
-- O MySQL não suporta ALTER ENUM facilmente, então usamos ALTER COLUMN
ALTER TABLE `vendas` MODIFY COLUMN `tipo_nota` VARCHAR(30) DEFAULT 'nao_fiscal';
