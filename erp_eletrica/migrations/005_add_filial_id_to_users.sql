-- Migration to add filial_id to usuarios table
-- This ensures each user is linked to a specific company/unit

SET @dbname = DATABASE();
SET @tablename = 'usuarios';
SET @columnname = 'filial_id';
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
   WHERE TABLE_SCHEMA = @dbname
     AND TABLE_NAME = @tablename
     AND COLUMN_NAME = @columnname) > 0,
  'SELECT 1',
  'ALTER TABLE usuarios ADD COLUMN filial_id INT DEFAULT NULL, ADD CONSTRAINT fk_user_filial FOREIGN KEY (filial_id) REFERENCES filiais(id)'
));
PREPARE stmt FROM @preparedStatement;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Update existing users to Matriz if they have no filial assigned and Matriz exists
UPDATE usuarios SET filial_id = (SELECT id FROM filiais WHERE principal = 1 LIMIT 1) WHERE filial_id IS NULL AND (SELECT id FROM filiais WHERE principal = 1 LIMIT 1) IS NOT NULL;
