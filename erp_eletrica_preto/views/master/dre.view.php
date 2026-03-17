<div class="card border-0 shadow-sm mb-4">
    <div class="card-body d-flex justify-content-between align-items-center">
        <h6 class="mb-0 fw-bold text-dark"><i class="fas fa-file-invoice-dollar me-2 text-primary"></i>Filtro do Exercício</h6>
        <form class="d-flex gap-2">
            <input type="hidden" name="action" value="dre">
            <select name="year" class="form-select form-select-sm shadow-sm" onchange="this.form.submit()">
                <?php for($y=date('Y'); $y>=2024; $y--): ?>
                    <option value="<?= $y ?>" <?= $year == $y ? 'selected' : '' ?>><?= $y ?></option>
                <?php endfor; ?>
            </select>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white border-0 py-3">
        <h6 class="mb-0 fw-bold text-dark">DRE Consolidado Global - Ano <?= $year ?></h6>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-bordered mb-0 align-middle">
                <thead class="bg-light text-center">
                    <tr>
                        <th class="text-start ps-4">Mês / Competência</th>
                        <th>Receita Bruta (Vendas)</th>
                        <th>Custos / Despesas (Pgto)</th>
                        <th>Resultado Líquido</th>
                        <th>Margem</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $total_rec = 0; $total_des = 0;
                    $meses = [
                        1 => 'Janeiro', 2 => 'Fevereiro', 3 => 'Março', 4 => 'Abril',
                        5 => 'Maio', 6 => 'Junho', 7 => 'Julho', 8 => 'Agosto',
                        9 => 'Setembro', 10 => 'Outubro', 11 => 'Novembro', 12 => 'Dezembro'
                    ];
                    foreach ($meses as $num => $nome): 
                        $rec = $receitas[$num] ?? 0;
                        $des = $despesas[$num] ?? 0;
                        $res = $rec - $des;
                        $margem = $rec > 0 ? ($res / $rec) * 100 : 0;
                        
                        $total_rec += $rec;
                        $total_des += $des;
                    ?>
                    <tr class="text-center">
                        <td class="text-start ps-4 fw-bold text-muted"><?= $nome ?></td>
                        <td class="text-success fw-bold">R$ <?= number_format($rec, 2, ',', '.') ?></td>
                        <td class="text-danger">R$ <?= number_format($des, 2, ',', '.') ?></td>
                        <td class="<?= $res >= 0 ? 'text-primary' : 'text-danger' ?> fw-bold bg-light">
                            R$ <?= number_format($res, 2, ',', '.') ?>
                        </td>
                        <td>
                            <span class="badge <?= $margem >= 20 ? 'bg-success' : ($margem >= 0 ? 'bg-warning' : 'bg-danger') ?> rounded-pill">
                                <?= number_format($margem, 1, ',', '.') ?>%
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot class="bg-dark text-white text-center">
                    <tr>
                        <th class="text-start ps-4">TOTAL ACUMULADO</th>
                        <th>R$ <?= number_format($total_rec, 2, ',', '.') ?></th>
                        <th>R$ <?= number_format($total_des, 2, ',', '.') ?></th>
                        <th class="bg-primary">R$ <?= number_format($total_rec - $total_des, 2, ',', '.') ?></th>
                        <th>-</th>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>
