(() => {
    'use strict';

    const root = document.querySelector('[data-report-page]');

    if (!(root instanceof HTMLElement)) {
        return;
    }

    const filterForm = root.querySelector('[data-report-filter-form]');
    const workspace = root.querySelector('[data-report-active-section]');

    function currentParams() {
        const serialized = workspace instanceof HTMLElement
            ? workspace.dataset.currentParams || ''
            : '';

        if (serialized !== '') {
            return new URLSearchParams(serialized);
        }

        const params = new URLSearchParams();

        if (filterForm instanceof HTMLFormElement) {
            const data = new FormData(filterForm);

            for (const [key, value] of data.entries()) {
                if (typeof value !== 'string') {
                    continue;
                }

                const normalized = value.trim();

                if (normalized !== '') {
                    params.set(key, normalized);
                }
            }
        }

        return params;
    }

    function printUrl() {
        const params = currentParams();

        const activeSection = workspace instanceof HTMLElement
            ? workspace.dataset.section
                || root.dataset.initialSection
                || 'clientes'
            : root.dataset.initialSection || 'clientes';

        /*
         * A impressão sempre busca todos os registros do período.
         * Não envia paginação ou identificadores de modal.
         */
        params.delete('page');
        params.delete('pagina');
        params.delete('per_page');
        params.delete('acao');
        params.delete('client_id');
        params.delete('group_key');

        params.set('source_section', activeSection);

        const url = new URL(
            'actions/relatorio-imprimir.php',
            document.baseURI
        );

        url.search = params.toString();

        return url.toString();
    }

    /*
     * Usa captura para impedir que o JavaScript antigo execute
     * window.print() diretamente sobre a página de relatórios.
     */
    document.addEventListener(
        'click',
        (event) => {
            const target = event.target;

            if (!(target instanceof Element)) {
                return;
            }

            const button = target.closest(
                '[data-report-print-current]'
            );

            if (!(button instanceof HTMLElement)) {
                return;
            }

            event.preventDefault();
            event.stopPropagation();
            event.stopImmediatePropagation();

            const url = printUrl();

            const popup = window.open(
                url,
                '_blank',
                'noopener,noreferrer'
            );

            /*
             * Fallback quando o navegador bloquear a nova aba.
             */
            if (popup === null) {
                window.location.href = url;
            }
        },
        true
    );
})();