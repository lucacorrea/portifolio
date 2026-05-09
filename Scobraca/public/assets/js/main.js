(function () {
    "use strict";

    const prefersReducedMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

    function initHeaderScroll() {
        const header = document.querySelector("[data-header]");

        if (!header) {
            return;
        }

        const update = () => {
            header.classList.toggle("is-scrolled", window.scrollY > 8);
        };

        update();
        window.addEventListener("scroll", update, { passive: true });
    }

    function initMobileMenu() {
        const header = document.querySelector("[data-header]");
        const toggle = document.querySelector("[data-menu-toggle]");
        const menu = document.querySelector("[data-mobile-menu]");

        if (!header || !toggle || !menu) {
            return;
        }

        const close = () => {
            header.classList.remove("is-menu-open");
            document.body.classList.remove("menu-open");
            toggle.setAttribute("aria-expanded", "false");
        };

        toggle.addEventListener("click", () => {
            const isOpen = header.classList.toggle("is-menu-open");
            document.body.classList.toggle("menu-open", isOpen);
            toggle.setAttribute("aria-expanded", String(isOpen));
        });

        menu.querySelectorAll("a").forEach((link) => {
            link.addEventListener("click", close);
        });

        document.addEventListener("keydown", (event) => {
            if (event.key === "Escape") {
                close();
            }
        });
    }

    function initMegaMenu() {
        const navItems = document.querySelectorAll(".nav-item.has-mega");

        if (!navItems.length) {
            return;
        }

        const closeAll = (except) => {
            navItems.forEach((item) => {
                if (item === except) {
                    return;
                }

                item.classList.remove("is-open");
                const button = item.querySelector("[data-mega-trigger]");

                if (button) {
                    button.setAttribute("aria-expanded", "false");
                }
            });
        };

        navItems.forEach((item) => {
            const button = item.querySelector("[data-mega-trigger]");

            if (!button) {
                return;
            }

            const open = () => {
                closeAll(item);
                item.classList.add("is-open");
                button.setAttribute("aria-expanded", "true");
            };

            const close = () => {
                item.classList.remove("is-open");
                button.setAttribute("aria-expanded", "false");
            };

            item.addEventListener("mouseenter", open);
            item.addEventListener("mouseleave", close);
            button.addEventListener("click", (event) => {
                event.preventDefault();
                const isOpen = item.classList.contains("is-open");
                closeAll(isOpen ? null : item);

                if (isOpen) {
                    close();
                } else {
                    open();
                }
            });
        });

        document.addEventListener("click", (event) => {
            if (!event.target.closest(".nav-item.has-mega")) {
                closeAll(null);
            }
        });
    }

    function initScrollReveal() {
        const items = document.querySelectorAll(".reveal");

        if (!items.length) {
            return;
        }

        if (prefersReducedMotion || !("IntersectionObserver" in window)) {
            items.forEach((item) => item.classList.add("is-visible"));
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
            threshold: 0.12,
            rootMargin: "0px 0px -48px 0px"
        });

        items.forEach((item) => observer.observe(item));
    }

    function formatCounter(value, prefix, suffix) {
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
            counter.textContent = formatCounter(target, prefix, suffix);
            return;
        }

        const duration = 1100;
        const start = performance.now();

        const tick = (now) => {
            const progress = Math.min((now - start) / duration, 1);
            const eased = 1 - Math.pow(1 - progress, 3);
            counter.textContent = formatCounter(target * eased, prefix, suffix);

            if (progress < 1) {
                window.requestAnimationFrame(tick);
            }
        };

        window.requestAnimationFrame(tick);
    }

    function initCounters() {
        const counters = document.querySelectorAll("[data-counter]");

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
        }, { threshold: 0.4 });

        counters.forEach((counter) => observer.observe(counter));
    }

    function initTestimonialsCarousel() {
        const carousel = document.querySelector("[data-carousel]");
        const previous = document.querySelector("[data-carousel-prev]");
        const next = document.querySelector("[data-carousel-next]");

        if (!carousel || !previous || !next) {
            return;
        }

        const scroll = (direction) => {
            const card = carousel.querySelector(".testimonial-card");
            const cardWidth = card ? card.getBoundingClientRect().width + 20 : 560;

            carousel.scrollBy({
                left: direction * cardWidth,
                behavior: prefersReducedMotion ? "auto" : "smooth"
            });
        };

        previous.addEventListener("click", () => scroll(-1));
        next.addEventListener("click", () => scroll(1));
    }

    function initComparisonToggle() {
        const button = document.querySelector("[data-comparison-toggle]");
        const panel = document.querySelector("[data-comparison-panel]");

        if (!button || !panel) {
            return;
        }

        button.addEventListener("click", () => {
            const isOpen = button.getAttribute("aria-expanded") === "true";
            button.setAttribute("aria-expanded", String(!isOpen));
            button.classList.toggle("is-open", !isOpen);
            panel.hidden = isOpen;

            if (!isOpen) {
                panel.scrollIntoView({
                    block: "nearest",
                    behavior: prefersReducedMotion ? "auto" : "smooth"
                });
            }
        });
    }

    function initFaq() {
        const list = document.querySelector("[data-faq-list]");

        if (!list) {
            return;
        }

        list.addEventListener("click", (event) => {
            const button = event.target.closest("button");

            if (!button || !list.contains(button)) {
                return;
            }

            const item = button.closest(".faq-item");
            const isOpen = item.classList.contains("is-open");

            list.querySelectorAll(".faq-item").forEach((faqItem) => {
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

    function sanitizeText(value) {
        return String(value || "")
            .trim()
            .replace(/[<>]/g, "")
            .replace(/\s+/g, " ");
    }

    function phoneDigits(value) {
        return String(value || "").replace(/\D/g, "");
    }

    function isValidEmail(value) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(value);
    }

    function setFieldError(form, name, message) {
        const field = form.elements[name];
        const error = form.querySelector(`[data-error-for="${name}"]`);
        const wrapper = field ? field.closest("label") : null;

        if (wrapper) {
            wrapper.classList.toggle("is-invalid", Boolean(message));
        }

        if (error) {
            error.textContent = message || "";
        }
    }

    function setFormStatus(form, message, isError) {
        const status = form.querySelector("[data-form-status]");

        if (!status) {
            return;
        }

        status.textContent = message;
        status.classList.toggle("is-error", Boolean(isError));
    }

    async function handleLeadSubmit(data) {
        // TODO: conectar endpoint real da FluxPay.
        return Promise.resolve(data);
    }

    function initLeadForm() {
        const form = document.querySelector("[data-lead-form]");

        if (!form) {
            return;
        }

        const phone = form.elements.phone;

        if (phone) {
            phone.addEventListener("input", () => {
                const digits = phoneDigits(phone.value).slice(0, 11);
                const area = digits.slice(0, 2);
                const middle = digits.length > 10 ? digits.slice(2, 7) : digits.slice(2, 6);
                const end = digits.length > 10 ? digits.slice(7, 11) : digits.slice(6, 10);

                if (digits.length <= 2) {
                    phone.value = area ? `(${area}` : "";
                    return;
                }

                phone.value = `(${area}) ${middle}${end ? `-${end}` : ""}`;
            });
        }

        form.addEventListener("submit", async (event) => {
            event.preventDefault();

            const data = {
                name: sanitizeText(form.elements.name.value),
                email: sanitizeText(form.elements.email.value).toLowerCase(),
                phone: sanitizeText(form.elements.phone.value),
                business: sanitizeText(form.elements.business.value)
            };

            const digits = phoneDigits(data.phone);
            let hasError = false;

            ["name", "email", "phone", "business"].forEach((field) => setFieldError(form, field, ""));
            setFormStatus(form, "", false);

            if (!data.name) {
                setFieldError(form, "name", "Informe seu nome.");
                hasError = true;
            }

            if (!data.email || !isValidEmail(data.email)) {
                setFieldError(form, "email", "Informe um e-mail válido.");
                hasError = true;
            }

            if (digits.length < 10 || digits.length > 11) {
                setFieldError(form, "phone", "Informe um WhatsApp válido com DDD.");
                hasError = true;
            }

            if (!data.business) {
                setFieldError(form, "business", "Selecione o tipo de negócio.");
                hasError = true;
            }

            if (hasError) {
                setFormStatus(form, "Revise os campos destacados para continuar.", true);
                return;
            }

            await handleLeadSubmit({
                ...data,
                phoneDigits: digits,
                source: "fluxpay_landing",
                capturedAt: new Date().toISOString()
            });

            setFormStatus(form, `Obrigado, ${data.name}. Sua solicitação foi registrada para demonstração.`, false);
            form.reset();
        });
    }

    function initCheckoutMock() {
        document.querySelectorAll(".pay-methods button").forEach((button) => {
            button.addEventListener("click", () => {
                const group = button.closest(".pay-methods");
                group.querySelectorAll("button").forEach((item) => item.classList.remove("active"));
                button.classList.add("active");
            });
        });
    }

    initHeaderScroll();
    initMobileMenu();
    initMegaMenu();
    initScrollReveal();
    initCounters();
    initTestimonialsCarousel();
    initComparisonToggle();
    initFaq();
    initCheckoutMock();
    initLeadForm();
})();
