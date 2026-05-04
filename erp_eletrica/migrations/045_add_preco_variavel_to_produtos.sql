-- Add preco_variavel column to produtos table
ALTER TABLE produtos ADD COLUMN preco_variavel TINYINT(1) DEFAULT 0;
