<?php
require_once 'config.php';

// Verificar se usuário está logado
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

// Dados para o dashboard
$stats = [
    'clientes' => $pdo->query("SELECT COUNT(*) FROM clientes")->fetchColumn(),
    'os_abertas' => $pdo->query("SELECT COUNT(*) FROM os WHERE status = 'aberta'")->fetchColumn(),
    'faturamento_mes' => $pdo->query("
        SELECT COALESCE(SUM(valor), 0) 
        FROM contas_receber 
        WHERE MONTH(data_vencimento) = MONTH(CURRENT_DATE)
        AND YEAR(data_vencimento) = YEAR(CURRENT_DATE)
        AND status = 'pago'
    ")->fetchColumn(),
    'produtos_estoque' => $pdo->query("SELECT COALESCE(SUM(quantidade), 0) FROM produtos")->fetchColumn()
];
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ERP Elétrica - Dashboard</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <div class="container">
        <!-- Sidebar -->
        <?php include 'includes/sidebar.php'; ?>
        
        <!-- Main Content -->
        <div class="main-content">
            <!-- Top Bar -->
            <div class="top-bar">
                <h1 class="page-title">Dashboard</h1>
                <div class="user-info">
                    <span>Bem-vindo, <?php echo $_SESSION['usuario_nome'] ?? 'Usuário'; ?></span>
                    <a href="logout.php" class="btn btn-danger"><i class="fas fa-sign-out-alt"></i> Sair</a>
                </div>
            </div>
            
            <!-- Stats Cards -->
            <div class="stats-cards">
                <div class="stat-card">
                    <div class="stat-info">
                        <h3>Clientes</h3>
                        <div class="stat-value"><?php echo $stats['clientes']; ?></div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-users"></i>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-info">
                        <h3>OS Abertas</h3>
                        <div class="stat-value"><?php echo $stats['os_abertas']; ?></div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-clipboard-list"></i>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-info">
                        <h3>Faturamento do Mês</h3>
                        <div class="stat-value"><?php echo formatarMoeda($stats['faturamento_mes']); ?></div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-dollar-sign"></i>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-info">
                        <h3>Produtos em Estoque</h3>
                        <div class="stat-value"><?php echo $stats['produtos_estoque']; ?></div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-boxes"></i>
                    </div>
                </div>
            </div>
            
            <!-- Charts Row -->
            <div class="form-row">
                <div class="form-group" style="flex: 2;">
                    <div class="table-container">
                        <h3>Vendas Mensais</h3>
                        <canvas id="salesChart" height="300"></canvas>
                    </div>
                </div>
                
                <div class="form-group" style="flex: 1;">
                    <div class="table-container">
                        <h3>Status das OS</h3>
                        <canvas id="statusChart" height="300"></canvas>
                    </div>
                </div>
            </div>
            
            <!-- Recent Orders -->
            <div class="table-container" style="margin-top: 30px;">
                <h3>Últimas Ordens de Serviço</h3>
                <table class="datatable">
                    <thead>
                        <tr>
                            <th>Nº OS</th>
                            <th>Cliente</th>
                            <th>Data Abertura</th>
                            <th>Status</th>
                            <th>Valor</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $recent_os = $pdo->query("
                            SELECT os.*, clientes.nome as cliente_nome 
                            FROM os 
                            JOIN clientes ON os.cliente_id = clientes.id 
                            ORDER BY os.created_at DESC 
                            LIMIT 10
                        ")->fetchAll();
                        
                        foreach ($recent_os as $os):
                        ?>
                        <tr>
                            <td><?php echo $os['numero_os']; ?></td>
                            <td><?php echo $os['cliente_nome']; ?></td>
                            <td><?php echo formatarData($os['data_abertura']); ?></td>
                            <td>
                                <span class="status-badge status-<?php echo $os['status']; ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', $os['status'])); ?>
                                </span>
                            </td>
                            <td><?php echo formatarMoeda($os['valor_total']); ?></td>
                            <td>
                                <button class="btn btn-primary btn-sm" onclick="openModal('modalOS')">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <script src="script.js"></script>
</body>
</html>