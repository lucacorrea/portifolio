ALTER TABLE nfe_importadas 
ADD COLUMN manifestacao_tipo VARCHAR(10) NULL AFTER status,
ADD COLUMN manifestacao_data DATETIME NULL AFTER manifestacao_tipo;
