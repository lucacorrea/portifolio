<!-- Productivity & Audit View -->
<div class="row g-4 mb-4">
    <!-- Sales x Commission Chart -->
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold text-secondary"><i class="fas fa-chart-area me-2"></i>Desempenho Comercial (Últimos 7 Dias)</h6>
                <div class="badge bg-primary bg-opacity-10 text-primary px-3">Tempo Real</div>
            </div>
            <div class="card-body">
                <canvas id="productivityChart" style="min-height: 300px;"></canvas>
            </div>
        </div>
    </div>

    <!-- Quick Stats -->
    <div class="col-lg-4">
        <div class="row g-4">
            <div class="col-12">
                <div class="card border-0 shadow-sm bg-primary text-white overflow-hidden position-relative">
                    <div class="card-body p-4">
                        <div class="position-relative z-index-1">
                            <h6 class="extra-small text-uppercase opacity-75 fw-bold mb-1">Total Comissões a Pagar</h6>
                            <h3 class="fw-bold mb-0">R$ <?= number_format(array_sum(array_column($rankings, 'comissao_montante')), 2, ',', '.') ?></h3>
                        </div>
                        <i class="fas fa-hand-holding-dollar position-absolute end-0 bottom-0 mb-n4 me-n4 opacity-25" style="font-size: 8rem;"></i>
                    </div>
                </div>
            </div>
            <div class="col-12">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body p-4">
                        <h6 class="extra-small text-uppercase text-muted fw-bold mb-3">Líder de Vendas</h6>
                        <?php if (!empty($rankings)): ?>
                            <div class="d-flex align-items-center">
                                <div class="bg-warning text-white rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 50px; height: 50px;">
                                    <i class="fas fa-crown fa-lg"></i>
                                </div>
                                <div>
                                    <div class="fw-bold fs-5"><?= $rankings[0]['nome'] ?></div>
                                    <div class="text-success small fw-bold">R$ <?= number_format($rankings[0]['vendas_montante'], 2, ',', '.') ?></div>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="text-muted small">Nenhuma venda registrada ainda.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Employee Rankings -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white py-3">
        <h6 class="mb-0 fw-bold text-secondary"><i class="fas fa-users-viewfinder me-2"></i>Produtividade por Colaborador</h6>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light">
                    <tr>
                        <th class="ps-4">Colaborador</th>
                        <th class="text-center">Qtd. Vendas</th>
                        <th class="text-end">Total Vendido</th>
                        <th class="text-end">Comissão Bruta</th>
                        <th class="text-end pe-4">Margem de Custo (%)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rankings as $r): ?>
                    <tr>
                        <td class="ps-4">
                            <div class="fw-bold"><?= $r['nome'] ?></div>
                            <div class="extra-small text-muted text-uppercase"><?= $r['nivel'] ?></div>
                        </td>
                        <td class="text-center fw-bold text-primary"><?= $r['total_vendas'] ?></td>
                        <td class="text-end">R$ <?= number_format($r['vendas_montante'], 2, ',', '.') ?></td>
                        <td class="text-end fw-bold text-success">R$ <?= number_format($r['comissao_montante'], 2, ',', '.') ?></td>
                        <td class="text-end pe-4">
                            <?php 
                                $margem = $r['vendas_montante'] > 0 ? ($r['comissao_montante'] / $r['vendas_montante']) * 100 : 0;
                            ?>
                            <span class="badge <?= $margem > 5 ? 'bg-danger' : 'bg-info' ?> bg-opacity-10 <?= $margem > 5 ? 'text-danger' : 'text-info' ?> px-3">
                                <?= number_format($margem, 2) ?>%
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Audit Log -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <h6 class="mb-0 fw-bold text-secondary"><i class="fas fa-fingerprint me-2"></i>Auditoria do Sistema</h6>
        <div class="small text-muted"><?= $pagination['total'] ?> alterações registradas</div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
                <thead class="bg-light">
                    <tr>
                        <th class="ps-4" width="180">Data / Hora</th>
                        <th width="150">Autor</th>
                        <th width="150">Ação</th>
                        <th>Detalhes da Alteração</th>
                    </tr>
                </thead>
                <tbody class="small">
                    <?php foreach ($auditLogs as $a): ?>
                    <tr>
                        <td class="ps-4 text-muted"><?= date('d/m/Y H:i:s', strtotime($a['created_at'])) ?></td>
                        <td class="fw-bold"><?= $a['usuario_nome'] ?: 'SISTEMA' ?></td>
                        <td>
                            <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25 px-2">
                                <?= strtoupper($a['acao']) ?>
                            </span>
                        </td>
                        <td>
                            <div class="text-truncate" style="max-width: 500px;" title='<?= htmlspecialchars($a['dados_novos']) ?>'>
                                <?= $a['tabela'] ? "<b>[{$a['tabela']} #{$a['registro_id']}]</b> " : "" ?>
                                <?= htmlspecialchars(substr($a['dados_novos'], 0, 150)) . (strlen($a['dados_novos']) > 150 ? '...' : '') ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <div class="card-footer bg-white border-top py-3">
        <?= renderPagination($pagination, 'inteligencia.php?action=productivity') ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('productivityChart').getContext('2d');
    const chartData = <?= json_encode($chartData) ?>;

    new Chart(ctx, {
        type: 'line',
        data: {
            labels: chartData.map(d => d.dia),
            datasets: [
                {
                    label: 'Vendas (R$)',
                    data: chartData.map(d => d.total_vendas),
                    borderColor: '#4e73df',
                    backgroundColor: 'rgba(78, 115, 223, 0.05)',
                    fill: true,
                    tension: 0.4
                },
                {
                    label: 'Comissões (R$)',
                    data: chartData.map(d => d.total_comissoes),
                    borderColor: '#1cc88a',
                    backgroundColor: 'rgba(28, 200, 138, 0.05)',
                    fill: true,
                    tension: 0.4
                }
            ]
        },
        options: {
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top',
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return 'R$ ' + value.toLocaleString('pt-BR');
                        }
                    }
                }
            }
        }
    });
});
</script>
