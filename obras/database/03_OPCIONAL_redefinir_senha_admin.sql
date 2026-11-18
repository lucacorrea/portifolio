-- APENAS PARA RECUPERACAO AUTORIZADA DE CONTA ADMINISTRADORA EXISTENTE.
-- A senha temporaria correspondente está no arquivo de credenciais PRIVADO.
-- Execute manualmente no phpMyAdmin somente se a conta admin@lucascorrea.pro
-- ja existir e voce precisar recuperar o acesso.
-- Troca obrigatoria da senha no proximo login.
UPDATE users
SET password_hash = '$argon2id$v=19$m=65536,t=3,p=1$OERuSmJtd3lxclZVMHF1QQ$Dk7TTS6xD5/WuQOmI8z6crtZ3oDmQn0dZ2LsExPHuS8',
    must_change_password = 1,
    failed_attempts = 0,
    locked_until = NULL,
    active = 1,
    updated_at = CURRENT_TIMESTAMP
WHERE email = 'admin@lucascorrea.pro' AND role = 'admin';
