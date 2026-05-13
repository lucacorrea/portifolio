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

    function initParallaxEffects() {
        const cards = Array.from(document.querySelectorAll("[data-parallax-card]"));
        const isSmallViewport = window.matchMedia("(max-width: 768px)").matches;

        if (!cards.length || prefersReducedMotion || isSmallViewport) {
            return;
        }

        let ticking = false;

        const update = () => {
            const viewportCenter = window.innerHeight / 2;

            cards.forEach((card) => {
                const rect = card.getBoundingClientRect();

                if (rect.bottom < -120 || rect.top > window.innerHeight + 120) {
                    return;
                }

                const cardCenter = rect.top + rect.height / 2;
                const offset = Math.max(-18, Math.min(18, (viewportCenter - cardCenter) * 0.026));
                card.style.setProperty("--parallax-y", `${offset.toFixed(2)}px`);
            });

            ticking = false;
        };

        const requestUpdate = () => {
            if (ticking) {
                return;
            }

            ticking = true;
            window.requestAnimationFrame(update);
        };

        update();
        window.addEventListener("scroll", requestUpdate, { passive: true });
        window.addEventListener("resize", requestUpdate);
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

    function bindPhoneMask(input) {
        if (!input) {
            return;
        }

        input.addEventListener("input", () => {
            const digits = phoneDigits(input.value).slice(0, 11);
            const area = digits.slice(0, 2);
            const middle = digits.length > 10 ? digits.slice(2, 7) : digits.slice(2, 6);
            const end = digits.length > 10 ? digits.slice(7, 11) : digits.slice(6, 10);

            if (digits.length <= 2) {
                input.value = area ? `(${area}` : "";
                return;
            }

            input.value = `(${area}) ${middle}${end ? `-${end}` : ""}`;
        });
    }

    function bindDocumentMask(input, cnpjOnly) {
        if (!input) {
            return;
        }

        input.addEventListener("input", () => {
            const maxLength = cnpjOnly ? 14 : 14;
            const digits = phoneDigits(input.value).slice(0, maxLength);

            if (cnpjOnly || digits.length > 11) {
                const part1 = digits.slice(0, 2);
                const part2 = digits.slice(2, 5);
                const part3 = digits.slice(5, 8);
                const part4 = digits.slice(8, 12);
                const part5 = digits.slice(12, 14);
                let value = part1;

                if (part2) value += `.${part2}`;
                if (part3) value += `.${part3}`;
                if (part4) value += `/${part4}`;
                if (part5) value += `-${part5}`;

                input.value = value;
                return;
            }

            const part1 = digits.slice(0, 3);
            const part2 = digits.slice(3, 6);
            const part3 = digits.slice(6, 9);
            const part4 = digits.slice(9, 11);
            let value = part1;

            if (part2) value += `.${part2}`;
            if (part3) value += `.${part3}`;
            if (part4) value += `-${part4}`;

            input.value = value;
        });
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

        bindPhoneMask(form.elements.phone);

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

    function initCheckoutPage() {
        const form = document.querySelector("[data-checkout-form]");

        if (!form) {
            return;
        }

        const cycleGroup = document.querySelector("[data-checkout-cycle]");
        const paymentGroup = document.querySelector("[data-checkout-payments]");
        const paymentPreview = document.querySelector("[data-payment-preview]");
        const summaryPrice = document.querySelector("[data-summary-price]");
        const summaryDiscount = document.querySelector("[data-summary-discount]");
        const summaryTotal = document.querySelector("[data-summary-total]");
        const summaryRenewal = document.querySelector("[data-summary-renewal]");
        const cycleInput = form.elements.cycle;
        const paymentInput = form.elements.payment;
        const submitButton = form.querySelector(".checkout-submit");

        const datasetValue = (element, key, fallback) => {
            if (!element || !element.dataset) {
                return fallback;
            }

            return element.dataset[key] || fallback;
        };

        const prices = {
            monthly: {
                price: datasetValue(summaryPrice, "monthlyPrice", ""),
                discount: datasetValue(summaryDiscount, "monthlyDiscount", ""),
                total: datasetValue(summaryTotal, "monthlyTotal", ""),
                renewal: datasetValue(summaryRenewal, "monthlyRenewal", "")
            },
            annual: {
                price: datasetValue(summaryPrice, "annualPrice", ""),
                discount: datasetValue(summaryDiscount, "annualDiscount", ""),
                total: datasetValue(summaryTotal, "annualTotal", ""),
                renewal: datasetValue(summaryRenewal, "annualRenewal", "")
            }
        };

        const paymentCopy = {
            pix: {
                title: "Pix selecionado",
                text: "A assinatura inicial será criada em teste. A cobrança recorrente deve ser confirmada conforme a forma escolhida e as regras comerciais do plano."
            },
            card: {
                title: "Cartão selecionado",
                text: "A preferência por cartão será enviada com o cadastro para orientar a ativação comercial da assinatura."
            },
            boleto: {
                title: "Boleto selecionado",
                text: "A preferência por boleto será enviada com o cadastro para orientar a ativação comercial da assinatura."
            }
        };

        const setCheckoutStatus = (message, isError) => {
            const status = form.querySelector("[data-checkout-status]");

            if (!status) {
                return;
            }

            status.textContent = message;
            status.classList.toggle("is-error", Boolean(isError));
        };

        const updateSummary = (cycle) => {
            const selected = prices[cycle] || prices.monthly;

            if (summaryPrice) summaryPrice.textContent = selected.price;
            if (summaryDiscount) summaryDiscount.textContent = selected.discount;
            if (summaryTotal) summaryTotal.textContent = selected.total;
            if (summaryRenewal) summaryRenewal.textContent = selected.renewal;
        };

        bindPhoneMask(form.elements.phone);
        bindDocumentMask(form.elements.empresa_cnpj, true);
        bindDocumentMask(form.elements.usuario_documento, false);

        if (cycleGroup) {
            cycleGroup.querySelectorAll("button").forEach((button) => {
                button.addEventListener("click", () => {
                    const cycle = button.dataset.cycle || "monthly";

                    cycleGroup.querySelectorAll("button").forEach((item) => {
                        item.classList.remove("active");
                        item.setAttribute("aria-pressed", "false");
                    });
                    button.classList.add("active");
                    button.setAttribute("aria-pressed", "true");

                    if (cycleInput) {
                        cycleInput.value = cycle;
                    }

                    updateSummary(cycle);
                });
            });
        }

        if (paymentGroup && paymentPreview) {
            paymentGroup.querySelectorAll("button").forEach((button) => {
                button.addEventListener("click", () => {
                    const payment = button.dataset.payment || "pix";

                    paymentGroup.querySelectorAll("button").forEach((item) => {
                        item.classList.remove("active");
                        item.setAttribute("aria-pressed", "false");
                    });
                    button.classList.add("active");
                    button.setAttribute("aria-pressed", "true");

                    if (paymentInput) {
                        paymentInput.value = payment;
                    }

                    const selected = paymentCopy[payment] || paymentCopy.pix;
                    paymentPreview.innerHTML = `<strong>${selected.title}</strong><p>${selected.text}</p>`;
                });
            });
        }

        form.addEventListener("submit", (event) => {
            const activeCycle = cycleGroup ? cycleGroup.querySelector(".active") : null;
            const activePayment = paymentGroup ? paymentGroup.querySelector(".active") : null;
            const data = {
                empresa_nome: sanitizeText(form.elements.empresa_nome.value),
                empresa_cnpj: sanitizeText(form.elements.empresa_cnpj.value),
                name: sanitizeText(form.elements.name.value),
                email: sanitizeText(form.elements.email.value).toLowerCase(),
                phone: sanitizeText(form.elements.phone.value),
                usuario_documento: sanitizeText(form.elements.usuario_documento.value),
                senha: String(form.elements.senha.value || ""),
                senha_confirmacao: String(form.elements.senha_confirmacao.value || ""),
                terms: Boolean(form.elements.terms.checked),
                cycle: activeCycle ? activeCycle.dataset.cycle : "monthly",
                payment: activePayment ? activePayment.dataset.payment : "pix"
            };

            const digits = phoneDigits(data.phone);
            const companyDocumentDigits = phoneDigits(data.empresa_cnpj);
            const userDocumentDigits = phoneDigits(data.usuario_documento);
            let hasError = false;

            [
                "empresa_nome",
                "empresa_cnpj",
                "name",
                "email",
                "phone",
                "usuario_documento",
                "senha",
                "senha_confirmacao",
                "terms"
            ].forEach((field) => setFieldError(form, field, ""));
            setCheckoutStatus("", false);

            if (!data.empresa_nome || data.empresa_nome.length < 3) {
                setFieldError(form, "empresa_nome", "Informe o nome da empresa.");
                hasError = true;
            }

            if (companyDocumentDigits.length !== 14) {
                setFieldError(form, "empresa_cnpj", "Informe um CNPJ válido.");
                hasError = true;
            }

            if (!data.name) {
                setFieldError(form, "name", "Informe seu nome completo.");
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

            if (![11, 14].includes(userDocumentDigits.length)) {
                setFieldError(form, "usuario_documento", "Informe um CPF ou CNPJ válido.");
                hasError = true;
            }

            if (data.senha.length < 8 || !/[A-Za-z]/.test(data.senha) || !/\d/.test(data.senha)) {
                setFieldError(form, "senha", "Use pelo menos 8 caracteres, letras e números.");
                hasError = true;
            }

            if (!data.senha_confirmacao || data.senha_confirmacao !== data.senha) {
                setFieldError(form, "senha_confirmacao", "Confirme a mesma senha.");
                hasError = true;
            }

            if (!data.terms) {
                setFieldError(form, "terms", "Confirme o aceite para criar a assinatura.");
                hasError = true;
            }

            if (hasError) {
                event.preventDefault();
                setCheckoutStatus("Revise os campos destacados antes de finalizar.", true);
                return;
            }

            if (cycleInput) {
                cycleInput.value = data.cycle;
            }

            if (paymentInput) {
                paymentInput.value = data.payment;
            }

            if (submitButton) {
                submitButton.disabled = true;
            }

            setCheckoutStatus("Validando e criando assinatura...", false);
        });
    }

    initHeaderScroll();
    initMobileMenu();
    initMegaMenu();
    initScrollReveal();
    initParallaxEffects();
    initCounters();
    initTestimonialsCarousel();
    initComparisonToggle();
    initFaq();
    initCheckoutPage();
    initLeadForm();
})();
