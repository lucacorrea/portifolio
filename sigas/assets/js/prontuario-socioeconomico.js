'use strict';

(() => {
    const config = window.SIGAS_SOCIO || {};
    const qs = selector => document.querySelector(selector);
    const qsa = selector => [...document.querySelectorAll(selector)];

    const workspace = qs('#socioWorkspace');
    const personCard = qs('#socioPersonCard');
    const recordShell = qs('.socio-record-shell');
    const searchInput = qs('#socioCpfSearch');
    const searchButton = qs('#socioSearchButton');
    const searchResult = qs('#socioSearchResult');
    const form = qs('#socioForm');
    const viewButton = qs('#socioViewButton');
    const editButton = qs('#socioEditButton');
    const useAnexoButton = qs('#socioUseAnexo');
    const familyList = qs('#socioFamilyMembers');
    const familyEmpty = qs('#socioFamilyEmpty');
    const addMemberButton = qs('#socioAddMember');
    const saveButton = qs('#socioSaveButton');
    const savebar = qs('#socioSavebar');
    const saveState = qs('#socioSaveState');
    const cancelEdit = qs('#socioCancelEdit');
    const modeBadge = qs('#socioModeBadge');
    const recordTitle = qs('#socioRecordTitle');
    const recordSubtitle = qs('#socioRecordSubtitle');
    const toastContainer = qs('#socioToastContainer');

    let lastLookup = null;
    let editing = false;
    let previewingAnexo = false;

    const tabMeta = {
        overview: ['Visão geral', 'Informações consolidadas para consulta técnica.'],
        identity: ['Identificação', 'Dados pessoais, contato e referência territorial.'],
        family: ['Família', 'Composição do domicílio registrada na entrevista social.'],
        income: ['Renda e trabalho', 'Escolaridade, ocupação, renda e condição de trabalho.'],
        housing: ['Moradia', 'Condições habitacionais, infraestrutura e exposição a risco.'],
        vulnerabilities: ['Vulnerabilidades', 'Condições sociais verificadas ou declaradas na entrevista.'],
        benefits: ['Benefícios', 'Transferências declaradas e solicitações abertas nos módulos do SIGAS.'],
        history: ['Histórico', 'Registro das versões confirmadas do prontuário socioeconômico.'],
    };

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

    const formatDate = raw => {
        if (!raw) return 'Não informado';
        const value = String(raw).slice(0, 10);
        const match = value.match(/^(\d{4})-(\d{2})-(\d{2})$/);
        return match ? `${match[3]}/${match[2]}/${match[1]}` : String(raw);
    };

    const dateOnly = raw => {
        if (!raw) return '';
        const text = String(raw);
        return /^\d{4}-\d{2}-\d{2}/.test(text) ? text.slice(0, 10) : '';
    };

    const moneyNumber = raw => {
        if (raw === null || raw === undefined || raw === '') return null;
        const text = String(raw).trim();
        if (text === '') return null;
        const normalized = text.includes(',')
            ? text.replace(/\./g, '').replace(',', '.')
            : text;
        const parsed = Number(normalized);
        return Number.isFinite(parsed) ? parsed : null;
    };

    const formatMoney = raw => {
        const parsed = moneyNumber(raw);
        return parsed === null
            ? 'Não informado'
            : new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(parsed);
    };

    const initials = name => {
        const parts = String(name || '').trim().split(/\s+/).filter(Boolean);
        if (!parts.length) return '—';
        return (parts[0][0] + (parts.length > 1 ? parts[parts.length - 1][0] : '')).toUpperCase();
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

    const normalizeMembers = members => Array.isArray(members)
        ? members.filter(item => item && typeof item === 'object')
        : [];

    const updateFamilyEmpty = () => {
        if (!familyEmpty) return;
        familyEmpty.hidden = qsa('.socio-family-row').length > 0;
    };

    const clearMembers = () => {
        if (familyList) familyList.innerHTML = '';
        updateFamilyEmpty();
    };

    const addMember = (member = {}) => {
        if (!familyList) return;
        const row = document.createElement('article');
        row.className = 'socio-family-row';
        row.innerHTML = `
            <div class="socio-family-row__head">
                <div><i class="bi bi-person"></i><strong>Familiar</strong></div>
                <button class="btn btn-light btn-sm socio-edit-only" type="button" data-remove-member aria-label="Remover familiar"><i class="bi bi-trash"></i> Remover</button>
            </div>
            <div class="socio-family-grid">
                <div class="family-span-2"><label class="form-label">Nome</label><input class="form-control" data-member="nome" maxlength="160" value="${escapeHTML(member.nome ?? member.name ?? '')}"></div>
                <div><label class="form-label">Nascimento</label><input class="form-control" type="date" data-member="data_nascimento" value="${escapeHTML(dateOnly(member.data_nascimento ?? member.birth_date ?? ''))}"></div>
                <div><label class="form-label">Parentesco</label><input class="form-control" data-member="parentesco" maxlength="80" value="${escapeHTML(member.parentesco ?? member.relationship ?? '')}"></div>
                <div><label class="form-label">Escolaridade</label><input class="form-control" data-member="escolaridade" maxlength="120" value="${escapeHTML(member.escolaridade ?? member.schooling ?? '')}"></div>
                <div><label class="form-label">Ocupação</label><input class="form-control" data-member="ocupacao" maxlength="150" value="${escapeHTML(member.ocupacao ?? '')}"></div>
                <div><label class="form-label">Renda mensal</label><input class="form-control" data-member="renda_mensal" inputmode="decimal" value="${escapeHTML(member.renda_mensal ?? '')}"></div>
                <div class="socio-family-check"><label><input class="form-check-input" type="checkbox" data-member="possui_deficiencia" ${Number(member.possui_deficiencia || 0) === 1 ? 'checked' : ''}> Pessoa com deficiência</label></div>
                <div class="family-span-2"><label class="form-label">Observação</label><input class="form-control" data-member="observacao" maxlength="500" value="${escapeHTML(member.observacao ?? '')}"></div>
            </div>`;
        familyList.appendChild(row);
        setEditMode(editing, false);
        updateFamilyEmpty();
    };

    const collectMembers = () => qsa('.socio-family-row').map(row => ({
        nome: row.querySelector('[data-member="nome"]')?.value.trim() || '',
        data_nascimento: row.querySelector('[data-member="data_nascimento"]')?.value || null,
        parentesco: row.querySelector('[data-member="parentesco"]')?.value.trim() || null,
        escolaridade: row.querySelector('[data-member="escolaridade"]')?.value.trim() || null,
        ocupacao: row.querySelector('[data-member="ocupacao"]')?.value.trim() || null,
        renda_mensal: row.querySelector('[data-member="renda_mensal"]')?.value.trim() || null,
        possui_deficiencia: row.querySelector('[data-member="possui_deficiencia"]')?.checked ? 1 : 0,
        observacao: row.querySelector('[data-member="observacao"]')?.value.trim() || null,
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
        updateFamilyEmpty();
    };

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
        previewingAnexo = true;
        refreshOverview();
    };

    const detailRow = (label, content) => `<div><span>${escapeHTML(label)}</span><strong>${escapeHTML(content || 'Não informado')}</strong></div>`;

    const renderBenefits = requests => {
        const container = qs('#socioBenefitsList');
        if (!container) return;
        if (!Array.isArray(requests) || requests.length === 0) {
            container.innerHTML = '<div class="socio-empty-mini">Nenhuma solicitação registrada no cadastro central.</div>';
            return;
        }
        container.innerHTML = requests.map(request => {
            const status = String(request.status || 'solicitado');
            const negative = ['indeferido', 'cancelado', 'nao_apto'].includes(status);
            const active = !['encerrado', 'cancelado', 'indeferido', 'entregue'].includes(status);
            return `<article class="socio-benefit-item"><div class="socio-benefit-item__top"><strong>${escapeHTML(request.beneficio_nome || request.modulo || 'Benefício')}</strong><span class="socio-status${active ? ' is-active' : ''}${negative ? ' is-negative' : ''}">${escapeHTML(status.replaceAll('_', ' '))}</span></div><small>${escapeHTML(request.modulo || '')}${request.responsavel_nome ? ` · responsável: ${escapeHTML(request.responsavel_nome)}` : ''}</small></article>`;
        }).join('');
    };

    const renderHistory = history => {
        const container = qs('#socioHistoryList');
        if (!container) return;
        if (!Array.isArray(history) || history.length === 0) {
            container.innerHTML = '<div class="socio-empty-state"><i class="bi bi-clock-history"></i><strong>Nenhuma versão registrada</strong><span>O histórico será criado quando o prontuário for salvo ou atualizado.</span></div>';
            return;
        }
        container.innerHTML = history.map(item => `
            <article class="socio-history-item">
                <div class="socio-history-icon"><i class="bi bi-clock-history"></i></div>
                <div><strong>${escapeHTML(item.motivo || 'Atualização do prontuário')}</strong><span>${escapeHTML(formatDate(item.criado_em))} · ${escapeHTML(item.usuario_nome || 'Sistema')}</span></div>
                <span class="socio-source-pill">${escapeHTML(String(item.origem || 'sigas').toUpperCase())}</span>
            </article>`).join('');
    };

    const renderPersonCard = data => {
        if (!personCard) return;
        const sourcePerson = data?.registered
            ? (data.person || {})
            : (data?.anexo?.person || {});
        const name = sourcePerson.nome || sourcePerson.name || 'Pessoa ainda não cadastrada';
        const cpf = data?.cpf || sourcePerson.cpf || '';
        const profileState = data?.socioeconomic_state || {};
        const badgeParts = [];

        if (data?.registered) badgeParts.push('<span class="socio-person-badge is-success"><i class="bi bi-check-circle"></i> Cadastro SIGAS</span>');
        else if (data?.anexo?.found) badgeParts.push('<span class="socio-person-badge is-warning"><i class="bi bi-cloud"></i> Localizada no ANEXO</span>');
        else badgeParts.push('<span class="socio-person-badge"><i class="bi bi-person-plus"></i> Novo cadastro</span>');

        if (data?.socioeconomic) {
            badgeParts.push(`<span class="socio-person-badge ${profileState.needs_review ? 'is-warning' : 'is-success'}"><i class="bi bi-journal-check"></i> ${profileState.needs_review ? 'Revisar prontuário' : 'Prontuário atualizado'}</span>`);
        }

        qs('#socioPersonInitials').textContent = initials(name);
        qs('#socioPersonBadges').innerHTML = badgeParts.join('');
        qs('#socioPersonName').textContent = name;
        qs('#socioPersonIdentity').textContent = `CPF ${formatCpf(cpf)}${sourcePerson.nis ? ` · NIS ${sourcePerson.nis}` : ''}`;

        const birth = sourcePerson.data_nascimento || sourcePerson.birth_date;
        const phone = sourcePerson.telefone || sourcePerson.phone;
        const district = sourcePerson.bairro || sourcePerson.district;
        const age = profileState.age_days;
        const metrics = [
            ['bi-telephone', 'Contato', phone || 'Não informado'],
            ['bi-calendar3', 'Nascimento', formatDate(birth)],
            ['bi-geo-alt', 'Território', district || 'Não informado'],
            ['bi-journal-check', 'Entrevista', data?.socioeconomic ? (age === null || age === undefined ? 'Data não informada' : `${age} dia(s)`) : 'Ainda não preenchida'],
        ];
        qs('#socioPersonMetrics').innerHTML = metrics.map(([icon, label, content]) => `<div><i class="bi ${icon}"></i><span>${escapeHTML(label)}</span><strong>${escapeHTML(content)}</strong></div>`).join('');

        if (viewButton) viewButton.hidden = !(data?.registered || data?.anexo?.found);
        if (editButton) {
            editButton.querySelector('span').textContent = data?.socioeconomic
                ? 'Editar / atualizar'
                : (data?.registered ? 'Criar prontuário' : 'Iniciar prontuário');
        }
        if (useAnexoButton) {
            useAnexoButton.hidden = !(config.canImport && data?.anexo_draft);
        }
        personCard.hidden = false;
    };

    const refreshOverview = () => {
        const profile = lastLookup?.socioeconomic || {};
        const state = lastLookup?.socioeconomic_state || {};
        const requests = Array.isArray(lastLookup?.benefit_requests) ? lastLookup.benefit_requests : [];
        const memberCount = Math.max(1, Number(value('#socioMembrosTotal') || 1));
        const familyIncome = moneyNumber(value('#socioRendaFamiliar'));
        const perCapitaStored = moneyNumber(profile.renda_per_capita);
        const perCapita = perCapitaStored ?? (familyIncome === null ? null : familyIncome / memberCount);
        const vulnerabilityCount = checkboxValues('#socioVulnerabilidades').length;
        const activeRequests = requests.filter(request => !['encerrado', 'cancelado', 'indeferido', 'entregue'].includes(String(request.status || ''))).length;
        const source = previewingAnexo ? 'ANEXO — prévia não salva' : String(value('#socioOrigem') || profile.origem || 'sigas').toUpperCase();

        const stats = [
            ['bi-cash-stack', 'Renda familiar', familyIncome === null ? 'Não informada' : formatMoney(familyIncome)],
            ['bi-person', 'Renda per capita', perCapita === null ? 'Não calculada' : formatMoney(perCapita)],
            ['bi-people', 'Moradores', String(memberCount)],
            ['bi-house', 'Moradia', value('#socioMoradia') || 'Não informada'],
            ['bi-shield-exclamation', 'Vulnerabilidades', String(vulnerabilityCount)],
            ['bi-box2-heart', 'Solicitações ativas', String(activeRequests)],
        ];
        const statsNode = qs('#socioOverviewStats');
        if (statsNode) statsNode.innerHTML = stats.map(([icon, label, content]) => `<article><i class="bi ${icon}"></i><span>${escapeHTML(label)}</span><strong>${escapeHTML(content)}</strong></article>`).join('');

        const interview = qs('#socioOverviewInterview');
        if (interview) {
            interview.innerHTML = [
                detailRow('Situação', previewingAnexo ? 'Prévia para conferência' : (state.label || 'Prontuário não preenchido')),
                detailRow('Data da entrevista', formatDate(profile.data_entrevista || profile.atualizado_em || profile.criado_em)),
                detailRow('Responsável', profile.entrevistado_por_nome || profile.atualizado_por_nome || 'Não informado'),
                detailRow('Origem', source),
            ].join('');
        }

        const address = [value('#socioLogradouro'), value('#socioNumero'), value('#socioBairro')].filter(Boolean).join(', ');
        const contact = qs('#socioOverviewContact');
        if (contact) {
            contact.innerHTML = [
                detailRow('Telefone', value('#socioTelefone')),
                detailRow('Endereço', address || value('#socioComunidade')),
                detailRow('NIS', value('#socioNis')),
                detailRow('Nascimento', formatDate(value('#socioNascimento'))),
            ].join('');
        }

        const summary = qs('#socioOverviewSummary');
        if (summary) summary.textContent = value('#socioResumo').trim() || 'Nenhum resumo social registrado para esta pessoa.';
    };

    const selectTab = tab => {
        if (!tabMeta[tab]) tab = 'overview';
        qsa('[data-socio-tab]').forEach(button => {
            const active = button.dataset.socioTab === tab;
            button.classList.toggle('active', active);
            button.setAttribute('aria-selected', active ? 'true' : 'false');
        });
        qsa('[data-socio-panel]').forEach(panel => {
            const active = panel.dataset.socioPanel === tab;
            panel.hidden = !active;
            panel.classList.toggle('active', active);
        });
        if (recordTitle) recordTitle.textContent = tabMeta[tab][0];
        if (recordSubtitle) recordSubtitle.textContent = tabMeta[tab][1];
        if (tab === 'overview') refreshOverview();
    };

    const setEditMode = (state, updateTab = true) => {
        editing = Boolean(state && config.canEdit);
        recordShell?.classList.toggle('is-editing', editing);
        if (savebar) savebar.hidden = !editing;
        if (modeBadge) {
            modeBadge.className = `socio-mode-badge${editing ? ' is-editing' : ''}`;
            modeBadge.innerHTML = editing
                ? '<i class="bi bi-pencil-square"></i> Edição'
                : '<i class="bi bi-eye"></i> Consulta';
        }
        form?.querySelectorAll('input:not([type="hidden"]), select, textarea').forEach(field => {
            field.disabled = !editing;
        });
        qsa('[data-remove-member]').forEach(button => { button.disabled = !editing; });
        if (addMemberButton) addMemberButton.disabled = !editing;
        if (updateTab && editing && !lastLookup?.socioeconomic) selectTab('identity');
        refreshOverview();
    };

    const openWorkspace = (tab = 'overview', edit = false) => {
        if (!workspace) return;
        workspace.hidden = false;
        setEditMode(edit, false);
        selectTab(tab);
        workspace.scrollIntoView({ behavior: 'smooth', block: 'start' });
    };

    const renderLookup = data => {
        lastLookup = data;
        previewingAnexo = false;
        const cpf = data?.cpf || digits(searchInput?.value);
        clearForm(cpf);
        workspace.hidden = true;
        personCard.hidden = true;

        if (data?.registered) {
            fillFromPayload(data);
            setSearchResult(`<strong>Pessoa localizada no SIGAS.</strong> ${data.socioeconomic ? 'O prontuário existente está disponível para consulta.' : 'A pessoa já possui cadastro, mas ainda precisa do prontuário socioeconômico.'}`, 'success');
        } else if (data?.anexo?.found) {
            value('#socioCpf', cpf);
            setSearchResult('<strong>Pessoa localizada no ANEXO.</strong> Os dados podem ser usados como base para conferência, sem alterar o sistema de origem.', 'warning');
        } else {
            value('#socioCpf', cpf);
            setSearchResult('<strong>CPF não localizado.</strong> Este será um novo cadastro central no SIGAS; preencha o prontuário somente uma vez.', 'warning');
        }

        renderPersonCard(data);
        renderBenefits(data?.benefit_requests || []);
        renderHistory(data?.history || []);
        refreshOverview();
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
            personCard.hidden = true;
            workspace.hidden = true;
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
        motivo_atualizacao: lastLookup?.socioeconomic
            ? 'Revisão/atualização do prontuário'
            : 'Cadastro inicial do prontuário socioeconômico'
    });

    const save = async event => {
        event.preventDefault();
        if (!editing || !config.canEdit) return;
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
            if (lastLookup?.registered) openWorkspace('overview', false);
        } catch (error) {
            if (saveState) saveState.textContent = 'Falha ao salvar — revise os dados';
            showToast(error.message || 'Falha ao salvar o prontuário.', 'danger');
        } finally {
            setLoading(false);
        }
    };

    searchInput?.addEventListener('input', () => { searchInput.value = formatCpf(searchInput.value); });
    searchInput?.addEventListener('keydown', event => {
        if (event.key === 'Enter') {
            event.preventDefault();
            lookup();
        }
    });
    searchButton?.addEventListener('click', lookup);

    viewButton?.addEventListener('click', () => {
        if (!lastLookup) return;
        if (!lastLookup.registered && lastLookup?.anexo_draft) {
            fillAnexoDraft(lastLookup.anexo_draft);
            showToast('Visualizando uma prévia dos dados do ANEXO. Nada foi salvo no SIGAS.', 'info');
        }
        openWorkspace('overview', false);
    });

    editButton?.addEventListener('click', () => {
        if (!lastLookup || !config.canEdit) return;
        if (lastLookup.registered) {
            fillFromPayload(lastLookup);
        } else {
            clearForm(lastLookup.cpf || digits(searchInput?.value));
        }
        previewingAnexo = false;
        openWorkspace(lastLookup.socioeconomic ? 'overview' : 'identity', true);
    });

    useAnexoButton?.addEventListener('click', () => {
        if (!lastLookup?.anexo_draft || !config.canImport || !config.canEdit) return;
        fillAnexoDraft(lastLookup.anexo_draft);
        openWorkspace('identity', true);
        showToast('Dados do ANEXO carregados para conferência. Revise antes de salvar.', 'info');
    });

    qsa('[data-socio-tab]').forEach(button => {
        button.addEventListener('click', () => selectTab(button.dataset.socioTab || 'overview'));
    });

    addMemberButton?.addEventListener('click', () => addMember());
    familyList?.addEventListener('click', event => {
        const button = event.target.closest('[data-remove-member]');
        if (button && editing) {
            button.closest('.socio-family-row')?.remove();
            updateFamilyEmpty();
            refreshOverview();
        }
    });

    cancelEdit?.addEventListener('click', () => {
        if (!lastLookup) return;
        previewingAnexo = false;
        if (lastLookup.registered) fillFromPayload(lastLookup);
        else clearForm(lastLookup.cpf || digits(searchInput?.value));
        setEditMode(false);
        selectTab('overview');
        showToast('Alterações não salvas foram descartadas.', 'secondary');
    });

    form?.addEventListener('input', () => {
        if (editing) refreshOverview();
    });
    form?.addEventListener('change', () => {
        if (editing) refreshOverview();
    });
    form?.addEventListener('submit', save);

    setEditMode(false, false);
    selectTab('overview');

    if (config.initialCpf && digits(config.initialCpf).length === 11) {
        if (searchInput) searchInput.value = formatCpf(config.initialCpf);
        lookup();
    }
})();
