<?php
require_once 'config.php';
$db = \App\Config\Database::getInstance()->getConnection();

function checkTable($db, $table) {
    echo "<h3>Estrutura da tabela: $table</h3>";
    try {
        $stmt = $db->query("DESCRIBE $table");
        echo "<table border='1'><tr><th>Field</th><th>Type</th></tr>";
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            echo "<tr><td>{$row['Field']}</td><td>{$row['Type']}</td></tr>";
        }
        echo "</table>";
    } catch (Exception $e) {
        echo "<p style='color:red;'>Erro: " . $e->getMessage() . "</p>";
    }
}

checkTable($db, 'pre_vendas');
checkTable($db, 'vendas');
checkTable($db, 'pre_venda_itens');
checkTable($db, 'vendas_itens');
