<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <h6 class="mb-0 fw-bold text-secondary"><i class="fas fa-file-invoice-dollar me-2"></i>Demonstrativo de Resultado (DRE)</h6>
        <div class="d-flex gap-2">
            <select class="form-select form-select-sm" onchange="location.href='financeiro.php?action=dre&month='+this.value+'&year=<?= $year ?>'">
                <?php for($m=1; $m<=12; $m++): ?>
                    <option value="<?= sprintf('%02d', $m) ?>" <?= $m == $month ? 'selected' : '' ?>><?= date('F', mktime(0,0,0,$m,1)) ?></option>
                <?php endfor; ?>
            </select>
            <button class="btn btn-sm btn-outline-secondary" onclick="window.print()"><i class="fas fa-print me-1"></i>Imprimir</button>
        </div>
    </div>
    <div class="card-body p-4">
        <div class="table-responsive">
            <table class="table table-borderless align-middle">
                <tbody>
                    <tr class="border-bottom">
                        <td class="py-3 fw-bold fs-5">(=) RECEITA BRUTA OPERACIONAL</td>
                        <td class="py-3 text-end fw-bold fs-5 text-primary"><?= formatarMoeda($dre['receita_bruta']) ?></td>
                    </tr>
                    <tr>
                        <td class="py-2 ps-4 text-muted">(-) Deduções e Impostos (Simulado 6%)</td>
                        <td class="py-2 text-end text-muted"><?= formatarMoeda($dre['receita_bruta'] * 0.06) ?></td>
                    </tr>
                    <tr class="border-bottom">
                        <td class="py-2 ps-4 fw-bold">(=) RECEITA LÍQUIDA</td>
                        <td class="py-2 text-end fw-bold"><?= formatarMoeda($dre['receita_bruta'] * 0.94) ?></td>
                    </tr>
                    
                    <tr class="bg-light bg-opacity-50">
                        <td class="py-3 ps-4 text-danger">(-) CUSTO DE MERCADORIA VENDIDA (CMV)</td>
                        <td class="py-3 text-end text-danger"><?= formatarMoeda($dre['cmv']) ?></td>
                    </tr>
                    
                    <tr class="border-bottom border-top bg-light">
                        <td class="py-3 fw-bold fs-5">(=) LUCRO BRUTO</td>
                        <td class="py-3 text-end fw-bold fs-5"><?= formatarMoeda($dre['lucro_bruto']) ?></td>
                    </tr>

                    <tr>
                        <td class="py-2 ps-4 text-muted">(-) Despesas Administrativas</td>
                        <td class="py-2 text-end text-muted"><?= formatarMoeda($dre['despesas']) ?></td>
                    </tr>
                    
                    <tr class="border-top border-primary border-2 mt-3">
                        <td class="py-4 fw-bold fs-4">(=) RESULTADO LÍQUIDO DO PERÍODO</td>
                        <?php $color = $dre['resultado_liquido'] >= 0 ? 'success' : 'danger'; ?>
                        <td class="py-4 text-end fw-bold fs-4 text-<?= $color ?>"><?= formatarMoeda($dre['resultado_liquido']) ?></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="row g-4 mt-2">
            <div class="col-md-4">
                <div class="p-3 rounded bg-primary bg-opacity-10 border border-primary border-opacity-25 text-center">
                    <div class="small fw-bold text-primary text-uppercase mb-1">Margem Líquida</div>
                    <div class="h3 mb-0 fw-bold"><?= number_format($dre['margem_liquida'], 1) ?>%</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="p-3 rounded bg-info bg-opacity-10 border border-info border-opacity-25 text-center">
                    <div class="small fw-bold text-info text-uppercase mb-1">Ponto de Equilíbrio</div>
                    <div class="h3 mb-0 fw-bold">R$ <?= number_format($dre['despesas'] / 0.3, 2, ',', '.') ?></div>
                    <div class="extra-small text-muted">Considerando margem de 30%</div>
                </div>
            </div>
        </div>
    </div>
</div>
