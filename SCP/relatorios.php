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
        :root {
            --corp-navy: #0f172a;
            --corp-slate: #475569;
            --corp-emerald: #059669;
            --corp-bg: #f8fafc;
        }
        .report-header {
            background: white;
            padding: 2.5rem;
            border-radius: 16px;
            margin-bottom: 2.5rem;
            border: 1px solid #e2e8f0;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        }
        .filter-bar {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 2rem;
            align-items: flex-end;
        }
        .filter-group label {
            font-weight: 700;
            color: var(--corp-navy);
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 0.75rem;
            display: block;
        }
        .filter-group .form-control {
            border: 1.5px solid #e2e8f0;
            font-weight: 500;
        }
        .report-grid {
            display: grid;
            grid-template-columns: repeat(12, 1fr);
            gap: 2rem;
        }
        .report-card {
            background: white;
            padding: 2rem;
            border-radius: 16px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .report-card h3 {
            font-size: 1rem;
            color: var(--corp-navy);
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid #f1f5f9;
            padding-bottom: 1rem;
        }
        .report-card h3 i {
            color: var(--primary);
            font-size: 1.1rem;
        }

        /* Stat Cards */
        .stat-main-grid {
            grid-column: span 12;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 1rem;
        }
        .stat-main-card {
            background: white;
            padding: 1.5rem;
            border-radius: 16px;
            border-left: 5px solid var(--primary);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        .stat-main-card .label { font-size: 0.8rem; font-weight: 700; color: var(--corp-slate); text-transform: uppercase; }
        .stat-main-card .value { font-size: 2rem; font-weight: 800; color: var(--corp-navy); margin-top: 0.5rem; }

        /* Corporate Table */
        .corp-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.9rem;
        }
        .corp-table th {
            text-align: left;
            padding: 1rem;
            background: #f8fafc;
            color: var(--corp-slate);
            font-weight: 700;
            text-transform: uppercase;
            font-size: 0.75rem;
            border-bottom: 2px solid #e2e8f0;
        }
        .corp-table td {
            padding: 1rem;
            border-bottom: 1px solid #f1f5f9;
            color: var(--corp-navy);
            font-weight: 500;
        }
        .corp-table tr:hover { background: #f8fafc; }
        .efficiency-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 700;
        }

        /* Metric Item Progress */
        .metric-list { display: flex; flex-direction: column; gap: 1.25rem; }
        .progress-container { height: 6px; background: #f1f5f9; border-radius: 10px; margin-top: 0.5rem; }
        .progress-bar { height: 100%; background: var(--primary); border-radius: 10px; }
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
        <a href="tipos.php" class="nav-link"><i class="fas fa-layer-group"></i> Tipos</a>
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
        <h2 id="periodo-title" style="margin-bottom: 2.5rem; font-weight: 800; color: var(--corp-navy); border-left: 8px solid var(--primary); padding-left: 1.5rem;">
            Relatório de Performance <span style="color: var(--primary);">| <span id="label-periodo"></span></span>
        </h2>

        <div class="stat-main-grid">
            <div class="stat-main-card">
                <span class="label">Volume Total</span>
                <span class="value" id="res-total">0</span>
            </div>
            <div class="stat-main-card" style="border-left-color: #f59e0b;">
                <span class="label">Operação Pendente</span>
                <span class="value" id="res-pendentes">0</span>
            </div>
            <div class="stat-main-card" style="border-left-color: #10b981;">
                <span class="label">Fluxo Protocolado</span>
                <span class="value" id="res-protocolados">0</span>
            </div>
            <div class="stat-main-card" style="border-left-color: #8b5cf6;">
                <span class="label">Check de Análise</span>
                <span class="value" id="res-analisados">0</span>
            </div>
            <div class="stat-main-card" style="border-left-color: #ef4444;">
                <span class="label">Prazos Excedidos</span>
                <span class="value" id="res-vencidos" style="color: #ef4444;">0</span>
            </div>
        </div>

        <div class="report-grid">
            <!-- Productivity Matrix -->
            <div class="report-card" style="grid-column: span 12;">
                <h3><i class="fas fa-users-cog"></i> Matriz de Produtividade da Equipe</h3>
                <div style="overflow-x: auto;">
                    <table class="corp-table">
                        <thead>
                            <tr>
                                <th>Analisador</th>
                                <th>Total Atribuído</th>
                                <th>Protocolados</th>
                                <th>Analisados</th>
                                <th>Peticionados</th>
                                <th>Performance</th>
                            </tr>
                        </thead>
                        <tbody id="res-matrix">
                            <!-- Dinâmico -->
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="report-card" id="card-mix" style="grid-column: span 6;">
                <h3><i class="fas fa-project-diagram"></i> Mix de Processos</h3>
                <div class="metric-list" id="res-tipos">
                    <!-- Dinâmico -->
                </div>
            </div>

            <div class="report-card" id="card-natureza" style="grid-column: span 6;">
                <h3><i class="fas fa-tags"></i> Natureza das Demandas</h3>
                <div class="metric-list" id="res-manifestacoes">
                    <!-- Dinâmico -->
                </div>
            </div>

            <!-- Protocolistas Breakdown -->
            <div class="report-card" id="card-protocolistas" style="grid-column: span 6;">
                <h3><i class="fas fa-user-check"></i> Rank de Protocolistas</h3>
                <div class="metric-list" id="res-protocolistas">
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
            document.getElementById('res-pendentes').textContent = filtered.filter(p => p.status === 'PENDENTE').length;
            document.getElementById('res-protocolados').textContent = filtered.filter(p => p.status === 'PROTOCOLADO').length;
            document.getElementById('res-analisados').textContent = filtered.filter(p => p.status === 'ANALISADO').length;

            // Vencidos
            const vencidos = filtered.filter(p => {
                if (!p.final_prazo) return false;
                const prazo = new Date(p.final_prazo);
                prazo.setHours(0,0,0,0);
                
                if (p.status === 'PENDENTE') {
                     const hoje = new Date();
                     hoje.setHours(0,0,0,0);
                     return hoje > prazo;
                } else {
                     const actionDateStr = p.data_analise || p.data_protocolo;
                     if (!actionDateStr) return false;
                     // Suporta "YYYY-MM-DD" ou "DD/MM/YYYY"
                     let isoDate = actionDateStr;
                     if (actionDateStr.includes('/')) {
                         const parts = actionDateStr.split(' ')[0].split('/');
                         isoDate = `${parts[2]}-${parts[1]}-${parts[0]}`;
                     }
                     const actionDate = new Date(isoDate + 'T00:00:00');
                     return actionDate > prazo;
                }
            }).length;
            document.getElementById('res-vencidos').textContent = vencidos;

            // Matrix Productivity Calculation
            const matrixContainer = document.getElementById('res-matrix');
            matrixContainer.innerHTML = '';
            
            const statsPorAnalisador = {};
            analisadoresUnicos.forEach(a => statsPorAnalisador[a] = { total: 0, protocolados: 0, analisados: 0, peticionados: 0 });
            
            filtered.forEach(p => {
                const a = p.analisador;
                if (!statsPorAnalisador[a]) statsPorAnalisador[a] = { total: 0, protocolados: 0, analisados: 0, peticionados: 0 };
                
                statsPorAnalisador[a].total++;
                if (p.status === 'PROTOCOLADO') statsPorAnalisador[a].protocolados++;
                if (p.status === 'ANALISADO') statsPorAnalisador[a].analisados++;
                if (p.peticionador) statsPorAnalisador[a].peticionados++;
            });

            Object.entries(statsPorAnalisador)
                .sort((a,b) => b[1].total - a[1].total)
                .forEach(([nome, s]) => {
                    if (s.total === 0) return;
                    
                    const eff = Math.round(((s.protocolados + s.analisados) / (s.total || 1)) * 100);
                    const color = eff > 80 ? '#059669' : (eff > 40 ? '#2563eb' : '#ef4444');
                    const bg = eff > 80 ? '#dcfce7' : (eff > 40 ? '#dbeafe' : '#fee2e2');

                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                        <td style="font-weight: 700;">${nome}</td>
                        <td>${s.total}</td>
                        <td style="color: #059669;">${s.protocolados}</td>
                        <td style="color: #8b5cf6;">${s.analisados}</td>
                        <td>${s.peticionados}</td>
                        <td><span class="efficiency-badge" style="background: ${bg}; color: ${color}">${eff}%</span></td>
                    `;
                    matrixContainer.appendChild(tr);
                });

            // Processos por Tipo
            const procTipos = {};
            filtered.forEach(p => {
                const t = p.tipo_processo || 'CIÊNCIA';
                procTipos[t] = (procTipos[t] || 0) + 1;
            });
            renderMetricList('res-tipos', procTipos);

            // Tipos Manifestação
            const tipos = {};
            filtered.forEach(p => {
                const t = p.natureza || 'OUTROS';
                tipos[t] = (tipos[t] || 0) + 1;
            });
            renderMetricList('res-manifestacoes', tipos);

            // Rank de Protocolistas
            const protocolistas = {};
            filtered.forEach(p => {
                if (p.status === 'PROTOCOLADO' && p.protocolista) {
                    const prot = p.protocolista;
                    protocolistas[prot] = (protocolistas[prot] || 0) + 1;
                }
            });
            renderMetricList('res-protocolistas', protocolistas);

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
