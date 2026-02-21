<!-- Page Header / Actions -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body d-flex justify-content-between align-items-center py-3">
        <h6 class="mb-0 fw-bold text-secondary"><i class="fas fa-network-wired me-2"></i>Unidades Operacionais</h6>
        <button class="btn btn-primary fw-bold" onclick="alert('Funcionalidade de Nova Filial em desenvolvimento')">
            <i class="fas fa-plus-circle me-2"></i>Expandir Operação
        </button>
    </div>
</div>

<!-- Branches Table -->
<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light">
                    <tr>
                        <th class="ps-4" width="100">Cód.</th>
                        <th>Nome da Unidade</th>
                        <th>CNPJ / Identificação</th>
                        <th>Tipo / Status</th>
                        <th class="text-end pe-4">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($branches as $b): ?>
                    <tr>
                        <td class="ps-4 small fw-bold text-muted">#<?= str_pad($b['id'], 3, '0', STR_PAD_LEFT) ?></td>
                        <td>
                            <div class="d-flex align-items-center">
                                <div class="bg-primary bg-opacity-10 text-primary rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 35px; height: 35px;">
                                    <i class="fas fa-building"></i>
                                </div>
                                <div class="fw-bold text-dark"><?= $b['nome'] ?></div>
                            </div>
                        </td>
                        <td class="small fw-bold text-muted"><?= $b['cnpj'] ?? '---' ?></td>
                        <td>
                            <?php if ($b['principal']): ?>
                                <span class="badge bg-success bg-opacity-10 text-success rounded-pill px-3 border border-success border-opacity-25">SEDE MATRIZ</span>
                            <?php else: ?>
                                <span class="badge bg-secondary bg-opacity-10 text-secondary rounded-pill px-3 border border-secondary border-opacity-25">FILIAL ATIVA</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end pe-4">
                            <div class="btn-group btn-group-sm">
                                <button class="btn btn-light border" title="Configurar Unidade">
                                    <i class="fas fa-cog text-primary"></i>
                                </button>
                                <button class="btn btn-light border text-danger" title="Suspender Atividades">
                                    <i class="fas fa-pause-circle"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
