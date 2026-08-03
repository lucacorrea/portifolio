function normalized(value) {
    return String(value ?? '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().trim();
}

function activeControls(container) {
    return [...container.querySelectorAll('input:not([disabled]), select:not([disabled])')]
        .filter((control) => {
            const value = normalized(control.value);
            return control.type !== 'hidden' && value !== '' && !['todos', 'todas'].includes(value);
        });
}

function candidateRows(bar) {
    const selector = bar.dataset.filterTarget || bar.querySelector('[data-filter-target]')?.dataset.filterTarget;
    if (selector) {
        try {
            const target = document.querySelector(selector);
            return target ? [...target.querySelectorAll('[data-filter-item], [data-demo-record], tbody tr, .record-card')] : [];
        } catch { return []; }
    }
    const scope = bar.closest('main, [data-filter-scope]') || document;
    return [...scope.querySelectorAll('[data-filter-item], .data-table tbody tr, .record-cards .record-card')];
}

function applyFilter(bar) {
    const content = bar.querySelector('[data-filter-content]');
    if (!content) return;
    const controls = activeControls(content);
    const terms = controls.map((control) => normalized(control.value));
    const rows = candidateRows(bar);

    rows.forEach((row) => {
        const searchable = normalized(`${row.textContent} ${Object.values(row.dataset).join(' ')}`);
        row.hidden = terms.some((term) => !searchable.includes(term));
    });

    const count = bar.querySelector('[data-filter-count]');
    if (count) count.textContent = controls.length > 0 ? `(${controls.length})` : '';
    bar.dispatchEvent(new CustomEvent('sigesp:filter', { detail: { controls: controls.length, visible: rows.filter((row) => !row.hidden).length } }));
}

document.querySelectorAll('[data-filter-bar]').forEach((bar) => {
    const button = bar.querySelector('[data-filter-toggle]');
    const content = bar.querySelector('[data-filter-content]');
    button?.addEventListener('click', () => {
        const open = content?.classList.toggle('is-open') ?? false;
        button.setAttribute('aria-expanded', String(open));
    });
    content?.addEventListener('input', () => applyFilter(bar));
    content?.addEventListener('change', () => applyFilter(bar));
    content?.addEventListener('reset', () => window.setTimeout(() => applyFilter(bar)));
    content?.querySelectorAll('form').forEach((form) => form.addEventListener('submit', (event) => {
        if (!form.hasAttribute('data-demo-filter')) return;
        event.preventDefault();
        applyFilter(bar);
    }));
    applyFilter(bar);
});
