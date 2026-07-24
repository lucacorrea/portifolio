document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  const supplierFields = [
    'id', 'tipo_pessoa', 'nome', 'nome_fantasia', 'documento', 'inscricao_estadual', 'contato', 'telefone', 'whatsapp', 'email',
    'cep', 'endereco', 'numero', 'complemento', 'bairro', 'cidade', 'estado', 'observacao',
  ];

  function payload(button) {
    try { return JSON.parse(button.dataset.supplier || '{}'); } catch (error) { return null; }
  }

  function value(supplier, key) {
    const current = key === 'tipo_pessoa' && supplier?.[key] === undefined ? supplier?.tipo : supplier?.[key];
    return current === null || current === undefined || String(current).trim() === '' ? '-' : String(current);
  }

  function element(tag, className, text) {
    const node = document.createElement(tag);
    if (className) node.className = className;
    if (text !== undefined) node.textContent = text;
    return node;
  }

  function renderSupplier(supplier) {
    const host = document.getElementById('supplier-view-content');
    if (!host) return;
    host.replaceChildren();
    const definitions = [
      ['Identificação', [['codigo', 'Código'], ['nome', 'Nome / Razão social'], ['nome_fantasia', 'Nome fantasia'], ['tipo_pessoa', 'Tipo'], ['documento', 'CPF / CNPJ'], ['inscricao_estadual', 'Inscrição estadual'], ['status', 'Status']]],
      ['Contato', [['contato', 'Pessoa de contato'], ['telefone', 'Telefone'], ['whatsapp', 'WhatsApp'], ['email', 'E-mail']]],
      ['Endereço', [['cep', 'CEP'], ['endereco', 'Endereço'], ['numero', 'Número'], ['complemento', 'Complemento'], ['bairro', 'Bairro'], ['cidade', 'Cidade'], ['estado', 'UF']]],
      ['Observações', [['observacao', 'Observação']]],
    ];
    definitions.forEach(function (definition) {
      const section = element('section', 'form-section');
      section.appendChild(element('h3', 'form-section-title', definition[0]));
      const grid = element('div', 'form-row');
      definition[1].forEach(function (field) {
        const group = element('div', 'form-group');
        group.append(element('span', 'form-label', field[1]), element('strong', '', value(supplier, field[0])));
        grid.appendChild(group);
      });
      section.appendChild(grid);
      host.appendChild(section);
    });
    const subtitle = document.getElementById('supplier-view-subtitle');
    if (subtitle) subtitle.textContent = value(supplier, 'codigo') + ' — ' + value(supplier, 'nome');
  }

  function populateEdit(supplier) {
    const form = document.querySelector('#modal-fornecedor-edit form');
    if (!form) return;
    form.reset();
    if (!Object.prototype.hasOwnProperty.call(supplier, 'tipo_pessoa') && Object.prototype.hasOwnProperty.call(supplier, 'tipo')) {
      supplier.tipo_pessoa = supplier.tipo;
    }
    supplierFields.forEach(function (name) {
      const field = form.elements.namedItem(name);
      if (field && !(field instanceof RadioNodeList)) field.value = supplier[name] ?? '';
    });
    const subtitle = document.getElementById('supplier-edit-subtitle');
    if (subtitle) subtitle.textContent = value(supplier, 'codigo') + ' — ' + value(supplier, 'nome');
  }

  function populateStatus(supplier) {
    const form = document.querySelector('#modal-fornecedor-status form');
    if (!form) return;
    const activating = String(supplier.status || 'ativo') !== 'ativo';
    form.elements.namedItem('id').value = supplier.id ?? '';
    form.elements.namedItem('status').value = activating ? 'ativo' : 'inativo';
    const action = activating ? 'Ativar' : 'Desativar';
    const title = document.getElementById('supplier-status-title');
    const message = document.getElementById('supplier-status-message');
    const submit = document.getElementById('supplier-status-submit');
    if (title) title.textContent = action + ' fornecedor';
    if (message) message.textContent = action + ' o fornecedor “' + value(supplier, 'nome') + '”?';
    if (submit) submit.textContent = action;
  }

  document.addEventListener('click', function (event) {
    const statusFilter = event.target.closest('.js-supplier-status-filter');
    if (statusFilter) {
      event.preventDefault();
      const select = document.querySelector('form[data-live-filter="suppliers"] select[name="status"]');
      if (!select) { window.location.assign(statusFilter.href); return; }
      select.value = statusFilter.dataset.status || '';
      select.dispatchEvent(new Event('change', { bubbles: true }));
      return;
    }

    const button = event.target.closest('.js-supplier-view, .js-supplier-edit, .js-supplier-status');
    if (!button) return;
    const supplier = payload(button);
    if (!supplier) { event.preventDefault(); return; }
    if (button.classList.contains('js-supplier-view')) renderSupplier(supplier);
    else if (button.classList.contains('js-supplier-edit')) populateEdit(supplier);
    else populateStatus(supplier);
  });

  const recoveryModal = document.querySelector('.modal[data-recovery-open="true"]');
  if (recoveryModal && window.bootstrap?.Modal) bootstrap.Modal.getOrCreateInstance(recoveryModal).show();
});
