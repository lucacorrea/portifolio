SET NAMES utf8mb4;

INSERT INTO empresas (id, nome, nome_fantasia, telefone, endereco, ativo)
VALUES (1, 'L&J Soluções Tech', 'L&J Caixa', '(97) 99999-0000', 'Coari - AM', 1)
ON DUPLICATE KEY UPDATE nome = VALUES(nome);

-- Acesso inicial:
-- E-mail: admin@ljsolucoestech.com.br
-- Senha: Admin@123
INSERT INTO usuarios (id, empresa_id, nome, email, senha_hash, nivel, ativo)
VALUES (1, 1, 'Administrador', 'admin@.com.br', '$2y$12$CVdQ49rtTQ7UJF9inzfV5udXRpJV0bXzm5iWrI1kSxd5hmvDlNp72', 'admin', 1)
ON DUPLICATE KEY UPDATE email = VALUES(email);

INSERT INTO categorias (empresa_id, nome) VALUES
(1, 'Laticínios'),
(1, 'Mercearia'),
(1, 'Higiene')
ON DUPLICATE KEY UPDATE nome = VALUES(nome);

INSERT INTO configuracoes (empresa_id, chave, valor) VALUES
(1, 'comprovante_modo', 'perguntar'),
(1, 'comprovante_modelo', 'detalhado'),
(1, 'alerta_validade_dias', '7'),
(1, 'prazo_divida_dias', '30'),
(1, 'estoque_minimo_padrao', '0'),
(1, 'bloquear_produto_vencido', '1'),
(1, 'bloquear_estoque_negativo', '1'),
(1, 'alertar_estoque_baixo', '1'),
(1, 'pagamento_pix', '1'),
(1, 'pagamento_dinheiro', '1'),
(1, 'pagamento_credito', '1'),
(1, 'pagamento_debito', '1'),
(1, 'pagamento_conta_cliente', '1'),
(1, 'pagamento_misto', '1'),
(1, 'permitir_desconto', '1'),
(1, 'limite_desconto_percentual', '0'),
(1, 'exigir_cliente_conta', '1'),
(1, 'exigir_motivo_cancelamento', '1'),
(1, 'auditoria_ativa', '1'),
(1, 'confirmar_exclusoes', '1'),
(1, 'pin_operador_ativo', '0'),
(1, 'notificacoes_ativas', '1')
ON DUPLICATE KEY UPDATE valor = VALUES(valor);
