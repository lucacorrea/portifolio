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
    <title>Tipos de Processos - SCP</title>
    <link rel="stylesheet" href="assets/css/estilo.css?v=5">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        .tabs {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
            border-bottom: 1px solid var(--border);
            padding-bottom: 1rem;
        }
        .tab-content {
            display: none;
        }
        .tab-content.active {
            display: block;
        }
    </style>
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
        <a href="prazos.php" class="nav-link"><i class="fas fa-clock"></i> Prazos</a>
        <a href="tipos.php" class="nav-link active"><i class="fas fa-layer-group"></i> Tipos</a>
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
    <header class="header">
        <div class="title-group">
            <h1>Tipos de Processos</h1>
            <p>Gerenciamento e organização visual dividida por categorias.</p>
        </div>
        <div class="user-profile" style="display: flex; align-items: center; gap: 1rem; background: var(--glass-bg); padding: 0.5rem 1rem; border-radius: 50px; border: 1px solid var(--glass-border);">
            <i class="fas fa-user-circle" style="font-size: 1.5rem; color: var(--primary);"></i>
            <span style="font-weight: 600;"><?php echo $_SESSION['usuario_nome']; ?></span>
        </div>
    </header>

    <div class="tabs" style="margin-top: 2rem;">
        <button class="tab-btn active" onclick="switchTab(event, 'ciencia')"><i class="fas fa-file-signature"></i> Processos de Ciência</button>
        <button class="tab-btn" onclick="switchTab(event, 'cumprimento')"><i class="fas fa-gavel"></i> Processos de Cumprimento</button>
        <button class="tab-btn" onclick="switchTab(event, 'recurso-ciencia')"><i class="fas fa-file-export"></i> Recurso - Ciência</button>
        <button class="tab-btn" onclick="switchTab(event, 'recurso-cumprimento')"><i class="fas fa-balance-scale-right"></i> Recurso - Cumprimento</button>
    </div>

    <!-- Banner de filtro do mês -->
    <div id="banner-mes" style="display: flex; align-items: center; justify-content: space-between; background: linear-gradient(135deg, #1e3a5f 0%, #2563eb 100%); border-radius: 12px; padding: 0.85rem 1.5rem; margin-bottom: 1.5rem; box-shadow: 0 4px 15px rgba(37,99,235,0.25);">
        <div style="display: flex; align-items: center; gap: 0.75rem;">
            <i class="fas fa-calendar-alt" style="color: #93c5fd; font-size: 1.2rem;"></i>
            <span style="color: #fff; font-weight: 600; font-size: 0.95rem;">Exibindo processos com prazo em <span id="label-mes-atual" style="color: #93c5fd;"></span></span>
        </div>
        <button id="btn-toggle-mes" onclick="toggleFiltroMes()" style="background: rgba(255,255,255,0.15); border: 1px solid rgba(255,255,255,0.3); color: #fff; padding: 0.45rem 1.1rem; border-radius: 8px; cursor: pointer; font-weight: 600; font-size: 0.85rem; transition: all 0.2s;" onmouseover="this.style.background='rgba(255,255,255,0.28)'" onmouseout="this.style.background='rgba(255,255,255,0.15)'">
            <i class="fas fa-eye"></i> Ver Todos
        </button>
    </div>

    <!-- Aba Ciência -->
    <div id="tab-ciencia" class="tab-content active">
        <section class="data-section">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                <h2 style="font-size: 1.25rem;">Ciência</h2>
                <div style="display: flex; gap: 1rem; align-items: center;">
                    <input type="text" id="filtro-ciencia" placeholder="Pesquisar..." style="width: 250px; padding: 0.5rem 1rem; height: 38px; border: 1px solid var(--border); border-radius: 8px;">
                </div>
            </div>

            <div style="overflow-x: auto;">
                <table id="tabela-ciencia">
                    <thead>
                        <tr>
                            <th>Nº PROCESSO</th>
                            <th>ATO / NATUREZA</th>
                            <th>PRAZO FINAL</th>
                            <th>ANALISADOR</th>
                            <th>STATUS</th>
                            <th>AÇÕES</th>
                        </tr>
                    </thead>
                    <tbody id="lista-ciencia">
                        <!-- Preenchido via JS -->
                    </tbody>
                </table>
            </div>
            <div id="paginacao-ciencia" class="pagination" style="margin-top: 1.5rem;"></div>
        </section>
    </div>

    <!-- Aba Cumprimento -->
    <div id="tab-cumprimento" class="tab-content">
        <section class="data-section">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                <h2 style="font-size: 1.25rem;">Cumprimento</h2>
                <div style="display: flex; gap: 1rem; align-items: center;">
                    <input type="text" id="filtro-cumprimento" placeholder="Pesquisar..." style="width: 250px; padding: 0.5rem 1rem; height: 38px; border: 1px solid var(--border); border-radius: 8px;">
                </div>
            </div>

            <div style="overflow-x: auto;">
                <table id="tabela-cumprimento">
                    <thead>
                        <tr>
                            <th>Nº PROCESSO</th>
                            <th>ATO / NATUREZA</th>
                            <th>PRAZO FINAL</th>
                            <th>ANALISADOR</th>
                            <th>STATUS</th>
                            <th>AÇÕES</th>
                        </tr>
                    </thead>
                    <tbody id="lista-cumprimento">
                        <!-- Preenchido via JS -->
                    </tbody>
                </table>
            </div>
            <div id="paginacao-cumprimento" class="pagination" style="margin-top: 1.5rem;"></div>
        </section>
    </div>

    <!-- Aba Recurso - Ciência -->
    <div id="tab-recurso-ciencia" class="tab-content">
        <section class="data-section">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                <h2 style="font-size: 1.25rem;">Recurso - Ciência</h2>
                <div style="display: flex; gap: 1rem; align-items: center;">
                    <input type="text" id="filtro-recurso-ciencia" placeholder="Pesquisar..." style="width: 250px; padding: 0.5rem 1rem; height: 38px; border: 1px solid var(--border); border-radius: 8px;">
                </div>
            </div>

            <div style="overflow-x: auto;">
                <table id="tabela-recurso-ciencia">
                    <thead>
                        <tr>
                            <th>Nº PROCESSO</th>
                            <th>ATO / NATUREZA</th>
                            <th>PRAZO FINAL</th>
                            <th>ANALISADOR</th>
                            <th>STATUS</th>
                            <th>AÇÕES</th>
                        </tr>
                    </thead>
                    <tbody id="lista-recurso-ciencia">
                        <!-- Preenchido via JS -->
                    </tbody>
                </table>
            </div>
            <div id="paginacao-recurso-ciencia" class="pagination" style="margin-top: 1.5rem;"></div>
        </section>
    </div>

    <!-- Aba Recurso - Cumprimento -->
    <div id="tab-recurso-cumprimento" class="tab-content">
        <section class="data-section">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                <h2 style="font-size: 1.25rem;">Recurso - Cumprimento</h2>
                <div style="display: flex; gap: 1rem; align-items: center;">
                    <input type="text" id="filtro-recurso-cumprimento" placeholder="Pesquisar..." style="width: 250px; padding: 0.5rem 1rem; height: 38px; border: 1px solid var(--border); border-radius: 8px;">
                </div>
            </div>

            <div style="overflow-x: auto;">
                <table id="tabela-recurso-cumprimento">
                    <thead>
                        <tr>
                            <th>Nº PROCESSO</th>
                            <th>ATO / NATUREZA</th>
                            <th>PRAZO FINAL</th>
                            <th>ANALISADOR</th>
                            <th>STATUS</th>
                            <th>AÇÕES</th>
                        </tr>
                    </thead>
                    <tbody id="lista-recurso-cumprimento">
                        <!-- Preenchido via JS -->
                    </tbody>
                </table>
            </div>
            <div id="paginacao-recurso-cumprimento" class="pagination" style="margin-top: 1.5rem;"></div>
        </section>
    </div>
</main>

<script>
    window.userPerfil = '<?php echo $_SESSION['usuario_perfil'] ?? 'ANALISADOR'; ?>';
</script>
<script src="assets/js/script.js?v=74"></script>
<script>
    let todosProcessos = [];
    let filtrarPorMesAtual = true; // Começa filtrando pelo mês atual
    
    // Estados para cada aba
    let stateCiencia = { pagina: 1, itens: 10 };
    let stateCumprimento = { pagina: 1, itens: 10 };
    let stateRecursoCiencia = { pagina: 1, itens: 10 };
    let stateRecursoCumprimento = { pagina: 1, itens: 10 };

    const MESES_PT = ['Janeiro','Fevereiro','Março','Abril','Maio','Junho','Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'];

    function switchTab(e, tab) {
        document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        
        document.getElementById('tab-' + tab).classList.add('active');
        const evt = e || window.event;
        if (evt && evt.currentTarget) {
            evt.currentTarget.classList.add('active');
        }
    }

    function toggleFiltroMes() {
        filtrarPorMesAtual = !filtrarPorMesAtual;
        const btn = document.getElementById('btn-toggle-mes');
        const banner = document.getElementById('banner-mes');
        if (filtrarPorMesAtual) {
            btn.innerHTML = '<i class="fas fa-eye"></i> Ver Todos';
            banner.style.background = 'linear-gradient(135deg, #1e3a5f 0%, #2563eb 100%)';
        } else {
            btn.innerHTML = '<i class="fas fa-calendar-alt"></i> Filtrar Mês Atual';
            banner.style.background = 'linear-gradient(135deg, #374151 0%, #6b7280 100%)';
        }
        // Reset páginas e re-renderiza
        stateCiencia.pagina = 1;
        stateCumprimento.pagina = 1;
        stateRecursoCiencia.pagina = 1;
        stateRecursoCumprimento.pagina = 1;
        renderTodasAbas();
    }

    function renderTodasAbas() {
        renderTabelaTipos('CIÊNCIA', 'lista-ciencia', 'filtro-ciencia', 'paginacao-ciencia', stateCiencia);
        renderTabelaTipos('CUMPRIMENTO', 'lista-cumprimento', 'filtro-cumprimento', 'paginacao-cumprimento', stateCumprimento);
        renderTabelaTipos('RECURSO - CIÊNCIA', 'lista-recurso-ciencia', 'filtro-recurso-ciencia', 'paginacao-recurso-ciencia', stateRecursoCiencia);
        renderTabelaTipos('RECURSO - CUMPRIMENTO', 'lista-recurso-cumprimento', 'filtro-recurso-cumprimento', 'paginacao-recurso-cumprimento', stateRecursoCumprimento);
    }

    document.addEventListener('DOMContentLoaded', async () => {
        // Exibe o nome do mês atual no banner
        const agora = new Date();
        document.getElementById('label-mes-atual').textContent = MESES_PT[agora.getMonth()] + ' de ' + agora.getFullYear();

        await carregarProcessosTipos();

        document.getElementById('filtro-ciencia').addEventListener('input', () => {
            stateCiencia.pagina = 1;
            renderTabelaTipos('CIÊNCIA', 'lista-ciencia', 'filtro-ciencia', 'paginacao-ciencia', stateCiencia);
        });

        document.getElementById('filtro-cumprimento').addEventListener('input', () => {
            stateCumprimento.pagina = 1;
            renderTabelaTipos('CUMPRIMENTO', 'lista-cumprimento', 'filtro-cumprimento', 'paginacao-cumprimento', stateCumprimento);
        });

        document.getElementById('filtro-recurso-ciencia').addEventListener('input', () => {
            stateRecursoCiencia.pagina = 1;
            renderTabelaTipos('RECURSO - CIÊNCIA', 'lista-recurso-ciencia', 'filtro-recurso-ciencia', 'paginacao-recurso-ciencia', stateRecursoCiencia);
        });

        document.getElementById('filtro-recurso-cumprimento').addEventListener('input', () => {
            stateRecursoCumprimento.pagina = 1;
            renderTabelaTipos('RECURSO - CUMPRIMENTO', 'lista-recurso-cumprimento', 'filtro-recurso-cumprimento', 'paginacao-recurso-cumprimento', stateRecursoCumprimento);
        });
    });

    async function carregarProcessosTipos() {
        const resp = await fetch('api.php?acao=listar');
        let dados = await resp.json();

        // Ordenação inteligente: Se for ACESSORES, prioriza prazos. Caso contrário, data de ciência.
        const perfil = (window.userPerfil || '').toUpperCase().trim();
        
        if (perfil === 'ACESSORES') {
            dados.sort((a, b) => {
                try {
                    const statusA = String(a.status || 'PENDENTE').toUpperCase();
                    const statusB = String(b.status || 'PENDENTE').toUpperCase();
                    
                    const isFinalA = ['PROTOCOLADO', 'PROCESSO FINALIZADO'].includes(statusA);
                    const isFinalB = ['PROTOCOLADO', 'PROCESSO FINALIZADO'].includes(statusB);

                    if (!isFinalA && isFinalB) return -1;
                    if (isFinalA && !isFinalB) return 1;

                    const pA = String(a.final_prazo || '');
                    const pB = String(b.final_prazo || '');
                    const isValidA = pA.length >= 10 && !pA.startsWith('0001');
                    const isValidB = pB.length >= 10 && !pB.startsWith('0001');

                    if (isValidA && !isValidB) return -1;
                    if (!isValidA && isValidB) return 1;

                    if (isValidA && isValidB) {
                        if (pA < pB) return -1;
                        if (pA > pB) return 1;
                    }
                    
                    const dA = a.data_ciencia ? new Date(a.data_ciencia).getTime() : 0;
                    const dB = b.data_ciencia ? new Date(b.data_ciencia).getTime() : 0;
                    return (dB || 0) - (dA || 0);
                } catch (e) { return 0; }
            });
        } else {
            dados.sort((a, b) => {
                if (!a.data_ciencia) return 1;
                if (!b.data_ciencia) return -1;
                return new Date(b.data_ciencia) - new Date(a.data_ciencia);
            });
        }

        todosProcessos = dados;
        renderTodasAbas();
    }
    window.carregarProcessosTipos = carregarProcessosTipos;

    function renderTabelaTipos(tipoProcesso, tbodyId, filtroId, pagId, stateObj) {
        const tbody = document.getElementById(tbodyId);
        if (!tbody) return;
        tbody.innerHTML = '';

        const inputBusca = document.getElementById(filtroId);
        let filtrados = todosProcessos.filter(p => (p.tipo_processo || 'CIÊNCIA') === tipoProcesso);

        // Filtra pelo mês atual do prazo_final
        if (filtrarPorMesAtual) {
            const agora = new Date();
            const mesAtual = agora.getMonth() + 1;  // 1-indexed
            const anoAtual = agora.getFullYear();
            filtrados = filtrados.filter(p => {
                const prazo = String(p.final_prazo || '');
                if (!prazo || prazo.length < 7 || prazo.startsWith('0001')) return false;
                // Suporta formatos YYYY-MM-DD ou DD/MM/YYYY
                let mes, ano;
                if (prazo.includes('-')) {
                    const partes = prazo.split('-');
                    ano = parseInt(partes[0], 10);
                    mes = parseInt(partes[1], 10);
                } else if (prazo.includes('/')) {
                    const partes = prazo.split('/');
                    mes = parseInt(partes[1], 10);
                    ano = parseInt(partes[2], 10);
                } else {
                    return false;
                }
                return mes === mesAtual && ano === anoAtual;
            });
        }

        if (inputBusca && inputBusca.value) {
            const query = inputBusca.value.toUpperCase();
            filtrados = filtrados.filter(p => 
                p.numero.includes(query) || 
                p.tipo_ato.toUpperCase().includes(query) || 
                p.natureza.toUpperCase().includes(query)
            );
        }

        const totalPaginas = Math.ceil(filtrados.length / stateObj.itens);
        const inicio = (stateObj.pagina - 1) * stateObj.itens;
        const fim = inicio + stateObj.itens;
        const paginados = filtrados.slice(inicio, fim);

        if (paginados.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6" style="text-align:center; padding: 2rem; color: var(--text-muted);">Nenhum processo encontrado nesta categoria.</td></tr>';
            renderPaginacaoTipos(totalPaginas, pagId, tipoProcesso, tbodyId, filtroId, stateObj);
            return;
        }

        paginados.forEach(p => {
            const classAto = window.getColorForAto(p.tipo_ato);
            const classNat = window.getColorForNatureza(p.natureza);
            const classUser = window.getColorForUser(p.analisador);

            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td style="font-weight: 600;">${p.numero}</td>
                <td>
                    <div class="tag-badge ${classAto}" style="margin-bottom: 4px;">${p.tipo_ato}</div>
                    <div class="tag-badge ${classNat}" style="margin-top: 2px;">${p.natureza}</div>
                </td>
                <td style="font-weight: bold;">
                    ${window.formatarData(p.final_prazo)}
                </td>
                <td><span class="tag-badge ${classUser}">${p.analisador}</span></td>
                <td>
                    <span class="badge badge-${p.status.toLowerCase().trim()}">${p.status.trim()}</span>
                    ${p.status.toUpperCase().trim() === 'PROTOCOLADO' ? `
                        <div style="font-size: 0.75rem; margin-top: 5px; color: var(--text-muted); line-height: 1.2;">
                            ${(p.data_protocolo || p.protocolista || p.peticionador) ? `
                                <i class="fas fa-calendar-check" style="color: var(--status-protocolado);"></i> ${window.formatarData(p.data_protocolo)}<br>
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
                            ${window.renderDropdownActions(p)}
                        </div>
                    </div>
                </td>
            `;
            tbody.appendChild(tr);
        });

        renderPaginacaoTipos(totalPaginas, pagId, tipoProcesso, tbodyId, filtroId, stateObj);
    }

    function renderPaginacaoTipos(totalPaginas, pagId, tipoProcesso, tbodyId, filtroId, stateObj) {
        const pagContainer = document.getElementById(pagId);
        if (!pagContainer) return;
        pagContainer.innerHTML = '';
        if (totalPaginas <= 1) return;

        const btnAnt = document.createElement('button');
        btnAnt.className = 'page-btn';
        btnAnt.innerHTML = '<i class="fas fa-chevron-left"></i>';
        btnAnt.disabled = stateObj.pagina === 1;
        btnAnt.onclick = () => { stateObj.pagina--; renderTabelaTipos(tipoProcesso, tbodyId, filtroId, pagId, stateObj); };
        pagContainer.appendChild(btnAnt);

        let botoes = [];
        if (totalPaginas <= 7) {
            for (let i = 1; i <= totalPaginas; i++) botoes.push(i);
        } else {
            if (stateObj.pagina <= 4) botoes = [1, 2, 3, 4, 5, '...', totalPaginas];
            else if (stateObj.pagina >= totalPaginas - 3) botoes = [1, '...', totalPaginas - 4, totalPaginas - 3, totalPaginas - 2, totalPaginas - 1, totalPaginas];
            else botoes = [1, '...', stateObj.pagina - 1, stateObj.pagina, stateObj.pagina + 1, '...', totalPaginas];
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
                btn.className = `page-btn ${item === stateObj.pagina ? 'active' : ''}`;
                btn.textContent = item;
                btn.onclick = () => { stateObj.pagina = item; renderTabelaTipos(tipoProcesso, tbodyId, filtroId, pagId, stateObj); };
                pagContainer.appendChild(btn);
            }
        });

        const btnProx = document.createElement('button');
        btnProx.className = 'page-btn';
        btnProx.innerHTML = '<i class="fas fa-chevron-right"></i>';
        btnProx.disabled = stateObj.pagina === totalPaginas;
        btnProx.onclick = () => { stateObj.pagina++; renderTabelaTipos(tipoProcesso, tbodyId, filtroId, pagId, stateObj); };
        pagContainer.appendChild(btnProx);
    }
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
