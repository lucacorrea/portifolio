const APP_STATE = {
    options: { tipos: [], situacoes: [] },
    processPage: 1,
    userPage: 1,
    auditPage: 1,
    reportData: null,
    reportItems: [],
    users: [],
    deadlineItems: [],
    deadlineTab: 'urgent',
    currentPaymentProcess: null,
};
const CUSTOM_OPTION_VALUE = '__custom__';

document.addEventListener('DOMContentLoaded', () => {
    bindPasswordToggles();
    bindModalClosers();

    const loginForm = document.getElementById('login-form');
    if (loginForm) initLogin();

    const page = document.querySelector('[data-page]')?.dataset.page;
    if (page === 'dashboard') initDashboard();
    if (page === 'process-form') initProcessForm();
    if (page === 'deadlines') initDeadlines();
    if (page === 'reports') initReports();
    if (page === 'users') initUsers();
    if (page === 'settings') initSettings();
});

async function api(action, { method = 'GET', data = null, params = null } = {}) {
    const query = new URLSearchParams(params || {});
    let url = `api.php?acao=${encodeURIComponent(action)}`;
    if ([...query].length) url += `&${query.toString()}`;

    const config = { method, headers: {} };
    if (data !== null) {
        config.headers['Content-Type'] = 'application/json';
        config.body = JSON.stringify(data);
    }

    const response = await fetch(url, config);
    const contentType = response.headers.get('content-type') || '';
    const payload = contentType.includes('application/json') ? await response.json() : {};

    if (!response.ok || payload.status === 'erro') {
        throw new Error(payload.message || 'Erro ao executar operacao.');
    }

    return payload;
}

function toast(message, type = 'success') {
    const root = document.getElementById('toast-root');
    if (!root) return;

    const item = document.createElement('div');
    item.className = `toast ${type}`;
    item.textContent = message;
    root.appendChild(item);

    setTimeout(() => item.remove(), 3800);
}

function escapeHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function formatDate(value) {
    if (!value) return '-';
    const date = parseDateInput(value);
    if (Number.isNaN(date.getTime())) return value;
    return new Intl.DateTimeFormat('pt-BR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    }).format(date);
}

function formatDateOnly(value) {
    if (!value) return '-';
    const date = parseDateInput(value);
    if (Number.isNaN(date.getTime())) return value;
    return new Intl.DateTimeFormat('pt-BR').format(date);
}

function parseDateInput(value) {
    if (value instanceof Date) return value;
    const text = String(value || '').trim();
    if (/^\d{4}-\d{2}-\d{2}$/.test(text)) {
        return new Date(`${text}T12:00:00`);
    }
    return new Date(text.replace(' ', 'T'));
}

function formatCurrency(value) {
    const number = Number(value || 0);
    return new Intl.NumberFormat('pt-BR', {
        style: 'currency',
        currency: 'BRL',
    }).format(number);
}

function formatPercent(value) {
    const number = Number(value || 0);
    return `${number.toLocaleString('pt-BR', { minimumFractionDigits: 0, maximumFractionDigits: 2 })}%`;
}

function parseLocaleNumber(value) {
    let text = String(value || '').replace(/[^\d,.-]/g, '').trim();
    if (text.includes(',')) {
        text = text.replace(/\./g, '').replace(',', '.');
    }
    return Number(text || 0);
}

function daysUntil(dateValue) {
    if (!dateValue) return null;
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    const date = new Date(`${dateValue}T12:00:00`);
    if (Number.isNaN(date.getTime())) return null;
    return Math.ceil((date - today) / (1000 * 60 * 60 * 24));
}

function deadlineLabel(dateValue) {
    const days = daysUntil(dateValue);
    if (days === null) return '<span class="muted">Sem prazo</span>';

    let tone = 'ok';
    let label = `${days} dia(s)`;
    if (days < 0) {
        tone = 'danger';
        label = `Vencido ha ${Math.abs(days)} dia(s)`;
    } else if (days === 0) {
        tone = 'warning';
        label = 'Vence hoje';
    } else if (days <= 7) {
        tone = 'warning';
        label = `Vence em ${days} dia(s)`;
    }

    return `<span class="deadline-pill ${tone}">${label}</span>`;
}

function paymentBadge(item) {
    if (item.pago_em) {
        return `
            <span class="payment-pill paid">Pago</span>
            <span class="cell-sub">${formatCurrency(item.valor_cobrado)} em ${formatDateOnly(item.pago_em)}</span>
        `;
    }

    return '<span class="payment-pill pending">Pendente</span>';
}

function truncate(value, size = 90) {
    const text = String(value || '');
    return text.length > size ? `${text.slice(0, size - 1)}...` : text;
}

function debounce(fn, wait = 280) {
    let timer;
    return (...args) => {
        clearTimeout(timer);
        timer = setTimeout(() => fn(...args), wait);
    };
}

function bindPasswordToggles() {
    document.querySelectorAll('[data-toggle-password]').forEach((button) => {
        button.addEventListener('click', () => {
            const input = document.getElementById(button.dataset.togglePassword);
            const icon = button.querySelector('i');
            if (!input) return;
            input.type = input.type === 'password' ? 'text' : 'password';
            icon?.classList.toggle('fa-eye');
            icon?.classList.toggle('fa-eye-slash');
        });
    });
}

function bindModalClosers() {
    document.addEventListener('click', (event) => {
        if (event.target.closest('[data-close-modal]')) {
            document.getElementById('process-modal')?.setAttribute('hidden', '');
        }
        if (event.target.closest('[data-close-user-modal]')) {
            closeUserModal();
        }
        if (event.target.closest('[data-close-payment-modal]')) {
            closePaymentModal();
        }
        if (event.target.classList.contains('modal-backdrop')) {
            event.target.setAttribute('hidden', '');
        }
    });
}

function statusColor(name) {
    return APP_STATE.options.situacoes.find((item) => item.nome === name)?.cor || '#64748b';
}

function typeColor(name) {
    return APP_STATE.options.tipos.find((item) => item.nome === name)?.cor || '#2563eb';
}

function badge(label, color) {
    return `<span class="badge" style="background:${escapeHtml(color)}">${escapeHtml(label || '-')}</span>`;
}

async function loadOptions() {
    const response = await api('opcoes');
    APP_STATE.options.tipos = response.tipos || [];
    APP_STATE.options.situacoes = response.situacoes || [];
    return APP_STATE.options;
}

function fillSelect(select, items, placeholder, allowCustom = false) {
    if (!select) return;
    const current = select.value;
    select.innerHTML = `<option value="">${escapeHtml(placeholder)}</option>`;
    items.forEach((item) => {
        const option = document.createElement('option');
        option.value = item.nome;
        option.textContent = item.nome;
        select.appendChild(option);
    });
    if (allowCustom) {
        const customOption = document.createElement('option');
        customOption.value = CUSTOM_OPTION_VALUE;
        customOption.textContent = 'Personalizado';
        select.appendChild(customOption);
    }
    if ([...select.options].some((option) => option.value === current)) {
        select.value = current;
    }
}

function renderPagination(container, page, totalPages, onChange) {
    if (!container) return;
    container.innerHTML = '';
    if (totalPages <= 1) return;

    const addButton = (label, targetPage, active = false, disabled = false) => {
        const button = document.createElement('button');
        button.type = 'button';
        button.innerHTML = label;
        button.className = active ? 'active' : '';
        button.disabled = disabled;
        button.addEventListener('click', () => onChange(targetPage));
        container.appendChild(button);
    };

    addButton('<i class="fa-solid fa-chevron-left"></i>', page - 1, false, page === 1);

    const pages = paginationWindow(page, totalPages);
    pages.forEach((item) => {
        if (item === '...') {
            const span = document.createElement('span');
            span.textContent = '...';
            container.appendChild(span);
        } else {
            addButton(String(item), item, item === page);
        }
    });

    addButton('<i class="fa-solid fa-chevron-right"></i>', page + 1, false, page === totalPages);
}

function paginationWindow(page, totalPages) {
    if (totalPages <= 7) return Array.from({ length: totalPages }, (_, i) => i + 1);
    if (page <= 4) return [1, 2, 3, 4, 5, '...', totalPages];
    if (page >= totalPages - 3) return [1, '...', totalPages - 4, totalPages - 3, totalPages - 2, totalPages - 1, totalPages];
    return [1, '...', page - 1, page, page + 1, '...', totalPages];
}

function initLogin() {
    const form = document.getElementById('login-form');
    const error = document.getElementById('login-error');
    const button = document.getElementById('login-submit');

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        error.hidden = true;
        button.disabled = true;
        button.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin"></i> Entrando';

        try {
            await api('login', {
                method: 'POST',
                data: {
                    login: document.getElementById('login').value,
                    senha: document.getElementById('senha').value,
                },
            });
            window.location.href = 'index.php';
        } catch (err) {
            error.textContent = err.message;
            error.hidden = false;
            button.disabled = false;
            button.innerHTML = '<i class="fa-solid fa-right-to-bracket"></i> Entrar';
        }
    });
}

async function initDashboard() {
    await loadOptions();
    fillSelect(document.getElementById('filter-tipo'), APP_STATE.options.tipos, 'Todos os tipos');
    fillSelect(document.getElementById('filter-situacao'), APP_STATE.options.situacoes, 'Todas as situacoes');

    const refresh = debounce(() => {
        APP_STATE.processPage = 1;
        loadDashboard();
    });

    ['filter-q', 'filter-tipo', 'filter-situacao', 'filter-inicio', 'filter-fim', 'filter-sort'].forEach((id) => {
        document.getElementById(id)?.addEventListener(id === 'filter-q' ? 'input' : 'change', refresh);
    });

    document.getElementById('clear-filters')?.addEventListener('click', () => {
        ['filter-q', 'filter-tipo', 'filter-situacao', 'filter-inicio', 'filter-fim'].forEach((id) => {
            const el = document.getElementById(id);
            if (el) el.value = '';
        });
        document.getElementById('filter-sort').value = 'recentes';
        APP_STATE.processPage = 1;
        loadDashboard();
    });

    loadDashboard();
}

function dashboardParams() {
    return {
        q: document.getElementById('filter-q')?.value || '',
        tipo: document.getElementById('filter-tipo')?.value || '',
        situacao: document.getElementById('filter-situacao')?.value || '',
        inicio: document.getElementById('filter-inicio')?.value || '',
        fim: document.getElementById('filter-fim')?.value || '',
        sort: document.getElementById('filter-sort')?.value || 'recentes',
        page: APP_STATE.processPage,
        per_page: 10,
    };
}

async function loadDashboard() {
    try {
        const [summaryResponse, listResponse] = await Promise.all([
            api('resumo'),
            api('listar_processos', { params: dashboardParams() }),
        ]);

        const summary = summaryResponse.data;
        document.getElementById('stat-total').textContent = summary.total;
        document.getElementById('stat-andamento').textContent = summary.em_andamento;
        document.getElementById('stat-proximos').textContent = summary.proximos;
        document.getElementById('stat-pagos').textContent = summary.pagos;

        renderProcessTable(listResponse.data);
    } catch (err) {
        toast(err.message, 'error');
    }
}

function renderProcessTable(data) {
    const tbody = document.getElementById('process-table-body');
    const label = document.getElementById('process-count-label');
    if (!tbody) return;

    label.textContent = `${data.total} processo(s) encontrado(s)`;
    tbody.innerHTML = '';

    if (!data.items.length) {
        tbody.innerHTML = '<tr><td colspan="8" class="empty-row">Nenhum processo encontrado.</td></tr>';
        renderPagination(document.getElementById('process-pagination'), 1, 1, () => {});
        return;
    }

    data.items.forEach((item) => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>
                <span class="cell-title">${escapeHtml(item.cliente)}</span>
                <span class="cell-sub">Criado por ${escapeHtml(item.criado_por_nome || 'Sistema')}</span>
            </td>
            <td><strong>${escapeHtml(item.numero_processo)}</strong></td>
            <td>${badge(item.tipo_processo, typeColor(item.tipo_processo))}</td>
            <td>${badge(item.situacao, statusColor(item.situacao))}</td>
            <td>
                <strong>${formatDateOnly(item.data_prazo)}</strong>
                <span class="cell-sub">${deadlineLabel(item.data_prazo)}</span>
            </td>
            <td>${paymentBadge(item)}</td>
            <td>
                <span>${formatDate(item.atualizado_em || item.criado_em)}</span>
                <span class="cell-sub">${item.atualizado_por_nome ? `Atualizado por ${escapeHtml(item.atualizado_por_nome)}` : 'Cadastro inicial'}</span>
            </td>
            <td>
                <div class="row-actions">
                    <button class="icon-button" type="button" title="Visualizar" aria-label="Visualizar" data-view-process="${item.id}">
                        <i class="fa-solid fa-eye"></i>
                    </button>
                    <a class="icon-button" title="Editar" aria-label="Editar" href="cadastro.php?id=${item.id}">
                        <i class="fa-solid fa-pen-to-square"></i>
                    </a>
                    <button class="icon-button success" type="button" title="Pagar" aria-label="Pagar" data-pay-process="${item.id}">
                        <i class="fa-solid fa-money-bill-wave"></i>
                    </button>
                    <button class="icon-button danger" type="button" title="Excluir" aria-label="Excluir" data-delete-process="${item.id}">
                        <i class="fa-solid fa-trash"></i>
                    </button>
                </div>
            </td>
        `;
        tbody.appendChild(tr);
    });

    tbody.querySelectorAll('[data-view-process]').forEach((button) => {
        button.addEventListener('click', () => openProcessModal(button.dataset.viewProcess));
    });
    tbody.querySelectorAll('[data-delete-process]').forEach((button) => {
        button.addEventListener('click', () => deleteProcess(button.dataset.deleteProcess));
    });
    tbody.querySelectorAll('[data-pay-process]').forEach((button) => {
        const item = data.items.find((process) => Number(process.id) === Number(button.dataset.payProcess));
        button.addEventListener('click', () => openPaymentModal(item));
    });

    renderPagination(document.getElementById('process-pagination'), data.page, data.total_pages, (page) => {
        APP_STATE.processPage = page;
        loadDashboard();
    });
}

async function initDeadlines() {
    await loadOptions();
    fillSelect(document.getElementById('deadline-tipo'), APP_STATE.options.tipos, 'Todos os tipos');

    const monthInput = document.getElementById('deadline-month');
    if (monthInput && !monthInput.value) {
        monthInput.value = new Date().toISOString().slice(0, 7);
    }

    document.querySelectorAll('[data-deadline-tab]').forEach((button) => {
        button.addEventListener('click', () => {
            document.querySelectorAll('[data-deadline-tab]').forEach((tab) => tab.classList.remove('active'));
            button.classList.add('active');
            APP_STATE.deadlineTab = button.dataset.deadlineTab;
            renderDeadlines();
        });
    });

    ['deadline-q', 'deadline-tipo', 'deadline-payment', 'deadline-month'].forEach((id) => {
        document.getElementById(id)?.addEventListener(id === 'deadline-q' ? 'input' : 'change', renderDeadlines);
    });

    loadDeadlines();
}

async function loadDeadlines() {
    try {
        const response = await api('prazos_processos');
        APP_STATE.deadlineItems = response.data.items || [];
        renderDeadlines();
    } catch (err) {
        toast(err.message, 'error');
    }
}

function renderDeadlines() {
    const tbody = document.getElementById('deadline-table-body');
    if (!tbody) return;

    const filtered = filterDeadlines();
    updateDeadlineStats();
    updateDeadlineHeading(filtered.length);

    tbody.innerHTML = '';
    if (!filtered.length) {
        tbody.innerHTML = '<tr><td colspan="7" class="empty-row">Nenhum prazo encontrado nesta categoria.</td></tr>';
        return;
    }

    filtered.forEach((item) => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>
                <span class="cell-title">${escapeHtml(item.cliente)}</span>
                <span class="cell-sub">${escapeHtml(item.criado_por_nome || 'Sistema')}</span>
            </td>
            <td><strong>${escapeHtml(item.numero_processo)}</strong></td>
            <td>${badge(item.tipo_processo, typeColor(item.tipo_processo))}</td>
            <td>${badge(item.situacao, statusColor(item.situacao))}</td>
            <td>
                <strong>${formatDateOnly(item.data_prazo)}</strong>
                <span class="cell-sub">${deadlineLabel(item.data_prazo)}</span>
            </td>
            <td>${paymentBadge(item)}</td>
            <td>
                <div class="row-actions">
                    <button class="icon-button" type="button" title="Visualizar" aria-label="Visualizar" data-view-process="${item.id}">
                        <i class="fa-solid fa-eye"></i>
                    </button>
                    <a class="icon-button" title="Editar" aria-label="Editar" href="cadastro.php?id=${item.id}">
                        <i class="fa-solid fa-pen-to-square"></i>
                    </a>
                    <button class="icon-button success" type="button" title="Pagar" aria-label="Pagar" data-pay-process="${item.id}">
                        <i class="fa-solid fa-money-bill-wave"></i>
                    </button>
                </div>
            </td>
        `;
        tbody.appendChild(tr);
    });

    tbody.querySelectorAll('[data-view-process]').forEach((button) => {
        button.addEventListener('click', () => openProcessModal(button.dataset.viewProcess));
    });
    tbody.querySelectorAll('[data-pay-process]').forEach((button) => {
        const item = filtered.find((process) => Number(process.id) === Number(button.dataset.payProcess));
        button.addEventListener('click', () => openPaymentModal(item));
    });
}

function filterDeadlines() {
    const query = (document.getElementById('deadline-q')?.value || '').toLowerCase();
    const type = document.getElementById('deadline-tipo')?.value || '';
    const payment = document.getElementById('deadline-payment')?.value || '';
    const month = document.getElementById('deadline-month')?.value || '';

    return APP_STATE.deadlineItems
        .filter((item) => {
            const days = daysUntil(item.data_prazo);
            const finalized = isFinalized(item);

            if (APP_STATE.deadlineTab === 'urgent' && (finalized || days === null || days < 0 || days > 7)) return false;
            if (APP_STATE.deadlineTab === 'overdue' && (finalized || days === null || days >= 0)) return false;
            if (APP_STATE.deadlineTab === 'month' && (!item.data_prazo || !item.data_prazo.startsWith(month))) return false;
            if (type && item.tipo_processo !== type) return false;
            if (payment === 'pending' && item.pago_em) return false;
            if (payment === 'paid' && !item.pago_em) return false;

            if (query) {
                const haystack = `${item.cliente} ${item.numero_processo} ${item.tipo_processo} ${item.situacao}`.toLowerCase();
                if (!haystack.includes(query)) return false;
            }

            return true;
        })
        .sort((a, b) => String(a.data_prazo || '').localeCompare(String(b.data_prazo || '')));
}

function isFinalized(item) {
    const status = APP_STATE.options.situacoes.find((entry) => entry.nome === item.situacao);
    return Number(status?.finalizadora || 0) === 1 || ['concluido', 'arquivado', 'finalizado'].includes(String(item.situacao || '').toLowerCase());
}

function updateDeadlineStats() {
    const activeItems = APP_STATE.deadlineItems.filter((item) => !isFinalized(item));
    const urgent = activeItems.filter((item) => {
        const days = daysUntil(item.data_prazo);
        return days !== null && days >= 0 && days <= 7;
    }).length;
    const overdue = activeItems.filter((item) => {
        const days = daysUntil(item.data_prazo);
        return days !== null && days < 0;
    }).length;
    const paid = APP_STATE.deadlineItems.filter((item) => item.pago_em).length;

    document.getElementById('deadline-stat-urgent').textContent = urgent;
    document.getElementById('deadline-stat-overdue').textContent = overdue;
    document.getElementById('deadline-stat-paid').textContent = paid;
}

function updateDeadlineHeading(total) {
    const titleMap = {
        urgent: 'Prazos urgentes',
        overdue: 'Prazos vencidos',
        month: 'Cronograma mensal',
    };
    document.getElementById('deadline-title').textContent = titleMap[APP_STATE.deadlineTab] || 'Prazos';
    document.getElementById('deadline-count-label').textContent = `${total} processo(s) encontrado(s)`;
}

async function openProcessModal(id) {
    try {
        const response = await api('obter_processo', { params: { id } });
        const p = response.data;
        const modal = document.getElementById('process-modal');
        const body = document.getElementById('process-modal-body');

        body.innerHTML = `
            <div class="detail-grid">
                <div class="detail-item">
                    <small>Cliente</small>
                    <strong>${escapeHtml(p.cliente)}</strong>
                </div>
                <div class="detail-item">
                    <small>Numero</small>
                    <strong>${escapeHtml(p.numero_processo)}</strong>
                </div>
                <div class="detail-item">
                    <small>Tipo</small>
                    <strong>${badge(p.tipo_processo, typeColor(p.tipo_processo))}</strong>
                </div>
                <div class="detail-item">
                    <small>Situacao</small>
                    <strong>${badge(p.situacao, statusColor(p.situacao))}</strong>
                </div>
                <div class="detail-item">
                    <small>Prazo</small>
                    <strong>${formatDateOnly(p.data_prazo)}</strong>
                    <p>${deadlineLabel(p.data_prazo)}</p>
                </div>
                <div class="detail-item">
                    <small>Pagamento</small>
                    <strong>${p.pago_em ? formatCurrency(p.valor_cobrado) : 'Pendente'}</strong>
                    <p>${p.pago_em ? `${formatPercent(p.porcentagem_cobrada)} sobre ${formatCurrency(p.valor_processo)}` : 'Nenhum pagamento registrado.'}</p>
                </div>
                <div class="detail-item">
                    <small>Cadastro</small>
                    <strong>${formatDate(p.criado_em)}</strong>
                </div>
                <div class="detail-item">
                    <small>Ultima atualizacao</small>
                    <strong>${formatDate(p.atualizado_em || p.criado_em)}</strong>
                </div>
                <div class="detail-item full">
                    <small>Observacao</small>
                    <p>${escapeHtml(p.observacao || 'Nenhuma observacao registrada.')}</p>
                </div>
            </div>
        `;
        modal.hidden = false;
    } catch (err) {
        toast(err.message, 'error');
    }
}

async function deleteProcess(id) {
    if (!confirm('Deseja excluir este processo?')) return;
    try {
        const response = await api('excluir_processo', { method: 'DELETE', params: { id } });
        toast(response.message);
        loadDashboard();
    } catch (err) {
        toast(err.message, 'error');
    }
}

function initPaymentForm() {
    const form = document.getElementById('payment-form');
    if (!form || form.dataset.bound === '1') return;

    form.dataset.bound = '1';
    ['payment-valor', 'payment-percentual'].forEach((id) => {
        document.getElementById(id)?.addEventListener('input', updatePaymentCalculation);
    });
    form.addEventListener('submit', savePayment);
}

function openPaymentModal(process) {
    if (!process) return;
    initPaymentForm();

    APP_STATE.currentPaymentProcess = process;
    document.getElementById('payment-process-id').value = process.id;
    document.getElementById('payment-process-label').textContent = `${process.cliente} - ${process.numero_processo}`;
    document.getElementById('payment-valor').value = process.valor_processo || '';
    document.getElementById('payment-percentual').value = process.porcentagem_cobrada || '';
    updatePaymentCalculation();
    document.getElementById('payment-modal').hidden = false;
}

function closePaymentModal() {
    document.getElementById('payment-modal')?.setAttribute('hidden', '');
}

function updatePaymentCalculation() {
    const valor = parseLocaleNumber(document.getElementById('payment-valor')?.value || 0);
    const percentual = parseLocaleNumber(document.getElementById('payment-percentual')?.value || 0);
    const total = (valor * percentual) / 100;
    const output = document.getElementById('payment-total');
    if (output) output.value = formatCurrency(total);
}

async function savePayment(event) {
    event.preventDefault();

    try {
        const response = await api('pagar_processo', {
            method: 'POST',
            data: {
                id: document.getElementById('payment-process-id').value,
                valor_processo: document.getElementById('payment-valor').value,
                porcentagem_cobrada: document.getElementById('payment-percentual').value,
            },
        });
        toast(`${response.message} Valor: ${formatCurrency(response.data.valor_cobrado)}`);
        closePaymentModal();

        if (document.querySelector('[data-page="deadlines"]')) {
            loadDeadlines();
        } else if (document.querySelector('[data-page="dashboard"]')) {
            loadDashboard();
        }
    } catch (err) {
        toast(err.message, 'error');
    }
}

async function initProcessForm() {
    await loadOptions();
    fillSelect(document.getElementById('tipo_processo'), APP_STATE.options.tipos, 'Selecione...', true);
    fillSelect(document.getElementById('situacao'), APP_STATE.options.situacoes, 'Selecione...', true);
    setupCustomSelect('tipo_processo', 'tipo_processo_personalizado');
    setupCustomSelect('situacao', 'situacao_personalizada');

    const processId = Number(document.querySelector('[data-process-id]')?.dataset.processId || 0);
    if (processId > 0) {
        await loadProcessForEdit(processId);
    }

    ['cliente', 'numero_processo', 'data_prazo', 'tipo_processo', 'situacao', 'tipo_processo_personalizado', 'situacao_personalizada'].forEach((id) => {
        document.getElementById(id)?.addEventListener('input', updatePreview);
        document.getElementById(id)?.addEventListener('change', updatePreview);
    });
    updatePreview();

    document.getElementById('process-form').addEventListener('submit', saveProcessForm);
}

async function loadProcessForEdit(id) {
    try {
        const response = await api('obter_processo', { params: { id } });
        const p = response.data;
        document.getElementById('processo-id').value = p.id;
        document.getElementById('cliente').value = p.cliente || '';
        document.getElementById('numero_processo').value = p.numero_processo || '';
        document.getElementById('data_prazo').value = p.data_prazo || '';
        setSelectOrCustomValue('tipo_processo', 'tipo_processo_personalizado', p.tipo_processo || '');
        setSelectOrCustomValue('situacao', 'situacao_personalizada', p.situacao || '');
        document.getElementById('observacao').value = p.observacao || '';
        updatePreview();
    } catch (err) {
        toast(err.message, 'error');
    }
}

function setupCustomSelect(selectId, inputId) {
    const select = document.getElementById(selectId);
    const input = document.getElementById(inputId);
    if (!select || !input) return;

    const toggle = () => {
        const isCustom = select.value === CUSTOM_OPTION_VALUE;
        input.hidden = !isCustom;
        input.required = isCustom;
        if (isCustom) input.focus();
        if (!isCustom) input.value = '';
        updatePreview();
    };

    select.addEventListener('change', toggle);
    toggle();
}

function setSelectOrCustomValue(selectId, inputId, value) {
    const select = document.getElementById(selectId);
    const input = document.getElementById(inputId);
    if (!select || !input) return;

    const exists = [...select.options].some((option) => option.value === value);
    if (exists) {
        select.value = value;
        input.value = '';
        input.hidden = true;
        input.required = false;
        return;
    }

    select.value = CUSTOM_OPTION_VALUE;
    input.value = value;
    input.hidden = false;
    input.required = true;
}

function getCustomSelectValue(selectId, inputId, label) {
    const select = document.getElementById(selectId);
    const input = document.getElementById(inputId);
    if (!select) return '';

    if (select.value !== CUSTOM_OPTION_VALUE) {
        return select.value;
    }

    const value = (input?.value || '').trim();
    if (!value) {
        throw new Error(`Informe o ${label} personalizado.`);
    }

    return value;
}

function updatePreview() {
    const simplePairs = [
        ['preview-cliente', 'cliente'],
        ['preview-numero', 'numero_processo'],
        ['preview-prazo', 'data_prazo'],
    ];
    simplePairs.forEach(([previewId, inputId]) => {
        const preview = document.getElementById(previewId);
        const input = document.getElementById(inputId);
        if (preview && input) {
            preview.textContent = inputId === 'data_prazo' ? formatDateOnly(input.value) : (input.value || '-');
        }
    });

    const previewTipo = document.getElementById('preview-tipo');
    const previewSituacao = document.getElementById('preview-situacao');
    if (previewTipo) {
        previewTipo.textContent = getPreviewSelectValue('tipo_processo', 'tipo_processo_personalizado') || '-';
    }
    if (previewSituacao) {
        previewSituacao.textContent = getPreviewSelectValue('situacao', 'situacao_personalizada') || '-';
    }
}

function getPreviewSelectValue(selectId, inputId) {
    const select = document.getElementById(selectId);
    const input = document.getElementById(inputId);
    if (!select) return '';
    return select.value === CUSTOM_OPTION_VALUE ? (input?.value || '') : select.value;
}

async function saveProcessForm(event) {
    event.preventDefault();
    const button = document.getElementById('process-submit');
    button.disabled = true;
    button.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin"></i> Salvando';

    try {
        const payload = {
            id: document.getElementById('processo-id').value,
            cliente: document.getElementById('cliente').value,
            numero_processo: document.getElementById('numero_processo').value,
            data_prazo: document.getElementById('data_prazo').value,
            tipo_processo: getCustomSelectValue('tipo_processo', 'tipo_processo_personalizado', 'tipo de processo'),
            situacao: getCustomSelectValue('situacao', 'situacao_personalizada', 'situacao'),
            observacao: document.getElementById('observacao').value,
        };
        const response = await api('salvar_processo', { method: 'POST', data: payload });
        toast(response.message);
        setTimeout(() => { window.location.href = 'index.php'; }, 650);
    } catch (err) {
        toast(err.message, 'error');
        button.disabled = false;
        button.innerHTML = '<i class="fa-solid fa-floppy-disk"></i> Salvar processo';
    }
}

async function initReports() {
    await loadOptions();
    fillSelect(document.getElementById('report-tipo'), APP_STATE.options.tipos, 'Todos os tipos');
    fillSelect(document.getElementById('report-situacao'), APP_STATE.options.situacoes, 'Todas as situacoes');

    const refresh = debounce(() => loadReport(), 320);

    ['report-q', 'report-tipo', 'report-situacao', 'report-mes', 'report-inicio', 'report-fim'].forEach((id) => {
        document.getElementById(id)?.addEventListener(id === 'report-q' ? 'input' : 'change', refresh);
    });

    document.getElementById('report-mes')?.addEventListener('change', () => {
        if (!document.getElementById('report-mes')?.value) return;
        ['report-inicio', 'report-fim'].forEach((id) => {
            const el = document.getElementById(id);
            if (el) el.value = '';
        });
    });

    ['report-inicio', 'report-fim'].forEach((id) => {
        document.getElementById(id)?.addEventListener('change', () => {
            const month = document.getElementById('report-mes');
            if (month) month.value = '';
        });
    });

    document.getElementById('report-generate')?.addEventListener('click', loadReport);
    document.getElementById('report-clear')?.addEventListener('click', () => {
        ['report-q', 'report-tipo', 'report-situacao', 'report-mes', 'report-inicio', 'report-fim'].forEach((id) => {
            const el = document.getElementById(id);
            if (el) el.value = '';
        });
        loadReport();
    });
    document.getElementById('report-csv')?.addEventListener('click', exportReportCsv);
    document.getElementById('report-print')?.addEventListener('click', printReport);

    loadReport();
}

function reportParams() {
    const month = document.getElementById('report-mes')?.value || '';
    const monthStart = month ? `${month}-01` : '';
    const monthEnd = month ? new Date(Number(month.slice(0, 4)), Number(month.slice(5, 7)), 0).toISOString().slice(0, 10) : '';

    return {
        q: document.getElementById('report-q')?.value || '',
        tipo: document.getElementById('report-tipo')?.value || '',
        situacao: document.getElementById('report-situacao')?.value || '',
        inicio: monthStart || document.getElementById('report-inicio')?.value || '',
        fim: monthEnd || document.getElementById('report-fim')?.value || '',
        date_by: 'pagamento',
        sort: 'recentes',
    };
}

async function loadReport() {
    try {
        const response = await api('relatorio_processos', { params: reportParams() });
        const data = response.data;
        APP_STATE.reportData = data;
        APP_STATE.reportItems = data.items || [];
        renderReport(data);
    } catch (err) {
        toast(err.message, 'error');
    }
}

function renderReport(data) {
    document.getElementById('report-count-label').textContent = `${data.total} processo(s) no recorte atual`;
    document.getElementById('report-total').textContent = data.total;
    document.getElementById('report-revenue').textContent = formatCurrency(data.summary.financeiro?.faturamento_total || 0);
    document.getElementById('report-paid').textContent = data.summary.financeiro?.processos_pagos || 0;
    document.getElementById('report-average').textContent = formatCurrency(data.summary.financeiro?.ticket_medio || 0);
    document.getElementById('report-types').textContent = data.summary.por_tipo.length;
    document.getElementById('report-statuses').textContent = data.summary.por_situacao.length;

    renderBars(document.getElementById('report-by-status'), data.summary.por_situacao, statusColor);
    renderBars(document.getElementById('report-by-type'), data.summary.por_tipo, typeColor);
    renderReportTable(data.items);
    updateReportPrintHeader(data);
}

function renderBars(container, items, colorFn) {
    if (!container) return;
    container.innerHTML = '';
    if (!items.length) {
        container.innerHTML = '<p class="muted">Sem dados.</p>';
        return;
    }
    const max = Math.max(...items.map((item) => Number(item.total)));
    items.forEach((item) => {
        const percent = max ? Math.max(6, (Number(item.total) / max) * 100) : 0;
        const row = document.createElement('div');
        row.className = 'bar-item';
        row.innerHTML = `
            <div class="bar-meta"><span>${escapeHtml(item.nome)}</span><span>${item.total}</span></div>
            <div class="bar-track"><div class="bar-fill" style="width:${percent}%;background:${colorFn(item.nome)}"></div></div>
        `;
        container.appendChild(row);
    });
}

function renderReportTable(items) {
    const tbody = document.getElementById('report-table-body');
    tbody.innerHTML = '';

    if (!items.length) {
        tbody.innerHTML = '<tr><td colspan="8" class="empty-row">Nenhum processo encontrado.</td></tr>';
        return;
    }

    items.forEach((item) => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>${escapeHtml(item.cliente)}</td>
            <td>${escapeHtml(item.numero_processo)}</td>
            <td>${escapeHtml(item.tipo_processo)}</td>
            <td>${escapeHtml(item.situacao)}</td>
            <td>${formatDateOnly(item.data_prazo)}</td>
            <td>${reportPaymentCell(item)}</td>
            <td>${formatDateOnly(item.criado_em)}</td>
            <td>${escapeHtml(truncate(item.observacao, 120))}</td>
        `;
        tbody.appendChild(tr);
    });
}

function reportPaymentCell(item) {
    if (!item.pago_em) {
        return '<span class="payment-pill pending">Pendente</span>';
    }

    return `
        <strong>${formatCurrency(item.valor_cobrado)}</strong>
        <span class="cell-sub">Pago em ${formatDateOnly(item.pago_em)}</span>
    `;
}

function printReport() {
    if (!APP_STATE.reportData) {
        toast('Gere um relatorio antes de imprimir.', 'info');
        return;
    }

    updateReportPrintHeader(APP_STATE.reportData);
    window.print();
}

function updateReportPrintHeader(data = APP_STATE.reportData) {
    const generated = document.getElementById('report-print-generated');
    const filters = document.getElementById('report-print-filters');
    if (generated) generated.textContent = formatDate(new Date());
    if (filters) filters.textContent = currentReportFilters().join(' | ');

    const count = document.getElementById('report-count-label');
    if (count && data) count.textContent = `${data.total} processo(s) no recorte atual`;
}

function currentReportFilters() {
    const filters = [];
    const search = document.getElementById('report-q')?.value.trim();
    const type = document.getElementById('report-tipo')?.value || '';
    const status = document.getElementById('report-situacao')?.value || '';
    const month = document.getElementById('report-mes')?.value || '';
    const start = document.getElementById('report-inicio')?.value || '';
    const end = document.getElementById('report-fim')?.value || '';

    if (search) filters.push(`Busca: ${search}`);
    if (type) filters.push(`Tipo: ${type}`);
    if (status) filters.push(`Situacao: ${status}`);
    if (month) filters.push(`Mes de pagamento: ${formatMonthLabel(month)}`);
    if (!month && (start || end)) {
        filters.push(`Pagamento: ${start ? formatDateOnly(start) : 'inicio'} ate ${end ? formatDateOnly(end) : 'hoje'}`);
    }

    return filters.length ? filters : ['Todos os processos'];
}

function formatMonthLabel(value) {
    const [year, month] = String(value || '').split('-').map(Number);
    if (!year || !month) return value || '-';
    return new Intl.DateTimeFormat('pt-BR', { month: 'long', year: 'numeric' }).format(new Date(year, month - 1, 1));
}

function exportReportCsv() {
    if (!APP_STATE.reportItems.length) {
        toast('Gere um relatorio antes de exportar.', 'info');
        return;
    }

    const rows = buildReportCsvRows(APP_STATE.reportData);
    const csv = ['sep=;', ...rows.map((row) => row.map(csvCell).join(';'))].join('\r\n');
    const blob = new Blob([`\uFEFF${csv}`], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = `relatorio-processos-${new Date().toISOString().slice(0, 10)}.csv`;
    document.body.appendChild(link);
    link.click();
    link.remove();
    URL.revokeObjectURL(url);
}

function buildReportCsvRows(data) {
    const report = data || { items: APP_STATE.reportItems, total: APP_STATE.reportItems.length, summary: {} };
    const summary = report.summary || {};
    const finance = summary.financeiro || {};
    const rows = [
        ['Relatorio de Processos'],
        ['Gerado em', formatDate(new Date())],
        ['Filtros aplicados', currentReportFilters().join(' | ')],
        ['Total de registros', report.total ?? report.items.length],
        [],
        ['Resumo financeiro'],
        ['Faturamento filtrado', formatCurrency(finance.faturamento_total || 0)],
        ['Processos pagos', finance.processos_pagos || 0],
        ['Ticket medio', formatCurrency(finance.ticket_medio || 0)],
        ['Valor pendente', formatCurrency(finance.valor_pendente || 0)],
        [],
        ['Quebra por situacao'],
        ['Situacao', 'Total'],
        ...(summary.por_situacao || []).map((item) => [item.nome, item.total]),
        [],
        ['Quebra por tipo'],
        ['Tipo', 'Total'],
        ...(summary.por_tipo || []).map((item) => [item.nome, item.total]),
        [],
        ['Listagem de processos'],
        [
            'Cliente',
            'Numero do Processo',
            'Tipo',
            'Situacao',
            'Prazo',
            'Status do Pagamento',
            'Data do Pagamento',
            'Valor do Processo',
            'Porcentagem Cobrada',
            'Valor Cobrado',
            'Cadastro',
            'Criado Por',
            'Atualizado Por',
            'Pago Por',
            'Observacao',
        ],
    ];

    (report.items || []).forEach((item) => {
        rows.push([
            item.cliente,
            item.numero_processo,
            item.tipo_processo,
            item.situacao,
            formatDateOnly(item.data_prazo),
            item.pago_em ? 'Pago' : 'Pendente',
            item.pago_em ? formatDateOnly(item.pago_em) : '-',
            reportCurrency(item.valor_processo),
            reportPercent(item.porcentagem_cobrada),
            item.pago_em ? reportCurrency(item.valor_cobrado) : '-',
            formatDateOnly(item.criado_em),
            item.criado_por_nome || 'Sistema',
            item.atualizado_por_nome || '-',
            item.pago_por_nome || '-',
            item.observacao || '',
        ]);
    });

    return rows;
}

function reportCurrency(value) {
    return value === null || value === undefined || value === '' ? '-' : formatCurrency(value);
}

function reportPercent(value) {
    return value === null || value === undefined || value === '' ? '-' : formatPercent(value);
}

function csvCell(value) {
    let text = String(value ?? '').replace(/\r?\n/g, ' ').trim();
    if (/^[=+\-@]/.test(text)) text = `'${text}`;
    return `"${text.replace(/"/g, '""')}"`;
}

async function initUsers() {
    const refresh = debounce(() => {
        APP_STATE.userPage = 1;
        loadUsers();
    });
    document.getElementById('user-q')?.addEventListener('input', refresh);
    document.getElementById('new-user')?.addEventListener('click', () => openUserModal());
    document.getElementById('user-form')?.addEventListener('submit', saveUser);
    loadUsers();
}

async function loadUsers() {
    try {
        const response = await api('listar_usuarios', {
            params: {
                q: document.getElementById('user-q')?.value || '',
                page: APP_STATE.userPage,
                per_page: 10,
            },
        });
        renderUsers(response.data);
    } catch (err) {
        toast(err.message, 'error');
    }
}

function renderUsers(data) {
    APP_STATE.users = data.items;
    document.getElementById('user-count-label').textContent = `${data.total} usuario(s) ativo(s)`;

    const tbody = document.getElementById('user-table-body');
    tbody.innerHTML = '';

    if (!data.items.length) {
        tbody.innerHTML = '<tr><td colspan="5" class="empty-row">Nenhum usuario encontrado.</td></tr>';
    } else {
        data.items.forEach((user) => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td><strong>${escapeHtml(user.nome)}</strong></td>
                <td>${escapeHtml(user.login)}</td>
                <td>${badge(user.perfil === 'suporte' ? 'Suporte' : 'Normal', user.perfil === 'suporte' ? '#203040' : '#0f766e')}</td>
                <td>${formatDate(user.ultimo_acesso)}</td>
                <td>
                    <div class="row-actions">
                        <button class="icon-button" type="button" title="Editar" aria-label="Editar" data-edit-user="${user.id}">
                            <i class="fa-solid fa-user-pen"></i>
                        </button>
                        <button class="icon-button danger" type="button" title="Desativar" aria-label="Desativar" data-delete-user="${user.id}">
                            <i class="fa-solid fa-user-slash"></i>
                        </button>
                    </div>
                </td>
            `;
            tbody.appendChild(tr);
        });
    }

    tbody.querySelectorAll('[data-edit-user]').forEach((button) => {
        button.addEventListener('click', () => openUserModal(button.dataset.editUser));
    });
    tbody.querySelectorAll('[data-delete-user]').forEach((button) => {
        button.addEventListener('click', () => deactivateUser(button.dataset.deleteUser));
    });

    renderPagination(document.getElementById('user-pagination'), data.page, data.total_pages, (page) => {
        APP_STATE.userPage = page;
        loadUsers();
    });
}

function openUserModal(id = null) {
    const modal = document.getElementById('user-modal');
    const form = document.getElementById('user-form');
    const title = document.getElementById('user-modal-title');
    const passwordInput = document.getElementById('user-senha');
    const passwordHelp = document.getElementById('user-senha-help');
    form.reset();
    document.getElementById('user-id').value = '';
    passwordInput.required = true;
    passwordInput.placeholder = 'Digite a senha do usuario';
    if (passwordHelp) passwordHelp.textContent = 'Senha obrigatoria para novo usuario.';
    title.textContent = 'Novo Usuario';

    if (id) {
        const user = APP_STATE.users.find((item) => Number(item.id) === Number(id));
        if (!user) return;
        document.getElementById('user-id').value = user.id;
        document.getElementById('user-nome').value = user.nome;
        document.getElementById('user-login').value = user.login;
        document.getElementById('user-perfil').value = user.perfil;
        passwordInput.required = false;
        passwordInput.placeholder = 'Deixe em branco para manter a senha atual';
        if (passwordHelp) passwordHelp.textContent = 'Por seguranca, a senha atual nao e exibida. Preencha apenas se quiser trocar.';
        title.textContent = 'Editar Usuario';
    }

    modal.hidden = false;
}

function closeUserModal() {
    document.getElementById('user-modal')?.setAttribute('hidden', '');
}

async function saveUser(event) {
    event.preventDefault();
    try {
        const response = await api('salvar_usuario', {
            method: 'POST',
            data: {
                id: document.getElementById('user-id').value,
                nome: document.getElementById('user-nome').value,
                login: document.getElementById('user-login').value,
                senha: document.getElementById('user-senha').value,
                perfil: document.getElementById('user-perfil').value,
            },
        });
        toast(response.message);
        closeUserModal();
        loadUsers();
    } catch (err) {
        toast(err.message, 'error');
    }
}

async function deactivateUser(id) {
    if (!confirm('Deseja desativar este usuario?')) return;
    try {
        const response = await api('excluir_usuario', { method: 'DELETE', params: { id } });
        toast(response.message);
        loadUsers();
    } catch (err) {
        toast(err.message, 'error');
    }
}

async function initSettings() {
    await loadOptions();
    renderCatalogs();

    document.getElementById('type-form')?.addEventListener('submit', (event) => saveCatalog(event, 'tipo'));
    document.getElementById('status-form')?.addEventListener('submit', (event) => saveCatalog(event, 'situacao'));

    const refreshAudit = debounce(() => {
        APP_STATE.auditPage = 1;
        loadAudit();
    });
    document.getElementById('audit-q')?.addEventListener('input', refreshAudit);
    loadAudit();
}

function renderCatalogs() {
    renderCatalogList('type-list', APP_STATE.options.tipos, 'tipo');
    renderCatalogList('status-list', APP_STATE.options.situacoes, 'situacao');
}

function renderCatalogList(containerId, items, kind) {
    const container = document.getElementById(containerId);
    container.innerHTML = '';

    items.forEach((item) => {
        const chip = document.createElement('div');
        chip.className = 'config-chip';
        chip.innerHTML = `
            <span class="dot" style="background:${escapeHtml(item.cor)}"></span>
            <strong>${escapeHtml(item.nome)}</strong>
            ${kind === 'situacao' && Number(item.finalizadora) === 1 ? '<small class="muted">final</small>' : ''}
            <button type="button" title="Editar" aria-label="Editar" data-edit-catalog="${kind}" data-id="${item.id}">
                <i class="fa-solid fa-pen"></i>
            </button>
            <button type="button" title="Desativar" aria-label="Desativar" data-delete-catalog="${kind}" data-id="${item.id}">
                <i class="fa-solid fa-xmark"></i>
            </button>
        `;
        container.appendChild(chip);
    });

    container.querySelectorAll('[data-edit-catalog]').forEach((button) => {
        button.addEventListener('click', () => editCatalogItem(button.dataset.editCatalog, button.dataset.id));
    });
    container.querySelectorAll('[data-delete-catalog]').forEach((button) => {
        button.addEventListener('click', () => deleteCatalogItem(button.dataset.deleteCatalog, button.dataset.id));
    });
}

function editCatalogItem(kind, id) {
    const collection = kind === 'tipo' ? APP_STATE.options.tipos : APP_STATE.options.situacoes;
    const item = collection.find((entry) => Number(entry.id) === Number(id));
    if (!item) return;

    if (kind === 'tipo') {
        document.getElementById('type-id').value = item.id;
        document.getElementById('type-name').value = item.nome;
        document.getElementById('type-color').value = item.cor;
        document.getElementById('type-order').value = item.ordem || 0;
    } else {
        document.getElementById('status-id').value = item.id;
        document.getElementById('status-name').value = item.nome;
        document.getElementById('status-color').value = item.cor;
        document.getElementById('status-final').checked = Number(item.finalizadora) === 1;
        document.getElementById('status-order').value = item.ordem || 0;
    }
}

async function saveCatalog(event, kind) {
    event.preventDefault();
    const isType = kind === 'tipo';
    const prefix = isType ? 'type' : 'status';
    const action = isType ? 'salvar_tipo' : 'salvar_situacao';
    const payload = {
        id: document.getElementById(`${prefix}-id`).value,
        nome: document.getElementById(`${prefix}-name`).value,
        cor: document.getElementById(`${prefix}-color`).value,
        ordem: document.getElementById(`${prefix}-order`).value,
    };
    if (!isType) payload.finalizadora = document.getElementById('status-final').checked;

    try {
        const response = await api(action, { method: 'POST', data: payload });
        toast(response.message);
        event.target.reset();
        document.getElementById(`${prefix}-id`).value = '';
        if (isType) document.getElementById('type-color').value = '#2563eb';
        if (!isType) document.getElementById('status-color').value = '#64748b';
        await loadOptions();
        renderCatalogs();
    } catch (err) {
        toast(err.message, 'error');
    }
}

async function deleteCatalogItem(kind, id) {
    if (!confirm('Deseja desativar este item?')) return;
    try {
        const action = kind === 'tipo' ? 'excluir_tipo' : 'excluir_situacao';
        const response = await api(action, { method: 'DELETE', params: { id } });
        toast(response.message);
        await loadOptions();
        renderCatalogs();
    } catch (err) {
        toast(err.message, 'error');
    }
}

async function loadAudit() {
    try {
        const response = await api('auditoria', {
            params: {
                q: document.getElementById('audit-q')?.value || '',
                page: APP_STATE.auditPage,
                per_page: 10,
            },
        });
        renderAudit(response.data);
    } catch (err) {
        toast(err.message, 'error');
    }
}

function renderAudit(data) {
    document.getElementById('audit-count-label').textContent = `${data.total} registro(s) de auditoria`;
    const tbody = document.getElementById('audit-table-body');
    tbody.innerHTML = '';

    if (!data.items.length) {
        tbody.innerHTML = '<tr><td colspan="5" class="empty-row">Nenhum registro encontrado.</td></tr>';
    } else {
        data.items.forEach((item) => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>${formatDate(item.criado_em)}</td>
                <td>${escapeHtml(item.usuario_nome || 'Sistema')}</td>
                <td>${badge(item.acao, auditColor(item.acao))}</td>
                <td>${escapeHtml(item.tabela)}</td>
                <td>${escapeHtml(item.registro_id || '-')}</td>
            `;
            tbody.appendChild(tr);
        });
    }

    renderPagination(document.getElementById('audit-pagination'), data.page, data.total_pages, (page) => {
        APP_STATE.auditPage = page;
        loadAudit();
    });
}

function auditColor(action) {
    const map = {
        LOGIN: '#203040',
        INSERT: '#15803d',
        UPDATE: '#2563eb',
        DELETE: '#be123c',
        DEACTIVATE: '#d97706',
    };
    return map[action] || '#64748b';
}
