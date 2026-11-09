SET NAMES utf8mb4;
SET time_zone = '-04:00';

INSERT INTO usuarios (nome, email, senha_hash, nivel, ativo)
VALUES ('Professor Demo', 'professor@biauto.local', '$2y$12$UBC.Oz.6A63WW8IYruZZuuU3JOBGfWUk5viviyCpL6.GV.Npi3T9e', 'admin', 1)
ON DUPLICATE KEY UPDATE nome = VALUES(nome), nivel = VALUES(nivel), ativo = 1;

SET @usuario_demo = (SELECT id FROM usuarios WHERE email = 'professor@biauto.local' LIMIT 1);

INSERT INTO fornecedores (nome_razao, cpf_cnpj, contato, telefone, email, observacoes, ativo)
VALUES
('Auto Peças Amazonas Ltda', '12456789000110', 'Carlos Mendes', '(92) 99111-2200', 'vendas@autopecasamazonas.com.br', 'Fornecedor fictício de filtros e lubrificantes.', 1),
('Norte Freios Distribuidora', '23567890000121', 'Mariana Lopes', '(92) 99222-3300', 'comercial@nortefreios.com.br', 'Fornecedor fictício de peças de freio.', 1),
('Elétrica Motor Center', '34678901000132', 'Rafael Lima', '(92) 99333-4400', 'contato@eletricamotor.com.br', 'Fornecedor fictício de peças elétricas.', 1)
ON DUPLICATE KEY UPDATE nome_razao = VALUES(nome_razao), contato = VALUES(contato), telefone = VALUES(telefone), email = VALUES(email), ativo = 1;

SET @fornecedor_1 = (SELECT id FROM fornecedores WHERE cpf_cnpj = '12456789000110' LIMIT 1);
SET @fornecedor_2 = (SELECT id FROM fornecedores WHERE cpf_cnpj = '23567890000121' LIMIT 1);
SET @fornecedor_3 = (SELECT id FROM fornecedores WHERE cpf_cnpj = '34678901000132' LIMIT 1);

INSERT INTO clientes (tipo, nome_razao, cpf_cnpj, rg_ie, telefone, whatsapp, email, cep, logradouro, numero, bairro, cidade, uf, observacoes, ativo, created_by, updated_by)
VALUES
('PF', 'João da Silva', '12345678901', '1234567', '(97) 99111-1111', '(97) 99111-1111', 'joao.silva@email.com', '69460000', 'Rua das Flores', '120', 'Centro', 'Coari', 'AM', 'Cliente fictício para testes.', 1, @usuario_demo, @usuario_demo),
('PF', 'Maria Souza', '23456789012', '2345678', '(97) 99222-2222', '(97) 99222-2222', 'maria.souza@email.com', '69460000', 'Avenida Amazonas', '450', 'Tauá-Mirim', 'Coari', 'AM', 'Cliente fictícia para testes.', 1, @usuario_demo, @usuario_demo),
('PF', 'Lucas Lima', '34567890123', '3456789', '(97) 99333-3333', '(97) 99333-3333', 'lucas.lima@email.com', '69460000', 'Rua Samuel Fritz', '88', 'Duque de Caxias', 'Coari', 'AM', 'Cliente fictício para testes.', 1, @usuario_demo, @usuario_demo),
('PF', 'Rita Alves', '45678901234', '4567890', '(97) 99444-4444', '(97) 99444-4444', 'rita.alves@email.com', '69460000', 'Estrada Coari-Mamiá', '55', 'Itamarati', 'Coari', 'AM', 'Cliente fictícia para orçamento.', 1, @usuario_demo, @usuario_demo),
('PJ', 'Transporte Solimões Ltda', '45789012000143', '987654321', '(97) 99555-5555', '(97) 99555-5555', 'frota@transportesolimoes.com.br', '69460000', 'Avenida do Futuro', '1000', 'União', 'Coari', 'AM', 'Empresa fictícia com frota de veículos.', 1, @usuario_demo, @usuario_demo)
ON DUPLICATE KEY UPDATE nome_razao = VALUES(nome_razao), telefone = VALUES(telefone), whatsapp = VALUES(whatsapp), email = VALUES(email), ativo = 1, updated_by = @usuario_demo;

SET @cliente_joao = (SELECT id FROM clientes WHERE cpf_cnpj = '12345678901' LIMIT 1);
SET @cliente_maria = (SELECT id FROM clientes WHERE cpf_cnpj = '23456789012' LIMIT 1);
SET @cliente_lucas = (SELECT id FROM clientes WHERE cpf_cnpj = '34567890123' LIMIT 1);
SET @cliente_rita = (SELECT id FROM clientes WHERE cpf_cnpj = '45678901234' LIMIT 1);
SET @cliente_empresa = (SELECT id FROM clientes WHERE cpf_cnpj = '45789012000143' LIMIT 1);

INSERT INTO veiculos (cliente_id, placa, marca, modelo, versao, ano_fabricacao, ano_modelo, cor, combustivel, chassi, renavam, km_atual, observacoes, ativo)
VALUES
(@cliente_joao, 'ABC1D23', 'Toyota', 'Hilux', 'SRV 2.8', 2021, 2022, 'Prata', 'diesel', '9BWZZZ377VT000101', '10000000001', 83520, 'Veículo fictício do João.', 1),
(@cliente_maria, 'DEF4G56', 'Chevrolet', 'Onix', 'LT 1.0', 2019, 2020, 'Branco', 'flex', '9BWZZZ377VT000102', '10000000002', 52110, 'Veículo fictício da Maria.', 1),
(@cliente_lucas, 'HIJ7K89', 'Honda', 'Civic', 'EXL 2.0', 2018, 2019, 'Cinza', 'flex', '9BWZZZ377VT000103', '10000000003', 68740, 'Veículo fictício do Lucas.', 1),
(@cliente_rita, 'KLM2N34', 'Fiat', 'Argo', 'Drive 1.3', 2022, 2023, 'Vermelho', 'flex', '9BWZZZ377VT000104', '10000000004', 31450, 'Veículo fictício da Rita.', 1),
(@cliente_empresa, 'NOP5Q67', 'Volkswagen', 'Saveiro', 'Robust 1.6', 2020, 2021, 'Branco', 'flex', '9BWZZZ377VT000105', '10000000005', 96400, 'Veículo fictício da frota.', 1)
ON DUPLICATE KEY UPDATE cliente_id = VALUES(cliente_id), marca = VALUES(marca), modelo = VALUES(modelo), km_atual = VALUES(km_atual), ativo = 1;

SET @veiculo_joao = (SELECT id FROM veiculos WHERE placa = 'ABC1D23' LIMIT 1);
SET @veiculo_maria = (SELECT id FROM veiculos WHERE placa = 'DEF4G56' LIMIT 1);
SET @veiculo_rita = (SELECT id FROM veiculos WHERE placa = 'KLM2N34' LIMIT 1);

INSERT INTO mecanicos (nome, cpf, telefone, email, especialidades, comissao_percentual, ativo)
VALUES
('Carlos Alberto', '11122233344', '(97) 99611-1111', 'carlos@biauto.local', 'Motor, suspensão e freios', 5.00, 1),
('Paulo Henrique', '22233344455', '(97) 99622-2222', 'paulo@biauto.local', 'Elétrica e injeção eletrônica', 5.00, 1),
('Renato Costa', '33344455566', '(97) 99633-3333', 'renato@biauto.local', 'Freios e revisão geral', 4.00, 1)
ON DUPLICATE KEY UPDATE nome = VALUES(nome), telefone = VALUES(telefone), email = VALUES(email), especialidades = VALUES(especialidades), ativo = 1;

SET @mecanico_carlos = (SELECT id FROM mecanicos WHERE cpf = '11122233344' LIMIT 1);
SET @mecanico_paulo = (SELECT id FROM mecanicos WHERE cpf = '22233344455' LIMIT 1);

INSERT INTO servicos (nome, categoria, descricao, tempo_estimado_min, valor_base, ativo)
SELECT 'Troca de óleo', 'Manutenção', 'Troca de óleo do motor e verificação básica.', 40, 150.00, 1
WHERE NOT EXISTS (SELECT 1 FROM servicos WHERE nome = 'Troca de óleo' AND deleted_at IS NULL);
INSERT INTO servicos (nome, categoria, descricao, tempo_estimado_min, valor_base, ativo)
SELECT 'Revisão de freios', 'Freios', 'Inspeção do sistema de freios.', 90, 250.00, 1
WHERE NOT EXISTS (SELECT 1 FROM servicos WHERE nome = 'Revisão de freios' AND deleted_at IS NULL);
INSERT INTO servicos (nome, categoria, descricao, tempo_estimado_min, valor_base, ativo)
SELECT 'Alinhamento', 'Suspensão', 'Alinhamento da direção.', 45, 120.00, 1
WHERE NOT EXISTS (SELECT 1 FROM servicos WHERE nome = 'Alinhamento' AND deleted_at IS NULL);
INSERT INTO servicos (nome, categoria, descricao, tempo_estimado_min, valor_base, ativo)
SELECT 'Balanceamento', 'Rodas', 'Balanceamento das quatro rodas.', 50, 100.00, 1
WHERE NOT EXISTS (SELECT 1 FROM servicos WHERE nome = 'Balanceamento' AND deleted_at IS NULL);
INSERT INTO servicos (nome, categoria, descricao, tempo_estimado_min, valor_base, ativo)
SELECT 'Diagnóstico eletrônico', 'Elétrica', 'Leitura de falhas com scanner.', 60, 180.00, 1
WHERE NOT EXISTS (SELECT 1 FROM servicos WHERE nome = 'Diagnóstico eletrônico' AND deleted_at IS NULL);
INSERT INTO servicos (nome, categoria, descricao, tempo_estimado_min, valor_base, ativo)
SELECT 'Troca de pastilhas', 'Freios', 'Substituição das pastilhas dianteiras.', 80, 220.00, 1
WHERE NOT EXISTS (SELECT 1 FROM servicos WHERE nome = 'Troca de pastilhas' AND deleted_at IS NULL);

SET @servico_oleo = (SELECT id FROM servicos WHERE nome = 'Troca de óleo' AND deleted_at IS NULL ORDER BY id LIMIT 1);
SET @servico_freio = (SELECT id FROM servicos WHERE nome = 'Revisão de freios' AND deleted_at IS NULL ORDER BY id LIMIT 1);
SET @servico_pastilha = (SELECT id FROM servicos WHERE nome = 'Troca de pastilhas' AND deleted_at IS NULL ORDER BY id LIMIT 1);

INSERT INTO pecas (fornecedor_id, codigo, codigo_barras, nome, marca, unidade, estoque_atual, estoque_minimo, custo_medio, preco_venda, localizacao, ativo)
VALUES
(@fornecedor_1, 'OLE-5W30', '7890000000011', 'Óleo 5W30', 'Lubrax', 'UN', 24.000, 10.000, 38.00, 58.00, 'A1', 1),
(@fornecedor_1, 'FLT-OLEO-01', '7890000000028', 'Filtro de óleo', 'Tecfil', 'UN', 12.000, 5.000, 28.00, 45.00, 'A2', 1),
(@fornecedor_1, 'FLT-AR-01', '7890000000035', 'Filtro de ar', 'Tecfil', 'UN', 8.000, 4.000, 42.00, 68.00, 'A3', 1),
(@fornecedor_2, 'PST-DIANT-01', '7890000000042', 'Pastilha de freio dianteira', 'Cobreq', 'JG', 7.000, 3.000, 165.00, 240.00, 'B1', 1),
(@fornecedor_2, 'DSC-FREIO-01', '7890000000059', 'Disco de freio dianteiro', 'Fremax', 'UN', 4.000, 2.000, 190.00, 285.00, 'B2', 1),
(@fornecedor_3, 'VELA-IGN-01', '7890000000066', 'Jogo de velas de ignição', 'NGK', 'JG', 6.000, 2.000, 110.00, 165.00, 'C1', 1),
(@fornecedor_3, 'BAT-60AH-01', '7890000000073', 'Bateria 60Ah', 'Moura', 'UN', 3.000, 2.000, 410.00, 560.00, 'C2', 1)
ON DUPLICATE KEY UPDATE fornecedor_id = VALUES(fornecedor_id), nome = VALUES(nome), marca = VALUES(marca), estoque_atual = VALUES(estoque_atual), estoque_minimo = VALUES(estoque_minimo), custo_medio = VALUES(custo_medio), preco_venda = VALUES(preco_venda), ativo = 1;

SET @peca_oleo = (SELECT id FROM pecas WHERE codigo = 'OLE-5W30' LIMIT 1);
SET @peca_filtro = (SELECT id FROM pecas WHERE codigo = 'FLT-OLEO-01' LIMIT 1);
SET @peca_pastilha = (SELECT id FROM pecas WHERE codigo = 'PST-DIANT-01' LIMIT 1);

INSERT INTO estoque_movimentos (peca_id, tipo, quantidade, custo_unitario, origem_tipo, observacao, usuario_id)
VALUES
(@peca_oleo, 'entrada', 24.000, 38.00, 'demo', 'Estoque fictício inicial', @usuario_demo),
(@peca_filtro, 'entrada', 12.000, 28.00, 'demo', 'Estoque fictício inicial', @usuario_demo),
(@peca_pastilha, 'entrada', 8.000, 165.00, 'demo', 'Estoque fictício inicial', @usuario_demo),
(@peca_pastilha, 'saida', 1.000, 165.00, 'demo', 'Uso em ordem fictícia', @usuario_demo);

INSERT INTO orcamentos (numero, cliente_id, veiculo_id, status, validade_ate, observacoes, subtotal_servicos, subtotal_pecas, desconto, total, aprovado_em, created_by)
VALUES ('ORC-DEMO-00001', @cliente_rita, @veiculo_rita, 'aprovado', DATE_ADD(CURDATE(), INTERVAL 7 DAY), 'Orçamento fictício para demonstração.', 150.00, 277.00, 27.00, 400.00, NOW(), @usuario_demo)
ON DUPLICATE KEY UPDATE status = VALUES(status), validade_ate = VALUES(validade_ate), subtotal_servicos = VALUES(subtotal_servicos), subtotal_pecas = VALUES(subtotal_pecas), desconto = VALUES(desconto), total = VALUES(total);

SET @orcamento_demo = (SELECT id FROM orcamentos WHERE numero = 'ORC-DEMO-00001' LIMIT 1);

INSERT INTO orcamento_servicos (orcamento_id, servico_id, descricao, quantidade, valor_unitario, total)
SELECT @orcamento_demo, @servico_oleo, 'Troca de óleo', 1.000, 150.00, 150.00
WHERE NOT EXISTS (SELECT 1 FROM orcamento_servicos WHERE orcamento_id = @orcamento_demo AND descricao = 'Troca de óleo');

INSERT INTO orcamento_pecas (orcamento_id, peca_id, descricao, quantidade, valor_unitario, total)
SELECT @orcamento_demo, @peca_oleo, 'Óleo 5W30', 4.000, 58.00, 232.00
WHERE NOT EXISTS (SELECT 1 FROM orcamento_pecas WHERE orcamento_id = @orcamento_demo AND peca_id = @peca_oleo);
INSERT INTO orcamento_pecas (orcamento_id, peca_id, descricao, quantidade, valor_unitario, total)
SELECT @orcamento_demo, @peca_filtro, 'Filtro de óleo', 1.000, 45.00, 45.00
WHERE NOT EXISTS (SELECT 1 FROM orcamento_pecas WHERE orcamento_id = @orcamento_demo AND peca_id = @peca_filtro);

INSERT INTO ordens_servico (numero, cliente_id, veiculo_id, mecanico_responsavel_id, status, km_entrada, data_entrada, previsao_entrega, relato_cliente, diagnostico, observacoes, subtotal_servicos, subtotal_pecas, desconto, acrescimo, total, created_by, updated_by)
VALUES ('OS-DEMO-00001', @cliente_joao, @veiculo_joao, @mecanico_carlos, 'em_servico', 83520, NOW(), DATE_ADD(NOW(), INTERVAL 2 DAY), 'Cliente relatou ruído ao frear.', 'Pastilhas dianteiras com desgaste.', 'Ordem fictícia para demonstração.', 220.00, 240.00, 10.00, 0.00, 450.00, @usuario_demo, @usuario_demo)
ON DUPLICATE KEY UPDATE mecanico_responsavel_id = VALUES(mecanico_responsavel_id), status = VALUES(status), diagnostico = VALUES(diagnostico), subtotal_servicos = VALUES(subtotal_servicos), subtotal_pecas = VALUES(subtotal_pecas), desconto = VALUES(desconto), total = VALUES(total), updated_by = @usuario_demo;

SET @ordem_demo = (SELECT id FROM ordens_servico WHERE numero = 'OS-DEMO-00001' LIMIT 1);

INSERT INTO ordem_servico_mecanicos (ordem_servico_id, mecanico_id, papel)
VALUES (@ordem_demo, @mecanico_carlos, 'Responsável')
ON DUPLICATE KEY UPDATE papel = VALUES(papel);

INSERT INTO ordem_servico_servicos (ordem_servico_id, servico_id, mecanico_id, descricao, quantidade, valor_unitario, total, status, iniciado_em)
SELECT @ordem_demo, @servico_pastilha, @mecanico_carlos, 'Troca de pastilhas', 1.000, 220.00, 220.00, 'em_execucao', NOW()
WHERE NOT EXISTS (SELECT 1 FROM ordem_servico_servicos WHERE ordem_servico_id = @ordem_demo AND descricao = 'Troca de pastilhas');

INSERT INTO ordem_servico_pecas (ordem_servico_id, peca_id, descricao, quantidade, custo_unitario, valor_unitario, total, baixada_estoque)
SELECT @ordem_demo, @peca_pastilha, 'Pastilha de freio dianteira', 1.000, 165.00, 240.00, 240.00, 1
WHERE NOT EXISTS (SELECT 1 FROM ordem_servico_pecas WHERE ordem_servico_id = @ordem_demo AND peca_id = @peca_pastilha);

INSERT INTO ordem_servico_historico (ordem_servico_id, usuario_id, status_anterior, status_novo, acao, observacao)
SELECT @ordem_demo, @usuario_demo, 'aberta', 'em_servico', 'Status alterado', 'Serviço fictício iniciado'
WHERE NOT EXISTS (SELECT 1 FROM ordem_servico_historico WHERE ordem_servico_id = @ordem_demo AND observacao = 'Serviço fictício iniciado');

INSERT INTO pagamentos (ordem_servico_id, forma, status, valor, pago_em, parcelas, referencia, observacao, usuario_id)
SELECT @ordem_demo, 'pix', 'pago', 200.00, NOW(), 1, 'PIX-DEMO-001', 'Pagamento fictício parcial.', @usuario_demo
WHERE NOT EXISTS (SELECT 1 FROM pagamentos WHERE referencia = 'PIX-DEMO-001');

INSERT INTO configuracoes (chave, valor, tipo, descricao, updated_by)
VALUES
('empresa.nome', 'Bianka Oficina Mecânica', 'string', 'Nome exibido da oficina', @usuario_demo),
('empresa.cidade', 'Coari', 'string', 'Cidade da oficina', @usuario_demo),
('empresa.uf', 'AM', 'string', 'UF da oficina', @usuario_demo),
('os.prefixo', 'OS', 'string', 'Prefixo da ordem de serviço', @usuario_demo),
('orcamento.prefixo', 'ORC', 'string', 'Prefixo do orçamento', @usuario_demo),
('os.permitir_desconto', '1', 'boolean', 'Permite desconto em ordem de serviço e orçamento', @usuario_demo),
('os.exigir_mecanico', '0', 'boolean', 'Exige mecânico responsável ao criar ordem de serviço', @usuario_demo),
('estoque.controlar_minimo', '1', 'boolean', 'Habilita alertas de estoque mínimo', @usuario_demo),
('estoque.permitir_negativo', '0', 'boolean', 'Permite saldo de estoque negativo', @usuario_demo)
ON DUPLICATE KEY UPDATE valor = VALUES(valor), tipo = VALUES(tipo), descricao = VALUES(descricao), updated_by = @usuario_demo;
