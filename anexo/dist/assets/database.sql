-- ================================
-- TABELA: BAIRROS
-- ================================
CREATE TABLE bairros (
    id                        INT AUTO_INCREMENT PRIMARY KEY,
    nome                      VARCHAR(255) NOT NULL
);

-- ================================
-- TABELA: CONTAS DE ACESSO
-- ================================
CREATE TABLE contas_acesso (
    id                        INT AUTO_INCREMENT PRIMARY KEY,

    nome                      VARCHAR(150) NOT NULL,
    email                     VARCHAR(180) NOT NULL UNIQUE,
    cpf                       CHAR(11) NOT NULL UNIQUE, -- armazenado apenas números

    senha_hash                CHAR(64) NOT NULL,        -- SHA-256 em hexadecimal
    senha_salt                CHAR(32) NOT NULL,        -- 16 bytes em hexadecimal
    senha_algo                VARCHAR(40) NOT NULL,        -- ex: sha256_salt

    role                      ENUM('prefeito','secretario','admin', 'suporte') NOT NULL DEFAULT 'admin',
    autorizado                ENUM('sim','nao') NOT NULL DEFAULT 'nao',

    created_at                DATETIME NOT NULL,
    updated_at                DATETIME NOT NULL,

    INDEX (email),
    INDEX (cpf)
);

-- ================================
-- TABELA: CONTAS DE ACESSO PRIVADO
-- ================================
CREATE TABLE contas_acesso_privado (
    id                        INT AUTO_INCREMENT PRIMARY KEY,

    nome                      VARCHAR(150) NOT NULL,
    email                     VARCHAR(180) NOT NULL UNIQUE,
    cpf                       CHAR(11) NOT NULL UNIQUE, -- armazenado apenas números

    senha_hash                CHAR(64) NOT NULL,        -- SHA-256 em hexadecimal
    senha_salt                CHAR(32) NOT NULL,        -- 16 bytes em hexadecimal
    senha_algo                VARCHAR(40) NOT NULL,        -- ex: sha256_salt

    role                      ENUM('prefeito','secretario','admin', 'suporte') NOT NULL DEFAULT 'admin',
    autorizado                ENUM('sim','nao') NOT NULL DEFAULT 'nao',

    created_at                DATETIME NOT NULL,
    updated_at                DATETIME NOT NULL,

    INDEX (email),
    INDEX (cpf)
);

-- ================================
-- TABELA: SENHA TOKENS
-- ================================
CREATE TABLE senha_tokens (
  id                          INT AUTO_INCREMENT PRIMARY KEY,
  conta_id                    INT NOT NULL,
  email                       VARCHAR(180) NOT NULL,
  codigo                      CHAR(6) NOT NULL,
  used                        TINYINT(1) NOT NULL DEFAULT 0,
  created_at                  DATETIME NOT NULL,
  expires_at                  DATETIME NOT NULL,
  INDEX (email),
  INDEX (conta_id),
  INDEX (expires_at)
);

-- ================================
-- TABELA: SENHA TOKENS PRIVADO
-- ================================
CREATE TABLE senha_tokens_privado (
  id                          INT AUTO_INCREMENT PRIMARY KEY,
  conta_id                    INT NOT NULL,
  email                       VARCHAR(180) NOT NULL,
  codigo                      CHAR(6) NOT NULL,
  used                        TINYINT(1) NOT NULL DEFAULT 0,
  created_at                  DATETIME NOT NULL,
  expires_at                  DATETIME NOT NULL,
  INDEX (email),
  INDEX (conta_id),
  INDEX (expires_at)
);

/* ============================
   Tabela: solicitantes
============================ */
CREATE TABLE solicitantes (
  id                          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

  -- Básico
  nome                        VARCHAR(180) NOT NULL,
  cpf                         CHAR(11) NOT NULL,
  nis                         VARCHAR(20) NULL,
  telefone                    VARCHAR(20) NULL,

  -- Bairro (relacional lógico)
  bairro_id                   INT UNSIGNED NULL,

  -- Foto
  foto_path                   VARCHAR(255) NULL,

  -- Pessoais
  genero                      VARCHAR(30) NULL,
  estado_civil                VARCHAR(30) NULL,
  data_nascimento             DATE NULL,
  nacionalidade               VARCHAR(60) NULL,
  naturalidade                VARCHAR(120) NULL,

  -- Documentos
  rg                          VARCHAR(30) NULL,
  rg_emissao                  DATE NULL,
  rg_uf                       CHAR(2) NULL,

  -- Tempo de moradia
  tempo_anos                  INT NULL,
  tempo_meses                 INT NULL,

  -- Endereço
  endereco                    VARCHAR(200) NULL,
  numero                      VARCHAR(20) NULL,
  complemento                 VARCHAR(120) NULL,
  referencia                  VARCHAR(160) NULL,

  -- Grupos/benefícios/renda
  grupo_tradicional           VARCHAR(120) NULL,
  grupo_outros                VARCHAR(120) NULL,

  pcd                         VARCHAR(10) NULL,
  pcd_tipo                    VARCHAR(120) NULL,

  bpc                         VARCHAR(10) NULL,
  bpc_valor                   DECIMAL(12,2) NULL,

  pbf                         VARCHAR(10) NULL,
  pbf_valor                   DECIMAL(12,2) NULL,

  beneficio_municipal         VARCHAR(10) NULL,
  beneficio_municipal_valor   DECIMAL(12,2) NULL,

  beneficio_estadual          VARCHAR(10) NULL,
  beneficio_estadual_valor    DECIMAL(12,2) NULL,

  renda_mensal_faixa          VARCHAR(60) NULL,
  renda_mensal_outros         VARCHAR(120) NULL,
  trabalho                    VARCHAR(120) NULL,
  renda_individual            DECIMAL(12,2) NULL,
  renda_familiar              DECIMAL(12,2) NULL,
  total_rendimentos           DECIMAL(12,2) NULL,
  tipificacao                 VARCHAR(120) NULL,
  ajuda_tipo_id               INT UNSIGNED NULL,  -- ref. ajudas_tipos.id (lógico)

  -- Composição familiar (totais)
  total_moradores             INT NULL,
  total_familias              INT NULL,
  pcd_residencia              VARCHAR(10) NULL,
  total_pcd                   INT NULL,

  -- Condições habitacionais
  situacao_imovel             VARCHAR(40) NULL,
  situacao_imovel_valor       DECIMAL(12,2) NULL,
  tipo_moradia                VARCHAR(60) NULL,
  abastecimento               VARCHAR(60) NULL,
  iluminacao                  VARCHAR(60) NULL,
  esgoto                      VARCHAR(60) NULL,
  lixo                        VARCHAR(60) NULL,
  entorno                     VARCHAR(120) NULL,

  -- Resumo
  resumo_caso                 TEXT NULL,

  -- Cônjuge
  conj_nome                   VARCHAR(180) NULL,
  conj_nis                    VARCHAR(20) NULL,
  conj_cpf                    CHAR(11) NULL,
  conj_rg                     VARCHAR(30) NULL,
  conj_nasc                   DATE NULL,
  conj_genero                 VARCHAR(30) NULL,
  conj_nacionalidade          VARCHAR(60) NULL,
  conj_naturalidade           VARCHAR(120) NULL,
  conj_trabalho               VARCHAR(120) NULL,
  conj_renda                  DECIMAL(12,2) NULL,
  conj_pcd                    VARCHAR(10) NULL,
  conj_bpc                    VARCHAR(10) NULL,
  conj_bpc_valor              DECIMAL(12,2) NULL,

  -- Campo usado pelo SEMAS
  beneficio_semas             VARCHAR(10) NULL,
  beneficio_semas_valor       DECIMAL(12,2) NULL,

  created_at                  DATETIME NULL,
  updated_at                  DATETIME NULL, 
  responsavel                 VARCHAR(500) NULL
);

CREATE UNIQUE INDEX uq_solicitantes_cpf ON solicitantes (cpf);
CREATE INDEX idx_solicitantes_bairro ON solicitantes (bairro_id);
CREATE INDEX idx_solicitantes_nome ON solicitantes (nome);
CREATE INDEX idx_solicitantes_created ON solicitantes (created_at);


CREATE TABLE solicitacoes (
  id                          INT AUTO_INCREMENT PRIMARY KEY,
  solicitante_id              INT NOT NULL,
  ajuda_tipo_id               INT,
  resumo_caso                 TEXT,
  data_solicitacao            DATETIME DEFAULT CURRENT_TIMESTAMP,
  status                      VARCHAR(20) DEFAULT 'Aberto',
  created_by                  VARCHAR(100)
);


/* ============================
   Tabela: familiares
   (ligados ao solicitante)
============================ */
CREATE TABLE familiares (
  id                          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  solicitante_id              INT UNSIGNED NOT NULL,

  nome                        VARCHAR(180) NOT NULL,
  data_nascimento             DATE NULL,
  parentesco                  VARCHAR(60) NULL,
  escolaridade                VARCHAR(120) NULL,
  obs                         VARCHAR(255) NULL,

  created_at                  DATETIME NULL
);

CREATE INDEX idx_familiares_solicitante ON familiares (solicitante_id);


/* ============================
   Tabela: solicitante_documentos
============================ */
CREATE TABLE solicitante_documentos (
  id                          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  solicitante_id              INT UNSIGNED NOT NULL,

  arquivo_path                VARCHAR(255) NOT NULL,
  original_name               VARCHAR(255) NOT NULL,
  mime_type                   VARCHAR(120) NULL,
  size_bytes                  BIGINT NULL,

  created_at                  DATETIME NULL
);

/* Catálogo de benefícios/ajudas (o que a SEMAS oferece) */
CREATE TABLE ajudas_tipos (
  id                          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nome                        VARCHAR(120) NOT NULL,
  categoria                   VARCHAR(60)  NULL,
  descricao                   TEXT         NULL,
  valor_padrao                DECIMAL(10,2) NULL,
  periodicidade               ENUM('Única','Mensal','Trimestral','Eventual') DEFAULT 'Única',
  qtd_padrao                  INT UNSIGNED DEFAULT 1,
  doc_exigido                 VARCHAR(120) NULL,
  status                      ENUM('Ativa','Inativa') DEFAULT 'Ativa',
  created_at                  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at                  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX ix_ajudas_nome (nome),
  INDEX ix_ajudas_status (status)
);


CREATE INDEX idx_docs_solicitante ON solicitante_documentos (solicitante_id);
CREATE INDEX idx_docs_created ON solicitante_documentos (created_at);

/* ============================
   Tabela: ajudas_entregas
   (inclui pessoa_cpf)
============================ */
CREATE TABLE ajudas_entregas (
  id                          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

  ajuda_tipo_id               INT UNSIGNED NOT NULL,   -- ref. ajudas_tipos.id (lógico)
  pessoa_id                   INT UNSIGNED NOT NULL,   -- ref. solicitantes.id (lógico)
  pessoa_cpf                  CHAR(11) NULL,           -- CPF do solicitante no momento da entrega
  familia_id                  INT UNSIGNED NULL,       -- ref. familiares.id (lógico)

  data_entrega                DATE NOT NULL,
  hora_entrega                TIME NULL,               -- ✅ hora da entrega (tempo real)

  quantidade                  INT NOT NULL,
  valor_aplicado              DECIMAL(12,2) NULL,

  responsavel                 VARCHAR(160) NULL,
  observacao                  TEXT NULL,

  foto_path                   VARCHAR(255) NULL,       -- ✅ caminho do arquivo (uploads/fotos/...)
  foto_mime                   VARCHAR(50) NULL, 
  entregue                    VARCHAR(3) NOT NULL,
       

  created_at                  DATETIME NULL
);

CREATE INDEX idx_entregas_tipo        ON ajudas_entregas (ajuda_tipo_id);
CREATE INDEX idx_entregas_pessoa      ON ajudas_entregas (pessoa_id);
CREATE INDEX idx_entregas_pessoa_cpf  ON ajudas_entregas (pessoa_cpf);
CREATE INDEX idx_entregas_familia     ON ajudas_entregas (familia_id);
CREATE INDEX idx_entregas_data        ON ajudas_entregas (data_entrega);
CREATE INDEX idx_entregas_data_hora   ON ajudas_entregas (data_entrega, hora_entrega);



