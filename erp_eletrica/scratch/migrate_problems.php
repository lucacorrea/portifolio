<?php
require_once 'config.php';
$db = \App\Config\Database::getInstance()->getConnection();

try {
    $sql = "CREATE TABLE IF NOT EXISTS produtos_problema (
        id INT PRIMARY KEY AUTO_INCREMENT,
        produto_id INT NOT NULL,
        filial_id INT NOT NULL,
        quantidade DECIMAL(10,2) NOT NULL,
        motivo TEXT,
        status ENUM('pendente', 'devolvido', 'descartado', 'consertado') DEFAULT 'pendente',
        data_registro DATETIME DEFAULT CURRENT_TIMESTAMP,
        usuario_id INT,
        INDEX (produto_id),
        INDEX (filial_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    
    $db->exec($sql);
    echo "Tabela produtos_problema criada com sucesso!\n";
} catch (Exception $e) {
    echo "Erro ao criar tabela: " . $e->getMessage() . "\n";
}
