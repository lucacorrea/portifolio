<?php
require_once 'config.php';

echo "<h1>🛠️ Sistema de Reparo e Atualização ERP</h1>";

try {
    $db = \App\Config\Database::getInstance()->getConnection();
    
    // --- MÓDULO CAIXA ---
    echo "<h3>1. Verificando Tabelas de Caixa...</h3>";
    $sqlCaixas = "CREATE TABLE IF NOT EXISTS caixas (
        id INT AUTO_INCREMENT PRIMARY KEY,
        filial_id INT NOT NULL,
        operador_id INT NOT NULL,
        valor_abertura DECIMAL(10,2) NOT NULL,
        valor_fechamento DECIMAL(10,2) NULL,
        status ENUM('aberto', 'fechado') DEFAULT 'aberto',
        data_abertura DATETIME NOT NULL,
        data_fechamento DATETIME NULL,
        observacao TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_caixa_filial (filial_id),
        INDEX idx_caixa_operador (operador_id),
        INDEX idx_caixa_status (status),
        FOREIGN KEY (filial_id) REFERENCES filiais(id),
        FOREIGN KEY (operador_id) REFERENCES usuarios(id)
    ) ENGINE=InnoDB;";
    $db->exec($sqlCaixas);

    $sqlMov = "CREATE TABLE IF NOT EXISTS caixa_movimentacoes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        caixa_id INT NOT NULL,
        tipo ENUM('sangria', 'suprimento') NOT NULL,
        valor DECIMAL(10,2) NOT NULL,
        motivo VARCHAR(255) NOT NULL,
        operador_id INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_mov_caixa (caixa_id),
        INDEX idx_mov_operador (operador_id),
        FOREIGN KEY (caixa_id) REFERENCES caixas(id) ON DELETE CASCADE,
        FOREIGN KEY (operador_id) REFERENCES usuarios(id)
    ) ENGINE=InnoDB;";
    $db->exec($sqlMov);
    echo "<p style='color:green;'>✅ Tabelas de Caixa OK.</p>";

    // --- MÓDULO CUSTOS ---
    echo "<h3>2. Verificando Tabelas de Centro de Custos...</h3>";
    $sqlCC = "CREATE TABLE IF NOT EXISTS centros_custo (
        id INT AUTO_INCREMENT PRIMARY KEY,
        filial_id INT NOT NULL,
        nome VARCHAR(100) NOT NULL,
        tipo ENUM('fixo', 'variavel') NOT NULL,
        ativo BOOLEAN DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_cc_filial (filial_id),
        FOREIGN KEY (filial_id) REFERENCES filiais(id)
    ) ENGINE=InnoDB;";
    $db->exec($sqlCC);

    $sqlLC = "CREATE TABLE IF NOT EXISTS lancamentos_custos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        filial_id INT NOT NULL,
        centro_custo_id INT NOT NULL,
        descricao VARCHAR(255) NOT NULL,
        valor DECIMAL(10,2) NOT NULL,
        data_lancamento DATE NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_lc_filial (filial_id),
        INDEX idx_lc_cc (centro_custo_id),
        INDEX idx_lc_data (data_lancamento),
        FOREIGN KEY (filial_id) REFERENCES filiais(id),
        FOREIGN KEY (centro_custo_id) REFERENCES centros_custo(id) ON DELETE CASCADE
    ) ENGINE=InnoDB;";
    $db->exec($sqlLC);
    echo "<p style='color:green;'>✅ Tabelas de Custos OK.</p>";

    // --- MÓDULO INTELIGÊNCIA ---
    echo "<h3>3. Verificando Tabelas de Inteligência Comercial...</h3>";
    $sqlABC = "CREATE TABLE IF NOT EXISTS produto_curva_abc (
        id INT AUTO_INCREMENT PRIMARY KEY,
        produto_id INT NOT NULL,
        filial_id INT NOT NULL,
        classificacao ENUM('A', 'B', 'C') NOT NULL,
        periodo_referencia VARCHAR(20) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uk_prod_filial_per (produto_id, filial_id, periodo_referencia),
        FOREIGN KEY (produto_id) REFERENCES produtos(id) ON DELETE CASCADE,
        FOREIGN KEY (filial_id) REFERENCES filiais(id)
    ) ENGINE=InnoDB;";
    $db->exec($sqlABC);

    $sqlAlert = "CREATE TABLE IF NOT EXISTS alertas_estoque (
        id INT AUTO_INCREMENT PRIMARY KEY,
        produto_id INT NOT NULL,
        filial_id INT NOT NULL,
        tipo ENUM('reposicao') NOT NULL,
        mensagem TEXT NOT NULL,
        status ENUM('ativo', 'resolvido') DEFAULT 'ativo',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_alert_filial (filial_id),
        INDEX idx_alert_prod (produto_id),
        FOREIGN KEY (produto_id) REFERENCES produtos(id) ON DELETE CASCADE,
        FOREIGN KEY (filial_id) REFERENCES filiais(id)
    ) ENGINE=InnoDB;";
    $db->exec($sqlAlert);
    echo "<p style='color:green;'>✅ Tabelas de Inteligência OK.</p>";

    // --- PERMISSÕES ---
    echo "<h3>4. Atualizando Permissões...</h3>";
    $sqlPerms = "
    CREATE TABLE IF NOT EXISTS permissoes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        modulo VARCHAR(50) NOT NULL,
        acao VARCHAR(50) NOT NULL,
        descricao VARCHAR(255),
        UNIQUE KEY uk_modulo_acao (modulo, acao)
    ) ENGINE=InnoDB;

    INSERT IGNORE INTO permissoes (modulo, acao, descricao) VALUES 
    ('caixa', 'abrir', 'Abrir novo caixa'),
    ('caixa', 'fechar', 'Fechar caixa aberto'),
    ('caixa', 'movimentar', 'Registrar sangria e suprimento'),
    ('caixa', 'visualizar', 'Visualizar histórico de caixa'),
    ('custos', 'visualizar', 'Ver relatórios de custos'),
    ('custos', 'gerenciar', 'Gerenciar centros e lançamentos'),
    ('inteligencia', 'visualizar', 'Ver BI e Inteligência Comercial'),
    ('inteligencia', 'recalcular', 'Recalcular Curvas e Alertas');";
    $db->exec($sqlPerms);
    echo "<p style='color:green;'>✅ Permissões Atualizadas.</p>";

    echo "<hr><h2>🎉 Tudo Pronto!</h2>";
    echo "<p>O sistema foi atualizado com os novos módulos. Por favor, <b>apague este arquivo (repair_system.php)</b>.</p>";
    echo "<a href='index.php' style='padding:10px 20px; background:#0d6efd; color:white; text-decoration:none; border-radius:5px;'>Ir para o Sistema</a>";

} catch (Exception $e) {
    echo "<h2 style='color:red;'>❌ Erro Crítico:</h2> " . $e->getMessage();
}
