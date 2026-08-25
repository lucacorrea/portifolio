-- Conclusao fiscal 2026 - YK/OSMais.
-- Alvo: MariaDB 10.4+, InnoDB, utf8mb4. Execute uma unica vez, manualmente,
-- no phpMyAdmin, somente depois de backup completo. Este arquivo nao habilita producao.

SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

-- 1. Catalogo versionado IBS/CBS. Enquadramento de produtos reais deve ser validado pelo contador.
CREATE TABLE IF NOT EXISTS fiscal_ibs_cbs_classificacoes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    codigo_cst CHAR(3) NOT NULL,
    codigo_classificacao CHAR(6) NOT NULL,
    descricao VARCHAR(255) NOT NULL,
    modo_calculo ENUM('standard', 'unsupported') NOT NULL DEFAULT 'unsupported',
    aliquota_ibs_uf DECIMAL(7,4) NULL,
    aliquota_ibs_municipio DECIMAL(7,4) NULL,
    aliquota_cbs DECIMAL(7,4) NULL,
    vigencia_inicio DATE NOT NULL,
    vigencia_fim DATE NULL,
    indicadores_json JSON NULL,
    fonte VARCHAR(255) NOT NULL,
    versao_fonte VARCHAR(60) NOT NULL,
    status ENUM('ativo', 'inativo') NOT NULL DEFAULT 'ativo',
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_fiscal_ibscbs_regra (codigo_cst, codigo_classificacao, vigencia_inicio),
    KEY idx_fiscal_ibscbs_vigencia (status, vigencia_inicio, vigencia_fim)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Regra tecnica padrao comprovada para 2026. Nao classifica automaticamente produtos reais.
INSERT INTO fiscal_ibs_cbs_classificacoes
    (codigo_cst, codigo_classificacao, descricao, modo_calculo,
     aliquota_ibs_uf, aliquota_ibs_municipio, aliquota_cbs,
     vigencia_inicio, vigencia_fim, indicadores_json, fonte, versao_fonte, status)
VALUES
    ('000', '000001', 'Tributacao integral - regra tecnica padrao 2026', 'standard',
     0.1000, 0.0000, 0.9000, '2026-01-01', '2026-12-31',
     JSON_OBJECT('ind_gIBSCBS', 1, 'indNFe', 1, 'indNFCe', 1),
     'Portal Nacional da NF-e', 'IT 2025.002 v1.60', 'ativo')
ON DUPLICATE KEY UPDATE
    descricao = VALUES(descricao), modo_calculo = VALUES(modo_calculo),
    aliquota_ibs_uf = VALUES(aliquota_ibs_uf),
    aliquota_ibs_municipio = VALUES(aliquota_ibs_municipio),
    aliquota_cbs = VALUES(aliquota_cbs), indicadores_json = VALUES(indicadores_json),
    fonte = VALUES(fonte), versao_fonte = VALUES(versao_fonte), status = VALUES(status);

-- 2. Dados fiscais dos servicos e complemento do tomador.
ALTER TABLE servicos
    ADD COLUMN IF NOT EXISTS codigo_tributacao_nacional CHAR(6) NULL AFTER descricao,
    ADD COLUMN IF NOT EXISTS nbs VARCHAR(9) NULL AFTER codigo_tributacao_nacional,
    ADD COLUMN IF NOT EXISTS descricao_fiscal VARCHAR(2000) NULL AFTER nbs,
    ADD COLUMN IF NOT EXISTS municipio_incidencia_ibge CHAR(7) NULL AFTER descricao_fiscal,
    ADD COLUMN IF NOT EXISTS tributacao_iss VARCHAR(30) NULL AFTER municipio_incidencia_ibge,
    ADD COLUMN IF NOT EXISTS iss_retido TINYINT(1) NOT NULL DEFAULT 0 AFTER tributacao_iss,
    ADD COLUMN IF NOT EXISTS aliquota_iss DECIMAL(7,4) NULL AFTER iss_retido,
    ADD COLUMN IF NOT EXISTS regime_especial VARCHAR(30) NULL AFTER aliquota_iss,
    ADD COLUMN IF NOT EXISTS exigibilidade_iss VARCHAR(30) NULL AFTER regime_especial,
    ADD COLUMN IF NOT EXISTS cst_pis_servico CHAR(2) NULL AFTER exigibilidade_iss,
    ADD COLUMN IF NOT EXISTS cst_cofins_servico CHAR(2) NULL AFTER cst_pis_servico,
    ADD COLUMN IF NOT EXISTS aliquota_pis_servico DECIMAL(7,4) NULL AFTER cst_cofins_servico,
    ADD COLUMN IF NOT EXISTS aliquota_cofins_servico DECIMAL(7,4) NULL AFTER aliquota_pis_servico,
    ADD COLUMN IF NOT EXISTS cst_ibs_cbs CHAR(3) NULL AFTER aliquota_cofins_servico,
    ADD COLUMN IF NOT EXISTS classificacao_tributaria_ibs_cbs CHAR(6) NULL AFTER cst_ibs_cbs,
    ADD COLUMN IF NOT EXISTS cindop VARCHAR(10) NULL AFTER classificacao_tributaria_ibs_cbs,
    ADD COLUMN IF NOT EXISTS finalidade_nfse VARCHAR(10) NULL AFTER cindop,
    ADD COLUMN IF NOT EXISTS tipo_operacao VARCHAR(10) NULL AFTER finalidade_nfse,
    ADD KEY IF NOT EXISTS idx_servicos_ctn (codigo_tributacao_nacional),
    ADD KEY IF NOT EXISTS idx_servicos_nbs (nbs),
    ADD KEY IF NOT EXISTS idx_servicos_perfil_nfse
        (codigo_tributacao_nacional, aliquota_iss, municipio_incidencia_ibge);

ALTER TABLE clientes
    ADD COLUMN IF NOT EXISTS telefone VARCHAR(30) NULL AFTER email,
    ADD COLUMN IF NOT EXISTS codigo_pais CHAR(4) NOT NULL DEFAULT '1058' AFTER codigo_municipio_ibge;

-- 3. Tentativas imutaveis de NF-e/NFC-e e alocacao financeira fiscal.
CREATE TABLE IF NOT EXISTS fiscal_documento_tentativas (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    documento_fiscal_id INT UNSIGNED NOT NULL,
    numero_tentativa SMALLINT UNSIGNED NOT NULL,
    snapshot_json JSON NOT NULL,
    xml_gerado_path VARCHAR(255) NULL,
    xml_gerado_sha256 CHAR(64) NULL,
    xml_assinado_path VARCHAR(255) NULL,
    xml_assinado_sha256 CHAR(64) NULL,
    resposta_path VARCHAR(255) NULL,
    resposta_sha256 CHAR(64) NULL,
    chave CHAR(44) NULL,
    lote_id VARCHAR(20) NULL,
    recibo_sefaz VARCHAR(40) NULL,
    cstat VARCHAR(10) NULL,
    xmotivo VARCHAR(255) NULL,
    status ENUM('preparado','assinado','enviado','pendente_reconsulta','autorizado','rejeitado','denegado','erro_tecnico') NOT NULL,
    criado_por INT UNSIGNED NOT NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    finalizado_em DATETIME NULL,
    UNIQUE KEY uq_fiscal_tentativa_numero (documento_fiscal_id, numero_tentativa),
    KEY idx_fiscal_tentativa_status (status, criado_em),
    CONSTRAINT fk_fiscal_tentativa_documento FOREIGN KEY (documento_fiscal_id)
        REFERENCES documentos_fiscais(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_fiscal_tentativa_usuario FOREIGN KEY (criado_por)
        REFERENCES usuarios(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS fiscal_pagamento_alocacoes (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ordem_servico_id INT UNSIGNED NOT NULL,
    pagamento_id INT UNSIGNED NOT NULL,
    tipo_documento ENUM('nfe','nfce','nfse') NOT NULL,
    documento_id INT UNSIGNED NOT NULL,
    valor_alocado DECIMAL(12,2) NOT NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_fiscal_pagamento_documento (pagamento_id, tipo_documento, documento_id),
    KEY idx_fiscal_alocacao_os (ordem_servico_id, criado_em),
    CONSTRAINT fk_fiscal_alocacao_os FOREIGN KEY (ordem_servico_id)
        REFERENCES ordens_servico(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_fiscal_alocacao_pagamento FOREIGN KEY (pagamento_id)
        REFERENCES ordem_servico_pagamentos(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Dominio NFS-e independente do modelo estadual 55/65.
CREATE TABLE IF NOT EXISTS nfse_configuracoes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    provedor VARCHAR(40) NOT NULL,
    ambiente ENUM('homologacao','producao') NOT NULL,
    base_url VARCHAR(255) NOT NULL,
    municipio_ibge CHAR(7) NOT NULL,
    schema_versao VARCHAR(40) NOT NULL,
    schema_path VARCHAR(255) NOT NULL,
    provider_versao VARCHAR(40) NOT NULL,
    certificado_id INT UNSIGNED NOT NULL,
    status ENUM('rascunho','validada','ativa','inativa') NOT NULL DEFAULT 'rascunho',
    criado_por INT UNSIGNED NOT NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    configuracao_ativa_chave VARCHAR(90) GENERATED ALWAYS AS
        (CASE WHEN status='ativa' THEN CONCAT(provedor, ':', ambiente, ':', municipio_ibge) ELSE NULL END) PERSISTENT,
    UNIQUE KEY uq_nfse_configuracao_ativa (configuracao_ativa_chave),
    KEY idx_nfse_configuracao (provedor, ambiente, municipio_ibge, status),
    CONSTRAINT fk_nfse_config_certificado FOREIGN KEY (certificado_id)
        REFERENCES fiscal_certificados(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_nfse_config_usuario FOREIGN KEY (criado_por)
        REFERENCES usuarios(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS nfse_series (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    configuracao_id INT UNSIGNED NOT NULL,
    serie_dps VARCHAR(10) NOT NULL,
    proximo_numero BIGINT UNSIGNED NOT NULL DEFAULT 1,
    ultimo_numero_reservado BIGINT UNSIGNED NULL,
    status ENUM('ativa','inativa') NOT NULL DEFAULT 'ativa',
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_nfse_serie (configuracao_id, serie_dps),
    CONSTRAINT fk_nfse_serie_config FOREIGN KEY (configuracao_id)
        REFERENCES nfse_configuracoes(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS nfse_documentos (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ordem_servico_id INT UNSIGNED NOT NULL,
    configuracao_id INT UNSIGNED NOT NULL,
    serie_id INT UNSIGNED NOT NULL,
    serie_dps VARCHAR(10) NOT NULL,
    numero_dps BIGINT UNSIGNED NOT NULL,
    grupo_fiscal_hash CHAR(64) NOT NULL,
    idempotency_key CHAR(64) NOT NULL,
    provedor VARCHAR(40) NOT NULL,
    ambiente ENUM('homologacao','producao') NOT NULL,
    municipio_ibge CHAR(7) NOT NULL,
    id_dps_provedor VARCHAR(120) NULL,
    protocolo VARCHAR(120) NULL,
    chave_nfse VARCHAR(80) NULL,
    numero_nfse VARCHAR(40) NULL,
    status ENUM('rascunho','preparado','assinado','enviado','aguardando_validacao','autorizado','rejeitado_estrutura','rejeitado_regra','cancelamento_pendente','cancelado','substituicao_pendente','substituido','erro_tecnico') NOT NULL,
    status_provedor VARCHAR(120) NULL,
    mensagem VARCHAR(500) NULL,
    valor_servicos DECIMAL(12,2) NOT NULL,
    snapshot_json JSON NOT NULL,
    proxima_tentativa SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    pdf_url VARCHAR(500) NULL,
    pdf_local_path VARCHAR(255) NULL,
    emitido_por INT UNSIGNED NOT NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    autorizado_em DATETIME NULL,
    cancelado_em DATETIME NULL,
    UNIQUE KEY uq_nfse_idempotency (idempotency_key),
    UNIQUE KEY uq_nfse_dps (configuracao_id, serie_dps, numero_dps),
    UNIQUE KEY uq_nfse_grupo_os (ordem_servico_id, ambiente, grupo_fiscal_hash),
    UNIQUE KEY uq_nfse_chave (chave_nfse),
    KEY idx_nfse_status (ambiente, status, criado_em),
    CONSTRAINT fk_nfse_documento_os FOREIGN KEY (ordem_servico_id)
        REFERENCES ordens_servico(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_nfse_documento_config FOREIGN KEY (configuracao_id)
        REFERENCES nfse_configuracoes(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_nfse_documento_serie FOREIGN KEY (serie_id)
        REFERENCES nfse_series(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_nfse_documento_usuario FOREIGN KEY (emitido_por)
        REFERENCES usuarios(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS nfse_documento_itens (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nfse_documento_id INT UNSIGNED NOT NULL,
    ordem_servico_item_id INT UNSIGNED NOT NULL,
    servico_id INT UNSIGNED NULL,
    valor DECIMAL(12,2) NOT NULL,
    snapshot_json JSON NOT NULL,
    UNIQUE KEY uq_nfse_item_os (nfse_documento_id, ordem_servico_item_id),
    CONSTRAINT fk_nfse_item_documento FOREIGN KEY (nfse_documento_id)
        REFERENCES nfse_documentos(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_nfse_item_os_item FOREIGN KEY (ordem_servico_item_id)
        REFERENCES ordem_servico_itens(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_nfse_item_servico FOREIGN KEY (servico_id)
        REFERENCES servicos(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS nfse_tentativas (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nfse_documento_id INT UNSIGNED NOT NULL,
    numero_tentativa SMALLINT UNSIGNED NOT NULL,
    snapshot_json JSON NOT NULL,
    protocolo VARCHAR(120) NULL,
    status VARCHAR(60) NOT NULL,
    codigo VARCHAR(40) NULL,
    mensagem VARCHAR(500) NULL,
    criado_por INT UNSIGNED NOT NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    finalizado_em DATETIME NULL,
    UNIQUE KEY uq_nfse_tentativa (nfse_documento_id, numero_tentativa),
    CONSTRAINT fk_nfse_tentativa_documento FOREIGN KEY (nfse_documento_id)
        REFERENCES nfse_documentos(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_nfse_tentativa_usuario FOREIGN KEY (criado_por)
        REFERENCES usuarios(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS nfse_eventos (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nfse_documento_id INT UNSIGNED NOT NULL,
    tipo VARCHAR(60) NOT NULL,
    status_anterior VARCHAR(60) NULL,
    status_novo VARCHAR(60) NOT NULL,
    codigo VARCHAR(40) NULL,
    mensagem VARCHAR(500) NULL,
    usuario_id INT UNSIGNED NOT NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_nfse_evento_documento (nfse_documento_id, criado_em),
    CONSTRAINT fk_nfse_evento_documento FOREIGN KEY (nfse_documento_id)
        REFERENCES nfse_documentos(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_nfse_evento_usuario FOREIGN KEY (usuario_id)
        REFERENCES usuarios(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS nfse_artifacts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nfse_documento_id INT UNSIGNED NOT NULL,
    tentativa_id BIGINT UNSIGNED NULL,
    tipo VARCHAR(60) NOT NULL,
    path VARCHAR(255) NOT NULL,
    sha256 CHAR(64) NOT NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_nfse_artifact_hash (nfse_documento_id, tipo, sha256),
    CONSTRAINT fk_nfse_artifact_documento FOREIGN KEY (nfse_documento_id)
        REFERENCES nfse_documentos(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_nfse_artifact_tentativa FOREIGN KEY (tentativa_id)
        REFERENCES nfse_tentativas(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Permissoes. Suporte recebe consulta/teste; operacoes destrutivas ficam com Dono/Administrador.
INSERT INTO permissoes (grupo, modulo, codigo, nome, descricao, ordem, status) VALUES
('Fiscal', 'nfse', 'nfse.visualizar', 'Visualizar NFS-e', 'Permite consultar documentos e eventos NFS-e.', 1610, 'ativo'),
('Fiscal', 'nfse', 'nfse.emitir', 'Emitir NFS-e', 'Permite preparar e transmitir DPS.', 1611, 'ativo'),
('Fiscal', 'nfse', 'nfse.reconsultar', 'Reconsultar NFS-e', 'Permite consultar protocolo no provedor.', 1612, 'ativo'),
('Fiscal', 'nfse', 'nfse.cancelar', 'Cancelar NFS-e', 'Permite solicitar cancelamento quando o provedor suportar.', 1613, 'ativo'),
('Fiscal', 'nfse', 'nfse.substituir', 'Substituir NFS-e', 'Permite solicitar substituicao quando o provedor suportar.', 1614, 'ativo'),
('Fiscal', 'nfse', 'nfse.imprimir', 'Imprimir NFS-e', 'Permite abrir DANFSe autorizado.', 1615, 'ativo'),
('Fiscal', 'nfse', 'nfse.baixar_xml', 'Baixar XML NFS-e', 'Permite baixar XML autorizado.', 1616, 'ativo'),
('Fiscal', 'nfse', 'nfse.configurar', 'Configurar NFS-e', 'Permite configurar provedor e serie DPS.', 1617, 'ativo'),
('Fiscal', 'nfse', 'nfse.testar_integracao', 'Testar integracao NFS-e', 'Permite teste controlado sem liberar producao.', 1618, 'ativo')
ON DUPLICATE KEY UPDATE nome=VALUES(nome), descricao=VALUES(descricao), ordem=VALUES(ordem), status=VALUES(status);

INSERT IGNORE INTO perfil_permissoes (perfil_id, permissao_id)
SELECT p.id, pm.id FROM perfis p JOIN permissoes pm
 WHERE p.nome IN ('Dono','Administrador') AND pm.modulo='nfse' AND pm.status='ativo';

INSERT IGNORE INTO perfil_permissoes (perfil_id, permissao_id)
SELECT p.id, pm.id FROM perfis p JOIN permissoes pm
 WHERE p.nome='Suporte' AND pm.codigo IN ('nfse.visualizar','nfse.reconsultar','nfse.testar_integracao')
   AND pm.status='ativo';

-- Fim. Producao permanece condicionada a FISCAL_INTEGRATION_ENABLED=true e
-- FISCAL_PRODUCTION_ENABLED=true, configuracao validada e homologacao comprovada.
