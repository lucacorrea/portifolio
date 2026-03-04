-- Migration 008: Performance & Security Hardening

-- 1. Intelligent Indexing for multi-tenant and reporting
ALTER TABLE vendas ADD INDEX idx_vendas_filial_data (filial_id, data_venda);
ALTER TABLE vendas ADD INDEX idx_vendas_status (status);
ALTER TABLE produtos ADD INDEX idx_produtos_filial_nome (filial_id, nome);
ALTER TABLE clientes ADD INDEX idx_clientes_filial_cpf (filial_id, cpf_cnpj);
ALTER TABLE os ADD INDEX idx_os_filial_status (filial_id, status);

-- 2. Access Logging Table
CREATE TABLE IF NOT EXISTS logs_acesso (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT,
    email_tentativa VARCHAR(255),
    ip_address VARCHAR(45),
    user_agent TEXT,
    sucesso TINYINT(1) DEFAULT 0,
    motivo VARCHAR(255),
    data_tentativa TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
);

-- 3. CSRF Protection (Optional: if we want to store tokens in DB, but session is better)
-- For this MVP we will use Session-based CSRF for performance.
