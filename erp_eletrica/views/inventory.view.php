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
        <form method="GET" action="estoque.php" class="d-flex gap-2 w-50" id="inventoryFilterForm">
            <div class="input-group">
                <span class="input-group-text bg-white border-end-0 text-muted">
                    <i class="fas fa-search"></i>
                </span>
                <input type="text" name="q" id="productSearch" class="form-control border-start-0" placeholder="Pesquisar por nome ou código..." value="<?= htmlspecialchars($filters['q']) ?>" autocomplete="off">
            </div>
            <select name="categoria" class="form-select w-auto" id="filterCategory" onchange="this.form.submit()">
                <option value="">Todas Categorias</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= $cat ?>" <?= $filters['categoria'] == $cat ? 'selected' : '' ?>><?= $cat ?></option>
                <?php endforeach; ?>
            </select>
        </form>
        <div class="d-flex gap-2">
            <?php if (!in_array($_SESSION['usuario_nivel'] ?? '', ['vendedor', 'gerente'])): ?>
            <button class="btn btn-primary fw-bold" data-bs-toggle="modal" data-bs-target="#newProductModal">
                <i class="fas fa-plus me-2"></i>Novo Material
            </button>
            <button class="btn btn-outline-secondary fw-bold" data-bs-toggle="modal" data-bs-target="#movementModal">
                <i class="fas fa-right-left me-2"></i>Movimentar
            </button>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
/* Allow dropdowns to overflow the table container */
.table-responsive {
    overflow: visible !important;
    padding-bottom: 60px; /* Extra space for dropdowns on the last row */
    min-height: 400px; /* Ensure space even with few rows */
}
.card-body {
    overflow: visible !important;
}
</style>

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
                    <tr class="<?= $p['quantidade'] <= $p['estoque_minimo'] ? 'table-danger' : '' ?>">
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
                            <div class="dropstart">
                                <button class="btn btn-light btn-sm border shadow-sm" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                    <i class="fas fa-ellipsis-v"></i>
                                </button>
                                <ul class="dropdown-menu shadow-lg border-0">
                                    <li><h6 class="dropdown-header text-uppercase small opacity-50">Movimentação</h6></li>
                                    <li><a class="dropdown-item py-2" href="javascript:void(0)" onclick="openMovement(<?= $p['id'] ?>, 'entrada')">
                                        <i class="fas fa-plus-circle text-success me-2"></i>Entrada
                                    </a></li>
                                    <li><a class="dropdown-item py-2" href="javascript:void(0)" onclick="openMovement(<?= $p['id'] ?>, 'saida')">
                                        <i class="fas fa-minus-circle text-danger me-2"></i>Saída
                                    </a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><h6 class="dropdown-header text-uppercase small opacity-50">Gestão</h6></li>
                                    <li><a class="dropdown-item py-2" href="javascript:void(0)" onclick="editProduct(<?= htmlspecialchars(json_encode($p)) ?>)">
                                        <i class="fas fa-edit text-primary me-2"></i>Editar
                                    </a></li>
                                    <li><a class="dropdown-item py-2 text-danger" href="javascript:void(0)" onclick="deleteProduct(<?= $p['id'] ?>)">
                                        <i class="fas fa-trash me-2"></i>Excluir
                                    </a></li>
                                </ul>
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
                    <a class="page-link" href="?page=<?= $pagination['current'] - 1 ?>&q=<?= urlencode($filters['q']) ?>&categoria=<?= urlencode($filters['categoria']) ?>" aria-label="Anterior">
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
                    <a class="page-link" href="?page=<?= $i ?>&q=<?= urlencode($filters['q']) ?>&categoria=<?= urlencode($filters['categoria']) ?>"><?= $i ?></a>
                </li>
                <?php endfor; ?>
                <li class="page-item <?= $pagination['current'] >= $pagination['pages'] ? 'disabled' : '' ?>">
                    <a class="page-link" href="?page=<?= $pagination['current'] + 1 ?>&q=<?= urlencode($filters['q']) ?>&categoria=<?= urlencode($filters['categoria']) ?>" aria-label="Próximo">
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
                        <input type="text" name="codigo" class="form-control shadow-sm" required id="edit_codigo" style="font-family: 'Roboto Mono';" value="<?php echo 'PRD' . str_pad((int)(\App\Config\Database::getInstance()->getConnection())->query("SELECT MAX(id) FROM produtos")->fetchColumn() + 1, 5, '0', STR_PAD_LEFT); ?>" readonly>
                    </div>
                    <div class="col-md-8">
                        <label class="form-label small fw-bold">Nome / Descrição do Material *</label>
                        <input type="text" name="nome" class="form-control shadow-sm" required id="edit_nome">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-bold">Unidade de Medida *</label>
                        <select name="unidade" class="form-select shadow-sm" id="edit_unidade" required onchange="updateFormVisibility()">
                            <option value="UN">Unidade (UN)</option>
                            <option value="MT">Metro (MT)</option>
                            <option value="CX">Caixa (CX)</option>
                            <option value="PCT">Pacote (PCT)</option>
                            <option value="RL">Rolo (RL)</option>
                            <option value="PC">Peça (PC)</option>
                            <option value="CJ">Conjunto (CJ)</option>
                            <option value="PR">Par (PR)</option>
                            <option value="CTL">Cartela (CTL)</option>
                            <option value="KG">Quilograma (KG)</option>
                            <option value="GR">Grama (GR)</option>
                            <option value="L">Litro (L)</option>
                            <option value="ML">Mililitro (ML)</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-bold">Categoria</label>
                        <select name="categoria" class="form-select shadow-sm" id="edit_categoria">
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat ?>"><?= $cat ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-bold">Tipo de Material</label>
                        <select name="tipo_produto" class="form-select shadow-sm" id="edit_tipo_produto" onchange="updateFormVisibility()">
                            <option value="simples">Item Simples</option>
                            <option value="composto">Kit / Composto</option>
                            <option value="consumo">Material de Consumo</option>
                        </select>
                    </div>

                    <div class="col-12 mt-2 mb-1"><h6 class="fw-bold text-success small border-bottom pb-2"><i class="fas fa-tag me-1"></i>Preços</h6></div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold">Preço de Custo (R$) *</label>
                        <input type="number" step="0.01" min="0" name="preco_custo" class="form-control shadow-sm" required id="edit_preco_custo">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold">Preço Venda Normal (R$) *</label>
                        <input type="number" step="0.01" min="0" name="preco_venda" class="form-control shadow-sm border-primary" required id="edit_preco_venda">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold">Preço 2 (R$)</label>
                        <input type="number" step="0.01" min="0" name="preco_venda_2" class="form-control shadow-sm" id="edit_preco_venda_2" placeholder="Opcional">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold">Preço 3 (R$)</label>
                        <input type="number" step="0.01" min="0" name="preco_venda_3" class="form-control shadow-sm" id="edit_preco_venda_3" placeholder="Opcional">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-bold">Preço Atacado (R$)</label>
                        <input type="number" step="0.01" min="0" name="preco_venda_atacado" class="form-control shadow-sm" id="edit_preco_venda_atacado" placeholder="Opcional">
                    </div>

                    <div class="col-12 mt-2 mb-1"><h6 class="fw-bold text-warning small border-bottom pb-2"><i class="fas fa-boxes me-1"></i>Estoque</h6></div>
                    <div class="col-md-4">
                        <label class="form-label small fw-bold">Quantidade Inicial *</label>
                        <input type="number" step="0.01" min="0" name="quantidade" class="form-control shadow-sm" required id="edit_quantidade" value="0">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-bold">Estoque Mínimo (Alerta)</label>
                        <input type="number" min="0" name="estoque_minimo" class="form-control shadow-sm" id="edit_estoque_minimo" value="0">
                    </div>

                    <div class="col-12 mt-2 mb-1"><h6 class="fw-bold text-secondary small border-bottom pb-2"><i class="fas fa-ruler-combined me-1"></i>Logística <small class="text-muted fw-normal">(mostrado conforme unidade)</small></h6></div>
                    <div class="col-md-4" id="div_peso">
                        <label class="form-label small fw-bold">Peso Bruto (KG)</label>
                        <input type="number" step="0.001" min="0" name="peso" class="form-control shadow-sm" id="edit_peso">
                    </div>
                    <div class="col-md-4" id="div_dimensoes">
                        <label class="form-label small fw-bold">Dimensões (CxLxA)</label>
                        <input type="text" name="dimensoes" class="form-control shadow-sm" id="edit_dimensoes" placeholder="Ex: 10x15x5cm">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-bold">Especificações Técnicas</label>
                        <input type="text" name="descricao" class="form-control shadow-sm" id="edit_descricao" placeholder="Ex: 2,5mm² 750V">
                    </div>

                    <div class="col-12 mt-2 mb-1"><h6 class="fw-bold text-primary small border-bottom pb-2"><i class="fas fa-file-invoice me-1"></i>Informações Fiscais (SEFAZ)</h6></div>
                    <div class="col-md-4 position-relative">
                        <label class="form-label small fw-bold">NCM</label>
                        <div class="input-group input-group-sm shadow-sm">
                            <input type="text" name="ncm" class="form-control" id="edit_ncm" placeholder="Digite cód. ou nome..." autocomplete="off" onkeyup="searchNcmInline(this.value)">
                            <span class="input-group-text bg-white" id="ncmLoader" style="display:none;"><i class="fas fa-spinner fa-spin text-primary" style="font-size:0.75rem;"></i></span>
                        </div>
                        <ul id="ncmDropdown" class="list-group shadow-lg" style="display:none; position:absolute; left:0; right:0; top:100%; z-index:2000; max-height:220px; overflow-y:auto; background-color:#fff; border:1px solid #dee2e6; border-top:none; border-radius:0 0 6px 6px;"></ul>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-bold">GTIN/EAN (cEAN)</label>
                        <input type="text" name="cean" id="edit_cean" class="form-control shadow-sm" placeholder="SEM GTIN">
                    </div>
                    <div class="col-md-4 position-relative">
                        <label class="form-label small fw-bold">CEST</label>
                        <div class="input-group input-group-sm shadow-sm">
                            <input type="text" name="cest" id="edit_cest" class="form-control" placeholder="Digite cód. ou descrição..." autocomplete="off" onkeyup="searchCestInline(this.value)">
                            <span class="input-group-text bg-white" id="cestLoader" style="display:none;"><i class="fas fa-spinner fa-spin text-primary" style="font-size:0.75rem;"></i></span>
                        </div>
                        <ul id="cestDropdown" class="list-group shadow-lg" style="display:none; position:absolute; left:0; right:0; top:100%; z-index:2000; max-height:220px; overflow-y:auto; background-color:#fff; border:1px solid #dee2e6; border-top:none; border-radius:0 0 6px 6px;"></ul>
                    </div>
                    <div class="col-md-4">
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

<!-- Removes old modal block completely -->

<!-- Stock Movement Modal -->
<div class="modal fade" id="movementModal" tabindex="-1">
    <div class="modal-dialog">
        <form class="modal-content" action="estoque.php?action=move" method="POST">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold">Movimentar Inventário</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="row g-3">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <div class="col-12 text-start">
                        <label class="form-label small fw-bold">Produto</label>
                        <select name="produto_id" class="form-select shadow-sm" required>
                            <?php foreach ($allProducts as $p): ?>
                                <option value="<?= $p['id'] ?>"><?= $p['nome'] ?> (Saldo: <?= $p['quantidade'] ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6 text-start">
                        <label class="form-label small fw-bold text-success"><i class="fas fa-layer-group me-1"></i>Quantidade</label>
                        <input type="number" step="0.01" name="quantidade" class="form-control shadow-sm" required placeholder="0,00">
                    </div>
                    <div class="col-md-6 text-start">
                        <label class="form-label small fw-bold text-primary"><i class="fas fa-exchange-alt me-1"></i>Tipo</label>
                        <select name="tipo" class="form-select shadow-sm" required>
                            <option value="entrada">Entrada (+)</option>
                            <option value="saida">Saída (-)</option>
                            <option value="ajuste">Ajuste de Saldo</option>
                        </select>
                    </div>
                    <div class="col-md-12 text-start">
                        <label class="form-label small fw-bold text-warning"><i class="fas fa-barcode me-1"></i>Lote / Rastreabilidade</label>
                        <input type="text" name="lote" class="form-control shadow-sm" placeholder="Ex: LOTE-2024-001 (Opcional)">
                    </div>
                    <div class="col-12 text-start">
                        <label class="form-label small fw-bold text-secondary"><i class="fas fa-comment-alt me-1"></i>Motivo / Observação</label>
                        <textarea name="motivo" class="form-control shadow-sm" rows="3" required placeholder="Ex: Compra de mercadoria, Baixa para OS, etc"></textarea>
                    </div>
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

// Debounced Auto-submit for inventory search
let inventorySearchTimer;
document.getElementById('productSearch').addEventListener('input', function() {
    clearTimeout(inventorySearchTimer);
    inventorySearchTimer = setTimeout(() => {
        document.getElementById('inventoryFilterForm').submit();
    }, 600);
});

// Maintain focus on search input after reload if it was active
if (window.location.search.includes('q=')) {
    const input = document.getElementById('productSearch');
    input.focus();
    const val = input.value;
    input.value = '';
    input.value = val;
}

// NCM Search Logic with Inline Dropdown Autocomplete
let ncmDebounceTimer;

function searchNcmInline(term, force = false) {
    const dropdown = document.getElementById('ncmDropdown');
    const loader = document.getElementById('ncmLoader');
    
    if (term.length < 3 && !force) {
        dropdown.style.display = 'none';
        return;
    }

    clearTimeout(ncmDebounceTimer);
    ncmDebounceTimer = setTimeout(() => {
        loader.style.display = 'block';
        
        fetch(`api/ncm_search.php?search=${encodeURIComponent(term)}`)
            .then(response => {
                if (!response.ok) throw new Error('API Falhou');
                return response.json();
            })
            .then(data => {
                dropdown.innerHTML = '';
                if (data && data.length > 0) {
                    data.forEach(item => {
                        const cleanCode = item.codigo.replace(/\./g, '');
                        dropdown.innerHTML += `
                            <li>
                                <a class="dropdown-item py-2" href="javascript:void(0)" onclick="selectNcm('${cleanCode}')">
                                    <div class="fw-bold text-primary font-monospace small">${item.codigo}</div>
                                    <div class="text-truncate text-muted" style="max-width: 300px; font-size: 0.80rem;" title="${item.descricao}">${item.descricao}</div>
                                </a>
                            </li>
                        `;
                    });
                } else {
                    dropdown.innerHTML = '<li><span class="dropdown-item text-muted small">Nenhum NCM encontrado.</span></li>';
                }
                dropdown.style.display = 'block';
            })
            .catch(err => {
                dropdown.innerHTML = '<li><span class="dropdown-item text-danger small">Erro na busca.</span></li>';
                dropdown.style.display = 'block';
            })
            .finally(() => {
                loader.style.display = 'none';
            });
    }, 500); // 500ms debounce
}

function selectNcm(code) {
    document.getElementById('edit_ncm').value = code;
    document.getElementById('ncmDropdown').style.display = 'none';
}

// Close Dropdowns if clicked outside
document.addEventListener('click', function(event) {
    const ncmGroup = document.querySelector('#edit_ncm').parentElement;
    if (!ncmGroup.contains(event.target)) {
        document.getElementById('ncmDropdown').style.display = 'none';
    }
    const cestGroup = document.querySelector('#edit_cest').parentElement;
    if (!cestGroup.contains(event.target)) {
        document.getElementById('cestDropdown').style.display = 'none';
    }
});

// CEST Search Logic (local database via api/cest_search.php)
let cestDebounceTimer;

function searchCestInline(term) {
    const dropdown = document.getElementById('cestDropdown');
    const loader = document.getElementById('cestLoader');

    if (term.length < 2) {
        dropdown.style.display = 'none';
        return;
    }

    clearTimeout(cestDebounceTimer);
    cestDebounceTimer = setTimeout(() => {
        loader.style.display = 'block';

        fetch(`api/cest_search.php?search=${encodeURIComponent(term)}`)
            .then(r => r.ok ? r.json() : [])
            .then(data => {
                dropdown.innerHTML = '';
                if (data && data.length > 0) {
                    data.forEach(item => {
                        dropdown.innerHTML += `
                            <li class="list-group-item list-group-item-action p-2" onclick="selectCest('${item.codigo}')" style="cursor:pointer;">
                                <div class="fw-bold text-success font-monospace small">${item.codigo}</div>
                                <div class="text-muted text-truncate" style="font-size:0.78rem;" title="${item.descricao}">${item.descricao}</div>
                            </li>
                        `;
                    });
                } else {
                    dropdown.innerHTML = '<li class="list-group-item text-muted small">Nenhum CEST encontrado. Consulte a tabela SEFAZ.</li>';
                }
                dropdown.style.display = 'block';
            })
            .catch(() => {
                dropdown.innerHTML = '<li class="list-group-item text-danger small">Erro ao buscar CEST.</li>';
                dropdown.style.display = 'block';
            })
            .finally(() => {
                loader.style.display = 'none';
            });
    }, 400);
}

function selectCest(code) {
    document.getElementById('edit_cest').value = code;
    document.getElementById('cestDropdown').style.display = 'none';
}

function updateFormVisibility() {
    const unidade = document.getElementById('edit_unidade').value;
    
    // For liquid/weight units, hide the physical dimensions and weight fields
    // since they're not relevant (e.g., you don't have "width" for a liquid)
    const liquidUnits = ['KG', 'GR', 'L', 'ML'];
    const hideDimensions = liquidUnits.includes(unidade);
    
    document.getElementById('div_peso').style.display = hideDimensions ? 'none' : '';
    document.getElementById('div_dimensoes').style.display = hideDimensions ? 'none' : '';
}
</script>
