<?php
require_once 'config.php';

echo "<h1>🔍 Diagnóstico de Migrações e Banco</h1>";

try {
    // 1. Check migrations table
    echo "<h3>Tabela de Migrações:</h3>";
    $migs = $pdo->query("SELECT * FROM migrations ORDER BY id ASC")->fetchAll();
    echo "<table border='1'><tr><th>ID</th><th>Migration</th><th>Created At</th></tr>";
    foreach ($migs as $m) {
        echo "<tr><td>{$m['id']}</td><td>{$m['migration']}</td><td>{$m['created_at']}</td></tr>";
    }
    echo "</table>";

    // 2. Check current schema of autorizacoes_temporarias
    echo "<h3>Estrutura de 'autorizacoes_temporarias':</h3>";
    $columns = $pdo->query("DESCRIBE autorizacoes_temporarias")->fetchAll();
    echo "<table border='1'><tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    foreach ($columns as $col) {
        $color = ($col['Field'] === 'tipo') ? "style='background: #fff3cd;'" : "";
        echo "<tr><td $color>{$col['Field']}</td><td $color>{$col['Type']}</td><td>{$col['Null']}</td><td>{$col['Key']}</td><td>{$col['Default']}</td><td>{$col['Extra']}</td></tr>";
    }
    echo "</table>";

    // 3. Check for specific files in migrations directory
    $path = __DIR__ . '/migrations/*.sql';
    $files = glob($path);
    echo "<h3>Arquivos na pasta /migrations/ (".count($files)."):</h3><ul>";
    foreach ($files as $file) {
        echo "<li>" . basename($file) . "</li>";
    }
    echo "</ul>";

} catch (Exception $e) {
    echo "<p style='color:red;'>Erro: " . $e->getMessage() . "</p>";
}
