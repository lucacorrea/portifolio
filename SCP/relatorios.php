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
    <title>Relatórios - SCP</title>
    <link rel="stylesheet" href="assets/css/estilo.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/xlsx-js-style@1.2.0/dist/xlsx.bundle.js"></script>
    <style>
        .report-header {
            background: var(--glass-bg);
            backdrop-filter: blur(12px);
            padding: 2rem;
            border-radius: 20px;
            margin-bottom: 2rem;
            border: 1px solid var(--glass-border);
            box-shadow: var(--shadow-md);
        }
        .filter-bar {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            align-items: flex-end;
        }
        .filter-group {
            position: relative;
        }
        .filter-group i {
            position: absolute;
            left: 1rem;
            top: 2.3rem;
            color: var(--primary);
            font-size: 0.9rem;
        }
        .filter-group .form-control {
            padding-left: 2.8rem;
            height: 48px;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            transition: all 0.3s ease;
        }
        .filter-group .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.1);
        }
        .report-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 2rem;
        }
        .report-card {
            background: white;
            padding: 2rem;
            border-radius: 20px;
            border: 1px solid var(--border);
            box-shadow: var(--shadow-sm);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .report-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-md);
        }
        .report-card h3 {
            font-size: 1.1rem;
            color: var(--text-main);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-weight: 700;
        }
        .report-card h3 i {
            color: var(--primary);
            background: rgba(37, 99, 235, 0.1);
            padding: 0.5rem;
            border-radius: 10px;
            font-size: 1rem;
        }
        .metric-list {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        .metric-item {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }
        .metric-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.95rem;
        }
        .metric-label { font-weight: 600; color: #475569; }
        .metric-value { font-weight: 800; color: var(--text-main); }
        
        /* Bar Visualization */
        .progress-container {
            height: 8px;
            background: #f1f5f9;
            border-radius: 10px;
            overflow: hidden;
            width: 100%;
        }
        .progress-bar {
            height: 100%;
            background: var(--primary);
            border-radius: 10px;
            transition: width 1s cubic-bezier(0.4, 0, 0.2, 1);
        }

        /* Stat Cards for Resumo */
        .stat-group {
            display: grid;
            grid-template-columns: 1fr;
            gap: 1rem;
        }
        .stat-mini-card {
            background: #f8fafc;
            padding: 1.25rem;
            border-radius: 16px;
            display: flex;
            align-items: center;
            gap: 1.25rem;
            border: 1px solid #f1f5f9;
        }
        .stat-mini-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
        }
        .stat-mini-info .label {
            font-size: 0.85rem;
            color: #64748b;
            font-weight: 600;
            display: block;
            margin-bottom: 0.25rem;
        }
        .stat-mini-info .value {
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--text-main);
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
        <a href="relatorios.php" class="nav-link active"><i class="fas fa-chart-line"></i> Relatórios</a>
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
            <h1>Relatórios de Produtividade</h1>
            <p>Acompanhamento mensal de processos e manifestações.</p>
        </div>
        <button id="btn-exportar" class="btn btn-primary" style="background: #10b981;">
            <i class="fas fa-file-excel"></i> Exportar Excel
        </button>
    </header>

    <div class="report-header">
        <div class="filter-bar">
            <div class="filter-group">
                <label>Mês / Ano</label>
                <i class="fas fa-calendar-alt"></i>
                <input type="month" id="filtro-mes" class="form-control">
            </div>
            <div class="filter-group">
                <label>Analisador</label>
                <i class="fas fa-user-tie"></i>
                <select id="filtro-analisador" class="form-control">
                    <option value="">Todos</option>
                </select>
            </div>
            <div style="display: flex; gap: 0.75rem;">
                <button id="btn-filtrar" class="btn btn-primary" style="height: 48px; flex: 1; border-radius: 12px; font-weight: 600;">
                    <i class="fas fa-filter"></i> Filtrar
                </button>
                <button onclick="location.reload()" class="btn" style="height: 48px; border-radius: 12px; background: #f1f5f9; color: #475569;">
                    <i class="fas fa-redo"></i>
                </button>
            </div>
        </div>
    </div>

    <div id="resultado-relatorio" style="display: none;">
        <h2 style="text-align: center; margin-bottom: 2rem; text-transform: uppercase; letter-spacing: 1px; color: var(--primary);">
            RELATÓRIO MENSAL - <span id="label-periodo"></span>
        </h2>

        <div class="report-grid">
            <div class="report-card">
                <h3><i class="fas fa-chart-pie"></i> Resumo Geral</h3>
                <div class="stat-group">
                    <div class="stat-mini-card">
                        <div class="stat-mini-icon" style="background: rgba(37, 99, 235, 0.1); color: var(--primary);">
                            <i class="fas fa-briefcase"></i>
                        </div>
                        <div class="stat-mini-info">
                            <span class="label">Total Analisado</span>
                            <span class="value" id="res-total">0</span>
                        </div>
                    </div>
                    <div class="stat-mini-card">
                        <div class="stat-mini-icon" style="background: rgba(16, 185, 129, 0.1); color: #10b981;">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="stat-mini-info">
                            <span class="label">Prazos Cumpridos</span>
                            <span class="value" id="res-cumpridos">0</span>
                        </div>
                    </div>
                    <div class="stat-mini-card">
                        <div class="stat-mini-icon" style="background: rgba(239, 68, 68, 0.1); color: #ef4444;">
                            <i class="fas fa-exclamation-circle"></i>
                        </div>
                        <div class="stat-mini-info">
                            <span class="label">Vencidos (Perda)</span>
                            <span class="value" id="res-vencidos">0</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="report-card">
                <h3><i class="fas fa-list-check"></i> Tipos de Manifestações</h3>
                <div class="metric-list" id="res-manifestacoes">
                    <!-- Dinâmico -->
                </div>
            </div>

            <div class="report-card">
                <h3><i class="fas fa-user-edit"></i> Responsáveis pelas Análises</h3>
                <div class="metric-list" id="res-analisadores">
                    <!-- Dinâmico -->
                </div>
            </div>

            <div class="report-card">
                <h3><i class="fas fa-pen-nib"></i> Responsáveis pelo Peticionamento</h3>
                <div class="metric-list" id="res-peticionadores">
                    <!-- Dinâmico -->
                </div>
            </div>
        </div>
    </div>
</main>

<script src="assets/js/script.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', async () => {
        const inputMes = document.getElementById('filtro-mes');
        const selectAnalisador = document.getElementById('filtro-analisador');
        const btnFiltrar = document.getElementById('btn-filtrar');
        const btnExportar = document.getElementById('btn-exportar');
        
        // Set current month
        const hoje = new Date();
        const mesAtual = hoje.toISOString().slice(0, 7);
        inputMes.value = mesAtual;

        // Load analyzers for filter
        const respList = await fetch('api.php?acao=listar');
        const todosDados = await respList.json();
        const analisadoresUnicos = [...new Set(todosDados.map(p => p.analisador))].sort();
        analisadoresUnicos.forEach(a => {
            const opt = document.createElement('option');
            opt.value = a;
            opt.textContent = a;
            selectAnalisador.appendChild(opt);
        });

        async function gerarRelatorio() {
            const mes = inputMes.value;
            const analisador = selectAnalisador.value;
            
            if (!mes) return alert('Selecione um mês!');

            const [ano, mesNum] = mes.split('-');
            const filtered = todosDados.filter(p => {
                const dataP = p.data_protocolo || p.data_criacao;
                if (!dataP) return false;
                const pMes = dataP.slice(0, 7);
                
                let match = (pMes === mes);
                if (analisador) match = match && (p.analisador === analisador);
                return match;
            });

            document.getElementById('label-periodo').textContent = mesNum + '/' + ano;
            document.getElementById('res-total').textContent = filtered.length;

            // Prazos
            const vencidos = filtered.filter(p => {
                if (!p.final_prazo || !p.data_protocolo) return false;
                return new Date(p.data_protocolo) > new Date(p.final_prazo);
            }).length;
            document.getElementById('res-vencidos').textContent = vencidos;
            document.getElementById('res-cumpridos').textContent = filtered.length - vencidos;

            // Tipos Manifestação
            const tipos = {};
            filtered.forEach(p => {
                const t = p.natureza || 'OUTROS';
                tipos[t] = (tipos[t] || 0) + 1;
            });
            renderMetricList('res-manifestacoes', tipos);

            // Analisadores
            const ans = {};
            filtered.forEach(p => {
                ans[p.analisador] = (ans[p.analisador] || 0) + 1;
            });
            renderMetricList('res-analisadores', ans);

            // Peticionadores
            const pets = {};
            filtered.forEach(p => {
                const n = p.peticionador || 'Não informado';
                pets[n] = (pets[n] || 0) + 1;
            });
            renderMetricList('res-peticionadores', pets);

            document.getElementById('resultado-relatorio').style.display = 'block';
            window.dadosRelatorio = filtered;
        }

        function renderMetricList(containerId, data) {
            const container = document.getElementById(containerId);
            container.innerHTML = '';
            
            const entries = Object.entries(data).sort((a,b) => b[1] - a[1]);
            const total = entries.reduce((acc, curr) => acc + curr[1], 0);
            const max = Math.max(...entries.map(e => e[1])) || 1;

            entries.forEach(([label, value]) => {
                const perc = (value / max) * 100;
                const item = document.createElement('div');
                item.className = 'metric-item';
                item.innerHTML = `
                    <div class="metric-header">
                        <span class="metric-label">${label}</span>
                        <span class="metric-value">${value}</span>
                    </div>
                    <div class="progress-container">
                        <div class="progress-bar" style="width: 0%"></div>
                    </div>
                `;
                container.appendChild(item);
                
                // Trigger animation after append
                setTimeout(() => {
                    item.querySelector('.progress-bar').style.width = perc + '%';
                }, 50);
            });
            
            if (entries.length === 0) {
                container.innerHTML = '<div style="text-align:center; padding: 2rem; color: #64748b;">Sem dados para o período.</div>';
            }
        }

        btnFiltrar.addEventListener('click', gerarRelatorio);
        
        btnExportar.addEventListener('click', () => {
            if (!window.dadosRelatorio || window.dadosRelatorio.length === 0) {
                return alert('Gere o relatório primeiro!');
            }

            const mesRelatorio = document.getElementById('label-periodo').textContent;
            const analisadorFiltro = selectAnalisador.value || 'TODOS';
            const dataHora = new Date().toLocaleString('pt-BR');

            // 1. Criar o Corpo do Relatório com Estilos
            const styleHeader = {
                fill: { fgColor: { rgb: "2563EB" } }, // Azul Primário
                font: { color: { rgb: "FFFFFF" }, bold: true, sz: 12 },
                alignment: { horizontal: "center", vertical: "center" },
                border: {
                    top: { style: "thin", color: { rgb: "000000" } },
                    bottom: { style: "thin", color: { rgb: "000000" } },
                    left: { style: "thin", color: { rgb: "000000" } },
                    right: { style: "thin", color: { rgb: "000000" } }
                }
            };

            const styleMainTitle = {
                font: { bold: true, sz: 16, color: { rgb: "1E293B" } },
                alignment: { horizontal: "center", vertical: "center" }
            };

            const styleSubTitle = {
                font: { bold: true, sz: 11, color: { rgb: "475569" } },
                alignment: { horizontal: "center", vertical: "center" }
            };

            const styleCell = {
                border: {
                    top: { style: "thin", color: { rgb: "CCCCCC" } },
                    bottom: { style: "thin", color: { rgb: "CCCCCC" } },
                    left: { style: "thin", color: { rgb: "CCCCCC" } },
                    right: { style: "thin", color: { rgb: "CCCCCC" } }
                },
                alignment: { vertical: "center" }
            };

            // 2. Construir Dados formatados
            const data = [
                [{ v: 'SISTEMA DE CONTROLE DE PROCESSOS - SCP PGM', s: styleMainTitle }],
                [{ v: `RELATÓRIO MENSAL DE PRODUTIVIDADE - PERÍODO: ${mesRelatorio}`, s: styleSubTitle }],
                [{ v: `FILTRO ANALISADOR: ${analisadorFiltro} | GERADO EM: ${dataHora}`, s: styleSubTitle }],
                [''], // Linha vazia
                [
                    { v: 'Nº PROCESSO', s: styleHeader }, 
                    { v: 'TIPO DE ATO', s: styleHeader }, 
                    { v: 'NATUREZA', s: styleHeader }, 
                    { v: 'REVELIA', s: styleHeader }, 
                    { v: 'MANIFESTAÇÃO', s: styleHeader }, 
                    { v: 'ENVIO INTIMAÇÃO', s: styleHeader }, 
                    { v: 'CIÊNCIA', s: styleHeader }, 
                    { v: 'CONTAGEM', s: styleHeader }, 
                    { v: 'VENCIMENTO', s: styleHeader }, 
                    { v: 'PROTOCOLO', s: styleHeader }, 
                    { v: 'CRÍTICO', s: styleHeader }, 
                    { v: 'ANALISADOR', s: styleHeader }, 
                    { v: 'PETICIONADOR', s: styleHeader }, 
                    { v: 'STATUS', s: styleHeader }
                ]
            ];

            window.dadosRelatorio.forEach(p => {
                data.push([
                    { v: p.numero, s: styleCell },
                    { v: p.tipo_ato, s: styleCell },
                    { v: p.natureza, s: styleCell },
                    { v: p.revelia || 'NÃO', s: styleCell },
                    { v: p.tipo_manifestacao || '-', s: styleCell },
                    { v: formatarData(p.data_envio), s: styleCell },
                    { v: formatarData(p.data_ciencia), s: styleCell },
                    { v: p.tipo_contagem, s: styleCell },
                    { v: formatarData(p.final_prazo), s: styleCell },
                    { v: formatarData(p.data_protocolo), s: styleCell },
                    { v: p.prazo_critico || 'NÃO', s: styleCell },
                    { v: p.analisador, s: styleCell },
                    { v: p.peticionador, s: styleCell },
                    { v: p.status, s: styleCell }
                ]);
            });

            const worksheet = XLSX.utils.aoa_to_sheet(data);

            // 3. Mesclar e Configurar (0 a 13 colunas agora)
            worksheet['!merges'] = [
                { s: { r: 0, c: 0 }, e: { r: 0, c: 13 } },
                { s: { r: 1, c: 0 }, e: { r: 1, c: 13 } },
                { s: { r: 2, c: 0 }, e: { r: 2, c: 13 } }
            ];

            const wscols = [
                {wch: 28}, {wch: 35}, {wch: 20}, {wch: 10}, {wch: 40}, 
                {wch: 15}, {wch: 15}, {wch: 12}, {wch: 15}, {wch: 15}, 
                {wch: 10}, {wch: 25}, {wch: 25}, {wch: 15}
            ];
            worksheet['!cols'] = wscols;

            worksheet['!autofilter'] = { ref: `A5:N${data.length}` };

            const workbook = XLSX.utils.book_new();
            XLSX.utils.book_append_sheet(workbook, worksheet, "Relatório PGM");
            const nomeArquivo = `Relatorio_SCP_${inputMes.value || 'Geral'}.xlsx`;
            XLSX.writeFile(workbook, nomeArquivo);
        });

        // Auto filter on load
        gerarRelatorio();
    });
</script>
</body>
</html>
