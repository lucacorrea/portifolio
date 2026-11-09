(function (root) {
  'use strict';

  const Urbanix = root.Urbanix = root.Urbanix || {};
  const Store = Urbanix.Store;
  const UI = Urbanix.UI;
  const tabs = [
    ['home', 'bi-house-door', 'Início'],
    ['property', 'bi-geo-alt', 'Meu imóvel'],
    ['finance', 'bi-wallet2', 'Financeiro'],
    ['contract', 'bi-file-earmark-text', 'Contrato'],
    ['documents', 'bi-folder2-open', 'Documentos'],
    ['work', 'bi-buildings', 'Acompanhar obra'],
    ['support', 'bi-chat-square-text', 'Atendimento']
  ];
  const statusLabels = {
    active: 'Ativo', paid: 'Pago', pending: 'A vencer', overdue: 'Vencido',
    answered: 'Respondido', open: 'Em atendimento', closed: 'Encerrado', sold: 'Vendido'
  };
  let portalContext = null;
  let initialized = false;

  const escape = value => UI.escapeHtml(value);
  const money = value => UI.formatMoney(value, { maximumFractionDigits: 2 });
  const byId = (items, id) => items.find(item => item.id === id) || null;
  const sum = items => items.reduce((total, item) => total + Number(item.amount || 0), 0);

  function isCustomerUser(user) {
    return user?.roleCode === 'client' || user?.accountType === 'customer';
  }

  function resolveContext(user) {
    const state = Store.getState();
    const params = new URLSearchParams(root.location.search);
    const isInternalPreview = !isCustomerUser(user);
    const contractedCustomerIds = [...new Set(state.contracts.map(item => item.customerId))];
    const previewCustomerId = params.get('previewCustomer');
    const customerId = isInternalPreview
      ? (contractedCustomerIds.includes(previewCustomerId) ? previewCustomerId : contractedCustomerIds[0])
      : user.customerId;
    const customer = byId(state.customers, customerId);
    const contracts = customer ? state.contracts.filter(item => item.customerId === customer.id) : [];
    const requestedContractId = params.get('contract');
    const contract = contracts.find(item => item.id === requestedContractId) || contracts[0] || null;
    const unit = contract ? byId(state.units, contract.unitId) : null;
    const enterprise = contract ? byId(state.enterprises, contract.enterpriseId) : null;
    const work = enterprise ? state.works.find(item => item.enterpriseId === enterprise.id) || null : null;
    const installments = contract
      ? state.installments.filter(item => item.contractId === contract.id).sort((left, right) => left.number - right.number)
      : [];
    const documents = contract
      ? state.documents.filter(item => item.customerId === customer.id && item.contractId === contract.id)
      : [];
    const tickets = contract
      ? state.supportRequests.filter(item => item.customerId === customer.id && item.contractId === contract.id)
      : [];
    return {
      user, state, isInternalPreview, contractedCustomerIds, customer, contracts, contract,
      unit, enterprise, work, installments, documents, tickets
    };
  }

  function statusBadge(status) {
    const classes = {
      active: 'soft-success', paid: 'soft-success', answered: 'soft-success', sold: 'soft-success',
      pending: 'soft-info', open: 'soft-info', overdue: 'soft-danger', closed: 'soft-muted'
    };
    return `<span class="badge-soft ${classes[status] || 'soft-muted'}">${escape(statusLabels[status] || status || 'Pendente')}</span>`;
  }

  function emptyState(title, message, icon) {
    return `<div class="card-app portal-empty"><i class="bi ${icon || 'bi-inbox'}"></i><h2>${escape(title)}</h2><p>${escape(message)}</p></div>`;
  }

  function imageMarkup(imageUrl, alt, loading) {
    const safeAlt = escape(alt || 'Imagem do empreendimento');
    if (!imageUrl) return `<span class="portal-image-fallback" role="img" aria-label="${safeAlt}"><i class="bi bi-image"></i><small>Imagem indisponível</small></span>`;
    return `<img src="${escape(imageUrl)}" alt="${safeAlt}" loading="${loading || 'lazy'}" decoding="async" referrerpolicy="no-referrer" data-portal-image><span class="portal-image-fallback" role="img" aria-label="${safeAlt}" hidden><i class="bi bi-image"></i><small>Imagem indisponível</small></span>`;
  }

  function bindImageFallbacks(container) {
    container.querySelectorAll('[data-portal-image]').forEach(image => {
      const showFallback = () => {
        image.hidden = true;
        const fallback = image.nextElementSibling;
        if (fallback?.classList.contains('portal-image-fallback')) fallback.hidden = false;
      };
      image.addEventListener('error', showFallback, { once: true });
      if (image.complete && image.naturalWidth === 0) showFallback();
    });
  }

  function updateLocation(values) {
    const url = new URL(root.location.href);
    url.searchParams.delete('customer');
    Object.entries(values).forEach(([key, value]) => {
      if (value) url.searchParams.set(key, value);
      else url.searchParams.delete(key);
    });
    root.location.assign(url.href);
  }

  function renderHeader() {
    const account = document.querySelector('.portal-account');
    if (!account) return;
    const { user, isInternalPreview } = portalContext;
    account.setAttribute('aria-label', `Conta de ${user.name}`);
    account.innerHTML = `<div class="portal-account-copy"><strong>${escape(user.name)}</strong><small>${escape(isInternalPreview ? 'Prévia administrativa' : 'Conta do cliente')}</small></div>
      ${isInternalPreview ? '' : '<button type="button" class="portal-icon-button" data-portal-theme aria-label="Alternar tema" title="Alternar tema"><i class="bi bi-circle-half"></i></button>'}
      <button type="button" class="portal-icon-button" data-portal-logout aria-label="Sair do portal" title="Sair"><i class="bi bi-box-arrow-right"></i></button>`;
    account.querySelector('[data-portal-theme]')?.addEventListener('click', () => {
      Store.mutate('settings.theme', state => { state.settings.theme = state.settings.theme === 'dark' ? 'light' : 'dark'; });
      Urbanix.App.applyTheme();
    });
    account.querySelector('[data-portal-logout]').addEventListener('click', async () => {
      if (!await UI.confirmAction({ title: 'Sair do portal?', message: 'Você precisará entrar novamente para acessar sua conta.', confirmLabel: 'Sair' })) return;
      Store.mutate('session.ended', state => { state.session = null; });
      root.location.replace('login.html');
    });
  }

  function previewToolbar() {
    const { state, isInternalPreview, contractedCustomerIds, customer, contracts, contract } = portalContext;
    const contractSelector = contracts.length > 1 ? `<label class="portal-select-label" for="portalContract">Contrato
      <select class="form-select" id="portalContract" data-portal-contract-select>${contracts.map(item => `<option value="${item.id}" ${item.id === contract?.id ? 'selected' : ''}>${escape(item.number)}</option>`).join('')}</select></label>` : '';
    if (!isInternalPreview) return contractSelector ? `<div class="portal-toolbar portal-toolbar-client">${contractSelector}</div>` : '';
    return `<aside class="portal-preview" aria-label="Controles da prévia administrativa" data-portal-readonly><div><i class="bi bi-eye"></i><span><strong>Prévia administrativa · somente leitura</strong><small>Os dados abaixo simulam a visão exclusiva deste cliente sem permitir alterações.</small></span></div><div class="portal-preview-actions"><label class="portal-select-label" for="portalCustomer">Cliente
      <select class="form-select" id="portalCustomer" data-portal-customer-select>${contractedCustomerIds.map(customerId => { const item = byId(state.customers, customerId); return `<option value="${customerId}" ${customerId === customer?.id ? 'selected' : ''}>${escape(item?.name || 'Cliente')}</option>`; }).join('')}</select></label>${contractSelector}<a class="btn btn-outline-app" href="index.html"><i class="bi bi-arrow-left me-1"></i>Voltar ao administrativo</a></div></aside>`;
  }

  function renderShell() {
    const main = document.querySelector('.portal-main');
    const { customer, contract } = portalContext;
    if (!customer) {
      main.innerHTML = emptyState('Conta sem cliente vinculado', 'Não foi possível localizar um cadastro de cliente para esta conta. Fale com a Urbanix.', 'bi-person-exclamation');
      return;
    }
    if (!contract) {
      main.innerHTML = `${previewToolbar()}${emptyState('Nenhum contrato disponível', 'Este cliente ainda não possui contrato ou imóvel liberado para o portal.', 'bi-file-earmark-x')}`;
      bindToolbar();
      return;
    }
    main.innerHTML = `${previewToolbar()}<div class="portal-heading"><div><span>Minha conta</span><h1>Olá, ${escape(customer.name.split(' ')[0])}</h1><p>Acompanhe seu imóvel, seus pagamentos e a evolução da obra.</p></div><span class="portal-security-note"><i class="bi bi-shield-check"></i> Acesso vinculado ao seu cadastro</span></div>
      <nav class="portal-nav" role="tablist" aria-label="Áreas do portal">${tabs.map(([key, icon, label]) => `<button type="button" id="portal-tab-${key}" role="tab" aria-selected="false" aria-controls="portal-panel" tabindex="-1" data-portal-tab="${key}"><i class="bi ${icon}" aria-hidden="true"></i><span>${label}</span></button>`).join('')}</nav>
      <section class="portal-panel" id="portal-panel" role="tabpanel" tabindex="0" aria-live="polite"></section>`;
    bindToolbar();
    const tabButtons = Array.from(main.querySelectorAll('[data-portal-tab]'));
    tabButtons.forEach(button => button.addEventListener('click', () => activateTab(button.dataset.portalTab, true)));
    main.querySelector('.portal-nav').addEventListener('keydown', event => {
      if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) return;
      event.preventDefault();
      const currentIndex = Math.max(0, tabButtons.indexOf(document.activeElement));
      const nextIndex = event.key === 'Home' ? 0 : event.key === 'End' ? tabButtons.length - 1 : (currentIndex + (event.key === 'ArrowRight' ? 1 : -1) + tabButtons.length) % tabButtons.length;
      tabButtons[nextIndex].focus();
      activateTab(tabButtons[nextIndex].dataset.portalTab, true);
    });
    activateTab(tabFromHash(), false);
  }

  function bindToolbar() {
    document.querySelector('[data-portal-customer-select]')?.addEventListener('change', event => updateLocation({ previewCustomer: event.target.value, contract: null }));
    document.querySelector('[data-portal-contract-select]')?.addEventListener('change', event => updateLocation({ contract: event.target.value }));
  }

  function tabFromHash() {
    const requested = root.location.hash.replace(/^#/, '');
    return tabs.some(([key]) => key === requested) ? requested : 'home';
  }

  function revealActiveTab(button) {
    const nav = button?.closest('.portal-nav');
    if (!nav || !button) return;
    const navRect = nav.getBoundingClientRect();
    const buttonRect = button.getBoundingClientRect();
    if (buttonRect.right > navRect.right - 8) nav.scrollLeft += buttonRect.right - navRect.right + 12;
    if (buttonRect.left < navRect.left + 8) nav.scrollLeft -= navRect.left - buttonRect.left + 12;
  }

  function activateTab(tab, updateHash, focusPanel) {
    const validTab = tabs.some(([key]) => key === tab) ? tab : 'home';
    let activeButton = null;
    document.querySelectorAll('[data-portal-tab]').forEach(button => {
      const selected = button.dataset.portalTab === validTab;
      button.classList.toggle('active', selected);
      button.setAttribute('aria-selected', String(selected));
      button.tabIndex = selected ? 0 : -1;
      if (selected) activeButton = button;
    });
    const panel = document.getElementById('portal-panel');
    if (!panel) return;
    panel.setAttribute('aria-labelledby', `portal-tab-${validTab}`);
    renderTab(validTab, panel);
    if (updateHash) root.history.replaceState(null, '', `${root.location.pathname}${root.location.search}#${validTab}`);
    revealActiveTab(activeButton);
    root.requestAnimationFrame(() => {
      revealActiveTab(activeButton);
      if (focusPanel) {
        root.requestAnimationFrame(() => {
          const positionBelowNavigation = () => {
            const nav = activeButton.closest('.portal-nav');
            const reservedTop = document.querySelector('.portal-header').offsetHeight + nav.offsetHeight + 28;
            const panelDocumentTop = root.scrollY + panel.getBoundingClientRect().top;
            root.scrollTo(0, Math.max(0, panelDocumentTop - reservedTop));
          };
          positionBelowNavigation();
          panel.focus({ preventScroll: true });
          root.setTimeout(positionBelowNavigation, 0);
          root.setTimeout(positionBelowNavigation, 120);
        });
      }
    });
    document.fonts?.ready.then(() => revealActiveTab(activeButton));
  }

  function metric(label, value, detail, tone, icon) {
    return `<div class="card-app portal-metric ${tone || ''}"><span>${escape(label)}</span><strong>${value}</strong><small>${detail}</small><i class="bi ${icon}" aria-hidden="true"></i></div>`;
  }

  function renderHome(panel) {
    const { customer, contract, unit, enterprise, work, installments } = portalContext;
    const paid = installments.filter(item => item.status === 'paid');
    const next = installments.find(item => item.status !== 'paid');
    const balance = Math.max(0, contract.totalAmount - contract.paidAmount);
    panel.innerHTML = `<section class="portal-hero"><div><span>Seu imóvel</span><h2>${escape(enterprise?.name || 'Empreendimento')}</h2><p>${escape(unit?.code || 'Unidade')} · ${escape(enterprise ? `${enterprise.city}/${enterprise.state}` : '')}</p></div><div class="portal-hero-status"><small>Contrato ${escape(contract.number)}</small>${statusBadge(contract.status)}</div></section>
      <div class="portal-metrics">${metric('Valor contratado', money(contract.totalAmount), `Saldo atual: ${money(balance)}`, '', 'bi-receipt')}${metric('Parcelas pagas', `${paid.length} de ${contract.installmentCount}`, 'Recorte financeiro da demonstração', 'success', 'bi-check2-circle')}${metric('Próxima parcela', next ? money(next.amount) : 'Quitado', next ? `Vencimento em ${UI.formatDate(next.dueDate)}` : 'Nenhuma parcela pendente', next?.status === 'overdue' ? 'danger' : 'warning', 'bi-calendar-event')}${metric('Avanço da obra', `${work?.progress ?? enterprise?.progress ?? 0}%`, `Atualizado em ${UI.formatDate(work?.updatedAt)}`, 'info', 'bi-buildings')}</div>
      <section class="card-app portal-quick-links"><div><span>Atalhos</span><h2>O que você deseja consultar?</h2></div><div><button type="button" data-go-tab="finance"><i class="bi bi-wallet2"></i><span><strong>Parcelas</strong><small>Consulte vencimentos</small></span><i class="bi bi-chevron-right"></i></button><button type="button" data-go-tab="documents"><i class="bi bi-folder2-open"></i><span><strong>Documentos</strong><small>Baixe arquivos demonstrativos</small></span><i class="bi bi-chevron-right"></i></button><button type="button" data-go-tab="support"><i class="bi bi-chat-square-text"></i><span><strong>Atendimento</strong><small>Fale com a Urbanix</small></span><i class="bi bi-chevron-right"></i></button></div></section>`;
    panel.querySelectorAll('[data-go-tab]').forEach(button => button.addEventListener('click', () => activateTab(button.dataset.goTab, true, true)));
  }

  function renderProperty(panel) {
    const { contract, unit, enterprise } = portalContext;
    panel.innerHTML = `<section class="card-app portal-property"><div class="portal-property-visual">${imageMarkup(enterprise?.imageUrl, enterprise?.imageAlt || `Vista do ${enterprise?.name || 'empreendimento'}`, 'eager')}<span class="portal-property-code"><strong>${escape(unit?.code || '—')}</strong><small>${escape(enterprise?.name || '')}</small></span></div><div class="portal-property-copy"><span>Meu imóvel</span><h2>${escape(enterprise?.name || 'Empreendimento')}</h2><p>${escape(enterprise ? `${enterprise.type} em ${enterprise.city}/${enterprise.state}` : 'Localização não informada')}</p><dl><div><dt>Unidade</dt><dd>${escape(unit?.code || '—')}</dd></div><div><dt>Área</dt><dd>${escape(unit?.area || 0)} m²</dd></div><div><dt>Localização</dt><dd>${escape(enterprise ? `${enterprise.city}/${enterprise.state}` : '—')}</dd></div><div><dt>Situação</dt><dd>${statusBadge(unit?.status)}</dd></div><div><dt>Contrato</dt><dd>${escape(contract.number)}</dd></div><div><dt>Assinatura</dt><dd>${UI.formatDate(contract.signedAt)}</dd></div></dl></div></section>`;
    bindImageFallbacks(panel);
  }

  function installmentModal(installment) {
    const content = document.createElement('dl');
    content.className = 'portal-modal-details';
    content.innerHTML = `<div><dt>Situação</dt><dd>${escape(statusLabels[installment.status] || installment.status)}</dd></div><div><dt>Vencimento</dt><dd>${UI.formatDate(installment.dueDate)}</dd></div><div><dt>Valor original</dt><dd>${money(installment.amount)}</dd></div><div><dt>Valor demonstrativo</dt><dd>${money(installment.amount)}</dd></div>`;
    UI.openModal({ eyebrow: `Parcela ${installment.number}`, title: money(installment.amount), content });
  }

  function renderFinance(panel) {
    const { contract, installments } = portalContext;
    const paid = installments.filter(item => item.status === 'paid');
    const overdue = installments.filter(item => item.status === 'overdue');
    const next = installments.find(item => item.status !== 'paid');
    const renderList = filter => {
      const items = filter === 'all' ? installments : installments.filter(item => item.status === filter);
      const list = panel.querySelector('[data-installment-list]');
      list.innerHTML = items.length ? items.map(item => `<button type="button" class="installment-card" data-portal-installment="${item.id}"><small>${String(item.number).padStart(2, '0')} / ${contract.installmentCount}</small><strong>${money(item.amount)}</strong><span>${UI.formatDate(item.dueDate)}</span>${statusBadge(item.status)}</button>`).join('') : emptyState('Nenhuma parcela neste filtro', 'Escolha outra situação para consultar as parcelas.', 'bi-calendar-x');
      list.querySelectorAll('[data-portal-installment]').forEach(button => button.addEventListener('click', () => installmentModal(installments.find(item => item.id === button.dataset.portalInstallment))));
    };
    panel.innerHTML = `<div class="portal-finance-summary">${metric('Total pago', money(sum(paid)), `${paid.length} parcelas no recorte`, 'success', 'bi-check2-circle')}${metric('Saldo do contrato', money(Math.max(0, contract.totalAmount - contract.paidAmount)), `Contrato ${escape(contract.number)}`, '', 'bi-wallet2')}${metric('Total vencido', money(sum(overdue)), `${overdue.length} parcela${overdue.length === 1 ? '' : 's'} vencida${overdue.length === 1 ? '' : 's'}`, overdue.length ? 'danger' : 'success', 'bi-exclamation-circle')}${metric('Próximo vencimento', next ? UI.formatDate(next.dueDate) : 'Quitado', next ? money(next.amount) : 'Sem pendências', 'warning', 'bi-calendar-event')}</div>
      <section class="card-app portal-installments"><header><div><span>Financeiro</span><h2>Minhas parcelas</h2><p>Recorte demonstrativo de ${installments.length} das ${contract.installmentCount} parcelas previstas no contrato.</p></div><label for="installmentFilter">Situação<select class="form-select" id="installmentFilter"><option value="all">Todas</option><option value="paid">Pagas</option><option value="pending">A vencer</option><option value="overdue">Vencidas</option></select></label></header><div class="installment-grid" data-installment-list></div></section>`;
    panel.querySelector('#installmentFilter').addEventListener('change', event => renderList(event.target.value));
    renderList('all');
  }

  function renderContract(panel) {
    const { customer, contract, unit, enterprise } = portalContext;
    panel.innerHTML = `<article class="card-app document-view print-document portal-contract"><div class="portal-document-note"><i class="bi bi-info-circle"></i> Visualização demonstrativa — não substitui o documento assinado.</div><small>URBANIX EMPREENDIMENTOS</small><h2>Contrato ${escape(contract.number)}</h2><p>Instrumento de compra e venda vinculado a <strong>${escape(customer.name)}</strong>.</p><dl><div><dt>Imóvel</dt><dd>${escape(enterprise?.name || '')} · ${escape(unit?.code || '')}</dd></div><div><dt>Valor contratado</dt><dd>${money(contract.totalAmount)}</dd></div><div><dt>Entrada</dt><dd>${money(contract.downPayment)}</dd></div><div><dt>Saldo</dt><dd>${money(Math.max(0, contract.totalAmount - contract.paidAmount))}</dd></div><div><dt>Assinatura</dt><dd>${UI.formatDate(contract.signedAt)}</dd></div><div><dt>Situação</dt><dd>${statusBadge(contract.status)}</dd></div></dl><button type="button" class="btn btn-primary-app no-print" data-contract-print><i class="bi bi-printer me-1"></i>Imprimir visualização</button></article>`;
    panel.querySelector('[data-contract-print]').addEventListener('click', () => root.print());
  }

  function documentContent(item) {
    const { customer, contract, enterprise, unit, installments } = portalContext;
    if (item.mimeType === 'text/csv') {
      return `Parcela;Vencimento;Valor;Situacao\r\n${installments.map(entry => `${entry.number};${entry.dueDate};${entry.amount.toFixed(2)};${statusLabels[entry.status] || entry.status}`).join('\r\n')}`;
    }
    return `<!doctype html><html lang="pt-BR"><meta charset="utf-8"><title>${escape(item.name)}</title><body><h1>${escape(item.name)}</h1><p>Documento demonstrativo Urbanix.</p><dl><dt>Cliente</dt><dd>${escape(customer.name)}</dd><dt>Contrato</dt><dd>${escape(contract.number)}</dd><dt>Imóvel</dt><dd>${escape(enterprise?.name || '')} · ${escape(unit?.code || '')}</dd></dl><p>Este arquivo não substitui o documento assinado.</p></body></html>`;
  }

  function downloadDocument(item) {
    const blob = new Blob([documentContent(item)], { type: `${item.mimeType};charset=utf-8` });
    const link = document.createElement('a');
    const url = URL.createObjectURL(blob);
    link.href = url;
    link.download = item.fileName;
    document.body.appendChild(link);
    link.click();
    link.remove();
    root.setTimeout(() => URL.revokeObjectURL(url), 1000);
    UI.showToast(`${item.fileName} foi gerado para a demonstração.`, 'success', 'Download iniciado');
  }

  function renderDocuments(panel) {
    const { documents } = portalContext;
    if (!documents.length) {
      panel.innerHTML = emptyState('Nenhum documento disponível', 'Quando um arquivo for liberado para este contrato, ele aparecerá aqui.', 'bi-folder2-open');
      return;
    }
    panel.innerHTML = `<section class="portal-section-heading"><span>Arquivos do contrato</span><h2>Meus documentos</h2><p>Downloads demonstrativos identificados pelo formato real do arquivo.</p></section><div class="portal-documents">${documents.map(item => `<button type="button" class="card-app portal-document" data-download-document="${item.id}"><span class="portal-document-icon"><i class="bi ${item.mimeType === 'text/csv' ? 'bi-filetype-csv' : 'bi-filetype-html'}"></i></span><span><strong>${escape(item.name)}</strong><small>${escape(item.category)} · ${UI.formatDate(item.createdAt)}</small><em>${escape(item.fileName)}</em></span><i class="bi bi-download" aria-hidden="true"></i><span class="visually-hidden">Baixar arquivo demonstrativo</span></button>`).join('')}</div>`;
    panel.querySelectorAll('[data-download-document]').forEach(button => button.addEventListener('click', () => downloadDocument(documents.find(item => item.id === button.dataset.downloadDocument))));
  }

  function renderWork(panel) {
    const { state, work, enterprise } = portalContext;
    if (!work) {
      panel.innerHTML = emptyState('Obra ainda não vinculada', 'O acompanhamento será exibido quando a obra deste empreendimento estiver disponível.', 'bi-buildings');
      return;
    }
    const phases = state.services.filter(item => item.workId === work.id && item.customerVisible);
    const photos = state.workPhotos.filter(item => item.workId === work.id);
    panel.innerHTML = `<section class="portal-work-header"><div><span>Acompanhar obra</span><h2>${escape(enterprise.name)}</h2><p>Última atualização em ${UI.formatDate(work.updatedAt, { dateStyle: 'long' })}</p></div><strong>${work.progress}%<small>avanço geral</small></strong></section>
      <div class="card-app portal-work-timeline">${phases.length ? phases.map((item, index) => `<div class="portal-work-phase"><span class="portal-phase-marker">${index + 1}</span><div><strong>${escape(item.name)}</strong><small>Atualizado em ${UI.formatDate(item.updatedAt)}</small><div class="progress progress-thin" aria-label="${escape(item.name)}: ${item.progress}%"><div class="progress-bar" style="width:${Math.min(100, item.progress)}%"></div></div></div><b>${item.progress}%</b></div>`).join('') : emptyState('Nenhuma etapa publicada', 'A engenharia ainda não liberou etapas para este empreendimento.', 'bi-list-check')}</div>
      <section class="portal-section-heading portal-gallery-heading"><span>Registro visual</span><h2>Galeria da obra</h2></section>${photos.length ? `<div class="portal-photo-gallery">${photos.map(item => `<figure class="card-app"><div class="portal-photo-media">${imageMarkup(item.imageUrl, item.alt, 'lazy')}</div><figcaption><strong>${escape(item.caption)}</strong><small>${UI.formatDate(item.createdAt)}</small></figcaption></figure>`).join('')}</div>` : emptyState('Nenhuma foto publicada', 'As fotos liberadas pela engenharia aparecerão aqui.', 'bi-images')}`;
    bindImageFallbacks(panel);
  }

  function renderSupport(panel) {
    const { tickets, isInternalPreview } = portalContext;
    const sorted = tickets.slice().sort((left, right) => right.createdAt.localeCompare(left.createdAt));
    const history = `<section class="card-app portal-ticket-history"><header><span>Histórico</span><h2>Meus atendimentos</h2><p>${sorted.length} solicitação${sorted.length === 1 ? '' : 'ões'} neste contrato.</p></header><div>${sorted.length ? sorted.map(item => `<article data-support-ticket="${item.id}"><div><strong>${escape(item.subject)}</strong>${statusBadge(item.status)}</div><p>${escape(item.message)}</p><small>Protocolo ${escape(item.id)} · ${UI.formatDate(item.createdAt, { dateStyle: 'short', timeStyle: 'short' })}</small></article>`).join('') : emptyState('Nenhum atendimento enviado', 'Sua primeira solicitação aparecerá aqui depois do envio.', 'bi-chat-square-text')}</div></section>`;
    if (isInternalPreview) {
      panel.innerHTML = `<div class="portal-readonly-note" role="status"><i class="bi bi-lock"></i><span><strong>Prévia somente leitura</strong><small>Atendimentos podem ser consultados, mas não criados na visualização administrativa.</small></span></div>${history}`;
      return;
    }
    panel.innerHTML = `<div class="portal-support-grid"><section class="card-app portal-support-form"><header><span>Fale com a Urbanix</span><h2>Novo atendimento</h2><p>Envie sua solicitação com até 500 caracteres.</p></header><form data-support-form novalidate><label for="supportSubject">Assunto<select class="form-select" id="supportSubject" name="subject" required><option value="">Selecione</option><option value="Financeiro">Financeiro</option><option value="Contrato">Contrato</option><option value="Obra">Obra</option><option value="Atualização cadastral">Atualização cadastral</option></select></label><label for="supportMessage">Mensagem<textarea class="form-control" id="supportMessage" name="message" rows="5" minlength="10" maxlength="500" required placeholder="Descreva como podemos ajudar"></textarea></label><div class="portal-field-error" data-support-error role="alert" aria-live="polite"></div><button class="btn btn-primary-app" type="submit">Enviar atendimento</button></form></section>${history}</div>`;
    const form = panel.querySelector('[data-support-form]');
    form.addEventListener('submit', event => {
      event.preventDefault();
      const data = new FormData(form);
      const subject = String(data.get('subject') || '').trim();
      const message = String(data.get('message') || '').trim();
      const error = form.querySelector('[data-support-error]');
      error.textContent = '';
      if (!subject || message.length < 10 || message.length > 500) {
        error.textContent = 'Selecione o assunto e escreva uma mensagem entre 10 e 500 caracteres.';
        form.querySelector(!subject ? '#supportSubject' : '#supportMessage').focus();
        return;
      }
      if (portalContext.isInternalPreview) return;
      const now = new Date().toISOString();
      Store.create('supportRequests', {
        customerId: portalContext.customer.id,
        contractId: portalContext.contract.id,
        createdByUserId: portalContext.user.id,
        subject, message, status: 'open', createdAt: now, updatedAt: now
      });
      UI.showToast('Solicitação enviada e adicionada ao histórico.', 'success', 'Atendimento registrado');
      portalContext = resolveContext(portalContext.user);
      renderSupport(panel);
    });
  }

  function renderTab(tab, panel) {
    if (tab === 'home') renderHome(panel);
    if (tab === 'property') renderProperty(panel);
    if (tab === 'finance') renderFinance(panel);
    if (tab === 'contract') renderContract(panel);
    if (tab === 'documents') renderDocuments(panel);
    if (tab === 'work') renderWork(panel);
    if (tab === 'support') renderSupport(panel);
  }

  function init(user) {
    if (initialized) return;
    initialized = true;
    portalContext = resolveContext(user);
    renderHeader();
    renderShell();
    root.addEventListener('hashchange', () => activateTab(tabFromHash(), false));
    root.addEventListener('resize', () => revealActiveTab(document.querySelector('[role="tab"][aria-selected="true"]')));
  }

  Urbanix.Portal = Object.freeze({ init, resolveContext });
})(window);
