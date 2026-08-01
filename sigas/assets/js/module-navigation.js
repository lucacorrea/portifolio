'use strict';

/* O menu é renderizado no servidor; este arquivo controla apenas a interação móvel. */
(() => {
    const shell = document.querySelector('[data-module-shell]');
    if (!shell) return;

    const setMenu = open => {
        shell.classList.toggle('module-menu-open', open);
        document.body.classList.toggle('module-menu-is-open', open);
        document.querySelectorAll('[data-module-menu-toggle]').forEach(button => {
            button.setAttribute('aria-expanded', String(open));
        });
    };

    document.addEventListener('click', event => {
        if (event.target.closest('[data-module-menu-toggle]')) setMenu(true);
        if (event.target.closest('[data-module-menu-close]')) setMenu(false);
        if (shell.classList.contains('module-menu-open') && event.target === shell) setMenu(false);
    });

    document.addEventListener('keydown', event => {
        if (event.key === 'Escape' && shell.classList.contains('module-menu-open')) setMenu(false);
    });
})();
