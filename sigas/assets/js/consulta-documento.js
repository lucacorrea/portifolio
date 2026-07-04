(() => {
    "use strict";

    const root = document.querySelector("[data-consulta-documento]");
    if (!root) return;

    const ctx = window.SIGAS_CONTEXT?.consultaDocumento || {};
    const permissions = ctx.permissions || {};
    const qs = (selector, base = document) => base.querySelector(selector);
    const qsa = (selector, base = document) => [...base.querySelectorAll(selector)];
    const escapeHTML = (value) => String(value ?? "").replaceAll("&", "&amp;").replaceAll("<", "&lt;").replaceAll(">", "&gt;").replaceAll('"', "&quot;").replaceAll("'", "&#039;");
    const digits = (value) => String(value || "").replace(/\D+/g, "");
    const modal = (selector) => bootstrap.Modal.getOrCreateInstance(qs(selector));
    const result = qs("#consultaResult");
    const form = qs("#manualCpfForm");
    const cpfInput = qs("#manualCpf");
    let currentCpf = "";
    let currentData = null;
    let controller = null;
    let stream = null;

    const maskCpf = (value) => {
        const numbers = digits(value).slice(0, 11);
        if (numbers.length <= 3) return numbers;
        if (numbers.length <= 6) return `${numbers.slice(0, 3)}.${numbers.slice(3)}`;
        if (numbers.length <= 9) return `${numbers.slice(0, 3)}.${numbers.slice(3, 6)}.${numbers.slice(6)}`;
        return `${numbers.slice(0, 3)}.${numbers.slice(3, 6)}.${numbers.slice(6, 9)}-${numbers.slice(9)}`;
    };

    const panel = (icon, title, text, tone = "info") => `<div class="alert-soft ${tone}"><i class="bi bi-${icon}"></i><div><strong>${escapeHTML(title)}</strong><br><span>${escapeHTML(text)}</span></div></div>`;
    const button = (attrs, icon, text, cls = "btn btn-primary") => `<button class="${cls}" type="button" ${attrs}><i class="bi bi-${icon}"></i>${escapeHTML(text)}</button>`;

    const setLoading = () => {
        result.hidden = false;
        result.innerHTML = `<div class="state-panel show"><i class="bi bi-hourglass-split"></i><h2>Consultando</h2><p>Buscando CPF no SIGAS.</p></div>`;
    };

    const renderNotFound = (data) => {
        const action = permissions.create ? `<a class="btn btn-primary" href="modulo.php?action=new&cpf=${encodeURIComponent(currentCpf)}"><i class="bi bi-plus-lg"></i>Iniciar cadastro no Comida na Mesa</a>` : "";
        result.innerHTML = `${panel("search", "Pessoa não localizada no SIGAS", "Nenhuma pessoa foi encontrada para o CPF informado.", "warning")}<div class="result-actions mt-3">${action}${button("data-reset-consulta", "arrow-repeat", "Consultar outra pessoa", "btn btn-light")}</div>`;
    };

    const renderPersonWithoutRegistration = (data) => {
        const action = permissions.create ? `<a class="btn btn-primary" href="modulo.php?action=new&cpf=${encodeURIComponent(currentCpf)}"><i class="bi bi-plus-lg"></i>Criar inscrição</a>` : "";
        result.innerHTML = `
            <div class="result-status-header"><span class="result-avatar">${escapeHTML((data.person?.name || "P").slice(0, 2).toUpperCase())}</span><div><span class="result-overline">Pessoa localizada sem inscrição</span><h2>${escapeHTML(data.person?.name)}</h2><p>CPF: ${escapeHTML(data.person?.cpf_masked)}</p></div><span class="status-badge status-warning"><i class="bi bi-hourglass"></i>Sem inscrição</span></div>
            <div class="verification-grid"><div><span>Vínculo familiar</span><strong>${escapeHTML(data.person?.vinculo_familiar || "sem_familia")}</strong></div><div><span>Família</span><strong>${escapeHTML(data.family?.code || "Sem família")}</strong></div></div>
            <div class="result-actions">${action}${button("data-reset-consulta", "arrow-repeat", "Consultar outra pessoa", "btn btn-light")}</div>`;
    };

    const renderRegistered = (data) => {
        const eligibility = data.eligibility || {};
        const canDelivery = permissions.deliver && eligibility.allowed && ["register", "reactivate"].includes(eligibility.action);
        const canCancel = permissions.cancelDelivery && eligibility.action === "cancel";
        const deliveryText = data.delivery?.status_label || "Não informado";
        const deliveryMeta = data.delivery?.delivered_at ? `${data.delivery.delivered_at}${data.delivery.receiver_name ? " · " + data.delivery.receiver_name : ""}` : (data.delivery?.cancellation_reason || eligibility.reason || "");
        result.innerHTML = `
            <div class="result-status-header">
                <span class="result-avatar">${escapeHTML((data.person?.name || "P").slice(0, 2).toUpperCase())}</span>
                <div><span class="result-overline">Pessoa inscrita</span><h2>${escapeHTML(data.person?.name)}</h2><p>CPF: ${escapeHTML(data.person?.cpf_masked)}</p></div>
                <span class="status-badge ${data.delivery?.status === "recebida" ? "status-success" : "status-info"}"><i class="bi bi-basket2"></i>${escapeHTML(deliveryText)}</span>
            </div>
            <div class="verification-grid">
                <div><span>Família</span><strong>${escapeHTML(data.registration?.family_code || data.family?.code)}</strong></div>
                <div><span>Situação</span><strong>${escapeHTML(data.registration?.status_label)}</strong></div>
                <div><span>Prioridade</span><strong>${escapeHTML(data.registration?.priority)}</strong></div>
                <div><span>Polo</span><strong>${escapeHTML(data.registration?.pole || "Sem polo")}</strong></div>
                <div><span>Competência</span><strong>${escapeHTML(data.competence?.label || "Sem competência")}</strong></div>
                <div><span>Entrega</span><strong>${escapeHTML(deliveryMeta || deliveryText)}</strong></div>
            </div>
            ${eligibility.reason ? panel("info-circle", "Regra operacional", eligibility.reason, "info") : ""}
            <div class="result-actions">
                ${button(`data-open-detail data-registration-id="${escapeHTML(data.registration?.id)}"`, "eye", "Visualizar detalhes", "btn btn-light")}
                ${permissions.edit ? `<a class="btn btn-light" href="modulo.php?action=edit&id=${encodeURIComponent(data.registration?.id)}"><i class="bi bi-pencil"></i>Editar</a>` : ""}
                ${canDelivery ? button("data-open-delivery", "basket2", eligibility.action === "reactivate" ? "Reativar entrega" : "Registrar entrega") : ""}
                ${canCancel ? button("data-open-cancel", "x-circle", "Cancelar entrega", "btn btn-danger") : ""}
                ${!data.competence && permissions.manageCompetences ? '<a class="btn btn-light" href="modulo.php?action=competence"><i class="bi bi-calendar-plus"></i>Gerenciar competência</a>' : ""}
                ${button("data-reset-consulta", "arrow-repeat", "Consultar outra pessoa", "btn btn-light")}
            </div>`;
    };

    const renderData = (data) => {
        currentData = data;
        if (data.state === "nao_localizado") renderNotFound(data);
        else if (data.state === "pessoa_sem_inscricao") renderPersonWithoutRegistration(data);
        else renderRegistered(data);
    };

    const consult = async () => {
        currentCpf = digits(cpfInput.value);
        if (currentCpf.length !== 11) {
            cpfInput.classList.add("is-invalid");
            return;
        }
        cpfInput.classList.remove("is-invalid");
        if (controller) controller.abort();
        controller = new AbortController();
        const current = controller;
        setLoading();
        const submit = qs('[type="submit"]', form);
        submit.disabled = true;
        try {
            const response = await fetch(form.action, { method: "POST", body: new FormData(form), headers: { Accept: "application/json" }, signal: current.signal });
            const data = await response.json();
            if (current !== controller) return;
            if (!response.ok || !data.ok) {
                result.innerHTML = panel("exclamation-triangle", "Consulta não realizada", data.error || "Revise o CPF informado.", "warning");
                return;
            }
            renderData(data);
        } catch (error) {
            if (error.name !== "AbortError") result.innerHTML = panel("wifi-off", "Erro de comunicação", "Não foi possível consultar agora.", "warning");
        } finally {
            if (current === controller) submit.disabled = false;
        }
    };

    const setAlert = (formEl, html = "") => {
        const alert = qs("[data-form-alert]", formEl);
        if (alert) alert.innerHTML = html;
    };

    const submitAction = async (formEl) => {
        setAlert(formEl, panel("hourglass-split", "Processando", "Aguarde a confirmação do servidor."));
        const submit = qs('[type="submit"]', formEl);
        submit.disabled = true;
        try {
            const response = await fetch(formEl.action, { method: "POST", body: new FormData(formEl), headers: { Accept: "application/json" } });
            const data = await response.json();
            if (!response.ok || !data.ok) {
                setAlert(formEl, panel("exclamation-triangle", "Operação não concluída", data.error || "Revise os dados.", "warning"));
                await consult();
                return false;
            }
            await consult();
            return true;
        } catch (error) {
            setAlert(formEl, panel("wifi-off", "Erro de comunicação", "Não foi possível concluir.", "warning"));
            return false;
        } finally {
            submit.disabled = false;
        }
    };

    form.addEventListener("submit", (event) => { event.preventDefault(); consult(); });
    cpfInput.addEventListener("input", () => { cpfInput.value = maskCpf(cpfInput.value); cpfInput.classList.remove("is-invalid"); });

    document.addEventListener("click", async (event) => {
        if (event.target.closest("[data-reset-consulta]")) {
            currentCpf = "";
            currentData = null;
            cpfInput.value = "";
            result.innerHTML = '<span class="result-empty-icon"><i class="bi bi-person-lines-fill"></i></span><h2>Resultado da consulta</h2><p>Informe o CPF para visualizar pessoa, família, inscrição, competência e situação da entrega.</p>';
        }
        if (event.target.closest("[data-open-delivery]") && currentData) {
            const formEl = qs("#consultaDeliveryForm");
            formEl.reset();
            setAlert(formEl, "");
            qs('[name="inscricao_id"]', formEl).value = currentData.registration.id;
            qs('[name="competencia_id"]', formEl).value = currentData.competence?.id || "";
            qs('[name="recebedor_nome"]', formEl).value = currentData.person?.name || "";
            qs('[name="recebedor_cpf"]', formEl).value = maskCpf(currentCpf);
            qs("[data-delivery-name]", formEl).textContent = currentData.person?.name || "";
            qs("[data-delivery-family]", formEl).textContent = currentData.registration?.family_code || "";
            qs("[data-delivery-competence]", formEl).textContent = currentData.competence?.label || "";
            qs("[data-delivery-pole]", formEl).textContent = currentData.registration?.pole || "";
            modal("#consultaDeliveryModal").show();
        }
        if (event.target.closest("[data-open-cancel]") && currentData) {
            const formEl = qs("#consultaCancelForm");
            formEl.reset();
            setAlert(formEl, "");
            qs('[name="inscricao_id"]', formEl).value = currentData.registration.id;
            qs('[name="competencia_id"]', formEl).value = currentData.competence?.id || "";
            modal("#consultaCancelModal").show();
        }
        const detail = event.target.closest("[data-open-detail]");
        if (detail) {
            const content = qs("[data-detail-content]");
            content.innerHTML = panel("hourglass-split", "Carregando", "Buscando detalhes.");
            modal("#consultaDetailModal").show();
            const response = await fetch(`api/comida-mesa/detalhar.php?id=${encodeURIComponent(detail.dataset.registrationId)}`, { headers: { Accept: "application/json" } });
            const payload = await response.json();
            if (!response.ok || !payload.ok) {
                content.innerHTML = panel("exclamation-triangle", "Detalhes indisponíveis", payload.error || "Tente novamente.", "warning");
                return;
            }
            const data = payload.data;
            const deliveries = (data.entregas || []).map((item) => `<tr><td>${escapeHTML(String(item.mes).padStart(2, "0"))}/${escapeHTML(item.ano)}</td><td>${escapeHTML(item.status)}</td><td>${escapeHTML(item.entregue_em || "")}</td><td>${escapeHTML(item.recebedor_nome || "")}</td><td>${escapeHTML(item.motivo_cancelamento || "")}</td></tr>`).join("");
            const documents = (data.documentos || []).map((item) => `<li class="list-group-item d-flex justify-content-between"><span>${escapeHTML(item.tipo)}<br><small>${escapeHTML(item.nome_original)}</small></span><a class="btn btn-light btn-sm" target="_blank" rel="noopener" href="api/comida-mesa/visualizar-documento.php?id=${encodeURIComponent(item.id)}">Abrir</a></li>`).join("");
            const history = (data.historico || []).map((item) => `<li class="list-group-item"><strong>${escapeHTML(item.acao)}</strong><br><span>${escapeHTML(item.descricao || "")}</span><br><small>${escapeHTML(item.criado_em || "")}</small></li>`).join("");
            content.innerHTML = `<div class="row g-3"><div class="col-md-4"><h3 class="fs-6">Responsável</h3><p><strong>${escapeHTML(data.nome)}</strong><br>CPF ${escapeHTML(data.cpf_mascarado)}</p></div><div class="col-md-4"><h3 class="fs-6">Família</h3><p>${escapeHTML(data.familia_codigo)}<br>${escapeHTML([data.logradouro, data.numero, data.bairro, data.comunidade].filter(Boolean).join(", "))}</p></div><div class="col-md-4"><h3 class="fs-6">Inscrição</h3><p>${escapeHTML(data.status)}<br>${escapeHTML(data.polo_nome || "Sem polo")}</p></div></div><h3 class="fs-6 mt-3">Entregas</h3><div class="table-responsive"><table class="data-table"><tbody>${deliveries || '<tr><td>Sem entregas.</td></tr>'}</tbody></table></div><div class="row g-3 mt-2"><div class="col-md-6"><h3 class="fs-6">Documentos</h3><ul class="list-group">${documents || '<li class="list-group-item">Sem documentos disponíveis.</li>'}</ul></div><div class="col-md-6"><h3 class="fs-6">Histórico</h3><ul class="list-group">${history || '<li class="list-group-item">Sem histórico disponível.</li>'}</ul></div></div>`;
        }
    });

    qs("#consultaDeliveryForm").addEventListener("submit", async (event) => {
        event.preventDefault();
        if (await submitAction(event.currentTarget)) modal("#consultaDeliveryModal").hide();
    });

    qs("#consultaCancelForm").addEventListener("submit", async (event) => {
        event.preventDefault();
        if (await submitAction(event.currentTarget)) modal("#consultaCancelModal").hide();
    });

    const video = qs("#scannerVideo");
    const preview = qs("#scannerPreview");
    const placeholder = qs("#cameraPlaceholder");
    const openCamera = qs("#openCameraButton");
    const capture = qs("#captureDocumentButton");
    const fileInput = qs("#documentImageInput");

    const stopCamera = () => {
        stream?.getTracks().forEach((track) => track.stop());
        stream = null;
        video.hidden = true;
        capture.hidden = true;
        openCamera.hidden = false;
    };

    openCamera.addEventListener("click", async () => {
        if (navigator.mediaDevices?.getUserMedia) {
            try {
                stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: "environment" }, audio: false });
                video.srcObject = stream;
                await video.play();
                video.hidden = false;
                preview.hidden = true;
                placeholder.hidden = true;
                capture.hidden = false;
                openCamera.hidden = true;
                return;
            } catch (error) {
                fileInput.click();
                return;
            }
        }
        fileInput.click();
    });

    capture.addEventListener("click", () => {
        const canvas = document.createElement("canvas");
        canvas.width = video.videoWidth || 640;
        canvas.height = video.videoHeight || 360;
        canvas.getContext("2d").drawImage(video, 0, 0, canvas.width, canvas.height);
        preview.src = canvas.toDataURL("image/jpeg", 0.85);
        preview.hidden = false;
        placeholder.hidden = true;
        stopCamera();
        result.innerHTML = panel("camera", "Documento capturado", "Confira o documento e informe o CPF abaixo para realizar a consulta.", "info");
    });

    fileInput.addEventListener("change", () => {
        const file = fileInput.files?.[0];
        if (!file) return;
        preview.src = URL.createObjectURL(file);
        preview.hidden = false;
        placeholder.hidden = true;
        result.innerHTML = panel("camera", "Documento selecionado", "Confira o documento e informe o CPF abaixo para realizar a consulta.", "info");
    });

    window.addEventListener("pagehide", stopCamera);
})();
