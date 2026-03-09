<?php
require_once 'config.php';

echo "<h1>🔧 Reparador de Banco de Dados</h1>";

try {
    // 1. Force the alteration of the column to VARCHAR(50) to be safe and avoid ENUM restrictions
    echo "<p>Atualizando coluna 'tipo' para VARCHAR...</p>";
    $pdo->exec("ALTER TABLE autorizacoes_temporarias MODIFY COLUMN tipo VARCHAR(50) NOT NULL");
    echo "<p style='color:green;'>✅ Coluna atualizada com sucesso!</p>";

    // 2. Add 'suprimento' and 'geral' to the list if they were missing (redundant but safe)
    // Actually VARCHAR doesn't need this, but we can ensure old 'empty' records are fixed if the user wants.
    
    // 3. Verify the structure
    $stmt = $pdo->query("DESCRIBE autorizacoes_temporarias");
    $columns = $stmt->fetchAll();
    echo "<h3>Estrutura Atual:</h3><table border='1'>";
    foreach ($columns as $col) {
        if ($col['Field'] === 'tipo') {
            echo "<tr style='background:#dfd;'><td>{$col['Field']}</td><td>{$col['Type']}</td></tr>";
        } else {
            echo "<tr><td>{$col['Field']}</td><td>{$col['Type']}</td></tr>";
        }
    }
    echo "</table>";

    echo "<p><b>Ação recomendada:</b> Tente gerar um NOVO código de Suprimento agora.</p>";

} catch (Exception $e) {
    echo "<p style='color:red;'>❌ Erro: " . $e->getMessage() . "</p>";
}
