(() => {
    "use strict";

    const escapeHtml = (value) => String(value)
        .replaceAll("&", "&amp;")
        .replaceAll("<", "&lt;")
        .replaceAll(">", "&gt;")
        .replaceAll('"', "&quot;")
        .replaceAll("'", "&#039;");

    const toastContainer = document.getElementById("toastContainer");

    const showToast = (message) => {
        if (!toastContainer) return;

        const toast = document.createElement("div");
        toast.className = "sigas-demo-toast";
        toast.setAttribute("role", "status");
        toast.innerHTML = `
            <i class="bi bi-info-circle" aria-hidden="true"></i>
            <span>${escapeHtml(message)}</span>
            <button type="button" aria-label="Fechar aviso">
                <i class="bi bi-x-lg" aria-hidden="true"></i>
            </button>
        `;

        const close = () => toast.remove();
        toast.querySelector("button")?.addEventListener("click", close);
        toastContainer.appendChild(toast);
        window.setTimeout(close, 3800);
    };

    document.querySelectorAll("[data-demo-action]").forEach((link) => {
        link.addEventListener("click", (event) => {
            event.preventDefault();
            const action = link.dataset.demoAction || "suporte";
            showToast(`A área de ${action} é demonstrativa neste protótipo.`);
        });
    });

    const form = document.getElementById("loginForm");
    const identity = document.getElementById("loginIdentity");
    const password = document.getElementById("loginPassword");
    const toggle = document.getElementById("passwordToggle");
    const capsWarning = document.getElementById("capsLockWarning");
    const submit = document.getElementById("loginSubmit");
    const feedback = document.getElementById("loginFeedback");

    if (!form || !identity || !password || !submit || !feedback) return;

    const setFieldValidity = (field) => {
        const group = field.closest(".sigas-form-group");
        const valid = field.checkValidity();
        field.setAttribute("aria-invalid", valid ? "false" : "true");
        group?.classList.toggle("is-invalid", !valid);
        return valid;
    };

    const clearFeedback = () => {
        feedback.textContent = "";
        feedback.className = "sigas-login-feedback";
    };

    [identity, password].forEach((field) => {
        field.addEventListener("input", () => {
            if (field.getAttribute("aria-invalid") === "true") {
                setFieldValidity(field);
            }
            clearFeedback();
        });

        field.addEventListener("blur", () => {
            if (field.value.trim()) setFieldValidity(field);
        });
    });

    toggle?.addEventListener("click", () => {
        const isVisible = password.type === "text";
        password.type = isVisible ? "password" : "text";
        toggle.setAttribute("aria-label", isVisible ? "Mostrar senha" : "Ocultar senha");
        toggle.setAttribute("aria-pressed", isVisible ? "false" : "true");

        const icon = toggle.querySelector("i");
        if (icon) icon.className = isVisible ? "bi bi-eye" : "bi bi-eye-slash";
        password.focus({ preventScroll: true });
    });

    const updateCapsLock = (event) => {
        if (!capsWarning || typeof event.getModifierState !== "function") return;
        capsWarning.hidden = !event.getModifierState("CapsLock");
    };

    password.addEventListener("keydown", updateCapsLock);
    password.addEventListener("keyup", updateCapsLock);
    password.addEventListener("blur", () => {
        if (capsWarning) capsWarning.hidden = true;
    });

    form.addEventListener("submit", (event) => {
        event.preventDefault();
        clearFeedback();

        const identityValid = setFieldValidity(identity);
        const passwordValid = setFieldValidity(password);

        if (!identityValid || !passwordValid) {
            feedback.textContent = "Revise os campos destacados para continuar.";
            feedback.classList.add("is-error");
            (identityValid ? password : identity).focus();
            return;
        }

        submit.disabled = true;
        submit.classList.add("is-loading");
        submit.setAttribute("aria-busy", "true");
        feedback.textContent = "Acesso validado. Abrindo o painel...";
        feedback.classList.add("is-success");

        window.setTimeout(() => {
            window.location.href = "dashboard.html";
        }, 900);
    });
})();
