<?php
require_once 'api.php';

try {
    echo "<h1>Limpando e Organizando Banco de Dados</h1>";
    
    // 1. Mostrar estado antes
    $stmt = $pdo->query("SELECT analisador, COUNT(*) as qtd FROM processos GROUP BY analisador");
    $antes = $stmt->fetchAll();
    echo "<h3>Antes da limpeza:</h3><ul>";
    foreach ($antes as $row) {
        echo "<li>'" . ($row['analisador'] ?: 'N/A') . "': " . $row['qtd'] . " registros</li>";
    }
    echo "</ul>";

    // 2. Normalizar Analisadores (Trim e Upper)
    echo "<h3>Normalizando nomes...</h3>";
    $pdo->exec("UPDATE processos SET analisador = UPPER(TRIM(analisador)) WHERE analisador IS NOT NULL");
    
    // 3. Caso específico de variações de KELLEN
    $pdo->exec("UPDATE processos SET analisador = 'KELLEN' WHERE analisador LIKE '%KELLEN%'");
    $pdo->exec("UPDATE usuarios SET nome = 'KELLEN' WHERE UPPER(nome) LIKE '%KELLEN%'");

    // 4. Mostrar estado depois
    $stmt = $pdo->query("SELECT analisador, COUNT(*) as qtd FROM processos GROUP BY analisador");
    $depois = $stmt->fetchAll();
    echo "<h3>Depois da limpeza:</h3><ul>";
    foreach ($depois as $row) {
        echo "<li>'" . ($row['analisador'] ?: 'N/A') . "': " . $row['qtd'] . " registros</li>";
    }
    echo "</ul>";

    echo "<br><div style='padding: 1rem; background: #dcfce7; color: #166534; border-radius: 8px;'>✅ Banco de dados sincronizado e nomes normalizados!</div>";
    echo "<p><a href='index.php' style='display: inline-block; padding: 0.5rem 1rem; background: #2563eb; color: white; text-decoration: none; border-radius: 5px; margin-top: 1rem;'>Voltar ao Dashboard</a></p>";

} catch (Exception $e) {
    echo "<div style='color: red;'>Erro: " . $e->getMessage() . "</div>";
}
