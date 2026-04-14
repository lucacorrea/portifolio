<?php
require_once 'config.php';

$db = \App\Config\Database::getInstance()->getConnection();

function addColumn($db, $table, $column, $type, $after = '') {
    try {
        $stmt = $db->query("SHOW COLUMNS FROM $table LIKE '$column'");
        if ($stmt->rowCount() == 0) {
            $sql = "ALTER TABLE $table ADD $column $type";
            if ($after) $sql .= " AFTER $after";
            $db->exec($sql);
            echo "Column '$column' added to '$table'.<br>";
        } else {
            echo "Column '$column' already exists in '$table'.<br>";
        }
    } catch (Exception $e) {
        echo "Error adding column '$column' to '$table': " . $e->getMessage() . "<br>";
    }
}

// 1. Ensure sefaz_config exists and has columns
try {
    $db->exec("CREATE TABLE IF NOT EXISTS sefaz_config (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ambiente VARCHAR(20) DEFAULT 'homologacao',
        certificado_path VARCHAR(255),
        certificado_senha VARCHAR(100),
        csc_id VARCHAR(10),
        csc_token VARCHAR(100)
    )");
} catch (Exception $e) { echo "Error creating sefaz_config: " . $e->getMessage() . "<br>"; }

$sefaz_cols = [
    ['cnpj', 'VARCHAR(20)'],
    ['razao_social', 'VARCHAR(255)'],
    ['telefone', 'VARCHAR(20)'],
    ['email', 'VARCHAR(100)'],
    ['inscricao_estadual', 'VARCHAR(20)'],
    ['nome_fantasia', 'VARCHAR(255)'],
    ['inscricao_municipal', 'VARCHAR(20)'],
    ['cep', 'VARCHAR(10)'],
    ['logradouro', 'VARCHAR(255)'],
    ['numero_endereco', 'VARCHAR(20)'],
    ['complemento', 'VARCHAR(100)'],
    ['bairro', 'VARCHAR(100)'],
    ['cidade', 'VARCHAR(100)'],
    ['uf', 'VARCHAR(2)'],
    ['codigo_uf', 'VARCHAR(2)'],
    ['codigo_municipio', 'VARCHAR(10)'],
    ['regime_tributario', 'VARCHAR(255)'],
    ['serie_nfce', 'VARCHAR(10)'],
    ['ultimo_numero_nfce', 'INT'],
    ['tipo_emissao', 'VARCHAR(10)'],
    ['finalidade', 'VARCHAR(10)'],
    ['ind_pres', 'VARCHAR(10)'],
    ['tipo_impressao', 'VARCHAR(10)']
];

foreach ($sefaz_cols as $col) {
    addColumn($db, 'sefaz_config', $col[0], $col[1]);
}

// 2. Ensure filiais has columns
$filial_cols = [
    ['csc_id', 'VARCHAR(10)', 'email'],
    ['csc_token', 'VARCHAR(100)', 'csc_id'],
    ['ambiente', 'VARCHAR(20)', 'csc_token'],
    ['tipo_emissao', 'VARCHAR(10)', 'ambiente'],
    ['finalidade', 'VARCHAR(10)', 'tipo_emissao'],
    ['ind_pres', 'VARCHAR(10)', 'finalidade'],
    ['tipo_impressao', 'TINYINT(1) DEFAULT 4', 'ind_pres'],
    ['serie_nfce', 'VARCHAR(10)', 'tipo_impressao'],
    ['ultimo_numero_nfce', 'INT', 'serie_nfce']
];

foreach ($filial_cols as $col) {
    addColumn($db, 'filiais', $col[0], $col[1], $col[2]);
}

// 3. Ensure configuracoes table
try {
    $db->exec("CREATE TABLE IF NOT EXISTS configuracoes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        chave VARCHAR(100) UNIQUE NOT NULL,
        valor TEXT,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
} catch (Exception $e) { echo "Error creating configuracoes: " . $e->getMessage() . "<br>"; }

// 4. Ensure nfe_importadas table
try {
    $db->exec("CREATE TABLE IF NOT EXISTS nfe_importadas (
        id INT AUTO_INCREMENT PRIMARY KEY,
        filial_id INT NOT NULL,
        chave_acesso VARCHAR(44) UNIQUE NOT NULL,
        fornecedor_cnpj VARCHAR(20),
        fornecedor_nome VARCHAR(255),
        numero_nota VARCHAR(20),
        data_emissao DATETIME,
        valor_total DECIMAL(15,2),
        manifestacao_tipo VARCHAR(10),
        manifestacao_data DATETIME,
        xml LONGTEXT,
        status VARCHAR(20) DEFAULT 'pendente',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
} catch (Exception $e) { echo "Error creating nfe_importadas: " . $e->getMessage() . "<br>"; }

// 5. Apply User CSC Data and Sync Matriz
try {
    $csc_id = '1';
    $csc_token = '092c39ec0d341d8f';
    $ambiente = 'producao';

    // Update Global Config
    $stmt = $db->query("SELECT id FROM sefaz_config LIMIT 1");
    $global = $stmt->fetch();
    if ($global) {
        $db->prepare("UPDATE sefaz_config SET csc_id = ?, csc_token = ?, ambiente = ? WHERE id = ?")
           ->execute([$csc_id, $csc_token, $ambiente, $global['id']]);
    } else {
        $db->prepare("INSERT INTO sefaz_config (csc_id, csc_token, ambiente) VALUES (?, ?, ?)")
           ->execute([$csc_id, $csc_token, $ambiente]);
    }
    echo "Global 'sefaz_config' updated with CSC data.<br>";

    // Sync to Matriz (filiais table)
    $stmtMatriz = $db->query("SELECT id FROM filiais WHERE principal = 1 LIMIT 1");
    $matrizId = $stmtMatriz->fetchColumn();
    if ($matrizId) {
        $db->prepare("UPDATE filiais SET csc_id = ?, csc_token = ?, ambiente = ? WHERE id = ?")
           ->execute([$csc_id, $csc_token, $ambiente, $matrizId]);
        echo "Matriz record in 'filiais' synced with CSC data.<br>";
    }
} catch (Exception $e) { echo "Error applying CSC data: " . $e->getMessage() . "<br>"; }

echo "DB Fix Completed.";
