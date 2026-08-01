document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  const pageDataElement = document.getElementById('employee-page-data');
  const pageData = pageDataElement ? JSON.parse(pageDataElement.textContent || '{}') : {};
  const viewContent = document.getElementById('employee-view-content');
  const viewLoading = document.getElementById('employee-view-loading');
  const viewError = document.getElementById('employee-view-error');
  let editLoadSequence = 0;
  const sensitiveFields = [
    'salario', 'banco', 'agencia', 'conta', 'tipo_conta', 'pix', 'rg_numero', 'rg_uf', 'rg_orgao_emissor',
    'rg_data_emissao', 'cpf_numero', 'titulo_eleitor_numero', 'titulo_eleitor_uf', 'titulo_eleitor_secao',
    'titulo_eleitor_zona', 'reservista_numero', 'reservista_data_emissao', 'certidao_nascimento_numero',
    'certidao_nascimento_cidade', 'certidao_nascimento_livro', 'certidao_nascimento_folha',
    'certidao_nascimento_data_emissao', 'carteira_trabalho_numero', 'carteira_trabalho_serie',
    'carteira_trabalho_uf', 'pis_pasep_numero', 'cnh_numero_registro', 'cnh_categoria', 'cnh_data_vencimento',
  ];

  const sections = [
    ['Dados profissionais', [
      ['funcao', 'Função'], ['salario', 'Salário', 'money'], ['data_cadastro', 'Data de cadastro', 'date'],
      ['data_admissao', 'Data de admissão', 'date'],
    ]],
    ['Dados pessoais e contato', [
      ['endereco', 'Endereço'], ['telefone_celular', 'Telefone celular', 'phone'], ['data_nascimento', 'Data de nascimento', 'date'],
      ['estado_civil', 'Estado civil'], ['sexo', 'Sexo'],
    ]],
    ['Dados bancários', [
      ['banco', 'Banco'], ['agencia', 'Agência'], ['conta', 'Conta'], ['tipo_conta', 'Tipo de conta'], ['pix', 'Chave Pix'],
    ]],
    ['RG e CPF', [
      ['rg_numero', 'RG'], ['rg_uf', 'UF'], ['rg_orgao_emissor', 'Órgão emissor'], ['rg_data_emissao', 'Emissão', 'date'],
      ['cpf_numero', 'CPF', 'cpf'],
    ]],
    ['Título de eleitor', [
      ['titulo_eleitor_numero', 'Número'], ['titulo_eleitor_uf', 'UF'], ['titulo_eleitor_secao', 'Seção'], ['titulo_eleitor_zona', 'Zona'],
    ]],
    ['Certificado de reservista', [
      ['reservista_numero', 'Série'], ['reservista_data_emissao', 'Emissão', 'date'],
    ]],
    ['Certidão de nascimento', [
      ['certidao_nascimento_numero', 'Número'], ['certidao_nascimento_cidade', 'Cidade'],
      ['certidao_nascimento_livro', 'Livro'], ['certidao_nascimento_folha', 'Folha'],
      ['certidao_nascimento_data_emissao', 'Emissão', 'date'],
    ]],
    ['Carteira de trabalho', [
      ['carteira_trabalho_numero', 'Número'], ['carteira_trabalho_serie', 'Série'], ['carteira_trabalho_uf', 'UF'],
      ['pis_pasep_numero', 'PIS/PASEP'],
    ]],
    ['Habilitação', [
      ['cnh_numero_registro', 'Nº do registro'], ['cnh_categoria', 'Categoria'], ['cnh_data_vencimento', 'Vencimento', 'date'],
    ]],
    ['Manequim', [
      ['manequim_camisa', 'Camisa'], ['manequim_calca', 'Calça'], ['manequim_calcado', 'Calçado'],
    ]],
  ];

  function element(tag, className, text) {
    const node = document.createElement(tag);
    if (className) node.className = className;
    if (text !== undefined && text !== null) node.textContent = String(text);
    return node;
  }

  function initials(name) {
    return String(name || 'F').trim().split(/\s+/).slice(0, 2).map(function (part) { return part.charAt(0); }).join('').toUpperCase() || 'F';
  }

  function formatted(value, type) {
    if (value === null || value === undefined || String(value).trim() === '') return '-';
    if (type === 'money') return Number(value).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
    if (type === 'date') {
      const match = String(value).match(/^(\d{4})-(\d{2})-(\d{2})/);
      return match ? match[3] + '/' + match[2] + '/' + match[1] : String(value);
    }
    if (type === 'datetime') {
      const match = String(value).match(/^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})/);
      return match ? match[3] + '/' + match[2] + '/' + match[1] + ' ' + match[4] + ':' + match[5] : String(value);
    }
    if (type === 'phone') {
      const digits = String(value).replace(/\D/g, '');
      if (digits.length === 11) return digits.replace(/(\d{2})(\d{5})(\d{4})/, '($1) $2-$3');
      if (digits.length === 10) return digits.replace(/(\d{2})(\d{4})(\d{4})/, '($1) $2-$3');
      return String(value);
    }
    if (type === 'cpf') {
      const digits = String(value).replace(/\D/g, '');
      return digits.length === 11 ? digits.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, '$1.$2.$3-$4') : String(value);
    }
    return String(value);
  }

  function setPhotoPreview(form, url, name) {
    const preview = form?.querySelector('[data-employee-photo-preview]');
    if (!preview) return;
    const image = preview.querySelector('img');
    const fallback = preview.querySelector('span');
    if (url) {
      image.src = url;
      image.alt = 'Foto de ' + (name || 'funcionário');
      image.classList.add('show');
      fallback.classList.add('d-none');
    } else {
      image.removeAttribute('src');
      image.classList.remove('show');
      fallback.classList.remove('d-none');
      fallback.textContent = initials(name);
    }
  }

  function renderView(employee) {
    viewContent.replaceChildren();
    const identity = element('section', 'form-section employee-identity');
    const avatar = element('div', 'employee-profile-avatar');
    if (employee.photo_url) {
      const image = element('img'); image.src = employee.photo_url; image.alt = 'Foto de ' + employee.name; avatar.appendChild(image);
    } else avatar.textContent = initials(employee.name);
    const heading = element('div'); heading.append(element('h3', 'mb-1', employee.name), element('p', 'text-muted mb-0', employee.code));
    identity.append(avatar, heading); viewContent.appendChild(identity);

    sections.forEach(function (definition) {
      const available = definition[1].filter(function (field) { return Object.prototype.hasOwnProperty.call(employee, field[0]); });
      if (!available.length) return;
      const section = element('section', 'form-section'); section.appendChild(element('h3', 'form-section-title', definition[0]));
      const grid = element('div', 'employee-detail-grid');
      available.forEach(function (field) {
        const item = element('div', 'employee-detail-item'); item.append(element('span', '', field[1]), element('strong', '', formatted(employee[field[0]], field[2]))); grid.appendChild(item);
      });
      section.appendChild(grid); viewContent.appendChild(section);
    });

    const audit = element('section', 'form-section'); audit.appendChild(element('h3', 'form-section-title', 'Registro'));
    const auditGrid = element('div', 'employee-detail-grid');
    [['criado_em', 'Criado em'], ['atualizado_em', 'Atualizado em']].forEach(function (field) {
      const item = element('div', 'employee-detail-item'); item.append(element('span', '', field[1]), element('strong', '', formatted(employee[field[0]], 'datetime'))); auditGrid.appendChild(item);
    });
    audit.appendChild(auditGrid); viewContent.appendChild(audit);
  }

  async function loadEmployee(id, mode) {
    const response = await fetch('actions/funcionario-detalhes.php?id=' + encodeURIComponent(id) + '&mode=' + encodeURIComponent(mode), {
      headers: { Accept: 'application/json' }, credentials: 'same-origin', cache: 'no-store',
    });
    const payload = await response.json().catch(function () { return null; });
    if (!response.ok || !payload?.employee) throw new Error(payload?.error || 'Não foi possível carregar o funcionário.');
    return payload.employee;
  }

  function populateEdit(employee) {
    const modal = document.getElementById('modal-funcionario-edit');
    const form = modal?.querySelector('form');
    if (!form) return;
    form.reset();
    Object.entries(employee).forEach(function (entry) {
      const field = form.elements.namedItem(entry[0]);
      if (field && !(field instanceof RadioNodeList) && field.type !== 'file') field.value = entry[1] ?? '';
    });
    form.elements.namedItem('id').value = employee.id;
    form.elements.namedItem('code').value = employee.code;
    const subtitle = document.getElementById('edit-employee-subtitle'); if (subtitle) subtitle.textContent = employee.code;
    setPhotoPreview(form, employee.photo_url || '', employee.name);
    form.dataset.currentPhotoUrl = employee.photo_url || '';
    const error = document.getElementById('edit-employee-form-error'); if (error) error.classList.add('d-none');
    const submit = form.querySelector('[type="submit"]'); if (submit) submit.disabled = false;
  }

  function prepareEditLoading() {
    const modal = document.getElementById('modal-funcionario-edit');
    const form = modal?.querySelector('form');
    if (!form) return;
    form.querySelectorAll('input[name], select[name], textarea[name]').forEach(function (field) {
      if (['csrf_token', 'return_to', 'operation', 'MAX_FILE_SIZE'].includes(field.name)) return;
      if (field.type === 'file') field.value = '';
      else if (field.type === 'checkbox' || field.type === 'radio') field.checked = false;
      else field.value = '';
    });
    form.dataset.currentPhotoUrl = '';
    setPhotoPreview(form, '', '');
    const submit = form.querySelector('[type="submit"]'); if (submit) submit.disabled = true;
    const subtitle = document.getElementById('edit-employee-subtitle'); if (subtitle) subtitle.textContent = 'Carregando dados…';
    const error = document.getElementById('edit-employee-form-error'); if (error) error.classList.add('d-none');
  }

  document.addEventListener('click', async function (event) {
    const button = event.target.closest('.js-employee-view, .js-employee-edit');
    if (!button) return;
    const editing = button.classList.contains('js-employee-edit');
    const currentEditSequence = editing ? ++editLoadSequence : 0;
    if (editing) prepareEditLoading();
    else { viewLoading?.classList.remove('d-none'); viewError?.classList.add('d-none'); viewContent?.replaceChildren(); }
    try {
      const employee = await loadEmployee(button.dataset.employeeId, editing ? 'edit' : 'view');
      if (editing) {
        if (currentEditSequence === editLoadSequence) populateEdit(employee);
      } else { const subtitle = document.getElementById('view-employee-subtitle'); if (subtitle) subtitle.textContent = employee.code; renderView(employee); }
    } catch (error) {
      if (editing && currentEditSequence !== editLoadSequence) return;
      const target = editing ? document.getElementById('edit-employee-form-error') : viewError;
      if (target) { target.textContent = error.message || 'Não foi possível carregar o funcionário.'; target.classList.remove('d-none'); }
    } finally { if (!editing) viewLoading?.classList.add('d-none'); }
  });

  document.querySelectorAll('.js-employee-photo-input').forEach(function (input) {
    input.addEventListener('change', function () {
      const form = input.form; const file = input.files?.[0];
      if (!file) { setPhotoPreview(form, form.dataset.currentPhotoUrl || '', form.elements.namedItem('name')?.value || ''); return; }
      setPhotoPreview(form, URL.createObjectURL(file), form.elements.namedItem('name')?.value || '');
      const remove = form.querySelector('.js-remove-employee-photo'); if (remove) remove.checked = false;
    });
  });
  document.querySelectorAll('.js-remove-employee-photo').forEach(function (checkbox) {
    checkbox.addEventListener('change', function () { const form = checkbox.form; setPhotoPreview(form, checkbox.checked ? '' : (form.dataset.currentPhotoUrl || ''), form.elements.namedItem('name')?.value || ''); });
  });
  document.getElementById('modal-funcionario-edit')?.addEventListener('hidden.bs.modal', function () { ++editLoadSequence; });

  const recoveryTargets = { create: 'modal-funcionario', edit: 'modal-funcionario-edit' };
  if (pageData.recoveryModal && recoveryTargets[pageData.recoveryModal] && window.bootstrap) {
    const modal = document.getElementById(recoveryTargets[pageData.recoveryModal]);
    if (modal && pageData.recoveryModal === 'edit') {
      const form = modal.querySelector('form');
      const submit = form?.querySelector('[type="submit"]'); if (submit) submit.disabled = true;
      const employeeId = form?.elements.namedItem('id')?.value;
      const recoverySequence = ++editLoadSequence;
      bootstrap.Modal.getOrCreateInstance(modal).show();
      loadEmployee(employeeId, 'edit').then(function (employee) {
        if (recoverySequence !== editLoadSequence) return;
        sensitiveFields.forEach(function (name) {
          const field = form.elements.namedItem(name);
          if (field && Object.prototype.hasOwnProperty.call(employee, name)) field.value = employee[name] ?? '';
        });
        form.dataset.currentPhotoUrl = employee.photo_url || '';
        setPhotoPreview(form, employee.photo_url || '', form.elements.namedItem('name')?.value || employee.name);
        if (submit) submit.disabled = false;
      }).catch(function (error) {
        if (recoverySequence !== editLoadSequence) return;
        const target = document.getElementById('edit-employee-form-error');
        if (target) { target.textContent = error.message || 'Não foi possível recuperar os dados protegidos.'; target.classList.remove('d-none'); }
      });
    } else if (modal) bootstrap.Modal.getOrCreateInstance(modal).show();
  }
});
