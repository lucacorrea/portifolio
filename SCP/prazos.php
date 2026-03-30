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
    <link rel="stylesheet" href="assets/css/estilo.css?v=5">
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
        <a href="cadastro.php" class="nav-link"><i class="fas fa-plus-circle"></i> Novo</a>
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
    <header class="header">
        <div class="title-group">
            <h1>Prazos Urgentes</h1>
            <p>Processos com prazo vencido ou a vencer em até 72 horas.</p>
        </div>
        <div class="user-profile" style="display: flex; align-items: center; gap: 1rem; background: var(--glass-bg); padding: 0.5rem 1rem; border-radius: 50px; border: 1px solid var(--glass-border);">
            <i class="fas fa-user-circle" style="font-size: 1.5rem; color: var(--primary);"></i>
            <span style="font-weight: 600;"><?php echo $_SESSION['usuario_nome']; ?></span>
        </div>
    </header>

    <section class="data-section">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
            <h2 style="font-size: 1.25rem;">Urgentes</h2>
            <div style="display: flex; gap: 1rem; align-items: center;">
                <select id="filtro-tipo-prazos" style="padding: 0.5rem 1rem; border-radius: 50px; border: 1px solid var(--border); background: white; font-weight: 600; color: var(--text-main); font-size: 0.85rem; outline: none; height: 38px;">
                    <option value="">Tipos (Todos)</option>
                    <option value="CIÊNCIA">Ciência</option>
                    <option value="CUMPRIMENTO">Cumprimento</option>
                </select>
                <div style="position: relative;">
                    <i class="fas fa-search" style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: var(--text-muted);"></i>
                    <input type="text" id="filtro-urgentes" placeholder="Pesquisar..." style="width: 250px; padding: 0.5rem 1rem 0.5rem 36px; height: 38px; border-radius: 50px; border: 1px solid var(--border); outline: none;">
                </div>
            </div>
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

<script src="assets/js/script.js?v=9"></script>
<script>
    document.addEventListener('DOMContentLoaded', async () => {
        const listPrazos = document.getElementById('lista-prazos');
        const inputBusca = document.getElementById('filtro-urgentes');
        let dadosUrgentes = [];
        let paginaAtual = 1;
        const itensPorPagina = 10;

        async function carregarPrazos() {
            const resp = await fetch('api.php?acao=listar');
            const dados = await resp.json();
            
            const hoje = new Date();
            hoje.setHours(0,0,0,0);

            dadosUrgentes = dados.filter(p => {
                if (p.status === 'PROTOCOLADO' || p.status === 'ANALISADO') return false;
                if (!p.final_prazo) return false;
                
                const dataPrazo = new Date(p.final_prazo);
                dataPrazo.setHours(0,0,0,0);
                
                const diffTime = dataPrazo - hoje;
                const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
                
                return diffDays <= 3;
            });

            dadosUrgentes.sort((a, b) => new Date(a.final_prazo) - new Date(b.final_prazo));
            renderizarTabela();
        }

        function renderizarTabela() {
            if (!listPrazos) return;
            listPrazos.innerHTML = '';

            const selectTipo = document.getElementById('filtro-tipo-prazos');
            let filtrados = dadosUrgentes;

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
                listPrazos.innerHTML = '<tr><td colspan="6" style="text-align:center; padding: 2rem;">Nenhum prazo urgente encontrado.</td></tr>';
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
                
                if (diffDays < 0) {
                    corPrazo = '#ef4444';
                    labelPrazo = 'VENCIDO (' + Math.abs(diffDays) + 'd)';
                } else if (diffDays === 0) {
                    corPrazo = '#f59e0b';
                    labelPrazo = 'HOJE';
                } else if (diffDays <= 2) {
                    corPrazo = '#f59e0b';
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
                    <td style="font-weight: bold; color: ${corPrazo};">${formatarData(p.final_prazo)}</td>
                    <td style="font-weight: 700; color: ${corPrazo};">${labelPrazo}</td>
                    <td><span class="tag-badge ${classUser}">${p.analisador}</span></td>
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
                renderizarTabela();
            });
        }

        carregarPrazos();
    });
</script>
</body>
</html>
