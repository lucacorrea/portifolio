
<?php
// Configuração de erro
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Caminhos
$base_path = dirname(dirname(__FILE__));
$config_path = $base_path . '/config/database.php';
$functions_path = $base_path . '/includes/functions.php';

// Verificar se os arquivos existem
if (!file_exists($config_path)) {
    die('Erro: Arquivo de configuração não encontrado em ' . $config_path);
}

if (!file_exists($functions_path)) {
    die('Erro: Arquivo de funções não encontrado em ' . $functions_path);
}

// Incluir arquivos
require_once $config_path;
require_once $functions_path;

// Inicializar variáveis
$stats = array(
    'total' => 0,
    'mes_atual' => 0,
    'batismo' => 0,
    'mudanca' => 0,
    'aclamacao' => 0
);

$membros = array();

// Tentar obter dados
try {
    if (function_exists('obterEstatisticas')) {
        $stats = obterEstatisticas();
    }
    
    if (function_exists('obterMembros')) {
        $membros = obterMembros(1, 5);
    }
} catch (Exception $e) {
    error_log('Erro ao obter dados: ' . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Membros - Igreja de Deus Nascer de Novo</title>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
    
    <!-- Estilos Customizados -->
    <link rel="stylesheet" href="css/style.css">
    
    <style>
        :root {
            --primary: #1e3a5f;
            --accent: #d4af37;
            --success: #27ae60;
            --danger: #e74c3c;
            --warning: #f39c12;
            --info: #3498db;
            --light: #ecf0f1;
            --dark: #2c3e50;
            --white: #ffffff;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f5f5;
            color: #333;
        }

        .container-main {
            display: flex;
            height: 100vh;
        }

        /* SIDEBAR */
        .sidebar {
            width: 280px;
            background: linear-gradient(135deg, var(--primary) 0%, #0f1f35 100%);
            color: white;
            padding: 2rem 1rem;
            overflow-y: auto;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
        }

        .sidebar-header {
            text-align: center;
            margin-bottom: 2rem;
            padding-bottom: 1.5rem;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .sidebar-logo {
            font-size: 2.5rem;
            margin-bottom: 1rem;
        }

        .sidebar-title {
            font-size: 1.25rem;
            font-weight: 700;
            margin: 0.5rem 0 0 0;
        }

        .sidebar-subtitle {
            font-size: 0.9rem;
            opacity: 0.8;
            margin: 0.25rem 0 0 0;
        }

        .sidebar-nav {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .nav-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            color: rgba(255,255,255,0.8);
        }

        .nav-item:hover {
            background-color: rgba(255,255,255,0.1);
            color: white;
        }

        .nav-item.active {
            background-color: var(--accent);
            color: var(--primary);
            font-weight: 600;
        }

        .nav-item i {
            font-size: 1.25rem;
        }

        /* HEADER */
        .header {
            background: white;
            padding: 1.5rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .header-title {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--primary);
            margin: 0;
        }

        .header-subtitle {
            font-size: 0.9rem;
            color: #666;
            margin: 0.25rem 0 0 0;
        }

        .header-right {
            display: flex;
            gap: 1rem;
        }

        .btn-icon {
            background: none;
            border: none;
            font-size: 1.5rem;
            color: var(--primary);
            cursor: pointer;
            transition: all 0.3s ease;
            padding: 0.5rem;
        }

        .btn-icon:hover {
            color: var(--accent);
        }

        /* MAIN CONTENT */
        .main-content {
            flex: 1;
            padding: 2rem;
            overflow-y: auto;
        }

        .breadcrumb {
            margin-bottom: 2rem;
            color: #666;
            font-size: 0.9rem;
        }

        .breadcrumb span {
            margin: 0 0.5rem;
        }

        /* GRID */
        .grid {
            display: grid;
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .grid-4 {
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        }

        .grid-2 {
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
        }

        /* STAT CARD */
        .stat-card {
            background: linear-gradient(135deg, var(--primary) 0%, #0f1f35 100%);
            color: white;
            border-radius: 12px;
            padding: 1.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 16px rgba(0,0,0,0.15);
        }

        .stat-card.accent {
            background: linear-gradient(135deg, var(--accent) 0%, #b8941f 100%);
        }

        .stat-icon {
            font-size: 2.5rem;
            opacity: 0.8;
        }

        .stat-content h3 {
            font-size: 2rem;
            margin: 0;
            color: white;
        }

        .stat-content p {
            margin: 0.25rem 0 0 0;
            opacity: 0.9;
        }

        /* CARD */
        .card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            overflow: hidden;
            margin-bottom: 1.5rem;
        }

        .card-header {
            background: linear-gradient(135deg, var(--primary) 0%, #0f1f35 100%);
            color: white;
            padding: 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .card-title {
            font-size: 1.25rem;
            font-weight: 600;
            margin: 0;
        }

        .card-body {
            padding: 1.5rem;
        }

        /* CHART */
        .chart-container {
            position: relative;
            height: 300px;
            margin-bottom: 1rem;
        }

        /* MEMBER ITEM */
        .member-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem;
            border-bottom: 1px solid #eee;
            transition: all 0.3s ease;
        }

        .member-item:hover {
            background-color: #f9f9f9;
        }

        .member-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary) 0%, var(--accent) 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 1.25rem;
        }

        .member-info {
            flex: 1;
        }

        .member-name {
            font-weight: 600;
            color: var(--primary);
            margin: 0;
        }

        .member-type {
            font-size: 0.85rem;
            color: #666;
            margin: 0.25rem 0 0 0;
        }

        .member-date {
            font-size: 0.85rem;
            color: #999;
        }

        /* BADGE */
        .badge {
            display: inline-block;
            padding: 0.35rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .badge-primary {
            background-color: var(--primary);
            color: white;
        }

        .badge-accent {
            background-color: var(--accent);
            color: var(--primary);
        }

        .badge-success {
            background-color: var(--success);
            color: white;
        }

        /* BUTTON */
        .btn {
            display: inline-block;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            border: none;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
        }

        .btn-primary {
            background-color: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background-color: #0f1f35;
            color: white;
        }

        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.9rem;
        }

        /* MODAL */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: white;
            border-radius: 12px;
            width: 90%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        }

        .modal-header {
            background: linear-gradient(135deg, var(--primary) 0%, #0f1f35 100%);
            color: white;
            padding: 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-title {
            font-size: 1.5rem;
            font-weight: 700;
            margin: 0;
        }

        .modal-close {
            background: none;
            border: none;
            color: white;
            font-size: 1.5rem;
            cursor: pointer;
        }

        .modal-body {
            padding: 1.5rem;
        }

        /* RESPONSIVIDADE */
        @media (max-width: 768px) {
            .container-main {
                flex-direction: column;
            }

            .sidebar {
                width: 100%;
                height: auto;
                padding: 1rem;
            }

            .grid-4 {
                grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            }

            .grid-2 {
                grid-template-columns: 1fr;
            }

            .stat-card {
                flex-direction: column;
                text-align: center;
            }

            .header {
                flex-direction: column;
                gap: 1rem;
            }

            .main-content {
                padding: 1rem;
            }
        }
    </style>
</head>
<body>
    <div class="container-main">
        <!-- SIDEBAR -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <div class="sidebar-logo">
                    <i class="fas fa-church" style="color: var(--accent);"></i>
                </div>
                <h2 class="sidebar-title">Igreja de Deus</h2>
                <p class="sidebar-subtitle">Nascer de Novo</p>
            </div>

            <nav class="sidebar-nav">
                <div class="nav-item active">
                    <i class="fas fa-chart-line"></i>
                    <span>Dashboard</span>
                </div>
                <div class="nav-item">
                    <i class="fas fa-users"></i>
                    <span>Membros</span>
                </div>
                <div class="nav-item">
                    <i class="fas fa-user-plus"></i>
                    <span>Novo Membro</span>
                </div>
                <div class="nav-item">
                    <i class="fas fa-file-pdf"></i>
                    <span>Relatórios</span>
                </div>
                <div class="nav-item">
                    <i class="fas fa-cog"></i>
                    <span>Configurações</span>
                </div>
            </nav>
        </aside>

        <!-- CONTEÚDO PRINCIPAL -->
        <div style="flex: 1; display: flex; flex-direction: column;">
            <!-- HEADER -->
            <header class="header">
                <div>
                    <h1 class="header-title">Dashboard</h1>
                    <p class="header-subtitle">Bem-vindo ao Sistema de Membros</p>
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
                            <h3><?php echo isset($stats['total']) ? $stats['total'] : '0'; ?></h3>
                            <p>Total de Membros</p>
                        </div>
                    </div>

                    <div class="stat-card accent">
                        <div class="stat-icon">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo isset($stats['mes_atual']) ? $stats['mes_atual'] : '0'; ?></h3>
                            <p>Este Mês</p>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-water"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo isset($stats['batismo']) ? $stats['batismo'] : '0'; ?></h3>
                            <p>Batismos</p>
                        </div>
                    </div>

                    <div class="stat-card accent">
                        <div class="stat-icon">
                            <i class="fas fa-exchange-alt"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo isset($stats['mudanca']) ? $stats['mudanca'] : '0'; ?></h3>
                            <p>Mudanças</p>
                        </div>
                    </div>
                </div>

                <!-- MEMBROS RECENTES -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Membros Recentes</h3>
                        <button class="btn btn-primary btn-sm">
                            <i class="fas fa-arrow-right"></i> Ver Todos
                        </button>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($membros)): ?>
                            <?php foreach ($membros as $membro): ?>
                                <div class="member-item">
                                    <div class="member-avatar">
                                        <?php echo strtoupper(substr($membro['nome_completo'] ?? 'N', 0, 1)); ?>
                                    </div>
                                    <div class="member-info">
                                        <p class="member-name"><?php echo htmlspecialchars($membro['nome_completo'] ?? 'Sem nome'); ?></p>
                                        <p class="member-type">
                                            <span class="badge badge-primary"><?php echo htmlspecialchars($membro['tipo_integracao'] ?? 'Não definido'); ?></span>
                                        </p>
                                    </div>
                                    <div class="member-date">
                                        <?php echo htmlspecialchars($membro['data_cadastro'] ?? 'N/A'); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p style="text-align: center; color: #999; padding: 2rem;">
                                <i class="fas fa-inbox"></i><br>
                                Nenhum membro cadastrado ainda.
                            </p>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Funções básicas
        console.log('Dashboard carregado com sucesso!');
    </script>
</body>
</html>
