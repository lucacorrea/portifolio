const sidebar = document.getElementById('sidebar');
const overlay = document.getElementById('overlay');
const menuToggle = document.getElementById('menuToggle');
const closeSidebar = document.getElementById('closeSidebar');
const toast = document.getElementById('toast');

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

function showToast(message) {
  toast.textContent = message;
  toast.classList.add('show');
  window.clearTimeout(showToast.timer);
  showToast.timer = window.setTimeout(() => toast.classList.remove('show'), 2800);
}

function closeMenu() {
  sidebar.classList.remove('open');
  overlay.classList.remove('show');
}
menuToggle?.addEventListener('click', () => {
  sidebar.classList.add('open');
  overlay.classList.add('show');
});
closeSidebar?.addEventListener('click', closeMenu);
overlay?.addEventListener('click', closeMenu);

function openPage(key) {
  const info = pageInfo[key] || pageInfo.dashboard;
  document.getElementById('pageTitle').textContent = info[0];
  document.getElementById('breadcrumbCurrent').textContent = info[0];
  document.getElementById('pageDescription').textContent = info[1];
  document.querySelectorAll('.nav-link').forEach(link => {
    link.classList.toggle('active', link.dataset.page === key);
  });
  if (key !== 'dashboard') {
    showToast(`Área “${info[0]}” selecionada. Conteúdo funcional será desenvolvido na próxima etapa.`);
  }
  closeMenu();
}

document.querySelectorAll('[data-page]').forEach(element => {
  element.addEventListener('click', () => openPage(element.dataset.page));
});

document.getElementById('newAction')?.addEventListener('click', () => {
  showToast('Ação demonstrativa: o cadastro de obras será conectado ao PHP na próxima etapa.');
});
document.getElementById('exportBtn')?.addEventListener('click', () => {
  showToast('A exportação será habilitada quando os dados reais forem integrados.');
});

const date = new Intl.DateTimeFormat('pt-BR', {
  weekday: 'short', day: '2-digit', month: 'long', year: 'numeric'
}).format(new Date());
document.getElementById('currentDate').textContent = `Coari, ${date}`;
