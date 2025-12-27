CREATE TABLE `categorias` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `feira_id` tinyint(3) UNSIGNED NOT NULL,
  `nome` varchar(120) NOT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp(),
  `atualizado_em` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `categorias` (`id`, `feira_id`, `nome`, `ativo`, `criado_em`, `atualizado_em`) VALUES
(1, 1, 'Derivados da Mandioca', 1, '2025-12-27 18:36:44', NULL),
(2, 1, 'Farinhas de Mandioca', 1, '2025-12-27 18:36:44', NULL),
(3, 1, 'Goma e Polvilho', 1, '2025-12-27 18:36:44', NULL),
(4, 1, 'Subprodutos da Mandioca', 1, '2025-12-27 18:36:44', NULL);

CREATE TABLE `comunidades` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `feira_id` tinyint(3) UNSIGNED NOT NULL,
  `nome` varchar(160) NOT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `observacao` varchar(255) DEFAULT NULL,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp(),
  `atualizado_em` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `comunidades` (`id`, `feira_id`, `nome`, `ativo`, `observacao`, `criado_em`, `atualizado_em`) VALUES
(1, 1, 'Comunidade São Francisco', 1, NULL, '2025-12-27 18:36:11', NULL),
(2, 1, 'Comunidade Nova Esperança', 1, NULL, '2025-12-27 18:36:11', NULL),
(3, 1, 'Comunidade Santa Luzia', 1, NULL, '2025-12-27 18:36:11', NULL),
(4, 1, 'Comunidade Boa Vista', 1, NULL, '2025-12-27 18:36:11', NULL);

CREATE TABLE `fechamento_dia` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `feira_id` tinyint(3) UNSIGNED NOT NULL,
  `data_ref` date NOT NULL,
  `qtd_vendas` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `total_dia` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total_dinheiro` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total_pix` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total_cartao` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total_outros` decimal(10,2) NOT NULL DEFAULT 0.00,
  `observacao` varchar(255) DEFAULT NULL,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp(),
  `atualizado_em` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `feiras` (
  `id` tinyint(3) UNSIGNED NOT NULL,
  `codigo` varchar(30) NOT NULL,
  `nome` varchar(120) NOT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp(),
  `atualizado_em` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `perfis` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `codigo` varchar(50) NOT NULL,
  `nome` varchar(100) NOT NULL,
  `descricao` varchar(255) DEFAULT NULL,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `produtores` (`id`, `feira_id`, `nome`, `contato`, `comunidade_id`, `documento`, `ativo`, `observacao`, `criado_em`, `atualizado_em`) VALUES
(1, 1, 'João Batista da Silva', '92991112222', 1, NULL, 1, 'Produtor de farinha tradicional', '2025-12-27 18:36:59', NULL),
(2, 1, 'Maria do Socorro Lima', '92992223333', 2, NULL, 1, 'Produção artesanal de goma', '2025-12-27 18:36:59', NULL),
(3, 1, 'José Raimundo Pereira', '92993334444', 3, NULL, 1, 'Especialista em tucupi', '2025-12-27 18:36:59', NULL),
(4, 1, 'Antônia Alves Costa', '92994445555', 4, NULL, 1, 'Venda de derivados diversos', '2025-12-27 18:36:59', NULL);

CREATE TABLE `produtos` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `feira_id` tinyint(3) UNSIGNED NOT NULL,
  `nome` varchar(160) NOT NULL,
  `categoria_id` bigint(20) UNSIGNED DEFAULT NULL,
  `unidade_id` bigint(20) UNSIGNED DEFAULT NULL,
  `produtor_id` bigint(20) UNSIGNED DEFAULT NULL,
  `preco_referencia` decimal(10,2) DEFAULT NULL,
  `custo_referencia` decimal(10,2) DEFAULT NULL,
  `codigo_interno` varchar(60) DEFAULT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `observacao` varchar(255) DEFAULT NULL,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp(),
  `atualizado_em` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `produtos` (`id`, `feira_id`, `nome`, `categoria_id`, `unidade_id`, `produtor_id`, `preco_referencia`, `custo_referencia`, `codigo_interno`, `ativo`, `observacao`, `criado_em`, `atualizado_em`) VALUES
(1, 1, 'Farinha d’Água Tradicional', 2, 1, 1, 8.00, 5.50, 'FAR-001', 1, 'Farinha grossa artesanal', '2025-12-27 18:37:19', NULL),
(2, 1, 'Farinha Seca Branca', 2, 1, 1, 7.50, 5.00, 'FAR-002', 1, NULL, '2025-12-27 18:37:19', NULL),
(3, 1, 'Farinha Torrada Amarela', 2, 1, 4, 9.00, 6.20, 'FAR-003', 1, 'Vendida também por saco', '2025-12-27 18:37:19', NULL),
(4, 1, 'Goma de Tapioca Fresca', 3, 1, 2, 6.00, 3.80, 'GOM-001', 1, NULL, '2025-12-27 18:37:19', NULL),
(5, 1, 'Polvilho Doce', 3, 1, 2, 6.50, 4.00, 'GOM-002', 1, NULL, '2025-12-27 18:37:19', NULL),
(6, 1, 'Polvilho Azedo', 3, 1, 2, 7.00, 4.50, 'GOM-003', 1, 'Fermentado naturalmente', '2025-12-27 18:37:19', NULL),
(7, 1, 'Tucupi Amarelo', 4, 3, 3, 5.00, 3.00, 'SUB-001', 1, 'Vendido por litro', '2025-12-27 18:37:19', NULL),
(8, 1, 'Tucupi Preto', 4, 3, 3, 6.00, 3.50, 'SUB-002', 1, NULL, '2025-12-27 18:37:19', NULL),
(9, 1, 'Maniçoba (massa)', 4, 1, 3, 4.50, 2.80, 'SUB-003', 1, 'Pré-cozida', '2025-12-27 18:37:19', NULL),
(10, 1, 'Beiju Tradicional', 1, 2, 4, 2.50, 1.20, 'DER-001', 1, 'Unidade', '2025-12-27 18:37:19', NULL),
(11, 1, 'Beiju com Coco', 1, 2, 4, 3.00, 1.50, 'DER-002', 1, NULL, '2025-12-27 18:37:19', NULL),
(12, 1, 'Tapioca Pronta', 1, 2, 4, 3.50, 1.80, 'DER-003', 1, NULL, '2025-12-27 18:37:19', NULL),
(13, 1, 'Massa de Mandioca Crúa', 4, 1, 3, 3.00, 1.90, 'MAS-001', 1, NULL, '2025-12-27 18:37:19', NULL),
(14, 1, 'Massa de Mandioca Lavada', 4, 1, 3, 3.50, 2.10, 'MAS-002', 1, NULL, '2025-12-27 18:37:19', NULL);

CREATE TABLE `unidades` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `feira_id` tinyint(3) UNSIGNED NOT NULL,
  `nome` varchar(80) NOT NULL,
  `sigla` varchar(20) DEFAULT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp(),
  `atualizado_em` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `unidades` (`id`, `feira_id`, `nome`, `sigla`, `ativo`, `criado_em`, `atualizado_em`) VALUES
(1, 1, 'Quilo', 'kg', 1, '2025-12-27 18:36:26', NULL),
(2, 1, 'Unidade', 'un', 1, '2025-12-27 18:36:26', NULL),
(3, 1, 'Litro', 'L', 1, '2025-12-27 18:36:26', NULL),
(4, 1, 'Saco', 'sc', 1, '2025-12-27 18:36:26', NULL),
(5, 1, 'Pacote', 'pct', 1, '2025-12-27 18:36:26', NULL);

CREATE TABLE `usuarios` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `nome` varchar(150) NOT NULL,
  `email` varchar(190) NOT NULL,
  `senha_hash` varchar(255) NOT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `ultimo_login_em` datetime DEFAULT NULL,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp(),
  `atualizado_em` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `usuario_perfis` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `usuario_id` bigint(20) UNSIGNED NOT NULL,
  `perfil_id` bigint(20) UNSIGNED NOT NULL,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `vendas` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `feira_id` tinyint(3) UNSIGNED NOT NULL,
  `data_hora` datetime NOT NULL DEFAULT current_timestamp(),
  `forma_pagamento` varchar(20) NOT NULL,
  `total` decimal(10,2) NOT NULL DEFAULT 0.00,
  `status` varchar(20) NOT NULL DEFAULT 'ABERTA',
  `observacao` varchar(255) DEFAULT NULL,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp(),
  `atualizado_em` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `venda_itens` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `feira_id` tinyint(3) UNSIGNED NOT NULL,
  `venda_id` bigint(20) UNSIGNED NOT NULL,
  `produto_id` bigint(20) UNSIGNED DEFAULT NULL,
  `descricao_livre` varchar(160) DEFAULT NULL,
  `quantidade` decimal(10,3) NOT NULL DEFAULT 1.000,
  `valor_unitario` decimal(10,2) NOT NULL DEFAULT 0.00,
  `subtotal` decimal(10,2) NOT NULL DEFAULT 0.00,
  `observacao` varchar(255) DEFAULT NULL,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
