-- Execute uma única vez em bases existentes para permitir login por CPF/CNPJ.
-- O documento é armazenado apenas com números para facilitar busca e evitar duplicidade.

ALTER TABLE usuarios
    ADD COLUMN documento VARCHAR(20) DEFAULT NULL AFTER email,
    ADD COLUMN documento_tipo ENUM('cpf','cnpj') DEFAULT NULL AFTER documento;

ALTER TABLE usuarios
    ADD UNIQUE KEY uq_usuarios_documento (documento);
