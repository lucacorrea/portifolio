document.addEventListener('DOMContentLoaded', () => {
    const sidebar = document.getElementById('sidebar');
    const toggle = document.querySelector('[data-sidebar-toggle]');
    const backdrop = document.querySelector('[data-sidebar-close]');
    const search = document.querySelector('[data-table-search]');

    const closeSidebar = () => {
        if (sidebar) {
            sidebar.classList.remove('mobile-open');
        }

        if (backdrop) {
            backdrop.classList.remove('is-visible');
        }
    };

    const openSidebar = () => {
        if (sidebar) {
            sidebar.classList.add('mobile-open');
        }

        if (backdrop) {
            backdrop.classList.add('is-visible');
        }
    };

    if (toggle) {
        toggle.addEventListener('click', (event) => {
            event.stopPropagation();

            if (sidebar && sidebar.classList.contains('mobile-open')) {
                closeSidebar();
                return;
            }

            openSidebar();
        });
    }

    if (backdrop) {
        backdrop.addEventListener('click', closeSidebar);
    }

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeSidebar();
        }
    });

    window.addEventListener('resize', () => {
        if (window.innerWidth > 768) {
            closeSidebar();
        }
    });

    document.querySelectorAll('.sidebar a').forEach((link) => {
        link.addEventListener('click', () => {
            if (window.innerWidth <= 768) {
                closeSidebar();
            }
        });
    });

    if (search) {
        search.addEventListener('input', () => {
            const term = search.value.trim().toLowerCase();

            document.querySelectorAll('tbody tr').forEach((row) => {
                row.style.display = row.textContent.toLowerCase().includes(term) ? '' : 'none';
            });
        });
    }

    document.querySelectorAll('[data-cobranca-form]').forEach((form) => {
        const typeSelect = form.querySelector('[data-billing-type]');
        const clientSelect = form.querySelector('[data-client-select]');
        const monthlyValue = form.querySelector('[data-monthly-value]');
        const monthlyDueDate = form.querySelector('[data-monthly-due-date]');
        const reference = form.elements.referencia;
        const sections = form.querySelectorAll('[data-billing-section]');

        const syncSections = () => {
            const activeType = typeSelect ? typeSelect.value : 'mensalidade';

            sections.forEach((section) => {
                const isActive = section.dataset.billingSection === activeType;
                section.hidden = !isActive;

                section.querySelectorAll('input, select, textarea').forEach((field) => {
                    field.disabled = !isActive;

                    if (field.hasAttribute('data-required-on-active')) {
                        field.required = isActive;
                    }
                });
            });
        };

        const fillMonthlyDefaults = (forceValue = false) => {
            if (!clientSelect) {
                return;
            }

            const option = clientSelect.selectedOptions[0];

            if (!option) {
                return;
            }

            if (monthlyValue && option.dataset.valor && (forceValue || monthlyValue.value.trim() === '')) {
                monthlyValue.value = option.dataset.valor;
            }

            if (!monthlyDueDate || !reference || !option.dataset.dia || !reference.value) {
                return;
            }

            const [year, month] = reference.value.split('-').map((part) => Number(part));

            if (!year || !month) {
                return;
            }

            const lastDay = new Date(year, month, 0).getDate();
            const day = Math.min(Number(option.dataset.dia) || 10, lastDay);
            monthlyDueDate.value = `${year}-${String(month).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
        };

        if (typeSelect) {
            typeSelect.addEventListener('change', syncSections);
        }

        if (clientSelect) {
            clientSelect.addEventListener('change', () => fillMonthlyDefaults(true));
        }

        if (reference) {
            reference.addEventListener('change', fillMonthlyDefaults);
        }

        syncSections();
        fillMonthlyDefaults();
    });
});
