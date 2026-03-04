-- Migration 006: Global B2B Multi-Branch Isolation
-- Adds filial_id to all remaining core tables to ensure strict data separation

-- 1. Suppliers (Fornecedores)
ALTER TABLE fornecedores ADD COLUMN IF NOT EXISTS filial_id INT AFTER id;
ALTER TABLE fornecedores ADD CONSTRAINT fk_fornecedor_filial FOREIGN KEY (filial_id) REFERENCES filiais(id);

-- 2. Service Orders (OS)
ALTER TABLE os ADD COLUMN IF NOT EXISTS filial_id INT AFTER id;
ALTER TABLE os ADD CONSTRAINT fk_os_filial FOREIGN KEY (filial_id) REFERENCES filiais(id);

-- 3. Financial - Accounts Payable
ALTER TABLE contas_pagar ADD COLUMN IF NOT EXISTS filial_id INT AFTER id;
ALTER TABLE contas_pagar ADD CONSTRAINT fk_cp_filial FOREIGN KEY (filial_id) REFERENCES filiais(id);

-- 4. Financial - Accounts Receivable
ALTER TABLE contas_receber ADD COLUMN IF NOT EXISTS filial_id INT AFTER id;
ALTER TABLE contas_receber ADD CONSTRAINT fk_cr_filial FOREIGN KEY (filial_id) REFERENCES filiais(id);

-- 5. Stock Movements
ALTER TABLE movimentacoes_estoque ADD COLUMN IF NOT EXISTS filial_id INT AFTER id;
ALTER TABLE movimentacoes_estoque ADD CONSTRAINT fk_mov_filial FOREIGN KEY (filial_id) REFERENCES filiais(id);

-- 6. Purchases (Compras)
ALTER TABLE compras ADD COLUMN IF NOT EXISTS filial_id INT AFTER id;
ALTER TABLE compras ADD CONSTRAINT fk_compra_filial FOREIGN KEY (filial_id) REFERENCES filiais(id);

-- Update existing records to Matriz (assuming principal = 1)
SET @matriz_id = (SELECT id FROM filiais WHERE principal = 1 LIMIT 1);
UPDATE fornecedores SET filial_id = @matriz_id WHERE filial_id IS NULL;
UPDATE os SET filial_id = @matriz_id WHERE filial_id IS NULL;
UPDATE contas_pagar SET filial_id = @matriz_id WHERE filial_id IS NULL;
UPDATE contas_receber SET filial_id = @matriz_id WHERE filial_id IS NULL;
UPDATE movimentacoes_estoque SET filial_id = @matriz_id WHERE filial_id IS NULL;
UPDATE compras SET filial_id = @matriz_id WHERE filial_id IS NULL;
