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
function genericListPage(key) {
  const c = configs[key];
  return `<div class="page-kpis">${c.kpis.map(k=>`<div class="mini-kpi"><span>${k[1]}</span><strong>${k[0]}</strong><small>Indicador demonstrativo</small></div>`).join('')}</div>
    <div class="panel"><div class="panel-heading"><div><h3>Registros cadastrados</h3><p>Consulte e acompanhe as informações do setor</p></div><button class="btn btn-primary" data-demo="${c.action}">＋ ${c.action}</button></div>
    <div class="page-toolbar"><div class="search-box"><input type="search" placeholder="Buscar por código, nome ou descrição..." aria-label="Buscar registros"></div><select class="filter-select" aria-label="Filtrar situação"><option>Todas as situações</option><option>Em andamento</option><option>Pendente</option><option>Concluída</option></select><button class="btn btn-light" data-demo="Filtros avançados">⚙ Filtros</button></div>
    ${tableMarkup(c)}<div class="table-note">Dados fictícios de demonstração. Os cadastros reais serão conectados ao PHP e MySQL na próxima etapa.</div></div>`;
}
function planningPage() {
  return `<div class="page-kpis"><div class="mini-kpi"><span>Planos ativos</span><strong>08</strong><small>Planejamentos demonstrativos</small></div><div class="mini-kpi"><span>Etapas em execução</span><strong>26</strong><small>Em diferentes obras</small></div><div class="mini-kpi"><span>Etapas atrasadas</span><strong>03</strong><small>Precisam de acompanhamento</small></div><div class="mini-kpi"><span>Avanço médio</span><strong>68%</strong><small>Percentual ilustrativo</small></div></div>
  <div class="panel"><div class="panel-heading"><div><h3>Cronogramas de execução</h3><p>Planejamento físico das principais frentes</p></div><button class="btn btn-primary" data-demo="Novo cronograma">＋ Novo cronograma</button></div>
  <div class="page-toolbar"><select class="filter-select"><option>Todas as obras</option><option>Reforma de unidade pública</option></select><select class="filter-select"><option>Todos os períodos</option><option>Novembro de 2026</option></select></div>
  ${[['Reforma de unidade pública','78%',78,'Em andamento'],['Manutenção de via urbana','45%',45,'Em atenção'],['Revitalização de praça','100%',100,'Concluída']].map(x=>`<div class="progress-row" style="padding:17px 0;border-bottom:1px solid #edf1f4"><div style="display:flex;justify-content:space-between;gap:10px;margin-bottom:9px"><div><strong style="font-size:12px;color:#385267">${x[0]}</strong><div style="font-size:10px;color:#94a1ac;margin-top:4px">Execução física · Cronograma geral</div></div><b style="font-size:12px">${x[1]}</b></div><div class="bar-track large"><span style="width:${x[2]}%"></span></div><div style="margin-top:8px"><span class="status ${statusClass(x[3])}">${x[3]}</span></div></div>`).join('')}</div>`;
}
function budgetPage() {
  return `<div class="page-kpis"><div class="mini-kpi"><span>Orçamento previsto</span><strong>R$ 4,8 mi</strong><small>Valor ilustrativo</small></div><div class="mini-kpi"><span>Valor medido</span><strong>R$ 2,9 mi</strong><small>Valor ilustrativo</small></div><div class="mini-kpi"><span>Saldo contratual</span><strong>R$ 1,9 mi</strong><small>Valor ilustrativo</small></div><div class="mini-kpi"><span>Medições pendentes</span><strong>04</strong><small>Para análise</small></div></div>
  <div class="content-card-grid"><div class="info-card"><div class="card-icon">▤</div><h3>Orçamentos</h3><p>Composições, quantitativos, insumos, BDI e demonstrativos sintéticos e analíticos.</p><button class="row-actions" data-demo="Abrir orçamentos">Acessar módulo →</button></div><div class="info-card"><div class="card-icon">▥</div><h3>Medições</h3><p>Registro de serviços medidos, períodos, itens executados e saldo contratual.</p><button class="row-actions" data-demo="Abrir medições">Acessar módulo →</button></div><div class="info-card"><div class="card-icon">▧</div><h3>Composições</h3><p>Base de serviços, unidades, insumos e referências de custos.</p><button class="row-actions" data-demo="Abrir composições">Acessar módulo →</button></div></div>
  <div class="panel" style="margin-top:15px"><div class="panel-heading"><div><h3>Últimas medições</h3><p>Exemplo de acompanhamento financeiro</p></div><button class="btn btn-primary" data-demo="Nova medição">＋ Nova medição</button></div><div class="table-scroll"><table class="data-table"><thead><tr><th>MEDIÇÃO</th><th>OBRA</th><th>PERÍODO</th><th>VALOR</th><th>SITUAÇÃO</th></tr></thead><tbody><tr><td><strong>MED-0008</strong></td><td>Reforma de unidade pública</td><td>Outubro/2026</td><td>R$ 185.400,00</td><td><span class="status status-amber">Em análise</span></td></tr><tr><td><strong>MED-0007</strong></td><td>Manutenção de via urbana</td><td>Outubro/2026</td><td>R$ 92.800,00</td><td><span class="status status-green">Aprovada</span></td></tr></tbody></table></div></div>`;
}
function reportsPage() {
  return `<div class="content-card-grid">${[['Obras e execução','Situação, evolução física, prazos e localização.','▦'],['Ordens de serviço','Solicitações, equipes, prioridades e conclusão.','☷'],['Medições e orçamento','Valores previstos, medidos e saldos.','＄'],['Materiais e estoque','Entradas, saídas, consumo e estoque mínimo.','▣'],['Fiscalização','Vistorias, ocorrências e registros fotográficos.','✓'],['Patrimônio público','Imóveis, manutenções e histórico.','⌂']].map(x=>`<div class="info-card"><div class="card-icon">${x[2]}</div><h3>${x[0]}</h3><p>${x[1]}</p><button class="row-actions" data-demo="Gerar relatório: ${x[0]}">Preparar relatório →</button></div>`).join('')}</div>
  <div class="form-panel"><h3 class="page-subtitle">Parâmetros do relatório</h3><p class="page-help">Selecione os filtros para preparar uma visualização demonstrativa.</p><div class="form-grid"><div class="field"><label>Tipo de relatório</label><select><option>Obras e execução</option><option>Ordens de serviço</option><option>Fiscalização</option><option>Materiais e estoque</option></select></div><div class="field"><label>Período</label><select><option>Últimos 30 dias</option><option>Últimos 90 dias</option><option>Ano atual</option></select></div><div class="field"><label>Situação</label><select><option>Todas</option><option>Em andamento</option><option>Concluídas</option><option>Pendentes</option></select></div><div class="field"><label>Formato de saída</label><select><option>PDF</option><option>Excel</option></select></div></div><div style="margin-top:16px"><button class="btn btn-primary" data-demo="Pré-visualização do relatório">Pré-visualizar relatório</button></div></div>`;
}
function formPage(key) {
  const title = pageInfo[key][0];
  return `<div class="form-panel"><h3 class="page-subtitle">Área de cadastro e consulta</h3><p class="page-help">Modelo visual dos campos previstos para este módulo. O envio ainda não grava informações.</p><div class="form-grid">
  <div class="field"><label>Identificação / código</label><input placeholder="Gerado pelo sistema"></div>
  <div class="field"><label>Nome / título</label><input placeholder="Informe o nome ou título"></div>
  <div class="field"><label>Setor responsável</label><select><option>Selecione o setor</option><option>Engenharia</option><option>Planejamento</option><option>Manutenção</option></select></div>
  <div class="field"><label>Responsável</label><input placeholder="Nome do responsável"></div>
  <div class="field"><label>Data de referência</label><input type="date"></div>
  <div class="field"><label>Situação</label><select><option>Em análise</option><option>Em andamento</option><option>Concluído</option><option>Suspenso</option></select></div>
  <div class="field full"><label>Descrição / observações</label><textarea placeholder="Descreva as informações relevantes..."></textarea></div>
  <div class="field full"><label>Anexos</label><input type="file" multiple><small style="display:block;color:#9aa6b0;font-size:10px;margin-top:6px">Seleção demonstrativa. O envio de arquivos será implementado no back-end.</small></div>
  </div><div style="display:flex;gap:9px;margin-top:18px"><button class="btn btn-primary" data-demo="Salvar ${title}">Salvar registro</button><button class="btn btn-light" data-demo="Formulário limpo">Cancelar</button></div></div>`;
}
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

function openPage(key) {
  const info = pageInfo[key] || pageInfo.dashboard;
  document.getElementById('pageTitle').textContent = info[0];
  document.getElementById('breadcrumbCurrent').textContent = info[0];
  document.getElementById('pageDescription').textContent = info[1];
  document.querySelectorAll('.nav-link').forEach(link => link.classList.toggle('active', link.dataset.page === key));
  pageContent.innerHTML = pageTemplate(key);
  closeMenu();
}
document.querySelectorAll('[data-page]').forEach(element => element.addEventListener('click', () => openPage(element.dataset.page)));
pageContent.addEventListener('click', event => {
  const button = event.target.closest('[data-demo]');
  if (button) showToast(`${button.dataset.demo}: demonstração visual. A função será ligada ao PHP na próxima etapa.`);
});
document.getElementById('newAction')?.addEventListener('click', () => showToast('Cadastro demonstrativo. A gravação será implementada na etapa PHP/MySQL.'));
document.getElementById('exportBtn')?.addEventListener('click', () => showToast('Exportação PDF/Excel será conectada aos dados reais na próxima etapa.'));
const date = new Intl.DateTimeFormat('pt-BR', {weekday:'short',day:'2-digit',month:'long',year:'numeric'}).format(new Date());
document.getElementById('currentDate').textContent = `Coari, ${date}`;
