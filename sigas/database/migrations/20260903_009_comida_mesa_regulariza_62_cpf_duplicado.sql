-- SIGAS / Comida na Mesa
-- Regularização controlada dos beneficiários confirmados cuja linha veio com
-- "CPF duplicado na planilha".
--
-- Regra:
-- 1) NÃO inventar CPF.
-- 2) Criar pessoa oficial com pessoas.cpf = NULL.
-- 3) Preservar o CPF informado/validado somente em comida_mesa_importacao_itens.
-- 4) Criar família e inscrição ativa normalmente.
-- 5) Marcar a inscrição como CPF pendente de regularização.
-- 6) Atualizar o item de importação com pessoa_id/familia_id/inscricao_id.
--
-- Idempotente: só processa itens confirmados como Beneficiario e ainda sem inscricao_id.

DELIMITER $$

DROP PROCEDURE IF EXISTS sp_cm_regulariza_cpf_duplicado$$
CREATE PROCEDURE sp_cm_regulariza_cpf_duplicado()
BEGIN
    DECLARE done INT DEFAULT 0;
    DECLARE v_item_id BIGINT UNSIGNED;
    DECLARE v_nome VARCHAR(150);
    DECLARE v_cpf_origem VARCHAR(40);
    DECLARE v_dados LONGTEXT;
    DECLARE v_usuario BIGINT UNSIGNED;
    DECLARE v_pessoa_id BIGINT UNSIGNED;
    DECLARE v_familia_id BIGINT UNSIGNED;
    DECLARE v_inscricao_id BIGINT UNSIGNED;
    DECLARE v_polo_id BIGINT UNSIGNED;
    DECLARE v_qtd_membros INT UNSIGNED;
    DECLARE v_prioridade VARCHAR(20);
    DECLARE v_data_inscricao DATE;

    DECLARE cur CURSOR FOR
        SELECT
            item.id,
            item.nome,
            COALESCE(NULLIF(item.cpf_validado, ''), NULLIF(item.cpf_informado, ''), ''),
            item.dados_json,
            COALESCE(item.decidido_por, 1)
        FROM comida_mesa_importacao_itens item
        WHERE item.situacao_programa = 'Beneficiario'
          AND item.inscricao_id IS NULL
          AND item.motivos LIKE '%CPF duplicado na planilha%'
        ORDER BY item.id;

    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = 1;
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;

    START TRANSACTION;
    OPEN cur;

    read_loop: LOOP
        FETCH cur INTO v_item_id, v_nome, v_cpf_origem, v_dados, v_usuario;
        IF done = 1 THEN
            LEAVE read_loop;
        END IF;

        SET v_qtd_membros = COALESCE(
            CAST(NULLIF(JSON_UNQUOTE(JSON_EXTRACT(v_dados, '$.quantidade_membros')), '') AS UNSIGNED),
            1
        );
        IF v_qtd_membros < 1 THEN
            SET v_qtd_membros = 1;
        END IF;

        SET v_prioridade = NULLIF(JSON_UNQUOTE(JSON_EXTRACT(v_dados, '$.prioridade')), '');
        IF v_prioridade IS NULL OR v_prioridade NOT IN ('alta', 'normal', 'baixa') THEN
            SET v_prioridade = 'normal';
        END IF;

        SET v_data_inscricao = STR_TO_DATE(
            NULLIF(JSON_UNQUOTE(JSON_EXTRACT(v_dados, '$.data_inscricao')), ''),
            '%Y-%m-%d'
        );
        IF v_data_inscricao IS NULL THEN
            SET v_data_inscricao = CURRENT_DATE();
        END IF;

        SET v_polo_id = CAST(NULLIF(JSON_UNQUOTE(JSON_EXTRACT(v_dados, '$.polo_id')), '') AS UNSIGNED);
        IF v_polo_id IS NOT NULL
           AND NOT EXISTS (SELECT 1 FROM comida_mesa_polos p WHERE p.id = v_polo_id) THEN
            SET v_polo_id = NULL;
        END IF;

        INSERT INTO pessoas (
            nome, cpf, nis, rg, data_nascimento, telefone, email,
            status, criado_por, atualizado_por
        ) VALUES (
            COALESCE(NULLIF(TRIM(v_nome), ''), 'Cadastro importado'),
            NULL,
            NULLIF(JSON_UNQUOTE(JSON_EXTRACT(v_dados, '$.nis')), ''),
            NULLIF(JSON_UNQUOTE(JSON_EXTRACT(v_dados, '$.rg')), ''),
            STR_TO_DATE(NULLIF(JSON_UNQUOTE(JSON_EXTRACT(v_dados, '$.data_nascimento')), ''), '%Y-%m-%d'),
            NULLIF(JSON_UNQUOTE(JSON_EXTRACT(v_dados, '$.telefone')), ''),
            NULLIF(JSON_UNQUOTE(JSON_EXTRACT(v_dados, '$.email')), ''),
            'ativo',
            v_usuario,
            v_usuario
        );
        SET v_pessoa_id = LAST_INSERT_ID();

        INSERT INTO familias (
            codigo, responsavel_pessoa_id, zona, logradouro, numero, complemento,
            bairro, comunidade, ponto_referencia, cep, quantidade_membros,
            renda_familiar, status, criado_por, atualizado_por
        ) VALUES (
            CONCAT('TMP-IMP-', v_item_id),
            v_pessoa_id,
            NULLIF(JSON_UNQUOTE(JSON_EXTRACT(v_dados, '$.zona')), ''),
            NULLIF(JSON_UNQUOTE(JSON_EXTRACT(v_dados, '$.logradouro')), ''),
            NULLIF(JSON_UNQUOTE(JSON_EXTRACT(v_dados, '$.numero')), ''),
            NULLIF(JSON_UNQUOTE(JSON_EXTRACT(v_dados, '$.complemento')), ''),
            NULLIF(JSON_UNQUOTE(JSON_EXTRACT(v_dados, '$.bairro')), ''),
            NULLIF(JSON_UNQUOTE(JSON_EXTRACT(v_dados, '$.comunidade')), ''),
            NULLIF(JSON_UNQUOTE(JSON_EXTRACT(v_dados, '$.ponto_referencia')), ''),
            NULLIF(JSON_UNQUOTE(JSON_EXTRACT(v_dados, '$.cep')), ''),
            v_qtd_membros,
            CAST(NULLIF(JSON_UNQUOTE(JSON_EXTRACT(v_dados, '$.renda_familiar')), '') AS DECIMAL(12,2)),
            'ativo',
            v_usuario,
            v_usuario
        );
        SET v_familia_id = LAST_INSERT_ID();

        UPDATE familias
           SET codigo = CONCAT('FAM-', LPAD(v_familia_id, 6, '0'))
         WHERE id = v_familia_id;

        INSERT INTO comida_mesa_inscricoes (
            familia_id, polo_id, status, prioridade, data_inscricao,
            data_aprovacao, aprovado_por, motivo_suspensao, observacao,
            criado_por, atualizado_por
        ) VALUES (
            v_familia_id,
            v_polo_id,
            'ativa',
            v_prioridade,
            v_data_inscricao,
            NOW(),
            v_usuario,
            NULL,
            CONCAT(
                'CPF pendente de regularização. O CPF ',
                COALESCE(NULLIF(v_cpf_origem, ''), 'não informado'),
                ' veio duplicado na importação e foi preservado somente como dado de origem. Benefício mantido ativo.'
            ),
            v_usuario,
            v_usuario
        );
        SET v_inscricao_id = LAST_INSERT_ID();

        UPDATE comida_mesa_importacao_itens
           SET pessoa_id = v_pessoa_id,
               familia_id = v_familia_id,
               inscricao_id = v_inscricao_id,
               efetivacao_status = 'Vinculado',
               efetivacao_motivo = CONCAT(
                   'Beneficiário ativo com CPF pendente de regularização. CPF duplicado da importação: ',
                   COALESCE(NULLIF(v_cpf_origem, ''), 'não informado'),
                   '. Nenhum CPF artificial foi criado.'
               )
         WHERE id = v_item_id
           AND inscricao_id IS NULL;
    END LOOP;

    CLOSE cur;
    COMMIT;
END$$

DELIMITER ;

CALL sp_cm_regulariza_cpf_duplicado();
DROP PROCEDURE IF EXISTS sp_cm_regulariza_cpf_duplicado;

-- Conferência esperada após execução:
-- SELECT COUNT(*) AS ainda_pendentes
-- FROM comida_mesa_importacao_itens
-- WHERE situacao_programa = 'Beneficiario'
--   AND inscricao_id IS NULL
--   AND motivos LIKE '%CPF duplicado na planilha%';
-- Resultado esperado para a carga atual: 0.
