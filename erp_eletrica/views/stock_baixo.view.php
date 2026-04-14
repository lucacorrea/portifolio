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
        <form method="GET" action="estoque_baixo.php" class="row g-3 align-items-center" id="filterForm">
            <div class="col-md-6">
                <div class="input-group">
                    <span class="input-group-text bg-white border-end-0 text-muted">
                        <i class="fas fa-search"></i>
                    </span>
                    <input type="text" name="q" id="searchInput" class="form-control border-start-0" placeholder="Pesquisar por código ou nome..." value="<?= htmlspecialchars($filters['q']) ?>" autocomplete="off">
                </div>
            </div>
            <div class="col-md-3">
                <select name="categoria" class="form-select" onchange="this.form.submit()">
                    <option value="">Todas Categorias</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat ?>" <?= $filters['categoria'] == $cat ? 'selected' : '' ?>><?= $cat ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <select name="status" class="form-select" onchange="this.form.submit()">
                    <option value="">Todos Status</option>
                    <option value="CRITICO" <?= $filters['status'] == 'CRITICO' ? 'selected' : '' ?>>Crítico</option>
                    <option value="BAIXO" <?= $filters['status'] == 'BAIXO' ? 'selected' : '' ?>>Baixo</option>
                    <option value="OK" <?= $filters['status'] == 'OK' ? 'selected' : '' ?>>OK</option>
                </select>
            </div>
        </form>
    </div>
</div>

<!-- Products Table -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white border-0 py-3 d-flex justify-content-between align-items-center">
        <h5 class="mb-0 fw-bold text-dark"><i class="fas fa-list me-2 text-primary"></i>Listagem de Alertas</h5>
        <button class="btn btn-outline-success btn-sm fw-bold" onclick="window.location.href='estoque_baixo.php?export=excel&q=<?= urlencode($filters['q']) ?>&categoria=<?= urlencode($filters['categoria']) ?>&status=<?= urlencode($filters['status']) ?>'">
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
                        <th class="text-center text-primary border-start border-end" style="background-color: rgba(43, 76, 125, 0.03);">Sugerido Compra</th>
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
                        <td class="text-center border-start border-end">
                            <?php if ($sugerido > 0): ?>
                                <span class="fw-bold text-primary">+ <?= number_format($sugerido, 0, ',', '.') ?></span>
                            <?php else: ?>
                                <span class="text-muted small">Suprido</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end pe-4">
                            <a href="estoque.php?q=<?= $p['codigo'] ?>" class="btn btn-sm btn-light border shadow-sm" title="Ver no Estoque">
                                <i class="fas fa-eye text-primary"></i>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <!-- Pagination -->
    <?php if (isset($pagination) && $pagination['pages'] > 1): ?>
    <div class="card-footer bg-white border-top py-3">
        <nav aria-label="Navegação de estoque">
            <ul class="pagination pagination-sm mb-0 justify-content-center">
                <li class="page-item <?= $pagination['current'] <= 1 ? 'disabled' : '' ?>">
                    <a class="page-link" href="?page=<?= $pagination['current'] - 1 ?>&q=<?= urlencode($filters['q']) ?>&categoria=<?= urlencode($filters['categoria']) ?>&status=<?= urlencode($filters['status']) ?>" aria-label="Anterior">
                        <i class="fas fa-chevron-left small"></i>
                    </a>
                </li>
                <?php 
                $start = max(1, $pagination['current'] - 2);
                $end = min($pagination['pages'], $start + 4);
                if ($end - $start < 4) $start = max(1, $end - 4);
                for($i = $start; $i <= $end; $i++): 
                ?>
                <li class="page-item <?= $i == $pagination['current'] ? 'active' : '' ?>">
                    <a class="page-link" href="?page=<?= $i ?>&q=<?= urlencode($filters['q']) ?>&categoria=<?= urlencode($filters['categoria']) ?>&status=<?= urlencode($filters['status']) ?>"><?= $i ?></a>
                </li>
                <?php endfor; ?>
                <li class="page-item <?= $pagination['current'] >= $pagination['pages'] ? 'disabled' : '' ?>">
                    <a class="page-link" href="?page=<?= $pagination['current'] + 1 ?>&q=<?= urlencode($filters['q']) ?>&categoria=<?= urlencode($filters['categoria']) ?>&status=<?= urlencode($filters['status']) ?>" aria-label="Próximo">
                        <i class="fas fa-chevron-right small"></i>
                    </a>
                </li>
            </ul>
        </nav>
    </div>
    <?php endif; ?>
</div>

<script>
function editProduct(product) {
    const modal = new bootstrap.Modal(document.getElementById('newProductModal'));
    document.getElementById('edit_id').value = product.id;
    document.getElementById('edit_codigo').value = product.codigo;
    document.getElementById('edit_ncm').value = product.ncm || '';
    document.getElementById('edit_nome').value = product.nome;
    document.getElementById('edit_unidade').value = product.unidade;
    document.getElementById('edit_categoria').value = product.categoria;
    document.getElementById('edit_tipo_produto').value = product.tipo_produto || 'simples';
    document.getElementById('edit_quantidade').value = product.quantidade || 0;
    
    document.getElementById('edit_peso').value = product.peso || '';
    document.getElementById('edit_dimensoes').value = product.dimensoes || '';
    document.getElementById('edit_descricao').value = product.descricao || '';
    
    document.getElementById('edit_preco_custo').value = product.preco_custo;
    document.getElementById('edit_preco_venda').value = product.preco_venda;
    document.getElementById('edit_preco_venda_2').value = product.preco_venda_2 || '';
    document.getElementById('edit_preco_venda_3').value = product.preco_venda_3 || '';
    document.getElementById('edit_preco_venda_atacado').value = product.preco_venda_atacado || '';
    document.getElementById('edit_estoque_minimo').value = product.estoque_minimo;
    
    // Fiscal Fields
    document.getElementById('edit_cean').value = product.cean || '';
    document.getElementById('edit_cest').value = product.cest || '';
    document.getElementById('edit_origem').value = product.origem || 0;
    document.getElementById('edit_csosn').value = product.csosn || '102';
    document.getElementById('edit_cfop_interno').value = product.cfop_interno || '5102';
    document.getElementById('edit_cfop_externo').value = product.cfop_externo || '6102';
    document.getElementById('edit_icms').value = product.aliquota_icms || 0;

    document.querySelector('#newProductModal .modal-title').innerText = 'Editar Material';
    modal.show();
}

function deleteProduct(id) {
    if (confirm('Deseja realmente excluir este material do estoque?')) {
        window.location.href = 'estoque.php?action=delete&id=' + id;
    }
}

function openMovement(productId, type) {
    const modal = new bootstrap.Modal(document.getElementById('movementModal'));
    const form = document.querySelector('#movementModal form');
    form.querySelector('select[name="produto_id"]').value = productId;
    form.querySelector('select[name="tipo"]').value = type;
    modal.show();
}

// Auxiliary Functions for New Product Modal
function updateFormVisibility() {
    const unidade = document.getElementById('edit_unidade').value;
    const liquidUnits = ['KG', 'GR', 'L', 'ML'];
    const hideDimensions = liquidUnits.includes(unidade);
    const divPeso = document.getElementById('div_peso');
    const divDimensoes = document.getElementById('div_dimensoes');
    if (divPeso) divPeso.style.display = hideDimensions ? 'none' : '';
    if (divDimensoes) divDimensoes.style.display = hideDimensions ? 'none' : '';
}

let ncmDebounceTimer;
function searchNcmInline(term) {
    const dropdown = document.getElementById('ncmDropdown');
    if (term.length < 3) { dropdown.style.display = 'none'; return; }
    clearTimeout(ncmDebounceTimer);
    ncmDebounceTimer = setTimeout(() => {
        fetch(`api/ncm_search.php?search=${encodeURIComponent(term)}`)
            .then(r => r.json()).then(data => {
                dropdown.innerHTML = '';
                if (data && data.length > 0) {
                    data.forEach(item => {
                        dropdown.innerHTML += `<li><a class="dropdown-item py-2" href="javascript:void(0)" onclick="selectNcm('${item.codigo.replace(/\./g, '')}')">
                            <div class="fw-bold text-primary small">${item.codigo}</div>
                            <div class="text-truncate text-muted small">${item.descricao}</div>
                        </a></li>`;
                    });
                }
                dropdown.style.display = 'block';
            });
    }, 500);
}

function selectNcm(code) {
    document.getElementById('edit_ncm').value = code;
    document.getElementById('ncmDropdown').style.display = 'none';
}

// Debounced Auto-submit for search
let searchTimer;
document.getElementById('searchInput').addEventListener('input', function() {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        document.getElementById('filterForm').submit();
    }, 600);
});

// Maintain focus on search input after reload if it was active
if (window.location.search.includes('q=')) {
    const input = document.getElementById('searchInput');
    input.focus();
    const val = input.value;
    input.value = '';
    input.value = val;
}
</script>

<style>
.bg-danger-soft { background-color: rgba(220, 53, 69, 0.1); }
.bg-warning-soft { background-color: rgba(255, 193, 7, 0.1); }
.bg-success-soft { background-color: rgba(25, 135, 84, 0.1); }
</style>
