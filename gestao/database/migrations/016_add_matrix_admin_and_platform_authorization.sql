SET NAMES utf8mb4;

SET @schema_name := DATABASE();

SET @sql := (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE empresas ADD COLUMN admin_principal_usuario_id BIGINT UNSIGNED NULL AFTER logo',
        'SELECT 1'
    )
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'empresas'
      AND COLUMN_NAME = 'admin_principal_usuario_id'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE empresas ADD KEY idx_empresas_admin_principal (admin_principal_usuario_id)',
        'SELECT 1'
    )
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'empresas'
      AND INDEX_NAME = 'idx_empresas_admin_principal'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE empresas ADD CONSTRAINT fk_empresas_admin_principal FOREIGN KEY (admin_principal_usuario_id) REFERENCES usuarios(id) ON DELETE RESTRICT',
        'SELECT 1'
    )
    FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'empresas'
      AND CONSTRAINT_NAME = 'fk_empresas_admin_principal'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

UPDATE empresas
SET admin_principal_usuario_id = NULL
WHERE COALESCE(tipo, 'matriz') <> 'matriz'
   OR empresa_pai_id IS NOT NULL;

UPDATE empresas e
INNER JOIN (
    SELECT ue.empresa_id, MIN(ue.usuario_id) AS usuario_id, COUNT(*) AS total_admins
    FROM usuario_empresas ue
    INNER JOIN usuarios u ON u.id = ue.usuario_id
    WHERE ue.nivel = 'admin'
      AND ue.ativo = 1
      AND u.ativo = 1
    GROUP BY ue.empresa_id
    HAVING COUNT(*) = 1
) only_admin ON only_admin.empresa_id = e.id
SET e.admin_principal_usuario_id = only_admin.usuario_id
WHERE e.empresa_pai_id IS NULL
  AND COALESCE(e.tipo, 'matriz') = 'matriz'
  AND e.admin_principal_usuario_id IS NULL;

UPDATE usuario_empresas ue
INNER JOIN empresas e
        ON e.id = ue.empresa_id
       AND e.admin_principal_usuario_id = ue.usuario_id
SET ue.principal = 1,
    ue.nivel = 'admin',
    ue.ativo = 1
WHERE e.empresa_pai_id IS NULL
  AND COALESCE(e.tipo, 'matriz') = 'matriz';

SET @sql := (
    SELECT IF(
        DATA_TYPE = 'enum',
        'ALTER TABLE empresa_contexto_auditoria MODIFY acao VARCHAR(60) NOT NULL',
        'SELECT 1'
    )
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'empresa_contexto_auditoria'
      AND COLUMN_NAME = 'acao'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE empresa_contexto_auditoria ADD COLUMN detalhes JSON NULL AFTER user_agent',
        'SELECT 1'
    )
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'empresa_contexto_auditoria'
      AND COLUMN_NAME = 'detalhes'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
