SET @budget_responsible_fk := (
    SELECT CONSTRAINT_NAME
      FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'orcamentos'
       AND COLUMN_NAME = 'responsavel_id'
       AND REFERENCED_TABLE_NAME IS NOT NULL
     LIMIT 1
);

SET @drop_budget_responsible_fk := IF(
    @budget_responsible_fk IS NULL,
    'SELECT 1',
    CONCAT('ALTER TABLE orcamentos DROP FOREIGN KEY `', REPLACE(@budget_responsible_fk, '`', '``'), '`')
);
PREPARE stmt FROM @drop_budget_responsible_fk;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @budget_responsible_index := (
    SELECT INDEX_NAME
      FROM INFORMATION_SCHEMA.STATISTICS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'orcamentos'
       AND COLUMN_NAME = 'responsavel_id'
       AND INDEX_NAME <> 'PRIMARY'
     ORDER BY INDEX_NAME = 'idx_orcamentos_responsavel' DESC, INDEX_NAME
     LIMIT 1
);

SET @drop_budget_responsible_index := IF(
    @budget_responsible_index IS NULL,
    'SELECT 1',
    CONCAT('ALTER TABLE orcamentos DROP INDEX `', REPLACE(@budget_responsible_index, '`', '``'), '`')
);
PREPARE stmt FROM @drop_budget_responsible_index;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @budget_responsible_column_exists := (
    SELECT COUNT(*)
      FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'orcamentos'
       AND COLUMN_NAME = 'responsavel_id'
);

SET @drop_budget_responsible_column := IF(
    @budget_responsible_column_exists = 0,
    'SELECT 1',
    'ALTER TABLE orcamentos DROP COLUMN responsavel_id'
);
PREPARE stmt FROM @drop_budget_responsible_column;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS ordens_servico (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    numero VARCHAR(20) NULL,
    cliente_id INT UNSIGNED NOT NULL,
    orcamento_id INT UNSIGNED NULL,
    funcionario_principal_id INT UNSIGNED NULL,
    funcionario_apoio_id INT UNSIGNED NULL,
    agendado_inicio DATETIME NULL,
    agendado_fim DATETIME NULL,
    status ENUM('rascunho', 'aberta', 'aguardando_agendamento', 'agendada', 'em_deslocamento', 'em_execucao', 'aguardando_peca', 'finalizada', 'cancelada') NOT NULL DEFAULT 'aberta',
    prioridade ENUM('baixa', 'media', 'alta', 'urgente') NOT NULL DEFAULT 'media',
    observacoes TEXT NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_ordens_servico_numero (numero),
    KEY idx_os_cliente (cliente_id),
    KEY idx_os_orcamento (orcamento_id),
    KEY idx_os_status (status),
    KEY idx_os_prioridade (prioridade),
    KEY idx_os_funcionario_principal (funcionario_principal_id),
    KEY idx_os_funcionario_apoio (funcionario_apoio_id),
    KEY idx_os_agendado_inicio (agendado_inicio),
    KEY idx_os_agendado_fim (agendado_fim),
    CONSTRAINT fk_os_cliente FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_os_orcamento FOREIGN KEY (orcamento_id) REFERENCES orcamentos(id) ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_os_funcionario_principal FOREIGN KEY (funcionario_principal_id) REFERENCES funcionarios(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_os_funcionario_apoio FOREIGN KEY (funcionario_apoio_id) REFERENCES funcionarios(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
