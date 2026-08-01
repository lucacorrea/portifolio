-- Migration 009 - Ajustes obrigatorios de negocio apos a base 008.
-- Aplicacao manual na hospedagem, apos backup validado.
-- Ordem: executar depois de 008_service_order_execution_financial_flow.sql.
-- Compatibilidade alvo: MariaDB/MySQL compartilhado, InnoDB, utf8mb4.

SET NAMES utf8mb4;

ALTER TABLE produtos
    ADD COLUMN IF NOT EXISTS ncm VARCHAR(8) NULL AFTER unidade,
    ADD KEY IF NOT EXISTS idx_produtos_ncm (ncm);

SET @idx_exists := (
    SELECT COUNT(*)
      FROM INFORMATION_SCHEMA.STATISTICS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'ordem_servico_funcionarios'
       AND INDEX_NAME = 'uq_os_funcionario'
);
SET @sql := IF(
    @idx_exists > 0,
    'ALTER TABLE ordem_servico_funcionarios DROP INDEX uq_os_funcionario',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

ALTER TABLE ordem_servico_funcionarios
    ADD KEY IF NOT EXISTS idx_os_funcionarios_historico (ordem_servico_id, funcionario_id, ativo, adicionado_em);

ALTER TABLE ordens_servico
    ADD COLUMN IF NOT EXISTS orcamento_operacional_chave INT UNSIGNED NULL AFTER orcamento_liberado;

UPDATE ordens_servico
   SET orcamento_operacional_chave = CASE
        WHEN orcamento_id IS NOT NULL
         AND (status <> 'cancelada' OR orcamento_liberado = 0)
        THEN orcamento_id
        ELSE NULL
   END;

ALTER TABLE ordens_servico
    ADD UNIQUE KEY IF NOT EXISTS uq_os_orcamento_operacional_unico (orcamento_operacional_chave);

ALTER TABLE estoque_movimentacoes
    MODIFY COLUMN tipo ENUM('saida_os', 'entrada', 'ajuste', 'venda_avulsa', 'estorno') NOT NULL;

ALTER TABLE contas_receber
    MODIFY COLUMN status ENUM('pendente', 'parcial', 'vencida', 'paga', 'estornada', 'cancelada') NOT NULL DEFAULT 'pendente';

ALTER TABLE configuracoes_empresa
    ADD COLUMN IF NOT EXISTS inscricao_estadual VARCHAR(40) NULL AFTER logo,
    ADD COLUMN IF NOT EXISTS inscricao_municipal VARCHAR(40) NULL AFTER inscricao_estadual,
    ADD COLUMN IF NOT EXISTS email VARCHAR(150) NULL AFTER inscricao_municipal;

ALTER TABLE funcionarios
    ADD COLUMN IF NOT EXISTS foto VARCHAR(255) NULL AFTER nome,
    ADD COLUMN IF NOT EXISTS funcao VARCHAR(100) NULL AFTER foto,
    ADD COLUMN IF NOT EXISTS salario DECIMAL(12,2) NULL AFTER funcao,
    ADD COLUMN IF NOT EXISTS endereco VARCHAR(255) NULL AFTER salario,
    ADD COLUMN IF NOT EXISTS telefone_celular VARCHAR(30) NULL AFTER endereco,
    ADD COLUMN IF NOT EXISTS data_nascimento DATE NULL AFTER telefone_celular,
    ADD COLUMN IF NOT EXISTS estado_civil ENUM('Solteiro', 'Casado', 'Divorciado', 'Viuvo', 'Uniao estavel', 'Outro') NULL AFTER data_nascimento,
    ADD COLUMN IF NOT EXISTS sexo ENUM('Masculino', 'Feminino') NULL AFTER estado_civil,
    ADD COLUMN IF NOT EXISTS data_cadastro DATE NULL AFTER sexo,
    ADD COLUMN IF NOT EXISTS data_admissao DATE NULL AFTER data_cadastro,
    ADD COLUMN IF NOT EXISTS banco VARCHAR(100) NULL AFTER data_admissao,
    ADD COLUMN IF NOT EXISTS agencia VARCHAR(30) NULL AFTER banco,
    ADD COLUMN IF NOT EXISTS conta VARCHAR(40) NULL AFTER agencia,
    ADD COLUMN IF NOT EXISTS tipo_conta VARCHAR(30) NULL AFTER conta,
    ADD COLUMN IF NOT EXISTS pix VARCHAR(150) NULL AFTER tipo_conta,
    ADD COLUMN IF NOT EXISTS rg_numero VARCHAR(40) NULL AFTER pix,
    ADD COLUMN IF NOT EXISTS rg_uf CHAR(2) NULL AFTER rg_numero,
    ADD COLUMN IF NOT EXISTS rg_orgao_emissor VARCHAR(30) NULL AFTER rg_uf,
    ADD COLUMN IF NOT EXISTS rg_data_emissao DATE NULL AFTER rg_orgao_emissor,
    ADD COLUMN IF NOT EXISTS cpf_numero VARCHAR(20) NULL AFTER rg_data_emissao,
    ADD COLUMN IF NOT EXISTS titulo_eleitor_numero VARCHAR(40) NULL AFTER cpf_numero,
    ADD COLUMN IF NOT EXISTS titulo_eleitor_uf CHAR(2) NULL AFTER titulo_eleitor_numero,
    ADD COLUMN IF NOT EXISTS titulo_eleitor_secao VARCHAR(20) NULL AFTER titulo_eleitor_uf,
    ADD COLUMN IF NOT EXISTS titulo_eleitor_zona VARCHAR(20) NULL AFTER titulo_eleitor_secao,
    ADD COLUMN IF NOT EXISTS reservista_numero VARCHAR(60) NULL AFTER titulo_eleitor_zona,
    ADD COLUMN IF NOT EXISTS reservista_data_emissao DATE NULL AFTER reservista_numero,
    ADD COLUMN IF NOT EXISTS certidao_nascimento_numero VARCHAR(80) NULL AFTER reservista_data_emissao,
    ADD COLUMN IF NOT EXISTS certidao_nascimento_cidade VARCHAR(100) NULL AFTER certidao_nascimento_numero,
    ADD COLUMN IF NOT EXISTS certidao_nascimento_livro VARCHAR(30) NULL AFTER certidao_nascimento_cidade,
    ADD COLUMN IF NOT EXISTS certidao_nascimento_folha VARCHAR(30) NULL AFTER certidao_nascimento_livro,
    ADD COLUMN IF NOT EXISTS certidao_nascimento_data_emissao DATE NULL AFTER certidao_nascimento_folha,
    ADD COLUMN IF NOT EXISTS carteira_trabalho_numero VARCHAR(40) NULL AFTER certidao_nascimento_data_emissao,
    ADD COLUMN IF NOT EXISTS carteira_trabalho_serie VARCHAR(30) NULL AFTER carteira_trabalho_numero,
    ADD COLUMN IF NOT EXISTS carteira_trabalho_uf CHAR(2) NULL AFTER carteira_trabalho_serie,
    ADD COLUMN IF NOT EXISTS pis_pasep_numero VARCHAR(40) NULL AFTER carteira_trabalho_uf,
    ADD COLUMN IF NOT EXISTS cnh_numero_registro VARCHAR(40) NULL AFTER pis_pasep_numero,
    ADD COLUMN IF NOT EXISTS cnh_categoria VARCHAR(20) NULL AFTER cnh_numero_registro,
    ADD COLUMN IF NOT EXISTS cnh_data_vencimento DATE NULL AFTER cnh_categoria,
    ADD COLUMN IF NOT EXISTS manequim_camisa VARCHAR(30) NULL AFTER cnh_data_vencimento,
    ADD COLUMN IF NOT EXISTS manequim_calca VARCHAR(30) NULL AFTER manequim_camisa,
    ADD COLUMN IF NOT EXISTS manequim_calcado VARCHAR(30) NULL AFTER manequim_calca,
    ADD KEY IF NOT EXISTS idx_funcionarios_cpf (cpf_numero),
    ADD KEY IF NOT EXISTS idx_funcionarios_funcao (funcao);

CREATE TABLE IF NOT EXISTS configuracoes_fiscais (
    id TINYINT UNSIGNED NOT NULL PRIMARY KEY DEFAULT 1,
    ambiente ENUM('homologacao', 'producao') NOT NULL DEFAULT 'homologacao',
    certificado_caminho VARCHAR(255) NULL,
    certificado_senha_ref VARCHAR(255) NULL,
    csc VARCHAR(120) NULL,
    id_csc VARCHAR(40) NULL,
    serie VARCHAR(20) NULL,
    proxima_numeracao INT UNSIGNED NOT NULL DEFAULT 1,
    status ENUM('pendente', 'configurada', 'inativa') NOT NULL DEFAULT 'pendente',
    atualizado_por INT UNSIGNED NULL,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_config_fiscal_usuario FOREIGN KEY (atualizado_por) REFERENCES usuarios(id) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS documentos_fiscais (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    origem_tipo VARCHAR(40) NOT NULL,
    origem_id INT UNSIGNED NULL,
    ambiente ENUM('homologacao', 'producao') NOT NULL,
    serie VARCHAR(20) NOT NULL,
    numero INT UNSIGNED NOT NULL,
    status ENUM('rascunho', 'pendente_configuracao', 'emitida', 'autorizada', 'rejeitada', 'cancelada') NOT NULL DEFAULT 'rascunho',
    chave VARCHAR(80) NULL,
    protocolo VARCHAR(80) NULL,
    xml_path VARCHAR(255) NULL,
    retorno TEXT NULL,
    emitido_por INT UNSIGNED NOT NULL,
    emitido_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_documento_fiscal_numero (ambiente, serie, numero),
    KEY idx_documento_fiscal_origem (origem_tipo, origem_id),
    CONSTRAINT fk_documento_fiscal_usuario FOREIGN KEY (emitido_por) REFERENCES usuarios(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS recibos (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    numero VARCHAR(30) NULL,
    cliente_id INT UNSIGNED NULL,
    ordem_servico_id INT UNSIGNED NULL,
    pagamento_id INT UNSIGNED NULL,
    descricao TEXT NOT NULL,
    valor DECIMAL(12,2) NOT NULL,
    forma_pagamento VARCHAR(40) NULL,
    status ENUM('emitido', 'cancelado') NOT NULL DEFAULT 'emitido',
    emitido_por INT UNSIGNED NOT NULL,
    emitido_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    cancelado_por INT UNSIGNED NULL,
    cancelado_em DATETIME NULL,
    motivo_cancelamento VARCHAR(255) NULL,
    UNIQUE KEY uq_recibos_numero (numero),
    KEY idx_recibos_cliente (cliente_id),
    KEY idx_recibos_os (ordem_servico_id),
    CONSTRAINT fk_recibos_cliente FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_recibos_os FOREIGN KEY (ordem_servico_id) REFERENCES ordens_servico(id) ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_recibos_usuario FOREIGN KEY (emitido_por) REFERENCES usuarios(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_recibos_cancel_usuario FOREIGN KEY (cancelado_por) REFERENCES usuarios(id) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS boletos (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    numero VARCHAR(50) NULL,
    cliente_id INT UNSIGNED NULL,
    conta_receber_id INT UNSIGNED NULL,
    valor DECIMAL(12,2) NOT NULL,
    vencimento_em DATE NOT NULL,
    status ENUM('registrado', 'pendente_retorno', 'pago', 'cancelado', 'vencido') NOT NULL DEFAULT 'registrado',
    linha_digitavel VARCHAR(120) NULL,
    codigo_barras VARCHAR(120) NULL,
    retorno TEXT NULL,
    criado_por INT UNSIGNED NOT NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_boletos_numero (numero),
    KEY idx_boletos_cliente (cliente_id),
    KEY idx_boletos_conta (conta_receber_id),
    CONSTRAINT fk_boletos_cliente FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_boletos_conta FOREIGN KEY (conta_receber_id) REFERENCES contas_receber(id) ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_boletos_usuario FOREIGN KEY (criado_por) REFERENCES usuarios(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO configuracoes_fiscais (id) VALUES (1);

INSERT IGNORE INTO permissoes (grupo, modulo, codigo, nome, descricao, ordem) VALUES
('Financeiro', 'contas_receber', 'contas_receber.baixa_lote', 'Baixa em lote', 'Permite registrar baixa em lote de contas do mesmo cliente.', 1585),
('Funcionários', 'funcionario', 'funcionario.visualizar_salario', 'Visualizar salário', 'Permite visualizar salário de funcionários.', 1040),
('Funcionários', 'funcionario', 'funcionario.editar_salario', 'Editar salário', 'Permite alterar salário de funcionários.', 1050),
('Funcionários', 'funcionario', 'funcionario.visualizar_documentos', 'Visualizar documentos', 'Permite visualizar documentos de funcionários.', 1060),
('Funcionários', 'funcionario', 'funcionario.editar_documentos', 'Editar documentos', 'Permite alterar documentos de funcionários.', 1070),
('Funcionários', 'funcionario', 'funcionario.visualizar_dados_bancarios', 'Visualizar dados bancários', 'Permite visualizar dados bancários de funcionários.', 1080),
('Funcionários', 'funcionario', 'funcionario.editar_dados_bancarios', 'Editar dados bancários', 'Permite alterar dados bancários de funcionários.', 1090),
('Fiscal', 'boleto', 'boleto.registrar_pagamento', 'Registrar pagamento de boleto', 'Permite registrar pagamento interno de boleto sem simular retorno bancário.', 1710);

INSERT IGNORE INTO perfil_permissoes (perfil_id, permissao_id)
SELECT p.id, pe.id
  FROM perfis p
  JOIN permissoes pe
 WHERE p.nome IN ('Administrador', 'Dono', 'Gerente')
   AND pe.codigo IN (
       'contas_receber.baixa_lote',
       'funcionario.visualizar_salario',
       'funcionario.editar_salario',
       'funcionario.visualizar_documentos',
       'funcionario.editar_documentos',
       'funcionario.visualizar_dados_bancarios',
       'funcionario.editar_dados_bancarios',
       'boleto.registrar_pagamento'
   );
