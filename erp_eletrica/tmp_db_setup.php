<?php
require_once 'config.php';

try {
    $db = \App\Config\Database::getInstance()->getConnection();
    
    $sql = "
    CREATE TABLE IF NOT EXISTS fiados_pagamentos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        fiado_id INT NOT NULL,
        valor DECIMAL(10,2) NOT NULL,
        metodo VARCHAR(50) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_fiado_id (fiado_id),
        FOREIGN KEY (fiado_id) REFERENCES contas_receber(id) ON DELETE CASCADE
    );";
    
    $db->exec($sql);
    echo "Tabela fiados_pagamentos criada com sucesso!";
} catch (Exception $e) {
    echo "Erro: " . $e->getMessage();
}
