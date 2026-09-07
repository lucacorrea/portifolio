'use strict';

(() => {
    const config = window.SIGAS_SOCIO || {};
    const qs = selector => document.querySelector(selector);
    const qsa = selector => [...document.querySelectorAll(selector)];
    const workspace = qs('#socioWorkspace');
    const searchInput = qs('#socioCpfSearch');
    const searchButton = qs('#socioSearchButton');
    const searchResult = qs('#socioSearchResult');
    const form = qs('#socioForm');
    const useAnexoButton = qs('#socioUseAnexo');
    const familyList = qs('#socioFamilyMembers');
    const addMemberButton = qs('#socioAddMember');
    const saveButton = qs('#socioSaveButton');
    const saveState = qs('#socioSaveState');
    const toastContainer = qs('#socioToastContainer');
    let lastLookup = null;

    const escapeHTML = value => String(value ?? '').replace(/[&<>"']/g, character => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
    }[character]));

    const digits = value => String(value ?? '').replace(/\D+/g, '');
    const formatCpf = value => {
        const raw = digits(value).slice(0, 11);
        return raw
            .replace(/^(\d{3})(\d)/, '$1.$2')
            .replace(/^(\d{3})\.(\d{3})(\d)/, '$1.$2.$3')
            .replace(/\.(\d{3})(\d{1,2})$/, '.$1-$2');
    };

    const showToast = (message, type = 'success') => {
        if (!toastContainer || typeof bootstrap === 'undefined') return;
        const toast = document.createElement('div');
        toast.className = `toast border-0 text-bg-${type}`;
        toast.setAttribute('role', 'status');
        toast.innerHTML = `<div class="d-flex"><div class="toast-body">${escapeHTML(message)}</div><button class="btn-close btn-close-white me-2 m-auto" type="button" data-bs-dismiss="toast" aria-label="Fechar"></button></div>`;
        toastContainer.appendChild(toast);
        const instance = new bootstrap.Toast(toast, { delay: 3600 });
        toast.addEventListener('hidden.bs.toast', () => toast.remove(), { once: true });
        instance.show();
    };

    const setLoading = loading => {
        document.body.classList.toggle('socio-loading', loading);
        if (searchButton) searchButton.disabled = loading;
        if (saveButton) saveButton.disabled = loading;
    };

    const setSearchResult = (message, type = '') => {
        if (!searchResult) return;
        searchResult.hidden = false;
        searchResult.className = 'socio-search-result' + (type ? ` is-${type}` : '');
        searchResult.innerHTML = message;
    };

    const value = (selector, newValue) => {
        const field = qs(selector);
        if (!field) return '';
        if (newValue !== undefined) field.value = newValue ?? '';
        return field.value;
    };

    const checked = (selector, state) => {
        const field = qs(selector);
        if (!field) return false;
        if (state !== undefined) field.checked = Boolean(state);
        return field.checked;
    };

    const dateOnly = raw => {
        if (!raw) return '';
        const text = String(raw);
        return /^\d{4}-\d{2}-\d{2}/.test(text) ? text.slice(0, 10) : '';
    };

    const normalizeList = list => Array.isArray(list)
        ? list.map(item => String(item ?? '').trim()).filter(Boolean)
        : [];

    const setCheckboxValues = (containerSelector, values) => {
        const expected = new Set(normalizeList(values).map(item => item.toLocaleLowerCase('pt-BR')));
        qsa(`${containerSelector} input[type="checkbox"]`).forEach(input => {
            input.checked = expected.has(String(input.value).toLocaleLowerCase('pt-BR'));
        });
    };

    const checkboxValues = containerSelector => qsa(`${containerSelector} input[type="checkbox"]:checked`)
        .map(input => String(input.value || '').trim())
        .filter(Boolean);

    const clearMembers = () => {
        if (familyList) familyList.innerHTML = '';
    };

    const addMember = (member = {}) => {
        if (!familyList) return;
        const row = document.createElement('div');
        row.className = 'socio-family-row';
        row.innerHTML = `
            <div><label class="form-label">Nome</label><input class="form-control" data-member="nome" maxlength="160" value="${escapeHTML(member.nome ?? member.name ?? '')}"></div>
            <div><label class="form-label">Nascimento</label><input class="form-control" type="date" data-member="data_nascimento" value="${escapeHTML(dateOnly(member.data_nascimento ?? member.birth_date ?? ''))}"></div>
            <div><label class="form-label">Parentesco</label><input class="form-control" data-member="parentesco" maxlength="80" value="${escapeHTML(member.parentesco ?? member.relationship ?? '')}"></div>
            <div><label class="form-label">Escolaridade</label><input class="form-control" data-member="escolaridade" maxlength="120" value="${escapeHTML(member.escolaridade ?? member.schooling ?? '')}"></div>
            <button class="btn btn-light" type="button" data-remove-member aria-label="Remover familiar"><i class="bi bi-trash"></i></button>`;
        familyList.appendChild(row);
        if (!config.canEdit) row.querySelectorAll('input,button').forEach(field => { field.disabled = true; });
    };

    const collectMembers = () => qsa('.socio-family-row').map(row => ({
        nome: row.querySelector('[data-member="nome"]')?.value.trim() || '',
        data_nascimento: row.querySelector('[data-member="data_nascimento"]')?.value || null,
        parentesco: row.querySelector('[data-member="parentesco"]')?.value.trim() || null,
        escolaridade: row.querySelector('[data-member="escolaridade"]')?.value.trim() || null,
    })).filter(member => member.nome !== '');

    const clearForm = cpf => {
        if (form) form.reset();
        clearMembers();
        value('#socioCpf', cpf || '');
        value('#socioOrigem', 'sigas');
        value('#socioAnexoId', '');
        value('#socioAnexoUpdated', '');
        value('#socioMembrosTotal', '1');
        setCheckboxValues('#socioBeneficios', []);
        setCheckboxValues('#socioVulnerabilidades', []);
        checked('#socioDeficiencia', false);
        checked('#socioAreaRisco', false);
    };

    const fillFromPayload = payload => {
        const person = payload?.person || {};
        const profile = payload?.socioeconomic || {};

        value('#socioCpf', person.cpf || payload?.cpf || '');
        value('#socioNome', person.nome || person.name || '');
        value('#socioNis', person.nis || '');
        value('#socioRg', person.rg || '');
        value('#socioNascimento', dateOnly(person.data_nascimento || person.birth_date || ''));
        value('#socioTelefone', person.telefone || person.phone || '');
        value('#socioEmail', person.email || '');
        value('#socioGrupo', profile.grupo_tradicional || '');

        value('#socioLogradouro', person.logradouro || person.street || '');
        value('#socioNumero', person.numero || person.number || '');
        value('#socioBairro', person.bairro || person.district || '');
        value('#socioComunidade', person.comunidade || '');
        value('#socioComplemento', person.complemento || person.complement || '');
        value('#socioCep', person.cep || '');
        value('#socioReferencia', person.ponto_referencia || person.reference_point || '');

        value('#socioEscolaridade', profile.escolaridade || '');
        value('#socioTrabalho', profile.situacao_trabalho || '');
        value('#socioOcupacao', profile.ocupacao || '');
        value('#socioRendaIndividual', profile.renda_individual || '');
        value('#socioRendaFamiliar', profile.renda_familiar ?? person.renda_familiar ?? person.family_income ?? '');
        value('#socioMembrosTotal', profile.quantidade_membros || person.quantidade_membros || person.members_count || 1);
        checked('#socioDeficiencia', Number(profile.possui_deficiencia || 0) === 1);
        value('#socioDeficienciaDescricao', profile.deficiencia_descricao || '');
        setCheckboxValues('#socioBeneficios', profile.beneficios || []);

        value('#socioMoradia', profile.tipo_moradia || '');
        value('#socioMaterial', profile.material_moradia || '');
        value('#socioComodos', profile.numero_comodos ?? '');
        value('#socioAgua', profile.abastecimento_agua || '');
        value('#socioEnergia', profile.energia_eletrica || '');
        value('#socioLixo', profile.coleta_lixo || '');
        value('#socioEsgoto', profile.esgotamento_sanitario || '');
        checked('#socioAreaRisco', Number(profile.area_risco || 0) === 1);
        value('#socioAreaRiscoDescricao', profile.area_risco_descricao || '');
        setCheckboxValues('#socioVulnerabilidades', profile.vulnerabilidades || []);

        value('#socioResumo', profile.resumo_social || profile.summary || '');
        value('#socioObservacoes', profile.observacoes || '');
        value('#socioOrigem', profile.origem || 'sigas');
        value('#socioAnexoId', profile.anexo_solicitante_id || '');
        value('#socioAnexoUpdated', profile.anexo_atualizado_em || '');

        clearMembers();
        normalizeMembers(profile.membros || payload?.members || []).forEach(addMember);
    };

    const normalizeMembers = members => Array.isArray(members) ? members.filter(item => item && typeof item === 'object') : [];

    const fillAnexoDraft = draft => {
        const person = draft?.person || {};
        const family = draft?.family || {};
        const profile = draft?.socioeconomic || {};
        fillFromPayload({
            cpf: person.cpf || value('#socioCpf'),
            person: { ...person, ...family },
            socioeconomic: { ...profile, membros: draft?.members || [] }
        });
        value('#socioOrigem', 'anexo');
        value('#socioAnexoId', profile.anexo_solicitante_id || '');
        value('#socioAnexoUpdated', profile.anexo_atualizado_em || '');
        showToast('Dados do ANEXO carregados para conferência. Revise antes de salvar.', 'info');
    };

    const renderProfileState = state => {
        const stateNode = qs('#socioProfileState');
        const info = qs('#socioInterviewInfo');
        if (!stateNode || !info) return;
        stateNode.textContent = state?.label || 'Sem prontuário';
        const age = state?.age_days;
        info.innerHTML = `
            <div class="socio-mini-item"><span>Situação</span><strong>${escapeHTML(state?.state || 'ausente')}</strong></div>
            <div class="socio-mini-item"><span>Idade da entrevista</span><strong>${age === null || age === undefined ? '—' : `${escapeHTML(age)} dia(s)`}</strong></div>
            <div class="socio-mini-item"><span>Revisão recomendada</span><strong>${state?.needs_review ? 'Sim' : 'Não'}</strong></div>`;
    };

    const renderBenefits = requests => {
        const container = qs('#socioBenefitsList');
        if (!container) return;
        if (!Array.isArray(requests) || requests.length === 0) {
            container.innerHTML = '<div class="socio-empty-mini">Nenhuma solicitação registrada no cadastro central.</div>';
            return;
        }
        container.innerHTML = requests.map(request => {
            const status = String(request.status || 'solicitado');
            const negative = ['indeferido','cancelado','nao_apto'].includes(status);
            const active = !['encerrado','cancelado','indeferido','entregue'].includes(status);
            return `<article class="socio-benefit-item"><div class="socio-benefit-item__top"><strong>${escapeHTML(request.beneficio_nome || request.modulo || 'Benefício')}</strong><span class="socio-status${active ? ' is-active' : ''}${negative ? ' is-negative' : ''}">${escapeHTML(status.replaceAll('_',' '))}</span></div><small>${escapeHTML(request.modulo || '')}${request.responsavel_nome ? ` · ${escapeHTML(request.responsavel_nome)}` : ''}</small></article>`;
        }).join('');
    };

    const renderLookup = data => {
        lastLookup = data;
        const cpf = data?.cpf || digits(searchInput?.value);
        clearForm(cpf);
        workspace.hidden = false;
        if (useAnexoButton) useAnexoButton.hidden = true;

        const sourceText = qs('#socioSourceText');
        if (data?.registered) {
            fillFromPayload(data);
            setSearchResult(`<strong>Pessoa localizada no SIGAS.</strong> ${data.socioeconomic ? 'O prontuário socioeconômico existente foi carregado.' : 'Ainda não existe prontuário socioeconômico central para esta pessoa.'}`, 'success');
            if (sourceText) sourceText.textContent = data.socioeconomic ? `SIGAS · origem do prontuário: ${data.socioeconomic.origem || 'sigas'}` : 'Pessoa já cadastrada no SIGAS';
        } else if (data?.anexo?.found) {
            value('#socioCpf', cpf);
            setSearchResult('<strong>Pessoa localizada no ANEXO.</strong> Você pode trazer os dados para conferência e criar o cadastro central no SIGAS sem alterar o ANEXO.', 'warning');
            if (sourceText) sourceText.textContent = 'ANEXO localizado · aguardando conferência/importação';
            if (useAnexoButton && config.canImport && data.anexo_draft) useAnexoButton.hidden = false;
        } else {
            value('#socioCpf', cpf);
            setSearchResult('<strong>CPF não localizado no SIGAS.</strong> Preencha o prontuário para criar o cadastro central da pessoa.', 'warning');
            if (sourceText) sourceText.textContent = data?.anexo?.available === false ? 'Cadastro novo · ANEXO indisponível/não consultado' : 'Cadastro novo';
        }

        renderProfileState(data?.socioeconomic_state || null);
        renderBenefits(data?.benefit_requests || []);

        if (!config.canEdit) {
            form?.querySelectorAll('input,select,textarea,button').forEach(field => {
                if (field.id !== 'socioSearchButton') field.disabled = true;
            });
        }
    };

    const lookup = async () => {
        const cpf = digits(searchInput?.value);
        if (cpf.length !== 11) {
            setSearchResult('Informe um CPF com 11 números.', 'error');
            return;
        }
        setLoading(true);
        try {
            const response = await fetch(`api/socioeconomico/consultar.php?cpf=${encodeURIComponent(cpf)}`, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
                cache: 'no-store'
            });
            const payload = await response.json().catch(() => ({}));
            if (!response.ok || !payload.ok) throw new Error(payload.error || 'Não foi possível consultar o CPF.');
            renderLookup(payload.data || {});
        } catch (error) {
            setSearchResult(escapeHTML(error.message || 'Falha ao consultar o CPF.'), 'error');
        } finally {
            setLoading(false);
        }
    };

    const buildPayload = () => ({
        _csrf: config.csrf || '',
        person: {
            cpf: digits(value('#socioCpf')),
            nome: value('#socioNome').trim(),
            nis: value('#socioNis').trim(),
            rg: value('#socioRg').trim(),
            data_nascimento: value('#socioNascimento') || null,
            telefone: value('#socioTelefone').trim(),
            email: value('#socioEmail').trim(),
        },
        family: {
            logradouro: value('#socioLogradouro').trim(),
            numero: value('#socioNumero').trim(),
            bairro: value('#socioBairro').trim(),
            comunidade: value('#socioComunidade').trim(),
            complemento: value('#socioComplemento').trim(),
            cep: value('#socioCep').trim(),
            ponto_referencia: value('#socioReferencia').trim(),
            quantidade_membros: Number(value('#socioMembrosTotal') || 1),
            renda_familiar: value('#socioRendaFamiliar').trim(),
        },
        socioeconomic: {
            origem: value('#socioOrigem') || 'sigas',
            anexo_solicitante_id: value('#socioAnexoId') || null,
            anexo_atualizado_em: value('#socioAnexoUpdated') || null,
            escolaridade: value('#socioEscolaridade'),
            situacao_trabalho: value('#socioTrabalho'),
            ocupacao: value('#socioOcupacao').trim(),
            renda_individual: value('#socioRendaIndividual').trim(),
            renda_familiar: value('#socioRendaFamiliar').trim(),
            quantidade_membros: Number(value('#socioMembrosTotal') || 1),
            grupo_tradicional: value('#socioGrupo'),
            possui_deficiencia: checked('#socioDeficiencia') ? 1 : 0,
            deficiencia_descricao: value('#socioDeficienciaDescricao').trim(),
            beneficios: checkboxValues('#socioBeneficios'),
            vulnerabilidades: checkboxValues('#socioVulnerabilidades'),
            tipo_moradia: value('#socioMoradia'),
            material_moradia: value('#socioMaterial'),
            numero_comodos: value('#socioComodos') || null,
            abastecimento_agua: value('#socioAgua'),
            energia_eletrica: value('#socioEnergia'),
            coleta_lixo: value('#socioLixo'),
            esgotamento_sanitario: value('#socioEsgoto'),
            area_risco: checked('#socioAreaRisco') ? 1 : 0,
            area_risco_descricao: value('#socioAreaRiscoDescricao').trim(),
            resumo_social: value('#socioResumo').trim(),
            observacoes: value('#socioObservacoes').trim(),
        },
        members: collectMembers(),
        motivo_atualizacao: lastLookup?.socioeconomic ? 'Revisão/atualização do prontuário' : 'Cadastro inicial do prontuário socioeconômico'
    });

    const save = async event => {
        event.preventDefault();
        if (!config.canEdit) return;
        if (!form?.reportValidity()) return;
        const payload = buildPayload();
        if (payload.person.cpf.length !== 11) {
            showToast('Consulte um CPF válido antes de salvar.', 'danger');
            return;
        }
        setLoading(true);
        if (saveState) saveState.textContent = 'Salvando prontuário...';
        try {
            const response = await fetch('api/socioeconomico/salvar.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
                body: JSON.stringify(payload)
            });
            const result = await response.json().catch(() => ({}));
            if (!response.ok || !result.ok) throw new Error(result.error || 'Não foi possível salvar o prontuário.');
            if (saveState) saveState.textContent = 'Prontuário salvo e histórico atualizado';
            showToast(result.message || 'Prontuário salvo.');
            if (searchInput) searchInput.value = formatCpf(payload.person.cpf);
            await lookup();
        } catch (error) {
            if (saveState) saveState.textContent = 'Falha ao salvar — revise os dados';
            showToast(error.message || 'Falha ao salvar o prontuário.', 'danger');
        } finally {
            setLoading(false);
        }
    };

    searchInput?.addEventListener('input', () => { searchInput.value = formatCpf(searchInput.value); });
    searchInput?.addEventListener('keydown', event => { if (event.key === 'Enter') { event.preventDefault(); lookup(); } });
    searchButton?.addEventListener('click', lookup);
    useAnexoButton?.addEventListener('click', () => { if (lastLookup?.anexo_draft) fillAnexoDraft(lastLookup.anexo_draft); });
    addMemberButton?.addEventListener('click', () => addMember());
    familyList?.addEventListener('click', event => {
        const button = event.target.closest('[data-remove-member]');
        if (button && config.canEdit) button.closest('.socio-family-row')?.remove();
    });
    form?.addEventListener('submit', save);

    if (config.initialCpf && digits(config.initialCpf).length === 11) {
        if (searchInput) searchInput.value = formatCpf(config.initialCpf);
        lookup();
    }
})();
