SET NAMES utf8mb4;

SET @schema_name := DATABASE();

SET @sql := (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE empresas ADD COLUMN empresa_pai_id BIGINT UNSIGNED NULL AFTER id',
        'SELECT 1'
    )
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'empresas'
      AND COLUMN_NAME = 'empresa_pai_id'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := (
    SELECT IF(
        COUNT(*) = 0,
        "ALTER TABLE empresas ADD COLUMN tipo ENUM('matriz','loja') NOT NULL DEFAULT 'matriz' AFTER empresa_pai_id",
        'SELECT 1'
    )
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'empresas'
      AND COLUMN_NAME = 'tipo'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE empresas ADD COLUMN codigo VARCHAR(50) NULL AFTER tipo',
        'SELECT 1'
    )
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'empresas'
      AND COLUMN_NAME = 'codigo'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

UPDATE empresas
SET tipo = 'matriz'
WHERE empresa_pai_id IS NULL
  AND (tipo IS NULL OR tipo <> 'matriz');

SET @sql := (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE empresas ADD KEY idx_empresas_pai (empresa_pai_id)',
        'SELECT 1'
    )
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'empresas'
      AND INDEX_NAME = 'idx_empresas_pai'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE empresas ADD KEY idx_empresas_pai_ativo (empresa_pai_id, ativo)',
        'SELECT 1'
    )
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'empresas'
      AND INDEX_NAME = 'idx_empresas_pai_ativo'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE empresas ADD UNIQUE KEY uk_empresas_pai_codigo (empresa_pai_id, codigo)',
        'SELECT 1'
    )
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'empresas'
      AND INDEX_NAME = 'uk_empresas_pai_codigo'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE empresas ADD CONSTRAINT fk_empresas_pai FOREIGN KEY (empresa_pai_id) REFERENCES empresas(id) ON DELETE RESTRICT',
        'SELECT 1'
    )
    FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'empresas'
      AND CONSTRAINT_NAME = 'fk_empresas_pai'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
