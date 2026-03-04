<?php
require_once 'config.php';
$db = \App\Config\Database::getInstance()->getConnection();

$tables_to_check = ['vendas', 'produtos', 'clientes', 'fornecedores', 'os', 'contas_pagar', 'contas_receber', 'movimentacoes_estoque', 'movimentacao_estoque', 'usuarios', 'filiais'];

echo "--- Database Table Verification ---\n";
foreach ($tables_to_check as $table) {
    try {
        $db->query("SELECT 1 FROM $table LIMIT 1");
        echo "OK: $table exists\n";
    } catch (Exception $e) {
        echo "MISSING: $table\n";
    }
}

echo "\n--- Multi-tenant Column Check ---\n";
foreach ($tables_to_check as $table) {
    try {
        $stmt = $db->query("DESCRIBE $table");
        $cols = $stmt->fetchAll(PDO::FETCH_COLUMN);
        if (in_array('filial_id', $cols)) {
            echo "$table: has filial_id\n";
        } else {
            echo "$table: MISSING filial_id\n";
        }
    } catch (Exception $e) {
        // Silently skip missing tables
    }
}
