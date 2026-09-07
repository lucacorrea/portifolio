'use strict';

(() => {
    const access = window.SIGAS_CONTEXT?.operationalProgramAccess;
    if (!access || !access.module) return;

    const currentModule = String(access.module || '');
    const currentPage = String(access.page || '');
    const visiblePages = new Set(Array.isArray(access.visiblePages) ? access.visiblePages.map(String) : []);

    const moduleHomes = {
        'kit-maternidade': 'kit-maternidade/index.php',
        'aluguel-social': 'aluguel-social/index.php',
        'beneficios-eventuais': 'beneficios-eventuais/index.php'
    };

    const escapeHTML = value => String(value ?? '').replace(/[&<>"']/g, character => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
    }[character]));

    const showToast = (message, type = 'warning') => {
        if (window.SIGAS_FRONTEND?.showToast) {
            window.SIGAS_FRONTEND.showToast(message, type);
            return;
        }
        window.alert(message);
    };

    const moduleHref = page => {
        const home = moduleHomes[currentModule];
        if (!home) return 'portal.php';
        return !page || page === 'painel' ? home : `${home}?pagina=${encodeURIComponent(page)}`;
    };

    const normalizeLegacyHref = href => {
        try {
            const url = new URL(href, window.location.href);
            if (!url.pathname.endsWith('/setor.php')) return href;
            const module = url.searchParams.get('ambiente') || '';
            const page = url.searchParams.get('pagina') || 'painel';
            if (!moduleHomes[module]) return href;
            return page === 'painel' ? moduleHomes[module] : `${moduleHomes[module]}?pagina=${encodeURIComponent(page)}`;
        } catch (_) {
            return href;
        }
    };

    document.querySelectorAll('a[href*="setor.php?ambiente="]').forEach(link => {
        const normalized = normalizeLegacyHref(link.getAttribute('href') || '');
        if (normalized) link.setAttribute('href', normalized);
    });

    // As ações das linhas são montadas pelo frontend-modules.js. Interceptamos apenas
    // navegações entre os três módulos independentes para manter a URL moderna e
    // impedir que um link visual leve a uma página oculta pela permissão atual.
    document.addEventListener('click', event => {
        const button = event.target.closest('[data-sigas-row-action-payload]');
        if (!button) return;

        let action = null;
        try {
            action = JSON.parse(button.dataset.sigasRowActionPayload || '{}');
        } catch (_) {
            action = null;
        }

        if (!action || action.kind !== 'navigate' || !action.page) return;

        const page = String(action.page);
        event.preventDefault();
        event.stopImmediatePropagation();

        if (visiblePages.size && !visiblePages.has(page)) {
            showToast('Seu nível de acesso não permite abrir esta área.', 'warning');
            return;
        }

        window.location.assign(moduleHref(page));
    }, true);

    const schemas = {
        'kit-maternidade': {
            cadastro: {
                title: 'Cadastro e triagem da gestante',
                fields: [
                    ['cpf', 'CPF', 'text', '000.000.000-00'],
                    ['nome', 'Nome completo', 'text', 'Nome da gestante'],
                    ['telefone', 'Telefone', 'text', '(97) 9 0000-0000'],
                    ['dpp', 'Data provável do parto', 'date', ''],
                    ['gestacao', 'Idade gestacional', 'text', 'Ex.: 28 semanas'],
                    ['origem', 'Origem do atendimento', 'select', ['CRAS', 'SEMAS', 'Busca ativa', 'Demanda espontânea']],
                    ['observacao', 'Observações da triagem', 'textarea', 'Documentos, critérios e pendências identificadas.']
                ]
            },
            visitas: {
                title: 'Registro de visita domiciliar',
                fields: [
                    ['beneficiaria', 'Beneficiária', 'text', 'Nome ou CPF'],
                    ['data', 'Data da visita', 'date', ''],
                    ['profissional', 'Profissional responsável', 'text', 'Nome do técnico'],
                    ['resultado', 'Resultado', 'select', ['Realizada', 'Não localizada', 'Reagendada', 'Recusada']],
                    ['proxima', 'Próxima ação', 'select', ['Nova visita', 'Reunião', 'Avaliação', 'Sem pendência']],
                    ['observacao', 'Relato da visita', 'textarea', 'Síntese objetiva do acompanhamento.']
                ]
            },
            reunioes: {
                title: 'Reunião ou atividade',
                fields: [
                    ['atividade', 'Atividade', 'text', 'Nome da reunião/orientação'],
                    ['data', 'Data', 'date', ''],
                    ['beneficiaria', 'Beneficiária', 'text', 'Nome ou CPF'],
                    ['presenca', 'Participação', 'select', ['Presente', 'Ausente', 'Ausência justificada']],
                    ['observacao', 'Observação', 'textarea', 'Orientações ou justificativa.']
                ]
            },
            avaliacao: {
                title: 'Avaliação para contemplação',
                fields: [
                    ['beneficiaria', 'Beneficiária', 'text', 'Nome ou CPF'],
                    ['situacao', 'Resultado da avaliação', 'select', ['Apta', 'Pendente', 'Não apta']],
                    ['criterios', 'Critérios conferidos', 'textarea', 'Registre os critérios e documentos considerados.'],
                    ['parecer', 'Parecer', 'textarea', 'Fundamentação técnica da decisão.'],
                    ['proxima', 'Próxima etapa', 'select', ['Entrega', 'Complementar documentação', 'Novo acompanhamento', 'Encerrar']]
                ]
            },
            entregas: {
                title: 'Registro de entrega do Kit',
                fields: [
                    ['beneficiaria', 'Beneficiária', 'text', 'Nome ou CPF'],
                    ['data', 'Data da entrega', 'date', ''],
                    ['lote', 'Lote / referência', 'text', 'Identificação do lote'],
                    ['responsavel', 'Responsável pela entrega', 'text', 'Servidor responsável'],
                    ['termo', 'Termo/comprovante', 'text', 'Número ou referência'],
                    ['observacao', 'Observação', 'textarea', 'Ocorrências da entrega.']
                ]
            },
            'pos-parto': {
                title: 'Pós-parto e encerramento',
                fields: [
                    ['beneficiaria', 'Beneficiária', 'text', 'Nome ou CPF'],
                    ['nascimento', 'Data do nascimento', 'date', ''],
                    ['kit', 'Situação do Kit', 'select', ['Entregue antes do parto', 'Entregue após o parto', 'Não entregue']],
                    ['encerramento', 'Situação do acompanhamento', 'select', ['Concluído', 'Manter acompanhamento', 'Encerrar com justificativa']],
                    ['observacao', 'Observação final', 'textarea', 'Registre justificativas e encaminhamentos.']
                ]
            }
        },
        'aluguel-social': {
            solicitacoes: {
                title: 'Nova solicitação de Aluguel Social',
                fields: [
                    ['cpf', 'CPF do responsável familiar', 'text', '000.000.000-00'],
                    ['familia', 'Responsável familiar', 'text', 'Nome completo'],
                    ['motivo', 'Motivo da solicitação', 'select', ['Desabrigamento', 'Risco estrutural', 'Sinistro', 'Violência/risco', 'Outra situação']],
                    ['endereco', 'Endereço de referência', 'text', 'Rua, número, bairro'],
                    ['prioridade', 'Prioridade', 'select', ['Normal', 'Prioritária', 'Urgente']],
                    ['observacao', 'Resumo da situação', 'textarea', 'Descrição objetiva da demanda.']
                ]
            },
            vistorias: {
                title: 'Registro de vistoria',
                fields: [
                    ['processo', 'Solicitação / processo', 'text', 'Referência'],
                    ['data', 'Data da vistoria', 'date', ''],
                    ['tecnico', 'Técnico responsável', 'text', 'Nome do profissional'],
                    ['condicao', 'Condição constatada', 'select', ['Risco confirmado', 'Risco não confirmado', 'Necessita nova avaliação']],
                    ['imovel', 'Situação do imóvel', 'textarea', 'Condições observadas no local.'],
                    ['recomendacao', 'Recomendação', 'textarea', 'Providências e encaminhamento sugerido.']
                ]
            },
            pareceres: {
                title: 'Parecer técnico-social',
                fields: [
                    ['processo', 'Processo', 'text', 'Referência da solicitação'],
                    ['decisao', 'Decisão', 'select', ['Deferir', 'Indeferir', 'Devolver para pendência']],
                    ['fundamentacao', 'Fundamentação', 'textarea', 'Critérios, documentos e análise técnica.'],
                    ['prazo', 'Prazo recomendado', 'text', 'Ex.: 6 meses'],
                    ['observacao', 'Observação administrativa', 'textarea', 'Informação complementar.']
                ]
            },
            concessoes: {
                title: 'Concessão do benefício',
                fields: [
                    ['beneficiario', 'Beneficiário', 'text', 'Nome ou CPF'],
                    ['proprietario', 'Proprietário do imóvel', 'text', 'Nome completo'],
                    ['imovel', 'Endereço do imóvel', 'text', 'Rua, número, bairro'],
                    ['valor', 'Valor mensal', 'number', '0,00'],
                    ['inicio', 'Início da vigência', 'date', ''],
                    ['fim', 'Fim da vigência', 'date', ''],
                    ['observacao', 'Condições da concessão', 'textarea', 'Termo, obrigações e observações.']
                ]
            },
            pagamentos: {
                title: 'Competência de pagamento',
                fields: [
                    ['beneficiario', 'Beneficiário', 'text', 'Nome ou CPF'],
                    ['competencia', 'Competência', 'month', ''],
                    ['valor', 'Valor', 'number', '0,00'],
                    ['situacao', 'Situação', 'select', ['Previsto', 'Autorizado', 'Pago', 'Pendente', 'Suspenso']],
                    ['referencia', 'Comprovante / referência', 'text', 'Número do documento'],
                    ['observacao', 'Observação', 'textarea', 'Pendência ou informação financeira.']
                ]
            },
            reavaliacoes: {
                title: 'Reavaliação do Aluguel Social',
                fields: [
                    ['beneficiario', 'Beneficiário', 'text', 'Nome ou CPF'],
                    ['data', 'Data da reavaliação', 'date', ''],
                    ['resultado', 'Resultado', 'select', ['Renovar', 'Manter até o fim da vigência', 'Suspender', 'Encerrar']],
                    ['novo_prazo', 'Novo prazo', 'text', 'Quando houver renovação'],
                    ['fundamentacao', 'Fundamentação', 'textarea', 'Motivo técnico-social da decisão.']
                ]
            }
        },
        'beneficios-eventuais': {
            solicitacoes: {
                title: 'Nova solicitação de Benefício Eventual',
                fields: [
                    ['cpf', 'CPF', 'text', '000.000.000-00'],
                    ['pessoa', 'Solicitante', 'text', 'Nome completo'],
                    ['tipo', 'Tipo de benefício', 'select', ['Auxílio natalidade', 'Auxílio funeral', 'Cesta/alimento', 'Passagem', 'Documento', 'Outro']],
                    ['motivo', 'Motivo da demanda', 'textarea', 'Resumo da necessidade apresentada.'],
                    ['prioridade', 'Prioridade', 'select', ['Normal', 'Prioritária', 'Urgente']]
                ]
            },
            triagem: {
                title: 'Triagem do benefício',
                fields: [
                    ['solicitacao', 'Solicitação', 'text', 'Referência'],
                    ['cadastro', 'Cadastro conferido', 'select', ['Sim', 'Com pendência', 'Não localizado']],
                    ['documentos', 'Documentação', 'select', ['Completa', 'Incompleta', 'Dispensada conforme regra']],
                    ['historico', 'Histórico consultado', 'select', ['Sem ocorrência relevante', 'Possui concessões anteriores', 'Necessita análise']],
                    ['observacao', 'Pendências / observações', 'textarea', 'Registre o que precisa ser analisado.']
                ]
            },
            analises: {
                title: 'Análise e parecer',
                fields: [
                    ['solicitacao', 'Solicitação', 'text', 'Referência'],
                    ['decisao', 'Decisão', 'select', ['Deferir', 'Indeferir', 'Retornar para pendência']],
                    ['criterios', 'Critérios analisados', 'textarea', 'Critérios aplicados ao caso.'],
                    ['parecer', 'Parecer', 'textarea', 'Fundamentação da decisão.']
                ]
            },
            concessoes: {
                title: 'Concessão do benefício eventual',
                fields: [
                    ['solicitacao', 'Solicitação', 'text', 'Referência'],
                    ['tipo', 'Benefício', 'text', 'Tipo aprovado'],
                    ['quantidade', 'Quantidade', 'number', '1'],
                    ['valor', 'Valor aplicado', 'number', '0,00'],
                    ['validade', 'Validade da autorização', 'date', ''],
                    ['observacao', 'Condições', 'textarea', 'Informações da autorização.']
                ]
            },
            entregas: {
                title: 'Entrega / efetivação do benefício',
                fields: [
                    ['beneficiario', 'Beneficiário', 'text', 'Nome ou CPF'],
                    ['beneficio', 'Benefício', 'text', 'Tipo concedido'],
                    ['data', 'Data da entrega', 'date', ''],
                    ['quantidade', 'Quantidade', 'number', '1'],
                    ['responsavel', 'Responsável pela entrega', 'text', 'Servidor responsável'],
                    ['comprovante', 'Comprovante', 'text', 'Referência do termo/foto/documento'],
                    ['observacao', 'Ocorrência', 'textarea', 'Informação complementar ou motivo de não entrega.']
                ]
            },
            tipos: {
                title: 'Tipo e regra de benefício',
                fields: [
                    ['nome', 'Nome do benefício', 'text', 'Ex.: Auxílio natalidade'],
                    ['periodicidade', 'Periodicidade', 'select', ['Única', 'Mensal', 'Trimestral', 'Eventual']],
                    ['documentos', 'Documentação exigida', 'textarea', 'Documentos e comprovações exigidas.'],
                    ['criterios', 'Critérios', 'textarea', 'Regras administrativas e sociais.'],
                    ['situacao', 'Situação', 'select', ['Ativo', 'Inativo']]
                ]
            }
        }
    };

    const createField = ([name, label, type, optionsOrPlaceholder]) => {
        const id = `operational_${currentModule}_${currentPage}_${name}`.replaceAll('-', '_');
        const colClass = type === 'textarea' ? 'col-12' : 'col-12 col-md-6';

        if (type === 'select') {
            const options = Array.isArray(optionsOrPlaceholder) ? optionsOrPlaceholder : [];
            return `<div class="${colClass}"><label class="form-label" for="${escapeHTML(id)}">${escapeHTML(label)}</label><select class="form-select" id="${escapeHTML(id)}" name="${escapeHTML(name)}" required><option value="">Selecione</option>${options.map(option => `<option>${escapeHTML(option)}</option>`).join('')}</select></div>`;
        }

        if (type === 'textarea') {
            return `<div class="${colClass}"><label class="form-label" for="${escapeHTML(id)}">${escapeHTML(label)}</label><textarea class="form-control" id="${escapeHTML(id)}" name="${escapeHTML(name)}" rows="3" maxlength="800" placeholder="${escapeHTML(optionsOrPlaceholder || '')}" required></textarea></div>`;
        }

        const inputType = ['date', 'month', 'number'].includes(type) ? type : 'text';
        const step = inputType === 'number' ? ' step="0.01" min="0"' : '';
        return `<div class="${colClass}"><label class="form-label" for="${escapeHTML(id)}">${escapeHTML(label)}</label><input class="form-control" id="${escapeHTML(id)}" name="${escapeHTML(name)}" type="${inputType}"${step} placeholder="${escapeHTML(optionsOrPlaceholder || '')}" required></div>`;
    };

    const actionModal = document.querySelector('#frontendActionModal');
    if (actionModal) {
        actionModal.addEventListener('show.bs.modal', () => {
            const schema = schemas[currentModule]?.[currentPage];
            if (!schema) return;

            const title = actionModal.querySelector('#frontendActionTitle');
            const body = actionModal.querySelector('.modal-body');
            const eyebrow = actionModal.querySelector('.eyebrow');
            const submit = actionModal.querySelector('button[type="submit"]');

            if (title) title.textContent = schema.title;
            if (eyebrow) eyebrow.innerHTML = '<i class="bi bi-ui-checks-grid"></i> Formulário operacional';
            if (submit) submit.innerHTML = '<i class="bi bi-check2"></i> Validar formulário';

            if (body) {
                body.innerHTML = `<div class="operational-front-note"><i class="bi bi-shield-check"></i><div><strong>Front-end operacional concluído</strong><span>Os campos, validações e fluxo visual estão prontos. Nesta etapa de front os dados ainda não são persistidos.</span></div></div><div class="row g-3 mt-1">${schema.fields.map(createField).join('')}</div>`;
            }
        });
    }

    // Torna as ações genéricas do cabeçalho mais descritivas sem precisar repetir
    // a mesma configuração em dezenas de views de protótipo.
    const headerActionLabels = {
        'kit-maternidade': {
            beneficiarias: 'Cadastrar gestante', cadastro: 'Iniciar cadastro', visitas: 'Registrar visita', reunioes: 'Registrar atividade', avaliacao: 'Registrar avaliação', entregas: 'Registrar entrega', 'pos-parto': 'Registrar pós-parto'
        },
        'aluguel-social': {
            solicitacoes: 'Nova solicitação', vistorias: 'Registrar vistoria', pareceres: 'Registrar parecer', concessoes: 'Nova concessão', pagamentos: 'Registrar pagamento', reavaliacoes: 'Registrar reavaliação'
        },
        'beneficios-eventuais': {
            solicitacoes: 'Nova solicitação', triagem: 'Registrar triagem', analises: 'Registrar análise', concessoes: 'Nova concessão', entregas: 'Registrar entrega', tipos: 'Novo tipo de benefício'
        }
    };

    const contextualLabel = headerActionLabels[currentModule]?.[currentPage];
    if (contextualLabel) {
        document.querySelectorAll('.sigas-page-actions [data-demo-action]').forEach(button => {
            const previous = button.dataset.demoAction || '';
            if (!previous || previous === 'Nova ação') {
                button.dataset.demoAction = contextualLabel;
                const textNode = [...button.childNodes].find(node => node.nodeType === Node.TEXT_NODE);
                if (textNode) textNode.textContent = contextualLabel;
            }
        });
    }
})();
