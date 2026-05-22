/* =========================================================
   CONFIG
   ========================================================= */
const API = 'dashboard.php';
const DEMO_MODE = true;
const DEMO_PAGE_SIZE = 8;
let currentPage = 1;
let chartStatus  = null;
let chartMonthly = null;

const demoClientes = [
  { id: 1, nome: 'Restaurante Sabor Norte' },
  { id: 2, nome: 'Mercado Ponto Frio' },
  { id: 3, nome: 'João Almeida' },
  { id: 4, nome: 'Clínica Bem Estar' },
  { id: 5, nome: 'Padaria Santa Luzia' },
  { id: 6, nome: 'Condomínio Jardim Europa' },
];

const demoTecnicos = [
  { id: 1, nome: 'Carlos Ferreira' },
  { id: 2, nome: 'Ana Martins' },
  { id: 3, nome: 'Pedro Alves' },
  { id: 4, nome: 'Lucas Ferreira' },
];

const demoSeedRows = [
  { titulo:'Manutenção preventiva em câmara fria', status:'em_andamento', prioridade:'alta', cliente:'Restaurante Sabor Norte', tecnico:'Carlos Ferreira', valor_final:1320 },
  { titulo:'Troca de compressor freezer vertical', status:'aguardando_peca', prioridade:'urgente', cliente:'Mercado Ponto Frio', tecnico:'Ana Martins', valor_final:2380 },
  { titulo:'Limpeza de ar-condicionado split', status:'aguardando_aprovacao', prioridade:'media', cliente:'Clínica Bem Estar', tecnico:'Lucas Ferreira', valor_final:360 },
  { titulo:'Carga de gás balcão refrigerado', status:'finalizada', prioridade:'baixa', cliente:'Padaria Santa Luzia', tecnico:'Pedro Alves', valor_final:520 },
  { titulo:'Diagnóstico técnico ar-condicionado janela', status:'aberta', prioridade:'media', cliente:'João Almeida', tecnico:null, valor_final:120 },
  { titulo:'Instalação de split 24.000 BTUs', status:'em_andamento', prioridade:'alta', cliente:'Condomínio Jardim Europa', tecnico:'Lucas Ferreira', valor_final:1650 },
  { titulo:'Troca de sensor de temperatura', status:'aberta', prioridade:'baixa', cliente:'Mercado Ponto Frio', tecnico:null, valor_final:0 },
  { titulo:'Manutenção corretiva em bebedouro', status:'cancelada', prioridade:'media', cliente:'Clínica Bem Estar', tecnico:'Ana Martins', valor_final:0 },
];

const demoOsRows = Array.from({ length: 248 }, (_, index) => {
  const base = demoSeedRows[index % demoSeedRows.length];
  const date = new Date('2026-05-18T00:00:00');
  date.setDate(date.getDate() - index);

  return {
    ...base,
    id: index + 1,
    numero: `OS-${String(248 - index).padStart(5, '0')}`,
    data_abertura: date.toISOString().slice(0, 10),
    valor_final: base.valor_final ? base.valor_final + ((index % 6) * 35) : 0,
  };
});

function delay(ms = 180) {
  return new Promise(resolve => setTimeout(resolve, ms));
}

function normalizeText(value) {
  return String(value ?? '')
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLowerCase();
}

async function mockApi(params, body = {}) {
  await delay();

  switch (params.action) {
    case 'metrics':
      return { abertas: 34, andando: 18, aguardandoPeca: 12, finalizadas: 92, orcPendentes: 17, estoqueBaixo: 6, faturado: 48720 };
    case 'list_os':
      return listDemoOS(params);
    case 'chart_status':
      return [
        { status: 'aberta', qty: 34 },
        { status: 'em_andamento', qty: 18 },
        { status: 'aguardando_peca', qty: 12 },
        { status: 'finalizada', qty: 92 },
        { status: 'cancelada', qty: 8 },
      ];
    case 'chart_monthly':
      return [
        { mes: 'Dez', abertas: 28, concluidas: 20 },
        { mes: 'Jan', abertas: 35, concluidas: 28 },
        { mes: 'Fev', abertas: 22, concluidas: 18 },
        { mes: 'Mar', abertas: 40, concluidas: 35 },
        { mes: 'Abr', abertas: 31, concluidas: 26 },
        { mes: 'Mai', abertas: 34, concluidas: 29 },
      ];
    case 'recent':
      return demoOsRows.slice(0, 5);
    case 'clientes':
      return demoClientes;
    case 'tecnicos':
      return demoTecnicos;
    case 'update_status':
      return { ok: true, id: body.id, status: body.status };
    case 'save_os':
      return { ok: true, numero: 'OS-00249' };
    default:
      throw new Error(`Ação de demonstração não implementada: ${params.action}`);
  }
}

function listDemoOS(params) {
  const page = Math.max(parseInt(params.page || '1', 10) || 1, 1);
  const search = normalizeText(params.search);
  let rows = [...demoOsRows];

  if (search) {
    rows = rows.filter(row => [
      row.numero,
      row.titulo,
      row.cliente,
      row.tecnico,
    ].some(value => normalizeText(value).includes(search)));
  }

  if (params.status) {
    rows = rows.filter(row => row.status === params.status);
  }

  if (params.prioridade) {
    rows = rows.filter(row => row.prioridade === params.prioridade);
  }

  const total = rows.length;
  const pages = Math.max(1, Math.ceil(total / DEMO_PAGE_SIZE));
  const safePage = Math.min(page, pages);
  const start = (safePage - 1) * DEMO_PAGE_SIZE;

  return {
    rows: rows.slice(start, start + DEMO_PAGE_SIZE),
    total,
    pages,
    page: safePage,
  };
}

/* =========================================================
   AJAX HELPER
   ========================================================= */
async function ajax(params, method = 'GET', body = null) {
  if (DEMO_MODE) {
    return mockApi(params, body);
  }

  const qs  = new URLSearchParams(params).toString();
  const url = `${API}?${qs}`;
  const opts = {
    method,
    headers: { 'X-Requested-With': 'XMLHttpRequest' }
  };
  if (body) {
    opts.body = new URLSearchParams(body);
    opts.headers['Content-Type'] = 'application/x-www-form-urlencoded';
  }
  const res  = await fetch(url, opts);
  const data = await res.json();
  if (data.error) throw new Error(data.error);
  return data;
}

/* =========================================================
   MÉTRICAS
   ========================================================= */
async function loadMetrics() {
  try {
    const d = await ajax({ action: 'metrics' });

    const cards = [
      {
        label: 'OS abertas',
        value: d.abertas,
        icon: 'bi-folder2-open',
        iconBg: '#FFFBEB', iconColor: '#D97706',
        accent: '#F59E0B',
        change: d.abertas > 5 ? 'Atenção' : 'Normal', changeType: 'neutral',
        period: 'aguardando atendimento'
      },
      {
        label: 'Em andamento',
        value: d.andando,
        icon: 'bi-arrow-repeat',
        iconBg: '#F5F3FF', iconColor: '#7C3AED',
        accent: '#8B5CF6',
        change: d.urgente + ' urgente(s)', changeType: d.urgente > 0 ? 'down' : 'neutral',
        period: 'em execução agora'
      },
      {
        label: 'Aguardando peça',
        value: d.aguardandoPeca,
        icon: 'bi-box-seam',
        iconBg: '#F5F3FF', iconColor: '#7C3AED',
        accent: '#8B5CF6',
        change: 'estoque', changeType: 'down',
        period: 'requer compra'
      },
      {
        label: 'Finalizadas no mês',
        value: d.finalizadas,
        icon: 'bi-check-circle',
        iconBg: '#DCFCE7', iconColor: '#16A34A',
        accent: '#22C55E',
        change: '+8%', changeType: 'up',
        period: 'vs. mês anterior'
      },
      {
        label: 'Orçamentos pendentes',
        value: d.orcPendentes,
        icon: 'bi-file-earmark-text',
        iconBg: '#EFF6FF', iconColor: '#2563EB',
        accent: '#3B82F6',
        change: 'aprovação', changeType: 'neutral',
        period: 'aguardando cliente'
      },
      {
        label: 'Estoque baixo',
        value: d.estoqueBaixo,
        icon: 'bi-currency-dollar',
        iconBg: '#FEF2F2', iconColor: '#DC2626',
        accent: '#EF4444',
        change: 'crítico', changeType: 'down',
        period: 'peças abaixo do mínimo'
      }
    ];

    const grid = document.getElementById('metrics-grid');
    grid.innerHTML = cards.map(c => `
      <div class="metric-card" style="--card-accent:${c.accent}">
        <div class="metric-head">
          <div class="metric-label">${c.label}</div>
          <div class="metric-icon-wrap" style="--icon-bg:${c.iconBg};--icon-color:${c.iconColor}">
            <i class="bi ${c.icon}"></i>
          </div>
        </div>
        <div class="metric-value">${c.value}</div>
        <div class="metric-footer">
          <span class="metric-change change-${c.changeType}">
            <i class="bi ${c.changeType === 'up' ? 'bi-arrow-up-short' : c.changeType === 'down' ? 'bi-arrow-down-short' : 'bi-dash'}"></i>
            ${c.change}
          </span>
          <span class="metric-period">${c.period}</span>
        </div>
      </div>
    `).join('');

    document.getElementById('sb-total').textContent = '248';

  } catch (e) {
    console.warn('Métricas:', e.message);
    renderMetricsMock();
  }
}

function renderMetricsMock() {
  const cards = [
    { label:'OS abertas', value:'34', icon:'bi-folder2-open', iconBg:'#FFFBEB', iconColor:'#D97706', accent:'#F59E0B', change:'Normal', changeType:'neutral', period:'aguardando atendimento' },
    { label:'Em andamento', value:'18', icon:'bi-arrow-repeat', iconBg:'#F5F3FF', iconColor:'#7C3AED', accent:'#8B5CF6', change:'2 urgentes', changeType:'down', period:'em execução agora' },
    { label:'Aguardando peça', value:'12', icon:'bi-box-seam', iconBg:'#F5F3FF', iconColor:'#7C3AED', accent:'#8B5CF6', change:'estoque', changeType:'down', period:'requer compra' },
    { label:'Finalizadas no mês', value:'92', icon:'bi-check-circle', iconBg:'#DCFCE7', iconColor:'#16A34A', accent:'#22C55E', change:'+8%', changeType:'up', period:'vs. mês anterior' },
    { label:'Orçamentos pendentes', value:'17', icon:'bi-file-earmark-text', iconBg:'#EFF6FF', iconColor:'#2563EB', accent:'#3B82F6', change:'aprovação', changeType:'neutral', period:'aguardando cliente' },
    { label:'Estoque baixo', value:'6', icon:'bi-exclamation-triangle', iconBg:'#FEF2F2', iconColor:'#DC2626', accent:'#EF4444', change:'crítico', changeType:'down', period:'peças abaixo do mínimo' },
  ];
  document.getElementById('metrics-grid').innerHTML = cards.map(c => `
    <div class="metric-card" style="--card-accent:${c.accent}">
      <div class="metric-head">
        <div class="metric-label">${c.label}</div>
        <div class="metric-icon-wrap" style="--icon-bg:${c.iconBg};--icon-color:${c.iconColor}">
          <i class="bi ${c.icon}"></i>
        </div>
      </div>
      <div class="metric-value">${c.value}</div>
      <div class="metric-footer">
        <span class="metric-change change-${c.changeType}">
          <i class="bi ${c.changeType==='up'?'bi-arrow-up-short':c.changeType==='down'?'bi-arrow-down-short':'bi-dash'}"></i>
          ${c.change}
        </span>
        <span class="metric-period">${c.period}</span>
      </div>
    </div>
  `).join('');
  document.getElementById('sb-total').textContent = '248';
}

/* =========================================================
   TABELA OS
   ========================================================= */
const statusMap = {
  aberta:        { label:'Aberta',        icon:'bi-circle', cls:'s-aberta' },
  em_andamento:  { label:'Em andamento',  icon:'bi-arrow-repeat', cls:'s-em_andamento' },
  aguardando_peca: { label:'Aguardando peça', icon:'bi-box-seam', cls:'s-aguardando' },
  aguardando_aprovacao: { label:'Aguardando aprovação', icon:'bi-hourglass-split', cls:'s-aguardando' },
  finalizada:    { label:'Finalizada',    icon:'bi-check-circle', cls:'s-concluida' },
  cancelada:     { label:'Cancelada',     icon:'bi-x-circle', cls:'s-cancelada' },
};
const priorMap = {
  baixa:   { label:'Baixa',   icon:'bi-arrow-down', cls:'p-baixa' },
  media:   { label:'Média',   icon:'bi-dash', cls:'p-media' },
  alta:    { label:'Alta',    icon:'bi-arrow-up', cls:'p-alta' },
  urgente: { label:'Urgente', icon:'bi-exclamation-triangle', cls:'p-urgente' },
};
const dotColor = { aberta:'#3B82F6', em_andamento:'#F59E0B', aguardando_peca:'#8B5CF6', aguardando_aprovacao:'#8B5CF6', finalizada:'#22C55E', cancelada:'#EF4444' };

async function loadOS(page = 1) {
  currentPage = page;
  const tbody = document.getElementById('os-tbody');
  tbody.innerHTML = `<tr><td colspan="8"><div class="skeleton sk-row"></div><div class="skeleton sk-row"></div><div class="skeleton sk-row"></div></td></tr>`;

  try {
    const params = {
      action: 'list_os', page,
      search:     document.getElementById('search-input').value,
      status:     document.getElementById('filter-status').value,
      prioridade: document.getElementById('filter-prior').value,
    };
    const d = await ajax(params);
    renderTable(d);
  } catch (e) {
    renderTableMock();
  }
}

function renderTable(d) {
  const tbody = document.getElementById('os-tbody');
  document.getElementById('os-total-label').textContent = d.total + ' registros';
  document.getElementById('pagination-info').textContent =
    `Exibindo ${((d.page-1)*10)+1}–${Math.min(d.page*10, d.total)} de ${d.total}`;

  if (!d.rows.length) {
    tbody.innerHTML = `<tr><td colspan="8"><div class="empty-state"><i class="bi bi-inbox"></i><p>Nenhuma OS encontrada</p></div></td></tr>`;
    renderPagination(d);
    return;
  }

  tbody.innerHTML = d.rows.map(r => {
    const s = statusMap[r.status]  || { label: r.status, icon:'bi-circle', cls:'s-aberta' };
    const p = priorMap[r.prioridade] || { label: r.prioridade, icon:'bi-dash', cls:'p-media' };
    const dt = r.data_abertura ? new Date(r.data_abertura).toLocaleDateString('pt-BR') : '—';
    const val = r.valor_final > 0
      ? 'R$ ' + parseFloat(r.valor_final).toLocaleString('pt-BR', {minimumFractionDigits:2})
      : '—';
    return `
    <tr>
      <td><span class="os-num">${r.numero}</span></td>
      <td>
        <div class="os-title">${r.titulo}</div>
        <div class="os-client"><i class="bi bi-person" style="font-size:11px;margin-right:3px"></i>${r.cliente||'—'}</div>
      </td>
      <td>
        <div class="status-dropdown">
          <span class="badge-status ${s.cls}" style="cursor:pointer" onclick="toggleStatusMenu(this,${r.id})">
            <i class="bi ${s.icon}"></i> ${s.label}
          </span>
          <div class="status-menu" id="smenu-${r.id}">
            ${Object.entries(statusMap).map(([k,v])=>`
              <div class="status-option" onclick="updateStatus(${r.id},'${k}',this)">
                <span class="badge-status ${v.cls}" style="font-size:11px;padding:2px 7px"><i class="bi ${v.icon}"></i> ${v.label}</span>
              </div>
            `).join('')}
          </div>
        </div>
      </td>
      <td><span class="badge-prior ${p.cls}"><i class="bi ${p.icon}"></i> ${p.label}</span></td>
      <td style="color:var(--slate-600);font-size:13px">${r.tecnico||'<span style="color:var(--slate-400)">—</span>'}</td>
      <td><span class="os-date">${dt}</span></td>
      <td><span class="os-value">${val}</span></td>
      <td>
        <div class="actions-cell" style="justify-content:center">
          <div class="btn-action" title="Ver detalhes"><i class="bi bi-eye"></i></div>
          <div class="btn-action" title="Editar"><i class="bi bi-pencil"></i></div>
          <div class="btn-action" title="Imprimir"><i class="bi bi-printer"></i></div>
          <div class="btn-action danger" title="Excluir"><i class="bi bi-trash"></i></div>
        </div>
      </td>
    </tr>`;
  }).join('');

  renderPagination(d);
}

function renderPagination(d) {
  const ctrl = document.getElementById('pagination-controls');
  if (d.pages <= 1) { ctrl.innerHTML = ''; return; }

  let html = `<button class="page-btn" onclick="loadOS(${d.page-1})" ${d.page<=1?'disabled':''}><i class="bi bi-chevron-left"></i></button>`;
  for (let i = 1; i <= d.pages; i++) {
    if (i === 1 || i === d.pages || (i >= d.page-1 && i <= d.page+1)) {
      html += `<button class="page-btn ${i===d.page?'active':''}" onclick="loadOS(${i})">${i}</button>`;
    } else if (i === d.page-2 || i === d.page+2) {
      html += `<button class="page-btn" disabled style="border:none;background:none;cursor:default">…</button>`;
    }
  }
  html += `<button class="page-btn" onclick="loadOS(${d.page+1})" ${d.page>=d.pages?'disabled':''}><i class="bi bi-chevron-right"></i></button>`;
  ctrl.innerHTML = html;
}

function renderTableMock() {
  const mock = [
    { id:1, numero:'OS-00248', titulo:'Manutenção preventiva em câmara fria', status:'em_andamento', prioridade:'alta', cliente:'Restaurante Sabor Norte', tecnico:'Carlos Ferreira', data_abertura:'2026-05-18', valor_final:1320 },
    { id:2, numero:'OS-00247', titulo:'Troca de compressor freezer vertical', status:'aguardando_peca', prioridade:'urgente', cliente:'Mercado Ponto Frio', tecnico:'Ana Martins', data_abertura:'2026-05-17', valor_final:2380 },
    { id:3, numero:'OS-00246', titulo:'Limpeza de ar-condicionado split', status:'aguardando_aprovacao', prioridade:'media', cliente:'Clínica Bem Estar', tecnico:'Lucas Ferreira', data_abertura:'2026-05-16', valor_final:360 },
    { id:4, numero:'OS-00245', titulo:'Carga de gás balcão refrigerado', status:'finalizada', prioridade:'baixa', cliente:'Padaria Santa Luzia', tecnico:'Pedro Alves', data_abertura:'2026-05-15', valor_final:520 },
    { id:5, numero:'OS-00244', titulo:'Diagnóstico técnico ar-condicionado janela', status:'aberta', prioridade:'media', cliente:'João Almeida', tecnico:null, data_abertura:'2026-05-14', valor_final:120 },
    { id:6, numero:'OS-00243', titulo:'Instalação de split 24.000 BTUs', status:'em_andamento', prioridade:'alta', cliente:'Condomínio Jardim Europa', tecnico:'Lucas Ferreira', data_abertura:'2026-05-13', valor_final:1650 },
    { id:7, numero:'OS-00242', titulo:'Troca de sensor de temperatura', status:'aberta', prioridade:'baixa', cliente:'Mercado Ponto Frio', tecnico:null, data_abertura:'2026-05-12', valor_final:0 },
    { id:8, numero:'OS-00241', titulo:'Manutenção corretiva em bebedouro', status:'cancelada', prioridade:'media', cliente:'Clínica Bem Estar', tecnico:'Ana Martins', data_abertura:'2026-05-11', valor_final:0 },
  ];
  document.getElementById('os-total-label').textContent = '248 registros';
  document.getElementById('pagination-info').textContent = 'Exibindo 1–8 de 248';
  renderTable({ rows: mock, total: 248, pages: 25, page: 1 });
}

/* ── Status dropdown ─────────────────────────────────── */
function toggleStatusMenu(el, id) {
  document.querySelectorAll('.status-menu.show').forEach(m => m.classList.remove('show'));
  const menu = document.getElementById('smenu-'+id);
  menu.classList.toggle('show');
  setTimeout(() => {
    const close = (e) => {
      if (!menu.contains(e.target) && e.target !== el) {
        menu.classList.remove('show');
        document.removeEventListener('click', close);
      }
    };
    document.addEventListener('click', close);
  }, 10);
}

async function updateStatus(id, status, el) {
  document.querySelectorAll('.status-menu.show').forEach(m => m.classList.remove('show'));
  try {
    await ajax({ action: 'update_status' }, 'POST', { id, status });
    toast('Status atualizado com sucesso', 'success');
    loadOS(currentPage);
    loadMetrics();
  } catch(e) {
    // mock: apenas atualiza visualmente
    loadOS(currentPage);
    toast('Status atualizado (modo demo)', 'success');
  }
}

/* =========================================================
   GRÁFICOS
   ========================================================= */
async function loadCharts() {
  await Promise.all([loadChartStatus(), loadChartMonthly()]);
}

async function loadChartStatus() {
  const canvas = document.getElementById('chart-status');
  let labels = ['Aberta','Em andamento','Aguardando peça','Finalizada','Cancelada'];
  let data   = [34, 18, 12, 92, 8];

  try {
    const rows = await ajax({ action: 'chart_status' });
    labels = rows.map(r => statusMap[r.status]?.label || r.status);
    data   = rows.map(r => parseInt(r.qty));
  } catch(e) {}

  if (chartStatus) chartStatus.destroy();
  chartStatus = new Chart(canvas, {
    type: 'doughnut',
    data: {
      labels,
      datasets: [{
        data,
        backgroundColor: ['#3B82F6','#F59E0B','#8B5CF6','#22C55E','#EF4444'],
        borderColor: '#fff',
        borderWidth: 3,
        hoverOffset: 6,
      }]
    },
    options: {
      responsive: true,
      cutout: '68%',
      plugins: {
        legend: {
          position: 'bottom',
          labels: {
            font: { family: 'DM Sans', size: 12 },
            color: '#64748B',
            padding: 12,
            usePointStyle: true,
            pointStyleWidth: 8,
          }
        },
        tooltip: {
          backgroundColor: '#0F172A',
          titleFont: { family: 'DM Sans', size: 13, weight: '600' },
          bodyFont:  { family: 'DM Sans', size: 12 },
          cornerRadius: 10,
          padding: 10,
        }
      }
    }
  });
}

async function loadChartMonthly() {
  const canvas = document.getElementById('chart-monthly');
  let labels   = ['Dez','Jan','Fev','Mar','Abr','Mai'];
  let abertas  = [28,35,22,40,31,34];
  let concl    = [20,28,18,35,26,29];

  try {
    const rows = await ajax({ action: 'chart_monthly' });
    labels  = rows.map(r => r.mes);
    abertas = rows.map(r => parseInt(r.abertas));
    concl   = rows.map(r => parseInt(r.concluidas));
  } catch(e) {}

  if (chartMonthly) chartMonthly.destroy();
  chartMonthly = new Chart(canvas, {
    type: 'bar',
    data: {
      labels,
      datasets: [
        {
          label: 'Abertas',
          data: abertas,
          backgroundColor: '#BFDBFE',
          borderRadius: 6,
          borderSkipped: false,
        },
        {
          label: 'Concluídas',
          data: concl,
          backgroundColor: '#2563EB',
          borderRadius: 6,
          borderSkipped: false,
        }
      ]
    },
    options: {
      responsive: true,
      plugins: {
        legend: {
          position: 'bottom',
          labels: {
            font: { family: 'DM Sans', size: 12 },
            color: '#64748B',
            padding: 12,
            usePointStyle: true,
            pointStyleWidth: 8,
          }
        },
        tooltip: {
          backgroundColor: '#0F172A',
          titleFont: { family: 'DM Sans', size: 13, weight: '600' },
          bodyFont:  { family: 'DM Sans', size: 12 },
          cornerRadius: 10,
          padding: 10,
        }
      },
      scales: {
        x: {
          grid: { display: false },
          ticks: { font: { family: 'DM Sans', size: 12 }, color: '#94A3B8' }
        },
        y: {
          grid: { color: '#F1F5F9' },
          border: { dash: [3,3], display: false },
          ticks: { font: { family: 'DM Sans', size: 12 }, color: '#94A3B8' }
        }
      }
    }
  });
}

/* =========================================================
   OS RECENTES
   ========================================================= */
async function loadRecent() {
  const el = document.getElementById('recent-list');
  try {
    const rows = await ajax({ action: 'recent' });
    renderRecent(rows);
  } catch(e) {
    renderRecent([
      { numero:'OS-00248', titulo:'Manutenção preventiva em câmara fria', status:'em_andamento', prioridade:'alta', cliente:'Restaurante Sabor Norte', data_abertura:'2026-05-18' },
      { numero:'OS-00247', titulo:'Troca de compressor freezer vertical', status:'aguardando_peca', prioridade:'urgente', cliente:'Mercado Ponto Frio', data_abertura:'2026-05-17' },
      { numero:'OS-00246', titulo:'Limpeza de ar-condicionado split', status:'aguardando_aprovacao', prioridade:'media', cliente:'Clínica Bem Estar', data_abertura:'2026-05-16' },
      { numero:'OS-00245', titulo:'Carga de gás balcão refrigerado', status:'finalizada', prioridade:'baixa', cliente:'Padaria Santa Luzia', data_abertura:'2026-05-15' },
      { numero:'OS-00244', titulo:'Diagnóstico técnico ar-condicionado janela', status:'aberta', prioridade:'media', cliente:'João Almeida', data_abertura:'2026-05-14' },
    ]);
  }
}

function renderRecent(rows) {
  const el = document.getElementById('recent-list');
  if (!rows.length) {
    el.innerHTML = `<div class="empty-state"><i class="bi bi-inbox"></i><p>Sem OS recentes</p></div>`;
    return;
  }
  el.innerHTML = rows.map(r => {
    const s  = statusMap[r.status] || statusMap.aberta;
    const dt = r.data_abertura ? new Date(r.data_abertura).toLocaleDateString('pt-BR',{day:'2-digit',month:'short'}) : '—';
    const dc = dotColor[r.status] || '#94A3B8';
    return `
    <div class="recent-item">
      <div class="recent-dot" style="background:${dc}"></div>
      <div class="recent-body">
        <div class="recent-num">${r.numero}</div>
        <div class="recent-title">${r.titulo}</div>
        <div class="recent-client">${r.cliente||'—'}</div>
      </div>
      <div class="recent-meta">
        <span class="badge-status ${s.cls}" style="font-size:10.5px;padding:3px 7px">${s.label}</span>
        <span class="recent-date">${dt}</span>
      </div>
    </div>`;
  }).join('');
}

/* =========================================================
   MODAL NOVA OS
   ========================================================= */
async function openModal() {
  document.getElementById('modal-nova-os').classList.add('show');
  document.body.style.overflow = 'hidden';
  try {
    const [clientes, tecnicos] = await Promise.all([
      ajax({ action: 'clientes' }),
      ajax({ action: 'tecnicos' })
    ]);
    const cs = document.getElementById('f-cliente');
    cs.innerHTML = '<option value="">Selecione o cliente...</option>';
    clientes.forEach(c => cs.add(new Option(c.nome, c.id)));

    const ts = document.getElementById('f-tecnico');
    ts.innerHTML = '<option value="">Sem técnico definido</option>';
    tecnicos.forEach(t => ts.add(new Option(t.nome, t.id)));
  } catch(e) {
    // BD não disponível: selects ficam com placeholder apenas
  }
}

function closeModal() {
  document.getElementById('modal-nova-os').classList.remove('show');
  document.body.style.overflow = '';
  document.getElementById('f-titulo').value = '';
  document.getElementById('f-descricao').value = '';
}

async function saveOS() {
  const titulo = document.getElementById('f-titulo').value.trim();
  const cliente = document.getElementById('f-cliente').value;
  if (!titulo) { toast('Informe o título da OS', 'error'); document.getElementById('f-titulo').focus(); return; }

  const btn = document.getElementById('btn-save-os');
  btn.disabled = true;
  btn.innerHTML = `<span class="spinner-border spinner-border-sm me-2"></span> Criando...`;

  try {
    const data = await ajax({ action: 'save_os' }, 'POST', {
      titulo, cliente_id: cliente,
      tecnico_id: document.getElementById('f-tecnico').value,
      descricao:  document.getElementById('f-descricao').value,
      status:     document.getElementById('f-status').value,
      prioridade: document.getElementById('f-prioridade').value,
      categoria:  document.getElementById('f-categoria').value,
      equipamento: document.getElementById('f-equipamento').value,
      valor_orcamento: document.getElementById('f-valor').value || 0,
      data_previsao: document.getElementById('f-previsao').value,
    });
    closeModal();
    const numero = String(data.numero ?? '').startsWith('OS') ? data.numero : `OS ${data.numero}`;
    toast(`${numero} criada com sucesso!`, 'success');
    loadAll();
  } catch(e) {
    toast('Erro ao criar OS: ' + e.message, 'error');
  } finally {
    btn.disabled = false;
    btn.innerHTML = `<i class="bi bi-check2"></i> Criar Ordem de Serviço`;
  }
}

// Fechar modal clicando fora
document.getElementById('modal-nova-os').addEventListener('click', function(e) {
  if (e.target === this) closeModal();
});

/* =========================================================
   FILTROS
   ========================================================= */
function clearFilters() {
  document.getElementById('search-input').value = '';
  document.getElementById('filter-status').value = '';
  document.getElementById('filter-prior').value = '';
  loadOS(1);
}

// Busca ao digitar (debounce)
let searchTimer;
document.getElementById('search-input').addEventListener('input', () => {
  clearTimeout(searchTimer);
  searchTimer = setTimeout(() => loadOS(1), 420);
});
document.getElementById('search-input').addEventListener('keydown', e => {
  if (e.key === 'Enter') loadOS(1);
});

/* =========================================================
   TOAST
   ========================================================= */
function toast(msg, type = 'info') {
  const icon = type === 'success' ? 'bi-check-circle-fill' : type === 'error' ? 'bi-x-circle-fill' : 'bi-info-circle-fill';
  const el = document.createElement('div');
  el.className = `toast-msg ${type}`;
  el.innerHTML = `<i class="bi ${icon}"></i> ${msg}`;
  document.getElementById('toast-container').appendChild(el);
  setTimeout(() => el.style.opacity = '0', 3200);
  setTimeout(() => el.remove(), 3600);
}

/* =========================================================
   UTILS
   ========================================================= */
function toggleFullscreen() {
  if (!document.fullscreenElement) document.documentElement.requestFullscreen?.();
  else document.exitFullscreen?.();
}

/* =========================================================
   INIT
   ========================================================= */
function loadAll() {
  loadMetrics();
  loadOS(1);
  loadCharts();
  loadRecent();
}

document.addEventListener('DOMContentLoaded', loadAll);
