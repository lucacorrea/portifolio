<?php
require_once __DIR__ . '/assets/conexao.php';

try {
    $pdo->exec("
        INSERT INTO ajudas_tipos (nome, categoria, status, created_at, updated_at)
        SELECT 'PERÍCIA', 'Outros', 'Ativa', NOW(), NOW()
        WHERE NOT EXISTS (
            SELECT 1 FROM ajudas_tipos WHERE nome = 'PERÍCIA'
        );
    ");
    echo "<h1>Sucesso!</h1><p>Opção 'PERÍCIA' adicionada (ou já existia).</p>";
} catch (PDOException $e) {
    echo "<h1>Erro</h1><p>" . $e->getMessage() . "</p>";
}
