'use strict';

(() => {
    const shell = document.querySelector('[data-frontend-shell]');
    const toastContainer = document.querySelector('#frontendToastContainer');
    const escapeHTML = value => String(value ?? '').replace(/[&<>"']/g, character => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[character]));

    const showToast = (message, type = 'success') => {
        if (!toastContainer || typeof bootstrap === 'undefined') return;
        const toast = document.createElement('div');
        toast.className = `toast border-0 text-bg-${type}`;
        toast.setAttribute('role', 'status');
        toast.innerHTML = `<div class="d-flex"><div class="toast-body">${escapeHTML(message)}</div><button class="btn-close btn-close-white me-2 m-auto" type="button" data-bs-dismiss="toast" aria-label="Fechar"></button></div>`;
        toastContainer.appendChild(toast);
        const instance = new bootstrap.Toast(toast, { delay: 3200 });
        toast.addEventListener('hidden.bs.toast', () => toast.remove(), { once: true });
        instance.show();
    };

    const setMenu = open => {
        if (!shell) return;
        shell.classList.toggle('module-menu-open', open);
        document.body.classList.toggle('module-menu-is-open', open);
        document.querySelectorAll('[data-frontend-menu-toggle]').forEach(button => button.setAttribute('aria-expanded', String(open)));
    };

    document.addEventListener('click', event => {
        if (event.target.closest('[data-frontend-menu-toggle]')) setMenu(true);
        if (event.target.closest('[data-frontend-menu-close]')) setMenu(false);

        const action = event.target.closest('[data-demo-action]');
        if (action) {
            const title = document.querySelector('#frontendActionTitle');
            if (title) title.textContent = action.dataset.demoAction || 'Recurso visual';
            bootstrap.Modal.getOrCreateInstance('#frontendActionModal').show();
        }

        const detail = event.target.closest('[data-detail-record]');
        if (detail) {
            let record = {};
            try { record = JSON.parse(detail.dataset.detailRecord || '{}'); } catch { record = {}; }
            const content = document.querySelector('[data-detail-content]');
            if (content) content.innerHTML = Object.entries(record).map(([key, value]) => `<div><dt>${escapeHTML(key.replaceAll('_', ' '))}</dt><dd>${escapeHTML(value)}</dd></div>`).join('');
            bootstrap.Modal.getOrCreateInstance('#frontendDetailModal').show();
        }
    });

    document.querySelectorAll('[data-frontend-filter]').forEach(form => {
        const apply = () => {
            const search = (form.querySelector('[data-filter-search]')?.value || '').trim().toLocaleLowerCase('pt-BR');
            const selections = [...form.querySelectorAll('[data-filter-select]')].map(field => field.value).filter(Boolean);
            let visible = 0;
            document.querySelectorAll('[data-filter-row]').forEach(row => {
                const content = row.dataset.search || row.textContent.toLocaleLowerCase('pt-BR');
                const matches = (!search || content.includes(search)) && selections.every(value => content.includes(value));
                row.hidden = !matches;
                if (matches) visible += 1;
            });
            document.querySelectorAll('[data-no-results]').forEach(state => { state.hidden = visible > 0; });
        };
        form.addEventListener('input', apply);
        form.addEventListener('reset', () => window.setTimeout(apply));
    });

    document.querySelectorAll('[data-demo-form]').forEach(form => form.addEventListener('submit', event => {
        event.preventDefault();
        const button = form.querySelector('[type="submit"]');
        if (button) button.disabled = true;
        window.setTimeout(() => {
            if (button) button.disabled = false;
            const modal = form.closest('.modal');
            if (modal) bootstrap.Modal.getOrCreateInstance(modal).hide();
            showToast('Alteração visual concluída. Nenhum dado foi persistido.');
        }, 450);
    }));

    document.querySelectorAll('canvas[data-frontend-chart]').forEach(canvas => {
        if (typeof Chart === 'undefined') return;
        const labels = JSON.parse(canvas.dataset.labels || '[]');
        const values = JSON.parse(canvas.dataset.values || '[]');
        new Chart(canvas, {
            type: canvas.dataset.frontendChart || 'bar',
            data: { labels, datasets: [{ label: 'Total', data: values, backgroundColor: 'rgba(23,107,58,.18)', borderColor: '#176b3a', borderWidth: 2, borderRadius: 7, fill: true, tension: .35 }] },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: canvas.dataset.frontendChart === 'doughnut' ? undefined : { x: { grid: { display: false } }, y: { beginAtZero: true } } }
        });
    });

    window.SIGAS_FRONTEND = { showToast };
})();
