document.addEventListener('DOMContentLoaded', () => {
    const listTable = document.getElementById('lista-processos');
    const formProcesso = document.getElementById('form-processo');
    const nomeAnalisadorExibicao = document.getElementById('nome-analisador');
    const toggleMeusPrazos = document.getElementById('filtro-meus-prazos');
    let filtroUsuarioAtivo = false;
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

    if (toggleMeusPrazos) {
        toggleMeusPrazos.addEventListener('click', () => {
            filtroUsuarioAtivo = !filtroUsuarioAtivo;
            toggleMeusPrazos.classList.toggle('active');
            carregarProcessos();
        });
    }
    
    // Carregar dados se estiver na index
    if (listTable) {
        carregarProcessos();
        setInterval(carregarProcessos, 30000); // Atualiza a cada 30s

        const inputBusca = document.getElementById('filtro-busca');
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
        const inputContagem = document.getElementById('tipo_contagem');
        const inputDias = document.getElementById('quantidade_dias');
        const inputFinal = document.getElementById('final_prazo');
        const inputAnalisador = document.getElementById('analisador');
        const inputPeticionador = document.getElementById('peticionador');

        window.calcularPrazoFinal = function() {
            if (!inputCiencia.value || !inputDias.value) return;

            let data = new Date(inputCiencia.value);
            const dias = parseInt(inputDias.value);
            const tipo = inputContagem.value;

            if (tipo === 'CORRIDOS') {
                data.setDate(data.getDate() + dias);
            } else if (tipo === 'ÚTEIS') {
                let cont = 0;
                while (cont < dias) {
                    data.setDate(data.getDate() + 1);
                    const diaSemana = data.getDay();
                    if (diaSemana !== 0 && diaSemana !== 6) { // 0=Dom, 6=Sáb
                        cont++;
                    }
                }
            } else if (tipo === 'REDESIGNADA') {
                // Mantém a data original ou lógica específica se houver
            }
            
            inputFinal.value = data.toISOString().split('T')[0];
        }

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

        [inputCiencia, inputContagem, inputDias].forEach(el => {
            if (el) {
                el.addEventListener('change', window.calcularPrazoFinal);
                el.addEventListener('input', window.calcularPrazoFinal);
            }
        });

        formProcesso.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const formData = {
                id: document.getElementById('processo-id').value,
                numero: document.getElementById('numero_processo').value,
                tipo_ato: document.getElementById('tipo_ato').value,
                natureza: document.getElementById('natureza_prazo').value,
                revelia: document.getElementById('revelia').value,
                data_ciencia: inputCiencia.value,
                data_envio: document.getElementById('data_envio_intimacao').value,
                tipo_manifestacao: document.getElementById('tipo_manifestacao').value,
                data_protocolo: document.getElementById('data_protocolo').value,
                tipo_contagem: inputContagem.value,
                final_prazo: inputFinal.value,
                analisador: inputAnalisador.value,
                peticionador: inputPeticionador.value,
                quantidade_dias: inputDias.value,
                status: document.getElementById('status').value,
                prazo_critico: document.getElementById('prazo_critico').value
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
        dadosOriginais = await resp.json();
        
        if (!listTable) return;
        
        // Atualizar Stats (com base em TODOS os dados)
        const totalProc = document.getElementById('total-processos');
        const totalPend = document.getElementById('total-pendentes');
        const totalProt = document.getElementById('total-protocolados');
        const totalHoje = document.getElementById('total-hoje');
 
        if (totalProc) totalProc.textContent = dadosOriginais.length;
        if (totalPend) totalPend.textContent = dadosOriginais.filter(p => p.status === 'PENDENTE').length;
        if (totalProt) totalProt.textContent = dadosOriginais.filter(p => p.status === 'PROTOCOLADO').length;
        
        const hoje_str = new Date().toISOString().split('T')[0];
        if (totalHoje) totalHoje.textContent = dadosOriginais.filter(p => p.final_prazo === hoje_str).length;
 
        renderizarPrioridade();
        renderizarTabela();
    }

    function renderizarPrioridade() {
        const listPrioridade = document.getElementById('lista-prioridade');
        const sectionUrgente = document.getElementById('section-urgente');
        if (!listPrioridade || !sectionUrgente) return;

        const hoje = new Date();
        hoje.setHours(0,0,0,0);

        const criticos = dadosOriginais.filter(p => {
            if (p.status === 'PROTOCOLADO' || p.status === 'ANALISADO') return false;
            return true; // Mostra qualquer um que não esteja pronto
        });

        if (criticos.length === 0) {
            sectionUrgente.style.display = 'none';
            return;
        }

        sectionUrgente.style.display = 'block';
        listPrioridade.innerHTML = '';

        criticos.sort((a, b) => {
            if (!a.final_prazo) return 1;
            if (!b.final_prazo) return -1;
            return new Date(a.final_prazo) - new Date(b.final_prazo);
        });
        const top5 = criticos.slice(0, 5);

        top5.forEach(p => {
            const dataPrazo = new Date(p.final_prazo);
            const diffTime = dataPrazo - hoje;
            const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
            
            let corPrazo = 'inherit';
            let labelPrazo = diffDays + ' dias';
            if (diffDays < 0) { corPrazo = '#ef4444'; labelPrazo = 'VENCIDO'; }
            else if (diffDays === 0) { corPrazo = '#f59e0b'; labelPrazo = 'HOJE'; }
            else if (diffDays <= 2) { corPrazo = '#f59e0b'; }

            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td style="font-weight: 700;">${p.numero}</td>
                <td>
                    <div style="font-weight: 700; font-size: 0.85rem;">${p.tipo_ato}</div>
                    <div style="font-size: 0.75rem; color: var(--text-muted);">${p.natureza}</div>
                </td>
                <td style="font-weight: bold; color: ${corPrazo};">${formatarData(p.final_prazo)}</td>
                <td style="font-weight: 800; color: ${corPrazo}; font-size: 1rem;">${labelPrazo}</td>
                <td style="font-weight: 600;">${p.analisador}</td>
                <td>
                    <div style="display: flex; gap: 8px;">
                        <button class="btn-quick protocolar" onclick="protocolarRapido(${p.id})" title="Protocolar Agora">
                            <i class="fas fa-check"></i>
                        </button>
                        <button class="btn-quick" onclick="editarProcesso('${encodeURIComponent(JSON.stringify(p))}')" title="Editar" style="color: var(--primary);">
                            <i class="fas fa-edit"></i>
                        </button>
                    </div>
                </td>
            `;
            listPrioridade.appendChild(tr);
        });
    }

    function renderizarTabela() {
        if (!listTable) return;
        listTable.innerHTML = '';
        
        const inputBusca = document.getElementById('filtro-busca');
        let dadosFiltrados = dadosOriginais;

        if (inputBusca && inputBusca.value) {
            const query = inputBusca.value.toUpperCase();
            dadosFiltrados = dadosFiltrados.filter(p => 
                p.numero.includes(query) || 
                p.tipo_ato.toUpperCase().includes(query) ||
                p.natureza.toUpperCase().includes(query)
            );
        }

        if (filtroUsuarioAtivo) {
            const meuNome = (nomeAnalisadorExibicao.textContent || '').trim().toLowerCase();
            dadosFiltrados = dadosFiltrados.filter(p => (p.analisador || '').trim().toLowerCase() === meuNome);
        }

        // Paginação
        const totalPaginas = Math.ceil(dadosFiltrados.length / itensPorPagina);
        const inicio = (paginaAtual - 1) * itensPorPagina;
        const fim = inicio + itensPorPagina;
        const dadosPaginados = dadosFiltrados.slice(inicio, fim);

        if (dadosPaginados.length === 0) {
            listTable.innerHTML = '<tr><td colspan="6" style="text-align:center; padding: 2rem; color: var(--text-muted);">Nenhum processo encontrado.</td></tr>';
            renderizarPaginacao(0);
            return;
        }

        dadosPaginados.forEach(p => {
            const classAto = p.tipo_ato.includes('DECISÃO') ? 'tag-decisao' : 
                             p.tipo_ato.includes('DESPACHO') ? 'tag-despacho' : 
                             p.tipo_ato.includes('CIÊNCIA') ? 'tag-ciencia' : 
                             p.tipo_ato.includes('SENTENÇA') ? 'tag-sentenca' : 
                             (p.tipo_ato.includes('JUNTADA') || p.tipo_ato.includes('EXPEDIÇÃO')) ? 'tag-juntada' : '';

            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td style="font-weight: 600;">${p.numero}</td>
                <td>
                    <div class="tag-ato ${classAto}" style="margin-bottom: 4px;">${p.tipo_ato}</div>
                    <div style="font-size: 0.75rem; color: var(--text-muted); font-weight: 600;">${p.natureza}</div>
                </td>
                <td style="color: ${p.prazo_critico === 'SIM' ? 'red' : 'inherit'}; font-weight: bold;">
                    ${formatarData(p.final_prazo)}
                    ${p.prazo_critico === 'SIM' ? ' <i class="fas fa-exclamation-triangle"></i>' : ''}
                </td>
                <td style="font-size: 0.85rem; font-weight:600;">${p.analisador}</td>
                <td><span class="badge badge-${p.status.toLowerCase()}">${p.status}</span></td>
                <td>
                    <div style="display: flex; gap: 8px;">
                        ${p.status === 'PENDENTE' ? `
                            <button class="btn-quick protocolar" onclick="protocolarRapido(${p.id})" title="Protocolar Agora">
                                <i class="fas fa-check"></i>
                            </button>
                        ` : ''}
                        <button class="btn-quick" onclick="editarProcesso('${encodeURIComponent(JSON.stringify(p))}')" title="Editar" style="color: var(--primary);">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn-quick" onclick="excluirProcesso('${p.id}')" title="Excluir" style="color: #ef4444;">
                            <i class="fas fa-trash"></i>
                        </button>
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
        btnAnt.innerHTML = '<i class="fas fa-chevron-left"></i>';
        btnAnt.disabled = paginaAtual === 1;
        btnAnt.onclick = () => { paginaAtual--; renderizarTabela(); };
        pagContainer.appendChild(btnAnt);

        for (let i = 1; i <= totalPaginas; i++) {
            const btn = document.createElement('button');
            btn.textContent = i;
            if (i === paginaAtual) btn.classList.add('active');
            btn.onclick = () => { paginaAtual = i; renderizarTabela(); };
            pagContainer.appendChild(btn);
        }

        const btnProx = document.createElement('button');
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
        
        const saveResp = await fetch('api.php?acao=salvar', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(p)
        });

        if (saveResp.ok) {
            carregarProcessos();
        }
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
        document.getElementById('tipo_ato').value = p.tipo_ato;
        document.getElementById('natureza_prazo').value = p.natureza;
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
        if (p.peticionador) document.getElementById('peticionador').value = p.peticionador;
        document.getElementById('status').value = p.status;
        document.getElementById('prazo_critico').value = p.prazo_critico;
        
        document.querySelector('h1').textContent = 'Editar Processo';
        const btnSalvar = document.getElementById('btn-salvar');
        if (btnSalvar) btnSalvar.innerHTML = '<i class="fas fa-sync"></i> Atualizar Processo';
    }
});
