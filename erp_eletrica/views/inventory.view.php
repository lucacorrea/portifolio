<?php
if (!function_exists('erp_first_product_image')) {
    function erp_first_product_image(?string $imagens): string {
        $imagens = trim((string)$imagens);
        if ($imagens === '') {
            return '';
        }

        $json = json_decode($imagens, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($json)) {
            if (!empty($json['url']) && is_string($json['url'])) {
                return trim($json['url']);
            }
            if (!empty($json['imagem']) && is_string($json['imagem'])) {
                return trim($json['imagem']);
            }
            if (!empty($json['path']) && is_string($json['path'])) {
                return trim($json['path']);
            }
            foreach ($json as $item) {
                if (is_string($item) && trim($item) !== '') {
                    return trim($item);
                }
                if (is_array($item)) {
                    foreach (['url', 'imagem', 'path'] as $key) {
                        if (!empty($item[$key]) && is_string($item[$key])) {
                            return trim($item[$key]);
                        }
                    }
                }
            }
        }

        $partes = preg_split('/[\r\n,;|]+/', $imagens);
        if (is_array($partes)) {
            foreach ($partes as $parte) {
                $parte = trim((string)$parte);
                if ($parte !== '') {
                    return $parte;
                }
            }
        }

        return $imagens;
    }
}

if (!function_exists('erp_product_image_url')) {
    function erp_product_image_url(?string $imagens): string {
        $imagem = erp_first_product_image($imagens);
        if ($imagem === '') {
            return '';
        }
        if (preg_match('#^(https?:)?//#i', $imagem) || str_starts_with($imagem, 'data:')) {
            return $imagem;
        }
        $imagem = ltrim($imagem, './');
        if (str_starts_with($imagem, '/')) {
            return $imagem;
        }
        if (str_starts_with($imagem, 'public/uploads/produtos/')) {
            $imagem = basename($imagem);
        }
        if (str_contains($imagem, '/')) {
            return $imagem;
        }
        return 'produto_imagem.php?f=' . rawurlencode($imagem);
    }
}
?>

<?php if (isset($_GET['msg'])): ?>
    <div class="alert alert-dismissible fade show shadow-sm border-0 mb-4 text-white" style="background-color: #2b8a3e; font-weight: 500;" role="alert">
        <i class="fas fa-check-circle me-2"></i> <?= htmlspecialchars($_GET['msg']) ?>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<?php if (isset($_GET['error'])): ?>
    <div class="alert alert-danger alert-dismissible fade show shadow-sm border-0 mb-4" role="alert">
        <i class="fas fa-exclamation-circle me-2"></i> <?= htmlspecialchars($_GET['error']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<!-- Page Header -->
<div class="row g-3 mb-4 row-cols-1 row-cols-sm-2 row-cols-md-4">
    <div class="col">
        <div class="card h-100 border-0 shadow-sm">
            <div class="card-body">
                <div class="text-muted extra-small fw-bold text-uppercase mb-2">Total em Estoque</div>
                <h4 class="mb-0 fw-bold text-primary"><?= number_format($stats['total_itens'], 0, ',', '.') ?> <small class="text-muted fw-normal fs-6">un</small></h4>
            </div>
        </div>
    </div>
    <div class="col">
        <div class="card h-100 border-0 shadow-sm">
            <div class="card-body">
                <div class="text-muted extra-small fw-bold text-uppercase mb-2">Patrimônio (Custo)</div>
                <h4 class="mb-0 fw-bold text-success"><?= formatarMoeda($stats['valor_custo']) ?></h4>
            </div>
        </div>
    </div>
    <div class="col">
        <a href="estoque_baixo.php" class="text-decoration-none">
            <div class="card h-100 border-0 shadow-sm card-hover" style="border-left: 4px solid #ffc107 !important;">
                <div class="card-body">
                    <div class="text-muted extra-small fw-bold text-uppercase mb-2">Alertas de Baixa</div>
                    <h4 class="mb-0 fw-bold text-warning"><?= $stats['itens_criticos'] ?> <small class="fw-normal fs-6 text-muted">itens</small></h4>
                </div>
            </div>
        </a>
    </div>
    <div class="col">
        <a href="estoque.php?action=movimentacoes" class="text-decoration-none">
            <div class="card h-100 border-0 shadow-sm border-start border-info border-4 card-hover">
                <div class="card-body">
                    <div class="text-muted extra-small fw-bold text-uppercase mb-2">Giro (Mês)</div>
                    <h4 class="mb-0 fw-bold text-info"><?= $stats['mov_mes'] ?> <small class="fw-normal fs-6">mov</small></h4>
                </div>
            </div>
        </a>
    </div>
</div>

<!-- Actions Bar -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 py-3">
        <form method="GET" action="estoque.php" class="d-flex flex-column flex-sm-row gap-2 w-100 w-md-75" id="inventoryFilterForm">
            <div class="input-group">
                <span class="input-group-text bg-white border-end-0 text-muted">
                    <i class="fas fa-search"></i>
                </span>
                <input type="text" name="q" id="productSearch" class="form-control border-start-0" placeholder="Pesquisar material..." value="<?= htmlspecialchars($filters['q']) ?>" autocomplete="off">
            </div>
            <select name="categoria" class="form-select w-100 w-sm-auto" id="filterCategory" onchange="this.form.submit()">
                <option value="">Todas Categorias</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= $cat ?>" <?= $filters['categoria'] == $cat ? 'selected' : '' ?>><?= $cat ?></option>
                <?php endforeach; ?>
            </select>
            <select name="ordem" class="form-select w-100 w-sm-auto fw-bold" onchange="this.form.submit()">
                <option value="codigo_desc" <?= $filters['ordem'] == 'codigo_desc' ? 'selected' : '' ?>>Último Código (Maior → Menor)</option>
                <option value="codigo_asc" <?= $filters['ordem'] == 'codigo_asc' ? 'selected' : '' ?>>Primeiro Código (Menor → Maior)</option>
                <option value="nome_asc" <?= $filters['ordem'] == 'nome_asc' ? 'selected' : '' ?>>Nome (A-Z)</option>
                <option value="categoria_asc" <?= $filters['ordem'] == 'categoria_asc' ? 'selected' : '' ?>>Categoria / Nome</option>
            </select>
        </form>
        <div class="d-flex gap-2 w-100 w-md-auto flex-wrap">
            <button class="btn btn-primary fw-bold flex-grow-1" onclick="openNewProduct()">
                <i class="fas fa-plus me-2"></i>Novo
            </button>
            <button class="btn btn-outline-secondary fw-bold flex-grow-1" data-bs-toggle="modal" data-bs-target="#movementModal">
                <i class="fas fa-right-left me-2"></i>Movimentar
            </button>
            <?php if ($_SESSION['is_matriz'] ?? false): ?>
                <button class="btn btn-outline-primary fw-bold flex-grow-1" data-bs-toggle="modal" data-bs-target="#replicateCatalogModal">
                    <i class="fas fa-copy me-2"></i>Enviar p/ Filial
                </button>
            <?php endif; ?>
            <a href="estoque.php?action=problems" class="btn btn-outline-danger fw-bold flex-grow-1">
                <i class="fas fa-exclamation-triangle me-2"></i>Produtos c/ Problema
            </a>
        </div>
    </div>
</div>

<style>
/* Allow dropdowns to overflow the table container ONLY on desktop */
@media (min-width: 992px) {
    .table-responsive {
        overflow: visible !important;
        padding-bottom: 120px; /* More space for dropdowns */
    }
    .card-body {
        overflow: visible !important;
    }
}

    /* On mobile, scroll is MANDATORY */
    @media (max-width: 991.98px) {
        .table-responsive {
            overflow-x: auto !important;
            padding-bottom: 1rem;
        }
    }

    .card-hover {
        transition: transform 0.2s ease, box-shadow 0.2s ease;
        cursor: pointer;
    }
    .card-hover:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 20px rgba(0,0,0,0.1) !important;
    }
</style>

<!-- Products Table -->
<div class="card border-0 shadow-sm" style="overflow: visible !important;">
    <div class="card-body p-0" style="min-height: 450px; overflow: visible !important;">
        <div class="table-responsive" style="overflow: visible !important;">
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
                                <?php $productImageUrl = erp_product_image_url((string)($p['imagens'] ?? '')); ?>
                                <?php if ($productImageUrl !== ''): ?>
                                    <div class="product-zoom-container rounded me-3 border" style="cursor: zoom-in; width: 40px; height: 40px; flex-shrink: 0;">
                                        <img src="<?= htmlspecialchars($productImageUrl, ENT_QUOTES, 'UTF-8') ?>" data-base-name="<?= htmlspecialchars(pathinfo(erp_first_product_image((string)($p['imagens'] ?? '')), PATHINFO_FILENAME), ENT_QUOTES, 'UTF-8') ?>" data-try-exts="jpg,JPG,jpeg,JPEG,png,PNG,webp,WEBP,gif,GIF,avif,AVIF" data-ext-idx="0" onerror="smartImgTryExt(this)" width="40" height="40" style="object-fit: cover;">
                                    </div>
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
                                <?= formatarQuantidade($p['quantidade']) ?>
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
                                    <li><a class="dropdown-item py-2" href="javascript:void(0)" onclick="openProblemModal(<?= $p['id'] ?>, '<?= addslashes($p['nome']) ?>')">
                                        <i class="fas fa-exclamation-triangle text-warning me-2"></i>Reportar Defeito
                                    </a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><h6 class="dropdown-header text-uppercase small opacity-50">Gestão</h6></li>
                                    <li><a class="dropdown-item py-2" href="javascript:void(0)" onclick="viewProduct(<?= htmlspecialchars(json_encode($p)) ?>)">
                                        <i class="fas fa-eye text-info me-2"></i>Visualizar
                                    </a></li>
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
    <div class="card-footer bg-white border-top py-3">
        <?= renderPagination($pagination, 'estoque.php', $filters) ?>
    </div>
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
                    <!-- Identificadores de Código -->
                    <div class="row g-3 mb-3">
                        <div class="col-md-3">
                            <label class="form-label small fw-bold">Código Interno *</label>
                            <input type="text" name="codigo" class="form-control shadow-sm" required id="edit_codigo" style="font-family: 'Roboto Mono';" oninput="toggleProductCodeViews(this.value)">
                        </div>
                        <div class="col-md-4 group-extra-codes">
                            <label class="form-label small fw-bold">Cód. de Barras (EAN)</label>
                            <input type="text" name="cean" id="edit_cean" class="form-control shadow-sm" placeholder="Opcional">
                        </div>
                        <div class="col-md-5 group-extra-codes">
                            <label class="form-label small fw-bold">QR Code</label>
                            <input type="text" name="qrcode" id="edit_qrcode" class="form-control shadow-sm" placeholder="Opcional">
                        </div>
                        
                        <!-- Nome ocupará linha inteira se for 00000, ou o resto se não for -->
                        <div class="col-md-12" id="div_edit_nome">
                            <label class="form-label small fw-bold">Nome / Descrição do Material *</label>
                            <input type="text" name="nome" class="form-control shadow-sm" required id="edit_nome" placeholder="Ex: Lâmpada LED 12W">
                        </div>
                    </div>
                    
                    <!-- Campo de Foto -->
                    <div class="col-md-12">
                        <label class="form-label small fw-bold">Foto do Produto</label>
                        <div class="d-flex align-items-center gap-3 p-3 border rounded bg-light border-dashed">
                            <div id="image-preview-container" class="bg-white rounded border d-flex align-items-center justify-content-center" style="width: 80px; height: 80px; overflow: hidden;">
                                <img id="edit_preview" src="" class="img-fluid d-none">
                                <i id="preview-icon" class="fas fa-image text-muted opacity-50 fa-2x"></i>
                            </div>
                            <div class="flex-grow-1">
                                <input type="file" name="foto" id="edit_foto" class="form-control form-control-sm" accept="image/*" onchange="previewImage(this)">
                                <small class="text-muted extra-small">Formatos aceitos: JPG, PNG. Tamanho máx: 2MB.</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
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
                    <div class="col-md-3">
                        <label class="form-label small fw-bold">Categoria</label>
                        <select name="categoria" class="form-select shadow-sm" id="edit_categoria">
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat ?>"><?= $cat ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold">Tipo de Material</label>
                        <select name="tipo_produto" class="form-select shadow-sm" id="edit_tipo_produto" onchange="updateFormVisibility()">
                            <option value="simples">Item Simples</option>
                            <option value="composto">Kit / Composto</option>
                            <option value="consumo">Material de Consumo</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold">Fornecedor</label>
                        <select name="fornecedor_id" class="form-select shadow-sm" id="edit_fornecedor_id">
                            <option value="">Nenhum</option>
                            <?php foreach ($suppliers as $sup): ?>
                                <option value="<?= $sup['id'] ?>"><?= htmlspecialchars($sup['nome_fantasia'] ?: $sup['nome']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-12 mt-2 mb-1"><h6 class="fw-bold text-success small border-bottom pb-2"><i class="fas fa-tag me-1"></i>Preços</h6></div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold">Preço de Custo (R$) *</label>
                        <input type="number" step="0.01" min="0" name="preco_custo" class="form-control shadow-sm" required id="edit_preco_custo">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold">Preço de Venda (R$) *</label>
                        <input type="number" step="0.01" min="0" name="preco_venda" class="form-control shadow-sm border-primary" required id="edit_preco_venda">
                    </div>
                    <div class="col-md-3 d-flex align-items-end" id="div_preco_variavel" style="display: none !important;">
                        <div class="form-check form-switch mb-2">
                            <input class="form-check-input" type="checkbox" name="preco_variavel" value="1" id="edit_preco_variavel">
                            <label class="form-check-label small fw-bold text-primary" for="edit_preco_variavel">Preço Variável (PDV)</label>
                        </div>
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
                        <input type="number" step="any" min="0" name="quantidade" class="form-control shadow-sm" required id="edit_quantidade" value="0">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-bold">Estoque Mínimo (Alerta)</label>
                        <input type="number" step="any" min="0" name="estoque_minimo" class="form-control shadow-sm" id="edit_estoque_minimo" value="0">
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
                        <label class="form-label small fw-bold">NCM <span class="text-danger">*</span></label>
                        <div class="input-group input-group-sm shadow-sm">
                            <input type="text" name="ncm" class="form-control" id="edit_ncm" placeholder="Digite cód. ou nome..." autocomplete="off" inputmode="numeric" maxlength="8" pattern="\d{8}" required onkeyup="searchNcmInline(this.value)">
                            <span class="input-group-text bg-white" id="ncmLoader" style="display:none;"><i class="fas fa-spinner fa-spin text-primary" style="font-size:0.75rem;"></i></span>
                        </div>
                        <div class="extra-small text-muted">Obrigatorio para emissao fiscal. Use 8 digitos.</div>
                        <ul id="ncmDropdown" class="list-group shadow-lg" style="display:none; position:absolute; left:0; right:0; top:100%; z-index:2000; max-height:220px; overflow-y:auto; background-color:#fff; border:1px solid #dee2e6; border-top:none; border-radius:0 0 6px 6px;"></ul>
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

<!-- Product Details View Modal -->
<div class="modal fade" id="viewProductModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 16px; overflow: hidden;">
            <!-- Premium Header -->
            <div class="modal-header bg-light border-0 py-3 px-4 d-flex align-items-center">
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-primary bg-opacity-10 text-primary px-3 py-2 rounded-pill fw-bold" id="view_label_codigo"></span>
                    <span class="badge bg-secondary bg-opacity-10 text-secondary px-3 py-2 rounded-pill fw-bold" id="view_label_unidade"></span>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            
            <div class="modal-body p-4" style="background-color: #fafbfc;">
                <div class="row g-4">
                    <!-- Left: Image & Stock Info -->
                    <div class="col-md-5 text-center">
                        <div class="card border-0 shadow-sm p-3 mb-3 text-center bg-white" style="border-radius: 12px; min-height: 250px; display: flex; align-items: center; justify-content: center;">
                            <img id="view_foto" src="" class="img-fluid rounded shadow-sm d-none" style="max-height: 220px; object-fit: contain; width: 100%;">
                            <div id="view_no_foto" class="d-flex flex-column align-items-center justify-content-center text-muted" style="height: 180px;">
                                <i class="fas fa-image fa-3x mb-2 opacity-25"></i>
                                <span class="small fw-semibold">Sem foto disponível</span>
                            </div>
                        </div>
                        
                        <!-- Stock Level Card -->
                        <div class="card border-0 shadow-sm bg-white" style="border-radius: 12px;">
                            <div class="card-body p-3">
                                <div class="row g-2">
                                    <div class="col-6 border-end">
                                        <div class="text-muted extra-small fw-bold text-uppercase mb-1">Estoque Atual</div>
                                        <div class="fs-4 fw-bold text-dark" id="view_quantidade">0,00</div>
                                    </div>
                                    <div class="col-6">
                                        <div class="text-muted extra-small fw-bold text-uppercase mb-1">Estoque Mínimo</div>
                                        <div class="fs-4 fw-bold text-secondary" id="view_estoque_minimo">0,00</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Right: Product Info & Pricing -->
                    <div class="col-md-7">
                        <div class="d-flex flex-column h-100">
                            <!-- Title & Category -->
                            <div class="mb-3 text-start">
                                <span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25 rounded-pill px-3 py-1 mb-2 fw-semibold" id="view_categoria"></span>
                                <h3 class="fw-bold text-dark mb-1" id="view_nome" style="line-height: 1.2;"></h3>
                                <div class="text-muted small" id="view_fornecedor"></div>
                            </div>

                            <!-- Pricing Showcase (WOW Factor) -->
                            <div class="card border-0 shadow-sm mb-3" style="background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%); border-radius: 12px; color: #fff;">
                                <div class="card-body p-4 text-center text-sm-start">
                                    <div class="text-white-50 extra-small fw-bold text-uppercase mb-1">Preço de Venda Principal</div>
                                    <h2 class="fw-bold text-white mb-0 fs-1" id="view_preco_venda">R$ 0,00</h2>
                                </div>
                            </div>

                            <!-- Pricing Tiers Grid -->
                            <div class="card border-0 shadow-sm bg-white mb-3" style="border-radius: 12px;">
                                <div class="card-body p-3">
                                    <div class="row g-3 text-start">
                                        <div class="col-6 col-sm-3 border-end">
                                            <div class="text-muted extra-small fw-bold text-uppercase mb-1">Custo</div>
                                            <div class="fw-bold text-danger" id="view_preco_custo">R$ 0,00</div>
                                        </div>
                                        <div class="col-6 col-sm-3 border-end">
                                            <div class="text-muted extra-small fw-bold text-uppercase mb-1">Preço 2</div>
                                            <div class="fw-bold text-dark" id="view_preco_venda_2">R$ 0,00</div>
                                        </div>
                                        <div class="col-6 col-sm-3 border-end">
                                            <div class="text-muted extra-small fw-bold text-uppercase mb-1">Preço 3</div>
                                            <div class="fw-bold text-dark" id="view_preco_venda_3">R$ 0,00</div>
                                        </div>
                                        <div class="col-6 col-sm-3">
                                            <div class="text-muted extra-small fw-bold text-uppercase mb-1">Atacado</div>
                                            <div class="fw-bold text-primary" id="view_preco_venda_atacado">R$ 0,00</div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Specs / Tech Description -->
                            <div class="card border-0 shadow-sm bg-white mb-3" style="border-radius: 12px;">
                                <div class="card-body p-3 text-start">
                                    <div class="text-muted extra-small fw-bold text-uppercase mb-2"><i class="fas fa-file-alt me-1"></i>Especificações / Descrição</div>
                                    <p class="mb-0 text-dark small fw-semibold" id="view_descricao" style="line-height: 1.5; white-space: pre-wrap; min-height: 40px;"></p>
                                </div>
                            </div>

                            <!-- Logistics & Fiscal Details -->
                            <div class="card border-0 shadow-sm bg-white" style="border-radius: 12px;">
                                <div class="card-body p-3">
                                    <div class="row g-2 text-muted small text-start">
                                        <div class="col-6">
                                            <span class="fw-semibold">NCM:</span> <span id="view_ncm" class="text-dark font-monospace fw-bold"></span>
                                        </div>
                                        <div class="col-6">
                                            <span class="fw-semibold">CEST:</span> <span id="view_cest" class="text-dark font-monospace fw-bold"></span>
                                        </div>
                                        <div class="col-6">
                                            <span class="fw-semibold">Peso:</span> <span id="view_peso" class="text-dark fw-bold"></span>
                                        </div>
                                        <div class="col-6">
                                            <span class="fw-semibold">Dimensões:</span> <span id="view_dimensoes" class="text-dark fw-bold"></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="modal-footer bg-light border-0 py-3 px-4">
                <button type="button" class="btn btn-secondary px-4 fw-bold rounded-pill" data-bs-dismiss="modal">Fechar</button>
                <button type="button" class="btn btn-primary px-4 fw-bold rounded-pill" id="btn_edit_from_view">
                    <i class="fas fa-edit me-2"></i>Editar Produto
                </button>
            </div>
        </div>
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
                        <label class="form-label small fw-bold text-secondary"><i class="fas fa-comment-alt me-1"></i>Motivo / Observação (Opcional)</label>
                        <textarea name="motivo" class="form-control shadow-sm" rows="3" placeholder="Ex: Compra de mercadoria, Baixa para OS, etc"></textarea>
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

<!-- Problem Report Modal -->
<div class="modal fade" id="problemModal" tabindex="-1">
    <div class="modal-dialog">
        <form class="modal-content" action="estoque.php?action=save_problem" method="POST">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold text-danger"><i class="fas fa-exclamation-triangle me-2"></i>Reportar Produto com Problema</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="produto_id" id="prob_produto_id">
                
                <div class="mb-3">
                    <label class="form-label small fw-bold">Produto</label>
                    <input type="text" id="prob_produto_nome" class="form-control bg-light" readonly>
                </div>
                
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label small fw-bold">Quantidade com Problema</label>
                        <input type="number" step="0.01" name="quantidade" class="form-control shadow-sm" required placeholder="0,00">
                    </div>
                    <div class="col-md-6 d-flex align-items-end">
                        <div class="form-check form-switch mb-2">
                            <input class="form-check-input" type="checkbox" name="subtrair_estoque" value="1" id="prob_subtrair" checked>
                            <label class="form-check-label small fw-bold" for="prob_subtrair">Retirar do estoque atual</label>
                        </div>
                    </div>
                    <div class="col-12">
                        <label class="form-label small fw-bold">Descreva o Problema / Defeito</label>
                        <textarea name="motivo" class="form-control shadow-sm" rows="3" required placeholder="Ex: Tela trincada, Não liga, Devolução de cliente com defeito..."></textarea>
                    </div>
                </div>
                
                <div class="alert alert-warning mt-3 mb-0 small">
                    <i class="fas fa-info-circle me-1"></i> 
                    Ao retirar do estoque, o saldo disponível para venda será diminuído imediatamente.
                </div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-light fw-bold" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-danger px-4 fw-bold">Registrar Problema</button>
            </div>
        </form>
    </div>
</div>

<!-- Replicate Catalog Modal -->
<div class="modal fade" id="replicateCatalogModal" tabindex="-1">
    <div class="modal-dialog">
        <form class="modal-content border-0 shadow-lg" style="border-radius: 16px; overflow: hidden;" action="estoque.php?action=replicate_catalog" method="POST">
            <div class="modal-header bg-primary text-white border-0 py-3">
                <h5 class="modal-title fw-bold text-white"><i class="fas fa-copy me-2"></i>Enviar Catálogo p/ Filial</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4 bg-light">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                
                <div class="alert alert-info border-0 shadow-sm mb-4" style="border-radius: 10px; background-color: rgba(43, 76, 125, 0.08); color: var(--primary-color);">
                    <h6 class="fw-bold mb-1"><i class="fas fa-info-circle me-1"></i> Como funciona a sincronização?</h6>
                    <p class="extra-small mb-0 opacity-75 text-dark" style="font-size: 0.75rem; line-height: 1.4;">
                        Esta ferramenta vinculará instantaneamente **todos** os produtos cadastrados na Matriz para a filial selecionada. O estoque na filial será iniciado com **1 unidade padrão** e o estoque mínimo de controle será definido em **1 unidade**, trazendo de forma compartilhada todas as fotos, dados fiscais, preços e descrições dos materiais.
                    </p>
                </div>
                
                <div class="mb-4 text-start">
                    <label class="form-label small fw-bold text-dark"><i class="fas fa-store me-1"></i>Selecionar Filial Destino *</label>
                    <select name="destino_filial_id" class="form-select shadow-sm border-primary" required style="border-radius: 8px;">
                        <option value="" disabled selected>Escolha a filial...</option>
                        <?php foreach ($branches as $br): ?>
                            <option value="<?= $br['id'] ?>"><?= htmlspecialchars($br['nome']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="alert alert-warning border-0 shadow-sm small text-start mb-0" style="border-radius: 10px;">
                    <i class="fas fa-exclamation-triangle me-1"></i> 
                    <strong>Importante:</strong> Se a filial já tiver produtos vinculados, os saldos e limites serão atualizados/sobrescritos para 1. Esta ação é imediata e irreversível.
                </div>
            </div>
            <div class="modal-footer border-0 bg-light pb-4">
                <button type="button" class="btn btn-light fw-bold" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary px-4 fw-bold shadow"><i class="fas fa-paper-plane me-1"></i>Enviar Catálogo</button>
            </div>
        </form>
    </div>
</div>

<script>
function firstProductImage(value) {
    const raw = String(value || '').trim();
    if (!raw) return '';

    try {
        const parsed = JSON.parse(raw);
        if (Array.isArray(parsed)) {
            for (const item of parsed) {
                if (typeof item === 'string' && item.trim()) return item.trim();
                if (item && typeof item === 'object') {
                    const found = item.url || item.imagem || item.path;
                    if (typeof found === 'string' && found.trim()) return found.trim();
                }
            }
        } else if (parsed && typeof parsed === 'object') {
            const found = parsed.url || parsed.imagem || parsed.path;
            if (typeof found === 'string' && found.trim()) return found.trim();
        }
    } catch (e) {}

    return raw.split(/[\r\n,;|]+/).map(s => s.trim()).find(Boolean) || raw;
}

function productImageUrl(value) {
    const image = firstProductImage(value);
    if (!image) return '';
    if (/^(https?:)?\/\//i.test(image) || image.startsWith('data:') || image.startsWith('/')) {
        return image;
    }
    const clean = image.replace(/^\.\//, '');
    if (clean.startsWith('public/uploads/produtos/')) {
        return `produto_imagem.php?f=${encodeURIComponent(clean.split('/').pop())}`;
    }
    return clean.includes('/') ? clean : `produto_imagem.php?f=${encodeURIComponent(clean)}`;
}

function smartImgTryExt(imgEl) {
    const base = imgEl.dataset.baseName || '';
    const list = (imgEl.dataset.tryExts || '').split(',').map(s => s.trim()).filter(Boolean);
    let idx = parseInt(imgEl.dataset.extIdx || '0', 10);
    if (!base || !list.length || idx >= list.length) {
        imgEl.style.display = 'none';
        return;
    }
    const nextExt = list[idx];
    imgEl.dataset.extIdx = String(idx + 1);
    imgEl.src = `produto_imagem.php?f=${encodeURIComponent(`${base}.${nextExt}`)}`;
}

function openNewProduct() {
    const modal = new bootstrap.Modal(document.getElementById('newProductModal'));
    const form = document.querySelector('#newProductModal form');
    form.reset();
    
    document.getElementById('edit_id').value = '';
    document.getElementById('edit_codigo').value = '<?= $nextCode ?>';
    
    // Clear image preview
    document.getElementById('edit_preview').src = '';
    document.getElementById('edit_preview').classList.add('d-none');
    document.getElementById('preview-icon').classList.remove('d-none');
    
    document.querySelector('#newProductModal .modal-title').innerText = 'Novo Material';
    
    // Reset code-dependent views
    toggleProductCodeViews('<?= $nextCode ?>');
    
    modal.show();
}

function editProduct(product) {
    const modal = new bootstrap.Modal(document.getElementById('newProductModal'));
    document.getElementById('edit_id').value = product.id;
    document.getElementById('edit_codigo').value = product.codigo;
    document.getElementById('edit_ncm').value = cleanNcmCode(product.ncm || '');
    document.getElementById('edit_nome').value = product.nome;
    document.getElementById('edit_unidade').value = product.unidade;
    document.getElementById('edit_categoria').value = product.categoria;
    document.getElementById('edit_tipo_produto').value = product.tipo_produto || 'simples';
    document.getElementById('edit_fornecedor_id').value = product.fornecedor_id || '';
    document.getElementById('edit_quantidade').value = parseFloat(product.quantidade || 0);
    
    document.getElementById('edit_peso').value = product.peso || '';
    document.getElementById('edit_dimensoes').value = product.dimensoes || '';
    document.getElementById('edit_descricao').value = product.descricao || '';
    
    document.getElementById('edit_preco_custo').value = product.preco_custo;
    document.getElementById('edit_preco_venda').value = product.preco_venda;
    document.getElementById('edit_preco_venda_2').value = product.preco_venda_2 || '';
    document.getElementById('edit_preco_venda_3').value = product.preco_venda_3 || '';
    document.getElementById('edit_preco_venda_atacado').value = product.preco_venda_atacado || '';
    document.getElementById('edit_estoque_minimo').value = parseFloat(product.estoque_minimo || 0);
    document.getElementById('edit_preco_variavel').checked = product.preco_variavel == 1;
    document.getElementById('edit_qrcode').value = product.qrcode || '';
    
    // Toggle views based on code (00000 vs others)
    toggleProductCodeViews(product.codigo);

    // Fiscal Fields
    document.getElementById('edit_cean').value = product.cean || '';
    document.getElementById('edit_cest').value = product.cest || '';
    document.getElementById('edit_origem').value = product.origem || 0;
    document.getElementById('edit_csosn').value = product.csosn || '102';
    document.getElementById('edit_cfop_interno').value = product.cfop_interno || '5102';
    document.getElementById('edit_cfop_externo').value = product.cfop_externo || '6102';
    document.getElementById('edit_icms').value = product.aliquota_icms || 0;
    
    // Preview da Imagem
    const preview = document.getElementById('edit_preview');
    const icon = document.getElementById('preview-icon');
    const productImage = productImageUrl(product.imagens);
    if (productImage) {
        preview.src = productImage;
        preview.dataset.baseName = firstProductImage(product.imagens).replace(/\.[^/.]+$/, '');
        preview.dataset.tryExts = 'jpg,JPG,jpeg,JPEG,png,PNG,webp,WEBP,gif,GIF,avif,AVIF';
        preview.dataset.extIdx = '0';
        preview.onerror = function() { smartImgTryExt(preview); };
        preview.classList.remove('d-none');
        icon.classList.add('d-none');
    } else {
        preview.src = '';
        preview.classList.add('d-none');
        icon.classList.remove('d-none');
    }

    document.querySelector('#newProductModal .modal-title').innerText = 'Editar Material';
    modal.show();
}

function previewImage(input) {
    const preview = document.getElementById('edit_preview');
    const icon = document.getElementById('preview-icon');
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            preview.src = e.target.result;
            preview.classList.remove('d-none');
            icon.classList.add('d-none');
        }
        reader.readAsDataURL(input.files[0]);
    }
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

function openProblemModal(productId, productName) {
    const modal = new bootstrap.Modal(document.getElementById('problemModal'));
    document.getElementById('prob_produto_id').value = productId;
    document.getElementById('prob_produto_nome').value = productName;
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

function cleanNcmCode(value) {
    return String(value || '').replace(/\D/g, '').slice(0, 8);
}

document.getElementById('edit_ncm').addEventListener('input', function(e) {
    const clean = cleanNcmCode(e.target.value);
    if (e.target.value !== clean) {
        e.target.value = clean;
    }
});

document.querySelectorAll('#newProductModal input[type="number"]').forEach(input => {
    input.addEventListener('focus', function() {
        this.select();
    });
});

// Handle manual code entry for Preço Variável toggle
document.getElementById('edit_codigo').addEventListener('input', function(e) {
    const divPrecoVariavel = document.getElementById('div_preco_variavel');
    if (e.target.value == '7423') {
        divPrecoVariavel.style.setProperty('display', 'flex', 'important');
    } else {
        divPrecoVariavel.style.setProperty('display', 'none', 'important');
    }
});

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
    document.getElementById('edit_ncm').value = cleanNcmCode(code);
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
function toggleProductCodeViews(code) {
    const isDiversos = (code === '00000' || code === '7423');
    const extraCodes = document.querySelectorAll('.group-extra-codes');
    const divNome = document.getElementById('div_edit_nome');
    const divPrecoVariavel = document.getElementById('div_preco_variavel');

    if (isDiversos) {
        extraCodes.forEach(el => el.classList.add('d-none'));
        if (divNome) {
            divNome.classList.remove('col-md-6');
            divNome.classList.add('col-md-12');
        }
        if (divPrecoVariavel) {
            divPrecoVariavel.style.setProperty('display', 'flex', 'important');
        }
    } else {
        extraCodes.forEach(el => el.classList.remove('d-none'));
        if (divNome) {
            divNome.classList.remove('col-md-12');
            divNome.classList.add('col-md-12');
        }
        if (divPrecoVariavel) {
            divPrecoVariavel.style.setProperty('display', 'none', 'important');
        }
    }
}

function viewProduct(product) {
    const modal = new bootstrap.Modal(document.getElementById('viewProductModal'));
    
    // Header labels
    document.getElementById('view_label_codigo').innerText = '#' + product.codigo;
    document.getElementById('view_label_unidade').innerText = product.unidade || 'UN';
    
    // Image resolution
    const viewFoto = document.getElementById('view_foto');
    const viewNoFoto = document.getElementById('view_no_foto');
    const viewProductImage = productImageUrl(product.imagens);
    if (viewProductImage) {
        viewFoto.src = viewProductImage;
        viewFoto.dataset.baseName = firstProductImage(product.imagens).replace(/\.[^/.]+$/, '');
        viewFoto.dataset.tryExts = 'jpg,JPG,jpeg,JPEG,png,PNG,webp,WEBP,gif,GIF,avif,AVIF';
        viewFoto.dataset.extIdx = '0';
        viewFoto.onerror = function() { smartImgTryExt(viewFoto); };
        viewFoto.classList.remove('d-none');
        viewNoFoto.classList.add('d-none');
    } else {
        viewFoto.src = '';
        viewFoto.classList.add('d-none');
        viewNoFoto.classList.remove('d-none');
    }
    
    // Name, Category, Fornecedor
    document.getElementById('view_nome').innerText = product.nome;
    document.getElementById('view_categoria').innerText = product.categoria || 'Sem Categoria';
    
    // Find supplier name if any
    let supplierName = 'Sem fornecedor cadastrado';
    if (product.fornecedor_id) {
        const select = document.getElementById('edit_fornecedor_id');
        const option = select.querySelector(`option[value="${product.fornecedor_id}"]`);
        if (option) {
            supplierName = 'Fornecedor: ' + option.innerText;
        }
    }
    document.getElementById('view_fornecedor').innerText = supplierName;
    
    // Stock Info
    document.getElementById('view_quantidade').innerText = formatQty(product.quantidade);
    document.getElementById('view_estoque_minimo').innerText = formatQty(product.estoque_minimo);
    
    // Primary pricing
    document.getElementById('view_preco_venda').innerText = formatCurrency(product.preco_venda);
    
    // Pricing tiers
    document.getElementById('view_preco_custo').innerText = formatCurrency(product.preco_custo);
    document.getElementById('view_preco_venda_2').innerText = formatCurrency(product.preco_venda_2);
    document.getElementById('view_preco_venda_3').innerText = formatCurrency(product.preco_venda_3);
    document.getElementById('view_preco_venda_atacado').innerText = formatCurrency(product.preco_venda_atacado);
    
    // Specs/Description
    document.getElementById('view_descricao').innerText = product.descricao || 'Nenhuma especificação técnica informada.';
    
    // Fiscal/Logistics
    document.getElementById('view_ncm').innerText = product.ncm || '---';
    document.getElementById('view_cest').innerText = product.cest || '---';
    document.getElementById('view_peso').innerText = product.peso ? parseFloat(product.peso).toFixed(3) + ' kg' : '---';
    document.getElementById('view_dimensoes').innerText = product.dimensoes || '---';
    
    // Bind Edit button
    document.getElementById('btn_edit_from_view').onclick = function() {
        // Hide view modal
        bootstrap.Modal.getInstance(document.getElementById('viewProductModal')).hide();
        // Wait for hide animation then open edit modal
        setTimeout(() => {
            editProduct(product);
        }, 150);
    };
    
    modal.show();
}

function formatQty(val) {
    if (val === null || val === undefined || isNaN(val)) return '0,00';
    return parseFloat(val).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 4 });
}

function formatCurrency(val) {
    if (val === null || val === undefined || isNaN(val)) return 'R$ 0,00';
    return 'R$ ' + parseFloat(val).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
</script>
