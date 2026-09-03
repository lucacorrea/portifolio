(() => {
    "use strict";

    const qs = (selector, root = document) => root.querySelector(selector);
    const qsa = (selector, root = document) => [...root.querySelectorAll(selector)];
    const text = (element) => String(element?.textContent || "").replace(/\s+/g, " ").trim();

    const resolutionFor = (reason) => {
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
                    "Abra a conferência e confirme se esta pessoa deve continuar como integrante da família atual ou se deve ser responsável de outra família.",
                    "Não crie uma segunda pessoa com o mesmo CPF para contornar o conflito.",
                    "Corrija o vínculo familiar no cadastro central conforme a situação real.",
                    "Depois, volte à importação e use Reprocessar vínculos."
                ]
            };
        }

        if (normalized.includes("cpf regularizado já pertence a outro cadastro") || normalized.includes("cpf regularizado ja pertence a outro cadastro")) {
            return {
                title: "CPF já pertence a outro cadastro",
                steps: [
                    "Abra a conferência e compare o registro importado com a pessoa que já possui esse CPF.",
                    "Confirme qual cadastro representa a mesma pessoa e corrija o vínculo, sem duplicar o CPF.",
                    "Se houver cadastro provisório duplicado, faça a regularização/unificação pelo fluxo administrativo definido para o SIGAS.",
                    "Após a correção, use Reprocessar vínculos."
                ]
            };
        }

        if (normalized.includes("pessoa com cpf diferente") || normalized.includes("cpf diferente")) {
            return {
                title: "Item ligado a uma pessoa com CPF diferente",
                steps: [
                    "Confira o CPF informado na importação e o CPF existente na pessoa vinculada.",
                    "Determine qual identificação está correta usando os documentos do beneficiário.",
                    "Corrija o vínculo ou o dado cadastral correto; não substitua CPF sem conferência.",
                    "Depois, use Reprocessar vínculos na importação."
                ]
            };
        }

        if (normalized.includes("família já possui inscrição") || normalized.includes("familia ja possui inscricao")) {
            return {
                title: "Família já possui inscrição no programa",
                steps: [
                    "Abra a conferência e localize a inscrição já existente dessa família.",
                    "Confirme se a linha importada é uma duplicidade ou se deve atualizar o cadastro existente.",
                    "Preserve uma única inscrição válida para a família.",
                    "Depois da correção, reprocesse os vínculos da importação."
                ]
            };
        }

        return {
            title: "Revisar o vínculo cadastral",
            steps: [
                "Abra a conferência da importação e revise o motivo apresentado pelo sistema.",
                "Corrija pessoa, CPF, família ou decisão do programa conforme o dado real.",
                "Não crie registros duplicados para eliminar o aviso.",
                "Após corrigir, use Reprocessar vínculos para tentar efetivar a inscrição novamente."
            ]
        };
    };

    const reviewHrefFor = (href, search) => {
        if (!href) return "";

        try {
            // beneficiarios.php já está dentro de /comida-mesa/. Os links PHP do módulo
            // usam o prefixo "comida-mesa/" pensando na raiz do SIGAS; resolvê-los contra
            // window.location.href duplicaria o segmento (/comida-mesa/comida-mesa/).
            // A raiz do SIGAS é um nível acima da página atual.
            const sigasRoot = new URL("../", window.location.href);
            const url = new URL(href, sigasRoot);

            // O padrão da tela é "Pendente". Conflitos já foram confirmados,
            // portanto o acesso vindo da lista principal precisa pesquisar em TODAS as situações.
            url.searchParams.set("review_situation", "");
            url.searchParams.set("review_search", String(search || "").trim());
            url.searchParams.delete("review_page");
            url.hash = "lista-conferencia";
            return url.href;
        } catch (_) {
            return href;
        }
    };

    const prepareRows = () => {
        qsa(".cm-data-table tbody tr").forEach((row) => {
            const cells = row.querySelectorAll("td");
            if (cells.length < 8) return;

            const conflict = qsa(".cm-status", cells[5]).some((status) => text(status) === "Conflito de vínculo");
            if (!conflict) return;

            const name = text(cells[1].querySelector("strong")) || "";
            const reviewLink = cells[5].querySelector('a[href*="importar-beneficiarios.php"]');
            if (reviewLink) {
                const correctedHref = reviewHrefFor(reviewLink.getAttribute("href") || "", name);
                if (correctedHref) reviewLink.href = correctedHref;
            }

            row.dataset.cmConflictRow = "1";
            row.tabIndex = 0;
            row.setAttribute("role", "button");
            row.setAttribute("aria-label", `Ver como regularizar ${name || "beneficiário"}`);
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
        const reviewLink = cells[5].querySelector('a[href*="importar-beneficiarios.php"]');
        const resolution = resolutionFor(reason);

        const set = (selector, value) => {
            const element = qs(selector, root);
            if (element) element.textContent = value;
        };

        set("[data-cm-conflict-name]", name);
        set("[data-cm-conflict-code]", code);
        set("[data-cm-conflict-location]", location);
        set("[data-cm-conflict-program]", program);
        set("[data-cm-conflict-delivery]", delivery);
        set("[data-cm-conflict-reason]", reason);
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
            if (reviewLink) {
                const correctedHref = reviewHrefFor(reviewLink.getAttribute("href") || "", name);
                reviewButton.hidden = false;
                reviewButton.href = correctedHref || reviewLink.href;
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