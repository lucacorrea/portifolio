<!-- Histórico de Movimentações View -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-0"><?= $pageTitle ?></h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="estoque.php" class="text-decoration-none">Estoque</a></li>
                <li class="breadcrumb-item active" aria-current="page">Movimentações</li>
            </ol>
        </nav>
    </div>
    <a href="estoque.php" class="btn btn-light border shadow-sm fw-bold">
        <i class="fas fa-arrow-left me-2"></i>Voltar ao Estoque
    </a>
</div>

<!-- Filters -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <form method="GET" action="estoque.php" class="row g-3 align-items-end">
            <input type="hidden" name="action" value="movimentacoes">
            <div class="col-md-3">
                <label class="form-label small fw-bold text-muted">A partir de:</label>
                <input type="date" name="desde" class="form-control" value="<?= htmlspecialchars($filters['desde']) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-bold text-muted">Até:</label>
                <input type="date" name="ate" class="form-control" value="<?= htmlspecialchars($filters['ate']) ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label small fw-bold text-muted">Filtrar por Material:</label>
                <select name="produto_id" class="form-select select2">
                    <option value="">Todos os materiais...</option>
                    <?php foreach ($products as $p): ?>
                        <option value="<?= $p['id'] ?>" <?= $filters['produto_id'] == $p['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($p['nome']) ?> (<?= $p['codigo'] ?>)
                        </option>
                    <?php endforeach; ?>
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

<!-- Movements Table -->
<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light">
                    <tr>
                        <th class="ps-4">Data/Hora</th>
                        <th>Material</th>
                        <th>Tipo</th>
                        <th class="text-center">Qtd</th>
                        <th>Motivo / Observação</th>
                        <th>Usuário</th>
                        <th class="text-end pe-4">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($movements)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">
                                <i class="fas fa-history fa-3x mb-3 opacity-25"></i>
                                <p class="mb-0">Nenhuma movimentação encontrada para este período.</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                    <?php foreach ($movements as $m): ?>
                    <tr>
                        <td class="ps-4">
                            <div class="fw-bold small"><?= formatarDataHora($m['data_movimento']) ?></div>
                        </td>
                        <td>
                            <div class="fw-bold"><?= htmlspecialchars($m['produto_nome']) ?></div>
                            <div class="text-muted extra-small">Cód: <?= $m['produto_codigo'] ?></div>
                        </td>
                        <td>
                            <?php if ($m['tipo'] === 'entrada'): ?>
                                <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 rounded-pill">
                                    <i class="fas fa-arrow-up me-1"></i> Entrada
                                </span>
                            <?php elseif ($m['tipo'] === 'saida'): ?>
                                <span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 rounded-pill">
                                    <i class="fas fa-arrow-down me-1"></i> Saída
                                </span>
                            <?php else: ?>
                                <span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25 rounded-pill">
                                    <i class="fas fa-sync me-1"></i> Ajuste
                                </span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center fw-bold">
                            <?= number_format($m['quantidade'], 2, ',', '.') ?>
                        </td>
                        <td>
                            <div class="text-truncate" style="max-width: 250px;" title="<?= htmlspecialchars($m['motivo']) ?>">
                                <?= htmlspecialchars($m['motivo']) ?: '<span class="text-muted italic small">Sem observação</span>' ?>
                            </div>
                        </td>
                        <td>
                            <div class="small fw-bold text-secondary"><?= htmlspecialchars($m['usuario_nome'] ?? 'Sistema') ?></div>
                        </td>
                        <td class="text-end pe-4">
                            <button class="btn btn-outline-primary btn-sm rounded-pill px-3 fw-bold" onclick="viewDetail(<?= htmlspecialchars(json_encode($m)) ?>)">
                                <i class="fas fa-eye me-1"></i> Visualizar
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Detail Modal -->
<div class="modal fade" id="movementDetailModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-primary text-white border-0">
                <h5 class="modal-title fw-bold"><i class="fas fa-info-circle me-2"></i>Detalhes da Movimentação</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="mb-4 text-center">
                    <div id="modal_type_badge" class="mb-2"></div>
                    <h3 class="fw-bold mb-0" id="modal_qty"></h3>
                    <p class="text-muted small" id="modal_date"></p>
                </div>
                
                <div class="list-group list-group-flush border rounded overflow-hidden">
                    <div class="list-group-item d-flex justify-content-between p-3">
                        <span class="text-muted small fw-bold text-uppercase">Material</span>
                        <span class="fw-bold text-primary" id="modal_product"></span>
                    </div>
                    <div class="list-group-item d-flex justify-content-between p-3">
                        <span class="text-muted small fw-bold text-uppercase">Cód. Produto</span>
                        <span class="fw-bold" id="modal_product_code"></span>
                    </div>
                    <div class="list-group-item d-flex justify-content-between p-3">
                        <span class="text-muted small fw-bold text-uppercase">Usuário Responsável</span>
                        <span class="fw-bold" id="modal_user"></span>
                    </div>
                    <div class="list-group-item d-flex justify-content-between p-3">
                        <span class="text-muted small fw-bold text-uppercase">Referência ID</span>
                        <span class="text-muted" id="modal_ref_id"></span>
                    </div>
                    <div class="list-group-item p-3">
                        <span class="text-muted small fw-bold text-uppercase d-block mb-2">Motivo / Descrição</span>
                        <div class="p-3 bg-light rounded small" id="modal_reason"></div>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-light fw-bold px-4" data-bs-dismiss="modal">Fechar</button>
            </div>
        </div>
    </div>
</div>

<script>
function viewDetail(m) {
    const modal = new bootstrap.Modal(document.getElementById('movementDetailModal'));
    
    // Set type badge
    const badge = document.getElementById('modal_type_badge');
    if (m.tipo === 'entrada') {
        badge.innerHTML = '<span class="badge bg-success rounded-pill px-3">ENTRADA</span>';
    } else if (m.tipo === 'saida') {
        badge.innerHTML = '<span class="badge bg-danger rounded-pill px-3">SAÍDA</span>';
    } else {
        badge.innerHTML = '<span class="badge bg-info rounded-pill px-3 text-dark">AJUSTE</span>';
    }
    
    document.getElementById('modal_qty').innerText = parseFloat(m.quantidade).toLocaleString('pt-BR', {minimumFractionDigits: 2}) + ' un';
    document.getElementById('modal_date').innerText = 'Processado em ' + new Date(m.data_movimento).toLocaleString('pt-BR');
    document.getElementById('modal_product').innerText = m.produto_nome;
    document.getElementById('modal_product_code').innerText = m.produto_codigo;
    document.getElementById('modal_user').innerText = m.usuario_nome || 'Sistema';
    document.getElementById('modal_ref_id').innerText = m.referencia_id || 'N/A';
    document.getElementById('modal_reason').innerText = m.motivo || 'Nenhuma observação registrada.';
    
    modal.show();
}
</script>

<style>
.breadcrumb-item + .breadcrumb-item::before {
    content: "/";
}
.extra-small {
    font-size: 0.7rem;
}
.italic {
    font-style: italic;
}
</style>
