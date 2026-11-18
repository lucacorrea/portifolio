-- Importar DEPOIS do arquivo mysql.sql no phpMyAdmin.
-- Login inicial: admin@lucascorrea.pro
-- A senha temporaria foi fornecida FORA do repositorio e deve ser trocada no primeiro acesso.
-- SQL idempotente; nao altera senhas de usuarios existentes.
INSERT INTO users (name, email, password_hash, role, active, must_change_password, failed_attempts)
SELECT 'Administrador OBRAS360', 'admin@lucascorrea.pro', '$argon2id$v=19$m=65536,t=3,p=1$OERuSmJtd3lxclZVMHF1QQ$Dk7TTS6xD5/WuQOmI8z6crtZ3oDmQn0dZ2LsExPHuS8', 'admin', 1, 1, 0
WHERE NOT EXISTS (SELECT 1 FROM users WHERE email = 'admin@lucascorrea.pro');
