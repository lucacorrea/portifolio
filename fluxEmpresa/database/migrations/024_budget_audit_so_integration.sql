-- Migration 024 - Auditoria imutável de orçamento e integração idempotente com o SO.
SET NAMES utf8mb4;

ALTER TABLE perfis
    ADD COLUMN IF NOT EXISTS codigo VARCHAR(80) NULL AFTER nome,
    ADD UNIQUE KEY IF NOT EXISTS uk_perfis_codigo (codigo);

UPDATE perfis
   SET codigo = 'suporte'
 WHERE codigo IS NULL
   AND LOWER(TRIM(nome)) = 'suporte';

ALTER TABLE orcamentos
    ADD COLUMN IF NOT EXISTS criado_por_usuario_id INT UNSIGNED NULL,
    ADD COLUMN IF NOT EXISTS criado_por_nome_snapshot VARCHAR(150) NULL,
    ADD COLUMN IF NOT EXISTS criado_por_perfil_id_snapshot INT UNSIGNED NULL,
    ADD COLUMN IF NOT EXISTS criado_por_perfil_codigo_snapshot VARCHAR(80) NULL,
    ADD COLUMN IF NOT EXISTS criado_por_perfil_nome_snapshot VARCHAR(100) NULL,
    ADD COLUMN IF NOT EXISTS criado_por_suporte TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS aprovado_por_usuario_id INT UNSIGNED NULL,
    ADD COLUMN IF NOT EXISTS aprovado_por_nome_snapshot VARCHAR(150) NULL,
    ADD COLUMN IF NOT EXISTS aprovado_por_perfil_id_snapshot INT UNSIGNED NULL,
    ADD COLUMN IF NOT EXISTS aprovado_por_perfil_codigo_snapshot VARCHAR(80) NULL,
    ADD COLUMN IF NOT EXISTS aprovado_por_perfil_nome_snapshot VARCHAR(100) NULL,
    ADD COLUMN IF NOT EXISTS so_evento_uuid CHAR(36) NULL,
    ADD COLUMN IF NOT EXISTS so_aquisicao_id BIGINT UNSIGNED NULL,
    ADD COLUMN IF NOT EXISTS so_aquisicao_numero VARCHAR(50) NULL,
    ADD COLUMN IF NOT EXISTS so_codigo_entrega VARCHAR(50) NULL,
    ADD COLUMN IF NOT EXISTS so_aquisicao_status VARCHAR(50) NULL,
    ADD COLUMN IF NOT EXISTS so_sincronizado_em DATETIME NULL,
    ADD KEY IF NOT EXISTS idx_orcamentos_criado_por (criado_por_usuario_id),
    ADD KEY IF NOT EXISTS idx_orcamentos_aprovado_por (aprovado_por_usuario_id),
    ADD KEY IF NOT EXISTS idx_orcamentos_criado_por_suporte (criado_por_suporte),
    ADD UNIQUE KEY IF NOT EXISTS uk_orcamentos_so_evento_uuid (so_evento_uuid),
    ADD UNIQUE KEY IF NOT EXISTS uk_orcamentos_so_aquisicao_id (so_aquisicao_id),
    ADD CONSTRAINT fk_orcamentos_criado_por_usuario FOREIGN KEY (criado_por_usuario_id)
        REFERENCES usuarios(id) ON UPDATE CASCADE ON DELETE SET NULL,
    ADD CONSTRAINT fk_orcamentos_aprovado_por_usuario FOREIGN KEY (aprovado_por_usuario_id)
        REFERENCES usuarios(id) ON UPDATE CASCADE ON DELETE SET NULL;

CREATE TABLE IF NOT EXISTS orcamento_integracoes_so (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    orcamento_id INT UNSIGNED NOT NULL,
    evento_uuid CHAR(36) NOT NULL,
    tipo_evento VARCHAR(80) NOT NULL,
    status ENUM('pendente', 'processando', 'sincronizado', 'falhou') NOT NULL DEFAULT 'pendente',
    tentativas INT UNSIGNED NOT NULL DEFAULT 0,
    requisicao_json LONGTEXT NULL,
    resposta_json LONGTEXT NULL,
    http_status SMALLINT UNSIGNED NULL,
    so_aquisicao_id BIGINT UNSIGNED NULL,
    so_aquisicao_numero VARCHAR(50) NULL,
    so_codigo_entrega VARCHAR(50) NULL,
    so_status VARCHAR(50) NULL,
    ultimo_erro VARCHAR(1000) NULL,
    ultima_tentativa_em DATETIME NULL,
    proxima_tentativa_em DATETIME NULL,
    sincronizado_em DATETIME NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_integracao_so_orcamento (orcamento_id),
    UNIQUE KEY uk_integracao_so_evento (evento_uuid),
    KEY idx_integracao_so_status_tentativa (status, proxima_tentativa_em),
    CONSTRAINT fk_integracao_so_orcamento FOREIGN KEY (orcamento_id)
        REFERENCES orcamentos(id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO permissoes (grupo, modulo, codigo, nome, descricao, ordem, status) VALUES
('Orçamentos', 'orcamento', 'orcamento.integracao_so.visualizar', 'Visualizar integração SO', 'Permite consultar a situação da integração com o SO.', 1230, 'ativo'),
('Orçamentos', 'orcamento', 'orcamento.integracao_so.reprocessar', 'Reprocessar integração SO', 'Permite reenviar uma integração de orçamento com o SO.', 1240, 'ativo')
ON DUPLICATE KEY UPDATE nome = VALUES(nome), descricao = VALUES(descricao), status = 'ativo';

INSERT IGNORE INTO perfil_permissoes (perfil_id, permissao_id)
SELECT perfil.id, permissao.id
  FROM perfis perfil
  JOIN permissoes permissao ON permissao.codigo IN ('orcamento.integracao_so.visualizar', 'orcamento.integracao_so.reprocessar')
 WHERE perfil.nome IN ('Administrador', 'Dono', 'Gerente')
   AND permissao.status = 'ativo';
