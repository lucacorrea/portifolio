<?php
require_once 'nfce/config.php';
try {
    $pdo->exec("ALTER TABLE vendas ADD COLUMN cpf_cliente VARCHAR(20) DEFAULT NULL AFTER filial_id");
    echo "Column cpf_cliente added.\n";
} catch (PDOException $e) {
    echo "cpf_cliente: " . $e->getMessage() . "\n";
}

try {
    $pdo->exec("ALTER TABLE vendas ADD COLUMN cliente_nome VARCHAR(255) DEFAULT NULL AFTER cpf_cliente");
    echo "Column cliente_nome added.\n";
} catch (PDOException $e) {
    echo "cliente_nome: " . $e->getMessage() . "\n";
}
