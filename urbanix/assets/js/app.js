(function (root) {
  'use strict';

  const Urbanix = root.Urbanix = root.Urbanix || {};
  const Store = Urbanix.Store;
  const UI = Urbanix.UI;

  function currentFile() {
    const file = decodeURIComponent(root.location.pathname).split('/').pop();
    return file || 'index.html';
  }

  function sessionUser() {
    const state = Store.getState();
    const session = state.session;
    if (!session || !session.userId || new Date(session.expiresAt).getTime() <= Date.now()) return null;
    return state.users.find(user => user.id === session.userId && user.active) || null;
  }

  function isCustomerUser(user) {
    return Boolean(user && (user.roleCode === 'client' || user.accountType === 'customer'));
  }

  function defaultRouteFor(user) {
    return isCustomerUser(user) ? 'portal-cliente.html' : 'index.html';
  }

  function canAccessPage(user, page) {
    if (!user) return false;
    const file = String(page || '').split(/[?#]/)[0].toLowerCase();
    return !isCustomerUser(user) || file === 'portal-cliente.html' || file === 'login.html';
  }

  function redirectToLogin() {
    const next = encodeURIComponent(`${currentFile()}${root.location.search}${root.location.hash}`);
    root.location.replace(`login.html?next=${next}`);
  }

  function applyTheme() {
    const state = Store.getState();
    document.documentElement.dataset.theme = state.settings.theme === 'dark' ? 'dark' : 'light';
  }

  applyTheme();
  const isLogin = currentFile().toLowerCase() === 'login.html';
  const initialUser = sessionUser();
  let accessRedirected = false;
  if (!isLogin && !initialUser) {
    accessRedirected = true;
    redirectToLogin();
  } else if (!isLogin && !canAccessPage(initialUser, currentFile())) {
    // Esta barreira melhora a demonstração. O backend futuro deve revalidar sessão, cliente e recurso em toda requisição.
    accessRedirected = true;
    root.location.replace(defaultRouteFor(initialUser));
  }

  function handleLogin() {
    const form = document.getElementById('loginForm');
    if (!form) return;
    const email = form.elements.email;
    const password = form.elements.password;
    const feedback = document.getElementById('loginFeedback');
    const params = new URLSearchParams(root.location.search);

    document.querySelectorAll('[data-demo-account]').forEach(button => {
      button.addEventListener('click', () => {
        email.value = button.dataset.demoAccount;
        password.value = '123456';
        email.focus();
      });
    });

    document.querySelector('[data-forgot-password]')?.addEventListener('click', () => {
      UI.openModal({
        title: 'Recuperação no ambiente demonstrativo',
        content: 'Use uma das contas de demonstração exibidas na tela. Todas utilizam a senha 123456.'
      });
    });

    form.addEventListener('submit', event => {
      event.preventDefault();
      feedback.textContent = '';
      form.classList.add('was-validated');
      if (!form.checkValidity()) return;
      const normalizedEmail = email.value.trim().toLowerCase();
      const user = Store.query('users').find(item => item.active && item.email.toLowerCase() === normalizedEmail && item.password === password.value);
      if (!user) {
        feedback.textContent = 'E-mail ou senha inválidos. Confira os dados e tente novamente.';
        email.setAttribute('aria-invalid', 'true');
        password.setAttribute('aria-invalid', 'true');
        password.focus();
        return;
      }
      const remember = form.elements.remember.checked;
      Store.mutate('session.started', state => {
        state.session = {
          userId: user.id,
          startedAt: new Date().toISOString(),
          expiresAt: new Date(Date.now() + (remember ? 30 : 1) * 24 * 60 * 60 * 1000).toISOString(),
          remember
        };
        state.audits.push({ id: Store.generateId('audit'), userId: user.id, action: 'session.started', entity: 'session', entityId: user.id, detail: 'Login demonstrativo realizado', createdAt: new Date().toISOString() });
      });
      const requested = params.get('next');
      const requestedIsSafe = requested && /^[a-z0-9-]+\.html(?:[?#].*)?$/i.test(requested) && canAccessPage(user, requested);
      const safeNext = requestedIsSafe ? requested : defaultRouteFor(user);
      root.location.assign(safeNext);
    });
  }

  function setActiveNavigation() {
    const file = currentFile().toLowerCase();
    document.querySelectorAll('.sidebar .nav-link[href]').forEach(link => {
      const active = link.getAttribute('href').split(/[?#]/)[0].toLowerCase() === file;
      link.classList.toggle('active', active);
      if (active) link.setAttribute('aria-current', 'page');
      else link.removeAttribute('aria-current');
    });
  }

  function initializeSidebar() {
    const sidebar = document.querySelector('.sidebar');
    const menuButton = document.querySelector('[data-menu-toggle]');
    if (!sidebar || !menuButton) return;
    menuButton.type = 'button';
    menuButton.setAttribute('aria-label', 'Abrir menu principal');
    menuButton.setAttribute('aria-expanded', 'false');
    const backdrop = document.createElement('button');
    backdrop.type = 'button';
    backdrop.className = 'sidebar-backdrop';
    backdrop.setAttribute('aria-label', 'Fechar menu principal');
    document.body.appendChild(backdrop);
    const close = () => {
      sidebar.classList.remove('open');
      document.body.classList.remove('sidebar-visible');
      menuButton.setAttribute('aria-expanded', 'false');
    };
    menuButton.addEventListener('click', () => {
      const open = !sidebar.classList.contains('open');
      sidebar.classList.toggle('open', open);
      document.body.classList.toggle('sidebar-visible', open);
      menuButton.setAttribute('aria-expanded', String(open));
    });
    backdrop.addEventListener('click', close);
    sidebar.querySelectorAll('a').forEach(link => link.addEventListener('click', close));
    document.addEventListener('keydown', event => { if (event.key === 'Escape') close(); });
  }

  function relationName(state, collection, id, fallback) {
    return state[collection].find(item => item.id === id)?.name || fallback;
  }

  function buildSearchIndex() {
    const state = Store.getState();
    const items = [];
    state.customers.forEach(item => items.push({ label: item.name, meta: `Cliente · ${item.cpf}`, href: 'clientes.html', tokens: `${item.name} ${item.cpf} ${item.email}` }));
    state.enterprises.forEach(item => items.push({ label: item.name, meta: `Empreendimento · ${item.city}/${item.state}`, href: 'empreendimentos.html', tokens: `${item.name} ${item.city} ${item.type}` }));
    state.units.forEach(item => items.push({ label: item.code, meta: `${relationName(state, 'enterprises', item.enterpriseId, 'Empreendimento')} · Unidade`, href: 'mapa-unidades.html', tokens: `${item.code} lote unidade ${item.status}` }));
    state.contracts.forEach(item => items.push({ label: item.number, meta: 'Contrato', href: 'contratos.html', tokens: `${item.number} contrato` }));
    state.proposals.forEach(item => items.push({ label: item.number, meta: 'Proposta', href: 'propostas.html', tokens: `${item.number} proposta` }));
    state.sales.forEach(item => items.push({ label: item.number, meta: 'Venda', href: 'vendas.html', tokens: `${item.number} venda` }));
    return items;
  }

  function createPanel(id, className) {
    const panel = document.createElement('section');
    panel.id = id;
    panel.className = `floating-panel ${className || ''}`.trim();
    panel.hidden = true;
    document.body.appendChild(panel);
    return panel;
  }

  function closePanels(except) {
    document.querySelectorAll('.floating-panel').forEach(panel => {
      if (panel !== except) panel.hidden = true;
    });
  }

  function togglePanel(panel) {
    const willOpen = panel.hidden;
    closePanels(panel);
    panel.hidden = !willOpen;
    return willOpen;
  }

  function initializeSearch(searchButton) {
    const panel = createPanel('globalSearchPanel', 'search-panel');
    panel.innerHTML = `<div class="panel-heading"><div><small>Busca global</small><strong>Localize qualquer registro</strong></div><kbd>Esc</kbd></div><label class="global-search-input"><i class="bi bi-search"></i><span class="visually-hidden">Pesquisar cliente, unidade, contrato ou venda</span><input type="search" placeholder="Cliente, CPF, empreendimento, unidade..." autocomplete="off"></label><div class="search-results" data-search-results><div class="search-hint">Digite pelo menos 2 caracteres para pesquisar.</div></div>`;
    const input = panel.querySelector('input');
    const results = panel.querySelector('[data-search-results]');
    const open = () => {
      closePanels(panel);
      panel.hidden = false;
      root.setTimeout(() => input.focus(), 0);
    };
    searchButton.addEventListener('click', open);
    input.addEventListener('input', () => {
      const term = UI.normalize(input.value);
      if (term.length < 2) {
        results.innerHTML = '<div class="search-hint">Digite pelo menos 2 caracteres para pesquisar.</div>';
        return;
      }
      const matches = buildSearchIndex().filter(item => UI.normalize(item.tokens).includes(term)).slice(0, 8);
      results.innerHTML = matches.length ? matches.map(item => `<a href="${item.href}"><i class="bi bi-arrow-return-right"></i><span><strong>${UI.escapeHtml(item.label)}</strong><small>${UI.escapeHtml(item.meta)}</small></span></a>`).join('') : '<div class="search-hint">Nenhum resultado encontrado.</div>';
    });
    document.addEventListener('keydown', event => {
      if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') {
        event.preventDefault();
        open();
      }
      if (event.key === 'Escape') panel.hidden = true;
    });
  }

  function initializeNotifications(button) {
    const panel = createPanel('notificationsPanel', 'notifications-panel');
    function render() {
      const notifications = Store.query('notifications').slice().sort((a, b) => b.createdAt.localeCompare(a.createdAt));
      const unread = notifications.filter(item => !item.read).length;
      const badge = button.querySelector('[data-notification-count]');
      badge.textContent = unread > 9 ? '9+' : String(unread);
      badge.hidden = unread === 0;
      panel.innerHTML = `<div class="panel-heading"><div><small>Central</small><strong>Notificações</strong></div><button type="button" data-read-all>Marcar como lidas</button></div><div class="notification-list">${notifications.map(item => `<button type="button" data-notification-id="${item.id}" data-href="${item.href}" class="${item.read ? '' : 'unread'}"><i class="bi bi-${item.read ? 'check2' : 'bell-fill'}"></i><span><strong>${UI.escapeHtml(item.title)}</strong><small>${UI.escapeHtml(item.detail)}</small></span></button>`).join('')}</div>`;
      panel.querySelector('[data-read-all]').addEventListener('click', () => {
        Store.mutate('notifications.read-all', state => state.notifications.forEach(item => { item.read = true; }));
        render();
      });
      panel.querySelectorAll('[data-notification-id]').forEach(item => item.addEventListener('click', () => {
        Store.mutate('notification.read', state => {
          const notification = state.notifications.find(entry => entry.id === item.dataset.notificationId);
          if (notification) notification.read = true;
        });
        root.location.assign(item.dataset.href);
      }));
    }
    render();
    button.addEventListener('click', () => { if (togglePanel(panel)) render(); });
  }

  function initializeQuickActions(button) {
    const panel = createPanel('quickActionsPanel', 'quick-actions-panel');
    const actions = [
      ['bi-person-plus', 'Novo lead', 'crm.html?action=new'],
      ['bi-person-vcard', 'Novo cliente', 'clientes.html?action=new'],
      ['bi-file-earmark-plus', 'Nova proposta', 'propostas.html?action=new'],
      ['bi-bookmark-plus', 'Nova reserva', 'reservas.html?action=new'],
      ['bi-cash-coin', 'Novo lançamento', 'financeiro.html?action=new'],
      ['bi-rulers', 'Nova medição', 'engenharia.html?action=new']
    ];
    panel.innerHTML = `<div class="panel-heading"><div><small>Atalhos</small><strong>Ações rápidas</strong></div></div><div class="quick-actions-grid">${actions.map(action => `<a href="${action[2]}"><i class="bi ${action[0]}"></i><span>${action[1]}</span></a>`).join('')}</div>`;
    button.addEventListener('click', () => togglePanel(panel));
  }

  function initializeUserMenu(user, userButton) {
    const panel = createPanel('userPanel', 'user-panel');
    const render = () => {
      const isDark = document.documentElement.dataset.theme === 'dark';
      panel.innerHTML = `<div class="user-panel-header"><div class="avatar">${UI.escapeHtml(user.initials)}</div><div><strong>${UI.escapeHtml(user.name)}</strong><small>${UI.escapeHtml(user.email)}</small></div></div><button type="button" data-profile><i class="bi bi-person"></i>Meu perfil</button><a href="configuracoes.html"><i class="bi bi-gear"></i>Configurações</a><button type="button" data-theme><i class="bi bi-${isDark ? 'sun' : 'moon-stars'}"></i>${isDark ? 'Usar tema claro' : 'Usar tema escuro'}</button><button type="button" data-reset><i class="bi bi-arrow-counterclockwise"></i>Restaurar demonstração</button><button type="button" data-logout class="text-danger"><i class="bi bi-box-arrow-right"></i>Sair</button>`;
      panel.querySelector('[data-profile]').addEventListener('click', () => {
        const profile = document.createElement('div');
        profile.className = 'profile-summary';
        profile.innerHTML = `<div class="avatar">${UI.escapeHtml(user.initials)}</div><dl><div><dt>Nome</dt><dd>${UI.escapeHtml(user.name)}</dd></div><div><dt>E-mail</dt><dd>${UI.escapeHtml(user.email)}</dd></div><div><dt>Telefone</dt><dd>${UI.escapeHtml(user.phone)}</dd></div><div><dt>Função</dt><dd>${UI.escapeHtml(user.role)}</dd></div></dl><p>Este perfil é demonstrativo. Autorização real deverá ser validada no backend.</p>`;
        UI.openModal({ title: 'Meu perfil', content: profile });
        panel.hidden = true;
      });
      panel.querySelector('[data-theme]').addEventListener('click', () => {
        Store.mutate('settings.theme', state => { state.settings.theme = state.settings.theme === 'dark' ? 'light' : 'dark'; });
        applyTheme();
        render();
      });
      panel.querySelector('[data-reset]').addEventListener('click', async () => {
        const confirmed = await UI.confirmAction({ title: 'Restaurar dados da demonstração?', message: 'Cadastros e alterações locais serão substituídos pelos dados iniciais. Sua sessão será mantida.', confirmLabel: 'Restaurar', danger: true });
        if (!confirmed) return;
        Store.reset({ keepSession: true });
        UI.showToast('Dados iniciais restaurados. A página será atualizada.', 'success');
        root.setTimeout(() => root.location.reload(), 700);
      });
      panel.querySelector('[data-logout]').addEventListener('click', async () => {
        const confirmed = await UI.confirmAction({ title: 'Sair do Urbanix?', message: 'Você precisará entrar novamente para acessar o protótipo.', confirmLabel: 'Sair' });
        if (!confirmed) return;
        Store.mutate('session.ended', state => { state.session = null; });
        root.location.replace('login.html');
      });
    };
    render();
    userButton.addEventListener('click', () => { if (togglePanel(panel)) render(); });
  }

  function initializeTopbar(user) {
    const actions = document.querySelector('.top-actions');
    if (!actions) return;
    actions.innerHTML = `<button type="button" class="icon-btn" data-global-search aria-label="Busca global" title="Busca global (Ctrl + K)"><i class="bi bi-search"></i></button><button type="button" class="icon-btn action-optional" data-quick-actions aria-label="Ações rápidas" title="Ações rápidas"><i class="bi bi-plus-lg"></i></button><a class="icon-btn action-optional" href="crm.html" aria-label="Mensagens" title="Mensagens"><i class="bi bi-chat-dots"></i></a><button type="button" class="icon-btn has-badge" data-notifications aria-label="Notificações" title="Notificações"><i class="bi bi-bell"></i><span class="action-badge" data-notification-count></span></button><button type="button" class="user-chip" data-user-menu aria-label="Abrir menu do usuário"><div class="avatar">${UI.escapeHtml(user.initials)}</div><div class="user-text"><strong>${UI.escapeHtml(user.name)}</strong><small>${UI.escapeHtml(user.role)}</small></div><i class="bi bi-chevron-down small text-secondary"></i></button>`;
    initializeSearch(actions.querySelector('[data-global-search]'));
    initializeQuickActions(actions.querySelector('[data-quick-actions]'));
    initializeNotifications(actions.querySelector('[data-notifications]'));
    initializeUserMenu(user, actions.querySelector('[data-user-menu]'));
    document.addEventListener('click', event => {
      if (!event.target.closest('.floating-panel, .top-actions')) closePanels();
    });
  }

  function initializeLots() {
    const statusMap = { available: 'Disponível', reserved: 'Reservado', sold: 'Vendido', blocked: 'Bloqueado' };
    const badgeMap = { available: 'soft-success', reserved: 'soft-warning', sold: 'soft-danger', blocked: 'soft-muted' };
    document.querySelectorAll('.lot:not([data-action-handled="true"])').forEach(lot => {
      lot.tabIndex = 0;
      lot.setAttribute('role', 'button');
      lot.setAttribute('aria-label', `Abrir detalhes da unidade ${lot.dataset.code || ''}`);
      const select = () => {
        document.querySelectorAll('.lot').forEach(item => item.classList.remove('selected'));
        lot.classList.add('selected');
        const target = document.getElementById('lotDetail');
        if (!target) return;
        const status = lot.dataset.status || 'available';
        target.querySelector('[data-lot-code]').textContent = lot.dataset.code || lot.querySelector('strong')?.textContent || '—';
        target.querySelector('[data-lot-area]').textContent = lot.dataset.area || '—';
        target.querySelector('[data-lot-value]').textContent = lot.dataset.value || '—';
        const badge = target.querySelector('[data-lot-status]');
        badge.textContent = statusMap[status] || status;
        badge.className = `badge-soft ${badgeMap[status] || 'soft-muted'}`;
      };
      lot.addEventListener('click', select);
      lot.addEventListener('keydown', event => { if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); select(); } });
    });
  }

  function initializeGenericActions() {
    document.querySelectorAll('[data-demo-toast]').forEach(button => {
      if (button.dataset.actionHandled === 'true') return;
      const label = button.textContent.trim().toLowerCase();
      button.addEventListener('click', () => {
        if (label.includes('imprimir')) {
          root.print();
          return;
        }
        if (label.includes('exportar')) {
          const table = document.querySelector('.table-app');
          if (table) UI.exportTableCsv(table, `urbanix-${document.body.dataset.page || 'dados'}`);
          else UI.showToast('Este relatório visual ainda não possui uma grade para exportação.', 'info', 'Exportação demonstrativa');
          return;
        }
        UI.showToast('O formulário específico será conectado pelo módulo desta área.', 'info', button.textContent.trim());
      });
    });
  }

  function initializeApp() {
    if (accessRedirected) return;
    if (isLogin) {
      handleLogin();
      return;
    }
    const user = sessionUser();
    if (!user) return;
    const page = document.body.dataset.page || currentFile().replace(/\.html$/i, '');
    if (page === 'portal-cliente') {
      Urbanix.Portal?.init(user);
      return;
    }
    setActiveNavigation();
    initializeSidebar();
    initializeTopbar(user);
    [Urbanix.Inventory, Urbanix.Commercial].forEach(module => {
      if (module && typeof module.init === 'function') module.init(page);
    });
    initializeLots();
    initializeGenericActions();
    document.querySelectorAll('.table-app').forEach(UI.enhanceTable);
    UI.applyMasks(document);
    if (root.bootstrap) document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(element => new root.bootstrap.Tooltip(element));
  }

  document.addEventListener('DOMContentLoaded', initializeApp);
  Urbanix.App = Object.freeze({ sessionUser, isCustomerUser, defaultRouteFor, canAccessPage, applyTheme, currentFile });
})(window);
