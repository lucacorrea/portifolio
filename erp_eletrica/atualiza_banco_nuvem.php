<?php
require 'config.php';

try {
    $db = \App\Config\Database::getInstance()->getConnection();
    
    $queries = [
        "ALTER TABLE filiais ADD COLUMN IF NOT EXISTS razao_social VARCHAR(150) AFTER nome",
        "ALTER TABLE filiais ADD COLUMN IF NOT EXISTS inscricao_estadual VARCHAR(30) AFTER cnpj",
        "ALTER TABLE filiais ADD COLUMN IF NOT EXISTS crt TINYINT(1) DEFAULT 1",
        "ALTER TABLE filiais ADD COLUMN IF NOT EXISTS tipo_emissao VARCHAR(50) DEFAULT 'Normal'",
        "ALTER TABLE filiais ADD COLUMN IF NOT EXISTS finalidade_emissao VARCHAR(50) DEFAULT 'Normal'",
        "ALTER TABLE filiais ADD COLUMN IF NOT EXISTS indicador_presenca VARCHAR(50) DEFAULT 'Operacao presencial'",
        "ALTER TABLE filiais ADD COLUMN IF NOT EXISTS tipo_impressao_danfe VARCHAR(50) DEFAULT 'NFC-e'",
        "ALTER TABLE filiais ADD COLUMN IF NOT EXISTS serie_nfce INT DEFAULT 1",
        "ALTER TABLE filiais ADD COLUMN IF NOT EXISTS ultimo_numero_nfce INT DEFAULT 0",
        "ALTER TABLE filiais ADD COLUMN IF NOT EXISTS logradouro VARCHAR(150)",
        "ALTER TABLE filiais ADD COLUMN IF NOT EXISTS numero VARCHAR(20)",
        "ALTER TABLE filiais ADD COLUMN IF NOT EXISTS complemento VARCHAR(100)",
        "ALTER TABLE filiais ADD COLUMN IF NOT EXISTS bairro VARCHAR(100)",
        "ALTER TABLE filiais ADD COLUMN IF NOT EXISTS cep VARCHAR(20)",
        "ALTER TABLE filiais ADD COLUMN IF NOT EXISTS municipio VARCHAR(100)",
        "ALTER TABLE filiais ADD COLUMN IF NOT EXISTS codigo_municipio VARCHAR(10)",
        "ALTER TABLE filiais ADD COLUMN IF NOT EXISTS uf VARCHAR(2)",
        "ALTER TABLE filiais ADD COLUMN IF NOT EXISTS telefone VARCHAR(20)",
        "ALTER TABLE filiais ADD COLUMN IF NOT EXISTS email VARCHAR(100)",
        "ALTER TABLE filiais ADD COLUMN IF NOT EXISTS csc_id VARCHAR(50)",
        "ALTER TABLE filiais ADD COLUMN IF NOT EXISTS csc_token VARCHAR(100)",
        "ALTER TABLE filiais ADD COLUMN IF NOT EXISTS ambiente TINYINT(1) DEFAULT 2",
        "ALTER TABLE filiais ADD COLUMN IF NOT EXISTS certificado_pfx VARCHAR(255)",
        "ALTER TABLE filiais ADD COLUMN IF NOT EXISTS certificado_senha VARCHAR(255)"
    ];

    echo "<h3>Atualizando Banco de Dados da Hospedagem...</h3><ul>";
    foreach($queries as $q) {
        $db->exec($q);
        echo "<li>Executado com sucesso: " . htmlspecialchars(substr($q, 0, 80)) . "...</li>";
    }
    echo "</ul><h2 style='color: green;'>Banco de dados SEFAZ (Filiais) atualizado 100% com sucesso na nuvem!</h2>";
    echo "<p><a href='filiais.php'>Voltar para o sistema</a></p>";

    // Auto-delete for security
    @unlink(__FILE__);
} catch(Exception $e) {
    echo "<h2 style='color: red;'>Erro no banco de dados: " . $e->getMessage() . "</h2>";
}
