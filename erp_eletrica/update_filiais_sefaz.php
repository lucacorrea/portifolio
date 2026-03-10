<?php
require 'config.php';
try {
    $db = \App\Config\Database::getInstance()->getConnection();
    
    $queries = [
        "ALTER TABLE filiais ADD COLUMN IF NOT EXISTS razao_social VARCHAR(150) AFTER nome",
        "ALTER TABLE filiais ADD COLUMN IF NOT EXISTS inscricao_estadual VARCHAR(30) AFTER cnpj",
        "ALTER TABLE filiais ADD COLUMN IF NOT EXISTS crt TINYINT(1) DEFAULT 1 AFTER inscricao_estadual COMMENT '1=Simples Nacional, 2=Simples Excesso, 3=Normal'",
        "ALTER TABLE filiais ADD COLUMN IF NOT EXISTS logradouro VARCHAR(150) AFTER razao_social",
        "ALTER TABLE filiais ADD COLUMN IF NOT EXISTS numero VARCHAR(20) AFTER logradouro",
        "ALTER TABLE filiais ADD COLUMN IF NOT EXISTS complemento VARCHAR(100) AFTER numero",
        "ALTER TABLE filiais ADD COLUMN IF NOT EXISTS bairro VARCHAR(100) AFTER complemento",
        "ALTER TABLE filiais ADD COLUMN IF NOT EXISTS cep VARCHAR(20) AFTER bairro",
        "ALTER TABLE filiais ADD COLUMN IF NOT EXISTS municipio VARCHAR(100) AFTER cep",
        "ALTER TABLE filiais ADD COLUMN IF NOT EXISTS codigo_municipio VARCHAR(10) AFTER municipio",
        "ALTER TABLE filiais ADD COLUMN IF NOT EXISTS uf VARCHAR(2) AFTER codigo_municipio",
        "ALTER TABLE filiais ADD COLUMN IF NOT EXISTS telefone VARCHAR(20) AFTER uf",
        "ALTER TABLE filiais ADD COLUMN IF NOT EXISTS email VARCHAR(100) AFTER telefone",
        "ALTER TABLE filiais ADD COLUMN IF NOT EXISTS csc_id VARCHAR(50) AFTER email",
        "ALTER TABLE filiais ADD COLUMN IF NOT EXISTS csc_token VARCHAR(100) AFTER csc_id",
        "ALTER TABLE filiais ADD COLUMN IF NOT EXISTS ambiente TINYINT(1) DEFAULT 2 AFTER csc_token COMMENT '1=Producao, 2=Homologacao'",
        "ALTER TABLE filiais ADD COLUMN IF NOT EXISTS certificado_pfx VARCHAR(255) AFTER ambiente",
        "ALTER TABLE filiais ADD COLUMN IF NOT EXISTS certificado_senha VARCHAR(255) AFTER certificado_pfx"
    ];

    foreach($queries as $q) {
        $db->exec($q);
    }
    echo "Tabela filiais atualizada com sucesso!\n";
} catch(Exception $e) {
    echo "Erro: " . $e->getMessage() . "\n";
}
