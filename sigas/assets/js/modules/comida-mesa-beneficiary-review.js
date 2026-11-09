(() => {
    "use strict";

    const applyReview = (row) => {
        if (!row) return;
        const modal = document.querySelector("#beneficiaryActionModal");
        if (!modal) return;

        const status = modal.querySelector("[data-cm-action-review]");
        const reasons = modal.querySelector("[data-cm-action-review-reasons]");
        const reviewStatus = String(row.dataset.reviewStatus || "Sem pendência").trim();
        const reviewReasons = String(row.dataset.reviewReasons || "").trim();

        if (status) status.textContent = reviewStatus || "Sem pendência";
        if (reasons) {
            reasons.textContent = reviewReasons;
            reasons.hidden = reviewReasons === "";
        }
    };

    // Capture garante que a informação seja preenchida antes de o listener padrão
    // abrir a modal de ações do beneficiário.
    document.addEventListener("click", (event) => {
        const row = event.target.closest?.("[data-cm-action-row]");
        if (!row) return;
        applyReview(row);
    }, true);

    document.addEventListener("keydown", (event) => {
        if (!["Enter", " "].includes(event.key)) return;
        const row = event.target.closest?.("[data-cm-action-row]");
        if (!row) return;
        applyReview(row);
    }, true);
})();
