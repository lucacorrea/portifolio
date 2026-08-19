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

(() => {
    const rows = [...document.querySelectorAll('[data-pe-candidate-row]')];
    const drawerElement = document.getElementById('peCandidateDrawer');
    if (!rows.length || !drawerElement || typeof bootstrap === 'undefined') return;

    const drawer = bootstrap.Offcanvas.getOrCreateInstance(drawerElement);
    const get = selector => drawerElement.querySelector(selector);

    const fields = {
        name: get('[data-pe-drawer-name]'),
        meta: get('[data-pe-drawer-meta]'),
        cpf: get('[data-pe-drawer-cpf]'),
        phone: get('[data-pe-drawer-phone]'),
        birth: get('[data-pe-drawer-birth]'),
        neighborhood: get('[data-pe-drawer-neighborhood]'),
        sector: get('[data-pe-drawer-sector]'),
        status: get('[data-pe-drawer-status]'),
        review: get('[data-pe-drawer-review]'),
        reviewDetails: get('[data-pe-drawer-review-details]'),
        reviewBox: get('[data-pe-drawer-review-box]'),
        reviewAction: get('[data-pe-action-review]'),
        visitAction: get('[data-pe-action-visit]'),
        profileAction: get('[data-pe-action-profile]'),
    };

    const candidateUrl = (path, id) => `primeiro-emprego/${path}?candidato_id=${encodeURIComponent(id)}`;

    const reviewUrl = id => {
        const url = new URL(window.location.href);
        url.searchParams.set('revisar', id);
        url.searchParams.delete('p');
        url.hash = 'revisao-candidato';
        return url.href;
    };

    const fillDrawer = row => {
        const data = row.dataset;
        fields.name.textContent = data.name || 'Candidato';
        fields.meta.textContent = `#${data.id || '—'} · ${data.origin || 'origem não informada'}`;
        fields.cpf.textContent = data.cpf || '—';
        fields.phone.textContent = data.phone || '—';
        fields.birth.textContent = data.birth || '—';
        fields.neighborhood.textContent = data.neighborhood || '—';
        fields.sector.textContent = data.sector || '—';
        fields.status.textContent = data.status || '—';
        fields.review.textContent = data.review || 'Sem pendência';
        fields.reviewDetails.textContent = data.reviewDetails || 'Cadastro sem pendências';

        fields.reviewBox.classList.remove('is-ok', 'is-warning', 'is-multiple', 'is-critical');
        if (data.duplicate === '1') {
            fields.reviewBox.classList.add('is-critical');
        } else if ((data.review || '').toLowerCase().includes('cadastro')) {
            fields.reviewBox.classList.add('is-multiple');
        } else if ((data.review || '') && data.review !== 'Sem pendência') {
            fields.reviewBox.classList.add('is-warning');
        } else {
            fields.reviewBox.classList.add('is-ok');
        }

        fields.reviewAction.href = reviewUrl(data.id);
        fields.visitAction.href = candidateUrl('acompanhamentos.php', data.id);
        fields.profileAction.href = candidateUrl('lotacoes.php', data.id);
    };

    const openRow = row => {
        fillDrawer(row);
        drawer.show();
    };

    rows.forEach(row => {
        row.addEventListener('click', event => {
            if (event.target.closest('a, button, input, select, textarea, label')) return;
            openRow(row);
        });

        row.addEventListener('keydown', event => {
            if (event.key !== 'Enter' && event.key !== ' ') return;
            event.preventDefault();
            openRow(row);
        });
    });
})();
