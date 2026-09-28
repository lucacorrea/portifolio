'use strict';

const refreshPageIcons = () => {
    if (window.lucide) window.lucide.createIcons();
};

document.querySelectorAll('[data-live-search]').forEach((input) => {
    const target = input.dataset.liveSearch;
    const rows = [...document.querySelectorAll(target)];

    input.addEventListener('input', () => {
        const value = input.value.trim().toLowerCase();
        rows.forEach((row) => {
            const text = (row.dataset.search || row.innerText).toLowerCase();
            row.hidden = value !== '' && !text.includes(value);
        });
    });
});

document.querySelectorAll('[data-status-select]').forEach((select) => {
    const rows = [...document.querySelectorAll('[data-filter-row]')];

    select.addEventListener('change', () => {
        const value = select.value;
        rows.forEach((row) => {
            row.hidden = value !== 'all' && row.dataset.status !== value;
        });
    });
});

const selectAll = document.querySelector('[data-select-all]');
const receiptChecks = [...document.querySelectorAll('[data-receipt-check]')];
selectAll?.addEventListener('change', () => {
    receiptChecks.forEach((check) => {
        check.checked = selectAll.checked;
    });
});

document.querySelector('[data-confirm-receipt]')?.addEventListener('click', () => {
    const selected = receiptChecks.filter((check) => check.checked);
    if (!selected.length) {
        alert('Selecione pelo menos um ofício para confirmar o recebimento.');
        return;
    }
    alert(selected.length + ' ofício(s) selecionado(s) para confirmação. O salvamento será ligado ao banco depois.');
});

document.querySelector('[data-office-form]')?.addEventListener('submit', (event) => {
    event.preventDefault();
    alert('Layout do cadastro pronto. Na próxima etapa conectamos este formulário ao banco de dados.');
});

refreshPageIcons();
