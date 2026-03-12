-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Tempo de geração: 12/03/2026 às 15:49
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
-- Estrutura para tabela `alertas_estoque`
--

CREATE TABLE `alertas_estoque` (
  `id` int(11) NOT NULL,
  `produto_id` int(11) NOT NULL,
  `filial_id` int(11) NOT NULL,
  `tipo` enum('reposicao') NOT NULL,
  `mensagem` text NOT NULL,
  `status` enum('ativo','resolvido') DEFAULT 'ativo',
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `audit_logs`
--

CREATE TABLE `audit_logs` (
  `id` int(11) NOT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `acao` varchar(100) NOT NULL,
  `tabela` varchar(50) DEFAULT NULL,
  `registro_id` int(11) DEFAULT NULL,
  `dados_anteriores` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`dados_anteriores`)),
  `dados_novos` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`dados_novos`)),
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `audit_logs`
--

INSERT INTO `audit_logs` (`id`, `usuario_id`, `acao`, `tabela`, `registro_id`, `dados_anteriores`, `dados_novos`, `ip_address`, `user_agent`, `created_at`) VALUES
(1, 1, 'migration_run', 'migrations', NULL, NULL, '{\"file\":\"001_create_audit_logs.sql\"}', '2803:9810:4d1b:fd10:74fe:245e:9781:188b', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-25 12:23:52'),
(2, 1, 'migration_run', 'migrations', NULL, NULL, '{\"file\":\"002_enhance_os_features.sql\"}', '2803:9810:4d1b:fd10:74fe:245e:9781:188b', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-25 12:23:52'),
(3, 1, 'migration_run', 'migrations', NULL, NULL, '{\"file\":\"003_fiscal_integration_schema.sql\"}', '2803:9810:4d1b:fd10:b0d2:c09f:a1ce:dad1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-25 18:26:19'),
(4, 1, 'migration_run', 'migrations', NULL, NULL, '{\"file\":\"004_create_settings_table.sql\"}', '2803:9810:4d1b:fd10:b0d2:c09f:a1ce:dad1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-25 18:38:23'),
(5, 1, 'migration_run', 'migrations', NULL, NULL, '{\"file\":\"005_add_filial_id_to_users.sql\"}', '2803:9810:4d1b:fd10:b0d2:c09f:a1ce:dad1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-25 19:01:44'),
(6, 1, 'abertura_caixa', 'caixas', NULL, NULL, '{\"valor\":\"05\"}', '148.227.83.24', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-03 12:32:56'),
(7, 1, 'suprimento', 'caixa_movimentacoes', NULL, NULL, '{\"valor\":\"1000\",\"motivo\":\"suprir\"}', '2803:9810:4d1b:fd10:acc0:684a:71eb:2fba', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-03 12:33:14'),
(8, 1, 'fechamento_caixa', 'caixas', 2, NULL, NULL, '2803:9810:4d1b:fd10:acc0:684a:71eb:2fba', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-03 12:34:10'),
(9, 1, 'abertura_caixa', 'caixas', NULL, NULL, '{\"valor\":\"200\"}', '148.227.123.180', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-03 13:02:41'),
(10, 1, 'sangria', 'caixa_movimentacoes', NULL, NULL, '{\"valor\":\"20\",\"motivo\":\"pediu\"}', '148.227.123.180', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-03 13:06:34'),
(11, 1, 'suprimento', 'caixa_movimentacoes', NULL, NULL, '{\"valor\":\"25\",\"motivo\":\"coca\"}', '148.227.123.180', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-03 13:06:46'),
(12, 1, 'fechamento_caixa', 'caixas', 3, NULL, NULL, '148.227.123.180', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-03 13:07:37'),
(13, 1, 'abertura_caixa', 'caixas', NULL, NULL, '{\"valor\":\"222\"}', '192.141.13.173', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-03 21:04:54'),
(14, 1, 'Venda fiado criada', 'vendas', 20, NULL, '\"{\\\"total\\\":210,\\\"entrada\\\":0,\\\"saldo_devedor\\\":210}\"', '2803:9810:4d1b:fd10:999b:7ac1:e556:8c87', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-04 14:47:48'),
(15, 1, 'Venda fiado criada', 'vendas', 21, NULL, '\"{\\\"total\\\":45,\\\"entrada\\\":0,\\\"saldo_devedor\\\":45}\"', '138.84.59.95', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-04 14:52:46'),
(16, 1, 'Venda fiado criada', 'vendas', 22, NULL, '\"{\\\"total\\\":2750,\\\"entrada\\\":0,\\\"saldo_devedor\\\":2750}\"', '2803:9810:4d1b:fd10:c9be:d7d6:7d85:dea9', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-04 15:50:27'),
(17, 1, 'Venda fiado criada', 'vendas', 23, NULL, '\"{\\\"total\\\":245,\\\"entrada\\\":0,\\\"saldo_devedor\\\":245}\"', '149.19.175.25', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-04 20:21:59'),
(18, 1, 'divergencia_caixa', 'caixas', 4, NULL, '{\"sistema\":222,\"informado\":\"3000\",\"diferenca\":2778,\"justificativa\":\"lll\"}', '2803:9810:4d1b:fd10:eded:a8ea:7ac2:a432', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-09 15:07:39'),
(19, 1, 'fechamento_caixa', 'caixas', 4, NULL, NULL, '2803:9810:4d1b:fd10:eded:a8ea:7ac2:a432', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-09 15:07:39'),
(20, 2, 'abertura_caixa', 'caixas', NULL, NULL, '{\"valor\":\"100\"}', '74.244.184.66', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-09 15:15:08'),
(21, 1, 'abertura_caixa', 'caixas', NULL, NULL, '{\"valor\":\"100\"}', '2803:9810:4d1b:fd10:eded:a8ea:7ac2:a432', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-09 15:35:49'),
(22, 1, 'suprimento', 'caixa_movimentacoes', NULL, NULL, '{\"valor\":\"1000\",\"motivo\":\"comprar arroz\",\"ip\":\"2803:9810:4d1b:fd10:eded:a8ea:7ac2:a432\",\"horario\":\"15:36:01\"}', '2803:9810:4d1b:fd10:eded:a8ea:7ac2:a432', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-09 15:36:01'),
(23, 2, 'Autorização de Movimentação de Caixa', 'caixa_movimentacoes', NULL, NULL, '{\"tipo\":\"sangria\",\"valor\":\"1000\",\"operador\":2,\"metodo\":\"codigo\"}', '2803:9810:4d1b:fd10:eded:a8ea:7ac2:a432', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-09 15:41:09'),
(24, 2, 'sangria', 'caixa_movimentacoes', NULL, NULL, '{\"valor\":\"1000\",\"motivo\":\"suprir\",\"ip\":\"2803:9810:4d1b:fd10:eded:a8ea:7ac2:a432\",\"horario\":\"15:41:09\"}', '2803:9810:4d1b:fd10:eded:a8ea:7ac2:a432', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-09 15:41:09'),
(25, 2, 'Autorização de Movimentação de Caixa', 'caixa_movimentacoes', NULL, NULL, '{\"tipo\":\"sangria\",\"valor\":\"1000000000\",\"operador\":2,\"metodo\":\"codigo\"}', '2803:9810:4d1b:fd10:117d:30e9:8ef8:c9c7', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-09 16:00:13'),
(26, 2, 'sangria', 'caixa_movimentacoes', NULL, NULL, '{\"valor\":\"1000000000\",\"motivo\":\"\",\"ip\":\"2803:9810:4d1b:fd10:117d:30e9:8ef8:c9c7\",\"horario\":\"16:00:13\"}', '2803:9810:4d1b:fd10:117d:30e9:8ef8:c9c7', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-09 16:00:13'),
(27, 2, 'Autorização de Movimentação de Caixa', 'caixa_movimentacoes', NULL, NULL, '{\"tipo\":\"suprimento\",\"valor\":\"600000000000000000\",\"operador\":2,\"metodo\":\"codigo\"}', '2803:9810:4d1b:fd10:117d:30e9:8ef8:c9c7', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-09 16:10:38'),
(28, 2, 'suprimento', 'caixa_movimentacoes', NULL, NULL, '{\"valor\":\"600000000000000000\",\"motivo\":\"\",\"ip\":\"2803:9810:4d1b:fd10:117d:30e9:8ef8:c9c7\",\"horario\":\"16:10:38\"}', '2803:9810:4d1b:fd10:117d:30e9:8ef8:c9c7', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-09 16:10:38'),
(29, 1, 'Configuração SEFAZ Global Atualizada', 'sefaz_config', NULL, NULL, NULL, '2803:9810:4d1b:fd10:557b:e1cc:c855:c761', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-10 12:02:22'),
(30, 1, 'Configuração SEFAZ Global Atualizada', 'sefaz_config', NULL, NULL, NULL, '2803:9810:4d1b:fd10:557b:e1cc:c855:c761', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-10 12:07:25'),
(31, 1, 'Configuração SEFAZ Global Atualizada', 'sefaz_config', NULL, NULL, NULL, '2803:9810:4d1b:fd10:557b:e1cc:c855:c761', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-10 12:07:43'),
(32, 1, 'Configuração SEFAZ Global Atualizada', 'sefaz_config', NULL, NULL, NULL, '2803:9810:4d1b:fd10:fdc0:d679:88ff:b0fa', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-10 12:51:48'),
(33, 1, 'Configuração SEFAZ Global Atualizada', 'sefaz_config', NULL, NULL, NULL, '2803:9810:4d1b:fd10:fdc0:d679:88ff:b0fa', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-10 12:53:34'),
(34, 1, 'Configuração SEFAZ Global Atualizada', 'sefaz_config', NULL, NULL, NULL, '2803:9810:4d1b:fd10:fdc0:d679:88ff:b0fa', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-10 14:25:03'),
(35, 2, 'Venda fiado criada', 'vendas', 24, NULL, '\"{\\\"total\\\":63.6,\\\"entrada\\\":20,\\\"saldo_devedor\\\":43.6}\"', '2803:9810:4e45:980c:75d0:a300:18b:c645', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-10 16:52:06'),
(36, 1, 'fiscal_error', 'vendas', 31, NULL, '{\"error\":\"SQLSTATE[42S02]: Base table or view not found: 1146 Table \'u784961086_pdv.venda_itens\' doesn\'t exist\"}', '45.190.21.201', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-10 20:06:27'),
(37, 1, 'sefaz_comm_error', 'vendas', NULL, NULL, '{\"error\":\"Erro HTTP 404. O servidor da SEFAZ rejeitou a requisi\\u00e7\\u00e3o. O certificado e a senha est\\u00e3o corretos, mas o conte\\u00fado ou o Mapeamento da SEFAZ pode estar inv\\u00e1lido.\"}', '45.190.21.201', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-10 20:09:40'),
(38, 1, 'fiscal_error', 'vendas', 33, NULL, '{\"error\":\"Falha na comunica\\u00e7\\u00e3o com a SEFAZ: Erro HTTP 404. O servidor da SEFAZ rejeitou a requisi\\u00e7\\u00e3o. O certificado e a senha est\\u00e3o corretos, mas o conte\\u00fado ou o Mapeamento da SEFAZ pode estar inv\\u00e1lido.\"}', '45.190.21.201', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-10 20:09:40'),
(39, 1, 'Configuração SEFAZ Global Atualizada', 'sefaz_config', NULL, NULL, NULL, '45.190.21.201', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-10 20:24:37'),
(40, 1, 'Configuração SEFAZ Global Atualizada', 'sefaz_config', NULL, NULL, NULL, '45.190.21.201', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-10 20:26:16'),
(41, 1, 'Configuração SEFAZ Global Atualizada', 'sefaz_config', NULL, NULL, NULL, '45.190.21.201', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-11 01:37:56'),
(42, 1, 'divergencia_caixa', 'caixas', 6, NULL, '{\"sistema\":3340,\"informado\":\"400000000000\",\"diferenca\":399999996660,\"justificativa\":\"ku\"}', '2803:9810:4d1b:fd10:40b:22a4:eb55:1df6', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-11 13:22:30'),
(43, 1, 'fechamento_caixa', 'caixas', 6, NULL, NULL, '2803:9810:4d1b:fd10:40b:22a4:eb55:1df6', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-11 13:22:30'),
(44, 1, 'abertura_caixa', 'caixas', NULL, NULL, '{\"valor\":\"50\"}', '148.227.90.131', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-11 13:35:04'),
(45, 1, 'migration_run', 'migrations', NULL, NULL, '{\"file\":\"007_rbac_schema.sql\"}', '2803:9810:4d1b:fd10:f037:39d3:2887:953e', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-11 14:37:47'),
(46, 1, 'migration_run', 'migrations', NULL, NULL, '{\"file\":\"008_perf_sec_hardening.sql\"}', '2803:9810:4d1b:fd10:f037:39d3:2887:953e', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-11 14:37:48'),
(47, 1, 'migration_run', 'migrations', NULL, NULL, '{\"file\":\"009_add_auth_fields_to_users.sql\"}', '2803:9810:4d1b:fd10:f037:39d3:2887:953e', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-11 14:37:48'),
(48, 1, 'migration_run', 'migrations', NULL, NULL, '{\"file\":\"011_create_cashier_tables.sql\"}', '2803:9810:4d1b:fd10:f037:39d3:2887:953e', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-11 14:37:48'),
(49, 1, 'migration_run', 'migrations', NULL, NULL, '{\"file\":\"013_create_cost_center_tables.sql\"}', '2803:9810:4d1b:fd10:f037:39d3:2887:953e', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-11 14:37:48'),
(50, 1, 'migration_run', 'migrations', NULL, NULL, '{\"file\":\"014_create_intelligence_tables.sql\"}', '2803:9810:4d1b:fd10:f037:39d3:2887:953e', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-11 14:37:48'),
(51, 1, 'migration_run', 'migrations', NULL, NULL, '{\"file\":\"016_create_autorizacoes_temporarias.sql\"}', '2803:9810:4d1b:fd10:f037:39d3:2887:953e', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-11 14:37:48'),
(52, 1, 'migration_run', 'migrations', NULL, NULL, '{\"file\":\"018_create_nfe_importadas.sql\"}', '2803:9810:4d1b:fd10:f037:39d3:2887:953e', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-11 14:37:48'),
(53, 1, 'Venda fiado criada', 'vendas', 92, NULL, '\"{\\\"total\\\":60,\\\"entrada\\\":20,\\\"saldo_devedor\\\":40}\"', '148.227.90.131', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-12 13:02:45');

-- --------------------------------------------------------

--
-- Estrutura para tabela `autorizacoes_temporarias`
--

CREATE TABLE `autorizacoes_temporarias` (
  `id` int(11) NOT NULL,
  `tipo` varchar(50) NOT NULL,
  `codigo` varchar(10) NOT NULL,
  `usuario_autorizador_id` int(11) DEFAULT NULL,
  `validade` datetime NOT NULL,
  `utilizado` tinyint(1) DEFAULT 0,
  `filial_id` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `autorizacoes_temporarias`
--

INSERT INTO `autorizacoes_temporarias` (`id`, `tipo`, `codigo`, `usuario_autorizador_id`, `validade`, `utilizado`, `filial_id`, `created_at`) VALUES
(1, 'sangria', '235649', 1, '2026-03-09 16:09:51', 1, 125, '2026-03-09 15:39:51'),
(2, '', '501530', 1, '2026-03-09 16:16:57', 0, 125, '2026-03-09 15:46:57'),
(3, '', '608825', 1, '2026-03-09 16:19:25', 0, 125, '2026-03-09 15:49:25'),
(4, '', '715644', 1, '2026-03-09 16:20:44', 0, 125, '2026-03-09 15:50:44'),
(5, '', '656414', 1, '2026-03-09 16:22:08', 0, 125, '2026-03-09 15:52:08'),
(6, '', '453022', 1, '2026-03-09 16:26:14', 0, 125, '2026-03-09 15:56:14'),
(7, 'sangria', '409034', 1, '2026-03-09 16:29:44', 1, 125, '2026-03-09 15:59:44'),
(8, '', '832738', 1, '2026-03-09 16:30:41', 0, 125, '2026-03-09 16:00:41'),
(9, '', '846480', 1, '2026-03-09 16:33:20', 0, 125, '2026-03-09 16:03:20'),
(10, '', '745380', 1, '2026-03-09 16:34:25', 0, 125, '2026-03-09 16:04:25'),
(11, 'desconto', '305359', 1, '2026-03-09 16:35:14', 0, 125, '2026-03-09 16:05:14'),
(12, '', '468352', 1, '2026-03-09 16:35:52', 0, 125, '2026-03-09 16:05:52'),
(13, 'suprimento', '536364', 1, '2026-03-09 16:40:09', 1, 125, '2026-03-09 16:10:09'),
(14, 'sangria', '290474', 1, '2026-03-11 00:04:19', 0, 125, '2026-03-10 23:34:19');

-- --------------------------------------------------------

--
-- Estrutura para tabela `caixas`
--

CREATE TABLE `caixas` (
  `id` int(11) NOT NULL,
  `filial_id` int(11) NOT NULL,
  `operador_id` int(11) NOT NULL,
  `valor_abertura` decimal(10,2) NOT NULL,
  `valor_fechamento` decimal(10,2) DEFAULT NULL,
  `status` enum('aberto','fechado') DEFAULT 'aberto',
  `data_abertura` datetime NOT NULL,
  `data_fechamento` datetime DEFAULT NULL,
  `observacao` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `caixas`
--

INSERT INTO `caixas` (`id`, `filial_id`, `operador_id`, `valor_abertura`, `valor_fechamento`, `status`, `data_abertura`, `data_fechamento`, `observacao`, `created_at`) VALUES
(1, 125, 1, 1000.00, 100.00, 'fechado', '2026-03-03 12:28:58', '2026-03-03 12:32:05', 'final do dia', '2026-03-03 12:28:58'),
(2, 125, 1, 5.00, 1005.00, 'fechado', '2026-03-03 12:32:56', '2026-03-03 12:34:10', 'final', '2026-03-03 12:32:56'),
(3, 125, 1, 200.00, 205.00, 'fechado', '2026-03-03 13:02:41', '2026-03-03 13:07:37', '', '2026-03-03 13:02:41'),
(4, 125, 1, 222.00, 3000.00, 'fechado', '2026-03-03 21:04:54', '2026-03-09 15:07:39', 'lll', '2026-03-03 21:04:54'),
(5, 125, 2, 100.00, NULL, 'aberto', '2026-03-09 15:15:08', NULL, NULL, '2026-03-09 15:15:08'),
(6, 125, 1, 100.00, 99999999.99, 'fechado', '2026-03-09 15:35:49', '2026-03-11 13:22:30', 'ku', '2026-03-09 15:35:49'),
(7, 125, 1, 50.00, NULL, 'aberto', '2026-03-11 13:35:04', NULL, NULL, '2026-03-11 13:35:04');

-- --------------------------------------------------------

--
-- Estrutura para tabela `caixa_movimentacoes`
--

CREATE TABLE `caixa_movimentacoes` (
  `id` int(11) NOT NULL,
  `caixa_id` int(11) NOT NULL,
  `tipo` enum('sangria','suprimento') NOT NULL,
  `valor` decimal(10,2) NOT NULL,
  `motivo` varchar(255) NOT NULL,
  `operador_id` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `caixa_movimentacoes`
--

INSERT INTO `caixa_movimentacoes` (`id`, `caixa_id`, `tipo`, `valor`, `motivo`, `operador_id`, `created_at`) VALUES
(1, 1, 'sangria', 500.00, 'comprar arroz', 1, '2026-03-03 12:31:20'),
(2, 2, 'suprimento', 1000.00, 'suprir', 1, '2026-03-03 12:33:14'),
(3, 3, 'sangria', 20.00, 'pediu', 1, '2026-03-03 13:06:34'),
(4, 3, 'suprimento', 25.00, 'coca', 1, '2026-03-03 13:06:46'),
(5, 6, 'suprimento', 1000.00, 'comprar arroz', 1, '2026-03-09 15:36:01'),
(6, 5, 'sangria', 1000.00, 'suprir', 2, '2026-03-09 15:41:09'),
(7, 5, 'sangria', 99999999.99, '', 2, '2026-03-09 16:00:13'),
(8, 5, 'suprimento', 99999999.99, '', 2, '2026-03-09 16:10:38'),
(9, 5, '', 20.00, 'Entrada Venda #24 (Fiado)', 2, '2026-03-10 16:52:06'),
(10, 6, '', 60.00, 'Venda #25 (DINHEIRO)', 1, '2026-03-10 19:43:44'),
(11, 6, '', 60.00, 'Venda #27 (DINHEIRO)', 1, '2026-03-10 20:01:55'),
(12, 6, '', 60.00, 'Venda #29 (DINHEIRO)', 1, '2026-03-10 20:05:48'),
(13, 6, '', 60.00, 'Venda #30 (DINHEIRO)', 1, '2026-03-10 20:06:13'),
(14, 6, '', 60.00, 'Venda #31 (DINHEIRO)', 1, '2026-03-10 20:06:26'),
(15, 6, '', 60.00, 'Venda #32 (DINHEIRO)', 1, '2026-03-10 20:08:38'),
(16, 6, '', 60.00, 'Venda #33 (DINHEIRO)', 1, '2026-03-10 20:09:39'),
(17, 6, '', 60.00, 'Venda #34 (DINHEIRO)', 1, '2026-03-10 20:18:46'),
(18, 6, '', 60.00, 'Venda #35 (DINHEIRO)', 1, '2026-03-10 20:19:09'),
(19, 6, '', 60.00, 'Venda #36 (DINHEIRO)', 1, '2026-03-10 20:46:25'),
(20, 6, '', 60.00, 'Venda #37 (DINHEIRO)', 1, '2026-03-10 20:46:48'),
(21, 6, '', 60.00, 'Venda #38 (DINHEIRO)', 1, '2026-03-10 20:47:45'),
(22, 6, '', 60.00, 'Venda #39 (DINHEIRO)', 1, '2026-03-10 23:25:20'),
(23, 6, '', 60.00, 'Venda #40 (DINHEIRO)', 1, '2026-03-10 23:29:22'),
(24, 6, '', 60.00, 'Venda #41 (DINHEIRO)', 1, '2026-03-10 23:29:44'),
(25, 6, '', 60.00, 'Venda #42 (DINHEIRO)', 1, '2026-03-10 23:30:21'),
(26, 6, '', 60.00, 'Venda #43 (DINHEIRO)', 1, '2026-03-11 00:28:44'),
(27, 6, '', 60.00, 'Venda #44 (DINHEIRO)', 1, '2026-03-11 00:38:42'),
(28, 6, '', 60.00, 'Venda #45 (DINHEIRO)', 1, '2026-03-11 00:39:16'),
(29, 6, '', 60.00, 'Venda #46 (DINHEIRO)', 1, '2026-03-11 01:14:09'),
(30, 6, '', 60.00, 'Venda #47 (DINHEIRO)', 1, '2026-03-11 01:16:16'),
(31, 6, '', 60.00, 'Venda #48 (DINHEIRO)', 1, '2026-03-11 01:21:09'),
(32, 6, '', 60.00, 'Venda #49 (DINHEIRO)', 1, '2026-03-11 01:21:41'),
(33, 6, '', 60.00, 'Venda #50 (DINHEIRO)', 1, '2026-03-11 01:23:49'),
(34, 6, '', 60.00, 'Venda #51 (DINHEIRO)', 1, '2026-03-11 01:27:40'),
(35, 6, '', 60.00, 'Venda #52 (DINHEIRO)', 1, '2026-03-11 01:28:05'),
(36, 6, '', 60.00, 'Venda #53 (DINHEIRO)', 1, '2026-03-11 01:34:23'),
(37, 6, '', 60.00, 'Venda #54 (DINHEIRO)', 1, '2026-03-11 01:35:19'),
(38, 6, '', 60.00, 'Venda #55 (DINHEIRO)', 1, '2026-03-11 01:38:13'),
(39, 6, '', 60.00, 'Venda #56 (DINHEIRO)', 1, '2026-03-11 01:38:45'),
(40, 6, '', 20.00, 'Venda #57 (DINHEIRO)', 1, '2026-03-11 01:41:38'),
(41, 6, '', 60.00, 'Venda #58 (DINHEIRO)', 1, '2026-03-11 01:41:57'),
(42, 6, '', 60.00, 'Venda #59 (DINHEIRO)', 1, '2026-03-11 01:43:32'),
(43, 6, '', 60.00, 'Venda #60 (DINHEIRO)', 1, '2026-03-11 01:48:27'),
(44, 6, '', 60.00, 'Venda #61 (DINHEIRO)', 1, '2026-03-11 01:48:57'),
(45, 6, '', 60.00, 'Venda #62 (DINHEIRO)', 1, '2026-03-11 01:50:58'),
(46, 6, '', 60.00, 'Venda #63 (DINHEIRO)', 1, '2026-03-11 12:01:26'),
(47, 6, '', 60.00, 'Venda #64 (DINHEIRO)', 1, '2026-03-11 12:29:03'),
(48, 7, '', 60.00, 'Venda #65 (DINHEIRO)', 1, '2026-03-11 13:44:14'),
(49, 7, '', 60.00, 'Venda #66 (DINHEIRO)', 1, '2026-03-11 13:46:56'),
(50, 7, '', 60.00, 'Venda #67 (DINHEIRO)', 1, '2026-03-11 14:06:28'),
(51, 7, '', 60.00, 'Venda #68 (DINHEIRO)', 1, '2026-03-11 14:07:41'),
(52, 7, '', 60.00, 'Venda #69 (DINHEIRO)', 1, '2026-03-11 14:14:19'),
(53, 7, '', 60.00, 'Venda #70 (DINHEIRO)', 1, '2026-03-11 14:17:27'),
(54, 7, '', 60.00, 'Venda #71 (DINHEIRO)', 1, '2026-03-11 14:18:15'),
(55, 7, '', 60.00, 'Venda #72 (DINHEIRO)', 1, '2026-03-11 14:20:05'),
(56, 7, '', 60.00, 'Venda #73 (DINHEIRO)', 1, '2026-03-11 14:28:29'),
(57, 7, '', 60.00, 'Venda #74 (DINHEIRO)', 1, '2026-03-11 14:31:12'),
(58, 7, '', 60.00, 'Venda #75 (DINHEIRO)', 1, '2026-03-11 14:31:26'),
(59, 7, '', 60.00, 'Venda #76 (DINHEIRO)', 1, '2026-03-11 14:41:21'),
(60, 7, '', 60.00, 'Venda #77 (DINHEIRO)', 1, '2026-03-11 14:42:02'),
(61, 7, '', 60.00, 'Venda #78 (DINHEIRO)', 1, '2026-03-11 14:45:20'),
(62, 7, '', 60.00, 'Venda #79 (DINHEIRO)', 1, '2026-03-11 14:47:53'),
(63, 7, '', 60.00, 'Venda #80 (DINHEIRO)', 1, '2026-03-11 14:48:10'),
(64, 7, '', 60.00, 'Venda #81 (DINHEIRO)', 1, '2026-03-12 12:03:03'),
(65, 7, '', 60.00, 'Venda #82 (DINHEIRO)', 1, '2026-03-12 12:03:17'),
(66, 7, '', 60.00, 'Venda #83 (DINHEIRO)', 1, '2026-03-12 12:03:42'),
(67, 7, '', 60.00, 'Venda #84 (DINHEIRO)', 1, '2026-03-12 12:03:42'),
(68, 7, '', 60.00, 'Venda #85 (PIX)', 1, '2026-03-12 12:04:50'),
(69, 7, '', 210.00, 'Venda #86 (DINHEIRO)', 1, '2026-03-12 12:06:22'),
(70, 7, '', 725.00, 'Venda #87 (DINHEIRO)', 1, '2026-03-12 12:18:18'),
(71, 7, '', 60.00, 'Venda #88 (PIX)', 1, '2026-03-12 12:54:43'),
(72, 7, '', 60.00, 'Venda #89 (CARTAO_CREDITO)', 1, '2026-03-12 12:55:22'),
(73, 7, '', 60.00, 'Venda #90 (PIX)', 1, '2026-03-12 13:01:40'),
(74, 7, '', 60.00, 'Venda #91 (BOLETO)', 1, '2026-03-12 13:02:02'),
(75, 7, '', 20.00, 'Entrada Venda #92 (Fiado)', 1, '2026-03-12 13:02:45'),
(76, 7, '', 60.00, 'Venda #93 (PIX)', 1, '2026-03-12 13:11:00'),
(77, 7, '', 60.00, 'Venda #94 (PIX)', 1, '2026-03-12 13:22:12'),
(78, 7, '', 60.00, 'Venda #95 (PIX)', 1, '2026-03-12 13:22:54'),
(79, 7, '', 60.00, 'Venda #96 (PIX)', 1, '2026-03-12 13:32:58'),
(80, 7, '', 60.00, 'Venda #97 (PIX)', 1, '2026-03-12 13:33:33'),
(81, 7, '', 60.00, 'Venda #98 (PIX)', 1, '2026-03-12 13:45:59'),
(82, 7, '', 60.00, 'Venda #99 (PIX)', 1, '2026-03-12 13:46:28'),
(83, 7, '', 60.00, 'Venda #100 (PIX)', 1, '2026-03-12 13:59:57'),
(84, 7, '', 60.00, 'Venda #101 (CARTAO_CREDITO)', 1, '2026-03-12 14:01:31'),
(85, 7, '', 60.00, 'Venda #102 (BOLETO)', 1, '2026-03-12 14:06:41'),
(86, 7, '', 60.00, 'Venda #103 (PIX)', 1, '2026-03-12 14:07:49'),
(87, 7, '', 60.00, 'Venda #104 (PIX)', 1, '2026-03-12 14:11:50'),
(88, 7, '', 60.00, 'Venda #105 (DINHEIRO)', 1, '2026-03-12 14:32:19'),
(89, 7, '', 60.00, 'Venda #106 (DINHEIRO)', 1, '2026-03-12 14:32:37'),
(90, 7, '', 60.00, 'Venda #107 (PIX)', 1, '2026-03-12 14:46:48'),
(91, 7, '', 60.00, 'Venda #108 (PIX)', 1, '2026-03-12 14:47:10'),
(92, 7, '', 60.00, 'Venda #109 (DINHEIRO)', 1, '2026-03-12 14:47:22'),
(93, 7, '', 60.00, 'Venda #110 (DINHEIRO)', 1, '2026-03-12 14:56:57'),
(94, 7, '', 60.00, 'Venda #111 (DINHEIRO)', 1, '2026-03-12 15:12:30'),
(95, 7, '', 60.00, 'Venda #112 (DINHEIRO)', 1, '2026-03-12 15:18:15'),
(96, 7, '', 60.00, 'Venda #113 (CARTAO_CREDITO)', 1, '2026-03-12 15:25:00'),
(97, 7, '', 60.00, 'Venda #114 (DINHEIRO)', 1, '2026-03-12 15:25:37'),
(98, 7, '', 60.00, 'Venda #115 (DINHEIRO)', 1, '2026-03-12 15:37:40'),
(99, 7, '', 60.00, 'Venda #116 (DINHEIRO)', 1, '2026-03-12 15:47:48');

-- --------------------------------------------------------

--
-- Estrutura para tabela `centros_custo`
--

CREATE TABLE `centros_custo` (
  `id` int(11) NOT NULL,
  `nome` varchar(100) NOT NULL,
  `tipo` enum('fixo','variavel') NOT NULL,
  `descricao` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `centros_custo`
--

INSERT INTO `centros_custo` (`id`, `nome`, `tipo`, `descricao`) VALUES
(1, 'Operacional', 'fixo', NULL),
(2, 'Administrativo', 'fixo', NULL),
(3, 'Marketing', 'fixo', NULL),
(4, 'Operacional', 'fixo', NULL),
(5, 'Administrativo', 'fixo', NULL),
(6, 'Marketing', 'fixo', NULL),
(7, 'Operacional', 'fixo', NULL),
(8, 'Administrativo', 'fixo', NULL),
(9, 'Marketing', 'fixo', NULL),
(10, 'Operacional', 'fixo', NULL),
(11, 'Administrativo', 'fixo', NULL),
(12, 'Marketing', 'fixo', NULL),
(13, 'Operacional', 'fixo', NULL),
(14, 'Administrativo', 'fixo', NULL),
(15, 'Marketing', 'fixo', NULL),
(16, 'Operacional', 'fixo', NULL),
(17, 'Administrativo', 'fixo', NULL),
(18, 'Marketing', 'fixo', NULL),
(19, 'Operacional', 'fixo', NULL),
(20, 'Administrativo', 'fixo', NULL),
(21, 'Marketing', 'fixo', NULL),
(22, 'Operacional', 'fixo', NULL),
(23, 'Administrativo', 'fixo', NULL),
(24, 'Marketing', 'fixo', NULL),
(25, 'Operacional', 'fixo', NULL),
(26, 'Administrativo', 'fixo', NULL),
(27, 'Marketing', 'fixo', NULL),
(28, 'Operacional', 'fixo', NULL),
(29, 'Administrativo', 'fixo', NULL),
(30, 'Marketing', 'fixo', NULL),
(31, 'Operacional', 'fixo', NULL),
(32, 'Administrativo', 'fixo', NULL),
(33, 'Marketing', 'fixo', NULL);

-- --------------------------------------------------------

--
-- Estrutura para tabela `clientes`
--

CREATE TABLE `clientes` (
  `id` int(11) NOT NULL,
  `filial_id` int(11) DEFAULT NULL,
  `nome` varchar(100) NOT NULL,
  `tipo` enum('fisica','juridica') DEFAULT 'fisica',
  `cpf_cnpj` varchar(20) DEFAULT NULL,
  `rg_ie` varchar(20) DEFAULT NULL,
  `telefone` varchar(20) DEFAULT NULL,
  `whatsapp` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `contato_nome` varchar(100) DEFAULT NULL,
  `endereco` text DEFAULT NULL,
  `lng` varchar(50) DEFAULT NULL,
  `lat` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `clientes`
--

INSERT INTO `clientes` (`id`, `filial_id`, `nome`, `tipo`, `cpf_cnpj`, `rg_ie`, `telefone`, `whatsapp`, `email`, `contato_nome`, `endereco`, `lng`, `lat`, `created_at`, `updated_at`) VALUES
(1, NULL, 'João Silva', 'fisica', '123.456.789-00', NULL, '(11) 99999-9999', NULL, 'joao@email.com', NULL, 'Rua A, 123 - São Paulo/SP', NULL, NULL, '2026-02-18 17:15:13', '2026-02-18 17:15:13'),
(2, NULL, 'Maria Oliveira', 'fisica', '987.654.321-00', NULL, '(11) 98888-8888', NULL, 'maria@email.com', NULL, 'Rua B, 456 - São Paulo/SP', NULL, NULL, '2026-02-18 17:15:13', '2026-02-18 17:15:13'),
(3, NULL, 'Empresa XYZ Ltda', 'fisica', '12.345.678/0001-90', NULL, '(11) 37777-7777', NULL, 'contato@empresa.com', NULL, 'Av. Paulista, 1000 - São Paulo/SP', NULL, NULL, '2026-02-18 17:15:13', '2026-02-18 17:15:13'),
(4, 1, 'João Instalador Ltda', 'fisica', '12.345.678/0001-90', NULL, '(11) 98888-7777', NULL, 'joão.silva@exemplo.com', 'João Silva', NULL, NULL, NULL, '2026-02-21 20:04:28', '2026-02-21 20:04:28'),
(5, 1, 'Construtora Alfa', 'juridica', '22.333.444/0001-55', NULL, '(11) 98888-7777', NULL, 'carlos.ramos@exemplo.com', 'Carlos Ramos', NULL, NULL, NULL, '2026-02-21 20:04:28', '2026-02-21 20:04:28'),
(6, 1, 'Maria Souza Eletro', 'juridica', '33.222.111/0001-00', NULL, '(11) 98888-7777', NULL, 'maria.souza@exemplo.com', 'Maria Souza', NULL, NULL, NULL, '2026-02-21 20:04:28', '2026-02-21 20:04:28'),
(7, 1, 'Condomínio Solaris', 'juridica', '44.555.666/0001-88', NULL, '(11) 98888-7777', NULL, 'síndico.josé@exemplo.com', 'Síndico José', NULL, NULL, NULL, '2026-02-21 20:04:28', '2026-02-21 20:04:28'),
(8, 1, 'Pedro Eletricista Autônomo', 'fisica', '123.456.789-00', NULL, '(11) 98888-7777', NULL, 'pedro.santos@exemplo.com', 'Pedro Santos', NULL, NULL, NULL, '2026-02-21 20:04:28', '2026-02-21 20:04:28'),
(9, 1, 'Pedro Eletricista Autônomo', 'fisica', '123.456.789-00', NULL, '(11) 98888-7777', NULL, 'pedro.santos@exemplo.com', NULL, '', NULL, NULL, '2026-02-23 12:59:48', '2026-02-23 12:59:48'),
(10, 1, 'Pedro Eletricista Autônomo', 'fisica', '123.456.789-00', NULL, '(11) 98888-7777', NULL, 'pedro.santos@exemplo.com', NULL, 'Av. Paulista', NULL, NULL, '2026-02-23 12:59:57', '2026-02-23 12:59:57'),
(11, 125, 'LUIZ BRENO DA FROTA', 'fisica', '0412552147', NULL, '92993469628', NULL, 'Luizfrota@gmal.com', NULL, 'Casa', NULL, NULL, '2026-03-04 14:33:01', '2026-03-04 14:33:01'),
(12, 125, 'lucas correa silva', 'fisica', '04125521247', NULL, '97798451322', NULL, NULL, NULL, 'alis, atras, do 38', NULL, NULL, '2026-03-04 15:48:30', '2026-03-04 15:50:23'),
(13, 125, 'Liandra', 'fisica', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-03-10 17:18:31', '2026-03-10 17:18:31');

-- --------------------------------------------------------

--
-- Estrutura para tabela `compras`
--

CREATE TABLE `compras` (
  `id` int(11) NOT NULL,
  `fornecedor_id` int(11) DEFAULT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `valor_total` decimal(10,2) DEFAULT NULL,
  `data_compra` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `compras`
--

INSERT INTO `compras` (`id`, `fornecedor_id`, `usuario_id`, `valor_total`, `data_compra`) VALUES
(1, 1, 1, 0.00, '2026-02-21 20:19:30'),
(2, 4, 1, 0.00, '2026-02-21 20:19:42');

-- --------------------------------------------------------

--
-- Estrutura para tabela `compra_itens`
--

CREATE TABLE `compra_itens` (
  `id` int(11) NOT NULL,
  `compra_id` int(11) DEFAULT NULL,
  `produto_id` int(11) DEFAULT NULL,
  `quantidade` decimal(10,3) DEFAULT NULL,
  `preco_custo` decimal(10,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `compra_itens`
--

INSERT INTO `compra_itens` (`id`, `compra_id`, `produto_id`, `quantidade`, `preco_custo`) VALUES
(1, 1, 2, 1.000, 0.00),
(2, 2, 16, 1.000, 0.00);

-- --------------------------------------------------------

--
-- Estrutura para tabela `configuracoes`
--

CREATE TABLE `configuracoes` (
  `chave` varchar(50) NOT NULL,
  `valor` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `configuracoes`
--

INSERT INTO `configuracoes` (`chave`, `valor`) VALUES
('empresa_cnpj', ''),
('empresa_email', ''),
('empresa_fone', ''),
('empresa_nome', 'ERP Elétrica'),
('estoque_min_default', '5'),
('msg_orcamento', ''),
('nome_empresa', 'ERP El?trica Retail'),
('telefone_suporte', '(11) 9999-9999'),
('versao_sistema', '2.0.0');

-- --------------------------------------------------------

--
-- Estrutura para tabela `contas_pagar`
--

CREATE TABLE `contas_pagar` (
  `id` int(11) NOT NULL,
  `filial_id` int(11) DEFAULT NULL,
  `fornecedor_id` int(11) DEFAULT NULL,
  `centro_custo_id` int(11) DEFAULT NULL,
  `descricao` varchar(255) DEFAULT NULL,
  `valor` decimal(10,2) DEFAULT NULL,
  `data_vencimento` date DEFAULT NULL,
  `data_pagamento` date DEFAULT NULL,
  `status` enum('pendente','pago','cancelado') DEFAULT 'pendente',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `contas_receber`
--

CREATE TABLE `contas_receber` (
  `id` int(11) NOT NULL,
  `filial_id` int(11) DEFAULT NULL,
  `os_id` int(11) DEFAULT NULL,
  `venda_id` int(11) DEFAULT NULL,
  `cliente_id` int(11) DEFAULT NULL,
  `descricao` varchar(255) DEFAULT NULL,
  `valor` decimal(10,2) DEFAULT NULL,
  `data_vencimento` date DEFAULT NULL,
  `data_pagamento` date DEFAULT NULL,
  `status` enum('pendente','pago','atrasado') DEFAULT 'pendente',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `contas_receber`
--

INSERT INTO `contas_receber` (`id`, `filial_id`, `os_id`, `venda_id`, `cliente_id`, `descricao`, `valor`, `data_vencimento`, `data_pagamento`, `status`, `created_at`, `updated_at`) VALUES
(1, NULL, 1, NULL, NULL, 'OS 2024001 - Instalação elétrica', 1250.00, '2024-02-15', NULL, 'pago', '2026-02-18 17:15:13', '2026-02-18 17:15:13'),
(2, NULL, 2, NULL, NULL, 'OS 2024002 - Troca de disjuntores', 450.00, '2024-02-20', '2026-02-21', 'pago', '2026-02-18 17:15:13', '2026-02-21 19:16:16'),
(3, NULL, 3, NULL, NULL, 'OS 2024003 - Manutenção preventiva', 800.00, '2024-02-25', '2026-02-21', 'pago', '2026-02-18 17:15:13', '2026-02-21 20:12:54'),
(4, 125, NULL, 20, 11, NULL, 210.00, '2026-04-03', NULL, 'pendente', '2026-03-04 14:47:48', '2026-03-04 14:47:48'),
(5, 125, NULL, 21, 11, NULL, 45.00, '2026-04-03', NULL, 'pendente', '2026-03-04 14:52:46', '2026-03-04 14:52:46'),
(6, 125, NULL, 22, 12, NULL, 2750.00, '2026-04-03', NULL, 'pendente', '2026-03-04 15:50:27', '2026-03-04 15:50:27'),
(7, 125, NULL, 23, 12, NULL, 245.00, '2026-04-03', NULL, 'pendente', '2026-03-04 20:21:59', '2026-03-04 20:21:59'),
(8, 125, NULL, 24, 11, NULL, 63.60, '2026-04-09', NULL, 'pendente', '2026-03-10 16:52:06', '2026-03-10 16:52:06'),
(9, 125, NULL, 92, 11, NULL, 60.00, '2026-04-11', NULL, 'pendente', '2026-03-12 13:02:45', '2026-03-12 13:02:45');

-- --------------------------------------------------------

--
-- Estrutura para tabela `depositos`
--

CREATE TABLE `depositos` (
  `id` int(11) NOT NULL,
  `nome` varchar(100) NOT NULL,
  `localizacao` varchar(255) DEFAULT NULL,
  `principal` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `depositos`
--

INSERT INTO `depositos` (`id`, `nome`, `localizacao`, `principal`) VALUES
(1, 'Depósito Central', NULL, 1),
(2, 'Depósito Central', NULL, 1),
(3, 'Depósito Central', NULL, 1),
(4, 'Depósito Central', NULL, 1),
(5, 'Depósito Central', NULL, 1),
(6, 'Depósito Central', NULL, 1),
(7, 'Depósito Central', NULL, 1),
(8, 'Depósito Central', NULL, 1),
(9, 'Depósito Central', NULL, 1),
(10, 'Depósito Central', NULL, 1),
(11, 'Depósito Central', NULL, 1);

-- --------------------------------------------------------

--
-- Estrutura para tabela `estoque_detalhado`
--

CREATE TABLE `estoque_detalhado` (
  `id` int(11) NOT NULL,
  `produto_id` int(11) DEFAULT NULL,
  `deposito_id` int(11) DEFAULT NULL,
  `lote` varchar(50) DEFAULT NULL,
  `validade` date DEFAULT NULL,
  `quantidade` decimal(10,3) DEFAULT 0.000
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `filiais`
--

CREATE TABLE `filiais` (
  `id` int(11) NOT NULL,
  `nome` varchar(100) NOT NULL,
  `razao_social` varchar(150) DEFAULT NULL,
  `cnpj` varchar(20) DEFAULT NULL,
  `inscricao_estadual` varchar(20) DEFAULT NULL,
  `logradouro` varchar(255) DEFAULT NULL,
  `numero` varchar(20) DEFAULT NULL,
  `bairro` varchar(100) DEFAULT NULL,
  `municipio` varchar(100) DEFAULT NULL,
  `uf` char(2) DEFAULT NULL,
  `cep` varchar(10) DEFAULT NULL,
  `csc_id` varchar(10) DEFAULT NULL,
  `csc_token` varchar(100) DEFAULT NULL,
  `certificado_pfx` varchar(255) DEFAULT NULL,
  `certificado_senha` varchar(255) DEFAULT NULL,
  `endereco` text DEFAULT NULL,
  `telefone` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `principal` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `ambiente` tinyint(4) DEFAULT 2 COMMENT '1: Produção, 2: Homologação',
  `crt` tinyint(1) DEFAULT 1,
  `tipo_emissao` varchar(50) DEFAULT 'Normal',
  `finalidade_emissao` varchar(50) DEFAULT 'Normal',
  `indicador_presenca` varchar(50) DEFAULT 'Operacao presencial',
  `tipo_impressao_danfe` varchar(50) DEFAULT 'NFC-e',
  `serie_nfce` int(11) DEFAULT 1,
  `ultimo_numero_nfce` int(11) DEFAULT 0,
  `complemento` varchar(100) DEFAULT NULL,
  `codigo_municipio` varchar(10) DEFAULT NULL,
  `finalidade` varchar(50) DEFAULT '1',
  `ind_pres` varchar(50) DEFAULT '1',
  `tipo_impressao` varchar(50) DEFAULT '4',
  `numero_endereco` varchar(20) DEFAULT NULL,
  `cidade` varchar(100) DEFAULT NULL,
  `codigo_uf` varchar(2) DEFAULT NULL,
  `csc` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `filiais`
--

INSERT INTO `filiais` (`id`, `nome`, `razao_social`, `cnpj`, `inscricao_estadual`, `logradouro`, `numero`, `bairro`, `municipio`, `uf`, `cep`, `csc_id`, `csc_token`, `certificado_pfx`, `certificado_senha`, `endereco`, `telefone`, `email`, `principal`, `created_at`, `ambiente`, `crt`, `tipo_emissao`, `finalidade_emissao`, `indicador_presenca`, `tipo_impressao_danfe`, `serie_nfce`, `ultimo_numero_nfce`, `complemento`, `codigo_municipio`, `finalidade`, `ind_pres`, `tipo_impressao`, `numero_endereco`, `cidade`, `codigo_uf`, `csc`) VALUES
(125, 'PAPAGAIO MOTOS', 'PAPAGAIO COMERCIO DE MOTOS LTDA', '59.598.453/0001-04', '05.475.644-8', 'PADRE VICENTE NOGUEIRA', '149', 'ITAMARATI', 'COARI', 'AM', '69460000', '2', '4b321d16ec35085a', 'cert_125_69b011280bcf6.pfx', 'OTU5NTEy', NULL, '9791979595', '', 1, '2026-02-21 19:00:34', 2, 3, 'Normal', 'Normal', 'Operacao presencial', 'NFC-e', 1, 37, '', '1301209', '1', '1', '4', NULL, NULL, '13', NULL),
(589, 'fifial', NULL, '000000000000000', 'tste', 'rua: Alvelos dantas', '38°', 'união', 'codajas', 'cd', '69460-000', '', '', NULL, '', NULL, NULL, NULL, 0, '2026-02-25 19:09:03', 2, 1, 'Normal', 'Normal', 'Operacao presencial', 'NFC-e', 1, 0, NULL, NULL, '1', '1', '4', NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Estrutura para tabela `fornecedores`
--

CREATE TABLE `fornecedores` (
  `id` int(11) NOT NULL,
  `filial_id` int(11) DEFAULT NULL,
  `nome_fantasia` varchar(100) NOT NULL,
  `razao_social` varchar(100) DEFAULT NULL,
  `cnpj` varchar(20) DEFAULT NULL,
  `telefone` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `site` varchar(100) DEFAULT NULL,
  `endereco` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `fornecedores`
--

INSERT INTO `fornecedores` (`id`, `filial_id`, `nome_fantasia`, `razao_social`, `cnpj`, `telefone`, `email`, `site`, `endereco`, `created_at`) VALUES
(1, NULL, 'Weg Equipamentos Elétricos', NULL, '01.234.567/0001-11', '', 'contato@weg.ne', NULL, '', '2026-02-21 20:04:28'),
(2, NULL, 'Schneider Electric Brasil', NULL, '02.345.678/0001-22', NULL, 'suporte@schneider.com.br', NULL, NULL, '2026-02-21 20:04:28'),
(3, NULL, 'Prysmian Group - Cabos', NULL, '03.456.789/0001-33', NULL, 'vendas@prysmian.com', NULL, NULL, '2026-02-21 20:04:28'),
(4, NULL, 'Tigre Conexões', NULL, '04.567.890/0001-44', NULL, 'atendimento@tigre.com.br', NULL, NULL, '2026-02-21 20:04:28');

-- --------------------------------------------------------

--
-- Estrutura para tabela `itens_os`
--

CREATE TABLE `itens_os` (
  `id` int(11) NOT NULL,
  `os_id` int(11) DEFAULT NULL,
  `produto_id` int(11) DEFAULT NULL,
  `quantidade` int(11) DEFAULT NULL,
  `valor_unitario` decimal(10,2) DEFAULT NULL,
  `subtotal` decimal(10,2) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `itens_os`
--

INSERT INTO `itens_os` (`id`, `os_id`, `produto_id`, `quantidade`, `valor_unitario`, `subtotal`, `created_at`) VALUES
(1, 1, NULL, 2, 129.90, 259.80, '2026-02-18 17:15:13'),
(2, 1, 2, 5, 15.90, 79.50, '2026-02-18 17:15:13'),
(3, 1, 3, 10, 8.90, 89.00, '2026-02-18 17:15:13'),
(4, 2, 2, 3, 15.90, 47.70, '2026-02-18 17:15:13');

-- --------------------------------------------------------

--
-- Estrutura para tabela `lancamentos_custos`
--

CREATE TABLE `lancamentos_custos` (
  `id` int(11) NOT NULL,
  `filial_id` int(11) NOT NULL,
  `centro_custo_id` int(11) NOT NULL,
  `descricao` varchar(255) NOT NULL,
  `valor` decimal(10,2) NOT NULL,
  `data_lancamento` date NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `logs_acesso`
--

CREATE TABLE `logs_acesso` (
  `id` int(11) NOT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `email_tentativa` varchar(255) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `sucesso` tinyint(1) DEFAULT 0,
  `motivo` varchar(255) DEFAULT NULL,
  `data_tentativa` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `migrations`
--

CREATE TABLE `migrations` (
  `id` int(11) NOT NULL,
  `migration` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `migrations`
--

INSERT INTO `migrations` (`id`, `migration`, `created_at`) VALUES
(1, '001_create_audit_logs.sql', '2026-02-25 12:23:52'),
(2, '002_enhance_os_features.sql', '2026-02-25 12:23:52'),
(3, '003_fiscal_integration_schema.sql', '2026-02-25 18:26:19'),
(4, '004_create_settings_table.sql', '2026-02-25 18:38:23'),
(5, '005_add_filial_id_to_users.sql', '2026-02-25 19:01:44'),
(6, '006_b2b_isolation_schema.sql', '2026-03-11 14:37:47'),
(7, '007_rbac_schema.sql', '2026-03-11 14:37:47'),
(8, '008_perf_sec_hardening.sql', '2026-03-11 14:37:48'),
(9, '009_add_auth_fields_to_users.sql', '2026-03-11 14:37:48'),
(10, '010_add_audit_to_vendas.sql', '2026-03-11 14:37:48'),
(11, '011_create_cashier_tables.sql', '2026-03-11 14:37:48'),
(12, '013_create_cost_center_tables.sql', '2026-03-11 14:37:48'),
(13, '014_create_intelligence_tables.sql', '2026-03-11 14:37:48'),
(14, '015_add_nome_cliente_avulso.sql', '2026-03-11 14:37:48'),
(15, '016_create_autorizacoes_temporarias.sql', '2026-03-11 14:37:48'),
(16, '017_add_nome_cliente_avulso_vendas.sql', '2026-03-11 14:37:48'),
(17, '018_create_nfe_importadas.sql', '2026-03-11 14:37:48');

-- --------------------------------------------------------

--
-- Estrutura para tabela `movimentacao_estoque`
--

CREATE TABLE `movimentacao_estoque` (
  `id` int(11) NOT NULL,
  `produto_id` int(11) DEFAULT NULL,
  `deposito_id` int(11) DEFAULT NULL,
  `quantidade` decimal(10,3) DEFAULT NULL,
  `tipo` enum('entrada','saida','ajuste','transferencia') NOT NULL,
  `motivo` varchar(255) DEFAULT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `referencia_id` int(11) DEFAULT NULL,
  `data_movimento` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `movimentacao_estoque`
--

INSERT INTO `movimentacao_estoque` (`id`, `produto_id`, `deposito_id`, `quantidade`, `tipo`, `motivo`, `usuario_id`, `referencia_id`, `data_movimento`) VALUES
(1, 2, 1, 1.000, 'entrada', 'Compra #1', 1, NULL, '2026-02-21 20:19:30'),
(2, 16, 1, 1.000, 'entrada', 'Compra #2', 1, NULL, '2026-02-21 20:19:42');

-- --------------------------------------------------------

--
-- Estrutura para tabela `nfce_emitidas`
--

CREATE TABLE `nfce_emitidas` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `empresa_id` varchar(40) NOT NULL,
  `venda_id` bigint(20) DEFAULT NULL,
  `ambiente` tinyint(4) NOT NULL,
  `serie` int(11) NOT NULL,
  `numero` int(11) NOT NULL,
  `chave` char(44) NOT NULL,
  `protocolo` varchar(50) DEFAULT NULL,
  `status_sefaz` varchar(10) NOT NULL,
  `mensagem` varchar(255) DEFAULT NULL,
  `xml_nfeproc` mediumtext DEFAULT NULL,
  `xml_envio` mediumtext DEFAULT NULL,
  `xml_retorno` mediumtext DEFAULT NULL,
  `valor_total` decimal(12,2) DEFAULT 0.00,
  `valor_troco` decimal(12,2) DEFAULT 0.00,
  `tpag_json` longtext DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `nfce_emitidas`
--

INSERT INTO `nfce_emitidas` (`id`, `empresa_id`, `venda_id`, `ambiente`, `serie`, `numero`, `chave`, `protocolo`, `status_sefaz`, `mensagem`, `xml_nfeproc`, `xml_envio`, `xml_retorno`, `valor_total`, `valor_troco`, `tpag_json`, `created_at`) VALUES
(1, '125', 76, 2, 1, 14, '13260359598453000104650010000000141777947015', NULL, '104', 'Lote processado', '<?xml version=\"1.0\" encoding=\"UTF-8\"?><nfeProc xmlns=\"http://www.portalfiscal.inf.br/nfe\" versao=\"4.00\"><NFe xmlns=\"http://www.portalfiscal.inf.br/nfe\"><infNFe Id=\"NFe13260359598453000104650010000000141777947015\" versao=\"4.00\"><ide><cUF>13</cUF><cNF>77794701</cNF><natOp>VENDA</natOp><mod>65</mod><serie>1</serie><nNF>14</nNF><dhEmi>2026-03-11T10:41:22-04:00</dhEmi><tpNF>1</tpNF><idDest>1</idDest><cMunFG>1301209</cMunFG><tpImp>4</tpImp><tpEmis>1</tpEmis><cDV>5</cDV><tpAmb>2</tpAmb><finNFe>1</finNFe><indFinal>1</indFinal><indPres>1</indPres><procEmi>0</procEmi><verProc>PDV-ACAI-1.0</verProc></ide><emit><CNPJ>59598453000104</CNPJ><xNome>PAPAGAIO COMERCIO DE MOTOS LTDA</xNome><xFant>PAPAGAIO MOTOS</xFant><enderEmit><xLgr>PADRE VICENTE NOGUEIRA</xLgr><nro>149</nro><xBairro>ITAMARATI</xBairro><cMun>1301209</cMun><xMun>COARI</xMun><UF>AM</UF><CEP>69460000</CEP><cPais>1058</cPais><xPais>Brasil</xPais><fone>9791979595</fone></enderEmit><IE>054756448</IE><CRT>3</CRT></emit><det nItem=\"1\"><prod><cProd>1</cProd><cEAN>SEM GTIN</cEAN><xProd>Buzina 12v c100</xProd><NCM>85123000</NCM><CFOP>5102</CFOP><uCom>UN</uCom><qCom>1.000</qCom><vUnCom>60.00</vUnCom><vProd>60.00</vProd><cEANTrib>SEM GTIN</cEANTrib><uTrib>UN</uTrib><qTrib>1.000</qTrib><vUnTrib>60.00</vUnTrib><indTot>1</indTot></prod><imposto><ICMS><ICMSSN102><orig>0</orig><CSOSN>102</CSOSN></ICMSSN102></ICMS><PIS><PISNT><CST>07</CST></PISNT></PIS><COFINS><COFINSNT><CST>07</CST></COFINSNT></COFINS></imposto></det><total><ICMSTot><vBC>0.00</vBC><vICMS>0.00</vICMS><vICMSDeson>0.00</vICMSDeson><vFCP>0.00</vFCP><vBCST>0.00</vBCST><vST>0.00</vST><vFCPST>0.00</vFCPST><vFCPSTRet>0.00</vFCPSTRet><vProd>60.00</vProd><vFrete>0.00</vFrete><vSeg>0.00</vSeg><vDesc>0.00</vDesc><vII>0.00</vII><vIPI>0.00</vIPI><vIPIDevol>0.00</vIPIDevol><vPIS>0.00</vPIS><vCOFINS>0.00</vCOFINS><vOutro>0.00</vOutro><vNF>60.00</vNF></ICMSTot></total><transp><modFrete>9</modFrete></transp><pag><detPag><indPag>0</indPag><tPag>01</tPag><vPag>60.00</vPag></detPag></pag><infAdic><infCpl>PDV A&#xE7;aiteria</infCpl></infAdic></infNFe><infNFeSupl><qrCode>https://sistemas.sefaz.am.gov.br/nfceweb-hom/consultarNFCe.jsp?p=13260359598453000104650010000000141777947015|2|2|2|54F30D8A17350C923B709D39D0A48F809A5394FD</qrCode><urlChave>www.sefaz.am.gov.br/nfce/consulta</urlChave></infNFeSupl><Signature xmlns=\"http://www.w3.org/2000/09/xmldsig#\"><SignedInfo><CanonicalizationMethod Algorithm=\"http://www.w3.org/TR/2001/REC-xml-c14n-20010315\"/><SignatureMethod Algorithm=\"http://www.w3.org/2000/09/xmldsig#rsa-sha1\"/><Reference URI=\"#NFe13260359598453000104650010000000141777947015\"><Transforms><Transform Algorithm=\"http://www.w3.org/2000/09/xmldsig#enveloped-signature\"/><Transform Algorithm=\"http://www.w3.org/TR/2001/REC-xml-c14n-20010315\"/></Transforms><DigestMethod Algorithm=\"http://www.w3.org/2000/09/xmldsig#sha1\"/><DigestValue>vBxUaXKlKsFqO2lIZXZHFxAKfvI=</DigestValue></Reference></SignedInfo><SignatureValue>qFhViL5RgE4qFEPPjwhu3Kb7QnAKxHxSQhawdPp4ouVK3hvIklZWJLE7juvFsjdqeyuVuGLHzo9STAh0CwHxEhBPEOeMQZLawDdmE2FAzfgR0J3uxsKflxZwpsutFnqDVY4xp6T00x4GkXv2+3UT5/S5tL9dwoTbOzYnxt2+GvfsVYsgZ3E5mv/3b0hYmg7PDm+bNhR0Km6zx8In2Bi5kp0BRsGRod83s/XUhQFwZrZ7gry8f2e7XgDvC7JO3Z2U6KxdTrCF3sLk0bdIuZhPp6THIQnqxAemu4nx19aiAjSz3krculSbDebI9D5nrGEuzI7MOOSGm8BJY2LNGkWLvA==</SignatureValue><KeyInfo><X509Data><X509Certificate>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</X509Certificate></X509Data></KeyInfo></Signature></NFe><protNFe versao=\"4.00\"><infProt><tpAmb>2</tpAmb><verAplic>AM3.10-4.00</verAplic><chNFe>13260359598453000104650010000000141777947015</chNFe><dhRecbto>2026-03-11T10:41:23-04:00</dhRecbto><cStat>373</cStat><xMotivo>Rejeicao: Descricao do primeiro item diferente de NOTA FISCAL EMITIDA EM AMBIENTE DE HOMOLOGACAO - SEM VALOR FISCAL</xMotivo></infProt></protNFe></nfeProc>', '<?xml version=\"1.0\"?><NFe xmlns=\"http://www.portalfiscal.inf.br/nfe\"><infNFe Id=\"NFe13260359598453000104650010000000141777947015\" versao=\"4.00\"><ide><cUF>13</cUF><cNF>77794701</cNF><natOp>VENDA</natOp><mod>65</mod><serie>1</serie><nNF>14</nNF><dhEmi>2026-03-11T10:41:22-04:00</dhEmi><tpNF>1</tpNF><idDest>1</idDest><cMunFG>1301209</cMunFG><tpImp>4</tpImp><tpEmis>1</tpEmis><cDV>5</cDV><tpAmb>2</tpAmb><finNFe>1</finNFe><indFinal>1</indFinal><indPres>1</indPres><procEmi>0</procEmi><verProc>PDV-ACAI-1.0</verProc></ide><emit><CNPJ>59598453000104</CNPJ><xNome>PAPAGAIO COMERCIO DE MOTOS LTDA</xNome><xFant>PAPAGAIO MOTOS</xFant><enderEmit><xLgr>PADRE VICENTE NOGUEIRA</xLgr><nro>149</nro><xBairro>ITAMARATI</xBairro><cMun>1301209</cMun><xMun>COARI</xMun><UF>AM</UF><CEP>69460000</CEP><cPais>1058</cPais><xPais>Brasil</xPais><fone>9791979595</fone></enderEmit><IE>054756448</IE><CRT>3</CRT></emit><det nItem=\"1\"><prod><cProd>1</cProd><cEAN>SEM GTIN</cEAN><xProd>Buzina 12v c100</xProd><NCM>85123000</NCM><CFOP>5102</CFOP><uCom>UN</uCom><qCom>1.000</qCom><vUnCom>60.00</vUnCom><vProd>60.00</vProd><cEANTrib>SEM GTIN</cEANTrib><uTrib>UN</uTrib><qTrib>1.000</qTrib><vUnTrib>60.00</vUnTrib><indTot>1</indTot></prod><imposto><ICMS><ICMSSN102><orig>0</orig><CSOSN>102</CSOSN></ICMSSN102></ICMS><PIS><PISNT><CST>07</CST></PISNT></PIS><COFINS><COFINSNT><CST>07</CST></COFINSNT></COFINS></imposto></det><total><ICMSTot><vBC>0.00</vBC><vICMS>0.00</vICMS><vICMSDeson>0.00</vICMSDeson><vFCP>0.00</vFCP><vBCST>0.00</vBCST><vST>0.00</vST><vFCPST>0.00</vFCPST><vFCPSTRet>0.00</vFCPSTRet><vProd>60.00</vProd><vFrete>0.00</vFrete><vSeg>0.00</vSeg><vDesc>0.00</vDesc><vII>0.00</vII><vIPI>0.00</vIPI><vIPIDevol>0.00</vIPIDevol><vPIS>0.00</vPIS><vCOFINS>0.00</vCOFINS><vOutro>0.00</vOutro><vNF>60.00</vNF></ICMSTot></total><transp><modFrete>9</modFrete></transp><pag><detPag><indPag>0</indPag><tPag>01</tPag><vPag>60.00</vPag></detPag></pag><infAdic><infCpl>PDV A&#xE7;aiteria</infCpl></infAdic></infNFe><infNFeSupl><qrCode>https://sistemas.sefaz.am.gov.br/nfceweb-hom/consultarNFCe.jsp?p=13260359598453000104650010000000141777947015|2|2|2|54F30D8A17350C923B709D39D0A48F809A5394FD</qrCode><urlChave>www.sefaz.am.gov.br/nfce/consulta</urlChave></infNFeSupl><Signature xmlns=\"http://www.w3.org/2000/09/xmldsig#\"><SignedInfo><CanonicalizationMethod Algorithm=\"http://www.w3.org/TR/2001/REC-xml-c14n-20010315\"/><SignatureMethod Algorithm=\"http://www.w3.org/2000/09/xmldsig#rsa-sha1\"/><Reference URI=\"#NFe13260359598453000104650010000000141777947015\"><Transforms><Transform Algorithm=\"http://www.w3.org/2000/09/xmldsig#enveloped-signature\"/><Transform Algorithm=\"http://www.w3.org/TR/2001/REC-xml-c14n-20010315\"/></Transforms><DigestMethod Algorithm=\"http://www.w3.org/2000/09/xmldsig#sha1\"/><DigestValue>vBxUaXKlKsFqO2lIZXZHFxAKfvI=</DigestValue></Reference></SignedInfo><SignatureValue>qFhViL5RgE4qFEPPjwhu3Kb7QnAKxHxSQhawdPp4ouVK3hvIklZWJLE7juvFsjdqeyuVuGLHzo9STAh0CwHxEhBPEOeMQZLawDdmE2FAzfgR0J3uxsKflxZwpsutFnqDVY4xp6T00x4GkXv2+3UT5/S5tL9dwoTbOzYnxt2+GvfsVYsgZ3E5mv/3b0hYmg7PDm+bNhR0Km6zx8In2Bi5kp0BRsGRod83s/XUhQFwZrZ7gry8f2e7XgDvC7JO3Z2U6KxdTrCF3sLk0bdIuZhPp6THIQnqxAemu4nx19aiAjSz3krculSbDebI9D5nrGEuzI7MOOSGm8BJY2LNGkWLvA==</SignatureValue><KeyInfo><X509Data><X509Certificate>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</X509Certificate></X509Data></KeyInfo></Signature></NFe>', '<?xml version=\'1.0\' encoding=\'utf-8\'?><soapenv:Envelope xmlns:soapenv=\"http://www.w3.org/2003/05/soap-envelope\"><soapenv:Body><nfeResultMsg xmlns=\"http://www.portalfiscal.inf.br/nfe/wsdl/NFeAutorizacao4\"><retEnviNFe xmlns=\"http://www.portalfiscal.inf.br/nfe\" versao=\"4.00\"><tpAmb>2</tpAmb><verAplic>AM3.10-4.00</verAplic><cStat>104</cStat><xMotivo>Lote processado</xMotivo><cUF>13</cUF><dhRecbto>2026-03-11T10:41:23-04:00</dhRecbto><protNFe versao=\"4.00\"><infProt><tpAmb>2</tpAmb><verAplic>AM3.10-4.00</verAplic><chNFe>13260359598453000104650010000000141777947015</chNFe><dhRecbto>2026-03-11T10:41:23-04:00</dhRecbto><cStat>373</cStat><xMotivo>Rejeicao: Descricao do primeiro item diferente de NOTA FISCAL EMITIDA EM AMBIENTE DE HOMOLOGACAO - SEM VALOR FISCAL</xMotivo></infProt></protNFe></retEnviNFe></nfeResultMsg></soapenv:Body></soapenv:Envelope>', 60.00, 0.00, '{\"tPag\":\"01\"}', '2026-03-11 14:41:23'),
(2, '125', 77, 2, 1, 15, '13260359598453000104650010000000151123540320', NULL, '104', 'Lote processado', '<?xml version=\"1.0\" encoding=\"UTF-8\"?><nfeProc xmlns=\"http://www.portalfiscal.inf.br/nfe\" versao=\"4.00\"><NFe xmlns=\"http://www.portalfiscal.inf.br/nfe\"><infNFe Id=\"NFe13260359598453000104650010000000151123540320\" versao=\"4.00\"><ide><cUF>13</cUF><cNF>12354032</cNF><natOp>VENDA</natOp><mod>65</mod><serie>1</serie><nNF>15</nNF><dhEmi>2026-03-11T10:42:03-04:00</dhEmi><tpNF>1</tpNF><idDest>1</idDest><cMunFG>1301209</cMunFG><tpImp>4</tpImp><tpEmis>1</tpEmis><cDV>0</cDV><tpAmb>2</tpAmb><finNFe>1</finNFe><indFinal>1</indFinal><indPres>1</indPres><procEmi>0</procEmi><verProc>PDV-ACAI-1.0</verProc></ide><emit><CNPJ>59598453000104</CNPJ><xNome>PAPAGAIO COMERCIO DE MOTOS LTDA</xNome><xFant>PAPAGAIO MOTOS</xFant><enderEmit><xLgr>PADRE VICENTE NOGUEIRA</xLgr><nro>149</nro><xBairro>ITAMARATI</xBairro><cMun>1301209</cMun><xMun>COARI</xMun><UF>AM</UF><CEP>69460000</CEP><cPais>1058</cPais><xPais>Brasil</xPais><fone>9791979595</fone></enderEmit><IE>054756448</IE><CRT>3</CRT></emit><det nItem=\"1\"><prod><cProd>1</cProd><cEAN>SEM GTIN</cEAN><xProd>Buzina 12v c100</xProd><NCM>85123000</NCM><CFOP>5102</CFOP><uCom>UN</uCom><qCom>1.000</qCom><vUnCom>60.00</vUnCom><vProd>60.00</vProd><cEANTrib>SEM GTIN</cEANTrib><uTrib>UN</uTrib><qTrib>1.000</qTrib><vUnTrib>60.00</vUnTrib><indTot>1</indTot></prod><imposto><ICMS><ICMSSN102><orig>0</orig><CSOSN>102</CSOSN></ICMSSN102></ICMS><PIS><PISNT><CST>07</CST></PISNT></PIS><COFINS><COFINSNT><CST>07</CST></COFINSNT></COFINS></imposto></det><total><ICMSTot><vBC>0.00</vBC><vICMS>0.00</vICMS><vICMSDeson>0.00</vICMSDeson><vFCP>0.00</vFCP><vBCST>0.00</vBCST><vST>0.00</vST><vFCPST>0.00</vFCPST><vFCPSTRet>0.00</vFCPSTRet><vProd>60.00</vProd><vFrete>0.00</vFrete><vSeg>0.00</vSeg><vDesc>0.00</vDesc><vII>0.00</vII><vIPI>0.00</vIPI><vIPIDevol>0.00</vIPIDevol><vPIS>0.00</vPIS><vCOFINS>0.00</vCOFINS><vOutro>0.00</vOutro><vNF>60.00</vNF></ICMSTot></total><transp><modFrete>9</modFrete></transp><pag><detPag><indPag>0</indPag><tPag>01</tPag><vPag>60.00</vPag></detPag></pag><infAdic><infCpl>PDV A&#xE7;aiteria</infCpl></infAdic></infNFe><infNFeSupl><qrCode>https://sistemas.sefaz.am.gov.br/nfceweb-hom/consultarNFCe.jsp?p=13260359598453000104650010000000151123540320|2|2|2|FCAECFC18298D9FACCD5C6C400E7EFE673A2C756</qrCode><urlChave>www.sefaz.am.gov.br/nfce/consulta</urlChave></infNFeSupl><Signature xmlns=\"http://www.w3.org/2000/09/xmldsig#\"><SignedInfo><CanonicalizationMethod Algorithm=\"http://www.w3.org/TR/2001/REC-xml-c14n-20010315\"/><SignatureMethod Algorithm=\"http://www.w3.org/2000/09/xmldsig#rsa-sha1\"/><Reference URI=\"#NFe13260359598453000104650010000000151123540320\"><Transforms><Transform Algorithm=\"http://www.w3.org/2000/09/xmldsig#enveloped-signature\"/><Transform Algorithm=\"http://www.w3.org/TR/2001/REC-xml-c14n-20010315\"/></Transforms><DigestMethod Algorithm=\"http://www.w3.org/2000/09/xmldsig#sha1\"/><DigestValue>nv97JJuIEttYa15f+nE5qgL/rIU=</DigestValue></Reference></SignedInfo><SignatureValue>bQBXFUnnosgZQqVvEfvCuYzn/wLKpVc0bPnP9KoiyLKKk24Fx20BGdcfH2GAUIN527nCGqvUByoYXJpyBMyvVAlGZOdoJsLB3xgtS3l/rfdfyXklQ5asp7nnWD4oaogqQ31nHyeSjJ8jQUYZ9wJzTi/2BzZbeMHSYI2ZhvSzUIgC+z86iSvd4ao0IvkGGmt78wHFuBPvkgK+dxJhAHnkWjUakigQqe6y050aPWd3cC5/a0cMY4DQhcvcbCshcxgeqJsNPVc19m0n2ZeoVtMa+l2S5g1I5L835XWfVJIolhS8NRQuqrOISJMgRbL0pGlgH2rQ0pQVDjvGPifwW7e/UA==</SignatureValue><KeyInfo><X509Data><X509Certificate>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</X509Certificate></X509Data></KeyInfo></Signature></NFe><protNFe versao=\"4.00\"><infProt><tpAmb>2</tpAmb><verAplic>AM3.10-4.00</verAplic><chNFe>13260359598453000104650010000000151123540320</chNFe><dhRecbto>2026-03-11T10:42:04-04:00</dhRecbto><cStat>373</cStat><xMotivo>Rejeicao: Descricao do primeiro item diferente de NOTA FISCAL EMITIDA EM AMBIENTE DE HOMOLOGACAO - SEM VALOR FISCAL</xMotivo></infProt></protNFe></nfeProc>', '<?xml version=\"1.0\"?><NFe xmlns=\"http://www.portalfiscal.inf.br/nfe\"><infNFe Id=\"NFe13260359598453000104650010000000151123540320\" versao=\"4.00\"><ide><cUF>13</cUF><cNF>12354032</cNF><natOp>VENDA</natOp><mod>65</mod><serie>1</serie><nNF>15</nNF><dhEmi>2026-03-11T10:42:03-04:00</dhEmi><tpNF>1</tpNF><idDest>1</idDest><cMunFG>1301209</cMunFG><tpImp>4</tpImp><tpEmis>1</tpEmis><cDV>0</cDV><tpAmb>2</tpAmb><finNFe>1</finNFe><indFinal>1</indFinal><indPres>1</indPres><procEmi>0</procEmi><verProc>PDV-ACAI-1.0</verProc></ide><emit><CNPJ>59598453000104</CNPJ><xNome>PAPAGAIO COMERCIO DE MOTOS LTDA</xNome><xFant>PAPAGAIO MOTOS</xFant><enderEmit><xLgr>PADRE VICENTE NOGUEIRA</xLgr><nro>149</nro><xBairro>ITAMARATI</xBairro><cMun>1301209</cMun><xMun>COARI</xMun><UF>AM</UF><CEP>69460000</CEP><cPais>1058</cPais><xPais>Brasil</xPais><fone>9791979595</fone></enderEmit><IE>054756448</IE><CRT>3</CRT></emit><det nItem=\"1\"><prod><cProd>1</cProd><cEAN>SEM GTIN</cEAN><xProd>Buzina 12v c100</xProd><NCM>85123000</NCM><CFOP>5102</CFOP><uCom>UN</uCom><qCom>1.000</qCom><vUnCom>60.00</vUnCom><vProd>60.00</vProd><cEANTrib>SEM GTIN</cEANTrib><uTrib>UN</uTrib><qTrib>1.000</qTrib><vUnTrib>60.00</vUnTrib><indTot>1</indTot></prod><imposto><ICMS><ICMSSN102><orig>0</orig><CSOSN>102</CSOSN></ICMSSN102></ICMS><PIS><PISNT><CST>07</CST></PISNT></PIS><COFINS><COFINSNT><CST>07</CST></COFINSNT></COFINS></imposto></det><total><ICMSTot><vBC>0.00</vBC><vICMS>0.00</vICMS><vICMSDeson>0.00</vICMSDeson><vFCP>0.00</vFCP><vBCST>0.00</vBCST><vST>0.00</vST><vFCPST>0.00</vFCPST><vFCPSTRet>0.00</vFCPSTRet><vProd>60.00</vProd><vFrete>0.00</vFrete><vSeg>0.00</vSeg><vDesc>0.00</vDesc><vII>0.00</vII><vIPI>0.00</vIPI><vIPIDevol>0.00</vIPIDevol><vPIS>0.00</vPIS><vCOFINS>0.00</vCOFINS><vOutro>0.00</vOutro><vNF>60.00</vNF></ICMSTot></total><transp><modFrete>9</modFrete></transp><pag><detPag><indPag>0</indPag><tPag>01</tPag><vPag>60.00</vPag></detPag></pag><infAdic><infCpl>PDV A&#xE7;aiteria</infCpl></infAdic></infNFe><infNFeSupl><qrCode>https://sistemas.sefaz.am.gov.br/nfceweb-hom/consultarNFCe.jsp?p=13260359598453000104650010000000151123540320|2|2|2|FCAECFC18298D9FACCD5C6C400E7EFE673A2C756</qrCode><urlChave>www.sefaz.am.gov.br/nfce/consulta</urlChave></infNFeSupl><Signature xmlns=\"http://www.w3.org/2000/09/xmldsig#\"><SignedInfo><CanonicalizationMethod Algorithm=\"http://www.w3.org/TR/2001/REC-xml-c14n-20010315\"/><SignatureMethod Algorithm=\"http://www.w3.org/2000/09/xmldsig#rsa-sha1\"/><Reference URI=\"#NFe13260359598453000104650010000000151123540320\"><Transforms><Transform Algorithm=\"http://www.w3.org/2000/09/xmldsig#enveloped-signature\"/><Transform Algorithm=\"http://www.w3.org/TR/2001/REC-xml-c14n-20010315\"/></Transforms><DigestMethod Algorithm=\"http://www.w3.org/2000/09/xmldsig#sha1\"/><DigestValue>nv97JJuIEttYa15f+nE5qgL/rIU=</DigestValue></Reference></SignedInfo><SignatureValue>bQBXFUnnosgZQqVvEfvCuYzn/wLKpVc0bPnP9KoiyLKKk24Fx20BGdcfH2GAUIN527nCGqvUByoYXJpyBMyvVAlGZOdoJsLB3xgtS3l/rfdfyXklQ5asp7nnWD4oaogqQ31nHyeSjJ8jQUYZ9wJzTi/2BzZbeMHSYI2ZhvSzUIgC+z86iSvd4ao0IvkGGmt78wHFuBPvkgK+dxJhAHnkWjUakigQqe6y050aPWd3cC5/a0cMY4DQhcvcbCshcxgeqJsNPVc19m0n2ZeoVtMa+l2S5g1I5L835XWfVJIolhS8NRQuqrOISJMgRbL0pGlgH2rQ0pQVDjvGPifwW7e/UA==</SignatureValue><KeyInfo><X509Data><X509Certificate>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</X509Certificate></X509Data></KeyInfo></Signature></NFe>', '<?xml version=\'1.0\' encoding=\'utf-8\'?><soapenv:Envelope xmlns:soapenv=\"http://www.w3.org/2003/05/soap-envelope\"><soapenv:Body><nfeResultMsg xmlns=\"http://www.portalfiscal.inf.br/nfe/wsdl/NFeAutorizacao4\"><retEnviNFe xmlns=\"http://www.portalfiscal.inf.br/nfe\" versao=\"4.00\"><tpAmb>2</tpAmb><verAplic>AM3.10-4.00</verAplic><cStat>104</cStat><xMotivo>Lote processado</xMotivo><cUF>13</cUF><dhRecbto>2026-03-11T10:42:04-04:00</dhRecbto><protNFe versao=\"4.00\"><infProt><tpAmb>2</tpAmb><verAplic>AM3.10-4.00</verAplic><chNFe>13260359598453000104650010000000151123540320</chNFe><dhRecbto>2026-03-11T10:42:04-04:00</dhRecbto><cStat>373</cStat><xMotivo>Rejeicao: Descricao do primeiro item diferente de NOTA FISCAL EMITIDA EM AMBIENTE DE HOMOLOGACAO - SEM VALOR FISCAL</xMotivo></infProt></protNFe></retEnviNFe></nfeResultMsg></soapenv:Body></soapenv:Envelope>', 60.00, 0.00, '{\"tPag\":\"01\"}', '2026-03-11 14:42:04'),
(3, '125', 78, 2, 1, 16, '13260359598453000104650010000000161697050510', NULL, '104', 'Lote processado', '<?xml version=\"1.0\" encoding=\"UTF-8\"?><nfeProc xmlns=\"http://www.portalfiscal.inf.br/nfe\" versao=\"4.00\"><NFe xmlns=\"http://www.portalfiscal.inf.br/nfe\"><infNFe Id=\"NFe13260359598453000104650010000000161697050510\" versao=\"4.00\"><ide><cUF>13</cUF><cNF>69705051</cNF><natOp>VENDA</natOp><mod>65</mod><serie>1</serie><nNF>16</nNF><dhEmi>2026-03-11T10:45:22-04:00</dhEmi><tpNF>1</tpNF><idDest>1</idDest><cMunFG>1301209</cMunFG><tpImp>4</tpImp><tpEmis>1</tpEmis><cDV>0</cDV><tpAmb>2</tpAmb><finNFe>1</finNFe><indFinal>1</indFinal><indPres>1</indPres><procEmi>0</procEmi><verProc>PDV-ACAI-1.0</verProc></ide><emit><CNPJ>59598453000104</CNPJ><xNome>PAPAGAIO COMERCIO DE MOTOS LTDA</xNome><xFant>PAPAGAIO MOTOS</xFant><enderEmit><xLgr>PADRE VICENTE NOGUEIRA</xLgr><nro>149</nro><xBairro>ITAMARATI</xBairro><cMun>1301209</cMun><xMun>COARI</xMun><UF>AM</UF><CEP>69460000</CEP><cPais>1058</cPais><xPais>Brasil</xPais><fone>9791979595</fone></enderEmit><IE>054756448</IE><CRT>3</CRT></emit><det nItem=\"1\"><prod><cProd>1</cProd><cEAN>SEM GTIN</cEAN><xProd>Buzina 12v c100</xProd><NCM>85123000</NCM><CFOP>5102</CFOP><uCom>UN</uCom><qCom>1.000</qCom><vUnCom>60.00</vUnCom><vProd>60.00</vProd><cEANTrib>SEM GTIN</cEANTrib><uTrib>UN</uTrib><qTrib>1.000</qTrib><vUnTrib>60.00</vUnTrib><indTot>1</indTot></prod><imposto><ICMS><ICMSSN102><orig>0</orig><CSOSN>102</CSOSN></ICMSSN102></ICMS><PIS><PISNT><CST>07</CST></PISNT></PIS><COFINS><COFINSNT><CST>07</CST></COFINSNT></COFINS></imposto></det><total><ICMSTot><vBC>0.00</vBC><vICMS>0.00</vICMS><vICMSDeson>0.00</vICMSDeson><vFCP>0.00</vFCP><vBCST>0.00</vBCST><vST>0.00</vST><vFCPST>0.00</vFCPST><vFCPSTRet>0.00</vFCPSTRet><vProd>60.00</vProd><vFrete>0.00</vFrete><vSeg>0.00</vSeg><vDesc>0.00</vDesc><vII>0.00</vII><vIPI>0.00</vIPI><vIPIDevol>0.00</vIPIDevol><vPIS>0.00</vPIS><vCOFINS>0.00</vCOFINS><vOutro>0.00</vOutro><vNF>60.00</vNF></ICMSTot></total><transp><modFrete>9</modFrete></transp><pag><detPag><indPag>0</indPag><tPag>01</tPag><vPag>60.00</vPag></detPag></pag><infAdic><infCpl>PDV A&#xE7;aiteria</infCpl></infAdic></infNFe><infNFeSupl><qrCode>https://sistemas.sefaz.am.gov.br/nfceweb-hom/consultarNFCe.jsp?p=13260359598453000104650010000000161697050510|2|2|2|2B0B162551B9306F133BA58D1D3703291853E22F</qrCode><urlChave>www.sefaz.am.gov.br/nfce/consulta</urlChave></infNFeSupl><Signature xmlns=\"http://www.w3.org/2000/09/xmldsig#\"><SignedInfo><CanonicalizationMethod Algorithm=\"http://www.w3.org/TR/2001/REC-xml-c14n-20010315\"/><SignatureMethod Algorithm=\"http://www.w3.org/2000/09/xmldsig#rsa-sha1\"/><Reference URI=\"#NFe13260359598453000104650010000000161697050510\"><Transforms><Transform Algorithm=\"http://www.w3.org/2000/09/xmldsig#enveloped-signature\"/><Transform Algorithm=\"http://www.w3.org/TR/2001/REC-xml-c14n-20010315\"/></Transforms><DigestMethod Algorithm=\"http://www.w3.org/2000/09/xmldsig#sha1\"/><DigestValue>5hzNJrfcJsB9DQggM6X09UAfI9E=</DigestValue></Reference></SignedInfo><SignatureValue>NET2KbRJy+l1VbzmhyCqtQudPOQFS6mpCJUIT0M+aSJzUcgz6J6k1yP7LRT62nPTo7Oa28S3hgzizITeX83lGCL5g+nzDeN0oJw44l0EUWleGF0+mNLNeanzbhR8OXhdApUcX1JLy0ZKjZx0uuFebJLOj3gJFfmpBWuKWSrcKBEXzCu98+D7dvY/ND1/9vT9/CAtvWYxFazVqZyrN7/9yvHi3k1HH0fDJAQUGchtWEy451Ufzm68xkDI+FDUGUsggAJ0QdMH0abSEMo307pIqvHv1Uxx66DWVwji9sb51kcLYD2z9FYHWMiuBdwIdfEVFLzyqdDY9LzXcQfbEDQXRw==</SignatureValue><KeyInfo><X509Data><X509Certificate>MIIH6DCCBdCgAwIBAgIKHqWXJoQ1LKoMazANBgkqhkiG9w0BAQsFADBbMQswCQYDVQQGEwJCUjEWMBQGA1UECwwNQUMgU3luZ3VsYXJJRDETMBEGA1UECgwKSUNQLUJyYXNpbDEfMB0GA1UEAwwWQUMgU3luZ3VsYXJJRCBNdWx0aXBsYTAeFw0yNTA2MDYyMDMzMzVaFw0yNjA2MDYyMDMzMzVaMIHOMQswCQYDVQQGEwJCUjETMBEGA1UECgwKSUNQLUJyYXNpbDEiMCAGA1UECwwZQ2VydGlmaWNhZG8gRGlnaXRhbCBQSiBBMTETMBEGA1UECwwKUHJlc2VuY2lhbDEXMBUGA1UECwwONDU2MTYzMDkwMDAxNDkxHzAdBgNVBAsMFkFDIFN5bmd1bGFySUQgTXVsdGlwbGExNzA1BgNVBAMMLlBBUEFHQUlPIENPTUVSQ0lPIERFIE1PVE9TIExUREE6NTk1OTg0NTMwMDAxMDQwggEiMA0GCSqGSIb3DQEBAQUAA4IBDwAwggEKAoIBAQC4B4OKj+3kr6hnMnIFUA43tacQhEJmlvGAKVoXSiBo+30gia2+nmAly72AhkoWVIEO2q+I5o5RNgEA3jegdKJCL0jvFiJK/xPfDueVmt/3E/9N+jhOSaXScEDFtrPF6/nlkiKmrlgYyst/uWSCtg3fYzu4BfCTHJ1LL5nuoP4i2FrYaxunvwpg+NzSwvpXeWxBg2UOYRYC+LM6bMJluy+CoQzNKt2RoD8ljHdpzHY10bSL5jkLQOxUCE52SSrDrMD0HmKy6oylYL7xKLbuhiZIgCODNs6mS8bX19mgTZQ7PXs0seuiFuP+M2++rRlQyg2skdwMRDLBykJQSHWs0DP5AgMBAAGjggM4MIIDNDAOBgNVHQ8BAf8EBAMCBeAwHQYDVR0lBBYwFAYIKwYBBQUHAwQGCCsGAQUFBwMCMAkGA1UdEwQCMAAwHwYDVR0jBBgwFoAUk+H/fh3l9eRN4TliiyFpleavchYwHQYDVR0OBBYEFGxSV0SZ6AU0rrnMDKuDbgUDLeQeMH8GCCsGAQUFBwEBBHMwcTBvBggrBgEFBQcwAoZjaHR0cDovL3N5bmd1bGFyaWQuY29tLmJyL3JlcG9zaXRvcmlvL2FjLXN5bmd1bGFyaWQtbXVsdGlwbGEvY2VydGlmaWNhZG9zL2FjLXN5bmd1bGFyaWQtbXVsdGlwbGEucDdiMIGCBgNVHSAEezB5MHcGB2BMAQIBgQUwbDBqBggrBgEFBQcCARZeaHR0cDovL3N5bmd1bGFyaWQuY29tLmJyL3JlcG9zaXRvcmlvL2FjLXN5bmd1bGFyaWQtbXVsdGlwbGEvZHBjL2RwYy1hYy1zeW5ndWxhcklELW11bHRpcGxhLnBkZjCBzAYDVR0RBIHEMIHBoCoGBWBMAQMCoCEEH0xBWkFSTyBDT1JERUlSTyBERSBBTE1FSURBIE5FVE+gGQYFYEwBAwOgEAQONTk1OTg0NTMwMDAxMDSgQgYFYEwBAwSgOQQ3MDQwMjE5OTUwMzM5NTQ2MDI2NjAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMKAXBgVgTAEDB6AOBAwwMDAwMDAwMDAwMDCBG0NPUkRFSVJPTEFaQVJPODcwQEdNQUlMLkNPTTCB4gYDVR0fBIHaMIHXMG+gbaBrhmlodHRwOi8vaWNwLWJyYXNpbC5zeW5ndWxhcmlkLmNvbS5ici9yZXBvc2l0b3Jpby9hYy1zeW5ndWxhcmlkLW11bHRpcGxhL2xjci9sY3ItYWMtc3luZ3VsYXJpZC1tdWx0aXBsYS5jcmwwZKBioGCGXmh0dHA6Ly9zeW5ndWxhcmlkLmNvbS5ici9yZXBvc2l0b3Jpby9hYy1zeW5ndWxhcmlkLW11bHRpcGxhL2xjci9sY3ItYWMtc3luZ3VsYXJpZC1tdWx0aXBsYS5jcmwwDQYJKoZIhvcNAQELBQADggIBACI8nwyikS2ZY4yjPZ68tXNvHbyfoCizJsYbqLavXaIzI9D0VBqEyJAvpZVA8JQQ4qMm2rZkZmZjm4ucYTNezZf7OLX5STql96fOLYgFXHCpVMfG251bhsLpwBJmgES9FtG7+DrdLkQ+FAX7vkVgNKT70AQnDwY9cSM2ghSLiv+fu8ubLwk4rlrc7y9y6QGAB4kvbHY5J8yWdfMnQMPF/raR7mFuWG1co+UjXDm4E69K3VoFy3B9Jq1u2sUdryVjVaamOlLwhoFz8UPJm9Bjn1E1OiuSc2MJ1gUGPzwqvsNgasiq7D9/XuMUCeYia2BBpdy7Z+LKDKO2vtFZ11N4O+gbXLykxqnuwn8Iiigqu7+txxRPj/v2+i8ckWUyONx3zRZvlHXsfk4gTyLqK1+VJ0NqIK1A11DQLc5uaTPQe+DjmqSNFbDKsoKjhySK0Fs7yT1FXDWyvqMNmJkl/HVdVbtQesk58PvGlpTnDunlJDCL5Lcyfm59yFc5bBF9HBS5xMSfG7Vnk0JvQUYpkdf1NOI5PzbJhsN/TYvdDpgTDCJ9DILMrnqovzvGhkG46R8sQIhF93HygHgXrHRSSBySv0QnDbgkv8tnu0gXQRyzadWVD5nBQakzQVIbWzM0h5DF2n300mDE+fGhqcDz2iXqdCNmuVwurA/pc4ZIJyvfMj/Q</X509Certificate></X509Data></KeyInfo></Signature></NFe><protNFe versao=\"4.00\"><infProt><tpAmb>2</tpAmb><verAplic>AM3.10-4.00</verAplic><chNFe>13260359598453000104650010000000161697050510</chNFe><dhRecbto>2026-03-11T10:45:23-04:00</dhRecbto><cStat>373</cStat><xMotivo>Rejeicao: Descricao do primeiro item diferente de NOTA FISCAL EMITIDA EM AMBIENTE DE HOMOLOGACAO - SEM VALOR FISCAL</xMotivo></infProt></protNFe></nfeProc>', '<?xml version=\"1.0\"?><NFe xmlns=\"http://www.portalfiscal.inf.br/nfe\"><infNFe Id=\"NFe13260359598453000104650010000000161697050510\" versao=\"4.00\"><ide><cUF>13</cUF><cNF>69705051</cNF><natOp>VENDA</natOp><mod>65</mod><serie>1</serie><nNF>16</nNF><dhEmi>2026-03-11T10:45:22-04:00</dhEmi><tpNF>1</tpNF><idDest>1</idDest><cMunFG>1301209</cMunFG><tpImp>4</tpImp><tpEmis>1</tpEmis><cDV>0</cDV><tpAmb>2</tpAmb><finNFe>1</finNFe><indFinal>1</indFinal><indPres>1</indPres><procEmi>0</procEmi><verProc>PDV-ACAI-1.0</verProc></ide><emit><CNPJ>59598453000104</CNPJ><xNome>PAPAGAIO COMERCIO DE MOTOS LTDA</xNome><xFant>PAPAGAIO MOTOS</xFant><enderEmit><xLgr>PADRE VICENTE NOGUEIRA</xLgr><nro>149</nro><xBairro>ITAMARATI</xBairro><cMun>1301209</cMun><xMun>COARI</xMun><UF>AM</UF><CEP>69460000</CEP><cPais>1058</cPais><xPais>Brasil</xPais><fone>9791979595</fone></enderEmit><IE>054756448</IE><CRT>3</CRT></emit><det nItem=\"1\"><prod><cProd>1</cProd><cEAN>SEM GTIN</cEAN><xProd>Buzina 12v c100</xProd><NCM>85123000</NCM><CFOP>5102</CFOP><uCom>UN</uCom><qCom>1.000</qCom><vUnCom>60.00</vUnCom><vProd>60.00</vProd><cEANTrib>SEM GTIN</cEANTrib><uTrib>UN</uTrib><qTrib>1.000</qTrib><vUnTrib>60.00</vUnTrib><indTot>1</indTot></prod><imposto><ICMS><ICMSSN102><orig>0</orig><CSOSN>102</CSOSN></ICMSSN102></ICMS><PIS><PISNT><CST>07</CST></PISNT></PIS><COFINS><COFINSNT><CST>07</CST></COFINSNT></COFINS></imposto></det><total><ICMSTot><vBC>0.00</vBC><vICMS>0.00</vICMS><vICMSDeson>0.00</vICMSDeson><vFCP>0.00</vFCP><vBCST>0.00</vBCST><vST>0.00</vST><vFCPST>0.00</vFCPST><vFCPSTRet>0.00</vFCPSTRet><vProd>60.00</vProd><vFrete>0.00</vFrete><vSeg>0.00</vSeg><vDesc>0.00</vDesc><vII>0.00</vII><vIPI>0.00</vIPI><vIPIDevol>0.00</vIPIDevol><vPIS>0.00</vPIS><vCOFINS>0.00</vCOFINS><vOutro>0.00</vOutro><vNF>60.00</vNF></ICMSTot></total><transp><modFrete>9</modFrete></transp><pag><detPag><indPag>0</indPag><tPag>01</tPag><vPag>60.00</vPag></detPag></pag><infAdic><infCpl>PDV A&#xE7;aiteria</infCpl></infAdic></infNFe><infNFeSupl><qrCode>https://sistemas.sefaz.am.gov.br/nfceweb-hom/consultarNFCe.jsp?p=13260359598453000104650010000000161697050510|2|2|2|2B0B162551B9306F133BA58D1D3703291853E22F</qrCode><urlChave>www.sefaz.am.gov.br/nfce/consulta</urlChave></infNFeSupl><Signature xmlns=\"http://www.w3.org/2000/09/xmldsig#\"><SignedInfo><CanonicalizationMethod Algorithm=\"http://www.w3.org/TR/2001/REC-xml-c14n-20010315\"/><SignatureMethod Algorithm=\"http://www.w3.org/2000/09/xmldsig#rsa-sha1\"/><Reference URI=\"#NFe13260359598453000104650010000000161697050510\"><Transforms><Transform Algorithm=\"http://www.w3.org/2000/09/xmldsig#enveloped-signature\"/><Transform Algorithm=\"http://www.w3.org/TR/2001/REC-xml-c14n-20010315\"/></Transforms><DigestMethod Algorithm=\"http://www.w3.org/2000/09/xmldsig#sha1\"/><DigestValue>5hzNJrfcJsB9DQggM6X09UAfI9E=</DigestValue></Reference></SignedInfo><SignatureValue>NET2KbRJy+l1VbzmhyCqtQudPOQFS6mpCJUIT0M+aSJzUcgz6J6k1yP7LRT62nPTo7Oa28S3hgzizITeX83lGCL5g+nzDeN0oJw44l0EUWleGF0+mNLNeanzbhR8OXhdApUcX1JLy0ZKjZx0uuFebJLOj3gJFfmpBWuKWSrcKBEXzCu98+D7dvY/ND1/9vT9/CAtvWYxFazVqZyrN7/9yvHi3k1HH0fDJAQUGchtWEy451Ufzm68xkDI+FDUGUsggAJ0QdMH0abSEMo307pIqvHv1Uxx66DWVwji9sb51kcLYD2z9FYHWMiuBdwIdfEVFLzyqdDY9LzXcQfbEDQXRw==</SignatureValue><KeyInfo><X509Data><X509Certificate>MIIH6DCCBdCgAwIBAgIKHqWXJoQ1LKoMazANBgkqhkiG9w0BAQsFADBbMQswCQYDVQQGEwJCUjEWMBQGA1UECwwNQUMgU3luZ3VsYXJJRDETMBEGA1UECgwKSUNQLUJyYXNpbDEfMB0GA1UEAwwWQUMgU3luZ3VsYXJJRCBNdWx0aXBsYTAeFw0yNTA2MDYyMDMzMzVaFw0yNjA2MDYyMDMzMzVaMIHOMQswCQYDVQQGEwJCUjETMBEGA1UECgwKSUNQLUJyYXNpbDEiMCAGA1UECwwZQ2VydGlmaWNhZG8gRGlnaXRhbCBQSiBBMTETMBEGA1UECwwKUHJlc2VuY2lhbDEXMBUGA1UECwwONDU2MTYzMDkwMDAxNDkxHzAdBgNVBAsMFkFDIFN5bmd1bGFySUQgTXVsdGlwbGExNzA1BgNVBAMMLlBBUEFHQUlPIENPTUVSQ0lPIERFIE1PVE9TIExUREE6NTk1OTg0NTMwMDAxMDQwggEiMA0GCSqGSIb3DQEBAQUAA4IBDwAwggEKAoIBAQC4B4OKj+3kr6hnMnIFUA43tacQhEJmlvGAKVoXSiBo+30gia2+nmAly72AhkoWVIEO2q+I5o5RNgEA3jegdKJCL0jvFiJK/xPfDueVmt/3E/9N+jhOSaXScEDFtrPF6/nlkiKmrlgYyst/uWSCtg3fYzu4BfCTHJ1LL5nuoP4i2FrYaxunvwpg+NzSwvpXeWxBg2UOYRYC+LM6bMJluy+CoQzNKt2RoD8ljHdpzHY10bSL5jkLQOxUCE52SSrDrMD0HmKy6oylYL7xKLbuhiZIgCODNs6mS8bX19mgTZQ7PXs0seuiFuP+M2++rRlQyg2skdwMRDLBykJQSHWs0DP5AgMBAAGjggM4MIIDNDAOBgNVHQ8BAf8EBAMCBeAwHQYDVR0lBBYwFAYIKwYBBQUHAwQGCCsGAQUFBwMCMAkGA1UdEwQCMAAwHwYDVR0jBBgwFoAUk+H/fh3l9eRN4TliiyFpleavchYwHQYDVR0OBBYEFGxSV0SZ6AU0rrnMDKuDbgUDLeQeMH8GCCsGAQUFBwEBBHMwcTBvBggrBgEFBQcwAoZjaHR0cDovL3N5bmd1bGFyaWQuY29tLmJyL3JlcG9zaXRvcmlvL2FjLXN5bmd1bGFyaWQtbXVsdGlwbGEvY2VydGlmaWNhZG9zL2FjLXN5bmd1bGFyaWQtbXVsdGlwbGEucDdiMIGCBgNVHSAEezB5MHcGB2BMAQIBgQUwbDBqBggrBgEFBQcCARZeaHR0cDovL3N5bmd1bGFyaWQuY29tLmJyL3JlcG9zaXRvcmlvL2FjLXN5bmd1bGFyaWQtbXVsdGlwbGEvZHBjL2RwYy1hYy1zeW5ndWxhcklELW11bHRpcGxhLnBkZjCBzAYDVR0RBIHEMIHBoCoGBWBMAQMCoCEEH0xBWkFSTyBDT1JERUlSTyBERSBBTE1FSURBIE5FVE+gGQYFYEwBAwOgEAQONTk1OTg0NTMwMDAxMDSgQgYFYEwBAwSgOQQ3MDQwMjE5OTUwMzM5NTQ2MDI2NjAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMKAXBgVgTAEDB6AOBAwwMDAwMDAwMDAwMDCBG0NPUkRFSVJPTEFaQVJPODcwQEdNQUlMLkNPTTCB4gYDVR0fBIHaMIHXMG+gbaBrhmlodHRwOi8vaWNwLWJyYXNpbC5zeW5ndWxhcmlkLmNvbS5ici9yZXBvc2l0b3Jpby9hYy1zeW5ndWxhcmlkLW11bHRpcGxhL2xjci9sY3ItYWMtc3luZ3VsYXJpZC1tdWx0aXBsYS5jcmwwZKBioGCGXmh0dHA6Ly9zeW5ndWxhcmlkLmNvbS5ici9yZXBvc2l0b3Jpby9hYy1zeW5ndWxhcmlkLW11bHRpcGxhL2xjci9sY3ItYWMtc3luZ3VsYXJpZC1tdWx0aXBsYS5jcmwwDQYJKoZIhvcNAQELBQADggIBACI8nwyikS2ZY4yjPZ68tXNvHbyfoCizJsYbqLavXaIzI9D0VBqEyJAvpZVA8JQQ4qMm2rZkZmZjm4ucYTNezZf7OLX5STql96fOLYgFXHCpVMfG251bhsLpwBJmgES9FtG7+DrdLkQ+FAX7vkVgNKT70AQnDwY9cSM2ghSLiv+fu8ubLwk4rlrc7y9y6QGAB4kvbHY5J8yWdfMnQMPF/raR7mFuWG1co+UjXDm4E69K3VoFy3B9Jq1u2sUdryVjVaamOlLwhoFz8UPJm9Bjn1E1OiuSc2MJ1gUGPzwqvsNgasiq7D9/XuMUCeYia2BBpdy7Z+LKDKO2vtFZ11N4O+gbXLykxqnuwn8Iiigqu7+txxRPj/v2+i8ckWUyONx3zRZvlHXsfk4gTyLqK1+VJ0NqIK1A11DQLc5uaTPQe+DjmqSNFbDKsoKjhySK0Fs7yT1FXDWyvqMNmJkl/HVdVbtQesk58PvGlpTnDunlJDCL5Lcyfm59yFc5bBF9HBS5xMSfG7Vnk0JvQUYpkdf1NOI5PzbJhsN/TYvdDpgTDCJ9DILMrnqovzvGhkG46R8sQIhF93HygHgXrHRSSBySv0QnDbgkv8tnu0gXQRyzadWVD5nBQakzQVIbWzM0h5DF2n300mDE+fGhqcDz2iXqdCNmuVwurA/pc4ZIJyvfMj/Q</X509Certificate></X509Data></KeyInfo></Signature></NFe>', '<?xml version=\'1.0\' encoding=\'utf-8\'?><soapenv:Envelope xmlns:soapenv=\"http://www.w3.org/2003/05/soap-envelope\"><soapenv:Body><nfeResultMsg xmlns=\"http://www.portalfiscal.inf.br/nfe/wsdl/NFeAutorizacao4\"><retEnviNFe xmlns=\"http://www.portalfiscal.inf.br/nfe\" versao=\"4.00\"><tpAmb>2</tpAmb><verAplic>AM3.10-4.00</verAplic><cStat>104</cStat><xMotivo>Lote processado</xMotivo><cUF>13</cUF><dhRecbto>2026-03-11T10:45:23-04:00</dhRecbto><protNFe versao=\"4.00\"><infProt><tpAmb>2</tpAmb><verAplic>AM3.10-4.00</verAplic><chNFe>13260359598453000104650010000000161697050510</chNFe><dhRecbto>2026-03-11T10:45:23-04:00</dhRecbto><cStat>373</cStat><xMotivo>Rejeicao: Descricao do primeiro item diferente de NOTA FISCAL EMITIDA EM AMBIENTE DE HOMOLOGACAO - SEM VALOR FISCAL</xMotivo></infProt></protNFe></retEnviNFe></nfeResultMsg></soapenv:Body></soapenv:Envelope>', 60.00, 0.00, '{\"tPag\":\"01\"}', '2026-03-11 14:45:23');
INSERT INTO `nfce_emitidas` (`id`, `empresa_id`, `venda_id`, `ambiente`, `serie`, `numero`, `chave`, `protocolo`, `status_sefaz`, `mensagem`, `xml_nfeproc`, `xml_envio`, `xml_retorno`, `valor_total`, `valor_troco`, `tpag_json`, `created_at`) VALUES
(4, '125', 79, 2, 1, 17, '13260359598453000104650010000000171015925564', NULL, '104', 'Lote processado', '<?xml version=\"1.0\" encoding=\"UTF-8\"?><nfeProc xmlns=\"http://www.portalfiscal.inf.br/nfe\" versao=\"4.00\"><NFe xmlns=\"http://www.portalfiscal.inf.br/nfe\"><infNFe Id=\"NFe13260359598453000104650010000000171015925564\" versao=\"4.00\"><ide><cUF>13</cUF><cNF>01592556</cNF><natOp>VENDA</natOp><mod>65</mod><serie>1</serie><nNF>17</nNF><dhEmi>2026-03-11T10:47:55-04:00</dhEmi><tpNF>1</tpNF><idDest>1</idDest><cMunFG>1301209</cMunFG><tpImp>4</tpImp><tpEmis>1</tpEmis><cDV>4</cDV><tpAmb>2</tpAmb><finNFe>1</finNFe><indFinal>1</indFinal><indPres>1</indPres><procEmi>0</procEmi><verProc>PDV-ACAI-1.0</verProc></ide><emit><CNPJ>59598453000104</CNPJ><xNome>PAPAGAIO COMERCIO DE MOTOS LTDA</xNome><xFant>PAPAGAIO MOTOS</xFant><enderEmit><xLgr>PADRE VICENTE NOGUEIRA</xLgr><nro>149</nro><xBairro>ITAMARATI</xBairro><cMun>1301209</cMun><xMun>COARI</xMun><UF>AM</UF><CEP>69460000</CEP><cPais>1058</cPais><xPais>Brasil</xPais><fone>9791979595</fone></enderEmit><IE>054756448</IE><CRT>3</CRT></emit><det nItem=\"1\"><prod><cProd>1</cProd><cEAN>SEM GTIN</cEAN><xProd>Buzina 12v c100</xProd><NCM>85123000</NCM><CFOP>5102</CFOP><uCom>UN</uCom><qCom>1.000</qCom><vUnCom>60.00</vUnCom><vProd>60.00</vProd><cEANTrib>SEM GTIN</cEANTrib><uTrib>UN</uTrib><qTrib>1.000</qTrib><vUnTrib>60.00</vUnTrib><indTot>1</indTot></prod><imposto><ICMS><ICMSSN102><orig>0</orig><CSOSN>102</CSOSN></ICMSSN102></ICMS><PIS><PISNT><CST>07</CST></PISNT></PIS><COFINS><COFINSNT><CST>07</CST></COFINSNT></COFINS></imposto></det><total><ICMSTot><vBC>0.00</vBC><vICMS>0.00</vICMS><vICMSDeson>0.00</vICMSDeson><vFCP>0.00</vFCP><vBCST>0.00</vBCST><vST>0.00</vST><vFCPST>0.00</vFCPST><vFCPSTRet>0.00</vFCPSTRet><vProd>60.00</vProd><vFrete>0.00</vFrete><vSeg>0.00</vSeg><vDesc>0.00</vDesc><vII>0.00</vII><vIPI>0.00</vIPI><vIPIDevol>0.00</vIPIDevol><vPIS>0.00</vPIS><vCOFINS>0.00</vCOFINS><vOutro>0.00</vOutro><vNF>60.00</vNF></ICMSTot></total><transp><modFrete>9</modFrete></transp><pag><detPag><indPag>0</indPag><tPag>01</tPag><vPag>60.00</vPag></detPag></pag><infAdic><infCpl>PDV A&#xE7;aiteria</infCpl></infAdic></infNFe><infNFeSupl><qrCode>https://sistemas.sefaz.am.gov.br/nfceweb-hom/consultarNFCe.jsp?p=13260359598453000104650010000000171015925564|2|2|2|7C138A44CF4A108F7EB9E552E38E6789D95D4499</qrCode><urlChave>www.sefaz.am.gov.br/nfce/consulta</urlChave></infNFeSupl><Signature xmlns=\"http://www.w3.org/2000/09/xmldsig#\"><SignedInfo><CanonicalizationMethod Algorithm=\"http://www.w3.org/TR/2001/REC-xml-c14n-20010315\"/><SignatureMethod Algorithm=\"http://www.w3.org/2000/09/xmldsig#rsa-sha1\"/><Reference URI=\"#NFe13260359598453000104650010000000171015925564\"><Transforms><Transform Algorithm=\"http://www.w3.org/2000/09/xmldsig#enveloped-signature\"/><Transform Algorithm=\"http://www.w3.org/TR/2001/REC-xml-c14n-20010315\"/></Transforms><DigestMethod Algorithm=\"http://www.w3.org/2000/09/xmldsig#sha1\"/><DigestValue>Nxd5PrQnLnuJjiLYFFMou4bwuXo=</DigestValue></Reference></SignedInfo><SignatureValue>Hq3eMwmWdmnpKqsfNZICRle/d18zGhvmsQDRo5Ps1ZMQELQVoH00oUGQmI8v5flxlho4H6kvP2O56vuJAIHYTCEyep9OqRGcrPbIPkBifn6Gy+9j+I3qOldH9D5I9odIhFf9Mqjq92tmAxgIfX8q5AZEVb+/hgd6wKYD8GT0Xxsb2FhmbXE6NpEyt+PiAWr3fHhux9oArjFXhzq09676MjSlNXF9aenqijehV/IxWuKMrprQroIETV9aLdT6fkGSqu/zwG8SSfUYnqJHxm+EZNbKKhlFujyPk23hKox2dOpMaW5Mi/2K2z1rjGI4nWw8yP8yHjhl1l4NaodSFJ5gWg==</SignatureValue><KeyInfo><X509Data><X509Certificate>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</X509Certificate></X509Data></KeyInfo></Signature></NFe><protNFe versao=\"4.00\"><infProt><tpAmb>2</tpAmb><verAplic>AM3.10-4.00</verAplic><chNFe>13260359598453000104650010000000171015925564</chNFe><dhRecbto>2026-03-11T10:47:55-04:00</dhRecbto><cStat>373</cStat><xMotivo>Rejeicao: Descricao do primeiro item diferente de NOTA FISCAL EMITIDA EM AMBIENTE DE HOMOLOGACAO - SEM VALOR FISCAL</xMotivo></infProt></protNFe></nfeProc>', '<?xml version=\"1.0\"?><NFe xmlns=\"http://www.portalfiscal.inf.br/nfe\"><infNFe Id=\"NFe13260359598453000104650010000000171015925564\" versao=\"4.00\"><ide><cUF>13</cUF><cNF>01592556</cNF><natOp>VENDA</natOp><mod>65</mod><serie>1</serie><nNF>17</nNF><dhEmi>2026-03-11T10:47:55-04:00</dhEmi><tpNF>1</tpNF><idDest>1</idDest><cMunFG>1301209</cMunFG><tpImp>4</tpImp><tpEmis>1</tpEmis><cDV>4</cDV><tpAmb>2</tpAmb><finNFe>1</finNFe><indFinal>1</indFinal><indPres>1</indPres><procEmi>0</procEmi><verProc>PDV-ACAI-1.0</verProc></ide><emit><CNPJ>59598453000104</CNPJ><xNome>PAPAGAIO COMERCIO DE MOTOS LTDA</xNome><xFant>PAPAGAIO MOTOS</xFant><enderEmit><xLgr>PADRE VICENTE NOGUEIRA</xLgr><nro>149</nro><xBairro>ITAMARATI</xBairro><cMun>1301209</cMun><xMun>COARI</xMun><UF>AM</UF><CEP>69460000</CEP><cPais>1058</cPais><xPais>Brasil</xPais><fone>9791979595</fone></enderEmit><IE>054756448</IE><CRT>3</CRT></emit><det nItem=\"1\"><prod><cProd>1</cProd><cEAN>SEM GTIN</cEAN><xProd>Buzina 12v c100</xProd><NCM>85123000</NCM><CFOP>5102</CFOP><uCom>UN</uCom><qCom>1.000</qCom><vUnCom>60.00</vUnCom><vProd>60.00</vProd><cEANTrib>SEM GTIN</cEANTrib><uTrib>UN</uTrib><qTrib>1.000</qTrib><vUnTrib>60.00</vUnTrib><indTot>1</indTot></prod><imposto><ICMS><ICMSSN102><orig>0</orig><CSOSN>102</CSOSN></ICMSSN102></ICMS><PIS><PISNT><CST>07</CST></PISNT></PIS><COFINS><COFINSNT><CST>07</CST></COFINSNT></COFINS></imposto></det><total><ICMSTot><vBC>0.00</vBC><vICMS>0.00</vICMS><vICMSDeson>0.00</vICMSDeson><vFCP>0.00</vFCP><vBCST>0.00</vBCST><vST>0.00</vST><vFCPST>0.00</vFCPST><vFCPSTRet>0.00</vFCPSTRet><vProd>60.00</vProd><vFrete>0.00</vFrete><vSeg>0.00</vSeg><vDesc>0.00</vDesc><vII>0.00</vII><vIPI>0.00</vIPI><vIPIDevol>0.00</vIPIDevol><vPIS>0.00</vPIS><vCOFINS>0.00</vCOFINS><vOutro>0.00</vOutro><vNF>60.00</vNF></ICMSTot></total><transp><modFrete>9</modFrete></transp><pag><detPag><indPag>0</indPag><tPag>01</tPag><vPag>60.00</vPag></detPag></pag><infAdic><infCpl>PDV A&#xE7;aiteria</infCpl></infAdic></infNFe><infNFeSupl><qrCode>https://sistemas.sefaz.am.gov.br/nfceweb-hom/consultarNFCe.jsp?p=13260359598453000104650010000000171015925564|2|2|2|7C138A44CF4A108F7EB9E552E38E6789D95D4499</qrCode><urlChave>www.sefaz.am.gov.br/nfce/consulta</urlChave></infNFeSupl><Signature xmlns=\"http://www.w3.org/2000/09/xmldsig#\"><SignedInfo><CanonicalizationMethod Algorithm=\"http://www.w3.org/TR/2001/REC-xml-c14n-20010315\"/><SignatureMethod Algorithm=\"http://www.w3.org/2000/09/xmldsig#rsa-sha1\"/><Reference URI=\"#NFe13260359598453000104650010000000171015925564\"><Transforms><Transform Algorithm=\"http://www.w3.org/2000/09/xmldsig#enveloped-signature\"/><Transform Algorithm=\"http://www.w3.org/TR/2001/REC-xml-c14n-20010315\"/></Transforms><DigestMethod Algorithm=\"http://www.w3.org/2000/09/xmldsig#sha1\"/><DigestValue>Nxd5PrQnLnuJjiLYFFMou4bwuXo=</DigestValue></Reference></SignedInfo><SignatureValue>Hq3eMwmWdmnpKqsfNZICRle/d18zGhvmsQDRo5Ps1ZMQELQVoH00oUGQmI8v5flxlho4H6kvP2O56vuJAIHYTCEyep9OqRGcrPbIPkBifn6Gy+9j+I3qOldH9D5I9odIhFf9Mqjq92tmAxgIfX8q5AZEVb+/hgd6wKYD8GT0Xxsb2FhmbXE6NpEyt+PiAWr3fHhux9oArjFXhzq09676MjSlNXF9aenqijehV/IxWuKMrprQroIETV9aLdT6fkGSqu/zwG8SSfUYnqJHxm+EZNbKKhlFujyPk23hKox2dOpMaW5Mi/2K2z1rjGI4nWw8yP8yHjhl1l4NaodSFJ5gWg==</SignatureValue><KeyInfo><X509Data><X509Certificate>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</X509Certificate></X509Data></KeyInfo></Signature></NFe>', '<?xml version=\'1.0\' encoding=\'utf-8\'?><soapenv:Envelope xmlns:soapenv=\"http://www.w3.org/2003/05/soap-envelope\"><soapenv:Body><nfeResultMsg xmlns=\"http://www.portalfiscal.inf.br/nfe/wsdl/NFeAutorizacao4\"><retEnviNFe xmlns=\"http://www.portalfiscal.inf.br/nfe\" versao=\"4.00\"><tpAmb>2</tpAmb><verAplic>AM3.10-4.00</verAplic><cStat>104</cStat><xMotivo>Lote processado</xMotivo><cUF>13</cUF><dhRecbto>2026-03-11T10:47:55-04:00</dhRecbto><protNFe versao=\"4.00\"><infProt><tpAmb>2</tpAmb><verAplic>AM3.10-4.00</verAplic><chNFe>13260359598453000104650010000000171015925564</chNFe><dhRecbto>2026-03-11T10:47:55-04:00</dhRecbto><cStat>373</cStat><xMotivo>Rejeicao: Descricao do primeiro item diferente de NOTA FISCAL EMITIDA EM AMBIENTE DE HOMOLOGACAO - SEM VALOR FISCAL</xMotivo></infProt></protNFe></retEnviNFe></nfeResultMsg></soapenv:Body></soapenv:Envelope>', 60.00, 0.00, '{\"tPag\":\"01\"}', '2026-03-11 14:47:55'),
(5, '125', 82, 2, 1, 18, '13260359598453000104650010000000181763214893', NULL, '104', 'Lote processado', '<?xml version=\"1.0\" encoding=\"UTF-8\"?><nfeProc xmlns=\"http://www.portalfiscal.inf.br/nfe\" versao=\"4.00\"><NFe xmlns=\"http://www.portalfiscal.inf.br/nfe\"><infNFe Id=\"NFe13260359598453000104650010000000181763214893\" versao=\"4.00\"><ide><cUF>13</cUF><cNF>76321489</cNF><natOp>VENDA</natOp><mod>65</mod><serie>1</serie><nNF>18</nNF><dhEmi>2026-03-12T08:03:18-04:00</dhEmi><tpNF>1</tpNF><idDest>1</idDest><cMunFG>1301209</cMunFG><tpImp>4</tpImp><tpEmis>1</tpEmis><cDV>3</cDV><tpAmb>2</tpAmb><finNFe>1</finNFe><indFinal>1</indFinal><indPres>1</indPres><procEmi>0</procEmi><verProc>PDV-ACAI-1.0</verProc></ide><emit><CNPJ>59598453000104</CNPJ><xNome>PAPAGAIO COMERCIO DE MOTOS LTDA</xNome><xFant>PAPAGAIO MOTOS</xFant><enderEmit><xLgr>PADRE VICENTE NOGUEIRA</xLgr><nro>149</nro><xBairro>ITAMARATI</xBairro><cMun>1301209</cMun><xMun>COARI</xMun><UF>AM</UF><CEP>69460000</CEP><cPais>1058</cPais><xPais>Brasil</xPais><fone>9791979595</fone></enderEmit><IE>054756448</IE><CRT>3</CRT></emit><det nItem=\"1\"><prod><cProd>1</cProd><cEAN>SEM GTIN</cEAN><xProd>Buzina 12v c100</xProd><NCM>85123000</NCM><CFOP>5102</CFOP><uCom>UN</uCom><qCom>1.000</qCom><vUnCom>60.00</vUnCom><vProd>60.00</vProd><cEANTrib>SEM GTIN</cEANTrib><uTrib>UN</uTrib><qTrib>1.000</qTrib><vUnTrib>60.00</vUnTrib><indTot>1</indTot></prod><imposto><ICMS><ICMSSN102><orig>0</orig><CSOSN>102</CSOSN></ICMSSN102></ICMS><PIS><PISNT><CST>07</CST></PISNT></PIS><COFINS><COFINSNT><CST>07</CST></COFINSNT></COFINS></imposto></det><total><ICMSTot><vBC>0.00</vBC><vICMS>0.00</vICMS><vICMSDeson>0.00</vICMSDeson><vFCP>0.00</vFCP><vBCST>0.00</vBCST><vST>0.00</vST><vFCPST>0.00</vFCPST><vFCPSTRet>0.00</vFCPSTRet><vProd>60.00</vProd><vFrete>0.00</vFrete><vSeg>0.00</vSeg><vDesc>0.00</vDesc><vII>0.00</vII><vIPI>0.00</vIPI><vIPIDevol>0.00</vIPIDevol><vPIS>0.00</vPIS><vCOFINS>0.00</vCOFINS><vOutro>0.00</vOutro><vNF>60.00</vNF></ICMSTot></total><transp><modFrete>9</modFrete></transp><pag><detPag><indPag>0</indPag><tPag>01</tPag><vPag>60.00</vPag></detPag></pag><infAdic><infCpl>PDV A&#xE7;aiteria</infCpl></infAdic></infNFe><infNFeSupl><qrCode>https://sistemas.sefaz.am.gov.br/nfceweb-hom/consultarNFCe.jsp?p=13260359598453000104650010000000181763214893|2|2|2|7C9C3D87C45CA81E20EA3F63CF6453F1E65A4E90</qrCode><urlChave>www.sefaz.am.gov.br/nfce/consulta</urlChave></infNFeSupl><Signature xmlns=\"http://www.w3.org/2000/09/xmldsig#\"><SignedInfo><CanonicalizationMethod Algorithm=\"http://www.w3.org/TR/2001/REC-xml-c14n-20010315\"/><SignatureMethod Algorithm=\"http://www.w3.org/2000/09/xmldsig#rsa-sha1\"/><Reference URI=\"#NFe13260359598453000104650010000000181763214893\"><Transforms><Transform Algorithm=\"http://www.w3.org/2000/09/xmldsig#enveloped-signature\"/><Transform Algorithm=\"http://www.w3.org/TR/2001/REC-xml-c14n-20010315\"/></Transforms><DigestMethod Algorithm=\"http://www.w3.org/2000/09/xmldsig#sha1\"/><DigestValue>zlOytt/9dkNtxofIid5XBoIxjxc=</DigestValue></Reference></SignedInfo><SignatureValue>gqEbEE86seyOWnOnvO+2H+OD+4nlyI6u66mHjJTrsdHQVyc5eYGrmxmiNhG9VFDZ6JoOh+9m3CfYngy+4e/Gxofq0zjAqE2CdVFJdGVABHH7bFyzPnbMSxyNStYDvoOO+1ARxvm6Uwh4eptrBO1PzCk2qYo5kaad/BCulBHLS1U6xAa2t/D/32Bo1kCofFLxnaJSfNzNanSvKL5/bcVjHopUzcubSLt7KYC0hyy6a2ObKO+OpCdh9uNZEHPP40AF8rfMzFBRWx4pLbRF589eP1TiGU50faU314Sis2Qb7xTr1N/yGJJgR01rsCAP1AIPLnWQ3PFBZYr1g+hyDwrqng==</SignatureValue><KeyInfo><X509Data><X509Certificate>MIIH6DCCBdCgAwIBAgIKHqWXJoQ1LKoMazANBgkqhkiG9w0BAQsFADBbMQswCQYDVQQGEwJCUjEWMBQGA1UECwwNQUMgU3luZ3VsYXJJRDETMBEGA1UECgwKSUNQLUJyYXNpbDEfMB0GA1UEAwwWQUMgU3luZ3VsYXJJRCBNdWx0aXBsYTAeFw0yNTA2MDYyMDMzMzVaFw0yNjA2MDYyMDMzMzVaMIHOMQswCQYDVQQGEwJCUjETMBEGA1UECgwKSUNQLUJyYXNpbDEiMCAGA1UECwwZQ2VydGlmaWNhZG8gRGlnaXRhbCBQSiBBMTETMBEGA1UECwwKUHJlc2VuY2lhbDEXMBUGA1UECwwONDU2MTYzMDkwMDAxNDkxHzAdBgNVBAsMFkFDIFN5bmd1bGFySUQgTXVsdGlwbGExNzA1BgNVBAMMLlBBUEFHQUlPIENPTUVSQ0lPIERFIE1PVE9TIExUREE6NTk1OTg0NTMwMDAxMDQwggEiMA0GCSqGSIb3DQEBAQUAA4IBDwAwggEKAoIBAQC4B4OKj+3kr6hnMnIFUA43tacQhEJmlvGAKVoXSiBo+30gia2+nmAly72AhkoWVIEO2q+I5o5RNgEA3jegdKJCL0jvFiJK/xPfDueVmt/3E/9N+jhOSaXScEDFtrPF6/nlkiKmrlgYyst/uWSCtg3fYzu4BfCTHJ1LL5nuoP4i2FrYaxunvwpg+NzSwvpXeWxBg2UOYRYC+LM6bMJluy+CoQzNKt2RoD8ljHdpzHY10bSL5jkLQOxUCE52SSrDrMD0HmKy6oylYL7xKLbuhiZIgCODNs6mS8bX19mgTZQ7PXs0seuiFuP+M2++rRlQyg2skdwMRDLBykJQSHWs0DP5AgMBAAGjggM4MIIDNDAOBgNVHQ8BAf8EBAMCBeAwHQYDVR0lBBYwFAYIKwYBBQUHAwQGCCsGAQUFBwMCMAkGA1UdEwQCMAAwHwYDVR0jBBgwFoAUk+H/fh3l9eRN4TliiyFpleavchYwHQYDVR0OBBYEFGxSV0SZ6AU0rrnMDKuDbgUDLeQeMH8GCCsGAQUFBwEBBHMwcTBvBggrBgEFBQcwAoZjaHR0cDovL3N5bmd1bGFyaWQuY29tLmJyL3JlcG9zaXRvcmlvL2FjLXN5bmd1bGFyaWQtbXVsdGlwbGEvY2VydGlmaWNhZG9zL2FjLXN5bmd1bGFyaWQtbXVsdGlwbGEucDdiMIGCBgNVHSAEezB5MHcGB2BMAQIBgQUwbDBqBggrBgEFBQcCARZeaHR0cDovL3N5bmd1bGFyaWQuY29tLmJyL3JlcG9zaXRvcmlvL2FjLXN5bmd1bGFyaWQtbXVsdGlwbGEvZHBjL2RwYy1hYy1zeW5ndWxhcklELW11bHRpcGxhLnBkZjCBzAYDVR0RBIHEMIHBoCoGBWBMAQMCoCEEH0xBWkFSTyBDT1JERUlSTyBERSBBTE1FSURBIE5FVE+gGQYFYEwBAwOgEAQONTk1OTg0NTMwMDAxMDSgQgYFYEwBAwSgOQQ3MDQwMjE5OTUwMzM5NTQ2MDI2NjAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMKAXBgVgTAEDB6AOBAwwMDAwMDAwMDAwMDCBG0NPUkRFSVJPTEFaQVJPODcwQEdNQUlMLkNPTTCB4gYDVR0fBIHaMIHXMG+gbaBrhmlodHRwOi8vaWNwLWJyYXNpbC5zeW5ndWxhcmlkLmNvbS5ici9yZXBvc2l0b3Jpby9hYy1zeW5ndWxhcmlkLW11bHRpcGxhL2xjci9sY3ItYWMtc3luZ3VsYXJpZC1tdWx0aXBsYS5jcmwwZKBioGCGXmh0dHA6Ly9zeW5ndWxhcmlkLmNvbS5ici9yZXBvc2l0b3Jpby9hYy1zeW5ndWxhcmlkLW11bHRpcGxhL2xjci9sY3ItYWMtc3luZ3VsYXJpZC1tdWx0aXBsYS5jcmwwDQYJKoZIhvcNAQELBQADggIBACI8nwyikS2ZY4yjPZ68tXNvHbyfoCizJsYbqLavXaIzI9D0VBqEyJAvpZVA8JQQ4qMm2rZkZmZjm4ucYTNezZf7OLX5STql96fOLYgFXHCpVMfG251bhsLpwBJmgES9FtG7+DrdLkQ+FAX7vkVgNKT70AQnDwY9cSM2ghSLiv+fu8ubLwk4rlrc7y9y6QGAB4kvbHY5J8yWdfMnQMPF/raR7mFuWG1co+UjXDm4E69K3VoFy3B9Jq1u2sUdryVjVaamOlLwhoFz8UPJm9Bjn1E1OiuSc2MJ1gUGPzwqvsNgasiq7D9/XuMUCeYia2BBpdy7Z+LKDKO2vtFZ11N4O+gbXLykxqnuwn8Iiigqu7+txxRPj/v2+i8ckWUyONx3zRZvlHXsfk4gTyLqK1+VJ0NqIK1A11DQLc5uaTPQe+DjmqSNFbDKsoKjhySK0Fs7yT1FXDWyvqMNmJkl/HVdVbtQesk58PvGlpTnDunlJDCL5Lcyfm59yFc5bBF9HBS5xMSfG7Vnk0JvQUYpkdf1NOI5PzbJhsN/TYvdDpgTDCJ9DILMrnqovzvGhkG46R8sQIhF93HygHgXrHRSSBySv0QnDbgkv8tnu0gXQRyzadWVD5nBQakzQVIbWzM0h5DF2n300mDE+fGhqcDz2iXqdCNmuVwurA/pc4ZIJyvfMj/Q</X509Certificate></X509Data></KeyInfo></Signature></NFe><protNFe versao=\"4.00\"><infProt><tpAmb>2</tpAmb><verAplic>AM3.10-4.00</verAplic><chNFe>13260359598453000104650010000000181763214893</chNFe><dhRecbto>2026-03-12T08:03:19-04:00</dhRecbto><cStat>373</cStat><xMotivo>Rejeicao: Descricao do primeiro item diferente de NOTA FISCAL EMITIDA EM AMBIENTE DE HOMOLOGACAO - SEM VALOR FISCAL</xMotivo></infProt></protNFe></nfeProc>', '<?xml version=\"1.0\"?><NFe xmlns=\"http://www.portalfiscal.inf.br/nfe\"><infNFe Id=\"NFe13260359598453000104650010000000181763214893\" versao=\"4.00\"><ide><cUF>13</cUF><cNF>76321489</cNF><natOp>VENDA</natOp><mod>65</mod><serie>1</serie><nNF>18</nNF><dhEmi>2026-03-12T08:03:18-04:00</dhEmi><tpNF>1</tpNF><idDest>1</idDest><cMunFG>1301209</cMunFG><tpImp>4</tpImp><tpEmis>1</tpEmis><cDV>3</cDV><tpAmb>2</tpAmb><finNFe>1</finNFe><indFinal>1</indFinal><indPres>1</indPres><procEmi>0</procEmi><verProc>PDV-ACAI-1.0</verProc></ide><emit><CNPJ>59598453000104</CNPJ><xNome>PAPAGAIO COMERCIO DE MOTOS LTDA</xNome><xFant>PAPAGAIO MOTOS</xFant><enderEmit><xLgr>PADRE VICENTE NOGUEIRA</xLgr><nro>149</nro><xBairro>ITAMARATI</xBairro><cMun>1301209</cMun><xMun>COARI</xMun><UF>AM</UF><CEP>69460000</CEP><cPais>1058</cPais><xPais>Brasil</xPais><fone>9791979595</fone></enderEmit><IE>054756448</IE><CRT>3</CRT></emit><det nItem=\"1\"><prod><cProd>1</cProd><cEAN>SEM GTIN</cEAN><xProd>Buzina 12v c100</xProd><NCM>85123000</NCM><CFOP>5102</CFOP><uCom>UN</uCom><qCom>1.000</qCom><vUnCom>60.00</vUnCom><vProd>60.00</vProd><cEANTrib>SEM GTIN</cEANTrib><uTrib>UN</uTrib><qTrib>1.000</qTrib><vUnTrib>60.00</vUnTrib><indTot>1</indTot></prod><imposto><ICMS><ICMSSN102><orig>0</orig><CSOSN>102</CSOSN></ICMSSN102></ICMS><PIS><PISNT><CST>07</CST></PISNT></PIS><COFINS><COFINSNT><CST>07</CST></COFINSNT></COFINS></imposto></det><total><ICMSTot><vBC>0.00</vBC><vICMS>0.00</vICMS><vICMSDeson>0.00</vICMSDeson><vFCP>0.00</vFCP><vBCST>0.00</vBCST><vST>0.00</vST><vFCPST>0.00</vFCPST><vFCPSTRet>0.00</vFCPSTRet><vProd>60.00</vProd><vFrete>0.00</vFrete><vSeg>0.00</vSeg><vDesc>0.00</vDesc><vII>0.00</vII><vIPI>0.00</vIPI><vIPIDevol>0.00</vIPIDevol><vPIS>0.00</vPIS><vCOFINS>0.00</vCOFINS><vOutro>0.00</vOutro><vNF>60.00</vNF></ICMSTot></total><transp><modFrete>9</modFrete></transp><pag><detPag><indPag>0</indPag><tPag>01</tPag><vPag>60.00</vPag></detPag></pag><infAdic><infCpl>PDV A&#xE7;aiteria</infCpl></infAdic></infNFe><infNFeSupl><qrCode>https://sistemas.sefaz.am.gov.br/nfceweb-hom/consultarNFCe.jsp?p=13260359598453000104650010000000181763214893|2|2|2|7C9C3D87C45CA81E20EA3F63CF6453F1E65A4E90</qrCode><urlChave>www.sefaz.am.gov.br/nfce/consulta</urlChave></infNFeSupl><Signature xmlns=\"http://www.w3.org/2000/09/xmldsig#\"><SignedInfo><CanonicalizationMethod Algorithm=\"http://www.w3.org/TR/2001/REC-xml-c14n-20010315\"/><SignatureMethod Algorithm=\"http://www.w3.org/2000/09/xmldsig#rsa-sha1\"/><Reference URI=\"#NFe13260359598453000104650010000000181763214893\"><Transforms><Transform Algorithm=\"http://www.w3.org/2000/09/xmldsig#enveloped-signature\"/><Transform Algorithm=\"http://www.w3.org/TR/2001/REC-xml-c14n-20010315\"/></Transforms><DigestMethod Algorithm=\"http://www.w3.org/2000/09/xmldsig#sha1\"/><DigestValue>zlOytt/9dkNtxofIid5XBoIxjxc=</DigestValue></Reference></SignedInfo><SignatureValue>gqEbEE86seyOWnOnvO+2H+OD+4nlyI6u66mHjJTrsdHQVyc5eYGrmxmiNhG9VFDZ6JoOh+9m3CfYngy+4e/Gxofq0zjAqE2CdVFJdGVABHH7bFyzPnbMSxyNStYDvoOO+1ARxvm6Uwh4eptrBO1PzCk2qYo5kaad/BCulBHLS1U6xAa2t/D/32Bo1kCofFLxnaJSfNzNanSvKL5/bcVjHopUzcubSLt7KYC0hyy6a2ObKO+OpCdh9uNZEHPP40AF8rfMzFBRWx4pLbRF589eP1TiGU50faU314Sis2Qb7xTr1N/yGJJgR01rsCAP1AIPLnWQ3PFBZYr1g+hyDwrqng==</SignatureValue><KeyInfo><X509Data><X509Certificate>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</X509Certificate></X509Data></KeyInfo></Signature></NFe>', '<?xml version=\'1.0\' encoding=\'utf-8\'?><soapenv:Envelope xmlns:soapenv=\"http://www.w3.org/2003/05/soap-envelope\"><soapenv:Body><nfeResultMsg xmlns=\"http://www.portalfiscal.inf.br/nfe/wsdl/NFeAutorizacao4\"><retEnviNFe xmlns=\"http://www.portalfiscal.inf.br/nfe\" versao=\"4.00\"><tpAmb>2</tpAmb><verAplic>AM3.10-4.00</verAplic><cStat>104</cStat><xMotivo>Lote processado</xMotivo><cUF>13</cUF><dhRecbto>2026-03-12T08:03:19-04:00</dhRecbto><protNFe versao=\"4.00\"><infProt><tpAmb>2</tpAmb><verAplic>AM3.10-4.00</verAplic><chNFe>13260359598453000104650010000000181763214893</chNFe><dhRecbto>2026-03-12T08:03:19-04:00</dhRecbto><cStat>373</cStat><xMotivo>Rejeicao: Descricao do primeiro item diferente de NOTA FISCAL EMITIDA EM AMBIENTE DE HOMOLOGACAO - SEM VALOR FISCAL</xMotivo></infProt></protNFe></retEnviNFe></nfeResultMsg></soapenv:Body></soapenv:Envelope>', 60.00, 0.00, '{\"tPag\":\"01\"}', '2026-03-12 12:03:19'),
(6, '125', 83, 2, 1, 19, '13260359598453000104650010000000191382488847', NULL, '104', 'Lote processado', '<?xml version=\"1.0\" encoding=\"UTF-8\"?><nfeProc xmlns=\"http://www.portalfiscal.inf.br/nfe\" versao=\"4.00\"><NFe xmlns=\"http://www.portalfiscal.inf.br/nfe\"><infNFe Id=\"NFe13260359598453000104650010000000191382488847\" versao=\"4.00\"><ide><cUF>13</cUF><cNF>38248884</cNF><natOp>VENDA</natOp><mod>65</mod><serie>1</serie><nNF>19</nNF><dhEmi>2026-03-12T08:03:43-04:00</dhEmi><tpNF>1</tpNF><idDest>1</idDest><cMunFG>1301209</cMunFG><tpImp>4</tpImp><tpEmis>1</tpEmis><cDV>7</cDV><tpAmb>2</tpAmb><finNFe>1</finNFe><indFinal>1</indFinal><indPres>1</indPres><procEmi>0</procEmi><verProc>PDV-ACAI-1.0</verProc></ide><emit><CNPJ>59598453000104</CNPJ><xNome>PAPAGAIO COMERCIO DE MOTOS LTDA</xNome><xFant>PAPAGAIO MOTOS</xFant><enderEmit><xLgr>PADRE VICENTE NOGUEIRA</xLgr><nro>149</nro><xBairro>ITAMARATI</xBairro><cMun>1301209</cMun><xMun>COARI</xMun><UF>AM</UF><CEP>69460000</CEP><cPais>1058</cPais><xPais>Brasil</xPais><fone>9791979595</fone></enderEmit><IE>054756448</IE><CRT>3</CRT></emit><det nItem=\"1\"><prod><cProd>1</cProd><cEAN>SEM GTIN</cEAN><xProd>Buzina 12v c100</xProd><NCM>85123000</NCM><CFOP>5102</CFOP><uCom>UN</uCom><qCom>1.000</qCom><vUnCom>60.00</vUnCom><vProd>60.00</vProd><cEANTrib>SEM GTIN</cEANTrib><uTrib>UN</uTrib><qTrib>1.000</qTrib><vUnTrib>60.00</vUnTrib><indTot>1</indTot></prod><imposto><ICMS><ICMSSN102><orig>0</orig><CSOSN>102</CSOSN></ICMSSN102></ICMS><PIS><PISNT><CST>07</CST></PISNT></PIS><COFINS><COFINSNT><CST>07</CST></COFINSNT></COFINS></imposto></det><total><ICMSTot><vBC>0.00</vBC><vICMS>0.00</vICMS><vICMSDeson>0.00</vICMSDeson><vFCP>0.00</vFCP><vBCST>0.00</vBCST><vST>0.00</vST><vFCPST>0.00</vFCPST><vFCPSTRet>0.00</vFCPSTRet><vProd>60.00</vProd><vFrete>0.00</vFrete><vSeg>0.00</vSeg><vDesc>0.00</vDesc><vII>0.00</vII><vIPI>0.00</vIPI><vIPIDevol>0.00</vIPIDevol><vPIS>0.00</vPIS><vCOFINS>0.00</vCOFINS><vOutro>0.00</vOutro><vNF>60.00</vNF></ICMSTot></total><transp><modFrete>9</modFrete></transp><pag><detPag><indPag>0</indPag><tPag>01</tPag><vPag>60.00</vPag></detPag></pag><infAdic><infCpl>PDV A&#xE7;aiteria</infCpl></infAdic></infNFe><infNFeSupl><qrCode>https://sistemas.sefaz.am.gov.br/nfceweb-hom/consultarNFCe.jsp?p=13260359598453000104650010000000191382488847|2|2|2|E9E1B89FB419CDED1ED9D56A4D6C651CDD573EA6</qrCode><urlChave>www.sefaz.am.gov.br/nfce/consulta</urlChave></infNFeSupl><Signature xmlns=\"http://www.w3.org/2000/09/xmldsig#\"><SignedInfo><CanonicalizationMethod Algorithm=\"http://www.w3.org/TR/2001/REC-xml-c14n-20010315\"/><SignatureMethod Algorithm=\"http://www.w3.org/2000/09/xmldsig#rsa-sha1\"/><Reference URI=\"#NFe13260359598453000104650010000000191382488847\"><Transforms><Transform Algorithm=\"http://www.w3.org/2000/09/xmldsig#enveloped-signature\"/><Transform Algorithm=\"http://www.w3.org/TR/2001/REC-xml-c14n-20010315\"/></Transforms><DigestMethod Algorithm=\"http://www.w3.org/2000/09/xmldsig#sha1\"/><DigestValue>22BNO7NUsdUetRn8ZQKLH/1plAE=</DigestValue></Reference></SignedInfo><SignatureValue>W9XgRKNfxl7Hb/1+P3UE0eMhrJ9pXV+yzKJLIbRqRLCnc/pg0mBzoXN45rZQ3a+qMY7lMdn4qO98omKTRFBaQjZfIzTxV1dMInS6NMG6nksLiCqWMX8Nbv8mbjkaXczOZYQwjebPx+38bAgbhDKPvm6FXNghOsyVe8b6NLUYmsncDNL1RAtDJFr29iFlQoPt3QGsjgj7KJaGhO763rzXlnItpmR6iKGIa5YYnkNG3+WPip1pqA19nku0dOjYD7vJ0ZA/IM0ef1kb3a11RKHZgZFsCkH8Hext0jPUv3uDXZ7dzHL7ZYGhrQiHECbV/I5h019iMmpG3UTS0tpj7fqvDQ==</SignatureValue><KeyInfo><X509Data><X509Certificate>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</X509Certificate></X509Data></KeyInfo></Signature></NFe><protNFe versao=\"4.00\"><infProt><tpAmb>2</tpAmb><verAplic>AM3.10-4.00</verAplic><chNFe>13260359598453000104650010000000191382488847</chNFe><dhRecbto>2026-03-12T08:03:44-04:00</dhRecbto><cStat>373</cStat><xMotivo>Rejeicao: Descricao do primeiro item diferente de NOTA FISCAL EMITIDA EM AMBIENTE DE HOMOLOGACAO - SEM VALOR FISCAL</xMotivo></infProt></protNFe></nfeProc>', '<?xml version=\"1.0\"?><NFe xmlns=\"http://www.portalfiscal.inf.br/nfe\"><infNFe Id=\"NFe13260359598453000104650010000000191382488847\" versao=\"4.00\"><ide><cUF>13</cUF><cNF>38248884</cNF><natOp>VENDA</natOp><mod>65</mod><serie>1</serie><nNF>19</nNF><dhEmi>2026-03-12T08:03:43-04:00</dhEmi><tpNF>1</tpNF><idDest>1</idDest><cMunFG>1301209</cMunFG><tpImp>4</tpImp><tpEmis>1</tpEmis><cDV>7</cDV><tpAmb>2</tpAmb><finNFe>1</finNFe><indFinal>1</indFinal><indPres>1</indPres><procEmi>0</procEmi><verProc>PDV-ACAI-1.0</verProc></ide><emit><CNPJ>59598453000104</CNPJ><xNome>PAPAGAIO COMERCIO DE MOTOS LTDA</xNome><xFant>PAPAGAIO MOTOS</xFant><enderEmit><xLgr>PADRE VICENTE NOGUEIRA</xLgr><nro>149</nro><xBairro>ITAMARATI</xBairro><cMun>1301209</cMun><xMun>COARI</xMun><UF>AM</UF><CEP>69460000</CEP><cPais>1058</cPais><xPais>Brasil</xPais><fone>9791979595</fone></enderEmit><IE>054756448</IE><CRT>3</CRT></emit><det nItem=\"1\"><prod><cProd>1</cProd><cEAN>SEM GTIN</cEAN><xProd>Buzina 12v c100</xProd><NCM>85123000</NCM><CFOP>5102</CFOP><uCom>UN</uCom><qCom>1.000</qCom><vUnCom>60.00</vUnCom><vProd>60.00</vProd><cEANTrib>SEM GTIN</cEANTrib><uTrib>UN</uTrib><qTrib>1.000</qTrib><vUnTrib>60.00</vUnTrib><indTot>1</indTot></prod><imposto><ICMS><ICMSSN102><orig>0</orig><CSOSN>102</CSOSN></ICMSSN102></ICMS><PIS><PISNT><CST>07</CST></PISNT></PIS><COFINS><COFINSNT><CST>07</CST></COFINSNT></COFINS></imposto></det><total><ICMSTot><vBC>0.00</vBC><vICMS>0.00</vICMS><vICMSDeson>0.00</vICMSDeson><vFCP>0.00</vFCP><vBCST>0.00</vBCST><vST>0.00</vST><vFCPST>0.00</vFCPST><vFCPSTRet>0.00</vFCPSTRet><vProd>60.00</vProd><vFrete>0.00</vFrete><vSeg>0.00</vSeg><vDesc>0.00</vDesc><vII>0.00</vII><vIPI>0.00</vIPI><vIPIDevol>0.00</vIPIDevol><vPIS>0.00</vPIS><vCOFINS>0.00</vCOFINS><vOutro>0.00</vOutro><vNF>60.00</vNF></ICMSTot></total><transp><modFrete>9</modFrete></transp><pag><detPag><indPag>0</indPag><tPag>01</tPag><vPag>60.00</vPag></detPag></pag><infAdic><infCpl>PDV A&#xE7;aiteria</infCpl></infAdic></infNFe><infNFeSupl><qrCode>https://sistemas.sefaz.am.gov.br/nfceweb-hom/consultarNFCe.jsp?p=13260359598453000104650010000000191382488847|2|2|2|E9E1B89FB419CDED1ED9D56A4D6C651CDD573EA6</qrCode><urlChave>www.sefaz.am.gov.br/nfce/consulta</urlChave></infNFeSupl><Signature xmlns=\"http://www.w3.org/2000/09/xmldsig#\"><SignedInfo><CanonicalizationMethod Algorithm=\"http://www.w3.org/TR/2001/REC-xml-c14n-20010315\"/><SignatureMethod Algorithm=\"http://www.w3.org/2000/09/xmldsig#rsa-sha1\"/><Reference URI=\"#NFe13260359598453000104650010000000191382488847\"><Transforms><Transform Algorithm=\"http://www.w3.org/2000/09/xmldsig#enveloped-signature\"/><Transform Algorithm=\"http://www.w3.org/TR/2001/REC-xml-c14n-20010315\"/></Transforms><DigestMethod Algorithm=\"http://www.w3.org/2000/09/xmldsig#sha1\"/><DigestValue>22BNO7NUsdUetRn8ZQKLH/1plAE=</DigestValue></Reference></SignedInfo><SignatureValue>W9XgRKNfxl7Hb/1+P3UE0eMhrJ9pXV+yzKJLIbRqRLCnc/pg0mBzoXN45rZQ3a+qMY7lMdn4qO98omKTRFBaQjZfIzTxV1dMInS6NMG6nksLiCqWMX8Nbv8mbjkaXczOZYQwjebPx+38bAgbhDKPvm6FXNghOsyVe8b6NLUYmsncDNL1RAtDJFr29iFlQoPt3QGsjgj7KJaGhO763rzXlnItpmR6iKGIa5YYnkNG3+WPip1pqA19nku0dOjYD7vJ0ZA/IM0ef1kb3a11RKHZgZFsCkH8Hext0jPUv3uDXZ7dzHL7ZYGhrQiHECbV/I5h019iMmpG3UTS0tpj7fqvDQ==</SignatureValue><KeyInfo><X509Data><X509Certificate>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</X509Certificate></X509Data></KeyInfo></Signature></NFe>', '<?xml version=\'1.0\' encoding=\'utf-8\'?><soapenv:Envelope xmlns:soapenv=\"http://www.w3.org/2003/05/soap-envelope\"><soapenv:Body><nfeResultMsg xmlns=\"http://www.portalfiscal.inf.br/nfe/wsdl/NFeAutorizacao4\"><retEnviNFe xmlns=\"http://www.portalfiscal.inf.br/nfe\" versao=\"4.00\"><tpAmb>2</tpAmb><verAplic>AM3.10-4.00</verAplic><cStat>104</cStat><xMotivo>Lote processado</xMotivo><cUF>13</cUF><dhRecbto>2026-03-12T08:03:44-04:00</dhRecbto><protNFe versao=\"4.00\"><infProt><tpAmb>2</tpAmb><verAplic>AM3.10-4.00</verAplic><chNFe>13260359598453000104650010000000191382488847</chNFe><dhRecbto>2026-03-12T08:03:44-04:00</dhRecbto><cStat>373</cStat><xMotivo>Rejeicao: Descricao do primeiro item diferente de NOTA FISCAL EMITIDA EM AMBIENTE DE HOMOLOGACAO - SEM VALOR FISCAL</xMotivo></infProt></protNFe></retEnviNFe></nfeResultMsg></soapenv:Body></soapenv:Envelope>', 60.00, 0.00, '{\"tPag\":\"01\"}', '2026-03-12 12:03:44');
INSERT INTO `nfce_emitidas` (`id`, `empresa_id`, `venda_id`, `ambiente`, `serie`, `numero`, `chave`, `protocolo`, `status_sefaz`, `mensagem`, `xml_nfeproc`, `xml_envio`, `xml_retorno`, `valor_total`, `valor_troco`, `tpag_json`, `created_at`) VALUES
(7, '125', 83, 2, 1, 20, '13260359598453000104650010000000201447842241', NULL, '104', 'Lote processado', '<?xml version=\"1.0\" encoding=\"UTF-8\"?><nfeProc xmlns=\"http://www.portalfiscal.inf.br/nfe\" versao=\"4.00\"><NFe xmlns=\"http://www.portalfiscal.inf.br/nfe\"><infNFe Id=\"NFe13260359598453000104650010000000201447842241\" versao=\"4.00\"><ide><cUF>13</cUF><cNF>44784224</cNF><natOp>VENDA</natOp><mod>65</mod><serie>1</serie><nNF>20</nNF><dhEmi>2026-03-12T08:04:52-04:00</dhEmi><tpNF>1</tpNF><idDest>1</idDest><cMunFG>1301209</cMunFG><tpImp>4</tpImp><tpEmis>1</tpEmis><cDV>1</cDV><tpAmb>2</tpAmb><finNFe>1</finNFe><indFinal>1</indFinal><indPres>1</indPres><procEmi>0</procEmi><verProc>PDV-ACAI-1.0</verProc></ide><emit><CNPJ>59598453000104</CNPJ><xNome>PAPAGAIO COMERCIO DE MOTOS LTDA</xNome><xFant>PAPAGAIO MOTOS</xFant><enderEmit><xLgr>PADRE VICENTE NOGUEIRA</xLgr><nro>149</nro><xBairro>ITAMARATI</xBairro><cMun>1301209</cMun><xMun>COARI</xMun><UF>AM</UF><CEP>69460000</CEP><cPais>1058</cPais><xPais>Brasil</xPais><fone>9791979595</fone></enderEmit><IE>054756448</IE><CRT>3</CRT></emit><det nItem=\"1\"><prod><cProd>1</cProd><cEAN>SEM GTIN</cEAN><xProd>Buzina 12v c100</xProd><NCM>85123000</NCM><CFOP>5102</CFOP><uCom>UN</uCom><qCom>1.000</qCom><vUnCom>60.00</vUnCom><vProd>60.00</vProd><cEANTrib>SEM GTIN</cEANTrib><uTrib>UN</uTrib><qTrib>1.000</qTrib><vUnTrib>60.00</vUnTrib><indTot>1</indTot></prod><imposto><ICMS><ICMSSN102><orig>0</orig><CSOSN>102</CSOSN></ICMSSN102></ICMS><PIS><PISNT><CST>07</CST></PISNT></PIS><COFINS><COFINSNT><CST>07</CST></COFINSNT></COFINS></imposto></det><total><ICMSTot><vBC>0.00</vBC><vICMS>0.00</vICMS><vICMSDeson>0.00</vICMSDeson><vFCP>0.00</vFCP><vBCST>0.00</vBCST><vST>0.00</vST><vFCPST>0.00</vFCPST><vFCPSTRet>0.00</vFCPSTRet><vProd>60.00</vProd><vFrete>0.00</vFrete><vSeg>0.00</vSeg><vDesc>0.00</vDesc><vII>0.00</vII><vIPI>0.00</vIPI><vIPIDevol>0.00</vIPIDevol><vPIS>0.00</vPIS><vCOFINS>0.00</vCOFINS><vOutro>0.00</vOutro><vNF>60.00</vNF></ICMSTot></total><transp><modFrete>9</modFrete></transp><pag><detPag><indPag>0</indPag><tPag>01</tPag><vPag>60.00</vPag></detPag></pag><infAdic><infCpl>PDV A&#xE7;aiteria</infCpl></infAdic></infNFe><infNFeSupl><qrCode>https://sistemas.sefaz.am.gov.br/nfceweb-hom/consultarNFCe.jsp?p=13260359598453000104650010000000201447842241|2|2|2|292EB77C8F0BE4CC6BFA49A56FC871BF262BA31F</qrCode><urlChave>www.sefaz.am.gov.br/nfce/consulta</urlChave></infNFeSupl><Signature xmlns=\"http://www.w3.org/2000/09/xmldsig#\"><SignedInfo><CanonicalizationMethod Algorithm=\"http://www.w3.org/TR/2001/REC-xml-c14n-20010315\"/><SignatureMethod Algorithm=\"http://www.w3.org/2000/09/xmldsig#rsa-sha1\"/><Reference URI=\"#NFe13260359598453000104650010000000201447842241\"><Transforms><Transform Algorithm=\"http://www.w3.org/2000/09/xmldsig#enveloped-signature\"/><Transform Algorithm=\"http://www.w3.org/TR/2001/REC-xml-c14n-20010315\"/></Transforms><DigestMethod Algorithm=\"http://www.w3.org/2000/09/xmldsig#sha1\"/><DigestValue>kkeZelnMP8Wqxv8fF7AQEBpZoAE=</DigestValue></Reference></SignedInfo><SignatureValue>GXBH/CsItl3L3mc8JFVDGdCHKiZK74khFrmfx39XYzPnzEiOpw5ytRopxQRfoqi6OP8w/yH4/kIScwqqmzEHQaulTjb/qKNPaIyXO4meUg8tZiWvXracc4Gl08QBzIedQLiOo4TT0hM4HEVMxaQgMiT4QhC5ti8k2H+dnXhtRG3rJ7W8+o0CoaXtDUpIydIrbHiNtHJQ+lnJcK3z3Qfhp4ApvlVqlFU5n2qPRnUVsgYct/K2MxusLBiZHeYlPOMPW+u+H0CNIqis9yG7USeg7QwqRI6JLXIIm2x8SmW2zgBmd46bg74lfA476Trb135XOfiee6hct/GEI90jFMN7Aw==</SignatureValue><KeyInfo><X509Data><X509Certificate>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</X509Certificate></X509Data></KeyInfo></Signature></NFe><protNFe versao=\"4.00\"><infProt><tpAmb>2</tpAmb><verAplic>AM3.10-4.00</verAplic><chNFe>13260359598453000104650010000000201447842241</chNFe><dhRecbto>2026-03-12T08:04:52-04:00</dhRecbto><cStat>373</cStat><xMotivo>Rejeicao: Descricao do primeiro item diferente de NOTA FISCAL EMITIDA EM AMBIENTE DE HOMOLOGACAO - SEM VALOR FISCAL</xMotivo></infProt></protNFe></nfeProc>', '<?xml version=\"1.0\"?><NFe xmlns=\"http://www.portalfiscal.inf.br/nfe\"><infNFe Id=\"NFe13260359598453000104650010000000201447842241\" versao=\"4.00\"><ide><cUF>13</cUF><cNF>44784224</cNF><natOp>VENDA</natOp><mod>65</mod><serie>1</serie><nNF>20</nNF><dhEmi>2026-03-12T08:04:52-04:00</dhEmi><tpNF>1</tpNF><idDest>1</idDest><cMunFG>1301209</cMunFG><tpImp>4</tpImp><tpEmis>1</tpEmis><cDV>1</cDV><tpAmb>2</tpAmb><finNFe>1</finNFe><indFinal>1</indFinal><indPres>1</indPres><procEmi>0</procEmi><verProc>PDV-ACAI-1.0</verProc></ide><emit><CNPJ>59598453000104</CNPJ><xNome>PAPAGAIO COMERCIO DE MOTOS LTDA</xNome><xFant>PAPAGAIO MOTOS</xFant><enderEmit><xLgr>PADRE VICENTE NOGUEIRA</xLgr><nro>149</nro><xBairro>ITAMARATI</xBairro><cMun>1301209</cMun><xMun>COARI</xMun><UF>AM</UF><CEP>69460000</CEP><cPais>1058</cPais><xPais>Brasil</xPais><fone>9791979595</fone></enderEmit><IE>054756448</IE><CRT>3</CRT></emit><det nItem=\"1\"><prod><cProd>1</cProd><cEAN>SEM GTIN</cEAN><xProd>Buzina 12v c100</xProd><NCM>85123000</NCM><CFOP>5102</CFOP><uCom>UN</uCom><qCom>1.000</qCom><vUnCom>60.00</vUnCom><vProd>60.00</vProd><cEANTrib>SEM GTIN</cEANTrib><uTrib>UN</uTrib><qTrib>1.000</qTrib><vUnTrib>60.00</vUnTrib><indTot>1</indTot></prod><imposto><ICMS><ICMSSN102><orig>0</orig><CSOSN>102</CSOSN></ICMSSN102></ICMS><PIS><PISNT><CST>07</CST></PISNT></PIS><COFINS><COFINSNT><CST>07</CST></COFINSNT></COFINS></imposto></det><total><ICMSTot><vBC>0.00</vBC><vICMS>0.00</vICMS><vICMSDeson>0.00</vICMSDeson><vFCP>0.00</vFCP><vBCST>0.00</vBCST><vST>0.00</vST><vFCPST>0.00</vFCPST><vFCPSTRet>0.00</vFCPSTRet><vProd>60.00</vProd><vFrete>0.00</vFrete><vSeg>0.00</vSeg><vDesc>0.00</vDesc><vII>0.00</vII><vIPI>0.00</vIPI><vIPIDevol>0.00</vIPIDevol><vPIS>0.00</vPIS><vCOFINS>0.00</vCOFINS><vOutro>0.00</vOutro><vNF>60.00</vNF></ICMSTot></total><transp><modFrete>9</modFrete></transp><pag><detPag><indPag>0</indPag><tPag>01</tPag><vPag>60.00</vPag></detPag></pag><infAdic><infCpl>PDV A&#xE7;aiteria</infCpl></infAdic></infNFe><infNFeSupl><qrCode>https://sistemas.sefaz.am.gov.br/nfceweb-hom/consultarNFCe.jsp?p=13260359598453000104650010000000201447842241|2|2|2|292EB77C8F0BE4CC6BFA49A56FC871BF262BA31F</qrCode><urlChave>www.sefaz.am.gov.br/nfce/consulta</urlChave></infNFeSupl><Signature xmlns=\"http://www.w3.org/2000/09/xmldsig#\"><SignedInfo><CanonicalizationMethod Algorithm=\"http://www.w3.org/TR/2001/REC-xml-c14n-20010315\"/><SignatureMethod Algorithm=\"http://www.w3.org/2000/09/xmldsig#rsa-sha1\"/><Reference URI=\"#NFe13260359598453000104650010000000201447842241\"><Transforms><Transform Algorithm=\"http://www.w3.org/2000/09/xmldsig#enveloped-signature\"/><Transform Algorithm=\"http://www.w3.org/TR/2001/REC-xml-c14n-20010315\"/></Transforms><DigestMethod Algorithm=\"http://www.w3.org/2000/09/xmldsig#sha1\"/><DigestValue>kkeZelnMP8Wqxv8fF7AQEBpZoAE=</DigestValue></Reference></SignedInfo><SignatureValue>GXBH/CsItl3L3mc8JFVDGdCHKiZK74khFrmfx39XYzPnzEiOpw5ytRopxQRfoqi6OP8w/yH4/kIScwqqmzEHQaulTjb/qKNPaIyXO4meUg8tZiWvXracc4Gl08QBzIedQLiOo4TT0hM4HEVMxaQgMiT4QhC5ti8k2H+dnXhtRG3rJ7W8+o0CoaXtDUpIydIrbHiNtHJQ+lnJcK3z3Qfhp4ApvlVqlFU5n2qPRnUVsgYct/K2MxusLBiZHeYlPOMPW+u+H0CNIqis9yG7USeg7QwqRI6JLXIIm2x8SmW2zgBmd46bg74lfA476Trb135XOfiee6hct/GEI90jFMN7Aw==</SignatureValue><KeyInfo><X509Data><X509Certificate>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</X509Certificate></X509Data></KeyInfo></Signature></NFe>', '<?xml version=\'1.0\' encoding=\'utf-8\'?><soapenv:Envelope xmlns:soapenv=\"http://www.w3.org/2003/05/soap-envelope\"><soapenv:Body><nfeResultMsg xmlns=\"http://www.portalfiscal.inf.br/nfe/wsdl/NFeAutorizacao4\"><retEnviNFe xmlns=\"http://www.portalfiscal.inf.br/nfe\" versao=\"4.00\"><tpAmb>2</tpAmb><verAplic>AM3.10-4.00</verAplic><cStat>104</cStat><xMotivo>Lote processado</xMotivo><cUF>13</cUF><dhRecbto>2026-03-12T08:04:52-04:00</dhRecbto><protNFe versao=\"4.00\"><infProt><tpAmb>2</tpAmb><verAplic>AM3.10-4.00</verAplic><chNFe>13260359598453000104650010000000201447842241</chNFe><dhRecbto>2026-03-12T08:04:52-04:00</dhRecbto><cStat>373</cStat><xMotivo>Rejeicao: Descricao do primeiro item diferente de NOTA FISCAL EMITIDA EM AMBIENTE DE HOMOLOGACAO - SEM VALOR FISCAL</xMotivo></infProt></protNFe></retEnviNFe></nfeResultMsg></soapenv:Body></soapenv:Envelope>', 60.00, 0.00, '{\"tPag\":\"01\"}', '2026-03-12 12:04:52'),
(8, '125', 87, 2, 1, 21, '13260359598453000104650010000000211793473060', NULL, '104', 'Lote processado', '<?xml version=\"1.0\" encoding=\"UTF-8\"?><nfeProc xmlns=\"http://www.portalfiscal.inf.br/nfe\" versao=\"4.00\"><NFe xmlns=\"http://www.portalfiscal.inf.br/nfe\"><infNFe Id=\"NFe13260359598453000104650010000000211793473060\" versao=\"4.00\"><ide><cUF>13</cUF><cNF>79347306</cNF><natOp>VENDA</natOp><mod>65</mod><serie>1</serie><nNF>21</nNF><dhEmi>2026-03-12T08:18:21-04:00</dhEmi><tpNF>1</tpNF><idDest>1</idDest><cMunFG>1301209</cMunFG><tpImp>4</tpImp><tpEmis>1</tpEmis><cDV>0</cDV><tpAmb>2</tpAmb><finNFe>1</finNFe><indFinal>1</indFinal><indPres>1</indPres><procEmi>0</procEmi><verProc>PDV-ACAI-1.0</verProc></ide><emit><CNPJ>59598453000104</CNPJ><xNome>PAPAGAIO COMERCIO DE MOTOS LTDA</xNome><xFant>PAPAGAIO MOTOS</xFant><enderEmit><xLgr>PADRE VICENTE NOGUEIRA</xLgr><nro>149</nro><xBairro>ITAMARATI</xBairro><cMun>1301209</cMun><xMun>COARI</xMun><UF>AM</UF><CEP>69460000</CEP><cPais>1058</cPais><xPais>Brasil</xPais><fone>9791979595</fone></enderEmit><IE>054756448</IE><CRT>3</CRT></emit><det nItem=\"1\"><prod><cProd>1</cProd><cEAN>SEM GTIN</cEAN><xProd>Mult&#xED;metro Digital Profissional</xProd><NCM>21069090</NCM><CFOP>5102</CFOP><uCom>UN</uCom><qCom>5.000</qCom><vUnCom>145.00</vUnCom><vProd>725.00</vProd><cEANTrib>SEM GTIN</cEANTrib><uTrib>UN</uTrib><qTrib>5.000</qTrib><vUnTrib>145.00</vUnTrib><indTot>1</indTot></prod><imposto><ICMS><ICMSSN102><orig>0</orig><CSOSN>102</CSOSN></ICMSSN102></ICMS><PIS><PISNT><CST>07</CST></PISNT></PIS><COFINS><COFINSNT><CST>07</CST></COFINSNT></COFINS></imposto></det><total><ICMSTot><vBC>0.00</vBC><vICMS>0.00</vICMS><vICMSDeson>0.00</vICMSDeson><vFCP>0.00</vFCP><vBCST>0.00</vBCST><vST>0.00</vST><vFCPST>0.00</vFCPST><vFCPSTRet>0.00</vFCPSTRet><vProd>725.00</vProd><vFrete>0.00</vFrete><vSeg>0.00</vSeg><vDesc>0.00</vDesc><vII>0.00</vII><vIPI>0.00</vIPI><vIPIDevol>0.00</vIPIDevol><vPIS>0.00</vPIS><vCOFINS>0.00</vCOFINS><vOutro>0.00</vOutro><vNF>725.00</vNF></ICMSTot></total><transp><modFrete>9</modFrete></transp><pag><detPag><indPag>0</indPag><tPag>01</tPag><vPag>725.00</vPag></detPag></pag><infAdic><infCpl>PDV A&#xE7;aiteria</infCpl></infAdic></infNFe><infNFeSupl><qrCode>https://sistemas.sefaz.am.gov.br/nfceweb-hom/consultarNFCe.jsp?p=13260359598453000104650010000000211793473060|2|2|2|03FCDA837BFAD888DBA419029D51284131573CF5</qrCode><urlChave>www.sefaz.am.gov.br/nfce/consulta</urlChave></infNFeSupl><Signature xmlns=\"http://www.w3.org/2000/09/xmldsig#\"><SignedInfo><CanonicalizationMethod Algorithm=\"http://www.w3.org/TR/2001/REC-xml-c14n-20010315\"/><SignatureMethod Algorithm=\"http://www.w3.org/2000/09/xmldsig#rsa-sha1\"/><Reference URI=\"#NFe13260359598453000104650010000000211793473060\"><Transforms><Transform Algorithm=\"http://www.w3.org/2000/09/xmldsig#enveloped-signature\"/><Transform Algorithm=\"http://www.w3.org/TR/2001/REC-xml-c14n-20010315\"/></Transforms><DigestMethod Algorithm=\"http://www.w3.org/2000/09/xmldsig#sha1\"/><DigestValue>v4yTffPYiw79G4OraZspawF41X8=</DigestValue></Reference></SignedInfo><SignatureValue>fh8MoPmLS7xJtgOUEzNaGuu9IYQ8MfH+cGCgSI+83twUbyxyg9Col4BZV2PKjBDcZHijyfX1wvS7+Y9H8n2fdnw5N5PA0GhmSxQhG2ysv8CgeOB5e+vdS2JzRA0gtFRv5cFiHydNMUIU5eZMMPr4a1Pfv52C3wpbIQ6bMUusGTKTLfFQWImlPmTJHD9dmLK1NJytdFP97I/xRee2DRaAcjU7no9pMMexKMfETyLGB61D3hzNVxjEHa0BNLbJpgxr8HL/zg6dlBffP6CLk5LlIHUjbZXqmUytEx4S+WinGlTwNU+bi41H1uYgUseGiDekffckDCTauZfQoTYGvrUCgA==</SignatureValue><KeyInfo><X509Data><X509Certificate>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</X509Certificate></X509Data></KeyInfo></Signature></NFe><protNFe versao=\"4.00\"><infProt><tpAmb>2</tpAmb><verAplic>AM3.10-4.00</verAplic><chNFe>13260359598453000104650010000000211793473060</chNFe><dhRecbto>2026-03-12T08:18:22-04:00</dhRecbto><cStat>373</cStat><xMotivo>Rejeicao: Descricao do primeiro item diferente de NOTA FISCAL EMITIDA EM AMBIENTE DE HOMOLOGACAO - SEM VALOR FISCAL</xMotivo></infProt></protNFe></nfeProc>', '<?xml version=\"1.0\"?><NFe xmlns=\"http://www.portalfiscal.inf.br/nfe\"><infNFe Id=\"NFe13260359598453000104650010000000211793473060\" versao=\"4.00\"><ide><cUF>13</cUF><cNF>79347306</cNF><natOp>VENDA</natOp><mod>65</mod><serie>1</serie><nNF>21</nNF><dhEmi>2026-03-12T08:18:21-04:00</dhEmi><tpNF>1</tpNF><idDest>1</idDest><cMunFG>1301209</cMunFG><tpImp>4</tpImp><tpEmis>1</tpEmis><cDV>0</cDV><tpAmb>2</tpAmb><finNFe>1</finNFe><indFinal>1</indFinal><indPres>1</indPres><procEmi>0</procEmi><verProc>PDV-ACAI-1.0</verProc></ide><emit><CNPJ>59598453000104</CNPJ><xNome>PAPAGAIO COMERCIO DE MOTOS LTDA</xNome><xFant>PAPAGAIO MOTOS</xFant><enderEmit><xLgr>PADRE VICENTE NOGUEIRA</xLgr><nro>149</nro><xBairro>ITAMARATI</xBairro><cMun>1301209</cMun><xMun>COARI</xMun><UF>AM</UF><CEP>69460000</CEP><cPais>1058</cPais><xPais>Brasil</xPais><fone>9791979595</fone></enderEmit><IE>054756448</IE><CRT>3</CRT></emit><det nItem=\"1\"><prod><cProd>1</cProd><cEAN>SEM GTIN</cEAN><xProd>Mult&#xED;metro Digital Profissional</xProd><NCM>21069090</NCM><CFOP>5102</CFOP><uCom>UN</uCom><qCom>5.000</qCom><vUnCom>145.00</vUnCom><vProd>725.00</vProd><cEANTrib>SEM GTIN</cEANTrib><uTrib>UN</uTrib><qTrib>5.000</qTrib><vUnTrib>145.00</vUnTrib><indTot>1</indTot></prod><imposto><ICMS><ICMSSN102><orig>0</orig><CSOSN>102</CSOSN></ICMSSN102></ICMS><PIS><PISNT><CST>07</CST></PISNT></PIS><COFINS><COFINSNT><CST>07</CST></COFINSNT></COFINS></imposto></det><total><ICMSTot><vBC>0.00</vBC><vICMS>0.00</vICMS><vICMSDeson>0.00</vICMSDeson><vFCP>0.00</vFCP><vBCST>0.00</vBCST><vST>0.00</vST><vFCPST>0.00</vFCPST><vFCPSTRet>0.00</vFCPSTRet><vProd>725.00</vProd><vFrete>0.00</vFrete><vSeg>0.00</vSeg><vDesc>0.00</vDesc><vII>0.00</vII><vIPI>0.00</vIPI><vIPIDevol>0.00</vIPIDevol><vPIS>0.00</vPIS><vCOFINS>0.00</vCOFINS><vOutro>0.00</vOutro><vNF>725.00</vNF></ICMSTot></total><transp><modFrete>9</modFrete></transp><pag><detPag><indPag>0</indPag><tPag>01</tPag><vPag>725.00</vPag></detPag></pag><infAdic><infCpl>PDV A&#xE7;aiteria</infCpl></infAdic></infNFe><infNFeSupl><qrCode>https://sistemas.sefaz.am.gov.br/nfceweb-hom/consultarNFCe.jsp?p=13260359598453000104650010000000211793473060|2|2|2|03FCDA837BFAD888DBA419029D51284131573CF5</qrCode><urlChave>www.sefaz.am.gov.br/nfce/consulta</urlChave></infNFeSupl><Signature xmlns=\"http://www.w3.org/2000/09/xmldsig#\"><SignedInfo><CanonicalizationMethod Algorithm=\"http://www.w3.org/TR/2001/REC-xml-c14n-20010315\"/><SignatureMethod Algorithm=\"http://www.w3.org/2000/09/xmldsig#rsa-sha1\"/><Reference URI=\"#NFe13260359598453000104650010000000211793473060\"><Transforms><Transform Algorithm=\"http://www.w3.org/2000/09/xmldsig#enveloped-signature\"/><Transform Algorithm=\"http://www.w3.org/TR/2001/REC-xml-c14n-20010315\"/></Transforms><DigestMethod Algorithm=\"http://www.w3.org/2000/09/xmldsig#sha1\"/><DigestValue>v4yTffPYiw79G4OraZspawF41X8=</DigestValue></Reference></SignedInfo><SignatureValue>fh8MoPmLS7xJtgOUEzNaGuu9IYQ8MfH+cGCgSI+83twUbyxyg9Col4BZV2PKjBDcZHijyfX1wvS7+Y9H8n2fdnw5N5PA0GhmSxQhG2ysv8CgeOB5e+vdS2JzRA0gtFRv5cFiHydNMUIU5eZMMPr4a1Pfv52C3wpbIQ6bMUusGTKTLfFQWImlPmTJHD9dmLK1NJytdFP97I/xRee2DRaAcjU7no9pMMexKMfETyLGB61D3hzNVxjEHa0BNLbJpgxr8HL/zg6dlBffP6CLk5LlIHUjbZXqmUytEx4S+WinGlTwNU+bi41H1uYgUseGiDekffckDCTauZfQoTYGvrUCgA==</SignatureValue><KeyInfo><X509Data><X509Certificate>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</X509Certificate></X509Data></KeyInfo></Signature></NFe>', '<?xml version=\'1.0\' encoding=\'utf-8\'?><soapenv:Envelope xmlns:soapenv=\"http://www.w3.org/2003/05/soap-envelope\"><soapenv:Body><nfeResultMsg xmlns=\"http://www.portalfiscal.inf.br/nfe/wsdl/NFeAutorizacao4\"><retEnviNFe xmlns=\"http://www.portalfiscal.inf.br/nfe\" versao=\"4.00\"><tpAmb>2</tpAmb><verAplic>AM3.10-4.00</verAplic><cStat>104</cStat><xMotivo>Lote processado</xMotivo><cUF>13</cUF><dhRecbto>2026-03-12T08:18:22-04:00</dhRecbto><protNFe versao=\"4.00\"><infProt><tpAmb>2</tpAmb><verAplic>AM3.10-4.00</verAplic><chNFe>13260359598453000104650010000000211793473060</chNFe><dhRecbto>2026-03-12T08:18:22-04:00</dhRecbto><cStat>373</cStat><xMotivo>Rejeicao: Descricao do primeiro item diferente de NOTA FISCAL EMITIDA EM AMBIENTE DE HOMOLOGACAO - SEM VALOR FISCAL</xMotivo></infProt></protNFe></retEnviNFe></nfeResultMsg></soapenv:Body></soapenv:Envelope>', 725.00, 0.00, '{\"tPag\":\"01\"}', '2026-03-12 12:18:22'),
(9, '125', 88, 2, 1, 22, '13260359598453000104650010000000221415351654', NULL, '104', 'Lote processado', '<?xml version=\"1.0\" encoding=\"UTF-8\"?><nfeProc xmlns=\"http://www.portalfiscal.inf.br/nfe\" versao=\"4.00\"><NFe xmlns=\"http://www.portalfiscal.inf.br/nfe\"><infNFe Id=\"NFe13260359598453000104650010000000221415351654\" versao=\"4.00\"><ide><cUF>13</cUF><cNF>41535165</cNF><natOp>VENDA</natOp><mod>65</mod><serie>1</serie><nNF>22</nNF><dhEmi>2026-03-12T08:54:45-04:00</dhEmi><tpNF>1</tpNF><idDest>1</idDest><cMunFG>1301209</cMunFG><tpImp>4</tpImp><tpEmis>1</tpEmis><cDV>4</cDV><tpAmb>2</tpAmb><finNFe>1</finNFe><indFinal>1</indFinal><indPres>1</indPres><procEmi>0</procEmi><verProc>PDV-ACAI-1.0</verProc></ide><emit><CNPJ>59598453000104</CNPJ><xNome>PAPAGAIO COMERCIO DE MOTOS LTDA</xNome><xFant>PAPAGAIO MOTOS</xFant><enderEmit><xLgr>PADRE VICENTE NOGUEIRA</xLgr><nro>149</nro><xBairro>ITAMARATI</xBairro><cMun>1301209</cMun><xMun>COARI</xMun><UF>AM</UF><CEP>69460000</CEP><cPais>1058</cPais><xPais>Brasil</xPais><fone>9791979595</fone></enderEmit><IE>054756448</IE><CRT>3</CRT></emit><det nItem=\"1\"><prod><cProd>1</cProd><cEAN>SEM GTIN</cEAN><xProd>Buzina 12v c100</xProd><NCM>85123000</NCM><CFOP>5102</CFOP><uCom>UN</uCom><qCom>1.000</qCom><vUnCom>60.00</vUnCom><vProd>60.00</vProd><cEANTrib>SEM GTIN</cEANTrib><uTrib>UN</uTrib><qTrib>1.000</qTrib><vUnTrib>60.00</vUnTrib><indTot>1</indTot></prod><imposto><ICMS><ICMSSN102><orig>0</orig><CSOSN>102</CSOSN></ICMSSN102></ICMS><PIS><PISNT><CST>07</CST></PISNT></PIS><COFINS><COFINSNT><CST>07</CST></COFINSNT></COFINS></imposto></det><total><ICMSTot><vBC>0.00</vBC><vICMS>0.00</vICMS><vICMSDeson>0.00</vICMSDeson><vFCP>0.00</vFCP><vBCST>0.00</vBCST><vST>0.00</vST><vFCPST>0.00</vFCPST><vFCPSTRet>0.00</vFCPSTRet><vProd>60.00</vProd><vFrete>0.00</vFrete><vSeg>0.00</vSeg><vDesc>0.00</vDesc><vII>0.00</vII><vIPI>0.00</vIPI><vIPIDevol>0.00</vIPIDevol><vPIS>0.00</vPIS><vCOFINS>0.00</vCOFINS><vOutro>0.00</vOutro><vNF>60.00</vNF></ICMSTot></total><transp><modFrete>9</modFrete></transp><pag><detPag><indPag>0</indPag><tPag>20</tPag><vPag>60.00</vPag></detPag></pag><infAdic><infCpl>PDV A&#xE7;aiteria</infCpl></infAdic></infNFe><infNFeSupl><qrCode>https://sistemas.sefaz.am.gov.br/nfceweb-hom/consultarNFCe.jsp?p=13260359598453000104650010000000221415351654|2|2|2|792835F0C94456B53A6D98706AF479B1F27E8C74</qrCode><urlChave>www.sefaz.am.gov.br/nfce/consulta</urlChave></infNFeSupl><Signature xmlns=\"http://www.w3.org/2000/09/xmldsig#\"><SignedInfo><CanonicalizationMethod Algorithm=\"http://www.w3.org/TR/2001/REC-xml-c14n-20010315\"/><SignatureMethod Algorithm=\"http://www.w3.org/2000/09/xmldsig#rsa-sha1\"/><Reference URI=\"#NFe13260359598453000104650010000000221415351654\"><Transforms><Transform Algorithm=\"http://www.w3.org/2000/09/xmldsig#enveloped-signature\"/><Transform Algorithm=\"http://www.w3.org/TR/2001/REC-xml-c14n-20010315\"/></Transforms><DigestMethod Algorithm=\"http://www.w3.org/2000/09/xmldsig#sha1\"/><DigestValue>k+JiptTMnGzPkx5A37Pz9RL479E=</DigestValue></Reference></SignedInfo><SignatureValue>tZpnfe8T3/TqKr47SlNdVLrVR5fKgoOLxMiHWKVPr0TJRSz5+eR/2Nv+vqVmJV8aRcJxWPCZDBdvwK/7Ls0f+/Brx0JY4KNrxPskCcFWt5ZGYPmSmqfbhwhSW42GwbmHsTSbT/Rs4fKjzP+PzSUsCeZzYeIIkSQhxldiWTzQyUGSp5QnTGwrPlS1awu/BtT1vWOmSYWOi9DMCDYHMWwjUkG1Lu73szC9f7SoBK1Jppi9Z9ajbjRXIiAlVgLXGBF43WF3YDcLykyvPBgGf/cGW27W86Q4rW4SooI2vMjaJu7iLVMUl0gdw9pkoh61bX3sTIxMv2ZoLrzsgP0/A97KsA==</SignatureValue><KeyInfo><X509Data><X509Certificate>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</X509Certificate></X509Data></KeyInfo></Signature></NFe><protNFe versao=\"4.00\"><infProt><tpAmb>2</tpAmb><verAplic>AM3.10-4.00</verAplic><chNFe>13260359598453000104650010000000221415351654</chNFe><dhRecbto>2026-03-12T08:54:45-04:00</dhRecbto><cStat>373</cStat><xMotivo>Rejeicao: Descricao do primeiro item diferente de NOTA FISCAL EMITIDA EM AMBIENTE DE HOMOLOGACAO - SEM VALOR FISCAL</xMotivo></infProt></protNFe></nfeProc>', '<?xml version=\"1.0\"?><NFe xmlns=\"http://www.portalfiscal.inf.br/nfe\"><infNFe Id=\"NFe13260359598453000104650010000000221415351654\" versao=\"4.00\"><ide><cUF>13</cUF><cNF>41535165</cNF><natOp>VENDA</natOp><mod>65</mod><serie>1</serie><nNF>22</nNF><dhEmi>2026-03-12T08:54:45-04:00</dhEmi><tpNF>1</tpNF><idDest>1</idDest><cMunFG>1301209</cMunFG><tpImp>4</tpImp><tpEmis>1</tpEmis><cDV>4</cDV><tpAmb>2</tpAmb><finNFe>1</finNFe><indFinal>1</indFinal><indPres>1</indPres><procEmi>0</procEmi><verProc>PDV-ACAI-1.0</verProc></ide><emit><CNPJ>59598453000104</CNPJ><xNome>PAPAGAIO COMERCIO DE MOTOS LTDA</xNome><xFant>PAPAGAIO MOTOS</xFant><enderEmit><xLgr>PADRE VICENTE NOGUEIRA</xLgr><nro>149</nro><xBairro>ITAMARATI</xBairro><cMun>1301209</cMun><xMun>COARI</xMun><UF>AM</UF><CEP>69460000</CEP><cPais>1058</cPais><xPais>Brasil</xPais><fone>9791979595</fone></enderEmit><IE>054756448</IE><CRT>3</CRT></emit><det nItem=\"1\"><prod><cProd>1</cProd><cEAN>SEM GTIN</cEAN><xProd>Buzina 12v c100</xProd><NCM>85123000</NCM><CFOP>5102</CFOP><uCom>UN</uCom><qCom>1.000</qCom><vUnCom>60.00</vUnCom><vProd>60.00</vProd><cEANTrib>SEM GTIN</cEANTrib><uTrib>UN</uTrib><qTrib>1.000</qTrib><vUnTrib>60.00</vUnTrib><indTot>1</indTot></prod><imposto><ICMS><ICMSSN102><orig>0</orig><CSOSN>102</CSOSN></ICMSSN102></ICMS><PIS><PISNT><CST>07</CST></PISNT></PIS><COFINS><COFINSNT><CST>07</CST></COFINSNT></COFINS></imposto></det><total><ICMSTot><vBC>0.00</vBC><vICMS>0.00</vICMS><vICMSDeson>0.00</vICMSDeson><vFCP>0.00</vFCP><vBCST>0.00</vBCST><vST>0.00</vST><vFCPST>0.00</vFCPST><vFCPSTRet>0.00</vFCPSTRet><vProd>60.00</vProd><vFrete>0.00</vFrete><vSeg>0.00</vSeg><vDesc>0.00</vDesc><vII>0.00</vII><vIPI>0.00</vIPI><vIPIDevol>0.00</vIPIDevol><vPIS>0.00</vPIS><vCOFINS>0.00</vCOFINS><vOutro>0.00</vOutro><vNF>60.00</vNF></ICMSTot></total><transp><modFrete>9</modFrete></transp><pag><detPag><indPag>0</indPag><tPag>20</tPag><vPag>60.00</vPag></detPag></pag><infAdic><infCpl>PDV A&#xE7;aiteria</infCpl></infAdic></infNFe><infNFeSupl><qrCode>https://sistemas.sefaz.am.gov.br/nfceweb-hom/consultarNFCe.jsp?p=13260359598453000104650010000000221415351654|2|2|2|792835F0C94456B53A6D98706AF479B1F27E8C74</qrCode><urlChave>www.sefaz.am.gov.br/nfce/consulta</urlChave></infNFeSupl><Signature xmlns=\"http://www.w3.org/2000/09/xmldsig#\"><SignedInfo><CanonicalizationMethod Algorithm=\"http://www.w3.org/TR/2001/REC-xml-c14n-20010315\"/><SignatureMethod Algorithm=\"http://www.w3.org/2000/09/xmldsig#rsa-sha1\"/><Reference URI=\"#NFe13260359598453000104650010000000221415351654\"><Transforms><Transform Algorithm=\"http://www.w3.org/2000/09/xmldsig#enveloped-signature\"/><Transform Algorithm=\"http://www.w3.org/TR/2001/REC-xml-c14n-20010315\"/></Transforms><DigestMethod Algorithm=\"http://www.w3.org/2000/09/xmldsig#sha1\"/><DigestValue>k+JiptTMnGzPkx5A37Pz9RL479E=</DigestValue></Reference></SignedInfo><SignatureValue>tZpnfe8T3/TqKr47SlNdVLrVR5fKgoOLxMiHWKVPr0TJRSz5+eR/2Nv+vqVmJV8aRcJxWPCZDBdvwK/7Ls0f+/Brx0JY4KNrxPskCcFWt5ZGYPmSmqfbhwhSW42GwbmHsTSbT/Rs4fKjzP+PzSUsCeZzYeIIkSQhxldiWTzQyUGSp5QnTGwrPlS1awu/BtT1vWOmSYWOi9DMCDYHMWwjUkG1Lu73szC9f7SoBK1Jppi9Z9ajbjRXIiAlVgLXGBF43WF3YDcLykyvPBgGf/cGW27W86Q4rW4SooI2vMjaJu7iLVMUl0gdw9pkoh61bX3sTIxMv2ZoLrzsgP0/A97KsA==</SignatureValue><KeyInfo><X509Data><X509Certificate>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</X509Certificate></X509Data></KeyInfo></Signature></NFe>', '<?xml version=\'1.0\' encoding=\'utf-8\'?><soapenv:Envelope xmlns:soapenv=\"http://www.w3.org/2003/05/soap-envelope\"><soapenv:Body><nfeResultMsg xmlns=\"http://www.portalfiscal.inf.br/nfe/wsdl/NFeAutorizacao4\"><retEnviNFe xmlns=\"http://www.portalfiscal.inf.br/nfe\" versao=\"4.00\"><tpAmb>2</tpAmb><verAplic>AM3.10-4.00</verAplic><cStat>104</cStat><xMotivo>Lote processado</xMotivo><cUF>13</cUF><dhRecbto>2026-03-12T08:54:45-04:00</dhRecbto><protNFe versao=\"4.00\"><infProt><tpAmb>2</tpAmb><verAplic>AM3.10-4.00</verAplic><chNFe>13260359598453000104650010000000221415351654</chNFe><dhRecbto>2026-03-12T08:54:45-04:00</dhRecbto><cStat>373</cStat><xMotivo>Rejeicao: Descricao do primeiro item diferente de NOTA FISCAL EMITIDA EM AMBIENTE DE HOMOLOGACAO - SEM VALOR FISCAL</xMotivo></infProt></protNFe></retEnviNFe></nfeResultMsg></soapenv:Body></soapenv:Envelope>', 60.00, 0.00, '{\"tPag\":\"20\"}', '2026-03-12 12:54:45');
INSERT INTO `nfce_emitidas` (`id`, `empresa_id`, `venda_id`, `ambiente`, `serie`, `numero`, `chave`, `protocolo`, `status_sefaz`, `mensagem`, `xml_nfeproc`, `xml_envio`, `xml_retorno`, `valor_total`, `valor_troco`, `tpag_json`, `created_at`) VALUES
(10, '125', 89, 2, 1, 23, '13260359598453000104650010000000231037780795', NULL, '104', 'Lote processado', '<?xml version=\"1.0\" encoding=\"UTF-8\"?><nfeProc xmlns=\"http://www.portalfiscal.inf.br/nfe\" versao=\"4.00\"><NFe xmlns=\"http://www.portalfiscal.inf.br/nfe\"><infNFe Id=\"NFe13260359598453000104650010000000231037780795\" versao=\"4.00\"><ide><cUF>13</cUF><cNF>03778079</cNF><natOp>VENDA</natOp><mod>65</mod><serie>1</serie><nNF>23</nNF><dhEmi>2026-03-12T08:55:23-04:00</dhEmi><tpNF>1</tpNF><idDest>1</idDest><cMunFG>1301209</cMunFG><tpImp>4</tpImp><tpEmis>1</tpEmis><cDV>5</cDV><tpAmb>2</tpAmb><finNFe>1</finNFe><indFinal>1</indFinal><indPres>1</indPres><procEmi>0</procEmi><verProc>PDV-ACAI-1.0</verProc></ide><emit><CNPJ>59598453000104</CNPJ><xNome>PAPAGAIO COMERCIO DE MOTOS LTDA</xNome><xFant>PAPAGAIO MOTOS</xFant><enderEmit><xLgr>PADRE VICENTE NOGUEIRA</xLgr><nro>149</nro><xBairro>ITAMARATI</xBairro><cMun>1301209</cMun><xMun>COARI</xMun><UF>AM</UF><CEP>69460000</CEP><cPais>1058</cPais><xPais>Brasil</xPais><fone>9791979595</fone></enderEmit><IE>054756448</IE><CRT>3</CRT></emit><det nItem=\"1\"><prod><cProd>1</cProd><cEAN>SEM GTIN</cEAN><xProd>Buzina 12v c100</xProd><NCM>85123000</NCM><CFOP>5102</CFOP><uCom>UN</uCom><qCom>1.000</qCom><vUnCom>60.00</vUnCom><vProd>60.00</vProd><cEANTrib>SEM GTIN</cEANTrib><uTrib>UN</uTrib><qTrib>1.000</qTrib><vUnTrib>60.00</vUnTrib><indTot>1</indTot></prod><imposto><ICMS><ICMSSN102><orig>0</orig><CSOSN>102</CSOSN></ICMSSN102></ICMS><PIS><PISNT><CST>07</CST></PISNT></PIS><COFINS><COFINSNT><CST>07</CST></COFINSNT></COFINS></imposto></det><total><ICMSTot><vBC>0.00</vBC><vICMS>0.00</vICMS><vICMSDeson>0.00</vICMSDeson><vFCP>0.00</vFCP><vBCST>0.00</vBCST><vST>0.00</vST><vFCPST>0.00</vFCPST><vFCPSTRet>0.00</vFCPSTRet><vProd>60.00</vProd><vFrete>0.00</vFrete><vSeg>0.00</vSeg><vDesc>0.00</vDesc><vII>0.00</vII><vIPI>0.00</vIPI><vIPIDevol>0.00</vIPIDevol><vPIS>0.00</vPIS><vCOFINS>0.00</vCOFINS><vOutro>0.00</vOutro><vNF>60.00</vNF></ICMSTot></total><transp><modFrete>9</modFrete></transp><pag><detPag><indPag>0</indPag><tPag>03</tPag><vPag>60.00</vPag><card><tpIntegra>2</tpIntegra></card></detPag></pag><infAdic><infCpl>PDV A&#xE7;aiteria</infCpl></infAdic></infNFe><infNFeSupl><qrCode>https://sistemas.sefaz.am.gov.br/nfceweb-hom/consultarNFCe.jsp?p=13260359598453000104650010000000231037780795|2|2|2|A598A2F88E36F7B13A5F45B2B3F0476E23726C9E</qrCode><urlChave>www.sefaz.am.gov.br/nfce/consulta</urlChave></infNFeSupl><Signature xmlns=\"http://www.w3.org/2000/09/xmldsig#\"><SignedInfo><CanonicalizationMethod Algorithm=\"http://www.w3.org/TR/2001/REC-xml-c14n-20010315\"/><SignatureMethod Algorithm=\"http://www.w3.org/2000/09/xmldsig#rsa-sha1\"/><Reference URI=\"#NFe13260359598453000104650010000000231037780795\"><Transforms><Transform Algorithm=\"http://www.w3.org/2000/09/xmldsig#enveloped-signature\"/><Transform Algorithm=\"http://www.w3.org/TR/2001/REC-xml-c14n-20010315\"/></Transforms><DigestMethod Algorithm=\"http://www.w3.org/2000/09/xmldsig#sha1\"/><DigestValue>mwzVGI6FljJJGsjsrLAN+t0MzOw=</DigestValue></Reference></SignedInfo><SignatureValue>p0VVTxrPUeOXhkLi5Kjx0EXjcBG9RqkU5wsJ4Bk3hpkZ1vw/dLJQumM6GXQMZqFPoiyDm7++Lhw4qyutt9+WxA6GXYOLZxV2bxQ6j9Z01Crft5LKaKTIlmxZB+71JRdLlcfNHgkVcLSM6KNMj2kPfzuDMGwnkKgfwiPYyvRZUBRkEWneOCCVwmJD9aP/LxW/ryNHce2dHEOFK1OHeoFAmJp8iAUGZlfiXS03tMDorH9tNSSuPBBeXakNiv6HqG+hWxQCcU9t2l++zm1d00GnuZ0LX937ZVVTHGrun/uf3TMJpifgJ1G+PchHiviRzyy+36gOEabIpEbdWoCiuR7S8w==</SignatureValue><KeyInfo><X509Data><X509Certificate>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</X509Certificate></X509Data></KeyInfo></Signature></NFe><protNFe versao=\"4.00\"><infProt><tpAmb>2</tpAmb><verAplic>AM3.10-4.00</verAplic><chNFe>13260359598453000104650010000000231037780795</chNFe><dhRecbto>2026-03-12T08:55:24-04:00</dhRecbto><cStat>373</cStat><xMotivo>Rejeicao: Descricao do primeiro item diferente de NOTA FISCAL EMITIDA EM AMBIENTE DE HOMOLOGACAO - SEM VALOR FISCAL</xMotivo></infProt></protNFe></nfeProc>', '<?xml version=\"1.0\"?><NFe xmlns=\"http://www.portalfiscal.inf.br/nfe\"><infNFe Id=\"NFe13260359598453000104650010000000231037780795\" versao=\"4.00\"><ide><cUF>13</cUF><cNF>03778079</cNF><natOp>VENDA</natOp><mod>65</mod><serie>1</serie><nNF>23</nNF><dhEmi>2026-03-12T08:55:23-04:00</dhEmi><tpNF>1</tpNF><idDest>1</idDest><cMunFG>1301209</cMunFG><tpImp>4</tpImp><tpEmis>1</tpEmis><cDV>5</cDV><tpAmb>2</tpAmb><finNFe>1</finNFe><indFinal>1</indFinal><indPres>1</indPres><procEmi>0</procEmi><verProc>PDV-ACAI-1.0</verProc></ide><emit><CNPJ>59598453000104</CNPJ><xNome>PAPAGAIO COMERCIO DE MOTOS LTDA</xNome><xFant>PAPAGAIO MOTOS</xFant><enderEmit><xLgr>PADRE VICENTE NOGUEIRA</xLgr><nro>149</nro><xBairro>ITAMARATI</xBairro><cMun>1301209</cMun><xMun>COARI</xMun><UF>AM</UF><CEP>69460000</CEP><cPais>1058</cPais><xPais>Brasil</xPais><fone>9791979595</fone></enderEmit><IE>054756448</IE><CRT>3</CRT></emit><det nItem=\"1\"><prod><cProd>1</cProd><cEAN>SEM GTIN</cEAN><xProd>Buzina 12v c100</xProd><NCM>85123000</NCM><CFOP>5102</CFOP><uCom>UN</uCom><qCom>1.000</qCom><vUnCom>60.00</vUnCom><vProd>60.00</vProd><cEANTrib>SEM GTIN</cEANTrib><uTrib>UN</uTrib><qTrib>1.000</qTrib><vUnTrib>60.00</vUnTrib><indTot>1</indTot></prod><imposto><ICMS><ICMSSN102><orig>0</orig><CSOSN>102</CSOSN></ICMSSN102></ICMS><PIS><PISNT><CST>07</CST></PISNT></PIS><COFINS><COFINSNT><CST>07</CST></COFINSNT></COFINS></imposto></det><total><ICMSTot><vBC>0.00</vBC><vICMS>0.00</vICMS><vICMSDeson>0.00</vICMSDeson><vFCP>0.00</vFCP><vBCST>0.00</vBCST><vST>0.00</vST><vFCPST>0.00</vFCPST><vFCPSTRet>0.00</vFCPSTRet><vProd>60.00</vProd><vFrete>0.00</vFrete><vSeg>0.00</vSeg><vDesc>0.00</vDesc><vII>0.00</vII><vIPI>0.00</vIPI><vIPIDevol>0.00</vIPIDevol><vPIS>0.00</vPIS><vCOFINS>0.00</vCOFINS><vOutro>0.00</vOutro><vNF>60.00</vNF></ICMSTot></total><transp><modFrete>9</modFrete></transp><pag><detPag><indPag>0</indPag><tPag>03</tPag><vPag>60.00</vPag><card><tpIntegra>2</tpIntegra></card></detPag></pag><infAdic><infCpl>PDV A&#xE7;aiteria</infCpl></infAdic></infNFe><infNFeSupl><qrCode>https://sistemas.sefaz.am.gov.br/nfceweb-hom/consultarNFCe.jsp?p=13260359598453000104650010000000231037780795|2|2|2|A598A2F88E36F7B13A5F45B2B3F0476E23726C9E</qrCode><urlChave>www.sefaz.am.gov.br/nfce/consulta</urlChave></infNFeSupl><Signature xmlns=\"http://www.w3.org/2000/09/xmldsig#\"><SignedInfo><CanonicalizationMethod Algorithm=\"http://www.w3.org/TR/2001/REC-xml-c14n-20010315\"/><SignatureMethod Algorithm=\"http://www.w3.org/2000/09/xmldsig#rsa-sha1\"/><Reference URI=\"#NFe13260359598453000104650010000000231037780795\"><Transforms><Transform Algorithm=\"http://www.w3.org/2000/09/xmldsig#enveloped-signature\"/><Transform Algorithm=\"http://www.w3.org/TR/2001/REC-xml-c14n-20010315\"/></Transforms><DigestMethod Algorithm=\"http://www.w3.org/2000/09/xmldsig#sha1\"/><DigestValue>mwzVGI6FljJJGsjsrLAN+t0MzOw=</DigestValue></Reference></SignedInfo><SignatureValue>p0VVTxrPUeOXhkLi5Kjx0EXjcBG9RqkU5wsJ4Bk3hpkZ1vw/dLJQumM6GXQMZqFPoiyDm7++Lhw4qyutt9+WxA6GXYOLZxV2bxQ6j9Z01Crft5LKaKTIlmxZB+71JRdLlcfNHgkVcLSM6KNMj2kPfzuDMGwnkKgfwiPYyvRZUBRkEWneOCCVwmJD9aP/LxW/ryNHce2dHEOFK1OHeoFAmJp8iAUGZlfiXS03tMDorH9tNSSuPBBeXakNiv6HqG+hWxQCcU9t2l++zm1d00GnuZ0LX937ZVVTHGrun/uf3TMJpifgJ1G+PchHiviRzyy+36gOEabIpEbdWoCiuR7S8w==</SignatureValue><KeyInfo><X509Data><X509Certificate>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</X509Certificate></X509Data></KeyInfo></Signature></NFe>', '<?xml version=\'1.0\' encoding=\'utf-8\'?><soapenv:Envelope xmlns:soapenv=\"http://www.w3.org/2003/05/soap-envelope\"><soapenv:Body><nfeResultMsg xmlns=\"http://www.portalfiscal.inf.br/nfe/wsdl/NFeAutorizacao4\"><retEnviNFe xmlns=\"http://www.portalfiscal.inf.br/nfe\" versao=\"4.00\"><tpAmb>2</tpAmb><verAplic>AM3.10-4.00</verAplic><cStat>104</cStat><xMotivo>Lote processado</xMotivo><cUF>13</cUF><dhRecbto>2026-03-12T08:55:24-04:00</dhRecbto><protNFe versao=\"4.00\"><infProt><tpAmb>2</tpAmb><verAplic>AM3.10-4.00</verAplic><chNFe>13260359598453000104650010000000231037780795</chNFe><dhRecbto>2026-03-12T08:55:24-04:00</dhRecbto><cStat>373</cStat><xMotivo>Rejeicao: Descricao do primeiro item diferente de NOTA FISCAL EMITIDA EM AMBIENTE DE HOMOLOGACAO - SEM VALOR FISCAL</xMotivo></infProt></protNFe></retEnviNFe></nfeResultMsg></soapenv:Body></soapenv:Envelope>', 60.00, 0.00, '{\"tPag\":\"03\"}', '2026-03-12 12:55:24'),
(11, '125', 90, 2, 1, 24, '13260359598453000104650010000000241548893258', NULL, '104', 'Lote processado', '<?xml version=\"1.0\" encoding=\"UTF-8\"?><nfeProc xmlns=\"http://www.portalfiscal.inf.br/nfe\" versao=\"4.00\"><NFe xmlns=\"http://www.portalfiscal.inf.br/nfe\"><infNFe Id=\"NFe13260359598453000104650010000000241548893258\" versao=\"4.00\"><ide><cUF>13</cUF><cNF>54889325</cNF><natOp>VENDA</natOp><mod>65</mod><serie>1</serie><nNF>24</nNF><dhEmi>2026-03-12T09:01:46-04:00</dhEmi><tpNF>1</tpNF><idDest>1</idDest><cMunFG>1301209</cMunFG><tpImp>4</tpImp><tpEmis>1</tpEmis><cDV>8</cDV><tpAmb>2</tpAmb><finNFe>1</finNFe><indFinal>1</indFinal><indPres>1</indPres><procEmi>0</procEmi><verProc>PDV-ACAI-1.0</verProc></ide><emit><CNPJ>59598453000104</CNPJ><xNome>PAPAGAIO COMERCIO DE MOTOS LTDA</xNome><xFant>PAPAGAIO MOTOS</xFant><enderEmit><xLgr>PADRE VICENTE NOGUEIRA</xLgr><nro>149</nro><xBairro>ITAMARATI</xBairro><cMun>1301209</cMun><xMun>COARI</xMun><UF>AM</UF><CEP>69460000</CEP><cPais>1058</cPais><xPais>Brasil</xPais><fone>9791979595</fone></enderEmit><IE>054756448</IE><CRT>3</CRT></emit><det nItem=\"1\"><prod><cProd>1</cProd><cEAN>SEM GTIN</cEAN><xProd>Buzina 12v c100</xProd><NCM>85123000</NCM><CFOP>5102</CFOP><uCom>UN</uCom><qCom>1.000</qCom><vUnCom>60.00</vUnCom><vProd>60.00</vProd><cEANTrib>SEM GTIN</cEANTrib><uTrib>UN</uTrib><qTrib>1.000</qTrib><vUnTrib>60.00</vUnTrib><indTot>1</indTot></prod><imposto><ICMS><ICMSSN102><orig>0</orig><CSOSN>102</CSOSN></ICMSSN102></ICMS><PIS><PISNT><CST>07</CST></PISNT></PIS><COFINS><COFINSNT><CST>07</CST></COFINSNT></COFINS></imposto></det><total><ICMSTot><vBC>0.00</vBC><vICMS>0.00</vICMS><vICMSDeson>0.00</vICMSDeson><vFCP>0.00</vFCP><vBCST>0.00</vBCST><vST>0.00</vST><vFCPST>0.00</vFCPST><vFCPSTRet>0.00</vFCPSTRet><vProd>60.00</vProd><vFrete>0.00</vFrete><vSeg>0.00</vSeg><vDesc>0.00</vDesc><vII>0.00</vII><vIPI>0.00</vIPI><vIPIDevol>0.00</vIPIDevol><vPIS>0.00</vPIS><vCOFINS>0.00</vCOFINS><vOutro>0.00</vOutro><vNF>60.00</vNF></ICMSTot></total><transp><modFrete>9</modFrete></transp><pag><detPag><indPag>0</indPag><tPag>20</tPag><vPag>60.00</vPag></detPag></pag><infAdic><infCpl>PDV A&#xE7;aiteria</infCpl></infAdic></infNFe><infNFeSupl><qrCode>https://sistemas.sefaz.am.gov.br/nfceweb-hom/consultarNFCe.jsp?p=13260359598453000104650010000000241548893258|2|2|2|E947A625DF19978B123904A4E701FABAB06424B0</qrCode><urlChave>www.sefaz.am.gov.br/nfce/consulta</urlChave></infNFeSupl><Signature xmlns=\"http://www.w3.org/2000/09/xmldsig#\"><SignedInfo><CanonicalizationMethod Algorithm=\"http://www.w3.org/TR/2001/REC-xml-c14n-20010315\"/><SignatureMethod Algorithm=\"http://www.w3.org/2000/09/xmldsig#rsa-sha1\"/><Reference URI=\"#NFe13260359598453000104650010000000241548893258\"><Transforms><Transform Algorithm=\"http://www.w3.org/2000/09/xmldsig#enveloped-signature\"/><Transform Algorithm=\"http://www.w3.org/TR/2001/REC-xml-c14n-20010315\"/></Transforms><DigestMethod Algorithm=\"http://www.w3.org/2000/09/xmldsig#sha1\"/><DigestValue>2DBx0MTyBthkSpEJNTSPvSFbXPI=</DigestValue></Reference></SignedInfo><SignatureValue>Mc9sTVQSlAH25Ih97PfJW4UFxDXgrqltSRqiOdbnSWZuR179d0hXBdwitZ8ZB9b8neEp9UDTsMgV90u3hTMo/9BAS7kvyPftW25HLFEadTIqAB8sd458oKgwxur51AZvSyffyLIyj4MfXdohxoixBMHcLaK/L2FzAvtlmCHzi2NnIxkbHf5bNKTck9xzZwwHuLsulbTsLXd7w4Xdps9aIxWV7S+ykEPjNWWgE7DMaoypyBRRF/8BWSDsCpUdFl5cXZC2ZDygjQlHq+7jmkC6zxFsHGCl+zVjVgd24213iX6GIBDQFNdRiIzhwNRHc8F+5SbMjWkMroNLMHvZQdWR/g==</SignatureValue><KeyInfo><X509Data><X509Certificate>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</X509Certificate></X509Data></KeyInfo></Signature></NFe><protNFe versao=\"4.00\"><infProt><tpAmb>2</tpAmb><verAplic>AM3.10-4.00</verAplic><chNFe>13260359598453000104650010000000241548893258</chNFe><dhRecbto>2026-03-12T09:01:47-04:00</dhRecbto><cStat>373</cStat><xMotivo>Rejeicao: Descricao do primeiro item diferente de NOTA FISCAL EMITIDA EM AMBIENTE DE HOMOLOGACAO - SEM VALOR FISCAL</xMotivo></infProt></protNFe></nfeProc>', '<?xml version=\"1.0\"?><NFe xmlns=\"http://www.portalfiscal.inf.br/nfe\"><infNFe Id=\"NFe13260359598453000104650010000000241548893258\" versao=\"4.00\"><ide><cUF>13</cUF><cNF>54889325</cNF><natOp>VENDA</natOp><mod>65</mod><serie>1</serie><nNF>24</nNF><dhEmi>2026-03-12T09:01:46-04:00</dhEmi><tpNF>1</tpNF><idDest>1</idDest><cMunFG>1301209</cMunFG><tpImp>4</tpImp><tpEmis>1</tpEmis><cDV>8</cDV><tpAmb>2</tpAmb><finNFe>1</finNFe><indFinal>1</indFinal><indPres>1</indPres><procEmi>0</procEmi><verProc>PDV-ACAI-1.0</verProc></ide><emit><CNPJ>59598453000104</CNPJ><xNome>PAPAGAIO COMERCIO DE MOTOS LTDA</xNome><xFant>PAPAGAIO MOTOS</xFant><enderEmit><xLgr>PADRE VICENTE NOGUEIRA</xLgr><nro>149</nro><xBairro>ITAMARATI</xBairro><cMun>1301209</cMun><xMun>COARI</xMun><UF>AM</UF><CEP>69460000</CEP><cPais>1058</cPais><xPais>Brasil</xPais><fone>9791979595</fone></enderEmit><IE>054756448</IE><CRT>3</CRT></emit><det nItem=\"1\"><prod><cProd>1</cProd><cEAN>SEM GTIN</cEAN><xProd>Buzina 12v c100</xProd><NCM>85123000</NCM><CFOP>5102</CFOP><uCom>UN</uCom><qCom>1.000</qCom><vUnCom>60.00</vUnCom><vProd>60.00</vProd><cEANTrib>SEM GTIN</cEANTrib><uTrib>UN</uTrib><qTrib>1.000</qTrib><vUnTrib>60.00</vUnTrib><indTot>1</indTot></prod><imposto><ICMS><ICMSSN102><orig>0</orig><CSOSN>102</CSOSN></ICMSSN102></ICMS><PIS><PISNT><CST>07</CST></PISNT></PIS><COFINS><COFINSNT><CST>07</CST></COFINSNT></COFINS></imposto></det><total><ICMSTot><vBC>0.00</vBC><vICMS>0.00</vICMS><vICMSDeson>0.00</vICMSDeson><vFCP>0.00</vFCP><vBCST>0.00</vBCST><vST>0.00</vST><vFCPST>0.00</vFCPST><vFCPSTRet>0.00</vFCPSTRet><vProd>60.00</vProd><vFrete>0.00</vFrete><vSeg>0.00</vSeg><vDesc>0.00</vDesc><vII>0.00</vII><vIPI>0.00</vIPI><vIPIDevol>0.00</vIPIDevol><vPIS>0.00</vPIS><vCOFINS>0.00</vCOFINS><vOutro>0.00</vOutro><vNF>60.00</vNF></ICMSTot></total><transp><modFrete>9</modFrete></transp><pag><detPag><indPag>0</indPag><tPag>20</tPag><vPag>60.00</vPag></detPag></pag><infAdic><infCpl>PDV A&#xE7;aiteria</infCpl></infAdic></infNFe><infNFeSupl><qrCode>https://sistemas.sefaz.am.gov.br/nfceweb-hom/consultarNFCe.jsp?p=13260359598453000104650010000000241548893258|2|2|2|E947A625DF19978B123904A4E701FABAB06424B0</qrCode><urlChave>www.sefaz.am.gov.br/nfce/consulta</urlChave></infNFeSupl><Signature xmlns=\"http://www.w3.org/2000/09/xmldsig#\"><SignedInfo><CanonicalizationMethod Algorithm=\"http://www.w3.org/TR/2001/REC-xml-c14n-20010315\"/><SignatureMethod Algorithm=\"http://www.w3.org/2000/09/xmldsig#rsa-sha1\"/><Reference URI=\"#NFe13260359598453000104650010000000241548893258\"><Transforms><Transform Algorithm=\"http://www.w3.org/2000/09/xmldsig#enveloped-signature\"/><Transform Algorithm=\"http://www.w3.org/TR/2001/REC-xml-c14n-20010315\"/></Transforms><DigestMethod Algorithm=\"http://www.w3.org/2000/09/xmldsig#sha1\"/><DigestValue>2DBx0MTyBthkSpEJNTSPvSFbXPI=</DigestValue></Reference></SignedInfo><SignatureValue>Mc9sTVQSlAH25Ih97PfJW4UFxDXgrqltSRqiOdbnSWZuR179d0hXBdwitZ8ZB9b8neEp9UDTsMgV90u3hTMo/9BAS7kvyPftW25HLFEadTIqAB8sd458oKgwxur51AZvSyffyLIyj4MfXdohxoixBMHcLaK/L2FzAvtlmCHzi2NnIxkbHf5bNKTck9xzZwwHuLsulbTsLXd7w4Xdps9aIxWV7S+ykEPjNWWgE7DMaoypyBRRF/8BWSDsCpUdFl5cXZC2ZDygjQlHq+7jmkC6zxFsHGCl+zVjVgd24213iX6GIBDQFNdRiIzhwNRHc8F+5SbMjWkMroNLMHvZQdWR/g==</SignatureValue><KeyInfo><X509Data><X509Certificate>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</X509Certificate></X509Data></KeyInfo></Signature></NFe>', '<?xml version=\'1.0\' encoding=\'utf-8\'?><soapenv:Envelope xmlns:soapenv=\"http://www.w3.org/2003/05/soap-envelope\"><soapenv:Body><nfeResultMsg xmlns=\"http://www.portalfiscal.inf.br/nfe/wsdl/NFeAutorizacao4\"><retEnviNFe xmlns=\"http://www.portalfiscal.inf.br/nfe\" versao=\"4.00\"><tpAmb>2</tpAmb><verAplic>AM3.10-4.00</verAplic><cStat>104</cStat><xMotivo>Lote processado</xMotivo><cUF>13</cUF><dhRecbto>2026-03-12T09:01:47-04:00</dhRecbto><protNFe versao=\"4.00\"><infProt><tpAmb>2</tpAmb><verAplic>AM3.10-4.00</verAplic><chNFe>13260359598453000104650010000000241548893258</chNFe><dhRecbto>2026-03-12T09:01:47-04:00</dhRecbto><cStat>373</cStat><xMotivo>Rejeicao: Descricao do primeiro item diferente de NOTA FISCAL EMITIDA EM AMBIENTE DE HOMOLOGACAO - SEM VALOR FISCAL</xMotivo></infProt></protNFe></retEnviNFe></nfeResultMsg></soapenv:Body></soapenv:Envelope>', 60.00, 0.00, '{\"tPag\":\"20\"}', '2026-03-12 13:01:47'),
(12, '125', 91, 2, 1, 25, '13260359598453000104650010000000251688857553', NULL, '104', 'Lote processado', '<?xml version=\"1.0\" encoding=\"UTF-8\"?><nfeProc xmlns=\"http://www.portalfiscal.inf.br/nfe\" versao=\"4.00\"><NFe xmlns=\"http://www.portalfiscal.inf.br/nfe\"><infNFe Id=\"NFe13260359598453000104650010000000251688857553\" versao=\"4.00\"><ide><cUF>13</cUF><cNF>68885755</cNF><natOp>VENDA</natOp><mod>65</mod><serie>1</serie><nNF>25</nNF><dhEmi>2026-03-12T09:02:04-04:00</dhEmi><tpNF>1</tpNF><idDest>1</idDest><cMunFG>1301209</cMunFG><tpImp>4</tpImp><tpEmis>1</tpEmis><cDV>3</cDV><tpAmb>2</tpAmb><finNFe>1</finNFe><indFinal>1</indFinal><indPres>1</indPres><procEmi>0</procEmi><verProc>PDV-ACAI-1.0</verProc></ide><emit><CNPJ>59598453000104</CNPJ><xNome>PAPAGAIO COMERCIO DE MOTOS LTDA</xNome><xFant>PAPAGAIO MOTOS</xFant><enderEmit><xLgr>PADRE VICENTE NOGUEIRA</xLgr><nro>149</nro><xBairro>ITAMARATI</xBairro><cMun>1301209</cMun><xMun>COARI</xMun><UF>AM</UF><CEP>69460000</CEP><cPais>1058</cPais><xPais>Brasil</xPais><fone>9791979595</fone></enderEmit><IE>054756448</IE><CRT>3</CRT></emit><det nItem=\"1\"><prod><cProd>1</cProd><cEAN>SEM GTIN</cEAN><xProd>Buzina 12v c100</xProd><NCM>85123000</NCM><CFOP>5102</CFOP><uCom>UN</uCom><qCom>1.000</qCom><vUnCom>60.00</vUnCom><vProd>60.00</vProd><cEANTrib>SEM GTIN</cEANTrib><uTrib>UN</uTrib><qTrib>1.000</qTrib><vUnTrib>60.00</vUnTrib><indTot>1</indTot></prod><imposto><ICMS><ICMSSN102><orig>0</orig><CSOSN>102</CSOSN></ICMSSN102></ICMS><PIS><PISNT><CST>07</CST></PISNT></PIS><COFINS><COFINSNT><CST>07</CST></COFINSNT></COFINS></imposto></det><total><ICMSTot><vBC>0.00</vBC><vICMS>0.00</vICMS><vICMSDeson>0.00</vICMSDeson><vFCP>0.00</vFCP><vBCST>0.00</vBCST><vST>0.00</vST><vFCPST>0.00</vFCPST><vFCPSTRet>0.00</vFCPSTRet><vProd>60.00</vProd><vFrete>0.00</vFrete><vSeg>0.00</vSeg><vDesc>0.00</vDesc><vII>0.00</vII><vIPI>0.00</vIPI><vIPIDevol>0.00</vIPIDevol><vPIS>0.00</vPIS><vCOFINS>0.00</vCOFINS><vOutro>0.00</vOutro><vNF>60.00</vNF></ICMSTot></total><transp><modFrete>9</modFrete></transp><pag><detPag><indPag>0</indPag><tPag>15</tPag><vPag>60.00</vPag></detPag></pag><infAdic><infCpl>PDV A&#xE7;aiteria</infCpl></infAdic></infNFe><infNFeSupl><qrCode>https://sistemas.sefaz.am.gov.br/nfceweb-hom/consultarNFCe.jsp?p=13260359598453000104650010000000251688857553|2|2|2|5A048FD80F1BD7776A880E80A6E157CF65B3A01D</qrCode><urlChave>www.sefaz.am.gov.br/nfce/consulta</urlChave></infNFeSupl><Signature xmlns=\"http://www.w3.org/2000/09/xmldsig#\"><SignedInfo><CanonicalizationMethod Algorithm=\"http://www.w3.org/TR/2001/REC-xml-c14n-20010315\"/><SignatureMethod Algorithm=\"http://www.w3.org/2000/09/xmldsig#rsa-sha1\"/><Reference URI=\"#NFe13260359598453000104650010000000251688857553\"><Transforms><Transform Algorithm=\"http://www.w3.org/2000/09/xmldsig#enveloped-signature\"/><Transform Algorithm=\"http://www.w3.org/TR/2001/REC-xml-c14n-20010315\"/></Transforms><DigestMethod Algorithm=\"http://www.w3.org/2000/09/xmldsig#sha1\"/><DigestValue>Zewq3aZbgXl1m5MaJ6KTn3T2hpQ=</DigestValue></Reference></SignedInfo><SignatureValue>P3or1Pzlhz5MRtgla7cC5P4C8UNtyKAnfcuzaGzTDAZfWgasOuWX8whnvqCYCq3jsnpBlGW/5m6gyiXDZqo16sfAXBUpu6zHhyqX9wjkEliEL/edNed8OR35C6NJZOUdn9RonNJaEstPTvA/nR6q4w6uZLphmFPlyGBMH6QVN1133VE8ftaPlvfQ/qkCqF37pP0ftQnU1jaFJU9IyRFSvy0tF1c0elftra90peOeTfTwAHmq4IZd8H8YIoEveLXRhWo6vvHmKkMk+OcCnajRry94CNcXGCSZg81L54rD0Zg5IZ1NKLXGNoruRo5LiMvv9aileLXc4gSQeSeWF6H00g==</SignatureValue><KeyInfo><X509Data><X509Certificate>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</X509Certificate></X509Data></KeyInfo></Signature></NFe><protNFe versao=\"4.00\"><infProt><tpAmb>2</tpAmb><verAplic>AM3.10-4.00</verAplic><chNFe>13260359598453000104650010000000251688857553</chNFe><dhRecbto>2026-03-12T09:02:05-04:00</dhRecbto><cStat>373</cStat><xMotivo>Rejeicao: Descricao do primeiro item diferente de NOTA FISCAL EMITIDA EM AMBIENTE DE HOMOLOGACAO - SEM VALOR FISCAL</xMotivo></infProt></protNFe></nfeProc>', '<?xml version=\"1.0\"?><NFe xmlns=\"http://www.portalfiscal.inf.br/nfe\"><infNFe Id=\"NFe13260359598453000104650010000000251688857553\" versao=\"4.00\"><ide><cUF>13</cUF><cNF>68885755</cNF><natOp>VENDA</natOp><mod>65</mod><serie>1</serie><nNF>25</nNF><dhEmi>2026-03-12T09:02:04-04:00</dhEmi><tpNF>1</tpNF><idDest>1</idDest><cMunFG>1301209</cMunFG><tpImp>4</tpImp><tpEmis>1</tpEmis><cDV>3</cDV><tpAmb>2</tpAmb><finNFe>1</finNFe><indFinal>1</indFinal><indPres>1</indPres><procEmi>0</procEmi><verProc>PDV-ACAI-1.0</verProc></ide><emit><CNPJ>59598453000104</CNPJ><xNome>PAPAGAIO COMERCIO DE MOTOS LTDA</xNome><xFant>PAPAGAIO MOTOS</xFant><enderEmit><xLgr>PADRE VICENTE NOGUEIRA</xLgr><nro>149</nro><xBairro>ITAMARATI</xBairro><cMun>1301209</cMun><xMun>COARI</xMun><UF>AM</UF><CEP>69460000</CEP><cPais>1058</cPais><xPais>Brasil</xPais><fone>9791979595</fone></enderEmit><IE>054756448</IE><CRT>3</CRT></emit><det nItem=\"1\"><prod><cProd>1</cProd><cEAN>SEM GTIN</cEAN><xProd>Buzina 12v c100</xProd><NCM>85123000</NCM><CFOP>5102</CFOP><uCom>UN</uCom><qCom>1.000</qCom><vUnCom>60.00</vUnCom><vProd>60.00</vProd><cEANTrib>SEM GTIN</cEANTrib><uTrib>UN</uTrib><qTrib>1.000</qTrib><vUnTrib>60.00</vUnTrib><indTot>1</indTot></prod><imposto><ICMS><ICMSSN102><orig>0</orig><CSOSN>102</CSOSN></ICMSSN102></ICMS><PIS><PISNT><CST>07</CST></PISNT></PIS><COFINS><COFINSNT><CST>07</CST></COFINSNT></COFINS></imposto></det><total><ICMSTot><vBC>0.00</vBC><vICMS>0.00</vICMS><vICMSDeson>0.00</vICMSDeson><vFCP>0.00</vFCP><vBCST>0.00</vBCST><vST>0.00</vST><vFCPST>0.00</vFCPST><vFCPSTRet>0.00</vFCPSTRet><vProd>60.00</vProd><vFrete>0.00</vFrete><vSeg>0.00</vSeg><vDesc>0.00</vDesc><vII>0.00</vII><vIPI>0.00</vIPI><vIPIDevol>0.00</vIPIDevol><vPIS>0.00</vPIS><vCOFINS>0.00</vCOFINS><vOutro>0.00</vOutro><vNF>60.00</vNF></ICMSTot></total><transp><modFrete>9</modFrete></transp><pag><detPag><indPag>0</indPag><tPag>15</tPag><vPag>60.00</vPag></detPag></pag><infAdic><infCpl>PDV A&#xE7;aiteria</infCpl></infAdic></infNFe><infNFeSupl><qrCode>https://sistemas.sefaz.am.gov.br/nfceweb-hom/consultarNFCe.jsp?p=13260359598453000104650010000000251688857553|2|2|2|5A048FD80F1BD7776A880E80A6E157CF65B3A01D</qrCode><urlChave>www.sefaz.am.gov.br/nfce/consulta</urlChave></infNFeSupl><Signature xmlns=\"http://www.w3.org/2000/09/xmldsig#\"><SignedInfo><CanonicalizationMethod Algorithm=\"http://www.w3.org/TR/2001/REC-xml-c14n-20010315\"/><SignatureMethod Algorithm=\"http://www.w3.org/2000/09/xmldsig#rsa-sha1\"/><Reference URI=\"#NFe13260359598453000104650010000000251688857553\"><Transforms><Transform Algorithm=\"http://www.w3.org/2000/09/xmldsig#enveloped-signature\"/><Transform Algorithm=\"http://www.w3.org/TR/2001/REC-xml-c14n-20010315\"/></Transforms><DigestMethod Algorithm=\"http://www.w3.org/2000/09/xmldsig#sha1\"/><DigestValue>Zewq3aZbgXl1m5MaJ6KTn3T2hpQ=</DigestValue></Reference></SignedInfo><SignatureValue>P3or1Pzlhz5MRtgla7cC5P4C8UNtyKAnfcuzaGzTDAZfWgasOuWX8whnvqCYCq3jsnpBlGW/5m6gyiXDZqo16sfAXBUpu6zHhyqX9wjkEliEL/edNed8OR35C6NJZOUdn9RonNJaEstPTvA/nR6q4w6uZLphmFPlyGBMH6QVN1133VE8ftaPlvfQ/qkCqF37pP0ftQnU1jaFJU9IyRFSvy0tF1c0elftra90peOeTfTwAHmq4IZd8H8YIoEveLXRhWo6vvHmKkMk+OcCnajRry94CNcXGCSZg81L54rD0Zg5IZ1NKLXGNoruRo5LiMvv9aileLXc4gSQeSeWF6H00g==</SignatureValue><KeyInfo><X509Data><X509Certificate>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</X509Certificate></X509Data></KeyInfo></Signature></NFe>', '<?xml version=\'1.0\' encoding=\'utf-8\'?><soapenv:Envelope xmlns:soapenv=\"http://www.w3.org/2003/05/soap-envelope\"><soapenv:Body><nfeResultMsg xmlns=\"http://www.portalfiscal.inf.br/nfe/wsdl/NFeAutorizacao4\"><retEnviNFe xmlns=\"http://www.portalfiscal.inf.br/nfe\" versao=\"4.00\"><tpAmb>2</tpAmb><verAplic>AM3.10-4.00</verAplic><cStat>104</cStat><xMotivo>Lote processado</xMotivo><cUF>13</cUF><dhRecbto>2026-03-12T09:02:05-04:00</dhRecbto><protNFe versao=\"4.00\"><infProt><tpAmb>2</tpAmb><verAplic>AM3.10-4.00</verAplic><chNFe>13260359598453000104650010000000251688857553</chNFe><dhRecbto>2026-03-12T09:02:05-04:00</dhRecbto><cStat>373</cStat><xMotivo>Rejeicao: Descricao do primeiro item diferente de NOTA FISCAL EMITIDA EM AMBIENTE DE HOMOLOGACAO - SEM VALOR FISCAL</xMotivo></infProt></protNFe></retEnviNFe></nfeResultMsg></soapenv:Body></soapenv:Envelope>', 60.00, 0.00, '{\"tPag\":\"15\"}', '2026-03-12 13:02:05');
INSERT INTO `nfce_emitidas` (`id`, `empresa_id`, `venda_id`, `ambiente`, `serie`, `numero`, `chave`, `protocolo`, `status_sefaz`, `mensagem`, `xml_nfeproc`, `xml_envio`, `xml_retorno`, `valor_total`, `valor_troco`, `tpag_json`, `created_at`) VALUES
(13, '125', 92, 2, 1, 26, '13260359598453000104650010000000261969279119', NULL, '104', 'Lote processado', '<?xml version=\"1.0\" encoding=\"UTF-8\"?><nfeProc xmlns=\"http://www.portalfiscal.inf.br/nfe\" versao=\"4.00\"><NFe xmlns=\"http://www.portalfiscal.inf.br/nfe\"><infNFe Id=\"NFe13260359598453000104650010000000261969279119\" versao=\"4.00\"><ide><cUF>13</cUF><cNF>96927911</cNF><natOp>VENDA</natOp><mod>65</mod><serie>1</serie><nNF>26</nNF><dhEmi>2026-03-12T09:02:47-04:00</dhEmi><tpNF>1</tpNF><idDest>1</idDest><cMunFG>1301209</cMunFG><tpImp>4</tpImp><tpEmis>1</tpEmis><cDV>9</cDV><tpAmb>2</tpAmb><finNFe>1</finNFe><indFinal>1</indFinal><indPres>1</indPres><procEmi>0</procEmi><verProc>PDV-ACAI-1.0</verProc></ide><emit><CNPJ>59598453000104</CNPJ><xNome>PAPAGAIO COMERCIO DE MOTOS LTDA</xNome><xFant>PAPAGAIO MOTOS</xFant><enderEmit><xLgr>PADRE VICENTE NOGUEIRA</xLgr><nro>149</nro><xBairro>ITAMARATI</xBairro><cMun>1301209</cMun><xMun>COARI</xMun><UF>AM</UF><CEP>69460000</CEP><cPais>1058</cPais><xPais>Brasil</xPais><fone>9791979595</fone></enderEmit><IE>054756448</IE><CRT>3</CRT></emit><det nItem=\"1\"><prod><cProd>1</cProd><cEAN>SEM GTIN</cEAN><xProd>Buzina 12v c100</xProd><NCM>85123000</NCM><CFOP>5102</CFOP><uCom>UN</uCom><qCom>1.000</qCom><vUnCom>60.00</vUnCom><vProd>60.00</vProd><cEANTrib>SEM GTIN</cEANTrib><uTrib>UN</uTrib><qTrib>1.000</qTrib><vUnTrib>60.00</vUnTrib><indTot>1</indTot></prod><imposto><ICMS><ICMSSN102><orig>0</orig><CSOSN>102</CSOSN></ICMSSN102></ICMS><PIS><PISNT><CST>07</CST></PISNT></PIS><COFINS><COFINSNT><CST>07</CST></COFINSNT></COFINS></imposto></det><total><ICMSTot><vBC>0.00</vBC><vICMS>0.00</vICMS><vICMSDeson>0.00</vICMSDeson><vFCP>0.00</vFCP><vBCST>0.00</vBCST><vST>0.00</vST><vFCPST>0.00</vFCPST><vFCPSTRet>0.00</vFCPSTRet><vProd>60.00</vProd><vFrete>0.00</vFrete><vSeg>0.00</vSeg><vDesc>0.00</vDesc><vII>0.00</vII><vIPI>0.00</vIPI><vIPIDevol>0.00</vIPIDevol><vPIS>0.00</vPIS><vCOFINS>0.00</vCOFINS><vOutro>0.00</vOutro><vNF>60.00</vNF></ICMSTot></total><transp><modFrete>9</modFrete></transp><pag><detPag><indPag>0</indPag><tPag>01</tPag><vPag>60.00</vPag></detPag></pag><infAdic><infCpl>PDV A&#xE7;aiteria</infCpl></infAdic></infNFe><infNFeSupl><qrCode>https://sistemas.sefaz.am.gov.br/nfceweb-hom/consultarNFCe.jsp?p=13260359598453000104650010000000261969279119|2|2|2|4F021F4B7DD6035A93551865ACCAB8DF8DA5DA14</qrCode><urlChave>www.sefaz.am.gov.br/nfce/consulta</urlChave></infNFeSupl><Signature xmlns=\"http://www.w3.org/2000/09/xmldsig#\"><SignedInfo><CanonicalizationMethod Algorithm=\"http://www.w3.org/TR/2001/REC-xml-c14n-20010315\"/><SignatureMethod Algorithm=\"http://www.w3.org/2000/09/xmldsig#rsa-sha1\"/><Reference URI=\"#NFe13260359598453000104650010000000261969279119\"><Transforms><Transform Algorithm=\"http://www.w3.org/2000/09/xmldsig#enveloped-signature\"/><Transform Algorithm=\"http://www.w3.org/TR/2001/REC-xml-c14n-20010315\"/></Transforms><DigestMethod Algorithm=\"http://www.w3.org/2000/09/xmldsig#sha1\"/><DigestValue>73jX4ALh5eqHGT/M+pvODfHk9Js=</DigestValue></Reference></SignedInfo><SignatureValue>dF2gTqB0h9/NVyYb6bd60Wg6KWi0Gi1xWaL2+pJ46cA6FNPUgAJAR4FF7wTB3h17tTorxJ+eRociExrTEryLL4WDsGNMxSIZTCgCgCDg5+arYSy/roaa+ClB44HHmKFhjX8mjD9fgFREnuRSneToqd6xvm+Aj0fEzO3pLW6b0NrdNtWOYLg+FxeXQ7gtD/pqxTULfGOw9z9pfvksjR4H8Y9qG9a1T/D2ZJtrxjh6YmJb46Vw0hJfVkIsDMsEzzOiGhPAvtDc8IdCdYVKKTUmOnT1lQu/icceGgjLGV5Boq5V/Xg9YglHIjReG9p3fC9hhR/RN5Kwex1zsI2UUTV00A==</SignatureValue><KeyInfo><X509Data><X509Certificate>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</X509Certificate></X509Data></KeyInfo></Signature></NFe><protNFe versao=\"4.00\"><infProt><tpAmb>2</tpAmb><verAplic>AM3.10-4.00</verAplic><chNFe>13260359598453000104650010000000261969279119</chNFe><dhRecbto>2026-03-12T09:02:48-04:00</dhRecbto><cStat>373</cStat><xMotivo>Rejeicao: Descricao do primeiro item diferente de NOTA FISCAL EMITIDA EM AMBIENTE DE HOMOLOGACAO - SEM VALOR FISCAL</xMotivo></infProt></protNFe></nfeProc>', '<?xml version=\"1.0\"?><NFe xmlns=\"http://www.portalfiscal.inf.br/nfe\"><infNFe Id=\"NFe13260359598453000104650010000000261969279119\" versao=\"4.00\"><ide><cUF>13</cUF><cNF>96927911</cNF><natOp>VENDA</natOp><mod>65</mod><serie>1</serie><nNF>26</nNF><dhEmi>2026-03-12T09:02:47-04:00</dhEmi><tpNF>1</tpNF><idDest>1</idDest><cMunFG>1301209</cMunFG><tpImp>4</tpImp><tpEmis>1</tpEmis><cDV>9</cDV><tpAmb>2</tpAmb><finNFe>1</finNFe><indFinal>1</indFinal><indPres>1</indPres><procEmi>0</procEmi><verProc>PDV-ACAI-1.0</verProc></ide><emit><CNPJ>59598453000104</CNPJ><xNome>PAPAGAIO COMERCIO DE MOTOS LTDA</xNome><xFant>PAPAGAIO MOTOS</xFant><enderEmit><xLgr>PADRE VICENTE NOGUEIRA</xLgr><nro>149</nro><xBairro>ITAMARATI</xBairro><cMun>1301209</cMun><xMun>COARI</xMun><UF>AM</UF><CEP>69460000</CEP><cPais>1058</cPais><xPais>Brasil</xPais><fone>9791979595</fone></enderEmit><IE>054756448</IE><CRT>3</CRT></emit><det nItem=\"1\"><prod><cProd>1</cProd><cEAN>SEM GTIN</cEAN><xProd>Buzina 12v c100</xProd><NCM>85123000</NCM><CFOP>5102</CFOP><uCom>UN</uCom><qCom>1.000</qCom><vUnCom>60.00</vUnCom><vProd>60.00</vProd><cEANTrib>SEM GTIN</cEANTrib><uTrib>UN</uTrib><qTrib>1.000</qTrib><vUnTrib>60.00</vUnTrib><indTot>1</indTot></prod><imposto><ICMS><ICMSSN102><orig>0</orig><CSOSN>102</CSOSN></ICMSSN102></ICMS><PIS><PISNT><CST>07</CST></PISNT></PIS><COFINS><COFINSNT><CST>07</CST></COFINSNT></COFINS></imposto></det><total><ICMSTot><vBC>0.00</vBC><vICMS>0.00</vICMS><vICMSDeson>0.00</vICMSDeson><vFCP>0.00</vFCP><vBCST>0.00</vBCST><vST>0.00</vST><vFCPST>0.00</vFCPST><vFCPSTRet>0.00</vFCPSTRet><vProd>60.00</vProd><vFrete>0.00</vFrete><vSeg>0.00</vSeg><vDesc>0.00</vDesc><vII>0.00</vII><vIPI>0.00</vIPI><vIPIDevol>0.00</vIPIDevol><vPIS>0.00</vPIS><vCOFINS>0.00</vCOFINS><vOutro>0.00</vOutro><vNF>60.00</vNF></ICMSTot></total><transp><modFrete>9</modFrete></transp><pag><detPag><indPag>0</indPag><tPag>01</tPag><vPag>60.00</vPag></detPag></pag><infAdic><infCpl>PDV A&#xE7;aiteria</infCpl></infAdic></infNFe><infNFeSupl><qrCode>https://sistemas.sefaz.am.gov.br/nfceweb-hom/consultarNFCe.jsp?p=13260359598453000104650010000000261969279119|2|2|2|4F021F4B7DD6035A93551865ACCAB8DF8DA5DA14</qrCode><urlChave>www.sefaz.am.gov.br/nfce/consulta</urlChave></infNFeSupl><Signature xmlns=\"http://www.w3.org/2000/09/xmldsig#\"><SignedInfo><CanonicalizationMethod Algorithm=\"http://www.w3.org/TR/2001/REC-xml-c14n-20010315\"/><SignatureMethod Algorithm=\"http://www.w3.org/2000/09/xmldsig#rsa-sha1\"/><Reference URI=\"#NFe13260359598453000104650010000000261969279119\"><Transforms><Transform Algorithm=\"http://www.w3.org/2000/09/xmldsig#enveloped-signature\"/><Transform Algorithm=\"http://www.w3.org/TR/2001/REC-xml-c14n-20010315\"/></Transforms><DigestMethod Algorithm=\"http://www.w3.org/2000/09/xmldsig#sha1\"/><DigestValue>73jX4ALh5eqHGT/M+pvODfHk9Js=</DigestValue></Reference></SignedInfo><SignatureValue>dF2gTqB0h9/NVyYb6bd60Wg6KWi0Gi1xWaL2+pJ46cA6FNPUgAJAR4FF7wTB3h17tTorxJ+eRociExrTEryLL4WDsGNMxSIZTCgCgCDg5+arYSy/roaa+ClB44HHmKFhjX8mjD9fgFREnuRSneToqd6xvm+Aj0fEzO3pLW6b0NrdNtWOYLg+FxeXQ7gtD/pqxTULfGOw9z9pfvksjR4H8Y9qG9a1T/D2ZJtrxjh6YmJb46Vw0hJfVkIsDMsEzzOiGhPAvtDc8IdCdYVKKTUmOnT1lQu/icceGgjLGV5Boq5V/Xg9YglHIjReG9p3fC9hhR/RN5Kwex1zsI2UUTV00A==</SignatureValue><KeyInfo><X509Data><X509Certificate>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</X509Certificate></X509Data></KeyInfo></Signature></NFe>', '<?xml version=\'1.0\' encoding=\'utf-8\'?><soapenv:Envelope xmlns:soapenv=\"http://www.w3.org/2003/05/soap-envelope\"><soapenv:Body><nfeResultMsg xmlns=\"http://www.portalfiscal.inf.br/nfe/wsdl/NFeAutorizacao4\"><retEnviNFe xmlns=\"http://www.portalfiscal.inf.br/nfe\" versao=\"4.00\"><tpAmb>2</tpAmb><verAplic>AM3.10-4.00</verAplic><cStat>104</cStat><xMotivo>Lote processado</xMotivo><cUF>13</cUF><dhRecbto>2026-03-12T09:02:48-04:00</dhRecbto><protNFe versao=\"4.00\"><infProt><tpAmb>2</tpAmb><verAplic>AM3.10-4.00</verAplic><chNFe>13260359598453000104650010000000261969279119</chNFe><dhRecbto>2026-03-12T09:02:48-04:00</dhRecbto><cStat>373</cStat><xMotivo>Rejeicao: Descricao do primeiro item diferente de NOTA FISCAL EMITIDA EM AMBIENTE DE HOMOLOGACAO - SEM VALOR FISCAL</xMotivo></infProt></protNFe></retEnviNFe></nfeResultMsg></soapenv:Body></soapenv:Envelope>', 60.00, 0.00, '{\"tPag\":\"01\"}', '2026-03-12 13:02:48'),
(14, '125', 93, 2, 1, 27, '13260359598453000104650010000000271590663305', NULL, '104', 'Lote processado', '<?xml version=\"1.0\" encoding=\"UTF-8\"?><nfeProc xmlns=\"http://www.portalfiscal.inf.br/nfe\" versao=\"4.00\"><NFe xmlns=\"http://www.portalfiscal.inf.br/nfe\"><infNFe Id=\"NFe13260359598453000104650010000000271590663305\" versao=\"4.00\"><ide><cUF>13</cUF><cNF>59066330</cNF><natOp>VENDA</natOp><mod>65</mod><serie>1</serie><nNF>27</nNF><dhEmi>2026-03-12T09:11:02-04:00</dhEmi><tpNF>1</tpNF><idDest>1</idDest><cMunFG>1301209</cMunFG><tpImp>4</tpImp><tpEmis>1</tpEmis><cDV>5</cDV><tpAmb>2</tpAmb><finNFe>1</finNFe><indFinal>1</indFinal><indPres>1</indPres><procEmi>0</procEmi><verProc>PDV-ACAI-1.0</verProc></ide><emit><CNPJ>59598453000104</CNPJ><xNome>PAPAGAIO COMERCIO DE MOTOS LTDA</xNome><xFant>PAPAGAIO MOTOS</xFant><enderEmit><xLgr>PADRE VICENTE NOGUEIRA</xLgr><nro>149</nro><xBairro>ITAMARATI</xBairro><cMun>1301209</cMun><xMun>COARI</xMun><UF>AM</UF><CEP>69460000</CEP><cPais>1058</cPais><xPais>Brasil</xPais><fone>9791979595</fone></enderEmit><IE>054756448</IE><CRT>3</CRT></emit><det nItem=\"1\"><prod><cProd>1</cProd><cEAN>SEM GTIN</cEAN><xProd>Buzina 12v c100</xProd><NCM>85123000</NCM><CFOP>5102</CFOP><uCom>UN</uCom><qCom>1.000</qCom><vUnCom>60.00</vUnCom><vProd>60.00</vProd><cEANTrib>SEM GTIN</cEANTrib><uTrib>UN</uTrib><qTrib>1.000</qTrib><vUnTrib>60.00</vUnTrib><indTot>1</indTot></prod><imposto><ICMS><ICMSSN102><orig>0</orig><CSOSN>102</CSOSN></ICMSSN102></ICMS><PIS><PISNT><CST>07</CST></PISNT></PIS><COFINS><COFINSNT><CST>07</CST></COFINSNT></COFINS></imposto></det><total><ICMSTot><vBC>0.00</vBC><vICMS>0.00</vICMS><vICMSDeson>0.00</vICMSDeson><vFCP>0.00</vFCP><vBCST>0.00</vBCST><vST>0.00</vST><vFCPST>0.00</vFCPST><vFCPSTRet>0.00</vFCPSTRet><vProd>60.00</vProd><vFrete>0.00</vFrete><vSeg>0.00</vSeg><vDesc>0.00</vDesc><vII>0.00</vII><vIPI>0.00</vIPI><vIPIDevol>0.00</vIPIDevol><vPIS>0.00</vPIS><vCOFINS>0.00</vCOFINS><vOutro>0.00</vOutro><vNF>60.00</vNF></ICMSTot></total><transp><modFrete>9</modFrete></transp><pag><detPag><indPag>0</indPag><tPag>20</tPag><vPag>60.00</vPag></detPag></pag><infAdic><infCpl>PDV A&#xE7;aiteria</infCpl></infAdic></infNFe><infNFeSupl><qrCode>https://sistemas.sefaz.am.gov.br/nfceweb-hom/consultarNFCe.jsp?p=13260359598453000104650010000000271590663305|2|2|2|AAF28FF855703B4CF9A2B511588B8D4850048FAB</qrCode><urlChave>www.sefaz.am.gov.br/nfce/consulta</urlChave></infNFeSupl><Signature xmlns=\"http://www.w3.org/2000/09/xmldsig#\"><SignedInfo><CanonicalizationMethod Algorithm=\"http://www.w3.org/TR/2001/REC-xml-c14n-20010315\"/><SignatureMethod Algorithm=\"http://www.w3.org/2000/09/xmldsig#rsa-sha1\"/><Reference URI=\"#NFe13260359598453000104650010000000271590663305\"><Transforms><Transform Algorithm=\"http://www.w3.org/2000/09/xmldsig#enveloped-signature\"/><Transform Algorithm=\"http://www.w3.org/TR/2001/REC-xml-c14n-20010315\"/></Transforms><DigestMethod Algorithm=\"http://www.w3.org/2000/09/xmldsig#sha1\"/><DigestValue>/Oiua133jfsiMMf/yRlgxemMdNM=</DigestValue></Reference></SignedInfo><SignatureValue>fTGywWiRt83fee5JNrj959kJ7/js+1di4QUORvNcEmbxr2QXxKgoUfmOPa5OIv7/6M2ki/FcQP10oHzcIBBuzqPwpRASaJgXluwUpX2iDzpQ1QGBpbTs/BouiTpQ9rACGh4ieTBaeB43g7ElqStkXaHE+anCNMbbOjVOpLyvvBlNn2vffRKWlxo0G/KFBPSJV66/boZPR/RmNZSYTuFcOZR1xb/UU8gKWcfv0QELyOWniuoGX3c8ErHeT2FRz6m1zv+ytMSzwosE8a3TCwKGAZEZ8s258Pi1Ge5F+h86C4gmaNNIDc7SDf+hzz0/M/cSKZyejhhv1w71W/vzm41Kig==</SignatureValue><KeyInfo><X509Data><X509Certificate>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</X509Certificate></X509Data></KeyInfo></Signature></NFe><protNFe versao=\"4.00\"><infProt><tpAmb>2</tpAmb><verAplic>AM3.10-4.00</verAplic><chNFe>13260359598453000104650010000000271590663305</chNFe><dhRecbto>2026-03-12T09:11:02-04:00</dhRecbto><cStat>373</cStat><xMotivo>Rejeicao: Descricao do primeiro item diferente de NOTA FISCAL EMITIDA EM AMBIENTE DE HOMOLOGACAO - SEM VALOR FISCAL</xMotivo></infProt></protNFe></nfeProc>', '<?xml version=\"1.0\"?><NFe xmlns=\"http://www.portalfiscal.inf.br/nfe\"><infNFe Id=\"NFe13260359598453000104650010000000271590663305\" versao=\"4.00\"><ide><cUF>13</cUF><cNF>59066330</cNF><natOp>VENDA</natOp><mod>65</mod><serie>1</serie><nNF>27</nNF><dhEmi>2026-03-12T09:11:02-04:00</dhEmi><tpNF>1</tpNF><idDest>1</idDest><cMunFG>1301209</cMunFG><tpImp>4</tpImp><tpEmis>1</tpEmis><cDV>5</cDV><tpAmb>2</tpAmb><finNFe>1</finNFe><indFinal>1</indFinal><indPres>1</indPres><procEmi>0</procEmi><verProc>PDV-ACAI-1.0</verProc></ide><emit><CNPJ>59598453000104</CNPJ><xNome>PAPAGAIO COMERCIO DE MOTOS LTDA</xNome><xFant>PAPAGAIO MOTOS</xFant><enderEmit><xLgr>PADRE VICENTE NOGUEIRA</xLgr><nro>149</nro><xBairro>ITAMARATI</xBairro><cMun>1301209</cMun><xMun>COARI</xMun><UF>AM</UF><CEP>69460000</CEP><cPais>1058</cPais><xPais>Brasil</xPais><fone>9791979595</fone></enderEmit><IE>054756448</IE><CRT>3</CRT></emit><det nItem=\"1\"><prod><cProd>1</cProd><cEAN>SEM GTIN</cEAN><xProd>Buzina 12v c100</xProd><NCM>85123000</NCM><CFOP>5102</CFOP><uCom>UN</uCom><qCom>1.000</qCom><vUnCom>60.00</vUnCom><vProd>60.00</vProd><cEANTrib>SEM GTIN</cEANTrib><uTrib>UN</uTrib><qTrib>1.000</qTrib><vUnTrib>60.00</vUnTrib><indTot>1</indTot></prod><imposto><ICMS><ICMSSN102><orig>0</orig><CSOSN>102</CSOSN></ICMSSN102></ICMS><PIS><PISNT><CST>07</CST></PISNT></PIS><COFINS><COFINSNT><CST>07</CST></COFINSNT></COFINS></imposto></det><total><ICMSTot><vBC>0.00</vBC><vICMS>0.00</vICMS><vICMSDeson>0.00</vICMSDeson><vFCP>0.00</vFCP><vBCST>0.00</vBCST><vST>0.00</vST><vFCPST>0.00</vFCPST><vFCPSTRet>0.00</vFCPSTRet><vProd>60.00</vProd><vFrete>0.00</vFrete><vSeg>0.00</vSeg><vDesc>0.00</vDesc><vII>0.00</vII><vIPI>0.00</vIPI><vIPIDevol>0.00</vIPIDevol><vPIS>0.00</vPIS><vCOFINS>0.00</vCOFINS><vOutro>0.00</vOutro><vNF>60.00</vNF></ICMSTot></total><transp><modFrete>9</modFrete></transp><pag><detPag><indPag>0</indPag><tPag>20</tPag><vPag>60.00</vPag></detPag></pag><infAdic><infCpl>PDV A&#xE7;aiteria</infCpl></infAdic></infNFe><infNFeSupl><qrCode>https://sistemas.sefaz.am.gov.br/nfceweb-hom/consultarNFCe.jsp?p=13260359598453000104650010000000271590663305|2|2|2|AAF28FF855703B4CF9A2B511588B8D4850048FAB</qrCode><urlChave>www.sefaz.am.gov.br/nfce/consulta</urlChave></infNFeSupl><Signature xmlns=\"http://www.w3.org/2000/09/xmldsig#\"><SignedInfo><CanonicalizationMethod Algorithm=\"http://www.w3.org/TR/2001/REC-xml-c14n-20010315\"/><SignatureMethod Algorithm=\"http://www.w3.org/2000/09/xmldsig#rsa-sha1\"/><Reference URI=\"#NFe13260359598453000104650010000000271590663305\"><Transforms><Transform Algorithm=\"http://www.w3.org/2000/09/xmldsig#enveloped-signature\"/><Transform Algorithm=\"http://www.w3.org/TR/2001/REC-xml-c14n-20010315\"/></Transforms><DigestMethod Algorithm=\"http://www.w3.org/2000/09/xmldsig#sha1\"/><DigestValue>/Oiua133jfsiMMf/yRlgxemMdNM=</DigestValue></Reference></SignedInfo><SignatureValue>fTGywWiRt83fee5JNrj959kJ7/js+1di4QUORvNcEmbxr2QXxKgoUfmOPa5OIv7/6M2ki/FcQP10oHzcIBBuzqPwpRASaJgXluwUpX2iDzpQ1QGBpbTs/BouiTpQ9rACGh4ieTBaeB43g7ElqStkXaHE+anCNMbbOjVOpLyvvBlNn2vffRKWlxo0G/KFBPSJV66/boZPR/RmNZSYTuFcOZR1xb/UU8gKWcfv0QELyOWniuoGX3c8ErHeT2FRz6m1zv+ytMSzwosE8a3TCwKGAZEZ8s258Pi1Ge5F+h86C4gmaNNIDc7SDf+hzz0/M/cSKZyejhhv1w71W/vzm41Kig==</SignatureValue><KeyInfo><X509Data><X509Certificate>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</X509Certificate></X509Data></KeyInfo></Signature></NFe>', '<?xml version=\'1.0\' encoding=\'utf-8\'?><soapenv:Envelope xmlns:soapenv=\"http://www.w3.org/2003/05/soap-envelope\"><soapenv:Body><nfeResultMsg xmlns=\"http://www.portalfiscal.inf.br/nfe/wsdl/NFeAutorizacao4\"><retEnviNFe xmlns=\"http://www.portalfiscal.inf.br/nfe\" versao=\"4.00\"><tpAmb>2</tpAmb><verAplic>AM3.10-4.00</verAplic><cStat>104</cStat><xMotivo>Lote processado</xMotivo><cUF>13</cUF><dhRecbto>2026-03-12T09:11:02-04:00</dhRecbto><protNFe versao=\"4.00\"><infProt><tpAmb>2</tpAmb><verAplic>AM3.10-4.00</verAplic><chNFe>13260359598453000104650010000000271590663305</chNFe><dhRecbto>2026-03-12T09:11:02-04:00</dhRecbto><cStat>373</cStat><xMotivo>Rejeicao: Descricao do primeiro item diferente de NOTA FISCAL EMITIDA EM AMBIENTE DE HOMOLOGACAO - SEM VALOR FISCAL</xMotivo></infProt></protNFe></retEnviNFe></nfeResultMsg></soapenv:Body></soapenv:Envelope>', 60.00, 0.00, '{\"tPag\":\"20\"}', '2026-03-12 13:11:02'),
(15, '125', 94, 2, 1, 28, '13260359598453000104650010000000281259337754', NULL, '104', 'Lote processado', '<?xml version=\"1.0\" encoding=\"UTF-8\"?><nfeProc xmlns=\"http://www.portalfiscal.inf.br/nfe\" versao=\"4.00\"><NFe xmlns=\"http://www.portalfiscal.inf.br/nfe\"><infNFe Id=\"NFe13260359598453000104650010000000281259337754\" versao=\"4.00\"><ide><cUF>13</cUF><cNF>25933775</cNF><natOp>VENDA</natOp><mod>65</mod><serie>1</serie><nNF>28</nNF><dhEmi>2026-03-12T09:22:13-04:00</dhEmi><tpNF>1</tpNF><idDest>1</idDest><cMunFG>1301209</cMunFG><tpImp>4</tpImp><tpEmis>1</tpEmis><cDV>4</cDV><tpAmb>2</tpAmb><finNFe>1</finNFe><indFinal>1</indFinal><indPres>1</indPres><procEmi>0</procEmi><verProc>PDV-ACAI-1.0</verProc></ide><emit><CNPJ>59598453000104</CNPJ><xNome>PAPAGAIO COMERCIO DE MOTOS LTDA</xNome><xFant>PAPAGAIO MOTOS</xFant><enderEmit><xLgr>PADRE VICENTE NOGUEIRA</xLgr><nro>149</nro><xBairro>ITAMARATI</xBairro><cMun>1301209</cMun><xMun>COARI</xMun><UF>AM</UF><CEP>69460000</CEP><cPais>1058</cPais><xPais>Brasil</xPais><fone>9791979595</fone></enderEmit><IE>054756448</IE><CRT>3</CRT></emit><det nItem=\"1\"><prod><cProd>1</cProd><cEAN>SEM GTIN</cEAN><xProd>Buzina 12v c100</xProd><NCM>85123000</NCM><CFOP>5102</CFOP><uCom>UN</uCom><qCom>1.000</qCom><vUnCom>60.00</vUnCom><vProd>60.00</vProd><cEANTrib>SEM GTIN</cEANTrib><uTrib>UN</uTrib><qTrib>1.000</qTrib><vUnTrib>60.00</vUnTrib><indTot>1</indTot></prod><imposto><ICMS><ICMSSN102><orig>0</orig><CSOSN>102</CSOSN></ICMSSN102></ICMS><PIS><PISNT><CST>07</CST></PISNT></PIS><COFINS><COFINSNT><CST>07</CST></COFINSNT></COFINS></imposto></det><total><ICMSTot><vBC>0.00</vBC><vICMS>0.00</vICMS><vICMSDeson>0.00</vICMSDeson><vFCP>0.00</vFCP><vBCST>0.00</vBCST><vST>0.00</vST><vFCPST>0.00</vFCPST><vFCPSTRet>0.00</vFCPSTRet><vProd>60.00</vProd><vFrete>0.00</vFrete><vSeg>0.00</vSeg><vDesc>0.00</vDesc><vII>0.00</vII><vIPI>0.00</vIPI><vIPIDevol>0.00</vIPIDevol><vPIS>0.00</vPIS><vCOFINS>0.00</vCOFINS><vOutro>0.00</vOutro><vNF>60.00</vNF></ICMSTot></total><transp><modFrete>9</modFrete></transp><pag><detPag><indPag>0</indPag><tPag>20</tPag><vPag>60.00</vPag></detPag></pag><infAdic><infCpl>PDV A&#xE7;aiteria</infCpl></infAdic></infNFe><infNFeSupl><qrCode>https://sistemas.sefaz.am.gov.br/nfceweb-hom/consultarNFCe.jsp?p=13260359598453000104650010000000281259337754|2|2|2|C8EE68EC7B5297D293DA32290DC75C097CE54D11</qrCode><urlChave>www.sefaz.am.gov.br/nfce/consulta</urlChave></infNFeSupl><Signature xmlns=\"http://www.w3.org/2000/09/xmldsig#\"><SignedInfo><CanonicalizationMethod Algorithm=\"http://www.w3.org/TR/2001/REC-xml-c14n-20010315\"/><SignatureMethod Algorithm=\"http://www.w3.org/2000/09/xmldsig#rsa-sha1\"/><Reference URI=\"#NFe13260359598453000104650010000000281259337754\"><Transforms><Transform Algorithm=\"http://www.w3.org/2000/09/xmldsig#enveloped-signature\"/><Transform Algorithm=\"http://www.w3.org/TR/2001/REC-xml-c14n-20010315\"/></Transforms><DigestMethod Algorithm=\"http://www.w3.org/2000/09/xmldsig#sha1\"/><DigestValue>80LKLe2DPcLtqRv5oVsDxALGyi4=</DigestValue></Reference></SignedInfo><SignatureValue>LgFPCJsfBrXOaktc+3VsGfx0NnasrwIKfE3qVGDv2ac/7+xc/VDD8h/hpDjK8hucjq9D8l1N27C470q+H1S9/zPdYpSDqFBf937k98FtRIad+Xw7ZtkMxRg2WGITL6m2y4qFWEgaB53Xekfv36QEc7sSIyyFo3behuoKpGl/pQ6Hrhds0NDM05hQDGILxDqVSPcIl694kpwpRmZGRpPH8cJuA1iPozB6CD2DBlKtua5jQarWogrDRsNzfveZWR3Nb0O6r3LoTKAwd3jBeES8xnCGae1dRLn9SLztd01lH3T+xXjMU/ZPRn8oiYVGlG/KeGWeyRsI52i2cLy6qQEPvA==</SignatureValue><KeyInfo><X509Data><X509Certificate>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</X509Certificate></X509Data></KeyInfo></Signature></NFe><protNFe versao=\"4.00\"><infProt><tpAmb>2</tpAmb><verAplic>AM3.10-4.00</verAplic><chNFe>13260359598453000104650010000000281259337754</chNFe><dhRecbto>2026-03-12T09:22:14-04:00</dhRecbto><cStat>373</cStat><xMotivo>Rejeicao: Descricao do primeiro item diferente de NOTA FISCAL EMITIDA EM AMBIENTE DE HOMOLOGACAO - SEM VALOR FISCAL</xMotivo></infProt></protNFe></nfeProc>', '<?xml version=\"1.0\"?><NFe xmlns=\"http://www.portalfiscal.inf.br/nfe\"><infNFe Id=\"NFe13260359598453000104650010000000281259337754\" versao=\"4.00\"><ide><cUF>13</cUF><cNF>25933775</cNF><natOp>VENDA</natOp><mod>65</mod><serie>1</serie><nNF>28</nNF><dhEmi>2026-03-12T09:22:13-04:00</dhEmi><tpNF>1</tpNF><idDest>1</idDest><cMunFG>1301209</cMunFG><tpImp>4</tpImp><tpEmis>1</tpEmis><cDV>4</cDV><tpAmb>2</tpAmb><finNFe>1</finNFe><indFinal>1</indFinal><indPres>1</indPres><procEmi>0</procEmi><verProc>PDV-ACAI-1.0</verProc></ide><emit><CNPJ>59598453000104</CNPJ><xNome>PAPAGAIO COMERCIO DE MOTOS LTDA</xNome><xFant>PAPAGAIO MOTOS</xFant><enderEmit><xLgr>PADRE VICENTE NOGUEIRA</xLgr><nro>149</nro><xBairro>ITAMARATI</xBairro><cMun>1301209</cMun><xMun>COARI</xMun><UF>AM</UF><CEP>69460000</CEP><cPais>1058</cPais><xPais>Brasil</xPais><fone>9791979595</fone></enderEmit><IE>054756448</IE><CRT>3</CRT></emit><det nItem=\"1\"><prod><cProd>1</cProd><cEAN>SEM GTIN</cEAN><xProd>Buzina 12v c100</xProd><NCM>85123000</NCM><CFOP>5102</CFOP><uCom>UN</uCom><qCom>1.000</qCom><vUnCom>60.00</vUnCom><vProd>60.00</vProd><cEANTrib>SEM GTIN</cEANTrib><uTrib>UN</uTrib><qTrib>1.000</qTrib><vUnTrib>60.00</vUnTrib><indTot>1</indTot></prod><imposto><ICMS><ICMSSN102><orig>0</orig><CSOSN>102</CSOSN></ICMSSN102></ICMS><PIS><PISNT><CST>07</CST></PISNT></PIS><COFINS><COFINSNT><CST>07</CST></COFINSNT></COFINS></imposto></det><total><ICMSTot><vBC>0.00</vBC><vICMS>0.00</vICMS><vICMSDeson>0.00</vICMSDeson><vFCP>0.00</vFCP><vBCST>0.00</vBCST><vST>0.00</vST><vFCPST>0.00</vFCPST><vFCPSTRet>0.00</vFCPSTRet><vProd>60.00</vProd><vFrete>0.00</vFrete><vSeg>0.00</vSeg><vDesc>0.00</vDesc><vII>0.00</vII><vIPI>0.00</vIPI><vIPIDevol>0.00</vIPIDevol><vPIS>0.00</vPIS><vCOFINS>0.00</vCOFINS><vOutro>0.00</vOutro><vNF>60.00</vNF></ICMSTot></total><transp><modFrete>9</modFrete></transp><pag><detPag><indPag>0</indPag><tPag>20</tPag><vPag>60.00</vPag></detPag></pag><infAdic><infCpl>PDV A&#xE7;aiteria</infCpl></infAdic></infNFe><infNFeSupl><qrCode>https://sistemas.sefaz.am.gov.br/nfceweb-hom/consultarNFCe.jsp?p=13260359598453000104650010000000281259337754|2|2|2|C8EE68EC7B5297D293DA32290DC75C097CE54D11</qrCode><urlChave>www.sefaz.am.gov.br/nfce/consulta</urlChave></infNFeSupl><Signature xmlns=\"http://www.w3.org/2000/09/xmldsig#\"><SignedInfo><CanonicalizationMethod Algorithm=\"http://www.w3.org/TR/2001/REC-xml-c14n-20010315\"/><SignatureMethod Algorithm=\"http://www.w3.org/2000/09/xmldsig#rsa-sha1\"/><Reference URI=\"#NFe13260359598453000104650010000000281259337754\"><Transforms><Transform Algorithm=\"http://www.w3.org/2000/09/xmldsig#enveloped-signature\"/><Transform Algorithm=\"http://www.w3.org/TR/2001/REC-xml-c14n-20010315\"/></Transforms><DigestMethod Algorithm=\"http://www.w3.org/2000/09/xmldsig#sha1\"/><DigestValue>80LKLe2DPcLtqRv5oVsDxALGyi4=</DigestValue></Reference></SignedInfo><SignatureValue>LgFPCJsfBrXOaktc+3VsGfx0NnasrwIKfE3qVGDv2ac/7+xc/VDD8h/hpDjK8hucjq9D8l1N27C470q+H1S9/zPdYpSDqFBf937k98FtRIad+Xw7ZtkMxRg2WGITL6m2y4qFWEgaB53Xekfv36QEc7sSIyyFo3behuoKpGl/pQ6Hrhds0NDM05hQDGILxDqVSPcIl694kpwpRmZGRpPH8cJuA1iPozB6CD2DBlKtua5jQarWogrDRsNzfveZWR3Nb0O6r3LoTKAwd3jBeES8xnCGae1dRLn9SLztd01lH3T+xXjMU/ZPRn8oiYVGlG/KeGWeyRsI52i2cLy6qQEPvA==</SignatureValue><KeyInfo><X509Data><X509Certificate>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</X509Certificate></X509Data></KeyInfo></Signature></NFe>', '<?xml version=\'1.0\' encoding=\'utf-8\'?><soapenv:Envelope xmlns:soapenv=\"http://www.w3.org/2003/05/soap-envelope\"><soapenv:Body><nfeResultMsg xmlns=\"http://www.portalfiscal.inf.br/nfe/wsdl/NFeAutorizacao4\"><retEnviNFe xmlns=\"http://www.portalfiscal.inf.br/nfe\" versao=\"4.00\"><tpAmb>2</tpAmb><verAplic>AM3.10-4.00</verAplic><cStat>104</cStat><xMotivo>Lote processado</xMotivo><cUF>13</cUF><dhRecbto>2026-03-12T09:22:14-04:00</dhRecbto><protNFe versao=\"4.00\"><infProt><tpAmb>2</tpAmb><verAplic>AM3.10-4.00</verAplic><chNFe>13260359598453000104650010000000281259337754</chNFe><dhRecbto>2026-03-12T09:22:14-04:00</dhRecbto><cStat>373</cStat><xMotivo>Rejeicao: Descricao do primeiro item diferente de NOTA FISCAL EMITIDA EM AMBIENTE DE HOMOLOGACAO - SEM VALOR FISCAL</xMotivo></infProt></protNFe></retEnviNFe></nfeResultMsg></soapenv:Body></soapenv:Envelope>', 60.00, 0.00, '{\"tPag\":\"20\"}', '2026-03-12 13:22:14');
INSERT INTO `nfce_emitidas` (`id`, `empresa_id`, `venda_id`, `ambiente`, `serie`, `numero`, `chave`, `protocolo`, `status_sefaz`, `mensagem`, `xml_nfeproc`, `xml_envio`, `xml_retorno`, `valor_total`, `valor_troco`, `tpag_json`, `created_at`) VALUES
(16, '125', 95, 2, 1, 29, '13260359598453000104650010000000291402033692', NULL, '104', 'Lote processado', '<?xml version=\"1.0\" encoding=\"UTF-8\"?><nfeProc xmlns=\"http://www.portalfiscal.inf.br/nfe\" versao=\"4.00\"><NFe xmlns=\"http://www.portalfiscal.inf.br/nfe\"><infNFe Id=\"NFe13260359598453000104650010000000291402033692\" versao=\"4.00\"><ide><cUF>13</cUF><cNF>40203369</cNF><natOp>VENDA</natOp><mod>65</mod><serie>1</serie><nNF>29</nNF><dhEmi>2026-03-12T09:22:55-04:00</dhEmi><tpNF>1</tpNF><idDest>1</idDest><cMunFG>1301209</cMunFG><tpImp>4</tpImp><tpEmis>1</tpEmis><cDV>2</cDV><tpAmb>2</tpAmb><finNFe>1</finNFe><indFinal>1</indFinal><indPres>1</indPres><procEmi>0</procEmi><verProc>PDV-ACAI-1.0</verProc></ide><emit><CNPJ>59598453000104</CNPJ><xNome>PAPAGAIO COMERCIO DE MOTOS LTDA</xNome><xFant>PAPAGAIO MOTOS</xFant><enderEmit><xLgr>PADRE VICENTE NOGUEIRA</xLgr><nro>149</nro><xBairro>ITAMARATI</xBairro><cMun>1301209</cMun><xMun>COARI</xMun><UF>AM</UF><CEP>69460000</CEP><cPais>1058</cPais><xPais>Brasil</xPais><fone>9791979595</fone></enderEmit><IE>054756448</IE><CRT>3</CRT></emit><det nItem=\"1\"><prod><cProd>1</cProd><cEAN>SEM GTIN</cEAN><xProd>Buzina 12v c100</xProd><NCM>85123000</NCM><CFOP>5102</CFOP><uCom>UN</uCom><qCom>1.000</qCom><vUnCom>60.00</vUnCom><vProd>60.00</vProd><cEANTrib>SEM GTIN</cEANTrib><uTrib>UN</uTrib><qTrib>1.000</qTrib><vUnTrib>60.00</vUnTrib><indTot>1</indTot></prod><imposto><ICMS><ICMSSN102><orig>0</orig><CSOSN>102</CSOSN></ICMSSN102></ICMS><PIS><PISNT><CST>07</CST></PISNT></PIS><COFINS><COFINSNT><CST>07</CST></COFINSNT></COFINS></imposto></det><total><ICMSTot><vBC>0.00</vBC><vICMS>0.00</vICMS><vICMSDeson>0.00</vICMSDeson><vFCP>0.00</vFCP><vBCST>0.00</vBCST><vST>0.00</vST><vFCPST>0.00</vFCPST><vFCPSTRet>0.00</vFCPSTRet><vProd>60.00</vProd><vFrete>0.00</vFrete><vSeg>0.00</vSeg><vDesc>0.00</vDesc><vII>0.00</vII><vIPI>0.00</vIPI><vIPIDevol>0.00</vIPIDevol><vPIS>0.00</vPIS><vCOFINS>0.00</vCOFINS><vOutro>0.00</vOutro><vNF>60.00</vNF></ICMSTot></total><transp><modFrete>9</modFrete></transp><pag><detPag><indPag>0</indPag><tPag>20</tPag><vPag>60.00</vPag></detPag></pag><infAdic><infCpl>PDV A&#xE7;aiteria</infCpl></infAdic></infNFe><infNFeSupl><qrCode>https://sistemas.sefaz.am.gov.br/nfceweb-hom/consultarNFCe.jsp?p=13260359598453000104650010000000291402033692|2|2|2|FD5F889829E0AE19F2F98CA0AD7F24840DBA3801</qrCode><urlChave>www.sefaz.am.gov.br/nfce/consulta</urlChave></infNFeSupl><Signature xmlns=\"http://www.w3.org/2000/09/xmldsig#\"><SignedInfo><CanonicalizationMethod Algorithm=\"http://www.w3.org/TR/2001/REC-xml-c14n-20010315\"/><SignatureMethod Algorithm=\"http://www.w3.org/2000/09/xmldsig#rsa-sha1\"/><Reference URI=\"#NFe13260359598453000104650010000000291402033692\"><Transforms><Transform Algorithm=\"http://www.w3.org/2000/09/xmldsig#enveloped-signature\"/><Transform Algorithm=\"http://www.w3.org/TR/2001/REC-xml-c14n-20010315\"/></Transforms><DigestMethod Algorithm=\"http://www.w3.org/2000/09/xmldsig#sha1\"/><DigestValue>nYnAzZ9AMTfBpbOq98YwIgdV+yw=</DigestValue></Reference></SignedInfo><SignatureValue>e1ALzd0idZDYfV8PubigUVRQhqKhpZ/ZS/imBuZF/YX3eqwjwf1ORJE9i8BqjnT+0kzY4OxnnBLsy/awd/QhsB8+AZKX4FysZBMiGa1V9z3QwMKNn8roYGXfKKro9hJyHjJ7ldhFXGEvrNM1XHDHkh5O/xjuqzOFtomtlTD25aOWfc/maVFTmc1IuK5xFB3AiYy8kkkS4ibtJCe3r4vUbIc0QCqvK5MfCtVB5y97hQA70ZCg5ijLWtuemoIkG897WLoW/rSuuWrGNds9TivVlDLXPpDWYN2GGMTqZQWvQ2GAtQu+hh5XqXxZ6SgMkW+5osvCF5C64E/HOR2XBoFf0g==</SignatureValue><KeyInfo><X509Data><X509Certificate>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</X509Certificate></X509Data></KeyInfo></Signature></NFe><protNFe versao=\"4.00\"><infProt><tpAmb>2</tpAmb><verAplic>AM3.10-4.00</verAplic><chNFe>13260359598453000104650010000000291402033692</chNFe><dhRecbto>2026-03-12T09:22:56-04:00</dhRecbto><cStat>373</cStat><xMotivo>Rejeicao: Descricao do primeiro item diferente de NOTA FISCAL EMITIDA EM AMBIENTE DE HOMOLOGACAO - SEM VALOR FISCAL</xMotivo></infProt></protNFe></nfeProc>', '<?xml version=\"1.0\"?><NFe xmlns=\"http://www.portalfiscal.inf.br/nfe\"><infNFe Id=\"NFe13260359598453000104650010000000291402033692\" versao=\"4.00\"><ide><cUF>13</cUF><cNF>40203369</cNF><natOp>VENDA</natOp><mod>65</mod><serie>1</serie><nNF>29</nNF><dhEmi>2026-03-12T09:22:55-04:00</dhEmi><tpNF>1</tpNF><idDest>1</idDest><cMunFG>1301209</cMunFG><tpImp>4</tpImp><tpEmis>1</tpEmis><cDV>2</cDV><tpAmb>2</tpAmb><finNFe>1</finNFe><indFinal>1</indFinal><indPres>1</indPres><procEmi>0</procEmi><verProc>PDV-ACAI-1.0</verProc></ide><emit><CNPJ>59598453000104</CNPJ><xNome>PAPAGAIO COMERCIO DE MOTOS LTDA</xNome><xFant>PAPAGAIO MOTOS</xFant><enderEmit><xLgr>PADRE VICENTE NOGUEIRA</xLgr><nro>149</nro><xBairro>ITAMARATI</xBairro><cMun>1301209</cMun><xMun>COARI</xMun><UF>AM</UF><CEP>69460000</CEP><cPais>1058</cPais><xPais>Brasil</xPais><fone>9791979595</fone></enderEmit><IE>054756448</IE><CRT>3</CRT></emit><det nItem=\"1\"><prod><cProd>1</cProd><cEAN>SEM GTIN</cEAN><xProd>Buzina 12v c100</xProd><NCM>85123000</NCM><CFOP>5102</CFOP><uCom>UN</uCom><qCom>1.000</qCom><vUnCom>60.00</vUnCom><vProd>60.00</vProd><cEANTrib>SEM GTIN</cEANTrib><uTrib>UN</uTrib><qTrib>1.000</qTrib><vUnTrib>60.00</vUnTrib><indTot>1</indTot></prod><imposto><ICMS><ICMSSN102><orig>0</orig><CSOSN>102</CSOSN></ICMSSN102></ICMS><PIS><PISNT><CST>07</CST></PISNT></PIS><COFINS><COFINSNT><CST>07</CST></COFINSNT></COFINS></imposto></det><total><ICMSTot><vBC>0.00</vBC><vICMS>0.00</vICMS><vICMSDeson>0.00</vICMSDeson><vFCP>0.00</vFCP><vBCST>0.00</vBCST><vST>0.00</vST><vFCPST>0.00</vFCPST><vFCPSTRet>0.00</vFCPSTRet><vProd>60.00</vProd><vFrete>0.00</vFrete><vSeg>0.00</vSeg><vDesc>0.00</vDesc><vII>0.00</vII><vIPI>0.00</vIPI><vIPIDevol>0.00</vIPIDevol><vPIS>0.00</vPIS><vCOFINS>0.00</vCOFINS><vOutro>0.00</vOutro><vNF>60.00</vNF></ICMSTot></total><transp><modFrete>9</modFrete></transp><pag><detPag><indPag>0</indPag><tPag>20</tPag><vPag>60.00</vPag></detPag></pag><infAdic><infCpl>PDV A&#xE7;aiteria</infCpl></infAdic></infNFe><infNFeSupl><qrCode>https://sistemas.sefaz.am.gov.br/nfceweb-hom/consultarNFCe.jsp?p=13260359598453000104650010000000291402033692|2|2|2|FD5F889829E0AE19F2F98CA0AD7F24840DBA3801</qrCode><urlChave>www.sefaz.am.gov.br/nfce/consulta</urlChave></infNFeSupl><Signature xmlns=\"http://www.w3.org/2000/09/xmldsig#\"><SignedInfo><CanonicalizationMethod Algorithm=\"http://www.w3.org/TR/2001/REC-xml-c14n-20010315\"/><SignatureMethod Algorithm=\"http://www.w3.org/2000/09/xmldsig#rsa-sha1\"/><Reference URI=\"#NFe13260359598453000104650010000000291402033692\"><Transforms><Transform Algorithm=\"http://www.w3.org/2000/09/xmldsig#enveloped-signature\"/><Transform Algorithm=\"http://www.w3.org/TR/2001/REC-xml-c14n-20010315\"/></Transforms><DigestMethod Algorithm=\"http://www.w3.org/2000/09/xmldsig#sha1\"/><DigestValue>nYnAzZ9AMTfBpbOq98YwIgdV+yw=</DigestValue></Reference></SignedInfo><SignatureValue>e1ALzd0idZDYfV8PubigUVRQhqKhpZ/ZS/imBuZF/YX3eqwjwf1ORJE9i8BqjnT+0kzY4OxnnBLsy/awd/QhsB8+AZKX4FysZBMiGa1V9z3QwMKNn8roYGXfKKro9hJyHjJ7ldhFXGEvrNM1XHDHkh5O/xjuqzOFtomtlTD25aOWfc/maVFTmc1IuK5xFB3AiYy8kkkS4ibtJCe3r4vUbIc0QCqvK5MfCtVB5y97hQA70ZCg5ijLWtuemoIkG897WLoW/rSuuWrGNds9TivVlDLXPpDWYN2GGMTqZQWvQ2GAtQu+hh5XqXxZ6SgMkW+5osvCF5C64E/HOR2XBoFf0g==</SignatureValue><KeyInfo><X509Data><X509Certificate>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</X509Certificate></X509Data></KeyInfo></Signature></NFe>', '<?xml version=\'1.0\' encoding=\'utf-8\'?><soapenv:Envelope xmlns:soapenv=\"http://www.w3.org/2003/05/soap-envelope\"><soapenv:Body><nfeResultMsg xmlns=\"http://www.portalfiscal.inf.br/nfe/wsdl/NFeAutorizacao4\"><retEnviNFe xmlns=\"http://www.portalfiscal.inf.br/nfe\" versao=\"4.00\"><tpAmb>2</tpAmb><verAplic>AM3.10-4.00</verAplic><cStat>104</cStat><xMotivo>Lote processado</xMotivo><cUF>13</cUF><dhRecbto>2026-03-12T09:22:56-04:00</dhRecbto><protNFe versao=\"4.00\"><infProt><tpAmb>2</tpAmb><verAplic>AM3.10-4.00</verAplic><chNFe>13260359598453000104650010000000291402033692</chNFe><dhRecbto>2026-03-12T09:22:56-04:00</dhRecbto><cStat>373</cStat><xMotivo>Rejeicao: Descricao do primeiro item diferente de NOTA FISCAL EMITIDA EM AMBIENTE DE HOMOLOGACAO - SEM VALOR FISCAL</xMotivo></infProt></protNFe></retEnviNFe></nfeResultMsg></soapenv:Body></soapenv:Envelope>', 60.00, 0.00, '{\"tPag\":\"20\"}', '2026-03-12 13:22:56'),
(17, '125', 96, 2, 1, 30, '13260359598453000104650010000000301649233765', NULL, '104', 'Lote processado', '<?xml version=\"1.0\" encoding=\"UTF-8\"?><nfeProc xmlns=\"http://www.portalfiscal.inf.br/nfe\" versao=\"4.00\"><NFe xmlns=\"http://www.portalfiscal.inf.br/nfe\"><infNFe Id=\"NFe13260359598453000104650010000000301649233765\" versao=\"4.00\"><ide><cUF>13</cUF><cNF>64923376</cNF><natOp>VENDA</natOp><mod>65</mod><serie>1</serie><nNF>30</nNF><dhEmi>2026-03-12T09:32:59-04:00</dhEmi><tpNF>1</tpNF><idDest>1</idDest><cMunFG>1301209</cMunFG><tpImp>4</tpImp><tpEmis>1</tpEmis><cDV>5</cDV><tpAmb>2</tpAmb><finNFe>1</finNFe><indFinal>1</indFinal><indPres>1</indPres><procEmi>0</procEmi><verProc>PDV-ACAI-1.0</verProc></ide><emit><CNPJ>59598453000104</CNPJ><xNome>PAPAGAIO COMERCIO DE MOTOS LTDA</xNome><xFant>PAPAGAIO MOTOS</xFant><enderEmit><xLgr>PADRE VICENTE NOGUEIRA</xLgr><nro>149</nro><xBairro>ITAMARATI</xBairro><cMun>1301209</cMun><xMun>COARI</xMun><UF>AM</UF><CEP>69460000</CEP><cPais>1058</cPais><xPais>Brasil</xPais><fone>9791979595</fone></enderEmit><IE>054756448</IE><CRT>3</CRT></emit><dest><CPF>04125521247</CPF><xNome>lucas correa silva</xNome><indIEDest>9</indIEDest></dest><det nItem=\"1\"><prod><cProd>1</cProd><cEAN>SEM GTIN</cEAN><xProd>Buzina 12v c100</xProd><NCM>85123000</NCM><CFOP>5102</CFOP><uCom>UN</uCom><qCom>1.000</qCom><vUnCom>60.00</vUnCom><vProd>60.00</vProd><cEANTrib>SEM GTIN</cEANTrib><uTrib>UN</uTrib><qTrib>1.000</qTrib><vUnTrib>60.00</vUnTrib><indTot>1</indTot></prod><imposto><ICMS><ICMSSN102><orig>0</orig><CSOSN>102</CSOSN></ICMSSN102></ICMS><PIS><PISNT><CST>07</CST></PISNT></PIS><COFINS><COFINSNT><CST>07</CST></COFINSNT></COFINS></imposto></det><total><ICMSTot><vBC>0.00</vBC><vICMS>0.00</vICMS><vICMSDeson>0.00</vICMSDeson><vFCP>0.00</vFCP><vBCST>0.00</vBCST><vST>0.00</vST><vFCPST>0.00</vFCPST><vFCPSTRet>0.00</vFCPSTRet><vProd>60.00</vProd><vFrete>0.00</vFrete><vSeg>0.00</vSeg><vDesc>0.00</vDesc><vII>0.00</vII><vIPI>0.00</vIPI><vIPIDevol>0.00</vIPIDevol><vPIS>0.00</vPIS><vCOFINS>0.00</vCOFINS><vOutro>0.00</vOutro><vNF>60.00</vNF></ICMSTot></total><transp><modFrete>9</modFrete></transp><pag><detPag><indPag>0</indPag><tPag>20</tPag><vPag>60.00</vPag></detPag></pag><infAdic><infCpl>PDV A&#xE7;aiteria</infCpl></infAdic></infNFe><infNFeSupl><qrCode>https://sistemas.sefaz.am.gov.br/nfceweb-hom/consultarNFCe.jsp?p=13260359598453000104650010000000301649233765|2|2|2|B6DE8EFA3A31A176E1FCFF767B49DBF65AEDFAD9</qrCode><urlChave>www.sefaz.am.gov.br/nfce/consulta</urlChave></infNFeSupl><Signature xmlns=\"http://www.w3.org/2000/09/xmldsig#\"><SignedInfo><CanonicalizationMethod Algorithm=\"http://www.w3.org/TR/2001/REC-xml-c14n-20010315\"/><SignatureMethod Algorithm=\"http://www.w3.org/2000/09/xmldsig#rsa-sha1\"/><Reference URI=\"#NFe13260359598453000104650010000000301649233765\"><Transforms><Transform Algorithm=\"http://www.w3.org/2000/09/xmldsig#enveloped-signature\"/><Transform Algorithm=\"http://www.w3.org/TR/2001/REC-xml-c14n-20010315\"/></Transforms><DigestMethod Algorithm=\"http://www.w3.org/2000/09/xmldsig#sha1\"/><DigestValue>PQBeh4H8O76iDffEWlEi04wklxM=</DigestValue></Reference></SignedInfo><SignatureValue>TAjnTnDFOnhdyMZFOhKbFIIZdXFEzH8wGThfRvnVmGmAbFL/lKtT7q7cWoXJh3Jsv7qErswXf400shlBmW5YF7veKbamfvFMh5CiwgIJR5sL1hAJOeFg+vGPFsypyMnn5M5uOWrQbSvY461szp4O/BXgksOngxvGgGc4rQporoCp32CYXYwspBm38l+NlkMWT83jngRvu2MEBAUNTe/ocWS/gKfSttAMxkNWEaVRdM5v3GlFzDBDu1wXlPMS3Yh0ePipTLmu1gIeco6R4O+ncLtMS0VdHm+a7B0IRNoqvgDdVy1gOmuV4lA0wmHaJNnDKJiuSlQZzWX2wCspXD50sA==</SignatureValue><KeyInfo><X509Data><X509Certificate>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</X509Certificate></X509Data></KeyInfo></Signature></NFe><protNFe versao=\"4.00\"><infProt><tpAmb>2</tpAmb><verAplic>AM3.10-4.00</verAplic><chNFe>13260359598453000104650010000000301649233765</chNFe><dhRecbto>2026-03-12T09:33:00-04:00</dhRecbto><cStat>373</cStat><xMotivo>Rejeicao: Descricao do primeiro item diferente de NOTA FISCAL EMITIDA EM AMBIENTE DE HOMOLOGACAO - SEM VALOR FISCAL</xMotivo></infProt></protNFe></nfeProc>', '<?xml version=\"1.0\"?><NFe xmlns=\"http://www.portalfiscal.inf.br/nfe\"><infNFe Id=\"NFe13260359598453000104650010000000301649233765\" versao=\"4.00\"><ide><cUF>13</cUF><cNF>64923376</cNF><natOp>VENDA</natOp><mod>65</mod><serie>1</serie><nNF>30</nNF><dhEmi>2026-03-12T09:32:59-04:00</dhEmi><tpNF>1</tpNF><idDest>1</idDest><cMunFG>1301209</cMunFG><tpImp>4</tpImp><tpEmis>1</tpEmis><cDV>5</cDV><tpAmb>2</tpAmb><finNFe>1</finNFe><indFinal>1</indFinal><indPres>1</indPres><procEmi>0</procEmi><verProc>PDV-ACAI-1.0</verProc></ide><emit><CNPJ>59598453000104</CNPJ><xNome>PAPAGAIO COMERCIO DE MOTOS LTDA</xNome><xFant>PAPAGAIO MOTOS</xFant><enderEmit><xLgr>PADRE VICENTE NOGUEIRA</xLgr><nro>149</nro><xBairro>ITAMARATI</xBairro><cMun>1301209</cMun><xMun>COARI</xMun><UF>AM</UF><CEP>69460000</CEP><cPais>1058</cPais><xPais>Brasil</xPais><fone>9791979595</fone></enderEmit><IE>054756448</IE><CRT>3</CRT></emit><dest><CPF>04125521247</CPF><xNome>lucas correa silva</xNome><indIEDest>9</indIEDest></dest><det nItem=\"1\"><prod><cProd>1</cProd><cEAN>SEM GTIN</cEAN><xProd>Buzina 12v c100</xProd><NCM>85123000</NCM><CFOP>5102</CFOP><uCom>UN</uCom><qCom>1.000</qCom><vUnCom>60.00</vUnCom><vProd>60.00</vProd><cEANTrib>SEM GTIN</cEANTrib><uTrib>UN</uTrib><qTrib>1.000</qTrib><vUnTrib>60.00</vUnTrib><indTot>1</indTot></prod><imposto><ICMS><ICMSSN102><orig>0</orig><CSOSN>102</CSOSN></ICMSSN102></ICMS><PIS><PISNT><CST>07</CST></PISNT></PIS><COFINS><COFINSNT><CST>07</CST></COFINSNT></COFINS></imposto></det><total><ICMSTot><vBC>0.00</vBC><vICMS>0.00</vICMS><vICMSDeson>0.00</vICMSDeson><vFCP>0.00</vFCP><vBCST>0.00</vBCST><vST>0.00</vST><vFCPST>0.00</vFCPST><vFCPSTRet>0.00</vFCPSTRet><vProd>60.00</vProd><vFrete>0.00</vFrete><vSeg>0.00</vSeg><vDesc>0.00</vDesc><vII>0.00</vII><vIPI>0.00</vIPI><vIPIDevol>0.00</vIPIDevol><vPIS>0.00</vPIS><vCOFINS>0.00</vCOFINS><vOutro>0.00</vOutro><vNF>60.00</vNF></ICMSTot></total><transp><modFrete>9</modFrete></transp><pag><detPag><indPag>0</indPag><tPag>20</tPag><vPag>60.00</vPag></detPag></pag><infAdic><infCpl>PDV A&#xE7;aiteria</infCpl></infAdic></infNFe><infNFeSupl><qrCode>https://sistemas.sefaz.am.gov.br/nfceweb-hom/consultarNFCe.jsp?p=13260359598453000104650010000000301649233765|2|2|2|B6DE8EFA3A31A176E1FCFF767B49DBF65AEDFAD9</qrCode><urlChave>www.sefaz.am.gov.br/nfce/consulta</urlChave></infNFeSupl><Signature xmlns=\"http://www.w3.org/2000/09/xmldsig#\"><SignedInfo><CanonicalizationMethod Algorithm=\"http://www.w3.org/TR/2001/REC-xml-c14n-20010315\"/><SignatureMethod Algorithm=\"http://www.w3.org/2000/09/xmldsig#rsa-sha1\"/><Reference URI=\"#NFe13260359598453000104650010000000301649233765\"><Transforms><Transform Algorithm=\"http://www.w3.org/2000/09/xmldsig#enveloped-signature\"/><Transform Algorithm=\"http://www.w3.org/TR/2001/REC-xml-c14n-20010315\"/></Transforms><DigestMethod Algorithm=\"http://www.w3.org/2000/09/xmldsig#sha1\"/><DigestValue>PQBeh4H8O76iDffEWlEi04wklxM=</DigestValue></Reference></SignedInfo><SignatureValue>TAjnTnDFOnhdyMZFOhKbFIIZdXFEzH8wGThfRvnVmGmAbFL/lKtT7q7cWoXJh3Jsv7qErswXf400shlBmW5YF7veKbamfvFMh5CiwgIJR5sL1hAJOeFg+vGPFsypyMnn5M5uOWrQbSvY461szp4O/BXgksOngxvGgGc4rQporoCp32CYXYwspBm38l+NlkMWT83jngRvu2MEBAUNTe/ocWS/gKfSttAMxkNWEaVRdM5v3GlFzDBDu1wXlPMS3Yh0ePipTLmu1gIeco6R4O+ncLtMS0VdHm+a7B0IRNoqvgDdVy1gOmuV4lA0wmHaJNnDKJiuSlQZzWX2wCspXD50sA==</SignatureValue><KeyInfo><X509Data><X509Certificate>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</X509Certificate></X509Data></KeyInfo></Signature></NFe>', '<?xml version=\'1.0\' encoding=\'utf-8\'?><soapenv:Envelope xmlns:soapenv=\"http://www.w3.org/2003/05/soap-envelope\"><soapenv:Body><nfeResultMsg xmlns=\"http://www.portalfiscal.inf.br/nfe/wsdl/NFeAutorizacao4\"><retEnviNFe xmlns=\"http://www.portalfiscal.inf.br/nfe\" versao=\"4.00\"><tpAmb>2</tpAmb><verAplic>AM3.10-4.00</verAplic><cStat>104</cStat><xMotivo>Lote processado</xMotivo><cUF>13</cUF><dhRecbto>2026-03-12T09:33:00-04:00</dhRecbto><protNFe versao=\"4.00\"><infProt><tpAmb>2</tpAmb><verAplic>AM3.10-4.00</verAplic><chNFe>13260359598453000104650010000000301649233765</chNFe><dhRecbto>2026-03-12T09:33:00-04:00</dhRecbto><cStat>373</cStat><xMotivo>Rejeicao: Descricao do primeiro item diferente de NOTA FISCAL EMITIDA EM AMBIENTE DE HOMOLOGACAO - SEM VALOR FISCAL</xMotivo></infProt></protNFe></retEnviNFe></nfeResultMsg></soapenv:Body></soapenv:Envelope>', 60.00, 0.00, '{\"tPag\":\"20\"}', '2026-03-12 13:33:00'),
(18, '125', 97, 2, 1, 31, '13260359598453000104650010000000311750865990', NULL, '104', 'Lote processado', '<?xml version=\"1.0\" encoding=\"UTF-8\"?><nfeProc xmlns=\"http://www.portalfiscal.inf.br/nfe\" versao=\"4.00\"><NFe xmlns=\"http://www.portalfiscal.inf.br/nfe\"><infNFe Id=\"NFe13260359598453000104650010000000311750865990\" versao=\"4.00\"><ide><cUF>13</cUF><cNF>75086599</cNF><natOp>VENDA</natOp><mod>65</mod><serie>1</serie><nNF>31</nNF><dhEmi>2026-03-12T09:33:35-04:00</dhEmi><tpNF>1</tpNF><idDest>1</idDest><cMunFG>1301209</cMunFG><tpImp>4</tpImp><tpEmis>1</tpEmis><cDV>0</cDV><tpAmb>2</tpAmb><finNFe>1</finNFe><indFinal>1</indFinal><indPres>1</indPres><procEmi>0</procEmi><verProc>PDV-ACAI-1.0</verProc></ide><emit><CNPJ>59598453000104</CNPJ><xNome>PAPAGAIO COMERCIO DE MOTOS LTDA</xNome><xFant>PAPAGAIO MOTOS</xFant><enderEmit><xLgr>PADRE VICENTE NOGUEIRA</xLgr><nro>149</nro><xBairro>ITAMARATI</xBairro><cMun>1301209</cMun><xMun>COARI</xMun><UF>AM</UF><CEP>69460000</CEP><cPais>1058</cPais><xPais>Brasil</xPais><fone>9791979595</fone></enderEmit><IE>054756448</IE><CRT>3</CRT></emit><det nItem=\"1\"><prod><cProd>1</cProd><cEAN>SEM GTIN</cEAN><xProd>Buzina 12v c100</xProd><NCM>85123000</NCM><CFOP>5102</CFOP><uCom>UN</uCom><qCom>1.000</qCom><vUnCom>60.00</vUnCom><vProd>60.00</vProd><cEANTrib>SEM GTIN</cEANTrib><uTrib>UN</uTrib><qTrib>1.000</qTrib><vUnTrib>60.00</vUnTrib><indTot>1</indTot></prod><imposto><ICMS><ICMSSN102><orig>0</orig><CSOSN>102</CSOSN></ICMSSN102></ICMS><PIS><PISNT><CST>07</CST></PISNT></PIS><COFINS><COFINSNT><CST>07</CST></COFINSNT></COFINS></imposto></det><total><ICMSTot><vBC>0.00</vBC><vICMS>0.00</vICMS><vICMSDeson>0.00</vICMSDeson><vFCP>0.00</vFCP><vBCST>0.00</vBCST><vST>0.00</vST><vFCPST>0.00</vFCPST><vFCPSTRet>0.00</vFCPSTRet><vProd>60.00</vProd><vFrete>0.00</vFrete><vSeg>0.00</vSeg><vDesc>0.00</vDesc><vII>0.00</vII><vIPI>0.00</vIPI><vIPIDevol>0.00</vIPIDevol><vPIS>0.00</vPIS><vCOFINS>0.00</vCOFINS><vOutro>0.00</vOutro><vNF>60.00</vNF></ICMSTot></total><transp><modFrete>9</modFrete></transp><pag><detPag><indPag>0</indPag><tPag>20</tPag><vPag>60.00</vPag></detPag></pag><infAdic><infCpl>PDV A&#xE7;aiteria</infCpl></infAdic></infNFe><infNFeSupl><qrCode>https://sistemas.sefaz.am.gov.br/nfceweb-hom/consultarNFCe.jsp?p=13260359598453000104650010000000311750865990|2|2|2|EDC8AB6FF10D839AA181BB1F8B85E93DF497EF20</qrCode><urlChave>www.sefaz.am.gov.br/nfce/consulta</urlChave></infNFeSupl><Signature xmlns=\"http://www.w3.org/2000/09/xmldsig#\"><SignedInfo><CanonicalizationMethod Algorithm=\"http://www.w3.org/TR/2001/REC-xml-c14n-20010315\"/><SignatureMethod Algorithm=\"http://www.w3.org/2000/09/xmldsig#rsa-sha1\"/><Reference URI=\"#NFe13260359598453000104650010000000311750865990\"><Transforms><Transform Algorithm=\"http://www.w3.org/2000/09/xmldsig#enveloped-signature\"/><Transform Algorithm=\"http://www.w3.org/TR/2001/REC-xml-c14n-20010315\"/></Transforms><DigestMethod Algorithm=\"http://www.w3.org/2000/09/xmldsig#sha1\"/><DigestValue>+aNyeG7ija4Q07MaEb/m19lmqC4=</DigestValue></Reference></SignedInfo><SignatureValue>pcAfxN4vpcsLngVnaWW3bbngpnnnYYOvBFS6CJVq0KTl+gkUu2CyUgXUtbaM8Cp+G3z5xRrEoMtA9un/yrySBghHVSKXpwQkVOykSpg04q9QxD+bH+FPcvAmqp/F/HBoj4IobmylDmpJ+fmmWDMFkdkKg/v8c9F41NqushvLN1K1JhLU4FZeWrM7bd828r03G/y2n/jL+H1OEDqnm4EunER/MWdPVCFRUrftqKOfz8S8bGGVDrDZghFspqYlYLJzTcuJExWiilOXSRhKoJXhc0t/Dzu+GXtOA/BMyKMoYyzd5raC9ZXixfVkFFEoYFggfCmwu+nsPtF3rGyyUDFjbQ==</SignatureValue><KeyInfo><X509Data><X509Certificate>MIIH6DCCBdCgAwIBAgIKHqWXJoQ1LKoMazANBgkqhkiG9w0BAQsFADBbMQswCQYDVQQGEwJCUjEWMBQGA1UECwwNQUMgU3luZ3VsYXJJRDETMBEGA1UECgwKSUNQLUJyYXNpbDEfMB0GA1UEAwwWQUMgU3luZ3VsYXJJRCBNdWx0aXBsYTAeFw0yNTA2MDYyMDMzMzVaFw0yNjA2MDYyMDMzMzVaMIHOMQswCQYDVQQGEwJCUjETMBEGA1UECgwKSUNQLUJyYXNpbDEiMCAGA1UECwwZQ2VydGlmaWNhZG8gRGlnaXRhbCBQSiBBMTETMBEGA1UECwwKUHJlc2VuY2lhbDEXMBUGA1UECwwONDU2MTYzMDkwMDAxNDkxHzAdBgNVBAsMFkFDIFN5bmd1bGFySUQgTXVsdGlwbGExNzA1BgNVBAMMLlBBUEFHQUlPIENPTUVSQ0lPIERFIE1PVE9TIExUREE6NTk1OTg0NTMwMDAxMDQwggEiMA0GCSqGSIb3DQEBAQUAA4IBDwAwggEKAoIBAQC4B4OKj+3kr6hnMnIFUA43tacQhEJmlvGAKVoXSiBo+30gia2+nmAly72AhkoWVIEO2q+I5o5RNgEA3jegdKJCL0jvFiJK/xPfDueVmt/3E/9N+jhOSaXScEDFtrPF6/nlkiKmrlgYyst/uWSCtg3fYzu4BfCTHJ1LL5nuoP4i2FrYaxunvwpg+NzSwvpXeWxBg2UOYRYC+LM6bMJluy+CoQzNKt2RoD8ljHdpzHY10bSL5jkLQOxUCE52SSrDrMD0HmKy6oylYL7xKLbuhiZIgCODNs6mS8bX19mgTZQ7PXs0seuiFuP+M2++rRlQyg2skdwMRDLBykJQSHWs0DP5AgMBAAGjggM4MIIDNDAOBgNVHQ8BAf8EBAMCBeAwHQYDVR0lBBYwFAYIKwYBBQUHAwQGCCsGAQUFBwMCMAkGA1UdEwQCMAAwHwYDVR0jBBgwFoAUk+H/fh3l9eRN4TliiyFpleavchYwHQYDVR0OBBYEFGxSV0SZ6AU0rrnMDKuDbgUDLeQeMH8GCCsGAQUFBwEBBHMwcTBvBggrBgEFBQcwAoZjaHR0cDovL3N5bmd1bGFyaWQuY29tLmJyL3JlcG9zaXRvcmlvL2FjLXN5bmd1bGFyaWQtbXVsdGlwbGEvY2VydGlmaWNhZG9zL2FjLXN5bmd1bGFyaWQtbXVsdGlwbGEucDdiMIGCBgNVHSAEezB5MHcGB2BMAQIBgQUwbDBqBggrBgEFBQcCARZeaHR0cDovL3N5bmd1bGFyaWQuY29tLmJyL3JlcG9zaXRvcmlvL2FjLXN5bmd1bGFyaWQtbXVsdGlwbGEvZHBjL2RwYy1hYy1zeW5ndWxhcklELW11bHRpcGxhLnBkZjCBzAYDVR0RBIHEMIHBoCoGBWBMAQMCoCEEH0xBWkFSTyBDT1JERUlSTyBERSBBTE1FSURBIE5FVE+gGQYFYEwBAwOgEAQONTk1OTg0NTMwMDAxMDSgQgYFYEwBAwSgOQQ3MDQwMjE5OTUwMzM5NTQ2MDI2NjAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMKAXBgVgTAEDB6AOBAwwMDAwMDAwMDAwMDCBG0NPUkRFSVJPTEFaQVJPODcwQEdNQUlMLkNPTTCB4gYDVR0fBIHaMIHXMG+gbaBrhmlodHRwOi8vaWNwLWJyYXNpbC5zeW5ndWxhcmlkLmNvbS5ici9yZXBvc2l0b3Jpby9hYy1zeW5ndWxhcmlkLW11bHRpcGxhL2xjci9sY3ItYWMtc3luZ3VsYXJpZC1tdWx0aXBsYS5jcmwwZKBioGCGXmh0dHA6Ly9zeW5ndWxhcmlkLmNvbS5ici9yZXBvc2l0b3Jpby9hYy1zeW5ndWxhcmlkLW11bHRpcGxhL2xjci9sY3ItYWMtc3luZ3VsYXJpZC1tdWx0aXBsYS5jcmwwDQYJKoZIhvcNAQELBQADggIBACI8nwyikS2ZY4yjPZ68tXNvHbyfoCizJsYbqLavXaIzI9D0VBqEyJAvpZVA8JQQ4qMm2rZkZmZjm4ucYTNezZf7OLX5STql96fOLYgFXHCpVMfG251bhsLpwBJmgES9FtG7+DrdLkQ+FAX7vkVgNKT70AQnDwY9cSM2ghSLiv+fu8ubLwk4rlrc7y9y6QGAB4kvbHY5J8yWdfMnQMPF/raR7mFuWG1co+UjXDm4E69K3VoFy3B9Jq1u2sUdryVjVaamOlLwhoFz8UPJm9Bjn1E1OiuSc2MJ1gUGPzwqvsNgasiq7D9/XuMUCeYia2BBpdy7Z+LKDKO2vtFZ11N4O+gbXLykxqnuwn8Iiigqu7+txxRPj/v2+i8ckWUyONx3zRZvlHXsfk4gTyLqK1+VJ0NqIK1A11DQLc5uaTPQe+DjmqSNFbDKsoKjhySK0Fs7yT1FXDWyvqMNmJkl/HVdVbtQesk58PvGlpTnDunlJDCL5Lcyfm59yFc5bBF9HBS5xMSfG7Vnk0JvQUYpkdf1NOI5PzbJhsN/TYvdDpgTDCJ9DILMrnqovzvGhkG46R8sQIhF93HygHgXrHRSSBySv0QnDbgkv8tnu0gXQRyzadWVD5nBQakzQVIbWzM0h5DF2n300mDE+fGhqcDz2iXqdCNmuVwurA/pc4ZIJyvfMj/Q</X509Certificate></X509Data></KeyInfo></Signature></NFe><protNFe versao=\"4.00\"><infProt><tpAmb>2</tpAmb><verAplic>AM3.10-4.00</verAplic><chNFe>13260359598453000104650010000000311750865990</chNFe><dhRecbto>2026-03-12T09:33:35-04:00</dhRecbto><cStat>373</cStat><xMotivo>Rejeicao: Descricao do primeiro item diferente de NOTA FISCAL EMITIDA EM AMBIENTE DE HOMOLOGACAO - SEM VALOR FISCAL</xMotivo></infProt></protNFe></nfeProc>', '<?xml version=\"1.0\"?><NFe xmlns=\"http://www.portalfiscal.inf.br/nfe\"><infNFe Id=\"NFe13260359598453000104650010000000311750865990\" versao=\"4.00\"><ide><cUF>13</cUF><cNF>75086599</cNF><natOp>VENDA</natOp><mod>65</mod><serie>1</serie><nNF>31</nNF><dhEmi>2026-03-12T09:33:35-04:00</dhEmi><tpNF>1</tpNF><idDest>1</idDest><cMunFG>1301209</cMunFG><tpImp>4</tpImp><tpEmis>1</tpEmis><cDV>0</cDV><tpAmb>2</tpAmb><finNFe>1</finNFe><indFinal>1</indFinal><indPres>1</indPres><procEmi>0</procEmi><verProc>PDV-ACAI-1.0</verProc></ide><emit><CNPJ>59598453000104</CNPJ><xNome>PAPAGAIO COMERCIO DE MOTOS LTDA</xNome><xFant>PAPAGAIO MOTOS</xFant><enderEmit><xLgr>PADRE VICENTE NOGUEIRA</xLgr><nro>149</nro><xBairro>ITAMARATI</xBairro><cMun>1301209</cMun><xMun>COARI</xMun><UF>AM</UF><CEP>69460000</CEP><cPais>1058</cPais><xPais>Brasil</xPais><fone>9791979595</fone></enderEmit><IE>054756448</IE><CRT>3</CRT></emit><det nItem=\"1\"><prod><cProd>1</cProd><cEAN>SEM GTIN</cEAN><xProd>Buzina 12v c100</xProd><NCM>85123000</NCM><CFOP>5102</CFOP><uCom>UN</uCom><qCom>1.000</qCom><vUnCom>60.00</vUnCom><vProd>60.00</vProd><cEANTrib>SEM GTIN</cEANTrib><uTrib>UN</uTrib><qTrib>1.000</qTrib><vUnTrib>60.00</vUnTrib><indTot>1</indTot></prod><imposto><ICMS><ICMSSN102><orig>0</orig><CSOSN>102</CSOSN></ICMSSN102></ICMS><PIS><PISNT><CST>07</CST></PISNT></PIS><COFINS><COFINSNT><CST>07</CST></COFINSNT></COFINS></imposto></det><total><ICMSTot><vBC>0.00</vBC><vICMS>0.00</vICMS><vICMSDeson>0.00</vICMSDeson><vFCP>0.00</vFCP><vBCST>0.00</vBCST><vST>0.00</vST><vFCPST>0.00</vFCPST><vFCPSTRet>0.00</vFCPSTRet><vProd>60.00</vProd><vFrete>0.00</vFrete><vSeg>0.00</vSeg><vDesc>0.00</vDesc><vII>0.00</vII><vIPI>0.00</vIPI><vIPIDevol>0.00</vIPIDevol><vPIS>0.00</vPIS><vCOFINS>0.00</vCOFINS><vOutro>0.00</vOutro><vNF>60.00</vNF></ICMSTot></total><transp><modFrete>9</modFrete></transp><pag><detPag><indPag>0</indPag><tPag>20</tPag><vPag>60.00</vPag></detPag></pag><infAdic><infCpl>PDV A&#xE7;aiteria</infCpl></infAdic></infNFe><infNFeSupl><qrCode>https://sistemas.sefaz.am.gov.br/nfceweb-hom/consultarNFCe.jsp?p=13260359598453000104650010000000311750865990|2|2|2|EDC8AB6FF10D839AA181BB1F8B85E93DF497EF20</qrCode><urlChave>www.sefaz.am.gov.br/nfce/consulta</urlChave></infNFeSupl><Signature xmlns=\"http://www.w3.org/2000/09/xmldsig#\"><SignedInfo><CanonicalizationMethod Algorithm=\"http://www.w3.org/TR/2001/REC-xml-c14n-20010315\"/><SignatureMethod Algorithm=\"http://www.w3.org/2000/09/xmldsig#rsa-sha1\"/><Reference URI=\"#NFe13260359598453000104650010000000311750865990\"><Transforms><Transform Algorithm=\"http://www.w3.org/2000/09/xmldsig#enveloped-signature\"/><Transform Algorithm=\"http://www.w3.org/TR/2001/REC-xml-c14n-20010315\"/></Transforms><DigestMethod Algorithm=\"http://www.w3.org/2000/09/xmldsig#sha1\"/><DigestValue>+aNyeG7ija4Q07MaEb/m19lmqC4=</DigestValue></Reference></SignedInfo><SignatureValue>pcAfxN4vpcsLngVnaWW3bbngpnnnYYOvBFS6CJVq0KTl+gkUu2CyUgXUtbaM8Cp+G3z5xRrEoMtA9un/yrySBghHVSKXpwQkVOykSpg04q9QxD+bH+FPcvAmqp/F/HBoj4IobmylDmpJ+fmmWDMFkdkKg/v8c9F41NqushvLN1K1JhLU4FZeWrM7bd828r03G/y2n/jL+H1OEDqnm4EunER/MWdPVCFRUrftqKOfz8S8bGGVDrDZghFspqYlYLJzTcuJExWiilOXSRhKoJXhc0t/Dzu+GXtOA/BMyKMoYyzd5raC9ZXixfVkFFEoYFggfCmwu+nsPtF3rGyyUDFjbQ==</SignatureValue><KeyInfo><X509Data><X509Certificate>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</X509Certificate></X509Data></KeyInfo></Signature></NFe>', '<?xml version=\'1.0\' encoding=\'utf-8\'?><soapenv:Envelope xmlns:soapenv=\"http://www.w3.org/2003/05/soap-envelope\"><soapenv:Body><nfeResultMsg xmlns=\"http://www.portalfiscal.inf.br/nfe/wsdl/NFeAutorizacao4\"><retEnviNFe xmlns=\"http://www.portalfiscal.inf.br/nfe\" versao=\"4.00\"><tpAmb>2</tpAmb><verAplic>AM3.10-4.00</verAplic><cStat>104</cStat><xMotivo>Lote processado</xMotivo><cUF>13</cUF><dhRecbto>2026-03-12T09:33:35-04:00</dhRecbto><protNFe versao=\"4.00\"><infProt><tpAmb>2</tpAmb><verAplic>AM3.10-4.00</verAplic><chNFe>13260359598453000104650010000000311750865990</chNFe><dhRecbto>2026-03-12T09:33:35-04:00</dhRecbto><cStat>373</cStat><xMotivo>Rejeicao: Descricao do primeiro item diferente de NOTA FISCAL EMITIDA EM AMBIENTE DE HOMOLOGACAO - SEM VALOR FISCAL</xMotivo></infProt></protNFe></retEnviNFe></nfeResultMsg></soapenv:Body></soapenv:Envelope>', 60.00, 0.00, '{\"tPag\":\"20\"}', '2026-03-12 13:33:35');
INSERT INTO `nfce_emitidas` (`id`, `empresa_id`, `venda_id`, `ambiente`, `serie`, `numero`, `chave`, `protocolo`, `status_sefaz`, `mensagem`, `xml_nfeproc`, `xml_envio`, `xml_retorno`, `valor_total`, `valor_troco`, `tpag_json`, `created_at`) VALUES
(19, '125', 98, 2, 1, 32, '13260359598453000104650010000000321826403166', NULL, '104', 'Lote processado', '<?xml version=\"1.0\" encoding=\"UTF-8\"?><nfeProc xmlns=\"http://www.portalfiscal.inf.br/nfe\" versao=\"4.00\"><NFe xmlns=\"http://www.portalfiscal.inf.br/nfe\"><infNFe Id=\"NFe13260359598453000104650010000000321826403166\" versao=\"4.00\"><ide><cUF>13</cUF><cNF>82640316</cNF><natOp>VENDA</natOp><mod>65</mod><serie>1</serie><nNF>32</nNF><dhEmi>2026-03-12T09:46:01-04:00</dhEmi><tpNF>1</tpNF><idDest>1</idDest><cMunFG>1301209</cMunFG><tpImp>4</tpImp><tpEmis>1</tpEmis><cDV>6</cDV><tpAmb>2</tpAmb><finNFe>1</finNFe><indFinal>1</indFinal><indPres>1</indPres><procEmi>0</procEmi><verProc>PDV-ACAI-1.0</verProc></ide><emit><CNPJ>59598453000104</CNPJ><xNome>PAPAGAIO COMERCIO DE MOTOS LTDA</xNome><xFant>PAPAGAIO MOTOS</xFant><enderEmit><xLgr>PADRE VICENTE NOGUEIRA</xLgr><nro>149</nro><xBairro>ITAMARATI</xBairro><cMun>1301209</cMun><xMun>COARI</xMun><UF>AM</UF><CEP>69460000</CEP><cPais>1058</cPais><xPais>Brasil</xPais><fone>9791979595</fone></enderEmit><IE>054756448</IE><CRT>3</CRT></emit><det nItem=\"1\"><prod><cProd>1</cProd><cEAN>SEM GTIN</cEAN><xProd>Buzina 12v c100</xProd><NCM>85123000</NCM><CFOP>5102</CFOP><uCom>UN</uCom><qCom>1.000</qCom><vUnCom>60.00</vUnCom><vProd>60.00</vProd><cEANTrib>SEM GTIN</cEANTrib><uTrib>UN</uTrib><qTrib>1.000</qTrib><vUnTrib>60.00</vUnTrib><indTot>1</indTot></prod><imposto><ICMS><ICMSSN102><orig>0</orig><CSOSN>102</CSOSN></ICMSSN102></ICMS><PIS><PISNT><CST>07</CST></PISNT></PIS><COFINS><COFINSNT><CST>07</CST></COFINSNT></COFINS></imposto></det><total><ICMSTot><vBC>0.00</vBC><vICMS>0.00</vICMS><vICMSDeson>0.00</vICMSDeson><vFCP>0.00</vFCP><vBCST>0.00</vBCST><vST>0.00</vST><vFCPST>0.00</vFCPST><vFCPSTRet>0.00</vFCPSTRet><vProd>60.00</vProd><vFrete>0.00</vFrete><vSeg>0.00</vSeg><vDesc>0.00</vDesc><vII>0.00</vII><vIPI>0.00</vIPI><vIPIDevol>0.00</vIPIDevol><vPIS>0.00</vPIS><vCOFINS>0.00</vCOFINS><vOutro>0.00</vOutro><vNF>60.00</vNF></ICMSTot></total><transp><modFrete>9</modFrete></transp><pag><detPag><indPag>0</indPag><tPag>20</tPag><vPag>60.00</vPag></detPag></pag><infAdic><infCpl>PDV A&#xE7;aiteria</infCpl></infAdic></infNFe><infNFeSupl><qrCode>https://sistemas.sefaz.am.gov.br/nfceweb-hom/consultarNFCe.jsp?p=13260359598453000104650010000000321826403166|2|2|2|796AA863B1FAC63D0A3F5C9BB8838D8EBB57669B</qrCode><urlChave>www.sefaz.am.gov.br/nfce/consulta</urlChave></infNFeSupl><Signature xmlns=\"http://www.w3.org/2000/09/xmldsig#\"><SignedInfo><CanonicalizationMethod Algorithm=\"http://www.w3.org/TR/2001/REC-xml-c14n-20010315\"/><SignatureMethod Algorithm=\"http://www.w3.org/2000/09/xmldsig#rsa-sha1\"/><Reference URI=\"#NFe13260359598453000104650010000000321826403166\"><Transforms><Transform Algorithm=\"http://www.w3.org/2000/09/xmldsig#enveloped-signature\"/><Transform Algorithm=\"http://www.w3.org/TR/2001/REC-xml-c14n-20010315\"/></Transforms><DigestMethod Algorithm=\"http://www.w3.org/2000/09/xmldsig#sha1\"/><DigestValue>jSj9NbqOWWFhVpntVtyBiQzNiVQ=</DigestValue></Reference></SignedInfo><SignatureValue>VSI0hIEhyYZ4+z/CM9h6G9B3nSyWHpIKRrYAxWjEuA9UYKalUVGEPD6c5b/xsyKoBQHVLYgqt/tuBCCS2bvQaZUwOPEDGYHptkQ6wXIXEBJzuRSEqsawHIR+VabWjAl4yifbM9I4/tgn4Q+WNERVO+lUHk+xggeYfmLtYjmje4H7K9gfm5ZszK9eUEVJbRjJJpacsLgVfxyPmIeAeJo3YKS0p7y6mwHH6M03aY6SCQQGvyexYdl17FXkoyooAoTo3uQLZ9Z1Jsr1JNSRaJR4NwC6HVuSp4ZPpDT35JTsYhjiEixsxCzt9QEWBdKdsDkI2OFHCNhkJ3anjWiAehDeeQ==</SignatureValue><KeyInfo><X509Data><X509Certificate>MIIH6DCCBdCgAwIBAgIKHqWXJoQ1LKoMazANBgkqhkiG9w0BAQsFADBbMQswCQYDVQQGEwJCUjEWMBQGA1UECwwNQUMgU3luZ3VsYXJJRDETMBEGA1UECgwKSUNQLUJyYXNpbDEfMB0GA1UEAwwWQUMgU3luZ3VsYXJJRCBNdWx0aXBsYTAeFw0yNTA2MDYyMDMzMzVaFw0yNjA2MDYyMDMzMzVaMIHOMQswCQYDVQQGEwJCUjETMBEGA1UECgwKSUNQLUJyYXNpbDEiMCAGA1UECwwZQ2VydGlmaWNhZG8gRGlnaXRhbCBQSiBBMTETMBEGA1UECwwKUHJlc2VuY2lhbDEXMBUGA1UECwwONDU2MTYzMDkwMDAxNDkxHzAdBgNVBAsMFkFDIFN5bmd1bGFySUQgTXVsdGlwbGExNzA1BgNVBAMMLlBBUEFHQUlPIENPTUVSQ0lPIERFIE1PVE9TIExUREE6NTk1OTg0NTMwMDAxMDQwggEiMA0GCSqGSIb3DQEBAQUAA4IBDwAwggEKAoIBAQC4B4OKj+3kr6hnMnIFUA43tacQhEJmlvGAKVoXSiBo+30gia2+nmAly72AhkoWVIEO2q+I5o5RNgEA3jegdKJCL0jvFiJK/xPfDueVmt/3E/9N+jhOSaXScEDFtrPF6/nlkiKmrlgYyst/uWSCtg3fYzu4BfCTHJ1LL5nuoP4i2FrYaxunvwpg+NzSwvpXeWxBg2UOYRYC+LM6bMJluy+CoQzNKt2RoD8ljHdpzHY10bSL5jkLQOxUCE52SSrDrMD0HmKy6oylYL7xKLbuhiZIgCODNs6mS8bX19mgTZQ7PXs0seuiFuP+M2++rRlQyg2skdwMRDLBykJQSHWs0DP5AgMBAAGjggM4MIIDNDAOBgNVHQ8BAf8EBAMCBeAwHQYDVR0lBBYwFAYIKwYBBQUHAwQGCCsGAQUFBwMCMAkGA1UdEwQCMAAwHwYDVR0jBBgwFoAUk+H/fh3l9eRN4TliiyFpleavchYwHQYDVR0OBBYEFGxSV0SZ6AU0rrnMDKuDbgUDLeQeMH8GCCsGAQUFBwEBBHMwcTBvBggrBgEFBQcwAoZjaHR0cDovL3N5bmd1bGFyaWQuY29tLmJyL3JlcG9zaXRvcmlvL2FjLXN5bmd1bGFyaWQtbXVsdGlwbGEvY2VydGlmaWNhZG9zL2FjLXN5bmd1bGFyaWQtbXVsdGlwbGEucDdiMIGCBgNVHSAEezB5MHcGB2BMAQIBgQUwbDBqBggrBgEFBQcCARZeaHR0cDovL3N5bmd1bGFyaWQuY29tLmJyL3JlcG9zaXRvcmlvL2FjLXN5bmd1bGFyaWQtbXVsdGlwbGEvZHBjL2RwYy1hYy1zeW5ndWxhcklELW11bHRpcGxhLnBkZjCBzAYDVR0RBIHEMIHBoCoGBWBMAQMCoCEEH0xBWkFSTyBDT1JERUlSTyBERSBBTE1FSURBIE5FVE+gGQYFYEwBAwOgEAQONTk1OTg0NTMwMDAxMDSgQgYFYEwBAwSgOQQ3MDQwMjE5OTUwMzM5NTQ2MDI2NjAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMKAXBgVgTAEDB6AOBAwwMDAwMDAwMDAwMDCBG0NPUkRFSVJPTEFaQVJPODcwQEdNQUlMLkNPTTCB4gYDVR0fBIHaMIHXMG+gbaBrhmlodHRwOi8vaWNwLWJyYXNpbC5zeW5ndWxhcmlkLmNvbS5ici9yZXBvc2l0b3Jpby9hYy1zeW5ndWxhcmlkLW11bHRpcGxhL2xjci9sY3ItYWMtc3luZ3VsYXJpZC1tdWx0aXBsYS5jcmwwZKBioGCGXmh0dHA6Ly9zeW5ndWxhcmlkLmNvbS5ici9yZXBvc2l0b3Jpby9hYy1zeW5ndWxhcmlkLW11bHRpcGxhL2xjci9sY3ItYWMtc3luZ3VsYXJpZC1tdWx0aXBsYS5jcmwwDQYJKoZIhvcNAQELBQADggIBACI8nwyikS2ZY4yjPZ68tXNvHbyfoCizJsYbqLavXaIzI9D0VBqEyJAvpZVA8JQQ4qMm2rZkZmZjm4ucYTNezZf7OLX5STql96fOLYgFXHCpVMfG251bhsLpwBJmgES9FtG7+DrdLkQ+FAX7vkVgNKT70AQnDwY9cSM2ghSLiv+fu8ubLwk4rlrc7y9y6QGAB4kvbHY5J8yWdfMnQMPF/raR7mFuWG1co+UjXDm4E69K3VoFy3B9Jq1u2sUdryVjVaamOlLwhoFz8UPJm9Bjn1E1OiuSc2MJ1gUGPzwqvsNgasiq7D9/XuMUCeYia2BBpdy7Z+LKDKO2vtFZ11N4O+gbXLykxqnuwn8Iiigqu7+txxRPj/v2+i8ckWUyONx3zRZvlHXsfk4gTyLqK1+VJ0NqIK1A11DQLc5uaTPQe+DjmqSNFbDKsoKjhySK0Fs7yT1FXDWyvqMNmJkl/HVdVbtQesk58PvGlpTnDunlJDCL5Lcyfm59yFc5bBF9HBS5xMSfG7Vnk0JvQUYpkdf1NOI5PzbJhsN/TYvdDpgTDCJ9DILMrnqovzvGhkG46R8sQIhF93HygHgXrHRSSBySv0QnDbgkv8tnu0gXQRyzadWVD5nBQakzQVIbWzM0h5DF2n300mDE+fGhqcDz2iXqdCNmuVwurA/pc4ZIJyvfMj/Q</X509Certificate></X509Data></KeyInfo></Signature></NFe><protNFe versao=\"4.00\"><infProt><tpAmb>2</tpAmb><verAplic>AM3.10-4.00</verAplic><chNFe>13260359598453000104650010000000321826403166</chNFe><dhRecbto>2026-03-12T09:46:02-04:00</dhRecbto><cStat>373</cStat><xMotivo>Rejeicao: Descricao do primeiro item diferente de NOTA FISCAL EMITIDA EM AMBIENTE DE HOMOLOGACAO - SEM VALOR FISCAL</xMotivo></infProt></protNFe></nfeProc>', '<?xml version=\"1.0\"?><NFe xmlns=\"http://www.portalfiscal.inf.br/nfe\"><infNFe Id=\"NFe13260359598453000104650010000000321826403166\" versao=\"4.00\"><ide><cUF>13</cUF><cNF>82640316</cNF><natOp>VENDA</natOp><mod>65</mod><serie>1</serie><nNF>32</nNF><dhEmi>2026-03-12T09:46:01-04:00</dhEmi><tpNF>1</tpNF><idDest>1</idDest><cMunFG>1301209</cMunFG><tpImp>4</tpImp><tpEmis>1</tpEmis><cDV>6</cDV><tpAmb>2</tpAmb><finNFe>1</finNFe><indFinal>1</indFinal><indPres>1</indPres><procEmi>0</procEmi><verProc>PDV-ACAI-1.0</verProc></ide><emit><CNPJ>59598453000104</CNPJ><xNome>PAPAGAIO COMERCIO DE MOTOS LTDA</xNome><xFant>PAPAGAIO MOTOS</xFant><enderEmit><xLgr>PADRE VICENTE NOGUEIRA</xLgr><nro>149</nro><xBairro>ITAMARATI</xBairro><cMun>1301209</cMun><xMun>COARI</xMun><UF>AM</UF><CEP>69460000</CEP><cPais>1058</cPais><xPais>Brasil</xPais><fone>9791979595</fone></enderEmit><IE>054756448</IE><CRT>3</CRT></emit><det nItem=\"1\"><prod><cProd>1</cProd><cEAN>SEM GTIN</cEAN><xProd>Buzina 12v c100</xProd><NCM>85123000</NCM><CFOP>5102</CFOP><uCom>UN</uCom><qCom>1.000</qCom><vUnCom>60.00</vUnCom><vProd>60.00</vProd><cEANTrib>SEM GTIN</cEANTrib><uTrib>UN</uTrib><qTrib>1.000</qTrib><vUnTrib>60.00</vUnTrib><indTot>1</indTot></prod><imposto><ICMS><ICMSSN102><orig>0</orig><CSOSN>102</CSOSN></ICMSSN102></ICMS><PIS><PISNT><CST>07</CST></PISNT></PIS><COFINS><COFINSNT><CST>07</CST></COFINSNT></COFINS></imposto></det><total><ICMSTot><vBC>0.00</vBC><vICMS>0.00</vICMS><vICMSDeson>0.00</vICMSDeson><vFCP>0.00</vFCP><vBCST>0.00</vBCST><vST>0.00</vST><vFCPST>0.00</vFCPST><vFCPSTRet>0.00</vFCPSTRet><vProd>60.00</vProd><vFrete>0.00</vFrete><vSeg>0.00</vSeg><vDesc>0.00</vDesc><vII>0.00</vII><vIPI>0.00</vIPI><vIPIDevol>0.00</vIPIDevol><vPIS>0.00</vPIS><vCOFINS>0.00</vCOFINS><vOutro>0.00</vOutro><vNF>60.00</vNF></ICMSTot></total><transp><modFrete>9</modFrete></transp><pag><detPag><indPag>0</indPag><tPag>20</tPag><vPag>60.00</vPag></detPag></pag><infAdic><infCpl>PDV A&#xE7;aiteria</infCpl></infAdic></infNFe><infNFeSupl><qrCode>https://sistemas.sefaz.am.gov.br/nfceweb-hom/consultarNFCe.jsp?p=13260359598453000104650010000000321826403166|2|2|2|796AA863B1FAC63D0A3F5C9BB8838D8EBB57669B</qrCode><urlChave>www.sefaz.am.gov.br/nfce/consulta</urlChave></infNFeSupl><Signature xmlns=\"http://www.w3.org/2000/09/xmldsig#\"><SignedInfo><CanonicalizationMethod Algorithm=\"http://www.w3.org/TR/2001/REC-xml-c14n-20010315\"/><SignatureMethod Algorithm=\"http://www.w3.org/2000/09/xmldsig#rsa-sha1\"/><Reference URI=\"#NFe13260359598453000104650010000000321826403166\"><Transforms><Transform Algorithm=\"http://www.w3.org/2000/09/xmldsig#enveloped-signature\"/><Transform Algorithm=\"http://www.w3.org/TR/2001/REC-xml-c14n-20010315\"/></Transforms><DigestMethod Algorithm=\"http://www.w3.org/2000/09/xmldsig#sha1\"/><DigestValue>jSj9NbqOWWFhVpntVtyBiQzNiVQ=</DigestValue></Reference></SignedInfo><SignatureValue>VSI0hIEhyYZ4+z/CM9h6G9B3nSyWHpIKRrYAxWjEuA9UYKalUVGEPD6c5b/xsyKoBQHVLYgqt/tuBCCS2bvQaZUwOPEDGYHptkQ6wXIXEBJzuRSEqsawHIR+VabWjAl4yifbM9I4/tgn4Q+WNERVO+lUHk+xggeYfmLtYjmje4H7K9gfm5ZszK9eUEVJbRjJJpacsLgVfxyPmIeAeJo3YKS0p7y6mwHH6M03aY6SCQQGvyexYdl17FXkoyooAoTo3uQLZ9Z1Jsr1JNSRaJR4NwC6HVuSp4ZPpDT35JTsYhjiEixsxCzt9QEWBdKdsDkI2OFHCNhkJ3anjWiAehDeeQ==</SignatureValue><KeyInfo><X509Data><X509Certificate>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</X509Certificate></X509Data></KeyInfo></Signature></NFe>', '<?xml version=\'1.0\' encoding=\'utf-8\'?><soapenv:Envelope xmlns:soapenv=\"http://www.w3.org/2003/05/soap-envelope\"><soapenv:Body><nfeResultMsg xmlns=\"http://www.portalfiscal.inf.br/nfe/wsdl/NFeAutorizacao4\"><retEnviNFe xmlns=\"http://www.portalfiscal.inf.br/nfe\" versao=\"4.00\"><tpAmb>2</tpAmb><verAplic>AM3.10-4.00</verAplic><cStat>104</cStat><xMotivo>Lote processado</xMotivo><cUF>13</cUF><dhRecbto>2026-03-12T09:46:02-04:00</dhRecbto><protNFe versao=\"4.00\"><infProt><tpAmb>2</tpAmb><verAplic>AM3.10-4.00</verAplic><chNFe>13260359598453000104650010000000321826403166</chNFe><dhRecbto>2026-03-12T09:46:02-04:00</dhRecbto><cStat>373</cStat><xMotivo>Rejeicao: Descricao do primeiro item diferente de NOTA FISCAL EMITIDA EM AMBIENTE DE HOMOLOGACAO - SEM VALOR FISCAL</xMotivo></infProt></protNFe></retEnviNFe></nfeResultMsg></soapenv:Body></soapenv:Envelope>', 60.00, 0.00, '{\"tPag\":\"20\"}', '2026-03-12 13:46:02'),
(20, '125', 99, 2, 1, 33, '13260359598453000104650010000000331761570672', NULL, '104', 'Lote processado', '<?xml version=\"1.0\" encoding=\"UTF-8\"?><nfeProc xmlns=\"http://www.portalfiscal.inf.br/nfe\" versao=\"4.00\"><NFe xmlns=\"http://www.portalfiscal.inf.br/nfe\"><infNFe Id=\"NFe13260359598453000104650010000000331761570672\" versao=\"4.00\"><ide><cUF>13</cUF><cNF>76157067</cNF><natOp>VENDA</natOp><mod>65</mod><serie>1</serie><nNF>33</nNF><dhEmi>2026-03-12T09:46:30-04:00</dhEmi><tpNF>1</tpNF><idDest>1</idDest><cMunFG>1301209</cMunFG><tpImp>4</tpImp><tpEmis>1</tpEmis><cDV>2</cDV><tpAmb>2</tpAmb><finNFe>1</finNFe><indFinal>1</indFinal><indPres>1</indPres><procEmi>0</procEmi><verProc>PDV-ACAI-1.0</verProc></ide><emit><CNPJ>59598453000104</CNPJ><xNome>PAPAGAIO COMERCIO DE MOTOS LTDA</xNome><xFant>PAPAGAIO MOTOS</xFant><enderEmit><xLgr>PADRE VICENTE NOGUEIRA</xLgr><nro>149</nro><xBairro>ITAMARATI</xBairro><cMun>1301209</cMun><xMun>COARI</xMun><UF>AM</UF><CEP>69460000</CEP><cPais>1058</cPais><xPais>Brasil</xPais><fone>9791979595</fone></enderEmit><IE>054756448</IE><CRT>3</CRT></emit><det nItem=\"1\"><prod><cProd>1</cProd><cEAN>SEM GTIN</cEAN><xProd>Buzina 12v c100</xProd><NCM>85123000</NCM><CFOP>5102</CFOP><uCom>UN</uCom><qCom>1.000</qCom><vUnCom>60.00</vUnCom><vProd>60.00</vProd><cEANTrib>SEM GTIN</cEANTrib><uTrib>UN</uTrib><qTrib>1.000</qTrib><vUnTrib>60.00</vUnTrib><indTot>1</indTot></prod><imposto><ICMS><ICMSSN102><orig>0</orig><CSOSN>102</CSOSN></ICMSSN102></ICMS><PIS><PISNT><CST>07</CST></PISNT></PIS><COFINS><COFINSNT><CST>07</CST></COFINSNT></COFINS></imposto></det><total><ICMSTot><vBC>0.00</vBC><vICMS>0.00</vICMS><vICMSDeson>0.00</vICMSDeson><vFCP>0.00</vFCP><vBCST>0.00</vBCST><vST>0.00</vST><vFCPST>0.00</vFCPST><vFCPSTRet>0.00</vFCPSTRet><vProd>60.00</vProd><vFrete>0.00</vFrete><vSeg>0.00</vSeg><vDesc>0.00</vDesc><vII>0.00</vII><vIPI>0.00</vIPI><vIPIDevol>0.00</vIPIDevol><vPIS>0.00</vPIS><vCOFINS>0.00</vCOFINS><vOutro>0.00</vOutro><vNF>60.00</vNF></ICMSTot></total><transp><modFrete>9</modFrete></transp><pag><detPag><indPag>0</indPag><tPag>20</tPag><vPag>60.00</vPag></detPag></pag><infAdic><infCpl>PDV A&#xE7;aiteria</infCpl></infAdic></infNFe><infNFeSupl><qrCode>https://sistemas.sefaz.am.gov.br/nfceweb-hom/consultarNFCe.jsp?p=13260359598453000104650010000000331761570672|2|2|2|DF832DB020A2ED1323FE2D2E2C1869E7F869F8CD</qrCode><urlChave>www.sefaz.am.gov.br/nfce/consulta</urlChave></infNFeSupl><Signature xmlns=\"http://www.w3.org/2000/09/xmldsig#\"><SignedInfo><CanonicalizationMethod Algorithm=\"http://www.w3.org/TR/2001/REC-xml-c14n-20010315\"/><SignatureMethod Algorithm=\"http://www.w3.org/2000/09/xmldsig#rsa-sha1\"/><Reference URI=\"#NFe13260359598453000104650010000000331761570672\"><Transforms><Transform Algorithm=\"http://www.w3.org/2000/09/xmldsig#enveloped-signature\"/><Transform Algorithm=\"http://www.w3.org/TR/2001/REC-xml-c14n-20010315\"/></Transforms><DigestMethod Algorithm=\"http://www.w3.org/2000/09/xmldsig#sha1\"/><DigestValue>CXoepQ+RqkQ9C3Rue5nS8nuwBc0=</DigestValue></Reference></SignedInfo><SignatureValue>MvihDpn27/+4rlFgcC9VVAhAcZETSUS9QfAlLOMwsL+/4orCxb6WrTN5ka0UtJweH7USotwGGmLe8bCxVBecvO0u5DCsdC8GnYx/syBCkSuubWq3a2m1gox7XRa8kbwcUaJGoojpgWKUpQM2MTiCNnAldqrOYQewPhaqEvVo3yNIyiyxSrbRk/9XVzYzEHupwsDAKJOmcWKViUz2h2mP+tRspvW2ni6TYEIT3UKtInCADRtXBYfG1p1s1TF5FPNjvN95YlRLVOFaLCPIqjHXuEeKG79VQUVyV0Zo4kOe1ATLRoeg6xXghE82ys+gvZjZTlfkPci9mBjEWZxW9vYtYg==</SignatureValue><KeyInfo><X509Data><X509Certificate>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</X509Certificate></X509Data></KeyInfo></Signature></NFe><protNFe versao=\"4.00\"><infProt><tpAmb>2</tpAmb><verAplic>AM3.10-4.00</verAplic><chNFe>13260359598453000104650010000000331761570672</chNFe><dhRecbto>2026-03-12T09:46:30-04:00</dhRecbto><cStat>373</cStat><xMotivo>Rejeicao: Descricao do primeiro item diferente de NOTA FISCAL EMITIDA EM AMBIENTE DE HOMOLOGACAO - SEM VALOR FISCAL</xMotivo></infProt></protNFe></nfeProc>', '<?xml version=\"1.0\"?><NFe xmlns=\"http://www.portalfiscal.inf.br/nfe\"><infNFe Id=\"NFe13260359598453000104650010000000331761570672\" versao=\"4.00\"><ide><cUF>13</cUF><cNF>76157067</cNF><natOp>VENDA</natOp><mod>65</mod><serie>1</serie><nNF>33</nNF><dhEmi>2026-03-12T09:46:30-04:00</dhEmi><tpNF>1</tpNF><idDest>1</idDest><cMunFG>1301209</cMunFG><tpImp>4</tpImp><tpEmis>1</tpEmis><cDV>2</cDV><tpAmb>2</tpAmb><finNFe>1</finNFe><indFinal>1</indFinal><indPres>1</indPres><procEmi>0</procEmi><verProc>PDV-ACAI-1.0</verProc></ide><emit><CNPJ>59598453000104</CNPJ><xNome>PAPAGAIO COMERCIO DE MOTOS LTDA</xNome><xFant>PAPAGAIO MOTOS</xFant><enderEmit><xLgr>PADRE VICENTE NOGUEIRA</xLgr><nro>149</nro><xBairro>ITAMARATI</xBairro><cMun>1301209</cMun><xMun>COARI</xMun><UF>AM</UF><CEP>69460000</CEP><cPais>1058</cPais><xPais>Brasil</xPais><fone>9791979595</fone></enderEmit><IE>054756448</IE><CRT>3</CRT></emit><det nItem=\"1\"><prod><cProd>1</cProd><cEAN>SEM GTIN</cEAN><xProd>Buzina 12v c100</xProd><NCM>85123000</NCM><CFOP>5102</CFOP><uCom>UN</uCom><qCom>1.000</qCom><vUnCom>60.00</vUnCom><vProd>60.00</vProd><cEANTrib>SEM GTIN</cEANTrib><uTrib>UN</uTrib><qTrib>1.000</qTrib><vUnTrib>60.00</vUnTrib><indTot>1</indTot></prod><imposto><ICMS><ICMSSN102><orig>0</orig><CSOSN>102</CSOSN></ICMSSN102></ICMS><PIS><PISNT><CST>07</CST></PISNT></PIS><COFINS><COFINSNT><CST>07</CST></COFINSNT></COFINS></imposto></det><total><ICMSTot><vBC>0.00</vBC><vICMS>0.00</vICMS><vICMSDeson>0.00</vICMSDeson><vFCP>0.00</vFCP><vBCST>0.00</vBCST><vST>0.00</vST><vFCPST>0.00</vFCPST><vFCPSTRet>0.00</vFCPSTRet><vProd>60.00</vProd><vFrete>0.00</vFrete><vSeg>0.00</vSeg><vDesc>0.00</vDesc><vII>0.00</vII><vIPI>0.00</vIPI><vIPIDevol>0.00</vIPIDevol><vPIS>0.00</vPIS><vCOFINS>0.00</vCOFINS><vOutro>0.00</vOutro><vNF>60.00</vNF></ICMSTot></total><transp><modFrete>9</modFrete></transp><pag><detPag><indPag>0</indPag><tPag>20</tPag><vPag>60.00</vPag></detPag></pag><infAdic><infCpl>PDV A&#xE7;aiteria</infCpl></infAdic></infNFe><infNFeSupl><qrCode>https://sistemas.sefaz.am.gov.br/nfceweb-hom/consultarNFCe.jsp?p=13260359598453000104650010000000331761570672|2|2|2|DF832DB020A2ED1323FE2D2E2C1869E7F869F8CD</qrCode><urlChave>www.sefaz.am.gov.br/nfce/consulta</urlChave></infNFeSupl><Signature xmlns=\"http://www.w3.org/2000/09/xmldsig#\"><SignedInfo><CanonicalizationMethod Algorithm=\"http://www.w3.org/TR/2001/REC-xml-c14n-20010315\"/><SignatureMethod Algorithm=\"http://www.w3.org/2000/09/xmldsig#rsa-sha1\"/><Reference URI=\"#NFe13260359598453000104650010000000331761570672\"><Transforms><Transform Algorithm=\"http://www.w3.org/2000/09/xmldsig#enveloped-signature\"/><Transform Algorithm=\"http://www.w3.org/TR/2001/REC-xml-c14n-20010315\"/></Transforms><DigestMethod Algorithm=\"http://www.w3.org/2000/09/xmldsig#sha1\"/><DigestValue>CXoepQ+RqkQ9C3Rue5nS8nuwBc0=</DigestValue></Reference></SignedInfo><SignatureValue>MvihDpn27/+4rlFgcC9VVAhAcZETSUS9QfAlLOMwsL+/4orCxb6WrTN5ka0UtJweH7USotwGGmLe8bCxVBecvO0u5DCsdC8GnYx/syBCkSuubWq3a2m1gox7XRa8kbwcUaJGoojpgWKUpQM2MTiCNnAldqrOYQewPhaqEvVo3yNIyiyxSrbRk/9XVzYzEHupwsDAKJOmcWKViUz2h2mP+tRspvW2ni6TYEIT3UKtInCADRtXBYfG1p1s1TF5FPNjvN95YlRLVOFaLCPIqjHXuEeKG79VQUVyV0Zo4kOe1ATLRoeg6xXghE82ys+gvZjZTlfkPci9mBjEWZxW9vYtYg==</SignatureValue><KeyInfo><X509Data><X509Certificate>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</X509Certificate></X509Data></KeyInfo></Signature></NFe>', '<?xml version=\'1.0\' encoding=\'utf-8\'?><soapenv:Envelope xmlns:soapenv=\"http://www.w3.org/2003/05/soap-envelope\"><soapenv:Body><nfeResultMsg xmlns=\"http://www.portalfiscal.inf.br/nfe/wsdl/NFeAutorizacao4\"><retEnviNFe xmlns=\"http://www.portalfiscal.inf.br/nfe\" versao=\"4.00\"><tpAmb>2</tpAmb><verAplic>AM3.10-4.00</verAplic><cStat>104</cStat><xMotivo>Lote processado</xMotivo><cUF>13</cUF><dhRecbto>2026-03-12T09:46:30-04:00</dhRecbto><protNFe versao=\"4.00\"><infProt><tpAmb>2</tpAmb><verAplic>AM3.10-4.00</verAplic><chNFe>13260359598453000104650010000000331761570672</chNFe><dhRecbto>2026-03-12T09:46:30-04:00</dhRecbto><cStat>373</cStat><xMotivo>Rejeicao: Descricao do primeiro item diferente de NOTA FISCAL EMITIDA EM AMBIENTE DE HOMOLOGACAO - SEM VALOR FISCAL</xMotivo></infProt></protNFe></retEnviNFe></nfeResultMsg></soapenv:Body></soapenv:Envelope>', 60.00, 0.00, '{\"tPag\":\"20\"}', '2026-03-12 13:46:30'),
(21, '125', 100, 2, 1, 34, '13260359598453000104650010000000341305627648', NULL, '104', 'Lote processado', '<?xml version=\"1.0\" encoding=\"UTF-8\"?><nfeProc xmlns=\"http://www.portalfiscal.inf.br/nfe\" versao=\"4.00\"><NFe xmlns=\"http://www.portalfiscal.inf.br/nfe\"><infNFe Id=\"NFe13260359598453000104650010000000341305627648\" versao=\"4.00\"><ide><cUF>13</cUF><cNF>30562764</cNF><natOp>VENDA</natOp><mod>65</mod><serie>1</serie><nNF>34</nNF><dhEmi>2026-03-12T09:59:59-04:00</dhEmi><tpNF>1</tpNF><idDest>1</idDest><cMunFG>1301209</cMunFG><tpImp>4</tpImp><tpEmis>1</tpEmis><cDV>8</cDV><tpAmb>2</tpAmb><finNFe>1</finNFe><indFinal>1</indFinal><indPres>1</indPres><procEmi>0</procEmi><verProc>PDV-ACAI-1.0</verProc></ide><emit><CNPJ>59598453000104</CNPJ><xNome>PAPAGAIO COMERCIO DE MOTOS LTDA</xNome><xFant>PAPAGAIO MOTOS</xFant><enderEmit><xLgr>PADRE VICENTE NOGUEIRA</xLgr><nro>149</nro><xBairro>ITAMARATI</xBairro><cMun>1301209</cMun><xMun>COARI</xMun><UF>AM</UF><CEP>69460000</CEP><cPais>1058</cPais><xPais>Brasil</xPais><fone>9791979595</fone></enderEmit><IE>054756448</IE><CRT>3</CRT></emit><det nItem=\"1\"><prod><cProd>1</cProd><cEAN>SEM GTIN</cEAN><xProd>Buzina 12v c100</xProd><NCM>85123000</NCM><CFOP>5102</CFOP><uCom>UN</uCom><qCom>1.000</qCom><vUnCom>60.00</vUnCom><vProd>60.00</vProd><cEANTrib>SEM GTIN</cEANTrib><uTrib>UN</uTrib><qTrib>1.000</qTrib><vUnTrib>60.00</vUnTrib><indTot>1</indTot></prod><imposto><ICMS><ICMSSN102><orig>0</orig><CSOSN>102</CSOSN></ICMSSN102></ICMS><PIS><PISNT><CST>07</CST></PISNT></PIS><COFINS><COFINSNT><CST>07</CST></COFINSNT></COFINS></imposto></det><total><ICMSTot><vBC>0.00</vBC><vICMS>0.00</vICMS><vICMSDeson>0.00</vICMSDeson><vFCP>0.00</vFCP><vBCST>0.00</vBCST><vST>0.00</vST><vFCPST>0.00</vFCPST><vFCPSTRet>0.00</vFCPSTRet><vProd>60.00</vProd><vFrete>0.00</vFrete><vSeg>0.00</vSeg><vDesc>0.00</vDesc><vII>0.00</vII><vIPI>0.00</vIPI><vIPIDevol>0.00</vIPIDevol><vPIS>0.00</vPIS><vCOFINS>0.00</vCOFINS><vOutro>0.00</vOutro><vNF>60.00</vNF></ICMSTot></total><transp><modFrete>9</modFrete></transp><pag><detPag><indPag>0</indPag><tPag>20</tPag><vPag>60.00</vPag></detPag></pag><infAdic><infCpl>PDV A&#xE7;aiteria</infCpl></infAdic></infNFe><infNFeSupl><qrCode>https://sistemas.sefaz.am.gov.br/nfceweb-hom/consultarNFCe.jsp?p=13260359598453000104650010000000341305627648|2|2|2|3DE6797398773E064AA6454F244CB05BA9EA6EE0</qrCode><urlChave>www.sefaz.am.gov.br/nfce/consulta</urlChave></infNFeSupl><Signature xmlns=\"http://www.w3.org/2000/09/xmldsig#\"><SignedInfo><CanonicalizationMethod Algorithm=\"http://www.w3.org/TR/2001/REC-xml-c14n-20010315\"/><SignatureMethod Algorithm=\"http://www.w3.org/2000/09/xmldsig#rsa-sha1\"/><Reference URI=\"#NFe13260359598453000104650010000000341305627648\"><Transforms><Transform Algorithm=\"http://www.w3.org/2000/09/xmldsig#enveloped-signature\"/><Transform Algorithm=\"http://www.w3.org/TR/2001/REC-xml-c14n-20010315\"/></Transforms><DigestMethod Algorithm=\"http://www.w3.org/2000/09/xmldsig#sha1\"/><DigestValue>WelF+KFmDrhtSn7ncWuSH2CmqyU=</DigestValue></Reference></SignedInfo><SignatureValue>n4+x+Ey9VAPXxizP8vocgNQMEuvn79nNwD9FzNRrdgcdm55DzhjUrAs2QIFKC6fo2EfNoT2TLfx6gjY88IxJXLFO/KhOeUhMfV3CUqs/GYqUVmC4OrffaKwAFrRM/MeGbsvvo0Ac5HbS21aTHFcD4Yx5Ing4tm3h8naVo3ozAtEM98X0zY1Gk3DUfi5TTwG8HMDpnsCTcWs+4ptuo+RJliJS1PKCixlbCpSnI9rW5oPp/zW9NHRxKi09ABSHty7+n5o1UHeS7CzxecMhPsWmF8RPjpV2bIlloEw5/HNCUcU/vTK80p1a9lAYVPQgzV8J/07aFdo5XvlXbq82u/GYjA==</SignatureValue><KeyInfo><X509Data><X509Certificate>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</X509Certificate></X509Data></KeyInfo></Signature></NFe><protNFe versao=\"4.00\"><infProt><tpAmb>2</tpAmb><verAplic>AM3.10-4.00</verAplic><chNFe>13260359598453000104650010000000341305627648</chNFe><dhRecbto>2026-03-12T10:00:00-04:00</dhRecbto><cStat>373</cStat><xMotivo>Rejeicao: Descricao do primeiro item diferente de NOTA FISCAL EMITIDA EM AMBIENTE DE HOMOLOGACAO - SEM VALOR FISCAL</xMotivo></infProt></protNFe></nfeProc>', '<?xml version=\"1.0\"?><NFe xmlns=\"http://www.portalfiscal.inf.br/nfe\"><infNFe Id=\"NFe13260359598453000104650010000000341305627648\" versao=\"4.00\"><ide><cUF>13</cUF><cNF>30562764</cNF><natOp>VENDA</natOp><mod>65</mod><serie>1</serie><nNF>34</nNF><dhEmi>2026-03-12T09:59:59-04:00</dhEmi><tpNF>1</tpNF><idDest>1</idDest><cMunFG>1301209</cMunFG><tpImp>4</tpImp><tpEmis>1</tpEmis><cDV>8</cDV><tpAmb>2</tpAmb><finNFe>1</finNFe><indFinal>1</indFinal><indPres>1</indPres><procEmi>0</procEmi><verProc>PDV-ACAI-1.0</verProc></ide><emit><CNPJ>59598453000104</CNPJ><xNome>PAPAGAIO COMERCIO DE MOTOS LTDA</xNome><xFant>PAPAGAIO MOTOS</xFant><enderEmit><xLgr>PADRE VICENTE NOGUEIRA</xLgr><nro>149</nro><xBairro>ITAMARATI</xBairro><cMun>1301209</cMun><xMun>COARI</xMun><UF>AM</UF><CEP>69460000</CEP><cPais>1058</cPais><xPais>Brasil</xPais><fone>9791979595</fone></enderEmit><IE>054756448</IE><CRT>3</CRT></emit><det nItem=\"1\"><prod><cProd>1</cProd><cEAN>SEM GTIN</cEAN><xProd>Buzina 12v c100</xProd><NCM>85123000</NCM><CFOP>5102</CFOP><uCom>UN</uCom><qCom>1.000</qCom><vUnCom>60.00</vUnCom><vProd>60.00</vProd><cEANTrib>SEM GTIN</cEANTrib><uTrib>UN</uTrib><qTrib>1.000</qTrib><vUnTrib>60.00</vUnTrib><indTot>1</indTot></prod><imposto><ICMS><ICMSSN102><orig>0</orig><CSOSN>102</CSOSN></ICMSSN102></ICMS><PIS><PISNT><CST>07</CST></PISNT></PIS><COFINS><COFINSNT><CST>07</CST></COFINSNT></COFINS></imposto></det><total><ICMSTot><vBC>0.00</vBC><vICMS>0.00</vICMS><vICMSDeson>0.00</vICMSDeson><vFCP>0.00</vFCP><vBCST>0.00</vBCST><vST>0.00</vST><vFCPST>0.00</vFCPST><vFCPSTRet>0.00</vFCPSTRet><vProd>60.00</vProd><vFrete>0.00</vFrete><vSeg>0.00</vSeg><vDesc>0.00</vDesc><vII>0.00</vII><vIPI>0.00</vIPI><vIPIDevol>0.00</vIPIDevol><vPIS>0.00</vPIS><vCOFINS>0.00</vCOFINS><vOutro>0.00</vOutro><vNF>60.00</vNF></ICMSTot></total><transp><modFrete>9</modFrete></transp><pag><detPag><indPag>0</indPag><tPag>20</tPag><vPag>60.00</vPag></detPag></pag><infAdic><infCpl>PDV A&#xE7;aiteria</infCpl></infAdic></infNFe><infNFeSupl><qrCode>https://sistemas.sefaz.am.gov.br/nfceweb-hom/consultarNFCe.jsp?p=13260359598453000104650010000000341305627648|2|2|2|3DE6797398773E064AA6454F244CB05BA9EA6EE0</qrCode><urlChave>www.sefaz.am.gov.br/nfce/consulta</urlChave></infNFeSupl><Signature xmlns=\"http://www.w3.org/2000/09/xmldsig#\"><SignedInfo><CanonicalizationMethod Algorithm=\"http://www.w3.org/TR/2001/REC-xml-c14n-20010315\"/><SignatureMethod Algorithm=\"http://www.w3.org/2000/09/xmldsig#rsa-sha1\"/><Reference URI=\"#NFe13260359598453000104650010000000341305627648\"><Transforms><Transform Algorithm=\"http://www.w3.org/2000/09/xmldsig#enveloped-signature\"/><Transform Algorithm=\"http://www.w3.org/TR/2001/REC-xml-c14n-20010315\"/></Transforms><DigestMethod Algorithm=\"http://www.w3.org/2000/09/xmldsig#sha1\"/><DigestValue>WelF+KFmDrhtSn7ncWuSH2CmqyU=</DigestValue></Reference></SignedInfo><SignatureValue>n4+x+Ey9VAPXxizP8vocgNQMEuvn79nNwD9FzNRrdgcdm55DzhjUrAs2QIFKC6fo2EfNoT2TLfx6gjY88IxJXLFO/KhOeUhMfV3CUqs/GYqUVmC4OrffaKwAFrRM/MeGbsvvo0Ac5HbS21aTHFcD4Yx5Ing4tm3h8naVo3ozAtEM98X0zY1Gk3DUfi5TTwG8HMDpnsCTcWs+4ptuo+RJliJS1PKCixlbCpSnI9rW5oPp/zW9NHRxKi09ABSHty7+n5o1UHeS7CzxecMhPsWmF8RPjpV2bIlloEw5/HNCUcU/vTK80p1a9lAYVPQgzV8J/07aFdo5XvlXbq82u/GYjA==</SignatureValue><KeyInfo><X509Data><X509Certificate>MIIH6DCCBdCgAwIBAgIKHqWXJoQ1LKoMazANBgkqhkiG9w0BAQsFADBbMQswCQYDVQQGEwJCUjEWMBQGA1UECwwNQUMgU3luZ3VsYXJJRDETMBEGA1UECgwKSUNQLUJyYXNpbDEfMB0GA1UEAwwWQUMgU3luZ3VsYXJJRCBNdWx0aXBsYTAeFw0yNTA2MDYyMDMzMzVaFw0yNjA2MDYyMDMzMzVaMIHOMQswCQYDVQQGEwJCUjETMBEGA1UECgwKSUNQLUJyYXNpbDEiMCAGA1UECwwZQ2VydGlmaWNhZG8gRGlnaXRhbCBQSiBBMTETMBEGA1UECwwKUHJlc2VuY2lhbDEXMBUGA1UECwwONDU2MTYzMDkwMDAxNDkxHzAdBgNVBAsMFkFDIFN5bmd1bGFySUQgTXVsdGlwbGExNzA1BgNVBAMMLlBBUEFHQUlPIENPTUVSQ0lPIERFIE1PVE9TIExUREE6NTk1OTg0NTMwMDAxMDQwggEiMA0GCSqGSIb3DQEBAQUAA4IBDwAwggEKAoIBAQC4B4OKj+3kr6hnMnIFUA43tacQhEJmlvGAKVoXSiBo+30gia2+nmAly72AhkoWVIEO2q+I5o5RNgEA3jegdKJCL0jvFiJK/xPfDueVmt/3E/9N+jhOSaXScEDFtrPF6/nlkiKmrlgYyst/uWSCtg3fYzu4BfCTHJ1LL5nuoP4i2FrYaxunvwpg+NzSwvpXeWxBg2UOYRYC+LM6bMJluy+CoQzNKt2RoD8ljHdpzHY10bSL5jkLQOxUCE52SSrDrMD0HmKy6oylYL7xKLbuhiZIgCODNs6mS8bX19mgTZQ7PXs0seuiFuP+M2++rRlQyg2skdwMRDLBykJQSHWs0DP5AgMBAAGjggM4MIIDNDAOBgNVHQ8BAf8EBAMCBeAwHQYDVR0lBBYwFAYIKwYBBQUHAwQGCCsGAQUFBwMCMAkGA1UdEwQCMAAwHwYDVR0jBBgwFoAUk+H/fh3l9eRN4TliiyFpleavchYwHQYDVR0OBBYEFGxSV0SZ6AU0rrnMDKuDbgUDLeQeMH8GCCsGAQUFBwEBBHMwcTBvBggrBgEFBQcwAoZjaHR0cDovL3N5bmd1bGFyaWQuY29tLmJyL3JlcG9zaXRvcmlvL2FjLXN5bmd1bGFyaWQtbXVsdGlwbGEvY2VydGlmaWNhZG9zL2FjLXN5bmd1bGFyaWQtbXVsdGlwbGEucDdiMIGCBgNVHSAEezB5MHcGB2BMAQIBgQUwbDBqBggrBgEFBQcCARZeaHR0cDovL3N5bmd1bGFyaWQuY29tLmJyL3JlcG9zaXRvcmlvL2FjLXN5bmd1bGFyaWQtbXVsdGlwbGEvZHBjL2RwYy1hYy1zeW5ndWxhcklELW11bHRpcGxhLnBkZjCBzAYDVR0RBIHEMIHBoCoGBWBMAQMCoCEEH0xBWkFSTyBDT1JERUlSTyBERSBBTE1FSURBIE5FVE+gGQYFYEwBAwOgEAQONTk1OTg0NTMwMDAxMDSgQgYFYEwBAwSgOQQ3MDQwMjE5OTUwMzM5NTQ2MDI2NjAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMKAXBgVgTAEDB6AOBAwwMDAwMDAwMDAwMDCBG0NPUkRFSVJPTEFaQVJPODcwQEdNQUlMLkNPTTCB4gYDVR0fBIHaMIHXMG+gbaBrhmlodHRwOi8vaWNwLWJyYXNpbC5zeW5ndWxhcmlkLmNvbS5ici9yZXBvc2l0b3Jpby9hYy1zeW5ndWxhcmlkLW11bHRpcGxhL2xjci9sY3ItYWMtc3luZ3VsYXJpZC1tdWx0aXBsYS5jcmwwZKBioGCGXmh0dHA6Ly9zeW5ndWxhcmlkLmNvbS5ici9yZXBvc2l0b3Jpby9hYy1zeW5ndWxhcmlkLW11bHRpcGxhL2xjci9sY3ItYWMtc3luZ3VsYXJpZC1tdWx0aXBsYS5jcmwwDQYJKoZIhvcNAQELBQADggIBACI8nwyikS2ZY4yjPZ68tXNvHbyfoCizJsYbqLavXaIzI9D0VBqEyJAvpZVA8JQQ4qMm2rZkZmZjm4ucYTNezZf7OLX5STql96fOLYgFXHCpVMfG251bhsLpwBJmgES9FtG7+DrdLkQ+FAX7vkVgNKT70AQnDwY9cSM2ghSLiv+fu8ubLwk4rlrc7y9y6QGAB4kvbHY5J8yWdfMnQMPF/raR7mFuWG1co+UjXDm4E69K3VoFy3B9Jq1u2sUdryVjVaamOlLwhoFz8UPJm9Bjn1E1OiuSc2MJ1gUGPzwqvsNgasiq7D9/XuMUCeYia2BBpdy7Z+LKDKO2vtFZ11N4O+gbXLykxqnuwn8Iiigqu7+txxRPj/v2+i8ckWUyONx3zRZvlHXsfk4gTyLqK1+VJ0NqIK1A11DQLc5uaTPQe+DjmqSNFbDKsoKjhySK0Fs7yT1FXDWyvqMNmJkl/HVdVbtQesk58PvGlpTnDunlJDCL5Lcyfm59yFc5bBF9HBS5xMSfG7Vnk0JvQUYpkdf1NOI5PzbJhsN/TYvdDpgTDCJ9DILMrnqovzvGhkG46R8sQIhF93HygHgXrHRSSBySv0QnDbgkv8tnu0gXQRyzadWVD5nBQakzQVIbWzM0h5DF2n300mDE+fGhqcDz2iXqdCNmuVwurA/pc4ZIJyvfMj/Q</X509Certificate></X509Data></KeyInfo></Signature></NFe>', '<?xml version=\'1.0\' encoding=\'utf-8\'?><soapenv:Envelope xmlns:soapenv=\"http://www.w3.org/2003/05/soap-envelope\"><soapenv:Body><nfeResultMsg xmlns=\"http://www.portalfiscal.inf.br/nfe/wsdl/NFeAutorizacao4\"><retEnviNFe xmlns=\"http://www.portalfiscal.inf.br/nfe\" versao=\"4.00\"><tpAmb>2</tpAmb><verAplic>AM3.10-4.00</verAplic><cStat>104</cStat><xMotivo>Lote processado</xMotivo><cUF>13</cUF><dhRecbto>2026-03-12T10:00:00-04:00</dhRecbto><protNFe versao=\"4.00\"><infProt><tpAmb>2</tpAmb><verAplic>AM3.10-4.00</verAplic><chNFe>13260359598453000104650010000000341305627648</chNFe><dhRecbto>2026-03-12T10:00:00-04:00</dhRecbto><cStat>373</cStat><xMotivo>Rejeicao: Descricao do primeiro item diferente de NOTA FISCAL EMITIDA EM AMBIENTE DE HOMOLOGACAO - SEM VALOR FISCAL</xMotivo></infProt></protNFe></retEnviNFe></nfeResultMsg></soapenv:Body></soapenv:Envelope>', 60.00, 0.00, '{\"tPag\":\"20\"}', '2026-03-12 14:00:00');
INSERT INTO `nfce_emitidas` (`id`, `empresa_id`, `venda_id`, `ambiente`, `serie`, `numero`, `chave`, `protocolo`, `status_sefaz`, `mensagem`, `xml_nfeproc`, `xml_envio`, `xml_retorno`, `valor_total`, `valor_troco`, `tpag_json`, `created_at`) VALUES
(22, '125', 101, 2, 1, 35, '13260359598453000104650010000000351917641410', NULL, '104', 'Lote processado', '<?xml version=\"1.0\" encoding=\"UTF-8\"?><nfeProc xmlns=\"http://www.portalfiscal.inf.br/nfe\" versao=\"4.00\"><NFe xmlns=\"http://www.portalfiscal.inf.br/nfe\"><infNFe Id=\"NFe13260359598453000104650010000000351917641410\" versao=\"4.00\"><ide><cUF>13</cUF><cNF>91764141</cNF><natOp>VENDA</natOp><mod>65</mod><serie>1</serie><nNF>35</nNF><dhEmi>2026-03-12T10:01:33-04:00</dhEmi><tpNF>1</tpNF><idDest>1</idDest><cMunFG>1301209</cMunFG><tpImp>4</tpImp><tpEmis>1</tpEmis><cDV>0</cDV><tpAmb>2</tpAmb><finNFe>1</finNFe><indFinal>1</indFinal><indPres>1</indPres><procEmi>0</procEmi><verProc>PDV-ACAI-1.0</verProc></ide><emit><CNPJ>59598453000104</CNPJ><xNome>PAPAGAIO COMERCIO DE MOTOS LTDA</xNome><xFant>PAPAGAIO MOTOS</xFant><enderEmit><xLgr>PADRE VICENTE NOGUEIRA</xLgr><nro>149</nro><xBairro>ITAMARATI</xBairro><cMun>1301209</cMun><xMun>COARI</xMun><UF>AM</UF><CEP>69460000</CEP><cPais>1058</cPais><xPais>Brasil</xPais><fone>9791979595</fone></enderEmit><IE>054756448</IE><CRT>3</CRT></emit><det nItem=\"1\"><prod><cProd>1</cProd><cEAN>SEM GTIN</cEAN><xProd>Buzina 12v c100</xProd><NCM>85123000</NCM><CFOP>5102</CFOP><uCom>UN</uCom><qCom>1.000</qCom><vUnCom>60.00</vUnCom><vProd>60.00</vProd><cEANTrib>SEM GTIN</cEANTrib><uTrib>UN</uTrib><qTrib>1.000</qTrib><vUnTrib>60.00</vUnTrib><indTot>1</indTot></prod><imposto><ICMS><ICMSSN102><orig>0</orig><CSOSN>102</CSOSN></ICMSSN102></ICMS><PIS><PISNT><CST>07</CST></PISNT></PIS><COFINS><COFINSNT><CST>07</CST></COFINSNT></COFINS></imposto></det><total><ICMSTot><vBC>0.00</vBC><vICMS>0.00</vICMS><vICMSDeson>0.00</vICMSDeson><vFCP>0.00</vFCP><vBCST>0.00</vBCST><vST>0.00</vST><vFCPST>0.00</vFCPST><vFCPSTRet>0.00</vFCPSTRet><vProd>60.00</vProd><vFrete>0.00</vFrete><vSeg>0.00</vSeg><vDesc>0.00</vDesc><vII>0.00</vII><vIPI>0.00</vIPI><vIPIDevol>0.00</vIPIDevol><vPIS>0.00</vPIS><vCOFINS>0.00</vCOFINS><vOutro>0.00</vOutro><vNF>60.00</vNF></ICMSTot></total><transp><modFrete>9</modFrete></transp><pag><detPag><indPag>0</indPag><tPag>03</tPag><vPag>60.00</vPag><card><tpIntegra>2</tpIntegra></card></detPag></pag><infAdic><infCpl>PDV A&#xE7;aiteria</infCpl></infAdic></infNFe><infNFeSupl><qrCode>https://sistemas.sefaz.am.gov.br/nfceweb-hom/consultarNFCe.jsp?p=13260359598453000104650010000000351917641410|2|2|2|D74DC34B584E13358A514255352F8BBC88E8572D</qrCode><urlChave>www.sefaz.am.gov.br/nfce/consulta</urlChave></infNFeSupl><Signature xmlns=\"http://www.w3.org/2000/09/xmldsig#\"><SignedInfo><CanonicalizationMethod Algorithm=\"http://www.w3.org/TR/2001/REC-xml-c14n-20010315\"/><SignatureMethod Algorithm=\"http://www.w3.org/2000/09/xmldsig#rsa-sha1\"/><Reference URI=\"#NFe13260359598453000104650010000000351917641410\"><Transforms><Transform Algorithm=\"http://www.w3.org/2000/09/xmldsig#enveloped-signature\"/><Transform Algorithm=\"http://www.w3.org/TR/2001/REC-xml-c14n-20010315\"/></Transforms><DigestMethod Algorithm=\"http://www.w3.org/2000/09/xmldsig#sha1\"/><DigestValue>M8the1Ib+j6r4hgMEyDrWPaaWyU=</DigestValue></Reference></SignedInfo><SignatureValue>iGd6N3TYxZAJyljfb6eLiaCsJNNANdwgaHfWO9VLO5tJDeOO0G2pIFBRhfPsihByCt+1aitSqEM6fgmoyTUQJEBKCuOEvFSTrV6OmLUPNGJ213L05yMbfCB1Q9U7jKIWmaAZsmlkAnIVC/RujIb5HX0aPYr/Li3mUyzIqKjoZsHmgleCvl6L9dhs59HATRZKKm69oZ138J/Z2cZ50ctuwHz7+tBSDQSUl79uclGYI8ozTyi51SX7x9dwd7GQRonUPYJ63qOO7Lk/lQutI2bUGKGTptxuKNh8TWaT3YfDaO6UkY0BsxsqkaX+gNcC6MLg2Jh6EDwTC2WcKx1X0rg/0Q==</SignatureValue><KeyInfo><X509Data><X509Certificate>MIIH6DCCBdCgAwIBAgIKHqWXJoQ1LKoMazANBgkqhkiG9w0BAQsFADBbMQswCQYDVQQGEwJCUjEWMBQGA1UECwwNQUMgU3luZ3VsYXJJRDETMBEGA1UECgwKSUNQLUJyYXNpbDEfMB0GA1UEAwwWQUMgU3luZ3VsYXJJRCBNdWx0aXBsYTAeFw0yNTA2MDYyMDMzMzVaFw0yNjA2MDYyMDMzMzVaMIHOMQswCQYDVQQGEwJCUjETMBEGA1UECgwKSUNQLUJyYXNpbDEiMCAGA1UECwwZQ2VydGlmaWNhZG8gRGlnaXRhbCBQSiBBMTETMBEGA1UECwwKUHJlc2VuY2lhbDEXMBUGA1UECwwONDU2MTYzMDkwMDAxNDkxHzAdBgNVBAsMFkFDIFN5bmd1bGFySUQgTXVsdGlwbGExNzA1BgNVBAMMLlBBUEFHQUlPIENPTUVSQ0lPIERFIE1PVE9TIExUREE6NTk1OTg0NTMwMDAxMDQwggEiMA0GCSqGSIb3DQEBAQUAA4IBDwAwggEKAoIBAQC4B4OKj+3kr6hnMnIFUA43tacQhEJmlvGAKVoXSiBo+30gia2+nmAly72AhkoWVIEO2q+I5o5RNgEA3jegdKJCL0jvFiJK/xPfDueVmt/3E/9N+jhOSaXScEDFtrPF6/nlkiKmrlgYyst/uWSCtg3fYzu4BfCTHJ1LL5nuoP4i2FrYaxunvwpg+NzSwvpXeWxBg2UOYRYC+LM6bMJluy+CoQzNKt2RoD8ljHdpzHY10bSL5jkLQOxUCE52SSrDrMD0HmKy6oylYL7xKLbuhiZIgCODNs6mS8bX19mgTZQ7PXs0seuiFuP+M2++rRlQyg2skdwMRDLBykJQSHWs0DP5AgMBAAGjggM4MIIDNDAOBgNVHQ8BAf8EBAMCBeAwHQYDVR0lBBYwFAYIKwYBBQUHAwQGCCsGAQUFBwMCMAkGA1UdEwQCMAAwHwYDVR0jBBgwFoAUk+H/fh3l9eRN4TliiyFpleavchYwHQYDVR0OBBYEFGxSV0SZ6AU0rrnMDKuDbgUDLeQeMH8GCCsGAQUFBwEBBHMwcTBvBggrBgEFBQcwAoZjaHR0cDovL3N5bmd1bGFyaWQuY29tLmJyL3JlcG9zaXRvcmlvL2FjLXN5bmd1bGFyaWQtbXVsdGlwbGEvY2VydGlmaWNhZG9zL2FjLXN5bmd1bGFyaWQtbXVsdGlwbGEucDdiMIGCBgNVHSAEezB5MHcGB2BMAQIBgQUwbDBqBggrBgEFBQcCARZeaHR0cDovL3N5bmd1bGFyaWQuY29tLmJyL3JlcG9zaXRvcmlvL2FjLXN5bmd1bGFyaWQtbXVsdGlwbGEvZHBjL2RwYy1hYy1zeW5ndWxhcklELW11bHRpcGxhLnBkZjCBzAYDVR0RBIHEMIHBoCoGBWBMAQMCoCEEH0xBWkFSTyBDT1JERUlSTyBERSBBTE1FSURBIE5FVE+gGQYFYEwBAwOgEAQONTk1OTg0NTMwMDAxMDSgQgYFYEwBAwSgOQQ3MDQwMjE5OTUwMzM5NTQ2MDI2NjAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMKAXBgVgTAEDB6AOBAwwMDAwMDAwMDAwMDCBG0NPUkRFSVJPTEFaQVJPODcwQEdNQUlMLkNPTTCB4gYDVR0fBIHaMIHXMG+gbaBrhmlodHRwOi8vaWNwLWJyYXNpbC5zeW5ndWxhcmlkLmNvbS5ici9yZXBvc2l0b3Jpby9hYy1zeW5ndWxhcmlkLW11bHRpcGxhL2xjci9sY3ItYWMtc3luZ3VsYXJpZC1tdWx0aXBsYS5jcmwwZKBioGCGXmh0dHA6Ly9zeW5ndWxhcmlkLmNvbS5ici9yZXBvc2l0b3Jpby9hYy1zeW5ndWxhcmlkLW11bHRpcGxhL2xjci9sY3ItYWMtc3luZ3VsYXJpZC1tdWx0aXBsYS5jcmwwDQYJKoZIhvcNAQELBQADggIBACI8nwyikS2ZY4yjPZ68tXNvHbyfoCizJsYbqLavXaIzI9D0VBqEyJAvpZVA8JQQ4qMm2rZkZmZjm4ucYTNezZf7OLX5STql96fOLYgFXHCpVMfG251bhsLpwBJmgES9FtG7+DrdLkQ+FAX7vkVgNKT70AQnDwY9cSM2ghSLiv+fu8ubLwk4rlrc7y9y6QGAB4kvbHY5J8yWdfMnQMPF/raR7mFuWG1co+UjXDm4E69K3VoFy3B9Jq1u2sUdryVjVaamOlLwhoFz8UPJm9Bjn1E1OiuSc2MJ1gUGPzwqvsNgasiq7D9/XuMUCeYia2BBpdy7Z+LKDKO2vtFZ11N4O+gbXLykxqnuwn8Iiigqu7+txxRPj/v2+i8ckWUyONx3zRZvlHXsfk4gTyLqK1+VJ0NqIK1A11DQLc5uaTPQe+DjmqSNFbDKsoKjhySK0Fs7yT1FXDWyvqMNmJkl/HVdVbtQesk58PvGlpTnDunlJDCL5Lcyfm59yFc5bBF9HBS5xMSfG7Vnk0JvQUYpkdf1NOI5PzbJhsN/TYvdDpgTDCJ9DILMrnqovzvGhkG46R8sQIhF93HygHgXrHRSSBySv0QnDbgkv8tnu0gXQRyzadWVD5nBQakzQVIbWzM0h5DF2n300mDE+fGhqcDz2iXqdCNmuVwurA/pc4ZIJyvfMj/Q</X509Certificate></X509Data></KeyInfo></Signature></NFe><protNFe versao=\"4.00\"><infProt><tpAmb>2</tpAmb><verAplic>AM3.10-4.00</verAplic><chNFe>13260359598453000104650010000000351917641410</chNFe><dhRecbto>2026-03-12T10:01:33-04:00</dhRecbto><cStat>373</cStat><xMotivo>Rejeicao: Descricao do primeiro item diferente de NOTA FISCAL EMITIDA EM AMBIENTE DE HOMOLOGACAO - SEM VALOR FISCAL</xMotivo></infProt></protNFe></nfeProc>', '<?xml version=\"1.0\"?><NFe xmlns=\"http://www.portalfiscal.inf.br/nfe\"><infNFe Id=\"NFe13260359598453000104650010000000351917641410\" versao=\"4.00\"><ide><cUF>13</cUF><cNF>91764141</cNF><natOp>VENDA</natOp><mod>65</mod><serie>1</serie><nNF>35</nNF><dhEmi>2026-03-12T10:01:33-04:00</dhEmi><tpNF>1</tpNF><idDest>1</idDest><cMunFG>1301209</cMunFG><tpImp>4</tpImp><tpEmis>1</tpEmis><cDV>0</cDV><tpAmb>2</tpAmb><finNFe>1</finNFe><indFinal>1</indFinal><indPres>1</indPres><procEmi>0</procEmi><verProc>PDV-ACAI-1.0</verProc></ide><emit><CNPJ>59598453000104</CNPJ><xNome>PAPAGAIO COMERCIO DE MOTOS LTDA</xNome><xFant>PAPAGAIO MOTOS</xFant><enderEmit><xLgr>PADRE VICENTE NOGUEIRA</xLgr><nro>149</nro><xBairro>ITAMARATI</xBairro><cMun>1301209</cMun><xMun>COARI</xMun><UF>AM</UF><CEP>69460000</CEP><cPais>1058</cPais><xPais>Brasil</xPais><fone>9791979595</fone></enderEmit><IE>054756448</IE><CRT>3</CRT></emit><det nItem=\"1\"><prod><cProd>1</cProd><cEAN>SEM GTIN</cEAN><xProd>Buzina 12v c100</xProd><NCM>85123000</NCM><CFOP>5102</CFOP><uCom>UN</uCom><qCom>1.000</qCom><vUnCom>60.00</vUnCom><vProd>60.00</vProd><cEANTrib>SEM GTIN</cEANTrib><uTrib>UN</uTrib><qTrib>1.000</qTrib><vUnTrib>60.00</vUnTrib><indTot>1</indTot></prod><imposto><ICMS><ICMSSN102><orig>0</orig><CSOSN>102</CSOSN></ICMSSN102></ICMS><PIS><PISNT><CST>07</CST></PISNT></PIS><COFINS><COFINSNT><CST>07</CST></COFINSNT></COFINS></imposto></det><total><ICMSTot><vBC>0.00</vBC><vICMS>0.00</vICMS><vICMSDeson>0.00</vICMSDeson><vFCP>0.00</vFCP><vBCST>0.00</vBCST><vST>0.00</vST><vFCPST>0.00</vFCPST><vFCPSTRet>0.00</vFCPSTRet><vProd>60.00</vProd><vFrete>0.00</vFrete><vSeg>0.00</vSeg><vDesc>0.00</vDesc><vII>0.00</vII><vIPI>0.00</vIPI><vIPIDevol>0.00</vIPIDevol><vPIS>0.00</vPIS><vCOFINS>0.00</vCOFINS><vOutro>0.00</vOutro><vNF>60.00</vNF></ICMSTot></total><transp><modFrete>9</modFrete></transp><pag><detPag><indPag>0</indPag><tPag>03</tPag><vPag>60.00</vPag><card><tpIntegra>2</tpIntegra></card></detPag></pag><infAdic><infCpl>PDV A&#xE7;aiteria</infCpl></infAdic></infNFe><infNFeSupl><qrCode>https://sistemas.sefaz.am.gov.br/nfceweb-hom/consultarNFCe.jsp?p=13260359598453000104650010000000351917641410|2|2|2|D74DC34B584E13358A514255352F8BBC88E8572D</qrCode><urlChave>www.sefaz.am.gov.br/nfce/consulta</urlChave></infNFeSupl><Signature xmlns=\"http://www.w3.org/2000/09/xmldsig#\"><SignedInfo><CanonicalizationMethod Algorithm=\"http://www.w3.org/TR/2001/REC-xml-c14n-20010315\"/><SignatureMethod Algorithm=\"http://www.w3.org/2000/09/xmldsig#rsa-sha1\"/><Reference URI=\"#NFe13260359598453000104650010000000351917641410\"><Transforms><Transform Algorithm=\"http://www.w3.org/2000/09/xmldsig#enveloped-signature\"/><Transform Algorithm=\"http://www.w3.org/TR/2001/REC-xml-c14n-20010315\"/></Transforms><DigestMethod Algorithm=\"http://www.w3.org/2000/09/xmldsig#sha1\"/><DigestValue>M8the1Ib+j6r4hgMEyDrWPaaWyU=</DigestValue></Reference></SignedInfo><SignatureValue>iGd6N3TYxZAJyljfb6eLiaCsJNNANdwgaHfWO9VLO5tJDeOO0G2pIFBRhfPsihByCt+1aitSqEM6fgmoyTUQJEBKCuOEvFSTrV6OmLUPNGJ213L05yMbfCB1Q9U7jKIWmaAZsmlkAnIVC/RujIb5HX0aPYr/Li3mUyzIqKjoZsHmgleCvl6L9dhs59HATRZKKm69oZ138J/Z2cZ50ctuwHz7+tBSDQSUl79uclGYI8ozTyi51SX7x9dwd7GQRonUPYJ63qOO7Lk/lQutI2bUGKGTptxuKNh8TWaT3YfDaO6UkY0BsxsqkaX+gNcC6MLg2Jh6EDwTC2WcKx1X0rg/0Q==</SignatureValue><KeyInfo><X509Data><X509Certificate>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</X509Certificate></X509Data></KeyInfo></Signature></NFe>', '<?xml version=\'1.0\' encoding=\'utf-8\'?><soapenv:Envelope xmlns:soapenv=\"http://www.w3.org/2003/05/soap-envelope\"><soapenv:Body><nfeResultMsg xmlns=\"http://www.portalfiscal.inf.br/nfe/wsdl/NFeAutorizacao4\"><retEnviNFe xmlns=\"http://www.portalfiscal.inf.br/nfe\" versao=\"4.00\"><tpAmb>2</tpAmb><verAplic>AM3.10-4.00</verAplic><cStat>104</cStat><xMotivo>Lote processado</xMotivo><cUF>13</cUF><dhRecbto>2026-03-12T10:01:33-04:00</dhRecbto><protNFe versao=\"4.00\"><infProt><tpAmb>2</tpAmb><verAplic>AM3.10-4.00</verAplic><chNFe>13260359598453000104650010000000351917641410</chNFe><dhRecbto>2026-03-12T10:01:33-04:00</dhRecbto><cStat>373</cStat><xMotivo>Rejeicao: Descricao do primeiro item diferente de NOTA FISCAL EMITIDA EM AMBIENTE DE HOMOLOGACAO - SEM VALOR FISCAL</xMotivo></infProt></protNFe></retEnviNFe></nfeResultMsg></soapenv:Body></soapenv:Envelope>', 60.00, 0.00, '{\"tPag\":\"03\"}', '2026-03-12 14:01:34'),
(23, '125', 102, 2, 1, 36, '13260359598453000104650010000000361185332233', NULL, '104', 'Lote processado', '<?xml version=\"1.0\" encoding=\"UTF-8\"?><nfeProc xmlns=\"http://www.portalfiscal.inf.br/nfe\" versao=\"4.00\"><NFe xmlns=\"http://www.portalfiscal.inf.br/nfe\"><infNFe Id=\"NFe13260359598453000104650010000000361185332233\" versao=\"4.00\"><ide><cUF>13</cUF><cNF>18533223</cNF><natOp>VENDA</natOp><mod>65</mod><serie>1</serie><nNF>36</nNF><dhEmi>2026-03-12T10:06:43-04:00</dhEmi><tpNF>1</tpNF><idDest>1</idDest><cMunFG>1301209</cMunFG><tpImp>4</tpImp><tpEmis>1</tpEmis><cDV>3</cDV><tpAmb>2</tpAmb><finNFe>1</finNFe><indFinal>1</indFinal><indPres>1</indPres><procEmi>0</procEmi><verProc>PDV-ACAI-1.0</verProc></ide><emit><CNPJ>59598453000104</CNPJ><xNome>PAPAGAIO COMERCIO DE MOTOS LTDA</xNome><xFant>PAPAGAIO MOTOS</xFant><enderEmit><xLgr>PADRE VICENTE NOGUEIRA</xLgr><nro>149</nro><xBairro>ITAMARATI</xBairro><cMun>1301209</cMun><xMun>COARI</xMun><UF>AM</UF><CEP>69460000</CEP><cPais>1058</cPais><xPais>Brasil</xPais><fone>9791979595</fone></enderEmit><IE>054756448</IE><CRT>3</CRT></emit><det nItem=\"1\"><prod><cProd>1</cProd><cEAN>SEM GTIN</cEAN><xProd>Buzina 12v c100</xProd><NCM>85123000</NCM><CFOP>5102</CFOP><uCom>UN</uCom><qCom>1.000</qCom><vUnCom>60.00</vUnCom><vProd>60.00</vProd><cEANTrib>SEM GTIN</cEANTrib><uTrib>UN</uTrib><qTrib>1.000</qTrib><vUnTrib>60.00</vUnTrib><indTot>1</indTot></prod><imposto><ICMS><ICMSSN102><orig>0</orig><CSOSN>102</CSOSN></ICMSSN102></ICMS><PIS><PISNT><CST>07</CST></PISNT></PIS><COFINS><COFINSNT><CST>07</CST></COFINSNT></COFINS></imposto></det><total><ICMSTot><vBC>0.00</vBC><vICMS>0.00</vICMS><vICMSDeson>0.00</vICMSDeson><vFCP>0.00</vFCP><vBCST>0.00</vBCST><vST>0.00</vST><vFCPST>0.00</vFCPST><vFCPSTRet>0.00</vFCPSTRet><vProd>60.00</vProd><vFrete>0.00</vFrete><vSeg>0.00</vSeg><vDesc>0.00</vDesc><vII>0.00</vII><vIPI>0.00</vIPI><vIPIDevol>0.00</vIPIDevol><vPIS>0.00</vPIS><vCOFINS>0.00</vCOFINS><vOutro>0.00</vOutro><vNF>60.00</vNF></ICMSTot></total><transp><modFrete>9</modFrete></transp><pag><detPag><indPag>0</indPag><tPag>15</tPag><vPag>60.00</vPag></detPag></pag><infAdic><infCpl>PDV A&#xE7;aiteria</infCpl></infAdic></infNFe><infNFeSupl><qrCode>https://sistemas.sefaz.am.gov.br/nfceweb-hom/consultarNFCe.jsp?p=13260359598453000104650010000000361185332233|2|2|2|E1920AAA5A8270287810908BE76A6A00584D18F2</qrCode><urlChave>www.sefaz.am.gov.br/nfce/consulta</urlChave></infNFeSupl><Signature xmlns=\"http://www.w3.org/2000/09/xmldsig#\"><SignedInfo><CanonicalizationMethod Algorithm=\"http://www.w3.org/TR/2001/REC-xml-c14n-20010315\"/><SignatureMethod Algorithm=\"http://www.w3.org/2000/09/xmldsig#rsa-sha1\"/><Reference URI=\"#NFe13260359598453000104650010000000361185332233\"><Transforms><Transform Algorithm=\"http://www.w3.org/2000/09/xmldsig#enveloped-signature\"/><Transform Algorithm=\"http://www.w3.org/TR/2001/REC-xml-c14n-20010315\"/></Transforms><DigestMethod Algorithm=\"http://www.w3.org/2000/09/xmldsig#sha1\"/><DigestValue>dEaVMY7crwZ65ywJU/pmumbE9uA=</DigestValue></Reference></SignedInfo><SignatureValue>T5Hv6JKWrYAsp0Ssg0u5Xk39mdTUjehRApOu7RGJp7KuTzX4BLOX43hc/Dv6xQKvQanJlaC81beYR7iyuiban/dWjEVWZfttUHTUa9aT3mD+zzF4SgFLbW2nLrZkXnmZ66FhG1zHOpcbRvh73ljN56tXAChWJD3B9bGRctbVFEZ1ldWzV5DGCX5lK76ZyEbxB59nzD2NFGzhy40Sv1dSmEPa85nm610pMZHcVawXdP9Y7XzxWJjbakNhSz/1r4acLOQHsvIn74LfzOPaA/R4CTRT7bIn1ULIn+zeU8mRrjFLO1PqyDsBwOJnRLY6R40Smqo7msOhYHBuP80jc77GEA==</SignatureValue><KeyInfo><X509Data><X509Certificate>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</X509Certificate></X509Data></KeyInfo></Signature></NFe><protNFe versao=\"4.00\"><infProt><tpAmb>2</tpAmb><verAplic>AM3.10-4.00</verAplic><chNFe>13260359598453000104650010000000361185332233</chNFe><dhRecbto>2026-03-12T10:06:43-04:00</dhRecbto><cStat>373</cStat><xMotivo>Rejeicao: Descricao do primeiro item diferente de NOTA FISCAL EMITIDA EM AMBIENTE DE HOMOLOGACAO - SEM VALOR FISCAL</xMotivo></infProt></protNFe></nfeProc>', '<?xml version=\"1.0\"?><NFe xmlns=\"http://www.portalfiscal.inf.br/nfe\"><infNFe Id=\"NFe13260359598453000104650010000000361185332233\" versao=\"4.00\"><ide><cUF>13</cUF><cNF>18533223</cNF><natOp>VENDA</natOp><mod>65</mod><serie>1</serie><nNF>36</nNF><dhEmi>2026-03-12T10:06:43-04:00</dhEmi><tpNF>1</tpNF><idDest>1</idDest><cMunFG>1301209</cMunFG><tpImp>4</tpImp><tpEmis>1</tpEmis><cDV>3</cDV><tpAmb>2</tpAmb><finNFe>1</finNFe><indFinal>1</indFinal><indPres>1</indPres><procEmi>0</procEmi><verProc>PDV-ACAI-1.0</verProc></ide><emit><CNPJ>59598453000104</CNPJ><xNome>PAPAGAIO COMERCIO DE MOTOS LTDA</xNome><xFant>PAPAGAIO MOTOS</xFant><enderEmit><xLgr>PADRE VICENTE NOGUEIRA</xLgr><nro>149</nro><xBairro>ITAMARATI</xBairro><cMun>1301209</cMun><xMun>COARI</xMun><UF>AM</UF><CEP>69460000</CEP><cPais>1058</cPais><xPais>Brasil</xPais><fone>9791979595</fone></enderEmit><IE>054756448</IE><CRT>3</CRT></emit><det nItem=\"1\"><prod><cProd>1</cProd><cEAN>SEM GTIN</cEAN><xProd>Buzina 12v c100</xProd><NCM>85123000</NCM><CFOP>5102</CFOP><uCom>UN</uCom><qCom>1.000</qCom><vUnCom>60.00</vUnCom><vProd>60.00</vProd><cEANTrib>SEM GTIN</cEANTrib><uTrib>UN</uTrib><qTrib>1.000</qTrib><vUnTrib>60.00</vUnTrib><indTot>1</indTot></prod><imposto><ICMS><ICMSSN102><orig>0</orig><CSOSN>102</CSOSN></ICMSSN102></ICMS><PIS><PISNT><CST>07</CST></PISNT></PIS><COFINS><COFINSNT><CST>07</CST></COFINSNT></COFINS></imposto></det><total><ICMSTot><vBC>0.00</vBC><vICMS>0.00</vICMS><vICMSDeson>0.00</vICMSDeson><vFCP>0.00</vFCP><vBCST>0.00</vBCST><vST>0.00</vST><vFCPST>0.00</vFCPST><vFCPSTRet>0.00</vFCPSTRet><vProd>60.00</vProd><vFrete>0.00</vFrete><vSeg>0.00</vSeg><vDesc>0.00</vDesc><vII>0.00</vII><vIPI>0.00</vIPI><vIPIDevol>0.00</vIPIDevol><vPIS>0.00</vPIS><vCOFINS>0.00</vCOFINS><vOutro>0.00</vOutro><vNF>60.00</vNF></ICMSTot></total><transp><modFrete>9</modFrete></transp><pag><detPag><indPag>0</indPag><tPag>15</tPag><vPag>60.00</vPag></detPag></pag><infAdic><infCpl>PDV A&#xE7;aiteria</infCpl></infAdic></infNFe><infNFeSupl><qrCode>https://sistemas.sefaz.am.gov.br/nfceweb-hom/consultarNFCe.jsp?p=13260359598453000104650010000000361185332233|2|2|2|E1920AAA5A8270287810908BE76A6A00584D18F2</qrCode><urlChave>www.sefaz.am.gov.br/nfce/consulta</urlChave></infNFeSupl><Signature xmlns=\"http://www.w3.org/2000/09/xmldsig#\"><SignedInfo><CanonicalizationMethod Algorithm=\"http://www.w3.org/TR/2001/REC-xml-c14n-20010315\"/><SignatureMethod Algorithm=\"http://www.w3.org/2000/09/xmldsig#rsa-sha1\"/><Reference URI=\"#NFe13260359598453000104650010000000361185332233\"><Transforms><Transform Algorithm=\"http://www.w3.org/2000/09/xmldsig#enveloped-signature\"/><Transform Algorithm=\"http://www.w3.org/TR/2001/REC-xml-c14n-20010315\"/></Transforms><DigestMethod Algorithm=\"http://www.w3.org/2000/09/xmldsig#sha1\"/><DigestValue>dEaVMY7crwZ65ywJU/pmumbE9uA=</DigestValue></Reference></SignedInfo><SignatureValue>T5Hv6JKWrYAsp0Ssg0u5Xk39mdTUjehRApOu7RGJp7KuTzX4BLOX43hc/Dv6xQKvQanJlaC81beYR7iyuiban/dWjEVWZfttUHTUa9aT3mD+zzF4SgFLbW2nLrZkXnmZ66FhG1zHOpcbRvh73ljN56tXAChWJD3B9bGRctbVFEZ1ldWzV5DGCX5lK76ZyEbxB59nzD2NFGzhy40Sv1dSmEPa85nm610pMZHcVawXdP9Y7XzxWJjbakNhSz/1r4acLOQHsvIn74LfzOPaA/R4CTRT7bIn1ULIn+zeU8mRrjFLO1PqyDsBwOJnRLY6R40Smqo7msOhYHBuP80jc77GEA==</SignatureValue><KeyInfo><X509Data><X509Certificate>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</X509Certificate></X509Data></KeyInfo></Signature></NFe>', '<?xml version=\'1.0\' encoding=\'utf-8\'?><soapenv:Envelope xmlns:soapenv=\"http://www.w3.org/2003/05/soap-envelope\"><soapenv:Body><nfeResultMsg xmlns=\"http://www.portalfiscal.inf.br/nfe/wsdl/NFeAutorizacao4\"><retEnviNFe xmlns=\"http://www.portalfiscal.inf.br/nfe\" versao=\"4.00\"><tpAmb>2</tpAmb><verAplic>AM3.10-4.00</verAplic><cStat>104</cStat><xMotivo>Lote processado</xMotivo><cUF>13</cUF><dhRecbto>2026-03-12T10:06:43-04:00</dhRecbto><protNFe versao=\"4.00\"><infProt><tpAmb>2</tpAmb><verAplic>AM3.10-4.00</verAplic><chNFe>13260359598453000104650010000000361185332233</chNFe><dhRecbto>2026-03-12T10:06:43-04:00</dhRecbto><cStat>373</cStat><xMotivo>Rejeicao: Descricao do primeiro item diferente de NOTA FISCAL EMITIDA EM AMBIENTE DE HOMOLOGACAO - SEM VALOR FISCAL</xMotivo></infProt></protNFe></retEnviNFe></nfeResultMsg></soapenv:Body></soapenv:Envelope>', 60.00, 0.00, '{\"tPag\":\"15\"}', '2026-03-12 14:06:43'),
(24, '125', 103, 2, 1, 37, '13260359598453000104650010000000371993349281', NULL, '104', 'Lote processado', '<?xml version=\"1.0\" encoding=\"UTF-8\"?><nfeProc xmlns=\"http://www.portalfiscal.inf.br/nfe\" versao=\"4.00\"><NFe xmlns=\"http://www.portalfiscal.inf.br/nfe\"><infNFe Id=\"NFe13260359598453000104650010000000371993349281\" versao=\"4.00\"><ide><cUF>13</cUF><cNF>99334928</cNF><natOp>VENDA</natOp><mod>65</mod><serie>1</serie><nNF>37</nNF><dhEmi>2026-03-12T10:07:51-04:00</dhEmi><tpNF>1</tpNF><idDest>1</idDest><cMunFG>1301209</cMunFG><tpImp>4</tpImp><tpEmis>1</tpEmis><cDV>1</cDV><tpAmb>2</tpAmb><finNFe>1</finNFe><indFinal>1</indFinal><indPres>1</indPres><procEmi>0</procEmi><verProc>PDV-ACAI-1.0</verProc></ide><emit><CNPJ>59598453000104</CNPJ><xNome>PAPAGAIO COMERCIO DE MOTOS LTDA</xNome><xFant>PAPAGAIO MOTOS</xFant><enderEmit><xLgr>PADRE VICENTE NOGUEIRA</xLgr><nro>149</nro><xBairro>ITAMARATI</xBairro><cMun>1301209</cMun><xMun>COARI</xMun><UF>AM</UF><CEP>69460000</CEP><cPais>1058</cPais><xPais>Brasil</xPais><fone>9791979595</fone></enderEmit><IE>054756448</IE><CRT>3</CRT></emit><det nItem=\"1\"><prod><cProd>1</cProd><cEAN>SEM GTIN</cEAN><xProd>Buzina 12v c100</xProd><NCM>85123000</NCM><CFOP>5102</CFOP><uCom>UN</uCom><qCom>1.000</qCom><vUnCom>60.00</vUnCom><vProd>60.00</vProd><cEANTrib>SEM GTIN</cEANTrib><uTrib>UN</uTrib><qTrib>1.000</qTrib><vUnTrib>60.00</vUnTrib><indTot>1</indTot></prod><imposto><ICMS><ICMSSN102><orig>0</orig><CSOSN>102</CSOSN></ICMSSN102></ICMS><PIS><PISNT><CST>07</CST></PISNT></PIS><COFINS><COFINSNT><CST>07</CST></COFINSNT></COFINS></imposto></det><total><ICMSTot><vBC>0.00</vBC><vICMS>0.00</vICMS><vICMSDeson>0.00</vICMSDeson><vFCP>0.00</vFCP><vBCST>0.00</vBCST><vST>0.00</vST><vFCPST>0.00</vFCPST><vFCPSTRet>0.00</vFCPSTRet><vProd>60.00</vProd><vFrete>0.00</vFrete><vSeg>0.00</vSeg><vDesc>0.00</vDesc><vII>0.00</vII><vIPI>0.00</vIPI><vIPIDevol>0.00</vIPIDevol><vPIS>0.00</vPIS><vCOFINS>0.00</vCOFINS><vOutro>0.00</vOutro><vNF>60.00</vNF></ICMSTot></total><transp><modFrete>9</modFrete></transp><pag><detPag><indPag>0</indPag><tPag>20</tPag><vPag>60.00</vPag></detPag></pag><infAdic><infCpl>PDV A&#xE7;aiteria</infCpl></infAdic></infNFe><infNFeSupl><qrCode>https://sistemas.sefaz.am.gov.br/nfceweb-hom/consultarNFCe.jsp?p=13260359598453000104650010000000371993349281|2|2|2|A885F9B91BC0764A3A887F104AA1EAB1EB83F687</qrCode><urlChave>www.sefaz.am.gov.br/nfce/consulta</urlChave></infNFeSupl><Signature xmlns=\"http://www.w3.org/2000/09/xmldsig#\"><SignedInfo><CanonicalizationMethod Algorithm=\"http://www.w3.org/TR/2001/REC-xml-c14n-20010315\"/><SignatureMethod Algorithm=\"http://www.w3.org/2000/09/xmldsig#rsa-sha1\"/><Reference URI=\"#NFe13260359598453000104650010000000371993349281\"><Transforms><Transform Algorithm=\"http://www.w3.org/2000/09/xmldsig#enveloped-signature\"/><Transform Algorithm=\"http://www.w3.org/TR/2001/REC-xml-c14n-20010315\"/></Transforms><DigestMethod Algorithm=\"http://www.w3.org/2000/09/xmldsig#sha1\"/><DigestValue>6ibnJpfjzIp+ZfsARVg3MmCfcfU=</DigestValue></Reference></SignedInfo><SignatureValue>RHkjT3dLP83nqLh+MPu2N/z9djq5hb15ctzFpviCY6sv85QpqYO9xwcWl+A3zqTAng3Y8K9xN0InNhrYusioWQ19DLbW33QfuzdzV/85VlLkCbZwnBBFmX0kPuRxsHLrj8jT7iDdD9eHdCYgfoyoLZ3+NzJ7tEiuHwttPk6jqi7+G7OEF0WlC4p2ZOPn9/dPIIato/B81MR/mWc61EA5zAApDLgSlOKbvVQFVxMvX2tPpyhAgWq4kGL6vZrjI2d6bcsTv1J9uRpIEVVUADl0VNuMTPGbBPUqavH4UOYGGsonWXGd5aOQvEdn83gPbiUnvDV8tcUc0ep56Coo+ANnrw==</SignatureValue><KeyInfo><X509Data><X509Certificate>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</X509Certificate></X509Data></KeyInfo></Signature></NFe><protNFe versao=\"4.00\"><infProt><tpAmb>2</tpAmb><verAplic>AM3.10-4.00</verAplic><chNFe>13260359598453000104650010000000371993349281</chNFe><dhRecbto>2026-03-12T10:07:51-04:00</dhRecbto><cStat>373</cStat><xMotivo>Rejeicao: Descricao do primeiro item diferente de NOTA FISCAL EMITIDA EM AMBIENTE DE HOMOLOGACAO - SEM VALOR FISCAL</xMotivo></infProt></protNFe></nfeProc>', '<?xml version=\"1.0\"?><NFe xmlns=\"http://www.portalfiscal.inf.br/nfe\"><infNFe Id=\"NFe13260359598453000104650010000000371993349281\" versao=\"4.00\"><ide><cUF>13</cUF><cNF>99334928</cNF><natOp>VENDA</natOp><mod>65</mod><serie>1</serie><nNF>37</nNF><dhEmi>2026-03-12T10:07:51-04:00</dhEmi><tpNF>1</tpNF><idDest>1</idDest><cMunFG>1301209</cMunFG><tpImp>4</tpImp><tpEmis>1</tpEmis><cDV>1</cDV><tpAmb>2</tpAmb><finNFe>1</finNFe><indFinal>1</indFinal><indPres>1</indPres><procEmi>0</procEmi><verProc>PDV-ACAI-1.0</verProc></ide><emit><CNPJ>59598453000104</CNPJ><xNome>PAPAGAIO COMERCIO DE MOTOS LTDA</xNome><xFant>PAPAGAIO MOTOS</xFant><enderEmit><xLgr>PADRE VICENTE NOGUEIRA</xLgr><nro>149</nro><xBairro>ITAMARATI</xBairro><cMun>1301209</cMun><xMun>COARI</xMun><UF>AM</UF><CEP>69460000</CEP><cPais>1058</cPais><xPais>Brasil</xPais><fone>9791979595</fone></enderEmit><IE>054756448</IE><CRT>3</CRT></emit><det nItem=\"1\"><prod><cProd>1</cProd><cEAN>SEM GTIN</cEAN><xProd>Buzina 12v c100</xProd><NCM>85123000</NCM><CFOP>5102</CFOP><uCom>UN</uCom><qCom>1.000</qCom><vUnCom>60.00</vUnCom><vProd>60.00</vProd><cEANTrib>SEM GTIN</cEANTrib><uTrib>UN</uTrib><qTrib>1.000</qTrib><vUnTrib>60.00</vUnTrib><indTot>1</indTot></prod><imposto><ICMS><ICMSSN102><orig>0</orig><CSOSN>102</CSOSN></ICMSSN102></ICMS><PIS><PISNT><CST>07</CST></PISNT></PIS><COFINS><COFINSNT><CST>07</CST></COFINSNT></COFINS></imposto></det><total><ICMSTot><vBC>0.00</vBC><vICMS>0.00</vICMS><vICMSDeson>0.00</vICMSDeson><vFCP>0.00</vFCP><vBCST>0.00</vBCST><vST>0.00</vST><vFCPST>0.00</vFCPST><vFCPSTRet>0.00</vFCPSTRet><vProd>60.00</vProd><vFrete>0.00</vFrete><vSeg>0.00</vSeg><vDesc>0.00</vDesc><vII>0.00</vII><vIPI>0.00</vIPI><vIPIDevol>0.00</vIPIDevol><vPIS>0.00</vPIS><vCOFINS>0.00</vCOFINS><vOutro>0.00</vOutro><vNF>60.00</vNF></ICMSTot></total><transp><modFrete>9</modFrete></transp><pag><detPag><indPag>0</indPag><tPag>20</tPag><vPag>60.00</vPag></detPag></pag><infAdic><infCpl>PDV A&#xE7;aiteria</infCpl></infAdic></infNFe><infNFeSupl><qrCode>https://sistemas.sefaz.am.gov.br/nfceweb-hom/consultarNFCe.jsp?p=13260359598453000104650010000000371993349281|2|2|2|A885F9B91BC0764A3A887F104AA1EAB1EB83F687</qrCode><urlChave>www.sefaz.am.gov.br/nfce/consulta</urlChave></infNFeSupl><Signature xmlns=\"http://www.w3.org/2000/09/xmldsig#\"><SignedInfo><CanonicalizationMethod Algorithm=\"http://www.w3.org/TR/2001/REC-xml-c14n-20010315\"/><SignatureMethod Algorithm=\"http://www.w3.org/2000/09/xmldsig#rsa-sha1\"/><Reference URI=\"#NFe13260359598453000104650010000000371993349281\"><Transforms><Transform Algorithm=\"http://www.w3.org/2000/09/xmldsig#enveloped-signature\"/><Transform Algorithm=\"http://www.w3.org/TR/2001/REC-xml-c14n-20010315\"/></Transforms><DigestMethod Algorithm=\"http://www.w3.org/2000/09/xmldsig#sha1\"/><DigestValue>6ibnJpfjzIp+ZfsARVg3MmCfcfU=</DigestValue></Reference></SignedInfo><SignatureValue>RHkjT3dLP83nqLh+MPu2N/z9djq5hb15ctzFpviCY6sv85QpqYO9xwcWl+A3zqTAng3Y8K9xN0InNhrYusioWQ19DLbW33QfuzdzV/85VlLkCbZwnBBFmX0kPuRxsHLrj8jT7iDdD9eHdCYgfoyoLZ3+NzJ7tEiuHwttPk6jqi7+G7OEF0WlC4p2ZOPn9/dPIIato/B81MR/mWc61EA5zAApDLgSlOKbvVQFVxMvX2tPpyhAgWq4kGL6vZrjI2d6bcsTv1J9uRpIEVVUADl0VNuMTPGbBPUqavH4UOYGGsonWXGd5aOQvEdn83gPbiUnvDV8tcUc0ep56Coo+ANnrw==</SignatureValue><KeyInfo><X509Data><X509Certificate>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</X509Certificate></X509Data></KeyInfo></Signature></NFe>', '<?xml version=\'1.0\' encoding=\'utf-8\'?><soapenv:Envelope xmlns:soapenv=\"http://www.w3.org/2003/05/soap-envelope\"><soapenv:Body><nfeResultMsg xmlns=\"http://www.portalfiscal.inf.br/nfe/wsdl/NFeAutorizacao4\"><retEnviNFe xmlns=\"http://www.portalfiscal.inf.br/nfe\" versao=\"4.00\"><tpAmb>2</tpAmb><verAplic>AM3.10-4.00</verAplic><cStat>104</cStat><xMotivo>Lote processado</xMotivo><cUF>13</cUF><dhRecbto>2026-03-12T10:07:51-04:00</dhRecbto><protNFe versao=\"4.00\"><infProt><tpAmb>2</tpAmb><verAplic>AM3.10-4.00</verAplic><chNFe>13260359598453000104650010000000371993349281</chNFe><dhRecbto>2026-03-12T10:07:51-04:00</dhRecbto><cStat>373</cStat><xMotivo>Rejeicao: Descricao do primeiro item diferente de NOTA FISCAL EMITIDA EM AMBIENTE DE HOMOLOGACAO - SEM VALOR FISCAL</xMotivo></infProt></protNFe></retEnviNFe></nfeResultMsg></soapenv:Body></soapenv:Envelope>', 60.00, 0.00, '{\"tPag\":\"20\"}', '2026-03-12 14:07:51');

-- --------------------------------------------------------

--
-- Estrutura para tabela `nfe_importadas`
--

CREATE TABLE `nfe_importadas` (
  `id` int(11) NOT NULL,
  `filial_id` int(11) NOT NULL,
  `chave_acesso` varchar(44) NOT NULL,
  `fornecedor_cnpj` varchar(14) NOT NULL,
  `fornecedor_nome` varchar(255) NOT NULL,
  `numero_nota` varchar(20) NOT NULL,
  `data_emissao` datetime NOT NULL,
  `valor_total` decimal(15,2) NOT NULL,
  `xml` longtext DEFAULT NULL,
  `status` enum('pendente','importada') DEFAULT 'pendente',
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `notas_fiscais`
--

CREATE TABLE `notas_fiscais` (
  `id` int(11) NOT NULL,
  `venda_id` int(11) NOT NULL,
  `tipo` enum('nfe','nfce') NOT NULL,
  `chave_acesso` varchar(44) DEFAULT NULL,
  `numero_nota` int(11) DEFAULT NULL,
  `serie_nota` int(11) DEFAULT NULL,
  `status` enum('pendente','autorizada','cancelada','rejeitada','contingencia') DEFAULT 'pendente',
  `xml_path` varchar(255) DEFAULT NULL,
  `danfe_path` varchar(255) DEFAULT NULL,
  `protocolo` varchar(50) DEFAULT NULL,
  `recibo` varchar(50) DEFAULT NULL,
  `mensagem_sefaz` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `os`
--

CREATE TABLE `os` (
  `id` int(11) NOT NULL,
  `filial_id` int(11) DEFAULT NULL,
  `numero_os` varchar(20) DEFAULT NULL,
  `cliente_id` int(11) DEFAULT NULL,
  `tecnico_id` int(11) DEFAULT NULL,
  `data_abertura` date DEFAULT NULL,
  `data_previsao` date DEFAULT NULL,
  `data_conclusao` date DEFAULT NULL,
  `status` enum('orcamento','aprovado','em_andamento','aguardando_peca','concluido','entregue','cancelado') DEFAULT 'orcamento',
  `descricao` text DEFAULT NULL,
  `checklist_tecnico` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`checklist_tecnico`)),
  `observacoes_internas` text DEFAULT NULL,
  `valor_total` decimal(10,2) DEFAULT NULL,
  `estoque_baixado` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `fotos` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`fotos`)),
  `assinatura_digital` text DEFAULT NULL,
  `sla_prazo_horas` int(11) DEFAULT 48,
  `data_vencimento_sla` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `os`
--

INSERT INTO `os` (`id`, `filial_id`, `numero_os`, `cliente_id`, `tecnico_id`, `data_abertura`, `data_previsao`, `data_conclusao`, `status`, `descricao`, `checklist_tecnico`, `observacoes_internas`, `valor_total`, `estoque_baixado`, `created_at`, `updated_at`, `fotos`, `assinatura_digital`, `sla_prazo_horas`, `data_vencimento_sla`) VALUES
(1, NULL, 'OS2024001', 1, NULL, '2024-01-15', NULL, NULL, '', 'Instalação elétrica completa', NULL, NULL, 1250.00, 0, '2026-02-18 17:15:13', '2026-02-18 17:15:13', NULL, NULL, 48, NULL),
(2, NULL, 'OS2024002', 2, NULL, '2024-01-20', NULL, NULL, 'em_andamento', 'Troca de disjuntores', NULL, NULL, 450.00, 0, '2026-02-18 17:15:13', '2026-02-18 17:15:13', NULL, NULL, 48, NULL),
(3, NULL, 'OS2024003', 3, NULL, '2024-01-25', NULL, NULL, '', 'Manutenção preventiva', NULL, NULL, 800.00, 0, '2026-02-18 17:15:13', '2026-02-18 17:15:13', NULL, NULL, 48, NULL);

-- --------------------------------------------------------

--
-- Estrutura para tabela `os_historico`
--

CREATE TABLE `os_historico` (
  `id` int(11) NOT NULL,
  `os_id` int(11) DEFAULT NULL,
  `status_anterior` varchar(50) DEFAULT NULL,
  `status_novo` varchar(50) DEFAULT NULL,
  `observacao` text DEFAULT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `data_historico` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `permissao_nivel`
--

CREATE TABLE `permissao_nivel` (
  `id` int(11) NOT NULL,
  `nivel` enum('admin','gerente','vendedor','tecnico','master') NOT NULL,
  `permissao_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `permissao_nivel`
--

INSERT INTO `permissao_nivel` (`id`, `nivel`, `permissao_id`) VALUES
(1, 'vendedor', 45),
(2, 'vendedor', 46);

-- --------------------------------------------------------

--
-- Estrutura para tabela `permissoes`
--

CREATE TABLE `permissoes` (
  `id` int(11) NOT NULL,
  `modulo` varchar(50) NOT NULL,
  `acao` varchar(50) NOT NULL,
  `descricao` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `permissoes`
--

INSERT INTO `permissoes` (`id`, `modulo`, `acao`, `descricao`) VALUES
(1, 'caixa', 'abrir', 'Abrir novo caixa'),
(2, 'caixa', 'fechar', 'Fechar caixa aberto'),
(3, 'caixa', 'movimentar', 'Registrar sangria e suprimento'),
(4, 'caixa', 'visualizar', 'Visualizar histórico e relatórios de caixa'),
(9, 'custos', 'visualizar', 'Ver relatórios de custos'),
(10, 'custos', 'gerenciar', 'Gerenciar centros e lançamentos'),
(11, 'inteligencia', 'visualizar', 'Ver BI e Inteligência Comercial'),
(12, 'inteligencia', 'recalcular', 'Recalcular Curvas e Alertas'),
(45, 'vendas', 'visualizar', 'Consultar histórico de vendas'),
(46, 'vendas', 'criar', 'Realizar novos pedidos/vendas'),
(47, 'vendas', 'editar', 'Alterar dados de vendas não finalizadas'),
(48, 'vendas', 'excluir', 'Excluir registros de vendas (Estorno)'),
(49, 'clientes', 'visualizar', 'Ver base de clientes'),
(50, 'clientes', 'criar', 'Cadastrar novos clientes'),
(51, 'clientes', 'editar', 'Alterar dados de clientes'),
(52, 'clientes', 'excluir', 'Remover clientes do sistema'),
(53, 'estoque', 'visualizar', 'Consultar catálogo e estoques'),
(54, 'estoque', 'criar', 'Cadastrar novos produtos'),
(55, 'estoque', 'editar', 'Ajustar preços e dados técnicos'),
(56, 'estoque', 'excluir', 'Remover produtos do catálogo'),
(57, 'financeiro', 'visualizar', 'Ver fluxo de caixa'),
(58, 'financeiro', 'gerenciar', 'Lançar pagamentos e recebimentos'),
(59, 'financeiro', 'dre', 'Acesso a relatórios de ROI e DRE'),
(60, 'fiscal', 'emitir_nota', 'Gerar e transmitir NFC-e/NF-e'),
(61, 'fiscal', 'configurar', 'Acessar certificados e tokens SEFAZ'),
(62, 'usuarios', 'visualizar', 'Ver lista de colaboradores'),
(63, 'usuarios', 'gerenciar', 'Criar, editar e bloquear operadores'),
(64, 'os', 'visualizar', 'Consultar ordens de serviço'),
(65, 'os', 'criar', 'Abrir novas ordens de serviço'),
(66, 'os', 'editar', 'Atualizar laudos e peças em OS'),
(67, 'os', 'excluir', 'Remover registros de OS'),
(68, 'fornecedores', 'visualizar', 'Ver base de fornecedores'),
(69, 'fornecedores', 'gerenciar', 'Cadastrar e editar fornecedores'),
(70, 'compras', 'visualizar', 'Ver histórico de compras'),
(71, 'compras', 'gerenciar', 'Lançar novas compras e entradas'),
(72, 'configuracoes', 'geral', 'Alterar dados da empresa e sistema');

-- --------------------------------------------------------

--
-- Estrutura para tabela `pre_vendas`
--

CREATE TABLE `pre_vendas` (
  `id` int(11) NOT NULL,
  `codigo` varchar(20) NOT NULL,
  `cliente_id` int(11) DEFAULT NULL,
  `nome_cliente_avulso` varchar(255) DEFAULT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `filial_id` int(11) DEFAULT NULL,
  `valor_total` decimal(10,2) DEFAULT 0.00,
  `status` enum('pendente','finalizado','cancelado') DEFAULT 'pendente',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `pre_vendas`
--

INSERT INTO `pre_vendas` (`id`, `codigo`, `cliente_id`, `nome_cliente_avulso`, `usuario_id`, `filial_id`, `valor_total`, `status`, `created_at`) VALUES
(1, 'PV-E344AC', NULL, NULL, 3, 1, 15.90, 'finalizado', '2026-02-21 20:05:34'),
(2, 'PV-D05227', NULL, NULL, 3, 1, 85.00, 'finalizado', '2026-02-21 20:06:53'),
(3, 'PV-939CB2', NULL, NULL, 3, 1, 165.00, 'finalizado', '2026-02-21 20:07:05'),
(4, 'PV-40B47C', NULL, NULL, 3, 1, 245.00, 'finalizado', '2026-02-21 20:07:16'),
(5, 'PV-85D258', NULL, NULL, 3, 1, 245.00, 'finalizado', '2026-02-21 20:07:52'),
(6, 'PV-8237C2', NULL, NULL, 3, 1, 15.90, 'finalizado', '2026-02-21 21:15:36'),
(7, 'PV-97E4E6', NULL, NULL, 3, 1, 15.90, 'finalizado', '2026-02-21 21:16:41'),
(8, 'PV-F45F01', NULL, NULL, 3, 1, 15.90, 'finalizado', '2026-02-21 21:17:03'),
(9, 'PV-42C11A', NULL, NULL, 1, 125, 5.00, 'finalizado', '2026-02-26 12:36:20'),
(10, 'PV-E53EB2', NULL, NULL, 3, 125, 15.90, 'finalizado', '2026-02-26 13:43:10'),
(11, 'PV-707F15', NULL, NULL, 3, 125, 45.00, 'finalizado', '2026-02-26 14:07:51'),
(12, 'PV-64EFB0', NULL, NULL, 3, 125, 5.00, 'finalizado', '2026-02-26 14:08:06'),
(13, 'PV-239B14', NULL, NULL, 3, 125, 295.00, 'finalizado', '2026-02-26 14:08:18'),
(14, 'PV-5289C9', NULL, NULL, 1, 125, 210.00, 'pendente', '2026-02-27 15:34:13'),
(15, 'PV-5E415B', NULL, NULL, 3, 125, 45.00, 'pendente', '2026-03-03 20:31:49'),
(16, 'PV-C1E1E5', NULL, NULL, 1, 125, 210.00, 'pendente', '2026-03-04 12:48:12'),
(17, 'PV-79FE04', NULL, 'luiz', 1, 125, 45.00, 'finalizado', '2026-03-04 12:55:51'),
(18, 'PV-0B89AF', NULL, 'breno', 1, 125, 725.00, 'finalizado', '2026-03-04 14:49:36'),
(19, 'PV-ACC90E', 11, NULL, 1, 125, 63.60, 'finalizado', '2026-03-04 15:39:38'),
(20, 'PV-E7F9F1', 12, 'lucas correa silva', 1, 125, 2750.00, 'finalizado', '2026-03-04 15:48:30'),
(21, 'PV-7C218E', 13, 'Liandra', 1, 125, 210.00, 'finalizado', '2026-03-10 17:18:31');

-- --------------------------------------------------------

--
-- Estrutura para tabela `pre_venda_itens`
--

CREATE TABLE `pre_venda_itens` (
  `id` int(11) NOT NULL,
  `pre_venda_id` int(11) DEFAULT NULL,
  `produto_id` int(11) DEFAULT NULL,
  `quantidade` decimal(10,3) DEFAULT NULL,
  `preco_unitario` decimal(10,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `pre_venda_itens`
--

INSERT INTO `pre_venda_itens` (`id`, `pre_venda_id`, `produto_id`, `quantidade`, `preco_unitario`) VALUES
(1, 1, 2, 1.000, 15.90),
(2, 2, 14, 1.000, 85.00),
(3, 3, 15, 1.000, 165.00),
(4, 4, 5, 1.000, 245.00),
(5, 5, 5, 1.000, 245.00),
(6, 6, 2, 1.000, 15.90),
(7, 7, 2, 1.000, 15.90),
(8, 8, 2, 1.000, 15.90),
(9, 9, 25, 1.000, 5.00),
(10, 10, 2, 1.000, 15.90),
(11, 11, 24, 1.000, 45.00),
(12, 12, 25, 1.000, 5.00),
(13, 13, 25, 1.000, 5.00),
(14, 13, 16, 1.000, 290.00),
(15, 14, 21, 1.000, 210.00),
(16, 15, 24, 1.000, 45.00),
(17, 16, 21, 1.000, 210.00),
(18, 17, 24, 1.000, 45.00),
(19, 18, 23, 5.000, 145.00),
(20, 19, 2, 4.000, 15.90),
(21, 20, 7, 5.000, 550.00),
(22, 21, 21, 1.000, 210.00);

-- --------------------------------------------------------

--
-- Estrutura para tabela `produtos`
--

CREATE TABLE `produtos` (
  `id` int(11) NOT NULL,
  `filial_id` int(11) DEFAULT NULL,
  `codigo` varchar(50) DEFAULT NULL,
  `ncm` varchar(10) DEFAULT NULL,
  `cean` varchar(14) DEFAULT 'SEM GTIN',
  `cest` varchar(10) DEFAULT NULL,
  `origem` tinyint(4) DEFAULT 0,
  `csosn` varchar(4) DEFAULT '102',
  `cfop_interno` varchar(4) DEFAULT '5102',
  `cfop_externo` varchar(4) DEFAULT '6102',
  `aliquota_icms` decimal(5,2) DEFAULT 0.00,
  `nome` varchar(100) NOT NULL,
  `unidade` varchar(10) DEFAULT 'UN',
  `peso` decimal(10,3) DEFAULT 0.000,
  `dimensoes` varchar(100) DEFAULT NULL,
  `descricao` text DEFAULT NULL,
  `imagens` text DEFAULT NULL,
  `categoria` varchar(50) DEFAULT NULL,
  `tipo_produto` enum('simples','composto') DEFAULT 'simples',
  `preco_custo` decimal(10,2) DEFAULT NULL,
  `preco_venda` decimal(10,2) DEFAULT NULL,
  `preco_venda_atacado` decimal(10,2) DEFAULT NULL,
  `quantidade` int(11) DEFAULT 0,
  `estoque_minimo` int(11) DEFAULT 5,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `produtos`
--

INSERT INTO `produtos` (`id`, `filial_id`, `codigo`, `ncm`, `cean`, `cest`, `origem`, `csosn`, `cfop_interno`, `cfop_externo`, `aliquota_icms`, `nome`, `unidade`, `peso`, `dimensoes`, `descricao`, `imagens`, `categoria`, `tipo_produto`, `preco_custo`, `preco_venda`, `preco_venda_atacado`, `quantidade`, `estoque_minimo`, `created_at`, `updated_at`) VALUES
(2, NULL, 'DIS001', '', 'SEM GTIN', NULL, 0, '102', '5102', '6102', 0.00, 'Disjuntor 10A', 'UN', 0.000, NULL, 'Disjuntor monopolar 10A', '699a1260c4723.png', 'Disjuntores', 'simples', 0.00, 15.90, NULL, 189, 20, '2026-02-18 17:15:13', '2026-03-10 16:52:06'),
(3, NULL, 'TOM001', NULL, 'SEM GTIN', NULL, 0, '102', '5102', '6102', 0.00, 'Tomada 10A', 'UN', 0.000, NULL, 'Tomada padrão 10A branca', NULL, 'Tomadas', 'simples', 3.20, 8.90, NULL, 200, 30, '2026-02-18 17:15:13', '2026-02-18 17:15:13'),
(4, NULL, 'LAM001', NULL, 'SEM GTIN', NULL, 0, '102', '5102', '6102', 0.00, 'Lâmpada LED 9W', 'UN', 0.000, NULL, 'Lâmpada LED 9W branca', NULL, 'Lâmpadas', 'simples', 4.50, 12.90, NULL, 150, 25, '2026-02-18 17:15:13', '2026-02-18 17:15:13'),
(5, 1, 'EL-7167', NULL, 'SEM GTIN', NULL, 0, '102', '5102', '6102', 0.00, 'Cabo Flexível 2,5mm Azul 100m', 'UN', 0.000, NULL, NULL, NULL, 'Fios e Cabos', 'simples', 189.90, 245.00, NULL, 30, 10, '2026-02-21 20:04:28', '2026-03-04 20:21:59'),
(6, 1, 'EL-9768', NULL, 'SEM GTIN', NULL, 0, '102', '5102', '6102', 0.00, 'Cabo Flexível 4,0mm Preto 100m', 'UN', 0.000, NULL, NULL, NULL, 'Fios e Cabos', 'simples', 295.00, 380.00, NULL, 182, 10, '2026-02-21 20:04:28', '2026-02-21 20:04:28'),
(7, 1, 'EL-9200', NULL, 'SEM GTIN', NULL, 0, '102', '5102', '6102', 0.00, 'Cabo Flexível 6,0mm Verde 100m', 'UN', 0.000, NULL, NULL, NULL, 'Fios e Cabos', 'simples', 420.00, 550.00, NULL, 176, 10, '2026-02-21 20:04:28', '2026-03-04 15:50:27'),
(8, 1, 'EL-1305', NULL, 'SEM GTIN', NULL, 0, '102', '5102', '6102', 0.00, 'Cabo PP 2x1,5mm 50m', 'UN', 0.000, NULL, NULL, NULL, 'Fios e Cabos', 'simples', 150.00, 210.00, NULL, 75, 10, '2026-02-21 20:04:28', '2026-02-21 20:04:28'),
(9, 1, 'EL-4994', NULL, 'SEM GTIN', NULL, 0, '102', '5102', '6102', 0.00, 'Lâmpada LED 9W Branca Bivolt', 'UN', 0.000, NULL, NULL, NULL, 'Iluminação', 'simples', 8.50, 15.90, NULL, 152, 10, '2026-02-21 20:04:28', '2026-02-21 20:04:28'),
(10, 1, 'EL-3863', NULL, 'SEM GTIN', NULL, 0, '102', '5102', '6102', 0.00, 'Painel LED 18W Quadrado Embutir', 'UN', 0.000, NULL, NULL, NULL, 'Iluminação', 'simples', 22.00, 45.00, NULL, 114, 10, '2026-02-21 20:04:28', '2026-02-21 20:04:28'),
(11, 1, 'EL-1477', NULL, 'SEM GTIN', NULL, 0, '102', '5102', '6102', 0.00, 'Refletor LED 50W SMD Preto', 'UN', 0.000, NULL, NULL, NULL, 'Iluminação', 'simples', 45.00, 89.00, NULL, 158, 10, '2026-02-21 20:04:28', '2026-02-21 21:13:07'),
(12, 1, 'EL-4352', NULL, 'SEM GTIN', NULL, 0, '102', '5102', '6102', 0.00, 'Fita LED 5050 RGB 5m', 'UN', 0.000, NULL, NULL, NULL, 'Iluminação', 'simples', 35.00, 75.00, NULL, 143, 10, '2026-02-21 20:04:28', '2026-02-26 15:00:48'),
(13, 1, 'EL-9014', NULL, 'SEM GTIN', NULL, 0, '102', '5102', '6102', 0.00, 'Disjuntor Monopolar 20A DIN', 'UN', 0.000, NULL, NULL, NULL, 'Disjuntores', 'simples', 12.00, 22.50, NULL, 136, 10, '2026-02-21 20:04:28', '2026-02-21 20:04:28'),
(14, 1, 'EL-6943', NULL, 'SEM GTIN', NULL, 0, '102', '5102', '6102', 0.00, 'Disjuntor Bipolar 40A DIN', 'UN', 0.000, NULL, NULL, NULL, 'Disjuntores', 'simples', 45.00, 85.00, NULL, 196, 10, '2026-02-21 20:04:28', '2026-02-21 20:11:56'),
(15, 1, 'EL-7122', NULL, 'SEM GTIN', NULL, 0, '102', '5102', '6102', 0.00, 'Disjuntor Tripolar 63A DIN', 'UN', 0.000, NULL, NULL, NULL, 'Disjuntores', 'simples', 95.00, 165.00, NULL, 144, 10, '2026-02-21 20:04:28', '2026-02-21 20:12:17'),
(16, 1, 'EL-1335', NULL, 'SEM GTIN', NULL, 0, '102', '5102', '6102', 0.00, 'IDR Tetrapolar 40A 30mA', 'UN', 0.000, NULL, NULL, NULL, 'Disjuntores', 'simples', 0.00, 290.00, NULL, 151, 10, '2026-02-21 20:04:28', '2026-02-26 15:00:20'),
(17, 1, 'EL-58632', '', 'SEM GTIN', NULL, 0, '102', '5102', '6102', 0.00, 'Conjunto Tomada 10A Branco', 'UN', 0.000, NULL, NULL, NULL, 'Fios e Cabos', 'simples', 7.50, 14.90, NULL, 89, 10, '2026-02-21 20:04:28', '2026-02-21 20:14:45'),
(18, 1, 'EL-2837', NULL, 'SEM GTIN', NULL, 0, '102', '5102', '6102', 0.00, 'Interruptor Simples 1 Tecla', 'UN', 0.000, NULL, NULL, NULL, 'Tomadas e Interruptores', 'simples', 6.80, 12.50, NULL, 129, 10, '2026-02-21 20:04:28', '2026-02-21 20:04:28'),
(19, 1, 'EL-6168', NULL, 'SEM GTIN', NULL, 0, '102', '5102', '6102', 0.00, 'Conjunto 2 Tomadas + 1 Interruptor', 'UN', 0.000, NULL, NULL, NULL, 'Tomadas e Interruptores', 'simples', 15.00, 28.00, NULL, 121, 10, '2026-02-21 20:04:28', '2026-02-21 20:04:28'),
(20, 1, 'EL-8325', NULL, 'SEM GTIN', NULL, 0, '102', '5102', '6102', 0.00, 'Módulo Tomada USB 2A', 'UN', 0.000, NULL, NULL, NULL, 'Tomadas e Interruptores', 'simples', 35.00, 65.00, NULL, 116, 10, '2026-02-21 20:04:28', '2026-02-21 20:04:28'),
(21, 1, 'EL-4622', NULL, 'SEM GTIN', NULL, 0, '102', '5102', '6102', 0.00, 'Alicate Amperímetro Digital ET-3200', 'UN', 0.000, NULL, NULL, NULL, 'Ferramentas', 'simples', 120.00, 210.00, NULL, 66, 10, '2026-02-21 20:04:28', '2026-03-12 12:06:22'),
(22, 1, 'EL-2901', NULL, 'SEM GTIN', NULL, 0, '102', '5102', '6102', 0.00, 'Alicate Universal 8 Pol Isolado', 'UN', 0.000, NULL, NULL, NULL, 'Ferramentas', 'simples', 35.00, 65.00, NULL, 76, 10, '2026-02-21 20:04:28', '2026-02-21 20:04:28'),
(23, 1, 'EL-8934', NULL, 'SEM GTIN', NULL, 0, '102', '5102', '6102', 0.00, 'Multímetro Digital Profissional', 'UN', 0.000, NULL, NULL, NULL, 'Ferramentas', 'simples', 85.00, 145.00, NULL, 80, 10, '2026-02-21 20:04:28', '2026-03-12 12:18:18'),
(24, 1, 'EL-4243', NULL, 'SEM GTIN', NULL, 0, '102', '5102', '6102', 0.00, 'Passa Fio Profissional 20m', 'UN', 0.000, NULL, NULL, NULL, 'Ferramentas', 'simples', 22.00, 45.00, NULL, 125, 10, '2026-02-21 20:04:28', '2026-03-04 14:52:46'),
(25, 1, '69460-000', '11111', 'SEM GTIN', '', 0, '102', '5102', '6102', 0.00, 'martello', 'UN', 0.000, NULL, NULL, '69a03c121e80a.png', 'Ferramentas', 'simples', 10.00, 5.00, NULL, -3, 600, '2026-02-26 12:26:58', '2026-02-26 15:00:20'),
(26, 1, '12113', '73182900', 'SEM GTIN', '160', 2, '102', '5102', '6102', 0.00, 'Bucha da coroa titan 150', 'UN', 0.000, NULL, NULL, NULL, 'Outros', 'simples', 7.90, 20.00, NULL, -1, 3, '2026-03-10 17:57:46', '2026-03-11 01:41:38'),
(27, 1, 'PRD00027', '85123000', '', '1701600', 2, '102', '5102', '6102', 0.00, 'Buzina 12v c100', 'UN', 0.000, '', '', NULL, 'Outros', 'simples', 20.00, 60.00, 30.00, -37, 3, '2026-03-10 19:40:32', '2026-03-12 15:47:48');

-- --------------------------------------------------------

--
-- Estrutura para tabela `produto_curva_abc`
--

CREATE TABLE `produto_curva_abc` (
  `id` int(11) NOT NULL,
  `produto_id` int(11) NOT NULL,
  `filial_id` int(11) NOT NULL,
  `classificacao` enum('A','B','C') NOT NULL,
  `periodo_referencia` varchar(20) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `produto_kits`
--

CREATE TABLE `produto_kits` (
  `id` int(11) NOT NULL,
  `produto_pai_id` int(11) DEFAULT NULL,
  `produto_filho_id` int(11) DEFAULT NULL,
  `quantidade` decimal(10,3) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `sefaz_config`
--

CREATE TABLE `sefaz_config` (
  `id` int(11) NOT NULL,
  `certificado_path` varchar(255) NOT NULL,
  `certificado_senha` varchar(255) NOT NULL,
  `ambiente` enum('producao','homologacao') DEFAULT 'producao',
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Despejando dados para a tabela `sefaz_config`
--

INSERT INTO `sefaz_config` (`id`, `certificado_path`, `certificado_senha`, `ambiente`, `created_at`) VALUES
(1, 'global_sefaz_1773174277.pfx', '959512', 'homologacao', '2026-03-10 12:02:22');

-- --------------------------------------------------------

--
-- Estrutura para tabela `transferencias_estoque`
--

CREATE TABLE `transferencias_estoque` (
  `id` int(11) NOT NULL,
  `produto_id` int(11) DEFAULT NULL,
  `origem_filial_id` int(11) DEFAULT NULL,
  `destino_filial_id` int(11) DEFAULT NULL,
  `quantidade` decimal(10,3) DEFAULT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `status` enum('pendente','concluido','cancelado') DEFAULT 'pendente',
  `data_transferencia` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
  `nivel` enum('vendedor','tecnico','gerente','admin','master') DEFAULT 'vendedor',
  `avatar` varchar(255) DEFAULT 'default_avatar.png',
  `ativo` tinyint(1) DEFAULT 1,
  `last_login` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `auth_pin` varchar(255) DEFAULT NULL,
  `auth_type` enum('password','pin') DEFAULT 'password',
  `desconto_maximo` decimal(5,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `usuarios`
--

INSERT INTO `usuarios` (`id`, `filial_id`, `nome`, `email`, `senha`, `nivel`, `avatar`, `ativo`, `last_login`, `created_at`, `auth_pin`, `auth_type`, `desconto_maximo`) VALUES
(1, 125, 'Administrador', 'admin@erp.com', '$2y$10$u9ZLuMfw6VDsw5CKLNE7/OGc82mYJbmxHVqhceFohXcT5Rh3YeM.a', 'admin', 'default_avatar.png', 1, '2026-03-12 15:47:38', '2026-02-21 18:42:59', '1234', 'password', 0.00),
(2, 125, 'Gerente Geral', 'gerente@erp.com', '$2y$10$zvApWXVbts1WoHsnQEOBK.VNz/FKi07J5u8hXLFQ.ZvSk8g7i67FC', 'gerente', 'default_avatar.png', 1, '2026-03-10 23:34:31', '2026-02-21 18:42:59', NULL, 'password', 0.00),
(3, 125, 'Vendedor 01', 'vendedor@erp.com', '$2y$10$Bl1iDbOKJWeIzmeV1H.jRebtE8OT/OgBK1vKkuFiTvzgy3LNUkNzy', 'vendedor', 'default_avatar.png', 1, '2026-03-09 15:44:20', '2026-02-21 18:42:59', NULL, 'password', 0.00),
(4, 589, 'Administrador', 'luizfrota@gmail.com', '$2y$10$v7j7klIlWuIL1gP.R0gCMOiJC6tnb6.yeyQ9B.STkNdTPHWVTjD5i', 'admin', 'default_avatar.png', 1, '2026-02-27 15:49:00', '2026-02-25 19:13:17', NULL, 'password', 0.00);

-- --------------------------------------------------------

--
-- Estrutura para tabela `vendas`
--

CREATE TABLE `vendas` (
  `id` int(11) NOT NULL,
  `cliente_id` int(11) DEFAULT NULL,
  `nome_cliente_avulso` varchar(255) DEFAULT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `autorizado_por` int(11) DEFAULT NULL,
  `filial_id` int(11) DEFAULT NULL,
  `valor_total` decimal(10,2) NOT NULL,
  `desconto_total` decimal(10,2) DEFAULT 0.00,
  `forma_pagamento` enum('dinheiro','pix','cartao_credito','cartao_debito','boleto') NOT NULL,
  `status` enum('orcamento','concluido','cancelado') DEFAULT 'concluido',
  `tipo_nota` enum('fiscal','nao_fiscal') NOT NULL DEFAULT 'nao_fiscal',
  `data_venda` timestamp NOT NULL DEFAULT current_timestamp(),
  `valor_recebido` decimal(10,2) DEFAULT NULL,
  `troco` decimal(10,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `vendas`
--

INSERT INTO `vendas` (`id`, `cliente_id`, `nome_cliente_avulso`, `usuario_id`, `autorizado_por`, `filial_id`, `valor_total`, `desconto_total`, `forma_pagamento`, `status`, `tipo_nota`, `data_venda`, `valor_recebido`, `troco`) VALUES
(1, NULL, NULL, 3, NULL, 1, 15.90, 0.00, 'pix', 'concluido', 'nao_fiscal', '2026-02-21 20:05:56', NULL, NULL),
(2, NULL, NULL, 3, NULL, 1, 85.00, 0.00, 'pix', 'concluido', 'nao_fiscal', '2026-02-21 20:11:56', NULL, NULL),
(3, NULL, NULL, 3, NULL, 1, 245.00, 0.00, 'pix', 'cancelado', 'nao_fiscal', '2026-02-21 20:12:02', NULL, NULL),
(4, NULL, NULL, 3, NULL, 1, 245.00, 0.00, 'cartao_credito', 'cancelado', 'nao_fiscal', '2026-02-21 20:12:10', NULL, NULL),
(5, NULL, NULL, 3, NULL, 1, 165.00, 0.00, 'pix', 'concluido', 'nao_fiscal', '2026-02-21 20:12:17', NULL, NULL),
(6, NULL, NULL, 1, NULL, 1, 15.90, 0.00, 'pix', 'cancelado', 'nao_fiscal', '2026-02-21 20:16:27', NULL, NULL),
(7, NULL, NULL, 3, NULL, 1, 15.90, 0.00, 'dinheiro', 'concluido', 'nao_fiscal', '2026-02-21 21:09:00', NULL, NULL),
(8, NULL, NULL, 3, NULL, 1, 89.00, 0.00, 'dinheiro', 'concluido', 'nao_fiscal', '2026-02-21 21:13:07', NULL, NULL),
(9, NULL, NULL, 3, NULL, 1, 15.90, 0.00, 'dinheiro', 'concluido', 'nao_fiscal', '2026-02-21 21:18:32', NULL, NULL),
(10, NULL, NULL, 1, NULL, 125, 5.00, 0.00, 'dinheiro', 'concluido', 'nao_fiscal', '2026-02-26 12:47:24', NULL, NULL),
(11, NULL, NULL, 1, NULL, 125, 15.74, 0.00, 'dinheiro', 'concluido', 'nao_fiscal', '2026-02-26 12:56:48', NULL, NULL),
(12, NULL, NULL, 2, NULL, 125, 15.76, 0.00, 'dinheiro', 'concluido', 'nao_fiscal', '2026-02-26 13:18:07', NULL, NULL),
(13, NULL, NULL, 2, NULL, 125, 14.63, 0.00, 'dinheiro', 'concluido', 'nao_fiscal', '2026-02-26 13:34:02', NULL, NULL),
(14, NULL, NULL, 2, NULL, 125, 15.90, 0.00, 'dinheiro', 'concluido', 'nao_fiscal', '2026-02-26 13:50:37', NULL, NULL),
(15, NULL, NULL, 2, 1, 125, 36.00, 9.00, 'dinheiro', 'concluido', 'nao_fiscal', '2026-02-26 14:55:40', NULL, NULL),
(16, NULL, NULL, 2, 1, 125, 4.00, 1.00, 'dinheiro', 'concluido', 'nao_fiscal', '2026-02-26 14:56:30', NULL, NULL),
(17, NULL, NULL, 2, 1, 125, 147.50, 147.50, 'dinheiro', 'concluido', 'nao_fiscal', '2026-02-26 15:00:20', NULL, NULL),
(18, NULL, NULL, 2, 1, 125, 60.00, 15.00, 'dinheiro', 'concluido', 'nao_fiscal', '2026-02-26 15:00:48', NULL, NULL),
(19, NULL, NULL, 1, NULL, 125, 15.90, 0.00, 'dinheiro', 'concluido', 'nao_fiscal', '2026-02-27 14:58:27', NULL, NULL),
(20, 11, NULL, 1, NULL, 125, 210.00, 0.00, '', 'concluido', 'nao_fiscal', '2026-03-04 14:47:48', NULL, NULL),
(21, 11, NULL, 1, NULL, 125, 45.00, 0.00, '', 'concluido', 'nao_fiscal', '2026-03-04 14:52:46', NULL, NULL),
(22, 12, NULL, 1, NULL, 125, 2750.00, 0.00, '', 'concluido', 'nao_fiscal', '2026-03-04 15:50:27', NULL, NULL),
(23, 12, NULL, 1, NULL, 125, 245.00, 0.00, '', 'concluido', 'nao_fiscal', '2026-03-04 20:21:59', NULL, NULL),
(24, 11, NULL, 2, NULL, 125, 63.60, 0.00, '', 'concluido', 'nao_fiscal', '2026-03-10 16:52:06', NULL, NULL),
(25, NULL, NULL, 1, NULL, 125, 60.00, 0.00, 'dinheiro', 'concluido', 'nao_fiscal', '2026-03-10 19:43:44', NULL, NULL),
(27, NULL, NULL, 1, NULL, 125, 60.00, 0.00, 'dinheiro', 'concluido', 'nao_fiscal', '2026-03-10 20:01:55', NULL, NULL),
(29, NULL, NULL, 1, NULL, 125, 60.00, 0.00, 'dinheiro', 'concluido', 'nao_fiscal', '2026-03-10 20:05:48', NULL, NULL),
(30, 11, NULL, 1, NULL, 125, 60.00, 0.00, 'dinheiro', 'concluido', 'nao_fiscal', '2026-03-10 20:06:13', NULL, NULL),
(31, 11, NULL, 1, NULL, 125, 60.00, 0.00, 'dinheiro', 'concluido', 'fiscal', '2026-03-10 20:06:26', NULL, NULL),
(32, NULL, NULL, 1, NULL, 125, 60.00, 0.00, 'dinheiro', 'concluido', 'nao_fiscal', '2026-03-10 20:08:38', NULL, NULL),
(33, NULL, NULL, 1, NULL, 125, 60.00, 0.00, 'dinheiro', 'concluido', 'fiscal', '2026-03-10 20:09:39', NULL, NULL),
(34, NULL, NULL, 1, NULL, 125, 60.00, 0.00, 'dinheiro', 'concluido', 'fiscal', '2026-03-10 20:18:46', NULL, NULL),
(35, NULL, NULL, 1, NULL, 125, 60.00, 0.00, 'dinheiro', 'concluido', 'nao_fiscal', '2026-03-10 20:19:09', NULL, NULL),
(36, NULL, NULL, 1, NULL, 125, 60.00, 0.00, 'dinheiro', 'concluido', 'nao_fiscal', '2026-03-10 20:46:25', NULL, NULL),
(37, NULL, NULL, 1, NULL, 125, 60.00, 0.00, 'dinheiro', 'concluido', 'fiscal', '2026-03-10 20:46:48', NULL, NULL),
(38, NULL, NULL, 1, NULL, 125, 60.00, 0.00, 'dinheiro', 'concluido', 'nao_fiscal', '2026-03-10 20:47:45', NULL, NULL),
(39, NULL, NULL, 1, NULL, 125, 60.00, 0.00, 'dinheiro', 'concluido', 'nao_fiscal', '2026-03-10 23:25:20', 60.00, 0.00),
(40, NULL, NULL, 1, NULL, 125, 60.00, 0.00, 'dinheiro', 'concluido', 'fiscal', '2026-03-10 23:29:22', 60.00, 0.00),
(41, NULL, NULL, 1, NULL, 125, 60.00, 0.00, 'dinheiro', 'concluido', 'nao_fiscal', '2026-03-10 23:29:44', 60.00, 0.00),
(42, NULL, NULL, 1, NULL, 125, 60.00, 0.00, 'dinheiro', 'concluido', 'nao_fiscal', '2026-03-10 23:30:21', 60.00, 0.00),
(43, NULL, NULL, 1, NULL, 125, 60.00, 0.00, 'dinheiro', 'concluido', 'fiscal', '2026-03-11 00:28:44', 60.00, 0.00),
(44, NULL, NULL, 1, NULL, 125, 60.00, 0.00, 'dinheiro', 'concluido', 'fiscal', '2026-03-11 00:38:42', 60.00, 0.00),
(45, NULL, NULL, 1, NULL, 125, 60.00, 0.00, 'dinheiro', 'concluido', 'fiscal', '2026-03-11 00:39:16', 60.00, 0.00),
(46, NULL, NULL, 1, NULL, 125, 60.00, 0.00, 'dinheiro', 'concluido', 'fiscal', '2026-03-11 01:14:09', 60.00, 0.00),
(47, NULL, NULL, 1, NULL, 125, 60.00, 0.00, 'dinheiro', 'concluido', 'nao_fiscal', '2026-03-11 01:16:16', 60.00, 0.00),
(48, NULL, NULL, 1, NULL, 125, 60.00, 0.00, 'dinheiro', 'concluido', 'nao_fiscal', '2026-03-11 01:21:09', 60.00, 0.00),
(49, NULL, NULL, 1, NULL, 125, 60.00, 0.00, 'dinheiro', 'concluido', 'fiscal', '2026-03-11 01:21:41', 60.00, 0.00),
(50, NULL, NULL, 1, NULL, 125, 60.00, 0.00, 'dinheiro', 'concluido', 'nao_fiscal', '2026-03-11 01:23:49', 60.00, 0.00),
(51, NULL, NULL, 1, NULL, 125, 60.00, 0.00, 'dinheiro', 'concluido', 'nao_fiscal', '2026-03-11 01:27:40', 60.00, 0.00),
(52, NULL, NULL, 1, NULL, 125, 60.00, 0.00, 'dinheiro', 'concluido', 'fiscal', '2026-03-11 01:28:05', 60.00, 0.00),
(53, NULL, NULL, 1, NULL, 125, 60.00, 0.00, 'dinheiro', 'concluido', 'fiscal', '2026-03-11 01:34:23', 60.00, 0.00),
(54, NULL, NULL, 1, NULL, 125, 60.00, 0.00, 'dinheiro', 'concluido', 'fiscal', '2026-03-11 01:35:19', 60.00, 0.00),
(55, NULL, NULL, 1, NULL, 125, 60.00, 0.00, 'dinheiro', 'concluido', 'fiscal', '2026-03-11 01:38:13', 60.00, 0.00),
(56, NULL, NULL, 1, NULL, 125, 60.00, 0.00, 'dinheiro', 'concluido', 'fiscal', '2026-03-11 01:38:45', 60.00, 0.00),
(57, NULL, NULL, 1, NULL, 125, 20.00, 0.00, 'dinheiro', 'concluido', 'nao_fiscal', '2026-03-11 01:41:37', 20.00, 0.00),
(58, NULL, NULL, 1, NULL, 125, 60.00, 0.00, 'dinheiro', 'concluido', 'fiscal', '2026-03-11 01:41:57', 60.00, 0.00),
(59, NULL, NULL, 1, NULL, 125, 60.00, 0.00, 'dinheiro', 'concluido', 'fiscal', '2026-03-11 01:43:32', 60.00, 0.00),
(60, NULL, NULL, 1, NULL, 125, 60.00, 0.00, 'dinheiro', 'concluido', 'fiscal', '2026-03-11 01:48:27', 60.00, 0.00),
(61, NULL, NULL, 1, NULL, 125, 60.00, 0.00, 'dinheiro', 'concluido', 'fiscal', '2026-03-11 01:48:57', 60.00, 0.00),
(62, NULL, NULL, 1, NULL, 125, 60.00, 0.00, 'dinheiro', 'concluido', 'fiscal', '2026-03-11 01:50:58', 60.00, 0.00),
(63, NULL, NULL, 1, NULL, 125, 60.00, 0.00, 'dinheiro', 'concluido', 'fiscal', '2026-03-11 12:01:26', 60.00, 0.00),
(64, NULL, NULL, 1, NULL, 125, 60.00, 0.00, 'dinheiro', 'concluido', 'fiscal', '2026-03-11 12:29:03', 60.00, 0.00),
(65, NULL, NULL, 1, NULL, 125, 60.00, 0.00, 'dinheiro', 'concluido', 'nao_fiscal', '2026-03-11 13:44:14', 60.00, 0.00),
(66, NULL, NULL, 1, NULL, 125, 60.00, 0.00, 'dinheiro', 'concluido', 'fiscal', '2026-03-11 13:46:56', 60.00, 0.00),
(67, NULL, NULL, 1, NULL, 125, 60.00, 0.00, 'dinheiro', 'concluido', 'fiscal', '2026-03-11 14:06:28', 60.00, 0.00),
(68, NULL, NULL, 1, NULL, 125, 60.00, 0.00, 'dinheiro', 'concluido', 'fiscal', '2026-03-11 14:07:41', 60.00, 0.00),
(69, NULL, NULL, 1, NULL, 125, 60.00, 0.00, 'dinheiro', 'concluido', 'fiscal', '2026-03-11 14:14:19', 60.00, 0.00),
(70, NULL, NULL, 1, NULL, 125, 60.00, 0.00, 'dinheiro', 'concluido', 'fiscal', '2026-03-11 14:17:27', 60.00, 0.00),
(71, NULL, NULL, 1, NULL, 125, 60.00, 0.00, 'dinheiro', 'concluido', 'fiscal', '2026-03-11 14:18:15', 60.00, 0.00),
(72, NULL, NULL, 1, NULL, 125, 60.00, 0.00, 'dinheiro', 'concluido', 'fiscal', '2026-03-11 14:20:05', 60.00, 0.00),
(73, NULL, NULL, 1, NULL, 125, 60.00, 0.00, 'dinheiro', 'concluido', 'fiscal', '2026-03-11 14:28:29', 60.00, 0.00),
(74, NULL, NULL, 1, NULL, 125, 60.00, 0.00, 'dinheiro', 'concluido', 'fiscal', '2026-03-11 14:31:12', 60.00, 0.00),
(75, NULL, NULL, 1, NULL, 125, 60.00, 0.00, 'dinheiro', 'concluido', 'fiscal', '2026-03-11 14:31:25', 60.00, 0.00),
(76, NULL, NULL, 1, NULL, 125, 60.00, 0.00, 'dinheiro', 'concluido', 'fiscal', '2026-03-11 14:41:21', 60.00, 0.00),
(77, NULL, NULL, 1, NULL, 125, 60.00, 0.00, 'dinheiro', 'concluido', 'fiscal', '2026-03-11 14:42:02', 60.00, 0.00),
(78, NULL, NULL, 1, NULL, 125, 60.00, 0.00, 'dinheiro', 'concluido', 'fiscal', '2026-03-11 14:45:20', 60.00, 0.00),
(79, NULL, NULL, 1, NULL, 125, 60.00, 0.00, 'dinheiro', 'concluido', 'fiscal', '2026-03-11 14:47:53', 60.00, 0.00),
(80, NULL, NULL, 1, NULL, 125, 60.00, 0.00, 'dinheiro', 'concluido', 'nao_fiscal', '2026-03-11 14:48:10', 60.00, 0.00),
(81, NULL, NULL, 1, NULL, 125, 60.00, 0.00, 'dinheiro', 'concluido', 'nao_fiscal', '2026-03-12 12:03:03', 60.00, 0.00),
(82, NULL, NULL, 1, NULL, 125, 60.00, 0.00, 'dinheiro', 'concluido', 'fiscal', '2026-03-12 12:03:17', 60.00, 0.00),
(83, 11, NULL, 1, NULL, 125, 60.00, 0.00, 'dinheiro', 'concluido', 'fiscal', '2026-03-12 12:03:42', 60.00, 0.00),
(84, 11, NULL, 1, NULL, 125, 60.00, 0.00, 'dinheiro', 'concluido', 'fiscal', '2026-03-12 12:03:42', 60.00, 0.00),
(85, 11, NULL, 1, NULL, 125, 60.00, 0.00, 'pix', 'concluido', 'fiscal', '2026-03-12 12:04:50', NULL, 0.00),
(86, 13, NULL, 1, NULL, 125, 210.00, 0.00, 'dinheiro', 'concluido', 'nao_fiscal', '2026-03-12 12:06:22', 210.00, 0.00),
(87, 13, NULL, 1, NULL, 125, 725.00, 0.00, 'dinheiro', 'concluido', 'fiscal', '2026-03-12 12:18:18', 725.00, 0.00),
(88, 11, NULL, 1, NULL, 125, 60.00, 0.00, 'pix', 'concluido', 'fiscal', '2026-03-12 12:54:43', NULL, 0.00),
(89, NULL, NULL, 1, NULL, 125, 60.00, 0.00, 'cartao_credito', 'concluido', 'fiscal', '2026-03-12 12:55:22', NULL, 0.00),
(90, 11, NULL, 1, NULL, 125, 60.00, 0.00, 'pix', 'concluido', 'fiscal', '2026-03-12 13:01:40', NULL, 0.00),
(91, 11, NULL, 1, NULL, 125, 60.00, 0.00, 'boleto', 'concluido', 'fiscal', '2026-03-12 13:02:02', NULL, 0.00),
(92, 11, NULL, 1, NULL, 125, 60.00, 0.00, '', 'concluido', 'fiscal', '2026-03-12 13:02:45', NULL, 0.00),
(93, 11, NULL, 1, NULL, 125, 60.00, 0.00, 'pix', 'concluido', 'fiscal', '2026-03-12 13:11:00', NULL, 0.00),
(94, NULL, NULL, 1, NULL, 125, 60.00, 0.00, 'pix', 'concluido', 'fiscal', '2026-03-12 13:22:12', NULL, 0.00),
(95, 11, NULL, 1, NULL, 125, 60.00, 0.00, 'pix', 'concluido', 'fiscal', '2026-03-12 13:22:54', NULL, 0.00),
(96, 12, NULL, 1, NULL, 125, 60.00, 0.00, 'pix', 'concluido', 'fiscal', '2026-03-12 13:32:58', NULL, 0.00),
(97, NULL, NULL, 1, NULL, 125, 60.00, 0.00, 'pix', 'concluido', 'fiscal', '2026-03-12 13:33:33', NULL, 0.00),
(98, NULL, NULL, 1, NULL, 125, 60.00, 0.00, 'pix', 'concluido', 'fiscal', '2026-03-12 13:45:59', NULL, 0.00),
(99, NULL, NULL, 1, NULL, 125, 60.00, 0.00, 'pix', 'concluido', 'fiscal', '2026-03-12 13:46:28', NULL, 0.00),
(100, NULL, NULL, 1, NULL, 125, 60.00, 0.00, 'pix', 'concluido', 'fiscal', '2026-03-12 13:59:57', NULL, 0.00),
(101, NULL, NULL, 1, NULL, 125, 60.00, 0.00, 'cartao_credito', 'concluido', 'fiscal', '2026-03-12 14:01:31', NULL, 0.00),
(102, NULL, NULL, 1, NULL, 125, 60.00, 0.00, 'boleto', 'concluido', 'fiscal', '2026-03-12 14:06:41', NULL, 0.00),
(103, 11, NULL, 1, NULL, 125, 60.00, 0.00, 'pix', 'concluido', 'fiscal', '2026-03-12 14:07:49', NULL, 0.00),
(104, 11, NULL, 1, NULL, 125, 60.00, 0.00, 'pix', 'concluido', 'fiscal', '2026-03-12 14:11:50', NULL, 0.00),
(105, 11, NULL, 1, NULL, 125, 60.00, 0.00, 'dinheiro', 'concluido', 'fiscal', '2026-03-12 14:32:19', 60.00, 0.00),
(106, 11, NULL, 1, NULL, 125, 60.00, 0.00, 'dinheiro', 'concluido', 'fiscal', '2026-03-12 14:32:37', 60.00, 0.00),
(107, 11, NULL, 1, NULL, 125, 60.00, 0.00, 'pix', 'concluido', 'fiscal', '2026-03-12 14:46:48', NULL, 0.00),
(108, 11, NULL, 1, NULL, 125, 60.00, 0.00, 'pix', 'concluido', 'nao_fiscal', '2026-03-12 14:47:10', NULL, 0.00),
(109, NULL, NULL, 1, NULL, 125, 60.00, 0.00, 'dinheiro', 'concluido', 'fiscal', '2026-03-12 14:47:22', 60.00, 0.00),
(110, 11, NULL, 1, NULL, 125, 60.00, 0.00, 'dinheiro', 'concluido', 'fiscal', '2026-03-12 14:56:57', 60.00, 0.00),
(111, 11, NULL, 1, NULL, 125, 60.00, 0.00, 'dinheiro', 'concluido', 'fiscal', '2026-03-12 15:12:30', 60.00, 0.00),
(112, 11, NULL, 1, NULL, 125, 60.00, 0.00, 'dinheiro', 'concluido', 'fiscal', '2026-03-12 15:18:15', 60.00, 0.00),
(113, 11, NULL, 1, NULL, 125, 60.00, 0.00, 'cartao_credito', 'concluido', 'fiscal', '2026-03-12 15:25:00', NULL, 0.00),
(114, NULL, NULL, 1, NULL, 125, 60.00, 0.00, 'dinheiro', 'concluido', 'fiscal', '2026-03-12 15:25:37', 60.00, 0.00),
(115, NULL, NULL, 1, NULL, 125, 60.00, 0.00, 'dinheiro', 'concluido', 'fiscal', '2026-03-12 15:37:40', 60.00, 0.00),
(116, NULL, NULL, 1, NULL, 125, 60.00, 0.00, 'dinheiro', 'concluido', 'fiscal', '2026-03-12 15:47:48', 60.00, 0.00);

-- --------------------------------------------------------

--
-- Estrutura para tabela `vendas_itens`
--

CREATE TABLE `vendas_itens` (
  `id` int(11) NOT NULL,
  `venda_id` int(11) DEFAULT NULL,
  `produto_id` int(11) DEFAULT NULL,
  `quantidade` decimal(10,3) NOT NULL,
  `preco_unitario` decimal(10,2) NOT NULL,
  `ncm` varchar(10) DEFAULT NULL COMMENT 'NCM 8 dígitos',
  `cean` varchar(14) DEFAULT 'SEM GTIN',
  `cest` varchar(7) DEFAULT NULL,
  `cfop` varchar(4) DEFAULT '5102',
  `origem` tinyint(4) DEFAULT 0,
  `csosn` varchar(3) DEFAULT '102',
  `unidade` varchar(6) DEFAULT 'UN'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `vendas_itens`
--

INSERT INTO `vendas_itens` (`id`, `venda_id`, `produto_id`, `quantidade`, `preco_unitario`, `ncm`, `cean`, `cest`, `cfop`, `origem`, `csosn`, `unidade`) VALUES
(1, 1, 2, 1.000, 15.90, NULL, 'SEM GTIN', NULL, '5102', 0, '102', 'UN'),
(2, 2, 14, 1.000, 85.00, NULL, 'SEM GTIN', NULL, '5102', 0, '102', 'UN'),
(3, 3, 5, 1.000, 245.00, NULL, 'SEM GTIN', NULL, '5102', 0, '102', 'UN'),
(4, 4, 5, 1.000, 245.00, NULL, 'SEM GTIN', NULL, '5102', 0, '102', 'UN'),
(5, 5, 15, 1.000, 165.00, NULL, 'SEM GTIN', NULL, '5102', 0, '102', 'UN'),
(6, 6, 2, 1.000, 15.90, NULL, 'SEM GTIN', NULL, '5102', 0, '102', 'UN'),
(7, 7, 2, 1.000, 15.90, NULL, 'SEM GTIN', NULL, '5102', 0, '102', 'UN'),
(8, 8, 11, 1.000, 89.00, NULL, 'SEM GTIN', NULL, '5102', 0, '102', 'UN'),
(9, 9, 2, 1.000, 15.90, NULL, 'SEM GTIN', NULL, '5102', 0, '102', 'UN'),
(10, 10, 25, 1.000, 5.00, NULL, 'SEM GTIN', NULL, '5102', 0, '102', 'UN'),
(11, 11, 2, 1.000, 15.90, NULL, 'SEM GTIN', NULL, '5102', 0, '102', 'UN'),
(12, 12, 2, 1.000, 15.90, NULL, 'SEM GTIN', NULL, '5102', 0, '102', 'UN'),
(13, 13, 2, 1.000, 15.90, NULL, 'SEM GTIN', NULL, '5102', 0, '102', 'UN'),
(14, 14, 2, 1.000, 15.90, NULL, 'SEM GTIN', NULL, '5102', 0, '102', 'UN'),
(15, 15, 24, 1.000, 45.00, NULL, 'SEM GTIN', NULL, '5102', 0, '102', 'UN'),
(16, 16, 25, 1.000, 5.00, NULL, 'SEM GTIN', NULL, '5102', 0, '102', 'UN'),
(17, 17, 25, 1.000, 5.00, NULL, 'SEM GTIN', NULL, '5102', 0, '102', 'UN'),
(18, 17, 16, 1.000, 290.00, NULL, 'SEM GTIN', NULL, '5102', 0, '102', 'UN'),
(19, 18, 12, 1.000, 75.00, NULL, 'SEM GTIN', NULL, '5102', 0, '102', 'UN'),
(20, 19, 2, 1.000, 15.90, NULL, 'SEM GTIN', NULL, '5102', 0, '102', 'UN'),
(21, 20, 21, 1.000, 210.00, NULL, 'SEM GTIN', NULL, '5102', 0, '102', 'UN'),
(22, 21, 24, 1.000, 45.00, NULL, 'SEM GTIN', NULL, '5102', 0, '102', 'UN'),
(23, 22, 7, 5.000, 550.00, NULL, 'SEM GTIN', NULL, '5102', 0, '102', 'UN'),
(24, 23, 5, 1.000, 245.00, NULL, 'SEM GTIN', NULL, '5102', 0, '102', 'UN'),
(25, 24, 2, 4.000, 15.90, NULL, 'SEM GTIN', NULL, '5102', 0, '102', 'UN'),
(26, 25, 27, 1.000, 60.00, NULL, 'SEM GTIN', NULL, '5102', 0, '102', 'UN'),
(27, 27, 27, 1.000, 60.00, NULL, 'SEM GTIN', NULL, '5102', 0, '102', 'UN'),
(28, 29, 27, 1.000, 60.00, NULL, 'SEM GTIN', NULL, '5102', 0, '102', 'UN'),
(29, 30, 27, 1.000, 60.00, NULL, 'SEM GTIN', NULL, '5102', 0, '102', 'UN'),
(30, 31, 27, 1.000, 60.00, '85123000', 'SEM GTIN', '1701600', '5102', 2, '102', 'UN'),
(31, 32, 27, 1.000, 60.00, NULL, 'SEM GTIN', NULL, '5102', 0, '102', 'UN'),
(32, 33, 27, 1.000, 60.00, '85123000', 'SEM GTIN', '1701600', '5102', 2, '102', 'UN'),
(33, 34, 27, 1.000, 60.00, '85123000', 'SEM GTIN', '1701600', '5102', 2, '102', 'UN'),
(34, 35, 27, 1.000, 60.00, NULL, 'SEM GTIN', NULL, '5102', 0, '102', 'UN'),
(35, 36, 27, 1.000, 60.00, NULL, 'SEM GTIN', NULL, '5102', 0, '102', 'UN'),
(36, 37, 27, 1.000, 60.00, '85123000', 'SEM GTIN', '1701600', '5102', 2, '102', 'UN'),
(37, 38, 27, 1.000, 60.00, NULL, 'SEM GTIN', NULL, '5102', 0, '102', 'UN'),
(38, 39, 27, 1.000, 60.00, NULL, 'SEM GTIN', NULL, '5102', 0, '102', 'UN'),
(39, 40, 27, 1.000, 60.00, '85123000', 'SEM GTIN', '1701600', '5102', 2, '102', 'UN'),
(40, 41, 27, 1.000, 60.00, NULL, 'SEM GTIN', NULL, '5102', 0, '102', 'UN'),
(41, 42, 27, 1.000, 60.00, NULL, 'SEM GTIN', NULL, '5102', 0, '102', 'UN'),
(42, 43, 27, 1.000, 60.00, '85123000', 'SEM GTIN', '1701600', '5102', 2, '102', 'UN'),
(43, 44, 27, 1.000, 60.00, '85123000', 'SEM GTIN', '1701600', '5102', 2, '102', 'UN'),
(44, 45, 27, 1.000, 60.00, '85123000', 'SEM GTIN', '1701600', '5102', 2, '102', 'UN'),
(45, 46, 27, 1.000, 60.00, '85123000', 'SEM GTIN', '1701600', '5102', 2, '102', 'UN'),
(46, 47, 27, 1.000, 60.00, NULL, 'SEM GTIN', NULL, '5102', 0, '102', 'UN'),
(47, 48, 27, 1.000, 60.00, NULL, 'SEM GTIN', NULL, '5102', 0, '102', 'UN'),
(48, 49, 27, 1.000, 60.00, '85123000', 'SEM GTIN', '1701600', '5102', 2, '102', 'UN'),
(49, 50, 27, 1.000, 60.00, NULL, 'SEM GTIN', NULL, '5102', 0, '102', 'UN'),
(50, 51, 27, 1.000, 60.00, NULL, 'SEM GTIN', NULL, '5102', 0, '102', 'UN'),
(51, 52, 27, 1.000, 60.00, '85123000', 'SEM GTIN', '1701600', '5102', 2, '102', 'UN'),
(52, 53, 27, 1.000, 60.00, '85123000', 'SEM GTIN', '1701600', '5102', 2, '102', 'UN'),
(53, 54, 27, 1.000, 60.00, '85123000', 'SEM GTIN', '1701600', '5102', 2, '102', 'UN'),
(54, 55, 27, 1.000, 60.00, '85123000', 'SEM GTIN', '1701600', '5102', 2, '102', 'UN'),
(55, 56, 27, 1.000, 60.00, '85123000', 'SEM GTIN', '1701600', '5102', 2, '102', 'UN'),
(56, 57, 26, 1.000, 20.00, NULL, 'SEM GTIN', NULL, '5102', 0, '102', 'UN'),
(57, 58, 27, 1.000, 60.00, '85123000', 'SEM GTIN', '1701600', '5102', 2, '102', 'UN'),
(58, 59, 27, 1.000, 60.00, '85123000', 'SEM GTIN', '1701600', '5102', 2, '102', 'UN'),
(59, 60, 27, 1.000, 60.00, '85123000', 'SEM GTIN', '1701600', '5102', 2, '102', 'UN'),
(60, 61, 27, 1.000, 60.00, '85123000', 'SEM GTIN', '1701600', '5102', 2, '102', 'UN'),
(61, 62, 27, 1.000, 60.00, '85123000', 'SEM GTIN', '1701600', '5102', 2, '102', 'UN'),
(62, 63, 27, 1.000, 60.00, '85123000', 'SEM GTIN', '1701600', '5102', 2, '102', 'UN'),
(63, 64, 27, 1.000, 60.00, '85123000', 'SEM GTIN', '1701600', '5102', 2, '102', 'UN'),
(64, 65, 27, 1.000, 60.00, NULL, 'SEM GTIN', NULL, '5102', 0, '102', 'UN'),
(65, 66, 27, 1.000, 60.00, '85123000', 'SEM GTIN', '1701600', '5102', 2, '102', 'UN'),
(66, 67, 27, 1.000, 60.00, '85123000', 'SEM GTIN', '1701600', '5102', 2, '102', 'UN'),
(67, 68, 27, 1.000, 60.00, '85123000', 'SEM GTIN', '1701600', '5102', 2, '102', 'UN'),
(68, 69, 27, 1.000, 60.00, '85123000', 'SEM GTIN', '1701600', '5102', 2, '102', 'UN'),
(69, 70, 27, 1.000, 60.00, '85123000', 'SEM GTIN', '1701600', '5102', 2, '102', 'UN'),
(70, 71, 27, 1.000, 60.00, '85123000', 'SEM GTIN', '1701600', '5102', 2, '102', 'UN'),
(71, 72, 27, 1.000, 60.00, '85123000', 'SEM GTIN', '1701600', '5102', 2, '102', 'UN'),
(72, 73, 27, 1.000, 60.00, '85123000', 'SEM GTIN', '1701600', '5102', 2, '102', 'UN'),
(73, 74, 27, 1.000, 60.00, '85123000', 'SEM GTIN', '1701600', '5102', 2, '102', 'UN'),
(74, 75, 27, 1.000, 60.00, '85123000', 'SEM GTIN', '1701600', '5102', 2, '102', 'UN'),
(75, 76, 27, 1.000, 60.00, '85123000', 'SEM GTIN', '1701600', '5102', 2, '102', 'UN'),
(76, 77, 27, 1.000, 60.00, '85123000', 'SEM GTIN', '1701600', '5102', 2, '102', 'UN'),
(77, 78, 27, 1.000, 60.00, '85123000', 'SEM GTIN', '1701600', '5102', 2, '102', 'UN'),
(78, 79, 27, 1.000, 60.00, '85123000', 'SEM GTIN', '1701600', '5102', 2, '102', 'UN'),
(79, 80, 27, 1.000, 60.00, NULL, 'SEM GTIN', NULL, '5102', 0, '102', 'UN'),
(80, 81, 27, 1.000, 60.00, NULL, 'SEM GTIN', NULL, '5102', 0, '102', 'UN'),
(81, 82, 27, 1.000, 60.00, '85123000', 'SEM GTIN', '1701600', '5102', 2, '102', 'UN'),
(82, 83, 27, 1.000, 60.00, '85123000', 'SEM GTIN', '1701600', '5102', 2, '102', 'UN'),
(83, 84, 27, 1.000, 60.00, '85123000', 'SEM GTIN', '1701600', '5102', 2, '102', 'UN'),
(84, 85, 27, 1.000, 60.00, '85123000', 'SEM GTIN', '1701600', '5102', 2, '102', 'UN'),
(85, 86, 21, 1.000, 210.00, NULL, 'SEM GTIN', NULL, '5102', 0, '102', 'UN'),
(86, 87, 23, 5.000, 145.00, '21069090', 'SEM GTIN', NULL, '5102', 0, '102', 'UN'),
(87, 88, 27, 1.000, 60.00, '85123000', 'SEM GTIN', '1701600', '5102', 2, '102', 'UN'),
(88, 89, 27, 1.000, 60.00, '85123000', 'SEM GTIN', '1701600', '5102', 2, '102', 'UN'),
(89, 90, 27, 1.000, 60.00, '85123000', 'SEM GTIN', '1701600', '5102', 2, '102', 'UN'),
(90, 91, 27, 1.000, 60.00, '85123000', 'SEM GTIN', '1701600', '5102', 2, '102', 'UN'),
(91, 92, 27, 1.000, 60.00, '85123000', 'SEM GTIN', '1701600', '5102', 2, '102', 'UN'),
(92, 93, 27, 1.000, 60.00, '85123000', 'SEM GTIN', '1701600', '5102', 2, '102', 'UN'),
(93, 94, 27, 1.000, 60.00, '85123000', 'SEM GTIN', '1701600', '5102', 2, '102', 'UN'),
(94, 95, 27, 1.000, 60.00, '85123000', 'SEM GTIN', '1701600', '5102', 2, '102', 'UN'),
(95, 96, 27, 1.000, 60.00, '85123000', 'SEM GTIN', '1701600', '5102', 2, '102', 'UN'),
(96, 97, 27, 1.000, 60.00, '85123000', 'SEM GTIN', '1701600', '5102', 2, '102', 'UN'),
(97, 98, 27, 1.000, 60.00, '85123000', 'SEM GTIN', '1701600', '5102', 2, '102', 'UN'),
(98, 99, 27, 1.000, 60.00, '85123000', 'SEM GTIN', '1701600', '5102', 2, '102', 'UN'),
(99, 100, 27, 1.000, 60.00, '85123000', 'SEM GTIN', '1701600', '5102', 2, '102', 'UN'),
(100, 101, 27, 1.000, 60.00, '85123000', 'SEM GTIN', '1701600', '5102', 2, '102', 'UN'),
(101, 102, 27, 1.000, 60.00, '85123000', 'SEM GTIN', '1701600', '5102', 2, '102', 'UN'),
(102, 103, 27, 1.000, 60.00, '85123000', 'SEM GTIN', '1701600', '5102', 2, '102', 'UN'),
(103, 104, 27, 1.000, 60.00, '85123000', 'SEM GTIN', '1701600', '5102', 2, '102', 'UN'),
(104, 105, 27, 1.000, 60.00, '85123000', 'SEM GTIN', '1701600', '5102', 2, '102', 'UN'),
(105, 106, 27, 1.000, 60.00, '85123000', 'SEM GTIN', '1701600', '5102', 2, '102', 'UN'),
(106, 107, 27, 1.000, 60.00, '85123000', 'SEM GTIN', '1701600', '5102', 2, '102', 'UN'),
(107, 108, 27, 1.000, 60.00, NULL, 'SEM GTIN', NULL, '5102', 0, '102', 'UN'),
(108, 109, 27, 1.000, 60.00, '85123000', 'SEM GTIN', '1701600', '5102', 2, '102', 'UN'),
(109, 110, 27, 1.000, 60.00, '85123000', 'SEM GTIN', '1701600', '5102', 2, '102', 'UN'),
(110, 111, 27, 1.000, 60.00, '85123000', 'SEM GTIN', '1701600', '5102', 2, '102', 'UN'),
(111, 112, 27, 1.000, 60.00, '85123000', 'SEM GTIN', '1701600', '5102', 2, '102', 'UN'),
(112, 113, 27, 1.000, 60.00, '85123000', 'SEM GTIN', '1701600', '5102', 2, '102', 'UN'),
(113, 114, 27, 1.000, 60.00, '85123000', 'SEM GTIN', '1701600', '5102', 2, '102', 'UN'),
(114, 115, 27, 1.000, 60.00, '85123000', 'SEM GTIN', '1701600', '5102', 2, '102', 'UN'),
(115, 116, 27, 1.000, 60.00, '85123000', 'SEM GTIN', '1701600', '5102', 2, '102', 'UN');

--
-- Índices para tabelas despejadas
--

--
-- Índices de tabela `alertas_estoque`
--
ALTER TABLE `alertas_estoque`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_alert_filial` (`filial_id`),
  ADD KEY `idx_alert_prod` (`produto_id`);

--
-- Índices de tabela `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `usuario_id` (`usuario_id`);

--
-- Índices de tabela `autorizacoes_temporarias`
--
ALTER TABLE `autorizacoes_temporarias`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_auth_codigo` (`codigo`),
  ADD KEY `idx_auth_filial` (`filial_id`),
  ADD KEY `idx_auth_validade` (`validade`);

--
-- Índices de tabela `caixas`
--
ALTER TABLE `caixas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_caixa_filial` (`filial_id`),
  ADD KEY `idx_caixa_operador` (`operador_id`),
  ADD KEY `idx_caixa_status` (`status`);

--
-- Índices de tabela `caixa_movimentacoes`
--
ALTER TABLE `caixa_movimentacoes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_mov_caixa` (`caixa_id`),
  ADD KEY `idx_mov_operador` (`operador_id`);

--
-- Índices de tabela `centros_custo`
--
ALTER TABLE `centros_custo`
  ADD PRIMARY KEY (`id`);

--
-- Índices de tabela `clientes`
--
ALTER TABLE `clientes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_clientes_filial_cpf` (`filial_id`,`cpf_cnpj`);

--
-- Índices de tabela `compras`
--
ALTER TABLE `compras`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fornecedor_id` (`fornecedor_id`),
  ADD KEY `usuario_id` (`usuario_id`);

--
-- Índices de tabela `compra_itens`
--
ALTER TABLE `compra_itens`
  ADD PRIMARY KEY (`id`),
  ADD KEY `compra_id` (`compra_id`),
  ADD KEY `produto_id` (`produto_id`);

--
-- Índices de tabela `configuracoes`
--
ALTER TABLE `configuracoes`
  ADD PRIMARY KEY (`chave`);

--
-- Índices de tabela `contas_pagar`
--
ALTER TABLE `contas_pagar`
  ADD PRIMARY KEY (`id`),
  ADD KEY `centro_custo_id` (`centro_custo_id`),
  ADD KEY `fk_cp_filial` (`filial_id`);

--
-- Índices de tabela `contas_receber`
--
ALTER TABLE `contas_receber`
  ADD PRIMARY KEY (`id`),
  ADD KEY `os_id` (`os_id`),
  ADD KEY `fk_cr_filial` (`filial_id`);

--
-- Índices de tabela `depositos`
--
ALTER TABLE `depositos`
  ADD PRIMARY KEY (`id`);

--
-- Índices de tabela `estoque_detalhado`
--
ALTER TABLE `estoque_detalhado`
  ADD PRIMARY KEY (`id`),
  ADD KEY `produto_id` (`produto_id`),
  ADD KEY `deposito_id` (`deposito_id`);

--
-- Índices de tabela `filiais`
--
ALTER TABLE `filiais`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `cnpj` (`cnpj`);

--
-- Índices de tabela `fornecedores`
--
ALTER TABLE `fornecedores`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `cnpj` (`cnpj`),
  ADD KEY `fk_fornecedor_filial` (`filial_id`);

--
-- Índices de tabela `itens_os`
--
ALTER TABLE `itens_os`
  ADD PRIMARY KEY (`id`),
  ADD KEY `os_id` (`os_id`),
  ADD KEY `produto_id` (`produto_id`);

--
-- Índices de tabela `lancamentos_custos`
--
ALTER TABLE `lancamentos_custos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_lc_filial` (`filial_id`),
  ADD KEY `idx_lc_cc` (`centro_custo_id`),
  ADD KEY `idx_lc_data` (`data_lancamento`);

--
-- Índices de tabela `logs_acesso`
--
ALTER TABLE `logs_acesso`
  ADD PRIMARY KEY (`id`),
  ADD KEY `usuario_id` (`usuario_id`);

--
-- Índices de tabela `migrations`
--
ALTER TABLE `migrations`
  ADD PRIMARY KEY (`id`);

--
-- Índices de tabela `movimentacao_estoque`
--
ALTER TABLE `movimentacao_estoque`
  ADD PRIMARY KEY (`id`),
  ADD KEY `produto_id` (`produto_id`),
  ADD KEY `usuario_id` (`usuario_id`);

--
-- Índices de tabela `nfce_emitidas`
--
ALTER TABLE `nfce_emitidas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_nfce_venda` (`venda_id`),
  ADD KEY `idx_nfce_chave` (`chave`),
  ADD KEY `idx_nfce_empresa` (`empresa_id`);

--
-- Índices de tabela `nfe_importadas`
--
ALTER TABLE `nfe_importadas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `chave_acesso` (`chave_acesso`),
  ADD KEY `idx_filial` (`filial_id`),
  ADD KEY `idx_chave` (`chave_acesso`);

--
-- Índices de tabela `notas_fiscais`
--
ALTER TABLE `notas_fiscais`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `chave_acesso` (`chave_acesso`),
  ADD KEY `venda_id` (`venda_id`);

--
-- Índices de tabela `os`
--
ALTER TABLE `os`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `numero_os` (`numero_os`),
  ADD KEY `cliente_id` (`cliente_id`),
  ADD KEY `fk_os_tecnico` (`tecnico_id`),
  ADD KEY `idx_os_filial_status` (`filial_id`,`status`);

--
-- Índices de tabela `os_historico`
--
ALTER TABLE `os_historico`
  ADD PRIMARY KEY (`id`),
  ADD KEY `os_id` (`os_id`),
  ADD KEY `usuario_id` (`usuario_id`);

--
-- Índices de tabela `permissao_nivel`
--
ALTER TABLE `permissao_nivel`
  ADD PRIMARY KEY (`id`),
  ADD KEY `permissao_id` (`permissao_id`);

--
-- Índices de tabela `permissoes`
--
ALTER TABLE `permissoes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_modulo_acao` (`modulo`,`acao`);

--
-- Índices de tabela `pre_vendas`
--
ALTER TABLE `pre_vendas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `codigo` (`codigo`),
  ADD KEY `cliente_id` (`cliente_id`),
  ADD KEY `usuario_id` (`usuario_id`),
  ADD KEY `filial_id` (`filial_id`);

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
  ADD UNIQUE KEY `codigo` (`codigo`),
  ADD KEY `idx_produtos_filial_nome` (`filial_id`,`nome`);

--
-- Índices de tabela `produto_curva_abc`
--
ALTER TABLE `produto_curva_abc`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_prod_filial_per` (`produto_id`,`filial_id`,`periodo_referencia`),
  ADD KEY `filial_id` (`filial_id`);

--
-- Índices de tabela `produto_kits`
--
ALTER TABLE `produto_kits`
  ADD PRIMARY KEY (`id`),
  ADD KEY `produto_pai_id` (`produto_pai_id`),
  ADD KEY `produto_filho_id` (`produto_filho_id`);

--
-- Índices de tabela `sefaz_config`
--
ALTER TABLE `sefaz_config`
  ADD PRIMARY KEY (`id`);

--
-- Índices de tabela `transferencias_estoque`
--
ALTER TABLE `transferencias_estoque`
  ADD PRIMARY KEY (`id`),
  ADD KEY `produto_id` (`produto_id`),
  ADD KEY `origem_filial_id` (`origem_filial_id`),
  ADD KEY `destino_filial_id` (`destino_filial_id`),
  ADD KEY `usuario_id` (`usuario_id`);

--
-- Índices de tabela `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Índices de tabela `vendas`
--
ALTER TABLE `vendas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `cliente_id` (`cliente_id`),
  ADD KEY `usuario_id` (`usuario_id`),
  ADD KEY `filial_id` (`filial_id`),
  ADD KEY `fk_vendas_autorizado_por` (`autorizado_por`),
  ADD KEY `idx_vendas_tipo_nota` (`tipo_nota`),
  ADD KEY `idx_vendas_filial_data` (`filial_id`,`data_venda`),
  ADD KEY `idx_vendas_status` (`status`);

--
-- Índices de tabela `vendas_itens`
--
ALTER TABLE `vendas_itens`
  ADD PRIMARY KEY (`id`),
  ADD KEY `venda_id` (`venda_id`),
  ADD KEY `produto_id` (`produto_id`);

--
-- AUTO_INCREMENT para tabelas despejadas
--

--
-- AUTO_INCREMENT de tabela `alertas_estoque`
--
ALTER TABLE `alertas_estoque`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=54;

--
-- AUTO_INCREMENT de tabela `autorizacoes_temporarias`
--
ALTER TABLE `autorizacoes_temporarias`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT de tabela `caixas`
--
ALTER TABLE `caixas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT de tabela `caixa_movimentacoes`
--
ALTER TABLE `caixa_movimentacoes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=100;

--
-- AUTO_INCREMENT de tabela `centros_custo`
--
ALTER TABLE `centros_custo`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;

--
-- AUTO_INCREMENT de tabela `clientes`
--
ALTER TABLE `clientes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT de tabela `compras`
--
ALTER TABLE `compras`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de tabela `compra_itens`
--
ALTER TABLE `compra_itens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de tabela `contas_pagar`
--
ALTER TABLE `contas_pagar`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `contas_receber`
--
ALTER TABLE `contas_receber`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT de tabela `depositos`
--
ALTER TABLE `depositos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT de tabela `estoque_detalhado`
--
ALTER TABLE `estoque_detalhado`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `filiais`
--
ALTER TABLE `filiais`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=590;

--
-- AUTO_INCREMENT de tabela `fornecedores`
--
ALTER TABLE `fornecedores`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de tabela `itens_os`
--
ALTER TABLE `itens_os`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de tabela `lancamentos_custos`
--
ALTER TABLE `lancamentos_custos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `logs_acesso`
--
ALTER TABLE `logs_acesso`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `migrations`
--
ALTER TABLE `migrations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT de tabela `movimentacao_estoque`
--
ALTER TABLE `movimentacao_estoque`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de tabela `nfce_emitidas`
--
ALTER TABLE `nfce_emitidas`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT de tabela `nfe_importadas`
--
ALTER TABLE `nfe_importadas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `notas_fiscais`
--
ALTER TABLE `notas_fiscais`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `os`
--
ALTER TABLE `os`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de tabela `os_historico`
--
ALTER TABLE `os_historico`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `permissao_nivel`
--
ALTER TABLE `permissao_nivel`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de tabela `permissoes`
--
ALTER TABLE `permissoes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=81;

--
-- AUTO_INCREMENT de tabela `pre_vendas`
--
ALTER TABLE `pre_vendas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT de tabela `pre_venda_itens`
--
ALTER TABLE `pre_venda_itens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT de tabela `produtos`
--
ALTER TABLE `produtos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT de tabela `produto_curva_abc`
--
ALTER TABLE `produto_curva_abc`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `produto_kits`
--
ALTER TABLE `produto_kits`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `sefaz_config`
--
ALTER TABLE `sefaz_config`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de tabela `transferencias_estoque`
--
ALTER TABLE `transferencias_estoque`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de tabela `vendas`
--
ALTER TABLE `vendas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=117;

--
-- AUTO_INCREMENT de tabela `vendas_itens`
--
ALTER TABLE `vendas_itens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=116;

--
-- Restrições para tabelas despejadas
--

--
-- Restrições para tabelas `alertas_estoque`
--
ALTER TABLE `alertas_estoque`
  ADD CONSTRAINT `alertas_estoque_ibfk_1` FOREIGN KEY (`produto_id`) REFERENCES `produtos` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `alertas_estoque_ibfk_2` FOREIGN KEY (`filial_id`) REFERENCES `filiais` (`id`);

--
-- Restrições para tabelas `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD CONSTRAINT `audit_logs_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL;

--
-- Restrições para tabelas `caixas`
--
ALTER TABLE `caixas`
  ADD CONSTRAINT `caixas_ibfk_1` FOREIGN KEY (`filial_id`) REFERENCES `filiais` (`id`),
  ADD CONSTRAINT `caixas_ibfk_2` FOREIGN KEY (`operador_id`) REFERENCES `usuarios` (`id`);

--
-- Restrições para tabelas `caixa_movimentacoes`
--
ALTER TABLE `caixa_movimentacoes`
  ADD CONSTRAINT `caixa_movimentacoes_ibfk_1` FOREIGN KEY (`caixa_id`) REFERENCES `caixas` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `caixa_movimentacoes_ibfk_2` FOREIGN KEY (`operador_id`) REFERENCES `usuarios` (`id`);

--
-- Restrições para tabelas `compras`
--
ALTER TABLE `compras`
  ADD CONSTRAINT `compras_ibfk_1` FOREIGN KEY (`fornecedor_id`) REFERENCES `fornecedores` (`id`),
  ADD CONSTRAINT `compras_ibfk_2` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`);

--
-- Restrições para tabelas `compra_itens`
--
ALTER TABLE `compra_itens`
  ADD CONSTRAINT `compra_itens_ibfk_1` FOREIGN KEY (`compra_id`) REFERENCES `compras` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `compra_itens_ibfk_2` FOREIGN KEY (`produto_id`) REFERENCES `produtos` (`id`);

--
-- Restrições para tabelas `contas_pagar`
--
ALTER TABLE `contas_pagar`
  ADD CONSTRAINT `contas_pagar_ibfk_1` FOREIGN KEY (`centro_custo_id`) REFERENCES `centros_custo` (`id`),
  ADD CONSTRAINT `fk_cp_filial` FOREIGN KEY (`filial_id`) REFERENCES `filiais` (`id`);

--
-- Restrições para tabelas `contas_receber`
--
ALTER TABLE `contas_receber`
  ADD CONSTRAINT `contas_receber_ibfk_1` FOREIGN KEY (`os_id`) REFERENCES `os` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_cr_filial` FOREIGN KEY (`filial_id`) REFERENCES `filiais` (`id`);

--
-- Restrições para tabelas `estoque_detalhado`
--
ALTER TABLE `estoque_detalhado`
  ADD CONSTRAINT `estoque_detalhado_ibfk_1` FOREIGN KEY (`produto_id`) REFERENCES `produtos` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `estoque_detalhado_ibfk_2` FOREIGN KEY (`deposito_id`) REFERENCES `depositos` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `fornecedores`
--
ALTER TABLE `fornecedores`
  ADD CONSTRAINT `fk_fornecedor_filial` FOREIGN KEY (`filial_id`) REFERENCES `filiais` (`id`);

--
-- Restrições para tabelas `itens_os`
--
ALTER TABLE `itens_os`
  ADD CONSTRAINT `itens_os_ibfk_1` FOREIGN KEY (`os_id`) REFERENCES `os` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `itens_os_ibfk_2` FOREIGN KEY (`produto_id`) REFERENCES `produtos` (`id`) ON DELETE SET NULL;

--
-- Restrições para tabelas `lancamentos_custos`
--
ALTER TABLE `lancamentos_custos`
  ADD CONSTRAINT `lancamentos_custos_ibfk_1` FOREIGN KEY (`filial_id`) REFERENCES `filiais` (`id`),
  ADD CONSTRAINT `lancamentos_custos_ibfk_2` FOREIGN KEY (`centro_custo_id`) REFERENCES `centros_custo` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `logs_acesso`
--
ALTER TABLE `logs_acesso`
  ADD CONSTRAINT `logs_acesso_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL;

--
-- Restrições para tabelas `movimentacao_estoque`
--
ALTER TABLE `movimentacao_estoque`
  ADD CONSTRAINT `movimentacao_estoque_ibfk_1` FOREIGN KEY (`produto_id`) REFERENCES `produtos` (`id`),
  ADD CONSTRAINT `movimentacao_estoque_ibfk_2` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`);

--
-- Restrições para tabelas `notas_fiscais`
--
ALTER TABLE `notas_fiscais`
  ADD CONSTRAINT `notas_fiscais_ibfk_1` FOREIGN KEY (`venda_id`) REFERENCES `vendas` (`id`);

--
-- Restrições para tabelas `os`
--
ALTER TABLE `os`
  ADD CONSTRAINT `fk_os_filial` FOREIGN KEY (`filial_id`) REFERENCES `filiais` (`id`),
  ADD CONSTRAINT `fk_os_tecnico` FOREIGN KEY (`tecnico_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `os_ibfk_1` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`) ON DELETE SET NULL;

--
-- Restrições para tabelas `os_historico`
--
ALTER TABLE `os_historico`
  ADD CONSTRAINT `os_historico_ibfk_1` FOREIGN KEY (`os_id`) REFERENCES `os` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `os_historico_ibfk_2` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`);

--
-- Restrições para tabelas `permissao_nivel`
--
ALTER TABLE `permissao_nivel`
  ADD CONSTRAINT `permissao_nivel_ibfk_1` FOREIGN KEY (`permissao_id`) REFERENCES `permissoes` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `pre_vendas`
--
ALTER TABLE `pre_vendas`
  ADD CONSTRAINT `pre_vendas_ibfk_1` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`),
  ADD CONSTRAINT `pre_vendas_ibfk_2` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`),
  ADD CONSTRAINT `pre_vendas_ibfk_3` FOREIGN KEY (`filial_id`) REFERENCES `filiais` (`id`);

--
-- Restrições para tabelas `pre_venda_itens`
--
ALTER TABLE `pre_venda_itens`
  ADD CONSTRAINT `pre_venda_itens_ibfk_1` FOREIGN KEY (`pre_venda_id`) REFERENCES `pre_vendas` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `pre_venda_itens_ibfk_2` FOREIGN KEY (`produto_id`) REFERENCES `produtos` (`id`);

--
-- Restrições para tabelas `produto_curva_abc`
--
ALTER TABLE `produto_curva_abc`
  ADD CONSTRAINT `produto_curva_abc_ibfk_1` FOREIGN KEY (`produto_id`) REFERENCES `produtos` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `produto_curva_abc_ibfk_2` FOREIGN KEY (`filial_id`) REFERENCES `filiais` (`id`);

--
-- Restrições para tabelas `produto_kits`
--
ALTER TABLE `produto_kits`
  ADD CONSTRAINT `produto_kits_ibfk_1` FOREIGN KEY (`produto_pai_id`) REFERENCES `produtos` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `produto_kits_ibfk_2` FOREIGN KEY (`produto_filho_id`) REFERENCES `produtos` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `transferencias_estoque`
--
ALTER TABLE `transferencias_estoque`
  ADD CONSTRAINT `transferencias_estoque_ibfk_1` FOREIGN KEY (`produto_id`) REFERENCES `produtos` (`id`),
  ADD CONSTRAINT `transferencias_estoque_ibfk_2` FOREIGN KEY (`origem_filial_id`) REFERENCES `filiais` (`id`),
  ADD CONSTRAINT `transferencias_estoque_ibfk_3` FOREIGN KEY (`destino_filial_id`) REFERENCES `filiais` (`id`),
  ADD CONSTRAINT `transferencias_estoque_ibfk_4` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`);

--
-- Restrições para tabelas `vendas`
--
ALTER TABLE `vendas`
  ADD CONSTRAINT `fk_vendas_autorizado_por` FOREIGN KEY (`autorizado_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `vendas_ibfk_1` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`),
  ADD CONSTRAINT `vendas_ibfk_2` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`),
  ADD CONSTRAINT `vendas_ibfk_3` FOREIGN KEY (`filial_id`) REFERENCES `filiais` (`id`);

--
-- Restrições para tabelas `vendas_itens`
--
ALTER TABLE `vendas_itens`
  ADD CONSTRAINT `vendas_itens_ibfk_1` FOREIGN KEY (`venda_id`) REFERENCES `vendas` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `vendas_itens_ibfk_2` FOREIGN KEY (`produto_id`) REFERENCES `produtos` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
