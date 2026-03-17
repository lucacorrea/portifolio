-- Migration 016: Create autorizacoes_temporarias
CREATE TABLE IF NOT EXISTS autorizacoes_temporarias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tipo ENUM('desconto', 'sangria') NOT NULL,
    codigo VARCHAR(10) NOT NULL,
    usuario_autorizador_id INT NULL,
    validade DATETIME NOT NULL,
    utilizado BOOLEAN DEFAULT 0,
    filial_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
