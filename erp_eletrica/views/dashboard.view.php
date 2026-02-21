<div class="row g-4 mb-4">
    <!-- Stat Cards -->
    <div class="col-md-3">
        <div class="card h-100 border-0 shadow-sm border-start border-primary border-4">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div class="text-muted small fw-bold text-uppercase">Vendas Hoje</div>
                    <div class="bg-primary bg-opacity-10 p-2 rounded">
                        <i class="fas fa-cart-shopping text-primary"></i>
                    </div>
                </div>
                <h3 class="mb-0 fw-bold"><?= formatarMoeda($stats['vendas_hoje']) ?></h3>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card h-100 border-0 shadow-sm border-start border-success border-4">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div class="text-muted small fw-bold text-uppercase">Vendas no Mês</div>
                    <div class="bg-success bg-opacity-10 p-2 rounded">
                        <i class="fas fa-calendar-check text-success"></i>
                    </div>
                </div>
                <h3 class="mb-0 fw-bold"><?= formatarMoeda($stats['vendas_mes']) ?></h3>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card h-100 border-0 shadow-sm border-start border-danger border-4">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div class="text-muted small fw-bold text-uppercase">Estoque Crítico</div>
                    <div class="bg-danger bg-opacity-10 p-2 rounded">
                        <i class="fas fa-triangle-exclamation text-danger"></i>
                    </div>
                </div>
                <h3 class="mb-0 fw-bold"><?= $stats['estoque_critico'] ?> <span class="text-muted fs-6 fw-normal">itens</span></h3>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card h-100 border-0 shadow-sm border-start border-info border-4">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div class="text-muted small fw-bold text-uppercase">O.S. Pendentes</div>
                    <div class="bg-info bg-opacity-10 p-2 rounded">
                        <i class="fas fa-screwdriver-wrench text-info"></i>
                    </div>
                </div>
                <h3 class="mb-0 fw-bold"><?= $stats['pedidos_pendentes'] ?></h3>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold"><i class="fas fa-history me-2 text-primary"></i>Vendas Recentes</h6>
                <a href="vendas.php" class="btn btn-sm btn-outline-primary fw-bold">Ver Tudo</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th class="ps-4">ID</th>
                                <th>Cliente</th>
                                <th>Data</th>
                                <th>Total</th>
                                <th>Status</th>
                                <th class="text-end pe-4">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentes_vendas as $venda): ?>
                            <tr>
                                <td class="ps-4 fw-bold text-muted">#<?= $venda['id'] ?></td>
                                <td><?= $venda['cliente_nome'] ?? 'Cliente Avulso' ?></td>
                                <td><?= formatarData($venda['data_venda']) ?></td>
                                <td class="fw-bold"><?= formatarMoeda($venda['valor_total']) ?></td>
                                <td>
                                    <?php 
                                        $statusClass = $venda['status'] == 'cancelado' ? 'danger' : 'success';
                                    ?>
                                    <span class="badge bg-<?= $statusClass ?> bg-opacity-10 text-<?= $statusClass ?> rounded-pill">
                                        <?= ucfirst($venda['status']) ?>
                                    </span>
                                </td>
                                <td class="text-end pe-4">
                                    <div class="btn-group btn-group-sm">
                                        <button class="btn btn-light border" title="Ver Detalhes">
                                            <i class="fas fa-eye text-primary"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($recentes_vendas)): ?>
                            <tr>
                                <td colspan="6" class="text-center py-5 text-muted">
                                    <i class="fas fa-folder-open fs-1 d-block mb-3 opacity-25"></i>
                                    Nenhuma venda registrada recentemente.
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white py-3">
                <h6 class="mb-0 fw-bold"><i class="fas fa-star me-2 text-warning"></i>Materiais Mais Vendidos</h6>
            </div>
            <div class="card-body p-0">
                <ul class="list-group list-group-flush">
                    <?php if (empty($top_produtos)): ?>
                        <li class="list-group-item text-center py-5 text-muted">Sem dados de giro disponíveis.</li>
                    <?php else: ?>
                        <?php foreach ($top_produtos as $prod): ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center py-3">
                            <div class="me-auto">
                                <div class="fw-bold text-dark small"><?= $prod['nome'] ?></div>
                                <div class="text-muted" style="font-size: 0.75rem;"><?= $prod['total_vendido'] ?> unidades vendidas</div>
                            </div>
                            <div class="text-end">
                                <div class="fw-bold text-primary small"><?= formatarMoeda($prod['receita']) ?></div>
                            </div>
                        </li>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </div>
</div>
