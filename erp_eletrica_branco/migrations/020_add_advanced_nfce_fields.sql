-- Adiciona campos específicos para configuração avancada de NF-e e NFC-e na tabela filiais

ALTER TABLE filiais ADD COLUMN IF NOT EXISTS tipo_emissao VARCHAR(50) DEFAULT 'Normal' AFTER crt;
ALTER TABLE filiais ADD COLUMN IF NOT EXISTS finalidade_emissao VARCHAR(50) DEFAULT 'Normal' AFTER tipo_emissao;
ALTER TABLE filiais ADD COLUMN IF NOT EXISTS indicador_presenca VARCHAR(50) DEFAULT 'Operacao presencial' AFTER finalidade_emissao;
ALTER TABLE filiais ADD COLUMN IF NOT EXISTS tipo_impressao_danfe VARCHAR(50) DEFAULT 'NFC-e' AFTER indicador_presenca;
ALTER TABLE filiais ADD COLUMN IF NOT EXISTS serie_nfce INT DEFAULT 1 AFTER tipo_impressao_danfe;
ALTER TABLE filiais ADD COLUMN IF NOT EXISTS ultimo_numero_nfce INT DEFAULT 0 AFTER serie_nfce;
