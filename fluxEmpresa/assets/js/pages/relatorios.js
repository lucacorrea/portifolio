(() => {
    'use strict';

    const root = document.querySelector('[data-report-page]');

    if (!(root instanceof HTMLElement)) {
        return;
    }

    const endpoint = root.dataset.sectionEndpoint
        || 'actions/relatorio-secao-carregar.php';

    const filterForm = root.querySelector(
        '[data-report-filter-form]'
    );

    const workspace = root.querySelector(
        '[data-report-active-section]'
    );

    const workspaceTitle = root.querySelector(
        '[data-report-workspace-title]'
    );

    const workspaceDescription = root.querySelector(
        '[data-report-workspace-description]'
    );

    const workspaceIcon = root.querySelector(
        '[data-report-workspace-icon]'
    );

    const sectionField = root.querySelector(
        '[data-report-section-field]'
    );

    const modeField = root.querySelector(
        '[data-report-mode-field]'
    );

    const exportCurrentLink = root.querySelector(
        '[data-report-export-current]'
    );

    const printCurrentButton = root.querySelector(
        '[data-report-print-current]'
    );

    const detailModalElement = document.getElementById(
        'report-detail-modal'
    );

    const detailModalBody = detailModalElement?.querySelector(
        '[data-report-detail-body]'
    );

    const detailModalTitle = detailModalElement?.querySelector(
        '[data-report-detail-title]'
    );

    if (!(workspace instanceof HTMLElement)) {
        return;
    }

    const allowedSections = [
        'empresa',
        'clientes',
        'servicos',
        'equipe',
    ];

    const cache = new Map();
    const pendingRequests = new Map();

    let activeSection = normalizeSection(
        root.dataset.initialSection || ''
    );

    let detailState = null;

    const requestHeaders = {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    };

    /**
     * Garante que somente uma seção conhecida seja utilizada.
     *
     * @param {unknown} section
     * @returns {string}
     */
    function normalizeSection(section) {
        const normalized = String(section || '')
            .trim()
            .toLowerCase();

        return allowedSections.includes(normalized)
            ? normalized
            : '';
    }

    /**
     * Retorna os cards de relatório disponíveis para o usuário.
     *
     * @returns {HTMLButtonElement[]}
     */
    function selectorCards() {
        return [
            ...root.querySelectorAll('[data-report-select]'),
        ].filter(
            (element) => element instanceof HTMLButtonElement
        );
    }

    /**
     * Verifica se a seção possui card disponível no DOM.
     *
     * Isso impede que uma seção sem permissão seja selecionada
     * pela URL ou pelo JavaScript.
     *
     * @param {string} section
     * @returns {boolean}
     */
    function sectionIsAvailable(section) {
        return selectorCards().some(
            (card) => card.dataset.reportSelect === section
        );
    }

    /**
     * Localiza o card de uma seção.
     *
     * @param {string} section
     * @returns {HTMLButtonElement|null}
     */
    function cardForSection(section) {
        for (const card of selectorCards()) {
            if (card.dataset.reportSelect === section) {
                return card;
            }
        }

        return null;
    }

    /**
     * Converte os valores do formulário global em parâmetros.
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
     * Ordena os parâmetros para criar chaves de cache estáveis.
     *
     * @param {URLSearchParams} params
     * @returns {URLSearchParams}
     */
    function orderedParams(params) {
        const ordered = new URLSearchParams();

        [...params.entries()]
            .sort(
                (
                    [leftKey, leftValue],
                    [rightKey, rightValue]
                ) => {
                    const keyComparison = leftKey.localeCompare(
                        rightKey
                    );

                    if (keyComparison !== 0) {
                        return keyComparison;
                    }

                    return leftValue.localeCompare(rightValue);
                }
            )
            .forEach(([key, value]) => {
                ordered.append(key, value);
            });

        return ordered;
    }

    /**
     * Monta os parâmetros completos para uma seção.
     *
     * @param {string} section
     * @param {Record<string, unknown>} overrides
     * @returns {URLSearchParams}
     */
    function buildParams(section, overrides = {}) {
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

        return orderedParams(params);
    }

    /**
     * Parâmetros atualmente aplicados ao relatório carregado.
     *
     * @returns {URLSearchParams}
     */
    function currentReportParams() {
        const currentSection = normalizeSection(
            workspace.dataset.section || activeSection
        );

        const serialized = workspace.dataset.currentParams || '';

        if (
            serialized !== ''
            && currentSection === activeSection
        ) {
            return new URLSearchParams(serialized);
        }

        return buildParams(activeSection);
    }

    /**
     * Mantém busca, ordenação e paginação ao alterar somente
     * uma propriedade do relatório atual.
     *
     * @param {Record<string, unknown>} changes
     * @returns {Record<string, string|number>}
     */
    function currentOverrides(changes = {}) {
        const params = currentReportParams();
        const overrides = {};

        for (const [key, value] of params.entries()) {
            if (
                ![
                    'secao',
                    'modo',
                    'competencia',
                    'data_inicial',
                    'data_final',
                    'acao',
                    'client_id',
                    'cliente_id',
                    'group_key',
                ].includes(key)
            ) {
                overrides[key] = value;
            }
        }

        return {
            ...overrides,
            ...changes,
        };
    }

    /**
     * Cria uma chave segura para cache.
     *
     * @param {string} namespace
     * @param {URLSearchParams} params
     * @returns {string}
     */
    function cacheKey(namespace, params) {
        return `${namespace}?${orderedParams(params).toString()}`;
    }

    /**
     * Cria a URL do endpoint.
     *
     * @param {URLSearchParams} params
     * @returns {string}
     */
    function endpointUrl(params) {
        const url = new URL(endpoint, document.baseURI);

        url.search = params.toString();

        return url.toString();
    }

    /**
     * Cria estados de carregamento e erro.
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

        const titleElement = document.createElement('strong');

        titleElement.textContent = title;

        const messageElement = document.createElement('p');

        messageElement.textContent = message;

        wrapper.append(
            iconElement,
            titleElement,
            messageElement
        );

        if (retrySection !== '') {
            const retryButton = document.createElement('button');

            retryButton.type = 'button';
            retryButton.className =
                'btn-filter btn-filter-ghost';

            retryButton.dataset.reportRetry =
                retrySection;

            retryButton.innerHTML = [
                '<i class="bi bi-arrow-clockwise"',
                ' aria-hidden="true"></i>',
                ' Tentar novamente',
            ].join('');

            wrapper.append(retryButton);
        }

        return wrapper;
    }

    /**
     * @param {HTMLElement} host
     * @param {string} message
     */
    function renderLoading(
        host,
        message = 'Os dados do período selecionado estão sendo preparados.'
    ) {
        host.replaceChildren(
            createState(
                'bi-arrow-repeat report-loading-icon',
                'Carregando relatório',
                message
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
                'Não foi possível carregar o relatório',
                message
                    || 'Tente novamente em alguns instantes.',
                section
            )
        );

        host.setAttribute('aria-busy', 'false');
    }

    /**
     * O HTML é produzido exclusivamente pelas partials PHP
     * internas, que devem escapar os dados no backend.
     *
     * @param {HTMLElement} host
     * @param {string} html
     */
    function renderHtml(host, html) {
        host.innerHTML = html;
        host.setAttribute('aria-busy', 'false');
    }

    /**
     * Executa uma requisição JSON com timeout.
     *
     * @param {URLSearchParams} params
     * @param {string} key
     * @returns {Promise<Record<string, unknown>>}
     */
    async function requestJson(params, key) {
        if (pendingRequests.has(key)) {
            return pendingRequests.get(key);
        }

        const controller = new AbortController();

        const timeout = window.setTimeout(
            () => controller.abort(),
            30000
        );

        const request = fetch(
            endpointUrl(params),
            {
                method: 'GET',
                credentials: 'same-origin',
                headers: requestHeaders,
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
                        : 'Não foi possível carregar o relatório.';

                    const requestError = new Error(message);

                    requestError.status = response.status;

                    throw requestError;
                }

                return payload;
            })
            .finally(() => {
                window.clearTimeout(timeout);
                pendingRequests.delete(key);
            });

        pendingRequests.set(key, request);

        return request;
    }

    /**
     * Atualiza o título, ícone e descrição do painel.
     *
     * @param {HTMLButtonElement} card
     */
    function updateWorkspaceHeader(card) {
        const title =
            card.dataset.reportTitle || 'Relatório';

        const description =
            card.dataset.reportDescription || '';

        const icon =
            card.dataset.reportIcon || 'bi-bar-chart';

        if (workspaceTitle instanceof HTMLElement) {
            workspaceTitle.textContent = title;
        }

        if (workspaceDescription instanceof HTMLElement) {
            workspaceDescription.textContent = description;
        }

        if (workspaceIcon instanceof HTMLElement) {
            workspaceIcon.className = `bi ${icon}`;
        }
    }

    /**
     * Atualiza o estado visual dos cards.
     *
     * @param {string} section
     */
    function updateSelectorCards(section) {
        for (const card of selectorCards()) {
            const selected =
                card.dataset.reportSelect === section;

            card.classList.toggle(
                'is-active',
                selected
            );

            card.setAttribute(
                'aria-pressed',
                selected ? 'true' : 'false'
            );
        }
    }

    /**
     * Atualiza o campo oculto da seção.
     *
     * @param {string} section
     */
    function updateSectionField(section) {
        if (sectionField instanceof HTMLInputElement) {
            sectionField.value = section;
        }
    }

    /**
     * Atualiza a URL sem recarregar a página.
     *
     * @param {string} section
     * @param {'push'|'replace'|'none'} historyMode
     */
    function updateBrowserUrl(
        section,
        historyMode = 'push'
    ) {
        if (historyMode === 'none') {
            return;
        }

        const params = periodParams();

        params.set('secao', section);

        const url = new URL(
            window.location.href
        );

        url.search = orderedParams(params).toString();

        const state = {
            section,
        };

        if (historyMode === 'replace') {
            window.history.replaceState(
                state,
                '',
                url.toString()
            );

            return;
        }

        window.history.pushState(
            state,
            '',
            url.toString()
        );
    }

    /**
     * Atualiza a URL de exportação do relatório ativo.
     *
     * @param {URLSearchParams|null} sourceParams
     */
    function updateExportUrl(sourceParams = null) {
        if (!(exportCurrentLink instanceof HTMLAnchorElement)) {
            return;
        }

        const params = sourceParams instanceof URLSearchParams
            ? new URLSearchParams(sourceParams)
            : currentReportParams();

        params.set('secao', activeSection);

        params.delete('acao');
        params.delete('client_id');
        params.delete('cliente_id');
        params.delete('group_key');

        /*
         * A exportação deve considerar todos os registros,
         * não apenas a página atual.
         */
        params.delete('page');
        params.delete('pagina');

        const url = new URL(
            'actions/relatorio-exportar.php',
            document.baseURI
        );

        url.search = orderedParams(params).toString();

        exportCurrentLink.href = url.toString();
    }

    /**
     * Carrega o relatório selecionado.
     *
     * @param {string} section
     * @param {Record<string, unknown>} overrides
     * @param {boolean} force
     */
    async function loadReport(
        section,
        overrides = {},
        force = false
    ) {
        const normalizedSection =
            normalizeSection(section);

        if (
            normalizedSection === ''
            || !sectionIsAvailable(normalizedSection)
        ) {
            return;
        }

        const params = buildParams(
            normalizedSection,
            overrides
        );

        const key = cacheKey(
            normalizedSection,
            params
        );

        workspace.dataset.section =
            normalizedSection;

        updateExportUrl(params);

        if (
            !force
            && workspace.dataset.loadedKey === key
        ) {
            return;
        }

        if (!force && cache.has(key)) {
            renderHtml(
                workspace,
                cache.get(key)
            );

            workspace.dataset.loadedKey = key;
            workspace.dataset.currentParams =
                params.toString();

            return;
        }

        renderLoading(workspace);

        try {
            const payload = await requestJson(
                params,
                key
            );

            const html =
                typeof payload.html === 'string'
                    ? payload.html
                    : '';

            if (html === '') {
                throw new Error(
                    'O relatório não retornou conteúdo válido.'
                );
            }

            cache.set(key, html);

            renderHtml(
                workspace,
                html
            );

            workspace.dataset.loadedKey = key;
            workspace.dataset.currentParams =
                params.toString();

            updateExportUrl(params);
        } catch (error) {
            const message = (
                error instanceof Error
                && error.name === 'AbortError'
            )
                ? 'A consulta demorou mais que o esperado. Tente novamente.'
                : (
                    error instanceof Error
                        ? error.message
                        : 'Não foi possível carregar o relatório.'
                );

            renderError(
                workspace,
                normalizedSection,
                message
            );
        }
    }

    /**
     * Seleciona uma seção e preenche o único painel.
     *
     * @param {string} section
     * @param {{
     *     historyMode?: 'push'|'replace'|'none',
     *     force?: boolean
     * }} options
     */
    async function selectSection(
        section,
        options = {}
    ) {
        const normalizedSection =
            normalizeSection(section);

        if (
            normalizedSection === ''
            || !sectionIsAvailable(normalizedSection)
        ) {
            return;
        }

        const card = cardForSection(
            normalizedSection
        );

        if (!(card instanceof HTMLButtonElement)) {
            return;
        }

        activeSection = normalizedSection;

        root.dataset.initialSection =
            normalizedSection;

        updateSelectorCards(normalizedSection);
        updateWorkspaceHeader(card);
        updateSectionField(normalizedSection);

        workspace.dataset.section =
            normalizedSection;

        workspace.dataset.loadedKey = '';
        workspace.dataset.currentParams = '';

        updateBrowserUrl(
            normalizedSection,
            options.historyMode || 'push'
        );

        await loadReport(
            normalizedSection,
            {},
            options.force === true
        );
    }

    /**
     * Abre a instância Bootstrap do modal.
     *
     * @returns {unknown|null}
     */
    function detailModalInstance() {
        if (
            !(detailModalElement instanceof HTMLElement)
            || !window.bootstrap?.Modal
        ) {
            return null;
        }

        return window.bootstrap.Modal.getOrCreateInstance(
            detailModalElement
        );
    }

    /**
     * Carrega conteúdo do modal.
     *
     * @param {URLSearchParams} params
     * @param {string} title
     * @param {boolean} force
     */
    async function loadDetail(
        params,
        title,
        force = false
    ) {
        if (!(detailModalBody instanceof HTMLElement)) {
            return;
        }

        const key = cacheKey(
            `${activeSection}:detalhes`,
            params
        );

        if (detailModalTitle instanceof HTMLElement) {
            detailModalTitle.textContent = title;
        }

        if (!force && cache.has(key)) {
            renderHtml(
                detailModalBody,
                cache.get(key)
            );

            return;
        }

        renderLoading(
            detailModalBody,
            'Os registros vinculados estão sendo preparados.'
        );

        try {
            const payload = await requestJson(
                params,
                key
            );

            const html =
                typeof payload.html === 'string'
                    ? payload.html
                    : '';

            if (html === '') {
                throw new Error(
                    'O detalhamento não retornou conteúdo válido.'
                );
            }

            cache.set(key, html);

            renderHtml(
                detailModalBody,
                html
            );
        } catch (error) {
            const message = (
                error instanceof Error
                && error.name === 'AbortError'
            )
                ? 'A consulta demorou mais que o esperado.'
                : (
                    error instanceof Error
                        ? error.message
                        : 'Não foi possível carregar o detalhamento.'
                );

            detailModalBody.replaceChildren(
                createState(
                    'bi-exclamation-circle',
                    'Não foi possível carregar o detalhamento',
                    message
                )
            );

            detailModalBody.setAttribute(
                'aria-busy',
                'false'
            );
        }
    }

    /**
     * Abre as OS de um cliente ou execuções de um serviço.
     *
     * @param {HTMLElement} button
     * @param {'cliente'|'servico'} type
     */
    async function openDetail(button, type) {
        if (
            !['clientes', 'servicos'].includes(activeSection)
        ) {
            return;
        }

        const params = currentReportParams();

        params.set('secao', activeSection);
        params.set('acao', 'detalhes');
        params.set('page', '1');

        if (type === 'cliente') {
            const clientId =
                button.dataset.clientId || '';

            if (!/^\d+$/.test(clientId)) {
                return;
            }

            params.set('client_id', clientId);
            params.delete('group_key');
        } else {
            const groupKey =
                button.dataset.groupKey || '';

            if (groupKey === '') {
                return;
            }

            params.set('group_key', groupKey);
            params.delete('client_id');
            params.delete('cliente_id');
        }

        const title = type === 'cliente'
            ? 'Ordens de serviço do cliente'
            : 'Execuções do serviço';

        detailState = {
            type,
            title,
            params: orderedParams(params),
        };

        detailModalInstance()?.show();

        await loadDetail(
            detailState.params,
            title
        );
    }

    /**
     * Paginação dentro do modal.
     *
     * @param {number} page
     */
    async function changeDetailPage(page) {
        if (
            detailState === null
            || !Number.isInteger(page)
            || page < 1
        ) {
            return;
        }

        const params = new URLSearchParams(
            detailState.params
        );

        params.set('page', String(page));

        detailState.params = orderedParams(params);

        await loadDetail(
            detailState.params,
            detailState.title
        );
    }

    /**
     * Controla o modo mensal ou personalizado.
     */
    function setupPeriodMode() {
        if (!(filterForm instanceof HTMLFormElement)) {
            return;
        }

        const modeControls = [
            ...root.querySelectorAll(
                '[data-report-mode-control]'
            ),
        ];

        const monthContainers = [
            ...filterForm.querySelectorAll(
                '[data-period-month-fields]'
            ),
        ];

        const customContainers = [
            ...filterForm.querySelectorAll(
                '[data-period-custom-fields]'
            ),
        ];

        const setContainerState = (
            container,
            enabled
        ) => {
            if (!(container instanceof HTMLElement)) {
                return;
            }

            container.hidden = !enabled;

            container
                .querySelectorAll(
                    'input, select, textarea'
                )
                .forEach((field) => {
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
            const selected = modeControls.find(
                (control) => (
                    control instanceof HTMLInputElement
                    && control.checked
                )
            );

            const mode = (
                selected instanceof HTMLInputElement
                && selected.value === 'periodo'
            )
                ? 'periodo'
                : 'mes';

            const monthly = mode === 'mes';

            if (modeField instanceof HTMLInputElement) {
                modeField.value = mode;
            }

            monthContainers.forEach(
                (container) => {
                    setContainerState(
                        container,
                        monthly
                    );
                }
            );

            customContainers.forEach(
                (container) => {
                    setContainerState(
                        container,
                        !monthly
                    );
                }
            );
        };

        modeControls.forEach((control) => {
            control.addEventListener(
                'change',
                refresh
            );
        });

        refresh();
    }

    /**
     * Submissão do filtro global continua utilizando GET.
     * Isso permite compartilhar a URL do período.
     */
    if (filterForm instanceof HTMLFormElement) {
        filterForm.addEventListener(
            'submit',
            () => {
                updateSectionField(activeSection);
            }
        );
    }

    /**
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

            const data = new FormData(form);
            const changes = {
                page: 1,
            };

            for (const [key, value] of data.entries()) {
                if (typeof value === 'string') {
                    changes[key] = value.trim();
                }
            }

            loadReport(
                activeSection,
                currentOverrides(changes)
            );
        }
    );

    /**
     * Limpeza da busca interna.
     */
    root.addEventListener(
        'reset',
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

            window.setTimeout(
                () => {
                    const searchField = form.querySelector(
                        '[name="busca"], [name="search"]'
                    );

                    if (
                        searchField instanceof HTMLInputElement
                    ) {
                        searchField.value = '';
                    }

                    loadReport(
                        activeSection,
                        currentOverrides({
                            busca: '',
                            search: '',
                            page: 1,
                        })
                    );
                },
                0
            );
        }
    );

    /**
     * Ações por clique.
     */
    document.addEventListener(
        'click',
        (event) => {
            const target = event.target;

            if (!(target instanceof Element)) {
                return;
            }

            const selector = target.closest(
                '[data-report-select]'
            );

            if (selector instanceof HTMLButtonElement) {
                const section = normalizeSection(
                    selector.dataset.reportSelect || ''
                );

                if (
                    section !== ''
                    && section !== activeSection
                ) {
                    selectSection(section, {
                        historyMode: 'push',
                    });
                }

                return;
            }

            const retry = target.closest(
                '[data-report-retry]'
            );

            if (retry instanceof HTMLElement) {
                const section = normalizeSection(
                    retry.dataset.reportRetry || activeSection
                );

                if (section !== '') {
                    loadReport(
                        section,
                        currentOverrides(),
                        true
                    );
                }

                return;
            }

            const pageButton = target.closest(
                '[data-report-page-number]'
            );

            if (pageButton instanceof HTMLButtonElement) {
                const page = Number.parseInt(
                    pageButton.dataset.reportPageNumber || '',
                    10
                );

                if (
                    Number.isInteger(page)
                    && page > 0
                ) {
                    loadReport(
                        activeSection,
                        currentOverrides({
                            page,
                        })
                    );
                }

                return;
            }

            const detailPageButton = target.closest(
                '[data-report-detail-page]'
            );

            if (
                detailPageButton
                instanceof HTMLButtonElement
            ) {
                const page = Number.parseInt(
                    detailPageButton.dataset
                        .reportDetailPage || '',
                    10
                );

                changeDetailPage(page);

                return;
            }

            const sortButton = target.closest(
                '[data-report-sort]'
            );

            if (sortButton instanceof HTMLButtonElement) {
                const sort =
                    sortButton.dataset.reportSort || '';

                const currentDirection =
                    sortButton.dataset.reportDirection
                    || 'desc';

                const direction =
                    currentDirection === 'asc'
                        ? 'desc'
                        : 'asc';

                loadReport(
                    activeSection,
                    currentOverrides({
                        sort,
                        direction,
                        page: 1,
                    })
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

            if (
                printCurrentButton instanceof HTMLElement
                && (
                    target === printCurrentButton
                    || printCurrentButton.contains(target)
                )
            ) {
                window.print();
            }
        }
    );

    /**
     * Limpa o modal após fechar.
     */
    detailModalElement?.addEventListener(
        'hidden.bs.modal',
        () => {
            detailState = null;

            if (
                detailModalBody instanceof HTMLElement
            ) {
                detailModalBody.replaceChildren(
                    createState(
                        'bi-list-check',
                        'Selecione um registro',
                        'O detalhamento será exibido aqui.'
                    )
                );
            }
        }
    );

    /**
     * Navegação voltar/avançar do navegador.
     */
    window.addEventListener(
        'popstate',
        () => {
            const params = new URLSearchParams(
                window.location.search
            );

            const section = normalizeSection(
                params.get('secao') || ''
            );

            if (
                section !== ''
                && sectionIsAvailable(section)
            ) {
                selectSection(section, {
                    historyMode: 'none',
                });
            }
        }
    );

    /*
     * =====================================================
     * INICIALIZAÇÃO
     * =====================================================
     */
    setupPeriodMode();

    if (
        activeSection === ''
        || !sectionIsAvailable(activeSection)
    ) {
        const firstCard = selectorCards()[0];

        activeSection = firstCard instanceof HTMLButtonElement
            ? normalizeSection(
                firstCard.dataset.reportSelect || ''
            )
            : '';
    }

    if (activeSection !== '') {
        selectSection(activeSection, {
            historyMode: 'replace',
        });
    }
})();