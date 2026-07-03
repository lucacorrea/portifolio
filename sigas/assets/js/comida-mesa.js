(() => {
    "use strict";

    const context = window.SIGAS_CONTEXT?.comidaMesa || {};
    const csrf = window.SIGAS_CONTEXT?.csrf || {};
    const permissions = context.permissions || {};
    const qs = (selector, root = document) => root.querySelector(selector);
    const qsa = (selector, root = document) => [...root.querySelectorAll(selector)];
    const digits = (value) => String(value || "").replace(/\D+/g, "");
    const escapeHTML = (value) => String(value ?? "").replaceAll("&", "&amp;").replaceAll("<", "&lt;").replaceAll(">", "&gt;").replaceAll('"', "&quot;").replaceAll("'", "&#039;");
    const modal = (id) => bootstrap.Modal.getOrCreateInstance(qs(id));

    const statePanel = (icon, title, text, tone = "info") => `
        <div class="alert-soft ${tone}"><i class="bi bi-${icon}" aria-hidden="true"></i><div><strong>${escapeHTML(title)}</strong><br><span>${escapeHTML(text)}</span></div></div>
    `;

    const maskCpf = (value) => {
        const numbers = digits(value).slice(0, 11);
        if (numbers.length <= 3) return numbers;
        if (numbers.length <= 6) return `${numbers.slice(0, 3)}.${numbers.slice(3)}`;
        if (numbers.length <= 9) return `${numbers.slice(0, 3)}.${numbers.slice(3, 6)}.${numbers.slice(6)}`;
        return `${numbers.slice(0, 3)}.${numbers.slice(3, 6)}.${numbers.slice(6, 9)}-${numbers.slice(9)}`;
    };

    const setAlert = (form, html = "") => {
        const alert = qs("[data-form-alert]", form);
        if (alert) alert.innerHTML = html;
    };

    const clearFields = (form) => {
        qsa(".is-invalid", form).forEach((field) => field.classList.remove("is-invalid"));
        qsa(".invalid-feedback[data-dynamic]", form).forEach((node) => node.remove());
    };

    const showFields = (form, fields = {}) => {
        Object.entries(fields).forEach(([name, message]) => {
            const field = qs(`[name="${CSS.escape(name)}"]`, form);
            if (!field) return;
            field.classList.add("is-invalid");
            const feedback = document.createElement("div");
            feedback.className = "invalid-feedback d-block";
            feedback.dataset.dynamic = "true";
            feedback.textContent = message;
            field.after(feedback);
        });
    };

    const submitJson = async (form) => {
        clearFields(form);
        setAlert(form, statePanel("hourglass-split", "Processando", "Aguarde a confirmação do servidor."));
        const button = qs('[type="submit"]', form);
        const original = button?.innerHTML;
        if (button) {
            button.disabled = true;
            button.innerHTML = '<span class="spinner-border spinner-border-sm" aria-hidden="true"></span> Salvando';
        }
        try {
            const response = await fetch(form.action, { method: "POST", body: new FormData(form), headers: { Accept: "application/json" } });
            const data = await response.json();
            if (!response.ok || !data.ok) {
                showFields(form, data.fields || {});
                setAlert(form, statePanel("exclamation-triangle", "Não foi possível salvar", data.error || "Revise os dados informados.", "warning"));
                return null;
            }
            setAlert(form, statePanel("check-circle", "Operação concluída", data.message || "Registro salvo.", "success"));
            return data;
        } catch (error) {
            setAlert(form, statePanel("wifi-off", "Erro de comunicação", "Não foi possível concluir a operação.", "warning"));
            return null;
        } finally {
            if (button) {
                button.disabled = false;
                button.innerHTML = original;
            }
        }
    };

    const reloadPreservingFilters = () => window.location.assign(window.location.href);

    const registrationForm = (() => {
        const form = qs("#registrationForm");
        if (!form) return {};

        const open = (seed = {}) => {
            form.reset();
            clearFields(form);
            setAlert(form, "");
            qs("#registrationFormTitle").textContent = seed.inscricao_id ? "Editar inscrição" : "Nova inscrição";
            Object.entries(seed).forEach(([key, value]) => {
                const field = qs(`[name="${CSS.escape(key)}"]`, form);
                if (field) field.value = value ?? "";
            });
            modal("#registrationFormModal").show();
        };

        const fillFromDetail = (data) => open({
            inscricao_id: data.id,
            nome: data.nome,
            cpf: data.cpf,
            telefone: data.telefone,
            nis: data.nis,
            rg: data.rg,
            data_nascimento: data.data_nascimento,
            email: data.email,
            zona: data.zona,
            logradouro: data.logradouro,
            numero: data.numero,
            complemento: data.complemento,
            bairro: data.bairro,
            comunidade: data.comunidade,
            ponto_referencia: data.ponto_referencia,
            cep: data.cep,
            quantidade_membros: data.quantidade_membros,
            renda_familiar: data.renda_familiar,
            polo_id: data.polo_id,
            status: data.status,
            prioridade: data.prioridade,
            data_inscricao: data.data_inscricao,
            motivo_suspensao: data.motivo_suspensao,
            observacao: data.observacao
        });

        qsa('[name="cpf"], [name="telefone"], [name="cep"], [name="nis"]', form).forEach((input) => {
            input.addEventListener("input", () => { if (input.name === "cpf") input.value = maskCpf(input.value); });
        });

        form.addEventListener("submit", async (event) => {
            event.preventDefault();
            const result = await submitJson(form);
            if (result) reloadPreservingFilters();
        });

        return { open, fillFromDetail };
    })();

    const detailViewer = (() => {
        const content = qs("[data-detail-content]");
        let controller = null;

        const label = (value) => escapeHTML(value || "Não informado");
        const render = (data) => {
            const entregaRows = (data.entregas || []).map((item) => `<tr><td>${escapeHTML(String(item.mes).padStart(2, "0"))}/${escapeHTML(item.ano)}</td><td>${escapeHTML(item.status)}</td><td>${label(item.entregue_em)}</td><td>${label(item.polo_nome)}</td><td>${label(item.recebedor_nome)}</td><td>${label(item.operador_nome)}</td><td>${label(item.motivo_cancelamento)}</td></tr>`).join("");
            const members = (data.integrantes || []).map((item) => `<li class="list-group-item d-flex justify-content-between"><span>${label(item.nome)}<br><small>${label(item.parentesco)} · ${label(item.cpf_mascarado)}</small></span></li>`).join("");
            const documents = (data.documentos || []).map((item) => `<li class="list-group-item d-flex justify-content-between align-items-center"><span>${label(item.tipo)}<br><small>${label(item.nome_original)} · ${label(item.mime_type)}</small></span><a class="btn btn-light btn-sm" href="api/comida-mesa/visualizar-documento.php?id=${encodeURIComponent(item.id)}" target="_blank" rel="noopener"><i class="bi bi-box-arrow-up-right"></i>Abrir</a></li>`).join("");
            const history = (data.historico || []).map((item) => `<li class="list-group-item"><strong>${label(item.acao)}</strong><br><span>${label(item.descricao)}</span><br><small>${label(item.usuario_nome)} · ${label(item.criado_em)}</small></li>`).join("");
            content.innerHTML = `
                <div class="row g-3">
                    <div class="col-lg-4"><h3 class="fs-6">Responsável</h3><dl class="small"><dt>Nome</dt><dd>${label(data.nome)}</dd><dt>CPF</dt><dd>${label(data.cpf_mascarado)}</dd><dt>NIS</dt><dd>${label(data.nis)}</dd><dt>Telefone</dt><dd>${label(data.telefone)}</dd><dt>E-mail</dt><dd>${label(data.email)}</dd></dl></div>
                    <div class="col-lg-4"><h3 class="fs-6">Família</h3><dl class="small"><dt>Código</dt><dd>${label(data.familia_codigo)}</dd><dt>Endereço</dt><dd>${label([data.logradouro, data.numero, data.bairro, data.comunidade].filter(Boolean).join(", "))}</dd><dt>Referência</dt><dd>${label(data.ponto_referencia)}</dd><dt>Membros</dt><dd>${label(data.quantidade_membros)}</dd><dt>Renda</dt><dd>${label(data.renda_familiar)}</dd></dl></div>
                    <div class="col-lg-4"><h3 class="fs-6">Inscrição</h3><dl class="small"><dt>Situação</dt><dd>${label(data.status)}</dd><dt>Prioridade</dt><dd>${label(data.prioridade)}</dd><dt>Polo</dt><dd>${label(data.polo_nome)}</dd><dt>Data</dt><dd>${label(data.data_inscricao)}</dd><dt>Motivo</dt><dd>${label(data.motivo_suspensao)}</dd></dl></div>
                </div>
                <h3 class="fs-6 mt-3">Entregas</h3><div class="table-responsive"><table class="data-table"><thead><tr><th>Competência</th><th>Status</th><th>Data</th><th>Polo</th><th>Recebedor</th><th>Operador</th><th>Cancelamento</th></tr></thead><tbody>${entregaRows || '<tr><td colspan="7">Sem entregas registradas.</td></tr>'}</tbody></table></div>
                <div class="row g-3 mt-2"><div class="col-lg-4"><h3 class="fs-6">Integrantes</h3><ul class="list-group">${members || '<li class="list-group-item">Sem integrantes vinculados.</li>'}</ul></div><div class="col-lg-4"><h3 class="fs-6">Documentos</h3><ul class="list-group">${documents || '<li class="list-group-item">Sem documentos disponíveis.</li>'}</ul></div><div class="col-lg-4"><h3 class="fs-6">Histórico</h3><ul class="list-group">${history || '<li class="list-group-item">Sem histórico disponível.</li>'}</ul></div></div>
            `;
        };

        const load = async (id, { edit = false } = {}) => {
            if (controller) controller.abort();
            controller = new AbortController();
            const current = controller;
            if (!edit) {
                content.innerHTML = statePanel("hourglass-split", "Carregando", "Buscando dados atualizados.");
                modal("#detailModal").show();
            }
            try {
                const response = await fetch(`api/comida-mesa/detalhar.php?id=${encodeURIComponent(id)}`, { headers: { Accept: "application/json" }, signal: current.signal });
                const payload = await response.json();
                if (current !== controller) return;
                if (!response.ok || !payload.ok) throw new Error(payload.error || "Falha");
                if (edit) registrationForm.fillFromDetail(payload.data);
                else render(payload.data);
            } catch (error) {
                if (error.name !== "AbortError" && !edit) content.innerHTML = statePanel("exclamation-triangle", "Não foi possível carregar", "Tente novamente.", "warning");
            }
        };

        document.addEventListener("click", (event) => {
            const detail = event.target.closest("[data-open-detail]");
            const edit = event.target.closest("[data-open-edit]");
            if (detail) load(detail.dataset.registrationId);
            if (edit) load(edit.dataset.registrationId, { edit: true });
        });

        return { load };
    })();

    const cpfLookup = (() => {
        const root = qs("[data-comida-mesa-consulta]");
        if (!root) return;
        const form = qs("#cpfLookupForm", root);
        const cpf = qs("#cpfLookupInput", root);
        const result = qs("#cpfLookupResult", root);
        const submit = qs("[data-cpf-submit]", root);
        let controller = null;

        const render = (html) => { result.innerHTML = html; };
        const renderResponse = (data) => {
            if (!data?.ok) { render(statePanel("exclamation-octagon", "Consulta indisponível", data?.error || "Não foi possível consultar.", "warning")); return; }
            if (data.state === "inscrito") {
                render(`${statePanel("person-check", "Pessoa já inscrita", "Abra a visualização completa para acompanhar a família.", "success")}<button class="btn btn-primary w-100 mt-3" type="button" data-open-detail data-registration-id="${escapeHTML(data.registration?.id)}">Visualizar inscrição</button>`);
            } else if (data.state === "pessoa_sem_inscricao") {
                render(`${statePanel("person-plus", "Pessoa localizada sem inscrição", "Continue o cadastro usando os dados já existentes.", "info")}<button class="btn btn-primary w-100 mt-3" type="button" data-start-registration data-cpf="${escapeHTML(data.cpf)}" data-name="${escapeHTML(data.person?.name)}">Continuar cadastro</button>`);
            } else {
                render(`${statePanel("search", "Pessoa não localizada", "Inicie um cadastro novo para este CPF.", "warning")}<button class="btn btn-primary w-100 mt-3" type="button" data-start-registration data-cpf="${escapeHTML(data.cpf)}">Iniciar novo cadastro</button>`);
            }
        };

        cpf.addEventListener("input", () => { cpf.value = maskCpf(cpf.value); cpf.classList.remove("is-invalid"); });
        document.addEventListener("click", (event) => {
            const trigger = event.target.closest("[data-consult-cpf]");
            if (trigger) { cpf.value = maskCpf(trigger.dataset.consultCpf || ""); render(""); }
            const start = event.target.closest("[data-start-registration]");
            if (start) { modal("#newRegistrationModal").hide(); registrationForm.open({ cpf: maskCpf(start.dataset.cpf || ""), nome: start.dataset.name || "", status: "em_analise", prioridade: "normal", quantidade_membros: 1 }); }
        });
        form.addEventListener("submit", async (event) => {
            event.preventDefault();
            if (digits(cpf.value).length !== 11) { cpf.classList.add("is-invalid"); render(statePanel("exclamation-triangle", "CPF inválido", "Informe um CPF com 11 números.", "warning")); return; }
            if (controller) controller.abort();
            controller = new AbortController();
            const current = controller;
            submit.disabled = true;
            render(statePanel("hourglass-split", "Consultando", "Aguarde enquanto verificamos o cadastro."));
            try {
                const response = await fetch(form.action, { method: "POST", body: new FormData(form), headers: { Accept: "application/json" }, signal: current.signal });
                const data = await response.json();
                if (current === controller) renderResponse(data);
            } catch (error) {
                if (error.name !== "AbortError") render(statePanel("wifi-off", "Erro de comunicação", "Não foi possível concluir a consulta.", "warning"));
            } finally {
                if (current === controller) submit.disabled = false;
            }
        });
    })();

    const deliveryForm = (() => {
        const form = qs("#deliveryForm");
        if (!form) return;
        document.addEventListener("click", (event) => {
            const trigger = event.target.closest("[data-open-delivery]");
            if (!trigger) return;
            form.reset();
            setAlert(form, "");
            qs('[name="inscricao_id"]', form).value = trigger.dataset.registrationId || "";
            qs('[name="competencia_id"]', form).value = context.competenciaId || "";
            qs("[data-delivery-family]", form).textContent = `${trigger.dataset.registrationName || "Família"} · ${trigger.dataset.familyCode || ""}`;
            modal("#deliveryModal").show();
        });
        form.addEventListener("submit", async (event) => { event.preventDefault(); if (await submitJson(form)) reloadPreservingFilters(); });
    })();

    const cancellationForm = (() => {
        const form = qs("#cancelDeliveryForm");
        if (!form) return;
        document.addEventListener("click", (event) => {
            const trigger = event.target.closest("[data-open-cancel]");
            if (!trigger) return;
            form.reset();
            setAlert(form, "");
            qs('[name="inscricao_id"]', form).value = trigger.dataset.registrationId || "";
            qs('[name="competencia_id"]', form).value = context.competenciaId || "";
            qs("[data-cancel-family]", form).textContent = `Família ${trigger.dataset.familyCode || ""} · ${context.competenciaId ? "competência selecionada" : "sem competência selecionada"}`;
            modal("#cancelDeliveryModal").show();
        });
        form.addEventListener("submit", async (event) => { event.preventDefault(); if (await submitJson(form)) reloadPreservingFilters(); });
    })();

    const competenceForm = (() => {
        const form = qs("#competenceForm");
        if (!form) return;
        document.addEventListener("click", (event) => {
            if (!event.target.closest("[data-open-competence]")) return;
            setAlert(form, "");
            modal("#competenceModal").show();
        });
        form.addEventListener("submit", async (event) => { event.preventDefault(); if (await submitJson(form)) reloadPreservingFilters(); });
    })();

    const documentUpload = (() => {
        const form = qs("#documentForm");
        if (!form) return;
        document.addEventListener("click", (event) => {
            const trigger = event.target.closest("[data-open-document]");
            if (!trigger) return;
            form.reset();
            setAlert(form, "");
            qs('[name="inscricao_id"]', form).value = trigger.dataset.registrationId || "";
            modal("#documentModal").show();
        });
        form.addEventListener("submit", async (event) => { event.preventDefault(); if (await submitJson(form)) reloadPreservingFilters(); });
    })();

    qsa("[data-toggle-advanced]").forEach((button) => {
        button.addEventListener("click", () => {
            const advanced = qs("#advancedFilters");
            advanced?.classList.toggle("show");
            button.setAttribute("aria-expanded", String(advanced?.classList.contains("show") || false));
        });
    });
})();
