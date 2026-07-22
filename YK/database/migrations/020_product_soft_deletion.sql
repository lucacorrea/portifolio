-- Migration 020 - Exclusao logica e auditada de produtos.
-- Preserva o historico de estoque, vendas, orcamentos e ordens de servico.

SET NAMES utf8mb4;

ALTER TABLE produtos
    ADD COLUMN IF NOT EXISTS excluido_em DATETIME NULL AFTER status,
    ADD COLUMN IF NOT EXISTS excluido_por INT UNSIGNED NULL AFTER excluido_em,
    ADD COLUMN IF NOT EXISTS motivo_exclusao VARCHAR(255) NULL AFTER excluido_por,
    ADD KEY IF NOT EXISTS idx_produtos_exclusao (excluido_em);

SET @fk_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'fk_produtos_exclusao_usuario');
SET @sql := IF(@fk_exists = 0, 'ALTER TABLE produtos ADD CONSTRAINT fk_produtos_exclusao_usuario FOREIGN KEY (excluido_por) REFERENCES usuarios(id) ON UPDATE CASCADE ON DELETE SET NULL', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

INSERT INTO permissoes (grupo, modulo, codigo, nome, descricao, ordem, status) VALUES
('Produtos', 'produto', 'produto.excluir', 'Excluir produtos', 'Permite excluir logicamente produtos sem apagar o historico.', 760, 'ativo')
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
  JOIN permissoes permissao ON permissao.codigo = 'produto.excluir'
 WHERE perfil.nome IN ('Administrador', 'Dono', 'Gerente')
   AND permissao.status = 'ativo';
