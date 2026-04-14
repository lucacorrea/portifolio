CREATE TABLE caixas_peca (
  id bigint(20) UNSIGNED NOT NULL,
  empresa_cnpj varchar(20) NOT NULL,
  tipo enum(individual, compartilhado) NOT NULL,
  terminal varchar(60) DEFAULT NULL,
  aberto_por_cpf varchar(20) DEFAULT NULL,
  aberto_em datetime NOT NULL DEFAULT current_timestamp(),
  saldo_inicial decimal(12, 2) NOT NULL DEFAULT 0.00,
  status enum(aberto, fechado) NOT NULL DEFAULT aberto,
  fechado_por_cpf varchar(20) DEFAULT NULL,
  fechado_em datetime DEFAULT NULL,
  observacoes text DEFAULT NULL
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- --------------------------------------------------------
--
-- Estrutura para tabela caixa_movimentos_peca
--
CREATE TABLE caixa_movimentos_peca (
  id bigint(20) UNSIGNED NOT NULL,
  empresa_cnpj varchar(20) NOT NULL,
  caixa_id bigint(20) UNSIGNED NOT NULL,
  tipo enum(entrada, saida) NOT NULL,
  forma_pagamento varchar(40) DEFAULT NULL,
  valor decimal(12, 2) NOT NULL,
  descricao varchar(255) DEFAULT NULL,
  criado_em timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- --------------------------------------------------------
--
-- Estrutura para tabela caixa_participantes_peca
--
CREATE TABLE caixa_participantes_peca (
  id bigint(20) UNSIGNED NOT NULL,
  caixa_id bigint(20) UNSIGNED NOT NULL,
  empresa_cnpj varchar(20) NOT NULL,
  operador_cpf varchar(20) NOT NULL,
  operador_nome varchar(150) DEFAULT NULL,
  entrou_em datetime NOT NULL DEFAULT current_timestamp(),
  saiu_em datetime DEFAULT NULL,
  ativo tinyint(1) NOT NULL DEFAULT 1
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_general_ci;

-- --------------------------------------------------------
--
-- Estrutura para tabela categorias_lavagem_peca
--
CREATE TABLE categorias_lavagem_peca (
  id bigint(20) UNSIGNED NOT NULL,
  empresa_cnpj varchar(20) NOT NULL,
  nome varchar(120) NOT NULL,
  descricao text DEFAULT NULL,
  ativo tinyint(1) NOT NULL DEFAULT 1,
  criado_em timestamp NULL DEFAULT current_timestamp()
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE categorias_produto_peca (
  id bigint(20) UNSIGNED NOT NULL,
  empresa_cnpj varchar(20) NOT NULL,
  nome varchar(120) NOT NULL,
  criado_em timestamp NULL DEFAULT current_timestamp()
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE empresas_peca (
  id bigint(20) UNSIGNED NOT NULL,
  cnpj varchar(20) NOT NULL,
  nome_fantasia varchar(150) NOT NULL,
  razao_social varchar(200) DEFAULT NULL,
  telefone varchar(20) DEFAULT NULL,
  email varchar(150) DEFAULT NULL,
  endereco text DEFAULT NULL,
  cidade varchar(100) DEFAULT NULL,
  estado char(2) DEFAULT NULL,
  cep varchar(10) DEFAULT NULL,
  status enum(ativa, inativa, suspensa) NOT NULL DEFAULT ativa,
  criado_em timestamp NULL DEFAULT current_timestamp()
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE fornecedores_peca (
  id bigint(20) UNSIGNED NOT NULL,
  empresa_cnpj varchar(20) NOT NULL,
  nome varchar(180) NOT NULL,
  cnpj_cpf varchar(20) DEFAULT NULL,
  telefone varchar(20) DEFAULT NULL,
  email varchar(150) DEFAULT NULL,
  endereco text DEFAULT NULL,
  cidade varchar(100) DEFAULT NULL,
  estado char(2) DEFAULT NULL,
  cep varchar(10) DEFAULT NULL,
  obs text DEFAULT NULL,
  ativo tinyint(1) NOT NULL DEFAULT 1,
  criado_em timestamp NULL DEFAULT current_timestamp()
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE lavadores_peca (
  id bigint(20) UNSIGNED NOT NULL,
  empresa_cnpj varchar(20) NOT NULL,
  nome varchar(150) NOT NULL,
  cpf varchar(20) DEFAULT NULL,
  telefone varchar(20) DEFAULT NULL,
  email varchar(150) DEFAULT NULL,
  ativo tinyint(1) NOT NULL DEFAULT 1,
  criado_em timestamp NULL DEFAULT current_timestamp()
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE lavagens_peca (
  id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  empresa_cnpj VARCHAR(20) NOT NULL,
  lavador_cpf VARCHAR(20) NOT NULL,
  placa VARCHAR(10) DEFAULT NULL,
  modelo VARCHAR(120) DEFAULT NULL,
  cor VARCHAR(30) DEFAULT NULL,
  categoria_id BIGINT(20) UNSIGNED DEFAULT NULL,
  categoria_nome VARCHAR(120) DEFAULT NULL,
  valor DECIMAL(12,2) NOT NULL,
  forma_pagamento VARCHAR(40) DEFAULT 'dinheiro',
  status ENUM('aberta','concluida','cancelada') NOT NULL DEFAULT 'aberta',
  checkin_at DATETIME DEFAULT NULL,
  checkout_at DATETIME DEFAULT NULL,
  observacoes TEXT DEFAULT NULL,
  criado_em TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE lavagem_adicionais_peca (
  id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  lavagem_id BIGINT(20) UNSIGNED NOT NULL,
  adicional_id BIGINT(20) UNSIGNED DEFAULT NULL,
  nome VARCHAR(120) NOT NULL,
  valor DECIMAL(12,2) NOT NULL,
  criado_em TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (lavagem_id) REFERENCES lavagens_peca(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE adicionais_peca (
  id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  nome VARCHAR(120) NOT NULL,
  valor DECIMAL(12,2) NOT NULL,
  ativo TINYINT(1) NOT NULL DEFAULT 1,
  criado_em TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (id)
) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;

CREATE TABLE mov_estoque_peca (
  id bigint(20) UNSIGNED NOT NULL,
  empresa_cnpj varchar(20) NOT NULL,
  produto_id bigint(20) UNSIGNED NOT NULL,
  tipo enum(entrada, saida, ajuste) NOT NULL,
  qtd decimal(12, 3) NOT NULL,
  origem enum(compra, venda, ajuste, os) NOT NULL,
  ref_id bigint(20) UNSIGNED DEFAULT NULL,
  usuario_cpf varchar(20) DEFAULT NULL,
  criado_em timestamp NULL DEFAULT current_timestamp()
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE orcamentos_peca (
  id bigint(20) UNSIGNED NOT NULL,
  empresa_cnpj varchar(20) NOT NULL,
  numero int(10) UNSIGNED NOT NULL,
  cliente_nome varchar(150) DEFAULT NULL,
  cliente_telefone varchar(20) DEFAULT NULL,
  cliente_email varchar(150) DEFAULT NULL,
  validade date DEFAULT NULL,
  status enum(aberto, aprovado, rejeitado, expirado) NOT NULL DEFAULT aberto,
  observacoes text DEFAULT NULL,
  total_bruto decimal(12, 2) NOT NULL DEFAULT 0.00,
  desconto decimal(12, 2) NOT NULL DEFAULT 0.00,
  total_liquido decimal(12, 2) NOT NULL DEFAULT 0.00,
  criado_em timestamp NULL DEFAULT current_timestamp()
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE orcamento_itens_peca (
  id bigint(20) UNSIGNED NOT NULL,
  orcamento_id bigint(20) UNSIGNED NOT NULL,
  item_tipo enum(produto, servico) NOT NULL,
  item_id bigint(20) UNSIGNED DEFAULT NULL,
  descricao varchar(255) NOT NULL,
  qtd decimal(12, 3) NOT NULL DEFAULT 1.000,
  valor_unit decimal(12, 2) NOT NULL DEFAULT 0.00,
  valor_total decimal(12, 2) NOT NULL DEFAULT 0.00
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS produtos_peca (
  id BIGINT(20) UNSIGNED NOT NULL,
  empresa_cnpj VARCHAR(20) NOT NULL,
  -- relacionamento
  categoria_id BIGINT(20) UNSIGNED DEFAULT NULL,
  fornecedor_id BIGINT(20) UNSIGNED DEFAULT NULL,
  -- identificação
  nome VARCHAR(180) NOT NULL,
  sku VARCHAR(60) DEFAULT NULL,
  ean VARCHAR(20) DEFAULT NULL,
  marca VARCHAR(80) DEFAULT NULL,
  fabricante VARCHAR(120) DEFAULT NULL,
  unidade VARCHAR(10) NOT NULL DEFAULT UN,
  localizacao VARCHAR(60) DEFAULT NULL,
  -- fiscais/códigos
  ncm VARCHAR(10) DEFAULT NULL,
  cest VARCHAR(10) DEFAULT NULL,
  cfop_venda VARCHAR(10) DEFAULT NULL,
  origem TINYINT(1) DEFAULT NULL,
  -- 0/1/2...
  cst_icms VARCHAR(5) DEFAULT NULL,
  -- ex.: 102/060
  cst_piscofins VARCHAR(3) DEFAULT NULL,
  -- ex.: 01/04/06
  -- preços
  preco_compra DECIMAL(12, 2) DEFAULT 0.00,
  -- usado para calcular preço 1 (venda)
  margem DECIMAL(9, 2) DEFAULT 0.00,
  -- %
  preco_venda DECIMAL(12, 2) NOT NULL,
  -- preço 1 (obrigatório)
  preco2 DECIMAL(12, 2) DEFAULT NULL,
  preco3 DECIMAL(12, 2) DEFAULT NULL,
  preco4 DECIMAL(12, 2) DEFAULT NULL,
  -- estoque
  estoque_inicial DECIMAL(12, 3) DEFAULT 0.000,
  estoque_atual DECIMAL(12, 3) NOT NULL DEFAULT 0.000,
  estoque_minimo DECIMAL(12, 3) NOT NULL DEFAULT 0.000,
  -- rastreabilidade
  controla_lote TINYINT(1) NOT NULL DEFAULT 0,
  controla_serial TINYINT(1) NOT NULL DEFAULT 0,
  lote VARCHAR(60) DEFAULT NULL,
  numero_serie VARCHAR(60) DEFAULT NULL,
  validade_lote DATE DEFAULT NULL,
  -- garantia/validade do produto
  garantia_meses SMALLINT DEFAULT NULL,
  validade_data DATE DEFAULT NULL,
  garantia_obs VARCHAR(200) DEFAULT NULL,
  -- descrição
  descricao TEXT NULL,
  -- status
  ativo TINYINT(1) NOT NULL DEFAULT 1,
  criado_em TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP(),
  -- chaves/índices
  PRIMARY KEY (id),
  KEY idx_empresa (empresa_cnpj),
  KEY idx_categoria (categoria_id),
  KEY idx_fornecedor (fornecedor_id),
  KEY idx_nome (empresa_cnpj, nome),
  KEY idx_ncm (empresa_cnpj, ncm),
  KEY idx_cest (empresa_cnpj, cest),
  UNIQUE KEY uk_sku (empresa_cnpj, sku),
  UNIQUE KEY uk_ean (empresa_cnpj, ean)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;


CREATE TABLE solicitacoes_empresas_peca (
  id bigint(20) UNSIGNED NOT NULL,
  nome_fantasia varchar(150) NOT NULL,
  cnpj varchar(20) DEFAULT NULL,
  telefone varchar(20) DEFAULT NULL,
  email varchar(150) DEFAULT NULL,
  proprietario_nome varchar(150) NOT NULL,
  proprietario_email varchar(150) NOT NULL,
  proprietario_senha_hash varchar(255) DEFAULT NULL,
  status enum(pendente, aprovada, recusada) NOT NULL DEFAULT pendente,
  token_aprovacao varchar(64) DEFAULT NULL,
  criado_em timestamp NULL DEFAULT current_timestamp()
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

INSERT INTO
  solicitacoes_empresas_peca (
    id,
    nome_fantasia,
    cnpj,
    telefone,
    email,
    proprietario_nome,
    proprietario_email,
    proprietario_senha_hash,
    status,
    token_aprovacao,
    criado_em
  )
VALUES
  (
    4,
    Junior,
    12345678901234,
    NULL,
    NULL,
    Junior,
    lucasscorrea396@gmail.com,
    $2y$10$7E9MTj1HjUaoMWY5b6zQQ.jzP3pltHd9k.5LxXy7bd0ACP0AwUJiu,
    aprovada,
    NULL,
    2025-08-24 19:24:29
  );

CREATE TABLE usuarios_peca (
  id bigint(20) UNSIGNED NOT NULL,
  empresa_cnpj varchar(20) DEFAULT NULL,
  nome varchar(120) NOT NULL,
  email varchar(150) NOT NULL,
  cpf varchar(20) DEFAULT NULL,
  telefone varchar(20) DEFAULT NULL,
  senha varchar(255) NOT NULL,
  perfil enum(super_admin, dono, funcionario) NOT NULL DEFAULT funcionario,
  tipo_funcionario enum(
    lavajato,
    autopeca,
    administrativo,
    caixa,
    estoque
  ) DEFAULT lavajato,
  status tinyint(1) NOT NULL DEFAULT 1,
  precisa_redefinir_senha tinyint(1) NOT NULL DEFAULT 0,
  senha_atualizada_em datetime DEFAULT NULL,
  ultimo_login_em datetime DEFAULT NULL,
  falhas_login int(10) UNSIGNED NOT NULL DEFAULT 0,
  criado_em timestamp NULL DEFAULT current_timestamp()
);

CREATE TABLE usuarios_redefinicao_senha_peca (
  id bigint(20) UNSIGNED NOT NULL,
  usuario_id bigint(20) UNSIGNED DEFAULT NULL,
  email varchar(150) NOT NULL,
  token varchar(64) NOT NULL,
  otp varchar(6) DEFAULT NULL,
  expiracao datetime NOT NULL,
  usado_em datetime DEFAULT NULL,
  ip_solicitante varchar(45) DEFAULT NULL,
  user_agent varchar(255) DEFAULT NULL,
  criado_em timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE vendas_peca (
  id bigint(20) UNSIGNED NOT NULL,
  empresa_cnpj varchar(20) NOT NULL,
  vendedor_cpf varchar(20) NOT NULL,
  origem enum(balcao, lavajato, orcamento) NOT NULL DEFAULT balcao,
  status enum(aberta, fechada, cancelada) NOT NULL DEFAULT fechada,
  total_bruto decimal(12, 2) NOT NULL DEFAULT 0.00,
  desconto decimal(12, 2) NOT NULL DEFAULT 0.00,
  total_liquido decimal(12, 2) NOT NULL DEFAULT 0.00,
  forma_pagamento varchar(40) DEFAULT dinheiro,
  criado_em timestamp NULL DEFAULT current_timestamp()
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- --------------------------------------------------------
--
-- Estrutura para tabela venda_itens_peca
--
CREATE TABLE venda_itens_peca (
  id bigint(20) UNSIGNED NOT NULL,
  venda_id bigint(20) UNSIGNED NOT NULL,
  item_tipo enum(produto, servico) NOT NULL,
  item_id bigint(20) UNSIGNED NOT NULL,
  descricao varchar(255) NOT NULL,
  qtd decimal(12, 3) NOT NULL DEFAULT 1.000,
  valor_unit decimal(12, 2) NOT NULL,
  valor_total decimal(12, 2) NOT NULL
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS lavjato_config_peca (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  empresa_cnpj VARCHAR(20) NOT NULL,
  utilidades_pct DECIMAL(5, 2) NOT NULL DEFAULT 0.00,
  -- % para água+luz (aplicado sobre o total)
  comissao_lavador_pct DECIMAL(5, 2) NOT NULL DEFAULT 0.00,
  -- % opcional para comissão dos lavadores (se quiser usar)
  permitir_publico_qr TINYINT(1) NOT NULL DEFAULT 1,
  -- 1=sim, 0=não (QR público)
  imprimir_auto TINYINT(1) NOT NULL DEFAULT 0,
  -- 1=imprimir automaticamente na nota
  forma_pagamento_padrao VARCHAR(30) DEFAULT dinheiro,
  obs TEXT DEFAULT NULL,
  atualizado_em TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  criado_em TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_cfg_emp (empresa_cnpj)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS clientes_peca (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  empresa_cnpj VARCHAR(20) NOT NULL,
  nome VARCHAR(150) NOT NULL,
  cpf VARCHAR(20) DEFAULT NULL,
  email VARCHAR(150) DEFAULT NULL,
  telefone VARCHAR(25) DEFAULT NULL,
  endereco TEXT DEFAULT NULL,
  cidade VARCHAR(100) DEFAULT NULL,
  estado CHAR(2) DEFAULT NULL,
  cep VARCHAR(10) DEFAULT NULL,
  obs TEXT DEFAULT NULL,
  ativo TINYINT(1) NOT NULL DEFAULT 1,
  criado_em TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_cli_emp (empresa_cnpj),
  KEY idx_cli_cpf (cpf),
  KEY idx_cli_nome (nome)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS venda_comprador_peca (
  id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  empresa_cnpj VARCHAR(20) NOT NULL,
  venda_id BIGINT(20) UNSIGNED NOT NULL,
  tipo ENUM('lavador','cliente') NOT NULL,
  ref_id BIGINT(20) UNSIGNED NOT NULL,
  nome_snapshot VARCHAR(150) DEFAULT NULL,
  criado_em TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_venda (venda_id),
  KEY idx_emp_tipo_ref (empresa_cnpj, tipo, ref_id),
  KEY idx_emp_venda (empresa_cnpj, venda_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE vales_lavadores_peca (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  empresa_cnpj VARCHAR(20) NOT NULL,
  lavador_id BIGINT UNSIGNED NOT NULL,
  lavador_nome VARCHAR(150) NOT NULL,
  valor DECIMAL(12,2) NOT NULL,
  motivo VARCHAR(255) DEFAULT NULL,
  criado_por_cpf VARCHAR(20) DEFAULT NULL,
  forma_pagamento VARCHAR(40) DEFAULT 'dinheiro' AFTER motivo;
  criado_em TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_empresa (empresa_cnpj),
  KEY idx_lavador (lavador_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
