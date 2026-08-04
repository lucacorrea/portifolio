(() => {
    'use strict';

    const root = document.querySelector('[data-report-page]');

    if (!(root instanceof HTMLElement)) {
        return;
    }

    const endpoint = root.dataset.sectionEndpoint
        || 'actions/relatorio-secao-carregar.php';

    const filterForm = root.querySelector('[data-report-filter-form]');
    const detailModalElement = document.getElementById('report-detail-modal');
    const detailModalBody = detailModalElement?.querySelector('[data-report-detail-body]');
    const detailModalTitle = detailModalElement?.querySelector('[data-report-detail-title]');

    const cache = new Map();
    const pendingRequests = new Map();

    let detailState = null;
    let printedCollapse = null;

    const jsonHeaders = {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    };

    function periodParams() {
        const params = new URLSearchParams();

        if (!(filterForm instanceof HTMLFormElement)) {
            return params;
        }

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

        return params;
    }

    function orderedParams(params) {
        const ordered = new URLSearchParams();

        [...params.entries()]
            .sort(([leftKey, leftValue], [rightKey, rightValue]) => {
                const keyComparison = leftKey.localeCompare(rightKey);

                return keyComparison !== 0
                    ? keyComparison
                    : leftValue.localeCompare(rightValue);
            })
            .forEach(([key, value]) => {
                ordered.append(key, value);
            });

        return ordered;
    }

    function normalizedParams(section, overrides = {}) {
        const params = periodParams();

        params.set('secao', section);

        for (const [key, value] of Object.entries(overrides)) {
            if (value === null || value === undefined || value === '') {
                params.delete(key);
                continue;
            }

            params.set(key, String(value));
        }

        return orderedParams(params);
    }

    function requestUrl(params) {
        const url = new URL(endpoint, document.baseURI);

        url.search = params.toString();

        return url.toString();
    }

    function cacheKey(namespace, params) {
        return `${namespace}?${orderedParams(params).toString()}`;
    }

    function sectionHost(sectionElement) {
        const host = sectionElement.querySelector('[data-report-section-content]');

        return host instanceof HTMLElement ? host : null;
    }

    function sectionElementByName(section) {
        const element = root.querySelector(
            `[data-report-section][data-section="${CSS.escape(section)}"]`
        );

        return element instanceof HTMLElement ? element : null;
    }

    function createState(icon, title, message, retrySection = '') {
        const wrapper = document.createElement('div');
        wrapper.className = 'report-section-state';
        wrapper.setAttribute('role', retrySection === '' ? 'status' : 'alert');

        const iconElement = document.createElement('i');
        iconElement.className = `bi ${icon}`;
        iconElement.setAttribute('aria-hidden', 'true');

        const strong = document.createElement('strong');
        strong.textContent = title;

        const paragraph = document.createElement('p');
        paragraph.textContent = message;

        wrapper.append(iconElement, strong, paragraph);

        if (retrySection !== '') {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'btn-filter btn-filter-ghost';
            button.dataset.reportRetry = retrySection;
            button.innerHTML = '<i class="bi bi-arrow-clockwise" aria-hidden="true"></i> Tentar novamente';
            wrapper.append(button);
        }

        return wrapper;
    }

    function renderLoading(host, message = 'Os dados desta seção estão sendo preparados.') {
        host.replaceChildren(
            createState(
                'bi-arrow-repeat report-loading-icon',
                'Carregando relatório',
                message
            )
        );

        host.setAttribute('aria-busy', 'true');
    }

    function renderError(host, section, message) {
        host.replaceChildren(
            createState(
                'bi-exclamation-circle',
                'Não foi possível carregar esta seção',
                message || 'Tente novamente em alguns instantes.',
                section
            )
        );

        host.setAttribute('aria-busy', 'false');
    }

    function renderHtml(host, html) {
        host.innerHTML = html;
        host.setAttribute('aria-busy', 'false');
    }

    async function requestJson(params, requestKey) {
        if (pendingRequests.has(requestKey)) {
            return pendingRequests.get(requestKey);
        }

        const controller = new AbortController();
        const timeout = window.setTimeout(() => controller.abort(), 30000);

        const promise = fetch(requestUrl(params), {
            method: 'GET',
            credentials: 'same-origin',
            headers: jsonHeaders,
            cache: 'no-store',
            signal: controller.signal,
        })
            .then(async (response) => {
                let payload = null;

                try {
                    payload = await response.json();
                } catch (error) {
                    payload = null;
                }

                if (!response.ok || !payload || payload.success !== true) {
                    const message = payload && typeof payload.message === 'string'
                        ? payload.message
                        : 'Não foi possível carregar esta seção do relatório.';

                    const requestError = new Error(message);
                    requestError.status = response.status;

                    throw requestError;
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

    function currentSectionParams(sectionElement) {
        const current = sectionElement.dataset.currentParams || '';

        if (current !== '') {
            return new URLSearchParams(current);
        }

        return normalizedParams(sectionElement.dataset.section || '');
    }

    function sectionOverridesFromCurrent(sectionElement, changes = {}) {
        const params = currentSectionParams(sectionElement);
        const result = {};

        for (const [key, value] of params.entries()) {
            if (![
                'modo',
                'competencia',
                'data_inicial',
                'data_final',
                'secao',
                'acao',
                'client_id',
                'cliente_id',
                'group_key',
            ].includes(key)) {
                result[key] = value;
            }
        }

        return {
            ...result,
            ...changes,
        };
    }

    function updateSectionActions(sectionElement, params) {
        const section = sectionElement.dataset.section || '';
        const exportLink = sectionElement.querySelector('[data-report-export-section]');

        if (!(exportLink instanceof HTMLAnchorElement)) {
            return;
        }

        const exportParams = new URLSearchParams(params);

        exportParams.delete('acao');
        exportParams.delete('client_id');
        exportParams.delete('cliente_id');
        exportParams.delete('group_key');
        exportParams.set('secao', section);

        const url = new URL('actions/relatorio-exportar.php', document.baseURI);
        url.search = orderedParams(exportParams).toString();

        exportLink.href = url.toString();
    }

    async function loadSection(sectionElement, overrides = {}, force = false) {
        if (!(sectionElement instanceof HTMLElement)) {
            return;
        }

        const section = sectionElement.dataset.section || '';

        if (!['clientes', 'servicos', 'equipe'].includes(section)) {
            return;
        }

        const host = sectionHost(sectionElement);

        if (host === null) {
            return;
        }

        const params = normalizedParams(section, overrides);
        const key = cacheKey(section, params);

        if (!force && sectionElement.dataset.loadedKey === key) {
            return;
        }

        if (!force && cache.has(key)) {
            renderHtml(host, cache.get(key));
            sectionElement.dataset.loadedKey = key;
            sectionElement.dataset.currentParams = params.toString();
            updateSectionActions(sectionElement, params);
            return;
        }

        renderLoading(host);

        try {
            const payload = await requestJson(params, key);
            const html = typeof payload.html === 'string' ? payload.html : '';

            if (html === '') {
                throw new Error('A seção não retornou conteúdo válido.');
            }

            cache.set(key, html);
            renderHtml(host, html);
            sectionElement.dataset.loadedKey = key;
            sectionElement.dataset.currentParams = params.toString();
            updateSectionActions(sectionElement, params);
        } catch (error) {
            const message = error instanceof Error && error.name === 'AbortError'
                ? 'A consulta demorou mais que o esperado. Tente novamente.'
                : error instanceof Error
                    ? error.message
                    : 'Não foi possível carregar esta seção do relatório.';

            renderError(host, section, message);
        }
    }

    function detailModalInstance() {
        if (!(detailModalElement instanceof HTMLElement)) {
            return null;
        }

        return window.bootstrap?.Modal.getOrCreateInstance(detailModalElement) || null;
    }

    async function loadDetail(params, title, force = false) {
        if (!(detailModalBody instanceof HTMLElement)) {
            return;
        }

        const key = cacheKey('detalhes', params);

        if (detailModalTitle instanceof HTMLElement) {
            detailModalTitle.textContent = title;
        }

        if (!force && cache.has(key)) {
            renderHtml(detailModalBody, cache.get(key));
            return;
        }

        renderLoading(
            detailModalBody,
            'Os registros vinculados estão sendo preparados.'
        );

        try {
            const payload = await requestJson(params, key);
            const html = typeof payload.html === 'string' ? payload.html : '';

            if (html === '') {
                throw new Error('O detalhamento não retornou conteúdo válido.');
            }

            cache.set(key, html);
            renderHtml(detailModalBody, html);
        } catch (error) {
            const message = error instanceof Error && error.name === 'AbortError'
                ? 'A consulta demorou mais que o esperado. Tente novamente.'
                : error instanceof Error
                    ? error.message
                    : 'Não foi possível carregar o detalhamento.';

            renderError(detailModalBody, '', message);
        }
    }

    async function openDetail(button, type) {
        const section = type === 'cliente' ? 'clientes' : 'servicos';
        const sectionElement = sectionElementByName(section);

        if (!(sectionElement instanceof HTMLElement)) {
            return;
        }

        const params = new URLSearchParams(currentSectionParams(sectionElement));

        params.set('secao', section);
        params.set('acao', 'detalhes');
        params.set('page', '1');

        if (type === 'cliente') {
            params.set('client_id', button.dataset.clientId || '');
            params.delete('group_key');
        } else {
            params.set('group_key', button.dataset.groupKey || '');
            params.delete('client_id');
            params.delete('cliente_id');
        }

        detailState = {
            section,
            type,
            title: type === 'cliente'
                ? 'Ordens de serviço do cliente'
                : 'Execuções do serviço',
            params: orderedParams(params),
        };

        detailModalInstance()?.show();

        await loadDetail(detailState.params, detailState.title);
    }

    async function changeDetailPage(page) {
        if (!detailState || !Number.isInteger(page) || page < 1) {
            return;
        }

        const params = new URLSearchParams(detailState.params);
        params.set('page', String(page));

        detailState.params = orderedParams(params);

        await loadDetail(detailState.params, detailState.title);
    }

    function setupPeriodMode() {
        if (!(filterForm instanceof HTMLFormElement)) {
            return;
        }

        const modeFields = [...filterForm.querySelectorAll('[name="modo"]')];
        const monthFields = filterForm.querySelector('[data-period-month-fields]');
        const customFields = [...filterForm.querySelectorAll('[data-period-custom-fields]')];

        const setFieldsState = (container, enabled) => {
            if (!(container instanceof HTMLElement)) {
                return;
            }

            container.hidden = !enabled;

            container.querySelectorAll('input, select, textarea').forEach((field) => {
                if (
                    field instanceof HTMLInputElement
                    || field instanceof HTMLSelectElement
                    || field instanceof HTMLTextAreaElement
                ) {
                    field.disabled = !enabled;
                }
            });
        };

        const refresh = () => {
            const checked = modeFields.find((field) => (
                field instanceof HTMLInputElement && field.checked
            ));

            const mode = checked instanceof HTMLInputElement
                ? checked.value
                : 'mes';

            const monthMode = mode === 'mes';

            setFieldsState(monthFields, monthMode);
            customFields.forEach((container) => setFieldsState(container, !monthMode));
        };

        modeFields.forEach((field) => {
            field.addEventListener('change', refresh);
        });

        refresh();
    }

    function preparePrint(section) {
        root.dataset.printingSection = section;

        const sectionElement = sectionElementByName(section);

        if (sectionElement instanceof HTMLElement) {
            printedCollapse = {
                element: sectionElement,
                wasShown: sectionElement.classList.contains('show'),
                styleDisplay: sectionElement.style.display,
            };

            sectionElement.classList.add('show');
            sectionElement.style.display = 'block';
        }

        window.print();
    }

    function cleanupPrint() {
        delete root.dataset.printingSection;

        if (printedCollapse && printedCollapse.element instanceof HTMLElement) {
            if (!printedCollapse.wasShown) {
                printedCollapse.element.classList.remove('show');
            }

            printedCollapse.element.style.display = printedCollapse.styleDisplay;
        }

        printedCollapse = null;
    }

    root.addEventListener('show.bs.collapse', (event) => {
        const target = event.target;

        if (target instanceof HTMLElement && target.matches('[data-report-section]')) {
            loadSection(target);
        }
    });

    root.addEventListener('submit', (event) => {
        const form = event.target;

        if (!(form instanceof HTMLFormElement) || !form.matches('[data-report-section-filter]')) {
            return;
        }

        event.preventDefault();

        const sectionElement = form.closest('[data-report-section]');

        if (!(sectionElement instanceof HTMLElement)) {
            return;
        }

        const changes = {page: 1};
        const data = new FormData(form);

        for (const [key, value] of data.entries()) {
            if (typeof value === 'string') {
                changes[key] = value.trim();
            }
        }

        loadSection(
            sectionElement,
            sectionOverridesFromCurrent(sectionElement, changes)
        );
    });

    root.addEventListener('reset', (event) => {
        const form = event.target;

        if (!(form instanceof HTMLFormElement) || !form.matches('[data-report-section-filter]')) {
            return;
        }

        const sectionElement = form.closest('[data-report-section]');

        if (!(sectionElement instanceof HTMLElement)) {
            return;
        }

        window.setTimeout(() => {
            const searchField = form.querySelector('[name="busca"], [name="search"]');

            if (searchField instanceof HTMLInputElement) {
                searchField.value = '';
            }

            loadSection(
                sectionElement,
                sectionOverridesFromCurrent(sectionElement, {
                    busca: '',
                    search: '',
                    page: 1,
                })
            );
        }, 0);
    });

    document.addEventListener('click', (event) => {
        const target = event.target;

        if (!(target instanceof Element)) {
            return;
        }

        const retry = target.closest('[data-report-retry]');

        if (retry instanceof HTMLElement) {
            const sectionElement = sectionElementByName(retry.dataset.reportRetry || '');

            if (sectionElement !== null) {
                loadSection(
                    sectionElement,
                    sectionOverridesFromCurrent(sectionElement),
                    true
                );
            }

            return;
        }

        const pageButton = target.closest('[data-report-page-number]');

        if (pageButton instanceof HTMLButtonElement) {
            const sectionElement = pageButton.closest('[data-report-section]');
            const page = Number.parseInt(pageButton.dataset.reportPageNumber || '', 10);

            if (
                sectionElement instanceof HTMLElement
                && Number.isInteger(page)
                && page > 0
            ) {
                loadSection(
                    sectionElement,
                    sectionOverridesFromCurrent(sectionElement, {page})
                );
            }

            return;
        }

        const detailPageButton = target.closest('[data-report-detail-page]');

        if (detailPageButton instanceof HTMLButtonElement) {
            const page = Number.parseInt(
                detailPageButton.dataset.reportDetailPage || '',
                10
            );

            changeDetailPage(page);
            return;
        }

        const sortButton = target.closest('[data-report-sort]');

        if (sortButton instanceof HTMLButtonElement) {
            const sectionElement = sortButton.closest('[data-report-section]');

            if (!(sectionElement instanceof HTMLElement)) {
                return;
            }

            const sort = sortButton.dataset.reportSort || '';
            const currentDirection = sortButton.dataset.reportDirection || 'desc';
            const direction = currentDirection === 'asc' ? 'desc' : 'asc';

            loadSection(
                sectionElement,
                sectionOverridesFromCurrent(sectionElement, {
                    sort,
                    direction,
                    page: 1,
                })
            );

            return;
        }

        const clientButton = target.closest('[data-report-client-orders]');

        if (clientButton instanceof HTMLElement) {
            openDetail(clientButton, 'cliente');
            return;
        }

        const serviceButton = target.closest('[data-report-service-executions]');

        if (serviceButton instanceof HTMLElement) {
            openDetail(serviceButton, 'servico');
            return;
        }

        const printButton = target.closest('[data-report-print-section]');

        if (printButton instanceof HTMLElement) {
            const section = printButton.dataset.reportPrintSection || '';

            if (section !== '') {
                preparePrint(section);
            }
        }
    });

    detailModalElement?.addEventListener('hidden.bs.modal', () => {
        detailState = null;

        if (detailModalBody instanceof HTMLElement) {
            detailModalBody.replaceChildren(
                createState(
                    'bi-list-check',
                    'Selecione um registro',
                    'O detalhamento será exibido aqui.'
                )
            );
        }
    });

    window.addEventListener('afterprint', cleanupPrint);

    setupPeriodMode();

    root.querySelectorAll('[data-report-section]').forEach((element) => {
        if (!(element instanceof HTMLElement)) {
            return;
        }

        const section = element.dataset.section || '';

        if (section !== '') {
            updateSectionActions(element, normalizedParams(section));
        }
    });
})();