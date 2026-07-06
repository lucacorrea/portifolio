-- Migration 010 - Complementos operacionais e financeiros apos a base 009.
-- Aplicacao manual na hospedagem, apos backup validado.
-- Ordem: executar depois de 009_required_business_adjustments.sql.
-- Compatibilidade alvo: MariaDB 10.4 compartilhado, InnoDB, utf8mb4.

SET NAMES utf8mb4;

ALTER TABLE estoque_autorizacoes
    ADD COLUMN utilizada_em DATETIME NULL AFTER autorizado_em,
    ADD COLUMN movimentacao_id INT UNSIGNED NULL AFTER utilizada_em,
    ADD KEY idx_estoque_aut_utilizacao (utilizada_em, movimentacao_id),
    ADD CONSTRAINT fk_estoque_aut_movimentacao
        FOREIGN KEY (movimentacao_id) REFERENCES estoque_movimentacoes(id)
        ON UPDATE CASCADE ON DELETE SET NULL;

CREATE TABLE IF NOT EXISTS vendas_avulsas (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    numero VARCHAR(30) NULL,
    cliente_id INT UNSIGNED NULL,
    subtotal DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    desconto DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    acrescimo DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    total DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    forma_pagamento ENUM('dinheiro', 'pix', 'cartao_debito', 'cartao_credito', 'transferencia', 'outro') NOT NULL,
    status ENUM('emitida', 'estornada', 'cancelada') NOT NULL DEFAULT 'emitida',
    caixa_movimentacao_id INT UNSIGNED NULL,
    criada_por INT UNSIGNED NOT NULL,
    criada_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    estornada_por INT UNSIGNED NULL,
    estornada_em DATETIME NULL,
    motivo_estorno VARCHAR(255) NULL,
    UNIQUE KEY uq_vendas_avulsas_numero (numero),
    KEY idx_vendas_avulsas_cliente (cliente_id),
    KEY idx_vendas_avulsas_caixa (caixa_movimentacao_id),
    KEY idx_vendas_avulsas_status_data (status, criada_em),
    CONSTRAINT fk_vendas_avulsas_cliente FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_vendas_avulsas_caixa FOREIGN KEY (caixa_movimentacao_id) REFERENCES caixa_movimentacoes(id) ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_vendas_avulsas_usuario FOREIGN KEY (criada_por) REFERENCES usuarios(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_vendas_avulsas_estorno_usuario FOREIGN KEY (estornada_por) REFERENCES usuarios(id) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS venda_avulsa_itens (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    venda_avulsa_id INT UNSIGNED NOT NULL,
    produto_id INT UNSIGNED NOT NULL,
    descricao VARCHAR(255) NOT NULL,
    unidade VARCHAR(20) NOT NULL DEFAULT 'un',
    quantidade DECIMAL(12,3) NOT NULL,
    valor_unitario DECIMAL(12,2) NOT NULL,
    desconto DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    subtotal DECIMAL(12,2) NOT NULL,
    estoque_movimentacao_id INT UNSIGNED NULL,
    ordem INT UNSIGNED NOT NULL DEFAULT 0,
    KEY idx_venda_avulsa_itens_venda (venda_avulsa_id),
    KEY idx_venda_avulsa_itens_produto (produto_id),
    KEY idx_venda_avulsa_itens_movimento (estoque_movimentacao_id),
    CONSTRAINT fk_venda_avulsa_itens_venda FOREIGN KEY (venda_avulsa_id) REFERENCES vendas_avulsas(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_venda_avulsa_itens_produto FOREIGN KEY (produto_id) REFERENCES produtos(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_venda_avulsa_itens_movimento FOREIGN KEY (estoque_movimentacao_id) REFERENCES estoque_movimentacoes(id) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO permissoes (grupo, modulo, codigo, nome, descricao, ordem) VALUES
('Caixa', 'venda_avulsa', 'venda_avulsa.visualizar', 'Visualizar vendas avulsas', 'Permite acessar vendas avulsas do caixa.', 1395),
('Caixa', 'venda_avulsa', 'venda_avulsa.criar', 'Criar venda avulsa', 'Permite registrar venda avulsa com baixa de estoque e entrada no caixa.', 1396),
('Caixa', 'venda_avulsa', 'venda_avulsa.estornar', 'Estornar venda avulsa', 'Permite estornar venda avulsa preservando historico.', 1397);

INSERT IGNORE INTO perfil_permissoes (perfil_id, permissao_id)
SELECT p.id, pe.id
  FROM perfis p
  JOIN permissoes pe
 WHERE p.nome IN ('Administrador', 'Dono', 'Gerente')
   AND pe.codigo IN (
       'venda_avulsa.visualizar',
       'venda_avulsa.criar',
       'venda_avulsa.estornar'
   );
