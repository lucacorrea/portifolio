-- Migration 016 - Sessao operacional de caixa, sangria, suprimento e PDV.
-- Compatibilidade alvo: MariaDB 10.4 compartilhado, InnoDB, utf8mb4.

SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS caixa_sessoes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    codigo VARCHAR(20) NULL,
    status ENUM('aberta', 'fechada') NOT NULL DEFAULT 'aberta',
    valor_abertura DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    observacao_abertura VARCHAR(255) NULL,
    aberto_por INT UNSIGNED NOT NULL,
    aberto_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    saldo_esperado DECIMAL(12,2) NULL,
    saldo_informado DECIMAL(12,2) NULL,
    diferenca DECIMAL(12,2) NULL,
    observacao_fechamento VARCHAR(255) NULL,
    fechado_por INT UNSIGNED NULL,
    fechado_em DATETIME NULL,
    sessao_aberta_chave TINYINT UNSIGNED
        GENERATED ALWAYS AS (CASE WHEN status = 'aberta' THEN 1 ELSE NULL END) PERSISTENT,
    UNIQUE KEY uq_caixa_sessao_codigo (codigo),
    UNIQUE KEY uq_caixa_sessao_aberta (sessao_aberta_chave),
    KEY idx_caixa_sessao_periodo (aberto_em, fechado_em),
    KEY idx_caixa_sessao_aberto_por (aberto_por),
    KEY idx_caixa_sessao_fechado_por (fechado_por),
    CONSTRAINT fk_caixa_sessao_aberto_usuario FOREIGN KEY (aberto_por) REFERENCES usuarios(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_caixa_sessao_fechado_usuario FOREIGN KEY (fechado_por) REFERENCES usuarios(id) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE caixa_movimentacoes
    ADD COLUMN IF NOT EXISTS caixa_sessao_id INT UNSIGNED NULL AFTER id,
    ADD KEY IF NOT EXISTS idx_caixa_mov_sessao_data (caixa_sessao_id, data_movimento);

SET @fk_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS
     WHERE CONSTRAINT_SCHEMA = DATABASE()
       AND CONSTRAINT_NAME = 'fk_caixa_mov_sessao'
);
SET @sql := IF(
    @fk_exists = 0,
    'ALTER TABLE caixa_movimentacoes ADD CONSTRAINT fk_caixa_mov_sessao FOREIGN KEY (caixa_sessao_id) REFERENCES caixa_sessoes(id) ON UPDATE CASCADE ON DELETE RESTRICT',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

ALTER TABLE vendas_avulsas
    ADD COLUMN IF NOT EXISTS caixa_sessao_id INT UNSIGNED NULL AFTER id,
    ADD KEY IF NOT EXISTS idx_vendas_avulsas_sessao (caixa_sessao_id);

SET @fk_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS
     WHERE CONSTRAINT_SCHEMA = DATABASE()
       AND CONSTRAINT_NAME = 'fk_vendas_avulsas_sessao'
);
SET @sql := IF(
    @fk_exists = 0,
    'ALTER TABLE vendas_avulsas ADD CONSTRAINT fk_vendas_avulsas_sessao FOREIGN KEY (caixa_sessao_id) REFERENCES caixa_sessoes(id) ON UPDATE CASCADE ON DELETE RESTRICT',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

ALTER TABLE estoque_movimentacoes
    MODIFY COLUMN tipo ENUM('entrada', 'saida_os', 'saida_venda', 'ajuste', 'estorno') NOT NULL;

INSERT INTO permissoes (grupo, modulo, codigo, nome, descricao, ordem, status) VALUES
('Caixa', 'caixa', 'caixa.visualizar', 'Visualizar Caixa', 'Permite acessar o Caixa.', 1310, 'ativo'),
('Caixa', 'caixa', 'caixa.abrir', 'Abrir Caixa', 'Permite abrir uma sessão operacional de Caixa.', 1320, 'ativo'),
('Caixa', 'caixa', 'caixa.registrar_venda', 'Operar PDV', 'Permite registrar vendas no PDV durante uma sessão aberta.', 1330, 'ativo'),
('Caixa', 'caixa', 'caixa.registrar_recebimento', 'Registrar recebimento', 'Permite registrar recebimentos no Caixa.', 1340, 'ativo'),
('Caixa', 'caixa', 'caixa.suprimento', 'Registrar suprimento', 'Permite adicionar dinheiro à sessão aberta com auditoria.', 1350, 'ativo'),
('Caixa', 'caixa', 'caixa.sangria', 'Registrar sangria', 'Permite retirar dinheiro da sessão aberta com auditoria.', 1360, 'ativo'),
('Caixa', 'caixa', 'caixa.estornar', 'Estornar Caixa', 'Permite estornar movimentações do Caixa.', 1370, 'ativo'),
('Caixa', 'caixa', 'caixa.fechar', 'Fechar Caixa', 'Permite conferir e fechar uma sessão operacional de Caixa.', 1380, 'ativo'),
('Caixa', 'caixa', 'caixa.visualizar_saldo', 'Visualizar saldo', 'Permite visualizar saldos e conferências do Caixa.', 1390, 'ativo')
ON DUPLICATE KEY UPDATE nome = VALUES(nome), descricao = VALUES(descricao), ordem = VALUES(ordem), status = VALUES(status);

INSERT IGNORE INTO perfil_permissoes (perfil_id, permissao_id)
SELECT perfil.id, permissao.id
  FROM perfis perfil
  JOIN permissoes permissao
 WHERE perfil.nome IN ('Administrador', 'Dono', 'Gerente')
   AND permissao.codigo IN (
       'caixa.visualizar', 'caixa.abrir', 'caixa.fechar', 'caixa.sangria',
       'caixa.suprimento', 'caixa.estornar', 'caixa.visualizar_saldo',
       'caixa.registrar_venda', 'venda_avulsa.visualizar',
       'venda_avulsa.criar', 'venda_avulsa.estornar'
   )
   AND permissao.status = 'ativo';
