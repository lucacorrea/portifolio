'use strict';

(() => {
    const context = window.SIGAS_CONTEXT?.kitMaternity || {};
    const qs = selector => document.querySelector(selector);
    const qsa = selector => [...document.querySelectorAll(selector)];

    const digits = value => String(value ?? '').replace(/\D+/g, '');
    const formatCpf = value => {
        const raw = digits(value).slice(0, 11);
        return raw
            .replace(/^(\d{3})(\d)/, '$1.$2')
            .replace(/^(\d{3})\.(\d{3})(\d)/, '$1.$2.$3')
            .replace(/\.(\d{3})(\d{1,2})$/, '.$1-$2');
    };
    const escapeHTML = value => String(value ?? '').replace(/[&<>"']/g, character => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
    }[character]));

    const showToast = (message, type = 'success') => {
        if (window.SIGAS_FRONTEND?.showToast) {
            window.SIGAS_FRONTEND.showToast(message, type);
            return;
        }
        window.alert(message);
    };

    const setFormBusy = (form, busy) => {
        form?.querySelectorAll('button,input,select,textarea').forEach(field => {
            if (field.type === 'hidden') return;
            if (!busy && field.dataset.initialDisabled === '1') {
                field.disabled = true;
                return;
            }
            if (!busy && field.dataset.initialDisabled !== '1') {
                field.disabled = false;
                return;
            }
            if (busy) {
                if (field.disabled) field.dataset.initialDisabled = '1';
                field.disabled = true;
            }
        });
    };

    const formObject = form => {
        const data = new FormData(form);
        const object = {};
        for (const [key, value] of data.entries()) {
            if (Object.prototype.hasOwnProperty.call(object, key)) {
                object[key] = Array.isArray(object[key]) ? [...object[key], value] : [object[key], value];
            } else {
                object[key] = value;
            }
        }
        if (object.pendencias_texto) {
            object.pendencias = String(object.pendencias_texto)
                .split(/[;\n,]+/)
                .map(item => item.trim())
                .filter(Boolean);
            delete object.pendencias_texto;
        }
        return object;
    };

    const submitOperation = async form => {
        if (!form.reportValidity()) return;
        const riskSelect = form.querySelector('[data-km-risk-select]');
        const riskDescription = form.querySelector('[data-km-risk-description]');
        if (riskSelect?.value === '1' && riskDescription && !riskDescription.value.trim()) {
            riskDescription.setCustomValidity('Descreva o risco identificado.');
            riskDescription.reportValidity();
            return;
        }
        riskDescription?.setCustomValidity('');

        setFormBusy(form, true);
        try {
            const response = await fetch('api/kit-maternidade/operacao.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
                body: JSON.stringify(formObject(form))
            });
            const payload = await response.json().catch(() => ({}));
            if (!response.ok || !payload.ok) throw new Error(payload.error || 'Não foi possível concluir a operação.');
            showToast(payload.message || 'Operação concluída.');
            const action = String(form.dataset.kmForm || '');
            if (action === 'abrir') {
                window.location.href = 'kit-maternidade/index.php?pagina=beneficiarias';
                return;
            }
            window.setTimeout(() => window.location.reload(), 350);
        } catch (error) {
            showToast(error.message || 'Falha ao concluir a operação.', 'danger');
            setFormBusy(form, false);
        }
    };

    qsa('[data-km-form]').forEach(form => {
        form.addEventListener('submit', event => {
            event.preventDefault();
            submitOperation(form);
        });
        form.querySelectorAll('[data-km-risk-select]').forEach(select => {
            const description = form.querySelector('[data-km-risk-description]');
            const sync = () => {
                if (!description) return;
                description.closest('div')?.classList.toggle('km-risk-required', select.value === '1');
                if (select.value !== '1') description.setCustomValidity('');
            };
            select.addEventListener('change', sync);
            sync();
        });
    });

    const lookupInput = qs('#kmCpfLookup');
    const lookupButton = qs('[data-km-person-lookup]');
    const result = qs('[data-km-person-result]');
    const requestForm = qs('[data-km-form="abrir"]');
    const personIdField = qs('[data-km-person-id]');
    const links = qs('[data-km-person-links]');
    const socioLink = qs('[data-km-socio-link]');

    const setPersonResult = (html, type = '') => {
        if (!result) return;
        result.hidden = false;
        result.className = `km-person-result mt-3${type ? ` is-${type}` : ''}`;
        result.innerHTML = html;
    };

    const benefitsHTML = requests => {
        if (!Array.isArray(requests) || requests.length === 0) return '<span class="km-person-benefits-empty">Nenhum outro benefício central registrado.</span>';
        return `<div class="km-person-benefits"><strong>Solicitações da pessoa</strong>${requests.slice(0, 8).map(item => `<span>${escapeHTML(item.beneficio_nome || item.modulo || 'Benefício')} <small>${escapeHTML(String(item.status || '').replaceAll('_',' '))}</small></span>`).join('')}</div>`;
    };

    const lookupPerson = async () => {
        const cpf = digits(lookupInput?.value);
        if (cpf.length !== 11) {
            setPersonResult('Informe um CPF válido com 11 números.', 'error');
            requestForm?.setAttribute('hidden', 'hidden');
            return;
        }
        if (lookupButton) lookupButton.disabled = true;
        try {
            const response = await fetch(`api/kit-maternidade/pessoa.php?cpf=${encodeURIComponent(cpf)}`, {
                credentials: 'same-origin',
                headers: { Accept: 'application/json' },
                cache: 'no-store'
            });
            const payload = await response.json().catch(() => ({}));
            if (!response.ok || !payload.ok) throw new Error(payload.error || 'Não foi possível localizar a pessoa.');
            const data = payload.data || {};
            if (socioLink) {
                socioLink.href = `prontuario-socioeconomico.php?cpf=${encodeURIComponent(cpf)}&retorno=${encodeURIComponent('kit-maternidade/index.php?pagina=cadastro')}`;
            }
            if (links) links.hidden = false;

            if (!data.found) {
                if (personIdField) personIdField.value = '';
                requestForm?.setAttribute('hidden', 'hidden');
                setPersonResult(`<div class="km-person-result__icon"><i class="bi bi-person-plus"></i></div><div><strong>Pessoa ainda não cadastrada no SIGAS</strong><p>Cadastre ou confira o prontuário socioeconômico central. Se ela existir no ANEXO, os dados poderão ser trazidos para conferência sem alterar o ANEXO.</p></div>`, 'warning');
                return;
            }

            const person = data.person || {};
            const profile = data.socioeconomic || {};
            if (personIdField) personIdField.value = person.id || '';

            if (data.open_kit_request) {
                requestForm?.setAttribute('hidden', 'hidden');
                setPersonResult(`<div class="km-person-result__icon"><i class="bi bi-exclamation-circle"></i></div><div><strong>${escapeHTML(person.nome || 'Pessoa localizada')}</strong><p>Já existe uma solicitação ativa do Kit Maternidade para esta pessoa. Não será criado cadastro duplicado.</p>${benefitsHTML(data.benefit_requests)}</div>`, 'warning');
                return;
            }

            requestForm?.removeAttribute('hidden');
            const profileMessage = profile.exists
                ? `Formulário socioeconômico preenchido${profile.data_entrevista ? ` em ${escapeHTML(String(profile.data_entrevista).slice(0,10).split('-').reverse().join('/'))}` : ''}.`
                : 'Formulário socioeconômico ainda pendente. A solicitação pode ser aberta, mas a avaliação não poderá ser concluída até o preenchimento.';
            setPersonResult(`<div class="km-person-result__icon"><i class="bi bi-person-check"></i></div><div><strong>${escapeHTML(person.nome || '')}</strong><p>${escapeHTML(person.bairro || 'Bairro não informado')} · ${escapeHTML(person.telefone || 'Telefone não informado')}</p><div class="km-profile-state${profile.exists ? ' is-ok' : ' is-pending'}">${escapeHTML(profileMessage)}</div>${benefitsHTML(data.benefit_requests)}</div>`, 'success');
        } catch (error) {
            requestForm?.setAttribute('hidden', 'hidden');
            setPersonResult(escapeHTML(error.message || 'Falha ao consultar a pessoa.'), 'error');
        } finally {
            if (lookupButton) lookupButton.disabled = false;
        }
    };

    lookupInput?.addEventListener('input', () => { lookupInput.value = formatCpf(lookupInput.value); });
    lookupInput?.addEventListener('keydown', event => {
        if (event.key === 'Enter') {
            event.preventDefault();
            lookupPerson();
        }
    });
    lookupButton?.addEventListener('click', lookupPerson);

    qsa('input[name="recebedor_cpf"]').forEach(input => input.addEventListener('input', () => { input.value = formatCpf(input.value); }));
})();
