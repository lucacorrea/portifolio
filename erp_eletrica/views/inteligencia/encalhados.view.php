<div class="alert alert-info border-0 shadow-sm d-flex align-items-center">
    <i class="fas fa-info-circle fs-4 me-3"></i>
    <div>
        <h6 class="mb-0 fw-bold">Definição de Encalhados</h6>
        <p class="mb-0 small">Produtos com estoque positivo mas que não tiveram nenhuma venda registrada nos últimos <b><?= $dias ?> dias</b>.</p>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-3 d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
        <h6 class="mb-0 fw-bold"><i class="fas fa-history me-2 text-primary"></i>Lista de Produtos sem Giro</h6>
        <form class="w-100 w-md-auto">
            <input type="hidden" name="action" value="encalhados">
            <div class="input-group input-group-sm">
                <span class="input-group-text bg-light">Período</span>
                <select name="dias" class="form-select fw-bold" onchange="this.form.submit()">
                    <option value="30" <?= $dias == 30 ? 'selected' : '' ?>>30 Dias</option>
                    <option value="60" <?= $dias == 60 ? 'selected' : '' ?>>60 Dias</option>
                    <option value="90" <?= $dias == 90 ? 'selected' : '' ?>>90 Dias</option>
                </select>
            </div>
        </form>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="bg-light">
                <tr>
                    <th class="ps-3">Produto</th>
                    <th class="text-center">Estoque Atual</th>
                    <th>Última Venda</th>
                    <th class="text-end pe-3">Ação Comercial</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($produtos as $p): ?>
                <tr>
                    <td class="ps-3 fw-bold"><?= $p['nome'] ?></td>
                    <td class="text-center">
                        <span class="badge bg-secondary rounded-pill"><?= $p['estoque'] ?></span>
                    </td>
                    <td class="text-muted small">
                        <?= $p['ultima_venda'] ? date('d/m/Y', strtotime($p['ultima_venda'])) : 'Sem vendas registradas' ?>
                    </td>
                    <td class="text-end pe-3">
                        <button class="btn btn-sm btn-outline-danger" title="Criar Promoção">
                            <i class="fas fa-tag"></i> Desconto
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($produtos)): ?>
                    <tr><td colspan="4" class="text-center py-5 text-muted">Parabéns! Nenhum produto está encalhado no período selecionado.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
