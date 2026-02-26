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

-- 4. Initial seed for core permissions (Granular)
INSERT IGNORE INTO permissoes (modulo, acao, descricao) VALUES 
-- Vendas
('vendas', 'visualizar', 'Consultar histórico de vendas'),
('vendas', 'criar', 'Realizar novos pedidos/vendas'),
('vendas', 'editar', 'Alterar dados de vendas não finalizadas'),
('vendas', 'excluir', 'Excluir registros de vendas (Estorno)'),
-- Clientes
('clientes', 'visualizar', 'Ver base de clientes'),
('clientes', 'criar', 'Cadastrar novos clientes'),
('clientes', 'editar', 'Alterar dados de clientes'),
('clientes', 'excluir', 'Remover clientes do sistema'),
-- Estoque / Produtos
('estoque', 'visualizar', 'Consultar catálogo e estoques'),
('estoque', 'criar', 'Cadastrar novos produtos'),
('estoque', 'editar', 'Ajustar preços e dados técnicos'),
('estoque', 'excluir', 'Remover produtos do catálogo'),
-- Financeiro
('financeiro', 'visualizar', 'Ver fluxo de caixa'),
('financeiro', 'gerenciar', 'Lançar pagamentos e recebimentos'),
('financeiro', 'dre', 'Acesso a relatórios de ROI e DRE'),
-- Fiscal
('fiscal', 'emitir_nota', 'Gerar e transmitir NFC-e/NF-e'),
('fiscal', 'configurar', 'Acessar certificados e tokens SEFAZ'),
-- Equipe / Usuarios
('usuarios', 'visualizar', 'Ver lista de colaboradores'),
('usuarios', 'gerenciar', 'Criar, editar e bloquear operadores'),
-- Ordem de Serviço (OS)
('os', 'visualizar', 'Consultar ordens de serviço'),
('os', 'criar', 'Abrir novas ordens de serviço'),
('os', 'editar', 'Atualizar laudos e peças em OS'),
('os', 'excluir', 'Remover registros de OS'),
-- Fornecedores
('fornecedores', 'visualizar', 'Ver base de fornecedores'),
('fornecedores', 'gerenciar', 'Cadastrar e editar fornecedores'),
-- Compras
('compras', 'visualizar', 'Ver histórico de compras'),
('compras', 'gerenciar', 'Lançar novas compras e entradas'),
-- Configurações
('configuracoes', 'geral', 'Alterar dados da empresa e sistema');

-- 5. Default permissions for 'vendedor' (example)
SET @perm_venda_view = (SELECT id FROM permissoes WHERE modulo = 'vendas' AND acao = 'visualizar');
SET @perm_venda_create = (SELECT id FROM permissoes WHERE modulo = 'vendas' AND acao = 'criar');
INSERT IGNORE INTO permissao_nivel (nivel, permissao_id) VALUES 
('vendedor', @perm_venda_view),
('vendedor', @perm_venda_create);

-- 6. Elevate default admin to master (optional but recommended for setup)
UPDATE usuarios SET nivel = 'master' WHERE email = 'admin@erp.com';
