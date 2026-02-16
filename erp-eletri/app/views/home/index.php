<?php require_once '../app/views/partials/header.php'; ?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="h4 mb-0 text-gray-800">Dashboard Gerencial</h2>
        <div class="btn-group" role="group">
            <button type="button" class="btn btn-sm btn-outline-secondary active">Hoje</button>
            <button type="button" class="btn btn-sm btn-outline-secondary">Semana</button>
            <button type="button" class="btn btn-sm btn-outline-secondary">Mês</button>
        </div>
    </div>

    <!-- Cards Stats -->
    <div class="row g-4 mb-4">
        <div class="col-xl-3 col-md-6">
            <div class="card h-100 border-start border-4 border-primary">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-grow-1">
                            <div class="text-uppercase text-muted small fw-bold mb-1">Vendas Hoje</div>
                            <div class="h3 mb-0 fw-bold text-dark">R$ 4.250,00</div>
                            <small class="text-success"><i class="bi bi-arrow-up"></i> 12% vs ontem</small>
                        </div>
                        <div class="ms-3">
                            <i class="bi bi-currency-dollar fs-1 text-primary opacity-25"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card h-100 border-start border-4 border-success">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-grow-1">
                            <div class="text-uppercase text-muted small fw-bold mb-1">Pré-vendas Abertas</div>
                            <div class="h3 mb-0 fw-bold text-dark">8</div>
                            <small class="text-muted">Aguardando pagamento</small>
                        </div>
                        <div class="ms-3">
                            <i class="bi bi-cart fs-1 text-success opacity-25"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card h-100 border-start border-4 border-warning">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-grow-1">
                            <div class="text-uppercase text-muted small fw-bold mb-1">Estoque Baixo</div>
                            <div class="h3 mb-0 fw-bold text-dark">15</div>
                            <small class="text-danger">Itens críticos</small>
                        </div>
                        <div class="ms-3">
                            <i class="bi bi-exclamation-triangle fs-1 text-warning opacity-25"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card h-100 border-start border-4 border-info">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-grow-1">
                            <div class="text-uppercase text-muted small fw-bold mb-1">Clientes Novos</div>
                            <div class="h3 mb-0 fw-bold text-dark">3</div>
                            <small class="text-muted">Cadastrados hoje</small>
                        </div>
                        <div class="ms-3">
                            <i class="bi bi-people fs-1 text-info opacity-25"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts and Tables -->
    <div class="row g-4">
        <!-- Chart -->
        <div class="col-xl-8">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>Performance de Vendas (Últimos 7 dias)</span>
                    <i class="bi bi-graph-up text-muted"></i>
                </div>
                <div class="card-body">
                    <canvas id="salesChart" height="100"></canvas>
                </div>
            </div>
        </div>

        <!-- Filiais -->
        <div class="col-xl-4">
            <div class="card h-100">
                <div class="card-header">
                    <span>Desempenho por Filial (Hoje)</span>
                </div>
                <ul class="list-group list-group-flush">
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <div>
                            <strong>Matriz Coari</strong>
                            <div class="small text-muted">15 Vendas</div>
                        </div>
                        <span class="badge bg-primary rounded-pill">R$ 2.850,00</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <div>
                            <strong>Filial Codajás</strong>
                            <div class="small text-muted">8 Vendas</div>
                        </div>
                        <span class="badge bg-info rounded-pill">R$ 1.400,00</span>
                    </li>
                </ul>
                <div class="card-footer text-center">
                    <a href="?url=filiais/index" class="text-decoration-none small">Ver detalhes</a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // Simple Chart Demo
    const ctx = document.getElementById('salesChart').getContext('2d');
    const salesChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: ['Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb', 'Dom'],
            datasets: [{
                label: 'Vendas (R$)',
                data: [1200, 1900, 3000, 5000, 2300, 3400, 4250],
                borderColor: '#0d6efd',
                backgroundColor: 'rgba(13, 110, 253, 0.1)',
                fill: true,
                tension: 0.4
            }]
        },
        options: {
            plugins: {
                legend: { display: false }
            },
            scales: {
                y: { beginAtZero: true }
            }
        }
    });
</script>

<?php require_once '../app/views/partials/footer.php'; ?>
