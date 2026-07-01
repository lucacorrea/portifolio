-- 1. Create solicitacoes table
CREATE TABLE IF NOT EXISTS solicitacoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    solicitante_id INT NOT NULL,
    ajuda_tipo_id INT NULL,
    resumo_caso TEXT,
    data_solicitacao DATETIME DEFAULT CURRENT_TIMESTAMP,
    status VARCHAR(20) DEFAULT 'Aberto',
    created_by VARCHAR(100),
    INDEX (solicitante_id),
    FOREIGN KEY (solicitante_id) REFERENCES solicitantes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Add solicitacao_id to ajudas_entregas
ALTER TABLE ajudas_entregas ADD COLUMN solicitacao_id INT NULL AFTER solicitante_id;

-- 3. Migrate data (Optional but recommended)
-- INSERT INTO solicitacoes (solicitante_id, resumo_caso, created_by, data_solicitacao)
-- SELECT id, resumo_caso, responsavel_cadastro, created_at FROM solicitantes;
