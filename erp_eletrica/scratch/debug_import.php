<?php
require_once __DIR__ . '/../config.php';
try {
    $db = \App\Config\Database::getInstance()->getConnection();
    $table = 'pre_vendas';
    
    // Check Columns
    $stmt = $db->query("DESCRIBE $table");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "Colunas de $table:\n";
    foreach ($columns as $col) {
        echo "- " . $col['Field'] . " (" . $col['Type'] . ")\n";
    }
    
    // Test the list_pending query
    $sql = "
        SELECT pv.id, pv.codigo, pv.valor_total, pv.status, pv.created_at, 
               COALESCE(c.nome, 'CONS', 'Consumidor') as cliente_nome
        FROM pre_vendas pv 
        LEFT JOIN clientes c ON pv.cliente_id = c.id 
        WHERE pv.status = 'pendente'
        LIMIT 1";
    $db->query($sql);
    echo "\nTeste de consulta simples: OK\n";

} catch (Exception $e) {
    echo "\nERRO: " . $e->getMessage() . "\n";
}
