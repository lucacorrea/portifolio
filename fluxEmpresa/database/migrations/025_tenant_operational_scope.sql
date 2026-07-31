-- Prepara o isolamento operacional por empresa sem atribuir automaticamente os dados legados.
-- O preenchimento de empresa_id deve ser executado de forma controlada pelo script CLI.

SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS usuario_empresas (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT UNSIGNED NOT NULL,
    usuario_id INT UNSIGNED NOT NULL,
    perfil_id INT UNSIGNED NOT NULL,
    status ENUM('ativo', 'inativo', 'bloqueado') NOT NULL DEFAULT 'ativo',
    principal TINYINT(1) NOT NULL DEFAULT 0,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_usuario_empresas_empresa_usuario (empresa_id, usuario_id),
    KEY idx_usuario_empresas_usuario_status (usuario_id, status),
    KEY idx_usuario_empresas_perfil (perfil_id),
    CONSTRAINT fk_usuario_empresas_empresa FOREIGN KEY (empresa_id) REFERENCES empresas(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_usuario_empresas_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_usuario_empresas_perfil FOREIGN KEY (perfil_id) REFERENCES perfis(id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS empresa_auditoria_operacional (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT UNSIGNED NOT NULL,
    usuario_id INT UNSIGNED NULL,
    acesso_administrativo_id INT UNSIGNED NULL,
    acao VARCHAR(80) NOT NULL,
    entidade_tipo VARCHAR(80) NULL,
    entidade_id BIGINT UNSIGNED NULL,
    sessao_chave CHAR(64) NULL,
    ip VARCHAR(45) NULL,
    detalhes LONGTEXT NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_empresa_auditoria_empresa_data (empresa_id, criado_em),
    KEY idx_empresa_auditoria_usuario_data (usuario_id, criado_em),
    KEY idx_empresa_auditoria_acesso (acesso_administrativo_id),
    CONSTRAINT fk_empresa_auditoria_empresa FOREIGN KEY (empresa_id) REFERENCES empresas(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_empresa_auditoria_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_empresa_auditoria_acesso FOREIGN KEY (acesso_administrativo_id) REFERENCES empresa_acessos_administrativos(id)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE funcionarios ADD COLUMN IF NOT EXISTS empresa_id INT UNSIGNED NULL, ADD INDEX IF NOT EXISTS idx_funcionarios_empresa (empresa_id);
ALTER TABLE produtos ADD COLUMN IF NOT EXISTS empresa_id INT UNSIGNED NULL, ADD INDEX IF NOT EXISTS idx_produtos_empresa (empresa_id);
ALTER TABLE servicos ADD COLUMN IF NOT EXISTS empresa_id INT UNSIGNED NULL, ADD INDEX IF NOT EXISTS idx_servicos_empresa (empresa_id);
ALTER TABLE clientes ADD COLUMN IF NOT EXISTS empresa_id INT UNSIGNED NULL, ADD INDEX IF NOT EXISTS idx_clientes_empresa (empresa_id);
ALTER TABLE orcamentos ADD COLUMN IF NOT EXISTS empresa_id INT UNSIGNED NULL, ADD INDEX IF NOT EXISTS idx_orcamentos_empresa (empresa_id);
ALTER TABLE orcamento_itens ADD COLUMN IF NOT EXISTS empresa_id INT UNSIGNED NULL, ADD INDEX IF NOT EXISTS idx_orcamento_itens_empresa (empresa_id);
ALTER TABLE ordens_servico ADD COLUMN IF NOT EXISTS empresa_id INT UNSIGNED NULL, ADD INDEX IF NOT EXISTS idx_ordens_servico_empresa (empresa_id);
ALTER TABLE ordem_servico_itens ADD COLUMN IF NOT EXISTS empresa_id INT UNSIGNED NULL, ADD INDEX IF NOT EXISTS idx_os_itens_empresa (empresa_id);
ALTER TABLE agenda_lembretes ADD COLUMN IF NOT EXISTS empresa_id INT UNSIGNED NULL, ADD INDEX IF NOT EXISTS idx_agenda_lembretes_empresa (empresa_id);
ALTER TABLE ordem_servico_funcionarios ADD COLUMN IF NOT EXISTS empresa_id INT UNSIGNED NULL, ADD INDEX IF NOT EXISTS idx_os_funcionarios_empresa (empresa_id);
ALTER TABLE ordem_servico_cancelamentos ADD COLUMN IF NOT EXISTS empresa_id INT UNSIGNED NULL, ADD INDEX IF NOT EXISTS idx_os_cancelamentos_empresa (empresa_id);
ALTER TABLE ordem_servico_finalizacoes ADD COLUMN IF NOT EXISTS empresa_id INT UNSIGNED NULL, ADD INDEX IF NOT EXISTS idx_os_finalizacoes_empresa (empresa_id);
ALTER TABLE ordem_servico_execucao_itens ADD COLUMN IF NOT EXISTS empresa_id INT UNSIGNED NULL, ADD INDEX IF NOT EXISTS idx_os_execucao_itens_empresa (empresa_id);
ALTER TABLE estoque_autorizacoes ADD COLUMN IF NOT EXISTS empresa_id INT UNSIGNED NULL, ADD INDEX IF NOT EXISTS idx_estoque_autorizacoes_empresa (empresa_id);
ALTER TABLE estoque_movimentacoes ADD COLUMN IF NOT EXISTS empresa_id INT UNSIGNED NULL, ADD INDEX IF NOT EXISTS idx_estoque_movimentacoes_empresa (empresa_id);
ALTER TABLE caixa_movimentacoes ADD COLUMN IF NOT EXISTS empresa_id INT UNSIGNED NULL, ADD INDEX IF NOT EXISTS idx_caixa_movimentacoes_empresa (empresa_id);
ALTER TABLE ordem_servico_pagamentos ADD COLUMN IF NOT EXISTS empresa_id INT UNSIGNED NULL, ADD INDEX IF NOT EXISTS idx_os_pagamentos_empresa (empresa_id);
ALTER TABLE contas_receber ADD COLUMN IF NOT EXISTS empresa_id INT UNSIGNED NULL, ADD INDEX IF NOT EXISTS idx_contas_receber_empresa (empresa_id);
ALTER TABLE contas_receber_eventos ADD COLUMN IF NOT EXISTS empresa_id INT UNSIGNED NULL, ADD INDEX IF NOT EXISTS idx_contas_receber_eventos_empresa (empresa_id);
ALTER TABLE configuracoes_empresa
    MODIFY COLUMN id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    ADD COLUMN IF NOT EXISTS empresa_id INT UNSIGNED NULL,
    ADD UNIQUE INDEX IF NOT EXISTS uq_configuracoes_empresa_empresa (empresa_id);
ALTER TABLE configuracoes_fiscais
    MODIFY COLUMN id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    ADD COLUMN IF NOT EXISTS empresa_id INT UNSIGNED NULL,
    ADD UNIQUE INDEX IF NOT EXISTS uq_configuracoes_fiscais_empresa (empresa_id);
ALTER TABLE documentos_fiscais ADD COLUMN IF NOT EXISTS empresa_id INT UNSIGNED NULL, ADD INDEX IF NOT EXISTS idx_documentos_fiscais_empresa (empresa_id);
ALTER TABLE recibos ADD COLUMN IF NOT EXISTS empresa_id INT UNSIGNED NULL, ADD INDEX IF NOT EXISTS idx_recibos_empresa (empresa_id);
ALTER TABLE boletos ADD COLUMN IF NOT EXISTS empresa_id INT UNSIGNED NULL, ADD INDEX IF NOT EXISTS idx_boletos_empresa (empresa_id);
ALTER TABLE vendas_avulsas ADD COLUMN IF NOT EXISTS empresa_id INT UNSIGNED NULL, ADD INDEX IF NOT EXISTS idx_vendas_avulsas_empresa (empresa_id);
ALTER TABLE venda_avulsa_itens ADD COLUMN IF NOT EXISTS empresa_id INT UNSIGNED NULL, ADD INDEX IF NOT EXISTS idx_venda_avulsa_itens_empresa (empresa_id);
ALTER TABLE fornecedores ADD COLUMN IF NOT EXISTS empresa_id INT UNSIGNED NULL, ADD INDEX IF NOT EXISTS idx_fornecedores_empresa (empresa_id);
ALTER TABLE contas_pagar ADD COLUMN IF NOT EXISTS empresa_id INT UNSIGNED NULL, ADD INDEX IF NOT EXISTS idx_contas_pagar_empresa (empresa_id);
ALTER TABLE metas_comissao_mensais ADD COLUMN IF NOT EXISTS empresa_id INT UNSIGNED NULL, ADD INDEX IF NOT EXISTS idx_metas_comissao_empresa (empresa_id);
ALTER TABLE contas_pagar_parcelas ADD COLUMN IF NOT EXISTS empresa_id INT UNSIGNED NULL, ADD INDEX IF NOT EXISTS idx_contas_pagar_parcelas_empresa (empresa_id);
ALTER TABLE contas_pagar_parcela_eventos ADD COLUMN IF NOT EXISTS empresa_id INT UNSIGNED NULL, ADD INDEX IF NOT EXISTS idx_contas_pagar_eventos_empresa (empresa_id);
ALTER TABLE caixa_sessoes ADD COLUMN IF NOT EXISTS empresa_id INT UNSIGNED NULL, ADD INDEX IF NOT EXISTS idx_caixa_sessoes_empresa (empresa_id);
ALTER TABLE fiscal_certificados ADD COLUMN IF NOT EXISTS empresa_id INT UNSIGNED NULL, ADD INDEX IF NOT EXISTS idx_fiscal_certificados_empresa (empresa_id);
ALTER TABLE fiscal_configuracoes ADD COLUMN IF NOT EXISTS empresa_id INT UNSIGNED NULL, ADD INDEX IF NOT EXISTS idx_fiscal_configuracoes_empresa (empresa_id);
ALTER TABLE fiscal_series ADD COLUMN IF NOT EXISTS empresa_id INT UNSIGNED NULL, ADD INDEX IF NOT EXISTS idx_fiscal_series_empresa (empresa_id);
ALTER TABLE fiscal_auditoria ADD COLUMN IF NOT EXISTS empresa_id INT UNSIGNED NULL, ADD INDEX IF NOT EXISTS idx_fiscal_auditoria_empresa (empresa_id);

-- Códigos e numerações pertencem à empresa. Os índices legados globais
-- impediriam duas empresas de usarem a mesma sequência comercial.
ALTER TABLE funcionarios DROP INDEX IF EXISTS uk_funcionarios_codigo, ADD UNIQUE KEY IF NOT EXISTS uk_funcionarios_empresa_codigo (empresa_id, codigo);
ALTER TABLE produtos DROP INDEX IF EXISTS uk_produtos_codigo, DROP INDEX IF EXISTS uk_produtos_codigo_barras, ADD UNIQUE KEY IF NOT EXISTS uk_produtos_empresa_codigo (empresa_id, codigo), ADD UNIQUE KEY IF NOT EXISTS uk_produtos_empresa_codigo_barras (empresa_id, codigo_barras);
ALTER TABLE servicos DROP INDEX IF EXISTS uk_servicos_codigo, ADD UNIQUE KEY IF NOT EXISTS uk_servicos_empresa_codigo (empresa_id, codigo);
ALTER TABLE clientes DROP INDEX IF EXISTS uk_clientes_codigo, DROP INDEX IF EXISTS uk_clientes_documento, ADD UNIQUE KEY IF NOT EXISTS uk_clientes_empresa_codigo (empresa_id, codigo), ADD UNIQUE KEY IF NOT EXISTS uk_clientes_empresa_documento (empresa_id, documento);
ALTER TABLE orcamentos DROP INDEX IF EXISTS uk_orcamentos_numero, ADD UNIQUE KEY IF NOT EXISTS uk_orcamentos_empresa_numero (empresa_id, numero);
ALTER TABLE ordens_servico DROP INDEX IF EXISTS uk_ordens_servico_numero, ADD UNIQUE KEY IF NOT EXISTS uk_ordens_servico_empresa_numero (empresa_id, numero);
ALTER TABLE vendas_avulsas DROP INDEX IF EXISTS uq_vendas_avulsas_numero, ADD UNIQUE KEY IF NOT EXISTS uq_vendas_avulsas_empresa_numero (empresa_id, numero);
ALTER TABLE documentos_fiscais DROP INDEX IF EXISTS uq_documento_fiscal_numero, ADD UNIQUE KEY IF NOT EXISTS uq_documento_fiscal_empresa_numero (empresa_id, ambiente, serie, numero);
ALTER TABLE recibos DROP INDEX IF EXISTS uq_recibos_numero, ADD UNIQUE KEY IF NOT EXISTS uq_recibos_empresa_numero (empresa_id, numero);
ALTER TABLE boletos DROP INDEX IF EXISTS uq_boletos_numero, ADD UNIQUE KEY IF NOT EXISTS uq_boletos_empresa_numero (empresa_id, numero);
ALTER TABLE fornecedores DROP INDEX IF EXISTS uq_fornecedores_codigo, DROP INDEX IF EXISTS uq_fornecedores_documento, ADD UNIQUE KEY IF NOT EXISTS uq_fornecedores_empresa_codigo (empresa_id, codigo), ADD UNIQUE KEY IF NOT EXISTS uq_fornecedores_empresa_documento (empresa_id, documento);
ALTER TABLE contas_pagar DROP INDEX IF EXISTS uq_contas_pagar_codigo, ADD UNIQUE KEY IF NOT EXISTS uq_contas_pagar_empresa_codigo (empresa_id, codigo);
ALTER TABLE caixa_sessoes DROP INDEX IF EXISTS uq_caixa_sessao_codigo, DROP INDEX IF EXISTS uq_caixa_sessao_aberta, ADD UNIQUE KEY IF NOT EXISTS uq_caixa_sessao_empresa_codigo (empresa_id, codigo), ADD UNIQUE KEY IF NOT EXISTS uq_caixa_sessao_empresa_aberta (empresa_id, sessao_aberta_chave);
ALTER TABLE metas_comissao_mensais DROP INDEX IF EXISTS uq_meta_comissao_competencia_versao, DROP INDEX IF EXISTS uq_meta_comissao_competencia_ativa, ADD UNIQUE KEY IF NOT EXISTS uq_meta_comissao_empresa_competencia_versao (empresa_id, competencia, versao), ADD UNIQUE KEY IF NOT EXISTS uq_meta_comissao_empresa_competencia_ativa (empresa_id, configuracao_ativa_chave);
ALTER TABLE fiscal_certificados DROP INDEX IF EXISTS uq_fiscal_certificado_arquivo_sha256, DROP INDEX IF EXISTS uq_fiscal_certificado_fingerprint, ADD UNIQUE KEY IF NOT EXISTS uq_fiscal_certificado_empresa_arquivo (empresa_id, arquivo_sha256), ADD UNIQUE KEY IF NOT EXISTS uq_fiscal_certificado_empresa_fingerprint (empresa_id, certificado_fingerprint_sha256);
ALTER TABLE fiscal_configuracoes DROP INDEX IF EXISTS uq_fiscal_configuracao_versao, DROP INDEX IF EXISTS uq_fiscal_configuracao_ativa, ADD UNIQUE KEY IF NOT EXISTS uq_fiscal_configuracao_empresa_versao (empresa_id, ambiente, modelo, versao), ADD UNIQUE KEY IF NOT EXISTS uq_fiscal_configuracao_empresa_ativa (empresa_id, configuracao_ativa_chave);
ALTER TABLE fiscal_series DROP INDEX IF EXISTS uq_fiscal_serie_ambiente_modelo, ADD UNIQUE KEY IF NOT EXISTS uq_fiscal_serie_empresa_ambiente_modelo (empresa_id, ambiente, modelo, serie);
