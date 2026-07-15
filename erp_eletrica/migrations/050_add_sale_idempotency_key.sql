-- Migration 050: prevent duplicated checkout submissions

SET @column_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'vendas'
      AND COLUMN_NAME = 'idempotency_key'
);

SET @after_column := IF(
    (
        SELECT COUNT(*)
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'vendas'
          AND COLUMN_NAME = 'multi_detalhes'
    ) > 0,
    'multi_detalhes',
    'forma_pagamento'
);

SET @sql := IF(
    @column_exists = 0,
    CONCAT('ALTER TABLE `vendas` ADD COLUMN `idempotency_key` VARCHAR(80) NULL DEFAULT NULL AFTER `', @after_column, '`'),
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @index_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'vendas'
      AND INDEX_NAME = 'idx_vendas_idempotency_key'
);

SET @sql := IF(
    @index_exists = 0,
    'CREATE UNIQUE INDEX `idx_vendas_idempotency_key` ON `vendas` (`idempotency_key`)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
