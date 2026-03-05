<?php
require_once 'config.php';

echo "<h1>🛠️ Sistema de Reparo: Módulo de Caixa</h1>";

try {
    $db = \App\Config\Database::getInstance()->getConnection();
    
    echo "<h3>1. Criando Tabela 'caixas'...</h3>";
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
    echo "<p style='color:green;'>✅ Tabela 'caixas' criada ou já existente.</p>";

    echo "<h3>2. Criando Tabela 'caixa_movimentacoes'...</h3>";
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
    echo "<p style='color:green;'>✅ Tabela 'caixa_movimentacoes' criada ou já existente.</p>";

    echo "<h3>3. Verificando Infraestrutura de Permissões (RBAC)...</h3>";
    $sqlRBAC = "
    CREATE TABLE IF NOT EXISTS permissoes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        modulo VARCHAR(50) NOT NULL,
        acao VARCHAR(50) NOT NULL,
        descricao VARCHAR(255),
        UNIQUE KEY uk_modulo_acao (modulo, acao)
    ) ENGINE=InnoDB;

    CREATE TABLE IF NOT EXISTS permissao_nivel (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nivel ENUM('admin', 'gerente', 'vendedor', 'tecnico', 'master') NOT NULL,
        permissao_id INT NOT NULL,
        FOREIGN KEY (permissao_id) REFERENCES permissoes(id) ON DELETE CASCADE
    ) ENGINE=InnoDB;";
    $db->exec($sqlRBAC);
    echo "<p style='color:green;'>✅ Tabelas de permissões verificadas/criadas.</p>";

    echo "<h3>4. Inserindo Permissões de Caixa...</h3>";
    $sqlPerms = "INSERT IGNORE INTO permissoes (modulo, acao, descricao) VALUES 
    ('caixa', 'abrir', 'Abrir novo caixa'),
    ('caixa', 'fechar', 'Fechar caixa aberto'),
    ('caixa', 'movimentar', 'Registrar sangria e suprimento'),
    ('caixa', 'visualizar', 'Visualizar histórico e relatórios de caixa');";
    $db->exec($sqlPerms);
    echo "<p style='color:green;'>✅ Permissões inseridas com sucesso.</p>";

    echo "<hr><h2>🎉 Reparo Concluído!</h2>";
    echo "<p>O sistema já deve estar funcionando agora. Por favor, <b>apague este arquivo (repair_caixa.php)</b> por segurança.</p>";
    echo "<a href='index.php' style='padding:10px 20px; background:#0d6efd; color:white; text-decoration:none; border-radius:5px;'>Voltar para o Dashboard</a>";

} catch (Exception $e) {
    echo "<h2 style='color:red;'>❌ Erro Crítico:</h2> " . $e->getMessage();
}
