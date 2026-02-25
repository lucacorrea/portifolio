-- Migration 007: RBAC Advanced & Super Admin Support

-- 1. Add maximum discount and master level support to usuarios
ALTER TABLE usuarios MODIFY COLUMN nivel ENUM('vendedor', 'tecnico', 'gerente', 'admin', 'master') DEFAULT 'vendedor';
ALTER TABLE usuarios ADD COLUMN IF NOT EXISTS desconto_maximo DECIMAL(5,2) DEFAULT 0.00;

-- 2. Create permissions table
CREATE TABLE IF NOT EXISTS permissoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    modulo VARCHAR(50) NOT NULL,
    acao VARCHAR(50) NOT NULL,
    descricao VARCHAR(255),
    UNIQUE KEY uk_modulo_acao (modulo, acao)
);

-- 3. Create level permissions bridge table
CREATE TABLE IF NOT EXISTS permissao_nivel (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nivel ENUM('vendedor', 'tecnico', 'gerente', 'admin', 'master') NOT NULL,
    permissao_id INT NOT NULL,
    FOREIGN KEY (permissao_id) REFERENCES permissoes(id) ON DELETE CASCADE
);

-- 4. Initial seed for core permissions
INSERT IGNORE INTO permissoes (modulo, acao, descricao) VALUES 
('vendas', 'visualizar', 'Ver lista de vendas'),
('vendas', 'criar', 'Realizar novas vendas'),
('vendas', 'cancelar', 'Cancelar vendas realizadas'),
('fiscal', 'emitir_nota', 'Emitir NFC-e/NF-e'),
('fiscal', 'configurar', 'Alterar configurações SEFAZ'),
('estoque', 'visualizar', 'Ver catálogo de produtos'),
('estoque', 'editar', 'Editar informações de produtos'),
('clientes', 'gerenciar', 'Cadastrar e editar clientes'),
('financeiro', 'dre', 'Visualizar DRE'),
('configuracoes', 'master', 'Acesso ao Painel Global Master');

-- 5. Default permissions for 'vendedor' (example)
SET @perm_venda_view = (SELECT id FROM permissoes WHERE modulo = 'vendas' AND acao = 'visualizar');
SET @perm_venda_create = (SELECT id FROM permissoes WHERE modulo = 'vendas' AND acao = 'criar');
INSERT IGNORE INTO permissao_nivel (nivel, permissao_id) VALUES 
('vendedor', @perm_venda_view),
('vendedor', @perm_venda_create);

-- 6. Elevate default admin to master (optional but recommended for setup)
UPDATE usuarios SET nivel = 'master' WHERE email = 'admin@erp.com';
