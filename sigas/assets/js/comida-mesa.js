(() => {
    "use strict";

    const root = document.querySelector("[data-comida-mesa-consulta]");
    if (!root) return;

    const form = root.querySelector("#cpfLookupForm");
    const cpf = root.querySelector("#cpfLookupInput");
    const result = root.querySelector("#cpfLookupResult");
    const submit = root.querySelector("[data-cpf-submit]");
    let controller = null;

    document.querySelectorAll("[data-toggle-advanced]").forEach((button) => {
        button.addEventListener("click", () => {
            const advanced = document.querySelector("#advancedFilters");
            advanced?.classList.toggle("show");
            button.setAttribute("aria-expanded", String(advanced?.classList.contains("show") || false));
        });
    });

    const escapeHTML = (value) => String(value ?? "")
        .replaceAll("&", "&amp;")
        .replaceAll("<", "&lt;")
        .replaceAll(">", "&gt;")
        .replaceAll('"', "&quot;")
        .replaceAll("'", "&#039;");

    const digits = (value) => String(value || "").replace(/\D+/g, "");

    const maskCpf = (value) => {
        const numbers = digits(value).slice(0, 11);
        let output = numbers;
        if (numbers.length > 3) output = `${numbers.slice(0, 3)}.${numbers.slice(3)}`;
        if (numbers.length > 6) output = `${numbers.slice(0, 3)}.${numbers.slice(3, 6)}.${numbers.slice(6)}`;
        if (numbers.length > 9) output = `${numbers.slice(0, 3)}.${numbers.slice(3, 6)}.${numbers.slice(6, 9)}-${numbers.slice(9)}`;
        return output;
    };

    const render = (html) => {
        if (result) result.innerHTML = html;
    };

    const statePanel = (icon, title, text, tone = "info") => `
        <div class="alert-soft ${tone}">
            <i class="bi bi-${icon}" aria-hidden="true"></i>
            <div><strong>${escapeHTML(title)}</strong><br><span>${escapeHTML(text)}</span></div>
        </div>
    `;

    const renderResponse = (data) => {
        if (!data?.ok) {
            render(statePanel("exclamation-octagon", "Consulta indisponível", data?.error || "Não foi possível consultar o CPF.", "warning"));
            return;
        }

        if (data.state === "inscrito") {
            render(`
                ${statePanel("person-check", "Pessoa já inscrita", "Não é possível duplicar inscrição.", "success")}
                <dl class="row small mt-3 mb-0">
                    <dt class="col-5">Nome</dt><dd class="col-7">${escapeHTML(data.person?.name)}</dd>
                    <dt class="col-5">CPF</dt><dd class="col-7">${escapeHTML(data.person?.cpf_masked)}</dd>
                    <dt class="col-5">Família</dt><dd class="col-7">${escapeHTML(data.family?.code || "Sem código")}</dd>
                    <dt class="col-5">Programa</dt><dd class="col-7">${escapeHTML(data.registration?.status_label)}</dd>
                    <dt class="col-5">Polo</dt><dd class="col-7">${escapeHTML(data.registration?.pole || "Sem polo")}</dd>
                    <dt class="col-5">Competência</dt><dd class="col-7">${escapeHTML(data.competence?.label || "Sem competência")}</dd>
                    <dt class="col-5">Entrega</dt><dd class="col-7">${escapeHTML(data.delivery?.status_label)}</dd>
                </dl>
            `);
            return;
        }

        if (data.state === "pessoa_sem_inscricao") {
            render(`
                ${statePanel("person-plus", "Pessoa localizada sem inscrição", "Cadastro será habilitado na próxima etapa.", "info")}
                <dl class="row small mt-3 mb-3">
                    <dt class="col-5">Nome</dt><dd class="col-7">${escapeHTML(data.person?.name)}</dd>
                    <dt class="col-5">CPF</dt><dd class="col-7">${escapeHTML(data.person?.cpf_masked)}</dd>
                    <dt class="col-5">Família</dt><dd class="col-7">${escapeHTML(data.family?.code || "Sem família vinculada")}</dd>
                </dl>
                <button class="btn btn-primary w-100" type="button" disabled>Continuar cadastro</button>
            `);
            return;
        }

        render(`
            ${statePanel("search", "Pessoa não localizada", "Cadastro será habilitado na próxima etapa.", "warning")}
            <button class="btn btn-primary w-100 mt-3" type="button" disabled>Iniciar novo cadastro</button>
        `);
    };

    cpf?.addEventListener("input", () => {
        cpf.value = maskCpf(cpf.value);
        cpf.classList.remove("is-invalid");
    });

    document.addEventListener("click", (event) => {
        const trigger = event.target.closest("[data-consult-cpf]");
        if (!trigger || !cpf) return;
        cpf.value = maskCpf(trigger.dataset.consultCpf || "");
        render("");
    });

    form?.addEventListener("submit", async (event) => {
        event.preventDefault();
        if (!cpf || !submit) return;

        if (digits(cpf.value).length !== 11) {
            cpf.classList.add("is-invalid");
            render(statePanel("exclamation-triangle", "CPF inválido", "Informe um CPF com 11 números.", "warning"));
            cpf.focus();
            return;
        }

        if (controller) controller.abort();
        controller = new AbortController();
        submit.disabled = true;
        render(statePanel("hourglass-split", "Consultando", "Aguarde enquanto verificamos o cadastro.", "info"));

        try {
            const response = await fetch(form.action, {
                method: "POST",
                body: new FormData(form),
                headers: { "Accept": "application/json" },
                signal: controller.signal
            });
            const data = await response.json();
            renderResponse(data);
        } catch (error) {
            if (error.name !== "AbortError") {
                render(statePanel("wifi-off", "Erro de comunicação", "Não foi possível concluir a consulta.", "warning"));
            }
        } finally {
            submit.disabled = false;
        }
    });
})();
