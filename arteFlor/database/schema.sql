CREATE TABLE IF NOT EXISTS usuarios_admin (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  nome VARCHAR(140) NOT NULL,
  email VARCHAR(180) NOT NULL,
  senha_hash VARCHAR(255) NOT NULL,
  perfil ENUM('admin', 'gerente', 'operador') NOT NULL DEFAULT 'operador',
  ativo TINYINT(1) NOT NULL DEFAULT 1,
  ultimo_acesso_em DATETIME NULL,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_usuarios_admin_email (email),
  KEY idx_usuarios_admin_perfil (perfil),
  KEY idx_usuarios_admin_ativo (ativo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS categorias (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  nome VARCHAR(120) NOT NULL,
  slug VARCHAR(140) NOT NULL,
  descricao VARCHAR(255) NULL,
  icone_textual VARCHAR(60) NULL,
  cor_apoio CHAR(7) NOT NULL DEFAULT '#4F8F6B',
  ordem INT UNSIGNED NOT NULL DEFAULT 0,
  exibir_home TINYINT(1) NOT NULL DEFAULT 1,
  exibir_catalogo TINYINT(1) NOT NULL DEFAULT 1,
  priorizar_listagem TINYINT(1) NOT NULL DEFAULT 0,
  status ENUM('ativa', 'inativa') NOT NULL DEFAULT 'ativa',
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  removido_em DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uk_categorias_slug (slug),
  KEY idx_categorias_status (status),
  KEY idx_categorias_ordem (ordem)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS produtos (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  categoria_id BIGINT UNSIGNED NULL,
  sku VARCHAR(60) NOT NULL,
  nome VARCHAR(180) NOT NULL,
  slug VARCHAR(200) NOT NULL,
  descricao_curta VARCHAR(255) NULL,
  descricao_completa TEXT NULL,
  preco DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  preco_promocional DECIMAL(10,2) NULL,
  estoque INT NOT NULL DEFAULT 0,
  estoque_minimo INT NOT NULL DEFAULT 0,
  status ENUM('disponivel', 'sob_encomenda', 'inativo', 'sem_estoque') NOT NULL DEFAULT 'disponivel',
  exibir_catalogo TINYINT(1) NOT NULL DEFAULT 1,
  permitir_venda_online TINYINT(1) NOT NULL DEFAULT 1,
  disponivel_pdv TINYINT(1) NOT NULL DEFAULT 1,
  destaque TINYINT(1) NOT NULL DEFAULT 0,
  sob_encomenda TINYINT(1) NOT NULL DEFAULT 0,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  removido_em DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uk_produtos_sku (sku),
  UNIQUE KEY uk_produtos_slug (slug),
  KEY idx_produtos_categoria (categoria_id),
  KEY idx_produtos_status (status),
  KEY idx_produtos_destaque (destaque),
  KEY idx_produtos_catalogo (exibir_catalogo),
  CONSTRAINT fk_produtos_categoria
    FOREIGN KEY (categoria_id) REFERENCES categorias (id)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT chk_produtos_preco CHECK (preco >= 0),
  CONSTRAINT chk_produtos_preco_promocional CHECK (preco_promocional IS NULL OR preco_promocional >= 0),
  CONSTRAINT chk_produtos_estoque CHECK (estoque >= 0),
  CONSTRAINT chk_produtos_estoque_minimo CHECK (estoque_minimo >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS produto_imagens (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  produto_id BIGINT UNSIGNED NOT NULL,
  url TEXT NOT NULL,
  texto_alternativo VARCHAR(180) NULL,
  ordem INT UNSIGNED NOT NULL DEFAULT 0,
  principal TINYINT(1) NOT NULL DEFAULT 0,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_produto_imagens_produto (produto_id),
  KEY idx_produto_imagens_ordem (produto_id, ordem),
  CONSTRAINT fk_produto_imagens_produto
    FOREIGN KEY (produto_id) REFERENCES produtos (id)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tags (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  nome VARCHAR(80) NOT NULL,
  slug VARCHAR(100) NOT NULL,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_tags_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS produto_tags (
  produto_id BIGINT UNSIGNED NOT NULL,
  tag_id BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (produto_id, tag_id),
  KEY idx_produto_tags_tag (tag_id),
  CONSTRAINT fk_produto_tags_produto
    FOREIGN KEY (produto_id) REFERENCES produtos (id)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_produto_tags_tag
    FOREIGN KEY (tag_id) REFERENCES tags (id)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS clientes (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  nome VARCHAR(160) NOT NULL,
  email VARCHAR(180) NULL,
  telefone VARCHAR(40) NULL,
  whatsapp VARCHAR(40) NULL,
  bairro VARCHAR(120) NULL,
  perfil ENUM('novo', 'recorrente', 'especial') NOT NULL DEFAULT 'novo',
  canal_preferido ENUM('telefone', 'whatsapp', 'email') NULL,
  flores_preferidas VARCHAR(255) NULL,
  aniversario DATE NULL,
  data_importante DATE NULL,
  observacoes TEXT NULL,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  removido_em DATETIME NULL,
  PRIMARY KEY (id),
  KEY idx_clientes_nome (nome),
  KEY idx_clientes_telefone (telefone),
  KEY idx_clientes_bairro (bairro),
  KEY idx_clientes_perfil (perfil)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cliente_enderecos (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  cliente_id BIGINT UNSIGNED NOT NULL,
  apelido VARCHAR(80) NULL,
  cep VARCHAR(12) NULL,
  rua VARCHAR(180) NULL,
  numero VARCHAR(30) NULL,
  complemento VARCHAR(120) NULL,
  bairro VARCHAR(120) NOT NULL,
  cidade VARCHAR(120) NOT NULL DEFAULT 'Coari',
  estado CHAR(2) NOT NULL DEFAULT 'AM',
  referencia VARCHAR(255) NULL,
  principal TINYINT(1) NOT NULL DEFAULT 0,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_cliente_enderecos_cliente (cliente_id),
  KEY idx_cliente_enderecos_bairro (bairro),
  CONSTRAINT fk_cliente_enderecos_cliente
    FOREIGN KEY (cliente_id) REFERENCES clientes (id)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cupons (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  codigo VARCHAR(40) NOT NULL,
  campanha VARCHAR(140) NOT NULL,
  tipo_desconto ENUM('percentual', 'valor_fixo', 'frete') NOT NULL,
  valor_desconto DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  inicio_em DATETIME NULL,
  validade_em DATETIME NULL,
  uso_maximo INT UNSIGNED NULL,
  usos_realizados INT UNSIGNED NOT NULL DEFAULT 0,
  valor_minimo_pedido DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  status ENUM('ativo', 'pausado', 'encerrado', 'expirado') NOT NULL DEFAULT 'ativo',
  canal ENUM('catalogo', 'pdv', 'atendimento', 'todos') NOT NULL DEFAULT 'todos',
  exibir_checkout TINYINT(1) NOT NULL DEFAULT 1,
  aplicar_catalogo TINYINT(1) NOT NULL DEFAULT 1,
  limitar_por_categoria TINYINT(1) NOT NULL DEFAULT 0,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_cupons_codigo (codigo),
  KEY idx_cupons_status (status),
  KEY idx_cupons_validade (validade_em),
  CONSTRAINT chk_cupons_valor_desconto CHECK (valor_desconto >= 0),
  CONSTRAINT chk_cupons_valor_minimo CHECK (valor_minimo_pedido >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cupom_categorias (
  cupom_id BIGINT UNSIGNED NOT NULL,
  categoria_id BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (cupom_id, categoria_id),
  KEY idx_cupom_categorias_categoria (categoria_id),
  CONSTRAINT fk_cupom_categorias_cupom
    FOREIGN KEY (cupom_id) REFERENCES cupons (id)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_cupom_categorias_categoria
    FOREIGN KEY (categoria_id) REFERENCES categorias (id)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cupom_produtos (
  cupom_id BIGINT UNSIGNED NOT NULL,
  produto_id BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (cupom_id, produto_id),
  KEY idx_cupom_produtos_produto (produto_id),
  CONSTRAINT fk_cupom_produtos_cupom
    FOREIGN KEY (cupom_id) REFERENCES cupons (id)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_cupom_produtos_produto
    FOREIGN KEY (produto_id) REFERENCES produtos (id)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pedidos (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  codigo VARCHAR(32) NOT NULL,
  cliente_id BIGINT UNSIGNED NULL,
  cliente_nome VARCHAR(160) NOT NULL,
  cliente_contato VARCHAR(60) NOT NULL,
  origem ENUM('catalogo', 'pdv', 'atendimento') NOT NULL DEFAULT 'catalogo',
  status ENUM(
    'pedido_recebido',
    'aguardando_pagamento',
    'pagamento_confirmado',
    'em_preparo',
    'saiu_para_entrega',
    'finalizado',
    'cancelado'
  ) NOT NULL DEFAULT 'pedido_recebido',
  forma_pagamento ENUM('pix', 'dinheiro', 'cartao_presencial', 'pagamento_retirada') NOT NULL,
  status_pagamento ENUM('pendente', 'aguardando_pagamento', 'confirmado', 'cancelado', 'estornado') NOT NULL DEFAULT 'pendente',
  recebimento ENUM('entrega', 'retirada') NOT NULL DEFAULT 'entrega',
  bairro VARCHAR(120) NULL,
  endereco VARCHAR(255) NULL,
  referencia VARCHAR(255) NULL,
  data_desejada DATE NULL,
  horario_desejado TIME NULL,
  mensagem_cartao VARCHAR(500) NULL,
  observacoes TEXT NULL,
  subtotal DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  desconto_total DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  taxa_entrega DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  total DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  finalizado_em DATETIME NULL,
  cancelado_em DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uk_pedidos_codigo (codigo),
  KEY idx_pedidos_cliente (cliente_id),
  KEY idx_pedidos_status (status),
  KEY idx_pedidos_origem (origem),
  KEY idx_pedidos_criado_em (criado_em),
  KEY idx_pedidos_data_desejada (data_desejada),
  CONSTRAINT fk_pedidos_cliente
    FOREIGN KEY (cliente_id) REFERENCES clientes (id)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT chk_pedidos_subtotal CHECK (subtotal >= 0),
  CONSTRAINT chk_pedidos_desconto CHECK (desconto_total >= 0),
  CONSTRAINT chk_pedidos_taxa_entrega CHECK (taxa_entrega >= 0),
  CONSTRAINT chk_pedidos_total CHECK (total >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pedido_itens (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  pedido_id BIGINT UNSIGNED NOT NULL,
  produto_id BIGINT UNSIGNED NULL,
  produto_sku VARCHAR(60) NULL,
  produto_nome VARCHAR(180) NOT NULL,
  produto_categoria VARCHAR(120) NULL,
  quantidade INT UNSIGNED NOT NULL DEFAULT 1,
  preco_unitario DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  desconto_unitario DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  total_linha DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  mensagem_cartao VARCHAR(500) NULL,
  observacoes TEXT NULL,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_pedido_itens_pedido (pedido_id),
  KEY idx_pedido_itens_produto (produto_id),
  CONSTRAINT fk_pedido_itens_pedido
    FOREIGN KEY (pedido_id) REFERENCES pedidos (id)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_pedido_itens_produto
    FOREIGN KEY (produto_id) REFERENCES produtos (id)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT chk_pedido_itens_quantidade CHECK (quantidade > 0),
  CONSTRAINT chk_pedido_itens_preco CHECK (preco_unitario >= 0),
  CONSTRAINT chk_pedido_itens_desconto CHECK (desconto_unitario >= 0),
  CONSTRAINT chk_pedido_itens_total CHECK (total_linha >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pagamentos (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  pedido_id BIGINT UNSIGNED NOT NULL,
  forma_pagamento ENUM('pix', 'dinheiro', 'cartao_presencial', 'pagamento_retirada') NOT NULL,
  status ENUM('pendente', 'aguardando_pagamento', 'confirmado', 'cancelado', 'estornado') NOT NULL DEFAULT 'pendente',
  provedor ENUM('manual', 'pix_demo', 'gateway') NOT NULL DEFAULT 'manual',
  referencia_provedor VARCHAR(180) NULL,
  chave_pix VARCHAR(180) NULL,
  codigo_pix TEXT NULL,
  valor DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  confirmado_em DATETIME NULL,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_pagamentos_pedido (pedido_id),
  KEY idx_pagamentos_status (status),
  KEY idx_pagamentos_confirmado_em (confirmado_em),
  CONSTRAINT fk_pagamentos_pedido
    FOREIGN KEY (pedido_id) REFERENCES pedidos (id)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT chk_pagamentos_valor CHECK (valor >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pedido_status_historico (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  pedido_id BIGINT UNSIGNED NOT NULL,
  usuario_admin_id BIGINT UNSIGNED NULL,
  status_anterior VARCHAR(60) NULL,
  status_novo VARCHAR(60) NOT NULL,
  observacao VARCHAR(255) NULL,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_pedido_status_pedido (pedido_id),
  KEY idx_pedido_status_usuario (usuario_admin_id),
  CONSTRAINT fk_pedido_status_pedido
    FOREIGN KEY (pedido_id) REFERENCES pedidos (id)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_pedido_status_usuario
    FOREIGN KEY (usuario_admin_id) REFERENCES usuarios_admin (id)
    ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cupom_usos (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  cupom_id BIGINT UNSIGNED NOT NULL,
  pedido_id BIGINT UNSIGNED NULL,
  cliente_id BIGINT UNSIGNED NULL,
  valor_desconto DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  usado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_cupom_usos_pedido (cupom_id, pedido_id),
  KEY idx_cupom_usos_cliente (cliente_id),
  CONSTRAINT fk_cupom_usos_cupom
    FOREIGN KEY (cupom_id) REFERENCES cupons (id)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_cupom_usos_pedido
    FOREIGN KEY (pedido_id) REFERENCES pedidos (id)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT fk_cupom_usos_cliente
    FOREIGN KEY (cliente_id) REFERENCES clientes (id)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT chk_cupom_usos_desconto CHECK (valor_desconto >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS caixas (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  aberto_por BIGINT UNSIGNED NULL,
  fechado_por BIGINT UNSIGNED NULL,
  status ENUM('aberto', 'fechado', 'cancelado') NOT NULL DEFAULT 'aberto',
  valor_abertura DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  valor_fechamento DECIMAL(10,2) NULL,
  valor_esperado DECIMAL(10,2) NULL,
  diferenca DECIMAL(10,2) NULL,
  aberto_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  fechado_em DATETIME NULL,
  observacoes TEXT NULL,
  PRIMARY KEY (id),
  KEY idx_caixas_status (status),
  KEY idx_caixas_aberto_por (aberto_por),
  KEY idx_caixas_fechado_por (fechado_por),
  CONSTRAINT fk_caixas_aberto_por
    FOREIGN KEY (aberto_por) REFERENCES usuarios_admin (id)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT fk_caixas_fechado_por
    FOREIGN KEY (fechado_por) REFERENCES usuarios_admin (id)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT chk_caixas_valor_abertura CHECK (valor_abertura >= 0),
  CONSTRAINT chk_caixas_valor_fechamento CHECK (valor_fechamento IS NULL OR valor_fechamento >= 0),
  CONSTRAINT chk_caixas_valor_esperado CHECK (valor_esperado IS NULL OR valor_esperado >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pdv_vendas (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  pedido_id BIGINT UNSIGNED NOT NULL,
  caixa_id BIGINT UNSIGNED NULL,
  operador_id BIGINT UNSIGNED NULL,
  valor_recebido DECIMAL(10,2) NULL,
  valor_troco DECIMAL(10,2) NULL,
  status ENUM('finalizada', 'suspensa', 'cancelada') NOT NULL DEFAULT 'finalizada',
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_pdv_vendas_pedido (pedido_id),
  KEY idx_pdv_vendas_caixa (caixa_id),
  KEY idx_pdv_vendas_operador (operador_id),
  CONSTRAINT fk_pdv_vendas_pedido
    FOREIGN KEY (pedido_id) REFERENCES pedidos (id)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_pdv_vendas_caixa
    FOREIGN KEY (caixa_id) REFERENCES caixas (id)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT fk_pdv_vendas_operador
    FOREIGN KEY (operador_id) REFERENCES usuarios_admin (id)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT chk_pdv_vendas_valor_recebido CHECK (valor_recebido IS NULL OR valor_recebido >= 0),
  CONSTRAINT chk_pdv_vendas_valor_troco CHECK (valor_troco IS NULL OR valor_troco >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS estoque_movimentacoes (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  produto_id BIGINT UNSIGNED NOT NULL,
  pedido_id BIGINT UNSIGNED NULL,
  usuario_admin_id BIGINT UNSIGNED NULL,
  tipo ENUM('entrada', 'saida', 'ajuste', 'perda', 'reserva') NOT NULL,
  origem ENUM('compra', 'venda', 'correcao_interna', 'montagem_kit', 'encomenda', 'outro') NOT NULL DEFAULT 'outro',
  quantidade INT UNSIGNED NOT NULL,
  estoque_anterior INT NULL,
  estoque_novo INT NULL,
  custo_unitario DECIMAL(10,2) NULL,
  responsavel_nome VARCHAR(140) NULL,
  motivo TEXT NULL,
  status ENUM('pendente', 'concluido', 'cancelado') NOT NULL DEFAULT 'concluido',
  movimentado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_estoque_mov_produto (produto_id),
  KEY idx_estoque_mov_pedido (pedido_id),
  KEY idx_estoque_mov_tipo (tipo),
  KEY idx_estoque_mov_data (movimentado_em),
  CONSTRAINT fk_estoque_mov_produto
    FOREIGN KEY (produto_id) REFERENCES produtos (id)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_estoque_mov_pedido
    FOREIGN KEY (pedido_id) REFERENCES pedidos (id)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT fk_estoque_mov_usuario
    FOREIGN KEY (usuario_admin_id) REFERENCES usuarios_admin (id)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT chk_estoque_mov_quantidade CHECK (quantidade > 0),
  CONSTRAINT chk_estoque_mov_custo CHECK (custo_unitario IS NULL OR custo_unitario >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS configuracoes_integracao (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  chave VARCHAR(120) NOT NULL,
  valor TEXT NULL,
  descricao VARCHAR(255) NULL,
  secreto TINYINT(1) NOT NULL DEFAULT 0,
  atualizado_por BIGINT UNSIGNED NULL,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_configuracoes_integracao_chave (chave),
  KEY idx_configuracoes_integracao_usuario (atualizado_por),
  CONSTRAINT fk_configuracoes_integracao_usuario
    FOREIGN KEY (atualizado_por) REFERENCES usuarios_admin (id)
    ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS blog_posts (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  categoria VARCHAR(120) NOT NULL,
  titulo VARCHAR(180) NOT NULL,
  slug VARCHAR(200) NOT NULL,
  resumo VARCHAR(255) NULL,
  conteudo LONGTEXT NULL,
  imagem_url TEXT NULL,
  status ENUM('rascunho', 'publicado', 'arquivado') NOT NULL DEFAULT 'rascunho',
  publicado_em DATETIME NULL,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  removido_em DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uk_blog_posts_slug (slug),
  KEY idx_blog_posts_categoria (categoria),
  KEY idx_blog_posts_status (status),
  KEY idx_blog_posts_publicado_em (publicado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
