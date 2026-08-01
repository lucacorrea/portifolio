-- Migration 022 - Exclusao logica e auditada de clientes, orcamentos e servicos.
-- Preserva documentos operacionais e historicos sem exigir motivo de exclusao.

SET NAMES utf8mb4;

ALTER TABLE clientes
    ADD COLUMN IF NOT EXISTS excluido_em DATETIME NULL AFTER status,
    ADD COLUMN IF NOT EXISTS excluido_por INT UNSIGNED NULL AFTER excluido_em,
    ADD KEY IF NOT EXISTS idx_clientes_exclusao (excluido_em);

ALTER TABLE orcamentos
    ADD COLUMN IF NOT EXISTS excluido_em DATETIME NULL AFTER recusado_em,
    ADD COLUMN IF NOT EXISTS excluido_por INT UNSIGNED NULL AFTER excluido_em,
    ADD KEY IF NOT EXISTS idx_orcamentos_exclusao (excluido_em);

ALTER TABLE servicos
    ADD COLUMN IF NOT EXISTS excluido_em DATETIME NULL AFTER status,
    ADD COLUMN IF NOT EXISTS excluido_por INT UNSIGNED NULL AFTER excluido_em,
    ADD KEY IF NOT EXISTS idx_servicos_exclusao (excluido_em);

SET @fk_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'fk_clientes_exclusao_usuario');
SET @sql := IF(@fk_exists = 0, 'ALTER TABLE clientes ADD CONSTRAINT fk_clientes_exclusao_usuario FOREIGN KEY (excluido_por) REFERENCES usuarios(id) ON UPDATE CASCADE ON DELETE SET NULL', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @fk_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'fk_orcamentos_exclusao_usuario');
SET @sql := IF(@fk_exists = 0, 'ALTER TABLE orcamentos ADD CONSTRAINT fk_orcamentos_exclusao_usuario FOREIGN KEY (excluido_por) REFERENCES usuarios(id) ON UPDATE CASCADE ON DELETE SET NULL', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @fk_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'fk_servicos_exclusao_usuario');
SET @sql := IF(@fk_exists = 0, 'ALTER TABLE servicos ADD CONSTRAINT fk_servicos_exclusao_usuario FOREIGN KEY (excluido_por) REFERENCES usuarios(id) ON UPDATE CASCADE ON DELETE SET NULL', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

INSERT INTO permissoes (grupo, modulo, codigo, nome, descricao, ordem, status) VALUES
('Clientes', 'cliente', 'cliente.excluir', 'Excluir clientes', 'Permite excluir logicamente clientes sem apagar o historico.', 150, 'ativo'),
('Orçamentos', 'orcamento', 'orcamento.excluir', 'Excluir orçamentos', 'Permite excluir logicamente orcamentos sem apagar o historico.', 480, 'ativo'),
('Serviços', 'servico', 'servico.excluir', 'Excluir serviços', 'Permite excluir logicamente servicos sem apagar o historico.', 940, 'ativo')
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
  JOIN permissoes permissao ON permissao.codigo IN ('cliente.excluir', 'orcamento.excluir', 'servico.excluir')
 WHERE perfil.nome IN ('Administrador', 'Dono', 'Gerente')
   AND permissao.status = 'ativo';
