CREATE TABLE IF NOT EXISTS usuarios (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nome VARCHAR(150) NOT NULL,
  email VARCHAR(190) NOT NULL,
  senha_hash VARCHAR(255) NOT NULL,
  ativo TINYINT(1) NOT NULL DEFAULT 1,
  ultimo_login_em DATETIME NULL,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_usuarios_email (email),
  KEY idx_usuarios_ativo (ativo),
  KEY idx_usuarios_nome (nome)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- =========================
-- PERFIS
-- =========================
CREATE TABLE IF NOT EXISTS perfis (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  codigo VARCHAR(50) NOT NULL,
  -- ADMIN / OPERADOR
  nome VARCHAR(100) NOT NULL,
  descricao VARCHAR(255) NULL,
  UNIQUE KEY uq_perfis_codigo (codigo),
  KEY idx_perfis_nome (nome)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- =========================
-- VÍNCULO USUÁRIO x PERFIL (sem FK)
-- =========================
CREATE TABLE IF NOT EXISTS usuario_perfis (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  usuario_id BIGINT UNSIGNED NOT NULL,
  perfil_id BIGINT UNSIGNED NOT NULL,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_usuario_perfil (usuario_id, perfil_id),
  KEY idx_up_usuario (usuario_id),
  KEY idx_up_perfil (perfil_id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- Perfis padrão
INSERT
  IGNORE INTO perfis (codigo, nome, descricao)
VALUES
  (
    'ADMIN',
    'Administrador',
    'Acesso total ao sistema'
  ),
  (
    'OPERADOR',
    'Operador',
    'Somente preencher dados'
  );

SET
  FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS venda_itens;

DROP TABLE IF EXISTS vendas;

DROP TABLE IF EXISTS fechamento_dia;

DROP TABLE IF EXISTS produtos;

DROP TABLE IF EXISTS produtores;

DROP TABLE IF EXISTS unidades;

DROP TABLE IF EXISTS categorias;

DROP TABLE IF EXISTS feiras;

SET
  FOREIGN_KEY_CHECKS = 1;

CREATE TABLE feiras (
  id TINYINT UNSIGNED NOT NULL,
  codigo VARCHAR(30) NOT NULL,
  nome VARCHAR(120) NOT NULL,
  ativo TINYINT(1) NOT NULL DEFAULT 1,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_feiras_codigo (codigo),
  KEY idx_feiras_ativo (ativo)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

INSERT INTO
  feiras (id, codigo, nome, ativo)
VALUES
  (1, 'PRODUTOR', 'Feira do Produtor', 1),
  (2, 'ALTERNATIVA', 'Feira Alternativa', 1);

CREATE TABLE categorias (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  feira_id TINYINT UNSIGNED NOT NULL,
  nome VARCHAR(120) NOT NULL,
  ativo TINYINT(1) NOT NULL DEFAULT 1,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_categorias_feira_nome (feira_id, nome),
  UNIQUE KEY uq_categorias_feira_id (feira_id, id),
  KEY idx_categorias_feira_ativo (feira_id, ativo),
  KEY idx_categorias_feira_nome (feira_id, nome),
  CONSTRAINT fk_categorias_feira FOREIGN KEY (feira_id) REFERENCES feiras(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE unidades (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  feira_id TINYINT UNSIGNED NOT NULL,
  nome VARCHAR(80) NOT NULL,
  sigla VARCHAR(20) NULL,
  ativo TINYINT(1) NOT NULL DEFAULT 1,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_unidades_feira_nome (feira_id, nome),
  UNIQUE KEY uq_unidades_feira_id (feira_id, id),
  KEY idx_unidades_feira_ativo (feira_id, ativo),
  KEY idx_unidades_feira_nome (feira_id, nome),
  CONSTRAINT fk_unidades_feira FOREIGN KEY (feira_id) REFERENCES feiras(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE produtores (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  feira_id TINYINT UNSIGNED NOT NULL,
  nome VARCHAR(160) NOT NULL,
  contato VARCHAR(60) NULL,
  comunidade VARCHAR(120) NULL,
  ativo TINYINT(1) NOT NULL DEFAULT 1,
  observacao VARCHAR(255) NULL,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_produtores_feira_nome (feira_id, nome),
  UNIQUE KEY uq_produtores_feira_id (feira_id, id),
  KEY idx_produtores_feira_ativo (feira_id, ativo),
  KEY idx_produtores_feira_nome (feira_id, nome),
  CONSTRAINT fk_produtores_feira FOREIGN KEY (feira_id) REFERENCES feiras(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE produtos (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  feira_id TINYINT UNSIGNED NOT NULL,
  nome VARCHAR(160) NOT NULL,
  categoria_id BIGINT UNSIGNED NULL,
  unidade_id BIGINT UNSIGNED NULL,
  produtor_id BIGINT UNSIGNED NULL,
  preco_referencia DECIMAL(10, 2) NULL,
  ativo TINYINT(1) NOT NULL DEFAULT 1,
  observacao VARCHAR(255) NULL,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_produtos_feira_nome (feira_id, nome),
  UNIQUE KEY uq_produtos_feira_id (feira_id, id),
  KEY idx_produtos_feira_ativo (feira_id, ativo),
  KEY idx_produtos_feira_categoria (feira_id, categoria_id),
  KEY idx_produtos_feira_unidade (feira_id, unidade_id),
  KEY idx_produtos_feira_produtor (feira_id, produtor_id),
  CONSTRAINT fk_produtos_feira FOREIGN KEY (feira_id) REFERENCES feiras(id) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_produtos_categoria FOREIGN KEY (feira_id, categoria_id) REFERENCES categorias(feira_id, id) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_produtos_unidade FOREIGN KEY (feira_id, unidade_id) REFERENCES unidades(feira_id, id) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_produtos_produtor FOREIGN KEY (feira_id, produtor_id) REFERENCES produtores(feira_id, id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE vendas (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  feira_id TINYINT UNSIGNED NOT NULL,
  data_hora DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  forma_pagamento VARCHAR(20) NOT NULL,
  total DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
  status VARCHAR(20) NOT NULL DEFAULT 'ABERTA',
  observacao VARCHAR(255) NULL,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_vendas_feira_id (feira_id, id),
  KEY idx_vendas_feira_data (feira_id, data_hora),
  KEY idx_vendas_feira_status (feira_id, status),
  KEY idx_vendas_feira_pagamento (feira_id, forma_pagamento),
  CONSTRAINT fk_vendas_feira FOREIGN KEY (feira_id) REFERENCES feiras(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE venda_itens (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  feira_id TINYINT UNSIGNED NOT NULL,
  venda_id BIGINT UNSIGNED NOT NULL,
  produto_id BIGINT UNSIGNED NULL,
  descricao_livre VARCHAR(160) NULL,
  quantidade DECIMAL(10, 3) NOT NULL DEFAULT 1.000,
  valor_unitario DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
  subtotal DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
  observacao VARCHAR(255) NULL,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_itens_feira_venda (feira_id, venda_id),
  KEY idx_itens_feira_produto (feira_id, produto_id),
  CONSTRAINT fk_itens_venda FOREIGN KEY (feira_id, venda_id) REFERENCES vendas(feira_id, id) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_itens_produto FOREIGN KEY (feira_id, produto_id) REFERENCES produtos(feira_id, id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE fechamento_dia (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  feira_id TINYINT UNSIGNED NOT NULL,
  data_ref DATE NOT NULL,
  qtd_vendas INT UNSIGNED NOT NULL DEFAULT 0,
  total_dia DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
  total_dinheiro DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
  total_pix DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
  total_cartao DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
  total_outros DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
  observacao VARCHAR(255) NULL,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_fechamento_feira_data (feira_id, data_ref),
  KEY idx_fechamento_feira_data (feira_id, data_ref),
  CONSTRAINT fk_fechamento_feira FOREIGN KEY (feira_id) REFERENCES feiras(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;