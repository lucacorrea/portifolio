<?php
declare(strict_types=1);

// Caminho absoluto para evitar erros de inclusão
require_once __DIR__ . '/../assets/conexao.php';

if (!isset($pdo) || !($pdo instanceof PDO)) {
    die("Erro: Conexão com o banco de dados não estabelecida.");
}

try {
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // SQL para criar a tabela de histórico de edições
    $sql = "
    CREATE TABLE IF NOT EXISTS solicitantes_edicoes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        solicitante_id INT NOT NULL,
        hora_edicao DATETIME NOT NULL,
        responsavel_edicao VARCHAR(255) NOT NULL,
        usuario_id INT DEFAULT NULL,
        observacao TEXT DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX (solicitante_id),
        FOREIGN KEY (solicitante_id) REFERENCES solicitantes(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";

    $pdo->exec($sql);
    echo "Tabela 'solicitantes_edicoes' criada ou já existente com sucesso!<br>";

} catch (PDOException $e) {
    die("Erro ao atualizar banco de dados: " . $e->getMessage());
}
?>
