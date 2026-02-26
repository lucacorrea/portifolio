<!-- Page Header -->
<div class="row g-4 mb-4">
    <div class="col-md-3">
        <div class="card h-100 border-0 shadow-sm">
            <div class="card-body">
                <div class="text-muted small fw-bold text-uppercase mb-2">Total em Estoque</div>
                <h3 class="mb-0 fw-bold text-primary"><?= number_format($stats['total_itens'], 0, ',', '.') ?> <small class="fw-normal fs-6">un</small></h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card h-100 border-0 shadow-sm">
            <div class="card-body">
                <div class="text-muted small fw-bold text-uppercase mb-2">Patrimônio (Custo)</div>
                <h3 class="mb-0 fw-bold text-success"><?= formatarMoeda($stats['valor_custo']) ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card h-100 border-0 shadow-sm border-start border-danger border-4">
            <div class="card-body">
                <div class="text-muted small fw-bold text-uppercase mb-2">Alertas de Baixa</div>
                <h3 class="mb-0 fw-bold text-danger"><?= $stats['itens_criticos'] ?> <small class="fw-normal fs-6">itens</small></h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card h-100 border-0 shadow-sm border-start border-info border-4">
            <div class="card-body">
                <div class="text-muted small fw-bold text-uppercase mb-2">Giro (Este Mês)</div>
                <h3 class="mb-0 fw-bold text-info"><?= $stats['mov_mes'] ?> <small class="fw-normal fs-6">mov</small></h3>
            </div>
        </div>
    </div>
</div>

<!-- Actions Bar -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body d-flex justify-content-between align-items-center py-3">
        <div class="d-flex gap-2 w-50">
            <div class="input-group">
                <span class="input-group-text bg-white border-end-0 text-muted">
                    <i class="fas fa-search"></i>
                </span>
                <input type="text" id="productSearch" class="form-control border-start-0" placeholder="Pesquisar por nome ou código...">
            </div>
            <select class="form-select w-auto" id="filterCategory">
                <option value="">Todas Categorias</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= $cat ?>"><?= $cat ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-primary fw-bold" data-bs-toggle="modal" data-bs-target="#newProductModal">
                <i class="fas fa-plus me-2"></i>Novo Material
            </button>
            <button class="btn btn-outline-secondary fw-bold" data-bs-toggle="modal" data-bs-target="#movementModal">
                <i class="fas fa-right-left me-2"></i>Movimentar
            </button>
        </div>
    </div>
</div>

<!-- Products Table -->
<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="inventoryTable">
                <thead class="bg-light">
                    <tr>
                        <th class="ps-4">Cód. / NCM</th>
                        <th>Material Elétrico</th>
                        <th>Categoria</th>
                        <th class="text-center">Quantidade</th>
                        <th>Preços (C / V)</th>
                        <th class="text-end pe-4">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($products as $p): ?>
                    <tr class="<?= $p['quantidade'] <= $p['estoque_minimo'] ? 'table-warning bg-opacity-10' : '' ?>">
                        <td class="ps-4">
                            <div class="fw-bold text-primary small"><?= $p['codigo'] ?></div>
                            <div class="text-muted extra-small">NCM: <?= $p['ncm'] ?: '---' ?></div>
                        </td>
                        <td>
                            <div class="d-flex align-items-center">
                                <?php if ($p['imagens']): ?>
                                    <img src="public/uploads/produtos/<?= $p['imagens'] ?>" class="rounded me-3 border" width="40" height="40" style="object-fit: cover;">
                                <?php else: ?>
                                    <div class="bg-light rounded me-3 d-flex align-items-center justify-content-center border" width="40" height="40" style="min-width: 40px; min-height: 40px;">
                                        <i class="fas fa-bolt text-muted opacity-25"></i>
                                    </div>
                                <?php endif; ?>
                                <div>
                                    <div class="fw-bold"><?= $p['nome'] ?></div>
                                    <div class="text-muted small"><?= $p['unidade'] ?></div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25 rounded-pill px-2">
                                <?= $p['categoria'] ?>
                            </span>
                        </td>
                        <td class="text-center">
                            <div class="fw-bold <?= $p['quantidade'] <= $p['estoque_minimo'] ? 'text-danger' : 'text-dark' ?>">
                                <?= number_format($p['quantidade'], 2, ',', '.') ?>
                            </div>
                            <?php if ($p['quantidade'] <= $p['estoque_minimo']): ?>
                                <span class="badge bg-danger text-uppercase" style="font-size: 0.6rem;">Estoque Baixo</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="text-muted small">C: <?= formatarMoeda($p['preco_custo']) ?></div>
                            <div class="fw-bold text-success">V: <?= formatarMoeda($p['preco_venda']) ?></div>
                        </td>
                        <td class="text-end pe-4">
                            <div class="btn-group btn-group-sm">
                                <button class="btn btn-light border" onclick="editProduct(<?= htmlspecialchars(json_encode($p)) ?>)" title="Editar">
                                    <i class="fas fa-edit text-primary"></i>
                                </button>
                                <button class="btn btn-light border text-danger" title="Excluir">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
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
                    <a class="page-link" href="?page=<?= $pagination['current'] - 1 ?>" aria-label="Anterior">
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
                    <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
                </li>
                <?php endfor; ?>
                <li class="page-item <?= $pagination['current'] >= $pagination['pages'] ? 'disabled' : '' ?>">
                    <a class="page-link" href="?page=<?= $pagination['current'] + 1 ?>" aria-label="Próximo">
                        <i class="fas fa-chevron-right small"></i>
                    </a>
                </li>
            </ul>
        </nav>
    </div>
    <?php endif; ?>
</div>

<!-- Modals -->
<div class="modal fade" id="newProductModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form class="modal-content" action="estoque.php?action=save" method="POST" enctype="multipart/form-data">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold">Gestão de Material</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="id" id="edit_id">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label small fw-bold">Código Interno</label>
                        <input type="text" name="codigo" class="form-control shadow-sm" required id="edit_codigo">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-bold">NCM</label>
                        <input type="text" name="ncm" class="form-control shadow-sm" id="edit_ncm">
                    </div>
                    <div class="col-md-12">
                        <label class="form-label small fw-bold">Nome / Descrição do Material</label>
                        <input type="text" name="nome" class="form-control shadow-sm" required id="edit_nome">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-bold">Unidade</label>
                        <select name="unidade" class="form-select shadow-sm" id="edit_unidade">
                            <option value="UN">Unidade (UN)</option>
                            <option value="MT">Metro (MT)</option>
                            <option value="CX">Caixa (CX)</option>
                            <option value="PCT">Pacote (PCT)</option>
                        </select>
                    </div>
                    <div class="col-md-8">
                        <label class="form-label small fw-bold">Categoria</label>
                        <select name="categoria" class="form-select shadow-sm" id="edit_categoria">
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat ?>"><?= $cat ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold">Preço de Custo (R$)</label>
                        <input type="number" step="0.01" name="preco_custo" class="form-control shadow-sm" required id="edit_preco_custo">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold">Preço de Venda (R$)</label>
                        <input type="number" step="0.01" name="preco_venda" class="form-control shadow-sm" required id="edit_preco_venda">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold">Estoque Mínimo (Alerta)</label>
                        <input type="number" name="estoque_minimo" class="form-control shadow-sm" id="edit_estoque_minimo">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold">Foto do Produto</label>
                        <input type="file" name="foto" class="form-control shadow-sm">
                    </div>

                    <div class="col-12 mt-4 mb-2"><h6 class="fw-bold text-primary small border-bottom pb-2">Informações Fiscais (SEFAZ)</h6></div>
                    <div class="col-md-4">
                        <label class="form-label small fw-bold">CEST</label>
                        <input type="text" name="cest" id="edit_cest" class="form-control shadow-sm">
                    </div>
                    <div class="col-md-8">
                        <label class="form-label small fw-bold">Origem da Mercadoria</label>
                        <select name="origem" id="edit_origem" class="form-select shadow-sm">
                            <option value="0">0 - Nacional</option>
                            <option value="1">1 - Estrangeira (Importação Direta)</option>
                            <option value="2">2 - Estrangeira (Mercado Interno)</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold">CSOSN</label>
                        <input type="text" name="csosn" id="edit_csosn" class="form-control shadow-sm" value="102">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold">CFOP (Int.)</label>
                        <input type="text" name="cfop_interno" id="edit_cfop_interno" class="form-control shadow-sm" value="5102">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold">CFOP (Ext.)</label>
                        <input type="text" name="cfop_externo" id="edit_cfop_externo" class="form-control shadow-sm" value="6102">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold">ICMS (%)</label>
                        <input type="number" step="0.01" name="aliquota_icms" id="edit_icms" class="form-control shadow-sm" value="0.00">
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-light fw-bold" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary px-4 fw-bold">Salvar Material</button>
            </div>
        </form>
    </div>
</div>

<!-- Stock Movement Modal -->
<div class="modal fade" id="movementModal" tabindex="-1">
    <div class="modal-dialog">
        <form class="modal-content" action="estoque.php?action=move" method="POST">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold">Movimentar Inventário</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
                <div class="row g-3">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <div class="col-12">
                        <label class="form-label small fw-bold">Produto</label>
                        <select name="produto_id" class="form-select shadow-sm" required>
                            <?php foreach ($allProducts as $p): ?>
                                <option value="<?= $p['id'] ?>"><?= $p['nome'] ?> (Saldo: <?= $p['quantidade'] ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold">Quantidade</label>
                        <input type="number" step="0.01" name="quantidade" class="form-control shadow-sm" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold">Tipo</label>
                        <select name="tipo" class="form-select shadow-sm" required>
                            <option value="entrada">Entrada (+)</option>
                            <option value="saida">Saída (-)</option>
                            <option value="ajuste">Ajuste de Saldo</option>
                        </select>
                    </div>
                    <div class="col-md-12">
                        <label class="form-label small fw-bold">Lote / Rastreabilidade (Opcional)</label>
                        <input type="text" name="lote" class="form-control shadow-sm" placeholder="Ex: LOTE-2024-001">
                    </div>
                    <div class="col-12">
                        <label class="form-label small fw-bold">Motivo / Observação</label>
                        <textarea name="motivo" class="form-control shadow-sm" rows="3" required placeholder="Ex: Compra de mercadoria, Baixa para OS, etc"></textarea>
                    </div>
                </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-light fw-bold" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-success px-4 fw-bold">Processar Movimento</button>
            </div>
        </form>
    </div>
</div>

<script>
    document.getElementById('edit_preco_venda').value = product.preco_venda;
    document.getElementById('edit_estoque_minimo').value = product.estoque_minimo;
    
    // Fiscal Fields
    document.getElementById('edit_cest').value = product.cest || '';
    document.getElementById('edit_origem').value = product.origem || 0;
    document.getElementById('edit_csosn').value = product.csosn || '102';
    document.getElementById('edit_cfop_interno').value = product.cfop_interno || '5102';
    document.getElementById('edit_cfop_externo').value = product.cfop_externo || '6102';
    document.getElementById('edit_icms').value = product.aliquota_icms || 0;

    document.querySelector('#newProductModal .modal-title').innerText = 'Editar Material';
    modal.show();
}

// Client-side search logic
document.getElementById('productSearch').addEventListener('keyup', function() {
    let value = this.value.toLowerCase();
    let rows = document.querySelectorAll('#inventoryTable tbody tr');
    
    rows.forEach(row => {
        let text = row.innerText.toLowerCase();
        row.style.display = text.includes(value) ? '' : 'none';
    });
});
</script>
