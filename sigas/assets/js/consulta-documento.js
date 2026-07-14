(() => {
    "use strict";
    const root = document.querySelector("[data-consulta-documento]");
    if (!root) return;
    const CPF_ROI = Object.freeze({ x: 0.06, y: 0.39, width: 0.88, height: 0.22 });
    const ctx = window.SIGAS_CONTEXT?.consultaDocumento || {};
    const permissions = ctx.permissions || {};
    const cpfOcr = window.SIGAS_CPF_OCR;
    const qs = (selector, base = document) => base.querySelector(selector);
    const escapeHTML = (value) => String(value ?? "").replaceAll("&", "&amp;").replaceAll("<", "&lt;").replaceAll(">", "&gt;").replaceAll('"', "&quot;").replaceAll("'", "&#039;");
    const digits = (value) => String(value || "").replace(/\D+/g, "");
    const modal = (selector) => bootstrap.Modal.getOrCreateInstance(qs(selector));
    const ids = ["#consultaResult", "#manualCpfForm", "#manualCpf", "#scannerVideo", "#scannerPreview", "#cameraPlaceholder", "#openCameraButton", "#captureDocumentButton", "#chooseImageButton", "#retryOcrButton", "#cancelOcrButton", "#documentImageInput", "#ocrStatus", "#ocrCandidates", "#cpfScanRegion", "[data-ocr-progress-bar]", "[data-ocr-progress]", "[data-ocr-title]", "[data-ocr-message]", "#continueOcrButton", "#zoomInButton", "#zoomOutButton", "#moveImageUpButton", "#moveImageDownButton"];
    const [result, form, cpfInput, video, preview, placeholder, openCamera, capture, chooseImage, retry, cancelOcr, fileInput, ocrStatus, ocrCandidates, scanRegion, progressBar, progressText, progressTitle, progressMessage, continueOcr, zoomIn, zoomOut, moveImageUp, moveImageDown] = ids.map((id) => qs(id));
    let currentCpf = "", currentData = null, controller = null, stream = null, objectUrl = "", selectedFile = null, ocrCandidateStore = [];
    let consultRunId = 0, lastResultModalRegistrationId = null;
    let ocrState = "idle", ocrRunId = 0, ocrWorker = null, ocrWorkerPromise = null, workerReady = false, lastConsultedCpf = "";
    let liveScanRunning = false, liveScanBusy = false, liveScanAttempt = 0, lastScanFinishedAt = 0, lowConfidenceCpf = "", lowConfidenceHits = 0, scanTimer = 0;
    let cameraZoomTrack = null, cameraZoom = null, imageZoom = 1, imageOffsetY = 0, dragStartY = null, dragStartOffset = 0;
    const maskCpf = (value) => {
        const numbers = digits(value).slice(0, 11);
        if (numbers.length <= 3) return numbers;
        if (numbers.length <= 6) return `${numbers.slice(0, 3)}.${numbers.slice(3)}`;
        if (numbers.length <= 9) return `${numbers.slice(0, 3)}.${numbers.slice(3, 6)}.${numbers.slice(6)}`;
        return `${numbers.slice(0, 3)}.${numbers.slice(3, 6)}.${numbers.slice(6, 9)}-${numbers.slice(9)}`;
    };
    const fullCpf = (data = {}) => {
        const value = data.cpf_completo || data.cpf_formatted || data.cpf_formatado || data.cpf || data.cpf_mascarado || data.cpf_masked || "";
        const numbers = digits(value);
        return numbers.length === 11 && !String(value).includes("*") ? maskCpf(numbers) : (value || "Não informado");
    };
    const panel = (icon, title, text, tone = "info") => `<div class="alert-soft ${tone}"><i class="bi bi-${icon}"></i><div><strong>${escapeHTML(title)}</strong><br><span>${escapeHTML(text)}</span></div></div>`;
    const button = (attrs, icon, text, cls = "btn btn-primary") => `<button class="${cls}" type="button" ${attrs}><i class="bi bi-${icon}"></i>${escapeHTML(text)}</button>`;
    const resetResultCardClass = () => { result.className = "content-card scan-result-empty"; };
    const setLoading = () => { result.hidden = false; resetResultCardClass(); result.innerHTML = `<div class="state-panel show"><i class="bi bi-hourglass-split"></i><h2>Consultando</h2><p>Buscando CPF no SIGAS.</p></div>`; };
    const renderNotFound = (data) => {
        resetResultCardClass();
        const action = permissions.create ? `<a class="btn btn-primary" href="modulo.php?action=new&cpf=${encodeURIComponent(currentCpf)}"><i class="bi bi-plus-lg"></i>Iniciar cadastro no Comida na Mesa</a>` : "";
        if (data?.anexo?.found) {
            result.innerHTML = `${panel("person-check", "Pessoa localizada no ANEXO, mas ainda não inscrita no Comida na Mesa.", data.anexo.person?.name || "CPF localizado no ANEXO.", "info")}<div class="result-actions mt-3">${action}${button("data-reset-consulta", "arrow-repeat", "Consultar outra pessoa", "btn btn-light")}</div>`;
            return;
        }
        result.innerHTML = `${panel("search", "Pessoa não localizada no SIGAS", "Nenhuma pessoa foi encontrada para o CPF informado.", "warning")}<div class="result-actions mt-3">${action}${button("data-reset-consulta", "arrow-repeat", "Consultar outra pessoa", "btn btn-light")}</div>`;
    };
    const renderPersonWithoutRegistration = (data) => {
        resetResultCardClass();
        const action = permissions.create ? `<a class="btn btn-primary" href="modulo.php?action=new&cpf=${encodeURIComponent(currentCpf)}"><i class="bi bi-plus-lg"></i>Criar inscrição</a>` : "";
        result.innerHTML = `<div class="result-status-header"><span class="result-avatar">${escapeHTML((data.person?.name || "P").slice(0, 2).toUpperCase())}</span><div><span class="result-overline">Pessoa localizada sem inscrição</span><h2>${escapeHTML(data.person?.name)}</h2><p>CPF: ${escapeHTML(fullCpf(data.person || data))}</p></div><span class="status-badge status-warning"><i class="bi bi-hourglass"></i>Sem inscrição</span></div><div class="verification-grid"><div><span>Vínculo familiar</span><strong>${escapeHTML(data.person?.vinculo_familiar || "sem_familia")}</strong></div><div><span>Família</span><strong>${escapeHTML(data.family?.code || "Sem família")}</strong></div></div><div class="result-actions">${action}${button("data-reset-consulta", "arrow-repeat", "Consultar outra pessoa", "btn btn-light")}</div>`;
    };
    const initials = (name) => {
        const parts = String(name || "").trim().split(/\s+/).filter(Boolean);
        return (parts.length ? parts.slice(0, 2).map((part) => part[0]).join("") : "P").toUpperCase();
    };
    const statusMeta = (data) => {
        if (!data.competence) return { className: "status-neutral", icon: "calendar-x", text: "Sem competência" };
        const status = String(data.delivery?.status || data.delivery?.status_label || "").toLowerCase();
        if (status.includes("receb")) return { className: "status-success", icon: "check-circle", text: "Recebida" };
        if (status.includes("cancel")) return { className: "status-warning", icon: "arrow-counterclockwise", text: "Entrega cancelada" };
        if (status.includes("bloque")) return { className: "status-danger", icon: "lock", text: "Bloqueada" };
        if (status.includes("aguard")) return { className: "status-info", icon: "clock", text: "Aguardando retirada" };
        return { className: "status-info", icon: "clock", text: data.delivery?.status_label || "Aguardando retirada" };
    };
    const ruleTone = (viewModel) => {
        if (viewModel.canDelivery) return "success";
        const reason = String(viewModel.eligibility.reason || "").toLowerCase();
        if (reason.includes("bloque") || reason.includes("suspens") || reason.includes("não apt") || reason.includes("nao apt")) return "danger";
        if (reason) return "warning";
        return "info";
    };
    const registeredViewModel = (data, runId) => {
        const eligibility = data.eligibility || {};
        const canDelivery = permissions.deliver && eligibility.allowed && ["register", "reactivate"].includes(eligibility.action);
        const canCancel = permissions.cancelDelivery && eligibility.action === "cancel";
        const status = statusMeta(data);
        return {
            data,
            runId,
            registrationId: data.registration?.id || "",
            name: data.person?.name || "Pessoa inscrita",
            cpf: fullCpf(data.person || data),
            familyCode: data.registration?.family_code || data.family?.code || "Sem família",
            status,
            eligibility,
            canDelivery,
            canCancel,
            primaryText: eligibility.action === "reactivate" ? "Reativar entrega" : "Registrar entrega",
            fields: [
                { icon: "check-circle", label: "Situação", value: data.registration?.status_label || "Não informado", span: 2 },
                { icon: "exclamation-diamond", label: "Prioridade", value: data.registration?.priority || "Não informado", span: 2 },
                { icon: "geo-alt", label: "Polo", value: data.registration?.pole || "Sem polo", span: 2 },
                { icon: "calendar3", label: "Competência", value: data.competence?.label || "Sem competência", span: 3 },
                { icon: "basket2", label: "Entrega", value: data.delivery?.status_label || status.text || "Não informado", span: 3 },
            ],
        };
    };
    const operationalCard = (icon, label, value, modifier = "") => `<div class="consulta-operational-card ${modifier}"><span class="consulta-operational-icon"><i class="bi bi-${icon}"></i></span><div><span class="consulta-operational-label">${escapeHTML(label)}</span><strong class="consulta-operational-value">${escapeHTML(value)}</strong></div></div>`;
    const renderRegisteredCompact = (viewModel) => {
        result.hidden = false;
        result.className = "content-card consulta-compact-result";
        result.innerHTML = `<div class="consulta-compact-head"><span class="consulta-compact-avatar">${escapeHTML(initials(viewModel.name))}</span><div><span>Pessoa inscrita</span><strong>${escapeHTML(viewModel.name)}</strong><small>CPF ${escapeHTML(viewModel.cpf)}</small></div></div><div class="consulta-compact-status"><span class="status-badge ${viewModel.status.className}"><i class="bi bi-${viewModel.status.icon}"></i>${escapeHTML(viewModel.status.text)}</span></div><button class="btn btn-primary w-100" type="button" data-open-consulta-result><i class="bi bi-eye"></i>Abrir resumo operacional</button><button class="btn btn-light w-100" type="button" data-reset-consulta><i class="bi bi-arrow-repeat"></i>Consultar outra pessoa</button>`;
    };
    const renderRulePanel = (viewModel) => {
        const text = viewModel.eligibility.reason || "Beneficiário apto para receber a cesta desta competência.";
        const tone = ruleTone(viewModel);
        return `<section class="consulta-rule-panel consulta-rule-panel--${tone}"><span class="consulta-rule-icon"><i class="bi bi-info-circle"></i></span><div><strong>Regra operacional</strong><p>${escapeHTML(text)}</p></div></section>`;
    };
    const registeredActions = (viewModel) => {
        const editAction = permissions.edit && viewModel.registrationId
            ? `<a class="btn btn-light" href="modulo.php?action=edit&id=${encodeURIComponent(viewModel.registrationId)}"><i class="bi bi-pencil"></i>Editar</a>`
            : "";
        const detailAction = viewModel.registrationId
            ? button(`data-open-detail data-registration-id="${escapeHTML(viewModel.registrationId)}"`, "eye", "Visualizar dados completos", "btn btn-light")
            : "";
        const competenceAction = !viewModel.data.competence && permissions.manageCompetences
            ? '<a class="btn btn-light" href="modulo.php?action=competence"><i class="bi bi-calendar-plus"></i>Gerenciar competência</a>'
            : "";
        const deliveryAction = viewModel.canDelivery ? button("data-open-delivery", "basket2", viewModel.primaryText, "btn btn-primary") : "";
        const cancelAction = viewModel.canCancel ? button("data-open-cancel", "x-circle", "Cancelar entrega", "btn btn-danger") : "";
        return `<div class="consulta-result-footer-secondary">${detailAction}${editAction}${competenceAction}</div><div class="consulta-result-footer-primary">${button("data-reset-consulta", "arrow-repeat", "Consultar outra pessoa", "btn btn-light")}${deliveryAction}${cancelAction}</div>`;
    };
    const renderRegisteredModal = (viewModel) => {
        const body = qs("[data-consulta-result-modal-body]");
        const footer = qs("[data-consulta-result-modal-footer]");
        body.innerHTML = `<div class="consulta-beneficiary-layout"><section class="consulta-beneficiary-hero"><div class="consulta-beneficiary-main"><span class="consulta-beneficiary-avatar">${escapeHTML(initials(viewModel.name))}</span><div class="consulta-beneficiary-copy"><span class="consulta-beneficiary-overline">Pessoa inscrita</span><h3 class="consulta-beneficiary-name">${escapeHTML(viewModel.name)}</h3><div class="consulta-beneficiary-meta"><span><i class="bi bi-person-vcard"></i>CPF ${escapeHTML(viewModel.cpf)}</span><span><i class="bi bi-people"></i>Família ${escapeHTML(viewModel.familyCode)}</span></div></div></div><span class="status-badge ${viewModel.status.className}"><i class="bi bi-${viewModel.status.icon}"></i>${escapeHTML(viewModel.status.text)}</span></section><section class="consulta-operational-section"><h3 class="consulta-section-title">Dados operacionais</h3><div class="consulta-operational-grid">${viewModel.fields.map((field) => operationalCard(field.icon, field.label, field.value, `consulta-operational-card--span-${field.span}`)).join("")}</div></section>${renderRulePanel(viewModel)}</div>`;
        footer.innerHTML = registeredActions(viewModel);
    };
    const openRegisteredModal = (viewModel) => {
        const key = `${viewModel.runId}:${viewModel.registrationId || viewModel.cpf}`;
        if (lastResultModalRegistrationId === key) return;
        lastResultModalRegistrationId = key;
        window.requestAnimationFrame(() => modal("#consultaResultModal").show());
    };
    const renderRegistered = (data, runId) => {
        const viewModel = registeredViewModel(data, runId);
        renderRegisteredCompact(viewModel);
        renderRegisteredModal(viewModel);
        openRegisteredModal(viewModel);
    };
    const renderData = (data, runId) => { currentData = data; if (data.state === "nao_localizado") renderNotFound(data); else if (data.state === "pessoa_sem_inscricao") renderPersonWithoutRegistration(data); else renderRegistered(data, runId); };
    const consult = async () => {
        const runId = ++consultRunId;
        currentCpf = digits(cpfInput.value);
        if (currentCpf.length !== 11) { cpfInput.classList.add("is-invalid"); return; }
        cpfInput.classList.remove("is-invalid");
        if (controller) controller.abort();
        controller = new AbortController();
        const current = controller, startedAt = performance.now();
        setLoading();
        const submit = qs('[type="submit"]', form);
        submit.disabled = true;
        try {
            const response = await fetch(form.action, { method: "POST", body: new FormData(form), headers: { Accept: "application/json" }, signal: current.signal });
            const data = await response.json();
            if (current !== controller) return;
            root.dataset.lastHttpMs = String(Math.round(performance.now() - startedAt));
            if (!response.ok || !data.ok) { resetResultCardClass(); result.innerHTML = panel("exclamation-triangle", "Consulta não realizada", data.error || "Revise o CPF informado.", "warning"); return; }
            renderData(data, runId);
        } catch (error) {
            if (error.name !== "AbortError") { resetResultCardClass(); result.innerHTML = panel("wifi-off", "Erro de comunicação", "Não foi possível consultar agora.", "warning"); }
        } finally {
            if (current === controller) submit.disabled = false;
        }
    };
    const setAlert = (formEl, html = "") => { const alert = qs("[data-form-alert]", formEl); if (alert) alert.innerHTML = html; };
    const submitAction = async (formEl) => {
        setAlert(formEl, panel("hourglass-split", "Processando", "Aguarde a confirmação do servidor."));
        const submit = qs('[type="submit"]', formEl); submit.disabled = true;
        try {
            const response = await fetch(formEl.action, { method: "POST", body: new FormData(formEl), headers: { Accept: "application/json" } });
            const data = await response.json();
            if (!response.ok || !data.ok) { setAlert(formEl, panel("exclamation-triangle", "Operação não concluída", data.error || "Revise os dados.", "warning")); await consult(); return false; }
            const ownerModal = formEl.closest(".modal");
            if (ownerModal?.classList.contains("show")) {
                await new Promise((resolve) => {
                    ownerModal.addEventListener("hidden.bs.modal", resolve, { once: true });
                    bootstrap.Modal.getOrCreateInstance(ownerModal).hide();
                });
            }
            await consult(); return true;
        } catch (error) {
            setAlert(formEl, panel("wifi-off", "Erro de comunicação", "Não foi possível concluir.", "warning")); return false;
        } finally { submit.disabled = false; }
    };
    const switchModal = (currentSelector, nextCallback) => {
        const currentElement = qs(currentSelector);
        const currentModal = modal(currentSelector);
        if (!currentElement.classList.contains("show")) {
            nextCallback();
            return;
        }
        currentElement.addEventListener("hidden.bs.modal", nextCallback, { once: true });
        currentModal.hide();
    };
    const setProgress = (title, percent, message) => {
        const value = Math.max(0, Math.min(100, Math.round(percent)));
        ocrStatus.hidden = false; progressTitle.textContent = title; progressText.textContent = `${value}%`; progressBar.style.width = `${value}%`; progressBar.parentElement.setAttribute("aria-valuenow", String(value)); progressMessage.textContent = message;
    };
    const updateButtons = () => {
        const cameraOpen = !!stream, busy = liveScanRunning || ocrState === "recognizing";
        openCamera.hidden = cameraOpen; capture.hidden = !cameraOpen && !selectedFile; capture.disabled = busy && !cameraOpen;
        cancelOcr.hidden = !liveScanRunning; retry.hidden = !cameraOpen && !selectedFile; continueOcr.hidden = true;
        moveImageUp.hidden = !selectedFile; moveImageDown.hidden = !selectedFile;
        zoomIn.hidden = !selectedFile && !cameraZoom; zoomOut.hidden = !selectedFile && !cameraZoom;
        scanRegion.hidden = !cameraOpen && !selectedFile; chooseImage.disabled = busy;
    };
    const resetCapture = () => {
        clearTimeout(scanTimer); liveScanRunning = false; liveScanBusy = false; liveScanAttempt = 0; lowConfidenceCpf = ""; lowConfidenceHits = 0;
        ocrCandidateStore = [];
        ocrCandidates.hidden = true; ocrCandidates.innerHTML = ""; ocrStatus.classList.remove("ocr-result-success", "ocr-result-warning");
    };
    const revokePreviewUrl = () => { if (objectUrl) URL.revokeObjectURL(objectUrl); objectUrl = ""; };
    const stopCamera = () => { stream?.getTracks().forEach((track) => track.stop()); stream = null; cameraZoomTrack = null; cameraZoom = null; video.srcObject = null; video.hidden = true; updateButtons(); };
    const resetInterface = () => { ocrRunId += 1; resetCapture(); stopCamera(); revokePreviewUrl(); selectedFile = null; fileInput.value = ""; preview.removeAttribute("src"); preview.hidden = true; placeholder.hidden = false; scanRegion.hidden = true; imageZoom = 1; imageOffsetY = 0; updatePreviewTransform(); updateButtons(); };
    const destroyOcrWorker = async () => { const worker = ocrWorker; ocrWorker = null; ocrWorkerPromise = null; workerReady = false; if (worker) await worker.terminate(); };
    const getOcrWorker = async () => {
        if (!window.Tesseract?.createWorker) throw new Error("ocr_unavailable");
        if (!ocrWorkerPromise) {
            const startedAt = performance.now();
            ocrWorkerPromise = window.Tesseract.createWorker("eng", { logger: (message) => {
                if (workerReady || ocrState === "recognizing") return;
                const percent = message.progress ? message.progress * 100 : 5;
                const titles = { "loading tesseract core": "Preparando leitor", "loading language traineddata": "Carregando reconhecimento", "initializing api": "Preparando leitor" };
                setProgress(titles[message.status] || "Preparando leitor", percent, "O leitor será usado em segundo plano.");
            }}).then(async (worker) => {
                ocrWorker = worker;
                await worker.setParameters({ tessedit_pageseg_mode: "7", preserve_interword_spaces: "1", tessedit_char_whitelist: "0123456789.- ", classify_bln_numeric_mode: "1" });
                workerReady = true; root.dataset.workerReadyMs = String(Math.round(performance.now() - startedAt));
                setProgress("Leitor pronto", 100, "Aproxime a linha do CPF da faixa destacada."); return worker;
            });
        }
        return ocrWorkerPromise;
    };
    const warmOcrWorker = () => { const warm = () => { getOcrWorker().catch(() => setProgress("Leitura indisponível", 100, "Digite o CPF manualmente ou tente carregar a página novamente.")); }; window.requestIdleCallback ? window.requestIdleCallback(warm, { timeout: 800 }) : setTimeout(warm, 100); };
    const outputSize = (sourceWidth, sourceHeight) => {
        const width = Math.min(1280, Math.max(960, sourceWidth));
        const height = Math.max(160, Math.min(300, Math.round(width * sourceHeight / sourceWidth)));
        return { width, height };
    };
    const captureCpfRegionFromVideo = (videoEl) => {
        const sourceX = Math.round(videoEl.videoWidth * CPF_ROI.x);
        const sourceY = Math.round(videoEl.videoHeight * CPF_ROI.y);
        const sourceWidth = Math.round(videoEl.videoWidth * CPF_ROI.width);
        const sourceHeight = Math.round(videoEl.videoHeight * CPF_ROI.height);
        const size = outputSize(sourceWidth, sourceHeight);
        const canvas = document.createElement("canvas");
        canvas.width = size.width; canvas.height = size.height;
        canvas.getContext("2d").drawImage(videoEl, sourceX, sourceY, sourceWidth, sourceHeight, 0, 0, size.width, size.height);
        return canvas;
    };
    const captureCpfRegionFromImage = () => {
        const frame = qs("#cameraFrame"), rect = frame.getBoundingClientRect();
        const naturalWidth = preview.naturalWidth, naturalHeight = preview.naturalHeight;
        const baseScale = Math.min(rect.width / naturalWidth, rect.height / naturalHeight) * imageZoom;
        const displayedWidth = naturalWidth * baseScale, displayedHeight = naturalHeight * baseScale;
        const imageLeft = (rect.width - displayedWidth) / 2, imageTop = (rect.height - displayedHeight) / 2 + imageOffsetY;
        const roiX = rect.width * CPF_ROI.x, roiY = rect.height * CPF_ROI.y, roiW = rect.width * CPF_ROI.width, roiH = rect.height * CPF_ROI.height;
        const sourceX = Math.max(0, Math.round((roiX - imageLeft) / baseScale));
        const sourceY = Math.max(0, Math.round((roiY - imageTop) / baseScale));
        const sourceWidth = Math.min(naturalWidth - sourceX, Math.round(roiW / baseScale));
        const sourceHeight = Math.min(naturalHeight - sourceY, Math.round(roiH / baseScale));
        const size = outputSize(sourceWidth, sourceHeight);
        const canvas = document.createElement("canvas");
        canvas.width = size.width; canvas.height = size.height;
        canvas.getContext("2d").drawImage(preview, sourceX, sourceY, sourceWidth, sourceHeight, 0, 0, size.width, size.height);
        return canvas;
    };
    const buildFastCpfCanvas = (sourceCanvas, threshold = false) => {
        const canvas = document.createElement("canvas");
        canvas.width = sourceCanvas.width; canvas.height = sourceCanvas.height;
        const ctx2d = canvas.getContext("2d", { willReadFrequently: true });
        ctx2d.filter = threshold ? "grayscale(1) contrast(2.1)" : "grayscale(1) contrast(1.8)";
        ctx2d.drawImage(sourceCanvas, 0, 0);
        if (threshold || ctx2d.filter === "none") {
            const data = ctx2d.getImageData(0, 0, canvas.width, canvas.height);
            for (let index = 0; index < data.data.length; index += 4) {
                const gray = data.data[index] * 0.299 + data.data[index + 1] * 0.587 + data.data[index + 2] * 0.114;
                const value = threshold ? (gray > 142 ? 255 : 0) : Math.max(0, Math.min(255, (gray - 128) * 1.8 + 128));
                data.data[index] = value; data.data[index + 1] = value; data.data[index + 2] = value;
            }
            ctx2d.putImageData(data, 0, 0);
        }
        return canvas;
    };
    const clearCanvas = (canvas) => { if (canvas) { canvas.width = 0; canvas.height = 0; } };
    const recognizeCpfRegion = async (sourceCanvas, useThreshold, runId) => {
        const worker = await getOcrWorker();
        if (runId !== ocrRunId) return null;
        const fastCanvas = buildFastCpfCanvas(sourceCanvas, useThreshold), startedAt = performance.now();
        try {
            ocrState = "recognizing"; setProgress("Procurando CPF", 60, "Analisando somente a faixa destacada.");
            const response = await worker.recognize(fastCanvas);
            root.dataset.lastOcrMs = String(Math.round(performance.now() - startedAt));
            const candidates = cpfOcr.extractCpfFromNumericRegion(response?.data?.text || "");
            return { candidates, confidence: Number(response?.data?.confidence || 0) };
        } finally { clearCanvas(fastCanvas); }
    };
    const acceptCandidate = async (candidate, confidence, runId) => {
        if (!candidate || runId !== ocrRunId) return false;
        if (confidence < 60) {
            if (lowConfidenceCpf === candidate.cpf) lowConfidenceHits += 1; else { lowConfidenceCpf = candidate.cpf; lowConfidenceHits = 1; }
            if (lowConfidenceHits < 2) return false;
        }
        liveScanRunning = false; clearTimeout(scanTimer); stopCamera();
        cpfInput.value = maskCpf(candidate.cpf); cpfInput.classList.remove("is-invalid");
        ocrStatus.classList.add("ocr-result-success"); setProgress("CPF identificado", 100, candidate.formatted);
        if (lastConsultedCpf !== candidate.cpf) { lastConsultedCpf = candidate.cpf; await consult(); }
        return true;
    };
    const renderMultipleCandidates = (candidates) => {
        liveScanRunning = false; updateButtons(); setProgress("Mais de um CPF encontrado", 100, "Selecione o número do titular do documento.");
        ocrCandidateStore = candidates;
        ocrCandidates.hidden = false; ocrCandidates.innerHTML = `<h3>Foram encontrados mais de um CPF.</h3><p>Selecione o número que pertence ao titular do documento.</p><div class="ocr-candidate-actions">${candidates.map((item, index) => button(`data-ocr-index="${index}"`, "person-vcard", item.formatted, "btn btn-light")).join("")}</div>`;
    };
    const scheduleNextScan = (runId) => {
        if (!liveScanRunning || runId !== ocrRunId) return;
        if (liveScanAttempt >= 12) {
            liveScanRunning = false; continueOcr.hidden = false; cancelOcr.hidden = true; setProgress("CPF ainda não identificado", 100, "Aproxime mais a câmera ou digite o número manualmente."); return;
        }
        scanTimer = setTimeout(() => runLiveScanAttempt(runId), 250);
    };
    async function runLiveScanAttempt(runId) {
        if (liveScanBusy || !liveScanRunning || runId !== ocrRunId || !video.videoWidth) return;
        liveScanBusy = true; liveScanAttempt += 1;
        const sourceCanvas = captureCpfRegionFromVideo(video);
        try {
            const response = await recognizeCpfRegion(sourceCanvas, liveScanAttempt > 6 && liveScanAttempt % 2 === 0, runId);
            lastScanFinishedAt = performance.now();
            if (!response || runId !== ocrRunId) return;
            if (response.candidates.length === 1 && await acceptCandidate(response.candidates[0], response.confidence, runId)) return;
            if (response.candidates.length > 1) { renderMultipleCandidates(response.candidates); return; }
            setProgress("Procurando CPF", Math.min(95, 20 + liveScanAttempt * 6), "Mantenha os 11 números dentro da faixa.");
        } catch (error) {
            setProgress("Leitura em andamento", 35, "Ajuste foco, distância ou iluminação.");
        } finally {
            clearCanvas(sourceCanvas); liveScanBusy = false; scheduleNextScan(runId);
        }
    }
    const startLiveCpfScanner = async () => {
        if (!stream || liveScanRunning) return;
        const runId = ocrRunId + 1; ocrRunId = runId; liveScanRunning = true; liveScanAttempt = 0; lowConfidenceCpf = ""; lowConfidenceHits = 0; updateButtons();
        setProgress(workerReady ? "Procurando CPF" : "Preparando leitor", 10, "Aproxime a linha do CPF da faixa destacada.");
        try { await getOcrWorker(); runLiveScanAttempt(runId); } catch (error) { liveScanRunning = false; updateButtons(); setProgress("Leitura indisponível", 100, "Digite o CPF manualmente ou tente outra imagem."); }
    };
    const runSingleScan = async () => {
        if (liveScanBusy) return;
        const runId = ocrRunId + 1; ocrRunId = runId; resetCapture(); setProgress("Procurando CPF", 20, "Analisando somente a faixa destacada.");
        const sourceCanvas = selectedFile ? captureCpfRegionFromImage() : captureCpfRegionFromVideo(video);
        try {
            const response = await recognizeCpfRegion(sourceCanvas, false, runId);
            if (!response || runId !== ocrRunId) return;
            if (response.candidates.length === 1) await acceptCandidate(response.candidates[0], response.confidence, runId);
            else if (response.candidates.length > 1) renderMultipleCandidates(response.candidates);
            else { ocrStatus.classList.add("ocr-result-warning"); setProgress("CPF não identificado", 100, "Reposicione a linha do CPF ou digite o número manualmente."); }
        } catch (error) {
            setProgress("Leitura indisponível", 100, "Não foi possível ler agora. Digite manualmente ou tente novamente.");
        } finally { clearCanvas(sourceCanvas); updateButtons(); }
    };
    const configureCameraTrack = async () => {
        const track = stream?.getVideoTracks()[0]; if (!track) return;
        const capabilities = track.getCapabilities?.() || {};
        try {
            const advanced = [];
            if (Array.isArray(capabilities.focusMode) && capabilities.focusMode.includes("continuous")) advanced.push({ focusMode: "continuous" });
            if (Array.isArray(capabilities.exposureMode) && capabilities.exposureMode.includes("continuous")) advanced.push({ exposureMode: "continuous" });
            if (advanced.length) await track.applyConstraints({ advanced });
        } catch (error) {}
        if (capabilities.zoom) {
            cameraZoomTrack = track; cameraZoom = { min: capabilities.zoom.min ?? 1, max: capabilities.zoom.max ?? 1, step: capabilities.zoom.step ?? 0.1, value: Math.min(capabilities.zoom.max ?? 1, Math.max(capabilities.zoom.min ?? 1, 1.35)) };
            await applyCameraZoom(cameraZoom.value);
        }
    };
    const applyCameraZoom = async (value) => {
        if (!cameraZoomTrack || !cameraZoom) return;
        cameraZoom.value = Math.max(cameraZoom.min, Math.min(cameraZoom.max, value));
        try { await cameraZoomTrack.applyConstraints({ advanced: [{ zoom: cameraZoom.value }] }); } catch (error) {}
        updateButtons();
    };
    const cameraMessage = (error) => {
        if (!navigator.mediaDevices?.getUserMedia) return "Este navegador não oferece acesso à câmera. Escolha uma imagem do documento.";
        if (!window.isSecureContext) return "O acesso à câmera exige HTTPS. Escolha uma imagem do documento ou digite o CPF manualmente.";
        if (error?.name === "NotAllowedError" || error?.name === "SecurityError") return "A permissão da câmera foi negada. Escolha uma imagem ou digite o CPF.";
        if (error?.name === "NotFoundError" || error?.name === "OverconstrainedError") return "Nenhuma câmera compatível foi encontrada. Escolha uma imagem do documento.";
        if (error?.name === "NotReadableError" || error?.name === "AbortError") return "A câmera está ocupada ou indisponível. Escolha uma imagem do documento.";
        return "Não foi possível abrir a câmera. Escolha uma imagem do documento.";
    };
    const startCamera = async () => {
        resetInterface();
        if (!navigator.mediaDevices?.getUserMedia || !window.isSecureContext) { result.innerHTML = panel("camera-video-off", "Use uma imagem do documento", cameraMessage(), "warning"); fileInput.click(); return; }
        try { stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: { ideal: "environment" }, width: { ideal: 1920 }, height: { ideal: 1080 } }, audio: false }); }
        catch (error) {
            try { stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: "environment" }, audio: false }); }
            catch (fallbackError) { result.innerHTML = panel("camera-video-off", "Use uma imagem do documento", cameraMessage(fallbackError), "warning"); fileInput.click(); return; }
        }
        video.srcObject = stream; await video.play(); await configureCameraTrack();
        video.hidden = false; preview.hidden = true; placeholder.hidden = true; updateButtons(); startLiveCpfScanner();
    };
    const updatePreviewTransform = () => { preview.style.transform = `translateY(${imageOffsetY}px) scale(${imageZoom})`; };
    const handleFileSelection = () => {
        const file = fileInput.files?.[0]; if (!file) return;
        if (!["image/jpeg", "image/png", "image/webp"].includes(file.type) || file.size > 15 * 1024 * 1024) { fileInput.value = ""; result.innerHTML = panel("image", "Imagem não aceita", "Use JPG, PNG ou WebP com até 15 MB.", "warning"); return; }
        resetInterface(); selectedFile = file; objectUrl = URL.createObjectURL(file); preview.src = objectUrl; preview.hidden = false; placeholder.hidden = true; imageZoom = 1; imageOffsetY = 0; updatePreviewTransform(); setProgress("Imagem pronta", 0, "Ajuste a foto atrás da faixa e clique em Ler CPF."); updateButtons();
    };
    form.addEventListener("submit", (event) => { event.preventDefault(); consult(); });
    cpfInput.addEventListener("input", () => { cpfInput.value = maskCpf(cpfInput.value); cpfInput.classList.remove("is-invalid"); });
    openCamera.addEventListener("click", startCamera);
    chooseImage.addEventListener("click", () => fileInput.click());
    retry.addEventListener("click", startCamera);
    capture.addEventListener("click", runSingleScan);
    cancelOcr.addEventListener("click", () => { ocrRunId += 1; clearTimeout(scanTimer); liveScanRunning = false; liveScanBusy = false; setProgress("Leitura parada", 0, "Leitor preparado. Reposicione ou digite manualmente."); updateButtons(); });
    continueOcr.addEventListener("click", startLiveCpfScanner);
    fileInput.addEventListener("change", handleFileSelection);
    zoomIn.addEventListener("click", () => selectedFile ? (imageZoom = Math.min(2.4, imageZoom + 0.15), updatePreviewTransform()) : applyCameraZoom((cameraZoom?.value || 1) + (cameraZoom?.step || 0.1)));
    zoomOut.addEventListener("click", () => selectedFile ? (imageZoom = Math.max(0.8, imageZoom - 0.15), updatePreviewTransform()) : applyCameraZoom((cameraZoom?.value || 1) - (cameraZoom?.step || 0.1)));
    moveImageUp.addEventListener("click", () => { imageOffsetY -= 18; updatePreviewTransform(); });
    moveImageDown.addEventListener("click", () => { imageOffsetY += 18; updatePreviewTransform(); });
    qs("#cameraFrame").addEventListener("pointerdown", (event) => { if (!selectedFile) return; dragStartY = event.clientY; dragStartOffset = imageOffsetY; });
    qs("#cameraFrame").addEventListener("pointermove", (event) => { if (dragStartY === null) return; imageOffsetY = dragStartOffset + event.clientY - dragStartY; updatePreviewTransform(); });
    ["pointerup", "pointercancel", "pointerleave"].forEach((type) => qs("#cameraFrame").addEventListener(type, () => { dragStartY = null; }));
    document.addEventListener("click", async (event) => {
        const selectedCpf = event.target.closest("[data-ocr-index]");
        if (selectedCpf) {
            const item = ocrCandidateStore[Number(selectedCpf.dataset.ocrIndex)];
            if (item?.cpf) {
                cpfInput.value = maskCpf(item.cpf);
                ocrCandidates.hidden = true;
                await consult();
            }
            return;
        }
        if (event.target.closest("[data-open-consulta-result]") && currentData) {
            modal("#consultaResultModal").show();
            return;
        }
        if (event.target.closest("[data-reset-consulta]")) {
            const resetConsulta = () => {
                currentCpf = "";
                currentData = null;
                lastResultModalRegistrationId = null;
                cpfInput.value = "";
                resetResultCardClass();
                result.innerHTML = '<span class="result-empty-icon"><i class="bi bi-person-lines-fill"></i></span><h2>Resultado da consulta</h2><p>Informe o CPF para visualizar pessoa, família, inscrição, competência e situação da entrega.</p>';
                qs("[data-consulta-result-modal-body]").innerHTML = "";
                qs("[data-consulta-result-modal-footer]").innerHTML = "";
                resetInterface();
                setTimeout(() => (cpfInput || openCamera).focus(), 0);
            };
            switchModal("#consultaResultModal", resetConsulta);
            return;
        }
        if (event.target.closest("[data-open-delivery]") && currentData) {
            switchModal("#consultaResultModal", () => {
                const formEl = qs("#consultaDeliveryForm"); formEl.reset(); setAlert(formEl, "");
                qs('[name="inscricao_id"]', formEl).value = currentData.registration.id; qs('[name="competencia_id"]', formEl).value = currentData.competence?.id || "";
                qs('[name="recebedor_nome"]', formEl).value = currentData.person?.name || ""; qs('[name="recebedor_cpf"]', formEl).value = maskCpf(currentCpf);
                qs("[data-delivery-name]", formEl).textContent = currentData.person?.name || ""; qs("[data-delivery-family]", formEl).textContent = currentData.registration?.family_code || "";
                qs("[data-delivery-competence]", formEl).textContent = currentData.competence?.label || ""; qs("[data-delivery-pole]", formEl).textContent = currentData.registration?.pole || ""; modal("#consultaDeliveryModal").show();
            });
            return;
        }
        if (event.target.closest("[data-open-cancel]") && currentData) {
            switchModal("#consultaResultModal", () => { const formEl = qs("#consultaCancelForm"); formEl.reset(); setAlert(formEl, ""); qs('[name="inscricao_id"]', formEl).value = currentData.registration.id; qs('[name="competencia_id"]', formEl).value = currentData.competence?.id || ""; modal("#consultaCancelModal").show(); });
            return;
        }
        const detail = event.target.closest("[data-open-detail]");
        if (detail) {
            const registrationId = detail.dataset.registrationId;
            switchModal("#consultaResultModal", async () => {
                const content = qs("[data-detail-content]"); content.innerHTML = panel("hourglass-split", "Carregando", "Buscando detalhes."); modal("#consultaDetailModal").show();
                const response = await fetch(`api/comida-mesa/detalhar.php?id=${encodeURIComponent(registrationId)}`, { headers: { Accept: "application/json" } });
                const payload = await response.json();
                if (!response.ok || !payload.ok) { content.innerHTML = panel("exclamation-triangle", "Detalhes indisponíveis", payload.error || "Tente novamente.", "warning"); return; }
                const data = payload.data;
                const deliveries = (data.entregas || []).map((item) => `<tr><td>${escapeHTML(String(item.mes).padStart(2, "0"))}/${escapeHTML(item.ano)}</td><td>${escapeHTML(item.status)}</td><td>${escapeHTML(item.entregue_em || "")}</td><td>${escapeHTML(item.recebedor_nome || "")}</td><td>${escapeHTML(item.motivo_cancelamento || "")}</td></tr>`).join("");
                const documents = (data.documentos || []).map((item) => `<li class="list-group-item d-flex justify-content-between"><span>${escapeHTML(item.tipo)}<br><small>${escapeHTML(item.nome_original)}</small></span><a class="btn btn-light btn-sm" target="_blank" rel="noopener" href="api/comida-mesa/visualizar-documento.php?id=${encodeURIComponent(item.id)}">Abrir</a></li>`).join("");
                const history = (data.historico || []).map((item) => `<li class="list-group-item"><strong>${escapeHTML(item.acao)}</strong><br><span>${escapeHTML(item.descricao || "")}</span><br><small>${escapeHTML(item.criado_em || "")}</small></li>`).join("");
                content.innerHTML = `<div class="row g-3"><div class="col-md-4"><h3 class="fs-6">Responsável</h3><p><strong>${escapeHTML(data.nome)}</strong><br>CPF ${escapeHTML(fullCpf(data))}</p></div><div class="col-md-4"><h3 class="fs-6">Família</h3><p>${escapeHTML(data.familia_codigo)}<br>${escapeHTML([data.logradouro, data.numero, data.bairro, data.comunidade].filter(Boolean).join(", "))}</p></div><div class="col-md-4"><h3 class="fs-6">Inscrição</h3><p>${escapeHTML(data.status)}<br>${escapeHTML(data.polo_nome || "Sem polo")}</p></div></div><h3 class="fs-6 mt-3">Entregas</h3><div class="table-responsive"><table class="data-table"><tbody>${deliveries || '<tr><td>Sem entregas.</td></tr>'}</tbody></table></div><div class="row g-3 mt-2"><div class="col-md-6"><h3 class="fs-6">Documentos</h3><ul class="list-group">${documents || '<li class="list-group-item">Sem documentos disponíveis.</li>'}</ul></div><div class="col-md-6"><h3 class="fs-6">Histórico</h3><ul class="list-group">${history || '<li class="list-group-item">Sem histórico disponível.</li>'}</ul></div></div>`;
            });
        }
    });
    qs("#consultaDeliveryForm").addEventListener("submit", async (event) => { event.preventDefault(); await submitAction(event.currentTarget); });
    qs("#consultaCancelForm").addEventListener("submit", async (event) => { event.preventDefault(); await submitAction(event.currentTarget); });
    const releaseLocalMedia = () => { resetInterface(); destroyOcrWorker(); };
    window.addEventListener("pagehide", releaseLocalMedia);
    window.addEventListener("beforeunload", releaseLocalMedia);
    warmOcrWorker();
})();
