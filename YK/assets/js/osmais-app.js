document.addEventListener('DOMContentLoaded', () => {
  const sidebar = document.getElementById('app-sidebar');
  const backdrop = document.getElementById('sidebar-backdrop');
  const toggles = document.querySelectorAll('[data-sidebar-toggle]');
  const sidebarPreferenceKey = 'yk.sidebar.collapsed';
  const mobileMedia = window.matchMedia('(max-width: 920px)');

  const syncSidebarToggles = () => {
    const isMobile = mobileMedia.matches;
    const isExpanded = isMobile
      ? Boolean(sidebar?.classList.contains('is-open'))
      : !document.body.classList.contains('sidebar-collapsed');

    toggles.forEach((toggle) => {
      toggle.setAttribute('aria-expanded', isExpanded ? 'true' : 'false');
      if (!toggle.classList.contains('desktop-sidebar-btn')) return;
      const label = isExpanded ? 'Ocultar barra lateral' : 'Exibir barra lateral';
      toggle.setAttribute('aria-label', label);
      toggle.setAttribute('title', label);
      const icon = toggle.querySelector('i');
      if (icon) icon.className = isExpanded ? 'bi bi-layout-sidebar-inset' : 'bi bi-layout-sidebar-inset-reverse';
    });
  };

  if (!mobileMedia.matches && window.localStorage?.getItem(sidebarPreferenceKey) === '1') {
    document.body.classList.add('sidebar-collapsed');
  }

  const closeSidebar = () => {
    sidebar?.classList.remove('is-open');
    backdrop?.classList.remove('is-visible');
    document.body.classList.remove('sidebar-open');
    syncSidebarToggles();
  };

  toggles.forEach((toggle) => {
    toggle.addEventListener('click', () => {
      if (mobileMedia.matches) {
        sidebar?.classList.toggle('is-open');
        backdrop?.classList.toggle('is-visible');
        document.body.classList.toggle('sidebar-open');
      } else {
        const collapsed = document.body.classList.toggle('sidebar-collapsed');
        window.localStorage?.setItem(sidebarPreferenceKey, collapsed ? '1' : '0');
      }
      syncSidebarToggles();
    });
  });

  backdrop?.addEventListener('click', closeSidebar);

  document.querySelectorAll('.os-sidebar a').forEach((link) => {
    link.addEventListener('click', () => {
      if (window.matchMedia('(max-width: 920px)').matches) closeSidebar();
    });
  });

  mobileMedia.addEventListener?.('change', () => {
    closeSidebar();
    if (!mobileMedia.matches && window.localStorage?.getItem(sidebarPreferenceKey) === '1') {
      document.body.classList.add('sidebar-collapsed');
    }
    syncSidebarToggles();
  });
  syncSidebarToggles();

  document.querySelectorAll('[title]').forEach((el) => {
    new bootstrap.Tooltip(el);
  });

  document.querySelectorAll('[data-weekly-fullscreen-toggle]').forEach((button) => {
    button.addEventListener('click', () => {
      const page = document.querySelector('.weekly-page');
      if (!page) return;
      const isFull = page.classList.toggle('weekly-fullscreen');
      const label = button.querySelector('span');
      const icon = button.querySelector('i');
      if (label) label.textContent = isFull ? 'Sair tela cheia' : 'Tela cheia';
      if (icon) icon.className = isFull ? 'bi bi-fullscreen-exit' : 'bi bi-arrows-fullscreen';
    });
  });

  const actionMenu = {
    active: null,
    viewportPadding: 12,
    gap: 6,
  };

  const isActionDropdown = (dropdown) => {
    if (!dropdown) return false;
    const toggle = dropdown.querySelector('.btn-action[data-bs-toggle="dropdown"]');
    return Boolean(
      toggle
      && (
        dropdown.classList.contains('table-action-dropdown')
        || dropdown.closest('.os-table')
      )
    );
  };

  const resetMenuStyles = (menu) => {
    [
      'position',
      'inset',
      'left',
      'top',
      'right',
      'bottom',
      'transform',
      'zIndex',
      'visibility',
      'display',
      'minWidth',
      'maxWidth',
      'maxHeight',
      'overflowY',
    ].forEach((property) => {
      menu.style[property] = '';
    });
  };

  const restoreActionMenu = () => {
    if (!actionMenu.active) return;

    const { menu, originalParent, originalNextSibling, toggle } = actionMenu.active;

    menu.classList.remove('action-menu-portal');
    resetMenuStyles(menu);

    if (originalParent) {
      if (
        originalNextSibling
        && originalNextSibling.parentNode === originalParent
      ) {
        originalParent.insertBefore(menu, originalNextSibling);
      } else {
        originalParent.appendChild(menu);
      }
    }

    toggle?.setAttribute('aria-expanded', 'false');
    actionMenu.active = null;
  };

  const closeActionMenu = () => {
    if (!actionMenu.active) return;

    const instance = window.bootstrap?.Dropdown?.getInstance(actionMenu.active.toggle);
    if (instance) {
      instance.hide();
      return;
    }

    restoreActionMenu();
  };

  const positionActionMenu = () => {
    if (!actionMenu.active) return;

    const { menu, toggle } = actionMenu.active;
    const toggleRect = toggle.getBoundingClientRect();
    const viewportWidth = window.innerWidth;
    const viewportHeight = window.innerHeight;
    const padding = actionMenu.viewportPadding;
    const gap = actionMenu.gap;

    const isInvisible = (
      toggleRect.bottom <= 0
      || toggleRect.top >= viewportHeight
      || toggleRect.right <= 0
      || toggleRect.left >= viewportWidth
    );

    if (isInvisible) {
      closeActionMenu();
      return;
    }

    menu.style.visibility = 'hidden';
    menu.style.display = 'block';
    menu.style.position = 'fixed';
    menu.style.inset = 'auto';
    menu.style.transform = 'none';
    menu.style.zIndex = '1070';

    const menuRect = menu.getBoundingClientRect();
    const availableWidth = Math.max(160, viewportWidth - (padding * 2));
    const menuWidth = Math.min(
      Math.max(menuRect.width, 200),
      availableWidth
    );
    const naturalHeight = Math.max(menuRect.height, 1);
    const availableBelow = viewportHeight - toggleRect.bottom - gap - padding;
    const availableAbove = toggleRect.top - gap - padding;
    const opensDown = availableBelow >= naturalHeight || availableBelow >= availableAbove;
    const maxHeight = Math.max(
      120,
      Math.min(
        naturalHeight,
        opensDown ? availableBelow : availableAbove
      )
    );
    const left = Math.min(
      Math.max(toggleRect.right - menuWidth, padding),
      viewportWidth - menuWidth - padding
    );
    const top = opensDown
      ? Math.min(toggleRect.bottom + gap, viewportHeight - maxHeight - padding)
      : Math.max(toggleRect.top - gap - maxHeight, padding);

    menu.style.left = `${Math.max(padding, left)}px`;
    menu.style.top = `${Math.max(padding, top)}px`;
    menu.style.minWidth = '200px';
    menu.style.maxWidth = `${availableWidth}px`;
    menu.style.maxHeight = `${maxHeight}px`;
    menu.style.overflowY = naturalHeight > maxHeight ? 'auto' : '';
    menu.style.visibility = '';
  };

  document.addEventListener('show.bs.dropdown', (event) => {
    const dropdown = event.target?.closest?.('.dropdown');
    if (!isActionDropdown(dropdown)) return;

    if (actionMenu.active && actionMenu.active.dropdown !== dropdown) {
      closeActionMenu();
    }
  });

  document.addEventListener('shown.bs.dropdown', (event) => {
    const dropdown = event.target?.closest?.('.dropdown');
    if (!isActionDropdown(dropdown)) return;

    const toggle = dropdown.querySelector('.btn-action[data-bs-toggle="dropdown"]');
    const menu = dropdown.querySelector('.dropdown-menu') || actionMenu.active?.menu;
    if (!toggle || !menu) return;

    actionMenu.active = {
      dropdown,
      toggle,
      menu,
      originalParent: menu.parentNode,
      originalNextSibling: menu.nextSibling,
    };

    menu.classList.add('action-menu-portal');
    document.body.appendChild(menu);
    toggle.setAttribute('aria-expanded', 'true');
    positionActionMenu();
  });

  document.addEventListener('hidden.bs.dropdown', (event) => {
    const dropdown = event.target?.closest?.('.dropdown');
    if (!isActionDropdown(dropdown)) return;
    if (!actionMenu.active || actionMenu.active.dropdown !== dropdown) return;
    restoreActionMenu();
  });

  document.addEventListener('click', (event) => {
    if (
      actionMenu.active
      && actionMenu.active.menu.contains(event.target)
      && event.target.closest('.dropdown-item')
    ) {
      window.setTimeout(closeActionMenu, 0);
    }
  });

  document.addEventListener('keydown', (event) => {
    if (event.key !== 'Escape' || !actionMenu.active) return;
    const toggle = actionMenu.active.toggle;
    closeActionMenu();
    toggle?.focus();
  });

  const rowActionsDialog = document.getElementById('row-actions-dialog');
  const rowActionsHost = rowActionsDialog?.querySelector('[data-row-actions-menu-host]');
  const rowActionsTitle = document.getElementById('row-actions-dialog-title');
  const coarsePointer = window.matchMedia('(pointer: coarse)');
  const actionItemSelector = '.dropdown-item:not(.disabled):not([disabled]):not([aria-disabled="true"])';
  let activeRowActions = null;

  const normalizedText = (value) => value
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .trim()
    .toLowerCase();

  const isInteractiveTarget = (target) => Boolean(target.closest(
    'a, button, input, select, textarea, label, form, [contenteditable="true"], [role="button"]'
  ));

  const rowActionTitle = (row, toggle) => {
    const label = toggle?.getAttribute('aria-label')?.trim() || '';
    if (label && !['acoes', 'acao'].includes(normalizedText(label))) return label;

    const identifier = row.cells[0]?.innerText.trim().replace(/\s+/g, ' ');
    return identifier ? `Ações do registro ${identifier}` : 'Ações do registro';
  };

  const restoreRowActions = ({ restoreFocus = true } = {}) => {
    if (!activeRowActions) return;

    const { menu, originalParent, originalNextSibling, row } = activeRowActions;
    activeRowActions = null;
    menu.classList.remove('row-actions-menu', 'show', 'action-menu-portal');
    resetMenuStyles(menu);

    if (originalNextSibling?.parentNode === originalParent) {
      originalParent.insertBefore(menu, originalNextSibling);
    } else {
      originalParent?.appendChild(menu);
    }

    if (rowActionsDialog?.open) rowActionsDialog.close();
    if (restoreFocus && row.isConnected) row.focus({ preventScroll: true });
  };

  const openRowActions = (row) => {
    if (!rowActionsDialog || !rowActionsHost || typeof rowActionsDialog.showModal !== 'function') return;

    const menu = row._rowActionsMenu;
    if (!menu) return;
    if (activeRowActions) restoreRowActions({ restoreFocus: false });
    closeActionMenu();

    activeRowActions = {
      row,
      menu,
      originalParent: menu.parentNode,
      originalNextSibling: menu.nextSibling,
    };

    menu.classList.remove('show', 'action-menu-portal');
    resetMenuStyles(menu);
    menu.classList.add('row-actions-menu');
    rowActionsHost.appendChild(menu);
    if (rowActionsTitle) rowActionsTitle.textContent = row.dataset.rowActionsTitle || 'Ações do registro';
    rowActionsDialog.showModal();

    window.requestAnimationFrame(() => {
      menu.querySelector(actionItemSelector)?.focus();
    });
  };

  const actionableRows = (table) => Array.from(table.tBodies)
    .flatMap((body) => Array.from(body.rows))
    .filter((row) => row.classList.contains('row-actions-trigger'));

  const setCurrentRow = (row, focus = false) => {
    actionableRows(row.closest('table')).forEach((candidate) => {
      candidate.tabIndex = candidate === row ? 0 : -1;
    });
    if (focus) row.focus({ preventScroll: true });
  };

  const addRowActionsHint = (table) => {
    const wrapper = table.closest('.table-panel-wrap');
    if (!wrapper || wrapper.previousElementSibling?.classList.contains('row-actions-hint')) return;

    const hint = document.createElement('p');
    hint.className = 'row-actions-hint';
    hint.innerHTML = '<i class="bi bi-cursor" aria-hidden="true"></i><span></span>';
    hint.querySelector('span').textContent = coarsePointer.matches
      ? 'Toque na linha para ver as ações.'
      : 'Dê dois cliques na linha ou use Enter para ver as ações.';
    wrapper.before(hint);
  };

  const enhanceActionTable = (table) => {
    if (table.closest('.modal, dialog')) return;

    const headers = Array.from(table.tHead?.rows[0]?.cells || []);
    const actionIndex = headers.findIndex((header) => normalizedText(header.textContent) === 'acoes');
    if (actionIndex < 0) return;

    const rows = Array.from(table.tBodies).flatMap((body) => Array.from(body.rows));
    const sources = rows.map((row) => ({ row, cell: row.cells[actionIndex] }))
      .filter(({ row, cell }) => !row.classList.contains('row-actions-trigger') && cell?.querySelector('.dropdown-menu'));
    if (sources.length === 0) {
      if (actionableRows(table).length === 0) {
        table.classList.remove('row-actions-table');
        delete table.dataset.rowActionsReady;
        headers[actionIndex].classList.remove('row-actions-source-cell');
        const hint = table.closest('.table-panel-wrap')?.previousElementSibling;
        if (hint?.classList.contains('row-actions-hint')) hint.remove();
      }
      return;
    }

    table.dataset.rowActionsReady = 'true';
    table.classList.add('row-actions-table');
    headers[actionIndex].classList.add('row-actions-source-cell');
    rows.forEach((row) => row.cells[actionIndex]?.classList.add('row-actions-source-cell'));

    let hasFocusableRow = actionableRows(table).length > 0;
    sources.forEach(({ row, cell }) => {
      const menu = cell.querySelector('.dropdown-menu');
      const toggle = cell.querySelector('.btn-action[data-bs-toggle="dropdown"]');
      const action = menu.querySelector(actionItemSelector);
      if (!action) return;

      row._rowActionsMenu = menu;
      row.dataset.rowActionsTitle = rowActionTitle(row, toggle);
      row.classList.add('row-actions-trigger');
      row.tabIndex = hasFocusableRow ? -1 : 0;
      row.setAttribute('aria-haspopup', 'dialog');
      row.setAttribute('aria-controls', 'row-actions-dialog');
      row.setAttribute('aria-describedby', 'row-actions-table-instructions');
      hasFocusableRow = true;
    });

    if (hasFocusableRow) addRowActionsHint(table);
  };

  if (rowActionsDialog && rowActionsHost && typeof rowActionsDialog.showModal === 'function') {
    window.OSMais = window.OSMais || {};
    window.OSMais.refreshActionTables = (root = document) => {
      closeActionMenu();
      if (activeRowActions) restoreRowActions({ restoreFocus: false });
      root.querySelectorAll('table.os-table').forEach(enhanceActionTable);
    };
    window.OSMais.refreshActionTables();

    document.addEventListener('dblclick', (event) => {
      const row = event.target.closest('tr.row-actions-trigger');
      if (!row || isInteractiveTarget(event.target)) return;
      setCurrentRow(row);
      openRowActions(row);
    });

    document.addEventListener('pointerup', (event) => {
      const row = event.target.closest('tr.row-actions-trigger');
      if (!row || isInteractiveTarget(event.target) || !['touch', 'pen'].includes(event.pointerType)) return;
      setCurrentRow(row);
      openRowActions(row);
    });

    document.addEventListener('focusin', (event) => {
      const row = event.target.closest?.('tr.row-actions-trigger');
      if (row && event.target === row) setCurrentRow(row);
    });

    document.addEventListener('keydown', (event) => {
      const row = event.target.closest?.('tr.row-actions-trigger');
      if (!row || event.target !== row) return;
      if (event.key === 'Enter' || event.key === ' ') {
        event.preventDefault();
        openRowActions(row);
        return;
      }

      if (!['ArrowDown', 'ArrowUp', 'Home', 'End'].includes(event.key)) return;
      event.preventDefault();
      const rows = actionableRows(row.closest('table'));
      const current = rows.indexOf(row);
      const next = event.key === 'Home' ? 0
        : event.key === 'End' ? rows.length - 1
          : Math.min(rows.length - 1, Math.max(0, current + (event.key === 'ArrowDown' ? 1 : -1)));
      setCurrentRow(rows[next], true);
    });

    rowActionsDialog.addEventListener('cancel', (event) => {
      event.preventDefault();
      restoreRowActions();
    });
    rowActionsDialog.addEventListener('close', () => restoreRowActions());
    rowActionsDialog.addEventListener('click', (event) => {
      if (event.target.closest('[data-row-actions-close]')) {
        event.preventDefault();
        restoreRowActions();
        return;
      }
      const action = event.target.closest('.dropdown-item');
      if (action) {
        const returnRow = activeRowActions?.row;
        const targetSelector = action.getAttribute('data-bs-target');
        const targetModal = targetSelector?.startsWith('#') ? document.querySelector(targetSelector) : null;
        targetModal?.addEventListener('hidden.bs.modal', () => {
          if (returnRow?.isConnected) setCurrentRow(returnRow, true);
        }, { once: true });
        const handsOffFocus = action.matches('[data-bs-toggle="modal"]');
        restoreRowActions({ restoreFocus: !handsOffFocus });
      }
    }, true);
    rowActionsDialog.addEventListener('click', (event) => {
      if (event.target !== rowActionsDialog) return;
      const rect = rowActionsDialog.getBoundingClientRect();
      const outside = event.clientX < rect.left || event.clientX > rect.right
        || event.clientY < rect.top || event.clientY > rect.bottom;
      if (outside) restoreRowActions();
    });
  }

  window.addEventListener('resize', positionActionMenu, { passive: true });
  document.addEventListener('scroll', positionActionMenu, {
    passive: true,
    capture: true,
  });
});
