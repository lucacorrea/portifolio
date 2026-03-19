<!-- Page Header -->
<div class="row g-4 mb-4">
    <div class="col-md-4">
        <div class="card h-100 border-0 shadow-sm border-start border-danger border-4">
            <div class="card-body">
                <div class="text-muted small fw-bold text-uppercase mb-2">Estoque Crítico</div>
                <div class="d-flex align-items-center">
                    <h3 class="mb-0 fw-bold text-danger"><?= $stats['critical'] ?></h3>
                    <span class="ms-2 badge bg-danger-soft text-danger small">Abaixo do Mínimo</span>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card h-100 border-0 shadow-sm border-start border-warning border-4">
            <div class="card-body">
                <div class="text-muted small fw-bold text-uppercase mb-2">Estoque Baixo</div>
                <div class="d-flex align-items-center">
                    <h3 class="mb-0 fw-bold text-warning"><?= $stats['low'] ?></h3>
                    <span class="ms-2 badge bg-warning-soft text-warning small">Alerta de Reposição</span>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card h-100 border-0 shadow-sm border-start border-success border-4">
            <div class="card-body">
                <div class="text-muted small fw-bold text-uppercase mb-2">Estoque OK</div>
                <div class="d-flex align-items-center">
                    <h3 class="mb-0 fw-bold text-success"><?= $stats['ok'] ?></h3>
                    <span class="ms-2 badge bg-success-soft text-success small">Nível Seguro</span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filters Bar -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body py-3">
        <form method="GET" action="estoque_baixo.php" class="row g-3 align-items-center">
            <div class="col-md-4">
                <div class="input-group">
                    <span class="input-group-text bg-white border-end-0 text-muted">
                        <i class="fas fa-search"></i>
                    </span>
                    <input type="text" name="q" class="form-control border-start-0" placeholder="Código ou nome..." value="<?= htmlspecialchars($filters['q']) ?>">
                </div>
            </div>
            <div class="col-md-3">
                <select name="categoria" class="form-select">
                    <option value="">Todas Categorias</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat ?>" <?= $filters['categoria'] == $cat ? 'selected' : '' ?>><?= $cat ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <select name="status" class="form-select">
                    <option value="">Todos Status</option>
                    <option value="CRITICO" <?= $filters['status'] == 'CRITICO' ? 'selected' : '' ?>>Crítico</option>
                    <option value="BAIXO" <?= $filters['status'] == 'BAIXO' ? 'selected' : '' ?>>Baixo</option>
                    <option value="OK" <?= $filters['status'] == 'OK' ? 'selected' : '' ?>>OK</option>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100 fw-bold">
                    <i class="fas fa-filter me-2"></i>Filtrar
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Products Table -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white border-0 py-3 d-flex justify-content-between align-items-center">
        <h5 class="mb-0 fw-bold text-dark"><i class="fas fa-list me-2 text-primary"></i>Listagem de Alertas</h5>
        <button class="btn btn-outline-success btn-sm fw-bold" onclick="exportToExcel()">
            <i class="fas fa-file-excel me-1"></i> Exportar
        </button>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="lowStockTable">
                <thead class="bg-light">
                    <tr>
                        <th class="ps-4">Cód / Material</th>
                        <th>Categoria</th>
                        <th class="text-center">Quantidade</th>
                        <th class="text-center">Mínimo</th>
                        <th class="text-center">Status</th>
                        <th class="text-center text-primary bg-primary bg-opacity-10">Sugerido Compra</th>
                        <th class="text-end pe-4">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($products)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">
                                <i class="fas fa-box-open fa-3x mb-3 opacity-25"></i>
                                <p class="mb-0">Nenhum item encontrado com os filtros aplicados.</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                    <?php foreach ($products as $p): ?>
                    <?php 
                        $statusClass = 'success';
                        $statusText = 'OK';
                        if ($p['quantidade'] <= $p['estoque_minimo'] && $p['estoque_minimo'] > 0) {
                            $statusClass = 'danger';
                            $statusText = 'CRÍTICO';
                        } elseif ($p['quantidade'] <= ($p['estoque_minimo'] * 1.5) && $p['estoque_minimo'] > 0) {
                            $statusClass = 'warning';
                            $statusText = 'BAIXO';
                        }
                        
                        $sugerido = max(0, ($p['estoque_minimo'] * 1.5) - $p['quantidade']);
                    ?>
                    <tr>
                        <td class="ps-4">
                            <div class="fw-bold text-primary small"><?= $p['codigo'] ?></div>
                            <div class="fw-bold"><?= $p['nome'] ?></div>
                        </td>
                        <td>
                            <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25 rounded-pill px-2">
                                <?= $p['categoria'] ?>
                            </span>
                        </td>
                        <td class="text-center fw-bold">
                            <?= number_format($p['quantidade'], 0, ',', '.') ?> <small class="text-muted fw-normal"><?= $p['unidade'] ?></small>
                        </td>
                        <td class="text-center text-muted small">
                            <?= number_format($p['estoque_minimo'], 0, ',', '.') ?>
                        </td>
                        <td class="text-center">
                            <span class="badge bg-<?= $statusClass ?> text-uppercase" style="font-size: 0.65rem; padding: 0.4em 0.8em;"><?= $statusText ?></span>
                        </td>
                        <td class="text-center bg-primary bg-opacity-10">
                            <?php if ($sugerido > 0): ?>
                                <span class="fw-bold text-primary">+ <?= number_format($sugerido, 0, ',', '.') ?></span>
                            <?php else: ?>
                                <span class="text-muted small">Suprido</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end pe-4">
                            <a href="estoque.php?q=<?= $p['codigo'] ?>" class="btn btn-sm btn-light border" title="Ver detalhes">
                                <i class="fas fa-eye text-primary"></i>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function exportToExcel() {
    // Simple table to CSV for now, matching the erp_eletrica style
    let table = document.getElementById("lowStockTable");
    let rows = table.querySelectorAll("tr");
    let csv = [];
    for (let i = 0; i < rows.length; i++) {
        let row = [], cols = rows[i].querySelectorAll("td, th");
        for (let j = 0; j < cols.length - 1; j++) {
            row.push('"' + cols[j].innerText.trim() + '"');
        }
        csv.push(row.join(","));
    }
    let csvContent = "data:text/csv;charset=utf-8,\uFEFF" + csv.join("\n");
    let encodedUri = encodeURI(csvContent);
    let link = document.createElement("a");
    link.setAttribute("href", encodedUri);
    link.setAttribute("download", "estoque_baixo_" + new Date().toISOString().slice(0,10) + ".csv");
    document.body.appendChild(link);
    link.click();
}
</script>

<style>
.bg-danger-soft { background-color: rgba(220, 53, 69, 0.1); }
.bg-warning-soft { background-color: rgba(255, 193, 7, 0.1); }
.bg-success-soft { background-color: rgba(25, 135, 84, 0.1); }
</style>
