document.addEventListener('DOMContentLoaded', () => {
  const confirmModalElement = document.getElementById('profile-confirm-modal');
  const confirmModal = confirmModalElement ? new bootstrap.Modal(confirmModalElement) : null;
  let pendingForm = null;
  let bypassConfirm = false;

  const showConfirm = (form) => {
    if (!confirmModal || bypassConfirm) return true;

    pendingForm = form;
    const title = form.getAttribute('data-confirm-title') || 'Confirmar ação';
    const message = form.getAttribute('data-confirm-message') || 'Deseja continuar?';
    confirmModalElement.querySelector('[data-confirm-title]').textContent = title;
    confirmModalElement.querySelector('[data-confirm-message]').textContent = message;
    confirmModal.show();

    return false;
  };

  document.querySelectorAll('form[data-confirm-title]').forEach((form) => {
    form.addEventListener('submit', (event) => {
      if (bypassConfirm) {
        bypassConfirm = false;
        return;
      }

      if (!showConfirm(form)) {
        event.preventDefault();
      }
    });
  });

  confirmModalElement?.querySelector('[data-confirm-submit]')?.addEventListener('click', () => {
    if (!pendingForm) return;
    bypassConfirm = true;
    confirmModal?.hide();
    pendingForm.requestSubmit();
    pendingForm = null;
  });

  const permissionsPage = document.querySelector('.permissions-page');
  const permissionForm = document.querySelector('[data-permission-form]');
  if (!permissionsPage || !permissionForm) return;

  const checkboxes = Array.from(permissionForm.querySelectorAll('[data-permission-checkbox]'));
  const selectedCounter = permissionForm.querySelector('[data-permission-selected]');
  const totalCounter = permissionForm.querySelector('[data-permission-total]');
  const initialSelection = new Set(checkboxes.filter((checkbox) => checkbox.checked).map((checkbox) => checkbox.value));
  const dependencies = JSON.parse(permissionsPage.getAttribute('data-permission-dependencies') || '{}');
  const byCode = new Map(checkboxes.map((checkbox) => [checkbox.getAttribute('data-permission-code'), checkbox]));
  let isDirty = false;

  const setDirty = () => {
    isDirty = true;
  };

  const updateCounters = () => {
    const selected = checkboxes.filter((checkbox) => checkbox.checked).length;
    if (selectedCounter) selectedCounter.textContent = String(selected);
    if (totalCounter) totalCounter.textContent = String(checkboxes.length);

    document.querySelectorAll('[data-permission-group]').forEach((group) => {
      const groupBoxes = Array.from(group.querySelectorAll('[data-permission-checkbox]'));
      const groupSelected = groupBoxes.filter((checkbox) => checkbox.checked).length;
      const counter = group.querySelector('[data-group-counter]');
      if (counter) counter.textContent = `${groupSelected} de ${groupBoxes.length}`;
    });
  };

  const applyDependencies = (checkbox) => {
    if (!checkbox.checked) return;
    const code = checkbox.getAttribute('data-permission-code');
    const dependency = dependencies[code];
    const dependencyCheckbox = dependency ? byCode.get(dependency) : null;
    if (dependencyCheckbox && !dependencyCheckbox.disabled) {
      dependencyCheckbox.checked = true;
    }
  };

  checkboxes.forEach((checkbox) => {
    checkbox.addEventListener('change', () => {
      applyDependencies(checkbox);
      setDirty();
      updateCounters();
    });
  });

  permissionForm.querySelector('[data-permission-select-all]')?.addEventListener('click', () => {
    checkboxes.forEach((checkbox) => {
      if (!checkbox.disabled) checkbox.checked = true;
    });
    setDirty();
    updateCounters();
  });

  permissionForm.querySelector('[data-permission-clear-all]')?.addEventListener('click', () => {
    checkboxes.forEach((checkbox) => {
      if (!checkbox.disabled) checkbox.checked = false;
    });
    setDirty();
    updateCounters();
  });

  permissionForm.querySelector('[data-permission-readonly]')?.addEventListener('click', () => {
    checkboxes.forEach((checkbox) => {
      if (checkbox.disabled) return;
      const code = checkbox.getAttribute('data-permission-code') || '';
      checkbox.checked = code.endsWith('.visualizar');
    });
    setDirty();
    updateCounters();
  });

  permissionForm.querySelector('[data-permission-restore]')?.addEventListener('click', () => {
    checkboxes.forEach((checkbox) => {
      if (!checkbox.disabled) checkbox.checked = initialSelection.has(checkbox.value);
    });
    isDirty = false;
    updateCounters();
  });

  document.querySelectorAll('[data-permission-group]').forEach((group) => {
    const groupBoxes = Array.from(group.querySelectorAll('[data-permission-checkbox]'));
    group.querySelector('[data-permission-group-select]')?.addEventListener('click', () => {
      groupBoxes.forEach((checkbox) => {
        if (!checkbox.disabled) checkbox.checked = true;
      });
      setDirty();
      updateCounters();
    });
    group.querySelector('[data-permission-group-clear]')?.addEventListener('click', () => {
      groupBoxes.forEach((checkbox) => {
        if (!checkbox.disabled) checkbox.checked = false;
      });
      setDirty();
      updateCounters();
    });
  });

  permissionForm.addEventListener('submit', () => {
    checkboxes.forEach(applyDependencies);
    isDirty = false;
    updateCounters();
  });

  document.querySelector('[data-permission-cancel]')?.addEventListener('click', (event) => {
    if (!isDirty || !confirmModal) return;

    event.preventDefault();
    const link = event.currentTarget;
    pendingForm = null;
    confirmModalElement.querySelector('[data-confirm-title]').textContent = 'Sair sem salvar';
    confirmModalElement.querySelector('[data-confirm-message]').textContent = 'Existem alterações não salvas. Deseja sair da matriz de permissões?';
    confirmModalElement.querySelector('[data-confirm-submit]').onclick = () => {
      window.location.href = link.href;
    };
    confirmModal.show();
  });

  window.addEventListener('beforeunload', (event) => {
    if (!isDirty) return;
    event.preventDefault();
    event.returnValue = '';
  });

  updateCounters();
});
