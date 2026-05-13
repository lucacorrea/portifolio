-- Execute após revisar se não existem empresas duplicadas com o mesmo CNPJ.
-- O checkout público depende desta unicidade para evitar duplicidade em concorrência.

UPDATE empresas
SET cnpj = NULL
WHERE cnpj = '';

UPDATE empresas
SET cnpj = REPLACE(REPLACE(REPLACE(REPLACE(cnpj, '.', ''), '/', ''), '-', ''), ' ', '')
WHERE cnpj IS NOT NULL;

UPDATE empresas
SET cnpj = NULL
WHERE cnpj = '';

SET @idx_empresas_cnpj_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'empresas'
      AND INDEX_NAME = 'uq_empresas_cnpj'
);

SET @idx_empresas_cnpj_sql := IF(
    @idx_empresas_cnpj_exists = 0,
    'ALTER TABLE empresas ADD UNIQUE KEY uq_empresas_cnpj (cnpj)',
    'SELECT 1'
);

PREPARE idx_empresas_cnpj_stmt FROM @idx_empresas_cnpj_sql;
EXECUTE idx_empresas_cnpj_stmt;
DEALLOCATE PREPARE idx_empresas_cnpj_stmt;
