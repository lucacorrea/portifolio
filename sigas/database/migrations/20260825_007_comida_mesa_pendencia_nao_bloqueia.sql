SET NAMES utf8mb4;
START TRANSACTION;

-- Beneficiário importado pode ser oficializado mesmo quando o CPF ainda precisa
-- ser regularizado. O valor original continua preservado em
-- comida_mesa_importacao_itens.cpf_informado.
ALTER TABLE pessoas
    MODIFY COLUMN cpf CHAR(11) NULL;

-- Polo é informação operacional/cadastral e não condição para receber o benefício.
-- Mantemos a FK, mas permitimos entrega sem polo definido.
ALTER TABLE comida_mesa_entregas
    DROP FOREIGN KEY fk_comida_mesa_entregas_polo;

ALTER TABLE comida_mesa_entregas
    MODIFY COLUMN polo_id BIGINT UNSIGNED NULL;

ALTER TABLE comida_mesa_entregas
    ADD CONSTRAINT fk_comida_mesa_entregas_polo
        FOREIGN KEY (polo_id) REFERENCES comida_mesa_polos (id)
        ON UPDATE CASCADE ON DELETE SET NULL;

COMMIT;
