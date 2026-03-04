<?php
require_once 'config.php';

echo "<h1>🛠️ Finalizando Instalação de Novas Funcionalidades</h1>";

try {
    $db = \App\Config\Database::getInstance()->getConnection();
    
    echo "<h3>1. Verificando tabela 'pre_vendas'...</h3>";
    $stmt = $db->query("SHOW COLUMNS FROM pre_vendas LIKE 'nome_cliente_avulso'");
    if (!$stmt->fetch()) {
        echo "<p>Adicionando coluna 'nome_cliente_avulso' em pre_vendas...</p>";
        $db->exec("ALTER TABLE pre_vendas ADD COLUMN nome_cliente_avulso VARCHAR(255) NULL AFTER cliente_id");
        echo "<p style='color:green;'>✅ Coluna 'nome_cliente_avulso' adicionada.</p>";
    } else {
        echo "<p style='color:blue;'>ℹ️ Coluna 'nome_cliente_avulso' já existe.</p>";
    }

    echo "<h3>2. Verificando tabela 'vendas'...</h3>";
    $stmt = $db->query("SHOW COLUMNS FROM vendas LIKE 'nome_cliente_avulso'");
    if (!$stmt->fetch()) {
        echo "<p>Adicionando coluna 'nome_cliente_avulso' em vendas...</p>";
        $db->exec("ALTER TABLE vendas ADD COLUMN nome_cliente_avulso VARCHAR(255) NULL AFTER cliente_id");
        echo "<p style='color:green;'>✅ Coluna 'nome_cliente_avulso' adicionada.</p>";
    } else {
        echo "<p style='color:blue;'>ℹ️ Coluna 'nome_cliente_avulso' já existe.</p>";
    }

    echo "<h3>3. Verificando tabela 'autorizacoes_temporarias'...</h3>";
    $sqlAuth = "CREATE TABLE IF NOT EXISTS autorizacoes_temporarias (
        id INT AUTO_INCREMENT PRIMARY KEY,
        tipo ENUM('desconto', 'sangria') NOT NULL,
        codigo VARCHAR(10) NOT NULL,
        usuario_autorizador_id INT NULL,
        validade DATETIME NOT NULL,
        utilizado BOOLEAN DEFAULT 0,
        filial_id INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_auth_codigo (codigo),
        INDEX idx_auth_filial (filial_id),
        INDEX idx_auth_validade (validade)
    ) ENGINE=InnoDB;";
    $db->exec($sqlAuth);
    echo "<p style='color:green;'>✅ Tabela 'autorizacoes_temporarias' verificada/criada.</p>";

    echo "<h3>4. Verificando tabela 'nfe_importadas'...</h3>";
    $sqlNfe = "CREATE TABLE IF NOT EXISTS nfe_importadas (
        id INT AUTO_INCREMENT PRIMARY KEY,
        filial_id INT NOT NULL,
        chave_nfe VARCHAR(44) NOT NULL,
        fornecedor_cnpj VARCHAR(14) NOT NULL,
        fornecedor_nome VARCHAR(255) NOT NULL,
        numero_nota VARCHAR(20) NOT NULL,
        data_emissao DATETIME NOT NULL,
        valor_total DECIMAL(15,2) NOT NULL,
        xml_conteudo LONGTEXT,
        status ENUM('pendente', 'importada') DEFAULT 'pendente',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_filial (filial_id),
        INDEX idx_chave (chave_nfe)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    
    try {
        $db->exec($sqlNfe);
        echo "<p style='color:green;'>✅ Tabela 'nfe_importadas' verificada/criada.</p>";
    } catch (Exception $e) {
        echo "<p style='color:red;'>❌ Erro ao criar tabela 'nfe_importadas': " . $e->getMessage() . "</p>";
    }

    echo "<hr><h2>Instalação/Atualização Concluída!</h2>";
    echo "<p>As funcionalidades de Pré-Venda, Fiado, XML e Autorizações estão com o banco de dados configurado.</p>";
    echo "<p>Por segurança, <b>apague este arquivo (finish_install.php)</b> agora.</p>";
    echo "<br><a href='index.php' style='padding:10px 20px; background:#198754; color:white; text-decoration:none; border-radius:5px;'>Ir para o Painel</a>";

} catch (Exception $e) {
    echo "<h2 style='color:red;'>❌ Erro ao atualizar banco:</h2> " . $e->getMessage();
}
