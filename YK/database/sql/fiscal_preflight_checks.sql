-- Somente leitura. Execute antes de qualquer alteracao fiscal e guarde o resultado.

SELECT VERSION() AS mariadb_version,
       @@sql_mode AS sql_mode,
       @@character_set_database AS database_charset,
       @@collation_database AS database_collation;

SELECT table_name, engine, table_collation
  FROM information_schema.tables
 WHERE table_schema = DATABASE()
   AND table_name IN (
       'configuracoes_empresa','produtos','clientes','ordens_servico','ordem_servico_itens',
       'ordem_servico_pagamentos','contas_receber',
       'documentos_fiscais','fiscal_configuracoes','fiscal_series','fiscal_certificados',
       'fiscal_documento_eventos','fiscal_documento_tentativas','fiscal_auditoria',
       'fiscal_pagamento_alocacoes','fiscal_ibs_cbs_classificacoes','fiscal_inutilizacoes'
   )
 ORDER BY table_name;

SELECT table_name, column_name, column_type, is_nullable, column_default
  FROM information_schema.columns
 WHERE table_schema = DATABASE()
   AND (
       (table_name = 'produtos' AND column_name IN (
           'ncm','cest','origem_mercadoria','cfop_padrao','cst_icms','csosn','cst_pis','cst_cofins',
           'aliquota_icms','aliquota_pis','aliquota_cofins','gtin_tributavel','unidade_tributavel',
           'cst_ibs_cbs','classificacao_tributaria_ibs_cbs'
       ))
       OR (table_name = 'documentos_fiscais' AND column_name IN (
           'ambiente','modelo','serie','numero','cnf','idempotency_key','processamento_status',
           'snapshot_json','chave','protocolo','tentativas','cancelamento_status'
       ))
       OR (table_name = 'fiscal_configuracoes' AND column_name IN (
           'ambiente','modelo','status','schema_versao','qr_code_versao','csc_id','csc_ciphertext'
       ))
       OR (table_name = 'fiscal_series' AND column_name IN (
           'ambiente','modelo','serie','proximo_numero','status'
       ))
       OR (table_name = 'fiscal_certificados' AND column_name IN (
           'titular_cnpj','valido_de','valido_ate','arquivo_referencia','arquivo_sha256',
           'certificado_fingerprint_sha256','senha_ciphertext','senha_nonce','senha_tag','status'
       ))
       OR (table_name = 'fiscal_documento_tentativas' AND column_name IN (
           'documento_fiscal_id','numero_tentativa','snapshot_json','xml_gerado_path','xml_gerado_sha256',
           'xml_assinado_path','xml_assinado_sha256','resposta_path','resposta_sha256','status'
       ))
       OR (table_name = 'fiscal_pagamento_alocacoes' AND column_name IN (
           'ordem_servico_id','pagamento_id','tipo_documento','documento_id','valor_alocado'
       ))
       OR (table_name = 'fiscal_auditoria' AND column_name IN (
           'usuario_id','acao','entidade_tipo','entidade_id','ambiente','modelo','detalhes','criado_em'
       ))
   )
 ORDER BY table_name, ordinal_position;

SELECT constraint_name, table_name, constraint_type
  FROM information_schema.table_constraints
 WHERE table_schema = DATABASE()
   AND table_name IN (
       'documentos_fiscais','fiscal_series','fiscal_documento_tentativas',
       'fiscal_pagamento_alocacoes','fiscal_inutilizacoes'
   )
 ORDER BY table_name, constraint_type, constraint_name;

SELECT ambiente, modelo, serie, COUNT(*) AS series_count,
       MIN(proximo_numero) AS min_next_number, MAX(proximo_numero) AS max_next_number
  FROM fiscal_series
 GROUP BY ambiente, modelo, serie
HAVING COUNT(*) > 1 OR MIN(proximo_numero) < 1;

SELECT ambiente, modelo, serie, numero, COUNT(*) AS document_count
  FROM documentos_fiscais
 GROUP BY ambiente, modelo, serie, numero
HAVING COUNT(*) > 1;

SELECT id, ambiente, modelo, processamento_status, chave, protocolo
  FROM documentos_fiscais
 WHERE (processamento_status = 'autorizado'
        AND (chave IS NULL OR chave NOT REGEXP '^[0-9]{44}$' OR protocolo IS NULL))
    OR (processamento_status IN ('processando','pendente_reconsulta')
        AND (chave IS NULL OR chave NOT REGEXP '^[0-9]{44}$'));

SELECT codigo_cst, codigo_classificacao, vigencia_inicio, vigencia_fim, versao_fonte,
       JSON_VALID(indicadores_json) AS indicators_json_valid
  FROM fiscal_ibs_cbs_classificacoes
 WHERE status = 'ativo'
 ORDER BY codigo_cst, codigo_classificacao, vigencia_inicio;

SELECT codigo, status
  FROM permissoes
 WHERE codigo IN (
       'nota_fiscal.emitir','nota_fiscal.visualizar','nota_fiscal.cancelar',
       'nota_fiscal.testar_integracao','nota_fiscal.ativar_producao','nota_fiscal.inutilizar'
   )
 ORDER BY codigo;
