-- Migration 003: Fiscal Integration Schema Extension
-- Adds fields for SEFAZ compliance (NF-e/NFC-e)

-- Enhance Filiais with fiscal data
ALTER TABLE filiais ADD COLUMN IF NOT EXISTS cnpj VARCHAR(18) AFTER nome;
ALTER TABLE filiais ADD COLUMN IF NOT EXISTS inscricao_estadual VARCHAR(20) AFTER cnpj;
ALTER TABLE filiais ADD COLUMN IF NOT EXISTS logradouro VARCHAR(255) AFTER inscricao_estadual;
ALTER TABLE filiais ADD COLUMN IF NOT EXISTS numero VARCHAR(20) AFTER logradouro;
ALTER TABLE filiais ADD COLUMN IF NOT EXISTS bairro VARCHAR(100) AFTER numero;
ALTER TABLE filiais ADD COLUMN IF NOT EXISTS municipio VARCHAR(100) AFTER bairro;
ALTER TABLE filiais ADD COLUMN IF NOT EXISTS uf CHAR(2) AFTER municipio;
ALTER TABLE filiais ADD COLUMN IF NOT EXISTS cep VARCHAR(10) AFTER uf;
ALTER TABLE filiais ADD COLUMN IF NOT EXISTS csc_id VARCHAR(10) AFTER cep;
ALTER TABLE filiais ADD COLUMN IF NOT EXISTS csc_token VARCHAR(100) AFTER csc_id;
ALTER TABLE filiais ADD COLUMN IF NOT EXISTS certificado_pfx VARCHAR(255) AFTER csc_token;
ALTER TABLE filiais ADD COLUMN IF NOT EXISTS certificado_senha VARCHAR(255) AFTER certificado_pfx;
ALTER TABLE filiais ADD COLUMN IF NOT EXISTS ambiente TINYINT DEFAULT 2 COMMENT '1: Produção, 2: Homologação';

-- Enhance Produtos with fiscal data
ALTER TABLE produtos ADD COLUMN IF NOT EXISTS cest VARCHAR(10) AFTER ncm;
ALTER TABLE produtos ADD COLUMN IF NOT EXISTS origem TINYINT DEFAULT 0 AFTER cest;
ALTER TABLE produtos ADD COLUMN IF NOT EXISTS csosn VARCHAR(4) DEFAULT '102' AFTER origem;
ALTER TABLE produtos ADD COLUMN IF NOT EXISTS cfop_interno VARCHAR(4) DEFAULT '5102' AFTER csosn;
ALTER TABLE produtos ADD COLUMN IF NOT EXISTS cfop_externo VARCHAR(4) DEFAULT '6102' AFTER cfop_interno;
ALTER TABLE produtos ADD COLUMN IF NOT EXISTS aliquota_icms DECIMAL(5,2) DEFAULT 0.00 AFTER cfop_externo;

-- Create table for Fiscal Invoices
CREATE TABLE IF NOT EXISTS notas_fiscais (
    id INT AUTO_INCREMENT PRIMARY KEY,
    venda_id INT NOT NULL,
    tipo ENUM('nfe', 'nfce') NOT NULL,
    chave_acesso VARCHAR(44) UNIQUE,
    numero_nota INT,
    serie_nota INT,
    status ENUM('pendente', 'autorizada', 'cancelada', 'rejeitada', 'contingencia') DEFAULT 'pendente',
    xml_path VARCHAR(255),
    danfe_path VARCHAR(255),
    protocolo VARCHAR(50),
    recibo VARCHAR(50),
    mensagem_sefaz TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (venda_id) REFERENCES vendas(id)
);
