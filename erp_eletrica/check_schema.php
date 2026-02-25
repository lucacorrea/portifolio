<?php
require_once 'config.php';
$db = \App\Config\Database::getInstance()->getConnection();

$tables = ['vendas', 'produtos', 'clientes', 'fornecedores', 'ordens_servico', 'contas_pagar', 'contas_receber', 'movimentacoes_estoque', 'compras', 'vendas_itens', 'orcamentos', 'pre_vendas'];

foreach ($tables as $table) {
    echo "Checking $table: ";
    try {
        $stmt = $db->query("DESCRIBE $table");
        $cols = $stmt->fetchAll(PDO::FETCH_COLUMN);
        if (in_array('filial_id', $cols)) {
            echo "HAS filial_id\n";
        } else {
            echo "MISSING filial_id\n";
        }
    } catch (Exception $e) {
        echo "ERROR - " . $e->getMessage() . "\n";
    }
}
