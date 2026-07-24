-- Migration 011 - Estorno transacional, exclusao logica de OS e recibos de pagamento.
-- Reentrante para aplicação automática depois de 010_operational_finance_completion.sql.
-- Compatibilidade alvo: MariaDB 10.4, InnoDB, utf8mb4.

SET NAMES utf8mb4;

ALTER TABLE ordens_servico
    ADD COLUMN IF NOT EXISTS excluida_em DATETIME NULL AFTER cancelada_em,
    ADD COLUMN IF NOT EXISTS excluida_por INT UNSIGNED NULL AFTER excluida_em,
    ADD COLUMN IF NOT EXISTS motivo_exclusao VARCHAR(255) NULL AFTER excluida_por,
    ADD KEY IF NOT EXISTS idx_os_exclusao (excluida_em);

SET @fk_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'fk_os_exclusao_usuario');
SET @sql := IF(@fk_exists = 0, 'ALTER TABLE ordens_servico ADD CONSTRAINT fk_os_exclusao_usuario FOREIGN KEY (excluida_por) REFERENCES usuarios(id) ON UPDATE CASCADE ON DELETE SET NULL', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

ALTER TABLE ordem_servico_finalizacoes
    ADD COLUMN IF NOT EXISTS status_origem ENUM('agendada', 'em_execucao', 'aguardando_peca') NOT NULL DEFAULT 'em_execucao' AFTER ativa,
    ADD COLUMN IF NOT EXISTS estornado_por INT UNSIGNED NULL AFTER finalizado_em,
    ADD COLUMN IF NOT EXISTS estornado_em DATETIME NULL AFTER estornado_por,
    ADD COLUMN IF NOT EXISTS motivo_estorno VARCHAR(255) NULL AFTER estornado_em,
    ADD COLUMN IF NOT EXISTS finalizacao_ativa_chave INT UNSIGNED
        GENERATED ALWAYS AS (CASE WHEN ativa = 1 THEN ordem_servico_id ELSE NULL END) PERSISTENT,
    ADD UNIQUE KEY IF NOT EXISTS uq_os_finalizacao_ativa (finalizacao_ativa_chave),
    ADD KEY IF NOT EXISTS idx_os_finalizacoes_estorno (estornado_em);

SET @fk_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'fk_os_finalizacoes_estorno_usuario');
SET @sql := IF(@fk_exists = 0, 'ALTER TABLE ordem_servico_finalizacoes ADD CONSTRAINT fk_os_finalizacoes_estorno_usuario FOREIGN KEY (estornado_por) REFERENCES usuarios(id) ON UPDATE CASCADE ON DELETE SET NULL', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

ALTER TABLE ordem_servico_execucao_itens
    ADD COLUMN IF NOT EXISTS finalizacao_id INT UNSIGNED NULL AFTER ordem_servico_id,
    ADD KEY IF NOT EXISTS idx_os_execucao_finalizacao (finalizacao_id);

SET @fk_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'fk_os_execucao_finalizacao');
SET @sql := IF(@fk_exists = 0, 'ALTER TABLE ordem_servico_execucao_itens ADD CONSTRAINT fk_os_execucao_finalizacao FOREIGN KEY (finalizacao_id) REFERENCES ordem_servico_finalizacoes(id) ON UPDATE CASCADE ON DELETE RESTRICT', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

UPDATE ordem_servico_execucao_itens item
JOIN ordem_servico_finalizacoes finalizacao
  ON finalizacao.ordem_servico_id = item.ordem_servico_id
 AND finalizacao.ativa = 1
SET item.finalizacao_id = finalizacao.id
WHERE item.finalizacao_id IS NULL;

ALTER TABLE estoque_movimentacoes
    ADD COLUMN IF NOT EXISTS estornado_de_id INT UNSIGNED NULL AFTER autorizacao_id,
    ADD UNIQUE KEY IF NOT EXISTS uq_estoque_estornado_de (estornado_de_id);

SET @fk_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'fk_estoque_estornado_de');
SET @sql := IF(@fk_exists = 0, 'ALTER TABLE estoque_movimentacoes ADD CONSTRAINT fk_estoque_estornado_de FOREIGN KEY (estornado_de_id) REFERENCES estoque_movimentacoes(id) ON UPDATE CASCADE ON DELETE SET NULL', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

ALTER TABLE caixa_movimentacoes
    ADD UNIQUE KEY IF NOT EXISTS uq_caixa_estornado_de (estornado_de_id);

ALTER TABLE recibos
    ADD COLUMN IF NOT EXISTS cliente_nome VARCHAR(150) NULL AFTER pagamento_id,
    ADD COLUMN IF NOT EXISTS cliente_documento VARCHAR(20) NULL AFTER cliente_nome,
    ADD COLUMN IF NOT EXISTS os_numero VARCHAR(20) NULL AFTER cliente_documento,
    ADD COLUMN IF NOT EXISTS pagamento_recebido_em DATETIME NULL AFTER os_numero,
    ADD COLUMN IF NOT EXISTS empresa_nome VARCHAR(150) NULL AFTER pagamento_recebido_em,
    ADD COLUMN IF NOT EXISTS empresa_documento VARCHAR(30) NULL AFTER empresa_nome,
    ADD COLUMN IF NOT EXISTS empresa_telefone VARCHAR(30) NULL AFTER empresa_documento,
    ADD COLUMN IF NOT EXISTS empresa_endereco VARCHAR(255) NULL AFTER empresa_telefone,
    ADD COLUMN IF NOT EXISTS empresa_logo VARCHAR(255) NULL AFTER empresa_endereco,
    ADD UNIQUE KEY IF NOT EXISTS uq_recibos_pagamento (pagamento_id);

SET @fk_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'fk_recibos_pagamento');
SET @sql := IF(@fk_exists = 0, 'ALTER TABLE recibos ADD CONSTRAINT fk_recibos_pagamento FOREIGN KEY (pagamento_id) REFERENCES ordem_servico_pagamentos(id) ON UPDATE CASCADE ON DELETE RESTRICT', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

INSERT IGNORE INTO permissoes (grupo, modulo, codigo, nome, descricao, ordem) VALUES
('Ordens de Serviço', 'os', 'os.estornar', 'Estornar ordens de serviço', 'Permite desfazer uma finalização compensando estoque, caixa, pagamentos e contas a receber.', 305);

INSERT IGNORE INTO perfil_permissoes (perfil_id, permissao_id)
SELECT perfil.id, permissao.id
  FROM perfis perfil
  JOIN permissoes permissao
 WHERE perfil.nome IN ('Administrador', 'Dono', 'Gerente')
   AND permissao.codigo = 'os.estornar';
