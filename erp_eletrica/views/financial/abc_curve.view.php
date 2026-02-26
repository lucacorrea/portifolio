<div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-3">
        <h6 class="mb-0 fw-bold text-secondary"><i class="fas fa-chart-line me-2"></i>Curva ABC de Produtos (Por Faturamento)</h6>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light">
                    <tr>
                        <th class="ps-4">Classe</th>
                        <th>Produto</th>
                        <th class="text-end">Receita Total</th>
                        <th class="text-center">% Vendas</th>
                        <th class="text-center">% Acumulada</th>
                        <th class="text-end pe-4">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($results as $r): ?>
                    <tr>
                        <td class="ps-4">
                            <span class="badge bg-<?= $r['class'] == 'A' ? 'primary' : ($r['class'] == 'B' ? 'info' : 'secondary') ?> rounded-circle" style="width: 25px; height: 25px; display: inline-flex; align-items: center; justify-content: center;">
                                <?= $r['class'] ?>
                            </span>
                        </td>
                        <td class="fw-bold"><?= $r['nome'] ?></td>
                        <td class="text-end fw-bold"><?= formatarMoeda($r['total_revenue']) ?></td>
                        <td class="text-center"><?= number_format($r['percentage'], 1) ?>%</td>
                        <td class="text-center">
                            <div class="progress" style="height: 6px; width: 80px; margin: 0 auto;">
                                <div class="progress-bar" role="progressbar" style="width: <?= $r['cumulative'] ?>%"></div>
                            </div>
                            <small class="text-muted extra-small"><?= number_format($r['cumulative'], 1) ?>%</small>
                        </td>
                        <td class="text-end pe-4">
                            <?php if($r['class'] == 'A'): ?>
                                <span class="text-primary small fw-bold">Item Crítico (80%)</span>
                            <?php elseif($r['class'] == 'B'): ?>
                                <span class="text-info small fw-bold">Intermediário</span>
                            <?php else: ?>
                                <span class="text-muted small">Baixo Giro</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="alert alert-info mt-4 border-0 shadow-sm">
    <div class="d-flex">
        <i class="fas fa-info-circle fs-4 me-3"></i>
        <div>
            <h6 class="fw-bold mb-1">O que é a Curva ABC?</h6>
            <p class="mb-0 small">
                <strong>Classe A:</strong> 20% dos produtos que geram 80% do seu faturamento. Foco total em não deixar faltar em estoque.<br>
                <strong>Classe B:</strong> 30% dos produtos que geram 15% do seu faturamento.<br>
                <strong>Classe C:</strong> 50% dos produtos que geram apenas 5% do faturamento. Avalie reduzir o estoque destes itens.
            </p>
        </div>
    </div>
</div>
