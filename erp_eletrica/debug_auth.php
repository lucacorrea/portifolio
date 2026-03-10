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

    echo "<h3>Últimas 10 Autorizações:</h3>";
    $last = $pdo->query("SELECT * FROM autorizacoes_temporarias ORDER BY id DESC LIMIT 10")->fetchAll();
    echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>
            <tr style='background: #eee;'>
                <th>ID</th><th>Tipo (No Banco)</th><th>Codigo</th><th>Filial ID</th><th>Validade</th><th>Utilizado</th>
            </tr>";
    foreach ($last as $l) {
        $tipoVal = $l['tipo'] === '' ? "<b style='color:red;'>VAZIO (ERRO ENUM)</b>" : "'".$l['tipo']."'";
        $utilizado = $l['utilizado'] ? 'Sim' : 'Não';
        echo "<tr>
                <td>{$l['id']}</td>
                <td>$tipoVal</td>
                <td>{$l['codigo']}</td>
                <td>{$l['filial_id']}</td>
                <td>{$l['validade']}</td>
                <td>$utilizado</td>
              </tr>";
    }
    echo "</table>";
    
    echo "<p style='color: blue;'>Se a coluna 'Tipo' acima estiver mostrando 'VAZIO', gere um NOVO código agora para testar a correção.</p>";

    echo "<h3>Migrações Executadas:</h3>";
    $migs = $pdo->query("SELECT migration FROM migrations ORDER BY id DESC LIMIT 10")->fetchAll(PDO::FETCH_COLUMN);
    echo "<ul>";
    foreach ($migs as $m) echo "<li>$m</li>";
    echo "</ul>";

} catch (Exception $e) {
    echo "Erro: " . $e->getMessage();
}
