(function (root) {
  'use strict';

  const Urbanix = root.Urbanix = root.Urbanix || {};

  function formatMoney(value, options) {
    const number = Number(value);
    return new Intl.NumberFormat('pt-BR', {
      style: 'currency',
      currency: (options && options.currency) || 'BRL',
      maximumFractionDigits: (options && options.maximumFractionDigits) ?? 2
    }).format(Number.isFinite(number) ? number : 0);
  }

  function formatDate(value, options) {
    if (!value) return '—';
    const date = value instanceof Date ? value : new Date(value.length === 10 ? `${value}T12:00:00` : value);
    if (Number.isNaN(date.getTime())) return '—';
    return new Intl.DateTimeFormat('pt-BR', options || { dateStyle: 'short' }).format(date);
  }

  function escapeHtml(value) {
    const span = document.createElement('span');
    span.textContent = String(value ?? '');
    return span.innerHTML;
  }

  function toastHost() {
    let host = document.getElementById('urbanixToastHost');
    if (!host) {
      host = document.createElement('div');
      host.id = 'urbanixToastHost';
      host.className = 'urbanix-toast-host';
      host.setAttribute('aria-live', 'polite');
      host.setAttribute('aria-atomic', 'true');
      document.body.appendChild(host);
    }
    return host;
  }

  function showToast(message, type, title) {
    const item = document.createElement('div');
    item.className = `urbanix-toast toast-${type || 'info'}`;
    item.setAttribute('role', type === 'danger' ? 'alert' : 'status');
    item.innerHTML = `<i class="bi ${type === 'success' ? 'bi-check-circle' : type === 'danger' ? 'bi-exclamation-circle' : 'bi-info-circle'}"></i><div><strong>${escapeHtml(title || (type === 'danger' ? 'Não foi possível concluir' : 'Urbanix'))}</strong><span>${escapeHtml(message)}</span></div><button type="button" aria-label="Fechar"><i class="bi bi-x-lg"></i></button>`;
    const close = () => {
      item.classList.add('leaving');
      root.setTimeout(() => item.remove(), 180);
    };
    item.querySelector('button').addEventListener('click', close);
    toastHost().appendChild(item);
    root.setTimeout(close, 4500);
    return item;
  }

  function openModal(options) {
    const settings = options || {};
    const overlay = document.createElement('div');
    overlay.className = 'urbanix-dialog-backdrop';
    overlay.innerHTML = `<section class="urbanix-dialog" role="dialog" aria-modal="true" aria-labelledby="urbanixDialogTitle"><header><div><small>${escapeHtml(settings.eyebrow || 'Urbanix ERP')}</small><h2 id="urbanixDialogTitle">${escapeHtml(settings.title || 'Detalhes')}</h2></div><button type="button" data-dialog-close aria-label="Fechar"><i class="bi bi-x-lg"></i></button></header><div class="urbanix-dialog-body"></div><footer></footer></section>`;
    const body = overlay.querySelector('.urbanix-dialog-body');
    if (settings.content instanceof Node) body.appendChild(settings.content);
    else body.textContent = settings.content || '';
    const footer = overlay.querySelector('footer');
    let resolver;
    let closed = false;
    const result = new Promise(resolve => { resolver = resolve; });
    const previouslyFocused = document.activeElement;
    const close = value => {
      if (closed) return;
      closed = true;
      document.removeEventListener('keydown', onKeydown);
      overlay.remove();
      document.body.classList.remove('dialog-open');
      if (previouslyFocused && previouslyFocused.focus) previouslyFocused.focus();
      resolver(value);
    };
    const onKeydown = event => {
      if (event.key === 'Escape') close(null);
      if (event.key === 'Tab') trapFocus(event, overlay);
    };
    (settings.actions || [{ label: 'Fechar', variant: 'outline', value: null }]).forEach(action => {
      const button = document.createElement('button');
      button.type = 'button';
      button.className = action.variant === 'primary' ? 'btn btn-primary-app' : action.variant === 'danger' ? 'btn btn-danger' : 'btn btn-outline-app';
      button.textContent = action.label;
      button.addEventListener('click', () => close(action.value));
      footer.appendChild(button);
    });
    document.body.appendChild(overlay);
    document.body.classList.add('dialog-open');
    overlay.querySelector('[data-dialog-close]').addEventListener('click', () => close(null));
    overlay.addEventListener('mousedown', event => { if (event.target === overlay) close(null); });
    document.addEventListener('keydown', onKeydown);
    root.setTimeout(() => overlay.querySelector('button, input, select, textarea, [tabindex="0"]')?.focus(), 0);
    return result;
  }

  function trapFocus(event, container) {
    const focusable = Array.from(container.querySelectorAll('button:not([disabled]), a[href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex="0"]'));
    if (!focusable.length) return;
    const first = focusable[0];
    const last = focusable[focusable.length - 1];
    if (event.shiftKey && document.activeElement === first) {
      event.preventDefault();
      last.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
      event.preventDefault();
      first.focus();
    }
  }

  function confirmAction(options) {
    const settings = typeof options === 'string' ? { title: options } : (options || {});
    return openModal({
      eyebrow: settings.eyebrow || 'Confirmação necessária',
      title: settings.title || 'Confirmar operação?',
      content: settings.message || 'Revise os dados antes de continuar.',
      actions: [
        { label: settings.cancelLabel || 'Cancelar', variant: 'outline', value: false },
        { label: settings.confirmLabel || 'Confirmar', variant: settings.danger ? 'danger' : 'primary', value: true }
      ]
    }).then(Boolean);
  }

  function normalize(value) {
    return String(value || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().trim();
  }

  function enhanceTable(table) {
    if (!table || table.dataset.enhanced === 'true') return;
    const body = table.tBodies[0];
    if (!body) return;
    const allRows = Array.from(body.rows);
    if (!allRows.length) return;
    table.dataset.enhanced = 'true';
    const responsive = table.closest('.table-responsive');
    const toolbar = document.createElement('div');
    toolbar.className = 'table-tools';
    toolbar.innerHTML = `<label class="table-search"><i class="bi bi-search"></i><span class="visually-hidden">Buscar na tabela</span><input type="search" class="form-control" placeholder="Buscar nesta tabela"></label><div class="table-page-size"><label>Mostrar <select class="form-select"><option>10</option><option>25</option><option>50</option><option>100</option></select></label><span data-table-count></span></div>`;
    responsive.parentNode.insertBefore(toolbar, responsive);
    const search = toolbar.querySelector('input');
    const pageSizeSelect = toolbar.querySelector('select');
    const count = toolbar.querySelector('[data-table-count]');
    let page = 1;
    let sortIndex = -1;
    let direction = 1;

    Array.from(table.tHead?.rows[0]?.cells || []).forEach((header, index) => {
      if (!header.textContent.trim()) return;
      header.tabIndex = 0;
      header.classList.add('sortable-column');
      header.setAttribute('aria-sort', 'none');
      const sort = () => {
        if (sortIndex === index) direction *= -1;
        else { sortIndex = index; direction = 1; }
        Array.from(table.tHead.rows[0].cells).forEach(cell => cell.setAttribute('aria-sort', 'none'));
        header.setAttribute('aria-sort', direction > 0 ? 'ascending' : 'descending');
        page = 1;
        render();
      };
      header.addEventListener('click', sort);
      header.addEventListener('keydown', event => { if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); sort(); } });
    });

    const pagination = document.createElement('nav');
    pagination.className = 'table-pagination';
    pagination.setAttribute('aria-label', 'Paginação da tabela');
    responsive.insertAdjacentElement('afterend', pagination);
    const noResults = document.createElement('div');
    noResults.className = 'table-empty';
    noResults.hidden = true;
    noResults.innerHTML = '<i class="bi bi-search"></i><strong>Nenhum registro encontrado.</strong><span>Ajuste o termo de busca e tente novamente.</span>';
    pagination.insertAdjacentElement('beforebegin', noResults);

    function render() {
      const term = normalize(search.value);
      let rows = allRows.filter(row => normalize(row.textContent).includes(term));
      if (sortIndex >= 0) {
        rows = rows.slice().sort((left, right) => normalize(left.cells[sortIndex]?.textContent).localeCompare(normalize(right.cells[sortIndex]?.textContent), 'pt-BR', { numeric: true }) * direction);
      }
      const pageSize = Number(pageSizeSelect.value);
      const pages = Math.max(1, Math.ceil(rows.length / pageSize));
      page = Math.min(page, pages);
      allRows.forEach(row => { row.hidden = true; });
      rows.slice((page - 1) * pageSize, page * pageSize).forEach(row => { row.hidden = false; body.appendChild(row); });
      noResults.hidden = rows.length > 0;
      responsive.hidden = rows.length === 0;
      count.textContent = `${rows.length} registro${rows.length === 1 ? '' : 's'}`;
      pagination.innerHTML = `<button type="button" ${page === 1 ? 'disabled' : ''} aria-label="Página anterior"><i class="bi bi-chevron-left"></i></button><span>Página ${page} de ${pages}</span><button type="button" ${page === pages ? 'disabled' : ''} aria-label="Próxima página"><i class="bi bi-chevron-right"></i></button>`;
      const buttons = pagination.querySelectorAll('button');
      buttons[0].addEventListener('click', () => { page -= 1; render(); });
      buttons[1].addEventListener('click', () => { page += 1; render(); });
    }

    search.addEventListener('input', () => { page = 1; render(); });
    pageSizeSelect.addEventListener('change', () => { page = 1; render(); });
    render();
  }

  function csvCell(value) {
    let text = String(value || '').replace(/\s+/g, ' ').trim();
    if (/^[=+\-@]/.test(text)) text = `'${text}`;
    return `"${text.replace(/"/g, '""')}"`;
  }

  function exportTableCsv(table, filename) {
    if (!table) throw new Error('Nenhuma tabela disponível para exportação.');
    const rows = Array.from(table.rows).filter(row => !row.hidden).map(row => Array.from(row.cells).map(cell => csvCell(cell.textContent)).join(';'));
    const blob = new Blob([`\ufeff${rows.join('\r\n')}`], { type: 'text/csv;charset=utf-8' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = `${filename || 'urbanix-exportacao'}.csv`;
    document.body.appendChild(link);
    link.click();
    const objectUrl = link.href;
    link.remove();
    root.setTimeout(() => URL.revokeObjectURL(objectUrl), 1000);
    showToast('Arquivo CSV gerado com os dados visíveis.', 'success', 'Exportação concluída');
  }

  function applyMasks(container) {
    (container || document).querySelectorAll('[data-mask]').forEach(input => {
      input.addEventListener('input', () => {
        const digits = input.value.replace(/\D/g, '');
        if (input.dataset.mask === 'cpf') input.value = digits.slice(0, 11).replace(/(\d{3})(\d)/, '$1.$2').replace(/(\d{3})(\d)/, '$1.$2').replace(/(\d{3})(\d{1,2})$/, '$1-$2');
        if (input.dataset.mask === 'cnpj') input.value = digits.slice(0, 14).replace(/(\d{2})(\d)/, '$1.$2').replace(/(\d{3})(\d)/, '$1.$2').replace(/(\d{3})(\d)/, '$1/$2').replace(/(\d{4})(\d{1,2})$/, '$1-$2');
        if (input.dataset.mask === 'phone') input.value = digits.slice(0, 11).replace(/(\d{2})(\d)/, '($1) $2').replace(/(\d{5})(\d)/, '$1-$2');
      });
    });
  }

  function emptyState(title, message, actionLabel) {
    const element = document.createElement('div');
    element.className = 'empty-state';
    element.innerHTML = `<i class="bi bi-inbox"></i><h3>${escapeHtml(title || 'Nenhum registro encontrado')}</h3><p>${escapeHtml(message || 'Ajuste os filtros ou crie o primeiro registro.')}</p>${actionLabel ? `<button type="button" class="btn btn-primary-app">${escapeHtml(actionLabel)}</button>` : ''}`;
    return element;
  }

  Urbanix.UI = Object.freeze({
    formatMoney,
    formatDate,
    escapeHtml,
    normalize,
    showToast,
    openModal,
    confirmAction,
    enhanceTable,
    exportTableCsv,
    applyMasks,
    emptyState
  });
})(window);
