<div class="card shadow-sm border-0">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <h6 class="mb-0 fw-bold text-secondary">Ordens de Serviço Ativas</h6>
        <a href="os.php?action=new" class="btn btn-sm btn-primary">
            <i class="fas fa-plus me-1"></i> Nova OS
        </a>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light">
                    <tr>
                        <th class="ps-4">OS #</th>
                        <th>Cliente</th>
                        <th>Abertura</th>
                        <th>Status</th>
                        <th>Total</th>
                        <th class="text-end pe-4">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($osList)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-4 text-muted">Nenhuma ordem de serviço pendente.</td>
                        </tr>
                    <?php endif; ?>
                    <?php foreach ($osList as $os): ?>
                    <tr>
                        <td class="ps-4 fw-bold">#<?= $os['numero_os'] ?></td>
                        <td><?= $os['cliente_nome'] ?></td>
                        <td><?= formatarData($os['created_at']) ?></td>
                        <td>
                            <span class="badge rounded-pill" style="background-color: <?= getStatusColor($os['status']) ?>15; color: <?= getStatusColor($os['status']) ?>">
                                <?= strtoupper(str_replace('_', ' ', $os['status'])) ?>
                            </span>
                        </td>
                        <td class="fw-bold"><?= formatarMoeda($os['valor_total']) ?></td>
                        <td class="text-end pe-4">
                            <a href="os.php?action=view&id=<?= $os['id'] ?>" class="btn btn-sm btn-light border">
                                <i class="fas fa-eye"></i>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
