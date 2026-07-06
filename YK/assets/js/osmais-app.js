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

  window.addEventListener('resize', positionActionMenu, { passive: true });
  document.addEventListener('scroll', positionActionMenu, {
    passive: true,
    capture: true,
  });
});
