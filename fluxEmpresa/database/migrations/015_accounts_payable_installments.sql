-- Migration 015 - Parcelamento e quitacao auditavel de contas a pagar.
-- Compatibilidade alvo: MariaDB 10.4 compartilhado, InnoDB, utf8mb4.

SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

ALTER TABLE contas_pagar
    ADD COLUMN IF NOT EXISTS tipo_pagamento ENUM('avista', 'parcelado') NOT NULL DEFAULT 'avista' AFTER valor,
    ADD COLUMN IF NOT EXISTS quantidade_parcelas SMALLINT UNSIGNED NOT NULL DEFAULT 1 AFTER tipo_pagamento,
    ADD COLUMN IF NOT EXISTS forma_pagamento ENUM('dinheiro', 'pix', 'boleto', 'cartao_credito', 'cartao_debito', 'transferencia', 'cheque', 'outro') NOT NULL DEFAULT 'outro' AFTER quantidade_parcelas;

ALTER TABLE contas_pagar
    MODIFY COLUMN status ENUM('pendente', 'parcial', 'paga', 'cancelada') NOT NULL DEFAULT 'pendente';

ALTER TABLE caixa_movimentacoes
    MODIFY COLUMN forma_pagamento ENUM('dinheiro', 'pix', 'boleto', 'cartao_credito', 'cartao_debito', 'transferencia', 'cheque', 'outro') NULL;

CREATE TABLE IF NOT EXISTS contas_pagar_parcelas (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    conta_pagar_id INT UNSIGNED NOT NULL,
    numero SMALLINT UNSIGNED NOT NULL,
    vencimento_em DATE NOT NULL,
    valor DECIMAL(12,2) NOT NULL,
    status ENUM('pendente', 'paga', 'cancelada') NOT NULL DEFAULT 'pendente',
    quitada_em DATETIME NULL,
    quitada_por INT UNSIGNED NULL,
    forma_pagamento_quitacao ENUM('dinheiro', 'pix', 'boleto', 'cartao_credito', 'cartao_debito', 'transferencia', 'cheque', 'outro') NULL,
    caixa_movimentacao_id INT UNSIGNED NULL,
    criada_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizada_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_conta_pagar_parcela_numero (conta_pagar_id, numero),
    KEY idx_conta_pagar_parcela_status_vencimento (status, vencimento_em),
    KEY idx_conta_pagar_parcela_quitada_por (quitada_por),
    KEY idx_conta_pagar_parcela_caixa (caixa_movimentacao_id),
    CONSTRAINT fk_conta_pagar_parcela_conta FOREIGN KEY (conta_pagar_id) REFERENCES contas_pagar(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_conta_pagar_parcela_quitada_usuario FOREIGN KEY (quitada_por) REFERENCES usuarios(id) ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_conta_pagar_parcela_caixa FOREIGN KEY (caixa_movimentacao_id) REFERENCES caixa_movimentacoes(id) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS contas_pagar_parcela_eventos (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    parcela_id INT UNSIGNED NOT NULL,
    tipo ENUM('quitacao', 'estorno') NOT NULL,
    forma_pagamento ENUM('dinheiro', 'pix', 'boleto', 'cartao_credito', 'cartao_debito', 'transferencia', 'cheque', 'outro') NULL,
    observacao VARCHAR(255) NULL,
    usuario_id INT UNSIGNED NOT NULL,
    caixa_movimentacao_id INT UNSIGNED NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_conta_pagar_evento_parcela_data (parcela_id, criado_em),
    KEY idx_conta_pagar_evento_usuario (usuario_id),
    KEY idx_conta_pagar_evento_caixa (caixa_movimentacao_id),
    CONSTRAINT fk_conta_pagar_evento_parcela FOREIGN KEY (parcela_id) REFERENCES contas_pagar_parcelas(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_conta_pagar_evento_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_conta_pagar_evento_caixa FOREIGN KEY (caixa_movimentacao_id) REFERENCES caixa_movimentacoes(id) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO contas_pagar_parcelas
    (conta_pagar_id, numero, vencimento_em, valor, status, quitada_em)
SELECT id, 1, vencimento_em, valor,
       CASE status WHEN 'paga' THEN 'paga' WHEN 'cancelada' THEN 'cancelada' ELSE 'pendente' END,
       CASE WHEN status = 'paga' THEN atualizado_em ELSE NULL END
  FROM contas_pagar;

INSERT INTO permissoes (grupo, modulo, codigo, nome, descricao, ordem, status) VALUES
('Financeiro', 'contas_pagar', 'contas_pagar.quitar', 'Quitar parcelas a pagar', 'Permite registrar a quitação individual de parcelas.', 1565, 'ativo'),
('Financeiro', 'contas_pagar', 'contas_pagar.estornar_pagamento', 'Estornar quitação a pagar', 'Permite estornar quitações preservando o histórico.', 1566, 'ativo')
ON DUPLICATE KEY UPDATE nome = VALUES(nome), descricao = VALUES(descricao), ordem = VALUES(ordem), status = VALUES(status);

INSERT IGNORE INTO perfil_permissoes (perfil_id, permissao_id)
SELECT perfil.id, permissao.id
  FROM perfis perfil
  JOIN permissoes permissao ON permissao.codigo IN ('contas_pagar.quitar', 'contas_pagar.estornar_pagamento')
 WHERE perfil.nome IN ('Administrador', 'Dono', 'Gerente');
