<!-- Chart.js já carregado no layout principal -->

<div class="container-fluid px-4">

    <!-- Header & Filters -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4 mt-2">
        <div>
            <h4 class="fw-bold mb-0"><i class="fas fa-chart-bar text-info me-2"></i>Dashboard de Inteligência</h4>
            <p class="text-muted small mb-0">Visão estratégica do desempenho comercial</p>
        </div>

        <form action="relatorios_gerais.php" method="GET" class="d-flex flex-wrap gap-2 align-items-center">
            <div class="input-group input-group-sm shadow-sm" style="width: auto;">
                <span class="input-group-text bg-white border-end-0"><i class="fas fa-calendar-alt text-muted"></i></span>
                <input type="date" name="start_date" class="form-control border-start-0" value="<?= htmlspecialchars($filters['start_date']) ?>">
                <span class="input-group-text bg-white border-start-0 border-end-0 px-1 text-muted">até</span>
                <input type="date" name="end_date" class="form-control border-start-0" value="<?= htmlspecialchars($filters['end_date']) ?>">
            </div>

            <?php if ($filters['is_admin']): ?>
            <div class="input-group input-group-sm shadow-sm" style="width: 200px;">
                <span class="input-group-text bg-white"><i class="fas fa-building text-muted"></i></span>
                <select name="filial_id" class="form-select">
                    <option value="all" <?= $filters['filial_id'] === 'all' ? 'selected' : '' ?>>TODAS AS UNIDADES</option>
                    <?php foreach ($branches as $b): ?>
                        <option value="<?= $b['id'] ?>" <?= $filters['filial_id'] == $b['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($b['nome']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>

            <!-- Atalhos de Período -->
            <div class="btn-group btn-group-sm shadow-sm">
                <a href="relatorios_gerais.php?start_date=<?= date('Y-m-d') ?>&end_date=<?= date('Y-m-d') ?><?= $filters['is_admin'] && $filters['filial_id'] !== 'all' ? '&filial_id='.$filters['filial_id'] : '' ?>" class="btn btn-outline-secondary">Hoje</a>
                <a href="relatorios_gerais.php?start_date=<?= date('Y-m-d', strtotime('-7 days')) ?>&end_date=<?= date('Y-m-d') ?><?= $filters['is_admin'] && $filters['filial_id'] !== 'all' ? '&filial_id='.$filters['filial_id'] : '' ?>" class="btn btn-outline-secondary">7 dias</a>
                <a href="relatorios_gerais.php?start_date=<?= date('Y-m-01') ?>&end_date=<?= date('Y-m-d') ?><?= $filters['is_admin'] && $filters['filial_id'] !== 'all' ? '&filial_id='.$filters['filial_id'] : '' ?>" class="btn btn-outline-secondary">Mês</a>
            </div>

            <button type="submit" class="btn btn-primary btn-sm px-3 shadow-sm fw-bold">
                <i class="fas fa-filter me-1"></i>Filtrar
            </button>
        </form>
    </div>

    <!-- KPI Cards -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm rounded-4 h-100" style="border-left: 4px solid #4e73df !important;">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div class="bg-primary bg-opacity-10 text-primary rounded-3 p-2">
                            <i class="fas fa-dollar-sign fa-lg"></i>
                        </div>
                        <span class="badge bg-success bg-opacity-10 text-success small"><?= number_format($kpis['total_vendas'] ?? 0, 0) ?> vendas</span>
                    </div>
                    <div class="text-muted extra-small text-uppercase fw-bold opacity-75 mb-1">Faturamento Total</div>
                    <h4 class="fw-bold mb-0 text-primary"><?= formatarMoeda($kpis['faturamento'] ?? 0) ?></h4>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm rounded-4 h-100" style="border-left: 4px solid #1cc88a !important;">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div class="bg-success bg-opacity-10 text-success rounded-3 p-2">
                            <i class="fas fa-chart-line fa-lg"></i>
                        </div>
                    </div>
                    <div class="text-muted extra-small text-uppercase fw-bold opacity-75 mb-1">Lucro Estimado</div>
                    <h4 class="fw-bold mb-0 text-success"><?= formatarMoeda($kpis['lucro_estimado'] ?? 0) ?></h4>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm rounded-4 h-100" style="border-left: 4px solid #f6c23e !important;">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div class="bg-warning bg-opacity-10 text-warning rounded-3 p-2">
                            <i class="fas fa-shopping-cart fa-lg"></i>
                        </div>
                    </div>
                    <div class="text-muted extra-small text-uppercase fw-bold opacity-75 mb-1">Ticket Médio</div>
                    <h4 class="fw-bold mb-0"><?= formatarMoeda($kpis['ticket_medio'] ?? 0) ?></h4>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm rounded-4 h-100" style="border-left: 4px solid #e74a3b !important;">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div class="bg-danger bg-opacity-10 text-danger rounded-3 p-2">
                            <i class="fas fa-percent fa-lg"></i>
                        </div>
                    </div>
                    <div class="text-muted extra-small text-uppercase fw-bold opacity-75 mb-1">Total Descontos</div>
                    <h4 class="fw-bold mb-0 text-danger"><?= formatarMoeda($kpis['total_descontos'] ?? 0) ?></h4>
                </div>
            </div>
        </div>
    </div>

    <!-- Row 2: Sales Chart + Categories -->
    <div class="row g-4 mb-4">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-header bg-white border-0 py-3 px-4">
                    <h6 class="mb-0 fw-bold"><i class="fas fa-chart-area me-2 text-primary"></i>Evolução de Faturamento</h6>
                </div>
                <div class="card-body px-4 pb-4" style="min-height: 280px;">
                    <canvas id="salesChart"></canvas>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-header bg-white border-0 py-3 px-4">
                    <h6 class="mb-0 fw-bold"><i class="fas fa-chart-pie me-2 text-info"></i>Vendas por Categoria</h6>
                </div>
                <div class="card-body px-4 pb-4">
                    <?php if (empty($categories_chart)): ?>
                        <div class="text-center py-5 text-muted small"><i class="fas fa-chart-pie fa-2x mb-2 d-block opacity-25"></i>Sem dados no período</div>
                    <?php else: ?>
                        <canvas id="categoriesChart"></canvas>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Row 3: Top Products + Top Sellers + Payments -->
    <div class="row g-4 mb-4">
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-header bg-white border-0 py-3 px-4">
                    <h6 class="mb-0 fw-bold"><i class="fas fa-award me-2 text-warning"></i>Top 10 Produtos</h6>
                </div>
                <div class="card-body p-0" style="overflow: auto; max-height: 400px;">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light sticky-top">
                            <tr>
                                <th class="ps-4 small fw-bold opacity-50">#</th>
                                <th class="small fw-bold opacity-50">PRODUTO</th>
                                <th class="text-center small fw-bold opacity-50">QTD</th>
                                <th class="text-end pe-4 small fw-bold opacity-50">TOTAL</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($top_products)): ?>
                                <tr><td colspan="4" class="text-center py-5 text-muted small">Sem dados no período.</td></tr>
                            <?php else: ?>
                                <?php foreach ($top_products as $i => $p): ?>
                                <tr>
                                    <td class="ps-4 text-muted fw-bold"><?= $i + 1 ?></td>
                                    <td>
                                        <div class="fw-bold small"><?= htmlspecialchars($p['nome'] ?? '') ?></div>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-light text-dark border"><?= number_format($p['total_qtd'], 0) ?></span>
                                    </td>
                                    <td class="text-end pe-4 fw-bold text-primary small"><?= formatarMoeda($p['total_valor'] ?? 0) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-header bg-white border-0 py-3 px-4">
                    <h6 class="mb-0 fw-bold"><i class="fas fa-user-tag me-2 text-secondary"></i>Performance por Vendedor</h6>
                </div>
                <div class="card-body p-0" style="overflow: auto; max-height: 400px;">
                    <?php if (empty($top_sellers)): ?>
                        <div class="text-center py-5 text-muted small"><i class="fas fa-users fa-2x mb-2 d-block opacity-25"></i>Sem dados no período</div>
                    <?php else: ?>
                        <?php
                        $maxVendas = max(array_column($top_sellers, 'total_vendas')) ?: 1;
                        foreach ($top_sellers as $s):
                            $pct = round(($s['total_vendas'] / $maxVendas) * 100);
                        ?>
                        <div class="p-3 border-bottom">
                            <div class="d-flex justify-content-between mb-1">
                                <div class="fw-bold small"><?= htmlspecialchars($s['nome'] ?? '') ?></div>
                                <div class="fw-bold text-primary small"><?= formatarMoeda($s['total_vendas'] ?? 0) ?></div>
                            </div>
                            <div class="d-flex justify-content-between mb-1">
                                <div class="extra-small text-muted"><?= $s['qtd_vendas'] ?> venda(s)</div>
                            </div>
                            <div class="progress" style="height: 5px;">
                                <div class="progress-bar bg-primary" style="width: <?= $pct ?>%"></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-3">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-header bg-white border-0 py-3 px-4">
                    <h6 class="mb-0 fw-bold"><i class="fas fa-credit-card me-2 text-success"></i>Formas de Pagamento</h6>
                </div>
                <div class="card-body">
                    <?php if (empty($payment_chart)): ?>
                        <div class="text-center py-4 text-muted small"><i class="fas fa-credit-card fa-2x mb-2 d-block opacity-25"></i>Sem dados</div>
                    <?php else: ?>
                        <canvas id="paymentChart"></canvas>
                        <div class="mt-3">
                            <?php foreach ($payment_chart as $pm): ?>
                            <div class="d-flex justify-content-between align-items-center mb-2 small">
                                <span class="text-capitalize"><?= htmlspecialchars($pm['metodo'] ?? '-') ?></span>
                                <span class="fw-bold"><?= formatarMoeda($pm['total'] ?? 0) ?></span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    // Dados vindos do PHP
    const salesLabels  = <?= json_encode(array_column($sales_chart ?? [], 'data')) ?>;
    const salesData    = <?= json_encode(array_map('floatval', array_column($sales_chart ?? [], 'total'))) ?>;
    const catLabels    = <?= json_encode(array_column($categories_chart ?? [], 'categoria')) ?>;
    const catData      = <?= json_encode(array_map('floatval', array_column($categories_chart ?? [], 'total'))) ?>;
    const payLabels    = <?= json_encode(array_column($payment_chart ?? [], 'metodo')) ?>;
    const payData      = <?= json_encode(array_map('floatval', array_column($payment_chart ?? [], 'total'))) ?>;

    const palette = ['#4e73df','#1cc88a','#36b9cc','#f6c23e','#e74a3b','#858796','#5a5c69','#f8f9fc'];
    const fmtBRL  = v => 'R$ ' + parseFloat(v).toLocaleString('pt-BR', {minimumFractionDigits: 2});

    // Sales line chart
    if (salesLabels.length > 0) {
        new Chart(document.getElementById('salesChart').getContext('2d'), {
            type: 'line',
            data: {
                labels: salesLabels,
                datasets: [{
                    label: 'Faturamento',
                    data: salesData,
                    borderColor: '#4e73df',
                    backgroundColor: 'rgba(78,115,223,0.08)',
                    fill: true,
                    tension: 0.4,
                    pointRadius: salesLabels.length > 20 ? 2 : 4,
                    pointBackgroundColor: '#4e73df'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { callback: fmtBRL }
                    }
                }
            }
        });
    } else {
        const c = document.getElementById('salesChart');
        if (c) c.closest('.card-body').innerHTML = '<div class="text-center py-5 text-muted small"><i class="fas fa-chart-area fa-2x mb-2 d-block opacity-25"></i>Sem vendas no período selecionado</div>';
    }

    // Categories donut chart
    if (catLabels.length > 0) {
        new Chart(document.getElementById('categoriesChart').getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: catLabels,
                datasets: [{ data: catData, backgroundColor: palette, hoverOffset: 4 }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { position: 'bottom', labels: { boxWidth: 12, padding: 12, font: { size: 10 } } },
                    tooltip: { callbacks: { label: ctx => ' ' + fmtBRL(ctx.parsed) } }
                }
            }
        });
    }

    // Payments pie chart
    if (payLabels.length > 0) {
        new Chart(document.getElementById('paymentChart').getContext('2d'), {
            type: 'pie',
            data: {
                labels: payLabels,
                datasets: [{ data: payData, backgroundColor: palette, hoverOffset: 4 }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { display: false },
                    tooltip: { callbacks: { label: ctx => ' ' + fmtBRL(ctx.parsed) } }
                }
            }
        });
    }
})();
</script>
