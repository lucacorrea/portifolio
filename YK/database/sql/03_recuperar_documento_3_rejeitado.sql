-- Recupera somente o estado local do documento 3.
--
-- A resposta preservada mostrou conclusivamente:
-- cStat externo 104 - Lote processado
-- cStat infProt 1115 - Rejeicao: IBS/CBS nao informado
--
-- Não autoriza nota e não cria nova numeração.

START TRANSACTION;

UPDATE documentos_fiscais AS documento
INNER JOIN fiscal_documento_tentativas AS tentativa
        ON tentativa.documento_fiscal_id = documento.id
       AND tentativa.id = 2
SET
    documento.status = 'rejeitada',
    documento.processamento_status = 'rejeitado',
    documento.cstat = '1115',
    documento.xmotivo = 'Rejeicao: IBS/CBS nao informado',
    documento.ultima_resposta_path = tentativa.resposta_path,
    documento.ultima_resposta_sha256 = tentativa.resposta_sha256,
    documento.reconsulta_apos = NULL
WHERE documento.id = 3
  AND documento.ordem_servico_id = 119
  AND documento.ambiente = 'homologacao'
  AND documento.modelo = '65'
  AND documento.chave = '13260814171052000135650010000000031829839630'
  AND documento.processamento_status = 'pendente_reconsulta';

UPDATE fiscal_documento_tentativas
SET
    status = 'rejeitado',
    cstat = '1115',
    xmotivo = 'Rejeicao: IBS/CBS nao informado',
    finalizado_em = COALESCE(finalizado_em, CURRENT_TIMESTAMP)
WHERE id = 2
  AND documento_fiscal_id = 3
  AND status = 'pendente_reconsulta';

COMMIT;

SELECT
    id,
    ordem_servico_id,
    ambiente,
    modelo,
    serie,
    numero,
    chave,
    cstat,
    xmotivo,
    processamento_status,
    ultima_resposta_path
FROM documentos_fiscais
WHERE id = 3;
