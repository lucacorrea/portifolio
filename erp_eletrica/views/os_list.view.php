<!-- Actions Bar -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body d-flex justify-content-between align-items-center py-3">
        <h6 class="mb-0 fw-bold text-secondary"><i class="fas fa-file-invoice me-2"></i>Ordens de Serviço Ativas</h6>
        <button class="btn btn-primary fw-bold" onclick="window.location.href='os.php?action=create'">
            <i class="fas fa-plus me-2"></i>Abrir Nova OS
        </button>
    </div>
</div>

<!-- OS Table -->
<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light">
                    <tr>
                        <th class="ps-4">Número OS</th>
                        <th>Cliente / Solicitante</th>
                        <th>Abertura</th>
                        <th>Status Atual</th>
                        <th class="text-end">Valor Estimado</th>
                        <th class="text-end pe-4">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orders as $o): ?>
                    <tr>
                        <td class="ps-4">
                            <span class="fw-bold text-primary">#<?= $o['numero_os'] ?></span>
                        </td>
                        <td>
                            <div class="fw-bold text-dark small"><?= $o['cliente_nome'] ?></div>
                            <div class="text-muted extra-small">ID Cliente: #<?= $o['cliente_id'] ?? '---' ?></div>
                        </td>
                        <td class="small text-muted"><?= formatarData($o['data_abertura']) ?></td>
                        <td>
                            <?php 
                                $statusMap = [
                                    'pendente' => 'warning',
                                    'em_execucao' => 'primary',
                                    'concluido' => 'success',
                                    'cancelado' => 'danger',
                                    'aguardando_pecas' => 'info'
                                ];
                                $color = $statusMap[$o['status']] ?? 'secondary';
                            ?>
                            <span class="badge bg-<?= $color ?> bg-opacity-10 text-<?= $color ?> border border-<?= $color ?> border-opacity-25 rounded-pill px-3">
                                <?= strtoupper(str_replace('_', ' ', $o['status'])) ?>
                            </span>
                        </td>
                        <td class="text-end fw-bold text-dark"><?= formatarMoeda($o['valor_total']) ?></td>
                        <td class="text-end pe-4">
                            <a href="os.php?action=view&id=<?= $o['id'] ?>" class="btn btn-sm btn-light border" title="Ver Detalhes">
                                <i class="fas fa-eye text-primary"></i>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if(empty($orders)): ?>
                    <tr>
                        <td colspan="6" class="text-center py-5 text-muted">
                            <i class="fas fa-clipboard-list fs-1 d-block mb-3 opacity-25"></i>
                            Nenhuma ordem de serviço pendente.
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
