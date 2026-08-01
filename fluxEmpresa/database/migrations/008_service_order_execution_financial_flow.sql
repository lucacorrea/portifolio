-- Migration 008 - Fluxo operacional, estoque, financeiro e comprovante de OS.
-- Aplicacao manual na hospedagem, apos backup validado.
-- Ordem sugerida: 001 a 007 ja aplicadas, depois executar este arquivo uma unica vez.
-- Compatibilidade alvo: MariaDB/MySQL compartilhado, InnoDB, utf8mb4.
-- Observacao: nao remove colunas legadas de equipe em ordens_servico.

SET NAMES utf8mb4;

ALTER TABLE ordem_servico_itens
    ADD COLUMN IF NOT EXISTS origem ENUM('orcamento', 'manual', 'finalizacao') NOT NULL DEFAULT 'manual' AFTER tipo,
    ADD COLUMN IF NOT EXISTS orcamento_item_id INT UNSIGNED NULL AFTER referencia_id,
    ADD KEY IF NOT EXISTS idx_os_itens_origem (origem),
    ADD KEY IF NOT EXISTS idx_os_itens_orcamento_item (orcamento_item_id);

ALTER TABLE ordens_servico
    ADD COLUMN IF NOT EXISTS orcamento_liberado TINYINT(1) NOT NULL DEFAULT 0 AFTER orcamento_id,
    ADD COLUMN IF NOT EXISTS ordem_substituta_id INT UNSIGNED NULL AFTER orcamento_liberado,
    ADD COLUMN IF NOT EXISTS valor_aprovado_orcamento DECIMAL(12,2) NULL AFTER total,
    ADD KEY IF NOT EXISTS idx_os_orcamento_operacional (orcamento_id, status, orcamento_liberado),
    ADD KEY IF NOT EXISTS idx_os_substituta (ordem_substituta_id);

CREATE TABLE IF NOT EXISTS ordem_servico_funcionarios (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ordem_servico_id INT UNSIGNED NOT NULL,
    funcionario_id INT UNSIGNED NOT NULL,
    funcao VARCHAR(80) NOT NULL DEFAULT 'Técnico',
    principal TINYINT(1) NOT NULL DEFAULT 0,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    adicionado_por INT UNSIGNED NULL,
    adicionado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    removido_por INT UNSIGNED NULL,
    removido_em DATETIME NULL,
    UNIQUE KEY uq_os_funcionario (ordem_servico_id, funcionario_id),
    KEY idx_os_funcionarios_os (ordem_servico_id, ativo),
    KEY idx_os_funcionarios_funcionario (funcionario_id, ativo),
    KEY idx_os_funcionarios_principal (ordem_servico_id, principal, ativo),
    CONSTRAINT fk_os_funcionarios_os FOREIGN KEY (ordem_servico_id) REFERENCES ordens_servico(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_os_funcionarios_funcionario FOREIGN KEY (funcionario_id) REFERENCES funcionarios(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_os_funcionarios_add_user FOREIGN KEY (adicionado_por) REFERENCES usuarios(id) ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_os_funcionarios_remove_user FOREIGN KEY (removido_por) REFERENCES usuarios(id) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO ordem_servico_funcionarios (ordem_servico_id, funcionario_id, funcao, principal, ativo)
SELECT os.id, os.funcionario_principal_id, 'Responsável técnico', 1, 1
  FROM ordens_servico os
 WHERE os.funcionario_principal_id IS NOT NULL
   AND NOT EXISTS (
       SELECT 1 FROM ordem_servico_funcionarios osf
        WHERE osf.ordem_servico_id = os.id
          AND osf.funcionario_id = os.funcionario_principal_id
   );

INSERT INTO ordem_servico_funcionarios (ordem_servico_id, funcionario_id, funcao, principal, ativo)
SELECT os.id, os.funcionario_apoio_id, 'Técnico', 0, 1
  FROM ordens_servico os
 WHERE os.funcionario_apoio_id IS NOT NULL
   AND os.funcionario_apoio_id <> COALESCE(os.funcionario_principal_id, 0)
   AND NOT EXISTS (
       SELECT 1 FROM ordem_servico_funcionarios osf
        WHERE osf.ordem_servico_id = os.id
          AND osf.funcionario_id = os.funcionario_apoio_id
   );

CREATE TABLE IF NOT EXISTS ordem_servico_cancelamentos (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ordem_servico_id INT UNSIGNED NOT NULL,
    opcao ENUM('definitivo', 'liberar_orcamento', 'criar_substituta') NOT NULL,
    motivo VARCHAR(150) NOT NULL,
    observacao TEXT NULL,
    orcamento_liberado TINYINT(1) NOT NULL DEFAULT 0,
    ordem_substituta_id INT UNSIGNED NULL,
    cancelado_por INT UNSIGNED NOT NULL,
    cancelado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_os_cancelamentos_os (ordem_servico_id),
    KEY idx_os_cancelamentos_substituta (ordem_substituta_id),
    CONSTRAINT fk_os_cancelamentos_os FOREIGN KEY (ordem_servico_id) REFERENCES ordens_servico(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_os_cancelamentos_substituta FOREIGN KEY (ordem_substituta_id) REFERENCES ordens_servico(id) ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_os_cancelamentos_usuario FOREIGN KEY (cancelado_por) REFERENCES usuarios(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ordem_servico_finalizacoes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ordem_servico_id INT UNSIGNED NOT NULL,
    ativa TINYINT(1) NOT NULL DEFAULT 1,
    subtotal_servicos DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    subtotal_produtos DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    subtotal_outros DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    desconto DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    acrescimo DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    total_executado DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    observacao TEXT NULL,
    finalizado_por INT UNSIGNED NOT NULL,
    finalizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_os_finalizacoes_os (ordem_servico_id, ativa),
    CONSTRAINT fk_os_finalizacoes_os FOREIGN KEY (ordem_servico_id) REFERENCES ordens_servico(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_os_finalizacoes_usuario FOREIGN KEY (finalizado_por) REFERENCES usuarios(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ordem_servico_execucao_itens (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ordem_servico_id INT UNSIGNED NOT NULL,
    ordem_servico_item_id INT UNSIGNED NULL,
    tipo ENUM('servico', 'produto', 'outro') NOT NULL,
    referencia_id INT UNSIGNED NULL,
    descricao VARCHAR(255) NOT NULL,
    unidade VARCHAR(20) NOT NULL DEFAULT 'un',
    quantidade DECIMAL(12,3) NOT NULL DEFAULT 1.000,
    valor_unitario DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    desconto DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    subtotal DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    adicional TINYINT(1) NOT NULL DEFAULT 0,
    ordem INT UNSIGNED NOT NULL DEFAULT 0,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_os_execucao_itens_os (ordem_servico_id),
    KEY idx_os_execucao_itens_item (ordem_servico_item_id),
    KEY idx_os_execucao_itens_tipo (tipo),
    CONSTRAINT fk_os_execucao_itens_os FOREIGN KEY (ordem_servico_id) REFERENCES ordens_servico(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_os_execucao_itens_item FOREIGN KEY (ordem_servico_item_id) REFERENCES ordem_servico_itens(id) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS estoque_autorizacoes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ordem_servico_id INT UNSIGNED NOT NULL,
    produto_id INT UNSIGNED NOT NULL,
    quantidade_solicitada DECIMAL(12,3) NOT NULL,
    saldo_disponivel DECIMAL(12,3) NOT NULL,
    quantidade_excedente DECIMAL(12,3) NOT NULL,
    solicitado_por INT UNSIGNED NOT NULL,
    autorizado_por INT UNSIGNED NOT NULL,
    motivo VARCHAR(255) NOT NULL,
    autorizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_estoque_aut_os (ordem_servico_id),
    KEY idx_estoque_aut_produto (produto_id),
    CONSTRAINT fk_estoque_aut_os FOREIGN KEY (ordem_servico_id) REFERENCES ordens_servico(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_estoque_aut_produto FOREIGN KEY (produto_id) REFERENCES produtos(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_estoque_aut_solicitado FOREIGN KEY (solicitado_por) REFERENCES usuarios(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_estoque_aut_autorizado FOREIGN KEY (autorizado_por) REFERENCES usuarios(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS estoque_movimentacoes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    produto_id INT UNSIGNED NOT NULL,
    ordem_servico_id INT UNSIGNED NULL,
    tipo ENUM('entrada', 'saida_os', 'ajuste', 'estorno') NOT NULL,
    quantidade DECIMAL(12,3) NOT NULL,
    saldo_anterior DECIMAL(12,3) NOT NULL,
    saldo_posterior DECIMAL(12,3) NOT NULL,
    autorizacao_id INT UNSIGNED NULL,
    usuario_id INT UNSIGNED NOT NULL,
    observacao VARCHAR(255) NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_estoque_mov_produto (produto_id, criado_em),
    KEY idx_estoque_mov_os (ordem_servico_id),
    CONSTRAINT fk_estoque_mov_produto FOREIGN KEY (produto_id) REFERENCES produtos(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_estoque_mov_os FOREIGN KEY (ordem_servico_id) REFERENCES ordens_servico(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_estoque_mov_aut FOREIGN KEY (autorizacao_id) REFERENCES estoque_autorizacoes(id) ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_estoque_mov_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS caixa_movimentacoes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tipo ENUM('entrada', 'saida', 'estorno_entrada', 'estorno_saida') NOT NULL,
    origem_tipo VARCHAR(40) NOT NULL,
    origem_id INT UNSIGNED NULL,
    descricao VARCHAR(255) NOT NULL,
    forma_pagamento ENUM('dinheiro', 'pix', 'cartao_debito', 'cartao_credito', 'transferencia', 'outro') NULL,
    valor DECIMAL(12,2) NOT NULL,
    data_movimento DATETIME NOT NULL,
    usuario_id INT UNSIGNED NOT NULL,
    estornado_de_id INT UNSIGNED NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_caixa_data (data_movimento),
    KEY idx_caixa_origem (origem_tipo, origem_id),
    KEY idx_caixa_usuario (usuario_id),
    CONSTRAINT fk_caixa_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_caixa_estorno FOREIGN KEY (estornado_de_id) REFERENCES caixa_movimentacoes(id) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ordem_servico_pagamentos (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ordem_servico_id INT UNSIGNED NOT NULL,
    valor DECIMAL(12,2) NOT NULL,
    forma_pagamento ENUM('dinheiro', 'pix', 'cartao_debito', 'cartao_credito', 'transferencia', 'outro') NOT NULL,
    recebido_em DATETIME NOT NULL,
    observacao VARCHAR(255) NULL,
    status ENUM('ativo', 'estornado') NOT NULL DEFAULT 'ativo',
    registrado_por INT UNSIGNED NOT NULL,
    caixa_movimentacao_id INT UNSIGNED NULL,
    estornado_em DATETIME NULL,
    estornado_por INT UNSIGNED NULL,
    motivo_estorno VARCHAR(255) NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_os_pagamentos_os (ordem_servico_id, status),
    KEY idx_os_pagamentos_caixa (caixa_movimentacao_id),
    CONSTRAINT fk_os_pagamentos_os FOREIGN KEY (ordem_servico_id) REFERENCES ordens_servico(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_os_pagamentos_usuario FOREIGN KEY (registrado_por) REFERENCES usuarios(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_os_pagamentos_caixa FOREIGN KEY (caixa_movimentacao_id) REFERENCES caixa_movimentacoes(id) ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_os_pagamentos_estorno_usuario FOREIGN KEY (estornado_por) REFERENCES usuarios(id) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS contas_receber (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ordem_servico_id INT UNSIGNED NOT NULL,
    valor_total DECIMAL(12,2) NOT NULL,
    valor_recebido DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    saldo DECIMAL(12,2) NOT NULL,
    vencimento_em DATE NULL,
    proximo_lembrete_em DATE NULL,
    status ENUM('pendente', 'parcial', 'vencida', 'paga', 'cancelada') NOT NULL DEFAULT 'pendente',
    observacao TEXT NULL,
    criado_por INT UNSIGNED NOT NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_contas_receber_os (ordem_servico_id),
    KEY idx_contas_receber_status_vencimento (status, vencimento_em),
    KEY idx_contas_receber_lembrete (proximo_lembrete_em),
    CONSTRAINT fk_contas_receber_os FOREIGN KEY (ordem_servico_id) REFERENCES ordens_servico(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_contas_receber_usuario FOREIGN KEY (criado_por) REFERENCES usuarios(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS contas_receber_eventos (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    conta_receber_id INT UNSIGNED NOT NULL,
    tipo ENUM('criacao', 'pagamento', 'estorno', 'contato', 'whatsapp', 'lembrete', 'alteracao_vencimento', 'negociacao', 'observacao', 'quitacao') NOT NULL,
    descricao TEXT NOT NULL,
    valor DECIMAL(12,2) NULL,
    data_evento DATETIME NOT NULL,
    usuario_id INT UNSIGNED NOT NULL,
    metadados JSON NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_contas_eventos_conta (conta_receber_id, data_evento),
    CONSTRAINT fk_contas_eventos_conta FOREIGN KEY (conta_receber_id) REFERENCES contas_receber(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_contas_eventos_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS configuracoes_empresa (
    id TINYINT UNSIGNED NOT NULL PRIMARY KEY DEFAULT 1,
    razao_social VARCHAR(150) NULL,
    nome_fantasia VARCHAR(150) NULL,
    documento VARCHAR(30) NULL,
    telefone VARCHAR(30) NULL,
    endereco VARCHAR(255) NULL,
    logo VARCHAR(255) NULL,
    atualizado_por INT UNSIGNED NULL,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_config_empresa_usuario FOREIGN KEY (atualizado_por) REFERENCES usuarios(id) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO configuracoes_empresa (id) VALUES (1);

INSERT IGNORE INTO permissoes (grupo, modulo, codigo, nome, descricao, ordem) VALUES
('Estoque', 'estoque', 'estoque.autorizar_saldo_negativo', 'Autorizar saldo negativo de estoque', 'Permite autorizar baixa de produto acima do saldo disponível.', 860),
('Financeiro', 'contas_receber', 'contas_receber.visualizar', 'Visualizar contas a receber', 'Permite acessar a carteira de contas a receber.', 1570),
('Financeiro', 'contas_receber', 'contas_receber.registrar_pagamento', 'Registrar pagamento de conta', 'Permite registrar pagamentos posteriores de OS.', 1580),
('Financeiro', 'contas_receber', 'contas_receber.alterar_vencimento', 'Alterar vencimento de conta', 'Permite alterar vencimentos de contas a receber.', 1590),
('Financeiro', 'contas_receber', 'contas_receber.configurar_lembrete', 'Configurar lembrete de conta', 'Permite configurar lembretes de cobrança.', 1600),
('Financeiro', 'contas_receber', 'contas_receber.registrar_contato', 'Registrar contato de cobrança', 'Permite registrar contatos com clientes.', 1610),
('Financeiro', 'contas_receber', 'contas_receber.negociar', 'Registrar negociação de conta', 'Permite registrar negociações de saldo pendente.', 1620),
('Financeiro', 'contas_receber', 'contas_receber.estornar_pagamento', 'Estornar pagamento de conta', 'Permite estornar pagamentos preservando histórico.', 1630),
('Ordens de Serviço', 'os', 'os.emitir_comprovante', 'Emitir comprovante de OS', 'Permite emitir comprovante não fiscal de serviço.', 330),
('Ordens de Serviço', 'os', 'os.finalizar_com_pagamento', 'Finalizar OS com pagamento', 'Permite finalizar OS registrando pagamento e saldo pendente.', 340);

INSERT IGNORE INTO perfil_permissoes (perfil_id, permissao_id)
SELECT p.id, pe.id
  FROM perfis p
  JOIN permissoes pe
 WHERE p.nome IN ('Administrador', 'Dono', 'Gerente')
   AND pe.codigo IN (
       'estoque.autorizar_saldo_negativo',
       'contas_receber.visualizar',
       'contas_receber.registrar_pagamento',
       'contas_receber.alterar_vencimento',
       'contas_receber.configurar_lembrete',
       'contas_receber.registrar_contato',
       'contas_receber.negociar',
       'contas_receber.estornar_pagamento',
       'os.emitir_comprovante',
       'os.finalizar_com_pagamento'
   );
