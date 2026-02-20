<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard de Estatísticas - Igreja de Deus Nascer de Novo</title>
    
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- Estilos Customizados -->
    <style>
        :root {
            --primary: #1a2e4a;
            --primary-light: #2d4a6f;
            --secondary: #c9a84c;
            --secondary-light: #e0c9a0;
            --success: #2d7d6f;
            --light: #f5f3f0;
            --text-primary: #2c3e50;
            --text-secondary: #7f8c8d;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, var(--light) 0%, #ffffff 100%);
            color: var(--text-primary);
            min-height: 100vh;
        }

        /* Header */
        .header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            color: white;
            padding: 40px 20px;
            text-align: center;
            border-bottom: 4px solid var(--secondary);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
        }

        .header h1 {
            font-family: 'Playfair Display', serif;
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .header p {
            font-size: 1.1rem;
            opacity: 0.9;
        }

        /* Container */
        .container-custom {
            max-width: 1400px;
            margin: 0 auto;
            padding: 40px 20px;
        }

        /* Cards */
        .card-custom {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            padding: 25px;
            margin-bottom: 30px;
            transition: all 0.3s ease;
            border-top: 4px solid var(--secondary);
        }

        .card-custom:hover {
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.12);
            transform: translateY(-5px);
        }

        .card-title {
            font-family: 'Playfair Display', serif;
            font-size: 1.8rem;
            color: var(--primary);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .card-title i {
            color: var(--secondary);
        }

        /* Stat Cards */
        .stat-card {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            color: white;
            border-radius: 12px;
            padding: 25px;
            text-align: center;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }

        .stat-value {
            font-size: 2.5rem;
            font-weight: 700;
            margin: 15px 0;
        }

        .stat-label {
            font-size: 0.95rem;
            opacity: 0.9;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        /* Charts */
        .chart-container {
            position: relative;
            height: 350px;
            margin-bottom: 20px;
        }

        .chart-wrapper {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(450px, 1fr));
            gap: 30px;
            margin-bottom: 30px;
        }

        /* Buttons */
        .btn-custom {
            background: var(--primary);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-custom:hover {
            background: var(--primary-light);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(26, 46, 74, 0.3);
            color: white;
        }

        .btn-secondary-custom {
            background: var(--secondary);
            color: var(--primary);
        }

        .btn-secondary-custom:hover {
            background: var(--secondary-light);
            color: var(--primary);
        }

        /* Footer */
        .footer {
            background: var(--primary);
            color: white;
            text-align: center;
            padding: 30px 20px;
            margin-top: 50px;
            border-top: 4px solid var(--secondary);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .header h1 {
                font-size: 1.8rem;
            }

            .chart-wrapper {
                grid-template-columns: 1fr;
            }

            .container-custom {
                padding: 20px 10px;
            }
        }

        /* Loading */
        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255, 255, 255, 0.3);
            border-top-color: white;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Alert */
        .alert-custom {
            background: rgba(45, 125, 111, 0.1);
            border-left: 4px solid var(--success);
            color: var(--success);
            padding: 15px 20px;
            border-radius: 6px;
            margin-bottom: 20px;
        }

        /* Table */
        .table-custom {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
        }

        .table-custom table {
            margin-bottom: 0;
        }

        .table-custom thead {
            background: var(--primary);
            color: white;
        }

        .table-custom th {
            padding: 15px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border: none;
        }

        .table-custom td {
            padding: 15px;
            border-bottom: 1px solid #e0e0e0;
        }

        .table-custom tbody tr:hover {
            background: var(--light);
        }

        .table-custom tbody tr:last-child td {
            border-bottom: none;
        }

        /* Badge */
        .badge-custom {
            display: inline-block;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }

        .badge-batismo {
            background: rgba(25, 118, 210, 0.1);
            color: #1976d2;
        }

        .badge-mudanca {
            background: rgba(245, 124, 0, 0.1);
            color: #f57c00;
        }

        .badge-aclamacao {
            background: rgba(45, 125, 111, 0.1);
            color: var(--success);
        }

        /* Grid */
        .grid-2 {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .grid-3 {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }

        /* Export Section */
        .export-section {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            text-align: center;
        }

        .export-section h3 {
            color: var(--primary);
            margin-bottom: 20px;
            font-family: 'Playfair Display', serif;
        }

        .export-buttons {
            display: flex;
            gap: 10px;
            justify-content: center;
            flex-wrap: wrap;
        }

        /* Info Box */
        .info-box {
            background: linear-gradient(135deg, rgba(26, 46, 74, 0.05) 0%, rgba(201, 168, 76, 0.05) 100%);
            border-left: 4px solid var(--secondary);
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .info-box strong {
            color: var(--primary);
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header">
        <h1><i class="fas fa-chart-line"></i> Dashboard de Estatísticas</h1>
        <p>Igreja de Deus Nascer de Novo</p>
    </div>

    <!-- Conteúdo -->
    <div class="container-custom">
        <!-- Info -->
        <div class="info-box">
            <i class="fas fa-info-circle"></i> 
            <strong>Última atualização:</strong> <span id="dataAtualizacao">Carregando...</span>
        </div>

        <!-- Cards de Estatísticas -->
        <div class="grid-3">
            <div class="stat-card">
                <i class="fas fa-users fa-2x" style="color: rgba(255,255,255,0.8)"></i>
                <div class="stat-label">Total de Membros</div>
                <div class="stat-value" id="totalMembros">0</div>
            </div>
            <div class="stat-card" style="background: linear-gradient(135deg, var(--success) 0%, #1f5a51 100%);">
                <i class="fas fa-cross fa-2x" style="color: rgba(255,255,255,0.8)"></i>
                <div class="stat-label">Batizados</div>
                <div class="stat-value" id="totalBatismo">0</div>
            </div>
            <div class="stat-card" style="background: linear-gradient(135deg, var(--secondary) 0%, #b8941f 100%);">
                <i class="fas fa-handshake fa-2x" style="color: rgba(255,255,255,0.8)"></i>
                <div class="stat-label">Integrados</div>
                <div class="stat-value" id="totalIntegrados">0</div>
            </div>
        </div>

        <!-- Gráficos -->
        <div class="chart-wrapper">
            <div class="card-custom">
                <div class="card-title">
                    <i class="fas fa-chart-doughnut"></i> Tipo de Integração
                </div>
                <div class="chart-container">
                    <canvas id="graficoTipo"></canvas>
                </div>
            </div>

            <div class="card-custom">
                <div class="card-title">
                    <i class="fas fa-venus-mars"></i> Distribuição por Sexo
                </div>
                <div class="chart-container">
                    <canvas id="graficoSexo"></canvas>
                </div>
            </div>

            <div class="card-custom">
                <div class="card-title">
                    <i class="fas fa-ring"></i> Estado Civil
                </div>
                <div class="chart-container">
                    <canvas id="graficoEstadoCivil"></canvas>
                </div>
            </div>

            <div class="card-custom">
                <div class="card-title">
                    <i class="fas fa-birthday-cake"></i> Faixa Etária
                </div>
                <div class="chart-container">
                    <canvas id="graficoFaixaEtaria"></canvas>
                </div>
            </div>
        </div>

        <!-- Tabela de Dados -->
        <div class="card-custom">
            <div class="card-title">
                <i class="fas fa-table"></i> Resumo de Dados
            </div>
            <div class="table-custom">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Categoria</th>
                            <th>Quantidade</th>
                            <th>Percentual</th>
                        </tr>
                    </thead>
                    <tbody id="tabelaResumo">
                        <tr>
                            <td colspan="3" class="text-center"><div class="loading"></div> Carregando...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Seção de Exportação -->
        <div class="export-section">
            <h3><i class="fas fa-download"></i> Exportar Dados</h3>
            <p style="color: var(--text-secondary); margin-bottom: 20px;">
                Baixe relatórios em PDF para análise e compartilhamento
            </p>
            <div class="export-buttons">
                <a href="relatorio.php?acao=todos" class="btn-custom btn-secondary-custom" target="_blank">
                    <i class="fas fa-file-pdf"></i> Lista de Membros
                </a>
                <a href="relatorio.php?acao=estatisticas" class="btn-custom btn-secondary-custom" target="_blank">
                    <i class="fas fa-chart-bar"></i> Relatório de Estatísticas
                </a>
                <a href="index.php" class="btn-custom">
                    <i class="fas fa-arrow-left"></i> Voltar ao Sistema
                </a>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <div class="footer">
        <p><i class="fas fa-church"></i> Igreja de Deus Nascer de Novo</p>
        <p style="font-size: 0.9rem; margin-top: 10px; opacity: 0.8;">Avenida Joanico 195, Urucu - CEP: 69460-000</p>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Carregar dados ao iniciar
        document.addEventListener('DOMContentLoaded', function() {
            carregarEstatisticas();
            document.getElementById('dataAtualizacao').textContent = new Date().toLocaleString('pt-BR');
        });

        function carregarEstatisticas() {
            fetch('membros.php?acao=estatisticas')
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'sucesso') {
                        renderizarDados(data.dados);
                    }
                })
                .catch(error => console.error('Erro:', error));
        }

        function renderizarDados(dados) {
            // Atualizar cards
            document.getElementById('totalMembros').textContent = dados.total;
            
            const batismo = dados.porTipo.find(t => t.tipo_integracao === 'Batismo')?.quantidade || 0;
            document.getElementById('totalBatismo').textContent = batismo;
            
            const integrados = dados.porTipo.reduce((sum, t) => sum + t.quantidade, 0);
            document.getElementById('totalIntegrados').textContent = integrados;

            // Gráficos
            if (dados.porTipo.length > 0) criarGraficoTipo(dados.porTipo);
            if (dados.porSexo.length > 0) criarGraficoSexo(dados.porSexo);
            if (dados.porEstadoCivil.length > 0) criarGraficoEstadoCivil(dados.porEstadoCivil);
            if (dados.porFaixaEtaria.length > 0) criarGraficoFaixaEtaria(dados.porFaixaEtaria);

            // Tabela
            renderizarTabela(dados);
        }

        function criarGraficoTipo(dados) {
            const ctx = document.getElementById('graficoTipo');
            const labels = dados.map(d => d.tipo_integracao);
            const values = dados.map(d => d.quantidade);
            const cores = ['#1a2e4a', '#c9a84c', '#2d7d6f', '#f57c00'];

            new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: labels,
                    datasets: [{
                        data: values,
                        backgroundColor: cores,
                        borderColor: 'white',
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'bottom' }
                    }
                }
            });
        }

        function criarGraficoSexo(dados) {
            const ctx = document.getElementById('graficoSexo');
            const labels = dados.map(d => d.sexo === 'M' ? 'Masculino' : 'Feminino');
            const values = dados.map(d => d.quantidade);

            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Quantidade',
                        data: values,
                        backgroundColor: ['#1976d2', '#d32f2f'],
                        borderRadius: 6
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    indexAxis: 'y',
                    plugins: { legend: { display: false } }
                }
            });
        }

        function criarGraficoEstadoCivil(dados) {
            const ctx = document.getElementById('graficoEstadoCivil');
            const labels = dados.map(d => d.estado_civil);
            const values = dados.map(d => d.quantidade);

            new Chart(ctx, {
                type: 'pie',
                data: {
                    labels: labels,
                    datasets: [{
                        data: values,
                        backgroundColor: ['#2d7d6f', '#1a2e4a', '#c9a84c', '#f57c00'],
                        borderColor: 'white',
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { position: 'bottom' } }
                }
            });
        }

        function criarGraficoFaixaEtaria(dados) {
            const ctx = document.getElementById('graficoFaixaEtaria');
            const labels = dados.map(d => d.faixa_etaria);
            const values = dados.map(d => d.quantidade);

            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Membros',
                        data: values,
                        borderColor: '#1a2e4a',
                        backgroundColor: 'rgba(26, 46, 74, 0.1)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4,
                        pointBackgroundColor: '#c9a84c',
                        pointBorderColor: '#1a2e4a',
                        pointBorderWidth: 2,
                        pointRadius: 5
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } }
                }
            });
        }

        function renderizarTabela(dados) {
            const tbody = document.getElementById('tabelaResumo');
            let html = '';

            // Por tipo de integração
            if (dados.porTipo.length > 0) {
                html += '<tr><td colspan="3" style="background: var(--light); font-weight: 600; color: var(--primary);">Por Tipo de Integração</td></tr>';
                dados.porTipo.forEach(item => {
                    const percentual = ((item.quantidade / dados.total) * 100).toFixed(1);
                    html += `<tr>
                        <td><span class="badge-custom badge-${item.tipo_integracao.toLowerCase()}">${item.tipo_integracao}</span></td>
                        <td>${item.quantidade}</td>
                        <td>${percentual}%</td>
                    </tr>`;
                });
            }

            // Por sexo
            if (dados.porSexo.length > 0) {
                html += '<tr><td colspan="3" style="background: var(--light); font-weight: 600; color: var(--primary);">Por Sexo</td></tr>';
                dados.porSexo.forEach(item => {
                    const percentual = ((item.quantidade / dados.total) * 100).toFixed(1);
                    const sexo = item.sexo === 'M' ? 'Masculino' : 'Feminino';
                    html += `<tr>
                        <td>${sexo}</td>
                        <td>${item.quantidade}</td>
                        <td>${percentual}%</td>
                    </tr>`;
                });
            }

            // Por estado civil
            if (dados.porEstadoCivil.length > 0) {
                html += '<tr><td colspan="3" style="background: var(--light); font-weight: 600; color: var(--primary);">Por Estado Civil</td></tr>';
                dados.porEstadoCivil.forEach(item => {
                    const percentual = ((item.quantidade / dados.total) * 100).toFixed(1);
                    html += `<tr>
                        <td>${item.estado_civil}</td>
                        <td>${item.quantidade}</td>
                        <td>${percentual}%</td>
                    </tr>`;
                });
            }

            tbody.innerHTML = html || '<tr><td colspan="3" class="text-center">Nenhum dado disponível</td></tr>';
        }
    </script>
</body>
</html>
