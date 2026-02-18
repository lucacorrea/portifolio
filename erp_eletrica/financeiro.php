<?php
require_once 'config.php';

// Processar pagamento
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] == 'baixar') {
        $stmt = $pdo->prepare("
            UPDATE contas_receber 
            SET status = 'pago', data_pagamento = CURRENT_DATE 
            WHERE id = ?
        ");
        $stmt->execute([$_POST['id']]);
        
        header('Location: financeiro.php?msg=Pagamento registrado com sucesso');
        exit;
    }
}

// Buscar contas a receber
$contas = $pdo->query("
    SELECT cr.*, os.numero_os, clientes.nome as cliente_nome
    FROM contas_receber cr
    JOIN os ON cr.os_id = os.id
    JOIN clientes ON os.cliente_id = clientes.id
    ORDER BY cr.data_vencimento
")->fetchAll();

// Estatísticas financeiras
$stats = [
    'a_receber' => $pdo->query("SELECT COALESCE(SUM(valor), 0) FROM contas_receber WHERE status = 'pendente'")->fetchColumn(),
    'recebido_mes' => $pdo->query("
        SELECT COALESCE(SUM(valor), 0) 
        FROM contas_receber 
        WHERE status = 'pago' 
        AND MONTH(data_pagamento) = MONTH(CURRENT_DATE)
        AND YEAR(data_pagamento) = YEAR(CURRENT_DATE)
    ")->fetchColumn(),
    'atrasadas' => $pdo->query("
        SELECT COUNT(*) 
        FROM contas_receber 
        WHERE status = 'pendente' 
        AND data_vencimento < CURRENT_DATE
    ")->fetchColumn(),
    'total_clientes' => $pdo->query("SELECT COUNT(*) FROM clientes")->fetchColumn()
];
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ERP Elétrica - Financeiro</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <div class="container">
        <?php include 'includes/sidebar.php'; ?>
        
        <div class="main-content">
            <div class="top-bar">
                <h1 class="page-title">Financeiro</h1>
                <div class="user-info">
                    <span>Bem-vindo, <?php echo $_SESSION['usuario_nome'] ?? 'Usuário'; ?></span>
                </div>
            </div>
            
            <?php if (isset($_GET['msg'])): ?>
                <div class="notification notification-success">
                    <?php echo $_GET['msg']; ?>
                </div>
            <?php endif; ?>
            
            <!-- Stats Cards -->
            <div class="stats-cards">
                <div class="stat-card">
                    <div class="stat-info">
                        <h3>A Receber</h3>
                        <div class="stat-value"><?php echo formatarMoeda($stats['a_receber']); ?></div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-info">
                        <h3>Recebido no Mês</h3>
                        <div class="stat-value"><?php echo formatarMoeda($stats['recebido_mes']); ?></div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-check-circle" style="color: #27ae60;"></i>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-info">
                        <h3>Contas Atrasadas</h3>
                        <div class="stat-value"><?php echo $stats['atrasadas']; ?></div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-exclamation-triangle" style="color: #e74c3c;"></i>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-info">
                        <h3>Total de Clientes</h3>
                        <div class="stat-value"><?php echo $stats['total_clientes']; ?></div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-users"></i>
                    </div>
                </div>
            </div>
            
            <!-- Gráficos -->
            <div class="form-row">
                <div class="form-group" style="flex: 1;">
                    <div class="table-container">
                        <h3>Fluxo de Caixa</h3>
                        <canvas id="fluxoCaixa" height="300"></canvas>
                    </div>
                </div>
                
                <div class="form-group" style="flex: 1;">
                    <div class="table-container">
                        <h3>Recebimentos por Status</h3>
                        <canvas id="graficoStatus" height="300"></canvas>
                    </div>
                </div>
            </div>
            
            <!-- Tabela de Contas -->
            <div class="table-container" style="margin-top: 30px;">
                <h3>Contas a Receber</h3>
                <table class="datatable">
                    <thead>
                        <tr>
                            <th>OS</th>
                            <th>Cliente</th>
                            <th>Descrição</th>
                            <th>Valor</th>
                            <th>Vencimento</th>
                            <th>Status</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($contas as $conta): 
                            $status_class = $conta['status'];
                            if ($conta['status'] == 'pendente' && strtotime($conta['data_vencimento']) < time()) {
                                $status_class = 'atrasado';
                            }
                        ?>
                        <tr>
                            <td><?php echo $conta['numero_os']; ?></td>
                            <td><?php echo $conta['cliente_nome']; ?></td>
                            <td><?php echo $conta['descricao']; ?></td>
                            <td><?php echo formatarMoeda($conta['valor']); ?></td>
                            <td><?php echo formatarData($conta['data_vencimento']); ?></td>
                            <td>
                                <span class="status-badge status-<?php echo $status_class; ?>">
                                    <?php 
                                    echo $status_class == 'atrasado' ? 'Atrasado' : 
                                         ($conta['status'] == 'pago' ? 'Pago' : 'Pendente');
                                    ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($conta['status'] != 'pago'): ?>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="baixar">
                                    <input type="hidden" name="id" value="<?php echo $conta['id']; ?>">
                                    <button type="submit" class="btn btn-success btn-sm" onclick="return confirm('Confirmar recebimento?')">
                                        <i class="fas fa-check"></i> Baixar
                                    </button>
                                </form>
                                <?php endif; ?>
                                <button class="btn btn-primary btn-sm" onclick="gerarBoleto(<?php echo $conta['id']; ?>)">
                                    <i class="fas fa-barcode"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Extrato Rápido -->
            <div class="form-row" style="margin-top: 30px;">
                <div class="form-group" style="flex: 1;">
                    <div class="table-container">
                        <h3>Últimos Recebimentos</h3>
                        <table>
                            <thead>
                                <tr>
                                    <th>Data</th>
                                    <th>Cliente</th>
                                    <th>Valor</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $ultimos = $pdo->query("
                                    SELECT cr.*, clientes.nome as cliente_nome
                                    FROM contas_receber cr
                                    JOIN os ON cr.os_id = os.id
                                    JOIN clientes ON os.cliente_id = clientes.id
                                    WHERE cr.status = 'pago'
                                    ORDER BY cr.data_pagamento DESC
                                    LIMIT 5
                                ")->fetchAll();
                                
                                foreach ($ultimos as $ultimo):
                                ?>
                                <tr>
                                    <td><?php echo formatarData($ultimo['data_pagamento']); ?></td>
                                    <td><?php echo $ultimo['cliente_nome']; ?></td>
                                    <td><?php echo formatarMoeda($ultimo['valor']); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <div class="form-group" style="flex: 1;">
                    <div class="table-container">
                        <h3>Próximos Vencimentos</h3>
                        <table>
                            <thead>
                                <tr>
                                    <th>Vencimento</th>
                                    <th>Cliente</th>
                                    <th>Valor</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $proximos = $pdo->query("
                                    SELECT cr.*, clientes.nome as cliente_nome
                                    FROM contas_receber cr
                                    JOIN os ON cr.os_id = os.id
                                    JOIN clientes ON os.cliente_id = clientes.id
                                    WHERE cr.status = 'pendente'
                                    AND cr.data_vencimento >= CURRENT_DATE
                                    ORDER BY cr.data_vencimento
                                    LIMIT 5
                                ")->fetchAll();
                                
                                foreach ($proximos as $proximo):
                                ?>
                                <tr>
                                    <td><?php echo formatarData($proximo['data_vencimento']); ?></td>
                                    <td><?php echo $proximo['cliente_nome']; ?></td>
                                    <td><?php echo formatarMoeda($proximo['valor']); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="script.js"></script>
    <script>
        // Gráfico de Fluxo de Caixa
        const ctxFluxo = document.getElementById('fluxoCaixa').getContext('2d');
        new Chart(ctxFluxo, {
            type: 'line',
            data: {
                labels: ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun'],
                datasets: [{
                    label: 'Recebimentos',
                    data: [12000, 15000, 18000, 16000, 22000, 25000],
                    borderColor: '#27ae60',
                    backgroundColor: 'rgba(39, 174, 96, 0.1)',
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });
        
        // Gráfico de Status
        const ctxStatus = document.getElementById('graficoStatus').getContext('2d');
        new Chart(ctxStatus, {
            type: 'doughnut',
            data: {
                labels: ['Pago', 'Pendente', 'Atrasado'],
                datasets: [{
                    data: [
                        <?php 
                        echo $pdo->query("SELECT COUNT(*) FROM contas_receber WHERE status = 'pago'")->fetchColumn() . ',';
                        echo $pdo->query("SELECT COUNT(*) FROM contas_receber WHERE status = 'pendente' AND data_vencimento >= CURRENT_DATE")->fetchColumn() . ',';
                        echo $pdo->query("SELECT COUNT(*) FROM contas_receber WHERE status = 'pendente' AND data_vencimento < CURRENT_DATE")->fetchColumn();
                        ?>
                    ],
                    backgroundColor: ['#27ae60', '#f39c12', '#e74c3c']
                }]
            }
        });
        
        function gerarBoleto(id) {
            alert('Funcionalidade em desenvolvimento: Gerar boleto #' + id);
        }
    </script>
</body>
</html>