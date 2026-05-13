<div class="card border-0 shadow-sm mb-4">
    <div class="card-body d-flex justify-content-between align-items-center py-3">
        <h5 class="mb-0 fw-bold text-secondary">Controle de Produtos com Problema / Defeito</h5>
        <a href="estoque.php" class="btn btn-outline-secondary btn-sm fw-bold">
            <i class="fas fa-arrow-left me-2"></i>Voltar ao Estoque
        </a>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
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
                                <p class="mb-0">Nenhum produto com problema registrado.</p>
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
                                <div class="text-muted small" style="max-width: 250px;"><?= nl2br(htmlspecialchars($p['motivo'])) ?></div>
                            </td>
                            <td>
                                <span class="badge bg-<?= $statusLabels[$p['status']]['class'] ?> text-uppercase" style="font-size: 0.7rem;">
                                    <?= $statusLabels[$p['status']]['label'] ?>
                                </span>
                            </td>
                            <td class="text-end pe-4">
                                <div class="btn-group">
                                    <button class="btn btn-light btn-sm border shadow-sm dropdown-toggle" data-bs-toggle="dropdown">
                                        Mudar Status
                                    </button>
                                    <ul class="dropdown-menu shadow-lg border-0">
                                        <?php foreach ($statusLabels as $statusKey => $statusInfo): ?>
                                            <?php if ($statusKey !== $p['status']): ?>
                                                <li>
                                                    <form action="estoque.php?action=update_problem_status" method="POST">
                                                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                                        <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                                        <input type="hidden" name="status" value="<?= $statusKey ?>">
                                                        <button type="submit" class="dropdown-item py-2">
                                                            Marcar como <span class="text-<?= $statusInfo['class'] ?> fw-bold"><?= $statusInfo['label'] ?></span>
                                                        </button>
                                                    </form>
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
