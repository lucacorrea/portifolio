(() => {
    'use strict';

    const root = document.querySelector('[data-report-page]');

    /*
     * A página atual ainda não possui a estrutura nova.
     * Até atualizarmos pages/relatorios.php, o script encerra
     * sem alterar o funcionamento existente.
     */
    if (!(root instanceof HTMLElement)) {
        return;
    }

    const endpoint = root.dataset.sectionEndpoint
        || 'actions/relatorio-secao-carregar.php';

    const filterForm = root.querySelector(
        '[data-report-filter-form]'
    );

    /*
     * Cache de conteúdos já carregados.
     *
     * A chave considera:
     * - seção;
     * - período;
     * - página;
     * - busca;
     * - ordenação;
     * - direção;
     * - detalhamento.
     */
    const cache = new Map();

    /*
     * Evita duas requisições simultâneas exatamente iguais.
     */
    const pendingRequests = new Map();

    const jsonHeaders = {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    };

    /**
     * Obtém os valores válidos do filtro global.
     *
     * @returns {URLSearchParams}
     */
    function periodParams() {
        const params = new URLSearchParams();

        if (!(filterForm instanceof HTMLFormElement)) {
            return params;
        }

        const formData = new FormData(filterForm);

        for (const [key, value] of formData.entries()) {
            if (typeof value !== 'string') {
                continue;
            }

            const normalized = value.trim();

            if (normalized !== '') {
                params.set(key, normalized);
            }
        }

        return params;
    }

    /**
     * Monta os parâmetros da seção sem confiar em URLs prontas.
     *
     * @param {string} section
     * @param {Record<string, unknown>} overrides
     * @returns {URLSearchParams}
     */
    function normalizedParams(section, overrides = {}) {
        const params = periodParams();

        params.set('secao', section);

        for (const [key, value] of Object.entries(overrides)) {
            if (
                value === null
                || value === undefined
                || value === ''
            ) {
                params.delete(key);
                continue;
            }

            params.set(key, String(value));
        }

        /*
         * Ordena os parâmetros para gerar uma chave de cache estável.
         */
        const ordered = new URLSearchParams();

        [...params.entries()]
            .sort(([left], [right]) => left.localeCompare(right))
            .forEach(([key, value]) => {
                ordered.append(key, value);
            });

        return ordered;
    }

    /**
     * @param {string} section
     * @param {URLSearchParams} params
     * @returns {string}
     */
    function cacheKey(section, params) {
        return `${section}?${params.toString()}`;
    }

    /**
     * @param {HTMLElement} sectionElement
     * @returns {HTMLElement|null}
     */
    function sectionHost(sectionElement) {
        const host = sectionElement.querySelector(
            '[data-report-section-content]'
        );

        return host instanceof HTMLElement ? host : null;
    }

    /**
     * Cria estados visuais sem inserir mensagem externa como HTML.
     *
     * @param {string} icon
     * @param {string} title
     * @param {string} message
     * @param {string} retrySection
     * @returns {HTMLElement}
     */
    function createState(
        icon,
        title,
        message,
        retrySection = ''
    ) {
        const wrapper = document.createElement('div');

        wrapper.className = 'report-section-state';
        wrapper.setAttribute(
            'role',
            retrySection === '' ? 'status' : 'alert'
        );

        const iconElement = document.createElement('i');

        iconElement.className = `bi ${icon}`;
        iconElement.setAttribute('aria-hidden', 'true');

        const strong = document.createElement('strong');

        strong.textContent = title;

        const paragraph = document.createElement('p');

        paragraph.textContent = message;

        wrapper.append(
            iconElement,
            strong,
            paragraph
        );

        if (retrySection !== '') {
            const button = document.createElement('button');

            button.type = 'button';
            button.className = 'btn-filter btn-filter-ghost';
            button.dataset.reportRetry = retrySection;

            button.innerHTML = [
                '<i class="bi bi-arrow-clockwise"',
                ' aria-hidden="true"></i>',
                ' Tentar novamente',
            ].join('');

            wrapper.append(button);
        }

        return wrapper;
    }

    /**
     * @param {HTMLElement} host
     */
    function renderLoading(host) {
        host.replaceChildren(
            createState(
                'bi-arrow-repeat report-loading-icon',
                'Carregando relatório',
                'Os dados desta seção estão sendo preparados.'
            )
        );

        host.setAttribute('aria-busy', 'true');
    }

    /**
     * @param {HTMLElement} host
     * @param {string} section
     * @param {string} message
     */
    function renderError(host, section, message) {
        host.replaceChildren(
            createState(
                'bi-exclamation-circle',
                'Não foi possível carregar esta seção',
                message
                    || 'Tente novamente em alguns instantes.',
                section
            )
        );

        host.setAttribute('aria-busy', 'false');
    }

    /**
     * O HTML recebido deverá ser produzido exclusivamente
     * pelas partials PHP internas, com dados escapados no backend.
     *
     * @param {HTMLElement} host
     * @param {string} html
     */
    function renderHtml(host, html) {
        host.innerHTML = html;
        host.setAttribute('aria-busy', 'false');
    }

    /**
     * Executa a consulta JSON com timeout e cache de promessas.
     *
     * @param {URLSearchParams} params
     * @param {string} requestKey
     * @returns {Promise<Record<string, unknown>>}
     */
    async function requestJson(params, requestKey) {
        if (pendingRequests.has(requestKey)) {
            return pendingRequests.get(requestKey);
        }

        const controller = new AbortController();

        const timeout = window.setTimeout(
            () => controller.abort(),
            30000
        );

        const promise = fetch(
            `${endpoint}?${params.toString()}`,
            {
                method: 'GET',
                credentials: 'same-origin',
                headers: jsonHeaders,
                cache: 'no-store',
                signal: controller.signal,
            }
        )
            .then(async (response) => {
                let payload = null;

                try {
                    payload = await response.json();
                } catch (error) {
                    payload = null;
                }

                if (
                    !response.ok
                    || !payload
                    || payload.success !== true
                ) {
                    const message = (
                        payload
                        && typeof payload.message === 'string'
                    )
                        ? payload.message
                        : 'Não foi possível carregar esta seção do relatório.';

                    throw new Error(message);
                }

                return payload;
            })
            .finally(() => {
                window.clearTimeout(timeout);
                pendingRequests.delete(requestKey);
            });

        pendingRequests.set(requestKey, promise);

        return promise;
    }

    /**
     * Carrega uma seção recolhível.
     *
     * @param {HTMLElement} sectionElement
     * @param {Record<string, unknown>} overrides
     * @param {boolean} force
     */
    async function loadSection(
        sectionElement,
        overrides = {},
        force = false
    ) {
        if (!(sectionElement instanceof HTMLElement)) {
            return;
        }

        const section = sectionElement.dataset.section || '';

        if (
            ![
                'clientes',
                'servicos',
                'equipe',
            ].includes(section)
        ) {
            return;
        }

        const host = sectionHost(sectionElement);

        if (host === null) {
            return;
        }

        const params = normalizedParams(
            section,
            overrides
        );

        const key = cacheKey(section, params);

        /*
         * A mesma combinação já está exibida.
         */
        if (
            !force
            && sectionElement.dataset.loadedKey === key
        ) {
            return;
        }

        /*
         * A combinação já foi carregada anteriormente.
         */
        if (!force && cache.has(key)) {
            renderHtml(
                host,
                cache.get(key)
            );

            sectionElement.dataset.loadedKey = key;
            sectionElement.dataset.currentParams =
                params.toString();

            updateSectionActions(
                sectionElement,
                params
            );

            return;
        }

        renderLoading(host);

        try {
            const payload = await requestJson(
                params,
                key
            );

            const html = typeof payload.html === 'string'
                ? payload.html
                : '';

            if (html === '') {
                throw new Error(
                    'A seção não retornou conteúdo válido.'
                );
            }

            cache.set(key, html);

            renderHtml(
                host,
                html
            );

            sectionElement.dataset.loadedKey = key;
            sectionElement.dataset.currentParams =
                params.toString();

            updateSectionActions(
                sectionElement,
                params
            );
        } catch (error) {
            const message = (
                error instanceof Error
                && error.name === 'AbortError'
            )
                ? 'A consulta demorou mais que o esperado. Tente novamente.'
                : (
                    error instanceof Error
                        ? error.message
                        : 'Não foi possível carregar esta seção do relatório.'
                );

            renderError(
                host,
                section,
                message
            );
        }
    }

    /**
     * Atualiza URLs internas de exportação conforme os filtros atuais.
     *
     * @param {HTMLElement} sectionElement
     * @param {URLSearchParams} params
     */
    function updateSectionActions(
        sectionElement,
        params
    ) {
        const section =
            sectionElement.dataset.section || '';

        const exportLink = sectionElement.querySelector(
            '[data-report-export-section]'
        );

        if (exportLink instanceof HTMLAnchorElement) {
            const exportParams =
                new URLSearchParams(params);

            exportParams.set(
                'secao',
                section
            );

            exportLink.href = [
                'actions/relatorio-exportar.php?',
                exportParams.toString(),
            ].join('');
        }
    }

    /**
     * @param {string} section
     * @returns {HTMLElement|null}
     */
    function sectionElementByName(section) {
        const elements = root.querySelectorAll(
            '[data-report-section]'
        );

        for (const element of elements) {
            if (
                element instanceof HTMLElement
                && element.dataset.section === section
            ) {
                return element;
            }
        }

        return null;
    }

    /**
     * Retorna os parâmetros atualmente exibidos na seção.
     *
     * @param {HTMLElement} sectionElement
     * @returns {URLSearchParams}
     */
    function currentSectionParams(sectionElement) {
        const current =
            sectionElement.dataset.currentParams || '';

        if (current !== '') {
            return new URLSearchParams(current);
        }

        return normalizedParams(
            sectionElement.dataset.section || ''
        );
    }

    /**
     * Preserva busca e ordenação da seção ao trocar página.
     *
     * @param {HTMLElement} sectionElement
     * @param {Record<string, unknown>} changes
     * @returns {Record<string, string|number>}
     */
    function sectionOverridesFromCurrent(
        sectionElement,
        changes = {}
    ) {
        const params =
            currentSectionParams(sectionElement);

        const result = {};

        for (const [key, value] of params.entries()) {
            if (
                ![
                    'modo',
                    'competencia',
                    'data_inicial',
                    'data_final',
                    'secao',
                ].includes(key)
            ) {
                result[key] = value;
            }
        }

        return {
            ...result,
            ...changes,
        };
    }

    /**
     * Alterna os campos visíveis do filtro global.
     */
    function setupPeriodMode() {
        if (!(filterForm instanceof HTMLFormElement)) {
            return;
        }

        const modeFields = [
            ...filterForm.querySelectorAll(
                '[name="modo"]'
            ),
        ];

        const monthFields = filterForm.querySelector(
            '[data-period-month-fields]'
        );

        const customFields = filterForm.querySelector(
            '[data-period-custom-fields]'
        );

        const refresh = () => {
            const checked = modeFields.find(
                (field) => (
                    field instanceof HTMLInputElement
                    && field.checked
                )
            );

            const mode = checked instanceof HTMLInputElement
                ? checked.value
                : 'mes';

            const monthMode = mode === 'mes';

            if (monthFields instanceof HTMLElement) {
                monthFields.hidden = !monthMode;

                monthFields
                    .querySelectorAll('input, select')
                    .forEach((field) => {
                        if (
                            field instanceof HTMLInputElement
                            || field instanceof HTMLSelectElement
                        ) {
                            field.disabled = !monthMode;
                        }
                    });
            }

            if (customFields instanceof HTMLElement) {
                customFields.hidden = monthMode;

                customFields
                    .querySelectorAll('input, select')
                    .forEach((field) => {
                        if (
                            field instanceof HTMLInputElement
                            || field instanceof HTMLSelectElement
                        ) {
                            field.disabled = monthMode;
                        }
                    });
            }
        };

        modeFields.forEach((field) => {
            field.addEventListener(
                'change',
                refresh
            );
        });

        refresh();
    }

    /**
     * Abre o detalhamento de cliente ou serviço.
     *
     * @param {HTMLElement} button
     * @param {'cliente'|'servico'} type
     */
    async function openDetail(button, type) {
        const modalElement =
            document.getElementById('report-detail-modal');

        const body = modalElement?.querySelector(
            '[data-report-detail-body]'
        );

        const title = modalElement?.querySelector(
            '[data-report-detail-title]'
        );

        if (
            !(modalElement instanceof HTMLElement)
            || !(body instanceof HTMLElement)
        ) {
            return;
        }

        const section = type === 'cliente'
            ? 'clientes'
            : 'servicos';

        const params = normalizedParams(
            section,
            {
                acao: 'detalhes',
                client_id: type === 'cliente'
                    ? button.dataset.clientId || ''
                    : '',
                group_key: type === 'servico'
                    ? button.dataset.groupKey || ''
                    : '',
            }
        );

        const key = cacheKey(
            `${section}:detalhes`,
            params
        );

        if (title instanceof HTMLElement) {
            title.textContent = type === 'cliente'
                ? 'Ordens de serviço do cliente'
                : 'Execuções do serviço';
        }

        renderLoading(body);

        const modal = window.bootstrap?.Modal
            .getOrCreateInstance(modalElement);

        modal?.show();

        try {
            if (cache.has(key)) {
                renderHtml(
                    body,
                    cache.get(key)
                );

                return;
            }

            const payload = await requestJson(
                params,
                key
            );

            const html = typeof payload.html === 'string'
                ? payload.html
                : '';

            if (html === '') {
                throw new Error(
                    'O detalhamento não retornou conteúdo válido.'
                );
            }

            cache.set(key, html);

            renderHtml(
                body,
                html
            );
        } catch (error) {
            renderError(
                body,
                '',
                error instanceof Error
                    ? error.message
                    : 'Não foi possível carregar o detalhamento.'
            );
        }
    }

    /*
     * Carrega a seção apenas quando ela for aberta.
     */
    root.addEventListener(
        'show.bs.collapse',
        (event) => {
            const target = event.target;

            if (
                target instanceof HTMLElement
                && target.matches(
                    '[data-report-section]'
                )
            ) {
                loadSection(target);
            }
        }
    );

    /*
     * Busca interna de clientes ou serviços.
     */
    root.addEventListener(
        'submit',
        (event) => {
            const form = event.target;

            if (
                !(form instanceof HTMLFormElement)
                || !form.matches(
                    '[data-report-section-filter]'
                )
            ) {
                return;
            }

            event.preventDefault();

            const sectionElement = form.closest(
                '[data-report-section]'
            );

            if (!(sectionElement instanceof HTMLElement)) {
                return;
            }

            const data = new FormData(form);

            const changes = {
                page: 1,
            };

            for (const [key, value] of data.entries()) {
                if (typeof value === 'string') {
                    changes[key] = value.trim();
                }
            }

            loadSection(
                sectionElement,
                sectionOverridesFromCurrent(
                    sectionElement,
                    changes
                )
            );
        }
    );

    /*
     * Paginação, ordenação, detalhamento, retry e impressão.
     */
    root.addEventListener(
        'click',
        (event) => {
            const target = event.target;

            if (!(target instanceof Element)) {
                return;
            }

            const retry = target.closest(
                '[data-report-retry]'
            );

            if (retry instanceof HTMLElement) {
                const sectionElement =
                    sectionElementByName(
                        retry.dataset.reportRetry || ''
                    );

                if (sectionElement !== null) {
                    loadSection(
                        sectionElement,
                        sectionOverridesFromCurrent(
                            sectionElement
                        ),
                        true
                    );
                }

                return;
            }

            const pageButton = target.closest(
                '[data-report-page-number]'
            );

            if (
                pageButton instanceof HTMLButtonElement
            ) {
                const sectionElement =
                    pageButton.closest(
                        '[data-report-section]'
                    );

                const page = Number.parseInt(
                    pageButton.dataset.reportPageNumber || '',
                    10
                );

                if (
                    sectionElement instanceof HTMLElement
                    && Number.isInteger(page)
                    && page > 0
                ) {
                    loadSection(
                        sectionElement,
                        sectionOverridesFromCurrent(
                            sectionElement,
                            {page}
                        )
                    );
                }

                return;
            }

            const sortButton = target.closest(
                '[data-report-sort]'
            );

            if (
                sortButton instanceof HTMLButtonElement
            ) {
                const sectionElement =
                    sortButton.closest(
                        '[data-report-section]'
                    );

                if (
                    !(sectionElement instanceof HTMLElement)
                ) {
                    return;
                }

                const sort =
                    sortButton.dataset.reportSort || '';

                const currentDirection =
                    sortButton.dataset.reportDirection
                    || 'desc';

                const direction =
                    currentDirection === 'asc'
                        ? 'desc'
                        : 'asc';

                loadSection(
                    sectionElement,
                    sectionOverridesFromCurrent(
                        sectionElement,
                        {
                            sort,
                            direction,
                            page: 1,
                        }
                    )
                );

                return;
            }

            const clientButton = target.closest(
                '[data-report-client-orders]'
            );

            if (clientButton instanceof HTMLElement) {
                openDetail(
                    clientButton,
                    'cliente'
                );

                return;
            }

            const serviceButton = target.closest(
                '[data-report-service-executions]'
            );

            if (serviceButton instanceof HTMLElement) {
                openDetail(
                    serviceButton,
                    'servico'
                );

                return;
            }

            const printButton = target.closest(
                '[data-report-print-section]'
            );

            if (printButton instanceof HTMLElement) {
                const section =
                    printButton.dataset.reportPrintSection
                    || '';

                root.dataset.printingSection = section;

                window.print();

                window.setTimeout(
                    () => {
                        delete root.dataset.printingSection;
                    },
                    0
                );
            }
        }
    );

    window.addEventListener(
        'afterprint',
        () => {
            delete root.dataset.printingSection;
        }
    );

    setupPeriodMode();
})();