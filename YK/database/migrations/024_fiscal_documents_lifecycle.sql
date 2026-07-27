-- Migration 024 - Ciclo seguro de documentos fiscais NF-e/NFC-e.
-- Mantem recibos/comprovantes nao fiscais separados e exige XML autorizado para impressao.
-- Compatibilidade alvo: MariaDB 10.4, InnoDB, utf8mb4.

SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

ALTER TABLE documentos_fiscais
    ADD COLUMN IF NOT EXISTS modelo ENUM('55', '65') NULL AFTER ambiente,
    ADD COLUMN IF NOT EXISTS configuracao_id INT UNSIGNED NULL AFTER modelo,
    ADD COLUMN IF NOT EXISTS serie_id INT UNSIGNED NULL AFTER configuracao_id,
    ADD COLUMN IF NOT EXISTS ordem_servico_id INT UNSIGNED NULL AFTER origem_id,
    ADD COLUMN IF NOT EXISTS conta_receber_id INT UNSIGNED NULL AFTER ordem_servico_id,
    ADD COLUMN IF NOT EXISTS pagamento_id INT UNSIGNED NULL AFTER conta_receber_id,
    ADD COLUMN IF NOT EXISTS finalidade ENUM('normal', 'complementar', 'ajuste', 'devolucao') NOT NULL DEFAULT 'normal' AFTER pagamento_id,
    ADD COLUMN IF NOT EXISTS idempotency_key CHAR(64) NULL AFTER finalidade,
    ADD COLUMN IF NOT EXISTS processamento_status ENUM(
        'rascunho', 'preparado', 'processando', 'pendente_reconsulta',
        'autorizado', 'rejeitado', 'denegado', 'cancelado', 'erro_tecnico'
    ) NOT NULL DEFAULT 'rascunho' AFTER status,
    ADD COLUMN IF NOT EXISTS valor_produtos DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER processamento_status,
    ADD COLUMN IF NOT EXISTS valor_nota DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER valor_produtos,
    ADD COLUMN IF NOT EXISTS snapshot_json JSON NULL AFTER valor_nota,
    ADD COLUMN IF NOT EXISTS lote_id VARCHAR(20) NULL AFTER snapshot_json,
    ADD COLUMN IF NOT EXISTS recibo_sefaz VARCHAR(40) NULL AFTER lote_id,
    ADD COLUMN IF NOT EXISTS cstat VARCHAR(10) NULL AFTER protocolo,
    ADD COLUMN IF NOT EXISTS xmotivo VARCHAR(255) NULL AFTER cstat,
    ADD COLUMN IF NOT EXISTS xml_assinado_path VARCHAR(255) NULL AFTER xml_path,
    ADD COLUMN IF NOT EXISTS xml_assinado_sha256 CHAR(64) NULL AFTER xml_assinado_path,
    ADD COLUMN IF NOT EXISTS xml_autorizado_path VARCHAR(255) NULL AFTER xml_assinado_sha256,
    ADD COLUMN IF NOT EXISTS xml_autorizado_sha256 CHAR(64) NULL AFTER xml_autorizado_path,
    ADD COLUMN IF NOT EXISTS ultima_resposta_path VARCHAR(255) NULL AFTER xml_autorizado_sha256,
    ADD COLUMN IF NOT EXISTS ultima_resposta_sha256 CHAR(64) NULL AFTER ultima_resposta_path,
    ADD COLUMN IF NOT EXISTS autorizado_em DATETIME NULL AFTER emitido_em,
    ADD COLUMN IF NOT EXISTS cancelado_em DATETIME NULL AFTER autorizado_em,
    ADD COLUMN IF NOT EXISTS atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER cancelado_em,
    ADD KEY IF NOT EXISTS idx_documento_fiscal_os (ordem_servico_id, modelo, processamento_status),
    ADD KEY IF NOT EXISTS idx_documento_fiscal_conta (conta_receber_id),
    ADD KEY IF NOT EXISTS idx_documento_fiscal_pagamento (pagamento_id),
    ADD KEY IF NOT EXISTS idx_documento_fiscal_status (ambiente, modelo, processamento_status, emitido_em),
    ADD KEY IF NOT EXISTS idx_documento_fiscal_configuracao (configuracao_id),
    ADD KEY IF NOT EXISTS idx_documento_fiscal_serie (serie_id),
    ADD UNIQUE KEY IF NOT EXISTS uq_documento_fiscal_idempotency (idempotency_key),
    ADD UNIQUE KEY IF NOT EXISTS uq_documento_fiscal_origem_modelo (ordem_servico_id, ambiente, modelo, finalidade),
    ADD CONSTRAINT fk_documento_fiscal_configuracao FOREIGN KEY (configuracao_id) REFERENCES fiscal_configuracoes(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    ADD CONSTRAINT fk_documento_fiscal_serie FOREIGN KEY (serie_id) REFERENCES fiscal_series(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    ADD CONSTRAINT fk_documento_fiscal_os FOREIGN KEY (ordem_servico_id) REFERENCES ordens_servico(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    ADD CONSTRAINT fk_documento_fiscal_conta FOREIGN KEY (conta_receber_id) REFERENCES contas_receber(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    ADD CONSTRAINT fk_documento_fiscal_pagamento FOREIGN KEY (pagamento_id) REFERENCES ordem_servico_pagamentos(id) ON UPDATE CASCADE ON DELETE RESTRICT;

CREATE TABLE IF NOT EXISTS fiscal_documento_eventos (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    documento_fiscal_id INT UNSIGNED NOT NULL,
    tipo VARCHAR(60) NOT NULL,
    status_anterior VARCHAR(40) NULL,
    status_novo VARCHAR(40) NOT NULL,
    cstat VARCHAR(10) NULL,
    xmotivo VARCHAR(255) NULL,
    artefato_path VARCHAR(255) NULL,
    artefato_sha256 CHAR(64) NULL,
    usuario_id INT UNSIGNED NOT NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_fiscal_documento_evento_documento (documento_fiscal_id, criado_em),
    KEY idx_fiscal_documento_evento_status (status_novo, criado_em),
    CONSTRAINT fk_fiscal_documento_evento_documento FOREIGN KEY (documento_fiscal_id) REFERENCES documentos_fiscais(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_fiscal_documento_evento_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO permissoes (grupo, modulo, codigo, nome, descricao, ordem, status) VALUES
('Fiscal', 'nota_fiscal', 'nota_fiscal.visualizar', 'Visualizar documentos fiscais', 'Permite consultar NF-e e NFC-e e seus estados.', 1598, 'ativo'),
('Fiscal', 'nota_fiscal', 'nota_fiscal.emitir', 'Emitir documentos fiscais', 'Permite preparar e transmitir NF-e/NFC-e para autorização.', 1599, 'ativo'),
('Fiscal', 'nota_fiscal', 'nota_fiscal.cancelar', 'Cancelar documentos fiscais', 'Permite solicitar evento fiscal de cancelamento.', 1600, 'ativo')
ON DUPLICATE KEY UPDATE nome = VALUES(nome), descricao = VALUES(descricao), ordem = VALUES(ordem), status = VALUES(status);

INSERT IGNORE INTO perfil_permissoes (perfil_id, permissao_id)
SELECT perfil.id, permissao.id
  FROM perfis perfil
  JOIN permissoes permissao
 WHERE perfil.nome IN ('Administrador', 'Dono')
   AND permissao.codigo IN (
       'nota_fiscal.visualizar', 'nota_fiscal.emitir', 'nota_fiscal.cancelar'
   )
   AND permissao.status = 'ativo';
