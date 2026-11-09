SET NAMES utf8mb4;

-- Compatibilidade para bases que receberam as migrations de importação/confirmacao,
-- mas ainda mantêm pessoas.cpf como NOT NULL do schema inicial.
--
-- No Comida na Mesa, CPF/RG vem da planilha original e nem sempre contém um CPF
-- válido de 11 dígitos. Isso é pendência cadastral, não motivo para retirar a pessoa
-- da lista de beneficiários. O valor original continua preservado em
-- comida_mesa_importacao_itens.cpf_informado.
--
-- MySQL/MariaDB permitem múltiplos NULL em índice UNIQUE, portanto a unicidade dos
-- CPFs válidos permanece protegida.
ALTER TABLE pessoas
    MODIFY COLUMN cpf CHAR(11) NULL;
