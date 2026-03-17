-- Migration 026: Create nfce_emitidas table
-- This table is used to persist SEFAZ responses for NFC-e emissions, as per Açaidinhos flow.

CREATE TABLE IF NOT EXISTS nfce_emitidas (
  id                                        BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  empresa_id                                VARCHAR(40) NOT NULL,
  venda_id                                  BIGINT DEFAULT NULL,
  ambiente                                  TINYINT NOT NULL,
  serie                                     INT NOT NULL,
  numero                                    INT NOT NULL,
  chave                                     CHAR(44) NOT NULL,
  protocolo                                 VARCHAR(50) DEFAULT NULL,
  status_sefaz                              VARCHAR(10) NOT NULL,
  mensagem                                  VARCHAR(255) DEFAULT NULL,
  xml_nfeproc                               MEDIUMTEXT DEFAULT NULL,
  xml_envio                                 MEDIUMTEXT DEFAULT NULL,
  xml_retorno                               MEDIUMTEXT DEFAULT NULL,
  valor_total                               DECIMAL(12,2) DEFAULT 0.00,
  valor_troco                               DECIMAL(12,2) DEFAULT 0.00,
  tpag_json                                 LONGTEXT,
  created_at                                DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_nfce_venda (venda_id),
  INDEX idx_nfce_chave (chave),
  INDEX idx_nfce_empresa (empresa_id)
);
