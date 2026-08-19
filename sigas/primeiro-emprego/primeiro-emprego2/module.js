'use strict';

(() => {
    const wizard = document.querySelector('[data-pe-wizard]');
    if (!wizard) return;

    const form = wizard.querySelector('[data-pe-wizard-form]');
    const panels = [...wizard.querySelectorAll('[data-pe-step]')];
    const indicators = [...wizard.querySelectorAll('[data-pe-step-indicator]')];
    const progress = wizard.querySelector('[data-pe-progress]');
    const progressBar = progress?.closest('[role="progressbar"]');
    const progressText = wizard.querySelector('[data-pe-progress-text]');
    const previous = wizard.querySelector('[data-pe-prev]');
    const next = wizard.querySelector('[data-pe-next]');
    let current = 0;

    const notify = message => window.SIGAS_FRONTEND?.showToast(message);
    const escapeHTML = value => String(value ?? '').replace(
        /[&<>"']/g,
        character => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[character]
    );

    const updateReview = () => {
        const review = wizard.querySelector('[data-pe-review]');
        if (!review) return;
        const fields = [
            ['Nome', form.elements.candidate_name?.value],
            ['Contato', form.elements.candidate_phone?.value],
            ['Bairro', form.elements.candidate_neighborhood?.value],
            ['Escolaridade', form.elements.candidate_education?.value],
            ['Experiência', form.elements.candidate_experience?.value],
            ['Disponibilidade', form.elements.candidate_availability?.value],
        ];
        review.innerHTML = fields
            .map(([label, value]) => `<div><dt>${escapeHTML(label)}</dt><dd>${escapeHTML(value || 'Não informado')}</dd></div>`)
            .join('');
    };

    const render = focus => {
        panels.forEach((panel, index) => { panel.hidden = index !== current; });
        indicators.forEach((indicator, index) => {
            if (index === current) indicator.setAttribute('aria-current', 'step');
            else indicator.removeAttribute('aria-current');
        });
        const percentage = ((current + 1) / panels.length) * 100;
        if (progress) progress.style.width = `${percentage}%`;
        if (progressBar) progressBar.setAttribute('aria-valuenow', String(current + 1));
        if (progressText) progressText.textContent = `Etapa ${current + 1} de ${panels.length}`;
        if (previous) previous.disabled = current === 0;
        if (next) next.innerHTML = current === panels.length - 1
            ? 'Confirmar visualmente<i class="bi bi-check-lg"></i>'
            : 'Próximo<i class="bi bi-arrow-right"></i>';
        if (current === panels.length - 1) updateReview();
        if (focus) panels[current]?.querySelector('h3')?.focus();
    };

    const currentIsValid = () => {
        const required = [...panels[current].querySelectorAll('[required]')];
        const areas = [...panels[current].querySelectorAll('[name="candidate_areas[]"]')];
        const areasValid = areas.length === 0 || areas.some(field => field.checked);
        areas[0]?.setCustomValidity(areasValid ? '' : 'Selecione ao menos uma área de interesse.');
        const valid = required.every(field => field.checkValidity()) && areasValid;
        panels[current].classList.toggle('was-validated', !valid);
        if (!valid) {
            [...required, ...areas].find(field => !field.checkValidity())?.reportValidity();
        }
        return valid;
    };

    previous?.addEventListener('click', () => {
        current = Math.max(0, current - 1);
        render(true);
    });

    next?.addEventListener('click', () => {
        if (!currentIsValid()) return;
        if (current === panels.length - 1) {
            const title = document.querySelector('#frontendActionTitle');
            if (title) title.textContent = 'Confirmar cadastro demonstrativo';
            bootstrap.Modal.getOrCreateInstance('#frontendActionModal').show();
            return;
        }
        current += 1;
        render(true);
    });

    indicators.forEach((indicator, index) => indicator.addEventListener('click', () => {
        if (index > current && !currentIsValid()) return;
        current = index;
        render(true);
    }));

    wizard.querySelector('[data-pe-save-draft]')?.addEventListener('click', () => {
        notify('Rascunho visual preparado. Nenhum dado foi persistido.');
    });

    render(false);
})();
