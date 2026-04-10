<?php
ob_start();
session_start();
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prazos Urgentes - SCP</title>
    <link rel="stylesheet" href="assets/css/estilo.css?v=45">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body>

<header class="navbar">
    <div class="logo">
        <i class="fas fa-balance-scale"></i>
        <span>SCP PGM</span>
    </div>
    <nav class="nav-links">
        <a href="index.php" class="nav-link"><i class="fas fa-home"></i> Dashboard</a>
        <?php if ($_SESSION['usuario_perfil'] !== 'ACESSORES'): ?>
        <a href="cadastro.php" class="nav-link"><i class="fas fa-plus-circle"></i> Novo</a>
        <?php endif; ?>
        <a href="prazos.php" class="nav-link active"><i class="fas fa-clock"></i> Prazos</a>
        <a href="tipos.php" class="nav-link"><i class="fas fa-layer-group"></i> Tipos</a>
        <a href="relatorios.php" class="nav-link"><i class="fas fa-chart-line"></i> Relatórios</a>
        <?php if ($_SESSION['usuario_perfil'] === 'ADMIN'): ?>
        <a href="usuarios.php" class="nav-link"><i class="fas fa-users"></i> Usuários</a>
        <a href="configuracoes.php" class="nav-link"><i class="fas fa-cog"></i></a>
        <?php endif; ?>
    </nav>
    <div style="display: flex; align-items: center; gap: 1rem;">
        <div id="nome-analisador" style="font-weight: 600; font-size: 0.9rem; color: var(--text-main);">
            <i class="fas fa-user-circle" style="color: var(--primary); margin-right: 5px;"></i>
            <?php echo $_SESSION['usuario_nome']; ?>
        </div>
        <a href="api.php?acao=logout" class="btn-quick" style="color: #f87171; border:none;" title="Sair">
            <i class="fas fa-sign-out-alt"></i>
        </a>
    </div>
</header>

<main class="main-content">
    <header class="header" style="flex-direction: column; align-items: flex-start; gap: 1.5rem;">
        <div style="display: flex; justify-content: space-between; align-items: center; width: 100%;">
            <div class="title-group">
                <h1 id="prazos-titulo">Prazos Urgentes</h1>
                <p id="prazos-descricao">Processos com prazo vencido ou a vencer em até 72 horas.</p>
            </div>
            <div class="user-profile" style="display: flex; align-items: center; gap: 1rem; background: var(--glass-bg); padding: 0.5rem 1rem; border-radius: 50px; border: 1px solid var(--glass-border);">
                <i class="fas fa-user-circle" style="font-size: 1.5rem; color: var(--primary);"></i>
                <span style="font-weight: 600;"><?php echo $_SESSION['usuario_nome']; ?></span>
            </div>
        </div>

        <div class="tab-container" style="display: flex; gap: 1rem; background: var(--glass-bg); padding: 0.4rem; border-radius: 12px; border: 1px solid var(--glass-border);">
            <button class="tab-btn active" onclick="switchTab('urgentes')" id="tab-urgentes">
                <i class="fas fa-exclamation-triangle"></i> Urgentes
            </button>
            <button class="tab-btn" onclick="switchTab('vencidos')" id="tab-vencidos">
                <i class="fas fa-calendar-times"></i> Vencidos
            </button>
            <button class="tab-btn" onclick="switchTab('mensal')" id="tab-mensal">
                <i class="fas fa-calendar-alt"></i> Cronograma Mensal
            </button>
        </div>
    </header>

    <style>
        .tab-btn {
            padding: 0.6rem 1.2rem;
            border-radius: 8px;
            border: none;
            background: transparent;
            color: var(--text-muted);
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .tab-btn:hover {
            background: rgba(37, 99, 235, 0.05);
            color: var(--primary);
        }
        .tab-btn.active {
            background: var(--primary);
            color: white;
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.2);
        }
        .filter-group-mensal {
            display: none;
            gap: 1rem;
            align-items: center;
        }
        .next-month-badge {
            background: #dbeafe;
            color: #1e40af;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 0.7rem;
            font-weight: 800;
            margin-left: 5px;
            border: 1px solid #bfdbfe;
        }

        /* Grid de Meses */
        .month-grid {
            display: none;
            grid-template-columns: repeat(auto-fill, minmax(130px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
            animation: fadeIn 0.3s ease-in-out;
        }
        .month-card {
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
            padding: 1rem;
            border-radius: 12px;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            position: relative;
            overflow: hidden;
        }
        .month-card:hover {
            transform: translateY(-3px);
            background: white;
            box-shadow: var(--shadow);
            border-color: var(--primary);
        }
        .month-card.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
        }
        .month-card .month-name {
            font-weight: 700;
            font-size: 0.85rem;
            text-transform: uppercase;
        }
        .month-card .process-count {
            font-size: 1.25rem;
            font-weight: 800;
        }
        .month-card .process-label {
            font-size: 0.7rem;
            font-weight: 600;
            opacity: 0.8;
        }
        .month-card.active .process-label {
            opacity: 1;
        }
    </style>

    <section class="data-section">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
            <h2 id="section-title" style="font-size: 1.25rem;">Urgentes</h2>
            
            <div style="display: flex; gap: 1rem; align-items: center; flex-wrap: wrap;">
                <select id="filtro-tipo-prazos" style="padding: 0.5rem 1rem; border-radius: 50px; border: 1px solid var(--border); background: white; font-weight: 600; color: var(--text-main); font-size: 0.85rem; outline: none; height: 38px;">
                    <option value="">Tipos (Todos)</option>
                    <option value="CIÊNCIA">Ciência</option>
                    <option value="CUMPRIMENTO">Cumprimento</option>
                    <option value="RECURSO - CIÊNCIA">Recurso - Ciência</option>
                    <option value="RECURSO - CUMPRIMENTO">Recurso - Cumprimento</option>
                </select>
                <div style="position: relative;">
                    <i class="fas fa-search" style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: var(--text-muted);"></i>
                    <input type="text" id="filtro-urgentes" placeholder="Pesquisar..." style="width: 250px; padding: 0.5rem 1rem 0.5rem 36px; height: 38px; border-radius: 50px; border: 1px solid var(--border); outline: none;">
                </div>
            </div>
        </div>

        <!-- Grid de Meses -->
        <div id="month-grid" class="month-grid">
            <!-- JS preenche -->
        </div>

        <div style="overflow-x: auto;">
            <table id="tabela-prazos">
                <thead>
                    <tr>
                        <th>Nº PROCESSO</th>
                        <th>TIPO</th>
                        <th>ATO / NATUREZA</th>
                        <th>PRAZO FINAL</th>
                        <th>DIAS RESTANTES</th>
                        <th>ANALISADOR</th>
                        <th>STATUS</th>
                        <th>AÇÕES</th>
                    </tr>
                </thead>
                <tbody id="lista-prazos">
                    <!-- Preenchido via JS -->
                </tbody>
            </table>
        </div>
        <div id="paginacao-prazos" class="pagination" style="margin-top: 1.5rem;"></div>
    </section>
</main>

<script src="assets/js/script.js?v=50"></script>
<script>
    document.addEventListener('DOMContentLoaded', async () => {
        const listPrazos = document.getElementById('lista-prazos');
        const inputBusca = document.getElementById('filtro-urgentes');
        
        let dadosOriginais = [];
        let dadosFiltrados = [];
        let activeTab = 'urgentes';
        let paginaAtual = 1;
        const itensPorPagina = 10;

        // Estado do Cronograma Mensal
        const hoje_global = new Date();
        let selectedMonth = hoje_global.getMonth();
        let selectedYear = hoje_global.getFullYear();


        window.switchTab = (tab) => {
            activeTab = tab;
            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            document.getElementById('tab-' + tab).classList.add('active');
            
            const gridMeses = document.getElementById('month-grid');
            const titulo = document.getElementById('prazos-titulo');
            const desc = document.getElementById('prazos-descricao');
            const sectionTitle = document.getElementById('section-title');

            if (tab === 'urgentes') {
                gridMeses.style.display = 'none';
                titulo.textContent = 'Prazos Urgentes';
                desc.textContent = 'Processos que vencem em até 7 dias.';
                sectionTitle.textContent = 'Urgentes';
            } else if (tab === 'vencidos') {
                gridMeses.style.display = 'none';
                titulo.textContent = 'Prazos Vencidos';
                desc.textContent = 'Processos cujas datas finais já expiraram e não foram protocolados.';
                sectionTitle.textContent = 'Atenção: Vencidos';
            } else {
                gridMeses.style.display = 'grid';
                titulo.textContent = 'Cronograma Mensal';
                desc.textContent = 'Visualize os prazos previstos para o mês selecionado (não vencidos).';
                sectionTitle.textContent = 'Prazos Disponíveis no Mês';
            }
            
            paginaAtual = 1;
            processarDados();
        };

        async function carregarPrazos() {
            const resp = await fetch('api.php?acao=listar');
            dadosOriginais = await resp.json();
            processarDados();
        }

        function processarDados() {
            const agora = new Date();
            const hojeStr = agora.getFullYear() + '-' + String(agora.getMonth() + 1).padStart(2, '0') + '-' + String(agora.getDate()).padStart(2, '0');

            if (activeTab === 'urgentes') {
                dadosFiltrados = dadosOriginais.filter(p => {
                    if (p.status === 'PROTOCOLADO' || p.status === 'ANALISADO') return false;
                    if (!p.final_prazo) return false;
                    
                    const pData = new Date(p.final_prazo + 'T12:00:00'); 
                    const diffTime = pData - agora;
                    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
                    
                    return p.final_prazo >= hojeStr && diffDays <= 7;
                });
                dadosFiltrados.sort((a, b) => new Date(a.final_prazo) - new Date(b.final_prazo));
            } else if (activeTab === 'vencidos') {
                const criticos = dadosOriginais.filter(p => {
                    if (p.status === 'PROTOCOLADO' || p.status === 'ANALISADO') return false;
                    if (!p.final_prazo) return true;
                    
                    // Comparação de string YYYY-MM-DD é segura para fuso horário
                    return p.final_prazo < hojeStr;
                });
                dadosFiltrados = criticos;
                dadosFiltrados.sort((a, b) => new Date(b.final_prazo) - new Date(a.final_prazo));
            } else {
                dadosFiltrados = dadosOriginais.filter(p => {
                    if (!p.final_prazo) return false;
                    const d = new Date(p.final_prazo + 'T12:00:00');
                    const matchMonth = d.getMonth() === selectedMonth && d.getFullYear() === selectedYear;
                    const naoVencido = p.final_prazo >= hojeStr;
                    return matchMonth && naoVencido;
                });
                dadosFiltrados.sort((a, b) => new Date(a.final_prazo) - new Date(b.final_prazo));
                renderizarGridMeses();
            }

            renderizarTabela();
        }

        function renderizarGridMeses() {
            const grid = document.getElementById('month-grid');
            if (!grid) return;
            grid.innerHTML = '';

            const meses = [
                'Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho',
                'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'
            ];
            
            const selectTipo = document.getElementById('filtro-tipo-prazos');
            const tipoSel = selectTipo ? selectTipo.value : '';
            const agora = new Date();
            const hojeStr = agora.getFullYear() + '-' + String(agora.getMonth() + 1).padStart(2, '0') + '-' + String(agora.getDate()).padStart(2, '0');

            meses.forEach((nome, index) => {
                const count = dadosOriginais.filter(p => {
                    if (!p.final_prazo) return false;
                    const d = new Date(p.final_prazo + 'T12:00:00');
                    
                    const matchData = d.getMonth() === index && d.getFullYear() === selectedYear;
                    const matchTipo = !tipoSel || (p.tipo_processo || 'CIÊNCIA') === tipoSel;
                    const naoVencido = p.final_prazo >= hojeStr;
                    
                    return matchData && matchTipo && naoVencido;
                }).length;

                const card = document.createElement('div');
                card.className = `month-card ${index === selectedMonth ? 'active' : ''}`;
                card.onclick = () => {
                    selectedMonth = index;
                    paginaAtual = 1;
                    processarDados();
                };

                card.innerHTML = `
                    <div class="month-name">${nome}</div>
                    <div class="process-count">${count}</div>
                    <div class="process-label">${count === 1 ? 'Processo' : 'Processos'}</div>
                `;
                grid.appendChild(card);
            });
        }

        function renderizarTabela() {
            if (!listPrazos) return;
            listPrazos.innerHTML = '';

            const selectTipo = document.getElementById('filtro-tipo-prazos');
            let filtrados = dadosFiltrados;

            if (selectTipo && selectTipo.value) {
                filtrados = filtrados.filter(p => (p.tipo_processo || 'CIÊNCIA') === selectTipo.value);
            }

            if (inputBusca && inputBusca.value) {
                const query = inputBusca.value.toUpperCase();
                filtrados = filtrados.filter(p => 
                    p.numero.includes(query) || 
                    p.tipo_ato.toUpperCase().includes(query) || 
                    p.natureza.toUpperCase().includes(query)
                );
            }

            const totalPaginas = Math.ceil(filtrados.length / itensPorPagina);
            const inicio = (paginaAtual - 1) * itensPorPagina;
            const fim = inicio + itensPorPagina;
            const paginados = filtrados.slice(inicio, fim);

            if (paginados.length === 0) {
                listPrazos.innerHTML = '<tr><td colspan="8" style="text-align:center; padding: 2rem;">Nenhum prazo encontrado nesta categoria.</td></tr>';
                renderizarPaginacao(0);
                return;
            }

            paginados.forEach(p => {
                const hoje = new Date();
                hoje.setHours(0,0,0,0);
                const dataPrazo = new Date(p.final_prazo);
                const diffTime = dataPrazo - hoje;
                const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
                
                let corPrazo = 'inherit';
                let labelPrazo = diffDays + ' dias';
                let extraBadge = '';
                
                if (diffDays < 0) {
                    corPrazo = '#ef4444';
                    labelPrazo = 'VENCIDO (' + Math.abs(diffDays) + 'd)';
                } else if (diffDays === 0) {
                    corPrazo = '#f59e0b';
                    labelPrazo = 'HOJE';
                } else if (diffDays <= 2) {
                    corPrazo = '#f59e0b';
                }

                // Destaque Próximo Mês
                const mesAtual = hoje.getMonth();
                const anoAtual = hoje.getFullYear();
                const mesPrazo = dataPrazo.getMonth();
                const anoPrazo = dataPrazo.getFullYear();

                let proximoMes = mesAtual + 1;
                let anoProximo = anoAtual;
                if (proximoMes > 11) { proximoMes = 0; anoProximo++; }

                if (mesPrazo === proximoMes && anoPrazo === anoProximo) {
                    extraBadge = `<span class="next-month-badge">Próximo Mês</span>`;
                }

                const classAto = window.getColorForAto(p.tipo_ato);
                const classNat = window.getColorForNatureza(p.natureza);
                const classUser = window.getColorForUser(p.analisador);

                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td style="font-weight: 600;">${p.numero}</td>
                    <td style="font-weight: 600; color: var(--primary);">${p.tipo_processo || 'CIÊNCIA'}</td>
                    <td>
                        <div class="tag-badge ${classAto}" style="margin-bottom: 4px;">${p.tipo_ato}</div>
                        <div class="tag-badge ${classNat}" style="margin-top: 2px;">${p.natureza}</div>
                    </td>
                    <td style="font-weight: bold; color: ${corPrazo};">
                        ${formatarData(p.final_prazo)}
                        ${extraBadge}
                    </td>
                    <td style="font-weight: 700; color: ${corPrazo};">${labelPrazo}</td>
                    <td><span class="tag-badge ${classUser}">${p.analisador}</span></td>
                    <td>
                        <span class="badge badge-${p.status.toLowerCase().trim()}">${p.status.trim()}</span>
                        ${p.status.toUpperCase().trim() === 'PROTOCOLADO' ? `
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
                                <button class="dropdown-item" onclick="window.visualizarProcesso('${encodeURIComponent(JSON.stringify(p))}')"><i class="fas fa-eye"></i> Visualizar</button>
                                ${p.status === 'PENDENTE' ? `
                                    <button class="dropdown-item" onclick="window.protocolarRapido(${p.id})"><i class="fas fa-check"></i> Protocolar</button>
                                ` : ''}
                                ${(p.status === 'PENDENTE' || p.status === 'PROTOCOLADO') ? `
                                    <button class="dropdown-item" onclick="window.marcarAnalisado(${p.id})"><i class="fas fa-eye"></i> Marcar Analisado</button>
                                ` : ''}
                                ${(!p.peticionador && (p.status === 'PENDENTE' || p.status === 'PROTOCOLADO')) ? `
                                    <button class="dropdown-item" onclick="window.peticionarProcesso(${p.id})"><i class="fas fa-file-upload"></i> Peticionar</button>
                                ` : ''}
                                <button class="dropdown-item" onclick="window.editarProcesso('${encodeURIComponent(JSON.stringify(p))}')"><i class="fas fa-edit"></i> Editar</button>
                                <div style="border-top: 1px solid var(--border); margin: 4px 0;"></div>
                                <button class="dropdown-item text-danger" onclick="window.excluirProcesso(${p.id})"><i class="fas fa-trash"></i> Excluir</button>
                            </div>
                        </div>
                    </td>
                `;
                listPrazos.appendChild(tr);
            });

            renderizarPaginacao(totalPaginas);
        }

        function renderizarPaginacao(totalPaginas) {
            const pagContainer = document.getElementById('paginacao-prazos');
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

        if (inputBusca) {
            inputBusca.addEventListener('input', () => {
                paginaAtual = 1;
                renderizarTabela();
            });
        }

        const selectTipoDropdown = document.getElementById('filtro-tipo-prazos');
        if (selectTipoDropdown) {
            selectTipoDropdown.addEventListener('change', () => {
                paginaAtual = 1;
                processarDados();
            });
        }

        carregarPrazos();
    });
</script>
    <!-- Modal de Detalhes -->
    <div id="modal-detalhes" class="modal-overlay">
        <div class="modal-content">
            <div id="detalhes-conteudo">
                <!-- Preenchido via JS -->
            </div>
        </div>
    </div>
</body>
</html>
