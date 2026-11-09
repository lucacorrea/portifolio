'use strict';

(() => {
    const access = window.SIGAS_CONTEXT?.primeiroEmpregoAccess;
    if (!access || typeof access !== 'object') return;

    const allRoutes = new Set(Array.isArray(access.allRoutes) ? access.allRoutes : []);
    const allowedRoutes = new Set(Array.isArray(access.allowedRoutes) ? access.allowedRoutes : []);

    const normalizeRoute = href => {
        if (typeof href !== 'string' || href.trim() === '') return '';

        try {
            const url = new URL(href, document.baseURI);
            const marker = '/primeiro-emprego/';
            const index = url.pathname.indexOf(marker);
            if (index < 0) return '';
            return `primeiro-emprego/${url.pathname.slice(index + marker.length)}`;
        } catch (_) {
            return '';
        }
    };

    document.querySelectorAll('a[href]').forEach(anchor => {
        const route = normalizeRoute(anchor.getAttribute('href'));
        if (route !== '' && allRoutes.has(route) && !allowedRoutes.has(route)) {
            anchor.remove();
        }
    });

    const actionAllowed = action => {
        if (access.currentPage === 'candidatos' && action === 'delete_candidate') {
            return access.canDeleteCandidate === true;
        }
        return access.canCurrentAction === true;
    };

    if (access.currentPage === 'candidatos' && access.canCurrentAction !== true) {
        document.querySelectorAll(
            '[data-pe-modal-action-review], [data-pe-modal-action-visit], [data-pe-modal-action-profile]'
        ).forEach(action => action.remove());
    }

    document.querySelectorAll('[data-pe-open]').forEach(opener => {
        const selector = opener.getAttribute('data-pe-open');
        if (!selector || !selector.startsWith('#')) return;

        const target = document.querySelector(selector);
        const actionInput = target?.querySelector('input[name="pe_action"]');
        const action = actionInput?.value?.trim() || '';

        if (action !== '' && !actionAllowed(action)) {
            opener.remove();
        }
    });

    document.querySelectorAll('dialog').forEach(dialog => {
        const actionInput = dialog.querySelector('input[name="pe_action"]');
        const action = actionInput?.value?.trim() || '';
        if (action !== '' && !actionAllowed(action)) {
            dialog.remove();
        }
    });
})();
