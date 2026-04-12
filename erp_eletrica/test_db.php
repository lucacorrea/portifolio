<?php
require 'config.php';
$db = \App\Config\Database::getInstance()->getConnection();

echo "<pre>";
echo "<strong>--- ESTRUTURA DA TABELA ---</strong>\n\n";

try {
    $q = $db->query("DESCRIBE nfe_importadas");
    $cols = $q->fetchAll(\PDO::FETCH_ASSOC);
    foreach($cols as $col) {
        echo str_pad($col['Field'], 20) . " | " . $col['Type'] . "\n";
    }
} catch (\Exception $e) {
    echo "Erro: " . $e->getMessage() . "\n";
}

echo "</pre>";

