<div class="row g-4 mb-4">
    <!-- Consolidated KPI Cards -->
    <div class="col-md-3">
        <div class="card border-0 shadow-sm bg-primary text-white">
            <div class="card-body">
                <h6 class="text-white-50 small text-uppercase fw-bold">Unidades Ativas</h6>
                <h3 class="fw-bold mb-0"><?= count($branches) ?></h3>
                <div class="mt-2 small"><i class="fas fa-check-circle me-1"></i>Todas operacionais</div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h6 class="text-muted small text-uppercase fw-bold">Vendas Globais (Recent)</h6>
                <h3 class="fw-bold mb-0"><?= count($recentSales) ?></h3>
                <div class="text-success small"><i class="fas fa-sync-alt fa-spin me-1"></i>Atualizado agora</div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Branch Ranking Table -->
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0 py-3">
                <h6 class="mb-0 fw-bold text-dark"><i class="fas fa-trophy text-warning me-2"></i>Ranking de Faturamento por Unidade</h6>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th class="ps-4">Pos.</th>
                                <th>Unidade / Filial</th>
                                <th>Volume de Vendas</th>
                                <th class="text-end pe-4">Faturamento (R$)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($billingByBranch as $index => $row): ?>
                            <tr>
                                <td class="ps-4 fw-bold">#<?= $index + 1 ?></td>
                                <td>
                                    <div class="fw-bold text-dark"><?= $row['nome'] ?></div>
                                    <div class="text-muted extra-small">Unidade Operacional</div>
                                </td>
                                <td>-</td>
                                <td class="text-end pe-4 fw-bold text-success">
                                    R$ <?= number_format($row['total'], 2, ',', '.') ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Stats -->
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white border-0 py-3">
                <h6 class="mb-0 fw-bold text-dark">Ações Estratégicas</h6>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <a href="filiais.php" class="btn btn-light border fw-bold text-start">
                        <i class="fas fa-plus me-2 text-primary"></i> Expandir Operação (Nova Unidade)
                    </a>
                    <a href="usuarios.php" class="btn btn-light border fw-bold text-start">
                        <i class="fas fa-users-cog me-2 text-primary"></i> Gerenciar Master Admins
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
