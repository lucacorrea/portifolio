-- Migration 019 - Pagamento idempotente de OS e reparo de permissoes operacionais.
-- Compatibilidade alvo: MariaDB 10.4, InnoDB, utf8mb4.

SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

ALTER TABLE ordem_servico_pagamentos
    ADD COLUMN IF NOT EXISTS payment_token VARCHAR(64) NULL AFTER caixa_movimentacao_id,
    ADD UNIQUE KEY IF NOT EXISTS uq_os_pagamento_token (payment_token);

ALTER TABLE ordem_servico_finalizacoes
    ADD COLUMN IF NOT EXISTS subtotal_servicos_origem DECIMAL(12,2) NULL AFTER status_origem,
    ADD COLUMN IF NOT EXISTS subtotal_produtos_origem DECIMAL(12,2) NULL AFTER subtotal_servicos_origem,
    ADD COLUMN IF NOT EXISTS subtotal_outros_origem DECIMAL(12,2) NULL AFTER subtotal_produtos_origem,
    ADD COLUMN IF NOT EXISTS desconto_origem DECIMAL(12,2) NULL AFTER subtotal_outros_origem,
    ADD COLUMN IF NOT EXISTS acrescimo_origem DECIMAL(12,2) NULL AFTER desconto_origem,
    ADD COLUMN IF NOT EXISTS total_origem DECIMAL(12,2) NULL AFTER acrescimo_origem;

INSERT INTO permissoes (grupo, modulo, codigo, nome, descricao, ordem, status) VALUES
('Ordens de Serviço', 'os', 'os.estornar', 'Estornar ordens de serviço', 'Permite desfazer a finalização compensando estoque, caixa, pagamentos e contas a receber.', 305, 'ativo'),
('Ordens de Serviço', 'os', 'os.excluir', 'Excluir ordens de serviço', 'Permite excluir logicamente ordens de serviço sem apagar o histórico.', 310, 'ativo'),
('Financeiro', 'contas_receber', 'contas_receber.registrar_pagamento', 'Registrar pagamento de conta', 'Permite registrar pagamentos posteriores de OS.', 1580, 'ativo'),
('Fiscal', 'recibo', 'recibo.emitir', 'Emitir recibos', 'Permite emitir recibos vinculados a pagamentos ou avulsos.', 1650, 'ativo')
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
  JOIN permissoes permissao
    ON permissao.codigo IN (
        'os.estornar', 'os.excluir',
        'contas_receber.registrar_pagamento', 'recibo.emitir'
    )
 WHERE perfil.nome IN ('Administrador', 'Dono', 'Gerente')
   AND permissao.status = 'ativo';
