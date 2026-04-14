<div class="row mb-4">
    <div class="col-md-6">
        <div class="card shadow-sm border-0">
            <div class="card-body">
                <h5 class="fw-bold mb-3"><i class="fas fa-plus-circle me-2"></i>Novo Centro de Custo</h5>
                <form action="custos.php?action=criar" method="POST">
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Nome do Centro</label>
                        <input type="text" name="nome" class="form-control" placeholder="Ex: Aluguel, Energia, Marketing..." required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Tipo</label>
                        <select name="tipo" class="form-select">
                            <option value="fixo">Custo Fixo</option>
                            <option value="variavel">Custo Variável</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary w-100 fw-bold">Criar Centro</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body">
                <h5 class="fw-bold mb-3"><i class="fas fa-file-invoice-dollar me-2"></i>Lançar Custo (<?= $mes_referencia ?>)</h5>
                <form action="custos.php?action=lancar" method="POST">
                    <div class="row g-2">
                        <div class="col-md-6 mb-3">
                            <label class="form-label small fw-bold">Centro de Custo</label>
                            <select name="centro_custo_id" class="form-select" required>
                                <?php foreach ($centros as $c): ?>
                                    <option value="<?= $c['id'] ?>"><?= $c['nome'] ?> (<?= ucfirst($c['tipo']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label small fw-bold">Data do Gasto</label>
                            <input type="date" name="data_lancamento" class="form-control" value="<?= date('Y-m-d') ?>" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Descrição</label>
                        <input type="text" name="descricao" class="form-control" placeholder="Ex: Pagamento mensalidade..." required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Valor (R$)</label>
                        <input type="number" step="0.01" name="valor" class="form-control fw-bold" required>
                    </div>
                    <button type="submit" class="btn btn-success w-100 fw-bold text-white">Registrar Lançamento</button>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center stack-on-mobile">
                <h6 class="mb-0 fw-bold"><i class="fas fa-list-ul me-2"></i>Extrato de Custos</h6>
                <div class="btn-group btn-group-sm w-100 w-md-auto">
                    <a href="custos.php?action=relatorioMensal" class="btn btn-outline-primary">Ver Comparativo</a>
                    <a href="custos.php?action=rentabilidade" class="btn btn-outline-primary">Rentabilidade</a>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-3">Data</th>
                            <th>Centro / Descrição</th>
                            <th>Tipo</th>
                            <th class="text-end pe-3">Valor</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($lancamentos as $l): ?>
                        <tr>
                            <td class="ps-3"><?= date('d/m/Y', strtotime($l['data_lancamento'])) ?></td>
                            <td>
                                <span class="fw-bold"><?= $l['centro_nome'] ?></span><br>
                                <small class="text-muted"><?= $l['descricao'] ?></small>
                            </td>
                            <td>
                                <span class="badge bg-<?= $l['tipo'] == 'fixo' ? 'secondary' : 'warning' ?> bg-opacity-10 text-<?= $l['tipo'] == 'fixo' ? 'secondary' : 'dark' ?> rounded-pill small">
                                    <?= strtoupper($l['tipo']) ?>
                                </span>
                            </td>
                            <td class="text-end pe-3 fw-bold text-danger"><?= formatarMoeda($l['valor']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($lancamentos)): ?>
                            <tr><td colspan="4" class="text-center py-4 text-muted small">Nenhum custo lançado neste mês.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3">
                <h6 class="mb-0 fw-bold">Distribuição de Custos</h6>
            </div>
            <div class="card-body">
                <div id="chartCustos"></div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var options = {
        series: [
            <?php 
                $fixo = 0; $variavel = 0;
                foreach ($resumo as $r) {
                    if ($r['tipo'] == 'fixo') $fixo = (float)$r['total'];
                    if ($r['tipo'] == 'variavel') $variavel = (float)$r['total'];
                }
                echo $fixo . ", " . $variavel;
            ?>
        ],
        chart: {
            type: 'donut',
            height: 350
        },
        labels: ['Custo Fixo', 'Custo Variável'],
        colors: ['#6c757d', '#ffc107'],
        legend: { position: 'bottom' },
        plotOptions: {
            pie: {
                donut: { size: '70%', labels: { show: true, total: { show: true, label: 'TOTAL', formatter: function() { return 'R$ <?= number_format($fixo + $variavel, 2, ',', '.') ?>' } } } }
            }
        }
    };

    var chart = new ApexCharts(document.querySelector("#chartCustos"), options);
    chart.render();
});
</script>
