-- Aplicar manualmente somente apos backup e revisao do preflight.
-- Este arquivo NAO e executado pelo MigrationRunner.
-- Pre-requisito: database/sql/fiscal_completion_2026.sql aplicado e validado.

ALTER TABLE documentos_fiscais
    ADD COLUMN IF NOT EXISTS cancelamento_status
        ENUM('nenhum','pendente','confirmado') NOT NULL DEFAULT 'nenhum'
        AFTER cancelamento_xml_sha256;

CREATE TABLE IF NOT EXISTS fiscal_inutilizacoes (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ambiente ENUM('homologacao','producao') NOT NULL,
    modelo ENUM('55','65') NOT NULL,
    configuracao_id INT UNSIGNED NOT NULL,
    serie_id INT UNSIGNED NOT NULL,
    serie SMALLINT UNSIGNED NOT NULL,
    ano SMALLINT UNSIGNED NOT NULL,
    numero_inicial INT UNSIGNED NOT NULL,
    numero_final INT UNSIGNED NOT NULL,
    justificativa VARCHAR(255) NOT NULL,
    idempotency_key CHAR(64) NOT NULL,
    status ENUM('processando','pendente_confirmacao','autorizado','rejeitado') NOT NULL,
    cstat VARCHAR(10) NULL,
    xmotivo VARCHAR(255) NULL,
    protocolo VARCHAR(30) NULL,
    pedido_path VARCHAR(255) NULL,
    pedido_sha256 CHAR(64) NULL,
    resposta_path VARCHAR(255) NULL,
    resposta_sha256 CHAR(64) NULL,
    criado_por INT UNSIGNED NOT NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    finalizado_em DATETIME NULL,
    UNIQUE KEY uq_fiscal_inutilizacao_idempotencia (idempotency_key),
    KEY idx_fiscal_inutilizacao_faixa
        (ambiente, modelo, serie, ano, numero_inicial, numero_final),
    KEY idx_fiscal_inutilizacao_status (status, criado_em),
    CONSTRAINT ck_fiscal_inutilizacao_faixa CHECK (numero_final >= numero_inicial),
    CONSTRAINT fk_fiscal_inutilizacao_configuracao FOREIGN KEY (configuracao_id)
        REFERENCES fiscal_configuracoes(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_fiscal_inutilizacao_serie FOREIGN KEY (serie_id)
        REFERENCES fiscal_series(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_fiscal_inutilizacao_usuario FOREIGN KEY (criado_por)
        REFERENCES usuarios(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO permissoes (grupo, modulo, codigo, nome, descricao, ordem, status) VALUES
('Fiscal', 'nota_fiscal', 'nota_fiscal.ativar_producao', 'Ativar emissão em produção',
 'Permite testar, configurar e ativar explicitamente a emissão fiscal em produção.', 1603, 'ativo'),
('Fiscal', 'nota_fiscal', 'nota_fiscal.inutilizar', 'Inutilizar faixa fiscal',
 'Permite inutilizar lacunas de numeracao de NF-e/NFC-e na SEFAZ.', 1609, 'ativo')
ON DUPLICATE KEY UPDATE
    nome = VALUES(nome), descricao = VALUES(descricao), ordem = VALUES(ordem), status = VALUES(status);

INSERT IGNORE INTO perfil_permissoes (perfil_id, permissao_id)
SELECT perfil.id, permissao.id
  FROM perfis perfil
  JOIN permissoes permissao ON permissao.codigo IN (
      'nota_fiscal.ativar_producao', 'nota_fiscal.inutilizar'
  )
 WHERE perfil.nome = 'Dono'
   AND permissao.status = 'ativo';

-- Administrador operacional nao recebe ativacao de producao. Suporte tecnico
-- autorizado deve receber esta permissao manualmente e de forma auditada.
DELETE perfil_permissoes
  FROM perfil_permissoes
  JOIN perfis ON perfis.id = perfil_permissoes.perfil_id
  JOIN permissoes ON permissoes.id = perfil_permissoes.permissao_id
 WHERE perfis.nome = 'Administrador'
   AND permissoes.codigo = 'nota_fiscal.ativar_producao';
