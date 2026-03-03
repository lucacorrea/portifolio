<div class="row mb-4">
    <div class="col-md-9">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold">Matriz de Curva ABC (Faturamento)</h6>
                <div class="d-flex gap-2">
                    <a href="inteligencia.php?action=recalcularABC" class="btn btn-sm btn-warning">
                        <i class="fas fa-sync me-1"></i> Recalcular ABC
                    </a>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-3">Produto</th>
                            <th class="text-center">Classificação</th>
                            <th>Status Estoque</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($dados as $d): ?>
                        <tr>
                            <td class="ps-3 fw-bold"><?= $d['nome'] ?></td>
                            <td class="text-center">
                                <span class="badge bg-<?= $d['classificacao'] == 'A' ? 'success' : ($d['classificacao'] == 'B' ? 'primary' : 'secondary') ?> py-2 px-3">
                                    Classe <?= $d['classificacao'] ?>
                                </span>
                            </td>
                            <td>
                                <small class="text-muted">Estoque Atual: <?= $d['estoque'] ?></small>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($dados)): ?>
                            <tr><td colspan="3" class="text-center py-5 text-muted small">Nenhum dado processado para este período. Clique em Recalcular.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm bg-light">
            <div class="card-body">
                <h6 class="fw-bold mb-3">O que é Curva ABC?</h6>
                <div class="mb-3">
                    <span class="badge bg-success">A</span> <b>Top 80%:</b><br>
                    <small class="text-muted">Poucos itens que geram a maior parte da receita.</small>
                </div>
                <div class="mb-3">
                    <span class="badge bg-primary">B</span> <b>Médios 15%:</b><br>
                    <small class="text-muted">Itens com importância intermediária.</small>
                </div>
                <div>
                    <span class="badge bg-secondary">C</span> <b>Baixos 5%:</b><br>
                    <small class="text-muted">Muitos itens que geram pouco faturamento.</small>
                </div>
            </div>
        </div>
    </div>
</div>
