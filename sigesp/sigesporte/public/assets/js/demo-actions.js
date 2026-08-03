import { appUrl } from './url.js';
import { showToast } from './toast.js';
import { closeModal, openModal } from './modal.js';

const SUCCESS_MESSAGE = 'Operação simulada com sucesso no ambiente de demonstração.';
const EXPORT_MESSAGE = 'A exportação será habilitada na integração com o back-end.';
const destructiveActions = new Set(['delete', 'excluir', 'remove', 'inactivate', 'inativar', 'reject', 'rejeitar']);
let pendingConfirmation = null;

function createConfirmDialog() {
    const modal = document.createElement('div');
    modal.className = 'modal';
    modal.id = 'demo-confirm-dialog';
    modal.dataset.confirmDialog = '';
    modal.setAttribute('role', 'alertdialog');
    modal.setAttribute('aria-modal', 'true');
    modal.setAttribute('aria-labelledby', 'demo-confirm-title');
    modal.setAttribute('aria-describedby', 'demo-confirm-message');
    modal.hidden = true;

    const backdrop = document.createElement('div');
    backdrop.className = 'modal__backdrop';
    backdrop.dataset.modalClose = '';
    backdrop.setAttribute('aria-hidden', 'true');
    const panel = document.createElement('section');
    panel.className = 'modal__panel';
    panel.dataset.modalPanel = '';
    panel.tabIndex = -1;
    const heading = document.createElement('h2');
    heading.id = 'demo-confirm-title';
    heading.textContent = 'Confirmar ação';
    const message = document.createElement('p');
    message.id = 'demo-confirm-message';
    message.dataset.confirmMessage = '';
    const actions = document.createElement('div');
    actions.className = 'form-actions';
    const cancel = document.createElement('button');
    cancel.className = 'button button--secondary';
    cancel.type = 'button';
    cancel.dataset.confirmCancel = '';
    cancel.textContent = 'Cancelar';
    const accept = document.createElement('button');
    accept.className = 'button';
    accept.type = 'button';
    accept.dataset.confirmAccept = '';
    accept.textContent = 'Confirmar';
    actions.append(cancel, accept);
    panel.append(heading, message, actions);
    modal.append(backdrop, panel);
    document.body.append(modal);
    return modal;
}

function confirmDialog() {
    return document.querySelector('[data-confirm-dialog]') || createConfirmDialog();
}

function targetFor(trigger) {
    if (trigger.dataset.demoTarget) {
        try { return document.querySelector(trigger.dataset.demoTarget); } catch { return null; }
    }
    return trigger.closest('[data-demo-record], tr, .record-card, article, .mini-list__item, .call-card');
}

function updateRecordCount(scope) {
    const container = scope?.closest('.surface') || document;
    const tableRecords = container.querySelectorAll('tbody [data-demo-record]');
    const cardRecords = container.querySelectorAll('.record-cards [data-demo-record]');
    const total = tableRecords.length || cardRecords.length;
    container.querySelectorAll('[data-record-count]').forEach((counter) => { counter.textContent = String(total); });
}

function removeRecord(trigger) {
    const target = targetFor(trigger);
    if (!target) return;
    const surface = target.closest('.surface');
    const groupSelector = target.matches('tr') ? 'tbody [data-demo-record]' : '.record-cards [data-demo-record]';
    const mirrorSelector = target.matches('tr') ? '.record-cards [data-demo-record]' : 'tbody [data-demo-record]';
    const group = [...(surface || document).querySelectorAll(groupSelector)];
    const index = group.indexOf(target);
    target.remove();
    if (index >= 0) [...(surface || document).querySelectorAll(mirrorSelector)][index]?.remove();
    updateRecordCount(surface);
}

function updateStatus(trigger, label, tone) {
    const target = targetFor(trigger);
    let badge = target?.querySelector('[data-demo-status], .badge');
    if (trigger.dataset.demoStatusTarget) {
        try { badge = document.querySelector(trigger.dataset.demoStatusTarget); } catch { badge = null; }
    }
    if (!badge) {
        const statusText = target?.querySelector('small');
        if (statusText) statusText.textContent = label;
        return;
    }
    badge.textContent = label;
    [...badge.classList].filter((name) => name.startsWith('badge--')).forEach((name) => badge.classList.remove(name));
    badge.classList.add(`badge--${tone}`);
}

function showRecordDetails(trigger) {
    let modal = document.querySelector('#demo-record-details');
    if (!modal) {
        modal = document.createElement('div');
        modal.className = 'modal';
        modal.id = 'demo-record-details';
        modal.setAttribute('role', 'dialog');
        modal.setAttribute('aria-modal', 'true');
        modal.setAttribute('aria-labelledby', 'demo-record-details-title');
        modal.hidden = true;
        const backdrop = document.createElement('div');
        backdrop.className = 'modal__backdrop';
        backdrop.dataset.modalClose = '';
        backdrop.setAttribute('aria-hidden', 'true');
        const panel = document.createElement('section');
        panel.className = 'modal__panel';
        panel.dataset.modalPanel = '';
        panel.tabIndex = -1;
        const header = document.createElement('header');
        const title = document.createElement('h2');
        title.id = 'demo-record-details-title';
        const close = document.createElement('button');
        close.className = 'icon-button';
        close.type = 'button';
        close.dataset.modalClose = '';
        close.setAttribute('aria-label', 'Fechar detalhes');
        close.textContent = '×';
        header.append(title, close);
        const content = document.createElement('div');
        content.dataset.recordDetailsContent = '';
        panel.append(header, content);
        modal.append(backdrop, panel);
        document.body.append(modal);
    }

    const record = targetFor(trigger);
    const title = modal.querySelector('#demo-record-details-title');
    const content = modal.querySelector('[data-record-details-content]');
    const name = trigger.dataset.recordName || record?.querySelector('strong')?.textContent || 'Registro demonstrativo';
    title.textContent = name.trim();
    content.replaceChildren();
    if (record?.matches('tr')) {
        const headings = [...record.closest('table')?.querySelectorAll('thead th') || []];
        const list = document.createElement('dl');
        list.className = 'details-list';
        [...record.cells].forEach((cell, index) => {
            if (index >= headings.length - 1) return;
            const term = document.createElement('dt');
            term.textContent = headings[index]?.textContent.trim() || `Campo ${index + 1}`;
            const detail = document.createElement('dd');
            detail.textContent = cell.textContent.trim() || '—';
            list.append(term, detail);
        });
        content.append(list);
    } else {
        const paragraph = document.createElement('p');
        paragraph.textContent = record?.textContent.trim() || 'Detalhes fictícios disponíveis somente nesta demonstração.';
        content.append(paragraph);
    }
    openModal(modal, trigger);
}

function performAction(trigger) {
    const action = String(trigger.dataset.demoAction || '').toLowerCase();
    const target = targetFor(trigger);
    if (['view', 'visualizar', 'details', 'detalhes'].includes(action)) {
        showRecordDetails(trigger);
        return;
    } else if (['delete', 'excluir', 'remove'].includes(action)) removeRecord(trigger);
    else if (['approve', 'aprovar'].includes(action)) updateStatus(trigger, 'Aprovado', 'success');
    else if (['reject', 'rejeitar'].includes(action)) updateStatus(trigger, 'Rejeitado', 'danger');
    else if (['activate', 'ativar'].includes(action)) updateStatus(trigger, 'Ativo', 'success');
    else if (['inactivate', 'inativar'].includes(action)) updateStatus(trigger, 'Inativo', 'neutral');
    else if (action === 'print' || action === 'imprimir') {
        window.print();
        return;
    } else if (action === 'csv') {
        const table = trigger.closest('section, article')?.querySelector('table') || document.querySelector('table');
        if (!table) {
            showToast(EXPORT_MESSAGE);
            return;
        }
        const lines = [...table.querySelectorAll('tr:not([hidden])')].map((row) => [...row.cells]
            .map((cell) => `"${cell.textContent.trim().replaceAll('"', '""')}"`).join(','));
        const url = URL.createObjectURL(new Blob([`\uFEFF${lines.join('\r\n')}`], { type: 'text/csv;charset=utf-8' }));
        const link = document.createElement('a');
        link.href = url;
        link.download = 'sigesp-demonstracao.csv';
        link.click();
        URL.revokeObjectURL(url);
        showToast('CSV demonstrativo gerado somente no navegador.');
        return;
    } else if (action.startsWith('export') || ['excel', 'pdf'].includes(action)) {
        showToast(EXPORT_MESSAGE);
        return;
    }

    showToast(trigger.dataset.demoMessage || SUCCESS_MESSAGE);
    trigger.dispatchEvent(new CustomEvent('sigesp:demo-action', { bubbles: true, detail: { action, target } }));
    if (trigger.dataset.demoRedirect) {
        window.setTimeout(() => { window.location.href = appUrl(trigger.dataset.demoRedirect); }, 700);
    }
}

function requestConfirmation(trigger) {
    const dialog = confirmDialog();
    const message = dialog.querySelector('[data-confirm-message]');
    if (message) message.textContent = trigger.dataset.demoConfirm || 'Confirma esta ação simulada?';
    pendingConfirmation = trigger;
    if (!dialog.hasAttribute('data-confirm-bound')) {
        dialog.dataset.confirmBound = '';
        dialog.addEventListener('sigesp:modal-close', () => { pendingConfirmation = null; });
    }
    openModal(dialog, trigger);
}

document.addEventListener('submit', (event) => {
    const form = event.target.closest('form[data-demo-form]');
    if (!form) return;
    event.preventDefault();
    if (!form.reportValidity()) return;
    showToast(form.dataset.demoMessage || SUCCESS_MESSAGE);
    form.dispatchEvent(new CustomEvent('sigesp:demo-submit', { bubbles: true, detail: { data: new FormData(form) } }));
    if (form.dataset.demoRedirect) {
        window.setTimeout(() => { window.location.href = appUrl(form.dataset.demoRedirect); }, 700);
    }
});

document.addEventListener('click', (event) => {
    const cancel = event.target.closest('[data-confirm-cancel]');
    if (cancel) {
        pendingConfirmation = null;
        closeModal(cancel.closest('.modal'));
        return;
    }
    const accept = event.target.closest('[data-confirm-accept]');
    if (accept) {
        const trigger = pendingConfirmation;
        pendingConfirmation = null;
        closeModal(accept.closest('.modal'));
        if (trigger) performAction(trigger);
        return;
    }

    const attendance = event.target.closest('[data-attendance]');
    if (attendance) {
        const group = attendance.closest('[role="group"]');
        group?.querySelectorAll('[data-attendance]').forEach((button) => {
            const selected = button === attendance;
            button.classList.toggle('is-selected', selected);
            button.setAttribute('aria-pressed', String(selected));
        });
        return;
    }

    const trigger = event.target.closest('[data-demo-action], [data-demo-delete]');
    if (!trigger) return;
    event.preventDefault();
    const action = String(trigger.dataset.demoAction || (trigger.hasAttribute('data-demo-delete') ? 'delete' : '')).toLowerCase();
    if (!trigger.dataset.demoAction) trigger.dataset.demoAction = action;
    if (trigger.hasAttribute('data-demo-destructive') || destructiveActions.has(action)) requestConfirmation(trigger);
    else performAction(trigger);
});

document.querySelectorAll('[data-global-search]').forEach((input) => input.addEventListener('keydown', (event) => {
    if (event.key !== 'Enter') return;
    event.preventDefault();
    const term = input.value.trim();
    showToast(term ? `Pesquisa demonstrativa por “${term}”.` : 'Digite um termo para pesquisar na demonstração.');
}));

export { SUCCESS_MESSAGE, EXPORT_MESSAGE };
