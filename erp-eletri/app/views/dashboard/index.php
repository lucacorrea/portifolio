<?php require_once '../app/views/partials/header.php'; ?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Dashboard</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <button type="button" class="btn btn-sm btn-outline-secondary">Compartilhar</button>
            <button type="button" class="btn btn-sm btn-outline-secondary">Exportar</button>
        </div>
        <button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle">
            <span data-feather="calendar"></span>
            Esta semana
        </button>
    </div>
</div>

<!-- Cards Stats -->
<div class="row g-4 mb-4">
    <div class="col-md-3">
        <div class="card text-white bg-primary h-100">
            <div class="card-body">
                <h5 class="card-title">Vendas Hoje</h5>
                <p class="card-text display-6 fw-bold">R$ 0,00</p>
                <small>0 pedidos</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-white bg-success h-100">
             <div class="card-body">
                <h5 class="card-title">Vendas Mês</h5>
                <p class="card-text display-6 fw-bold">R$ 0,00</p>
                <small>Meta: R$ 50k</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-dark bg-light h-100 border-warning">
             <div class="card-body">
                <h5 class="card-title">Produtos</h5>
                <p class="card-text display-6 fw-bold"><?php echo $stats['products_count']; ?></p>
                <small class="text-warning">Verificar Estoque Baixo</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
         <div class="card text-dark bg-light h-100 border-info">
             <div class="card-body">
                <h5 class="card-title">Clientes</h5>
                <p class="card-text display-6 fw-bold"><?php echo $stats['clients_count']; ?></p>
                <small class="text-info">+ Novos este mês</small>
            </div>
        </div>
    </div>
</div>

<!-- Charts & Recent Sales -->
<div class="row">
    <div class="col-md-8">
        <div class="card mb-4">
            <div class="card-header fw-bold">
                Desempenho de Vendas (Últimos 7 dias)
            </div>
            <div class="card-body">
                <canvas id="salesChart" width="400" height="150" style="width: 100%; height: 300px; background-color: #f8f9fa; display: flex; align-items: center; justify-content: center;">
                    <!-- Placeholder for Chart.js -->
                    <p class="text-muted m-auto">Gráfico será renderizado aqui</p>
                </canvas>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card mb-4">
            <div class="card-header fw-bold">
                Vendas Recentes
            </div>
            <ul class="list-group list-group-flush">
                <?php foreach ($stats['recent_sales'] as $sale): ?>
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    <div>
                        <span class="fw-bold">COMPRA #<?php echo $sale['id']; ?></span><br>
                        <small class="text-muted"><?php echo $sale['cliente_nome']; ?></small>
                    </div>
                    <span class="badge bg-primary rounded-pill">R$ <?php echo number_format($sale['total'], 2, ',', '.'); ?></span>
                </li>
                <?php endforeach; ?>
            </ul>
             <div class="card-footer text-center">
                <a href="?url=vendas" class="btn btn-sm btn-link">Ver todas</a>
            </div>
        </div>
    </div>
</div>

<!-- Chart JS Script (CDN or Local) -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    // Dummy Data for Chart
    const ctx = document.getElementById('salesChart');
    if (ctx) {
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: ['Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb', 'Dom'],
                datasets: [{
                    label: 'Vendas (R$)',
                    data: [1200, 1900, 3000, 500, 2000, 3000, 4000],
                    borderWidth: 2,
                    borderColor: '#0d6efd',
                    backgroundColor: 'rgba(13, 110, 253, 0.1)',
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                    }
                }
            }
        });
    }
</script>

<?php require_once '../app/views/partials/footer.php'; ?>
