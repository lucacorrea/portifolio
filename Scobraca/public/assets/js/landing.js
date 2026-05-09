(function () {
    "use strict";

    const prefersReducedMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
    const header = document.querySelector("[data-header]");
    const menuToggle = document.querySelector("[data-menu-toggle]");
    const nav = document.querySelector("[data-nav]");
    const revealItems = document.querySelectorAll(".reveal");
    const counters = document.querySelectorAll("[data-counter]");
    const faqList = document.querySelector("[data-faq-list]");
    const leadForm = document.querySelector("[data-lead-form]");

    function setHeaderState() {
        if (!header) {
            return;
        }

        header.classList.toggle("is-scrolled", window.scrollY > 8);
    }

    function closeMenu() {
        if (!header || !menuToggle) {
            return;
        }

        header.classList.remove("nav-active");
        document.body.classList.remove("nav-open");
        menuToggle.setAttribute("aria-expanded", "false");
    }

    function toggleMenu() {
        if (!header || !menuToggle) {
            return;
        }

        const isOpen = header.classList.toggle("nav-active");
        document.body.classList.toggle("nav-open", isOpen);
        menuToggle.setAttribute("aria-expanded", String(isOpen));
    }

    function initMobileMenu() {
        if (!menuToggle || !nav) {
            return;
        }

        menuToggle.addEventListener("click", toggleMenu);

        nav.querySelectorAll("a").forEach((link) => {
            link.addEventListener("click", closeMenu);
        });

        document.addEventListener("keydown", (event) => {
            if (event.key === "Escape") {
                closeMenu();
            }
        });

        document.addEventListener("click", (event) => {
            const clickedInsideMenu = nav.contains(event.target);
            const clickedToggle = menuToggle.contains(event.target);

            if (!clickedInsideMenu && !clickedToggle) {
                closeMenu();
            }
        });
    }

    function initSmoothScroll() {
        document.querySelectorAll('a[href^="#"]').forEach((link) => {
            link.addEventListener("click", (event) => {
                const targetId = link.getAttribute("href");

                if (!targetId || targetId === "#") {
                    return;
                }

                const target = document.querySelector(targetId);

                if (!target) {
                    return;
                }

                event.preventDefault();
                target.scrollIntoView({
                    behavior: prefersReducedMotion ? "auto" : "smooth",
                    block: "start"
                });
            });
        });
    }

    function initReveal() {
        if (!revealItems.length) {
            return;
        }

        if (prefersReducedMotion || !("IntersectionObserver" in window)) {
            revealItems.forEach((item) => item.classList.add("is-visible"));
            return;
        }

        const observer = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    entry.target.classList.add("is-visible");
                    observer.unobserve(entry.target);
                }
            });
        }, {
            threshold: 0.14,
            rootMargin: "0px 0px -40px 0px"
        });

        revealItems.forEach((item) => observer.observe(item));
    }

    function formatCounterValue(value, prefix, suffix) {
        return `${prefix || ""}${Math.round(value).toLocaleString("pt-BR")}${suffix || ""}`;
    }

    function animateCounter(counter) {
        const target = Number(counter.dataset.count || "0");
        const prefix = counter.dataset.prefix || "";
        const suffix = counter.dataset.suffix || "";

        if (!Number.isFinite(target)) {
            return;
        }

        if (prefersReducedMotion) {
            counter.textContent = formatCounterValue(target, prefix, suffix);
            return;
        }

        const duration = 1100;
        const start = performance.now();

        function tick(now) {
            const elapsed = Math.min((now - start) / duration, 1);
            const eased = 1 - Math.pow(1 - elapsed, 3);
            counter.textContent = formatCounterValue(target * eased, prefix, suffix);

            if (elapsed < 1) {
                window.requestAnimationFrame(tick);
            }
        }

        window.requestAnimationFrame(tick);
    }

    function initCounters() {
        if (!counters.length) {
            return;
        }

        if (!("IntersectionObserver" in window)) {
            counters.forEach(animateCounter);
            return;
        }

        const observer = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    animateCounter(entry.target);
                    observer.unobserve(entry.target);
                }
            });
        }, {
            threshold: 0.4
        });

        counters.forEach((counter) => observer.observe(counter));
    }

    function initFaq() {
        if (!faqList) {
            return;
        }

        faqList.addEventListener("click", (event) => {
            const button = event.target.closest("button");

            if (!button || !faqList.contains(button)) {
                return;
            }

            const item = button.closest(".faq-item");

            if (!item) {
                return;
            }

            const isOpen = item.classList.contains("is-open");

            faqList.querySelectorAll(".faq-item").forEach((faqItem) => {
                faqItem.classList.remove("is-open");
                const faqButton = faqItem.querySelector("button");

                if (faqButton) {
                    faqButton.setAttribute("aria-expanded", "false");
                }
            });

            if (!isOpen) {
                item.classList.add("is-open");
                button.setAttribute("aria-expanded", "true");
            }
        });
    }

    function sanitizeInput(value) {
        return String(value || "")
            .trim()
            .replace(/[<>]/g, "")
            .replace(/\s+/g, " ");
    }

    function isValidEmail(email) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(email);
    }

    function normalizePhone(phone) {
        return String(phone || "").replace(/\D/g, "");
    }

    function setFieldError(form, fieldName, message) {
        const field = form.elements[fieldName];
        const error = form.querySelector(`[data-error-for="${fieldName}"]`);
        const wrapper = field ? field.closest(".form-field") : null;

        if (wrapper) {
            wrapper.classList.toggle("is-invalid", Boolean(message));
        }

        if (error) {
            error.textContent = message || "";
        }
    }

    function clearFormErrors(form) {
        ["name", "email", "phone", "business"].forEach((fieldName) => {
            setFieldError(form, fieldName, "");
        });
    }

    function setFormStatus(form, message, isError) {
        const status = form.querySelector("[data-form-status]");

        if (!status) {
            return;
        }

        status.textContent = message;
        status.classList.toggle("is-error", Boolean(isError));
    }

    function handleLeadSubmit(event) {
        event.preventDefault();

        const form = event.currentTarget;
        const lead = {
            name: sanitizeInput(form.elements.name.value),
            email: sanitizeInput(form.elements.email.value).toLowerCase(),
            phone: sanitizeInput(form.elements.phone.value),
            business: sanitizeInput(form.elements.business.value)
        };

        const phoneDigits = normalizePhone(lead.phone);
        let hasError = false;

        clearFormErrors(form);
        setFormStatus(form, "", false);

        if (!lead.name) {
            setFieldError(form, "name", "Informe seu nome.");
            hasError = true;
        }

        if (!lead.email || !isValidEmail(lead.email)) {
            setFieldError(form, "email", "Informe um e-mail válido.");
            hasError = true;
        }

        if (!phoneDigits || phoneDigits.length < 10 || phoneDigits.length > 11) {
            setFieldError(form, "phone", "Informe um WhatsApp válido com DDD.");
            hasError = true;
        }

        if (!lead.business) {
            setFieldError(form, "business", "Selecione o tipo de negócio.");
            hasError = true;
        }

        if (hasError) {
            setFormStatus(form, "Revise os campos destacados para continuar.", true);
            return;
        }

        const payload = {
            ...lead,
            phoneDigits,
            source: "landing_fluxpay",
            capturedAt: new Date().toISOString()
        };

        // Integração futura: enviar payload para o endpoint do back-end com fetch().
        // Exemplo: await fetch("/api/leads", { method: "POST", headers: { "Content-Type": "application/json" }, body: JSON.stringify(payload) });
        console.info("Lead FluxPay preparado para integração:", payload);

        setFormStatus(form, `Obrigado, ${lead.name}. Sua solicitação foi registrada para demonstração.`, false);
        form.reset();
    }

    function initLeadForm() {
        if (!leadForm) {
            return;
        }

        leadForm.addEventListener("submit", handleLeadSubmit);

        const phone = leadForm.elements.phone;

        if (phone) {
            phone.addEventListener("input", () => {
                const digits = normalizePhone(phone.value).slice(0, 11);
                const partOne = digits.slice(0, 2);
                const partTwo = digits.length > 10 ? digits.slice(2, 7) : digits.slice(2, 6);
                const partThree = digits.length > 10 ? digits.slice(7, 11) : digits.slice(6, 10);

                if (digits.length <= 2) {
                    phone.value = partOne ? `(${partOne}` : "";
                    return;
                }

                phone.value = `(${partOne}) ${partTwo}${partThree ? `-${partThree}` : ""}`;
            });
        }
    }

    window.addEventListener("scroll", setHeaderState, { passive: true });
    window.addEventListener("resize", setHeaderState);

    setHeaderState();
    initMobileMenu();
    initSmoothScroll();
    initReveal();
    initCounters();
    initFaq();
    initLeadForm();
})();
