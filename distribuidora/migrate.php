<?php
require_once __DIR__ . '/assets/conexao.php';
$pdo = db();

$sql = "
CREATE TABLE IF NOT EXISTS usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(255) NOT NULL,
    email VARCHAR(190) UNIQUE NOT NULL,
    senha VARCHAR(255) NOT NULL,
    perfil ENUM('ADMIN', 'VENDEDOR') DEFAULT 'VENDEDOR',
    status VARCHAR(10) DEFAULT 'ATIVO',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS parametros (
    id INT AUTO_INCREMENT PRIMARY KEY,
    chave VARCHAR(100) UNIQUE NOT NULL,
    valor TEXT,
    descricao VARCHAR(255),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
";

try {
    $pdo->exec($sql);
    echo "Tabelas criadas com sucesso!";
} catch (PDOException $e) {
    echo "Erro ao criar tabelas: " . $e->getMessage();
}
?>
