CREATE TABLE `categorias` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `feira_id` tinyint(3) UNSIGNED NOT NULL,
  `nome` varchar(120) NOT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp(),
  `atualizado_em` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;


CREATE TABLE `comunidades` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `feira_id` tinyint(3) UNSIGNED NOT NULL,
  `nome` varchar(160) NOT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `observacao` varchar(255) DEFAULT NULL,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp(),
  `atualizado_em` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;


  (
    1,
    1,
    'Comunidade São Francisco',
    1,
    NULL,
    '2025-12-27 18:36:11',
    NULL
  ),
  (
    2,
    1,
    'Comunidade Nova Esperança',
    1,
    NULL,
    '2025-12-27 18:36:11',
    NULL
  ),
  (
    3,
    1,
    'Comunidade Santa Luzia',
    1,
    NULL,
    '2025-12-27 18:36:11',
    NULL
  ),
  (
    4,
    1,
    'Comunidade Boa Vista',
    1,
    NULL,
    '2025-12-27 18:36:11',
    NULL
  );

CREATE TABLE `fechamento_dia` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `feira_id` tinyint(3) UNSIGNED NOT NULL,
  `data_ref` date NOT NULL,
  `qtd_vendas` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `total_dia` decimal(10, 2) NOT NULL DEFAULT 0.00,
  `total_dinheiro` decimal(10, 2) NOT NULL DEFAULT 0.00,
  `total_pix` decimal(10, 2) NOT NULL DEFAULT 0.00,
  `total_cartao` decimal(10, 2) NOT NULL DEFAULT 0.00,
  `total_outros` decimal(10, 2) NOT NULL DEFAULT 0.00,
  `observacao` varchar(255) DEFAULT NULL,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp(),
  `atualizado_em` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE `feiras` (
  `id` tinyint(3) UNSIGNED NOT NULL,
  `codigo` varchar(30) NOT NULL,
  `nome` varchar(120) NOT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp(),
  `atualizado_em` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE `perfis` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `codigo` varchar(50) NOT NULL,
  `nome` varchar(100) NOT NULL,
  `descricao` varchar(255) DEFAULT NULL,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE `produtores` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `feira_id` tinyint(3) UNSIGNED NOT NULL,
  `nome` varchar(160) NOT NULL,
  `contato` varchar(60) DEFAULT NULL,
  `comunidade_id` bigint(20) UNSIGNED NOT NULL,
  `documento` varchar(30) DEFAULT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `observacao` varchar(255) DEFAULT NULL,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp(),
  `atualizado_em` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;



CREATE TABLE `produtos` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `feira_id` tinyint(3) UNSIGNED NOT NULL,
  `nome` varchar(160) NOT NULL,
  `categoria_id` bigint(20) UNSIGNED DEFAULT NULL,
  `unidade_id` bigint(20) UNSIGNED DEFAULT NULL,
  `produtor_id` bigint(20) UNSIGNED DEFAULT NULL,
  `preco_referencia` decimal(10, 2) DEFAULT NULL,
  `custo_referencia` decimal(10, 2) DEFAULT NULL,
  `codigo_interno` varchar(60) DEFAULT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `observacao` varchar(255) DEFAULT NULL,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp(),
  `atualizado_em` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;


CREATE TABLE `unidades` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `feira_id` tinyint(3) UNSIGNED NOT NULL,
  `nome` varchar(80) NOT NULL,
  `sigla` varchar(20) DEFAULT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp(),
  `atualizado_em` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE `usuarios` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `nome` varchar(150) NOT NULL,
  `email` varchar(190) NOT NULL,
  `senha_hash` varchar(255) NOT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `ultimo_login_em` datetime DEFAULT NULL,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp(),
  `atualizado_em` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE `usuario_perfis` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `usuario_id` bigint(20) UNSIGNED NOT NULL,
  `perfil_id` bigint(20) UNSIGNED NOT NULL,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE `vendas` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `feira_id` tinyint(3) UNSIGNED NOT NULL,
  `data_hora` datetime NOT NULL DEFAULT current_timestamp(),
  `forma_pagamento` varchar(20) NOT NULL,
  `total` decimal(10, 2) NOT NULL DEFAULT 0.00,
  `status` varchar(20) NOT NULL DEFAULT 'ABERTA',
  `observacao` varchar(255) DEFAULT NULL,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp(),
  `atualizado_em` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE `venda_itens` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `feira_id` tinyint(3) UNSIGNED NOT NULL,
  `venda_id` bigint(20) UNSIGNED NOT NULL,
  `produto_id` bigint(20) UNSIGNED DEFAULT NULL,
  `descricao_livre` varchar(160) DEFAULT NULL,
  `quantidade` decimal(10, 3) NOT NULL DEFAULT 1.000,
  `valor_unitario` decimal(10, 2) NOT NULL DEFAULT 0.00,
  `subtotal` decimal(10, 2) NOT NULL DEFAULT 0.00,
  `observacao` varchar(255) DEFAULT NULL,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;


CREATE TABLE `redefinir_senha_tokens` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `usuario_id` bigint(20) UNSIGNED DEFAULT NULL,
  `email` varchar(190) NOT NULL,
  `token_hash` varchar(255) NOT NULL,
  `codigo` varchar(10) NOT NULL,
  `expira_em` datetime NOT NULL,
  `usado_em` datetime DEFAULT NULL,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `config_relatorio` (
  `id` bigint(20) UNSIGNED AUTO_INCREMENT NOT NULL PRIMARY KEY,
  `feira_id` tinyint(3) UNSIGNED NOT NULL,
  `titulo_feira` varchar(160) NOT NULL DEFAULT '',
  `subtitulo_feira` varchar(160) DEFAULT NULL,
  `municipio` varchar(100) NOT NULL DEFAULT '',
  `estado` char(2) NOT NULL DEFAULT '',
  `secretaria` varchar(200) DEFAULT NULL,
  `logotipo_prefeitura` varchar(255) DEFAULT NULL,
  `logotipo_feira` varchar(255) DEFAULT NULL,
  `incluir_introducao` tinyint(1) NOT NULL DEFAULT 1,
  `texto_introducao` text DEFAULT NULL,
  `incluir_produtos_comercializados` tinyint(1) NOT NULL DEFAULT 1,
  `incluir_conclusao` tinyint(1) NOT NULL DEFAULT 1,
  `texto_conclusao` text DEFAULT NULL,
  `assinatura_nome` varchar(160) DEFAULT NULL,
  `assinatura_cargo` varchar(160) DEFAULT NULL,
  `mostrar_graficos` tinyint(1) NOT NULL DEFAULT 1,
  `mostrar_por_categoria` tinyint(1) NOT NULL DEFAULT 1,
  `mostrar_por_feirante` tinyint(1) NOT NULL DEFAULT 1,
  `produtos_detalhados` tinyint(1) NOT NULL DEFAULT 1,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp(),
  `atualizado_em` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE romaneio_dia (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  feira_id TINYINT UNSIGNED NOT NULL,
  data_ref DATE NOT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'ABERTO', -- ABERTO | FECHADO
  observacao VARCHAR(255) DEFAULT NULL,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP(),
  atualizado_em DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP(),
  PRIMARY KEY (id),
  UNIQUE KEY uk_romaneio (feira_id, data_ref)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE romaneio_itens (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  feira_id TINYINT UNSIGNED NOT NULL,
  romaneio_id BIGINT UNSIGNED NOT NULL,
  produtor_id BIGINT UNSIGNED NOT NULL,
  produto_id BIGINT UNSIGNED NOT NULL,

  quantidade_entrada DECIMAL(10,3) NOT NULL DEFAULT 0.000,
  preco_unitario_dia DECIMAL(10,2) NOT NULL DEFAULT 0.00,

  -- fechamento (preenchido no final do dia)
  quantidade_sobra DECIMAL(10,3) DEFAULT NULL,
  quantidade_vendida DECIMAL(10,3) DEFAULT NULL,
  total_bruto DECIMAL(10,2) DEFAULT NULL,

  observacao VARCHAR(255) DEFAULT NULL,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP(),
  atualizado_em DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP(),

  PRIMARY KEY (id),
  KEY idx_romaneio (romaneio_id),
  KEY idx_produtor (produtor_id),
  KEY idx_produto (produto_id),

  CONSTRAINT fk_ri_romaneio FOREIGN KEY (romaneio_id) REFERENCES romaneio_dia(id),
  CONSTRAINT fk_ri_produtor FOREIGN KEY (produtor_id) REFERENCES produtores(id),
  CONSTRAINT fk_ri_produto FOREIGN KEY (produto_id) REFERENCES produtos(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE romaneio_item_fotos (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  romaneio_item_id BIGINT UNSIGNED NOT NULL,
  caminho VARCHAR(255) NOT NULL,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP(),
  PRIMARY KEY (id),
  KEY idx_item (romaneio_item_id),
  CONSTRAINT fk_rif_item FOREIGN KEY (romaneio_item_id) REFERENCES romaneio_itens(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
