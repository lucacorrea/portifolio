<?php
declare(strict_types=1);
/** Configuração declarativa dos formulários, tabelas e permissões de cada módulo. */
$statuses = static fn(array $values): array => $values;
return [
 'obras' => [
  'table'=>'works','name'=>'Obras','singular'=>'Obra','icon'=>'hard-hat','prefix'=>'OBR-','description'=>'Carteira de intervenções e acompanhamento físico-financeiro.','date_field'=>'start_date',
  'columns'=>['code'=>'Código','title'=>'Obra / intervenção','local'=>'Local','responsible'=>'Responsável','progress'=>'Execução','status'=>'Situação'],
  'fields'=>[
   'title'=>['label'=>'Nome da obra','type'=>'text','required'=>true],
   'type'=>['label'=>'Tipo de intervenção','type'=>'select','options'=>['Reforma','Pavimentação','Drenagem','Urbanismo','Educação','Construção','Outros'],'required'=>true],
   'local'=>['label'=>'Localidade / endereço','type'=>'text','required'=>true],
   'responsible'=>['label'=>'Responsável','type'=>'text','required'=>true],
   'status'=>['label'=>'Situação','type'=>'select','options'=>['Planejada','Em andamento','Em atenção','Concluída','Paralisada'],'required'=>true],
   'progress'=>['label'=>'Execução física (%)','type'=>'number','min'=>0,'max'=>100,'step'=>'0.01','required'=>true],
   'budget'=>['label'=>'Orçamento estimado (R$)','type'=>'number','min'=>0,'step'=>'0.01'],
   'contract_ref'=>['label'=>'Contrato / referência','type'=>'text'],
   'start_date'=>['label'=>'Data de início','type'=>'date'],
   'end_date'=>['label'=>'Previsão de conclusão','type'=>'date'],
   'latitude'=>['label'=>'Latitude (decimal)','type'=>'number','min'=>-90,'max'=>90,'step'=>'any'],
   'longitude'=>['label'=>'Longitude (decimal)','type'=>'number','min'=>-180,'max'=>180,'step'=>'any'],
   'description'=>['label'=>'Descrição / escopo','type'=>'textarea']
  ]
 ],
 'ordens'=>[
  'table'=>'service_orders','name'=>'Ordens de serviço','singular'=>'Ordem de serviço','icon'=>'clipboard-list','prefix'=>'OS-','description'=>'Abertura, prioridades e acompanhamento dos atendimentos.','date_field'=>'due_date',
  'columns'=>['code'=>'Ordem','title'=>'Serviço','local'=>'Local','responsible'=>'Equipe','priority'=>'Prioridade','status'=>'Situação'],
  'fields'=>[
   'title'=>['label'=>'Serviço solicitado','type'=>'text','required'=>true],
   'work_id'=>['label'=>'Obra relacionada','type'=>'work'],
   'local'=>['label'=>'Local de atendimento','type'=>'text','required'=>true],
   'requester'=>['label'=>'Setor solicitante','type'=>'text','required'=>true],
   'responsible'=>['label'=>'Equipe responsável','type'=>'text','required'=>true],
   'priority'=>['label'=>'Prioridade','type'=>'select','options'=>['Baixa','Média','Alta','Urgente'],'required'=>true],
   'status'=>['label'=>'Situação','type'=>'select','options'=>['Aberta','Em execução','Concluída','Cancelada'],'required'=>true],
   'due_date'=>['label'=>'Prazo previsto','type'=>'date'],
   'description'=>['label'=>'Descrição do serviço','type'=>'textarea']
  ]
 ],
 'fiscalizacao'=>[
  'table'=>'inspections','name'=>'Fiscalização','singular'=>'Vistoria','icon'=>'shield-check','prefix'=>'FIS-','description'=>'Registro e histórico de vistorias técnicas.','date_field'=>'inspection_date',
  'columns'=>['code'=>'Registro','title'=>'Vistoria','work_id'=>'Obra','responsible'=>'Fiscal','inspection_date'=>'Data','status'=>'Situação'],
  'fields'=>[
   'title'=>['label'=>'Título da vistoria','type'=>'text','required'=>true],
   'work_id'=>['label'=>'Obra vinculada','type'=>'work','required'=>true],
   'responsible'=>['label'=>'Fiscal responsável','type'=>'text','required'=>true],
   'type'=>['label'=>'Tipo de vistoria','type'=>'select','options'=>['Rotina','Técnica','Recebimento','Extraordinária'],'required'=>true],
   'inspection_date'=>['label'=>'Data da vistoria','type'=>'date','required'=>true],
   'status'=>['label'=>'Situação','type'=>'select','options'=>['Pendente','Registrada','Concluída','Com apontamentos'],'required'=>true],
   'description'=>['label'=>'Constatações e recomendações','type'=>'textarea']
  ]
 ],
 'projetos'=>[
  'table'=>'documents','name'=>'Projetos e documentos','singular'=>'Documento','icon'=>'folder-open','prefix'=>'DOC-','description'=>'Arquivos técnicos com download protegido e versões.','date_field'=>'document_date',
  'columns'=>['code'=>'Código','title'=>'Documento','work_id'=>'Obra','type'=>'Tipo','document_date'=>'Data','status'=>'Situação'],
  'fields'=>[
   'title'=>['label'=>'Nome do documento','type'=>'text','required'=>true],
   'work_id'=>['label'=>'Obra vinculada','type'=>'work'],
   'type'=>['label'=>'Tipo documental','type'=>'select','options'=>['Projeto','Memorial','Responsabilidade','Licença','Relatório','Outro'],'required'=>true],
   'version'=>['label'=>'Versão','type'=>'text'],
   'document_date'=>['label'=>'Data de registro','type'=>'date'],
   'status'=>['label'=>'Situação','type'=>'select','options'=>['Em elaboração','Em revisão','Publicado','Arquivado'],'required'=>true],
   'description'=>['label'=>'Resumo / observações','type'=>'textarea'],
   'upload'=>['label'=>'Arquivo (PDF, PNG, JPG, DOCX ou XLSX, até 10 MB)','type'=>'file','virtual'=>true]
  ]
 ],
 'orcamentos'=>[
  'table'=>'measurements','name'=>'Orçamentos e medições','singular'=>'Medição','icon'=>'wallet','prefix'=>'MED-','description'=>'Medições financeiras por obra e período.','date_field'=>null,
  'columns'=>['code'=>'Medição','title'=>'Descrição','work_id'=>'Obra','period'=>'Período','value'=>'Valor (R$)','status'=>'Situação'],
  'fields'=>[
   'title'=>['label'=>'Descrição da medição','type'=>'text','required'=>true],
   'work_id'=>['label'=>'Obra vinculada','type'=>'work','required'=>true],
   'period'=>['label'=>'Período (AAAA-MM)','type'=>'month','required'=>true],
   'value'=>['label'=>'Valor (R$)','type'=>'number','min'=>0,'step'=>'0.01','required'=>true],
   'responsible'=>['label'=>'Responsável técnico','type'=>'text'],
   'status'=>['label'=>'Situação','type'=>'select','options'=>['Em análise','Aprovada','Pendente','Reprovada'],'required'=>true],
   'description'=>['label'=>'Memória de cálculo','type'=>'textarea']
  ]
 ],
 'materiais'=>[
  'table'=>'materials','name'=>'Materiais e estoque','singular'=>'Material','icon'=>'package','prefix'=>'MAT-','description'=>'Controle de saldos e movimentações rastreáveis.','date_field'=>null,
  'columns'=>['code'=>'Código','title'=>'Material','unit'=>'Unidade','local'=>'Armazenagem','quantity'=>'Saldo','minimum'=>'Mínimo'],
  'fields'=>[
   'title'=>['label'=>'Material','type'=>'text','required'=>true],
   'unit'=>['label'=>'Unidade','type'=>'text','required'=>true],
   'local'=>['label'=>'Local de armazenamento','type'=>'text','required'=>true],
   'quantity'=>['label'=>'Saldo inicial (somente no cadastro)','type'=>'number','min'=>0,'step'=>'0.01','create_only'=>true],
   'minimum'=>['label'=>'Estoque mínimo','type'=>'number','min'=>0,'step'=>'0.01','required'=>true],
   'description'=>['label'=>'Observações','type'=>'textarea']
  ]
 ],
 'patrimonio'=>[
  'table'=>'buildings','name'=>'Prédios públicos','singular'=>'Imóvel','icon'=>'building-2','prefix'=>'PAT-','description'=>'Inventário e conservação do patrimônio municipal.','date_field'=>null,
  'columns'=>['code'=>'Código','title'=>'Imóvel','local'=>'Localização','type'=>'Tipo','condition_status'=>'Conservação','status'=>'Uso'],
  'fields'=>[
   'title'=>['label'=>'Nome do prédio','type'=>'text','required'=>true],
   'local'=>['label'=>'Localização','type'=>'text','required'=>true],
   'type'=>['label'=>'Tipo de imóvel','type'=>'select','options'=>['Administrativo','Educação','Saúde','Operacional','Atendimento','Outro'],'required'=>true],
   'area'=>['label'=>'Área aproximada (m²)','type'=>'number','min'=>0,'step'=>'0.01'],
   'responsible'=>['label'=>'Setor responsável','type'=>'text'],
   'condition_status'=>['label'=>'Conservação','type'=>'select','options'=>['Bom estado','Regular','Manutenção necessária','Crítico'],'required'=>true],
   'status'=>['label'=>'Utilização','type'=>'select','options'=>['Em uso','Desocupado','Em reforma'],'required'=>true],
   'latitude'=>['label'=>'Latitude','type'=>'number','min'=>-90,'max'=>90,'step'=>'any'],
   'longitude'=>['label'=>'Longitude','type'=>'number','min'=>-180,'max'=>180,'step'=>'any'],
   'description'=>['label'=>'Observações','type'=>'textarea']
  ]
 ],
 'protocolos'=>[
  'table'=>'protocols','name'=>'Protocolos','singular'=>'Protocolo','icon'=>'files','prefix'=>'PRT-','description'=>'Acompanhamento e histórico de encaminhamentos.','date_field'=>'opened_on',
  'columns'=>['code'=>'Número','title'=>'Assunto','origin_department'=>'Origem','destination_department'=>'Destino','opened_on'=>'Abertura','status'=>'Situação'],
  'fields'=>[
   'title'=>['label'=>'Assunto','type'=>'text','required'=>true],
   'origin_department'=>['label'=>'Setor de origem','type'=>'text','required'=>true],
   'destination_department'=>['label'=>'Setor destinatário','type'=>'text','required'=>true],
   'opened_on'=>['label'=>'Data de abertura','type'=>'date','required'=>true],
   'priority'=>['label'=>'Prioridade','type'=>'select','options'=>['Normal','Alta','Urgente'],'required'=>true],
   'status'=>['label'=>'Situação','type'=>'select','options'=>['Em análise','Em tramitação','Aguardando','Concluído','Arquivado'],'required'=>true],
   'description'=>['label'=>'Descrição / despacho inicial','type'=>'textarea']
  ]
 ],
 'marcos'=>[
  'table'=>'milestones','name'=>'Marcos do planejamento','singular'=>'Marco','icon'=>'calendar-check','prefix'=>'MRC-','description'=>'Entregas e prazos associados às obras.','date_field'=>'due_date',
  'columns'=>['code'=>'Código','title'=>'Marco','work_id'=>'Obra','due_date'=>'Prazo','status'=>'Situação'],
  'fields'=>[
   'title'=>['label'=>'Título do marco','type'=>'text','required'=>true],
   'work_id'=>['label'=>'Obra vinculada','type'=>'work','required'=>true],
   'due_date'=>['label'=>'Data prevista','type'=>'date','required'=>true],
   'status'=>['label'=>'Situação','type'=>'select','options'=>['Programado','Em execução','Aguardando','Concluído','Atrasado'],'required'=>true],
   'description'=>['label'=>'Descrição','type'=>'textarea']
  ]
 ]
];
