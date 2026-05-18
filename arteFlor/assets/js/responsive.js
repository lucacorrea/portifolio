(function () {
  'use strict';

  function qs(selector, root = document) {
    return root.querySelector(selector);
  }

  function qsa(selector, root = document) {
    return Array.from(root.querySelectorAll(selector));
  }

  function ensurePublicMobileMenu() {
    const header = qs('.site-header');
    const nav = qs('.main-nav');

    if (!header || !nav) return;

    let toggle = qs('.menu-toggle');

    if (!toggle) {
      toggle = document.createElement('button');
      toggle.type = 'button';
      toggle.className = 'menu-toggle';
      toggle.setAttribute('aria-label', 'Abrir menu');
      toggle.setAttribute('aria-expanded', 'false');
      toggle.innerHTML = `
        <span class="menu-toggle-icon" aria-hidden="true">
          <span></span>
          <span></span>
          <span></span>
        </span>
        <span class="menu-toggle-text">Menu</span>
      `;

      const headerInner = qs('.header-inner', header) || header;
      headerInner.appendChild(toggle);
    }

    if (!nav.id) {
      nav.id = 'mainNav';
    }

    toggle.setAttribute('aria-controls', nav.id);

    let backdrop = qs('.mobile-menu-backdrop');

    if (!backdrop) {
      backdrop = document.createElement('div');
      backdrop.className = 'mobile-menu-backdrop';
      document.body.appendChild(backdrop);
    }

    function closeMenu() {
      nav.classList.remove('open');
      document.body.classList.remove('menu-open');
      toggle.setAttribute('aria-expanded', 'false');
      toggle.setAttribute('aria-label', 'Abrir menu');
    }

    function openMenu() {
      nav.classList.add('open');
      document.body.classList.add('menu-open');
      toggle.setAttribute('aria-expanded', 'true');
      toggle.setAttribute('aria-label', 'Fechar menu');
    }

    toggle.addEventListener('click', function (event) {
      event.preventDefault();
      event.stopPropagation();

      if (nav.classList.contains('open')) {
        closeMenu();
      } else {
        openMenu();
      }
    });

    backdrop.addEventListener('click', closeMenu);

    qsa('a', nav).forEach(function (link) {
      link.addEventListener('click', function () {
        closeMenu();
      });
    });

    document.addEventListener('keydown', function (event) {
      if (event.key === 'Escape') {
        closeMenu();
      }
    });

    window.addEventListener('resize', function () {
      if (window.innerWidth > 920) {
        closeMenu();
      }
    });
  }

  function ensureAdminMobileMenu() {
    const sidebar = qs('.admin-sidebar');
    const shell = qs('.admin-shell');

    if (!sidebar || !shell) return;

    let toggle = qs('.admin-mobile-toggle');

    if (!toggle) {
      toggle = document.createElement('button');
      toggle.type = 'button';
      toggle.className = 'admin-mobile-toggle';
      toggle.setAttribute('aria-label', 'Abrir menu administrativo');
      toggle.setAttribute('aria-expanded', 'false');
      toggle.textContent = '☰';
      document.body.appendChild(toggle);
    }

    let backdrop = qs('.admin-mobile-backdrop');

    if (!backdrop) {
      backdrop = document.createElement('div');
      backdrop.className = 'admin-mobile-backdrop';
      document.body.appendChild(backdrop);
    }

    function closeAdminMenu() {
      document.body.classList.remove('admin-menu-open');
      toggle.setAttribute('aria-expanded', 'false');
      toggle.setAttribute('aria-label', 'Abrir menu administrativo');
      toggle.textContent = '☰';
    }

    function openAdminMenu() {
      document.body.classList.add('admin-menu-open');
      toggle.setAttribute('aria-expanded', 'true');
      toggle.setAttribute('aria-label', 'Fechar menu administrativo');
      toggle.textContent = '×';
    }

    toggle.addEventListener('click', function (event) {
      event.preventDefault();
      event.stopPropagation();

      if (document.body.classList.contains('admin-menu-open')) {
        closeAdminMenu();
      } else {
        openAdminMenu();
      }
    });

    backdrop.addEventListener('click', closeAdminMenu);

    qsa('a', sidebar).forEach(function (link) {
      link.addEventListener('click', closeAdminMenu);
    });

    document.addEventListener('keydown', function (event) {
      if (event.key === 'Escape') {
        closeAdminMenu();
      }
    });

    window.addEventListener('resize', function () {
      if (window.innerWidth > 920) {
        closeAdminMenu();
      }
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    ensurePublicMobileMenu();
    ensureAdminMobileMenu();
  });
})();