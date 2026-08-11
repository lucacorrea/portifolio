-- Migration 052: prevent duplicated stock transfer submissions

SET @column_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'erp_transferencias'
      AND COLUMN_NAME = 'idempotency_key'
);

SET @sql := IF(
    @column_exists = 0,
    'ALTER TABLE `erp_transferencias` ADD COLUMN `idempotency_key` VARCHAR(80) NULL DEFAULT NULL AFTER `problema_resolvido`',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @index_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'erp_transferencias'
      AND INDEX_NAME = 'idx_erp_transferencias_idempotency_key'
);

SET @sql := IF(
    @index_exists = 0,
    'CREATE UNIQUE INDEX `idx_erp_transferencias_idempotency_key` ON `erp_transferencias` (`idempotency_key`)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
