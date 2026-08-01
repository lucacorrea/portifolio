ALTER TABLE trocas ADD COLUMN IF NOT EXISTS grupo_troca VARCHAR(40) NULL AFTER id;
ALTER TABLE trocas ADD COLUMN IF NOT EXISTS tipo VARCHAR(20) DEFAULT 'troca' AFTER grupo_troca;
ALTER TABLE trocas MODIFY quantidade_original DECIMAL(10,3) NOT NULL;
ALTER TABLE trocas MODIFY quantidade_nova DECIMAL(10,3) NOT NULL DEFAULT 0;
CREATE INDEX IF NOT EXISTS idx_trocas_grupo_troca ON trocas (grupo_troca);
