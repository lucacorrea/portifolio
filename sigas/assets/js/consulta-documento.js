(() => {
    "use strict";
    const root = document.querySelector("[data-consulta-documento]");
    if (!root) return;
    const ctx = window.SIGAS_CONTEXT?.consultaDocumento || {};
    const permissions = ctx.permissions || {};
    const cpfOcr = window.SIGAS_CPF_OCR;
    const qs = (selector, base = document) => base.querySelector(selector);
    const escapeHTML = (value) => String(value ?? "").replaceAll("&", "&amp;").replaceAll("<", "&lt;").replaceAll(">", "&gt;").replaceAll('"', "&quot;").replaceAll("'", "&#039;");
    const digits = (value) => String(value || "").replace(/\D+/g, "");
    const modal = (selector) => bootstrap.Modal.getOrCreateInstance(qs(selector));
    const [
        result, form, cpfInput, video, preview, placeholder, openCamera, capture, chooseImage, retry,
        cancelOcr, fileInput, ocrStatus, ocrCandidates, ocrLine, progressBar, progressText, progressTitle, progressMessage,
    ] = [
        "#consultaResult", "#manualCpfForm", "#manualCpf", "#scannerVideo", "#scannerPreview", "#cameraPlaceholder",
        "#openCameraButton", "#captureDocumentButton", "#chooseImageButton", "#retryOcrButton", "#cancelOcrButton",
        "#documentImageInput", "#ocrStatus", "#ocrCandidates", "#ocrScanningLine", "[data-ocr-progress-bar]",
        "[data-ocr-progress]", "[data-ocr-title]", "[data-ocr-message]",
    ].map((selector) => qs(selector));
    let currentCpf = "", currentData = null, controller = null, stream = null, objectUrl = "", selectedBlob = null;
    let ocrState = "idle", ocrRunId = 0, ocrWorker = null, ocrWorkerPromise = null, autoConsultCpf = "";
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
    const renderNotFound = () => {
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
        result.innerHTML = `<div class="result-status-header"><span class="result-avatar">${escapeHTML((data.person?.name || "P").slice(0, 2).toUpperCase())}</span><div><span class="result-overline">Pessoa inscrita</span><h2>${escapeHTML(data.person?.name)}</h2><p>CPF: ${escapeHTML(data.person?.cpf_masked)}</p></div><span class="status-badge ${data.delivery?.status === "recebida" ? "status-success" : "status-info"}"><i class="bi bi-basket2"></i>${escapeHTML(deliveryText)}</span></div>
            <div class="verification-grid"><div><span>Família</span><strong>${escapeHTML(data.registration?.family_code || data.family?.code)}</strong></div><div><span>Situação</span><strong>${escapeHTML(data.registration?.status_label)}</strong></div><div><span>Prioridade</span><strong>${escapeHTML(data.registration?.priority)}</strong></div><div><span>Polo</span><strong>${escapeHTML(data.registration?.pole || "Sem polo")}</strong></div><div><span>Competência</span><strong>${escapeHTML(data.competence?.label || "Sem competência")}</strong></div><div><span>Entrega</span><strong>${escapeHTML(deliveryMeta || deliveryText)}</strong></div></div>
            ${eligibility.reason ? panel("info-circle", "Regra operacional", eligibility.reason, "info") : ""}<div class="result-actions">${button(`data-open-detail data-registration-id="${escapeHTML(data.registration?.id)}"`, "eye", "Visualizar detalhes", "btn btn-light")}${permissions.edit ? `<a class="btn btn-light" href="modulo.php?action=edit&id=${encodeURIComponent(data.registration?.id)}"><i class="bi bi-pencil"></i>Editar</a>` : ""}${canDelivery ? button("data-open-delivery", "basket2", eligibility.action === "reactivate" ? "Reativar entrega" : "Registrar entrega") : ""}${canCancel ? button("data-open-cancel", "x-circle", "Cancelar entrega", "btn btn-danger") : ""}${!data.competence && permissions.manageCompetences ? '<a class="btn btn-light" href="modulo.php?action=competence"><i class="bi bi-calendar-plus"></i>Gerenciar competência</a>' : ""}${button("data-reset-consulta", "arrow-repeat", "Consultar outra pessoa", "btn btn-light")}</div>`;
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
    const setOcrState = (state) => {
        ocrState = state;
        const busy = ["preparing", "recognizing"].includes(state);
        capture.disabled = busy || !["camera", "captured"].includes(state);
        openCamera.disabled = busy;
        chooseImage.disabled = busy;
        retry.disabled = busy;
        cancelOcr.hidden = !busy;
        ocrLine.hidden = !busy;
        retry.hidden = !["captured", "found", "multiple", "not_found", "error", "cancelled"].includes(state);
    };
    const setProgress = (title, percent, message) => {
        const value = Math.max(0, Math.min(100, Math.round(percent)));
        ocrStatus.hidden = false;
        progressTitle.textContent = title;
        progressText.textContent = `${value}%`;
        progressBar.style.width = `${value}%`;
        progressBar.parentElement.setAttribute("aria-valuenow", String(value));
        progressMessage.textContent = message;
    };
    const hideOcrPanels = () => {
        ocrStatus.hidden = true;
        ocrCandidates.hidden = true;
        ocrCandidates.innerHTML = "";
        ocrLine.hidden = true;
    };
    const revokePreviewUrl = () => {
        if (objectUrl) URL.revokeObjectURL(objectUrl);
        objectUrl = "";
    };
    const stopCamera = () => {
        stream?.getTracks().forEach((track) => track.stop());
        stream = null;
        video.srcObject = null;
        video.hidden = true;
        openCamera.hidden = false;
        capture.hidden = true;
    };
    const terminateOcrWorker = async () => {
        const worker = ocrWorker;
        ocrWorker = null;
        ocrWorkerPromise = null;
        if (worker) await worker.terminate();
    };
    const getOcrWorker = async () => {
        if (!window.Tesseract?.createWorker) throw new Error("ocr_unavailable");
        if (!ocrWorkerPromise) {
            ocrWorkerPromise = window.Tesseract.createWorker("eng", {
                logger: (message) => {
                    if (!["preparing", "recognizing"].includes(ocrState)) return;
                    const percent = message.progress ? message.progress * 100 : 5;
                    const titles = {
                        "loading tesseract core": "Preparando leitor",
                        "loading language traineddata": "Carregando reconhecimento",
                        "initializing api": "Analisando documento",
                        "recognizing text": "Procurando CPF",
                    };
                    setProgress(titles[message.status] || "Analisando documento", percent, "Processamento local em andamento.");
                },
            }).then(async (worker) => {
                ocrWorker = worker;
                await worker.setParameters({
                    tessedit_pageseg_mode: "11",
                    preserve_interword_spaces: "1",
                    tessedit_char_whitelist: "0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyzÁÀÂÃÉÊÍÓÔÕÚÇáàâãéêíóôõúç.-:/ ",
                });
                return worker;
            });
        }
        return ocrWorkerPromise;
    };
    const showImageBlob = (blob) => {
        revokePreviewUrl();
        selectedBlob = blob;
        objectUrl = URL.createObjectURL(blob);
        preview.src = objectUrl;
        preview.hidden = false;
        placeholder.hidden = true;
        capture.hidden = false;
        capture.disabled = false;
        openCamera.hidden = false;
        setOcrState("captured");
    };
    const imageFromBlob = async (blob) => {
        if (window.createImageBitmap) {
            try {
                return await createImageBitmap(blob, { imageOrientation: "from-image" });
            } catch (error) {
                return await createImageBitmap(blob);
            }
        }
        return new Promise((resolve, reject) => {
            const img = new Image();
            const url = URL.createObjectURL(blob);
            img.onload = () => { URL.revokeObjectURL(url); resolve(img); };
            img.onerror = () => { URL.revokeObjectURL(url); reject(new Error("invalid_image")); };
            img.src = url;
        });
    };
    const buildOcrCanvases = async (blob) => {
        const source = await imageFromBlob(blob);
        const maxSide = Math.max(source.width, source.height), scale = maxSide > 2200 ? 2200 / maxSide : 1;
        const width = Math.max(1, Math.round(source.width * scale)), height = Math.max(1, Math.round(source.height * scale));
        const canvas = document.createElement("canvas");
        canvas.width = width; canvas.height = height;
        const ctx2d = canvas.getContext("2d", { willReadFrequently: true });
        ctx2d.drawImage(source, 0, 0, width, height);
        const imageData = ctx2d.getImageData(0, 0, width, height);
        const src = imageData.data;
        for (let index = 0; index < src.length; index += 4) {
            const gray = src[index] * 0.299 + src[index + 1] * 0.587 + src[index + 2] * 0.114;
            const contrasted = Math.max(0, Math.min(255, (gray - 128) * 1.32 + 128));
            src[index] = contrasted;
            src[index + 1] = contrasted;
            src[index + 2] = contrasted;
        }
        const sharpened = new Uint8ClampedArray(src);
        for (let y = 1; y < height - 1; y += 1) {
            for (let x = 1; x < width - 1; x += 1) {
                const index = (y * width + x) * 4;
                const value = Math.max(0, Math.min(255, src[index] * 1.6 - (src[index - 4] + src[index + 4] + src[index - width * 4] + src[index + width * 4]) * 0.15));
                sharpened[index] = value;
                sharpened[index + 1] = value;
                sharpened[index + 2] = value;
            }
        }
        imageData.data.set(sharpened);
        ctx2d.putImageData(imageData, 0, 0);
        const binary = document.createElement("canvas");
        binary.width = width; binary.height = height;
        const binaryCtx = binary.getContext("2d", { willReadFrequently: true });
        binaryCtx.drawImage(canvas, 0, 0);
        const binaryData = binaryCtx.getImageData(0, 0, width, height);
        const dst = binaryData.data;
        for (let index = 0; index < dst.length; index += 4) {
            const value = dst[index] > 150 ? 255 : 0;
            dst[index] = value;
            dst[index + 1] = value;
            dst[index + 2] = value;
        }
        binaryCtx.putImageData(binaryData, 0, 0);
        if (source.close) source.close();
        return [canvas, binary];
    };
    const finishCanvases = (canvases) => {
        canvases.forEach((canvas) => {
            canvas.width = 0;
            canvas.height = 0;
        });
    };
    const applySingleCpf = async (candidate, runId) => {
        if (runId !== ocrRunId) return;
        cpfInput.value = maskCpf(candidate.cpf);
        cpfInput.classList.remove("is-invalid");
        setOcrState("found");
        setProgress("CPF identificado e validado", 100, candidate.formatted);
        ocrStatus.classList.add("ocr-result-success");
        if (autoConsultCpf === candidate.cpf) return;
        autoConsultCpf = candidate.cpf;
        await consult();
    };
    const renderMultipleCandidates = (candidates) => {
        setOcrState("multiple");
        setProgress("Validando CPF", 100, "Foram encontrados mais de um CPF.");
        ocrCandidates.hidden = false;
        ocrCandidates.innerHTML = `<h3>Foram encontrados mais de um CPF.</h3><p>Selecione o número que pertence ao titular do documento.</p><div class="ocr-candidate-actions">${candidates.map((item) => button(`data-ocr-cpf="${escapeHTML(item.cpf)}"`, "person-vcard", item.formatted, "btn btn-light")).join("")}</div>`;
    };
    const renderNotIdentified = () => {
        setOcrState("not_found");
        ocrStatus.classList.add("ocr-result-warning");
        setProgress("CPF não identificado", 100, "Não foi possível localizar um CPF válido nesta imagem. Fotografe o lado do documento onde o CPF aparece, melhore a iluminação ou digite o número manualmente.");
        result.innerHTML = panel("upc-scan", "O CPF não foi localizado como texto neste documento", "Digite o número manualmente ou tente outra imagem.", "warning");
    };
    const runOcr = async (blob) => {
        if (!blob || ["preparing", "recognizing"].includes(ocrState)) return;
        const runId = ocrRunId + 1;
        ocrRunId = runId;
        ocrStatus.classList.remove("ocr-result-success", "ocr-result-warning");
        ocrCandidates.hidden = true;
        ocrCandidates.innerHTML = "";
        setOcrState("preparing");
        setProgress("Preparando leitor", 2, "A imagem será analisada somente neste dispositivo.");
        let canvases = [];
        try {
            canvases = await buildOcrCanvases(blob);
            if (runId !== ocrRunId) return;
            const worker = await getOcrWorker();
            for (let index = 0; index < canvases.length; index += 1) {
                if (runId !== ocrRunId) return;
                setOcrState("recognizing");
                setProgress(index === 0 ? "Procurando CPF" : "Validando CPF", index === 0 ? 35 : 68, index === 0 ? "Analisando documento." : "Tentando uma variação com mais contraste.");
                const response = await worker.recognize(canvases[index]);
                let recognizedText = response?.data?.text || "";
                const decision = cpfOcr.selectCpfResult(recognizedText);
                recognizedText = "";
                if (decision.state === "single") {
                    await applySingleCpf(decision.candidates[0], runId);
                    return;
                }
                if (decision.state === "multiple") {
                    renderMultipleCandidates(decision.candidates);
                    return;
                }
            }
            if (runId === ocrRunId) renderNotIdentified();
        } catch (error) {
            if (runId !== ocrRunId) return;
            setOcrState("error");
            ocrStatus.classList.add("ocr-result-warning");
            setProgress("Leitura indisponível", 100, "Não foi possível iniciar a leitura automática. Digite o CPF manualmente ou tente outra imagem.");
        } finally {
            finishCanvases(canvases);
        }
    };
    const cancelCurrentOcr = async () => {
        ocrRunId += 1;
        setOcrState("cancelled");
        setProgress("Leitura cancelada", 0, "A imagem foi mantida para uma nova tentativa.");
        await terminateOcrWorker();
    };
    const resetScan = async () => {
        ocrRunId += 1;
        autoConsultCpf = "";
        stopCamera();
        await terminateOcrWorker();
        revokePreviewUrl();
        selectedBlob = null; fileInput.value = "";
        preview.removeAttribute("src");
        preview.hidden = true; placeholder.hidden = false; capture.hidden = true; openCamera.hidden = false;
        hideOcrPanels();
        setOcrState("idle");
    };
    const openFilePickerWithMessage = (message) => {
        result.innerHTML = panel("camera-video-off", "Use uma imagem do documento", message, "warning");
        fileInput.click();
    };
    const cameraMessage = (error) => {
        if (!navigator.mediaDevices?.getUserMedia) return "Este navegador não oferece acesso à câmera. Escolha uma imagem do documento.";
        if (!window.isSecureContext) return "O acesso à câmera exige HTTPS. Escolha uma imagem do documento ou digite o CPF manualmente.";
        if (error?.name === "NotAllowedError" || error?.name === "SecurityError") return "A permissão da câmera foi negada. Escolha uma imagem do documento ou digite o CPF manualmente.";
        if (error?.name === "NotFoundError" || error?.name === "OverconstrainedError") return "Nenhuma câmera compatível foi encontrada. Escolha uma imagem do documento.";
        if (error?.name === "NotReadableError" || error?.name === "AbortError") return "A câmera está ocupada ou indisponível. Escolha uma imagem do documento.";
        return "Não foi possível abrir a câmera. Escolha uma imagem do documento.";
    };
    const startCamera = async () => {
        if (["preparing", "recognizing"].includes(ocrState)) return;
        await resetScan();
        if (!navigator.mediaDevices?.getUserMedia || !window.isSecureContext) {
            openFilePickerWithMessage(cameraMessage());
            return;
        }
        try {
            stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: { ideal: "environment" }, width: { ideal: 1920 }, height: { ideal: 1080 } }, audio: false });
        } catch (error) {
            try {
                stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: "environment" }, audio: false });
            } catch (fallbackError) {
                openFilePickerWithMessage(cameraMessage(fallbackError));
                return;
            }
        }
        video.srcObject = stream;
        await video.play();
        video.hidden = false;
        preview.hidden = true;
        placeholder.hidden = true;
        capture.hidden = false;
        openCamera.hidden = true;
        setOcrState("camera");
    };
    const captureFrame = async () => {
        if (ocrState === "captured" && selectedBlob) {
            await runOcr(selectedBlob);
            return;
        }
        if (!video.videoWidth || !video.videoHeight) {
            result.innerHTML = panel("camera-video-off", "Imagem indisponível", "A câmera ainda não enviou uma imagem válida. Tente novamente.", "warning");
            return;
        }
        const canvas = document.createElement("canvas");
        canvas.width = video.videoWidth; canvas.height = video.videoHeight;
        canvas.getContext("2d").drawImage(video, 0, 0, canvas.width, canvas.height);
        const blob = await new Promise((resolve) => canvas.toBlob(resolve, "image/jpeg", 0.92));
        canvas.width = 0; canvas.height = 0;
        stopCamera();
        showImageBlob(blob);
        await runOcr(blob);
    };
    const handleFileSelection = () => {
        const file = fileInput.files?.[0];
        if (!file) return;
        if (!["image/jpeg", "image/png", "image/webp"].includes(file.type) || file.size > 15 * 1024 * 1024) {
            fileInput.value = "";
            result.innerHTML = panel("image", "Imagem não aceita", "Use JPG, PNG ou WebP com até 15 MB.", "warning");
            return;
        }
        stopCamera();
        hideOcrPanels();
        showImageBlob(file);
        result.innerHTML = panel("image", "Documento selecionado", "Confira a imagem e clique em Ler CPF para iniciar o processamento local.", "info");
    };
    form.addEventListener("submit", (event) => { event.preventDefault(); consult(); });
    cpfInput.addEventListener("input", () => { cpfInput.value = maskCpf(cpfInput.value); cpfInput.classList.remove("is-invalid"); });
    openCamera.addEventListener("click", startCamera);
    chooseImage.addEventListener("click", () => { if (!["preparing", "recognizing"].includes(ocrState)) fileInput.click(); });
    retry.addEventListener("click", startCamera);
    capture.addEventListener("click", captureFrame);
    cancelOcr.addEventListener("click", cancelCurrentOcr);
    fileInput.addEventListener("change", handleFileSelection);
    document.addEventListener("click", async (event) => {
        const selectedCpf = event.target.closest("[data-ocr-cpf]");
        if (selectedCpf) {
            cpfInput.value = maskCpf(selectedCpf.dataset.ocrCpf);
            ocrCandidates.hidden = true;
            await consult();
            return;
        }
        if (event.target.closest("[data-reset-consulta]")) {
            currentCpf = "";
            currentData = null;
            cpfInput.value = "";
            result.innerHTML = '<span class="result-empty-icon"><i class="bi bi-person-lines-fill"></i></span><h2>Resultado da consulta</h2><p>Informe o CPF para visualizar pessoa, família, inscrição, competência e situação da entrega.</p>';
            await resetScan();
        }
        if (event.target.closest("[data-open-delivery]") && currentData) {
            const formEl = qs("#consultaDeliveryForm");
            formEl.reset();
            setAlert(formEl, "");
            qs('[name="inscricao_id"]', formEl).value = currentData.registration.id; qs('[name="competencia_id"]', formEl).value = currentData.competence?.id || "";
            qs('[name="recebedor_nome"]', formEl).value = currentData.person?.name || ""; qs('[name="recebedor_cpf"]', formEl).value = maskCpf(currentCpf);
            qs("[data-delivery-name]", formEl).textContent = currentData.person?.name || ""; qs("[data-delivery-family]", formEl).textContent = currentData.registration?.family_code || "";
            qs("[data-delivery-competence]", formEl).textContent = currentData.competence?.label || ""; qs("[data-delivery-pole]", formEl).textContent = currentData.registration?.pole || "";
            modal("#consultaDeliveryModal").show();
        }
        if (event.target.closest("[data-open-cancel]") && currentData) {
            const formEl = qs("#consultaCancelForm");
            formEl.reset();
            setAlert(formEl, "");
            qs('[name="inscricao_id"]', formEl).value = currentData.registration.id; qs('[name="competencia_id"]', formEl).value = currentData.competence?.id || "";
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
    const releaseLocalMedia = () => { stopCamera(); revokePreviewUrl(); if (ocrWorker) ocrWorker.terminate(); };
    window.addEventListener("pagehide", releaseLocalMedia);
    window.addEventListener("beforeunload", releaseLocalMedia);
})();
