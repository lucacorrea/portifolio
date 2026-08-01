-- Migration 017 - Fundacao fiscal segura, versionada e separada por ambiente/modelo.
-- Nao habilita emissao: prepara configuracao, certificado, numeracao e auditoria.
-- Compatibilidade alvo: MariaDB 10.4, InnoDB, utf8mb4.

SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS fiscal_certificados (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    arquivo_referencia VARCHAR(255) NOT NULL,
    arquivo_sha256 CHAR(64) NOT NULL,
    certificado_fingerprint_sha256 CHAR(64) NOT NULL,
    certificado_serial VARCHAR(120) NULL,
    titular_cnpj VARCHAR(14) NOT NULL,
    titular_nome VARCHAR(255) NULL,
    valido_de DATETIME NOT NULL,
    valido_ate DATETIME NOT NULL,
    senha_ciphertext VARBINARY(2048) NOT NULL,
    senha_nonce VARBINARY(64) NOT NULL,
    senha_tag VARBINARY(64) NOT NULL,
    cifra_algoritmo VARCHAR(40) NOT NULL DEFAULT 'xchacha20poly1305_ietf',
    chave_versao VARCHAR(30) NOT NULL DEFAULT 'v1',
    status ENUM('ativo', 'substituido', 'revogado', 'expirado') NOT NULL DEFAULT 'ativo',
    criado_por INT UNSIGNED NOT NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    substituido_por INT UNSIGNED NULL,
    substituido_em DATETIME NULL,
    UNIQUE KEY uq_fiscal_certificado_arquivo_sha256 (arquivo_sha256),
    UNIQUE KEY uq_fiscal_certificado_fingerprint (certificado_fingerprint_sha256),
    KEY idx_fiscal_certificado_cnpj_validade (titular_cnpj, valido_ate, status),
    KEY idx_fiscal_certificado_substituto (substituido_por),
    CONSTRAINT fk_fiscal_certificado_criado_usuario FOREIGN KEY (criado_por) REFERENCES usuarios(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_fiscal_certificado_substituto FOREIGN KEY (substituido_por) REFERENCES fiscal_certificados(id) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS fiscal_configuracoes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ambiente ENUM('homologacao', 'producao') NOT NULL,
    modelo ENUM('55', '65') NOT NULL,
    versao INT UNSIGNED NOT NULL,
    uf CHAR(2) NOT NULL,
    schema_versao VARCHAR(20) NOT NULL DEFAULT '4.00',
    qr_code_versao TINYINT UNSIGNED NULL,
    certificado_id INT UNSIGNED NOT NULL,
    csc_id VARCHAR(40) NULL,
    csc_ciphertext VARBINARY(1024) NULL,
    csc_nonce VARBINARY(64) NULL,
    csc_tag VARBINARY(64) NULL,
    csc_algoritmo VARCHAR(40) NULL,
    segredo_chave_versao VARCHAR(30) NULL,
    status ENUM('rascunho', 'validada', 'ativa', 'inativa') NOT NULL DEFAULT 'rascunho',
    criado_por INT UNSIGNED NOT NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ativado_por INT UNSIGNED NULL,
    ativado_em DATETIME NULL,
    desativado_por INT UNSIGNED NULL,
    desativado_em DATETIME NULL,
    configuracao_ativa_chave VARCHAR(32)
        GENERATED ALWAYS AS (CASE WHEN status = 'ativa' THEN CONCAT(ambiente, ':', modelo) ELSE NULL END) PERSISTENT,
    UNIQUE KEY uq_fiscal_configuracao_versao (ambiente, modelo, versao),
    UNIQUE KEY uq_fiscal_configuracao_ativa (configuracao_ativa_chave),
    KEY idx_fiscal_configuracao_consulta (ambiente, modelo, status, versao),
    KEY idx_fiscal_configuracao_certificado (certificado_id),
    CONSTRAINT fk_fiscal_configuracao_certificado FOREIGN KEY (certificado_id) REFERENCES fiscal_certificados(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_fiscal_configuracao_criado_usuario FOREIGN KEY (criado_por) REFERENCES usuarios(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_fiscal_configuracao_ativado_usuario FOREIGN KEY (ativado_por) REFERENCES usuarios(id) ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_fiscal_configuracao_desativado_usuario FOREIGN KEY (desativado_por) REFERENCES usuarios(id) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS fiscal_series (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ambiente ENUM('homologacao', 'producao') NOT NULL,
    modelo ENUM('55', '65') NOT NULL,
    serie SMALLINT UNSIGNED NOT NULL,
    proximo_numero INT UNSIGNED NOT NULL DEFAULT 1,
    ultimo_numero_reservado INT UNSIGNED NULL,
    status ENUM('ativa', 'inativa') NOT NULL DEFAULT 'ativa',
    criado_por INT UNSIGNED NOT NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_por INT UNSIGNED NULL,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_fiscal_serie_ambiente_modelo (ambiente, modelo, serie),
    KEY idx_fiscal_serie_status (ambiente, modelo, status),
    CONSTRAINT fk_fiscal_serie_criado_usuario FOREIGN KEY (criado_por) REFERENCES usuarios(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_fiscal_serie_atualizado_usuario FOREIGN KEY (atualizado_por) REFERENCES usuarios(id) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS fiscal_auditoria (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    entidade_tipo VARCHAR(50) NOT NULL,
    entidade_id INT UNSIGNED NULL,
    acao VARCHAR(80) NOT NULL,
    ambiente ENUM('homologacao', 'producao') NULL,
    modelo ENUM('55', '65') NULL,
    usuario_id INT UNSIGNED NOT NULL,
    detalhes JSON NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_fiscal_auditoria_entidade (entidade_tipo, entidade_id, criado_em),
    KEY idx_fiscal_auditoria_usuario (usuario_id, criado_em),
    KEY idx_fiscal_auditoria_ambiente (ambiente, modelo, criado_em),
    CONSTRAINT fk_fiscal_auditoria_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE produtos
    ADD COLUMN IF NOT EXISTS cest VARCHAR(7) NULL AFTER ncm,
    ADD COLUMN IF NOT EXISTS origem_mercadoria TINYINT UNSIGNED NULL AFTER cest,
    ADD COLUMN IF NOT EXISTS cfop_padrao CHAR(4) NULL AFTER origem_mercadoria,
    ADD COLUMN IF NOT EXISTS cst_icms CHAR(3) NULL AFTER cfop_padrao,
    ADD COLUMN IF NOT EXISTS csosn CHAR(3) NULL AFTER cst_icms,
    ADD COLUMN IF NOT EXISTS cst_pis CHAR(2) NULL AFTER csosn,
    ADD COLUMN IF NOT EXISTS cst_cofins CHAR(2) NULL AFTER cst_pis,
    ADD COLUMN IF NOT EXISTS aliquota_icms DECIMAL(7,4) NULL AFTER cst_cofins,
    ADD COLUMN IF NOT EXISTS aliquota_pis DECIMAL(7,4) NULL AFTER aliquota_icms,
    ADD COLUMN IF NOT EXISTS aliquota_cofins DECIMAL(7,4) NULL AFTER aliquota_pis,
    ADD COLUMN IF NOT EXISTS gtin_tributavel VARCHAR(14) NULL AFTER codigo_barras,
    ADD COLUMN IF NOT EXISTS unidade_tributavel VARCHAR(20) NULL AFTER gtin_tributavel,
    ADD COLUMN IF NOT EXISTS cst_ibs_cbs CHAR(3) NULL AFTER aliquota_cofins,
    ADD COLUMN IF NOT EXISTS classificacao_tributaria_ibs_cbs VARCHAR(6) NULL AFTER cst_ibs_cbs,
    ADD KEY IF NOT EXISTS idx_produtos_cest (cest),
    ADD KEY IF NOT EXISTS idx_produtos_cfop (cfop_padrao),
    ADD KEY IF NOT EXISTS idx_produtos_gtin_tributavel (gtin_tributavel);

ALTER TABLE clientes
    ADD COLUMN IF NOT EXISTS inscricao_estadual VARCHAR(20) NULL AFTER documento,
    ADD COLUMN IF NOT EXISTS indicador_ie ENUM('contribuinte', 'isento', 'nao_contribuinte') NOT NULL DEFAULT 'nao_contribuinte' AFTER inscricao_estadual,
    ADD COLUMN IF NOT EXISTS codigo_municipio_ibge CHAR(7) NULL AFTER cidade,
    ADD KEY IF NOT EXISTS idx_clientes_inscricao_estadual (inscricao_estadual),
    ADD KEY IF NOT EXISTS idx_clientes_municipio_ibge (codigo_municipio_ibge);

ALTER TABLE configuracoes_empresa
    ADD COLUMN IF NOT EXISTS crt TINYINT UNSIGNED NULL AFTER inscricao_municipal,
    ADD COLUMN IF NOT EXISTS cnae_principal CHAR(7) NULL AFTER crt,
    ADD COLUMN IF NOT EXISTS endereco_logradouro VARCHAR(150) NULL AFTER endereco,
    ADD COLUMN IF NOT EXISTS endereco_numero VARCHAR(30) NULL AFTER endereco_logradouro,
    ADD COLUMN IF NOT EXISTS endereco_complemento VARCHAR(100) NULL AFTER endereco_numero,
    ADD COLUMN IF NOT EXISTS endereco_bairro VARCHAR(100) NULL AFTER endereco_complemento,
    ADD COLUMN IF NOT EXISTS endereco_cidade VARCHAR(100) NULL AFTER endereco_bairro,
    ADD COLUMN IF NOT EXISTS endereco_uf CHAR(2) NULL AFTER endereco_cidade,
    ADD COLUMN IF NOT EXISTS endereco_cep VARCHAR(8) NULL AFTER endereco_uf,
    ADD COLUMN IF NOT EXISTS codigo_municipio_ibge CHAR(7) NULL AFTER endereco_cep;

INSERT INTO permissoes (grupo, modulo, codigo, nome, descricao, ordem, status) VALUES
('Fiscal', 'nota_fiscal', 'nota_fiscal.configurar', 'Configurar emissão fiscal', 'Permite criar versões da configuração fiscal de homologação.', 1601, 'ativo'),
('Fiscal', 'nota_fiscal', 'nota_fiscal.gerenciar_credenciais', 'Gerenciar credenciais fiscais', 'Permite cadastrar e substituir certificado e segredos fiscais cifrados.', 1602, 'ativo'),
('Fiscal', 'nota_fiscal', 'nota_fiscal.ativar_producao', 'Ativar emissão em produção', 'Permite ativar explicitamente uma configuração fiscal de produção.', 1603, 'ativo'),
('Fiscal', 'nota_fiscal', 'nota_fiscal.testar_integracao', 'Testar integração fiscal', 'Permite testar certificado, cadastro e comunicação com a SEFAZ.', 1604, 'ativo'),
('Fiscal', 'nota_fiscal', 'nota_fiscal.baixar_xml', 'Baixar XML fiscal', 'Permite baixar XML fiscal autorizado, sujeito a auditoria.', 1605, 'ativo')
ON DUPLICATE KEY UPDATE nome = VALUES(nome), descricao = VALUES(descricao), ordem = VALUES(ordem), status = VALUES(status);

INSERT IGNORE INTO perfil_permissoes (perfil_id, permissao_id)
SELECT perfil.id, permissao.id
  FROM perfis perfil
  JOIN permissoes permissao
 WHERE perfil.nome IN ('Administrador', 'Dono')
   AND permissao.codigo IN (
       'nota_fiscal.configurar', 'nota_fiscal.gerenciar_credenciais',
       'nota_fiscal.ativar_producao', 'nota_fiscal.testar_integracao',
       'nota_fiscal.baixar_xml'
   )
   AND permissao.status = 'ativo';

DELETE perfil_permissao
  FROM perfil_permissoes perfil_permissao
  JOIN perfis perfil ON perfil.id = perfil_permissao.perfil_id
  JOIN permissoes permissao ON permissao.id = perfil_permissao.permissao_id
 WHERE permissao.codigo IN (
       'nota_fiscal.configurar', 'nota_fiscal.gerenciar_credenciais',
       'nota_fiscal.ativar_producao', 'nota_fiscal.testar_integracao',
       'nota_fiscal.baixar_xml'
   )
   AND perfil.nome NOT IN ('Administrador', 'Dono');
