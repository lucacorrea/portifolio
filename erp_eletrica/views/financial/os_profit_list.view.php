<div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <h6 class="mb-0 fw-bold text-secondary"><i class="fas fa-chart-line me-2"></i>Rentabilidade por Ordem de Serviço</h6>
        <button class="btn btn-sm btn-outline-primary fw-bold" onclick="window.print()"><i class="fas fa-file-pdf me-1"></i>Exportar Relatório</button>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light">
                    <tr>
                        <th class="ps-4">Nº OS</th>
                        <th class="text-end">Receita (Venda)</th>
                        <th class="text-end">Custo (Materiais)</th>
                        <th class="text-end">Lucro Bruto</th>
                        <th class="text-center">Margem</th>
                        <th class="text-end pe-4">Status / Eficiência</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($reports as $r): ?>
                    <tr>
                        <td class="ps-4 fw-bold">#<?= $r['numero'] ?></td>
                        <td class="text-end"><?= formatarMoeda($r['valor_venda']) ?></td>
                        <td class="text-end text-danger"><?= formatarMoeda($r['custo_materiais']) ?></td>
                        <td class="text-end fw-bold text-success"><?= formatarMoeda($r['lucro']) ?></td>
                        <td class="text-center">
                            <span class="badge bg-primary bg-opacity-10 text-primary rounded-pill px-3">
                                <?= number_format($r['margem'], 1) ?>%
                            </span>
                        </td>
                        <td class="text-end pe-4">
                            <?php if($r['margem'] >= 40): ?>
                                <span class="text-success small fw-bold"><i class="fas fa-star me-1"></i> Alta Eficiência</span>
                            <?php elseif($r['margem'] >= 20): ?>
                                <span class="text-info small fw-bold"><i class="fas fa-check me-1"></i> Saudável</span>
                            <?php else: ?>
                                <span class="text-warning small fw-bold"><i class="fas fa-triangle-exclamation me-1"></i> Margem Baixa</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
