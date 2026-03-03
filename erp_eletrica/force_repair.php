<?php
require_once 'config.php';

echo "<h1>🛠️ Correção Forçada de Banco de Dados</h1>";

try {
    $db = \App\Config\Database::getInstance()->getConnection();
    
    echo "<h3>Verificando centros_custo...</h3>";
    // Check if column 'tipo' exists in 'centros_custo'
    $stmt = $db->query("SHOW COLUMNS FROM centros_custo LIKE 'tipo'");
    if (!$stmt->fetch()) {
        echo "<p>Adicionando coluna 'tipo' em centros_custo...</p>";
        $db->exec("ALTER TABLE centros_custo ADD COLUMN tipo ENUM('fixo', 'variavel') NOT NULL AFTER nome");
        echo "<p style='color:green;'>✅ Coluna 'tipo' adicionada.</p>";
    } else {
        echo "<p style='color:blue;'>ℹ️ Coluna 'tipo' já existe.</p>";
    }

    echo "<h3>Verificando lancamentos_custos...</h3>";
    // Ensure lancamentos_custos exists as well, just in case
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
    echo "<p style='color:green;'>✅ Tabela lancamentos_custos verificada.</p>";

    echo "<hr><h2>🎉 Correção Concluída!</h2>";
    echo "<p>Por favor, <b>apague este arquivo (force_repair.php)</b> e tente acessar o Centro de Custos novamente.</p>";
    echo "<a href='custos.php' style='padding:10px 20px; background:#0d6efd; color:white; text-decoration:none; border-radius:5px;'>Voltar para Custos</a>";

} catch (Exception $e) {
    echo "<h2 style='color:red;'>❌ Erro Crítico:</h2> " . $e->getMessage();
}
