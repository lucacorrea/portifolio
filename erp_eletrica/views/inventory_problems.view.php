<?php if (isset($_GET['msg'])): ?>
    <div class="alert alert-success alert-dismissible fade show shadow-sm border-0 mb-4" role="alert">
        <i class="fas fa-check-circle me-2"></i> <?= htmlspecialchars($_GET['msg']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<?php if (isset($_GET['error'])): ?>
    <div class="alert alert-danger alert-dismissible fade show shadow-sm border-0 mb-4" role="alert">
        <i class="fas fa-exclamation-circle me-2"></i> <?= htmlspecialchars($_GET['error']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<!-- Stats Cards -->
<div class="row g-3 mb-4 row-cols-1 row-cols-sm-2 row-cols-md-5">
    <div class="col">
        <div class="card h-100 border-0 shadow-sm">
            <div class="card-body">
                <div class="text-muted extra-small fw-bold text-uppercase mb-1">Total Registrado</div>
                <h4 class="mb-0 fw-bold"><?= $stats['total'] ?></h4>
            </div>
        </div>
    </div>
    <div class="col">
        <div class="card h-100 border-start border-warning border-4 shadow-sm">
            <div class="card-body">
                <div class="text-muted extra-small fw-bold text-uppercase mb-1 text-warning">Pendentes</div>
                <h4 class="mb-0 fw-bold"><?= $stats['pendente'] ?></h4>
            </div>
        </div>
    </div>
    <div class="col">
        <div class="card h-100 border-start border-primary border-4 shadow-sm">
            <div class="card-body">
                <div class="text-muted extra-small fw-bold text-uppercase mb-1 text-primary">Devolvidos</div>
                <h4 class="mb-0 fw-bold"><?= $stats['devolvido'] ?></h4>
            </div>
        </div>
    </div>
    <div class="col">
        <div class="card h-100 border-start border-success border-4 shadow-sm">
            <div class="card-body">
                <div class="text-muted extra-small fw-bold text-uppercase mb-1 text-success">Consertados</div>
                <h4 class="mb-0 fw-bold"><?= $stats['consertado'] ?></h4>
            </div>
        </div>
    </div>
    <div class="col">
        <div class="card h-100 border-start border-danger border-4 shadow-sm">
            <div class="card-body">
                <div class="text-muted extra-small fw-bold text-uppercase mb-1 text-danger">Descartados</div>
                <h4 class="mb-0 fw-bold"><?= $stats['descartado'] ?></h4>
            </div>
        </div>
    </div>
</div>

<!-- Filters Bar -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body py-3">
        <form method="GET" action="estoque.php" class="row g-3">
            <input type="hidden" name="action" value="problems">
            <div class="col-md-6">
                <div class="input-group">
                    <span class="input-group-text bg-white border-end-0 text-muted">
                        <i class="fas fa-search"></i>
                    </span>
                    <input type="text" name="q" class="form-control border-start-0" placeholder="Pesquisar por material ou motivo..." value="<?= htmlspecialchars($filters['q'] ?? '') ?>">
                </div>
            </div>
            <div class="col-md-3">
                <select name="status" class="form-select" onchange="this.form.submit()">
                    <option value="">Todos Status</option>
                    <?php foreach ($statusLabels as $key => $info): ?>
                        <option value="<?= $key ?>" <?= ($filters['status'] ?? '') == $key ? 'selected' : '' ?>><?= $info['label'] ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary fw-bold w-100">Filtrar</button>
                <a href="estoque.php" class="btn btn-outline-secondary fw-bold">
                    <i class="fas fa-arrow-left"></i>
                </a>
            </div>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm" style="overflow: visible !important;">
    <div class="card-body p-0" style="min-height: 450px; overflow: visible !important;">
        <div class="table-responsive" style="overflow: visible !important;">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light">
                    <tr>
                        <th class="ps-4">Data / Usuário</th>
                        <th>Produto</th>
                        <th class="text-center">Quantidade</th>
                        <th>Motivo / Descrição</th>
                        <th>Status</th>
                        <th class="text-end pe-4">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($problems)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">
                                <i class="fas fa-check-circle fa-3x mb-3 opacity-25"></i>
                                <p class="mb-0">Nenhum registro encontrado para os filtros aplicados.</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($problems as $p): ?>
                        <tr>
                            <td class="ps-4">
                                <div class="small fw-bold"><?= date('d/m/Y H:i', strtotime($p['data_registro'])) ?></div>
                                <div class="extra-small text-muted"><?= $p['usuario_nome'] ?></div>
                            </td>
                            <td>
                                <div class="fw-bold"><?= $p['produto_nome'] ?></div>
                                <div class="text-muted small">Cód: <?= $p['produto_codigo'] ?></div>
                            </td>
                            <td class="text-center">
                                <span class="badge bg-danger bg-opacity-10 text-danger fw-bold fs-6 px-3 py-2 rounded-pill">
                                    <?= formatarQuantidade($p['quantidade']) ?>
                                </span>
                            </td>
                            <td>
                                <div class="text-muted small" style="max-width: 350px; white-space: normal;"><?= nl2br(htmlspecialchars($p['motivo'])) ?></div>
                            </td>
                            <td>
                                <span class="badge bg-<?= $statusLabels[$p['status']]['class'] ?> text-uppercase px-2" style="font-size: 0.65rem;">
                                    <?= $statusLabels[$p['status']]['label'] ?>
                                </span>
                            </td>
                            <td class="text-end pe-4">
                                <div class="dropdown">
                                    <button class="btn btn-light btn-sm border shadow-sm dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                                        Mudar Status
                                    </button>
                                    <ul class="dropdown-menu shadow-lg border-0 dropdown-menu-end">
                                        <?php foreach ($statusLabels as $statusKey => $statusInfo): ?>
                                            <?php if ($statusKey !== $p['status']): ?>
                                                <li>
                                                    <a class="dropdown-item py-2" href="javascript:void(0)" onclick="updateStatus(<?= $p['id'] ?>, '<?= $statusKey ?>')">
                                                        Marcar como <span class="text-<?= $statusInfo['class'] ?> fw-bold"><?= $statusInfo['label'] ?></span>
                                                    </a>
                                                </li>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                        <li><hr class="dropdown-divider"></li>
                                        <li>
                                            <a class="dropdown-item py-2 text-danger" href="javascript:void(0)" onclick="if(confirm('Remover este registro permanentemente?')) window.location.href='estoque.php?action=delete_problem&id=<?= $p['id'] ?>'">
                                                <i class="fas fa-trash me-2"></i>Excluir Registro
                                            </a>
                                        </li>
                                    </ul>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Hidden form for status updates -->
<form id="statusUpdateForm" action="estoque.php?action=update_problem_status" method="POST" style="display: none;">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="id" id="status_id">
    <input type="hidden" name="status" id="status_value">
</form>

<script>
function updateStatus(id, status) {
    document.getElementById('status_id').value = id;
    document.getElementById('status_value').value = status;
    document.getElementById('statusUpdateForm').submit();
}
</script>
