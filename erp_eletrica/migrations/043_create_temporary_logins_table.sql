-- Migration 043: Create logins_temporarios table
CREATE TABLE IF NOT EXISTS logins_temporarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_aleatorio VARCHAR(50) UNIQUE NOT NULL,
    senha_hash VARCHAR(255) NOT NULL,
    admin_criador_id INT NOT NULL,
    filial_id INT NOT NULL,
    validade DATETIME NOT NULL,
    status ENUM('ativo', 'utilizado', 'expirado', 'invalidado') DEFAULT 'ativo',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_criador_id) REFERENCES usuarios(id),
    FOREIGN KEY (filial_id) REFERENCES filiais(id)
);
