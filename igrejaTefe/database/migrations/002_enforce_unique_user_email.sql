-- Enforce globally unique user emails for login by email/password.
-- MySQL 8+
--
-- Run this on databases created before `uk_usuarios_email` existed.
-- If duplicate active/inactive emails exist, fix them manually before applying.

SET @duplicate_user_emails := (
    SELECT COUNT(*)
    FROM (
        SELECT email
        FROM usuarios
        GROUP BY email
        HAVING COUNT(*) > 1
    ) AS duplicates
);

SET @has_unique_email_index := (
    SELECT COUNT(*)
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'usuarios'
      AND index_name = 'uk_usuarios_email'
);

SET @sql := CASE
    WHEN @duplicate_user_emails > 0 THEN
        'SELECT ''ERRO: existem emails duplicados em usuarios. Corrija antes de criar uk_usuarios_email.'' AS resultado'
    WHEN @has_unique_email_index = 0 THEN
        'ALTER TABLE usuarios ADD UNIQUE KEY uk_usuarios_email (email)'
    ELSE
        'SELECT ''OK: uk_usuarios_email ja existe.'' AS resultado'
END;

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

