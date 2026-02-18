-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Tempo de geração: 18/02/2026 às 14:01
-- Versão do servidor: 11.8.3-MariaDB-log
-- Versão do PHP: 7.2.34

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Banco de dados: `u784961086_pdv`
--

-- --------------------------------------------------------

--
-- Estrutura para tabela `categorias`
--

CREATE TABLE `categorias` (
  `id` int(11) NOT NULL,
  `nome` varchar(100) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Despejando dados para a tabela `categorias`
--

INSERT INTO `categorias` (`id`, `nome`, `created_at`) VALUES
(1, 'Fios e Cabos', '2026-02-14 18:51:38'),
(2, 'Iluminação', '2026-02-14 18:51:38'),
(3, 'Disjuntores', '2026-02-14 18:51:38'),
(4, 'Tomadas e Interruptores', '2026-02-14 18:51:38'),
(5, 'Ferramentas', '2026-02-14 18:51:38'),
(6, 'Eletrodutos', '2026-02-14 18:51:38');

-- --------------------------------------------------------

--
-- Estrutura para tabela `clientes`
--

CREATE TABLE `clientes` (
  `id` int(11) NOT NULL,
  `nome` varchar(100) NOT NULL,
  `cpf_cnpj` varchar(20) DEFAULT NULL,
  `ie` varchar(20) DEFAULT NULL,
  `endereco` varchar(255) DEFAULT NULL,
  `cidade` varchar(100) DEFAULT NULL,
  `estado` char(2) DEFAULT NULL,
  `tipo` enum('pessoa_fisica','pessoa_juridica') DEFAULT 'pessoa_fisica',
  `limite_credito` decimal(10,2) DEFAULT 0.00,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Despejando dados para a tabela `clientes`
--

INSERT INTO `clientes` (`id`, `nome`, `cpf_cnpj`, `ie`, `endereco`, `cidade`, `estado`, `tipo`, `limite_credito`, `created_at`) VALUES
(1, 'Cliente Balcão', '000.000.000-00', NULL, NULL, 'Coari', 'AM', 'pessoa_fisica', 0.00, '2026-02-14 18:52:42'),
(2, 'Construtora Norte', '12.345.678/0001-90', NULL, NULL, 'Coari', 'AM', 'pessoa_juridica', 0.00, '2026-02-14 18:52:42'),
(3, 'Prefeitura de Coari', '98.765.432/0001-10', NULL, NULL, 'Coari', 'AM', 'pessoa_juridica', 0.00, '2026-02-14 18:52:42');

-- --------------------------------------------------------

--
-- Estrutura para tabela `estoque`
--

CREATE TABLE `estoque` (
  `id` int(11) NOT NULL,
  `produto_id` int(11) DEFAULT NULL,
  `filial_id` int(11) DEFAULT NULL,
  `quantidade` int(11) DEFAULT 0,
  `localizacao` varchar(50) DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Despejando dados para a tabela `estoque`
--

INSERT INTO `estoque` (`id`, `produto_id`, `filial_id`, `quantidade`, `localizacao`, `updated_at`) VALUES
(1, 1, 1, 100, NULL, '2026-02-14 18:52:34'),
(2, 2, 1, 50, NULL, '2026-02-14 18:52:34'),
(3, 3, 1, 200, NULL, '2026-02-14 18:52:34'),
(4, 4, 1, 80, NULL, '2026-02-14 18:52:34'),
(5, 5, 1, 150, NULL, '2026-02-14 18:52:34'),
(6, 1, 2, 30, NULL, '2026-02-14 18:52:34'),
(7, 2, 2, 15, NULL, '2026-02-14 18:52:34');

-- --------------------------------------------------------

--
-- Estrutura para tabela `filiais`
--

CREATE TABLE `filiais` (
  `id` int(11) NOT NULL,
  `nome` varchar(100) NOT NULL,
  `endereco` varchar(255) DEFAULT NULL,
  `cidade` varchar(100) DEFAULT NULL,
  `estado` char(2) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Despejando dados para a tabela `filiais`
--

INSERT INTO `filiais` (`id`, `nome`, `endereco`, `cidade`, `estado`, `created_at`) VALUES
(1, 'Matriz Coari', NULL, 'Coari', 'AM', '2026-02-14 18:51:22'),
(2, 'Filial Codajás', NULL, 'Codajás', 'AM', '2026-02-14 18:51:22');

-- --------------------------------------------------------

--
-- Estrutura para tabela `fluxo_caixa`
--

CREATE TABLE `fluxo_caixa` (
  `id` int(11) NOT NULL,
  `filial_id` int(11) DEFAULT NULL,
  `caixa_id` int(11) DEFAULT NULL,
  `tipo` enum('abertura','fechamento','sangria','suprimento') NOT NULL,
  `valor` decimal(10,2) NOT NULL,
  `observacao` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `movimentacoes_estoque`
--

CREATE TABLE `movimentacoes_estoque` (
  `id` int(11) NOT NULL,
  `produto_id` int(11) DEFAULT NULL,
  `filial_id` int(11) DEFAULT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `tipo` enum('entrada','saida','transferencia','ajuste','venda','devolucao') DEFAULT NULL,
  `quantidade` int(11) DEFAULT NULL,
  `motivo` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `pre_vendas`
--

CREATE TABLE `pre_vendas` (
  `id` int(11) NOT NULL,
  `filial_id` int(11) DEFAULT NULL,
  `cliente_id` int(11) DEFAULT NULL,
  `vendedor_id` int(11) DEFAULT NULL,
  `total` decimal(10,2) DEFAULT NULL,
  `status` enum('aberta','finalizada','cancelada') DEFAULT 'aberta',
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `pre_venda_itens`
--

CREATE TABLE `pre_venda_itens` (
  `id` int(11) NOT NULL,
  `pre_venda_id` int(11) DEFAULT NULL,
  `produto_id` int(11) DEFAULT NULL,
  `quantidade` int(11) DEFAULT NULL,
  `preco_unitario` decimal(10,2) DEFAULT NULL,
  `subtotal` decimal(10,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `produtos`
--

CREATE TABLE `produtos` (
  `id` int(11) NOT NULL,
  `categoria_id` int(11) DEFAULT NULL,
  `nome` varchar(150) NOT NULL,
  `codigo_interno` varchar(50) DEFAULT NULL,
  `codigo_barras` varchar(50) DEFAULT NULL,
  `ncm` varchar(20) DEFAULT NULL,
  `unidade` varchar(10) DEFAULT NULL,
  `preco_custo` decimal(10,2) DEFAULT NULL,
  `preco_venda` decimal(10,2) DEFAULT NULL,
  `preco_prefeitura` decimal(10,2) DEFAULT NULL,
  `preco_avista` decimal(10,2) DEFAULT NULL,
  `imagem` varchar(255) DEFAULT NULL,
  `min_estoque` int(11) DEFAULT 10,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Despejando dados para a tabela `produtos`
--

INSERT INTO `produtos` (`id`, `categoria_id`, `nome`, `codigo_interno`, `codigo_barras`, `ncm`, `unidade`, `preco_custo`, `preco_venda`, `preco_prefeitura`, `preco_avista`, `imagem`, `min_estoque`, `created_at`) VALUES
(1, 1, 'Fio Cabo Flexível 2.5mm 100m', '000001', '7890000000001', NULL, 'UN', 90.00, 150.00, 172.50, 135.00, NULL, 10, '2026-02-14 18:51:49'),
(2, 1, 'Fio Cabo Flexível 4.0mm 100m', '000002', '7890000000002', NULL, 'UN', 168.00, 280.00, 322.00, 252.00, NULL, 10, '2026-02-14 18:51:59'),
(3, 2, 'Lâmpada LED 9W', '000003', '7890000000003', NULL, 'UN', 7.20, 12.00, 13.80, 10.80, NULL, 10, '2026-02-14 18:52:08'),
(4, 3, 'Disjuntor Unipolar 16A', '000004', '7890000000004', NULL, 'UN', 10.80, 18.00, 20.70, 16.20, NULL, 10, '2026-02-14 18:52:15'),
(5, 4, 'Tomada Simples 10A', '000005', '7890000000005', NULL, 'UN', 5.10, 8.50, 9.78, 7.65, NULL, 10, '2026-02-14 18:52:23');

-- --------------------------------------------------------

--
-- Estrutura para tabela `usuarios`
--

CREATE TABLE `usuarios` (
  `id` int(11) NOT NULL,
  `filial_id` int(11) DEFAULT NULL,
  `nome` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `senha` varchar(255) NOT NULL,
  `nivel` enum('admin','gerente','caixa','vendedor','estoque') NOT NULL,
  `ativo` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Despejando dados para a tabela `usuarios`
--

INSERT INTO `usuarios` (`id`, `filial_id`, `nome`, `email`, `senha`, `nivel`, `ativo`, `created_at`) VALUES
(1, 1, 'Administrador', 'admin@admin.com', '$2y$10$BaccQ8s.iQ1T9.3X7z.7/e/0/0/0/0/0/0/0/0/0/0/0/0/0', 'admin', 1, '2026-02-14 18:51:30'),
(2, 1, 'Gerente Coari', 'gerente@coari.com', '$2y$10$BaccQ8s.iQ1T9.3X7z.7/e/0/0/0/0/0/0/0/0/0/0/0/0/0', 'gerente', 1, '2026-02-14 18:51:30'),
(3, 1, 'Vendedor Coari', 'vendedor@coari.com', '$2y$10$BaccQ8s.iQ1T9.3X7z.7/e/0/0/0/0/0/0/0/0/0/0/0/0/0', 'vendedor', 1, '2026-02-14 18:51:30'),
(4, 1, 'Caixa Coari', 'caixa@coari.com', '$2y$10$BaccQ8s.iQ1T9.3X7z.7/e/0/0/0/0/0/0/0/0/0/0/0/0/0', 'caixa', 1, '2026-02-14 18:51:30'),
(5, 2, 'Gerente Codajás', 'gerente@codajas.com', '$2y$10$BaccQ8s.iQ1T9.3X7z.7/e/0/0/0/0/0/0/0/0/0/0/0/0/0', 'gerente', 1, '2026-02-14 18:51:30');

-- --------------------------------------------------------

--
-- Estrutura para tabela `vendas`
--

CREATE TABLE `vendas` (
  `id` int(11) NOT NULL,
  `filial_id` int(11) DEFAULT NULL,
  `cliente_id` int(11) DEFAULT NULL,
  `vendedor_id` int(11) DEFAULT NULL,
  `caixa_id` int(11) DEFAULT NULL,
  `pre_venda_id` int(11) DEFAULT NULL,
  `total` decimal(10,2) DEFAULT NULL,
  `forma_pagamento` varchar(50) DEFAULT NULL,
  `desconto` decimal(10,2) DEFAULT 0.00,
  `acrescimo` decimal(10,2) DEFAULT 0.00,
  `observacoes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `venda_itens`
--

CREATE TABLE `venda_itens` (
  `id` int(11) NOT NULL,
  `venda_id` int(11) DEFAULT NULL,
  `produto_id` int(11) DEFAULT NULL,
  `quantidade` int(11) DEFAULT NULL,
  `preco_unitario` decimal(10,2) DEFAULT NULL,
  `subtotal` decimal(10,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Índices para tabelas despejadas
--

--
-- Índices de tabela `categorias`
--
ALTER TABLE `categorias`
  ADD PRIMARY KEY (`id`);

--
-- Índices de tabela `clientes`
--
ALTER TABLE `clientes`
  ADD PRIMARY KEY (`id`);

--
-- Índices de tabela `estoque`
--
ALTER TABLE `estoque`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_estoque` (`produto_id`,`filial_id`),
  ADD KEY `filial_id` (`filial_id`);

--
-- Índices de tabela `filiais`
--
ALTER TABLE `filiais`
  ADD PRIMARY KEY (`id`);

--
-- Índices de tabela `fluxo_caixa`
--
ALTER TABLE `fluxo_caixa`
  ADD PRIMARY KEY (`id`),
  ADD KEY `filial_id` (`filial_id`),
  ADD KEY `caixa_id` (`caixa_id`);

--
-- Índices de tabela `movimentacoes_estoque`
--
ALTER TABLE `movimentacoes_estoque`
  ADD PRIMARY KEY (`id`),
  ADD KEY `produto_id` (`produto_id`),
  ADD KEY `filial_id` (`filial_id`),
  ADD KEY `usuario_id` (`usuario_id`);

--
-- Índices de tabela `pre_vendas`
--
ALTER TABLE `pre_vendas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `filial_id` (`filial_id`),
  ADD KEY `cliente_id` (`cliente_id`),
  ADD KEY `vendedor_id` (`vendedor_id`);

--
-- Índices de tabela `pre_venda_itens`
--
ALTER TABLE `pre_venda_itens`
  ADD PRIMARY KEY (`id`),
  ADD KEY `pre_venda_id` (`pre_venda_id`),
  ADD KEY `produto_id` (`produto_id`);

--
-- Índices de tabela `produtos`
--
ALTER TABLE `produtos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `codigo_interno` (`codigo_interno`),
  ADD KEY `categoria_id` (`categoria_id`);

--
-- Índices de tabela `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `filial_id` (`filial_id`);

--
-- Índices de tabela `vendas`
--
ALTER TABLE `vendas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `filial_id` (`filial_id`),
  ADD KEY `cliente_id` (`cliente_id`),
  ADD KEY `vendedor_id` (`vendedor_id`),
  ADD KEY `caixa_id` (`caixa_id`),
  ADD KEY `pre_venda_id` (`pre_venda_id`);

--
-- Índices de tabela `venda_itens`
--
ALTER TABLE `venda_itens`
  ADD PRIMARY KEY (`id`),
  ADD KEY `venda_id` (`venda_id`),
  ADD KEY `produto_id` (`produto_id`);

--
-- AUTO_INCREMENT para tabelas despejadas
--

--
-- AUTO_INCREMENT de tabela `categorias`
--
ALTER TABLE `categorias`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de tabela `clientes`
--
ALTER TABLE `clientes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de tabela `estoque`
--
ALTER TABLE `estoque`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT de tabela `filiais`
--
ALTER TABLE `filiais`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de tabela `fluxo_caixa`
--
ALTER TABLE `fluxo_caixa`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `movimentacoes_estoque`
--
ALTER TABLE `movimentacoes_estoque`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `pre_vendas`
--
ALTER TABLE `pre_vendas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `pre_venda_itens`
--
ALTER TABLE `pre_venda_itens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `produtos`
--
ALTER TABLE `produtos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de tabela `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de tabela `vendas`
--
ALTER TABLE `vendas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `venda_itens`
--
ALTER TABLE `venda_itens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Restrições para tabelas despejadas
--

--
-- Restrições para tabelas `estoque`
--
ALTER TABLE `estoque`
  ADD CONSTRAINT `estoque_ibfk_1` FOREIGN KEY (`produto_id`) REFERENCES `produtos` (`id`),
  ADD CONSTRAINT `estoque_ibfk_2` FOREIGN KEY (`filial_id`) REFERENCES `filiais` (`id`);

--
-- Restrições para tabelas `fluxo_caixa`
--
ALTER TABLE `fluxo_caixa`
  ADD CONSTRAINT `fluxo_caixa_ibfk_1` FOREIGN KEY (`filial_id`) REFERENCES `filiais` (`id`),
  ADD CONSTRAINT `fluxo_caixa_ibfk_2` FOREIGN KEY (`caixa_id`) REFERENCES `usuarios` (`id`);

--
-- Restrições para tabelas `movimentacoes_estoque`
--
ALTER TABLE `movimentacoes_estoque`
  ADD CONSTRAINT `movimentacoes_estoque_ibfk_1` FOREIGN KEY (`produto_id`) REFERENCES `produtos` (`id`),
  ADD CONSTRAINT `movimentacoes_estoque_ibfk_2` FOREIGN KEY (`filial_id`) REFERENCES `filiais` (`id`),
  ADD CONSTRAINT `movimentacoes_estoque_ibfk_3` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`);

--
-- Restrições para tabelas `pre_vendas`
--
ALTER TABLE `pre_vendas`
  ADD CONSTRAINT `pre_vendas_ibfk_1` FOREIGN KEY (`filial_id`) REFERENCES `filiais` (`id`),
  ADD CONSTRAINT `pre_vendas_ibfk_2` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`),
  ADD CONSTRAINT `pre_vendas_ibfk_3` FOREIGN KEY (`vendedor_id`) REFERENCES `usuarios` (`id`);

--
-- Restrições para tabelas `pre_venda_itens`
--
ALTER TABLE `pre_venda_itens`
  ADD CONSTRAINT `pre_venda_itens_ibfk_1` FOREIGN KEY (`pre_venda_id`) REFERENCES `pre_vendas` (`id`),
  ADD CONSTRAINT `pre_venda_itens_ibfk_2` FOREIGN KEY (`produto_id`) REFERENCES `produtos` (`id`);

--
-- Restrições para tabelas `produtos`
--
ALTER TABLE `produtos`
  ADD CONSTRAINT `produtos_ibfk_1` FOREIGN KEY (`categoria_id`) REFERENCES `categorias` (`id`);

--
-- Restrições para tabelas `usuarios`
--
ALTER TABLE `usuarios`
  ADD CONSTRAINT `usuarios_ibfk_1` FOREIGN KEY (`filial_id`) REFERENCES `filiais` (`id`);

--
-- Restrições para tabelas `vendas`
--
ALTER TABLE `vendas`
  ADD CONSTRAINT `vendas_ibfk_1` FOREIGN KEY (`filial_id`) REFERENCES `filiais` (`id`),
  ADD CONSTRAINT `vendas_ibfk_2` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`),
  ADD CONSTRAINT `vendas_ibfk_3` FOREIGN KEY (`vendedor_id`) REFERENCES `usuarios` (`id`),
  ADD CONSTRAINT `vendas_ibfk_4` FOREIGN KEY (`caixa_id`) REFERENCES `usuarios` (`id`),
  ADD CONSTRAINT `vendas_ibfk_5` FOREIGN KEY (`pre_venda_id`) REFERENCES `pre_vendas` (`id`);

--
-- Restrições para tabelas `venda_itens`
--
ALTER TABLE `venda_itens`
  ADD CONSTRAINT `venda_itens_ibfk_1` FOREIGN KEY (`venda_id`) REFERENCES `vendas` (`id`),
  ADD CONSTRAINT `venda_itens_ibfk_2` FOREIGN KEY (`produto_id`) REFERENCES `produtos` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
