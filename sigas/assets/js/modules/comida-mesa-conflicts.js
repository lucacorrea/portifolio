(() => {
    "use strict";

    const qs = (selector, root = document) => root.querySelector(selector);
    const qsa = (selector, root = document) => [...root.querySelectorAll(selector)];
    const text = (element) => String(element?.textContent || "").replace(/\s+/g, " ").trim();
    const digits = (value) => String(value || "").replace(/\D+/g, "");

    const cpfFromRow = (row) => {
        const identityText = text(row?.querySelector("td:nth-child(2) small"));
        const formatted = identityText.match(/(?:^|\D)(\d{3}\.?\d{3}\.?\d{3}-?\d{2})(?:\D|$)/);
        if (!formatted) return "";
        const cpf = digits(formatted[1]);
        return cpf.length === 11 ? cpf : "";
    };

    const resolutionFor = (reason, cpf) => {
        if (!cpf) {
            return {
                title: "CPF precisa ser regularizado",
                steps: [
                    "Confira o documento original do beneficiário e informe um CPF válido de 11 dígitos.",
                    "Não localize nem vincule esta pessoa pelo nome, pois podem existir homônimos.",
                    "Depois de regularizar o CPF, volte à conferência e use Reprocessar vínculos.",
                    "O CPF válido passa a ser a chave para localizar a pessoa e os benefícios existentes."
                ]
            };
        }

        const normalized = String(reason || "").toLocaleLowerCase("pt-BR");

        if (normalized.includes("outra linha confirmada com decisão diferente") || normalized.includes("mesmo cpf possui outra linha")) {
            return {
                title: "Mesmo CPF com decisões diferentes",
                steps: [
                    "Abra a conferência da importação e localize todas as linhas desse CPF.",
                    "Confirme qual é a situação correta da pessoa: Beneficiária ou Lista de espera.",
                    "Corrija a linha divergente para que o mesmo CPF não tenha decisões opostas.",
                    "Depois da correção, use Reprocessar vínculos para o sistema tentar efetivar a inscrição novamente."
                ]
            };
        }

        if (normalized.includes("integrante de outra família")) {
            return {
                title: "Pessoa já vinculada como integrante de outra família",
                steps: [
                    "Use o CPF para conferir em qual família a pessoa está vinculada.",
                    "Confirme se deve continuar como integrante ou se deve ser responsável de outra família.",
                    "Não crie uma segunda pessoa com o mesmo CPF para contornar o conflito.",
                    "Depois de corrigir o vínculo familiar, use Reprocessar vínculos."
                ]
            };
        }

        if (normalized.includes("cpf regularizado já pertence a outro cadastro") || normalized.includes("cpf regularizado ja pertence a outro cadastro")) {
            return {
                title: "CPF já pertence a outro cadastro",
                steps: [
                    "Localize o cadastro existente exclusivamente pelo CPF.",
                    "Compare o registro importado com a pessoa que já possui esse CPF.",
                    "Corrija ou unifique o vínculo sem criar outra pessoa com o mesmo CPF.",
                    "Após a correção, use Reprocessar vínculos."
                ]
            };
        }

        if (normalized.includes("pessoa com cpf diferente") || normalized.includes("cpf diferente")) {
            return {
                title: "Item ligado a uma pessoa com CPF diferente",
                steps: [
                    "Confira o CPF informado na importação e o CPF existente na pessoa vinculada.",
                    "Determine qual CPF está correto usando o documento do beneficiário.",
                    "Corrija o vínculo; não substitua CPF sem conferência documental.",
                    "Depois, use Reprocessar vínculos na importação."
                ]
            };
        }

        if (normalized.includes("família já possui inscrição") || normalized.includes("familia ja possui inscricao")) {
            return {
                title: "Família já possui inscrição no programa",
                steps: [
                    "Use o CPF do responsável para localizar a inscrição existente.",
                    "Confirme se a linha importada é duplicidade ou deve aproveitar a inscrição atual.",
                    "Preserve uma única pessoa e uma única inscrição válida para o CPF.",
                    "Depois da correção, reprocesse os vínculos da importação."
                ]
            };
        }

        return {
            title: "Revisar o vínculo cadastral pelo CPF",
            steps: [
                "Abra a conferência filtrada pelo CPF deste beneficiário.",
                "Revise pessoa, família e situação do programa associadas a esse CPF.",
                "Não crie registros duplicados nem use nome como chave de vínculo.",
                "Após corrigir, use Reprocessar vínculos para tentar efetivar a inscrição novamente."
            ]
        };
    };

    const reviewHrefFor = (href, cpf) => {
        if (!href || !cpf) return "";

        try {
            const sigasRoot = new URL("../", window.location.href);
            const url = new URL(href, sigasRoot);

            // Conflitos já estão confirmados. A conferência deve pesquisar todas as
            // situações, mas sempre usando CPF exato como identidade da pessoa.
            url.searchParams.set("review_situation", "");
            url.searchParams.set("review_search", cpf);
            url.searchParams.delete("review_page");
            url.hash = "lista-conferencia";
            return url.href;
        } catch (_) {
            return "";
        }
    };

    const prepareRows = () => {
        qsa(".cm-data-table tbody tr").forEach((row) => {
            const cells = row.querySelectorAll("td");
            if (cells.length < 8) return;

            const conflict = qsa(".cm-status", cells[5]).some((status) => text(status) === "Conflito de vínculo");
            if (!conflict) return;

            const name = text(cells[1].querySelector("strong")) || "";
            const cpf = cpfFromRow(row);
            const reviewLink = cells[5].querySelector('a[href*="importar-beneficiarios.php"]');

            row.dataset.cmConflictRow = "1";
            row.dataset.cmConflictCpf = cpf;
            row.tabIndex = 0;
            row.setAttribute("role", "button");
            row.setAttribute("aria-label", `Ver como regularizar ${name || "beneficiário"}`);

            if (reviewLink) {
                const correctedHref = reviewHrefFor(reviewLink.getAttribute("href") || "", cpf);
                if (correctedHref) {
                    reviewLink.href = correctedHref;
                    reviewLink.textContent = "Revisar pelo CPF";
                } else {
                    reviewLink.removeAttribute("href");
                    reviewLink.textContent = "Regularizar CPF primeiro";
                    reviewLink.setAttribute("aria-disabled", "true");
                }
            }
        });
    };

    const openConflict = (row) => {
        const root = qs("#beneficiaryConflictModal");
        if (!root || !window.bootstrap) return;

        const cells = row.querySelectorAll("td");
        if (cells.length < 8) return;

        const name = text(cells[1].querySelector("strong")) || "Beneficiário com conflito";
        const code = text(cells[0].querySelector("strong")) || "Sem código";
        const location = text(cells[2]) || "Localidade não informada";
        const program = text(cells[4].querySelector(".cm-status")) || "Não informado";
        const delivery = text(cells[6].querySelector(".cm-status")) || "Bloqueada";
        const reason = text(cells[5].querySelector("small")) || "O vínculo ao cadastro central precisa ser revisado antes da entrega.";
        const cpf = row.dataset.cmConflictCpf || cpfFromRow(row);
        const reviewLink = cells[5].querySelector('a[href*="importar-beneficiarios.php"]');
        const resolution = resolutionFor(reason, cpf);

        const set = (selector, value) => {
            const element = qs(selector, root);
            if (element) element.textContent = value;
        };

        set("[data-cm-conflict-name]", name);
        set("[data-cm-conflict-code]", cpf ? `${code} · CPF ${cpf}` : `${code} · CPF pendente`);
        set("[data-cm-conflict-location]", location);
        set("[data-cm-conflict-program]", program);
        set("[data-cm-conflict-delivery]", delivery);
        set("[data-cm-conflict-reason]", cpf ? reason : `${reason} CPF válido não identificado; o sistema não fará busca por nome.`);
        set("[data-cm-conflict-resolution-title]", resolution.title);

        const steps = qs("[data-cm-conflict-steps]", root);
        if (steps) {
            steps.replaceChildren(...resolution.steps.map((step) => {
                const item = document.createElement("li");
                item.textContent = step;
                return item;
            }));
        }

        const reviewButton = qs("[data-cm-conflict-review]", root);
        if (reviewButton) {
            const correctedHref = reviewLink ? reviewHrefFor(reviewLink.getAttribute("href") || "", cpf) : "";
            if (correctedHref) {
                reviewButton.hidden = false;
                reviewButton.href = correctedHref;
                reviewButton.removeAttribute("aria-disabled");
                reviewButton.innerHTML = '<i class="bi bi-search"></i> Abrir conferência pelo CPF';
            } else {
                reviewButton.hidden = true;
                reviewButton.removeAttribute("href");
            }
        }

        bootstrap.Modal.getOrCreateInstance(root).show();
    };

    const init = () => {
        prepareRows();

        document.addEventListener("click", (event) => {
            const row = event.target.closest?.("[data-cm-conflict-row]");
            if (!row) return;
            if (event.target.closest("a,button,input,select,textarea,label,.dropdown-menu")) return;
            openConflict(row);
        });

        document.addEventListener("keydown", (event) => {
            const row = event.target.closest?.("[data-cm-conflict-row]");
            if (!row || !["Enter", " "].includes(event.key)) return;
            event.preventDefault();
            openConflict(row);
        });
    };

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", init, { once: true });
    } else {
        init();
    }
})();
