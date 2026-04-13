document.addEventListener('DOMContentLoaded', () => {
    const listTable = document.getElementById('lista-processos');
    const formProcesso = document.getElementById('form-processo');
    const nomeAnalisadorExibicao = document.getElementById('nome-analisador');
    let analisadorAtivo = 'TODOS';
    let dadosOriginais = [];
    let paginaAtual = 1;
    const itensPorPagina = 10;
    
    // Mask para numero de processo
    const inputNumero = document.getElementById('numero_processo');
    if (inputNumero) {
        inputNumero.addEventListener('input', e => {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length > 20) value = value.slice(0, 20);
            
            let formatted = '';
            if (value.length > 0) formatted += value.slice(0, 7);
            if (value.length > 7) formatted += '-' + value.slice(7, 9);
            if (value.length > 9) formatted += '.' + value.slice(9, 13);
            if (value.length > 13) formatted += '.' + value.slice(13, 14);
            if (value.length > 14) formatted += '.' + value.slice(14, 16);
            if (value.length > 16) formatted += '.' + value.slice(16);
            
            e.target.value = formatted;
        });
    }

    // Tabs de analisadores renderizados dinamicamente

    
    // Carregar dados se estiver na index
    if (listTable) {
        carregarProcessos();
        setInterval(carregarProcessos, 30000); // Atualiza a cada 30s

        const inputBusca = document.getElementById('filtro-busca');
        const selectTipoProcesso = document.getElementById('filtro-tipo-processo');
        const selectStatus = document.getElementById('filtro-status');
        const inputDataInicio = document.getElementById('filtro-data-inicio');
        const inputDataFim = document.getElementById('filtro-data-fim');

        if (selectTipoProcesso) {
            selectTipoProcesso.addEventListener('change', () => {
                paginaAtual = 1;
                renderizarTabela();
            });
        }

        if (selectStatus) {
            selectStatus.addEventListener('change', () => {
                paginaAtual = 1;
                renderizarTabela();
            });
        }

        if (inputDataInicio) {
            inputDataInicio.addEventListener('change', () => {
                paginaAtual = 1;
                renderizarTabela();
            });
        }
        
        if (inputDataFim) {
            inputDataFim.addEventListener('change', () => {
                paginaAtual = 1;
                renderizarTabela();
            });
        }

        if (inputBusca) {
            inputBusca.addEventListener('input', () => {
                paginaAtual = 1;
                renderizarTabela();
            });
        }
    }

    // Lógica do formulário
    if (formProcesso) {
        const inputCiencia = document.getElementById('data_ciencia');
        const inputEnvio = document.getElementById('data_envio_intimacao');
        const inputContagem = document.getElementById('tipo_contagem');
        const inputDias = document.getElementById('quantidade_dias');
        const inputFinal = document.getElementById('final_prazo');
        const inputAnalisador = document.getElementById('analisador');
        const inputPeticionador = document.getElementById('peticionador');

        window.calcularPrazoFinal = function() {
            if (!inputEnvio || !inputEnvio.value || !inputFinal || !inputFinal.value) return;

            let dataInicio = new Date(inputEnvio.value + 'T12:00:00');
            let dataFim = new Date(inputFinal.value + 'T12:00:00');
            const tipo = inputContagem.value;

            if (dataFim < dataInicio) {
                inputDias.value = 0;
                return;
            }

            if (tipo === 'CORRIDOS') {
                const diffTime = Math.abs(dataFim - dataInicio);
                const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
                inputDias.value = diffDays;
            } else if (tipo === 'ÚTEIS') {
                let diasUteis = 0;
                let dataAtual = new Date(dataInicio);
                
                while (dataAtual < dataFim) {
                    dataAtual.setDate(dataAtual.getDate() + 1);
                    const diaSemana = dataAtual.getDay();
                    if (diaSemana !== 0 && diaSemana !== 6) { // 0=Dom, 6=Sáb
                        diasUteis++;
                    }
                }
                inputDias.value = diasUteis;
            } else if (tipo === 'REDESIGNADA') {
                inputDias.value = 0; // Ou lógica se houver
            }
        };

        // Adiciona eventos aos campos para recálculo dinâmico
        [inputEnvio, inputContagem, inputFinal].forEach(input => {
            if(input) {
                input.addEventListener('change', window.calcularPrazoFinal);
                input.addEventListener('input', window.calcularPrazoFinal);
            }
        });

        window.irParaEtapa = (n) => {
            if (n === 1) window.etapaAnterior();
            else if (n === 2) window.proximaEtapa();
        };

        window.proximaEtapa = () => {
            const step1 = document.getElementById('step-1');
            const step2 = document.getElementById('step-2');
            const ind1 = document.getElementById('indicator-1');
            const ind2 = document.getElementById('indicator-2');
            
            if (step1 && step2) {
                step1.classList.remove('form-step-active');
                step2.classList.add('form-step-active');
                if (ind1) ind1.classList.remove('active');
                if (ind2) ind2.classList.add('active');
                window.calcularPrazoFinal();
            }
        };

        window.etapaAnterior = () => {
            const step1 = document.getElementById('step-1');
            const step2 = document.getElementById('step-2');
            const ind1 = document.getElementById('indicator-1');
            const ind2 = document.getElementById('indicator-2');

            step2.classList.remove('form-step-active');
            step1.classList.add('form-step-active');
            ind2.classList.remove('active');
            ind1.classList.add('active');
        };

        const checkFormValidity = () => {
            const requiredFields = formProcesso.querySelectorAll('[required]');
            const btnSalvar = document.getElementById('btn-salvar');
            let isAllFilled = true;
            
            requiredFields.forEach(field => {
                if (!field.value) isAllFilled = false;
            });
            
            if (btnSalvar) {
                btnSalvar.disabled = !isAllFilled;
            }
        };

        formProcesso.querySelectorAll('input, select').forEach(input => {
            input.addEventListener('input', checkFormValidity);
            input.addEventListener('change', checkFormValidity);
        });

        checkFormValidity();

        const selectTipoAto = document.getElementById('tipo_ato');
        const inputTipoAtoPers = document.getElementById('tipo_ato_personalizado');
        if (selectTipoAto && inputTipoAtoPers) {
            selectTipoAto.addEventListener('change', () => {
                inputTipoAtoPers.style.display = selectTipoAto.value === 'PERSONALIZADO' ? 'block' : 'none';
            });
        }

        const selectNatPrazo = document.getElementById('natureza_prazo');
        const inputNatPrazoPers = document.getElementById('natureza_prazo_personalizado');
        if (selectNatPrazo && inputNatPrazoPers) {
            selectNatPrazo.addEventListener('change', () => {
                inputNatPrazoPers.style.display = selectNatPrazo.value === 'PERSONALIZADO' ? 'block' : 'none';
            });
        }

        [inputCiencia, inputContagem, inputDias].forEach(el => {
            if (el) {
                el.addEventListener('change', window.calcularPrazoFinal);
                el.addEventListener('input', window.calcularPrazoFinal);
            }
        });

        formProcesso.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            let tipoAtoVal = document.getElementById('tipo_ato').value;
            if (tipoAtoVal === 'PERSONALIZADO' && document.getElementById('tipo_ato_personalizado')) {
                tipoAtoVal = document.getElementById('tipo_ato_personalizado').value.toUpperCase();
            }

            let natPrazoVal = document.getElementById('natureza_prazo').value;
            if (natPrazoVal === 'PERSONALIZADO' && document.getElementById('natureza_prazo_personalizado')) {
                natPrazoVal = document.getElementById('natureza_prazo_personalizado').value.toUpperCase();
            }
            
            const formData = {
                id: document.getElementById('processo-id').value,
                numero: document.getElementById('numero_processo').value,
                tipo_processo: document.getElementById('tipo_processo').value,
                tipo_ato: tipoAtoVal,
                natureza: natPrazoVal,
                revelia: document.getElementById('revelia').value,
                data_ciencia: inputCiencia.value,
                data_envio: document.getElementById('data_envio_intimacao').value,
                tipo_manifestacao: document.getElementById('tipo_manifestacao').value,
                data_protocolo: document.getElementById('data_protocolo').value,
                tipo_contagem: inputContagem.value,
                final_prazo: inputFinal.value || '',
                analisador: inputAnalisador ? inputAnalisador.value : '',
                peticionador: inputPeticionador ? inputPeticionador.value : '',
                protocolista: document.getElementById('protocolista') ? document.getElementById('protocolista').value : '',
                quantidade_dias: inputDias ? inputDias.value : 15,
                status: document.getElementById('status').value || 'PENDENTE',
                prazo_critico: document.getElementById('prazo_critico') ? document.getElementById('prazo_critico').value : 'NÃO',
                data_analise: document.getElementById('data_analise') ? document.getElementById('data_analise').value : '',
                data_peticionamento: document.getElementById('data_peticionamento') ? document.getElementById('data_peticionamento').value : '',
                observacoes: document.getElementById('observacoes') ? document.getElementById('observacoes').value : ''
            };

            const resp = await fetch('api.php?acao=salvar', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(formData)
            });

            if (resp.ok) {
                alert('Processo salvo com sucesso!');
                window.location.href = 'index.php';
            }
        });
    }

    async function carregarProcessos() {
        const resp = await fetch('api.php?acao=listar');
        let dados = await resp.json();
        
        // Ordenação padrão: por Data de Ciência decrescente (mais recentes primeiro)
        dados.sort((a, b) => {
            if (!a.data_ciencia) return 1;
            if (!b.data_ciencia) return -1;
            return new Date(b.data_ciencia) - new Date(a.data_ciencia);
        });

        dadosOriginais = dados;
        
        if (!listTable) return;
        
        // Inicializar abas de analisadores (com fallback em caso de erro nos dados)
        try {
            renderizarAbasAnalisadores();
        } catch(e) {
            console.error('Erro ao renderizar abas:', e);
        }
        
        // Atualizar Stats (com base em TODOS os dados)
        const totalProc = document.getElementById('total-processos');
        const totalPend = document.getElementById('total-pendentes');
        const totalProt = document.getElementById('total-protocolados');
        const totalHoje = document.getElementById('total-hoje');
 
        if (totalProc) totalProc.textContent = dadosOriginais.length;
        if (totalPend) totalPend.textContent = dadosOriginais.filter(p => ['PENDENTE', 'SENDO AVALIADO', 'EM ELABORAÇÃO'].includes(p.status)).length;
        if (totalProt) totalProt.textContent = dadosOriginais.filter(p => ['PROTOCOLADO', 'ANALISADO', 'PROCESSO FINALIZADO'].includes(p.status)).length;
        
        const hoje_str = new Date().toISOString().split('T')[0];
        if (totalHoje) totalHoje.textContent = dadosOriginais.filter(p => p.final_prazo === hoje_str).length;
 
        renderizarPrioridade();
        renderizarTabela();
    }

    window.renderDropdownActions = function(p) {
        const perfil = (window.userPerfil || 'ANALISADOR').toUpperCase().trim();
        let actionsHtml = `<button class="dropdown-item" onclick="window.visualizarProcesso('${encodeURIComponent(JSON.stringify(p))}')"><i class="fas fa-eye"></i> Visualizar</button>`;

        if (p.status === 'PENDENTE') {
            if (perfil === 'ACESSORES') {
                actionsHtml += `<button class="dropdown-item" onclick="window.marcarSendoAvaliado(${p.id})"><i class="fas fa-search"></i> Marcar como Avaliado</button>`;
                actionsHtml += `<button class="dropdown-item" onclick="window.marcarEmElaboracao(${p.id})"><i class="fas fa-pencil-alt"></i> Em Elaboração</button>`;
                actionsHtml += `<button class="dropdown-item" onclick="window.protocolarRapido(${p.id})"><i class="fas fa-check"></i> Protocolar</button>`;
            } else {
                // ANALISADOR ou ADMIN
                actionsHtml += `<button class="dropdown-item" onclick="window.marcarEmElaboracao(${p.id})"><i class="fas fa-pencil-alt"></i> Em Elaboração</button>`;
                actionsHtml += `<button class="dropdown-item" onclick="window.protocolarRapido(${p.id})"><i class="fas fa-check"></i> Protocolar</button>`;
                actionsHtml += `<button class="dropdown-item" onclick="window.marcarFinalizado(${p.id})"><i class="fas fa-flag-checkered"></i> Processo Finalizado</button>`;
            }
        } else if (p.status === 'PROTOCOLADO' || p.status === 'ANALISADO' || p.status === 'SENDO AVALIADO' || p.status === 'EM ELABORAÇÃO') {
            // Opções comuns para estados intermediários
            if (!p.peticionador) {
                actionsHtml += `<button class="dropdown-item" onclick="window.peticionarProcesso(${p.id})"><i class="fas fa-file-upload"></i> Peticionar</button>`;
            }
            // Analisador pode finalizar processos que já saíram do PENDENTE também? 
            // O usuário disse: "Os analisadores podem trocar de PENDENTES para..."
            // Mas geralmente podem finalizar a qualquer momento.
            if (perfil !== 'ACESSORES' && p.status !== 'PROCESSO FINALIZADO') {
                actionsHtml += `<button class="dropdown-item" onclick="window.marcarFinalizado(${p.id})"><i class="fas fa-flag-checkered"></i> Processo Finalizado</button>`;
            }
        }

        if (perfil !== 'ACESSORES') {
            actionsHtml += `<button class="dropdown-item" onclick="window.editarProcesso('${encodeURIComponent(JSON.stringify(p))}')"><i class="fas fa-edit"></i> Editar</button>`;
            actionsHtml += `<div style="border-top: 1px solid var(--border); margin: 4px 0;"></div>`;
            actionsHtml += `<button class="dropdown-item text-danger" onclick="window.excluirProcesso(${p.id})"><i class="fas fa-trash"></i> Excluir</button>`;
        }

        return actionsHtml;
    }

    function renderizarPrioridade() {
        const listPrioridade = document.getElementById('lista-prioridade');
        const sectionUrgente = document.getElementById('section-urgente');
        if (!listPrioridade || !sectionUrgente) return;

        const hoje = new Date();
        hoje.setHours(0,0,0,0);

        const criticos = dadosOriginais.filter(p => {
            if (p.status === 'PROTOCOLADO' || p.status === 'ANALISADO') return false;
            if (!p.final_prazo) return false; // Sem prazo não é prioridade urgente de prazo
            
            const pData = new Date(p.final_prazo + 'T12:00:00');
            const diffTime = pData - hoje;
            const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));

            // Vencidos (diffDays < 0) não entram aqui.
            return diffDays >= 0;
        })
        .sort((a, b) => new Date(a.final_prazo) - new Date(b.final_prazo))
        .slice(0, 5);

        if (criticos.length === 0) {
            sectionUrgente.style.display = 'none';
            return;
        }

        sectionUrgente.style.display = 'block';
        listPrioridade.innerHTML = '';

        criticos.forEach(p => {
            const dataPrazo = new Date(p.final_prazo);
            const diffTime = dataPrazo - hoje;
            const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
            
            let corPrazo = 'inherit';
            let labelPrazo = diffDays + ' dias';
            if (diffDays < 0) { corPrazo = '#ef4444'; labelPrazo = 'VENCIDO'; }
            else if (diffDays === 0) { corPrazo = '#f59e0b'; labelPrazo = 'HOJE'; }
            else if (diffDays <= 2) { corPrazo = '#f59e0b'; }

            const classAto = window.getColorForAto(p.tipo_ato);
            const classNat = window.getColorForNatureza(p.natureza);
            const classUser = window.getColorForUser(p.analisador);

            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td style="font-weight: 700;">${p.numero}</td>
                <td style="font-weight: 600; color: #475569;">${p.tipo_processo || 'CIÊNCIA'}</td>
                <td>
                    <div class="tag-badge ${classAto}" style="margin-bottom: 4px;">${p.tipo_ato}</div>
                    <div class="tag-badge ${classNat}" style="margin-top: 2px;">${p.natureza}</div>
                </td>
                <td style="font-weight: bold; color: ${corPrazo};">${formatarData(p.final_prazo)}</td>
                <td style="font-weight: 800; color: ${corPrazo}; font-size: 1rem;">${labelPrazo}</td>
                <td><span class="tag-badge ${classUser}">${p.analisador}</span></td>
                <td>
                    <div class="dropdown">
                        <button class="btn-dots" onclick="window.toggleDropdown(this)" title="Ações">
                            <i class="fas fa-ellipsis-v"></i>
                        </button>
                        <div class="dropdown-menu">
                            ${renderDropdownActions(p)}
                        </div>
                    </div>
                </td>
            `;
            listPrioridade.appendChild(tr);
        });
    }

    window.switchAnalisadorTab = function(analisador) {
        analisadorAtivo = analisador;
        paginaAtual = 1;
        
        document.querySelectorAll('#analisador-tabs .tab-btn').forEach(btn => btn.classList.remove('active'));
        const activeBtn = document.getElementById('tab-analisador-' + encodeURIComponent(analisador));
        if (activeBtn) activeBtn.classList.add('active');
        
        renderizarTabela();
    };

    function renderizarAbasAnalisadores() {
        const tabsContainer = document.getElementById('analisador-tabs');
        if (!tabsContainer) return;

        const analisadores = [...new Set(dadosOriginais.map(p => p.analisador || 'N/A'))]
            .filter(a => a !== 'N/A')
            .map(a => String(a).toUpperCase())
            .sort();
        
        // Determinar aba padrão caso ainda não tenha aba ativa
        if (!analisadorAtivo || analisadorAtivo === 'null') {
            analisadorAtivo = 'TODOS';
        }

        tabsContainer.innerHTML = '';
        
        // Aba Todos
        const btnTodos = document.createElement('button');
        btnTodos.className = 'tab-btn' + (analisadorAtivo === 'TODOS' ? ' active' : '');
        btnTodos.id = 'tab-analisador-TODOS';
        btnTodos.innerHTML = '<i class="fas fa-users"></i> Todos';
        btnTodos.onclick = () => window.switchAnalisadorTab('TODOS');
        tabsContainer.appendChild(btnTodos);

        // Abas Individuais
        analisadores.forEach(nome => {
            const btn = document.createElement('button');
            btn.className = 'tab-btn' + (analisadorAtivo === nome ? ' active' : '');
            btn.id = 'tab-analisador-' + encodeURIComponent(nome);
            btn.innerHTML = `<i class="fas fa-user-circle"></i> ${nome}`;
            btn.onclick = () => window.switchAnalisadorTab(nome);
            tabsContainer.appendChild(btn);
        });
    }

    function renderizarTabela() {
        if (!listTable) return;
        listTable.innerHTML = '';
        
        const inputBusca = document.getElementById('filtro-busca');
        const selectTipoProcesso = document.getElementById('filtro-tipo-processo');
        const selectStatus = document.getElementById('filtro-status');
        const inputDataInicio = document.getElementById('filtro-data-inicio');
        const inputDataFim = document.getElementById('filtro-data-fim');
        let filtrados = dadosOriginais;

        if (selectTipoProcesso && selectTipoProcesso.value) {
            filtrados = filtrados.filter(p => (p.tipo_processo || 'CIÊNCIA') === selectTipoProcesso.value);
        }

        if (selectStatus && selectStatus.value) {
            filtrados = filtrados.filter(p => p.status === selectStatus.value);
        }

        if (inputDataInicio && inputDataInicio.value) {
            filtrados = filtrados.filter(p => new Date(p.final_prazo) >= new Date(inputDataInicio.value));
        }

        if (inputDataFim && inputDataFim.value) {
            filtrados = filtrados.filter(p => new Date(p.final_prazo) <= new Date(inputDataFim.value));
        }

        if (inputBusca && inputBusca.value) {
            const query = inputBusca.value.toUpperCase();
            filtrados = filtrados.filter(p => 
                p.numero.includes(query) || 
                p.tipo_ato.toUpperCase().includes(query) ||
                p.natureza.toUpperCase().includes(query)
            );
        }

        if (analisadorAtivo && analisadorAtivo !== 'TODOS') {
            filtrados = filtrados.filter(p => String(p.analisador || 'N/A').toUpperCase() === analisadorAtivo);
        }

        // Paginação
        const totalPaginas = Math.ceil(filtrados.length / itensPorPagina);
        const inicio = (paginaAtual - 1) * itensPorPagina;
        const fim = inicio + itensPorPagina;
        const dadosPaginados = filtrados.slice(inicio, fim);

        if (dadosPaginados.length === 0) {
            listTable.innerHTML = '<tr><td colspan="6" style="text-align:center; padding: 2rem; color: var(--text-muted);">Nenhum processo encontrado.</td></tr>';
            renderizarPaginacao(0);
            return;
        }

        dadosPaginados.forEach(p => {
            const classAto = window.getColorForAto(p.tipo_ato);
            const classNat = window.getColorForNatureza(p.natureza);
            const classUser = window.getColorForUser(p.analisador);

            const statusLimpo = (p.status || 'PENDENTE').toUpperCase().trim();
            const statusClass = statusLimpo.toLowerCase();

            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td style="font-weight: 600;">${p.numero || '---'}</td>
                <td style="font-weight: 600; color: #475569;">${p.tipo_processo || 'CIÊNCIA'}</td>
                <td>
                    <div class="tag-badge ${classAto}" style="margin-bottom: 4px;">${p.tipo_ato || '---'}</div>
                    <br>
                    <div class="tag-badge ${classNat}" style="margin-top: 2px;">${p.natureza || '---'}</div>
                </td>
                <td style="color: ${p.prazo_critico === 'SIM' ? 'red' : 'inherit'}; font-weight: bold;">
                    ${formatarData(p.final_prazo)}
                    ${p.prazo_critico === 'SIM' ? ' <i class="fas fa-exclamation-triangle"></i>' : ''}
                </td>
                <td><span class="tag-badge ${classUser}">${p.analisador || 'N/A'}</span></td>
                <td>
                    <span class="badge badge-${statusClass}">${statusLimpo}</span>
                    ${statusLimpo === 'PROTOCOLADO' ? `
                        <div style="font-size: 0.75rem; margin-top: 5px; color: var(--text-muted); line-height: 1.2;">
                            ${(p.data_protocolo || p.protocolista || p.peticionador) ? `
                                <i class="fas fa-calendar-check" style="color: var(--status-protocolado);"></i> ${formatarData(p.data_protocolo)}<br>
                                <i class="fas fa-user-edit" style="color: var(--status-protocolado);"></i> ${p.protocolista || p.peticionador || 'N/A'}
                            ` : `<i class="fas fa-info-circle"></i> Sem registro de detalhes`}
                        </div>
                    ` : ''}
                </td>
                <td>
                    <div class="dropdown">
                        <button class="btn-dots" onclick="window.toggleDropdown(this)" title="Ações">
                            <i class="fas fa-ellipsis-v"></i>
                        </button>
                        <div class="dropdown-menu">
                            ${renderDropdownActions(p)}
                        </div>
                    </div>
                </td>
            `;
            listTable.appendChild(tr);
        });

        renderizarPaginacao(totalPaginas);
    }

    function renderizarPaginacao(totalPaginas) {
        const pagContainer = document.getElementById('paginacao-processos');
        if (!pagContainer) return;
        pagContainer.innerHTML = '';
        if (totalPaginas <= 1) return;

        const btnAnt = document.createElement('button');
        btnAnt.className = 'page-btn';
        btnAnt.innerHTML = '<i class="fas fa-chevron-left"></i>';
        btnAnt.disabled = paginaAtual === 1;
        btnAnt.onclick = () => { paginaAtual--; renderizarTabela(); };
        pagContainer.appendChild(btnAnt);

        let botoes = [];
        if (totalPaginas <= 7) {
            for (let i = 1; i <= totalPaginas; i++) botoes.push(i);
        } else {
            if (paginaAtual <= 4) botoes = [1, 2, 3, 4, 5, '...', totalPaginas];
            else if (paginaAtual >= totalPaginas - 3) botoes = [1, '...', totalPaginas - 4, totalPaginas - 3, totalPaginas - 2, totalPaginas - 1, totalPaginas];
            else botoes = [1, '...', paginaAtual - 1, paginaAtual, paginaAtual + 1, '...', totalPaginas];
        }

        botoes.forEach(item => {
            if (item === '...') {
                const span = document.createElement('span');
                span.textContent = '...';
                span.style.padding = '0 0.5rem';
                span.style.color = 'var(--text-muted)';
                span.style.fontWeight = 'bold';
                pagContainer.appendChild(span);
            } else {
                const btn = document.createElement('button');
                btn.className = `page-btn ${item === paginaAtual ? 'active' : ''}`;
                btn.textContent = item;
                btn.onclick = () => { paginaAtual = item; renderizarTabela(); };
                pagContainer.appendChild(btn);
            }
        });

        const btnProx = document.createElement('button');
        btnProx.className = 'page-btn';
        btnProx.innerHTML = '<i class="fas fa-chevron-right"></i>';
        btnProx.disabled = paginaAtual === totalPaginas;
        btnProx.onclick = () => { paginaAtual++; renderizarTabela(); };
        pagContainer.appendChild(btnProx);
    }

    window.protocolarRapido = async (id) => {
        const resp = await fetch('api.php?acao=listar');
        const dados = await resp.json();
        const p = dados.find(x => x.id == id);
        
        if (!p) return;
        
        p.status = 'PROTOCOLADO';
        p.data_protocolo = new Date().toISOString().split('T')[0];
        
        const elAnalisador = document.getElementById('nome-analisador');
        p.protocolista = elAnalisador ? elAnalisador.textContent.trim() : 'Usuário';
        
        const saveResp = await fetch('api.php?acao=salvar', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(p)
        });

        if (saveResp.ok) {
            window.location.reload();
        }
    };

    // Color Mappings
    window.getColorForAto = (ato) => {
        if (!ato) return 'bg-lightyellow';
        const a = ato.toUpperCase();
        if (a.includes('DECISÃO')) return 'bg-darkblue';
        if (a.includes('DESPACHO')) return 'bg-blue';
        if (a.includes('DECURSO DE PRAZO')) return 'bg-orange';
        if (a.includes('CERTIDÃO')) return 'bg-yellow';
        if (a.includes('ATO ORDINATÓRIO')) return 'bg-lightblue';
        if (a.includes('OFÍCIO')) return 'bg-darkblue';
        if (a.includes('SENTENÇA') || a.includes('SEQUESTRO')) return 'bg-red';
        if (a.includes('DILIGÊNCIA') || a.includes('MANIFESTAÇÃO DA PARTE')) return 'bg-pink';
        if (a.includes('CIÊNCIA')) return 'bg-lightgreen';
        return 'bg-indigo';
    };

    window.getColorForNatureza = (nat) => {
        if (!nat) return 'bg-lightyellow';
        const n = nat.toUpperCase();
        if (n.includes('MANIFESTAÇÃO')) return 'bg-lightyellow';
        if (n.includes('PAGAMENTO')) return 'bg-lightgreen';
        if (n.includes('RECURSO') || n.includes('IMPUGNAÇÃO')) return 'bg-lightblue';
        if (n.includes('REMETIDOS')) return 'bg-pink';
        if (n.includes('CUMPRIMENTO DA DECISÃO')) return 'bg-darkblue';
        if (n.includes('CONTESTAÇÃO')) return 'bg-magenta';
        if (n.includes('APELAÇÃO')) return 'bg-orange';
        if (n.includes('FINALIZADO')) return 'bg-green';
        if (n.includes('AUDIÊNCIA')) return 'bg-purple';
        if (n.includes('ANÁLISE')) return 'bg-pink';
        if (n.includes('CIÊNCIA')) return 'bg-lightgreen';
        return 'bg-indigo';
    };

    window.getColorForUser = (user) => {
        if (!user) return 'user-default';
        const u = user.toUpperCase();
        if (u.includes('ANANDA')) return 'user-ananda';
        if (u.includes('UANNA')) return 'user-uanna';
        if (u.includes('RHANY')) return 'user-rhanny';
        if (u.includes('RHANNY')) return 'user-rhanny';
        if (u.includes('MARINEZ')) return 'user-marinez';
        if (u.includes('HARRISON')) return 'user-harrison';
        return 'user-default';
    };

    window.formatarData = (dataStr) => {
        if (!dataStr) return '-';
        const parts = dataStr.split('-');
        if (parts.length !== 3) return dataStr;
        return `${parts[2]}/${parts[1]}/${parts[0]}`;
    };

    window.excluirProcesso = async (id) => {
        if (!confirm('Deseja realmente excluir este processo?')) return;
        await fetch(`api.php?acao=excluir&id=${id}`, { method: 'DELETE' });
        carregarProcessos();
    };

    window.editarProcesso = (pJson) => {
        const p = JSON.parse(decodeURIComponent(pJson));
        sessionStorage.setItem('edit_processo', JSON.stringify(p));
        window.location.href = 'cadastro.php';
    };

    // Verificar se há edição pendente
    if (formProcesso && sessionStorage.getItem('edit_processo')) {
        const p = JSON.parse(sessionStorage.getItem('edit_processo'));
        sessionStorage.removeItem('edit_processo');
        
        document.getElementById('processo-id').value = p.id;
        document.getElementById('numero_processo').value = p.numero;
        if (document.getElementById('tipo_processo')) document.getElementById('tipo_processo').value = p.tipo_processo || 'CIÊNCIA';
        const elAto = document.getElementById('tipo_ato');
        if (elAto) {
            let atoEncontrado = Array.from(elAto.options).some(opt => opt.value === p.tipo_ato);
            if (!atoEncontrado && p.tipo_ato) {
                elAto.value = 'PERSONALIZADO';
                const elAtoPers = document.getElementById('tipo_ato_personalizado');
                if (elAtoPers) {
                    elAtoPers.value = p.tipo_ato;
                    elAtoPers.style.display = 'block';
                }
            } else {
                elAto.value = p.tipo_ato;
            }
        }

        const elNat = document.getElementById('natureza_prazo');
        if (elNat) {
            let natEncontrada = Array.from(elNat.options).some(opt => opt.value === p.natureza);
            if (!natEncontrada && p.natureza) {
                elNat.value = 'PERSONALIZADO';
                const elNatPers = document.getElementById('natureza_prazo_personalizado');
                if (elNatPers) {
                    elNatPers.value = p.natureza;
                    elNatPers.style.display = 'block';
                }
            } else {
                elNat.value = p.natureza;
            }
        }
        document.getElementById('revelia').value = p.revelia;
        document.getElementById('data_ciencia').value = p.data_ciencia;
        document.getElementById('data_envio_intimacao').value = p.data_envio || '';
        document.getElementById('tipo_manifestacao').value = p.tipo_manifestacao || '';
        document.getElementById('data_protocolo').value = p.data_protocolo || '';
        document.getElementById('tipo_contagem').value = p.tipo_contagem;
        document.getElementById('quantidade_dias').value = p.quantidade_dias || 15;
        document.getElementById('final_prazo').value = p.final_prazo;
        // analisador e peticionador são preenchidos com os dados do processo durante a edição
        if (p.analisador) document.getElementById('analisador').value = p.analisador;
        
        if (document.getElementById('peticionador_visivel')) {
            document.getElementById('peticionador_visivel').value = p.peticionador || '';
            document.getElementById('peticionador').value = p.peticionador || '';
        } else if (p.peticionador) {
            const elPeti = document.getElementById('peticionador');
            if(elPeti) elPeti.value = p.peticionador;
        }

        if (document.getElementById('data_peticionamento_visivel')) {
            document.getElementById('data_peticionamento_visivel').value = p.data_peticionamento || '';
            document.getElementById('data_peticionamento').value = p.data_peticionamento || '';
        }
        
        if (document.getElementById('data_protocolo_visivel')) {
            document.getElementById('data_protocolo_visivel').value = p.data_protocolo || '';
            document.getElementById('data_protocolo').value = p.data_protocolo || '';
        }
        if (document.getElementById('protocolista_visivel')) {
            document.getElementById('protocolista_visivel').value = p.protocolista || '';
            document.getElementById('protocolista').value = p.protocolista || '';
        }
        if (document.getElementById('data_analise_visivel')) {
            document.getElementById('data_analise_visivel').value = p.data_analise || '';
            document.getElementById('data_analise').value = p.data_analise || '';
        }
        if (document.getElementById('analisador_visivel')) {
            document.getElementById('analisador_visivel').value = p.analisador || '';
            document.getElementById('analisador').value = p.analisador || '';
        }

        document.getElementById('status').value = p.status;
        document.getElementById('prazo_critico').value = p.prazo_critico;
        document.getElementById('observacoes').value = p.observacoes || '';

        if (p.status === 'PROTOCOLADO' || p.status === 'ANALISADO') {
            const cProt = document.getElementById('container-protocolo');
            if(cProt) cProt.style.display = 'grid';
        }
        if (p.status === 'ANALISADO') {
            const cAnalise = document.getElementById('container-analise');
            if(cAnalise) cAnalise.style.display = 'grid';
        }
        if (p.peticionador || p.data_peticionamento) {
            const cPeti = document.getElementById('container-peticionamento');
            if(cPeti) cPeti.style.display = 'grid';
        }
        
        document.querySelector('h1').textContent = 'Editar Processo';
        const btnSalvar = document.getElementById('btn-salvar');
        if (btnSalvar) btnSalvar.innerHTML = '<i class="fas fa-sync"></i> Atualizar Processo';
    }

    // Global Action Helpers
    window.toggleDropdown = function(btn) {
        // Fechar todos os clones abertos
        document.querySelectorAll('.dropdown-menu-fixed').forEach(m => m.remove());
        const isAlreadyActive = btn.classList.contains('active-btn');
        document.querySelectorAll('.btn-dots').forEach(b => b.classList.remove('active-btn'));
        
        if (isAlreadyActive) return;

        btn.classList.add('active-btn');
        
        const dropdown = btn.closest('.dropdown');
        const menu = dropdown.querySelector('.dropdown-menu');
        const clone = menu.cloneNode(true);
        
        // Estilizar como fixed na tela principal
        const rect = btn.getBoundingClientRect();
        clone.classList.add('dropdown-menu-fixed');
        clone.style.display = 'block';
        clone.style.position = 'fixed';
        clone.style.left = 'auto';
        clone.style.right = (window.innerWidth - rect.right) + 'px';
        clone.style.zIndex = '999999';
        
        // Se bater no fundo da tela, abrir pra cima
        if (rect.bottom + 200 > window.innerHeight) {
            clone.style.top = 'auto';
            clone.style.bottom = (window.innerHeight - rect.top + 5) + 'px';
        } else {
            clone.style.bottom = 'auto';
            clone.style.top = (rect.bottom + 5) + 'px';
        }

        document.body.appendChild(clone);
        if (window.event) window.event.stopPropagation();
    };

    document.addEventListener('click', function(e) {
        if (!e.target.closest('.dropdown-menu-fixed')) {
            document.querySelectorAll('.dropdown-menu-fixed').forEach(m => m.remove());
            document.querySelectorAll('.btn-dots').forEach(b => b.classList.remove('active-btn'));
        }
    });

    window.addEventListener('scroll', function(e) {
        document.querySelectorAll('.dropdown-menu-fixed').forEach(m => m.remove());
        document.querySelectorAll('.btn-dots').forEach(b => b.classList.remove('active-btn'));
    }, true);

    window.marcarAnalisado = async (id) => {
        const resp = await fetch('api.php?acao=listar');
        const dados = await resp.json();
        const p = dados.find(x => x.id == id);
        if (!p) return;
        
        p.status = 'ANALISADO';
        p.data_analise = new Date().toISOString().split('T')[0];
        
        const saveResp = await fetch('api.php?acao=salvar', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(p)
        });

        if (saveResp.ok) {
            window.location.reload();
        }
    };

    window.marcarSendoAvaliado = async (id) => {
        const resp = await fetch('api.php?acao=listar');
        const dados = await resp.json();
        const p = dados.find(x => x.id == id);
        if (!p) return;
        p.status = 'SENDO AVALIADO';
        const saveResp = await fetch('api.php?acao=salvar', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(p)
        });
        if (saveResp.ok) window.location.reload();
    };

    window.marcarEmElaboracao = async (id) => {
        const resp = await fetch('api.php?acao=listar');
        const dados = await resp.json();
        const p = dados.find(x => x.id == id);
        if (!p) return;
        p.status = 'EM ELABORAÇÃO';
        const saveResp = await fetch('api.php?acao=salvar', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(p)
        });
        if (saveResp.ok) window.location.reload();
    };

    window.marcarFinalizado = async (id) => {
        const resp = await fetch('api.php?acao=listar');
        const dados = await resp.json();
        const p = dados.find(x => x.id == id);
        if (!p) return;
        p.status = 'PROCESSO FINALIZADO';
        // Processo finalizado é similar ao protocolado/analisado na lógica de registro
        p.data_analise = new Date().toISOString().split('T')[0]; 
        const saveResp = await fetch('api.php?acao=salvar', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(p)
        });
        if (saveResp.ok) window.location.reload();
    };

    window.peticionarProcesso = async (id) => {
        const resp = await fetch('api.php?acao=listar');
        const dados = await resp.json();
        const p = dados.find(x => x.id == id);
        if (!p) return;
        
        const elAnalisador = document.getElementById('nome-analisador');
        p.peticionador = elAnalisador ? elAnalisador.textContent.trim() : 'Usuário';
        p.data_peticionamento = new Date().toISOString().split('T')[0];
        
        const saveResp = await fetch('api.php?acao=salvar', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(p)
        });

        if (saveResp.ok) {
            window.location.reload();
        }
    };

    window.visualizarProcesso = (pJson) => {
        // Fechar dropdown menus ao vizualizar
        document.querySelectorAll('.dropdown-menu-fixed').forEach(m => m.remove());
        document.querySelectorAll('.btn-dots').forEach(b => b.classList.remove('active-btn'));

        const p = JSON.parse(decodeURIComponent(pJson));
        const modal = document.getElementById('modal-detalhes');
        if (!modal) return;

        const body = document.getElementById('detalhes-conteudo');
        const statusOriginal = (p.status || 'PENDENTE').toUpperCase();
        const statusClass = statusOriginal.toLowerCase().normalize("NFD").replace(/[\u0300-\u036f]/g, "").replace(/\s+/g, '-');
        
        body.innerHTML = `
            <div class="modal-header-classic">
                <div style="display: flex; align-items: center; gap: 0.5rem; color: #1e293b;">
                    <i class="fas fa-file-invoice" style="font-size: 1.25rem;"></i>
                    <h2 style="margin: 0; font-size: 1rem; font-weight: 800; text-transform: uppercase;">DETALHES DO PROCESSO</h2>
                </div>
                <button class="btn-quick-close" onclick="window.fecharModalDetalhes()">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <div class="modal-body-split">
                <!-- Coluna Esquerda -->
                <div class="split-col">
                    <h3 class="split-title">INFORMAÇÕES GERAIS</h3>
                    
                    <div class="split-item-stacked">
                        <span class="split-label">Nº do Processo</span>
                        <span class="split-value-large">${p.numero}</span>
                    </div>

                    <div class="split-grid-2">
                        <div class="split-item-stacked">
                            <span class="split-label">Status Atual</span>
                            <div>
                                <span class="split-status-badge header-status-${statusClass}">
                                    ${statusOriginal}
                                </span>
                            </div>
                        </div>
                        <div class="split-item-stacked">
                            <span class="split-label">Tipo de Processo</span>
                            <span class="split-value">${p.tipo_processo || 'CIÊNCIA'}</span>
                        </div>
                    </div>

                    <div class="split-grid-2" style="border-bottom: 1px dashed #e2e8f0; padding-bottom: 1.25rem; margin-bottom: 1.25rem;">
                        <div class="split-item-stacked">
                            <span class="split-label">Ato Processual</span>
                            <div>
                                <span class="tag-badge ${window.getColorForAto(p.tipo_ato)}">${p.tipo_ato}</span>
                            </div>
                        </div>
                        <div class="split-item-stacked">
                            <span class="split-label">Natureza</span>
                            <div>
                                <span class="tag-badge ${window.getColorForNatureza(p.natureza)}">${p.natureza}</span>
                            </div>
                        </div>
                    </div>

                    <h3 class="split-title">REGISTRO E OBSERVAÇÕES</h3>
                    
                    ${p.peticionador ? `
                    <div class="split-item flex-between">
                        <span class="split-label">Peticionado por</span>
                        <span class="split-value">${p.peticionador}<br><span style="font-size:0.8rem;color:#64748b;font-weight:600;">em ${formatarData(p.data_peticionamento)}</span></span>
                    </div>` : ''}

                    ${p.status === 'PROTOCOLADO' || p.status === 'ANALISADO' ? `
                    <div class="split-item flex-between">
                        <span class="split-label">Protocolado por</span>
                        <span class="split-value">${p.protocolista || 'N/A'}<br><span style="font-size:0.8rem;color:#64748b;font-weight:600;">em ${formatarData(p.data_protocolo)}</span></span>
                    </div>` : ''}

                    <div class="split-item-stacked" style="border:none;">
                        <span class="split-label">Observações Adicionais</span>
                        <div class="split-obs">${p.observacoes || 'Nenhuma observação registrada.'}</div>
                    </div>
                </div>

                <!-- Coluna Direita -->
                <div class="split-col right-col">
                    <h3 class="split-title">CONTROLE DE PRAZOS</h3>
                    
                    <div class="split-item flex-between">
                        <span class="split-label">Ciência / Envio</span>
                        <span class="split-value">${formatarData(p.data_ciencia)} / ${formatarData(p.data_envio)}</span>
                    </div>
                    
                    <div class="split-item flex-between">
                        <span class="split-label">Prazo Final</span>
                        <span class="split-value" style="color: ${p.prazo_critico === 'SIM' ? '#ef4444' : '#ef4444'}; font-size: 1.15rem; font-weight: 800;">
                            ${formatarData(p.final_prazo)}
                            ${p.prazo_critico === 'SIM' ? ' <i class="fas fa-exclamation-circle" title="CRÍTICO"></i>' : ''}
                        </span>
                    </div>

                    <div class="split-item flex-between">
                        <span class="split-label">Tempo Restante</span>
                        <span class="split-value" style="color: ${p.prazo_critico === 'SIM' ? '#ef4444' : '#16a34a'}; font-weight: 700; font-size: 1.05rem;">
                            ${p.quantidade_dias} dias ${p.tipo_contagem}
                        </span>
                    </div>

                    <h3 class="split-title" style="margin-top: 2rem;">RESPONSÁVEIS E AÇÕES</h3>
                    
                    <div class="split-item flex-between">
                        <span class="split-label">Analisador Designado</span>
                        <span class="split-value" style="color: var(--primary); font-weight: 800;">${p.analisador || 'N/A'}</span>
                    </div>

                    <div class="split-item-stacked" style="border:none; margin-top: 0.5rem;">
                        <span class="split-label">Manifestação Exigida</span>
                        <span class="split-value" style="text-align: left; background: #f8fafc; padding: 10px; border-radius: 4px; border-left: 3px solid var(--primary);">${p.tipo_manifestacao || '---'}</span>
                    </div>

                    ${p.status === 'ANALISADO' ? `
                    <div class="split-item flex-between" style="border-top: 1px dashed #e2e8f0; padding-top: 1rem; margin-top: 1rem;">
                        <span class="split-label">Análise Concluída</span>
                        <span class="split-value">Em ${formatarData(p.data_analise)}</span>
                    </div>
                    ` : ''}
                </div>
            </div>
        `;

        modal.classList.add('active');
    };

    window.fecharModalDetalhes = () => {
        const modal = document.getElementById('modal-detalhes');
        if (modal) modal.classList.remove('active');
    };

    // Fechar ao clicar fora do modal
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('modal-overlay')) {
            window.fecharModalDetalhes();
        }
    });

});
