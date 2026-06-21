SET NAMES utf8mb4;

SELECT
    empresa_id,
    codigo_barras,
    COUNT(*) AS quantidade
FROM produtos
WHERE codigo_barras IS NOT NULL
  AND TRIM(codigo_barras) <> ''
GROUP BY empresa_id, codigo_barras
HAVING COUNT(*) > 1;

SET @db_name = DATABASE();

SET @sql = (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE produtos ADD COLUMN descricao TEXT NULL AFTER imagem',
        'SELECT "Coluna produtos.descricao ja existe" AS info'
    )
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db_name
      AND TABLE_NAME = 'produtos'
      AND COLUMN_NAME = 'descricao'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE produtos ADD COLUMN marca VARCHAR(150) NULL AFTER descricao',
        'SELECT "Coluna produtos.marca ja existe" AS info'
    )
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db_name
      AND TABLE_NAME = 'produtos'
      AND COLUMN_NAME = 'marca'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE produtos ADD COLUMN unidade VARCHAR(20) NULL AFTER marca',
        'SELECT "Coluna produtos.unidade ja existe" AS info'
    )
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db_name
      AND TABLE_NAME = 'produtos'
      AND COLUMN_NAME = 'unidade'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE produtos ADD COLUMN quantidade_embalagem VARCHAR(50) NULL AFTER unidade',
        'SELECT "Coluna produtos.quantidade_embalagem ja existe" AS info'
    )
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db_name
      AND TABLE_NAME = 'produtos'
      AND COLUMN_NAME = 'quantidade_embalagem'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE produtos ADD COLUMN ncm VARCHAR(10) NULL AFTER quantidade_embalagem',
        'SELECT "Coluna produtos.ncm ja existe" AS info'
    )
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db_name
      AND TABLE_NAME = 'produtos'
      AND COLUMN_NAME = 'ncm'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE produtos ADD COLUMN cest VARCHAR(10) NULL AFTER ncm',
        'SELECT "Coluna produtos.cest ja existe" AS info'
    )
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db_name
      AND TABLE_NAME = 'produtos'
      AND COLUMN_NAME = 'cest'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE produtos ADD COLUMN fabricante VARCHAR(150) NULL AFTER cest',
        'SELECT "Coluna produtos.fabricante ja existe" AS info'
    )
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db_name
      AND TABLE_NAME = 'produtos'
      AND COLUMN_NAME = 'fabricante'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE produtos ADD COLUMN origem_dados VARCHAR(50) NULL AFTER fabricante',
        'SELECT "Coluna produtos.origem_dados ja existe" AS info'
    )
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db_name
      AND TABLE_NAME = 'produtos'
      AND COLUMN_NAME = 'origem_dados'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE produtos ADD COLUMN url_imagem_origem VARCHAR(500) NULL AFTER origem_dados',
        'SELECT "Coluna produtos.url_imagem_origem ja existe" AS info'
    )
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db_name
      AND TABLE_NAME = 'produtos'
      AND COLUMN_NAME = 'url_imagem_origem'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @duplicate_barcodes = (
    SELECT COUNT(*)
    FROM (
        SELECT empresa_id, codigo_barras
        FROM produtos
        WHERE codigo_barras IS NOT NULL
          AND TRIM(codigo_barras) <> ''
        GROUP BY empresa_id, codigo_barras
        HAVING COUNT(*) > 1
    ) duplicados
);

SET @barcode_index_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = @db_name
      AND TABLE_NAME = 'produtos'
      AND INDEX_NAME = 'uk_produtos_empresa_codigo_barras'
);

SET @sql = IF(
    @duplicate_barcodes = 0 AND @barcode_index_exists = 0,
    'ALTER TABLE produtos ADD UNIQUE KEY uk_produtos_empresa_codigo_barras (empresa_id, codigo_barras)',
    'SELECT "Indice uk_produtos_empresa_codigo_barras nao criado: resolva duplicidades ou indice ja existente" AS info'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
