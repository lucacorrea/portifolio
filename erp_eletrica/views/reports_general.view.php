<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div class="container-fluid px-4">
    <!-- Header & Filters -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4 mt-2">
        <div>
            <h4 class="fw-bold mb-0">Dashboard de Inteligência</h4>
            <p class="text-muted small mb-0">Visão estratégica do desempenho comercial</p>
        </div>
        
        <form action="relatorios_gerais.php" method="GET" class="d-flex flex-wrap gap-2">
            <div class="input-group input-group-sm shadow-sm" style="width: auto;">
                <span class="input-group-text bg-white"><i class="fas fa-calendar-alt opacity-50"></i></span>
                <input type="date" name="start_date" class="form-control" value="<?= $filters['start_date'] ?>">
                <input type="date" name="end_date" class="form-control" value="<?= $filters['end_date'] ?>">
            </div>

            <?php if ($filters['is_admin']): ?>
            <div class="input-group input-group-sm shadow-sm" style="width: 200px;">
                <span class="input-group-text bg-white"><i class="fas fa-building opacity-50"></i></span>
                <select name="filial_id" class="form-select">
                    <option value="all">TODAS UNIDADES</option>
                    <?php foreach ($branches as $b): ?>
                        <option value="<?= $b['id'] ?>" <?= $filters['filial_id'] == $b['id'] ? 'selected' : '' ?>><?= $b['nome'] ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>

            <button type="submit" class="btn btn-primary btn-sm px-3 shadow-sm fw-bold">
                <i class="fas fa-filter me-1"></i> Filtrar
            </button>
        </form>
    </div>

    <!-- KPI Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden h-100">
                <div class="card-body p-4 border-start border-primary border-4">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <div class="bg-primary bg-opacity-10 text-primary rounded-3 p-2">
                            <i class="fas fa-dollar-sign fa-lg"></i>
                        </div>
                        <span class="badge bg-success bg-opacity-10 text-success">+ <?= number_format($kpis['total_vendas'], 0) ?> Vendas</span>
                    </div>
                    <h6 class="text-muted extra-small text-uppercase fw-bold opacity-75">Faturamento Total</h6>
                    <h3 class="fw-bold mb-0"><?= formatarMoeda($kpis['faturamento']) ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden h-100">
                <div class="card-body p-4 border-start border-success border-4">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <div class="bg-success bg-opacity-10 text-success rounded-3 p-2">
                            <i class="fas fa-chart-line fa-lg"></i>
                        </div>
                    </div>
                    <h6 class="text-muted extra-small text-uppercase fw-bold opacity-75">Lucro Estimado</h6>
                    <h3 class="fw-bold mb-0 text-success"><?= formatarMoeda($kpis['lucro_estimado']) ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden h-100">
                <div class="card-body p-4 border-start border-warning border-4">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <div class="bg-warning bg-opacity-10 text-warning rounded-3 p-2">
                            <i class="fas fa-shopping-cart fa-lg"></i>
                        </div>
                    </div>
                    <h6 class="text-muted extra-small text-uppercase fw-bold opacity-75">Ticket Médio</h6>
                    <h3 class="fw-bold mb-0"><?= formatarMoeda($kpis['ticket_medio']) ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden h-100">
                <div class="card-body p-4 border-start border-info border-4">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <div class="bg-info bg-opacity-10 text-info rounded-3 p-2">
                            <i class="fas fa-percent fa-lg"></i>
                        </div>
                    </div>
                    <h6 class="text-muted extra-small text-uppercase fw-bold opacity-75">Total de Descontos</h6>
                    <h3 class="fw-bold mb-0"><?= formatarMoeda($kpis['total_descontos']) ?></h3>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <!-- Sales Chart -->
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-header bg-white border-0 py-4 px-4">
                    <h6 class="mb-0 fw-bold"><i class="fas fa-chart-area me-2 text-primary"></i>Evolução de Vendas</h6>
                </div>
                <div class="card-body px-4 pb-4">
                    <canvas id="salesChart" height="280"></canvas>
                </div>
            </div>
        </div>
        <!-- Categories Chart -->
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-header bg-white border-0 py-4 px-4">
                    <h6 class="mb-0 fw-bold"><i class="fas fa-chart-pie me-2 text-info"></i>Vendas por Categoria</h6>
                </div>
                <div class="card-body px-4 pb-4">
                    <canvas id="categoriesChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- Top Products -->
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-header bg-white border-0 py-4 px-4">
                    <h6 class="mb-0 fw-bold"><i class="fas fa-award me-2 text-warning"></i>Top 10 Produtos (Mais Vendidos)</h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th class="ps-4 small fw-bold opacity-50">PRODUTO</th>
                                    <th class="text-center small fw-bold opacity-50">QTD</th>
                                    <th class="text-end pe-4 small fw-bold opacity-50">TOTAL</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($topProducts as $p): ?>
                                <tr>
                                    <td class="ps-4">
                                        <div class="fw-bold small"><?= $p['nome'] ?></div>
                                        <div class="extra-small text-muted">ID: <?= $p['produto_id'] ?></div>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-light text-dark border px-3"><?= number_format($p['total_qtd'], 0) ?></span>
                                    </td>
                                    <td class="text-end pe-4 fw-bold text-primary"><?= formatarMoeda($p['total_valor']) ?></td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($topProducts)): ?>
                                    <tr><td colspan="3" class="text-center py-5 text-muted small">Nenhum dado registrado neste período.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Top Sellers -->
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-header bg-white border-0 py-4 px-4">
                    <h6 class="mb-0 fw-bold"><i class="fas fa-user-tag me-2 text-secondary"></i>Performance por Vendedor</h6>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                        <?php foreach ($topSellers as $s): ?>
                        <div class="list-group-item border-0 py-3 px-4">
                            <div class="d-flex justify-content-between align-items-center">
                                <div class="d-flex align-items-center">
                                    <div class="bg-secondary bg-opacity-10 text-secondary rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 40px; height: 40px;">
                                        <i class="fas fa-user"></i>
                                    </div>
                                    <div>
                                        <div class="fw-bold small"><?= $s['nome'] ?></div>
                                        <div class="extra-small text-muted"><?= $s['qtd_vendas'] ?> vendas realizadas</div>
                                    </div>
                                </div>
                                <div class="text-end">
                                    <div class="fw-bold text-dark"><?= formatarMoeda($s['total_vendas']) ?></div>
                                    <div class="progress mt-1" style="height: 4px; width: 100px;">
                                        <div class="progress-bar bg-success" style="width: 100%"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <?php if (empty($topSellers)): ?>
                            <div class="text-center py-5 text-muted small">Sem registros.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // Sales Evolution Chart
    const salesCtx = document.getElementById('salesChart').getContext('2d');
    new Chart(salesCtx, {
        type: 'line',
        data: {
            labels: <?= json_encode(array_column($salesChart, 'data')) ?>,
            datasets: [{
                label: 'Faturamento diário',
                data: <?= json_encode(array_column($salesChart, 'total')) ?>,
                borderColor: '#4e73df',
                backgroundColor: 'rgba(78, 115, 223, 0.05)',
                fill: true,
                tension: 0.4,
                pointRadius: 4,
                pointBackgroundColor: '#4e73df'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) { return 'R$ ' + value.toLocaleString('pt-BR'); }
                    }
                }
            }
        }
    });

    // Categories Chart
    const catCtx = document.getElementById('categoriesChart').getContext('2d');
    new Chart(catCtx, {
        type: 'doughnut',
        data: {
            labels: <?= json_encode(array_column($categoriesChart, 'categoria')) ?>,
            datasets: [{
                data: <?= json_encode(array_column($categoriesChart, 'total')) ?>,
                backgroundColor: ['#4e73df', '#1cc88a', '#36b9cc', '#f6c23e', '#e74a3b', '#858796', '#5a5c69', '#f8f9fc'],
                hoverOffset: 4
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { position: 'bottom', labels: { boxWidth: 12, padding: 15, font: { size: 10 } } }
            }
        }
    });
</script>

<style>
    .extra-small { font-size: 0.75rem; }
    .progress { background-color: #f8f9fc; }
</style>
