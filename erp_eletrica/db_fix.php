<?php
require_once 'config.php';

$db = \App\Config\Database::getInstance()->getConnection();

function addColumn($db, $table, $column, $type, $after = '') {
    try {
        // Check if column exists
        $stmt = $db->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
        if ($stmt->rowCount() == 0) {
            $sql = "ALTER TABLE `$table` ADD COLUMN `$column` $type";
            if ($after) $sql .= " AFTER `$after`";
            $db->exec($sql);
            echo "Column '$column' added to '$table'.<br>";
        } else {
            echo "Column '$column' already exists in '$table'.<br>";
        }
    } catch (Exception $e) {
        echo "Error adding '$column' to '$table': " . $e->getMessage() . "<br>";
    }
}

echo "Starting DB Fix...<br>";

// 1. Update sefaz_config
$sefaz_cols = [
    ['cnpj', 'VARCHAR(18)', 'id'],
    ['razao_social', 'VARCHAR(255)', 'cnpj'],
    ['nome_fantasia', 'VARCHAR(255)', 'razao_social'],
    ['inscricao_estadual', 'VARCHAR(30)', 'nome_fantasia'],
    ['inscricao_municipal', 'VARCHAR(30)', 'inscricao_estadual'],
    ['cep', 'VARCHAR(10)', 'inscricao_municipal'],
    ['logradouro', 'VARCHAR(255)', 'cep'],
    ['numero_endereco', 'VARCHAR(20)', 'logradouro'],
    ['complemento', 'VARCHAR(100)', 'numero_endereco'],
    ['bairro', 'VARCHAR(100)', 'complemento'],
    ['cidade', 'VARCHAR(100)', 'bairro'],
    ['uf', 'CHAR(2)', 'cidade'],
    ['codigo_uf', 'INT', 'uf'],
    ['codigo_municipio', 'VARCHAR(10)', 'codigo_uf'],
    ['telefone', 'VARCHAR(20)', 'codigo_municipio'],
    ['regime_tributario', 'TINYINT(1) DEFAULT 1', 'certificado_senha'],
    ['serie_nfce', 'INT DEFAULT 1', 'regime_tributario'],
    ['ultimo_numero_nfce', 'INT DEFAULT 0', 'serie_nfce'],
    ['csc', 'VARCHAR(255)', 'ultimo_numero_nfce'],
    ['csc_id', 'VARCHAR(50)', 'csc'],
    ['tipo_emissao', 'TINYINT(1) DEFAULT 1', 'csc_id'],
    ['finalidade', 'TINYINT(1) DEFAULT 1', 'tipo_emissao'],
    ['ind_pres', 'TINYINT(1) DEFAULT 1', 'finalidade'],
    ['tipo_impressao', 'TINYINT(1) DEFAULT 4', 'ind_pres']
];

foreach ($sefaz_cols as $col) {
    addColumn($db, 'sefaz_config', $col[0], $col[1], $col[2]);
}

// 2. Update filiais
$filial_cols = [
    ['nome_fantasia', 'VARCHAR(255)', 'razao_social'],
    ['inscricao_municipal', 'VARCHAR(30)', 'inscricao_estadual'],
    ['numero_endereco', 'VARCHAR(20)', 'logradouro'],
    ['cidade', 'VARCHAR(100)', 'bairro'],
    ['codigo_uf', 'INT', 'uf'],
    ['regime_tributario', 'TINYINT(1) DEFAULT 1', 'ambiente'],
    ['csc', 'VARCHAR(255)', 'csc_token'],
    ['finalidade', 'TINYINT(1) DEFAULT 1', 'finalidade_emissao'],
    ['ind_pres', 'TINYINT(1) DEFAULT 1', 'indicador_presenca'],
    ['tipo_impressao', 'TINYINT(1) DEFAULT 4', 'tipo_impressao_danfe']
];

foreach ($filial_cols as $col) {
    addColumn($db, 'filiais', $col[0], $col[1], $col[2]);
}

echo "DB Fix Completed.";
