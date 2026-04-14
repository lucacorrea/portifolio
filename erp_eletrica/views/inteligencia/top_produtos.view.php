<div class="row mb-4">
    <div class="col-md-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3 d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                <h6 class="mb-0 fw-bold"><i class="fas fa-chart-bar me-2 text-primary"></i>Produtos Mais Vendidos</h6>
                <form class="d-flex flex-column flex-sm-row gap-2 w-100 w-md-auto">
                    <input type="hidden" name="action" value="topProdutos">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-light">De</span>
                        <input type="date" name="inicio" class="form-control" value="<?= $inicio ?>">
                    </div>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-light">Até</span>
                        <input type="date" name="fim" class="form-control" value="<?= $fim ?>">
                    </div>
                    <button type="submit" class="btn btn-sm btn-primary px-3 fw-bold">
                        <i class="fas fa-filter me-1"></i> FILTRAR
                    </button>
                </form>
            </div>
            <div class="card-body">
                <div id="chartTopProdutos"></div>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-3">Produto</th>
                            <th class="text-center">Qtd. Vendida</th>
                            <th class="text-end pe-3">Receita Bruta</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($produtos as $p): ?>
                        <tr>
                            <td class="ps-3 fw-bold"><?= $p['nome'] ?></td>
                            <td class="text-center"><?= number_format($p['total_qtd'], 0) ?></td>
                            <td class="text-end pe-3 fw-bold text-success"><?= formatarMoeda($p['total_receita']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var options = {
        series: [{
            name: 'Vendas',
            data: [<?php echo implode(',', array_column($produtos, 'total_qtd')); ?>]
        }],
        chart: {
            type: 'bar',
            height: 350,
            background: 'transparent'
        },
        theme: {
            mode: 'light'
        },
        plotOptions: {
            bar: {
                borderRadius: 4,
                horizontal: true,
            }
        },
        dataLabels: { enabled: false },
        xaxis: {
            categories: [<?php echo "'" . implode("','", array_column($produtos, 'nome')) . "'"; ?>],
        },
        colors: ['#fbbf24']
    };

    var chart = new ApexCharts(document.querySelector("#chartTopProdutos"), options);
    chart.render();
});
</script>
