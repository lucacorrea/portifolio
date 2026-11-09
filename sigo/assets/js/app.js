'use strict';

document.documentElement.classList.add('js-enabled');

const body = document.body;
const railToggle = document.querySelector('[data-rail-toggle]');
const searchOpenButtons = document.querySelectorAll('[data-search-open]');
const searchOverlay = document.querySelector('[data-search-overlay]');
const searchClose = document.querySelector('[data-search-close]');
const searchInput = document.querySelector('[data-search-input]');
const tableSearch = document.querySelector('[data-table-search]');
const officeRows = [...document.querySelectorAll('[data-office-row]')];
const visibleCount = document.querySelector('[data-visible-count]');
const tableEmpty = document.querySelector('[data-table-empty]');
const filterPanel = document.querySelector('[data-filter-panel]');
const filterCount = document.querySelector('[data-filter-count]');
const activeFilter = document.querySelector('[data-active-filter]');
const activeFilterLabel = document.querySelector('[data-active-filter-label]');
const periodMenu = document.querySelector('[data-period-menu]');
const periodLabel = document.querySelector('[data-period-label]');
const toast = document.querySelector('[data-toast]');
const toastText = document.querySelector('[data-toast-text]');

let activeStatus = 'all';
let searchTerm = '';
let toastTimer;

const statusLabels = {
    all: 'Todos',
    pending: 'Aguardando',
    progress: 'Em andamento',
    received: 'Recebidos',
    archived: 'Arquivados'
};

const refreshIcons = () => {
    if (window.lucide) window.lucide.createIcons();
};

const showToast = (message) => {
    if (!toast || !toastText) return;
    toastText.textContent = message;
    toast.hidden = false;
    window.clearTimeout(toastTimer);
    toastTimer = window.setTimeout(() => {
        toast.hidden = true;
    }, 2600);
};

const openSearch = () => {
    if (!searchOverlay) return;
    searchOverlay.hidden = false;
    window.setTimeout(() => searchInput?.focus(), 20);
};

const closeSearch = () => {
    if (!searchOverlay) return;
    searchOverlay.hidden = true;
};

const closeFilterPanel = () => {
    if (!filterPanel) return;
    filterPanel.hidden = true;
    document.querySelectorAll('[data-filter-toggle]').forEach((button) => {
        button.setAttribute('aria-expanded', 'false');
    });
};

const applyTableFilters = () => {
    let count = 0;

    officeRows.forEach((row) => {
        const statusMatches = activeStatus === 'all' || row.dataset.status === activeStatus;
        const searchMatches = !searchTerm || (row.dataset.search || '').includes(searchTerm);
        const visible = statusMatches && searchMatches;
        row.hidden = !visible;
        if (visible) count += 1;
    });

    if (visibleCount) visibleCount.textContent = String(count);
    if (tableEmpty) tableEmpty.hidden = count !== 0;

    document.querySelectorAll('[data-status-filter]').forEach((button) => {
        button.classList.toggle('active', button.dataset.statusFilter === activeStatus);
    });

    if (activeFilter && activeFilterLabel) {
        const hasFilter = activeStatus !== 'all';
        activeFilter.hidden = !hasFilter;
        activeFilterLabel.textContent = statusLabels[activeStatus] || activeStatus;
    }

    if (filterCount) {
        filterCount.hidden = activeStatus === 'all';
        filterCount.textContent = activeStatus === 'all' ? '' : '1';
    }
};

railToggle?.addEventListener('click', () => {
    body.classList.toggle('rail-open');
});

searchOpenButtons.forEach((button) => button.addEventListener('click', openSearch));
searchClose?.addEventListener('click', closeSearch);

document.querySelectorAll('[data-filter-toggle]').forEach((button) => {
    button.addEventListener('click', (event) => {
        event.stopPropagation();
        if (!filterPanel) return;
        filterPanel.hidden = !filterPanel.hidden;
        button.setAttribute('aria-expanded', String(!filterPanel.hidden));
    });
});

document.querySelector('[data-filter-close]')?.addEventListener('click', closeFilterPanel);

document.querySelectorAll('[data-status-filter]').forEach((button) => {
    button.addEventListener('click', () => {
        activeStatus = button.dataset.statusFilter || 'all';
        applyTableFilters();
        closeFilterPanel();
        document.querySelector('#historico')?.scrollIntoView({behavior: 'smooth', block: 'start'});
    });
});

document.querySelector('[data-clear-filter]')?.addEventListener('click', () => {
    activeStatus = 'all';
    applyTableFilters();
});

tableSearch?.addEventListener('input', () => {
    searchTerm = tableSearch.value.trim().toLowerCase();
    applyTableFilters();
});

searchInput?.addEventListener('input', () => {
    searchTerm = searchInput.value.trim().toLowerCase();
    if (tableSearch) tableSearch.value = searchInput.value;
    applyTableFilters();
});

searchInput?.addEventListener('keydown', (event) => {
    if (event.key !== 'Enter') return;
    closeSearch();
    document.querySelector('#historico')?.scrollIntoView({behavior: 'smooth', block: 'start'});
});

document.querySelector('[data-period-toggle]')?.addEventListener('click', (event) => {
    event.stopPropagation();
    if (!periodMenu) return;
    periodMenu.hidden = !periodMenu.hidden;
});

document.querySelectorAll('[data-period]').forEach((button) => {
    button.addEventListener('click', () => {
        const label = button.dataset.period || 'Esta semana';
        if (periodLabel) periodLabel.textContent = label;
        document.querySelectorAll('[data-period]').forEach((item) => item.classList.remove('active'));
        button.classList.add('active');
        if (periodMenu) periodMenu.hidden = true;
        showToast('Período alterado para ' + label.toLowerCase());
    });
});

document.querySelector('[data-export]')?.addEventListener('click', () => {
    const headers = ['Protocolo', 'Ofício', 'Situação', 'Responsável', 'Hora'];
    const lines = [headers];

    officeRows.filter((row) => !row.hidden).forEach((row) => {
        const cells = [...row.querySelectorAll('td')].map((cell) => cell.innerText.trim());
        lines.push(cells);
    });

    const csv = lines
        .map((line) => line.map((value) => '"' + value.replaceAll('"', '""') + '"').join(';'))
        .join('\n');

    const blob = new Blob(['\uFEFF' + csv], {type: 'text/csv;charset=utf-8;'});
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = 'sigo-oficios.csv';
    document.body.appendChild(link);
    link.click();
    link.remove();
    URL.revokeObjectURL(url);
    showToast('Relatório exportado com sucesso');
});

document.addEventListener('keydown', (event) => {
    if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') {
        event.preventDefault();
        openSearch();
    }

    if (event.key === 'Escape') {
        closeSearch();
        closeFilterPanel();
        if (periodMenu) periodMenu.hidden = true;
        body.classList.remove('rail-open');
    }
});

document.addEventListener('click', (event) => {
    if (filterPanel && !filterPanel.hidden && !filterPanel.contains(event.target)) {
        closeFilterPanel();
    }

    if (periodMenu && !periodMenu.hidden && !periodMenu.contains(event.target)) {
        periodMenu.hidden = true;
    }

    if (body.classList.contains('rail-open')) {
        const rail = document.querySelector('[data-utility-rail]');
        if (!rail?.contains(event.target) && !railToggle?.contains(event.target)) {
            body.classList.remove('rail-open');
        }
    }
});

applyTableFilters();
refreshIcons();
