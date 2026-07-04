(() => {
    "use strict";

    const context = window.SIGAS_CONTEXT?.comidaMesa || {};
    const csrf = window.SIGAS_CONTEXT?.csrf || {};
    const permissions = context.permissions || {};
    const competences = new Map((context.competences || []).map((item) => [String(item.id), item]));
    let pendingRegistration = null;
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
            versao_atualizacao: data.atualizado_em,
            nome: data.nome,
            cpf: data.cpf_completo || data.cpf_mascarado,
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
        const description = (pairs) => pairs.map(([term, value]) => `<dt>${escapeHTML(term)}</dt><dd>${label(value)}</dd>`).join("");
        const render = (data) => {
            const entregaRows = (data.entregas || []).map((item) => `<tr><td>${label(item.competencia_label)}</td><td>${label(item.status_label)}</td><td>${label(item.entregue_em_formatado)}</td><td>${label(item.polo_nome)}</td><td>${label(item.recebedor_nome)}</td><td>${label(item.recebedor_parentesco)}</td><td>${label(item.operador_nome)}</td><td>${label(item.cancelador_nome)}</td><td>${label(item.cancelada_em_formatada)}</td><td>${label(item.motivo_cancelamento)}</td></tr>`).join("");
            const members = (data.integrantes || []).map((item) => `<li class="list-group-item d-flex justify-content-between"><span>${label(item.nome)}<br><small>${label(item.parentesco)} · ${label(item.cpf_mascarado)}</small></span></li>`).join("");
            const documents = (data.documentos || []).map((item) => `<li class="list-group-item d-flex justify-content-between align-items-center"><span>${label(item.tipo)}<br><small>${label(item.descricao)} · ${label(item.nome_original)} · ${label(item.mime_type)} · ${label(item.tamanho_formatado)} · ${label(item.criado_em_formatado)} · ${label(item.enviado_por_nome)}</small></span><a class="btn btn-light btn-sm" href="api/comida-mesa/visualizar-documento.php?id=${encodeURIComponent(item.id)}" target="_blank" rel="noopener"><i class="bi bi-box-arrow-up-right"></i>Abrir</a></li>`).join("");
            const history = (data.historico || []).map((item) => {
                const changes = (item.changes || []).map((change) => `<li>${label(change.field)}: ${label(change.before)} &rarr; ${label(change.after)}</li>`).join("");
                return `<li class="list-group-item"><strong>${label(item.acao)}</strong><br><span>${label(item.descricao)}</span><br><small>${label(item.usuario_nome)} · ${label(item.criado_em)}</small>${changes ? `<ul class="mt-2 mb-0">${changes}</ul>` : ""}</li>`;
            }).join("");
            content.innerHTML = `
                <div class="row g-3">
                    <div class="col-lg-4"><h3 class="fs-6">Responsável</h3><dl class="small">${description([["Nome", data.nome], ["CPF", data.cpf_completo || data.cpf_mascarado], ["NIS", data.nis], ["RG", data.rg], ["Nascimento", data.data_nascimento], ["Telefone", data.telefone], ["E-mail", data.email]])}</dl></div>
                    <div class="col-lg-4"><h3 class="fs-6">Família</h3><dl class="small">${description([["Código", data.familia_codigo], ["Zona", data.zona], ["Logradouro", data.logradouro], ["Número", data.numero], ["Complemento", data.complemento], ["Bairro", data.bairro], ["Comunidade", data.comunidade], ["Referência", data.ponto_referencia], ["CEP", data.cep], ["Membros", data.quantidade_membros], ["Renda", data.renda_familiar_formatada]])}</dl></div>
                    <div class="col-lg-4"><h3 class="fs-6">Inscrição</h3><dl class="small">${description([["Situação", data.status_label], ["Prioridade", data.prioridade_label], ["Polo", data.polo_nome], ["Data de inscrição", data.data_inscricao_formatada], ["Data de aprovação", data.data_aprovacao_formatada], ["Observação", data.observacao], ["Motivo", data.motivo_suspensao], ["Atualização", data.atualizado_em_formatado]])}</dl></div>
                </div>
                <h3 class="fs-6 mt-3">Entregas</h3><div class="table-responsive"><table class="data-table"><thead><tr><th>Competência</th><th>Status</th><th>Data</th><th>Polo</th><th>Recebedor</th><th>Parentesco</th><th>Operador</th><th>Cancelador</th><th>Cancelamento</th><th>Motivo</th></tr></thead><tbody>${entregaRows || '<tr><td colspan="10">Sem entregas registradas.</td></tr>'}</tbody></table></div>
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
                pendingRegistration = { cpf: data.cpf || "", name: data.person?.name || "" };
                render(`${statePanel("person-plus", "Pessoa localizada sem inscrição", "Continue o cadastro usando os dados já existentes.", "info")}<button class="btn btn-primary w-100 mt-3" type="button" data-start-registration>Continuar cadastro</button>`);
            } else {
                pendingRegistration = { cpf: data.cpf || "", name: "" };
                render(`${statePanel("search", "Pessoa não localizada", "Inicie um cadastro novo para este CPF.", "warning")}<button class="btn btn-primary w-100 mt-3" type="button" data-start-registration>Iniciar novo cadastro</button>`);
            }
        };

        cpf.addEventListener("input", () => { cpf.value = maskCpf(cpf.value); cpf.classList.remove("is-invalid"); });
        document.addEventListener("click", (event) => {
            const start = event.target.closest("[data-start-registration]");
            if (start) {
                modal("#newRegistrationModal").hide();
                registrationForm.open({
                    cpf: maskCpf(pendingRegistration?.cpf || ""),
                    nome: pendingRegistration?.name || "",
                    status: "em_analise",
                    prioridade: "normal",
                    quantidade_membros: 1
                });
            }
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
            const isReactivate = trigger.dataset.deliveryAction === "reactivate";
            qs('[name="inscricao_id"]', form).value = trigger.dataset.registrationId || "";
            qs('[name="competencia_id"]', form).value = context.competenciaId || "";
            qs("#deliveryTitle").textContent = isReactivate ? "Reativar entrega" : "Registrar entrega";
            qs("[data-delivery-submit]", form).innerHTML = isReactivate ? '<i class="bi bi-check2"></i>Confirmar reativação' : '<i class="bi bi-check2"></i>Confirmar entrega';
            qs("[data-delivery-family]", form).textContent = isReactivate ? "Reativação de entrega" : "Registro de entrega";
            qs("[data-delivery-summary]", form).textContent = context.competenceLabel || "Competência selecionada";
            qs("[data-delivery-responsible]", form).textContent = trigger.dataset.registrationName || "Não informado";
            qs("[data-delivery-code]", form).textContent = trigger.dataset.familyCode || "Não informado";
            qs("[data-delivery-pole]", form).textContent = trigger.dataset.poleName || "Não informado";
            qs('[name="recebedor_nome"]', form).value = trigger.dataset.registrationName || "";
            qs('[name="recebedor_cpf"]', form).value = "";
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
            qs("[data-cancel-responsible]", form).textContent = trigger.dataset.registrationName || "Não informado";
            qs("[data-cancel-code]", form).textContent = trigger.dataset.familyCode || "Não informado";
            qs("[data-cancel-pole]", form).textContent = trigger.dataset.poleName || "Não informado";
            qs("[data-cancel-date]", form).textContent = trigger.dataset.deliveryDate || "Não informado";
            qs("[data-cancel-operator]", form).textContent = trigger.dataset.deliveryOperator || "Não informado";
            modal("#cancelDeliveryModal").show();
        });
        form.addEventListener("submit", async (event) => { event.preventDefault(); if (await submitJson(form)) reloadPreservingFilters(); });
    })();

    const competenceForm = (() => {
        const form = qs("#competenceForm");
        if (!form) return;
        const fill = (item = null) => {
            form.reset();
            clearFields(form);
            setAlert(form, "");
            qs("#competenceTitle").textContent = item ? "Editar competência selecionada" : "Nova competência";
            qs('[name="competencia_id"]', form).value = item?.id || "";
            qs('[name="mes"]', form).value = item?.month || new Date().getMonth() + 1;
            qs('[name="ano"]', form).value = item?.year || new Date().getFullYear();
            qs('[name="status"]', form).value = item?.status || "planejada";
            qs('[name="inicio_entregas"]', form).value = item?.startsAt || "";
            qs('[name="fim_entregas"]', form).value = item?.endsAt || "";
            qs('[name="observacao"]', form).value = item?.observation || "";
            modal("#competenceModal").show();
        };
        document.addEventListener("click", (event) => {
            if (event.target.closest("[data-open-new-competence]")) {
                fill(null);
                return;
            }
            if (event.target.closest("[data-open-edit-competence]")) {
                fill(competences.get(String(context.competenciaId)) || null);
            }
        });
        form.addEventListener("submit", async (event) => {
            event.preventDefault();
            const result = await submitJson(form);
            if (!result) return;
            const id = result.data?.id || result.id || qs('[name="competencia_id"]', form).value;
            if (qs('[name="competencia_id"]', form).value === "" && id) {
                window.location.assign(`modulo.php?competencia_id=${encodeURIComponent(id)}`);
                return;
            }
            reloadPreservingFilters();
        });
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

    (() => {
        const params = new URLSearchParams(window.location.search);
        const action = params.get("action");
        const id = params.get("id");
        const cpf = params.get("cpf");

        if (action === "new") {
            registrationForm.open({ cpf: maskCpf(cpf || ""), status: "em_analise", prioridade: "normal", quantidade_membros: 1 });
        } else if (action === "view" && /^\d+$/.test(id || "")) {
            detailViewer.load(id);
        } else if (action === "edit" && /^\d+$/.test(id || "")) {
            detailViewer.load(id, { edit: true });
        } else if (action === "delivery" && /^\d+$/.test(id || "")) {
            const trigger = qs(`[data-open-delivery][data-registration-id="${CSS.escape(id)}"]`);
            trigger?.click();
        } else if (action === "competence") {
            qs("[data-open-edit-competence]")?.click();
        } else if (action === "new-competence") {
            qs("[data-open-new-competence]")?.click();
        }
    })();
})();
