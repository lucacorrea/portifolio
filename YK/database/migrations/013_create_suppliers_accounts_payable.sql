-- Migration 013 - Cadastro de fornecedores e contas a pagar manuais.
-- Escopo inicial sem baixa financeira; pagamentos serao tratados em etapa propria.
-- Compatibilidade alvo: MariaDB 10.4 compartilhado, InnoDB, utf8mb4.

SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS fornecedores (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    codigo VARCHAR(20) NULL,
    tipo_pessoa ENUM('fisica', 'juridica') NOT NULL DEFAULT 'juridica',
    nome VARCHAR(150) NOT NULL,
    nome_fantasia VARCHAR(150) NULL,
    documento VARCHAR(20) NULL,
    inscricao_estadual VARCHAR(30) NULL,
    contato VARCHAR(120) NULL,
    telefone VARCHAR(30) NULL,
    whatsapp VARCHAR(30) NULL,
    email VARCHAR(150) NULL,
    cep VARCHAR(10) NULL,
    endereco VARCHAR(180) NULL,
    numero VARCHAR(20) NULL,
    complemento VARCHAR(100) NULL,
    bairro VARCHAR(100) NULL,
    cidade VARCHAR(100) NULL,
    estado CHAR(2) NULL,
    observacao TEXT NULL,
    status ENUM('ativo', 'inativo') NOT NULL DEFAULT 'ativo',
    criado_por INT UNSIGNED NOT NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_fornecedores_codigo (codigo),
    UNIQUE KEY uq_fornecedores_documento (documento),
    KEY idx_fornecedores_nome (nome),
    KEY idx_fornecedores_status (status),
    KEY idx_fornecedores_criado_por (criado_por),
    CONSTRAINT fk_fornecedores_criado_por FOREIGN KEY (criado_por) REFERENCES usuarios(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS contas_pagar (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    codigo VARCHAR(20) NULL,
    fornecedor_id INT UNSIGNED NOT NULL,
    descricao VARCHAR(255) NOT NULL,
    documento VARCHAR(80) NULL,
    data_emissao DATE NULL,
    vencimento_em DATE NOT NULL,
    valor DECIMAL(12,2) NOT NULL,
    status ENUM('pendente', 'paga', 'cancelada') NOT NULL DEFAULT 'pendente',
    observacao TEXT NULL,
    criado_por INT UNSIGNED NOT NULL,
    cancelada_em DATETIME NULL,
    cancelada_por INT UNSIGNED NULL,
    motivo_cancelamento VARCHAR(255) NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_contas_pagar_codigo (codigo),
    UNIQUE KEY uq_contas_pagar_fornecedor_documento (fornecedor_id, documento),
    KEY idx_contas_pagar_fornecedor_status (fornecedor_id, status),
    KEY idx_contas_pagar_status_vencimento (status, vencimento_em),
    KEY idx_contas_pagar_vencimento (vencimento_em),
    KEY idx_contas_pagar_criado_por (criado_por),
    KEY idx_contas_pagar_cancelada_por (cancelada_por),
    CONSTRAINT fk_contas_pagar_fornecedor FOREIGN KEY (fornecedor_id) REFERENCES fornecedores(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_contas_pagar_criado_por FOREIGN KEY (criado_por) REFERENCES usuarios(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_contas_pagar_cancelada_por FOREIGN KEY (cancelada_por) REFERENCES usuarios(id) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO permissoes (grupo, modulo, codigo, nome, descricao, ordem, status) VALUES
('Fornecedores', 'fornecedor', 'fornecedor.visualizar', 'Visualizar fornecedores', 'Permite acessar fornecedores.', 1110, 'ativo'),
('Fornecedores', 'fornecedor', 'fornecedor.criar', 'Criar fornecedores', 'Permite cadastrar fornecedores.', 1120, 'ativo'),
('Fornecedores', 'fornecedor', 'fornecedor.editar', 'Editar fornecedores', 'Permite alterar fornecedores.', 1130, 'ativo'),
('Fornecedores', 'fornecedor', 'fornecedor.desativar', 'Desativar fornecedores', 'Permite inativar ou reativar fornecedores.', 1140, 'ativo'),
('Financeiro', 'contas_pagar', 'contas_pagar.visualizar', 'Visualizar contas a pagar', 'Permite acessar a carteira de contas a pagar.', 1561, 'ativo'),
('Financeiro', 'contas_pagar', 'contas_pagar.criar', 'Criar contas a pagar', 'Permite inserir manualmente contas de fornecedores.', 1562, 'ativo'),
('Financeiro', 'contas_pagar', 'contas_pagar.editar', 'Editar contas a pagar', 'Permite alterar contas a pagar pendentes.', 1563, 'ativo'),
('Financeiro', 'contas_pagar', 'contas_pagar.cancelar', 'Cancelar contas a pagar', 'Permite cancelar contas a pagar preservando a auditoria.', 1564, 'ativo')
ON DUPLICATE KEY UPDATE
    grupo = VALUES(grupo),
    modulo = VALUES(modulo),
    nome = VALUES(nome),
    descricao = VALUES(descricao),
    ordem = VALUES(ordem),
    status = VALUES(status);

INSERT IGNORE INTO perfil_permissoes (perfil_id, permissao_id)
SELECT perfil.id, permissao.id
FROM perfis perfil
INNER JOIN permissoes permissao ON permissao.codigo IN (
    'fornecedor.visualizar',
    'fornecedor.criar',
    'fornecedor.editar',
    'fornecedor.desativar',
    'contas_pagar.visualizar',
    'contas_pagar.criar',
    'contas_pagar.editar',
    'contas_pagar.cancelar'
)
WHERE perfil.nome IN ('Administrador', 'Dono', 'Gerente');
