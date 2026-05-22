/* =========================================================
   OSmais — Telas operacionais front-end
   ========================================================= */
const appRoot = document.querySelector('.operational-page');
const currentPageKey = appRoot?.dataset.page;
const appState = {
  page: currentPageKey,
  pageSize: 8,
  pageNumber: 1,
  filters: {},
  budgetItems: [],
};

const clientesMock = [
  { id: 1, nome: 'Restaurante Sabor Norte', tipo: 'Pessoa Jurídica', doc: '12.845.778/0001-20', telefone: '(92) 98844-1188', whatsapp: '(92) 98844-1188', cidade: 'Manaus', status: 'Ativo', endereco: 'Av. Djalma Batista, 1240', osAbertas: 2, ultimo: '18/05/2026', email: 'financeiro@sabornorte.com.br' },
  { id: 2, nome: 'Mercado Ponto Frio', tipo: 'Pessoa Jurídica', doc: '08.441.302/0001-91', telefone: '(92) 98122-5544', whatsapp: '(92) 98122-5544', cidade: 'Manaus', status: 'Ativo', endereco: 'Rua das Palmeiras, 88', osAbertas: 3, ultimo: '16/05/2026', email: 'compras@pontofrio.local' },
  { id: 3, nome: 'João Almeida', tipo: 'Pessoa Física', doc: '182.334.210-09', telefone: '(92) 99134-2201', whatsapp: '(92) 99134-2201', cidade: 'Manaus', status: 'Ativo', endereco: 'Rua Rio Madeira, 55', osAbertas: 1, ultimo: '14/05/2026', email: 'joao.almeida@email.com' },
  { id: 4, nome: 'Clínica Bem Estar', tipo: 'Pessoa Jurídica', doc: '31.672.404/0001-75', telefone: '(92) 3232-4411', whatsapp: '(92) 98420-1199', cidade: 'Manaus', status: 'Ativo', endereco: 'Av. Constantino Nery, 880', osAbertas: 1, ultimo: '12/05/2026', email: 'adm@bemestar.local' },
  { id: 5, nome: 'Padaria Santa Luzia', tipo: 'Pessoa Jurídica', doc: '19.504.100/0001-12', telefone: '(92) 3663-7788', whatsapp: '(92) 98233-4510', cidade: 'Manaus', status: 'Ativo', endereco: 'Rua Recife, 190', osAbertas: 0, ultimo: '09/05/2026', email: 'padaria@santaluzia.local' },
];

const tecnicosMock = [
  { id: 1, nome: 'Carlos Ferreira', telefone: '(92) 98444-1001', whatsapp: '(92) 98444-1001', email: 'carlos@yamaguchi.local', especialidade: 'Ar-condicionado', status: 'Em atendimento', osHoje: 3, osAndamento: 4, media: '1,8 dias' },
  { id: 2, nome: 'Ana Martins', telefone: '(92) 98222-3104', whatsapp: '(92) 98222-3104', email: 'ana@yamaguchi.local', especialidade: 'Câmara fria', status: 'Disponível', osHoje: 2, osAndamento: 2, media: '2,1 dias' },
  { id: 3, nome: 'Lucas Ferreira', telefone: '(92) 98654-9900', whatsapp: '(92) 98654-9900', email: 'lucas@yamaguchi.local', especialidade: 'Refrigeração comercial', status: 'Ativo', osHoje: 1, osAndamento: 3, media: '2,4 dias' },
  { id: 4, nome: 'Pedro Alves', telefone: '(92) 98101-7654', whatsapp: '(92) 98101-7654', email: 'pedro@yamaguchi.local', especialidade: 'Manutenção corretiva', status: 'Ativo', osHoje: 0, osAndamento: 1, media: '1,6 dias' },
];

const servicosMock = [
  { id: 1, nome: 'Limpeza de ar-condicionado split', categoria: 'Manutenção preventiva', valor: 180, tempo: '1h30', status: 'Ativo', uso: 42 },
  { id: 2, nome: 'Troca de compressor', categoria: 'Manutenção corretiva', valor: 650, tempo: '3h', status: 'Ativo', uso: 18 },
  { id: 3, nome: 'Manutenção preventiva em câmara fria', categoria: 'Câmara fria', valor: 520, tempo: '4h', status: 'Ativo', uso: 21 },
  { id: 4, nome: 'Carga de gás', categoria: 'Refrigeração comercial', valor: 320, tempo: '2h', status: 'Ativo', uso: 27 },
  { id: 5, nome: 'Diagnóstico técnico', categoria: 'Visita técnica', valor: 120, tempo: '45min', status: 'Ativo', uso: 58 },
  { id: 6, nome: 'Manutenção em balcão refrigerado', categoria: 'Refrigeração comercial', valor: 430, tempo: '3h', status: 'Inativo', uso: 9 },
];

const pecasMock = [
  { id: 1, codigo: 'CMP-014', nome: 'Compressor 1/4 HP', categoria: 'Compressor', estoque: 3, minimo: 2, custo: 420, venda: 620, fornecedor: 'FrioPeças AM', status: 'Em estoque' },
  { id: 2, codigo: 'SEN-022', nome: 'Sensor de temperatura', categoria: 'Sensor', estoque: 2, minimo: 5, custo: 38, venda: 75, fornecedor: 'Refrigera Norte', status: 'Estoque baixo' },
  { id: 3, codigo: 'PLC-110', nome: 'Placa eletrônica split', categoria: 'Placa eletrônica', estoque: 1, minimo: 3, custo: 210, venda: 360, fornecedor: 'Clima Parts', status: 'Estoque baixo' },
  { id: 4, codigo: 'GAS-410', nome: 'Gás refrigerante R410A', categoria: 'Gás refrigerante', estoque: 8, minimo: 4, custo: 280, venda: 390, fornecedor: 'FrioPeças AM', status: 'Em estoque' },
  { id: 5, codigo: 'FLT-009', nome: 'Filtro secador', categoria: 'Filtro', estoque: 0, minimo: 6, custo: 24, venda: 49, fornecedor: 'Refrigera Norte', status: 'Sem estoque' },
  { id: 6, codigo: 'TBC-018', nome: 'Tubo de cobre 1/4', categoria: 'Tubulação', estoque: 14, minimo: 8, custo: 32, venda: 58, fornecedor: 'MetalClima', status: 'Em estoque' },
];

const osMock = [
  { id: 248, numero: 'OS-00248', cliente: 'Restaurante Sabor Norte', telefone: '(92) 98844-1188', servico: 'Manutenção preventiva em câmara fria', equipamento: 'Câmara fria Consul 4 portas', status: 'Em andamento', prioridade: 'Alta', tecnico: 'Carlos Ferreira', data: '22/05/2026', valor: 1320, whatsapp: '(92) 98844-1188' },
  { id: 247, numero: 'OS-00247', cliente: 'Mercado Ponto Frio', telefone: '(92) 98122-5544', servico: 'Troca de compressor', equipamento: 'Freezer vertical Metalfrio', status: 'Aguardando peça', prioridade: 'Urgente', tecnico: 'Ana Martins', data: '22/05/2026', valor: 2380, whatsapp: '(92) 98122-5544' },
  { id: 246, numero: 'OS-00246', cliente: 'Clínica Bem Estar', telefone: '(92) 3232-4411', servico: 'Limpeza de ar-condicionado split', equipamento: 'Split LG 18.000 BTUs', status: 'Aguardando aprovação', prioridade: 'Média', tecnico: 'Lucas Ferreira', data: '21/05/2026', valor: 360, whatsapp: '(92) 98420-1199' },
  { id: 245, numero: 'OS-00245', cliente: 'Padaria Santa Luzia', telefone: '(92) 3663-7788', servico: 'Carga de gás', equipamento: 'Balcão refrigerado Gelopar', status: 'Finalizada', prioridade: 'Baixa', tecnico: 'Pedro Alves', data: '20/05/2026', valor: 520, whatsapp: '(92) 98233-4510' },
  { id: 244, numero: 'OS-00244', cliente: 'João Almeida', telefone: '(92) 99134-2201', servico: 'Diagnóstico técnico', equipamento: 'Ar-condicionado Janela', status: 'Aberta', prioridade: 'Média', tecnico: '—', data: '23/05/2026', valor: 120, whatsapp: '(92) 99134-2201' },
  { id: 243, numero: 'OS-00243', cliente: 'Mercado Ponto Frio', telefone: '(92) 98122-5544', servico: 'Manutenção em balcão refrigerado', equipamento: 'Balcão refrigerado 2m', status: 'Cancelada', prioridade: 'Alta', tecnico: 'Carlos Ferreira', data: '18/05/2026', valor: 0, whatsapp: '(92) 98122-5544' },
];

const agendaMock = [
  { id: 1, horario: '08:30', cliente: 'Restaurante Sabor Norte', endereco: 'Av. Djalma Batista, 1240', tecnico: 'Carlos Ferreira', servico: 'Câmara fria', status: 'Agendado', prioridade: 'Alta', os: 'OS-00248' },
  { id: 2, horario: '10:00', cliente: 'Mercado Ponto Frio', endereco: 'Rua das Palmeiras, 88', tecnico: 'Ana Martins', servico: 'Troca de compressor', status: 'Em andamento', prioridade: 'Urgente', os: 'OS-00247' },
  { id: 3, horario: '14:00', cliente: 'Clínica Bem Estar', endereco: 'Av. Constantino Nery, 880', tecnico: 'Lucas Ferreira', servico: 'Limpeza split', status: 'Aguardando aprovação', prioridade: 'Média', os: 'OS-00246' },
  { id: 4, horario: '16:30', cliente: 'João Almeida', endereco: 'Rua Rio Madeira, 55', tecnico: 'Sem técnico', servico: 'Diagnóstico técnico', status: 'Aberta', prioridade: 'Média', os: 'OS-00244' },
];

const orcamentosMock = [
  { id: 123, numero: 'ORC-000123', cliente: 'Restaurante Sabor Norte', whatsapp: '(92) 98844-1188', os: 'OS-00248', data: '22/05/2026', validade: '29/05/2026', valor: 1320, status: 'Aguardando envio', tecnico: 'Carlos Ferreira' },
  { id: 122, numero: 'ORC-000122', cliente: 'Mercado Ponto Frio', whatsapp: '(92) 98122-5544', os: 'OS-00247', data: '21/05/2026', validade: '28/05/2026', valor: 2380, status: 'Enviado', tecnico: 'Ana Martins' },
  { id: 121, numero: 'ORC-000121', cliente: 'Clínica Bem Estar', whatsapp: '(92) 98420-1199', os: 'OS-00246', data: '20/05/2026', validade: '27/05/2026', valor: 360, status: 'Aprovado', tecnico: 'Lucas Ferreira' },
  { id: 120, numero: 'ORC-000120', cliente: 'Padaria Santa Luzia', whatsapp: '(92) 98233-4510', os: 'OS-00245', data: '18/05/2026', validade: '25/05/2026', valor: 520, status: 'Recusado', tecnico: 'Pedro Alves' },
];

const notasMock = [
  { id: 1, numero: 'NF-00084', cliente: 'Clínica Bem Estar', vinculo: 'ORC-000121', data: '21/05/2026', valor: 360, status: 'Emitida' },
  { id: 2, numero: 'Pendente', cliente: 'Restaurante Sabor Norte', vinculo: 'ORC-000123', data: '22/05/2026', valor: 1320, status: 'Aguardando emissão' },
  { id: 3, numero: 'NF-00083', cliente: 'Padaria Santa Luzia', vinculo: 'OS-00245', data: '20/05/2026', valor: 520, status: 'Emitida' },
  { id: 4, numero: 'Pendente', cliente: 'Mercado Ponto Frio', vinculo: 'ORC-000122', data: '22/05/2026', valor: 2380, status: 'Pendente' },
];

const currency = value => Number(value || 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
const escapeHtml = value => String(value ?? '').replace(/[&<>"']/g, char => ({ '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#039;' }[char]));
const byId = id => document.getElementById(id);

function toast(msg, type = 'info') {
  const container = byId('toast-container');
  if (!container) return;
  const icon = type === 'success' ? 'bi-check-circle-fill' : type === 'error' ? 'bi-x-circle-fill' : 'bi-info-circle-fill';
  const el = document.createElement('div');
  el.className = `toast-msg ${type}`;
  el.innerHTML = `<i class="bi ${icon}"></i> ${escapeHtml(msg)}`;
  container.appendChild(el);
  setTimeout(() => el.style.opacity = '0', 3200);
  setTimeout(() => el.remove(), 3600);
}

function handleTopbarRefresh() {
  renderCurrentPage();
  toast('Tela atualizada com dados de demonstração', 'success');
}

function toggleFullscreen() {
  if (!document.fullscreenElement) document.documentElement.requestFullscreen?.();
  else document.exitFullscreen?.();
}

function badge(value, variant = 'gray') {
  const map = {
    'Aberta': 'blue',
    'Agendado': 'blue',
    'Em andamento': 'amber',
    'Aguardando peça': 'purple',
    'Aguardando aprovação': 'purple',
    'Aguardando envio': 'amber',
    'Enviado': 'blue',
    'Finalizada': 'green',
    'Aprovado': 'green',
    'Emitida': 'green',
    'Ativo': 'green',
    'Disponível': 'green',
    'Estoque baixo': 'amber',
    'Urgente': 'red',
    'Alta': 'red',
    'Cancelada': 'red',
    'Recusado': 'red',
    'Sem estoque': 'red',
    'Inativo': 'gray',
  };
  return `<span class="badge-soft badge-${map[value] || variant}">${escapeHtml(value)}</span>`;
}

function actions(buttons) {
  return `<div class="action-row">${buttons.map(btn => `
    <button class="btn-action${btn.danger ? ' danger' : ''}" type="button" title="${escapeHtml(btn.title)}" onclick="${btn.onClick}">
      <i class="bi ${btn.icon}"></i>
    </button>
  `).join('')}</div>`;
}

function matchesSearch(row, fields, term) {
  if (!term) return true;
  return fields.some(field => normalizeText(row[field]).includes(term));
}

function normalizeText(value) {
  return String(value ?? '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
}

function metric(label, value, icon, accent, footer = '') {
  return `
    <div class="metric-card" style="--card-accent:${accent}">
      <div class="metric-head">
        <div class="metric-label">${escapeHtml(label)}</div>
        <div class="metric-icon-wrap" style="--icon-bg:#EFF6FF;--icon-color:${accent}">
          <i class="bi ${icon}"></i>
        </div>
      </div>
      <div class="metric-value">${escapeHtml(value)}</div>
      <div class="metric-footer">
        <span class="metric-change change-neutral">${escapeHtml(footer || 'demo')}</span>
      </div>
    </div>
  `;
}

const pageConfigs = {
  ordens: {
    title: 'Ordens de Serviço',
    data: osMock,
    search: ['numero', 'cliente', 'equipamento', 'tecnico', 'servico'],
    filters: [
      { key: 'status', label: 'Status', values: ['Aberta', 'Em andamento', 'Aguardando peça', 'Aguardando aprovação', 'Finalizada', 'Cancelada'] },
      { key: 'prioridade', label: 'Prioridade', values: ['Baixa', 'Média', 'Alta', 'Urgente'] },
      { key: 'tecnico', label: 'Técnico', values: tecnicosMock.map(t => t.nome) },
      { key: 'data', label: 'Período', type: 'date' },
    ],
    summary: rows => [
      metric('Abertas', rows.filter(r => r.status === 'Aberta').length, 'bi-folder2-open', '#2563EB', 'aguardando atendimento'),
      metric('Em andamento', rows.filter(r => r.status === 'Em andamento').length, 'bi-arrow-repeat', '#D97706', 'em execução'),
      metric('Aguardando peça', rows.filter(r => r.status === 'Aguardando peça').length, 'bi-box-seam', '#7C3AED', 'estoque crítico'),
      metric('Finalizadas', rows.filter(r => r.status === 'Finalizada').length, 'bi-check-circle', '#16A34A', 'no mês'),
      metric('Canceladas', rows.filter(r => r.status === 'Cancelada').length, 'bi-x-circle', '#DC2626', 'histórico'),
    ],
    columns: [
      ['Nº OS', r => r.numero],
      ['Cliente', r => primaryCell(r.cliente, r.telefone)],
      ['Equipamento', r => primaryCell(r.equipamento, r.servico)],
      ['Técnico', r => r.tecnico],
      ['Status', r => badge(r.status)],
      ['Prioridade', r => badge(r.prioridade)],
      ['Data', r => r.data],
      ['Valor', r => currency(r.valor)],
      ['Ações', r => actions([
        { title: 'Ver detalhes', icon: 'bi-eye', onClick: `openDetail('os', ${r.id})` },
        { title: 'Editar', icon: 'bi-pencil', onClick: `openEntityModal('os', ${r.id})` },
        { title: 'Gerar orçamento', icon: 'bi-file-earmark-text', onClick: `openEntityModal('orcamento')` },
        { title: 'Finalizar', icon: 'bi-check2-circle', onClick: `visualStatus('OS finalizada visualmente')` },
        { title: 'Cancelar', icon: 'bi-x-circle', danger: true, onClick: `confirmVisualAction('Cancelar OS?')` },
      ])],
    ],
    side: () => sideCards([
      ['OS urgentes', '2 atendimentos exigem prioridade hoje', 'bi-exclamation-triangle'],
      ['Orçamentos aguardando aprovação', '3 propostas precisam de retorno', 'bi-file-earmark-check'],
      ['Peças críticas', 'Filtro secador e sensor abaixo do mínimo', 'bi-box-seam'],
    ]),
  },
  clientes: {
    title: 'Clientes',
    data: clientesMock,
    search: ['nome', 'doc', 'telefone', 'whatsapp', 'endereco', 'cidade'],
    filters: [
      { key: 'tipo', label: 'Tipo de cliente', values: ['Pessoa Física', 'Pessoa Jurídica'] },
      { key: 'cidade', label: 'Cidade', values: ['Manaus'] },
      { key: 'status', label: 'Status', values: ['Ativo', 'Inativo'] },
    ],
    summary: rows => [
      metric('Total de clientes', rows.length, 'bi-people', '#2563EB', 'base ativa'),
      metric('Pessoa física', rows.filter(r => r.tipo === 'Pessoa Física').length, 'bi-person', '#7C3AED', 'PF'),
      metric('Empresas', rows.filter(r => r.tipo === 'Pessoa Jurídica').length, 'bi-building', '#D97706', 'PJ'),
      metric('Com OS aberta', rows.filter(r => r.osAbertas > 0).length, 'bi-wrench', '#16A34A', 'em atendimento'),
    ],
    columns: [
      ['Nome/Razão Social', r => primaryCell(r.nome, r.tipo)],
      ['CPF/CNPJ', r => r.doc],
      ['Telefone', r => r.telefone],
      ['WhatsApp', r => r.whatsapp],
      ['Cidade', r => r.cidade],
      ['OS abertas', r => r.osAbertas],
      ['Último atendimento', r => r.ultimo],
      ['Status', r => badge(r.status)],
      ['Ações', r => actions([
        { title: 'Ver histórico', icon: 'bi-clock-history', onClick: `openDetail('cliente', ${r.id})` },
        { title: 'Editar', icon: 'bi-pencil', onClick: `openEntityModal('cliente', ${r.id})` },
        { title: 'Criar OS', icon: 'bi-card-list', onClick: `openEntityModal('os')` },
        { title: 'Criar orçamento', icon: 'bi-file-earmark-text', onClick: `openEntityModal('orcamento')` },
        { title: 'Excluir visualmente', icon: 'bi-trash', danger: true, onClick: `confirmVisualAction('Excluir cliente visualmente?')` },
      ])],
    ],
    side: () => sideCards([
      ['Histórico recente', 'Restaurante Sabor Norte teve 2 OS no mês', 'bi-clock-history'],
      ['Clientes críticos', 'Mercado Ponto Frio possui OS urgente', 'bi-exclamation-triangle'],
    ]),
  },
  tecnicos: {
    title: 'Técnicos',
    data: tecnicosMock,
    search: ['nome', 'telefone', 'especialidade', 'status'],
    filters: [
      { key: 'especialidade', label: 'Especialidade', values: ['Instalação', 'Manutenção preventiva', 'Manutenção corretiva', 'Câmara fria', 'Ar-condicionado', 'Refrigeração comercial', 'Elétrica básica'] },
      { key: 'status', label: 'Status', values: ['Ativo', 'Inativo', 'Em atendimento', 'Disponível'] },
    ],
    summary: rows => [
      metric('Técnicos ativos', rows.filter(r => r.status !== 'Inativo').length, 'bi-person-check', '#16A34A', 'equipe'),
      metric('Em atendimento hoje', rows.filter(r => r.osHoje > 0).length, 'bi-geo-alt', '#2563EB', 'agenda'),
      metric('OS sem técnico', '11', 'bi-person-x', '#DC2626', 'alocar'),
      metric('Média de finalização', '1,9 dias', 'bi-speedometer2', '#7C3AED', 'últimos 30 dias'),
    ],
    columns: [
      ['Nome', r => primaryCell(r.nome, r.email)],
      ['Telefone', r => r.telefone],
      ['Especialidade', r => r.especialidade],
      ['Status', r => badge(r.status)],
      ['OS em andamento', r => r.osAndamento],
      ['Agenda do dia', r => `${r.osHoje} visitas`],
      ['Ações', r => actions([
        { title: 'Ver agenda', icon: 'bi-calendar3', onClick: `location.href='agenda.php'` },
        { title: 'Editar', icon: 'bi-pencil', onClick: `openEntityModal('tecnico', ${r.id})` },
        { title: 'Vincular OS', icon: 'bi-link-45deg', onClick: `openEntityModal('os')` },
        { title: 'Desativar', icon: 'bi-person-dash', danger: true, onClick: `confirmVisualAction('Desativar técnico visualmente?')` },
      ])],
    ],
  },
  agenda: {
    title: 'Agenda operacional',
    data: agendaMock,
    search: ['cliente', 'endereco', 'tecnico', 'servico', 'os'],
    filters: [
      { key: 'tecnico', label: 'Técnico', values: ['Sem técnico', ...tecnicosMock.map(t => t.nome)] },
      { key: 'status', label: 'Status', values: ['Aberta', 'Agendado', 'Em andamento', 'Aguardando aprovação', 'Finalizada'] },
      { key: 'prioridade', label: 'Prioridade', values: ['Baixa', 'Média', 'Alta', 'Urgente'] },
      { key: 'servico', label: 'Tipo de serviço', values: ['Câmara fria', 'Troca de compressor', 'Limpeza split', 'Diagnóstico técnico'] },
    ],
    summary: rows => [
      metric('Hoje', rows.length, 'bi-calendar-day', '#2563EB', 'atendimentos'),
      metric('Semana', '18', 'bi-calendar-week', '#7C3AED', 'previstos'),
      metric('Sem técnico', rows.filter(r => r.tecnico === 'Sem técnico').length, 'bi-person-x', '#DC2626', 'alocar'),
      metric('Urgentes', rows.filter(r => r.prioridade === 'Urgente').length, 'bi-exclamation-triangle', '#D97706', 'prioridade'),
    ],
    columns: [
      ['Horário', r => r.horario],
      ['Cliente', r => primaryCell(r.cliente, r.endereco)],
      ['Técnico', r => r.tecnico],
      ['Serviço', r => r.servico],
      ['Status', r => badge(r.status)],
      ['Prioridade', r => badge(r.prioridade)],
      ['OS', r => r.os],
      ['Ações', r => actions([
        { title: 'Ver OS', icon: 'bi-eye', onClick: `openDetail('agenda', ${r.id})` },
        { title: 'Reagendar', icon: 'bi-calendar-event', onClick: `openEntityModal('agenda', ${r.id})` },
        { title: 'Marcar em andamento', icon: 'bi-play-circle', onClick: `visualStatus('Atendimento marcado em andamento')` },
        { title: 'Finalizar', icon: 'bi-check2-circle', onClick: `visualStatus('Atendimento finalizado visualmente')` },
        { title: 'Cancelar', icon: 'bi-x-circle', danger: true, onClick: `confirmVisualAction('Cancelar agendamento?')` },
      ])],
    ],
    secondary: () => unassignedPanel(),
  },
  pecas: {
    title: 'Peças / Estoque',
    data: pecasMock,
    search: ['codigo', 'nome', 'categoria', 'fornecedor'],
    filters: [
      { key: 'categoria', label: 'Categoria', values: ['Compressor', 'Sensor', 'Tubulação', 'Gás refrigerante', 'Placa eletrônica', 'Controle', 'Filtro', 'Motor', 'Ventilador', 'Outro'] },
      { key: 'status', label: 'Status do estoque', values: ['Em estoque', 'Estoque baixo', 'Sem estoque'] },
      { key: 'fornecedor', label: 'Fornecedor', values: ['FrioPeças AM', 'Refrigera Norte', 'Clima Parts', 'MetalClima'] },
    ],
    summary: rows => [
      metric('Total de peças', rows.length, 'bi-box-seam', '#2563EB', 'itens'),
      metric('Estoque baixo', rows.filter(r => r.status === 'Estoque baixo').length, 'bi-exclamation-triangle', '#D97706', 'atenção'),
      metric('Valor em estoque', currency(rows.reduce((sum, r) => sum + r.custo * r.estoque, 0)), 'bi-cash-coin', '#16A34A', 'custo estimado'),
      metric('Mais usadas', 'Compressor', 'bi-star', '#7C3AED', 'últimos 30 dias'),
    ],
    columns: [
      ['Código', r => r.codigo],
      ['Peça', r => primaryCell(r.nome, r.categoria)],
      ['Estoque atual', r => r.estoque],
      ['Estoque mínimo', r => r.minimo],
      ['Custo', r => currency(r.custo)],
      ['Venda', r => currency(r.venda)],
      ['Fornecedor', r => r.fornecedor],
      ['Status', r => badge(r.status)],
      ['Ações', r => actions([
        { title: 'Editar', icon: 'bi-pencil', onClick: `openEntityModal('peca', ${r.id})` },
        { title: 'Entrada de estoque', icon: 'bi-plus-circle', onClick: `openStockMovement('entrada', ${r.id})` },
        { title: 'Saída de estoque', icon: 'bi-dash-circle', onClick: `openStockMovement('saida', ${r.id})` },
        { title: 'Ver movimentações', icon: 'bi-clock-history', onClick: `openDetail('peca', ${r.id})` },
      ])],
    ],
  },
  servicos: {
    title: 'Serviços',
    data: servicosMock,
    search: ['nome', 'categoria', 'status'],
    filters: [
      { key: 'categoria', label: 'Categoria', values: ['Instalação', 'Manutenção preventiva', 'Manutenção corretiva', 'Câmara fria', 'Refrigeração comercial', 'Visita técnica'] },
      { key: 'status', label: 'Status', values: ['Ativo', 'Inativo'] },
    ],
    summary: rows => [
      metric('Serviços ativos', rows.filter(r => r.status === 'Ativo').length, 'bi-check-circle', '#16A34A', 'catálogo'),
      metric('Mais usado', 'Diagnóstico', 'bi-graph-up', '#2563EB', '58 OS'),
      metric('Ticket médio', currency(365), 'bi-cash', '#D97706', 'estimado'),
      metric('Inativos', rows.filter(r => r.status === 'Inativo').length, 'bi-pause-circle', '#64748B', 'ocultos'),
    ],
    columns: [
      ['Serviço', r => primaryCell(r.nome, r.categoria)],
      ['Valor base', r => currency(r.valor)],
      ['Tempo médio', r => r.tempo],
      ['Uso', r => `${r.uso} OS`],
      ['Status', r => badge(r.status)],
      ['Ações', r => actions([
        { title: 'Editar', icon: 'bi-pencil', onClick: `openEntityModal('servico', ${r.id})` },
        { title: 'Desativar', icon: 'bi-pause-circle', danger: true, onClick: `confirmVisualAction('Desativar serviço visualmente?')` },
        { title: 'Ver OS vinculadas', icon: 'bi-card-list', onClick: `location.href='ordens-servico.php'` },
      ])],
    ],
  },
  orcamentos: {
    title: 'Orçamentos',
    data: orcamentosMock,
    search: ['numero', 'cliente', 'os', 'tecnico'],
    filters: [
      { key: 'status', label: 'Status', values: ['Rascunho', 'Aguardando envio', 'Enviado', 'Aprovado', 'Recusado', 'Expirado'] },
      { key: 'tecnico', label: 'Técnico', values: tecnicosMock.map(t => t.nome) },
      { key: 'data', label: 'Período', type: 'date' },
    ],
    summary: rows => [
      metric('Criados', rows.length, 'bi-file-earmark-text', '#2563EB', 'mês atual'),
      metric('Aguardando aprovação', rows.filter(r => ['Aguardando envio', 'Enviado'].includes(r.status)).length, 'bi-hourglass-split', '#D97706', 'retorno'),
      metric('Aprovados', rows.filter(r => r.status === 'Aprovado').length, 'bi-check-circle', '#16A34A', 'confirmados'),
      metric('Recusados', rows.filter(r => r.status === 'Recusado').length, 'bi-x-circle', '#DC2626', 'perdidos'),
      metric('Valor pendente', currency(rows.filter(r => r.status !== 'Aprovado').reduce((sum, r) => sum + r.valor, 0)), 'bi-cash-coin', '#7C3AED', 'pipeline'),
    ],
    columns: [
      ['Nº Orçamento', r => r.numero],
      ['Cliente', r => primaryCell(r.cliente, r.whatsapp)],
      ['OS vinculada', r => r.os],
      ['Data', r => r.data],
      ['Validade', r => r.validade],
      ['Valor total', r => currency(r.valor)],
      ['Status', r => badge(r.status)],
      ['Ações', r => actions([
        { title: 'Visualizar', icon: 'bi-eye', onClick: `previewBudgetPdf(${r.id})` },
        { title: 'Editar', icon: 'bi-pencil', onClick: `openEntityModal('orcamento', ${r.id})` },
        { title: 'Gerar PDF', icon: 'bi-filetype-pdf', onClick: `downloadBudgetPdf(getBudgetById(${r.id}))` },
        { title: 'Enviar WhatsApp', icon: 'bi-whatsapp', onClick: `sendBudgetWhatsapp(getBudgetById(${r.id}))` },
        { title: 'Aprovar', icon: 'bi-check2-circle', onClick: `visualStatus('Orçamento marcado como aprovado')` },
        { title: 'Recusar', icon: 'bi-x-circle', danger: true, onClick: `visualStatus('Orçamento marcado como recusado')` },
      ])],
    ],
    side: () => sideCards([
      ['Fluxo correto', 'PDF é baixado e o WhatsApp abre com mensagem pronta.', 'bi-whatsapp'],
      ['Limite do front-end', 'Anexe manualmente o PDF baixado na conversa.', 'bi-info-circle'],
    ]),
  },
  faturamento: {
    title: 'Notas / Faturamento',
    data: notasMock,
    search: ['numero', 'cliente', 'vinculo', 'status'],
    filters: [{ key: 'status', label: 'Status', values: ['Pendente', 'Emitida', 'Cancelada', 'Aguardando emissão'] }],
    summary: rows => [
      metric('Faturado no mês', currency(rows.filter(r => r.status === 'Emitida').reduce((sum, r) => sum + r.valor, 0)), 'bi-currency-dollar', '#16A34A', 'emitidas'),
      metric('Pendentes', rows.filter(r => r.status === 'Pendente').length, 'bi-hourglass', '#D97706', 'atenção'),
      metric('Emitidas', rows.filter(r => r.status === 'Emitida').length, 'bi-receipt', '#2563EB', 'controle'),
      metric('Aguardando nota', rows.filter(r => r.status === 'Aguardando emissão').length, 'bi-file-earmark-check', '#7C3AED', 'orçamentos'),
    ],
    columns: [
      ['Nº Nota', r => r.numero],
      ['Cliente', r => r.cliente],
      ['Vínculo', r => r.vinculo],
      ['Data', r => r.data],
      ['Valor', r => currency(r.valor)],
      ['Status', r => badge(r.status)],
      ['Ações', r => actions([
        { title: 'Visualizar', icon: 'bi-eye', onClick: `openDetail('nota', ${r.id})` },
        { title: 'Registrar como emitida', icon: 'bi-check2-circle', onClick: `visualStatus('Nota registrada como emitida')` },
        { title: 'Vincular orçamento', icon: 'bi-link-45deg', onClick: `openEntityModal('nota', ${r.id})` },
        { title: 'Baixar comprovante visual', icon: 'bi-download', onClick: `toast('Comprovante visual simulado', 'success')` },
      ])],
    ],
    secondary: () => `<div class="panel"><div class="panel-header"><div class="panel-title"><i class="bi bi-info-circle"></i> Aviso fiscal</div></div><div class="modal-body"><p class="section-note">Emissão fiscal real será integrada futuramente. Esta tela registra o controle visual das notas no front-end.</p></div></div>`,
  },
  relatorios: {
    title: 'Relatórios',
    data: osMock,
    search: ['cliente', 'servico', 'tecnico', 'status'],
    filters: [{ key: 'status', label: 'Período', values: ['Mês atual', 'Últimos 30 dias', 'Trimestre'] }],
    summary: () => [
      metric('OS finalizadas', '48', 'bi-check-circle', '#16A34A', 'mês atual'),
      metric('Faturamento estimado', currency(48720), 'bi-cash-coin', '#2563EB', 'mês atual'),
      metric('Orçamentos aprovados', '17', 'bi-file-earmark-check', '#7C3AED', 'conversão'),
      metric('Serviço líder', 'Diagnóstico', 'bi-star', '#D97706', 'mais solicitado'),
      metric('Peça crítica', 'Sensor', 'bi-box-seam', '#DC2626', 'maior saída'),
    ],
    columns: [
      ['Cliente', r => r.cliente],
      ['Serviço', r => r.servico],
      ['Técnico', r => r.tecnico],
      ['Status', r => badge(r.status)],
      ['Valor', r => currency(r.valor)],
    ],
    secondary: () => reportsContent(),
  },
  configuracoes: {
    title: 'Configurações',
    data: [],
    search: [],
    filters: [],
    summary: () => [
      metric('Empresa', 'K.Yamaguchi', 'bi-building', '#2563EB', 'dados visuais'),
      metric('Validade padrão', '7 dias', 'bi-calendar-check', '#7C3AED', 'orçamentos'),
      metric('DDI WhatsApp', '55', 'bi-whatsapp', '#16A34A', 'Brasil'),
      metric('Tema', 'Bloqueado', 'bi-palette', '#64748B', 'preserva layout'),
    ],
    columns: [],
    secondary: () => settingsContent(),
  },
};

function primaryCell(title, subtitle = '') {
  return `<div class="table-primary-cell"><div class="table-primary">${escapeHtml(title)}</div><div class="table-secondary">${escapeHtml(subtitle)}</div></div>`;
}

function getConfig() {
  return pageConfigs[appState.page];
}

function filteredRows() {
  const config = getConfig();
  if (!config) return [];
  const term = normalizeText(byId('page-search')?.value || '');
  return (config.data || []).filter(row => {
    const searchOk = matchesSearch(row, config.search || [], term);
    const filterOk = Object.entries(appState.filters).every(([key, value]) => !value || String(row[key] ?? '') === value);
    return searchOk && filterOk;
  });
}

function renderCurrentPage() {
  const config = getConfig();
  if (!appRoot || !config) return;
  renderSummary(config);
  renderFilters(config);
  renderTable(config, filteredRows());
  renderSide(config);
  renderSecondary(config);
}

function renderSummary(config) {
  byId('summary-grid').innerHTML = config.summary(filteredRows()).join('');
  const sbTotal = byId('sb-total');
  if (sbTotal) sbTotal.textContent = '248';
}

function renderFilters(config) {
  const search = byId('page-search');
  if (search && !search.dataset.ready) {
    search.placeholder = config.searchPlaceholder || 'Buscar por nome, número, cliente ou técnico...';
    search.addEventListener('input', () => {
      appState.pageNumber = 1;
      renderTable(config, filteredRows());
    });
    search.dataset.ready = '1';
  }

  byId('dynamic-filters').innerHTML = (config.filters || []).map(filter => {
    if (filter.type === 'date') {
      return `<input class="filter-select page-filter" type="date" data-key="${filter.key}" aria-label="${escapeHtml(filter.label)}">`;
    }
    return `
      <select class="filter-select page-filter" data-key="${filter.key}">
        <option value="">${escapeHtml(filter.label)}</option>
        ${filter.values.map(v => `<option value="${escapeHtml(v)}">${escapeHtml(v)}</option>`).join('')}
      </select>
    `;
  }).join('');

  document.querySelectorAll('.page-filter').forEach(field => {
    field.value = appState.filters[field.dataset.key] || '';
    field.addEventListener('change', () => {
      appState.filters[field.dataset.key] = field.value;
      appState.pageNumber = 1;
      renderTable(config, filteredRows());
    });
  });
}

function renderTable(config, rows) {
  const title = byId('main-panel-title');
  if (title) title.innerHTML = `<i class="bi bi-table"></i> ${escapeHtml(config.title)}`;

  const table = document.querySelector('.data-table');
  if (!config.columns.length) {
    table.style.display = 'none';
    byId('table-count-label').textContent = 'Conteúdo configurável';
    byId('table-pagination').innerHTML = '';
    return;
  }
  table.style.display = '';

  byId('data-table-head').innerHTML = `<tr>${config.columns.map(col => `<th>${escapeHtml(col[0])}</th>`).join('')}</tr>`;
  const total = rows.length;
  const pages = Math.max(1, Math.ceil(total / appState.pageSize));
  appState.pageNumber = Math.min(appState.pageNumber, pages);
  const start = (appState.pageNumber - 1) * appState.pageSize;
  const pageRows = rows.slice(start, start + appState.pageSize);

  if (!pageRows.length) {
    byId('data-table-body').innerHTML = `<tr><td colspan="${config.columns.length}"><div class="empty-state"><i class="bi bi-inbox"></i><p>Nenhum registro encontrado</p></div></td></tr>`;
  } else {
    byId('data-table-body').innerHTML = pageRows.map(row => `
      <tr>${config.columns.map(col => `<td>${col[1](row)}</td>`).join('')}</tr>
    `).join('');
  }

  byId('table-count-label').textContent = total ? `Exibindo ${start + 1}-${Math.min(start + appState.pageSize, total)} de ${total}` : 'Nenhum registro';
  byId('table-pagination').innerHTML = renderPagination(pages);
}

function renderPagination(pages) {
  if (pages <= 1) return '';
  let html = `<button class="page-btn" type="button" onclick="setPage(${appState.pageNumber - 1})" ${appState.pageNumber <= 1 ? 'disabled' : ''}><i class="bi bi-chevron-left"></i></button>`;
  for (let i = 1; i <= pages; i++) {
    html += `<button class="page-btn ${i === appState.pageNumber ? 'active' : ''}" type="button" onclick="setPage(${i})">${i}</button>`;
  }
  html += `<button class="page-btn" type="button" onclick="setPage(${appState.pageNumber + 1})" ${appState.pageNumber >= pages ? 'disabled' : ''}><i class="bi bi-chevron-right"></i></button>`;
  return html;
}

function setPage(page) {
  appState.pageNumber = Math.max(1, page);
  renderTable(getConfig(), filteredRows());
}

function renderSide(config) {
  const side = byId('page-side-panels');
  side.innerHTML = config.side ? config.side() : '';
  byId('operational-layout').classList.toggle('full-width', !side.innerHTML.trim());
}

function renderSecondary(config) {
  byId('secondary-content').innerHTML = config.secondary ? config.secondary() : '';
}

function applyPageFilters() {
  document.querySelectorAll('.page-filter').forEach(field => appState.filters[field.dataset.key] = field.value);
  appState.pageNumber = 1;
  renderTable(getConfig(), filteredRows());
  toast('Filtros aplicados', 'success');
}

function clearPageFilters() {
  appState.filters = {};
  if (byId('page-search')) byId('page-search').value = '';
  renderCurrentPage();
}

function sideCards(items) {
  return `
    <div class="panel">
      <div class="panel-header"><div class="panel-title"><i class="bi bi-activity"></i> Acompanhamento</div></div>
      <div class="info-list">
        ${items.map(([title, text, icon]) => `
          <div class="info-item">
            <div class="info-icon"><i class="bi ${icon}"></i></div>
            <div><strong>${escapeHtml(title)}</strong><span>${escapeHtml(text)}</span></div>
          </div>
        `).join('')}
      </div>
    </div>
  `;
}

function unassignedPanel() {
  return `
    <div class="secondary-grid">
      <div class="panel">
        <div class="panel-header"><div class="panel-title"><i class="bi bi-person-x"></i> Sem técnico definido</div></div>
        <div class="info-list">
          ${agendaMock.filter(a => a.tecnico === 'Sem técnico').map(a => `
            <div class="info-item">
              <div class="info-icon"><i class="bi bi-clock"></i></div>
              <div><strong>${a.horario} — ${escapeHtml(a.cliente)}</strong><span>${escapeHtml(a.servico)} · ${escapeHtml(a.endereco)}</span></div>
            </div>
          `).join('')}
        </div>
      </div>
      <div class="panel">
        <div class="panel-header"><div class="panel-title"><i class="bi bi-calendar-week"></i> Visualizações</div></div>
        <div class="modal-body">
          <div class="deadline-list">
            <div class="deadline-item"><span class="deadline-time">Hoje</span><div><strong>4 atendimentos</strong><small>Lista operacional do dia</small></div>${badge('Aberta')}</div>
            <div class="deadline-item"><span class="deadline-time">Semana</span><div><strong>18 atendimentos</strong><small>Planejamento dos técnicos</small></div>${badge('Em andamento')}</div>
            <div class="deadline-item"><span class="deadline-time">Mês</span><div><strong>74 atendimentos</strong><small>Visão consolidada</small></div>${badge('Finalizada')}</div>
          </div>
        </div>
      </div>
    </div>
  `;
}

function reportsContent() {
  const bars = [
    ['OS por status', 'Em andamento', 65],
    ['Faturamento por mês', 'Maio', 82],
    ['Serviços mais realizados', 'Diagnóstico técnico', 74],
    ['Peças com maior saída', 'Sensor de temperatura', 58],
  ];
  return `
    <div class="secondary-grid">
      ${bars.map(([title, label, percent]) => `
        <div class="panel">
          <div class="panel-header"><div class="panel-title"><i class="bi bi-bar-chart"></i> ${escapeHtml(title)}</div></div>
          <div class="modal-body">
            <div class="deadline-item"><span class="deadline-time">${percent}%</span><div><strong>${escapeHtml(label)}</strong><small>Indicador visual simples</small></div></div>
            <div class="report-bar"><span style="width:${percent}%"></span></div>
          </div>
        </div>
      `).join('')}
    </div>
  `;
}

function settingsContent() {
  return `
    <div class="settings-grid">
      ${settingsSection('Dados da empresa', [
        ['Nome da empresa', 'K.Yamaguchi Refrigeração'],
        ['CNPJ', '12.345.678/0001-90'],
        ['Telefone', '(92) 3232-0000'],
        ['WhatsApp', '(92) 98888-0000'],
        ['E-mail', 'atendimento@kyamaguchi.com.br'],
        ['Endereço', 'Manaus - AM'],
      ])}
      ${settingsSection('Orçamentos', [
        ['Texto padrão', 'Orçamento válido conforme prazo informado.'],
        ['Validade padrão', '7 dias'],
        ['Garantia padrão', '90 dias para serviço executado'],
        ['Prefixo', 'ORC'],
      ])}
      ${settingsSection('Ordens de serviço', [
        ['Prefixo da OS', 'OS'],
        ['Status', 'Aberta, Em andamento, Aguardando peça, Finalizada'],
        ['Prioridades', 'Baixa, Média, Alta, Urgente'],
        ['Categorias', 'Instalação, Preventiva, Corretiva, Câmara fria'],
      ])}
      ${settingsSection('WhatsApp e aparência', [
        ['DDI padrão', '55'],
        ['Envio manual', 'Anexar PDF baixado na conversa'],
        ['Tema', 'Visual atual bloqueado para preservar layout'],
      ])}
    </div>
  `;
}

function settingsSection(title, fields) {
  return `
    <div class="panel">
      <div class="panel-header"><div class="panel-title"><i class="bi bi-sliders"></i> ${escapeHtml(title)}</div></div>
      <div class="modal-body">
        ${fields.map(([label, value]) => `
          <div class="form-group">
            <label class="form-label">${escapeHtml(label)}</label>
            <input class="form-control-os" value="${escapeHtml(value)}">
          </div>
        `).join('')}
      </div>
    </div>
  `;
}

function openEntityModal(entity, id = null) {
  const titleMap = {
    os: 'Nova Ordem de Serviço',
    cliente: 'Novo Cliente',
    tecnico: 'Novo Técnico',
    agenda: 'Novo Agendamento',
    peca: 'Nova Peça',
    servico: 'Novo Serviço',
    orcamento: 'Novo Orçamento',
    nota: 'Registrar Nota',
  };
  openAppModal(titleMap[entity] || 'Cadastro', entityForm(entity, id), modalFooter(entity));
  wireMasks();
  if (entity === 'orcamento') {
    appState.budgetItems = [
      { tipo: 'Serviço', descricao: 'Diagnóstico técnico', qtd: 1, unitario: 120, desconto: 0 },
      { tipo: 'Peça', descricao: 'Sensor de temperatura', qtd: 1, unitario: 75, desconto: 0 },
    ];
    renderBudgetItems();
  }
}

function entityForm(entity) {
  const commonOptions = {
    clientes: clientesMock.map(c => `<option>${escapeHtml(c.nome)}</option>`).join(''),
    tecnicos: tecnicosMock.map(t => `<option>${escapeHtml(t.nome)}</option>`).join(''),
    servicos: servicosMock.map(s => `<option>${escapeHtml(s.nome)}</option>`).join(''),
  };

  const forms = {
    os: `
      ${formSection('Cliente', `
        <div class="form-row"><div class="form-group"><label class="form-label">Cliente <span>*</span></label><select class="form-control-os" data-required="Cliente">${commonOptions.clientes}</select></div><div class="form-group"><label class="form-label">Telefone/WhatsApp <span>*</span></label><input class="form-control-os js-phone" data-required="Telefone" value="(92) 98844-1188"></div></div>
        <div class="form-group"><label class="form-label">Endereço</label><input class="form-control-os" value="Av. Djalma Batista, 1240"></div>
      `)}
      ${formSection('Equipamento', `
        <div class="form-row-3"><div class="form-group"><label class="form-label">Tipo</label><select class="form-control-os"><option>Ar-condicionado Split</option><option>Ar-condicionado Janela</option><option>Câmara fria</option><option>Freezer</option><option>Geladeira</option><option>Bebedouro</option><option>Balcão refrigerado</option><option>Outro</option></select></div><div class="form-group"><label class="form-label">Marca</label><input class="form-control-os" value="LG"></div><div class="form-group"><label class="form-label">Modelo</label><input class="form-control-os" value="Dual Inverter 18.000 BTUs"></div></div>
        <div class="form-row"><div class="form-group"><label class="form-label">Número de série</label><input class="form-control-os"></div><div class="form-group"><label class="form-label">Local de instalação</label><input class="form-control-os" value="Salão principal"></div></div>
      `)}
      ${formSection('Serviço', `
        <div class="form-row"><div class="form-group"><label class="form-label">Tipo de serviço <span>*</span></label><select class="form-control-os" data-required="Serviço">${commonOptions.servicos}</select></div><div class="form-group"><label class="form-label">Técnico</label><select class="form-control-os">${commonOptions.tecnicos}</select></div></div>
        <div class="form-group"><label class="form-label">Descrição do problema</label><textarea class="form-control-os">Equipamento não está refrigerando corretamente.</textarea></div>
        <div class="form-row-3"><div class="form-group"><label class="form-label">Prioridade</label><select class="form-control-os"><option>Média</option><option>Alta</option><option>Urgente</option></select></div><div class="form-group"><label class="form-label">Data agendada <span>*</span></label><input type="date" class="form-control-os" data-required="Data"></div><div class="form-group"><label class="form-label">Horário</label><input type="time" class="form-control-os" value="09:00"></div></div>
      `)}
      ${formSection('Valores', `
        <div class="form-row-3"><div class="form-group"><label class="form-label">Valor da visita</label><input class="form-control-os js-money" value="120,00"></div><div class="form-group"><label class="form-label">Valor do serviço</label><input class="form-control-os js-money" value="360,00"></div><div class="form-group"><label class="form-label">Desconto</label><input class="form-control-os js-money" value="0,00"></div></div>
      `)}
    `,
    cliente: `
      ${formSection('Dados principais', `<div class="form-row"><div class="form-group"><label class="form-label">Tipo</label><select class="form-control-os"><option>Pessoa Física</option><option>Pessoa Jurídica</option></select></div><div class="form-group"><label class="form-label">Nome/Razão Social <span>*</span></label><input class="form-control-os" data-required="Nome" value="Restaurante Sabor Norte"></div></div><div class="form-row"><div class="form-group"><label class="form-label">CPF/CNPJ</label><input class="form-control-os js-doc" value="12.845.778/0001-20"></div><div class="form-group"><label class="form-label">Telefone ou WhatsApp <span>*</span></label><input class="form-control-os js-phone" data-required="Telefone" value="(92) 98844-1188"></div></div><div class="form-group"><label class="form-label">E-mail</label><input class="form-control-os" value="financeiro@sabornorte.com.br"></div>`)}
      ${formSection('Endereço', `<div class="form-row-3"><div class="form-group"><label class="form-label">CEP</label><input class="form-control-os"></div><div class="form-group"><label class="form-label">Cidade</label><input class="form-control-os" value="Manaus"></div><div class="form-group"><label class="form-label">Estado</label><input class="form-control-os" value="AM"></div></div><div class="form-row"><div class="form-group"><label class="form-label">Endereço</label><input class="form-control-os" value="Av. Djalma Batista"></div><div class="form-group"><label class="form-label">Número</label><input class="form-control-os" value="1240"></div></div><div class="form-group"><label class="form-label">Observações</label><textarea class="form-control-os"></textarea></div>`)}
    `,
    tecnico: simpleForm(['Nome', 'Telefone', 'WhatsApp', 'E-mail', 'Especialidade', 'Status', 'Observações']),
    agenda: simpleForm(['Cliente', 'Técnico', 'Data', 'Horário', 'Tipo de serviço', 'Status', 'Prioridade', 'Endereço resumido']),
    peca: simpleForm(['Nome da peça', 'Código interno', 'Categoria', 'Quantidade atual', 'Estoque mínimo', 'Valor de custo', 'Valor de venda', 'Fornecedor', 'Observações']),
    servico: simpleForm(['Nome do serviço', 'Categoria', 'Descrição', 'Valor base', 'Tempo médio estimado', 'Status ativo/inativo']),
    nota: simpleForm(['Cliente', 'OS vinculada', 'Orçamento vinculado', 'Valor', 'Data de emissão', 'Número da nota', 'Observações']),
    orcamento: budgetForm(commonOptions),
  };
  return forms[entity] || '';
}

function formSection(title, content) {
  return `<section class="form-section"><div class="form-section-title"><i class="bi bi-chevron-right"></i>${escapeHtml(title)}</div>${content}</section>`;
}

function simpleForm(fields) {
  return `<div class="form-row">${fields.map(label => `<div class="form-group"><label class="form-label">${escapeHtml(label)}</label><input class="form-control-os ${/telefone|whatsapp/i.test(label) ? 'js-phone' : /valor|quantidade|estoque/i.test(label) ? 'js-money' : ''}" ${/nome|cliente|serviço/i.test(label) ? `data-required="${escapeHtml(label)}"` : ''}></div>`).join('')}</div>`;
}

function budgetForm(options) {
  return `
    ${formSection('Cliente', `<div class="form-row"><div class="form-group"><label class="form-label">Cliente <span>*</span></label><select class="form-control-os" id="budget-client" data-required="Cliente">${options.clientes}</select></div><div class="form-group"><label class="form-label">WhatsApp <span>*</span></label><input class="form-control-os js-phone" id="budget-whatsapp" data-required="WhatsApp" value="(92) 98844-1188"></div></div>`)}
    ${formSection('OS vinculada', `<div class="form-group"><label class="form-label">OS existente opcional</label><select class="form-control-os"><option>OS-00248</option><option>OS-00247</option><option>Orçamento independente</option></select></div>`)}
    ${formSection('Dados do orçamento', `<div class="form-row-3"><div class="form-group"><label class="form-label">Número</label><input class="form-control-os" id="budget-number" value="ORC-000124"></div><div class="form-group"><label class="form-label">Emissão</label><input type="date" class="form-control-os" value="2026-05-22"></div><div class="form-group"><label class="form-label">Validade</label><input type="date" class="form-control-os" id="budget-validity" value="2026-05-29"></div></div><div class="form-group"><label class="form-label">Responsável</label><select class="form-control-os">${options.tecnicos}</select></div>`)}
    ${formSection('Itens do orçamento', `<div class="os-table-wrap"><table class="budget-items-table"><thead><tr><th>Tipo</th><th>Descrição</th><th>Qtd</th><th>Unitário</th><th>Desc.</th><th>Subtotal</th><th></th></tr></thead><tbody id="budget-items-body"></tbody></table></div><button class="btn-filter btn-filter-ghost" type="button" onclick="addBudgetItem()"><i class="bi bi-plus-lg"></i> Adicionar item</button>`)}
    ${formSection('Totais e condições', `<div class="form-row"><div class="budget-total-box" id="budget-total-box"></div><div><div class="form-group"><label class="form-label">Forma de pagamento</label><input class="form-control-os" value="Pix, dinheiro ou cartão"></div><div class="form-group"><label class="form-label">Prazo de execução</label><input class="form-control-os" value="Até 3 dias úteis após aprovação"></div><div class="form-group"><label class="form-label">Garantia</label><input class="form-control-os" value="90 dias"></div></div></div><div class="form-group"><label class="form-label">Observações finais</label><textarea class="form-control-os">Valores sujeitos à aprovação após avaliação técnica final.</textarea></div>`)}
  `;
}

function modalFooter(entity) {
  if (entity === 'orcamento') {
    return `
      <div class="form-actions-split">
        <button class="btn-modal-cancel" type="button" onclick="closeAppModal()">Cancelar</button>
        <div class="d-flex gap-2 flex-wrap">
          <button class="btn-modal-secondary" type="button" onclick="saveVisual('${entity}')">Salvar rascunho</button>
          <button class="btn-modal-warning" type="button" onclick="previewBudgetPdf()">Visualizar</button>
          <button class="btn-modal-save" type="button" onclick="downloadBudgetPdf(getBudgetFromForm())"><i class="bi bi-filetype-pdf"></i> Gerar PDF</button>
          <button class="btn-modal-save" type="button" onclick="sendBudgetWhatsapp(getBudgetFromForm())"><i class="bi bi-whatsapp"></i> Enviar WhatsApp</button>
        </div>
      </div>
    `;
  }
  return `
    <button class="btn-modal-cancel" type="button" onclick="closeAppModal()">Cancelar</button>
    <button class="btn-modal-secondary" type="button" onclick="saveVisual('${entity}', true)">Salvar rascunho</button>
    <button class="btn-modal-save" type="button" onclick="saveVisual('${entity}')"><i class="bi bi-check2"></i> Salvar</button>
  `;
}

function openAppModal(title, body, footer) {
  byId('app-modal-root').innerHTML = `
    <div class="modal-overlay show" id="app-modal">
      <div class="modal-box" style="max-width:920px">
        <div class="modal-header">
          <div class="modal-title"><i class="bi bi-window"></i> ${escapeHtml(title)}</div>
          <button class="modal-close" type="button" onclick="closeAppModal()"><i class="bi bi-x-lg"></i></button>
        </div>
        <div class="modal-body">${body}</div>
        <div class="modal-footer">${footer}</div>
      </div>
    </div>
  `;
  byId('app-modal').addEventListener('click', event => {
    if (event.target.id === 'app-modal') closeAppModal();
  });
}

function closeAppModal() {
  byId('app-modal-root').innerHTML = '';
}

function validateModal() {
  const fields = [...document.querySelectorAll('#app-modal [data-required]')];
  const invalid = fields.find(field => !String(field.value || '').trim());
  if (invalid) {
    toast(`${invalid.dataset.required} é obrigatório`, 'error');
    invalid.focus();
    return false;
  }
  const negative = [...document.querySelectorAll('#app-modal .js-money')].find(field => parseMoney(field.value) < 0);
  if (negative) {
    toast('Valores não podem ser negativos', 'error');
    negative.focus();
    return false;
  }
  return true;
}

function saveVisual(entity, draft = false) {
  if (!draft && !validateModal()) return;
  closeAppModal();
  toast(draft ? 'Rascunho salvo visualmente' : 'Registro salvo visualmente', 'success');
}

function visualStatus(message) {
  toast(message, 'success');
}

function confirmVisualAction(message) {
  if (confirm(message)) toast('Ação visual aplicada', 'success');
}

function openDetail(type, id) {
  const detail = {
    os: osMock.find(r => r.id === id),
    cliente: clientesMock.find(r => r.id === id),
    peca: pecasMock.find(r => r.id === id),
    agenda: agendaMock.find(r => r.id === id),
    nota: notasMock.find(r => r.id === id),
  }[type];
  openAppModal('Detalhes', detailHtml(detail), `<button class="btn-modal-cancel" onclick="closeAppModal()">Fechar</button>`);
}

function detailHtml(item) {
  if (!item) return '<div class="empty-state"><i class="bi bi-inbox"></i><p>Registro não encontrado</p></div>';
  return `
    <div class="settings-grid">
      ${Object.entries(item).map(([key, value]) => `
        <div class="form-group"><label class="form-label">${escapeHtml(key)}</label><div class="form-control-os d-flex align-items-center">${escapeHtml(value)}</div></div>
      `).join('')}
    </div>
    <div class="form-section"><div class="form-section-title"><i class="bi bi-clock-history"></i> Histórico de movimentações</div><p class="section-note">22/05/2026 — Registro atualizado visualmente no front-end de demonstração.</p></div>
  `;
}

function openStockMovement(type, id) {
  const peca = pecasMock.find(p => p.id === id);
  openAppModal(`${type === 'entrada' ? 'Entrada' : 'Saída'} de estoque`, `
    <div class="form-section"><div class="form-section-title"><i class="bi bi-box-seam"></i>${escapeHtml(peca?.nome || 'Peça')}</div>
      <div class="form-row"><div class="form-group"><label class="form-label">Tipo</label><select class="form-control-os"><option>${type === 'entrada' ? 'Entrada' : 'Saída'}</option></select></div><div class="form-group"><label class="form-label">Quantidade <span>*</span></label><input class="form-control-os js-money" data-required="Quantidade" value="1"></div></div>
      <div class="form-group"><label class="form-label">Motivo</label><input class="form-control-os" value="${type === 'entrada' ? 'Compra de reposição' : 'Uso em ordem de serviço'}"></div>
      <div class="form-group"><label class="form-label">OS vinculada opcional</label><input class="form-control-os" value="OS-00248"></div>
    </div>
  `, `<button class="btn-modal-cancel" onclick="closeAppModal()">Cancelar</button><button class="btn-modal-save" onclick="saveVisual('movimentacao')">Salvar movimentação</button>`);
}

function addBudgetItem() {
  appState.budgetItems.push({ tipo: 'Serviço', descricao: '', qtd: 1, unitario: 0, desconto: 0 });
  renderBudgetItems();
}

function removeBudgetItem(index) {
  appState.budgetItems.splice(index, 1);
  renderBudgetItems();
}

function renderBudgetItems() {
  const body = byId('budget-items-body');
  if (!body) return;
  body.innerHTML = appState.budgetItems.map((item, index) => {
    const subtotal = item.qtd * item.unitario - item.desconto;
    return `
      <tr>
        <td><select class="form-control-os" onchange="updateBudgetItem(${index}, 'tipo', this.value)"><option ${item.tipo === 'Serviço' ? 'selected' : ''}>Serviço</option><option ${item.tipo === 'Peça' ? 'selected' : ''}>Peça</option></select></td>
        <td><input class="form-control-os" value="${escapeHtml(item.descricao)}" onchange="updateBudgetItem(${index}, 'descricao', this.value)"></td>
        <td><input class="form-control-os" type="number" min="0" value="${item.qtd}" onchange="updateBudgetItem(${index}, 'qtd', this.value)"></td>
        <td><input class="form-control-os" type="number" min="0" value="${item.unitario}" onchange="updateBudgetItem(${index}, 'unitario', this.value)"></td>
        <td><input class="form-control-os" type="number" min="0" value="${item.desconto}" onchange="updateBudgetItem(${index}, 'desconto', this.value)"></td>
        <td><strong>${currency(subtotal)}</strong></td>
        <td><button class="btn-action danger" type="button" onclick="removeBudgetItem(${index})"><i class="bi bi-trash"></i></button></td>
      </tr>
    `;
  }).join('');
  renderBudgetTotals();
}

function updateBudgetItem(index, key, value) {
  appState.budgetItems[index][key] = ['qtd', 'unitario', 'desconto'].includes(key) ? Math.max(0, Number(value || 0)) : value;
  renderBudgetItems();
}

function renderBudgetTotals() {
  const subtotal = appState.budgetItems.reduce((sum, item) => sum + item.qtd * item.unitario, 0);
  const discount = appState.budgetItems.reduce((sum, item) => sum + Number(item.desconto || 0), 0);
  const visit = 120;
  const total = subtotal - discount + visit;
  byId('budget-total-box').innerHTML = `
    <div class="total-line"><span>Subtotal</span><strong>${currency(subtotal)}</strong></div>
    <div class="total-line"><span>Descontos</span><strong>${currency(discount)}</strong></div>
    <div class="total-line"><span>Taxa de visita</span><strong>${currency(visit)}</strong></div>
    <div class="total-line final"><span>Total final</span><strong>${currency(total)}</strong></div>
  `;
}

function getBudgetFromForm() {
  const cliente = byId('budget-client')?.value || 'Restaurante Sabor Norte';
  return {
    numero: byId('budget-number')?.value || 'ORC-000124',
    cliente,
    whatsapp: byId('budget-whatsapp')?.value || '(92) 98844-1188',
    data: new Date().toLocaleDateString('pt-BR'),
    validade: formatDateBr(byId('budget-validity')?.value) || '29/05/2026',
    os: 'OS-00248',
    responsavel: 'K.Yamaguchi Refrigeração',
    endereco: clientesMock.find(c => c.nome === cliente)?.endereco || 'Manaus - AM',
    items: [...appState.budgetItems],
    pagamento: 'Pix, dinheiro ou cartão',
    prazo: 'Até 3 dias úteis após aprovação',
    garantia: '90 dias',
    observacoes: 'Valores sujeitos à aprovação após avaliação técnica final.',
  };
}

function getBudgetById(id) {
  const budget = orcamentosMock.find(item => item.id === id) || orcamentosMock[0];
  return {
    ...budget,
    endereco: clientesMock.find(c => c.nome === budget.cliente)?.endereco || 'Manaus - AM',
    items: [
      { tipo: 'Serviço', descricao: osMock.find(o => o.numero === budget.os)?.servico || 'Serviço técnico', qtd: 1, unitario: Math.max(120, budget.valor - 120), desconto: 0 },
      { tipo: 'Serviço', descricao: 'Taxa de visita técnica', qtd: 1, unitario: 120, desconto: 0 },
    ],
    pagamento: 'Pix, dinheiro ou cartão',
    prazo: 'Até 3 dias úteis após aprovação',
    garantia: '90 dias',
    observacoes: 'PDF gerado no front-end para demonstração.',
  };
}

function generateBudgetPdf(budgetData = getBudgetById(123)) {
  const total = budgetData.items.reduce((sum, item) => sum + item.qtd * item.unitario - item.desconto, 0);
  const html = `
    <div class="budget-pdf">
      <div class="budget-pdf-header">
        <div class="budget-pdf-brand"><div class="budget-pdf-logo"><i class="bi bi-tools"></i></div><div><h1>K.Yamaguchi Refrigeração</h1><p>Serviços técnicos em refrigeração e climatização</p></div></div>
        <div><h2>Orçamento</h2><p><strong>${escapeHtml(budgetData.numero)}</strong></p><p>Emissão: ${escapeHtml(budgetData.data)}</p><p>Validade: ${escapeHtml(budgetData.validade)}</p></div>
      </div>
      <div class="budget-pdf-grid">
        <div class="budget-pdf-box"><h3>Cliente</h3><p>${escapeHtml(budgetData.cliente)}</p><p>${escapeHtml(budgetData.whatsapp)}</p><p>${escapeHtml(budgetData.endereco)}</p></div>
        <div class="budget-pdf-box"><h3>Dados do serviço</h3><p>OS vinculada: ${escapeHtml(budgetData.os || 'Independente')}</p><p>Responsável: ${escapeHtml(budgetData.responsavel || budgetData.tecnico || 'Equipe técnica')}</p></div>
      </div>
      <table class="budget-pdf-table"><thead><tr><th>Tipo</th><th>Descrição</th><th>Qtd</th><th>Unitário</th><th>Desconto</th><th>Subtotal</th></tr></thead><tbody>${budgetData.items.map(item => `<tr><td>${escapeHtml(item.tipo)}</td><td>${escapeHtml(item.descricao)}</td><td>${item.qtd}</td><td>${currency(item.unitario)}</td><td>${currency(item.desconto)}</td><td>${currency(item.qtd * item.unitario - item.desconto)}</td></tr>`).join('')}</tbody></table>
      <div class="budget-pdf-grid">
        <div class="budget-pdf-box"><h3>Condições</h3><p>Pagamento: ${escapeHtml(budgetData.pagamento)}</p><p>Prazo: ${escapeHtml(budgetData.prazo)}</p><p>Garantia: ${escapeHtml(budgetData.garantia)}</p></div>
        <div class="budget-pdf-box"><h3>Total</h3><h1>${currency(total)}</h1><p>${escapeHtml(budgetData.observacoes)}</p></div>
      </div>
      <div class="budget-pdf-footer">K.Yamaguchi Refrigeração · Orçamento gerado automaticamente pelo sistema OSmais. Assinatura do cliente: ______________________________</div>
    </div>
  `;
  byId('pdf-workspace').innerHTML = html;
  return byId('pdf-workspace').firstElementChild;
}

function budgetFilename(budgetData) {
  const client = normalizeText(budgetData.cliente).replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
  return `orcamento-k-yamaguchi-${budgetData.numero.replace(/\D/g, '')}-${client}.pdf`;
}

async function downloadBudgetPdf(budgetData = getBudgetFromForm()) {
  const node = generateBudgetPdf(budgetData);
  if (!window.html2pdf) {
    downloadSimpleBudgetPdf(budgetData);
    toast('PDF gerado com fallback nativo', 'success');
    return true;
  }
  await window.html2pdf().set({
    margin: 0,
    filename: budgetFilename(budgetData),
    image: { type: 'jpeg', quality: 0.98 },
    html2canvas: { scale: 2 },
    jsPDF: { unit: 'px', format: [794, 1123], orientation: 'portrait' },
  }).from(node).save();
  toast('PDF gerado com sucesso', 'success');
  return true;
}

function downloadSimpleBudgetPdf(budgetData) {
  const total = budgetData.items.reduce((sum, item) => sum + item.qtd * item.unitario - item.desconto, 0);
  const lines = [
    'K.Yamaguchi Refrigeração',
    'Orçamento',
    `Número: ${budgetData.numero}`,
    `Emissão: ${budgetData.data}`,
    `Validade: ${budgetData.validade}`,
    '',
    `Cliente: ${budgetData.cliente}`,
    `WhatsApp: ${budgetData.whatsapp}`,
    `Endereço: ${budgetData.endereco}`,
    `OS vinculada: ${budgetData.os || 'Independente'}`,
    '',
    'Itens:',
    ...budgetData.items.map(item => `${item.tipo} | ${item.descricao} | Qtd ${item.qtd} | Unit. ${currency(item.unitario)} | Desc. ${currency(item.desconto)} | Subtotal ${currency(item.qtd * item.unitario - item.desconto)}`),
    '',
    `Total final: ${currency(total)}`,
    '',
    `Pagamento: ${budgetData.pagamento}`,
    `Prazo: ${budgetData.prazo}`,
    `Garantia: ${budgetData.garantia}`,
    `Observações: ${budgetData.observacoes}`,
    '',
    'Assinatura do cliente: ______________________________',
  ];
  const pdf = createTextPdf(lines);
  const blob = new Blob([pdf], { type: 'application/pdf' });
  const link = document.createElement('a');
  link.href = URL.createObjectURL(blob);
  link.download = budgetFilename(budgetData);
  document.body.appendChild(link);
  link.click();
  URL.revokeObjectURL(link.href);
  link.remove();
}

function createTextPdf(lines) {
  const escapePdf = value => String(value).replace(/[\\()]/g, '\\$&');
  const content = ['BT', '/F1 11 Tf', '50 790 Td'];
  lines.forEach((line, index) => {
    if (index > 0) content.push('0 -18 Td');
    content.push(`(${escapePdf(line)}) Tj`);
  });
  content.push('ET');
  const stream = content.join('\n');
  const objects = [
    '<< /Type /Catalog /Pages 2 0 R >>',
    '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
    '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>',
    '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
    `<< /Length ${stream.length} >>\nstream\n${stream}\nendstream`,
  ];
  let pdf = '%PDF-1.4\n';
  const offsets = [0];
  objects.forEach((obj, index) => {
    offsets.push(pdf.length);
    pdf += `${index + 1} 0 obj\n${obj}\nendobj\n`;
  });
  const xref = pdf.length;
  pdf += `xref\n0 ${objects.length + 1}\n0000000000 65535 f \n`;
  offsets.slice(1).forEach(offset => {
    pdf += `${String(offset).padStart(10, '0')} 00000 n \n`;
  });
  pdf += `trailer\n<< /Size ${objects.length + 1} /Root 1 0 R >>\nstartxref\n${xref}\n%%EOF`;
  return pdf;
}

function previewBudgetPdf(budgetId = null) {
  const budget = budgetId ? getBudgetById(budgetId) : getBudgetFromForm();
  openAppModal('Prévia do orçamento', `<div class="os-table-wrap">${generateBudgetPdf(budget).outerHTML}</div>`, `<button class="btn-modal-cancel" onclick="closeAppModal()">Fechar</button><button class="btn-modal-save" onclick="downloadBudgetPdf(getBudgetById(${budgetId || 123}))">Baixar PDF</button>`);
}

async function sendBudgetWhatsapp(budgetData = getBudgetFromForm()) {
  if (!validateWhatsapp(budgetData.whatsapp)) {
    toast('WhatsApp do cliente inválido ou não cadastrado', 'error');
    return;
  }
  await downloadBudgetPdf(budgetData);
  const phone = cleanWhatsapp(budgetData.whatsapp);
  const total = budgetData.items.reduce((sum, item) => sum + item.qtd * item.unitario - item.desconto, 0);
  const message = `Olá, ${budgetData.cliente}! Tudo bem?\n\nSegue o orçamento nº ${budgetData.numero} referente ao serviço de refrigeração solicitado.\n\nValor total: ${currency(total)}\nValidade: ${budgetData.validade}\n\nO PDF do orçamento foi gerado pelo sistema. Por favor, confira as informações e nos avise se deseja aprovar.\n\nAtenciosamente,\nK.Yamaguchi Refrigeração`;
  const url = `https://wa.me/${phone}?text=${encodeURIComponent(message)}`;
  openAppModal('PDF gerado com sucesso', `
    <p class="section-note">O WhatsApp será aberto com a mensagem pronta. Por limitação do navegador/WhatsApp Web, anexe manualmente o PDF baixado na conversa para concluir o envio.</p>
    <div class="form-section"><div class="form-section-title"><i class="bi bi-filetype-pdf"></i>${escapeHtml(budgetFilename(budgetData))}</div><p class="section-note">Arquivo preparado para envio manual.</p></div>
  `, `<button class="btn-modal-cancel" onclick="closeAppModal()">Fechar</button><button class="btn-modal-save" onclick="window.open('${url}', '_blank', 'noopener'); closeAppModal();"><i class="bi bi-whatsapp"></i> Abrir WhatsApp</button>`);
  window.open(url, '_blank', 'noopener');
}

function cleanWhatsapp(value) {
  let phone = String(value || '').replace(/\D/g, '');
  if (!phone.startsWith('55')) phone = `55${phone}`;
  return phone;
}

function validateWhatsapp(value) {
  const phone = cleanWhatsapp(value);
  return /^55\d{10,11}$/.test(phone);
}

function parseMoney(value) {
  return Number(String(value || '0').replace(/\./g, '').replace(',', '.').replace(/[^\d.-]/g, '')) || 0;
}

function formatDateBr(value) {
  if (!value) return '';
  const [year, month, day] = value.split('-');
  return `${day}/${month}/${year}`;
}

function wireMasks() {
  document.querySelectorAll('.js-phone').forEach(input => input.addEventListener('input', () => {
    let value = input.value.replace(/\D/g, '').slice(0, 11);
    value = value.replace(/^(\d{2})(\d)/, '($1) $2').replace(/(\d{5})(\d)/, '$1-$2');
    input.value = value;
  }));
  document.querySelectorAll('.js-doc').forEach(input => input.addEventListener('input', () => {
    const digits = input.value.replace(/\D/g, '').slice(0, 14);
    input.value = digits.length > 11
      ? digits.replace(/^(\d{2})(\d{3})(\d{3})(\d{4})(\d{0,2}).*/, '$1.$2.$3/$4-$5')
      : digits.replace(/^(\d{3})(\d{3})(\d{3})(\d{0,2}).*/, '$1.$2.$3-$4');
  }));
}

document.addEventListener('DOMContentLoaded', renderCurrentPage);
