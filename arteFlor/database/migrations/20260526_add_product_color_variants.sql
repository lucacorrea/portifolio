CREATE TABLE IF NOT EXISTS produto_cores (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  produto_id BIGINT UNSIGNED NOT NULL,
  nome VARCHAR(80) NOT NULL,
  slug VARCHAR(100) NOT NULL,
  hex CHAR(7) NOT NULL DEFAULT '#FFFFFF',
  imagem_url TEXT NULL,
  estoque INT NOT NULL DEFAULT 0,
  ativo TINYINT(1) NOT NULL DEFAULT 1,
  ordem INT UNSIGNED NOT NULL DEFAULT 0,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_produto_cores_produto_slug (produto_id, slug),
  KEY idx_produto_cores_produto (produto_id),
  KEY idx_produto_cores_ativo (ativo),
  CONSTRAINT fk_produto_cores_produto
    FOREIGN KEY (produto_id) REFERENCES produtos (id)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT chk_produto_cores_estoque CHECK (estoque >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE pedido_itens
  ADD COLUMN produto_cor_id BIGINT UNSIGNED NULL AFTER produto_id,
  ADD COLUMN produto_cor_nome VARCHAR(80) NULL AFTER produto_categoria,
  ADD COLUMN produto_cor_hex CHAR(7) NULL AFTER produto_cor_nome,
  ADD COLUMN produto_cor_imagem TEXT NULL AFTER produto_cor_hex,
  ADD KEY idx_pedido_itens_cor (produto_cor_id),
  ADD CONSTRAINT fk_pedido_itens_cor
    FOREIGN KEY (produto_cor_id) REFERENCES produto_cores (id)
    ON UPDATE CASCADE ON DELETE SET NULL;

ALTER TABLE estoque_movimentacoes
  ADD COLUMN produto_cor_id BIGINT UNSIGNED NULL AFTER produto_id,
  ADD KEY idx_estoque_mov_cor (produto_cor_id),
  ADD CONSTRAINT fk_estoque_mov_cor
    FOREIGN KEY (produto_cor_id) REFERENCES produto_cores (id)
    ON UPDATE CASCADE ON DELETE SET NULL;
