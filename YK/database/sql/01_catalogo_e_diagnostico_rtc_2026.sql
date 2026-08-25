-- RTC 2026 - catálogo técnico IBS/CBS.
-- Idempotente. Não classifica automaticamente produtos reais.

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
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_fiscal_ibscbs_regra
        (codigo_cst, codigo_classificacao, vigencia_inicio),
    KEY idx_fiscal_ibscbs_vigencia
        (status, vigencia_inicio, vigencia_fim)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

INSERT INTO fiscal_ibs_cbs_classificacoes (
    codigo_cst,
    codigo_classificacao,
    descricao,
    modo_calculo,
    aliquota_ibs_uf,
    aliquota_ibs_municipio,
    aliquota_cbs,
    vigencia_inicio,
    vigencia_fim,
    indicadores_json,
    fonte,
    versao_fonte,
    status
)
VALUES (
    '000',
    '000001',
    'Tributação integral - regra técnica padrão 2026',
    'standard',
    0.1000,
    0.0000,
    0.9000,
    '2026-01-01',
    '2026-12-31',
    JSON_OBJECT(
        'ind_gIBSCBS', 1,
        'indNFe', 1,
        'indNFCe', 1
    ),
    'Portal Nacional da NF-e / RTC',
    'IT 2025.002 v1.60 + alíquotas teste 2026',
    'ativo'
)
ON DUPLICATE KEY UPDATE
    descricao = VALUES(descricao),
    modo_calculo = VALUES(modo_calculo),
    aliquota_ibs_uf = VALUES(aliquota_ibs_uf),
    aliquota_ibs_municipio = VALUES(aliquota_ibs_municipio),
    aliquota_cbs = VALUES(aliquota_cbs),
    indicadores_json = VALUES(indicadores_json),
    fonte = VALUES(fonte),
    versao_fonte = VALUES(versao_fonte),
    status = VALUES(status);

SELECT
    id,
    codigo,
    nome,
    ncm,
    origem_mercadoria,
    cfop_padrao,
    csosn,
    cst_pis,
    aliquota_pis,
    cst_cofins,
    aliquota_cofins,
    unidade_tributavel,
    cst_ibs_cbs,
    classificacao_tributaria_ibs_cbs
FROM produtos
WHERE nome = 'MOTOR VENTILADOR TESTE HOMOLOGAÇÃO'
  AND excluido_em IS NULL;

SELECT
    id,
    codigo,
    nome,
    cst_ibs_cbs,
    classificacao_tributaria_ibs_cbs
FROM produtos
WHERE excluido_em IS NULL
  AND status = 'ativo'
  AND (
      cst_ibs_cbs IS NULL
      OR cst_ibs_cbs = ''
      OR classificacao_tributaria_ibs_cbs IS NULL
      OR classificacao_tributaria_ibs_cbs = ''
  )
ORDER BY nome, id;
