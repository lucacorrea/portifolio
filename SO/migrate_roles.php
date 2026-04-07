<?php
require_once 'config/database.php';

try {
    $pdo->exec("ALTER TABLE usuarios MODIFY COLUMN nivel ENUM('SUPORTE', 'ADMIN', 'FUNCIONARIO', 'SECRETARIO') NOT NULL");
    echo "Tabela 'usuarios' atualizada com sucesso!\n";
} catch (PDOException $e) {
    echo "Erro na migração: " . $e->getMessage() . "\n";
}
