-- Compatibilidade para o dump u784961086_empresa de 31/07/2026.
-- Execute UMA vez após backup e antes de: php scripts/migrate.php
-- Não recria tabelas nem apaga dados. Exige que o banco esteja selecionado.
SET NAMES utf8mb4;

ALTER TABLE ordens_servico
    ADD COLUMN IF NOT EXISTS excluida_em DATETIME NULL,
    ADD COLUMN IF NOT EXISTS excluida_por INT UNSIGNED NULL,
    ADD COLUMN IF NOT EXISTS motivo_exclusao VARCHAR(255) NULL,
    ADD KEY IF NOT EXISTS idx_os_exclusao (excluida_em);

ALTER TABLE ordem_servico_finalizacoes
    ADD COLUMN IF NOT EXISTS status_origem ENUM('agendada','em_execucao','aguardando_peca') NOT NULL DEFAULT 'em_execucao',
    ADD COLUMN IF NOT EXISTS estornado_por INT UNSIGNED NULL,
    ADD COLUMN IF NOT EXISTS estornado_em DATETIME NULL,
    ADD COLUMN IF NOT EXISTS motivo_estorno VARCHAR(255) NULL,
    ADD COLUMN IF NOT EXISTS finalizacao_ativa_chave INT UNSIGNED NULL,
    ADD KEY IF NOT EXISTS idx_os_finalizacoes_estorno (estornado_em);

-- Se esta atualização falhar no índice único, corrija as duplicidades retornadas
-- pela consulta abaixo antes de repetir. Nunca apague histórico para contornar isso.
SELECT ordem_servico_id, COUNT(*) AS finalizacoes_ativas
  FROM ordem_servico_finalizacoes
 WHERE ativa = 1
 GROUP BY ordem_servico_id
HAVING COUNT(*) > 1;

UPDATE ordem_servico_finalizacoes
   SET finalizacao_ativa_chave = CASE WHEN ativa = 1 THEN ordem_servico_id ELSE NULL END;

ALTER TABLE ordem_servico_finalizacoes
    ADD UNIQUE KEY IF NOT EXISTS uq_os_finalizacao_ativa (finalizacao_ativa_chave);

DROP TRIGGER IF EXISTS trg_os_finalizacoes_bi_chave_ativa;
CREATE TRIGGER trg_os_finalizacoes_bi_chave_ativa
BEFORE INSERT ON ordem_servico_finalizacoes
FOR EACH ROW SET NEW.finalizacao_ativa_chave = IF(NEW.ativa = 1, NEW.ordem_servico_id, NULL);

DROP TRIGGER IF EXISTS trg_os_finalizacoes_bu_chave_ativa;
CREATE TRIGGER trg_os_finalizacoes_bu_chave_ativa
BEFORE UPDATE ON ordem_servico_finalizacoes
FOR EACH ROW SET NEW.finalizacao_ativa_chave = IF(NEW.ativa = 1, NEW.ordem_servico_id, NULL);

ALTER TABLE ordem_servico_execucao_itens
    ADD COLUMN IF NOT EXISTS finalizacao_id INT UNSIGNED NULL,
    ADD KEY IF NOT EXISTS idx_os_execucao_finalizacao (finalizacao_id);

UPDATE ordem_servico_execucao_itens item
JOIN ordem_servico_finalizacoes finalizacao
  ON finalizacao.ordem_servico_id = item.ordem_servico_id AND finalizacao.ativa = 1
   SET item.finalizacao_id = finalizacao.id
 WHERE item.finalizacao_id IS NULL;

ALTER TABLE estoque_movimentacoes
    ADD COLUMN IF NOT EXISTS estornado_de_id INT UNSIGNED NULL,
    ADD UNIQUE KEY IF NOT EXISTS uq_estoque_estornado_de (estornado_de_id);

ALTER TABLE caixa_movimentacoes
    ADD UNIQUE KEY IF NOT EXISTS uq_caixa_estornado_de (estornado_de_id);

ALTER TABLE recibos
    ADD COLUMN IF NOT EXISTS cliente_nome VARCHAR(150) NULL,
    ADD COLUMN IF NOT EXISTS cliente_documento VARCHAR(20) NULL,
    ADD COLUMN IF NOT EXISTS os_numero VARCHAR(20) NULL,
    ADD COLUMN IF NOT EXISTS pagamento_recebido_em DATETIME NULL,
    ADD COLUMN IF NOT EXISTS empresa_nome VARCHAR(150) NULL,
    ADD COLUMN IF NOT EXISTS empresa_documento VARCHAR(30) NULL,
    ADD COLUMN IF NOT EXISTS empresa_telefone VARCHAR(30) NULL,
    ADD COLUMN IF NOT EXISTS empresa_endereco VARCHAR(255) NULL,
    ADD COLUMN IF NOT EXISTS empresa_logo VARCHAR(255) NULL,
    ADD UNIQUE KEY IF NOT EXISTS uq_recibos_pagamento (pagamento_id);

SET @fk_exists := (SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'fk_os_finalizacoes_estorno_usuario');
SET @sql := IF(@fk_exists = 0, 'ALTER TABLE ordem_servico_finalizacoes ADD CONSTRAINT fk_os_finalizacoes_estorno_usuario FOREIGN KEY (estornado_por) REFERENCES usuarios(id) ON UPDATE CASCADE ON DELETE SET NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_exists := (SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'fk_os_execucao_finalizacao');
SET @sql := IF(@fk_exists = 0, 'ALTER TABLE ordem_servico_execucao_itens ADD CONSTRAINT fk_os_execucao_finalizacao FOREIGN KEY (finalizacao_id) REFERENCES ordem_servico_finalizacoes(id) ON UPDATE CASCADE ON DELETE RESTRICT', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_exists := (SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'fk_estoque_estornado_de');
SET @sql := IF(@fk_exists = 0, 'ALTER TABLE estoque_movimentacoes ADD CONSTRAINT fk_estoque_estornado_de FOREIGN KEY (estornado_de_id) REFERENCES estoque_movimentacoes(id) ON UPDATE CASCADE ON DELETE SET NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_exists := (SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'fk_recibos_pagamento');
SET @sql := IF(@fk_exists = 0, 'ALTER TABLE recibos ADD CONSTRAINT fk_recibos_pagamento FOREIGN KEY (pagamento_id) REFERENCES ordem_servico_pagamentos(id) ON UPDATE CASCADE ON DELETE RESTRICT', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

INSERT IGNORE INTO permissoes (grupo, modulo, codigo, nome, descricao, ordem)
VALUES ('Ordens de Serviço','os','os.estornar','Estornar ordens de serviço','Permite desfazer uma finalização preservando o histórico.',305);
INSERT IGNORE INTO perfil_permissoes (perfil_id, permissao_id)
SELECT perfil.id, permissao.id FROM perfis perfil JOIN permissoes permissao
 WHERE perfil.nome IN ('Administrador','Dono','Gerente') AND permissao.codigo = 'os.estornar';
