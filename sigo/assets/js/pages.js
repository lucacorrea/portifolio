'use strict';

const refreshPageIcons = () => {
    if (window.lucide) window.lucide.createIcons();
};

document.querySelectorAll('[data-live-search]').forEach((input) => {
    const target = input.dataset.liveSearch;
    const rows = [...document.querySelectorAll(target)];

    input.addEventListener('input', () => {
        const value = input.value.trim().toLowerCase();
        rows.forEach((row) => {
            const text = (row.dataset.search || row.innerText).toLowerCase();
            row.hidden = value !== '' && !text.includes(value);
        });
    });
});

document.querySelectorAll('[data-status-select]').forEach((select) => {
    const rows = [...document.querySelectorAll('[data-filter-row]')];

    select.addEventListener('change', () => {
        const value = select.value;
        rows.forEach((row) => {
            row.hidden = value !== 'all' && row.dataset.status !== value;
        });
    });
});

const selectAll = document.querySelector('[data-select-all]');
const receiptChecks = [...document.querySelectorAll('[data-receipt-check]')];
selectAll?.addEventListener('change', () => {
    receiptChecks.forEach((check) => {
        check.checked = selectAll.checked;
        check.dispatchEvent(new Event('change'));
    });
});

document.querySelector('[data-office-form]')?.addEventListener('submit', (event) => {
    event.preventDefault();
    alert('Layout do cadastro pronto. Na próxima etapa conectamos este formulário ao banco de dados.');
});

/* Fluxo de recebimento rápido */
const receiptFlow = document.querySelector('[data-receipt-flow]');

if (receiptFlow) {
    const personSelect = document.querySelector('[data-receipt-person]');
    const pinInput = document.querySelector('[data-receipt-pin]');
    const rememberPerson = document.querySelector('[data-remember-person]');
    const step1Error = document.querySelector('[data-receipt-error]');
    const step2Error = document.querySelector('[data-receipt-error-step2]');
    const step3Error = document.querySelector('[data-receipt-error-step3]');
    const selectedCount = document.querySelector('[data-selected-count]');
    const buttonCount = document.querySelector('[data-button-count]');
    const reviewList = document.querySelector('[data-review-list]');
    const reviewCount = document.querySelector('[data-review-count]');
    const confirmCheck = document.querySelector('[data-confirm-check]');

    const getInitials = (name) => {
        return name
            .trim()
            .split(/\s+/)
            .slice(0, 2)
            .map((part) => part.charAt(0).toUpperCase())
            .join('');
    };

    const updatePerson = () => {
        const name = personSelect?.value || '';
        document.querySelectorAll('[data-person-name], [data-review-person], [data-success-person]').forEach((element) => {
            element.textContent = name || '—';
        });

        document.querySelectorAll('[data-person-initials], [data-review-initials]').forEach((element) => {
            element.textContent = name ? getInitials(name) : '--';
        });
    };

    const selectedOffices = () => receiptChecks.filter((check) => check.checked);

    const updateSelection = () => {
        const selected = selectedOffices();
        const count = selected.length;

        if (selectedCount) selectedCount.textContent = String(count);
        if (buttonCount) {
            buttonCount.hidden = count === 0;
            buttonCount.textContent = String(count);
        }

        if (selectAll) {
            selectAll.checked = receiptChecks.length > 0 && count === receiptChecks.length;
            selectAll.indeterminate = count > 0 && count < receiptChecks.length;
        }

        if (step2Error && count > 0) step2Error.hidden = true;
    };

    const updateReview = () => {
        const selected = selectedOffices();

        if (reviewCount) reviewCount.textContent = String(selected.length);
        if (!reviewList) return;

        reviewList.innerHTML = selected.map((check) => {
            const office = check.dataset.office || '';
            const protocol = check.dataset.protocol || '';
            const origin = check.dataset.origin || '';
            const subject = check.dataset.subject || '';

            return '<div class="review-office-item">' +
                '<strong>Ofício ' + office + ' · Protocolo ' + protocol + '</strong>' +
                '<span>' + origin + ' · ' + subject + '</span>' +
            '</div>';
        }).join('');
    };

    const setStep = (step) => {
        document.querySelectorAll('[data-receipt-step]').forEach((section) => {
            const sectionStep = Number(section.dataset.receiptStep);
            section.hidden = sectionStep !== step;
            section.classList.toggle('active', sectionStep === step);
        });

        document.querySelectorAll('[data-step-indicator]').forEach((indicator) => {
            const indicatorStep = Number(indicator.dataset.stepIndicator);
            indicator.classList.toggle('active', indicatorStep === step);
            indicator.classList.toggle('done', indicatorStep < step || step === 4);
        });

        if (step === 2) updatePerson();
        if (step === 3) {
            updatePerson();
            updateReview();
        }

        window.scrollTo({top: 0, behavior: 'smooth'});
        refreshPageIcons();
    };

    const validateIdentity = () => {
        const name = personSelect?.value || '';
        const pin = pinInput?.value.trim() || '';
        let message = '';

        if (!name) {
            message = 'Selecione seu nome para continuar.';
        } else if (!/^\d{4,6}$/.test(pin)) {
            message = 'Digite um PIN válido de 4 a 6 números.';
        }

        if (step1Error) {
            step1Error.hidden = !message;
            const text = step1Error.querySelector('span');
            if (text) text.textContent = message;
        }

        if (!message && rememberPerson?.checked) {
            localStorage.setItem('sigo_receipt_person', name);
        } else if (!message && !rememberPerson?.checked) {
            localStorage.removeItem('sigo_receipt_person');
        }

        return !message;
    };

    const rememberedPerson = localStorage.getItem('sigo_receipt_person');
    if (rememberedPerson && personSelect) {
        const option = [...personSelect.options].find((item) => item.value === rememberedPerson);
        if (option) {
            personSelect.value = rememberedPerson;
            updatePerson();
        }
    }

    receiptChecks.forEach((check) => check.addEventListener('change', updateSelection));

    document.querySelectorAll('[data-receipt-next]').forEach((button) => {
        button.addEventListener('click', () => {
            const nextStep = Number(button.dataset.receiptNext);

            if (nextStep === 2 && !validateIdentity()) return;

            if (nextStep === 3 && selectedOffices().length === 0) {
                if (step2Error) step2Error.hidden = false;
                return;
            }

            setStep(nextStep);
        });
    });

    document.querySelectorAll('[data-receipt-back]').forEach((button) => {
        button.addEventListener('click', () => {
            const targetStep = Number(button.dataset.receiptBack);
            setStep(targetStep);
        });
    });

    document.querySelector('[data-confirm-receipt-final]')?.addEventListener('click', () => {
        if (!confirmCheck?.checked) {
            if (step3Error) step3Error.hidden = false;
            return;
        }

        if (step3Error) step3Error.hidden = true;

        const selected = selectedOffices();
        const person = personSelect?.value || '—';

        const successCount = document.querySelector('[data-success-count]');
        const successPerson = document.querySelector('[data-success-person]');
        const successTime = document.querySelector('[data-success-time]');
        const successMessage = document.querySelector('[data-success-message]');

        if (successCount) successCount.textContent = String(selected.length);
        if (successPerson) successPerson.textContent = person;
        if (successTime) {
            successTime.textContent = new Intl.DateTimeFormat('pt-BR', {
                dateStyle: 'short',
                timeStyle: 'short'
            }).format(new Date());
        }
        if (successMessage) {
            successMessage.textContent = selected.length === 1
                ? '1 ofício foi registrado como recebido.'
                : selected.length + ' ofícios foram registrados como recebidos.';
        }

        setStep(4);
    });

    document.querySelector('[data-receipt-finish]')?.addEventListener('click', () => {
        receiptChecks.forEach((check) => {
            check.checked = false;
        });

        if (pinInput) pinInput.value = '';
        if (confirmCheck) confirmCheck.checked = false;
        updateSelection();
        setStep(1);
    });

    personSelect?.addEventListener('change', updatePerson);
    pinInput?.addEventListener('input', () => {
        if (step1Error) step1Error.hidden = true;
    });
    confirmCheck?.addEventListener('change', () => {
        if (step3Error && confirmCheck.checked) step3Error.hidden = true;
    });

    updateSelection();
    updatePerson();
}

refreshPageIcons();
