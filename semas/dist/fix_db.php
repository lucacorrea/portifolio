<?php
require_once __DIR__ . '/assets/conexao.php';

try {
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $sql = "CREATE TABLE IF NOT EXISTS solicitantes_edicoes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        solicitante_id INT NOT NULL,
        hora_edicao DATETIME DEFAULT CURRENT_TIMESTAMP,
        responsavel_edicao VARCHAR(255),
        usuario_id INT,
        observacao TEXT,
        INDEX (solicitante_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

    $pdo->exec($sql);
    echo "Tabela 'solicitantes_edicoes' criada com sucesso!<br>";

    // Also check/add size_bytes if missing
    try {
        $pdo->exec("ALTER TABLE solicitante_documentos ADD COLUMN size_bytes BIGINT DEFAULT 0");
        echo "Coluna 'size_bytes' adicionada em 'solicitante_documentos'.<br>";
    } catch (Throwable $e) {
        echo "Coluna 'size_bytes' ja existe ou erro: " . $e->getMessage() . "<br>";
    }

} catch (Throwable $e) {
    echo "Erro: " . $e->getMessage();
}
