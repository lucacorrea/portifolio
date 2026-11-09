(() => {
    "use strict";

    const table = document.querySelector(".cm-import-table");
    if (!table || !window.bootstrap) return;

    const text = (element) => String(element?.textContent || "").replace(/\s+/g, " ").trim();
    const digits = (value) => String(value || "").replace(/\D+/g, "");
    const valueOrDash = (value) => {
        const normalized = String(value ?? "").trim();
        return normalized === "" ? "—" : normalized;
    };
    const formatCpf = (value) => {
        const cpf = digits(value);
        return cpf.length === 11 ? `${cpf.slice(0, 3)}.${cpf.slice(3, 6)}.${cpf.slice(6, 9)}-${cpf.slice(9)}` : valueOrDash(value);
    };
    const formatDate = (value, withTime = false) => {
        const raw = String(value || "").trim();
        if (!raw) return "—";
        const normalized = raw.includes("T") ? raw : raw.replace(" ", "T");
        const date = new Date(normalized);
        if (Number.isNaN(date.getTime())) return raw;
        return new Intl.DateTimeFormat("pt-BR", {
            dateStyle: "short",
            ...(withTime ? { timeStyle: "short" } : {})
        }).format(date);
    };
    const formatMoney = (value) => {
        if (value === null || value === undefined || value === "") return "—";
        const number = Number(value);
        if (!Number.isFinite(number)) return valueOrDash(value);
        return new Intl.NumberFormat("pt-BR", { style: "currency", currency: "BRL" }).format(number);
    };

    let activeRow = null;

    const modalMarkup = `
        <div class="modal fade" id="importReviewActionModal" tabindex="-1" aria-labelledby="importReviewActionTitle" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable cm-action-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <div>
                            <div class="eyebrow"><i class="bi bi-grid"></i> Ações da conferência</div>
                            <h2 class="modal-title" id="importReviewActionTitle" data-import-action-name>Beneficiário</h2>
                            <p class="cm-modal-subtitle"><span data-import-action-document>Documento não informado</span> · <span data-import-action-order>Sem ordem</span></p>
                        </div>
                        <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Fechar"></button>
                    </div>
                    <div class="modal-body">
                        <div class="cm-action-status-grid">
                            <div><span>Situação no programa</span><strong data-import-action-program>—</strong></div>
                            <div><span>Vínculo</span><strong data-import-action-link>—</strong></div>
                        </div>
                        <div class="cm-action-grid mt-3">
                            <button type="button" class="cm-action-card" data-import-action-command="details">
                                <i class="bi bi-eye"></i><span><strong>Ver dados completos</strong><small>Importação, cadastro, vínculo e cruzamentos</small></span><i class="bi bi-chevron-right"></i>
                            </button>
                            <button type="button" class="cm-action-card" data-import-action-command="beneficiary">
                                <i class="bi bi-check-circle"></i><span><strong>Confirmar como beneficiário</strong><small>Preserva o CPF como chave de vínculo</small></span><i class="bi bi-chevron-right"></i>
                            </button>
                            <button type="button" class="cm-action-card" data-import-action-command="waitlist">
                                <i class="bi bi-hourglass-split"></i><span><strong>Enviar para lista de espera</strong><small>Atualiza a situação desta pessoa</small></span><i class="bi bi-chevron-right"></i>
                            </button>
                        </div>
                        <p class="cm-action-note">As ações utilizam os mesmos formulários, permissões e validações já existentes na conferência.</p>
                    </div>
                    <div class="modal-footer"><button class="btn btn-light" type="button" data-bs-dismiss="modal">Fechar</button></div>
                </div>
            </div>
        </div>

        <div class="modal fade" id="importReviewDetailModal" tabindex="-1" aria-labelledby="importReviewDetailTitle" aria-hidden="true">
            <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <div>
                            <div class="eyebrow"><i class="bi bi-person-vcard"></i> Dados completos da conferência</div>
                            <h2 class="modal-title" id="importReviewDetailTitle" data-import-detail-name>Beneficiário</h2>
                            <p class="cm-modal-subtitle" data-import-detail-subtitle>Carregando informações...</p>
                        </div>
                        <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Fechar"></button>
                    </div>
                    <div class="modal-body" data-import-detail-body>
                        <div class="cm-empty-state"><i class="bi bi-hourglass-split"></i><strong>Carregando dados</strong></div>
                    </div>
                    <div class="modal-footer"><button class="btn btn-light" type="button" data-bs-dismiss="modal">Fechar</button></div>
                </div>
            </div>
        </div>`;

    const wrapper = document.createElement("div");
    wrapper.innerHTML = modalMarkup;
    document.body.append(...wrapper.children);

    const actionModalElement = document.getElementById("importReviewActionModal");
    const detailModalElement = document.getElementById("importReviewDetailModal");
    const actionModal = bootstrap.Modal.getOrCreateInstance(actionModalElement);
    const detailModal = bootstrap.Modal.getOrCreateInstance(detailModalElement);

    const setText = (root, selector, value) => {
        const element = root.querySelector(selector);
        if (element) element.textContent = valueOrDash(value);
    };

    const rowMeta = (row) => {
        const cells = row.querySelectorAll("td");
        const itemInput = row.querySelector('input[name="item_id"]');
        const importInput = row.querySelector('input[name="import_id"]');
        return {
            itemId: Number(itemInput?.value || 0),
            importId: Number(importInput?.value || 0),
            order: text(cells[1]?.querySelector("strong")) || text(cells[1]),
            name: text(cells[2]?.querySelector("strong")) || "Beneficiário",
            document: text(cells[3]?.querySelector("strong")) || "Documento não informado",
            program: text(cells[7]?.querySelector(".cm-status")) || "Não informado",
            link: text(cells[8]?.querySelector(".cm-status")) || "Não informado",
            classification: text(cells[5]?.querySelector(".cm-status")) || "Não informado"
        };
    };

    const formFor = (row, decision) => {
        return [...row.querySelectorAll("form")].find((form) => form.querySelector(`input[name="program_decision"][value="${decision}"]`)) || null;
    };

    const openActions = (row) => {
        activeRow = row;
        const meta = rowMeta(row);
        setText(actionModalElement, "[data-import-action-name]", meta.name);
        setText(actionModalElement, "[data-import-action-document]", meta.document);
        setText(actionModalElement, "[data-import-action-order]", meta.order ? `Ordem ${meta.order}` : "Sem ordem");
        setText(actionModalElement, "[data-import-action-program]", meta.program);
        setText(actionModalElement, "[data-import-action-link]", meta.link);
        actionModal.show();
    };

    const field = (label, value) => {
        const col = document.createElement("div");
        col.className = "col-12 col-md-6 col-xl-4";
        const card = document.createElement("div");
        card.className = "border rounded-3 p-3 h-100";
        const small = document.createElement("small");
        small.className = "d-block text-muted mb-1";
        small.textContent = label;
        const strong = document.createElement("strong");
        strong.className = "d-block text-break";
        strong.textContent = valueOrDash(value);
        card.append(small, strong);
        col.append(card);
        return col;
    };

    const section = (title, icon, fields) => {
        const sectionElement = document.createElement("section");
        sectionElement.className = "mb-4";
        const heading = document.createElement("div");
        heading.className = "d-flex align-items-center gap-2 mb-3";
        const iconElement = document.createElement("i");
        iconElement.className = `bi bi-${icon}`;
        const titleElement = document.createElement("h3");
        titleElement.className = "h6 mb-0";
        titleElement.textContent = title;
        heading.append(iconElement, titleElement);
        const row = document.createElement("div");
        row.className = "row g-2";
        fields.forEach(([label, value]) => row.append(field(label, value)));
        sectionElement.append(heading, row);
        return sectionElement;
    };

    const crossSummary = (cross) => {
        if (!cross || typeof cross !== "object") return "Não cruzado";
        if (cross.consultavel !== "sim") return "CPF pendente — sem cruzamento automático por nome";
        const found = [];
        const programs = Array.isArray(cross?.sigas?.programas) ? cross.sigas.programas : [];
        programs.forEach((program) => {
            const name = String(program?.nome || "Programa SIGAS");
            const status = String(program?.situacao || "").trim();
            found.push(status ? `${name}: ${status}` : name);
        });
        if (cross?.sigas?.cadastro_geral && programs.length === 0) found.push("Cadastro geral do SIGAS");
        if (cross?.anexo?.encontrado === "sim") {
            const benefits = Array.isArray(cross?.anexo?.beneficios) ? cross.anexo.beneficios.filter(Boolean) : [];
            found.push(benefits.length ? `ANEXO: ${benefits.join(", ")}` : "ANEXO");
        }
        return found.length ? found.join(" · ") : "Não localizado nas bases cruzadas";
    };

    const renderDetails = (item) => {
        const body = detailModalElement.querySelector("[data-import-detail-body]");
        body.replaceChildren();

        setText(detailModalElement, "[data-import-detail-name]", item?.identificacao?.nome || "Beneficiário");
        const line = item?.importacao?.linha ? `Linha ${item.importacao.linha}` : "Linha não informada";
        const file = item?.importacao?.arquivo || "Arquivo não informado";
        setText(detailModalElement, "[data-import-detail-subtitle]", `${file} · ${line}`);

        body.append(
            section("Identificação importada", "person-vcard", [
                ["Nome", item?.identificacao?.nome],
                ["CPF informado", item?.identificacao?.cpf_informado],
                ["CPF validado", item?.identificacao?.cpf_validado ? formatCpf(item.identificacao.cpf_validado) : "CPF pendente"],
                ["Telefone", item?.identificacao?.telefone_informado],
                ["NIS", item?.identificacao?.nis],
                ["RG", item?.identificacao?.rg],
                ["Data de nascimento", formatDate(item?.identificacao?.data_nascimento)],
                ["Cônjuge", item?.identificacao?.conjuge],
                ["E-mail", item?.identificacao?.email]
            ]),
            section("Endereço e origem", "geo-alt", [
                ["Zona", item?.endereco?.zona],
                ["Logradouro", item?.endereco?.logradouro],
                ["Número", item?.endereco?.numero],
                ["Complemento", item?.endereco?.complemento],
                ["Bairro", item?.endereco?.bairro],
                ["Comunidade", item?.endereco?.comunidade],
                ["Ponto de referência", item?.endereco?.referencia],
                ["CEP", item?.endereco?.cep],
                ["Local/polo informado", item?.endereco?.local_origem]
            ]),
            section("Conferência e situação no programa", "clipboard-check", [
                ["Situação", item?.programa?.situacao],
                ["Classificação", item?.programa?.classificacao],
                ["Pendências/motivos", item?.programa?.motivos],
                ["Status do vínculo", item?.programa?.efetivacao_status],
                ["Motivo da efetivação/conflito", item?.programa?.efetivacao_motivo],
                ["Polo informado", item?.programa?.polo_informado],
                ["Decidido por", item?.programa?.decisor],
                ["Data da decisão", formatDate(item?.programa?.decidido_em, true)],
                ["Cruzamento por CPF", crossSummary(item?.cruzamento)]
            ]),
            section("Vínculo oficial no SIGAS", "link-45deg", [
                ["Pessoa ID", item?.vinculo_oficial?.pessoa_id],
                ["Família ID", item?.vinculo_oficial?.familia_id],
                ["Inscrição ID", item?.vinculo_oficial?.inscricao_id],
                ["Código da família", item?.vinculo_oficial?.familia_codigo],
                ["Nome oficial", item?.vinculo_oficial?.nome],
                ["CPF oficial", item?.vinculo_oficial?.cpf ? formatCpf(item.vinculo_oficial.cpf) : "—"],
                ["NIS oficial", item?.vinculo_oficial?.nis],
                ["RG oficial", item?.vinculo_oficial?.rg],
                ["Nascimento oficial", formatDate(item?.vinculo_oficial?.data_nascimento)],
                ["Telefone oficial", item?.vinculo_oficial?.telefone],
                ["Bairro oficial", item?.vinculo_oficial?.bairro],
                ["Comunidade oficial", item?.vinculo_oficial?.comunidade],
                ["Situação da inscrição", item?.vinculo_oficial?.inscricao_status],
                ["Prioridade", item?.vinculo_oficial?.prioridade],
                ["Polo oficial", item?.vinculo_oficial?.polo],
                ["Data da inscrição", formatDate(item?.vinculo_oficial?.data_inscricao)],
                ["Data da aprovação", formatDate(item?.vinculo_oficial?.data_aprovacao, true)],
                ["Quantidade de membros", item?.vinculo_oficial?.membros],
                ["Renda familiar", formatMoney(item?.vinculo_oficial?.renda)],
                ["Observação", item?.vinculo_oficial?.observacao]
            ]),
            section("Origem da importação", "file-earmark-spreadsheet", [
                ["Carga", item?.importacao?.id ? `#${item.importacao.id}` : "—"],
                ["Arquivo", item?.importacao?.arquivo],
                ["Status da carga", item?.importacao?.status],
                ["Ordem na planilha", item?.importacao?.ordem],
                ["Linha física", item?.importacao?.linha],
                ["Importado em", formatDate(item?.importacao?.importado_em, true)]
            ])
        );
    };

    const loadDetails = async (row) => {
        const meta = rowMeta(row);
        if (!meta.itemId) return;

        actionModal.hide();
        const body = detailModalElement.querySelector("[data-import-detail-body]");
        body.innerHTML = '<div class="cm-empty-state"><i class="bi bi-hourglass-split"></i><strong>Carregando dados completos</strong><span>Aguarde um instante.</span></div>';
        setText(detailModalElement, "[data-import-detail-name]", meta.name);
        setText(detailModalElement, "[data-import-detail-subtitle]", `${meta.document} · ${meta.order || "Sem ordem"}`);
        detailModal.show();

        try {
            const url = new URL("importacao-item.php", window.location.href);
            url.searchParams.set("item_id", String(meta.itemId));
            if (meta.importId) url.searchParams.set("import_id", String(meta.importId));
            const response = await fetch(url, { headers: { Accept: "application/json" }, credentials: "same-origin" });
            const payload = await response.json();
            if (!response.ok || !payload?.ok || !payload?.item) {
                throw new Error(payload?.message || "Não foi possível carregar o registro.");
            }
            renderDetails(payload.item);
        } catch (error) {
            body.replaceChildren();
            const alert = document.createElement("div");
            alert.className = "alert alert-danger mb-0";
            alert.textContent = error instanceof Error ? error.message : "Não foi possível carregar os dados completos deste registro.";
            body.append(alert);
        }
    };

    table.querySelectorAll("tbody tr").forEach((row) => {
        const meta = rowMeta(row);
        if (!meta.itemId) return;
        const cells = row.querySelectorAll("td");
        const actionCell = cells[cells.length - 1];
        if (!actionCell) return;

        actionCell.querySelectorAll("form").forEach((form) => {
            form.hidden = true;
            form.setAttribute("aria-hidden", "true");
        });

        const button = document.createElement("button");
        button.type = "button";
        button.className = "btn btn-light btn-sm";
        button.dataset.importReviewActions = "1";
        button.innerHTML = '<i class="bi bi-grid"></i> Ações';
        actionCell.append(button);

        row.dataset.importReviewRow = "1";
        row.tabIndex = 0;
        row.setAttribute("role", "button");
        row.setAttribute("aria-label", `Abrir ações de ${meta.name}`);
    });

    document.addEventListener("click", (event) => {
        const actionButton = event.target.closest?.("[data-import-review-actions]");
        if (actionButton) {
            const row = actionButton.closest("tr");
            if (row) openActions(row);
            return;
        }

        const row = event.target.closest?.("[data-import-review-row]");
        if (row && !event.target.closest("input,button,a,select,textarea,label,form")) {
            openActions(row);
        }
    });

    document.addEventListener("keydown", (event) => {
        const row = event.target.closest?.("[data-import-review-row]");
        if (!row || !["Enter", " "].includes(event.key)) return;
        if (event.target.matches("input,button,a,select,textarea")) return;
        event.preventDefault();
        openActions(row);
    });

    actionModalElement.addEventListener("click", (event) => {
        const command = event.target.closest?.("[data-import-action-command]")?.dataset.importActionCommand;
        if (!command || !activeRow) return;

        if (command === "details") {
            loadDetails(activeRow);
            return;
        }

        const form = command === "beneficiary"
            ? formFor(activeRow, "Beneficiario")
            : command === "waitlist"
                ? formFor(activeRow, "ListaEspera")
                : null;

        if (form) {
            actionModal.hide();
            form.requestSubmit();
        }
    });
})();
