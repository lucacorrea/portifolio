(function () {
  'use strict';

  document.addEventListener('DOMContentLoaded', () => {
    initSidebar();
    initProfileDropdown();
    initModals();
    syncGlobalSearch();
  });

  function initSidebar() {
    const sidebar = document.getElementById('sidebar') || document.querySelector('.sidebar');
    const overlay = document.getElementById('sidebarOverlay') || document.getElementById('menu-overlay') || document.getElementById('overlay');
    const toggles = [
      document.getElementById('menuToggle'),
      document.getElementById('menu-toggle')
    ].filter(Boolean);

    if (!sidebar) return;

    const close = () => {
      sidebar.classList.remove('open', 'is-open');
      overlay?.classList.remove('show', 'is-open');
      overlay?.classList.add('hidden');
      overlay?.setAttribute('aria-hidden', 'true');
    };

    const open = () => {
      sidebar.classList.add('open');
      overlay?.classList.add('show', 'is-open');
      overlay?.classList.remove('hidden');
      overlay?.setAttribute('aria-hidden', 'false');
    };

    toggles.forEach((toggle) => {
      toggle.addEventListener('click', () => {
        if (sidebar.classList.contains('open') || sidebar.classList.contains('is-open')) close();
        else open();
      });
    });

    overlay?.addEventListener('click', close);
    document.addEventListener('keydown', (event) => {
      if (event.key === 'Escape') close();
    });
  }

  function initProfileDropdown() {
    const button = document.getElementById('profileBtn') || document.getElementById('profile-btn');
    const dropdown = document.getElementById('profileDropdown') || document.getElementById('profile-dropdown');

    if (!button || !dropdown) return;

    const close = () => {
      dropdown.classList.add('hidden');
      button.setAttribute('aria-expanded', 'false');
    };

    const toggle = (event) => {
      event.stopPropagation();
      dropdown.classList.toggle('hidden');
      button.setAttribute('aria-expanded', dropdown.classList.contains('hidden') ? 'false' : 'true');
    };

    button.addEventListener('click', toggle);
    button.addEventListener('keydown', (event) => {
      if (event.key === 'Enter' || event.key === ' ') toggle(event);
    });
    dropdown.addEventListener('click', (event) => event.stopPropagation());
    document.addEventListener('click', close);
    document.addEventListener('keydown', (event) => {
      if (event.key === 'Escape') close();
    });
  }

  function initModals() {
    document.addEventListener('click', (event) => {
      const trigger = event.target.closest('[data-modal]');
      const close = event.target.closest('[data-modal-close]');

      if (trigger) {
        const modal = document.getElementById(`${trigger.dataset.modal}Modal`) || document.getElementById(trigger.dataset.modal);
        modal?.classList.add('is-open');
        modal?.setAttribute('aria-hidden', 'false');
      }

      if (close) {
        const modal = close.closest('.modal');
        modal?.classList.remove('is-open');
        modal?.setAttribute('aria-hidden', 'true');
      }

      if (event.target.classList.contains('modal')) {
        event.target.classList.remove('is-open');
        event.target.setAttribute('aria-hidden', 'true');
      }
    });
  }

  function syncGlobalSearch() {
    const global = document.getElementById('globalSearch');
    const table = document.getElementById('tableSearch');
    if (!global || !table || global === table) return;

    global.addEventListener('input', () => {
      table.value = global.value;
      table.dispatchEvent(new Event('input', { bubbles: true }));
    });
  }
})();
