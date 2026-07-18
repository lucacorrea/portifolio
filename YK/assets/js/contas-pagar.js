document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  const accountFields = ['id', 'fornecedor_id', 'descricao', 'documento', 'data_emissao', 'vencimento_em', 'valor', 'observacao'];

  function payload(button) {
    try { return JSON.parse(button.dataset.account || '{}'); } catch (error) { return null; }
  }

  function value(account, key) {
    const current = account?.[key];
    return current === null || current === undefined || String(current).trim() === '' ? '-' : String(current);
  }

  function formatted(account, key, type) {
    const current = value(account, key);
    if (current === '-') return current;
    if (type === 'date') {
      const match = current.match(/^(\d{4})-(\d{2})-(\d{2})/);
      return match ? match[3] + '/' + match[2] + '/' + match[1] : current;
    }
    if (type === 'money') {
      const number = Number(current.replace(',', '.'));
      return Number.isFinite(number) ? number.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' }) : current;
    }
    return current;
  }

  function element(tag, className, text) {
    const node = document.createElement(tag);
    if (className) node.className = className;
    if (text !== undefined) node.textContent = text;
    return node;
  }

  function renderAccount(account) {
    const host = document.getElementById('payable-view-content');
    if (!host) return;
    host.replaceChildren();
    const definitions = [
      ['Fornecedor e referência', [['codigo', 'Código'], ['fornecedor_nome', 'Fornecedor'], ['descricao', 'Descrição'], ['documento', 'Documento']]],
      ['Valores e vencimento', [['data_emissao', 'Emissão', 'date'], ['vencimento_em', 'Vencimento', 'date'], ['valor', 'Valor', 'money'], ['status_exibicao', 'Status']]],
      ['Observações', [['observacao', 'Observação']]],
    ];
    definitions.forEach(function (definition) {
      const section = element('section', 'form-section');
      section.appendChild(element('h3', 'form-section-title', definition[0]));
      const grid = element('div', 'form-row');
      definition[1].forEach(function (field) {
        const group = element('div', 'form-group');
        const display = field[0] === 'status_exibicao' && value(account, field[0]) === '-' ? value(account, 'status') : formatted(account, field[0], field[2]);
        group.append(element('span', 'form-label', field[1]), element('strong', '', display));
        grid.appendChild(group);
      });
      section.appendChild(grid);
      host.appendChild(section);
    });
    const subtitle = document.getElementById('payable-view-subtitle');
    if (subtitle) subtitle.textContent = value(account, 'codigo') + ' — ' + value(account, 'fornecedor_nome');
  }

  function populateEdit(account) {
    const form = document.querySelector('#modal-conta-pagar-edit form');
    if (!form) return;
    form.reset();
    const supplierSelect = form.elements.namedItem('fornecedor_id');
    supplierSelect?.querySelectorAll('option[data-temporary]').forEach(function (option) { option.remove(); });
    if (supplierSelect && !Array.from(supplierSelect.options).some(function (option) { return option.value === String(account.fornecedor_id); })) {
      const option = document.createElement('option');
      option.value = account.fornecedor_id ?? '';
      option.textContent = account.fornecedor_nome || 'Fornecedor atual';
      option.dataset.temporary = 'true';
      supplierSelect.appendChild(option);
    }
    accountFields.forEach(function (name) {
      const field = form.elements.namedItem(name);
      if (field && !(field instanceof RadioNodeList)) field.value = account[name] ?? '';
    });
    const subtitle = document.getElementById('payable-edit-subtitle');
    if (subtitle) subtitle.textContent = value(account, 'codigo') + ' — ' + value(account, 'fornecedor_nome');
  }

  function populateCancel(account) {
    const form = document.querySelector('#modal-conta-pagar-cancel form');
    if (!form) return;
    form.reset();
    form.elements.namedItem('id').value = account.id ?? '';
    const message = document.getElementById('payable-cancel-message');
    if (message) message.textContent = 'Cancelar a conta “' + value(account, 'codigo') + '” de ' + value(account, 'fornecedor_nome') + '?';
  }

  document.addEventListener('click', function (event) {
    const statusFilter = event.target.closest('.js-payable-status-filter');
    if (statusFilter) {
      event.preventDefault();
      const select = document.querySelector('form[data-live-filter="payables"] select[name="status"]');
      if (!select) { window.location.assign(statusFilter.href); return; }
      select.value = statusFilter.dataset.status || '';
      select.dispatchEvent(new Event('change', { bubbles: true }));
      return;
    }

    const button = event.target.closest('.js-payable-view, .js-payable-edit, .js-payable-cancel');
    if (!button) return;
    const account = payload(button);
    if (!account) { event.preventDefault(); return; }
    if (button.classList.contains('js-payable-view')) renderAccount(account);
    else if (button.classList.contains('js-payable-edit')) populateEdit(account);
    else populateCancel(account);
  });

  const recoveryModal = document.querySelector('.modal[data-recovery-open="true"]');
  if (recoveryModal && window.bootstrap?.Modal) bootstrap.Modal.getOrCreateInstance(recoveryModal).show();
});
