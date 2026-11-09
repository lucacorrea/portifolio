SET NAMES utf8mb4;
START TRANSACTION;

ALTER TABLE comida_mesa_importacoes
    ADD COLUMN IF NOT EXISTS pendentes_confirmacao INT UNSIGNED NOT NULL DEFAULT 0 AFTER erros,
    ADD COLUMN IF NOT EXISTS beneficiarios_confirmados INT UNSIGNED NOT NULL DEFAULT 0 AFTER pendentes_confirmacao,
    ADD COLUMN IF NOT EXISTS lista_espera_confirmados INT UNSIGNED NOT NULL DEFAULT 0 AFTER beneficiarios_confirmados;

ALTER TABLE comida_mesa_importacao_itens
    ADD COLUMN IF NOT EXISTS situacao_programa VARCHAR(20) NOT NULL DEFAULT 'Pendente' AFTER status,
    ADD COLUMN IF NOT EXISTS decidido_em DATETIME NULL AFTER dados_json,
    ADD COLUMN IF NOT EXISTS decidido_por BIGINT UNSIGNED NULL AFTER decidido_em,
    ADD COLUMN IF NOT EXISTS efetivacao_status VARCHAR(20) NOT NULL DEFAULT 'Pendente' AFTER decidido_por,
    ADD COLUMN IF NOT EXISTS efetivacao_motivo VARCHAR(255) NULL AFTER efetivacao_status;

ALTER TABLE comida_mesa_importacao_itens
    ADD INDEX IF NOT EXISTS idx_cm_import_itens_situacao_programa (situacao_programa),
    ADD INDEX IF NOT EXISTS idx_cm_import_itens_decidido_por (decidido_por),
    ADD INDEX IF NOT EXISTS idx_cm_import_itens_efetivacao (efetivacao_status);

-- Mantém compatibilidade com cargas já processadas antes deste fluxo.
-- Itens que já apontam para uma inscrição ativa/lista de espera são reconhecidos
-- conforme a situação oficial já existente; os demais ficam aguardando confirmação.
UPDATE comida_mesa_importacao_itens item
LEFT JOIN comida_mesa_inscricoes ins ON ins.id = item.inscricao_id
SET item.situacao_programa = CASE
        WHEN ins.status = 'ativa' THEN 'Beneficiario'
        WHEN ins.status = 'lista_espera' THEN 'ListaEspera'
        ELSE 'Pendente'
    END,
    item.efetivacao_status = CASE
        WHEN item.inscricao_id IS NOT NULL THEN 'Vinculado'
        ELSE 'Pendente'
    END
WHERE item.situacao_programa = 'Pendente';

UPDATE comida_mesa_importacoes imp
SET imp.pendentes_confirmacao = (
        SELECT COUNT(*) FROM comida_mesa_importacao_itens item
        WHERE item.importacao_id = imp.id AND item.situacao_programa = 'Pendente'
    ),
    imp.beneficiarios_confirmados = (
        SELECT COUNT(*) FROM comida_mesa_importacao_itens item
        WHERE item.importacao_id = imp.id AND item.situacao_programa = 'Beneficiario'
    ),
    imp.lista_espera_confirmados = (
        SELECT COUNT(*) FROM comida_mesa_importacao_itens item
        WHERE item.importacao_id = imp.id AND item.situacao_programa = 'ListaEspera'
    );

COMMIT;
