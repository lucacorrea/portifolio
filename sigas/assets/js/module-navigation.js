'use strict';

/*
 * O menu é renderizado no servidor; este arquivo controla a interação móvel.
 *
 * Compatibilidade visual:
 * páginas legadas do SIGAS ainda usam shell próprio, mas compartilham este script.
 * Enquanto são migradas para module-layout.php, conectamos nelas o Design System
 * Operacional Global sem alterar seu HTML, regras de negócio ou navegação.
 */
(() => {
    const currentScript = document.currentScript;
    const shell = document.querySelector('[data-module-shell]');
    if (!shell) return;

    const loadStylesheetOnce = (href, selector, datasetName, datasetValue) => {
        if (document.querySelector(selector)) return;
        const stylesheet = document.createElement('link');
        stylesheet.rel = 'stylesheet';
        stylesheet.href = href;
        stylesheet.dataset[datasetName] = datasetValue;
        document.head.appendChild(stylesheet);
    };

    const isOfficialLayout = document.body.classList.contains('frontend-module-page');
    if (!isOfficialLayout && currentScript && currentScript.src) {
        document.body.classList.add('sigas-legacy-module-page');

        loadStylesheetOnce(
            new URL('../css/sigas-global-layout.css', currentScript.src).href,
            'link[data-sigas-global-layout], link[href*="sigas-global-layout.css"]',
            'sigasGlobalLayout',
            'legacy-bridge'
        );

        loadStylesheetOnce(
            new URL('../css/sigas-legacy-layout.css', currentScript.src).href,
            'link[data-sigas-legacy-layout], link[href*="sigas-legacy-layout.css"]',
            'sigasLegacyLayout',
            'v1'
        );
    }

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
