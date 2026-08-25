(() => {
    "use strict";

    const root = document.querySelector("[data-cm-cpf-consulta]");
    if (!root || !window.bootstrap) return;

    const context = window.SIGAS_CONTEXT?.consultaDocumento || {};
    const permissions = context.permissions || {};
    const urls = window.SIGAS_CONTEXT?.comidaMesa?.urls || {};
    const beneficiariesUrl = urls.beneficiarios || "comida-mesa/beneficiarios.php";
    const competencesUrl = urls.competencias || "comida-mesa/competencias.php";

    const qs = (selector, base = document) => base.querySelector(selector);
    const qsa = (selector, base = document) => [...base.querySelectorAll(selector)];
    const digits = (value) => String(value || "").replace(/\D+/g, "");
    const escapeHTML = (value) => String(value ?? "")
        .replaceAll("&", "&amp;")
        .replaceAll("<", "&lt;")
        .replaceAll(">", "&gt;")
        .replaceAll('"', "&quot;")
        .replaceAll("'", "&#039;");

    const form = qs("#cmCpfForm");
    const cpfInput = qs("#cmCpfInput");
    const submitButton = qs("[data-cm-cpf-submit]");
    const inlineAlert = qs("[data-cm-cpf-alert]");
    const lastResult = qs("[data-cm-last-result]");

    const resultModalEl = qs("#cmCpfResultModal");
    const actionsModalEl = qs("#cmCpfActionsModal");
    const detailModalEl = qs("#cmCpfDetailModal");
    const deliveryModalEl = qs("#cmCpfDeliveryModal");
    const cancelModalEl = qs("#cmCpfCancelModal");

    const resultModal = bootstrap.Modal.getOrCreateInstance(resultModalEl);
    const actionsModal = bootstrap.Modal.getOrCreateInstance(actionsModalEl);
    const detailModal = bootstrap.Modal.getOrCreateInstance(detailModalEl);
    const deliveryModal = bootstrap.Modal.getOrCreateInstance(deliveryModalEl);
    const cancelModal = bootstrap.Modal.getOrCreateInstance(cancelModalEl);

    let currentCpf = "";
    let currentData = null;
    let currentDetail = null;
    let controller = null;
    let suppressChildReturn = false;
    let detailDefaultTab = "cadastro";

    const maskCpf = (value) => {
        const numbers = digits(value).slice(0, 11);
        if (numbers.length <= 3) return numbers;
        if (numbers.length <= 6) return `${numbers.slice(0, 3)}.${numbers.slice(3)}`;
        if (numbers.length <= 9) return `${numbers.slice(0, 3)}.${numbers.slice(3, 6)}.${numbers.slice(6)}`;
        return `${numbers.slice(0, 3)}.${numbers.slice(3, 6)}.${numbers.slice(6, 9)}-${numbers.slice(9)}`;
    };

    const validCpf = (value) => {
        const cpf = digits(value);
        if (!/^\d{11}$/.test(cpf) || /^(\d)\1{10}$/.test(cpf)) return false;
        const calc = (length) => {
            let sum = 0;
            for (let i = 0; i < length; i += 1) sum += Number(cpf[i]) * (length + 1 - i);
            const mod = (sum * 10) % 11;
            return mod === 10 ? 0 : mod;
        };
        return calc(9) === Number(cpf[9]) && calc(10) === Number(cpf[10]);
    };

    const fullCpf = (data = {}) => {
        const value = data.cpf_completo || data.cpf_formatted || data.cpf_formatado || data.cpf || data.cpf_mascarado || data.cpf_masked || "";
        const numbers = digits(value);
        return numbers.length === 11 && !String(value).includes("*") ? maskCpf(numbers) : (value || "Não informado");
    };

    const initials = (name) => {
        const parts = String(name || "").trim().split(/\s+/).filter(Boolean);
        return (parts.length ? parts.slice(0, 2).map((part) => part[0]).join("") : "P").toUpperCase();
    };

    const statusMeta = (data) => {
        if (!data?.competence) return { tone: "neutral", icon: "calendar-x", label: "Sem competência" };
        const status = String(data?.delivery?.status || data?.delivery?.status_label || "").toLowerCase();
        if (status.includes("receb")) return { tone: "success", icon: "check-circle", label: "Recebida" };
        if (status.includes("cancel")) return { tone: "warning", icon: "arrow-counterclockwise", label: "Entrega cancelada" };
        if (status.includes("bloque")) return { tone: "danger", icon: "lock", label: "Bloqueada" };
        if (status.includes("aguard")) return { tone: "info", icon: "clock", label: "Aguardando retirada" };
        return { tone: "info", icon: "clock", label: data?.delivery?.status_label || "Aguardando retirada" };
    };

    const modalShown = (element) => element?.classList.contains("show");

    const switchModal = (fromElement, fromInstance, toInstance, beforeShow = null) => {
        const showNext = () => {
            if (typeof beforeShow === "function") beforeShow();
            toInstance.show();
        };
        if (!fromElement || !modalShown(fromElement)) {
            showNext();
            return;
        }
        fromElement.addEventListener("hidden.bs.modal", showNext, { once: true });
        fromInstance.hide();
    };

    const setStep = (step) => {
        qsa("[data-cm-step]", root).forEach((item) => {
            const itemStep = Number(item.dataset.cmStep || 0);
            item.classList.toggle("is-active", itemStep === step);
            item.classList.toggle("is-done", itemStep < step);
        });
    };

    const alertHTML = (tone, icon, title, text) => `
        <div class="cm-cpf-alert cm-cpf-alert--${escapeHTML(tone)}">
            <i class="bi bi-${escapeHTML(icon)}"></i>
            <div><strong>${escapeHTML(title)}</strong><span>${escapeHTML(text)}</span></div>
        </div>`;

    const setInlineAlert = (html = "") => {
        inlineAlert.innerHTML = html;
        inlineAlert.hidden = html === "";
    };

    const renderLastResult = (data) => {
        lastResult.hidden = false;
        if (data.state === "inscrito") {
            const status = statusMeta(data);
            lastResult.innerHTML = `
                <div class="cm-last-person">
                    <span class="cm-last-avatar">${escapeHTML(initials(data.person?.name))}</span>
                    <div>
                        <small>Última consulta</small>
                        <strong>${escapeHTML(data.person?.name || "Pessoa inscrita")}</strong>
                        <span>CPF ${escapeHTML(fullCpf(data.person || data))} · ${escapeHTML(data.registration?.family_code || "Sem família")}</span>
                    </div>
                </div>
                <span class="cm-status cm-status--${escapeHTML(status.tone)}"><i class="bi bi-${escapeHTML(status.icon)}"></i>${escapeHTML(status.label)}</span>
                <button class="btn btn-light" type="button" data-cm-open-result><i class="bi bi-eye"></i>Reabrir resultado</button>`;
            return;
        }
        const label = data.state === "pessoa_sem_inscricao" ? "Pessoa sem inscrição" : "CPF não localizado";
        lastResult.innerHTML = `
            <div class="cm-last-person">
                <span class="cm-last-avatar"><i class="bi bi-person"></i></span>
                <div><small>Última consulta</small><strong>${escapeHTML(data.person?.name || label)}</strong><span>CPF ${escapeHTML(maskCpf(currentCpf))}</span></div>
            </div>
            <span class="cm-status cm-status--warning"><i class="bi bi-exclamation-circle"></i>${escapeHTML(label)}</span>
            <button class="btn btn-light" type="button" data-cm-open-result><i class="bi bi-eye"></i>Reabrir resultado</button>`;
    };

    const resultIdentity = (data) => {
        const person = data.person || {};
        return `
            <section class="cm-result-person">
                <span class="cm-result-avatar">${escapeHTML(initials(person.name || "P"))}</span>
                <div class="cm-result-copy">
                    <span class="cm-result-overline">Pessoa consultada</span>
                    <h3>${escapeHTML(person.name || "CPF consultado")}</h3>
                    <p><i class="bi bi-person-vcard"></i> CPF ${escapeHTML(fullCpf(person.cpf_formatado ? person : data))}</p>
                </div>
            </section>`;
    };

    const renderResultModal = (data) => {
        const body = qs("[data-cm-result-body]");
        const footer = qs("[data-cm-result-footer]");

        if (data.state === "nao_localizado") {
            const anexoFound = Boolean(data.anexo?.found);
            body.innerHTML = `
                <div class="cm-result-state cm-result-state--warning">
                    <span><i class="bi bi-search"></i></span>
                    <h3>${anexoFound ? "Pessoa localizada no ANEXO" : "CPF não localizado no SIGAS"}</h3>
                    <p>${anexoFound
                        ? `${escapeHTML(data.anexo?.person?.name || "Pessoa localizada")}, mas ainda sem inscrição no Coari Comida na Mesa.`
                        : "Nenhuma pessoa foi encontrada para o CPF informado."}</p>
                    <div class="cm-result-cpf">${escapeHTML(maskCpf(currentCpf))}</div>
                </div>`;
            footer.innerHTML = `
                <button class="btn btn-light" type="button" data-cm-new-query><i class="bi bi-arrow-repeat"></i>Consultar outro CPF</button>
                ${permissions.create ? `<a class="btn btn-primary" href="${beneficiariesUrl}?action=new&cpf=${encodeURIComponent(currentCpf)}"><i class="bi bi-person-plus"></i>Iniciar cadastro</a>` : ""}`;
            return;
        }

        if (data.state === "pessoa_sem_inscricao") {
            body.innerHTML = `
                ${resultIdentity(data)}
                <div class="cm-result-state cm-result-state--warning cm-result-state--compact">
                    <span><i class="bi bi-person-exclamation"></i></span>
                    <div><h3>Pessoa localizada sem inscrição</h3><p>${data.family?.code ? `Vínculo familiar encontrado: ${escapeHTML(data.family.code)}.` : "Ainda não há uma inscrição ativa no programa."}</p></div>
                </div>`;
            footer.innerHTML = `
                <button class="btn btn-light" type="button" data-cm-new-query><i class="bi bi-arrow-repeat"></i>Consultar outro CPF</button>
                ${permissions.create ? `<a class="btn btn-primary" href="${beneficiariesUrl}?action=new&cpf=${encodeURIComponent(currentCpf)}"><i class="bi bi-person-plus"></i>Criar inscrição</a>` : ""}`;
            return;
        }

        const status = statusMeta(data);
        const eligibility = data.eligibility || {};
        const tone = eligibility.allowed ? "success" : (String(eligibility.reason || "").toLowerCase().includes("bloque") ? "danger" : "warning");
        body.innerHTML = `
            ${resultIdentity(data)}
            <div class="cm-result-status-grid">
                <div><span>Situação no programa</span><strong>${escapeHTML(data.registration?.status_label || "Não informado")}</strong></div>
                <div><span>Família</span><strong>${escapeHTML(data.registration?.family_code || data.family?.code || "Não informado")}</strong></div>
                <div><span>Polo</span><strong>${escapeHTML(data.registration?.pole || "Sem polo")}</strong></div>
                <div><span>Competência</span><strong>${escapeHTML(data.competence?.label || "Sem competência")}</strong></div>
                <div><span>Situação da entrega</span><strong class="cm-status-text cm-status-text--${escapeHTML(status.tone)}"><i class="bi bi-${escapeHTML(status.icon)}"></i>${escapeHTML(status.label)}</strong></div>
                <div><span>Prioridade</span><strong>${escapeHTML(data.registration?.priority || "normal")}</strong></div>
            </div>
            <div class="cm-result-rule cm-result-rule--${escapeHTML(tone)}">
                <i class="bi bi-info-circle"></i>
                <div><strong>Regra operacional</strong><p>${escapeHTML(eligibility.reason || "Beneficiário apto para a operação atual.")}</p></div>
            </div>`;
        footer.innerHTML = `
            <button class="btn btn-light" type="button" data-cm-new-query><i class="bi bi-arrow-repeat"></i>Nova consulta</button>
            <button class="btn btn-primary" type="button" data-cm-open-actions><i class="bi bi-grid"></i>Continuar para ações</button>`;
    };

    const actionCard = ({ id = "", href = "", icon, title, text, tone = "default", disabled = false }) => {
        const common = `class="cm-action-card cm-action-card--${escapeHTML(tone)}${disabled ? " is-disabled" : ""}"`;
        const content = `<span class="cm-action-icon"><i class="bi bi-${escapeHTML(icon)}"></i></span><div><strong>${escapeHTML(title)}</strong><small>${escapeHTML(text)}</small></div><i class="bi bi-chevron-right cm-action-arrow"></i>`;
        if (href && !disabled) return `<a ${common} href="${escapeHTML(href)}">${content}</a>`;
        return `<button ${common} type="button" ${id ? `data-cm-action="${escapeHTML(id)}"` : ""} ${disabled ? "disabled" : ""}>${content}</button>`;
    };

    const renderActionsModal = () => {
        if (!currentData || currentData.state !== "inscrito") return;
        const data = currentData;
        const status = statusMeta(data);
        const eligibility = data.eligibility || {};
        const canDelivery = Boolean(permissions.deliver && eligibility.allowed && ["register", "reactivate"].includes(eligibility.action));
        const canCancel = Boolean(permissions.cancelDelivery && eligibility.action === "cancel");
        const deliveryText = eligibility.action === "reactivate" ? "Reativar entrega" : "Registrar entrega";

        qs("[data-cm-actions-person]").innerHTML = `
            <span class="cm-action-person-avatar">${escapeHTML(initials(data.person?.name))}</span>
            <div><small>Beneficiário</small><strong>${escapeHTML(data.person?.name || "Pessoa inscrita")}</strong><span>CPF ${escapeHTML(fullCpf(data.person || data))} · ${escapeHTML(data.registration?.family_code || "Sem família")}</span></div>
            <span class="cm-status cm-status--${escapeHTML(status.tone)}"><i class="bi bi-${escapeHTML(status.icon)}"></i>${escapeHTML(status.label)}</span>`;

        const cards = [
            actionCard({ id: "detail", icon: "eye", title: "Visualizar cadastro", text: "Dados da família, inscrição e endereço" }),
            actionCard({ href: permissions.edit ? `${beneficiariesUrl}?action=edit&id=${encodeURIComponent(data.registration?.id || "")}` : "", icon: "pencil", title: "Editar cadastro", text: permissions.edit ? "Atualizar os dados da inscrição" : "Sem permissão para editar", disabled: !permissions.edit }),
            actionCard({ id: "delivery", icon: "basket2", title: deliveryText, text: canDelivery ? "Executar a operação da competência atual" : (eligibility.reason || "Entrega indisponível"), tone: canDelivery ? "success" : "default", disabled: !canDelivery }),
            actionCard({ id: "documents", icon: "paperclip", title: "Documentos", text: permissions.viewDocuments ? "Consultar documentos vinculados" : "Sem permissão para visualizar", disabled: !permissions.viewDocuments }),
            actionCard({ id: "history", icon: "clock-history", title: "Histórico", text: permissions.viewHistory ? "Consultar movimentações e entregas" : "Sem permissão para visualizar", disabled: !permissions.viewHistory }),
            actionCard({ id: "cancel", icon: "x-circle", title: "Cancelar entrega", text: canCancel ? "Cancelar a entrega atual com justificativa" : "Cancelamento indisponível", tone: "danger", disabled: !canCancel }),
        ];

        if (!data.competence && permissions.manageCompetences) {
            cards.push(actionCard({ href: competencesUrl, icon: "calendar-plus", title: "Gerenciar competência", text: "Criar ou abrir uma competência mensal" }));
        }

        qs("[data-cm-actions-grid]").innerHTML = cards.join("");
    };

    const setLoading = (isLoading) => {
        submitButton.disabled = isLoading;
        submitButton.innerHTML = isLoading
            ? '<span class="spinner-border spinner-border-sm" aria-hidden="true"></span><span>Consultando...</span>'
            : '<i class="bi bi-search"></i><span>Consultar</span>';
    };

    const consult = async ({ autoOpen = true } = {}) => {
        currentCpf = digits(cpfInput.value);
        if (!validCpf(currentCpf)) {
            cpfInput.classList.add("is-invalid");
            setInlineAlert(alertHTML("warning", "exclamation-circle", "CPF inválido", "Confira os 11 números e tente novamente."));
            cpfInput.focus();
            return false;
        }

        cpfInput.classList.remove("is-invalid");
        setInlineAlert("");
        setLoading(true);
        setStep(1);

        if (controller) controller.abort();
        controller = new AbortController();
        const localController = controller;

        try {
            const response = await fetch(form.action, {
                method: "POST",
                body: new FormData(form),
                headers: { Accept: "application/json" },
                signal: localController.signal,
            });
            const data = await response.json();
            if (localController !== controller) return false;

            if (!response.ok || !data.ok) {
                throw new Error(data.error || "Não foi possível concluir a consulta.");
            }

            currentData = data;
            currentDetail = null;
            renderResultModal(data);
            renderLastResult(data);
            setStep(2);
            if (autoOpen) resultModal.show();
            return true;
        } catch (error) {
            if (error?.name === "AbortError") return false;
            setInlineAlert(alertHTML("danger", "wifi-off", "Consulta não concluída", error?.message || "Não foi possível consultar agora."));
            return false;
        } finally {
            if (localController === controller) setLoading(false);
        }
    };

    const resetQuery = () => {
        currentCpf = "";
        currentData = null;
        currentDetail = null;
        cpfInput.value = "";
        cpfInput.classList.remove("is-invalid");
        lastResult.hidden = true;
        lastResult.innerHTML = "";
        setInlineAlert("");
        setStep(1);
        window.setTimeout(() => cpfInput.focus(), 80);
    };

    const showActions = () => {
        if (!currentData || currentData.state !== "inscrito") return;
        renderActionsModal();
        setStep(3);
        switchModal(resultModalEl, resultModal, actionsModal);
    };

    const returnToResult = () => {
        setStep(2);
        switchModal(actionsModalEl, actionsModal, resultModal);
    };

    const childModalElements = [detailModalEl, deliveryModalEl, cancelModalEl];
    childModalElements.forEach((element) => {
        element.addEventListener("hidden.bs.modal", () => {
            if (suppressChildReturn) {
                suppressChildReturn = false;
                return;
            }
            if (currentData?.state === "inscrito" && !modalShown(actionsModalEl) && !modalShown(resultModalEl)) {
                setStep(3);
                actionsModal.show();
            }
        });
    });

    const openChild = (instance, setup) => {
        switchModal(actionsModalEl, actionsModal, instance, setup);
    };

    const detailTabLabel = {
        cadastro: "Cadastro",
        entregas: "Entregas",
        documentos: "Documentos",
        historico: "Histórico",
    };

    const renderDetail = (data, defaultTab = "cadastro") => {
        const deliveries = Array.isArray(data.entregas) ? data.entregas : [];
        const documents = Array.isArray(data.documentos) ? data.documentos : [];
        const history = Array.isArray(data.historico) ? data.historico : [];
        const content = qs("[data-cm-detail-content]");

        content.innerHTML = `
            <div class="cm-detail-tabs" role="tablist" aria-label="Informações do beneficiário">
                ${Object.entries(detailTabLabel).map(([key, label]) => `<button class="cm-detail-tab${key === defaultTab ? " is-active" : ""}" type="button" data-cm-detail-tab="${key}">${escapeHTML(label)}</button>`).join("")}
            </div>

            <section class="cm-detail-pane${defaultTab === "cadastro" ? " is-active" : ""}" data-cm-detail-pane="cadastro">
                <div class="cm-detail-summary-grid">
                    <article><span>Responsável familiar</span><strong>${escapeHTML(data.nome || "Não informado")}</strong><small>CPF ${escapeHTML(fullCpf(data))}</small></article>
                    <article><span>Família</span><strong>${escapeHTML(data.familia_codigo || "Não informado")}</strong><small>${escapeHTML([data.logradouro, data.numero, data.bairro, data.comunidade].filter(Boolean).join(", ") || "Endereço não informado")}</small></article>
                    <article><span>Situação no programa</span><strong>${escapeHTML(data.status || "Não informado")}</strong><small>${escapeHTML(data.polo_nome || "Sem polo")}</small></article>
                </div>
            </section>

            <section class="cm-detail-pane${defaultTab === "entregas" ? " is-active" : ""}" data-cm-detail-pane="entregas">
                <div class="table-responsive cm-detail-table-wrap">
                    <table class="cm-data-table">
                        <thead><tr><th>Competência</th><th>Situação</th><th>Entrega</th><th>Recebedor</th><th>Observação</th></tr></thead>
                        <tbody>
                            ${deliveries.length ? deliveries.map((item) => `<tr><td>${escapeHTML(String(item.mes || "").padStart(2, "0"))}/${escapeHTML(item.ano || "")}</td><td>${escapeHTML(item.status || "")}</td><td>${escapeHTML(item.entregue_em || "—")}</td><td>${escapeHTML(item.recebedor_nome || "—")}</td><td>${escapeHTML(item.motivo_cancelamento || "—")}</td></tr>`).join("") : '<tr><td colspan="5"><div class="cm-empty-row">Nenhuma entrega registrada.</div></td></tr>'}
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="cm-detail-pane${defaultTab === "documentos" ? " is-active" : ""}" data-cm-detail-pane="documentos">
                <div class="cm-detail-list-cards">
                    ${documents.length ? documents.map((item) => `<article><span class="cm-detail-list-icon"><i class="bi bi-file-earmark-text"></i></span><div><strong>${escapeHTML(item.tipo || "Documento")}</strong><small>${escapeHTML(item.nome_original || "Arquivo")}</small></div><a class="btn btn-light btn-sm" target="_blank" rel="noopener" href="api/comida-mesa/visualizar-documento.php?id=${encodeURIComponent(item.id)}"><i class="bi bi-box-arrow-up-right"></i>Abrir</a></article>`).join("") : '<div class="cm-empty-state"><i class="bi bi-folder2-open"></i><strong>Nenhum documento disponível</strong><span>Não há arquivos vinculados a esta inscrição.</span></div>'}
                </div>
            </section>

            <section class="cm-detail-pane${defaultTab === "historico" ? " is-active" : ""}" data-cm-detail-pane="historico">
                <div class="cm-history-timeline">
                    ${history.length ? history.map((item) => `<article><span class="cm-history-dot"></span><div><strong>${escapeHTML(item.acao || "Movimentação")}</strong><p>${escapeHTML(item.descricao || "")}</p><small>${escapeHTML(item.criado_em || "")}</small></div></article>`).join("") : '<div class="cm-empty-state"><i class="bi bi-clock-history"></i><strong>Sem movimentações</strong><span>Não há histórico disponível para esta inscrição.</span></div>'}
                </div>
            </section>`;

        qs("[data-cm-detail-loading]").hidden = true;
        content.hidden = false;
    };

    const loadDetail = async (defaultTab = "cadastro") => {
        if (!currentData?.registration?.id) return;
        detailDefaultTab = defaultTab;
        const loading = qs("[data-cm-detail-loading]");
        const content = qs("[data-cm-detail-content]");
        loading.hidden = false;
        loading.innerHTML = '<span class="spinner-border spinner-border-sm" aria-hidden="true"></span><span>Carregando informações...</span>';
        content.hidden = true;
        content.innerHTML = "";

        try {
            if (!currentDetail) {
                const response = await fetch(`api/comida-mesa/detalhar.php?id=${encodeURIComponent(currentData.registration.id)}`, { headers: { Accept: "application/json" } });
                const payload = await response.json();
                if (!response.ok || !payload.ok) throw new Error(payload.error || "Não foi possível carregar os detalhes.");
                currentDetail = payload.data;
            }
            renderDetail(currentDetail, detailDefaultTab);
        } catch (error) {
            loading.innerHTML = alertHTML("danger", "exclamation-triangle", "Detalhes indisponíveis", error?.message || "Tente novamente.");
        }
    };

    const fillDelivery = () => {
        const data = currentData;
        const deliveryForm = qs("#cmCpfDeliveryForm");
        deliveryForm.reset();
        qs("[data-cm-form-alert]", deliveryForm).innerHTML = "";
        qs('[name="inscricao_id"]', deliveryForm).value = data.registration?.id || "";
        qs('[name="competencia_id"]', deliveryForm).value = data.competence?.id || "";
        qs('[name="recebedor_nome"]', deliveryForm).value = data.person?.name || "";
        qs('[name="recebedor_cpf"]', deliveryForm).value = maskCpf(currentCpf);
        qs("[data-cm-delivery-name]", deliveryForm).textContent = data.person?.name || "—";
        qs("[data-cm-delivery-family]", deliveryForm).textContent = data.registration?.family_code || "—";
        qs("[data-cm-delivery-competence]", deliveryForm).textContent = data.competence?.label || "—";
        qs("[data-cm-delivery-pole]", deliveryForm).textContent = data.registration?.pole || "—";
        qs("#cmCpfDeliveryTitle").textContent = data.eligibility?.action === "reactivate" ? "Reativar entrega" : "Registrar entrega";
    };

    const fillCancel = () => {
        const cancelForm = qs("#cmCpfCancelForm");
        cancelForm.reset();
        qs("[data-cm-form-alert]", cancelForm).innerHTML = "";
        qs('[name="inscricao_id"]', cancelForm).value = currentData.registration?.id || "";
        qs('[name="competencia_id"]', cancelForm).value = currentData.competence?.id || "";
    };

    const formAlert = (formElement, html = "") => {
        const target = qs("[data-cm-form-alert]", formElement);
        if (target) target.innerHTML = html;
    };

    const submitAction = async (formElement) => {
        const button = qs('[type="submit"]', formElement);
        const oldHtml = button.innerHTML;
        button.disabled = true;
        button.innerHTML = '<span class="spinner-border spinner-border-sm" aria-hidden="true"></span>Processando...';
        formAlert(formElement, "");

        try {
            const response = await fetch(formElement.action, {
                method: "POST",
                body: new FormData(formElement),
                headers: { Accept: "application/json" },
            });
            const payload = await response.json();
            if (!response.ok || !payload.ok) throw new Error(payload.error || "Não foi possível concluir a operação.");

            const owner = formElement.closest(".modal");
            suppressChildReturn = true;
            if (owner) {
                await new Promise((resolve) => {
                    owner.addEventListener("hidden.bs.modal", resolve, { once: true });
                    bootstrap.Modal.getOrCreateInstance(owner).hide();
                });
            }

            cpfInput.value = maskCpf(currentCpf);
            const refreshed = await consult({ autoOpen: true });
            if (refreshed) {
                setInlineAlert(alertHTML("success", "check-circle", "Operação concluída", payload.message || "Os dados foram atualizados com sucesso."));
            }
            return true;
        } catch (error) {
            formAlert(formElement, alertHTML("danger", "exclamation-triangle", "Operação não concluída", error?.message || "Revise os dados e tente novamente."));
            return false;
        } finally {
            button.disabled = false;
            button.innerHTML = oldHtml;
        }
    };

    form.addEventListener("submit", (event) => {
        event.preventDefault();
        consult({ autoOpen: true });
    });

    cpfInput.addEventListener("input", () => {
        cpfInput.value = maskCpf(cpfInput.value);
        cpfInput.classList.remove("is-invalid");
        setInlineAlert("");
    });

    document.addEventListener("click", (event) => {
        if (event.target.closest("[data-cm-open-result]")) {
            if (currentData) {
                setStep(2);
                resultModal.show();
            }
            return;
        }

        if (event.target.closest("[data-cm-open-actions]")) {
            showActions();
            return;
        }

        if (event.target.closest("[data-cm-back-result]")) {
            returnToResult();
            return;
        }

        if (event.target.closest("[data-cm-new-query]")) {
            const finish = () => resetQuery();
            if (modalShown(actionsModalEl)) {
                actionsModalEl.addEventListener("hidden.bs.modal", finish, { once: true });
                actionsModal.hide();
            } else if (modalShown(resultModalEl)) {
                resultModalEl.addEventListener("hidden.bs.modal", finish, { once: true });
                resultModal.hide();
            } else {
                finish();
            }
            return;
        }

        const action = event.target.closest("[data-cm-action]");
        if (action?.disabled) return;
        const actionName = action?.dataset.cmAction;

        if (actionName === "detail") {
            openChild(detailModal, () => loadDetail("cadastro"));
            return;
        }
        if (actionName === "documents") {
            openChild(detailModal, () => loadDetail("documentos"));
            return;
        }
        if (actionName === "history") {
            openChild(detailModal, () => loadDetail("historico"));
            return;
        }
        if (actionName === "delivery") {
            openChild(deliveryModal, fillDelivery);
            return;
        }
        if (actionName === "cancel") {
            openChild(cancelModal, fillCancel);
            return;
        }

        const tab = event.target.closest("[data-cm-detail-tab]");
        if (tab) {
            const key = tab.dataset.cmDetailTab;
            qsa("[data-cm-detail-tab]").forEach((item) => item.classList.toggle("is-active", item.dataset.cmDetailTab === key));
            qsa("[data-cm-detail-pane]").forEach((pane) => pane.classList.toggle("is-active", pane.dataset.cmDetailPane === key));
        }
    });

    qs('#cmCpfDeliveryForm [name="recebedor_cpf"]').addEventListener("input", (event) => {
        event.target.value = maskCpf(event.target.value);
    });

    qs("#cmCpfDeliveryForm").addEventListener("submit", async (event) => {
        event.preventDefault();
        await submitAction(event.currentTarget);
    });

    qs("#cmCpfCancelForm").addEventListener("submit", async (event) => {
        event.preventDefault();
        await submitAction(event.currentTarget);
    });

    resultModalEl.addEventListener("shown.bs.modal", () => setStep(2));
    actionsModalEl.addEventListener("shown.bs.modal", () => setStep(3));
    resultModalEl.addEventListener("hidden.bs.modal", () => {
        if (!modalShown(actionsModalEl) && !modalShown(detailModalEl) && !modalShown(deliveryModalEl) && !modalShown(cancelModalEl)) {
            setStep(currentData ? 2 : 1);
        }
    });

    window.setTimeout(() => cpfInput.focus(), 120);
})();
