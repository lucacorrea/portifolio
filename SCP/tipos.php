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
        .tab-btn {
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            border: none;
            background: none;
            cursor: pointer;
            font-weight: 600;
            color: #64748b;
            transition: all 0.3s;
            border-bottom: 3px solid transparent;
        }
        .tab-btn.active {
            color: var(--primary);
            border-bottom-color: var(--primary);
            border-radius: 0;
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
        <a href="cadastro.php" class="nav-link"><i class="fas fa-plus-circle"></i> Novo</a>
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
        <button class="tab-btn active" onclick="switchTab('ciencia')"><i class="fas fa-file-signature"></i> Processos de Ciência</button>
        <button class="tab-btn" onclick="switchTab('cumprimento')"><i class="fas fa-gavel"></i> Processos de Cumprimento</button>
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
</main>

<script src="assets/js/script.js?v=11"></script>
<script>
    let todosProcessos = [];
    
    // Estados para cada aba
    let stateCiencia = { pagina: 1, itens: 10 };
    let stateCumprimento = { pagina: 1, itens: 10 };

    function switchTab(tab) {
        document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        
        document.getElementById('tab-' + tab).classList.add('active');
        event.currentTarget.classList.add('active');
    }

    document.addEventListener('DOMContentLoaded', async () => {
        await carregarProcessosTipos();

        document.getElementById('filtro-ciencia').addEventListener('input', () => {
            stateCiencia.pagina = 1;
            renderTabelaTipos('CIÊNCIA', 'lista-ciencia', 'filtro-ciencia', 'paginacao-ciencia', stateCiencia);
        });

        document.getElementById('filtro-cumprimento').addEventListener('input', () => {
            stateCumprimento.pagina = 1;
            renderTabelaTipos('CUMPRIMENTO', 'lista-cumprimento', 'filtro-cumprimento', 'paginacao-cumprimento', stateCumprimento);
        });
    });

    async function carregarProcessosTipos() {
        const resp = await fetch('api.php?acao=listar');
        let dados = await resp.json();

        // Ordenação inteligente: Data de Ciência decrescente
        dados.sort((a, b) => {
            if (!a.data_ciencia) return 1;
            if (!b.data_ciencia) return -1;
            return new Date(b.data_ciencia) - new Date(a.data_ciencia);
        });

        todosProcessos = dados;
        
        renderTabelaTipos('CIÊNCIA', 'lista-ciencia', 'filtro-ciencia', 'paginacao-ciencia', stateCiencia);
        renderTabelaTipos('CUMPRIMENTO', 'lista-cumprimento', 'filtro-cumprimento', 'paginacao-cumprimento', stateCumprimento);
    }

    function renderTabelaTipos(tipoProcesso, tbodyId, filtroId, pagId, stateObj) {
        const tbody = document.getElementById(tbodyId);
        if (!tbody) return;
        tbody.innerHTML = '';

        const inputBusca = document.getElementById(filtroId);
        let filtrados = todosProcessos.filter(p => (p.tipo_processo || 'CIÊNCIA') === tipoProcesso);

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
</body>
</html>
