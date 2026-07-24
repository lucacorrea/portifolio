-- Migration 018 - Conclusao auditavel de lembretes da Agenda.
-- Mantem cancelamento e conclusao como estados distintos.

ALTER TABLE agenda_lembretes
    MODIFY COLUMN status ENUM('ativo', 'concluido', 'cancelado') NOT NULL DEFAULT 'ativo';

SET @column_exists := (
    SELECT COUNT(*)
      FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'agenda_lembretes'
       AND COLUMN_NAME = 'concluido_em'
);
SET @sql := IF(
    @column_exists = 0,
    'ALTER TABLE agenda_lembretes ADD COLUMN concluido_em DATETIME NULL AFTER status',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @column_exists := (
    SELECT COUNT(*)
      FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'agenda_lembretes'
       AND COLUMN_NAME = 'concluido_por'
);
SET @sql := IF(
    @column_exists = 0,
    'ALTER TABLE agenda_lembretes ADD COLUMN concluido_por INT UNSIGNED NULL AFTER concluido_em, ADD KEY idx_agenda_lembretes_concluido_por (concluido_por)',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_exists := (
    SELECT COUNT(*)
      FROM INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS
     WHERE CONSTRAINT_SCHEMA = DATABASE()
       AND CONSTRAINT_NAME = 'fk_agenda_lembretes_concluido_usuario'
);
SET @sql := IF(
    @fk_exists = 0,
    'ALTER TABLE agenda_lembretes ADD CONSTRAINT fk_agenda_lembretes_concluido_usuario FOREIGN KEY (concluido_por) REFERENCES usuarios(id) ON UPDATE CASCADE ON DELETE SET NULL',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
