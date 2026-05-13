USE fluxpay_saas;

INSERT INTO planos
(nome, preco, limite_clientes, limite_usuarios, whatsapp_ativo, leitura_comprovante, relatorios_avancados, ativo)
VALUES
('Start', 49.00, 50, 1, 1, 0, 0, 1),
('Growth', 89.00, 200, 1, 1, 1, 1, 1),
('Pro', 129.00, NULL, 5, 1, 1, 1, 1),
('Scale', 199.00, NULL, NULL, 1, 1, 1, 1);

INSERT INTO usuarios
(empresa_id, nome, email, senha, tipo, ativo)
VALUES
(NULL, 'Administrador Plataforma', 'admin@fluxpay.com.br', '$2y$12$ISDF67fzkJUUCcT7ZKOtFOokJXNIu8uDtkAlB6E75s1Zz6QQfrSLe', 'platform_admin', 1);
