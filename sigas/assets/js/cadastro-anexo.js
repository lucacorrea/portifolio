'use strict';

(() => {
    const root = document.querySelector('[data-anexo-form]');
    if (!root) return;

    const form = document.getElementById('anexoForm');
    const panels = [...root.querySelectorAll('[data-anexo-panel]')];
    const steps = [...root.querySelectorAll('[data-anexo-step]')];
    const previousButton = document.getElementById('anexoPrevious');
    const nextButton = document.getElementById('anexoNext');
    const finishButton = document.getElementById('anexoFinish');
    const progressBar = document.getElementById('anexoProgressBar');
    const progress = progressBar?.closest('.progress');
    const progressLabel = document.getElementById('anexoProgressLabel');
    const progressTitle = document.getElementById('anexoProgressTitle');
    const progressPercent = document.getElementById('anexoProgressPercent');
    const actionHint = document.getElementById('anexoActionHint');
    const draftState = document.getElementById('draftState');
    const identityFields = document.getElementById('identityFields');
    const cpfInput = document.getElementById('anexoCpf');
    const validateCpfButton = document.getElementById('validateAnexoCpf');
    const identityResult = document.getElementById('identityGateResult');
    const identityTitle = document.getElementById('identityGateTitle');
    const identityText = document.getElementById('identityGateText');
    const identityBadge = document.getElementById('identityGateBadge');
    const identityIcon = document.getElementById('identityGateIcon');
    const identityOrigin = document.getElementById('identityOrigin');
    const memberList = document.getElementById('familyMembersList');
    const memberTemplate = document.getElementById('familyMemberTemplate');
    const memberEmpty = document.getElementById('familyMemberEmpty');
    const reviewGrid = document.getElementById('anexoReviewGrid');
    const selectedDocuments = document.getElementById('selectedDocuments');
    const storageKey = 'sigas-anexo-draft-v2';

    const stepMeta = [
        {
            title: 'Identificação',
            help: 'Valide primeiro o CPF. O restante do formulário só será liberado quando não houver duplicidade bloqueante.',
            checklist: ['CPF consultado nas duas bases', 'Nome completo conferido', 'Telefone informado']
        },
        {
            title: 'Endereço',
            help: 'Informe onde a família realmente reside. A unidade de referência deve corresponder ao território de acompanhamento.',
            checklist: ['Logradouro e número', 'Bairro ou comunidade', 'Unidade de referência']
        },
        {
            title: 'Situação socioeconômica',
            help: 'Registre a situação declarada no atendimento. Valores familiares serão calculados automaticamente a partir dos integrantes.',
            checklist: ['Situação de trabalho', 'Benefícios conferidos', 'Renda individual informada']
        },
        {
            title: 'Composição familiar',
            help: 'Inclua somente pessoas que residem no mesmo domicílio. Cônjuge e dependentes ficam reunidos nesta etapa para evitar duplicidade de campos.',
            checklist: ['Integrantes residentes', 'Parentesco informado', 'Rendas individuais conferidas']
        },
        {
            title: 'Habitação',
            help: 'Registre as condições atuais da moradia e do entorno. Use a observação apenas para informações que não cabem nos campos estruturados.',
            checklist: ['Situação do imóvel', 'Tipo de moradia', 'Serviços essenciais']
        },
        {
            title: 'Demanda',
            help: 'Descreva a demanda atual de forma objetiva. O histórico será registrado em atendimentos posteriores, sem sobrescrever o cadastro original.',
            checklist: ['Tipo de demanda', 'Prioridade definida', 'Resumo técnico preenchido']
        },
        {
            title: 'Documentos e revisão',
            help: 'Anexe somente o necessário, confira o resumo e confirme a responsabilidade pelo registro antes de concluir.',
            checklist: ['Documentos necessários', 'Dados essenciais revisados', 'Declaração confirmada']
        }
    ];

    let currentStep = 0;
    let maxVisitedStep = 0;
    let identityValidated = false;
    let identityDecision = '';
    let documentFiles = new Map();

    const escapeHtml = value => String(value ?? '').replace(/[&<>'"]/g, character => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;'
    }[character]));

    const notify = (message, type = 'success') => {
        if (window.SIGAS?.showToast) {
            window.SIGAS.showToast(message, type);
            return;
        }
        const container = document.getElementById('toastContainer');
        if (!container || typeof bootstrap === 'undefined') return;
        const toast = document.createElement('div');
        toast.className = 'toast border-0';
        toast.innerHTML = `<div class="d-flex"><div class="toast-body">${escapeHtml(message)}</div><button class="btn-close me-2 m-auto" type="button" data-bs-dismiss="toast" aria-label="Fechar"></button></div>`;
        container.appendChild(toast);
        const instance = new bootstrap.Toast(toast, { delay: 3200 });
        toast.addEventListener('hidden.bs.toast', () => toast.remove());
        instance.show();
    };

    const parseMoney = value => {
        const digits = String(value || '').replace(/\D/g, '');
        return digits ? Number(digits) / 100 : 0;
    };

    const formatMoneyNumber = value => Number(value || 0).toLocaleString('pt-BR', {
        style: 'currency', currency: 'BRL'
    });

    const formatMoneyInput = input => {
        input.value = formatMoneyNumber(parseMoney(input.value));
    };

    const formatCpf = value => {
        const digits = String(value || '').replace(/\D/g, '').slice(0, 11);
        return digits
            .replace(/^(\d{3})(\d)/, '$1.$2')
            .replace(/^(\d{3})\.(\d{3})(\d)/, '$1.$2.$3')
            .replace(/\.(\d{3})(\d)/, '.$1-$2');
    };

    const formatPhone = value => {
        const digits = String(value || '').replace(/\D/g, '').slice(0, 11);
        if (digits.length <= 10) {
            return digits.replace(/^(\d{2})(\d)/, '($1) $2').replace(/(\d{4})(\d)/, '$1-$2');
        }
        return digits.replace(/^(\d{2})(\d)/, '($1) $2').replace(/(\d{5})(\d)/, '$1-$2');
    };

    const setSystemDateTime = () => {
        const now = new Date();
        document.getElementById('systemDate').textContent = now.toLocaleDateString('pt-BR');
        document.getElementById('systemTime').textContent = now.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
    };

    const updateHelp = () => {
        const meta = stepMeta[currentStep];
        document.getElementById('stepHelpTitle').textContent = meta.title;
        document.getElementById('stepHelpText').textContent = meta.help;
        document.getElementById('stepChecklist').innerHTML = meta.checklist
            .map(item => `<li><i class="bi bi-circle"></i>${escapeHtml(item)}</li>`).join('');
    };

    const updateProgress = () => {
        const percentage = Math.round(((currentStep + 1) / panels.length) * 100);
        panels.forEach((panel, index) => {
            const active = index === currentStep;
            panel.classList.toggle('active', active);
            panel.hidden = !active;
        });
        steps.forEach((step, index) => {
            step.classList.toggle('active', index === currentStep);
            step.classList.toggle('done', index < currentStep);
            step.disabled = index > maxVisitedStep;
            step.setAttribute('aria-current', index === currentStep ? 'step' : 'false');
        });
        progressBar.style.width = `${percentage}%`;
        progress?.setAttribute('aria-valuenow', String(percentage));
        progressLabel.textContent = `Etapa ${currentStep + 1} de ${panels.length}`;
        progressTitle.textContent = stepMeta[currentStep].title;
        progressPercent.textContent = `${percentage}%`;
        previousButton.disabled = currentStep === 0;
        nextButton.classList.toggle('d-none', currentStep === panels.length - 1);
        finishButton.classList.toggle('d-none', currentStep !== panels.length - 1);
        nextButton.disabled = currentStep === 0 && !identityValidated;
        actionHint.textContent = currentStep === 0 && !identityValidated
            ? 'Valide o CPF para iniciar.'
            : currentStep === panels.length - 1
                ? 'Confira os dados e conclua o cadastro.'
                : 'Os campos obrigatórios da etapa serão validados ao avançar.';
        updateHelp();
        if (currentStep === panels.length - 1) updateReview();
        window.scrollTo({ top: 0, behavior: 'smooth' });
    };

    const markField = (field, valid) => {
        field.classList.toggle('is-invalid', !valid);
        if (valid) field.classList.remove('is-valid');
    };

    const validateCurrentPanel = () => {
        const panel = panels[currentStep];
        const requiredFields = [...panel.querySelectorAll('[required]')]
            .filter(field => !field.disabled && !field.closest('[hidden]'));
        let valid = true;
        requiredFields.forEach(field => {
            const fieldValid = field.type === 'checkbox' ? field.checked : String(field.value || '').trim() !== '';
            markField(field, fieldValid);
            valid = valid && fieldValid;
        });
        if (currentStep === 0 && !identityValidated) valid = false;
        if (!valid) notify('Preencha os campos obrigatórios antes de continuar.', 'danger');
        return valid;
    };

    const goToStep = target => {
        if (target < 0 || target >= panels.length) return;
        currentStep = target;
        maxVisitedStep = Math.max(maxVisitedStep, currentStep);
        updateProgress();
    };

    const renderIdentityResult = (data, allowed) => {
        identityResult.hidden = false;
        identityResult.className = `identity-gate-result ${data.severity}`;
        identityTitle.textContent = data.title;
        identityText.textContent = data.message;
        identityBadge.className = `status-badge status-${data.severity === 'success' ? 'success' : data.severity === 'warning' ? 'warning' : data.severity === 'info' ? 'info' : 'danger'}`;
        identityBadge.innerHTML = allowed
            ? '<i class="bi bi-check-circle"></i>Cadastro liberado'
            : '<i class="bi bi-slash-circle"></i>Cadastro bloqueado';
        identityIcon.innerHTML = `<i class="bi bi-${allowed ? 'shield-check' : 'shield-x'}"></i>`;
    };

    const resetIdentityValidation = () => {
        identityValidated = false;
        identityDecision = '';
        identityFields.disabled = true;
        identityResult.hidden = true;
        nextButton.disabled = true;
        actionHint.textContent = 'Valide o CPF para iniciar.';
        identityOrigin.textContent = 'SIGAS';
    };

    const validateIdentity = async () => {
        const digits = window.SIGASIntegration?.digits(cpfInput.value) || cpfInput.value.replace(/\D/g, '');
        if (digits.length !== 11) {
            cpfInput.classList.add('is-invalid');
            notify('Informe um CPF com 11 números.', 'danger');
            return;
        }
        cpfInput.classList.remove('is-invalid');
        validateCpfButton.disabled = true;
        validateCpfButton.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Consultando';
        const data = window.SIGASIntegration
            ? await window.SIGASIntegration.lookupByCpf(digits)
            : { decision: 'create-new', severity: 'success', title: 'Nenhum cadastro localizado', message: 'O cadastro pode prosseguir.', sigas: { found: false }, semth: { found: false } };
        validateCpfButton.disabled = false;
        validateCpfButton.innerHTML = '<i class="bi bi-search"></i>Consultar novamente';

        const allowed = ['create-new', 'create-reference'].includes(data.decision);
        renderIdentityResult(data, allowed);
        identityDecision = data.decision;
        identityValidated = allowed;
        identityFields.disabled = !allowed;
        nextButton.disabled = !allowed;

        if (data.decision === 'create-reference') {
            document.getElementById('anexoNome').value = data.name || '';
            document.getElementById('anexoNascimento').value = data.birthDate || '';
            identityOrigin.textContent = `SIGAS + referência ${data.semth.id || 'SEMTH'}`;
            notify('Referência SEMTH localizada. Os dados foram preenchidos apenas para conferência.', 'info');
        } else if (data.decision === 'create-new') {
            identityOrigin.textContent = 'Novo cadastro SIGAS';
            notify('Nenhuma duplicidade localizada. O cadastro foi liberado.');
        } else if (['open-existing', 'open-linked'].includes(data.decision)) {
            identityText.textContent += ' Utilize a listagem de pessoas para abrir o prontuário existente.';
        } else if (data.decision === 'review-conflict') {
            identityText.textContent += ' Encaminhe o caso ao painel de integração antes de qualquer novo registro.';
        }
        updateProgress();
    };

    const toggleConditional = control => {
        const targetSelector = control.dataset.toggleTarget || control.dataset.toggleOther;
        if (!targetSelector) return;
        const target = document.querySelector(targetSelector);
        if (!target) return;
        const values = control.dataset.toggleValues
            ? control.dataset.toggleValues.split(',').map(value => value.trim())
            : [control.dataset.toggleValue || 'Outros'];
        const visible = values.includes(control.value);
        target.hidden = !visible;
        target.querySelectorAll('input, select, textarea').forEach(field => {
            field.disabled = !visible;
            if (!visible) field.classList.remove('is-invalid');
        });
    };

    const updateFamilyTotals = () => {
        const members = [...memberList.querySelectorAll('[data-family-member]')];
        const responsibleIncome = parseMoney(document.getElementById('anexoRendaIndividual').value);
        const memberIncome = members.reduce((sum, member) => sum + parseMoney(member.querySelector('.member-income')?.value), 0);
        const benefitIncome = ['anexoBpcValor', 'anexoPbfValor', 'anexoBeneficioMunicipalValor', 'anexoBeneficioEstadualValor']
            .reduce((sum, id) => sum + parseMoney(document.getElementById(id)?.value), 0);
        const people = 1 + members.length;
        const totalIncome = responsibleIncome + memberIncome + benefitIncome;
        const perCapita = people ? totalIncome / people : 0;
        const pcdCount = (document.getElementById('anexoPcd').value === 'Sim' ? 1 : 0)
            + members.filter(member => member.querySelector('.member-pcd')?.value === 'Sim').length;

        document.getElementById('calcResidents').textContent = people;
        document.getElementById('calcFamilyIncome').textContent = formatMoneyNumber(totalIncome);
        document.getElementById('calcPerCapita').textContent = formatMoneyNumber(perCapita);
        document.getElementById('calcPcd').textContent = pcdCount;
        document.getElementById('familyTotalPeople').textContent = people;
        document.getElementById('familyTotalIncome').textContent = formatMoneyNumber(totalIncome);
        document.getElementById('familyPerCapita').textContent = formatMoneyNumber(perCapita);
        document.getElementById('anexoTotalMoradores').value = String(people);
        document.getElementById('anexoRendaFamiliar').value = totalIncome.toFixed(2);
        document.getElementById('anexoTotalRendimentos').value = totalIncome.toFixed(2);
        document.getElementById('anexoTotalPcd').value = String(pcdCount);
    };

    const refreshMemberCards = () => {
        const members = [...memberList.querySelectorAll('[data-family-member]')];
        memberEmpty.hidden = members.length > 0;
        members.forEach((member, index) => {
            member.querySelector('.family-member-index').textContent = `Integrante ${index + 1}`;
            const name = member.querySelector('.member-name')?.value.trim();
            member.querySelector('[data-member-title]').textContent = name || 'Novo integrante';
        });
        updateFamilyTotals();
    };

    const addFamilyMember = data => {
        const fragment = memberTemplate.content.cloneNode(true);
        const member = fragment.querySelector('[data-family-member]');
        memberList.appendChild(fragment);
        if (data) {
            Object.entries(data).forEach(([name, value]) => {
                const field = member.querySelector(`[name="${CSS.escape(name)}"]`);
                if (field) field.value = value;
            });
        }
        refreshMemberCards();
        member.querySelector('.member-name')?.focus();
    };

    const collectFormData = () => {
        const data = {};
        const fields = [...form.querySelectorAll('input, select, textarea')]
            .filter(field => field.name && field.type !== 'file');
        fields.forEach(field => {
            if (field.name.endsWith('[]')) return;
            data[field.name] = field.type === 'checkbox' ? field.checked : field.value;
        });
        data.identityValidated = identityValidated;
        data.identityDecision = identityDecision;
        data.currentStep = currentStep;
        data.members = [...memberList.querySelectorAll('[data-family-member]')].map(member => {
            const memberData = {};
            member.querySelectorAll('[name$="[]"]').forEach(field => { memberData[field.name] = field.value; });
            return memberData;
        });
        return data;
    };

    const saveDraft = () => {
        try {
            localStorage.setItem(storageKey, JSON.stringify(collectFormData()));
            draftState.innerHTML = '<i class="bi bi-cloud-check"></i>Rascunho salvo agora';
            draftState.classList.add('saved');
            notify('Rascunho salvo neste navegador.');
        } catch (error) {
            notify('Não foi possível salvar o rascunho neste navegador.', 'danger');
        }
    };

    const prefillEditMode = () => {
        const editMode = new URLSearchParams(window.location.search).get('modo') === 'editar';
        if (!editMode || localStorage.getItem(storageKey)) return false;
        const values = {
            anexoCpf: '123.456.789-09',
            anexoNome: 'Maria da Silva',
            anexoNis: '12345678900',
            anexoRg: '1234567-8',
            anexoRgUf: 'AM',
            anexoNascimento: '1986-03-14',
            anexoGenero: 'Feminino',
            anexoEstadoCivil: 'União estável',
            anexoNaturalidade: 'Coari/AM',
            anexoNacionalidade: 'Brasileiro(a)',
            anexoTelefone: '(97) 99999-4321',
            anexoEndereco: 'Rua das Acácias',
            anexoNumero: '125',
            anexoBairro: 'São Sebastião',
            anexoReferencia: 'Próximo à escola municipal',
            anexoUnidade: 'Casa do Cidadão',
            anexoTrabalho: 'Trabalho informal',
            anexoRendaIndividual: 'R$ 620,00',
            anexoPbf: 'Sim',
            anexoPbfValor: 'R$ 800,00',
            anexoSituacaoImovel: 'Alugado',
            anexoSituacaoImovelValor: 'R$ 450,00',
            anexoTipoMoradia: 'Madeira',
            anexoAbastecimento: 'Rede pública',
            anexoIluminacao: 'Rede pública com medidor',
            anexoEsgoto: 'Fossa rudimentar',
            anexoLixo: 'Coleta pública',
            anexoCategoria: 'Cesta de alimentos',
            anexoPrioridade: 'Normal',
            anexoResumoCaso: 'Família solicita atualização cadastral e avaliação para benefício eventual, em razão de redução temporária da renda.'
        };
        Object.entries(values).forEach(([id, value]) => {
            const field = document.getElementById(id);
            if (field) field.value = value;
        });
        identityValidated = true;
        identityDecision = 'open-existing';
        identityFields.disabled = false;
        identityResult.hidden = false;
        identityResult.className = 'identity-gate-result info';
        identityTitle.textContent = 'Editando prontuário existente no SIGAS';
        identityText.textContent = 'As alterações serão aplicadas somente ao prontuário ANX-000125. A referência SEMTH permanece somente leitura.';
        identityBadge.className = 'status-badge status-info';
        identityBadge.innerHTML = '<i class="bi bi-pencil"></i>Modo de edição';
        identityOrigin.textContent = 'SIGAS + referência SEMTH';
        nextButton.disabled = false;
        const pageTitle = root.querySelector('.anexo-page-header h1');
        if (pageTitle) pageTitle.textContent = 'Editar Cadastro ANEXO';
        addFamilyMember({
            'fam_nome[]': 'João da Silva',
            'fam_parentesco[]': 'Cônjuge',
            'fam_nasc[]': '1982-07-10',
            'fam_escolaridade[]': 'Fundamental incompleto',
            'fam_renda[]': 'R$ 0,00',
            'fam_pcd[]': 'Não',
            'fam_bpc[]': 'Não',
            'fam_ocupacao[]': 'Desempregado'
        });
        document.querySelectorAll('[data-toggle-target], [data-toggle-other]').forEach(toggleConditional);
        document.getElementById('caseSummaryCount').textContent = String(document.getElementById('anexoResumoCaso').value.length);
        updateFamilyTotals();
        draftState.innerHTML = '<i class="bi bi-pencil-square"></i>Modo de edição';
        draftState.classList.add('saved');
        return true;
    };

    const restoreDraft = () => {
        const raw = localStorage.getItem(storageKey);
        if (!raw) return;
        try {
            const data = JSON.parse(raw);
            Object.entries(data).forEach(([name, value]) => {
                if (['members', 'identityValidated', 'identityDecision', 'currentStep'].includes(name)) return;
                const field = form.elements.namedItem(name);
                if (!field || typeof value === 'object') return;
                if (field.type === 'checkbox') field.checked = Boolean(value);
                else field.value = value;
            });
            (data.members || []).forEach(addFamilyMember);
            identityValidated = Boolean(data.identityValidated);
            identityDecision = data.identityDecision || '';
            if (identityValidated) {
                identityFields.disabled = false;
                identityResult.hidden = false;
                identityResult.className = 'identity-gate-result success';
                identityTitle.textContent = 'Validação restaurada do rascunho';
                identityText.textContent = 'Confira novamente o CPF antes de concluir o cadastro.';
                identityBadge.className = 'status-badge status-success';
                identityBadge.innerHTML = '<i class="bi bi-check-circle"></i>Cadastro liberado';
                nextButton.disabled = false;
            }
            currentStep = Math.min(Number(data.currentStep || 0), panels.length - 1);
            maxVisitedStep = currentStep;
            document.querySelectorAll('[data-toggle-target], [data-toggle-other]').forEach(toggleConditional);
            updateFamilyTotals();
            updateProgress();
            draftState.innerHTML = '<i class="bi bi-clock-history"></i>Rascunho restaurado';
            draftState.classList.add('saved');
        } catch (error) {
            localStorage.removeItem(storageKey);
        }
    };

    const documentRow = (category, file) => `<div class="selected-document-row"><span class="selected-document-icon"><i class="bi bi-file-earmark-check"></i></span><div><strong>${escapeHtml(category)}</strong><small>${escapeHtml(file.name)} · ${(file.size / 1024).toFixed(1)} KB</small></div><button class="btn btn-light btn-sm" type="button" data-remove-document="${escapeHtml(category)}" aria-label="Remover ${escapeHtml(category)}"><i class="bi bi-x-lg"></i></button></div>`;

    const renderDocuments = () => {
        if (!documentFiles.size) {
            selectedDocuments.innerHTML = '<div class="selected-documents-empty"><i class="bi bi-paperclip"></i>Nenhum documento selecionado.</div>';
            return;
        }
        selectedDocuments.innerHTML = [...documentFiles.entries()].map(([category, file]) => documentRow(category, file)).join('');
    };

    const textValue = (id, fallback = 'Não informado') => {
        const field = document.getElementById(id);
        return field && String(field.value || '').trim() ? field.value.trim() : fallback;
    };

    const updateReview = () => {
        updateFamilyTotals();
        const summary = [
            ['Responsável familiar', textValue('anexoNome')],
            ['CPF', textValue('anexoCpf')],
            ['Nascimento', textValue('anexoNascimento')],
            ['Telefone', textValue('anexoTelefone')],
            ['Bairro / comunidade', textValue('anexoBairro')],
            ['Unidade de referência', textValue('anexoUnidade')],
            ['Pessoas no domicílio', document.getElementById('familyTotalPeople').textContent],
            ['Renda familiar', document.getElementById('familyTotalIncome').textContent],
            ['Situação do imóvel', textValue('anexoSituacaoImovel')],
            ['Demanda atual', textValue('anexoCategoria')],
            ['Prioridade', textValue('anexoPrioridade')],
            ['Documentos anexados', String(documentFiles.size)]
        ];
        reviewGrid.innerHTML = summary.map(([label, value]) => `<div><span>${escapeHtml(label)}</span><strong>${escapeHtml(value)}</strong></div>`).join('');
    };

    previousButton.addEventListener('click', () => goToStep(currentStep - 1));
    nextButton.addEventListener('click', () => {
        if (validateCurrentPanel()) goToStep(currentStep + 1);
    });
    steps.forEach(step => step.addEventListener('click', () => {
        const target = Number(step.dataset.anexoStep);
        if (target <= maxVisitedStep && (target < currentStep || validateCurrentPanel())) goToStep(target);
    }));

    validateCpfButton.addEventListener('click', validateIdentity);
    cpfInput.addEventListener('input', () => {
        cpfInput.value = formatCpf(cpfInput.value);
        cpfInput.classList.remove('is-invalid');
        resetIdentityValidation();
    });

    form.addEventListener('input', event => {
        const field = event.target;
        field.classList.remove('is-invalid');
        if (field.classList.contains('money-field')) updateFamilyTotals();
        if (field.classList.contains('member-name')) refreshMemberCards();
        if (field.id === 'anexoResumoCaso') document.getElementById('caseSummaryCount').textContent = String(field.value.length);
        draftState.innerHTML = '<i class="bi bi-cloud-slash"></i>Alterações não salvas';
        draftState.classList.remove('saved');
    });

    form.addEventListener('change', event => {
        const field = event.target;
        if (field.matches('[data-toggle-target], [data-toggle-other]')) toggleConditional(field);
        if (field.classList.contains('member-pcd') || field.id === 'anexoPcd') updateFamilyTotals();
    });

    root.querySelectorAll('.money-field').forEach(input => {
        input.addEventListener('blur', () => {
            if (input.value.trim()) formatMoneyInput(input);
            updateFamilyTotals();
        });
    });

    root.querySelectorAll('#anexoTelefone, #anexoTelefoneAlternativo').forEach(input => {
        input.addEventListener('input', () => { input.value = formatPhone(input.value); });
    });

    document.addEventListener('click', event => {
        if (event.target.closest('[data-add-family-member]')) addFamilyMember();
        const removeMember = event.target.closest('[data-remove-family-member]');
        if (removeMember) {
            removeMember.closest('[data-family-member]')?.remove();
            refreshMemberCards();
            notify('Integrante removido do formulário.', 'info');
        }
        const removeDocument = event.target.closest('[data-remove-document]');
        if (removeDocument) {
            documentFiles.delete(removeDocument.dataset.removeDocument);
            const input = [...root.querySelectorAll('[data-document-input]')]
                .find(item => item.dataset.documentInput === removeDocument.dataset.removeDocument);
            if (input) input.value = '';
            renderDocuments();
        }
        if (event.target.closest('[data-go-first-step]')) goToStep(0);
        if (event.target.closest('[data-save-anexo-draft]')) saveDraft();
    });

    root.querySelectorAll('[data-document-input]').forEach(input => {
        input.addEventListener('change', () => {
            const file = input.files?.[0];
            const category = input.dataset.documentInput;
            if (!file) {
                documentFiles.delete(category);
                renderDocuments();
                return;
            }
            const allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];
            if (!allowedTypes.includes(file.type) || file.size > 10 * 1024 * 1024) {
                input.value = '';
                notify('Use PDF, JPG, PNG ou WEBP com até 10 MB.', 'danger');
                return;
            }
            documentFiles.set(category, file);
            renderDocuments();
            notify(`${category}: documento selecionado.`);
        });
    });

    form.addEventListener('submit', event => {
        event.preventDefault();
        if (!validateCurrentPanel()) return;
        localStorage.removeItem(storageKey);
        const modal = document.getElementById('anexoSuccessModal');
        if (modal && typeof bootstrap !== 'undefined') bootstrap.Modal.getOrCreateInstance(modal).show();
    });

    setSystemDateTime();
    document.querySelectorAll('[data-toggle-target], [data-toggle-other]').forEach(toggleConditional);
    renderDocuments();
    refreshMemberCards();
    updateProgress();
    if (!prefillEditMode()) restoreDraft();
})();
