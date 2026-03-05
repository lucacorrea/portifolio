<?php
/**
 * Database Initialization Script
 * Creates the necessary tables if they don't exist.
 */

require_once __DIR__ . '/../config/database.php';

function initializeDatabase() {
    $pdo = getDatabaseConnection();

    $sql = "CREATE TABLE IF NOT EXISTS documentos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        tipo_documento VARCHAR(50) NOT NULL,
        numero_documento VARCHAR(20) NOT NULL,
        destinatario VARCHAR(255) NOT NULL,
        assunto VARCHAR(255) NOT NULL,
        conteudo TEXT NOT NULL,
        responsavel VARCHAR(100) NOT NULL,
        cargo VARCHAR(100) NOT NULL,
        cidade VARCHAR(100) NOT NULL,
        data_documento DATE NOT NULL,
        data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_tipo (tipo_documento),
        INDEX idx_data (data_documento)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

    try {
        $pdo->exec($sql);
        return true;
    } catch (PDOException $e) {
        error_log("Erro ao inicializar banco de dados: " . $e->getMessage());
        return false;
    }
}

// Run if called directly
if (basename(__FILE__) == basename($_SERVER['SCRIPT_FILENAME'])) {
    if (initializeDatabase()) {
        echo "Banco de dados inicializado com sucesso!";
    } else {
        echo "Falha ao inicializar o banco de dados.";
    }
}
