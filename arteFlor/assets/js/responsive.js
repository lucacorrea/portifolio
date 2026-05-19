(function () {
  'use strict';

  function ready(callback) {
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', callback);
    } else {
      callback();
    }
  }

  function createMenuButton(headerInner) {
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'menu-toggle';
    button.setAttribute('aria-label', 'Abrir menu');
    button.setAttribute('aria-expanded', 'false');
    button.innerHTML = `
      <span class="menu-toggle-icon" aria-hidden="true">
        <span></span>
        <span></span>
        <span></span>
      </span>
      <span class="menu-toggle-text">Menu</span>
    `;

    headerInner.appendChild(button);
    return button;
  }

  function initPublicMenu() {
    const header = document.querySelector('.site-header');
    const headerInner = document.querySelector('.header-inner') || header;
    const nav = document.querySelector('.main-nav');

    if (!header || !headerInner || !nav) return;

    let button = document.querySelector('.menu-toggle');

    if (!button) {
      button = createMenuButton(headerInner);
    }

    if (!nav.id) {
      nav.id = 'mainNavMobile';
    }

    button.setAttribute('aria-controls', nav.id);

    let backdrop = document.querySelector('.mobile-menu-backdrop');

    if (!backdrop) {
      backdrop = document.createElement('div');
      backdrop.className = 'mobile-menu-backdrop';
      document.body.appendChild(backdrop);
    }

    function isOpen() {
      return nav.classList.contains('open');
    }

    function openMenu() {
      nav.classList.add('open');
      document.body.classList.add('menu-open');
      button.classList.add('is-open');
      button.setAttribute('aria-expanded', 'true');
      button.setAttribute('aria-label', 'Fechar menu');
    }

    function closeMenu() {
      nav.classList.remove('open');
      document.body.classList.remove('menu-open');
      button.classList.remove('is-open');
      button.setAttribute('aria-expanded', 'false');
      button.setAttribute('aria-label', 'Abrir menu');
    }

    function toggleMenu(event) {
      event.preventDefault();
      event.stopPropagation();
      event.stopImmediatePropagation();

      if (isOpen()) {
        closeMenu();
      } else {
        openMenu();
      }
    }

    button.addEventListener('click', toggleMenu, true);
    button.addEventListener('touchend', toggleMenu, true);

    backdrop.addEventListener('click', closeMenu);

    nav.querySelectorAll('a').forEach(function (link) {
      link.addEventListener('click', function () {
        closeMenu();
      });
    });

    document.addEventListener('click', function (event) {
      if (!isOpen()) return;

      const clickedInsideNav = nav.contains(event.target);
      const clickedButton = button.contains(event.target);

      if (!clickedInsideNav && !clickedButton) {
        closeMenu();
      }
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

  function initAdminMenu() {
    const sidebar = document.querySelector('.admin-sidebar');
    const shell = document.querySelector('.admin-shell');

    if (!sidebar || !shell) return;

    let button = document.querySelector('.admin-mobile-toggle');

    if (!button) {
      button = document.createElement('button');
      button.type = 'button';
      button.className = 'admin-mobile-toggle';
      button.setAttribute('aria-label', 'Abrir menu administrativo');
      button.setAttribute('aria-expanded', 'false');
      button.textContent = '☰';
      document.body.appendChild(button);
    }

    let backdrop = document.querySelector('.admin-mobile-backdrop');

    if (!backdrop) {
      backdrop = document.createElement('div');
      backdrop.className = 'admin-mobile-backdrop';
      document.body.appendChild(backdrop);
    }

    function isOpen() {
      return document.body.classList.contains('admin-menu-open');
    }

    function openMenu() {
      document.body.classList.add('admin-menu-open');
      button.textContent = '×';
      button.setAttribute('aria-expanded', 'true');
      button.setAttribute('aria-label', 'Fechar menu administrativo');
    }

    function closeMenu() {
      document.body.classList.remove('admin-menu-open');
      button.textContent = '☰';
      button.setAttribute('aria-expanded', 'false');
      button.setAttribute('aria-label', 'Abrir menu administrativo');
    }

    function toggleMenu(event) {
      event.preventDefault();
      event.stopPropagation();
      event.stopImmediatePropagation();

      if (isOpen()) {
        closeMenu();
      } else {
        openMenu();
      }
    }

    button.addEventListener('click', toggleMenu, true);
    button.addEventListener('touchend', toggleMenu, true);

    backdrop.addEventListener('click', closeMenu);

    sidebar.querySelectorAll('a').forEach(function (link) {
      link.addEventListener('click', closeMenu);
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

  ready(function () {
    initPublicMenu();
    initAdminMenu();
  });
})();