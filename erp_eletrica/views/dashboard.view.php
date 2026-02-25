<div class="row g-4 mb-4">
    <!-- Stat Cards -->
    <div class="col-md-3">
        <div class="card h-100 border-start border-primary border-4 p-2">
            <div class="card-body">
                <div class="text-muted small fw-bold text-uppercase mb-2">Faturamento / Hoje</div>
                <h3 class="mb-0 fw-bold"><?= formatarMoeda($stats['vendas_hoje']) ?></h3>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card h-100 border-start border-success border-4 p-2">
            <div class="card-body">
                <div class="text-muted small fw-bold text-uppercase mb-2">Faturamento / Mês</div>
                <h3 class="mb-0 fw-bold"><?= formatarMoeda($stats['vendas_mes']) ?></h3>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card h-100 border-start border-warning border-4 p-2">
            <div class="card-body">
                <div class="text-muted small fw-bold text-uppercase mb-2">Ticket Médio</div>
                <h3 class="mb-0 fw-bold"><?= formatarMoeda($stats['ticket_medio']) ?></h3>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card h-100 border-start border-info border-4 p-2">
            <div class="card-body">
                <div class="text-muted small fw-bold text-uppercase mb-2">Margem de Lucro</div>
                <h3 class="mb-0 fw-bold text-info"><?= number_format($stats['margem_lucro'], 1) ?> %</h3>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-lg-8">
        <div class="card h-100">
            <div class="card-header bg-white py-3">
                <h6 class="mb-0 fw-bold text-secondary">Desempenho Comercial (Últimos 6 meses)</h6>
            </div>
            <div class="card-body">
                <div id="chart-faturamento"></div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header bg-white py-3">
                <h6 class="mb-0 fw-bold text-secondary">Avisos de Operação</h6>
            </div>
            <div class="card-body">
                <div class="d-flex align-items-center mb-4">
                    <div class="bg-danger bg-opacity-10 p-3 rounded-circle me-3">
                        <i class="fas fa-triangle-exclamation text-danger fs-4"></i>
                    </div>
                    <div>
                        <div class="fw-bold fs-4"><?= $stats['estoque_critico'] ?></div>
                        <div class="text-muted small">Itens em estoque crítico</div>
                    </div>
                </div>
                <div class="d-flex align-items-center mb-4">
                    <div class="bg-primary bg-opacity-10 p-3 rounded-circle me-3">
                        <i class="fas fa-cash-register text-primary fs-4"></i>
                    </div>
                    <div>
                        <div class="fw-bold fs-4">PDV</div>
                        <div class="text-muted small">Iniciar nova venda rápida</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold text-secondary">Vendas Recentes</h6>
                <a href="vendas.php" class="btn btn-sm btn-outline-primary fw-bold">Ver Todas</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th class="ps-4">Venda</th>
                                <th>Cliente</th>
                                <th>Data</th>
                                <th>Total</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentes_vendas as $venda): ?>
                            <tr>
                                <td class="ps-4 fw-bold text-muted">#<?= $venda['id'] ?></td>
                                <td><?= $venda['cliente_nome'] ?? '<span class="text-muted italic">Venda Direta</span>' ?></td>
                                <td><?= date('d/m/Y', strtotime($venda['data_venda'])) ?></td>
                                <td class="fw-bold"><?= formatarMoeda($venda['valor_total']) ?></td>
                                <td>
                                    <?php $statusColor = $venda['status'] == 'concluido' ? 'success' : 'danger'; ?>
                                    <span class="badge bg-<?= $statusColor ?> bg-opacity-10 text-<?= $statusColor ?> rounded-pill">
                                        <?= strtoupper($venda['status']) ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card">
            <div class="card-header bg-white py-3 text-secondary">
                <h6 class="mb-0 fw-bold">Produtos de Maior Giro</h6>
            </div>
            <div class="card-body p-0">
                <div class="list-group list-group-flush">
                    <?php foreach ($top_produtos as $prod): ?>
                    <div class="list-group-item d-flex justify-content-between align-items-center py-3">
                        <div>
                            <div class="small fw-bold text-dark"><?= $prod['nome'] ?></div>
                            <div class="text-muted small"><?= $prod['total_vendido'] ?> UN vendidas</div>
                        </div>
                        <div class="text-end">
                            <div class="small fw-bold text-primary"><?= formatarMoeda($prod['receita']) ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ApexCharts Script -->
<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        var options = {
            series: [{
                name: 'Faturamento',
                data: [<?= implode(',', array_column($faturamento_historico, 'total')) ?>]
            }],
            chart: {
                type: 'area',
                height: 350,
                toolbar: { show: false },
                fontFamily: 'Inter, sans-serif'
            },
            colors: ['#0d6efd'],
            dataLabels: { enabled: false },
            stroke: { curve: 'smooth', width: 3 },
            fill: {
                type: 'gradient',
                gradient: {
                    shadeIntensity: 1,
                    opacityFrom: 0.45,
                    opacityTo: 0.05,
                    stops: [20, 100, 100, 100]
                }
            },
            xaxis: {
                categories: [<?= "'" . implode("','", array_column($faturamento_historico, 'mes')) . "'" ?>],
                axisBorder: { show: false },
                axisTicks: { show: false }
            },
            yaxis: {
                labels: {
                    formatter: function (val) {
                        return "R$ " + val.toLocaleString('pt-BR');
                    }
                }
            },
            grid: {
                borderColor: '#f1f1f1',
                strokeDashArray: 4
            }
        };

        var chart = new ApexCharts(document.querySelector("#chart-faturamento"), options);
        chart.render();
    });
</script>
