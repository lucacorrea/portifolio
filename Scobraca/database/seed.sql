USE tatico_gps_saas;

INSERT INTO planos
(nome, preco, limite_clientes, limite_usuarios, whatsapp_ativo, leitura_comprovante, relatorios_avancados, ativo)
VALUES
('Básico', 79.90, 50, 1, 1, 0, 0, 1),
('Profissional', 149.90, 200, 3, 1, 1, 1, 1),
('Premium', 199.90, NULL, NULL, 1, 1, 1, 1);

INSERT INTO usuarios
(empresa_id, nome, email, senha, tipo, ativo)
VALUES
(NULL, 'Administrador Plataforma', 'admin@taticogps.com.br', '$2y$12$ISDF67fzkJUUCcT7ZKOtFOokJXNIu8uDtkAlB6E75s1Zz6QQfrSLe', 'platform_admin', 1);
