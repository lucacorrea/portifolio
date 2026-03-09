<?php
require_once 'config.php';

echo "<h1>Debug Autorizações</h1>";

try {
    echo "<h3>Estrutura da Tabela:</h3>";
    $columns = $pdo->query("DESCRIBE autorizacoes_temporarias")->fetchAll();
    echo "<table border='1'><tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    foreach ($columns as $col) {
        echo "<tr><td>{$col['Field']}</td><td>{$col['Type']}</td><td>{$col['Null']}</td><td>{$col['Key']}</td><td>{$col['Default']}</td><td>{$col['Extra']}</td></tr>";
    }
    echo "</table>";

    echo "<h3>Últimas 5 Autorizações:</h3>";
    $last = $pdo->query("SELECT * FROM autorizacoes_temporarias ORDER BY id DESC LIMIT 5")->fetchAll();
    echo "<table border='1'><tr><th>ID</th><th>Tipo</th><th>Codigo</th><th>Filial</th><th>Validade</th></tr>";
    foreach ($last as $l) {
        echo "<tr><td>{$l['id']}</td><td>'{$l['tipo']}'</td><td>{$l['codigo']}</td><td>{$l['filial_id']}</td><td>{$l['validade']}</td></tr>";
    }
    echo "</table>";

    echo "<h3>Migrações Executadas:</h3>";
    $migs = $pdo->query("SELECT migration FROM migrations ORDER BY id DESC LIMIT 10")->fetchAll(PDO::FETCH_COLUMN);
    echo "<ul>";
    foreach ($migs as $m) echo "<li>$m</li>";
    echo "</ul>";

} catch (Exception $e) {
    echo "Erro: " . $e->getMessage();
}
