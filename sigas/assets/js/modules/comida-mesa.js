(() => {
    "use strict";

    const qs = (selector, root = document) => root.querySelector(selector);
    const qsa = (selector, root = document) => [...root.querySelectorAll(selector)];
    const modal = (selector) => {
        const element = qs(selector);
        return element && window.bootstrap ? bootstrap.Modal.getOrCreateInstance(element) : null;
    };

    document.addEventListener("click", (event) => {
        const opener = event.target.closest("[data-cm-open]");
        if (opener) {
            const selector = opener.dataset.cmOpen || "";
            if (selector) modal(selector)?.show();
        }
    });

    const poleForm = qs("#poleForm");
    if (poleForm) {
        const title = qs("#poleModalTitle");
        const fill = (data = null) => {
            poleForm.reset();
            qs('[name="polo_id"]', poleForm).value = data?.id || "";
            qs('[name="nome"]', poleForm).value = data?.nome || "";
            qs('[name="endereco"]', poleForm).value = data?.endereco || "";
            qs('[name="ativo"]', poleForm).value = data?.ativo ?? "1";
            if (title) title.textContent = data ? "Editar polo" : "Novo polo";
            const alert = qs("[data-form-alert]", poleForm);
            if (alert) alert.innerHTML = "";
            modal("#poleModal")?.show();
        };

        document.addEventListener("click", (event) => {
            const trigger = event.target.closest("[data-cm-edit-pole]");
            if (!trigger) return;
            fill({
                id: trigger.dataset.id,
                nome: trigger.dataset.nome,
                endereco: trigger.dataset.endereco,
                ativo: trigger.dataset.ativo,
            });
        });

        poleForm.addEventListener("submit", async (event) => {
            event.preventDefault();
            const submit = qs('[type="submit"]', poleForm);
            const original = submit?.innerHTML;
            if (submit) { submit.disabled = true; submit.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Salvando'; }
            try {
                const response = await fetch(poleForm.action, {method: "POST", body: new FormData(poleForm), headers: {Accept: "application/json"}});
                const data = await response.json();
                if (!response.ok || !data.ok) {
                    const alert = qs("[data-form-alert]", poleForm);
                    if (alert) alert.innerHTML = `<div class="alert alert-danger">${String(data.error || "Não foi possível salvar.")}</div>`;
                    return;
                }
                window.location.reload();
            } catch (_) {
                const alert = qs("[data-form-alert]", poleForm);
                if (alert) alert.innerHTML = '<div class="alert alert-danger">Erro de comunicação com o servidor.</div>';
            } finally {
                if (submit) { submit.disabled = false; submit.innerHTML = original; }
            }
        });
    }

    // Competências: clique na linha abre a modal já existente preenchida pelo comida-mesa.js.
    document.addEventListener("click", (event) => {
        const row = event.target.closest("[data-cm-competence-row]");
        if (!row || event.target.closest("a,button,input,select,textarea,label")) return;
        const id = String(row.dataset.id || "");
        const item = (window.SIGAS_CONTEXT?.comidaMesa?.competences || []).find((entry) => String(entry.id) === id);
        const form = qs("#competenceForm");
        if (!form || !item) return;
        form.reset();
        qs('[name="competencia_id"]', form).value = item.id || "";
        qs('[name="mes"]', form).value = item.month || "";
        qs('[name="ano"]', form).value = item.year || "";
        qs('[name="status"]', form).value = item.status || "planejada";
        qs('[name="inicio_entregas"]', form).value = item.startsAt || "";
        qs('[name="fim_entregas"]', form).value = item.endsAt || "";
        qs('[name="observacao"]', form).value = item.observation || "";
        const title = qs("#competenceTitle");
        if (title) title.textContent = "Editar competência";
        modal("#competenceModal")?.show();
    });



    // Polos: toda a linha é acionável, mantendo botões/links internos independentes.
    document.addEventListener("click", (event) => {
        const row = event.target.closest("[data-cm-editable-pole-row]");
        if (!row || event.target.closest("a,button,input,select,textarea,label")) return;
        row.querySelector("[data-cm-edit-pole]")?.click();
    });

    // Documentos: clique na linha abre a central de ações do documento.
    document.addEventListener("click", (event) => {
        const row = event.target.closest("[data-cm-document-row]");
        if (!row || event.target.closest("a,button,input,select,textarea,label")) return;
        const actionModal = qs("#documentActionModal");
        if (!actionModal) return;

        const title = qs("[data-cm-doc-title]", actionModal);
        const subtitle = qs("[data-cm-doc-subtitle]", actionModal);
        const open = qs("[data-cm-doc-open]", actionModal);
        const family = qs("[data-cm-doc-family]", actionModal);
        const upload = qs("[data-cm-doc-new]", actionModal);

        if (title) title.textContent = row.dataset.type || "Documento selecionado";
        if (subtitle) subtitle.textContent = `${row.dataset.familyCode || "Família"} · ${row.dataset.name || "Responsável não informado"} · ${row.dataset.file || "Arquivo"}`;
        if (open) open.href = `api/comida-mesa/visualizar-documento.php?id=${encodeURIComponent(row.dataset.documentId || "")}`;

        if (family) {
            family.dataset.registrationId = row.dataset.registrationId || "";
        }
        if (upload) {
            upload.dataset.registrationId = row.dataset.registrationId || "";
        }
        modal("#documentActionModal")?.show();
    });

    document.addEventListener("click", (event) => {
        const family = event.target.closest("[data-cm-doc-family]");
        if (family) {
            modal("#documentActionModal")?.hide();
            window.setTimeout(() => {
                const dispatcher = document.createElement("button");
                dispatcher.type = "button";
                dispatcher.hidden = true;
                dispatcher.setAttribute("data-open-detail", "");
                dispatcher.setAttribute("data-registration-id", family.dataset.registrationId || "");
                document.body.appendChild(dispatcher);
                dispatcher.click();
                dispatcher.remove();
            }, 180);
            return;
        }

        const upload = event.target.closest("[data-cm-doc-new]");
        if (upload) {
            const form = qs("#documentForm");
            if (!form) return;
            form.reset();
            const registration = qs('[name="inscricao_id"]', form);
            if (registration) registration.value = upload.dataset.registrationId || "";
            modal("#documentActionModal")?.hide();
            window.setTimeout(() => modal("#documentModal")?.show(), 180);
        }
    });

    // Histórico: clique em qualquer evento abre o cadastro completo da família.
    document.addEventListener("click", (event) => {
        const row = event.target.closest("[data-cm-history-row]");
        if (!row || event.target.closest("a,button,input,select,textarea,label")) return;
        const dispatcher = document.createElement("button");
        dispatcher.type = "button";
        dispatcher.hidden = true;
        dispatcher.setAttribute("data-open-detail", "");
        dispatcher.setAttribute("data-registration-id", row.dataset.registrationId || "");
        dispatcher.setAttribute("data-detail-section", "history");
        document.body.appendChild(dispatcher);
        dispatcher.click();
        dispatcher.remove();
    });

    qsa("[data-cm-document-row], [data-cm-history-row]").forEach((row) => {
        row.addEventListener("keydown", (event) => {
            if (!["Enter", " "].includes(event.key)) return;
            event.preventDefault();
            row.click();
        });
    });

    // Tabelas auxiliares: Enter/Espaço mantém a mesma ação do clique.
    qsa("[data-cm-competence-row], [data-cm-editable-pole-row]").forEach((row) => {
        row.addEventListener("keydown", (event) => {
            if (!["Enter", " "].includes(event.key)) return;
            event.preventDefault();
            if (row.matches("[data-cm-editable-pole-row]")) {
                row.querySelector("[data-cm-edit-pole]")?.click();
            } else {
                row.click();
            }
        });
    });

    // Gráficos declarativos do módulo.
    const chartPayload = qs("#cmChartPayload");
    if (chartPayload && window.Chart) {
        let payload = {};
        try { payload = JSON.parse(chartPayload.textContent || "{}"); } catch (_) { payload = {}; }
        Chart.defaults.font.family = 'Inter, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif';
        Chart.defaults.color = "#50635a";
        Chart.defaults.maintainAspectRatio = false;
        Chart.defaults.plugins.legend.position = "bottom";
        const palette = ["#176b3a", "#2f8b55", "#69aa7d", "#a7cfb2", "#e4f2e8", "#d99b31", "#b85450"];
        const series = (key) => Array.isArray(payload[key]) ? payload[key].filter((item) => Number(item.value || 0) > 0) : [];
        const make = (id, type, data, options = {}) => {
            const canvas = qs(id);
            if (!canvas || !data.length) return;
            new Chart(canvas, {
                type,
                data: {labels: data.map(i => i.label), datasets: [{label: options.label || "Total", data: data.map(i => Number(i.value || 0)), backgroundColor: type === "line" ? "rgba(23,107,58,.12)" : palette, borderColor: "#176b3a", fill: type === "line", tension: .25}]},
                options: {...options.chartOptions, scales: type === "doughnut" ? undefined : {y: {beginAtZero: true, ticks: {precision: 0}}}}
            });
        };
        make("#cmChartMonthly", "line", series("monthly"), {label: "Entregas"});
        make("#cmChartProgram", "doughnut", series("program"));
        make("#cmChartDelivery", "doughnut", series("delivery"));
        make("#cmChartPoles", "bar", series("poles"), {label: "Famílias"});
        make("#cmChartZones", "doughnut", series("zones"));
        make("#cmChartDistricts", "bar", series("districts"), {label: "Famílias"});
    }
})();
