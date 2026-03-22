<?php
// update_db_solicitantes.php
require_once __DIR__ . '/../assets/conexao.php';

try {
    if (!isset($pdo)) {
        throw new Exception("PDO connection not found or failed.");
    }
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "Verificando colunas na tabela 'solicitantes'...\n";

    // 1. Check/Add hora_cadastro
    $stmt = $pdo->query("SHOW COLUMNS FROM solicitantes LIKE 'hora_cadastro'");
    if ($stmt->fetch()) {
        echo "Coluna 'hora_cadastro' ja existe.\n";
    } else {
        echo "Adicionando coluna 'hora_cadastro'...\n";
        $pdo->exec("ALTER TABLE solicitantes ADD COLUMN hora_cadastro TIME DEFAULT NULL");
        echo "Coluna 'hora_cadastro' adicionada com sucesso.\n";
    }

    // 2. Check/Add responsavel
    $stmt = $pdo->query("SHOW COLUMNS FROM solicitantes LIKE 'responsavel'");
    if ($stmt->fetch()) {
        echo "Coluna 'responsavel' ja existe.\n";
    } else {
        echo "Adicionando coluna 'responsavel'...\n";
        $pdo->exec("ALTER TABLE solicitantes ADD COLUMN responsavel VARCHAR(255) DEFAULT NULL");
        echo "Coluna 'responsavel' adicionada com sucesso.\n";
    }

    echo "Migracao concluida.\n";

} catch (Exception $e) {
    echo "Erro: " . $e->getMessage() . "\n";
}
