SET @column_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ordens_servico' AND COLUMN_NAME = 'equipamento_tipo');
SET @sql := IF(@column_exists = 0, 'ALTER TABLE ordens_servico ADD COLUMN equipamento_tipo VARCHAR(100) NULL AFTER prioridade', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @column_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ordens_servico' AND COLUMN_NAME = 'equipamento_marca');
SET @sql := IF(@column_exists = 0, 'ALTER TABLE ordens_servico ADD COLUMN equipamento_marca VARCHAR(100) NULL AFTER equipamento_tipo', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @column_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ordens_servico' AND COLUMN_NAME = 'equipamento_modelo');
SET @sql := IF(@column_exists = 0, 'ALTER TABLE ordens_servico ADD COLUMN equipamento_modelo VARCHAR(100) NULL AFTER equipamento_marca', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @column_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ordens_servico' AND COLUMN_NAME = 'equipamento_capacidade');
SET @sql := IF(@column_exists = 0, 'ALTER TABLE ordens_servico ADD COLUMN equipamento_capacidade VARCHAR(100) NULL AFTER equipamento_modelo', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @column_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ordens_servico' AND COLUMN_NAME = 'equipamento_numero_serie');
SET @sql := IF(@column_exists = 0, 'ALTER TABLE ordens_servico ADD COLUMN equipamento_numero_serie VARCHAR(100) NULL AFTER equipamento_capacidade', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @column_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ordens_servico' AND COLUMN_NAME = 'equipamento_ambiente');
SET @sql := IF(@column_exists = 0, 'ALTER TABLE ordens_servico ADD COLUMN equipamento_ambiente VARCHAR(100) NULL AFTER equipamento_numero_serie', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @column_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ordens_servico' AND COLUMN_NAME = 'equipamento_local');
SET @sql := IF(@column_exists = 0, 'ALTER TABLE ordens_servico ADD COLUMN equipamento_local VARCHAR(150) NULL AFTER equipamento_ambiente', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @column_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ordens_servico' AND COLUMN_NAME = 'problema_relatado');
SET @sql := IF(@column_exists = 0, 'ALTER TABLE ordens_servico ADD COLUMN problema_relatado TEXT NULL AFTER equipamento_local', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @column_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ordens_servico' AND COLUMN_NAME = 'problema_identificado');
SET @sql := IF(@column_exists = 0, 'ALTER TABLE ordens_servico ADD COLUMN problema_identificado TEXT NULL AFTER problema_relatado', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @column_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ordens_servico' AND COLUMN_NAME = 'diagnostico');
SET @sql := IF(@column_exists = 0, 'ALTER TABLE ordens_servico ADD COLUMN diagnostico TEXT NULL AFTER problema_identificado', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @column_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ordens_servico' AND COLUMN_NAME = 'solucao');
SET @sql := IF(@column_exists = 0, 'ALTER TABLE ordens_servico ADD COLUMN solucao TEXT NULL AFTER diagnostico', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @column_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ordens_servico' AND COLUMN_NAME = 'recomendacao');
SET @sql := IF(@column_exists = 0, 'ALTER TABLE ordens_servico ADD COLUMN recomendacao TEXT NULL AFTER solucao', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @column_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ordens_servico' AND COLUMN_NAME = 'observacoes_internas');
SET @sql := IF(@column_exists = 0, 'ALTER TABLE ordens_servico ADD COLUMN observacoes_internas TEXT NULL AFTER recomendacao', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @column_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ordens_servico' AND COLUMN_NAME = 'subtotal_servicos');
SET @sql := IF(@column_exists = 0, 'ALTER TABLE ordens_servico ADD COLUMN subtotal_servicos DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER observacoes', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @column_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ordens_servico' AND COLUMN_NAME = 'subtotal_produtos');
SET @sql := IF(@column_exists = 0, 'ALTER TABLE ordens_servico ADD COLUMN subtotal_produtos DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER subtotal_servicos', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @column_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ordens_servico' AND COLUMN_NAME = 'subtotal_outros');
SET @sql := IF(@column_exists = 0, 'ALTER TABLE ordens_servico ADD COLUMN subtotal_outros DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER subtotal_produtos', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @column_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ordens_servico' AND COLUMN_NAME = 'desconto');
SET @sql := IF(@column_exists = 0, 'ALTER TABLE ordens_servico ADD COLUMN desconto DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER subtotal_outros', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @column_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ordens_servico' AND COLUMN_NAME = 'acrescimo');
SET @sql := IF(@column_exists = 0, 'ALTER TABLE ordens_servico ADD COLUMN acrescimo DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER desconto', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @column_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ordens_servico' AND COLUMN_NAME = 'total');
SET @sql := IF(@column_exists = 0, 'ALTER TABLE ordens_servico ADD COLUMN total DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER acrescimo', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @column_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ordens_servico' AND COLUMN_NAME = 'finalizada_em');
SET @sql := IF(@column_exists = 0, 'ALTER TABLE ordens_servico ADD COLUMN finalizada_em DATETIME NULL AFTER total', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @column_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ordens_servico' AND COLUMN_NAME = 'cancelada_em');
SET @sql := IF(@column_exists = 0, 'ALTER TABLE ordens_servico ADD COLUMN cancelada_em DATETIME NULL AFTER finalizada_em', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS ordem_servico_itens (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ordem_servico_id INT UNSIGNED NOT NULL,
    tipo ENUM('servico', 'produto', 'outro') NOT NULL,
    referencia_id INT UNSIGNED NULL,
    descricao VARCHAR(255) NOT NULL,
    unidade VARCHAR(20) NOT NULL DEFAULT 'un',
    quantidade DECIMAL(12,3) NOT NULL DEFAULT 1.000,
    valor_unitario DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    desconto DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    subtotal DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    ordem INT UNSIGNED NOT NULL DEFAULT 0,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_os_itens_ordem_servico (ordem_servico_id),
    KEY idx_os_itens_tipo (tipo),
    KEY idx_os_itens_referencia (referencia_id),
    KEY idx_os_itens_ordem (ordem),
    CONSTRAINT fk_os_itens_ordem_servico FOREIGN KEY (ordem_servico_id) REFERENCES ordens_servico(id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS agenda_lembretes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    titulo VARCHAR(150) NOT NULL,
    descricao TEXT NULL,
    inicio DATETIME NOT NULL,
    fim DATETIME NULL,
    status ENUM('ativo', 'cancelado') NOT NULL DEFAULT 'ativo',
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_agenda_lembretes_inicio (inicio),
    KEY idx_agenda_lembretes_fim (fim),
    KEY idx_agenda_lembretes_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
