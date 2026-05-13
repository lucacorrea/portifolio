CREATE TABLE IF NOT EXISTS whatsapp_conexoes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT UNSIGNED NOT NULL,
    instancia_nome VARCHAR(100) NOT NULL,
    telefone_conectado VARCHAR(30) DEFAULT NULL,
    status ENUM('desconectado','conectando','conectado','erro') NOT NULL DEFAULT 'desconectado',
    qr_code TEXT DEFAULT NULL,
    qr_code_imagem MEDIUMTEXT DEFAULT NULL,
    pairing_code VARCHAR(40) DEFAULT NULL,
    ultimo_erro TEXT DEFAULT NULL,
    ultima_sincronizacao DATETIME DEFAULT NULL,
    conectado_em DATETIME DEFAULT NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_whatsapp_conexoes_empresa (empresa_id),
    UNIQUE KEY uq_whatsapp_conexoes_instancia (instancia_nome),
    INDEX idx_whatsapp_conexoes_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @idx_whatsapp_cobranca_tipo_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'whatsapp_envios'
      AND INDEX_NAME = 'idx_whatsapp_cobranca_tipo'
);

SET @idx_whatsapp_cobranca_tipo_sql := IF(
    @idx_whatsapp_cobranca_tipo_exists = 0,
    'ALTER TABLE whatsapp_envios ADD INDEX idx_whatsapp_cobranca_tipo (empresa_id, cobranca_id, tipo)',
    'SELECT 1'
);

PREPARE idx_whatsapp_cobranca_tipo_stmt FROM @idx_whatsapp_cobranca_tipo_sql;
EXECUTE idx_whatsapp_cobranca_tipo_stmt;
DEALLOCATE PREPARE idx_whatsapp_cobranca_tipo_stmt;
