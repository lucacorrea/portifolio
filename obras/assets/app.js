const sidebar = document.getElementById('sidebar');
const overlay = document.getElementById('overlay');
const menuToggle = document.getElementById('menuToggle');
const closeSidebar = document.getElementById('closeSidebar');
const toast = document.getElementById('toast');
const pageContent = document.getElementById('pageContent');

const pageInfo = {
  dashboard: ['Painel geral', 'Acompanhe os principais indicadores e atividades da Secretaria.'],
  obras: ['Obras', 'Cadastro, acompanhamento e histórico das obras municipais.'],
  ordens: ['Ordens de serviço', 'Organize solicitações, equipes responsáveis e execução dos serviços.'],
  cronogramas: ['Planejamento', 'Acompanhe etapas, prazos e evolução dos cronogramas.'],
  fiscalizacao: ['Fiscalização', 'Registre vistorias, ocorrências e evidências de execução.'],
  projetos: ['Projetos e documentos', 'Centralize projetos técnicos, memoriais e documentos.'],
  orcamentos: ['Orçamentos e medições', 'Consulte demonstrativos de orçamento e medições.'],
  materiais: ['Materiais e estoque', 'Acompanhe materiais, movimentações e requisições.'],
  patrimonio: ['Prédios públicos', 'Consulte imóveis municipais e seus históricos de manutenção.'],
  protocolos: ['Protocolos', 'Acompanhe processos e movimentações entre setores.'],
  relatorios: ['Relatórios', 'Visualize demonstrativos gerenciais e operacionais.']
};

const sampleRows = {
  obras: [
    ['OBR-2026-001','Reforma de unidade pública','Centro · Coari','Engenharia','78%','Em andamento'],
    ['OBR-2026-002','Manutenção de via urbana','Santa Efigênia','Equipe de obras','45%','Em atenção'],
    ['OBR-2026-003','Revitalização de praça','Área urbana','Engenharia','100%','Concluída'],
    ['OBR-2026-004','Adequação de prédio público','Urucu','Engenharia','20%','Em andamento']
  ],
  ordens: [
    ['OS-00081','Reparo elétrico','Prédio administrativo','Equipe elétrica','Alta','Aberta'],
    ['OS-00082','Manutenção hidráulica','Unidade pública','Equipe manutenção','Média','Em execução'],
    ['OS-00083','Pintura interna','Equipamento municipal','Equipe civil','Baixa','Concluída']
  ],
  fiscalizacao: [
    ['FIS-0018','Reforma de unidade pública','Eng. responsável','08/11/2026','Vistoria de rotina','Registrada'],
    ['FIS-0017','Manutenção de via urbana','Fiscal de contrato','07/11/2026','Verificação de etapa','Pendente'],
    ['FIS-0016','Revitalização de praça','Eng. responsável','05/11/2026','Vistoria final','Concluída']
  ],
  projetos: [
    ['DOC-0041','Projeto arquitetônico','Reforma de unidade pública','PDF · 8,4 MB','08/11/2026'],
    ['DOC-0040','Memorial descritivo','Manutenção de prédio','PDF · 1,2 MB','07/11/2026'],
    ['DOC-0039','Projeto elétrico','Adequação de instalação','PDF · 3,1 MB','04/11/2026']
  ],
  materiais: [
    ['MAT-001','Cimento CP-II','Saco 50 kg','Almoxarifado central','320','Estoque normal'],
    ['MAT-002','Tinta acrílica branca','Lata 18 L','Almoxarifado central','18','Estoque baixo'],
    ['MAT-003','Cabo flexível 2,5 mm²','Metro','Depósito de manutenção','850','Estoque normal']
  ],
  patrimonio: [
    ['PAT-001','Prédio administrativo','Centro','Próprio','Bom estado'],
    ['PAT-002','Unidade de atendimento','Santa Efigênia','Próprio','Manutenção necessária'],
    ['PAT-003','Galpão municipal','Área urbana','Próprio','Bom estado']
  ],
  protocolos: [
    ['2026.000184','Solicitação de manutenção','Secretaria solicitante','08/11/2026','Em análise'],
    ['2026.000183','Encaminhamento de projeto','Engenharia','07/11/2026','Em tramitação'],
    ['2026.000182','Parecer técnico','Planejamento','06/11/2026','Concluído']
  ]
};

const configs = {
  obras: {kpis:[['24','Obras cadastradas'],['12','Em andamento'],['07','Concluídas'],['03','Em atenção']],heads:['CÓDIGO','OBRA / SERVIÇO','LOCALIZAÇÃO','RESPONSÁVEL','PROGRESSO','SITUAÇÃO'],rows:sampleRows.obras,action:'Cadastrar obra'},
  ordens: {kpis:[['38','Ordens no mês'],['08','Abertas'],['09','Em execução'],['21','Concluídas']],heads:['Nº ORDEM','SERVIÇO','LOCAL','EQUIPE','PRIORIDADE','SITUAÇÃO'],rows:sampleRows.ordens,action:'Nova ordem de serviço'},
  fiscalizacao: {kpis:[['18','Vistorias no mês'],['05','Pendentes'],['11','Registradas'],['02','Com apontamentos']],heads:['CÓDIGO','OBRA','FISCAL','DATA','TIPO','SITUAÇÃO'],rows:sampleRows.fiscalizacao,action:'Registrar vistoria'},
  projetos: {kpis:[['126','Documentos'],['32','Projetos'],['18','Memoriais'],['07','Pendentes']],heads:['CÓDIGO','DOCUMENTO','VINCULADO A','ARQUIVO','ATUALIZADO'],rows:sampleRows.projetos,action:'Adicionar documento'},
  materiais: {kpis:[['248','Itens cadastrados'],['18','Estoque baixo'],['36','Requisições'],['12','Movimentações hoje']],heads:['CÓDIGO','MATERIAL','UNIDADE','LOCAL','SALDO','SITUAÇÃO'],rows:sampleRows.materiais,action:'Cadastrar material'},
  patrimonio: {kpis:[['42','Imóveis cadastrados'],['31','Em uso'],['06','Com manutenção'],['05','Sem atualização']],heads:['CÓDIGO','IMÓVEL','LOCALIZAÇÃO','VÍNCULO','CONDIÇÃO'],rows:sampleRows.patrimonio,action:'Cadastrar imóvel'},
  protocolos: {kpis:[['184','Protocolos no ano'],['12','Em análise'],['08','Em tramitação'],['164','Concluídos']],heads:['PROTOCOLO','ASSUNTO','SETOR','DATA','SITUAÇÃO'],rows:sampleRows.protocolos,action:'Novo protocolo'}
};

function showToast(message) {
  toast.textContent = message;
  toast.classList.add('show');
  window.clearTimeout(showToast.timer);
  showToast.timer = window.setTimeout(() => toast.classList.remove('show'), 3000);
}
function closeMenu() {
  sidebar.classList.remove('open');
  overlay.classList.remove('show');
}
menuToggle?.addEventListener('click', () => { sidebar.classList.add('open'); overlay.classList.add('show'); });
closeSidebar?.addEventListener('click', closeMenu);
overlay?.addEventListener('click', closeMenu);

function statusClass(value) {
  const v = String(value).toLowerCase();
  if (v.includes('conclu') || v.includes('normal') || v.includes('registrad')) return 'status-green';
  if (v.includes('atenção') || v.includes('baixo') || v.includes('pendente') || v.includes('aberta')) return 'status-amber';
  if (v.includes('andamento') || v.includes('execução') || v.includes('tramitação')) return 'status-blue';
  return 'status-gray';
}
function tableMarkup(config) {
  return `<div class="table-scroll"><table class="data-table"><thead><tr>${config.heads.map(h=>`<th>${h}</th>`).join('')}<th>AÇÕES</th></tr></thead><tbody>${config.rows.map(row=>`<tr>${row.map((cell,i)=>`<td>${i===0?`<strong>${cell}</strong>`:i===config.heads.length-1?`<span class="status ${statusClass(cell)}">${cell}</span>`:cell}</td>`).join('')}<td><button class="row-actions" data-demo="Detalhes do registro">Ver detalhes ↗</button></td></tr>`).join('')}</tbody></table></div>`;
}
function moduleIntro(title, description, symbol, action, actionLabel) {
  return `<div class="module-intro"><div><h2>${title}</h2><p>${description}</p></div><div class="module-actions"><button class="btn btn-light" data-demo="Exportar ${title}">↓ Exportar</button><button class="btn btn-primary" data-demo="${action}">＋ ${actionLabel}</button></div><div class="module-symbol">${symbol}</div></div>`;
}
function kpiMarkup(items) {
  return `<div class="page-kpis">${items.map(k=>`<div class="mini-kpi"><span>${k[1]}</span><strong>${k[0]}</strong><small>${k[2]||'Indicador demonstrativo'}</small></div>`).join('')}</div>`;
}
function listPanel(key, heading='Registros cadastrados') {
  const c=configs[key];
  return `<div class="panel"><div class="panel-heading"><div><h3>${heading}</h3><p>Consulte, filtre e acompanhe os registros do módulo</p></div><button class="btn btn-primary" data-demo="${c.action}">＋ ${c.action}</button></div><div class="page-toolbar"><div class="search-box"><input type="search" placeholder="Buscar por código, nome ou descrição..." aria-label="Buscar registros"></div><select class="filter-select"><option>Todas as situações</option><option>Em andamento</option><option>Pendente</option><option>Concluída</option></select><button class="btn btn-light" data-demo="Filtros avançados">⚙ Filtros</button></div>${tableMarkup(c)}<div class="table-note">Dados fictícios de demonstração. A gravação e consulta reais serão conectadas ao PHP/MySQL.</div></div>`;
}
function genericListPage(key) {
  const c=configs[key];
  const details={
    obras:['Carteira de obras municipais','Visão consolidada de contratos, frentes de trabalho, localização e execução.','⌂','Cadastrar obra'],
    ordens:['Central de solicitações e serviços','Abertura, distribuição, prioridade, equipes e acompanhamento das ordens.','☷','Nova ordem'],
    fiscalizacao:['Controle de vistorias e ocorrências','Registre visitas técnicas, conformidades, pendências e evidências de campo.','✓','Registrar vistoria'],
    projetos:['Biblioteca técnica da Secretaria','Organize projetos, plantas, memoriais, ART/RRT, licenças e arquivos relacionados.','▧','Adicionar documento'],
    materiais:['Almoxarifado e movimentação de materiais','Controle de catálogo, saldo disponível, entradas, saídas e requisições por setor.','▣','Cadastrar material'],
    patrimonio:['Cadastro de equipamentos públicos','Inventário de imóveis, unidades, características, conservação e histórico de manutenção.','⌂','Cadastrar imóvel'],
    protocolos:['Gestão de processos e solicitações','Acompanhe recebimento, distribuição, despacho, tramitação e encerramento.','▤','Novo protocolo']
  }[key];
  let extra='';
  if(key==='obras') extra=`<div class="module-grid"><div class="module-panel"><h3>Distribuição por situação</h3><p>Carteira atual de obras</p><div class="module-list">${[['Em andamento','12','50%'],['Concluídas','07','29%'],['Planejadas','03','13%'],['Paralisadas','02','8%']].map(x=>`<div class="module-list-item"><strong>${x[0]}</strong><b>${x[1]} <small>${x[2]}</small></b></div>`).join('')}</div></div><div class="module-panel"><h3>Obras que exigem atenção</h3><p>Prazos e execução física</p><div class="module-list">${[['Manutenção de via urbana','Prazo contratual próximo','45%'],['Adequação de prédio público','Aguardando etapa técnica','20%'],['Reforma de unidade pública','Acompanhamento semanal','78%']].map(x=>`<div class="module-list-item"><div><strong>${x[0]}</strong><small>${x[1]}</small></div><b>${x[2]}</b></div>`).join('')}</div></div></div>`;
  if(key==='ordens') extra=`<div class="module-grid"><div class="module-panel"><h3>Ordens por prioridade</h3><p>Distribuição das solicitações abertas</p><div class="module-list">${[['Alta','05'],['Média','09'],['Baixa','04']].map(x=>`<div class="module-list-item"><strong>${x[0]}</strong><b>${x[1]}</b></div>`).join('')}</div></div><div class="module-panel"><h3>Fluxo de atendimento</h3><p>Etapas previstas para cada ordem</p><div class="module-list">${[['Aberta','Recebida pelo setor'],['Em triagem','Avaliação e priorização'],['Em execução','Equipe designada'],['Concluída','Validação e encerramento']].map(x=>`<div class="module-list-item"><div><strong>${x[0]}</strong><small>${x[1]}</small></div><span class="status status-blue">Etapa</span></div>`).join('')}</div></div></div>`;
  if(key==='fiscalizacao') extra=`<div class="module-grid"><div class="module-panel"><h3>Agenda de fiscalização</h3><p>Programação demonstrativa de campo</p><div class="timeline">${[['09 NOV · 08:00','Reforma de unidade pública','Vistoria de etapa'],['10 NOV · 09:30','Manutenção de via urbana','Verificação de execução'],['11 NOV · 14:00','Revitalização de praça','Vistoria de recebimento']].map(x=>`<div class="timeline-item"><strong>${x[0]} · ${x[1]}</strong><small>${x[2]}</small></div>`).join('')}</div></div><div class="module-panel"><h3>Checklist de vistoria</h3><p>Itens que podem compor um registro</p><div class="module-list">${['Serviços executados conforme projeto','Materiais e especificações conferidos','Registro fotográfico anexado','Pendências e recomendações registradas'].map(x=>`<div class="module-list-item"><strong>${x}</strong><span class="status status-gray">Pendente</span></div>`).join('')}</div></div></div>`;
  if(key==='projetos') extra=`<div class="module-grid"><div class="module-panel"><h3>Pastas técnicas</h3><p>Classificação documental</p><div class="module-list">${[['Projetos arquitetônicos','32 arquivos'],['Projetos complementares','24 arquivos'],['Memoriais e especificações','18 arquivos'],['ART / RRT e licenças','15 arquivos'],['Relatórios e laudos','37 arquivos']].map(x=>`<div class="module-list-item"><strong>▰ ${x[0]}</strong><b>${x[1]}</b></div>`).join('')}</div></div><div class="module-panel"><h3>Documentos recentes</h3><p>Últimos arquivos adicionados</p><div class="module-list">${[['PDF','Projeto arquitetônico · Unidade pública','08/11/2026'],['PDF','Memorial descritivo · Prédio municipal','07/11/2026'],['DWG','Planta de implantação · Praça','04/11/2026']].map(x=>`<div class="doc-card"><div class="doc-type">${x[0]}</div><div><strong>${x[1]}</strong><small>Atualizado em ${x[2]}</small></div><button class="row-actions" data-demo="Visualizar documento">Abrir</button></div>`).join('')}</div></div></div>`;
  if(key==='materiais') extra=`<div class="module-grid"><div class="module-panel"><h3>Posição do estoque</h3><p>Exemplo de níveis de disponibilidade</p>${[['Cimento CP-II','320 sacos',78],['Tinta acrílica branca','18 latas',18],['Cabo flexível 2,5 mm²','850 metros',65],['Lâmpada LED 40 W','12 unidades',12]].map(x=>`<div class="module-list-item"><div style="flex:1"><strong>${x[0]}</strong><small>${x[1]}</small><div class="stock-indicator ${x[2]<25?'low':''}"><span style="width:${x[2]}%"></span></div></div></div>`).join('')}</div><div class="module-panel"><h3>Movimentações recentes</h3><p>Entradas e saídas do almoxarifado</p><div class="timeline">${[['08/11 · Saída','Cimento CP-II · 40 sacos','OS-00081 · Manutenção'],['08/11 · Entrada','Cabo flexível · 200 m','Nota de fornecimento'],['07/11 · Requisição','Tinta acrílica · 4 latas','Equipe de manutenção']].map(x=>`<div class="timeline-item"><strong>${x[0]} — ${x[1]}</strong><small>${x[2]}</small></div>`).join('')}</div></div></div>`;
  if(key==='patrimonio') extra=`<div class="module-grid"><div class="module-panel"><h3>Condição dos imóveis</h3><p>Classificação de conservação</p><div class="module-list">${[['Bom estado','24'],['Manutenção preventiva','12'],['Manutenção corretiva','06']].map(x=>`<div class="module-list-item"><strong>${x[0]}</strong><b>${x[1]}</b></div>`).join('')}</div></div><div class="module-panel"><h3>Próximas manutenções</h3><p>Programação ilustrativa</p><div class="timeline">${[['12 NOV','Prédio administrativo','Inspeção elétrica'],['15 NOV','Unidade de atendimento','Revisão hidráulica'],['18 NOV','Galpão municipal','Cobertura e calhas']].map(x=>`<div class="timeline-item"><strong>${x[0]} · ${x[1]}</strong><small>${x[2]}</small></div>`).join('')}</div></div></div>`;
  if(key==='protocolos') extra=`<div class="module-grid"><div class="module-panel"><h3>Tramitação por setor</h3><p>Distribuição ilustrativa dos processos</p><div class="module-list">${[['Protocolo geral','18'],['Engenharia','42'],['Planejamento','31'],['Fiscalização','27'],['Gabinete / gestão','14']].map(x=>`<div class="module-list-item"><strong>${x[0]}</strong><b>${x[1]}</b></div>`).join('')}</div></div><div class="module-panel"><h3>Últimas movimentações</h3><p>Histórico resumido</p><div class="timeline">${[['08/11 · 10:42','2026.000184','Encaminhado para Engenharia'],['08/11 · 09:15','2026.000183','Análise documental iniciada'],['07/11 · 16:20','2026.000182','Parecer técnico concluído']].map(x=>`<div class="timeline-item"><strong>${x[0]} · ${x[1]}</strong><small>${x[2]}</small></div>`).join('')}</div></div></div>`;
  return `${moduleIntro(...details)}${kpiMarkup(c.kpis.map(k=>[k[0],k[1],'Resumo do módulo']))}${extra}${listPanel(key)}`;
}
function planningPage() {
 return `${moduleIntro('Planejamento físico e operacional','Organize planos de trabalho, etapas, marcos, responsáveis e prazos de execução.','▤','Novo cronograma','Novo cronograma')}${kpiMarkup([['08','Planos ativos'],['26','Etapas em execução'],['03','Etapas atrasadas'],['68%','Avanço médio']])}<div class="module-grid"><div class="module-panel"><h3>Cronogramas por obra</h3><p>Avanço físico e situação das frentes</p>${[['Reforma de unidade pública',78,'Em andamento'],['Manutenção de via urbana',45,'Em atenção'],['Revitalização de praça',100,'Concluída'],['Adequação de prédio público',20,'Em andamento']].map(x=>`<div class="module-list-item" style="display:block"><div style="display:flex;justify-content:space-between"><strong>${x[0]}</strong><b>${x[1]}%</b></div><div class="bar-track large" style="margin-top:10px"><span style="width:${x[1]}%"></span></div><small><span class="status ${statusClass(x[2])}">${x[2]}</span></small></div>`).join('')}</div><div class="module-panel"><h3>Marcos e entregas</h3><p>Próximos eventos planejados</p><div class="timeline">${[['12 NOV 2026','Conclusão da etapa elétrica','Reforma de unidade pública'],['16 NOV 2026','Entrega de materiais','Manutenção de via urbana'],['22 NOV 2026','Vistoria de recebimento','Revitalização de praça'],['30 NOV 2026','Fechamento da medição','Adequação de prédio público']].map(x=>`<div class="timeline-item"><strong>${x[0]} · ${x[1]}</strong><small>${x[2]}</small></div>`).join('')}</div></div></div><div class="form-panel"><h3 class="page-subtitle">Filtros do planejamento</h3><div class="form-grid"><div class="field"><label>Obra / projeto</label><select><option>Todas as obras</option><option>Reforma de unidade pública</option></select></div><div class="field"><label>Período</label><select><option>Novembro de 2026</option><option>Dezembro de 2026</option></select></div><div class="field"><label>Responsável</label><select><option>Todos os responsáveis</option><option>Engenharia</option></select></div><div class="field"><label>Situação</label><select><option>Todas</option><option>Em atraso</option><option>Concluída</option></select></div></div><div class="visual-note">Os cronogramas, percentuais e datas exibidos são exemplos visuais e deverão ser alimentados por registros reais.</div></div>`;
}
function budgetPage() {
 return `${moduleIntro('Gestão de custos, contratos e medições','Acompanhe orçamento previsto, valores contratados, medições executadas e saldos.','＄','Nova medição','Nova medição')}${kpiMarkup([['R$ 4,8 mi','Orçamento previsto'],['R$ 2,9 mi','Valor medido'],['R$ 1,9 mi','Saldo contratual'],['04','Medições pendentes']])}<div class="module-grid">${[['Orçamentos','Composições, quantitativos, insumos, BDI e planilhas sintéticas e analíticas.','▤'],['Medições','Serviços executados, períodos, memória de cálculo e aprovação.','▥'],['Composições de custos','Bases de serviços, unidades, insumos e referências de preços.','▧'],['Contratos e aditivos','Dados contratuais, vigência, reajustes, aditivos e saldo.','▣']].map(x=>`<div class="info-card"><div class="card-icon">${x[2]}</div><h3>${x[0]}</h3><p>${x[1]}</p><button class="row-actions" data-demo="Acessar ${x[0]}">Acessar módulo →</button></div>`).join('')}</div><div class="module-grid"><div class="module-panel"><h3>Execução financeira</h3><p>Comparativo demonstrativo do orçamento</p><div class="progress-summary"><strong>60,4%</strong><span>do orçamento medido</span></div><div class="bar-track large"><span style="width:60.4%"></span></div><div class="module-list"><div class="module-list-item"><strong>Previsto</strong><b>R$ 4.800.000,00</b></div><div class="module-list-item"><strong>Medido</strong><b>R$ 2.900.000,00</b></div><div class="module-list-item"><strong>Saldo</strong><b>R$ 1.900.000,00</b></div></div></div><div class="module-panel"><h3>Etapas de uma medição</h3><p>Fluxo sugerido para análise e aprovação</p><div class="timeline">${[['01 · Lançamento','Registro de itens e quantitativos'],['02 · Conferência técnica','Validação pelo fiscal responsável'],['03 · Aprovação','Autorização da medição'],['04 · Encaminhamento','Integração com setor financeiro']].map(x=>`<div class="timeline-item"><strong>${x[0]}</strong><small>${x[1]}</small></div>`).join('')}</div></div></div><div class="panel"><div class="panel-heading"><div><h3>Últimas medições</h3><p>Movimentações financeiras demonstrativas</p></div><button class="btn btn-light" data-demo="Ver todas as medições">Ver todas</button></div><div class="table-scroll"><table class="data-table"><thead><tr><th>MEDIÇÃO</th><th>OBRA</th><th>PERÍODO</th><th>VALOR</th><th>SITUAÇÃO</th><th>AÇÕES</th></tr></thead><tbody><tr><td><strong>MED-0008</strong></td><td>Reforma de unidade pública</td><td>Outubro/2026</td><td>R$ 185.400,00</td><td><span class="status status-amber">Em análise</span></td><td><button class="row-actions" data-demo="Detalhes da medição">Detalhes ↗</button></td></tr><tr><td><strong>MED-0007</strong></td><td>Manutenção de via urbana</td><td>Outubro/2026</td><td>R$ 92.800,00</td><td><span class="status status-green">Aprovada</span></td><td><button class="row-actions" data-demo="Detalhes da medição">Detalhes ↗</button></td></tr></tbody></table></div></div>`;
}
function reportsPage() {
 const cards=[['Obras e execução','Situação, evolução física, prazos, contratos e localização.','▦'],['Ordens de serviço','Solicitações, equipes, prioridades, atendimento e conclusão.','☷'],['Medições e orçamento','Valores previstos, medidos, saldos e execução financeira.','＄'],['Materiais e estoque','Entradas, saídas, consumo, requisições e estoque mínimo.','▣'],['Fiscalização','Vistorias, ocorrências, não conformidades e evidências.','✓'],['Patrimônio público','Imóveis, conservação, intervenções e histórico.','⌂']];
 return `${moduleIntro('Central de relatórios gerenciais','Gere visões consolidadas para acompanhamento operacional, técnico e administrativo.','▥','Preparar relatório','Preparar relatório')}${kpiMarkup([['12','Modelos disponíveis'],['06','Categorias'],['04','Relatórios recentes'],['PDF / XLSX','Formatos de saída']])}<div class="content-card-grid">${cards.map(x=>`<div class="info-card"><div class="card-icon">${x[2]}</div><h3>${x[0]}</h3><p>${x[1]}</p><button class="row-actions" data-demo="Preparar relatório: ${x[0]}">Configurar relatório →</button></div>`).join('')}</div><div class="form-panel" style="margin-top:16px"><h3 class="page-subtitle">Parâmetros do relatório</h3><p class="page-help">Defina os critérios para montar uma prévia demonstrativa.</p><div class="form-grid"><div class="field"><label>Tipo de relatório</label><select><option>Obras e execução</option><option>Ordens de serviço</option><option>Fiscalização</option><option>Materiais e estoque</option><option>Patrimônio público</option></select></div><div class="field"><label>Data inicial</label><input type="date" value="2026-10-01"></div><div class="field"><label>Data final</label><input type="date" value="2026-11-09"></div><div class="field"><label>Situação</label><select><option>Todas</option><option>Em andamento</option><option>Concluídas</option><option>Pendentes</option></select></div><div class="field"><label>Formato de saída</label><select><option>PDF</option><option>Excel (XLSX)</option></select></div><div class="field"><label>Agrupar por</label><select><option>Setor responsável</option><option>Obra</option><option>Situação</option><option>Período</option></select></div></div><div style="margin-top:17px"><button class="btn btn-primary" data-demo="Pré-visualização do relatório">Pré-visualizar relatório</button></div></div><div class="module-panel" style="margin-top:16px"><h3>Relatórios recentes</h3><p>Histórico demonstrativo de relatórios preparados</p><div class="module-list">${[['REL-0024','Resumo mensal de obras','08/11/2026'],['REL-0023','Posição de estoque','06/11/2026'],['REL-0022','Ordens de serviço por setor','04/11/2026']].map(x=>`<div class="module-list-item"><div><strong>${x[0]} · ${x[1]}</strong><small>Gerado em ${x[2]}</small></div><button class="row-actions" data-demo="Abrir ${x[0]}">Visualizar ↗</button></div>`).join('')}</div></div>`;
}
function formPage(key) { return genericListPage(key); }
function pageTemplate(key) {
  if (key === 'dashboard') return document.getElementById('pageContent').dataset.dashboardHtml || '';
  if (configs[key]) return genericListPage(key);
  if (key === 'cronogramas') return planningPage();
  if (key === 'orcamentos') return budgetPage();
  if (key === 'relatorios') return reportsPage();
  return formPage(key);
}
const dashboardHTML = pageContent.innerHTML;
pageContent.dataset.dashboardHtml = dashboardHTML;

function pageHeading(info) {
  return `<div class="page-heading"><div><div class="eyebrow">GESTÃO MUNICIPAL</div><h1 id="pageTitle">${info[0]}</h1><p id="pageDescription">${info[1]}</p></div><div class="heading-actions"><button class="btn btn-light" data-demo="Exportar resumo">↓ Exportar resumo</button><button class="btn btn-primary" data-demo="${info[0] === 'Painel geral' ? 'Nova obra' : 'Novo registro'}">＋ ${info[0] === 'Painel geral' ? 'Nova obra' : 'Novo registro'}</button></div></div>`;
}
function openPage(key) {
  const info = pageInfo[key] || pageInfo.dashboard;
  document.getElementById('breadcrumbCurrent').textContent = info[0];
  document.querySelectorAll('.nav-link').forEach(link => link.classList.toggle('active', link.dataset.page === key));
  if (key === 'dashboard') pageContent.innerHTML = dashboardHTML;
  else pageContent.innerHTML = pageHeading(info) + pageTemplate(key);
  closeMenu();
}
document.addEventListener('click', event => {
  const pageLink = event.target.closest('[data-page]');
  if (pageLink) { openPage(pageLink.dataset.page); return; }
});
pageContent.addEventListener('click', event => {
  const button = event.target.closest('[data-demo]');
  const target = event.target.closest('#newAction, #exportBtn');
  if (target) { showToast(target.id === 'newAction' ? 'Cadastro demonstrativo. A gravação será implementada na etapa PHP/MySQL.' : 'Exportação PDF/Excel será conectada aos dados reais na próxima etapa.'); return; }
  if (button) showToast(`${button.dataset.demo}: demonstração visual. A função será ligada ao PHP na próxima etapa.`);
});
document.getElementById('newAction')?.addEventListener('click', () => showToast('Cadastro demonstrativo. A gravação será implementada na etapa PHP/MySQL.'));
document.getElementById('exportBtn')?.addEventListener('click', () => showToast('Exportação PDF/Excel será conectada aos dados reais na próxima etapa.'));
const date = new Intl.DateTimeFormat('pt-BR', {weekday:'short',day:'2-digit',month:'long',year:'numeric'}).format(new Date());
document.getElementById('currentDate').textContent = `Coari, ${date}`;

// Abre o módulo indicado pelo HTML individual, mantendo a navegação integrada.
const initialPage = document.body.dataset.initialPage;
if (initialPage && initialPage !== 'dashboard') {
  openPage(initialPage);
}
