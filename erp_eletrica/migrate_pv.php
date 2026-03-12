<?php
require_once 'config.php';
$db = \App\Config\Database::getInstance()->getConnection();
try {
    $db->exec("ALTER TABLE pre_vendas ADD COLUMN cpf_cliente VARCHAR(20) DEFAULT NULL AFTER nome_cliente_avulso");
    echo "Sucesso: Coluna cpf_cliente adicionada à tabela pre_vendas.\n";
} catch (Exception $e) {
    echo "Erro ou já existe: " . $e->getMessage() . "\n";
}
