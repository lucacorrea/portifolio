(() => {
    'use strict';

    const hub = document.querySelector('[data-pe-import-hub]');
    if (!hub) return;

    const modeButtons = Array.from(hub.querySelectorAll('[data-pe-import-mode]'));
    const panels = Array.from(hub.querySelectorAll('[data-pe-import-panel]'));

    function setMode(mode) {
        modeButtons.forEach((button) => {
            const active = button.dataset.peImportMode === mode;
            button.classList.toggle('is-active', active);
            button.setAttribute('aria-selected', active ? 'true' : 'false');
        });
        panels.forEach((panel) => {
            panel.hidden = panel.dataset.peImportPanel !== mode;
        });
        if (mode === 'payment-pdf') {
            history.replaceState(null, '', `${location.pathname}${location.search}#pdf-pagamentos`);
        } else if (location.hash === '#pdf-pagamentos') {
            history.replaceState(null, '', `${location.pathname}${location.search}`);
        }
    }

    modeButtons.forEach((button) => button.addEventListener('click', () => setMode(button.dataset.peImportMode || 'spreadsheet')));
    const initialMode = hub.dataset.peDefaultImportMode === 'payment-pdf' || location.hash === '#pdf-pagamentos'
        ? 'payment-pdf'
        : 'spreadsheet';
    setMode(initialMode);

    const form = hub.querySelector('[data-pe-payment-pdf-form]');
    if (!form) return;

    const fileInput = form.querySelector('[data-pe-payment-pdf-file]');
    const textInput = form.querySelector('[data-pe-payment-pdf-text]');
    const competenceInput = form.querySelector('[data-pe-payment-competence]');
    const analyzeButton = form.querySelector('[data-pe-payment-analyze]');
    const applyButton = form.querySelector('[data-pe-payment-apply]');
    const analysisBox = hub.querySelector('[data-pe-payment-analysis]');
    const metaNode = hub.querySelector('[data-pe-payment-meta]');
    const sourceNode = hub.querySelector('[data-pe-payment-source]');
    const kpisNode = hub.querySelector('[data-pe-payment-kpis]');
    const warningNode = hub.querySelector('[data-pe-payment-warning]');
    const rowsNode = hub.querySelector('[data-pe-payment-rows]');
    const limitNote = hub.querySelector('[data-pe-payment-limit-note]');

    if (!fileInput || !textInput || !competenceInput || !analyzeButton || !applyButton || !analysisBox) return;

    let analyzed = false;
    let lastAnalysis = null;
    let pdfJsPromise = null;

    const escapeHtml = (value) => String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');

    const formatCpf = (value) => {
        const digits = String(value || '').replace(/\D+/g, '').padStart(11, '0');
        if (digits.length !== 11) return value || '—';
        return `${digits.slice(0, 3)}.${digits.slice(3, 6)}.${digits.slice(6, 9)}-${digits.slice(9)}`;
    };

    const money = (value) => new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(Number(value || 0));

    function updateCsrf(token) {
        if (!token) return;
        document.querySelectorAll('input[name="_csrf"]').forEach((input) => {
            input.value = token;
        });
    }

    function showToast(message, type = 'info') {
        if (window.SIGASFrontend?.toast) {
            window.SIGASFrontend.toast(message, type);
            return;
        }
        const container = document.getElementById('frontendToastContainer');
        if (!container) {
            alert(message);
            return;
        }
        const item = document.createElement('div');
        item.className = `alert alert-${type === 'danger' ? 'danger' : type === 'success' ? 'success' : 'info'} shadow-sm`;
        item.textContent = message;
        container.appendChild(item);
        window.setTimeout(() => item.remove(), 5000);
    }

    function resetAnalysis() {
        analyzed = false;
        lastAnalysis = null;
        applyButton.disabled = true;
        analysisBox.hidden = true;
        if (rowsNode) rowsNode.innerHTML = '';
        if (kpisNode) kpisNode.innerHTML = '';
        if (metaNode) metaNode.textContent = '—';
        if (sourceNode) sourceNode.textContent = '—';
        if (warningNode) warningNode.textContent = '';
        if (limitNote) limitNote.textContent = '';
    }

    function loadScript(url) {
        return new Promise((resolve, reject) => {
            const script = document.createElement('script');
            script.src = url;
            script.async = true;
            script.crossOrigin = 'anonymous';
            script.addEventListener('load', () => resolve(url), { once: true });
            script.addEventListener('error', () => reject(new Error(`Falha ao carregar ${url}`)), { once: true });
            document.head.appendChild(script);
        });
    }

    async function loadPdfJs() {
        if (window.pdfjsLib?.getDocument) return window.pdfjsLib;
        if (pdfJsPromise) return pdfJsPromise;

        pdfJsPromise = (async () => {
            const sources = [
                {
                    lib: 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js',
                    worker: 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js',
                },
                {
                    lib: 'https://cdn.jsdelivr.net/npm/pdfjs-dist@3.11.174/build/pdf.min.js',
                    worker: 'https://cdn.jsdelivr.net/npm/pdfjs-dist@3.11.174/build/pdf.worker.min.js',
                },
            ];

            let lastError = null;
            for (const source of sources) {
                try {
                    await loadScript(source.lib);
                    if (window.pdfjsLib?.getDocument) {
                        window.pdfjsLib.GlobalWorkerOptions.workerSrc = source.worker;
                        return window.pdfjsLib;
                    }
                } catch (error) {
                    lastError = error;
                }
            }
            throw lastError || new Error('PDF.js indisponível.');
        })();

        return pdfJsPromise;
    }

    function pageItemsToLines(items) {
        const prepared = items
            .filter((item) => item && typeof item.str === 'string' && item.str.trim() !== '' && Array.isArray(item.transform))
            .map((item) => ({
                text: item.str.trim(),
                x: Number(item.transform[4] || 0),
                y: Number(item.transform[5] || 0),
            }))
            .sort((a, b) => Math.abs(b.y - a.y) > 1.8 ? b.y - a.y : a.x - b.x);

        const lines = [];
        for (const item of prepared) {
            let line = lines.find((candidate) => Math.abs(candidate.y - item.y) <= 1.8);
            if (!line) {
                line = { y: item.y, items: [] };
                lines.push(line);
            }
            line.items.push(item);
        }

        return lines
            .sort((a, b) => b.y - a.y)
            .map((line) => line.items.sort((a, b) => a.x - b.x).map((item) => item.text).join(' ').replace(/\s+/g, ' ').trim())
            .filter(Boolean);
    }

    async function extractPdfText(file) {
        const pdfjsLib = await loadPdfJs();
        const data = await file.arrayBuffer();
        const loadingTask = pdfjsLib.getDocument({ data });
        const pdf = await loadingTask.promise;
        if (pdf.numPages < 1 || pdf.numPages > 300) {
            throw new Error('Quantidade de páginas do PDF fora do limite permitido.');
        }

        const pages = [];
        for (let pageNumber = 1; pageNumber <= pdf.numPages; pageNumber += 1) {
            analyzeButton.innerHTML = `<span class="spinner-border spinner-border-sm" aria-hidden="true"></span> Lendo página ${pageNumber}/${pdf.numPages}`;
            const page = await pdf.getPage(pageNumber);
            const content = await page.getTextContent({ disableCombineTextItems: false });
            pages.push(pageItemsToLines(content.items).join('\n'));
            page.cleanup();
        }
        await pdf.destroy();
        return pages.join('\n');
    }

    function statusMeta(status) {
        const map = {
            novo_pagamento: ['Pronto', 'success'],
            atualizar_pagamento: ['Atualizar bolsa', 'primary'],
            ja_conciliado: ['Já conciliado', 'secondary'],
            conflito_financeiro: ['Conflito financeiro', 'danger'],
            nao_localizado: ['Não localizado', 'warning'],
            cpf_ambiguo: ['CPF ambíguo', 'danger'],
            cpf_invalido: ['CPF inválido', 'danger'],
        };
        return map[status] || [status || '—', 'secondary'];
    }

    function renderAnalysis(payload) {
        const analysis = payload.analysis;
        lastAnalysis = analysis;
        const { meta, summary, rows } = analysis;

        if (competenceInput.value === '' && meta.competencia) {
            competenceInput.value = meta.competencia;
        }

        metaNode.textContent = `Convênio ${meta.convenio_numero || '—'} · Lista ${meta.lista_numero || '—'} (${meta.lista_nome || '—'}) · ${meta.total_pagamentos} pagamentos · ${money(meta.valor_total)} · Estado: ${meta.estado_lista || '—'}`;
        sourceNode.textContent = payload.source === 'pdftotext-servidor' ? 'Texto validado no servidor' : 'Texto extraído no navegador';

        const cards = [
            ['Registros no PDF', summary.total],
            ['CPF válidos', summary.cpf_validos],
            ['Novos pagamentos', summary.prontos],
            ['Bolsas a atualizar', summary.atualizar_pagamento],
            ['Já conciliados', summary.ja_conciliados],
            ['Não localizados', summary.nao_localizados],
            ['CPF ambíguo', summary.ambiguos],
            ['Conflitos financeiros', summary.conflitos_financeiros],
        ];
        kpisNode.innerHTML = cards.map(([label, value]) => `<div class="pe-payment-kpi"><span>${escapeHtml(label)}</span><strong>${Number(value || 0)}</strong></div>`).join('');

        const unresolved = Number(summary.nao_localizados || 0) + Number(summary.ambiguos || 0) + Number(summary.cpf_invalidos || 0);
        const canApply = String(meta.estado_lista || '').trim().toUpperCase() === 'PAGA';
        warningNode.className = `alert ${unresolved || summary.conflitos_financeiros ? 'alert-warning' : 'alert-success'} mt-3 mb-0`;
        warningNode.innerHTML = canApply
            ? `<strong>Pré-análise concluída.</strong> ${unresolved} registro(s) não serão aplicados automaticamente. ${Number(summary.divergencias_nome || 0)} divergência(s) de nome serão registradas para auditoria. Os demais poderão ser conciliados.`
            : `<strong>Conciliação bloqueada.</strong> A lista está como “${escapeHtml(meta.estado_lista || 'sem estado')}”. Somente listas PAGA podem ser aplicadas.`;

        const priorities = {
            conflito_financeiro: 1,
            cpf_ambiguo: 2,
            cpf_invalido: 3,
            nao_localizado: 4,
            atualizar_pagamento: 5,
            novo_pagamento: 6,
            ja_conciliado: 7,
        };
        const ordered = [...rows].sort((a, b) => (priorities[a.match_status] || 99) - (priorities[b.match_status] || 99));
        const displayRows = ordered.slice(0, 220);

        rowsNode.innerHTML = displayRows.map((row) => {
            const [label, badge] = statusMeta(row.match_status);
            let sigas = '—';
            if (row.candidate_id) {
                sigas = `<strong>#${Number(row.candidate_id)} · ${escapeHtml(row.candidate_name)}</strong><small>${escapeHtml(row.candidate_status || '')}${row.name_divergence ? ' · Divergência de nome' : ''}</small>`;
            } else if (row.suggestion?.id) {
                sigas = `<span class="text-warning-emphasis">Sugestão por nome: #${Number(row.suggestion.id)} · ${escapeHtml(row.suggestion.nome)}</span><small>Não será vinculado automaticamente.</small>`;
            } else if (Array.isArray(row.ambiguous_candidates) && row.ambiguous_candidates.length) {
                sigas = row.ambiguous_candidates.slice(0, 3).map((item) => `#${Number(item.id)} ${escapeHtml(item.nome)}`).join('<br>');
            }
            return `<tr class="pe-payment-row pe-payment-row--${escapeHtml(row.match_status)}">
                <td>${escapeHtml(row.n_ident)}</td>
                <td>${escapeHtml(formatCpf(row.cpf || row.cpf_informado))}</td>
                <td><strong>${escapeHtml(row.nome)}</strong>${row.name_divergence ? '<small class="text-warning-emphasis">Nome difere do SIGAS</small>' : ''}</td>
                <td>${escapeHtml(money(row.valor))}</td>
                <td class="pe-payment-sigas-cell">${sigas}</td>
                <td><span class="badge text-bg-${badge}">${escapeHtml(label)}</span><small>${escapeHtml(row.match_message || '')}</small></td>
            </tr>`;
        }).join('');

        limitNote.textContent = rows.length > displayRows.length
            ? `Exibindo ${displayRows.length} de ${rows.length} linhas, priorizando conflitos e pendências.`
            : `Exibindo ${rows.length} linhas.`;

        analysisBox.hidden = false;
        analyzed = canApply;
        applyButton.disabled = !canApply;
    }

    fileInput.addEventListener('change', () => {
        textInput.value = '';
        resetAnalysis();
    });
    competenceInput.addEventListener('change', () => {
        if (lastAnalysis) {
            resetAnalysis();
            showToast('A competência mudou. Analise o PDF novamente antes de confirmar.', 'info');
        }
    });

    analyzeButton.addEventListener('click', async () => {
        const file = fileInput.files?.[0];
        if (!file) {
            showToast('Selecione o PDF de pagamentos.', 'danger');
            fileInput.focus();
            return;
        }
        if (!/\.pdf$/i.test(file.name) || file.size <= 0 || file.size > 12 * 1024 * 1024) {
            showToast('Envie um PDF válido com no máximo 12 MB.', 'danger');
            return;
        }

        resetAnalysis();
        analyzeButton.disabled = true;
        const originalHtml = analyzeButton.innerHTML;
        analyzeButton.innerHTML = '<span class="spinner-border spinner-border-sm" aria-hidden="true"></span> Preparando PDF...';

        try {
            const text = await extractPdfText(file);
            if (!text || text.length < 100) {
                throw new Error('O PDF não possui texto suficiente para leitura. Use o extrato digital original, não uma digitalização sem camada de texto.');
            }
            textInput.value = text;

            const data = new FormData(form);
            data.set('pe_action', 'analyze_payment_pdf');
            const response = await fetch(`${location.pathname}?ajax=payment_pdf_analyze`, {
                method: 'POST',
                body: data,
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            });
            const payload = await response.json().catch(() => null);
            if (!payload) throw new Error('O servidor retornou uma resposta inválida.');
            updateCsrf(payload.csrf_token);
            if (!response.ok || !payload.success) {
                throw new Error(payload.message || 'Não foi possível analisar o PDF.');
            }
            renderAnalysis(payload);
            setMode('payment-pdf');
            showToast('PDF analisado. Confira as pendências antes de confirmar.', 'success');
        } catch (error) {
            resetAnalysis();
            showToast(error instanceof Error ? error.message : 'Não foi possível analisar o PDF.', 'danger');
        } finally {
            analyzeButton.disabled = false;
            analyzeButton.innerHTML = originalHtml;
        }
    });

    form.addEventListener('submit', (event) => {
        if (!analyzed || !lastAnalysis || !textInput.value) {
            event.preventDefault();
            showToast('Analise o PDF novamente antes de confirmar a conciliação.', 'danger');
            return;
        }
        if (!competenceInput.value) {
            event.preventDefault();
            showToast('Informe a competência da bolsa.', 'danger');
            competenceInput.focus();
            return;
        }

        const s = lastAnalysis.summary || {};
        const message = `Confirmar a conciliação de ${Number(s.prontos || 0)} novo(s) pagamento(s) e ${Number(s.atualizar_pagamento || 0)} atualização(ões) de bolsa? Registros não localizados, ambíguos ou com conflito financeiro serão apenas registrados para revisão.`;
        if (!window.confirm(message)) {
            event.preventDefault();
            return;
        }

        applyButton.disabled = true;
        applyButton.innerHTML = '<span class="spinner-border spinner-border-sm" aria-hidden="true"></span> Conciliando...';
    });
})();
