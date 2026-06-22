'use strict';

(() => {
    const table = document.getElementById('peopleTable');
    if (!table) return;

    const allRows = [...table.querySelectorAll('[data-person-row]')];
    const form = document.getElementById('peopleFilterForm');
    const search = document.getElementById('peopleSearch');
    const status = document.getElementById('peopleStatus');
    const source = document.getElementById('peopleSource');
    const unit = document.getElementById('peopleUnit');
    const pageSizeSelect = document.getElementById('peoplePageSize');
    const summary = document.getElementById('peopleResultSummary');
    const info = document.getElementById('peoplePaginationInfo');
    const pages = document.getElementById('peoplePaginationPages');
    const previous = document.getElementById('peoplePrevPage');
    const next = document.getElementById('peopleNextPage');
    const empty = document.getElementById('peopleEmptyState');
    const mobileList = document.getElementById('peopleMobileList');
    let currentPage = 1;
    let filteredRows = [...allRows];

    const initialQuery = new URLSearchParams(window.location.search).get('q');
    if (initialQuery) search.value = initialQuery;

    const normalize = value => String(value || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().trim();

    const mobileCard = row => {
        const cells = row.cells;
        const record = cells[0].querySelector('strong')?.textContent || '';
        const person = cells[1].querySelector('strong')?.textContent || '';
        const meta = cells[1].querySelector('span')?.textContent || '';
        const unitName = cells[3].textContent.trim();
        const origin = cells[4].innerHTML;
        const situation = cells[5].innerHTML;
        const action = cells[7].innerHTML;
        const initials = person.split(/\s+/).slice(0, 2).map(part => part[0]).join('');
        return `<article class="mobile-record-card"><div class="mobile-record-head"><span class="mini-avatar">${initials}</span><div class="item-main"><strong>${person}</strong><span>${record} · ${meta}</span></div></div><div class="mobile-record-grid"><div class="mobile-record-meta"><span>Unidade</span><strong>${unitName}</strong></div><div class="mobile-record-meta"><span>Origem</span><strong>${origin}</strong></div><div class="mobile-record-meta"><span>Situação</span><strong>${situation}</strong></div><div class="mobile-record-meta"><span>Atualização</span><strong>${cells[6].textContent.trim()}</strong></div></div><div class="mobile-record-actions">${action}</div></article>`;
    };

    const render = () => {
        const pageSize = Number(pageSizeSelect.value);
        const totalPages = Math.max(1, Math.ceil(filteredRows.length / pageSize));
        currentPage = Math.min(currentPage, totalPages);
        const start = (currentPage - 1) * pageSize;
        const visibleRows = filteredRows.slice(start, start + pageSize);

        allRows.forEach(row => { row.hidden = !visibleRows.includes(row); });
        empty.hidden = filteredRows.length > 0;
        table.closest('.table-responsive').hidden = filteredRows.length === 0;
        mobileList.hidden = filteredRows.length === 0;
        mobileList.innerHTML = visibleRows.map(mobileCard).join('');

        const first = filteredRows.length ? start + 1 : 0;
        const last = Math.min(start + pageSize, filteredRows.length);
        summary.textContent = `${filteredRows.length} prontuário(s) localizado(s) com os filtros atuais`;
        info.textContent = `Exibindo ${first}–${last} de ${filteredRows.length}`;
        previous.disabled = currentPage === 1;
        next.disabled = currentPage === totalPages || filteredRows.length === 0;
        pages.innerHTML = Array.from({ length: totalPages }, (_, index) => index + 1)
            .map(page => `<button class="pagination-page ${page === currentPage ? 'active' : ''}" type="button" data-page="${page}" aria-label="Página ${page}" ${page === currentPage ? 'aria-current="page"' : ''}>${page}</button>`).join('');
    };

    const applyFilters = () => {
        const term = normalize(search.value);
        filteredRows = allRows.filter(row => {
            const matchesTerm = !term || normalize(row.dataset.search).includes(term);
            const matchesStatus = !status.value || row.dataset.status === status.value;
            const matchesSource = !source.value || row.dataset.source === source.value;
            const matchesUnit = !unit.value || row.dataset.unit === unit.value;
            return matchesTerm && matchesStatus && matchesSource && matchesUnit;
        });
        currentPage = 1;
        render();
    };

    form.addEventListener('submit', event => { event.preventDefault(); applyFilters(); });
    search.addEventListener('input', applyFilters);
    [status, source, unit].forEach(field => field.addEventListener('change', applyFilters));
    pageSizeSelect.addEventListener('change', () => { currentPage = 1; render(); });
    document.getElementById('clearPeopleFilters').addEventListener('click', () => { form.reset(); applyFilters(); });
    previous.addEventListener('click', () => { if (currentPage > 1) { currentPage -= 1; render(); } });
    next.addEventListener('click', () => {
        const totalPages = Math.ceil(filteredRows.length / Number(pageSizeSelect.value));
        if (currentPage < totalPages) { currentPage += 1; render(); }
    });
    pages.addEventListener('click', event => {
        const button = event.target.closest('[data-page]');
        if (!button) return;
        currentPage = Number(button.dataset.page);
        render();
    });

    applyFilters();
})();
