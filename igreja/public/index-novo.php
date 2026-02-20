<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

// Obter estatísticas
$stats = obterEstatisticas();
$membros = obterMembros(1, 5);
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Membros - Igreja de Deus Nascer de Novo</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.css">
    <link rel="stylesheet" href="css/style-novo.css">
    <style>
        /* Estilos específicos da página */
        .stat-card {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            color: var(--white);
            border-radius: var(--radius-lg);
            padding: var(--spacing-xl);
            display: flex;
            align-items: center;
            gap: var(--spacing-lg);
            box-shadow: var(--shadow-md);
            transition: var(--transition);
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
        }

        .stat-card.accent {
            background: linear-gradient(135deg, var(--accent) 0%, var(--accent-light) 100%);
        }

        .stat-icon {
            font-size: 2.5rem;
            opacity: 0.8;
        }

        .stat-content h3 {
            font-size: 2rem;
            margin: 0;
            color: var(--white);
        }

        .stat-content p {
            margin: 0;
            opacity: 0.9;
        }

        .chart-container {
            position: relative;
            height: 300px;
            margin-bottom: var(--spacing-xl);
        }

        .recent-members {
            margin-top: var(--spacing-xl);
        }

        .member-item {
            display: flex;
            align-items: center;
            gap: var(--spacing-md);
            padding: var(--spacing-md);
            border-bottom: 1px solid var(--gray);
            transition: var(--transition);
        }

        .member-item:hover {
            background-color: var(--light-gray);
        }

        .member-avatar {
            width: 50px;
            height: 50px;
            border-radius: var(--radius-full);
            background: linear-gradient(135deg, var(--primary) 0%, var(--accent) 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--white);
            font-weight: 700;
            font-size: 1.25rem;
        }

        .member-info {
            flex: 1;
        }

        .member-name {
            font-weight: 600;
            color: var(--text-dark);
            margin: 0;
        }

        .member-type {
            font-size: 0.85rem;
            color: var(--text-light);
            margin: 0;
        }

        .member-date {
            font-size: 0.85rem;
            color: var(--text-light);
        }
    </style>
</head>
<body>
    <div class="container-main">
        <!-- SIDEBAR -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <div class="sidebar-logo">
                    <i class="fas fa-church" style="font-size: 2rem; color: var(--accent);"></i>
                </div>
                <h2 class="sidebar-title">Igreja de Deus</h2>
                <p class="sidebar-subtitle">Nascer de Novo</p>
            </div>

            <nav class="sidebar-nav">
                <div class="nav-item active" onclick="navigate('dashboard')">
                    <i class="fas fa-chart-line"></i>
                    <span>Dashboard</span>
                </div>
                <div class="nav-item" onclick="navigate('membros')">
                    <i class="fas fa-users"></i>
                    <span>Membros</span>
                </div>
                <div class="nav-item" onclick="openModal('modalCadastro')">
                    <i class="fas fa-user-plus"></i>
                    <span>Novo Membro</span>
                </div>
                <div class="nav-item" onclick="navigate('relatorios')">
                    <i class="fas fa-file-pdf"></i>
                    <span>Relatórios</span>
                </div>
                <div class="nav-item" onclick="navigate('configuracoes')">
                    <i class="fas fa-cog"></i>
                    <span>Configurações</span>
                </div>
            </nav>
        </aside>

        <!-- CONTEÚDO PRINCIPAL -->
        <div style="flex: 1;">
            <!-- HEADER -->
            <header class="header">
                <div class="header-left">
                    <div>
                        <h1 class="header-title">Dashboard</h1>
                        <p class="header-subtitle">Bem-vindo ao Sistema de Membros</p>
                    </div>
                </div>
                <div class="header-right">
                    <button class="btn-icon" title="Notificações">
                        <i class="fas fa-bell"></i>
                    </button>
                    <button class="btn-icon" title="Perfil">
                        <i class="fas fa-user-circle"></i>
                    </button>
                </div>
            </header>

            <!-- CONTEÚDO -->
            <main class="main-content">
                <!-- BREADCRUMB -->
                <div class="breadcrumb">
                    <span>Início</span>
                    <span>/</span>
                    <span>Dashboard</span>
                </div>

                <!-- ESTATÍSTICAS -->
                <div class="grid grid-4">
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo $stats['total']; ?></h3>
                            <p>Total de Membros</p>
                        </div>
                    </div>

                    <div class="stat-card accent">
                        <div class="stat-icon">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo $stats['mes_atual']; ?></h3>
                            <p>Este Mês</p>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-water"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo $stats['batismo']; ?></h3>
                            <p>Batismos</p>
                        </div>
                    </div>

                    <div class="stat-card accent">
                        <div class="stat-icon">
                            <i class="fas fa-exchange-alt"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo $stats['mudanca']; ?></h3>
                            <p>Mudanças</p>
                        </div>
                    </div>
                </div>

                <!-- GRÁFICOS -->
                <div class="grid grid-2">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Tipo de Integração</h3>
                        </div>
                        <div class="card-body">
                            <div class="chart-container">
                                <canvas id="chartTipo"></canvas>
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Distribuição por Sexo</h3>
                        </div>
                        <div class="card-body">
                            <div class="chart-container">
                                <canvas id="chartSexo"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- MEMBROS RECENTES -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Membros Recentes</h3>
                        <button class="btn btn-primary btn-sm" onclick="navigate('membros')">
                            <i class="fas fa-arrow-right"></i> Ver Todos
                        </button>
                    </div>
                    <div class="card-body recent-members">
                        <?php foreach ($membros as $membro): ?>
                            <div class="member-item">
                                <div class="member-avatar">
                                    <?php echo strtoupper(substr($membro['nome_completo'], 0, 1)); ?>
                                </div>
                                <div class="member-info">
                                    <p class="member-name"><?php echo htmlspecialchars($membro['nome_completo']); ?></p>
                                    <p class="member-type">
                                        <span class="badge badge-primary"><?php echo $membro['tipo_integracao'] ?? 'Não definido'; ?></span>
                                    </p>
                                </div>
                                <div class="member-date">
                                    <?php echo formataData($membro['data_cadastro']); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- MODAL DE CADASTRO -->
    <div class="modal" id="modalCadastro">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Novo Membro</h2>
                <button class="modal-close" onclick="closeModal('modalCadastro')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <form id="formCadastro">
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label required">Nome Completo</label>
                            <input type="text" class="form-control" name="nome_completo" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">CPF</label>
                            <input type="text" class="form-control" name="cpf" placeholder="000.000.000-00">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Data de Nascimento</label>
                            <input type="date" class="form-control" name="data_nascimento">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Sexo</label>
                            <select class="form-control" name="sexo">
                                <option value="">Selecione...</option>
                                <option value="M">Masculino</option>
                                <option value="F">Feminino</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Telefone</label>
                            <input type="tel" class="form-control" name="telefone">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Tipo de Integração</label>
                            <select class="form-control" name="tipo_integracao">
                                <option value="">Selecione...</option>
                                <option value="Batismo">Batismo</option>
                                <option value="Mudança">Mudança</option>
                                <option value="Aclamação">Aclamação</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Foto (3x4)</label>
                        <input type="file" class="form-control" name="foto" accept="image/*">
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline" onclick="closeModal('modalCadastro')">Cancelar</button>
                <button class="btn btn-primary" onclick="salvarMembro()">Salvar Membro</button>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
    <script>
        // Gráficos
        const ctxTipo = document.getElementById('chartTipo').getContext('2d');
        new Chart(ctxTipo, {
            type: 'doughnut',
            data: {
                labels: ['Batismo', 'Mudança', 'Aclamação'],
                datasets: [{
                    data: [<?php echo $stats['batismo']; ?>, <?php echo $stats['mudanca']; ?>, <?php echo $stats['aclamacao']; ?>],
                    backgroundColor: [
                        'rgba(30, 58, 95, 0.8)',
                        'rgba(212, 175, 55, 0.8)',
                        'rgba(39, 174, 96, 0.8)'
                    ],
                    borderColor: ['#1e3a5f', '#d4af37', '#27ae60'],
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });

        const ctxSexo = document.getElementById('chartSexo').getContext('2d');
        new Chart(ctxSexo, {
            type: 'bar',
            data: {
                labels: ['Masculino', 'Feminino'],
                datasets: [{
                    label: 'Quantidade',
                    data: [<?php echo $stats['masculino']; ?>, <?php echo $stats['feminino']; ?>],
                    backgroundColor: [
                        'rgba(30, 58, 95, 0.8)',
                        'rgba(212, 175, 55, 0.8)'
                    ],
                    borderColor: ['#1e3a5f', '#d4af37'],
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

        // Funções
        function openModal(id) {
            document.getElementById(id).classList.add('show');
        }

        function closeModal(id) {
            document.getElementById(id).classList.remove('show');
        }

        function navigate(page) {
            console.log('Navegando para:', page);
        }

        function salvarMembro() {
            const form = document.getElementById('formCadastro');
            const formData = new FormData(form);
            
            fetch('api/membros.php?acao=criar', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.sucesso) {
                    alert('Membro cadastrado com sucesso!');
                    closeModal('modalCadastro');
                    form.reset();
                    location.reload();
                } else {
                    alert('Erro: ' + data.mensagem);
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                alert('Erro ao salvar membro');
            });
        }

        // Fechar modal ao clicar fora
        document.querySelectorAll('.modal').forEach(modal => {
            modal.addEventListener('click', function(e) {
                if (e.target === this) {
                    this.classList.remove('show');
                }
            });
        });
    </script>
</body>
</html>
