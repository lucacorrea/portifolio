<?php if (!$caixaAberto): ?>
<div class="alert alert-warning shadow-sm border-0 d-flex align-items-center mb-4 p-3 rounded-4" style="background: rgba(251, 191, 36, 0.1); border: 1px solid var(--primary-color) !important;">
    <i class="fas fa-cash-register fa-2x me-3 text-warning"></i>
    <div class="flex-grow-1">
        <h6 class="mb-1 fw-bold text-dark">CAIXA FECHADO PARA ESTA FILIAL</h6>
        <p class="mb-0 small text-secondary">A geração de pré-vendas (orçamentos) requer que você tenha um caixa aberto.</p>
    </div>
</div>
<?php endif; ?>

<div class="row g-4 <?php echo !$caixaAberto ? 'opacity-50 select-none' : ''; ?>" style="<?php echo !$caixaAberto ? 'pointer-events: none;' : ''; ?>">
    <!-- Left Side: Product selection & Preview -->
    <div class="col-lg-8 d-flex flex-column">
        <div class="row g-4 mb-4" style="position: relative; z-index: 1050; overflow: visible !important;">
            <div class="col-md-9" style="position: relative; z-index: 1060; overflow: visible !important;">
                <div class="card border-0 shadow-sm h-100" style="overflow: visible !important;">
                    <div class="card-body" style="overflow: visible !important;">
                        <div class="position-relative" style="overflow: visible !important;">
                            <div class="input-group input-group-lg shadow-sm rounded">
                                <span class="input-group-text bg-white border-end-0 text-muted">
                                    <i class="fas fa-barcode"></i>
                                </span>
                                <input type="text" id="pv_product_search" class="form-control border-start-0 ps-0" placeholder="Pesquisar Produto (F4)..." autocomplete="off" style="flex: 3;">
                                <span class="input-group-text bg-light border-start-0 text-muted extra-small fw-bold">QTD</span>
                                <input type="number" id="pvQty" class="form-control border-start-0 text-center fw-bold" value="1" min="1" step="0.001" style="flex: 1; max-width: 90px;" title="Quantidade">
                            </div>
                            <div id="pv_search_results" class="list-group shadow-lg d-none" style="position: absolute; top: 100%; left: 0; z-index: 10000; width: 100%; max-height: 400px; overflow-y: auto;">
                                <!-- Results will be injected here -->
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Product Preview -->
            <div class="col-md-3">
                <div class="card border-0 shadow-sm h-100 text-center d-flex flex-column align-items-center justify-content-center p-3">
                    <div id="pvPreviewImg" class="bg-light rounded mb-2 d-flex align-items-center justify-content-center border product-zoom-container" style="width: 100px; height: 100px; overflow: hidden;">
                        <i class="fas fa-image fs-1 text-muted opacity-25"></i>
                    </div>
                    <div id="pvPreviewName" class="extra-small fw-bold text-uppercase text-muted" style="display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; min-height: 2.8em; line-height: 1.4em;">Aguardando...</div>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm flex-grow-1 overflow-auto">
            <div class="card-header bg-white py-3">
                <h6 class="mb-0 fw-bold text-secondary"><i class="fas fa-file-invoice me-2"></i>Itens do Orçamento</h6>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" id="pvCartTable">
                        <thead class="bg-light sticky-top">
                            <tr>
                                <th class="ps-4" width="40">#</th>
                                <th width="100">Cód. Interno</th>
                                <th>Produto</th>
                                <th class="text-center" width="120">Qtd</th>
                                <th class="text-end" width="120">Unitário</th>
                                <th class="text-end" width="120">Subtotal</th>
                                <th class="text-center" width="60"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Items injected here -->
                        </tbody>
                    </table>
                </div>
                <!-- Empty state -->
                <div id="pvCartEmptyState" class="text-center py-5 text-muted">
                    <i class="fas fa-hammer fs-1 d-block mb-3 opacity-25"></i>
                    Selecione os materiais para compor o orçamento.
                </div>
            </div>
        </div>
    </div>

    <!-- Right Side: Quote Summary -->
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm h-100 d-flex flex-column">
            <div class="card-header bg-secondary text-white py-3">
                <h5 class="mb-0 fw-bold"><i class="fas fa-check-double me-2"></i>Finalizar Pré-Venda</h5>
            </div>
            <div class="card-body flex-grow-1">
                <div class="mb-4">
                    <label class="form-label small fw-bold text-uppercase text-muted">Cliente</label>
                    <div class="input-group mb-2">
                        <span class="input-group-text bg-light border-end-0"><i class="fas fa-user text-muted"></i></span>
                        <select id="pv_cliente_id" class="form-select bg-light border-start-0" onchange="toggleManualName(this.value)">
                            <option value="">CONSUMIDOR FINAL / NOME MANUAL</option>
                            <!-- Searchable list could be here -->
                        </select>
                        <button class="btn btn-outline-success fw-bold px-3" type="button" onclick="abrirModalQuickClient()" title="Cadastro Rápido de Cliente">
                            <i class="fas fa-plus"></i>
                        </button>
                    </div>
                    <div id="manual_name_container" class="mt-2 position-relative">
                        <input type="text" id="pv_nome_cliente_avulso" class="form-control mb-2" placeholder="Digite o nome do cliente avulso..." autocomplete="off">
                        <input type="text" id="pv_cpf_cliente" class="form-control" placeholder="CPF/CNPJ do cliente avulso (opcional)..." autocomplete="off">
                        <div id="pv_client_results" class="list-group shadow d-none" style="position: absolute; top: 100%; left: 0; z-index: 1050; width: 100%; max-height: 200px; overflow-y: auto;"></div>
                    </div>
                </div>

                <div class="bg-light p-4 rounded-3 border mb-4">
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Subtotal</span>
                        <span class="fw-bold" id="pv_subtotal">R$ 0,00</span>
                    </div>
                    <hr>
                    <div class="d-flex justify-content-between align-items-center">
                        <h4 class="mb-0 fw-bold">TOTAL</h4>
                        <h2 class="mb-0 fw-bold text-secondary" id="pv_total">R$ 0,00</h2>
                    </div>
                </div>

                <div class="alert border-0 shadow-sm small mb-4" style="background-color: rgba(56, 189, 248, 0.1); border: 1px solid rgba(56, 189, 248, 0.3) !important; color: #38bdf8;">
                    <i class="fas fa-info-circle me-2"></i>
                    A pré-venda reserva o estoque temporariamente e gera um código para o caixa.
                </div>

                <button class="btn btn-lg w-100 py-3 fw-bold shadow-sm border-0 text-white mb-2" style="background-color: var(--erp-primary) !important;" onclick="generatePreSale(false)">
                    <i class="fas fa-check-circle me-2"></i>CONFIRMAR PRÉ-VENDA (F9)
                </button>
                <div class="row g-2 mb-2">
                    <div class="col-6">
                        <button class="btn btn-outline-success fw-bold w-100 py-2 shadow-sm d-flex align-items-center justify-content-center" onclick="generatePreSale(true)" id="btnSavePvOrcamento" title="Salvar Orçamento (F3)">
                            <i class="fas fa-file-invoice me-2"></i><span class="small ms-1">Orçamento</span> (F3)
                        </button>
                    </div>
                    <div class="col-6">
                        <button class="btn btn-outline-dark fw-bold w-100 py-2 shadow-sm d-flex align-items-center justify-content-center" onclick="loadPendingOrcamentosModal()" title="Listar Orçamentos (F8)">
                            <i class="fas fa-list-alt me-2"></i><span class="small ms-1">Listar Orç.</span> (F8)
                        </button>
                    </div>
                </div>
                <button class="btn btn-outline-warning fw-bold w-100 py-2 shadow-sm mb-2 d-flex align-items-center justify-content-center" onclick="openSearchSalesModalFromPV()" title="Pesquisar Venda (F5)" id="btnPVSearchSales">
                    <i class="fas fa-search-dollar me-2"></i>BUSCAR VENDA (F5)
                </button>
                <button class="btn btn-outline-danger w-100 py-2 fw-bold shadow-sm border-0" onclick="if(confirm('Tem certeza que deseja cancelar o orçamento atual e limpar a tela?')) location.reload()">
                    <i class="fas fa-times me-2"></i>CANCELAR E LIMPAR
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Success Modal -->
<div class="modal fade" id="modal-pv-success" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-body text-center p-5">
                <div class="mb-4">
                    <i class="fas fa-check-circle text-success" style="font-size: 5rem;"></i>
                </div>
                <h2 class="fw-bold mb-2">PRÉ-VENDA GERADA!</h2>
                <p class="text-muted mb-4">Informe este código no caixa para concluir a venda:</p>
                
                <div class="bg-light border border-2 border-dashed p-4 rounded-4 mb-4">
                    <h1 class="display-4 fw-bold text-primary mb-0" id="pv_generated_code">PV-0000</h1>
                </div>

                <div class="d-grid gap-2">
                    <button class="btn btn-primary btn-lg fw-bold" onclick="printPVSlip()">
                        <i class="fas fa-print me-2"></i>IMPRIMIR FICHA
                    </button>
                    <button class="btn btn-outline-secondary" onclick="location.reload()">
                        NOVA PRÉ-VENDA
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Pending Orcamentos -->
<div class="modal fade" id="modalPendingOrcamentos" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-success text-white border-0 shadow-sm">
                <h5 class="modal-title fw-bold text-white"><i class="fas fa-file-invoice me-2 text-white"></i>Orçamentos Salvos (Validade 24h)</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th class="ps-4">Código</th>
                                <th>Cliente</th>
                                <th>Valor</th>
                                <th>Criado Em</th>
                                <th>Validade</th>
                                <th class="text-end pe-4">Ação</th>
                            </tr>
                        </thead>
                        <tbody id="listPendingOrcamentos">
                            <!-- Injected via JS -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
let currentPvId = null;
let currentPvCode = null;
let pvCart = [];
let pvSearchIndex = -1;
let currentPvSearchResults = [];
const pvSearchInput = document.getElementById('pv_product_search');
const pvSearchResults = document.getElementById('pv_search_results');
const pvCartTable = document.getElementById('pvCartTable').querySelector('tbody');
const pvCartEmptyState = document.getElementById('pvCartEmptyState');
const pvTotal = document.getElementById('pv_total');
const pvSubtotal = document.getElementById('pv_subtotal');
const pvPreviewImg = document.getElementById('pvPreviewImg');
const pvPreviewName = document.getElementById('pvPreviewName');

pvSearchInput.addEventListener('input', async (e) => {
    const term = e.target.value;
    if (term.length < 2) {
        pvSearchResults.classList.add('d-none');
        currentPvSearchResults = [];
        pvSearchIndex = -1;
        return;
    }

    const response = await fetch(`vendas.php?action=search&term=${term}`);
    const products = await response.json();
    
    renderPVSearchResults(products);
});

pvSearchInput.addEventListener('keydown', (e) => {
    if (e.key === '*') {
        e.preventDefault();
        document.getElementById('pvQty').focus();
        document.getElementById('pvQty').select();
        return;
    }

    const items = pvSearchResults.querySelectorAll('.list-group-item');
    if (items.length === 0) return;

    if (e.key === 'ArrowDown') {
        e.preventDefault();
        pvSearchIndex = Math.min(pvSearchIndex + 1, items.length - 1);
        highlightPvSearchResult(items);
    } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        pvSearchIndex = Math.max(pvSearchIndex - 1, -1);
        highlightPvSearchResult(items);
    } else if (e.key === 'Enter') {
        e.preventDefault();
        if (pvSearchIndex === -1 && items.length > 0) {
            pvSearchIndex = 0;
        }
        if (pvSearchIndex >= 0) {
            items[pvSearchIndex].click();
        }
    } else if (e.key === 'Escape') {
        pvSearchResults.classList.add('d-none');
        pvSearchIndex = -1;
    }
});

function highlightPvSearchResult(items) {
    items.forEach((item, idx) => {
        if (idx === pvSearchIndex) {
            item.classList.add('active');
            item.scrollIntoView({ block: 'nearest' });
            // Show preview for the selected item
            if (currentPvSearchResults[idx]) {
                showPvPreview(currentPvSearchResults[idx]);
            }
        } else {
            item.classList.remove('active');
        }
    });

    if (pvSearchIndex === -1) {
        pvPreviewImg.innerHTML = `<i class="fas fa-image fs-1 text-muted opacity-25"></i>`;
        pvPreviewName.innerText = 'Aguardando...';
    }
}

function renderPVSearchResults(products) {
    pvSearchResults.innerHTML = '';
    currentPvSearchResults = products;
    pvSearchIndex = -1;

    if (products.length === 0) {
        pvSearchResults.classList.add('d-none');
        return;
    }

    products.forEach(p => {
        const item = document.createElement('button');
        item.className = 'list-group-item list-group-item-action d-flex align-items-center justify-content-between py-3';
        
        const isPV = p.type === 'pre_sale';
        const isOrcamento = isPV && p.codigo && p.codigo.startsWith('ORC-');
        
        let icon = 'fa-box text-primary';
        let badge = '';
        let titleHtml = `<div class="fw-bold text-primary">${p.nome}</div>`;
        let subTextHtml = `<small class="text-muted">Cód: ${p.id} | Un: ${p.unidade}</small>`;
        let actionText = '';
        let clickHandler = () => selectForPVQty(p);

        if (isOrcamento) {
            icon = 'fa-file-invoice text-success';
            
            // Check budget validity (24 hours)
            const createdAt = new Date(p.created_at.replace(/-/g, "/"));
            const now = new Date();
            const diffHours = (now - createdAt) / (1000 * 60 * 60);
            const isValid = diffHours < 24;
            
            const validityText = isValid ? 'Validade 24h' : 'Orçamento Inválido';
            const validityColor = isValid ? 'text-success' : 'text-danger';
            
            titleHtml = `<div class="fw-bold text-success" style="font-weight: 900; font-size: 1.05em;">ORÇAMENTO</div>`;
            subTextHtml = `
                <div class="fw-bold ${validityColor}" style="font-weight: bold; margin-top: 2px;">${validityText}</div>
                <small class="text-muted">Nº: ${p.codigo} | Cliente: ${p.cliente_nome || p.nome}</small>
            `;
            actionText = isValid ? '<small class="text-success extra-small fw-bold">CLIQUE PARA CARREGAR</small>' : '<small class="text-danger extra-small fw-bold">EXPIRADO</small>';
            
            clickHandler = (e) => {
                e.preventDefault();
                if (!isValid) {
                    alert("Este orçamento expirou e não pode ser importado.");
                    return;
                }
                loadPreSaleInPreSaleScreen(p.codigo);
                pvSearchInput.value = '';
                pvSearchResults.classList.add('d-none');
            };
        } else if (isPV) {
            icon = 'fa-file-invoice-dollar text-warning';
            badge = '<span class="badge bg-warning text-dark extra-small ms-2">PRÉ-VENDA</span>';
            titleHtml = `<div class="fw-bold text-warning">${p.nome} ${badge}</div>`;
            subTextHtml = `<small class="text-muted">Cód: ${p.codigo || p.id} | Un: ${p.unidade}</small>`;
            actionText = '<small class="text-success extra-small fw-bold">CLIQUE PARA CARREGAR</small>';
            
            clickHandler = (e) => {
                e.preventDefault();
                loadPreSaleInPreSaleScreen(p.codigo);
                pvSearchInput.value = '';
                pvSearchResults.classList.add('d-none');
            };
        }

        item.innerHTML = `
            <div class="d-flex align-items-center">
                <i class="fas ${icon} fs-4 me-3 opacity-75"></i>
                <div>
                    ${titleHtml}
                    ${subTextHtml}
                </div>
            </div>
            <div class="text-end">
                <div class="fw-bold">R$ ${parseFloat(p.preco_venda).toFixed(2).replace('.', ',')}</div>
                ${actionText}
            </div>
        `;
        
        if (isPV) {
            item.onclick = clickHandler;
        } else {
            item.onmouseover = () => showPvPreview(p);
            item.onclick = clickHandler;
        }
        pvSearchResults.appendChild(item);
    });
    pvSearchResults.classList.remove('d-none');
}

function showPvPreview(p) {
    if (p.imagens) {
        pvPreviewImg.innerHTML = `<img src="${resolveSmartProductImage(p.imagens)}" style="width:100%; height:100%; object-fit:contain; cursor:pointer;" class="fade-in" onclick="if(window.openLightbox) window.openLightbox(this.src)" onerror="smartSwapImg(this)">`;
    } else {
        pvPreviewImg.innerHTML = `<i class="fas fa-image fs-1 text-muted opacity-25"></i>`;
    }
    pvPreviewName.innerText = p.nome;
}

function resolveSmartProductImage(value) {
    const raw = String(value || '').trim();
    if (!raw) return '';
    let image = raw;
    try {
        const parsed = JSON.parse(raw);
        if (Array.isArray(parsed)) {
            for (const item of parsed) {
                if (typeof item === 'string' && item.trim()) {
                    image = item.trim();
                    break;
                }
                if (item && typeof item === 'object') {
                    const found = item.url || item.imagem || item.path;
                    if (typeof found === 'string' && found.trim()) {
                        image = found.trim();
                        break;
                    }
                }
            }
        } else if (parsed && typeof parsed === 'object') {
            const found = parsed.url || parsed.imagem || parsed.path;
            if (typeof found === 'string' && found.trim()) image = found.trim();
        }
    } catch (e) {
        image = raw.split(/[\r\n,;|]+/).map(s => s.trim()).find(Boolean) || raw;
    }
    if (/^(https?:)?\/\//i.test(image) || image.startsWith('data:') || image.startsWith('/')) return image;
    const cleaned = image.replace(/^\.\/+/, '').replace(/^\/+/, '');
    if (cleaned.includes('/')) return cleaned;
    return `public/uploads/produtos/${cleaned}`;
}

function smartSwapImg(img) {
    const src = img.getAttribute('src') || '';
    const m = src.match(/^(.*\/)?([^\/]+?)(?:\.([^.\/]+))?$/);
    if (!m) return;
    const prefix = m[1] || 'public/uploads/produtos/';
    const base = m[2];
    const currentExt = m[3] || '';
    const candidates = currentExt
        ? [currentExt.toLowerCase(), currentExt.toUpperCase(), currentExt]
        : ['jpg', 'JPG', 'jpeg', 'JPEG', 'png', 'PNG', 'webp', 'WEBP', 'gif', 'GIF', 'avif', 'AVIF'];
    let idx = parseInt(img.dataset.extIdx || '0', 10) + 1;
    const unique = [...new Set(candidates)];
    if (idx >= unique.length) return;
    img.dataset.extIdx = String(idx);
    img.src = `${prefix}${base}.${unique[idx]}`;
}

let pendingPvProduct = null;

function selectForPVQty(product) {
    pendingPvProduct = product;
    showPvPreview(product);
    const qtyInput = document.getElementById('pvQty');
    qtyInput.focus();
    qtyInput.select();
    pvSearchResults.classList.add('d-none');
}

function addToPVCart(product) {
    const qtyInput = document.getElementById('pvQty');
    const qtyToAdd = parseFloat(qtyInput.value) || 1;

    const existing = pvCart.find(i => i.id === product.id && (parseInt(product.preco_variavel) !== 1));
    if (existing) {
        existing.qty += qtyToAdd;
    } else {
        pvCart.push({
            id: product.id,
            nome: product.nome,
            codigo: product.codigo,
            price: parseFloat(product.preco_venda),
            price1: parseFloat(product.preco_venda),
            price2: parseFloat(product.preco_venda_2 || 0),
            price3: parseFloat(product.preco_venda_3 || 0),
            preco_variavel: parseInt(product.preco_variavel) === 1,
            price_tier: 1,
            qty: qtyToAdd,
            imagens: product.imagens
        });
    }
    
    pvSearchInput.value = '';
    qtyInput.value = 1;
    pvSearchResults.classList.add('d-none');
    renderPVCart();

    // Auto-focus price if it's variable
    if (parseInt(product.preco_variavel) === 1) {
        setTimeout(() => {
            const lastRow = pvCartTable.querySelector('tr:last-child');
            if (lastRow) {
                const priceInput = lastRow.querySelector('input[onchange*="updatePVPrice"]');
                if (priceInput) {
                    priceInput.focus();
                    priceInput.select();
                }
            }
        }, 100);
    } else {
        pvSearchInput.focus();
    }
}

function updatePVPrice(index, val) {
    pvCart[index].price = Math.max(0, parseFloat(val));
    renderPVCart();
}

function renderPVCart() {
    pvCartTable.innerHTML = '';
    let total = 0;

    if (pvCart.length === 0) {
        pvCartEmptyState.classList.remove('d-none');
    } else {
        pvCartEmptyState.classList.add('d-none');
    }

    pvCart.forEach((item, index) => {
        const subtotal = item.price * item.qty;
        total += subtotal;
        
        const row = document.createElement('tr');
        row.onmouseover = () => showPvPreview(item);
        row.innerHTML = `
            <td class="ps-4 fw-bold text-muted">${index + 1}</td>
            <td class="fw-bold text-muted small">${item.codigo || '#' + item.id}</td>
            <td>
                <div class="fw-bold">${item.nome}</div>
                ${!item.preco_variavel ? `
                <div class="mt-1">
                    <select class="form-select form-select-sm py-0 extra-small border-primary border-opacity-25" style="width: auto; height: 24px; font-size: 0.75rem;" onchange="changePVPriceTier(${index}, this.value)">
                        <option value="1" ${item.price_tier == 1 ? 'selected' : ''}>Preço 1 (R$ ${item.price1.toFixed(2).replace('.', ',')})</option>
                        <option value="2" ${item.price_tier == 2 ? 'selected' : ''}>Preço 2 (R$ ${item.price2.toFixed(2).replace('.', ',')})</option>
                        <option value="3" ${item.price_tier == 3 ? 'selected' : ''}>Preço 3 (R$ ${item.price3.toFixed(2).replace('.', ',')})</option>
                    </select>
                </div>` : ''}
            </td>
            <td class="text-center">
                <input type="number" class="form-control form-control-sm text-center mx-auto" style="width: 70px" value="${item.qty}" min="1" step="any" onchange="updatePVQty(${index}, this.value)">
            </td>
            <td class="text-end">
                ${item.preco_variavel ? 
                    `<input type="number" class="form-control form-control-sm text-end d-inline-block border-primary fw-bold" style="width: 90px" value="${item.price.toFixed(2)}" step="0.01" onchange="updatePVPrice(${index}, this.value)">` : 
                    `R$ ${item.price.toFixed(2).replace('.', ',')}`
                }
            </td>
            <td class="text-end fw-bold">R$ ${subtotal.toFixed(2).replace('.', ',')}</td>
            <td class="text-center">
                <button class="btn btn-sm btn-link text-danger p-0" onclick="removeFromPVCart(${index})"><i class="fas fa-times"></i></button>
            </td>
        `;
        pvCartTable.appendChild(row);
    });

    const totalStr = `R$ ${total.toFixed(2).replace('.', ',')}`;
    pvTotal.innerText = totalStr;
    pvSubtotal.innerText = totalStr;
}

function updatePVQty(index, val) {
    pvCart[index].qty = Math.max(1, parseFloat(val));
    renderPVCart();
}

function changePVPriceTier(index, tier) {
    const item = pvCart[index];
    item.price_tier = parseInt(tier);
    
    if (tier == 1) item.price = item.price1;
    else if (tier == 2) item.price = item.price2;
    else if (tier == 3) item.price = item.price3;
    
    renderPVCart();
}

function removeFromPVCart(index) {
    pvCart.splice(index, 1);
    renderPVCart();
}

async function generatePreSale(isOrcamento = false) {
    if (pvCart.length === 0) {
        alert("O carrinho está vazio.");
        return;
    }
    
    const data = {
        id: currentPvId,
        codigo: currentPvCode,
        cliente_id: document.getElementById('pv_cliente_id').value || null,
        nome_cliente_avulso: document.getElementById('pv_nome_cliente_avulso').value || null,
        cpf_cliente: document.getElementById('pv_cpf_cliente').value || null,
        items: pvCart,
        valor_total: pvCart.reduce((acc, i) => acc + (i.price * i.qty), 0),
        is_orcamento: isOrcamento
    };

    try {
        const res = await fetch('pre_vendas.php?action=save', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });

        const result = await res.json();
        if (result.success) {
            if (isOrcamento) {
                alert(`Orçamento gerado com sucesso!\nCódigo: ${result.codigo}`);
                chooseOrcamentoPrintFormat(result.codigo, true);
            } else {
                document.getElementById('pv_generated_code').innerText = result.codigo;
                const modal = new bootstrap.Modal(document.getElementById('modal-pv-success'));
                modal.show();
            }
        } else {
            alert('Erro ao processar: ' + (result.error || 'Erro desconhecido'));
        }
    } catch (err) {
        console.error("Erro ao salvar pré-venda/orçamento:", err);
        alert("Erro de conexão ao salvar.");
    }
}

async function loadPendingOrcamentosModal() {
    console.log("Pré-Venda: Carregando orçamentos pendentes...");
    const term = '';
    
    try {
        const res = await fetch(`pre_vendas.php?action=list_pending&tipo=orcamento&term=${encodeURIComponent(term)}`);
        if (!res.ok) throw new Error("Falha ao comunicar com pre_vendas.php");
        
        const data = await res.json();
        const list = document.getElementById('listPendingOrcamentos');
        if (!list) return;
        
        list.innerHTML = '';

        if (data.error) {
            list.innerHTML = `<tr><td colspan="6" class="text-center py-4 text-danger">
                <i class="fas fa-exclamation-circle me-1"></i> Erro no servidor: ${data.error}
            </td></tr>`;
            return;
        }

        const pvs = Array.isArray(data) ? data : [];
        
        if (pvs.length === 0) {
            list.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-muted">Nenhum orçamento encontrado.</td></tr>';
        }

        pvs.forEach(pv => {
            const createdAt = new Date(pv.created_at.replace(/-/g, "/"));
            const now = new Date();
            const diffHours = (now - createdAt) / (1000 * 60 * 60);
            const isValid = diffHours < 24;
            const validityText = isValid ? 'Validade 24h' : 'Orçamento Inválido';
            const validityClass = isValid ? 'text-success fw-bold' : 'text-danger fw-bold';
            
            const row = document.createElement('tr');
            row.innerHTML = `
                <td class="ps-4 fw-bold text-success">${pv.codigo}</td>
                <td>${pv.cliente_nome || 'Consumidor Final'}</td>
                <td class="fw-bold">R$ ${parseFloat(pv.valor_total).toFixed(2).replace('.', ',')}</td>
                <td class="small text-muted">${new Date(pv.created_at).toLocaleString('pt-BR')}</td>
                <td class="${validityClass}">${validityText}</td>
                <td class="text-end pe-4 d-flex gap-2 justify-content-end">
                    <button class="btn btn-sm btn-success fw-bold" onclick="importOrcamento('${pv.codigo}', ${isValid})" ${!isValid ? 'disabled' : ''}>CARREGAR</button>
                    <button class="btn btn-sm btn-outline-primary" onclick="printOrcamento('${pv.codigo}')" title="Imprimir Orçamento">
                        <i class="fas fa-print"></i>
                    </button>
                    <button class="btn btn-sm btn-outline-danger" onclick="deleteOrcamento(${pv.id})" title="Excluir Orçamento">
                        <i class="fas fa-trash-alt"></i>
                    </button>
                </td>
            `;
            list.appendChild(row);
        });
        
        const modalEl = document.getElementById('modalPendingOrcamentos');
        if (modalEl) {
            const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
            modal.show();
        }
    } catch (err) {
        console.error("Pré-Venda: Erro ao carregar orçamentos:", err);
        alert("Erro ao carregar orçamentos. Verifique o console.");
    }
}

function printOrcamento(code) {
    if (typeof chooseOrcamentoPrintFormat === 'function') {
        // If the format chooser is available (e.g. loaded via sales.view.php), use it
        chooseOrcamentoPrintFormat(code);
    } else {
        // Fallback
        window.open('orcamento_imprimir.php?code=' + code, '_blank', 'width=400,height=600');
    }
}

function importOrcamento(code, isValid) {
    if (!isValid) {
        alert("Este orçamento expirou e não pode ser importado.");
        return;
    }
    loadPreSaleInPreSaleScreen(code);
    const modalInstance = bootstrap.Modal.getInstance(document.getElementById('modalPendingOrcamentos'));
    if (modalInstance) modalInstance.hide();
}

async function deleteOrcamento(id) {
    if (!confirm('Deseja realmente excluir este orçamento permanentemente?')) return;
    
    try {
        const res = await fetch(`pre_vendas.php?action=delete&id=${id}`);
        const data = await res.json();
        
        if (data.success) {
            loadPendingOrcamentosModal();
        } else {
            alert('Erro ao excluir: ' + (data.error || 'Erro desconhecido'));
        }
    } catch (err) {
        console.error("Pré-Venda: Erro ao excluir orçamento:", err);
        alert("Erro de conexão ao excluir.");
    }
}

async function loadPreSaleInPreSaleScreen(code) {
    const res = await fetch(`pre_vendas.php?action=get_by_code&code=${code}`);
    const pv = await res.json();
    
    if (pv) {
        pvCart = pv.itens.map(i => ({
            id: i.produto_id,
            nome: i.produto_nome,
            codigo: i.codigo,
            price: parseFloat(i.preco_unitario),
            price1: parseFloat(i.preco_venda || 0),
            price2: parseFloat(i.preco_venda_2 || 0),
            price3: parseFloat(i.preco_venda_3 || 0),
            preco_variavel: parseInt(i.preco_variavel) === 1,
            price_tier: parseInt(i.preco_tier || 1),
            qty: parseFloat(i.quantidade),
            imagens: i.imagens
        }));
        
        currentPvId = pv.id;
        currentPvCode = pv.codigo;
        
        // Load customer
        if (pv.cliente_id) {
            const select = document.getElementById('pv_cliente_id');
            let option = Array.from(select.options).find(o => o.value == pv.cliente_id);
            if (!option) {
                option = new Option(pv.cliente_nome, pv.cliente_id);
                select.add(option);
            }
            select.value = pv.cliente_id;
            toggleManualName(pv.cliente_id);
        } else if (pv.nome_cliente_avulso) {
            document.getElementById('pv_cliente_id').value = "";
            toggleManualName("");
            document.getElementById('pv_nome_cliente_avulso').value = pv.nome_cliente_avulso;
            document.getElementById('pv_cpf_cliente').value = pv.cliente_doc || "";
        } else {
            document.getElementById('pv_cliente_id').value = "";
            toggleManualName("");
            document.getElementById('pv_nome_cliente_avulso').value = "";
            document.getElementById('pv_cpf_cliente').value = "";
        }
        
        renderPVCart();
    }
}

function printPVSlip() {
    const code = document.getElementById('pv_generated_code').innerText;
    window.open('pre_venda_imprimir.php?code=' + code, '_blank', 'width=400,height=600');
}

document.addEventListener('keydown', (e) => {
    if (e.key === 'F9') {
        e.preventDefault();
        generatePreSale(false);
    }
    if (e.key === 'F3') {
        e.preventDefault();
        generatePreSale(true);
    }
    if (e.key === 'F8') {
        e.preventDefault();
        loadPendingOrcamentosModal();
    }
    if (e.key === 'F4') {
        e.preventDefault();
        pvSearchInput.focus();
        pvSearchInput.select();
    }
    if (e.key === 'F5') {
        e.preventDefault();
        openSearchSalesModalFromPV();
    }
});

// If user presses Enter in Qty, focus search
document.getElementById('pvQty').addEventListener('keydown', (e) => {
    if (e.key === 'Enter') {
        e.preventDefault();
        if (pendingPvProduct) {
            addToPVCart(pendingPvProduct);
            pendingPvProduct = null;
        }
        pvSearchInput.focus();
    }
});
function toggleManualName(val) {
    const container = document.getElementById('manual_name_container');
    const input = document.getElementById('pv_nome_cliente_avulso');
    if (val === "") {
        container.classList.remove('d-none');
    } else {
        container.classList.add('d-none');
        input.value = "";
    }
}

// Client search in Pre-Sale (Manual field)
const pvClientSearch = document.getElementById('pv_nome_cliente_avulso');
const pvClientResults = document.getElementById('pv_client_results');
const pvClientSelect = document.getElementById('pv_cliente_id');

pvClientSearch.addEventListener('input', async (e) => {
    const term = e.target.value;
    if (term.length < 2) {
        pvClientResults.classList.add('d-none');
        return;
    }

    try {
        const res = await fetch(`vendas.php?action=search_clients&term=${encodeURIComponent(term)}`);
        const clients = await res.json();
        renderPVClientResults(clients);
    } catch (err) {
        console.error("Erro busca cliente:", err);
    }
});

pvClientSearch.addEventListener('keydown', (e) => {
    if (e.key === 'Enter') {
        const term = e.target.value.trim();
        const cleanTerm = term.replace(/\D/g, '');
        // If it looks like a document, just leave it as manual name/doc
        if (cleanTerm.length === 11 || cleanTerm.length === 14) {
            // Do nothing, just let it be a manual name
        }
    }
});

function renderPVClientResults(clients) {
    pvClientResults.innerHTML = '';
    if (clients.length === 0) {
        pvClientResults.classList.add('d-none');
        return;
    }

    clients.forEach(c => {
        const item = document.createElement('button');
        item.className = 'list-group-item list-group-item-action py-2 small';
        item.innerHTML = `<strong>${c.nome}</strong><br><span class="text-muted extra-small">${c.doc || ''}</span>`;
        item.onclick = (e) => {
            e.preventDefault();
            selectClientInPV(c);
        };
        pvClientResults.appendChild(item);
    });
    pvClientResults.classList.remove('d-none');
}

function selectClientInPV(client) {
    // 1. Check if client exists in select, if not add it temporarily
    let option = Array.from(pvClientSelect.options).find(o => o.value == client.id);
    if (!option) {
        option = new Option(client.nome, client.id);
        pvClientSelect.add(option);
    }
    
    // 2. Select it
    pvClientSelect.value = client.id;
    
    // 3. UI Update (hide manual field)
    toggleManualName(client.id);
    pvClientResults.classList.add('d-none');
}

// Close dropdown on click outside
document.addEventListener('click', (e) => {
    if (!pvClientSearch.contains(e.target) && !pvClientResults.contains(e.target)) {
        pvClientResults.classList.add('d-none');
    }
});

function abrirModalQuickClient() {
    document.getElementById('qc_nome').value = '';
    document.getElementById('qc_razao_social').value = '';
    document.getElementById('qc_cpf_cnpj').value = '';
    document.getElementById('qc_telefone').value = '';
    document.getElementById('qc_endereco').value = '';
    document.getElementById('qc_cep').value = '';
    document.getElementById('qc_banco_agencia').value = '';
    document.getElementById('qc_banco_cc').value = '';
    new bootstrap.Modal(document.getElementById('modalQuickClient')).show();
}

async function salvarQuickClient() {
    const nome = document.getElementById('qc_nome').value;
    const razao_social = document.getElementById('qc_razao_social').value;
    const cpf_cnpj = document.getElementById('qc_cpf_cnpj').value;
    const telefone = document.getElementById('qc_telefone').value;
    const endereco = document.getElementById('qc_endereco').value;
    const cep = document.getElementById('qc_cep').value;
    const banco_agencia = document.getElementById('qc_banco_agencia').value;
    const banco_cc = document.getElementById('qc_banco_cc').value;

    if (!nome) return alert('O nome é obrigatório.');

    const btn = event.currentTarget || document.querySelector('button[onclick="salvarQuickClient()"]');
    const originalText = btn?.innerHTML || 'SALVAR';
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Salvando...';
    }

    try {
        const res = await fetch('vendas.php?action=quick_register_client', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ nome, razao_social, cpf_cnpj, telefone, endereco, cep, banco_agencia, banco_cc })
        });

        const result = await res.json();
        if (result.success) {
            selectClientInPV({ id: result.client_id, nome: nome, doc: cpf_cnpj });
            const modal = bootstrap.Modal.getInstance(document.getElementById('modalQuickClient'));
            if (modal) modal.hide();
        } else {
            alert('Erro ao cadastrar: ' + result.error);
        }
    } catch (err) {
        alert('Erro de conexão: ' + err.message);
    } finally {
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = originalText;
        }
    }
}
</script>

<!-- Modal: Cadastro Rápido de Cliente -->
<div class="modal fade" id="modalQuickClient" tabindex="-1" style="z-index: 1080;">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-primary text-white border-0 shadow-sm">
                <h6 class="modal-title fw-bold text-white"><i class="fas fa-user-plus me-2 text-white"></i>Cadastro Rápido de Cliente</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="mb-3">
                    <label class="form-label extra-small fw-bold text-uppercase opacity-75">Nome Completo / Nome Fantasia *</label>
                    <input type="text" id="qc_nome" class="form-control" placeholder="Ex: João da Silva">
                </div>
                <div class="mb-3">
                    <label class="form-label extra-small fw-bold text-uppercase opacity-75">Razão Social</label>
                    <input type="text" id="qc_razao_social" class="form-control" placeholder="Ex: João da Silva ME">
                </div>
                <div class="row mb-3">
                    <div class="col-6">
                        <label class="form-label extra-small fw-bold text-uppercase opacity-75">CPF / CNPJ</label>
                        <input type="text" id="qc_cpf_cnpj" class="form-control" placeholder="000.000.000-00">
                    </div>
                    <div class="col-6">
                        <label class="form-label extra-small fw-bold text-uppercase opacity-75">Telefone</label>
                        <input type="text" id="qc_telefone" class="form-control" placeholder="(00) 00000-0000">
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-8">
                        <label class="form-label extra-small fw-bold text-uppercase opacity-75">Endereço</label>
                        <input type="text" id="qc_endereco" class="form-control" placeholder="Rua, Número, Bairro...">
                    </div>
                    <div class="col-4">
                        <label class="form-label extra-small fw-bold text-uppercase opacity-75">CEP</label>
                        <input type="text" id="qc_cep" class="form-control" placeholder="69000-000">
                    </div>
                </div>
                <div class="row mb-4">
                    <div class="col-6">
                        <label class="form-label extra-small fw-bold text-uppercase opacity-75">Banco / Agência</label>
                        <input type="text" id="qc_banco_agencia" class="form-control" placeholder="Ex: Sicoob - 0002">
                    </div>
                    <div class="col-6">
                        <label class="form-label extra-small fw-bold text-uppercase opacity-75">Conta Corrente (C/C)</label>
                        <input type="text" id="qc_banco_cc" class="form-control" placeholder="Ex: 91103-7">
                    </div>
                </div>
                <div class="d-grid">
                    <button class="btn btn-primary fw-bold py-3 shadow-sm" onclick="salvarQuickClient()">
                        CADASTRAR E SELECIONAR
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Escolher Formato de Impressão -->
<div class="modal fade" id="modalChoosePrintFormat" tabindex="-1" style="z-index: 1090;">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-primary text-white border-0 shadow-sm">
                <h6 class="modal-title fw-bold text-white"><i class="fas fa-print me-2 text-white"></i>Formato de Impressão</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4 text-center">
                <p class="text-muted mb-4">Selecione o formato desejado para a impressão do orçamento:</p>
                <div class="row g-3">
                    <div class="col-6">
                        <button class="btn btn-outline-secondary w-100 py-3 fw-bold" onclick="printOrcamentoFormat('cupom')">
                            <i class="fas fa-receipt d-block fs-3 mb-2"></i>
                            CUPOM (BOBINA)
                        </button>
                    </div>
                    <div class="col-6">
                        <button class="btn btn-primary w-100 py-3 fw-bold text-white" onclick="printOrcamentoFormat('A4')">
                            <i class="fas fa-file-invoice d-block fs-3 mb-2 text-white"></i>
                            A4 (COMPLETO)
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Pesquisar Vendas (Histórico) -->
<div class="modal fade" id="modalSearchSales" tabindex="-1" style="z-index: 1070;">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-primary text-white border-0 shadow-sm">
                <h5 class="modal-title fw-bold text-white" style="color: #ffffff !important;"><i class="fas fa-search me-2 text-white" style="color: #ffffff !important;"></i>Pesquisar Venda</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4 bg-light">
                <div class="row g-2 mb-3">
                    <div class="col-md-8">
                        <input type="text" id="searchSalesInput" class="form-control form-control-lg" placeholder="Buscar por código, cliente ou CPF..." onkeyup="if(event.key==='Enter') searchSalesList(1)">
                    </div>
                    <div class="col-md-4">
                        <button class="btn btn-primary btn-lg w-100 fw-bold" onclick="searchSalesList(1)"><i class="fas fa-search me-2"></i>BUSCAR</button>
                    </div>
                </div>
                <div class="table-responsive bg-white border rounded shadow-sm">
                    <table class="table table-hover table-striped align-middle mb-0">
                        <thead class="table-dark">
                            <tr>
                                <th>Cód.</th>
                                <th>Data</th>
                                <th>Cliente</th>
                                <th>Valor</th>
                                <th>Tipo</th>
                                <th>Status</th>
                                <th class="text-center">Ações</th>
                            </tr>
                        </thead>
                        <tbody id="searchSalesTbody">
                            <tr><td colspan="7" class="text-center py-4 text-muted">Digite para buscar...</td></tr>
                        </tbody>
                    </table>
                </div>
                <div id="searchSalesPagination" class="mt-3 d-flex justify-content-center"></div>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Sale Management -->
<div class="modal fade" id="modalSaleManager" tabindex="-1" style="z-index: 1080;">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-primary text-white border-0 shadow-sm">
                <h5 class="modal-title fw-bold text-white" style="color: #ffffff !important;"><i class="fas fa-cash-register me-2 text-white" style="color: #ffffff !important;"></i>Gestão de Venda <span class="text-white" style="color: #ffffff !important;">#</span><span id="manageSaleId" class="text-white" style="color: #ffffff !important;"></span></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="bg-light p-3 rounded mb-3 border">
                    <div class="d-flex justify-content-between mb-1">
                        <span class="text-muted small">Cliente:</span>
                        <span class="fw-bold" id="manageSaleCustomer"></span>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span class="text-muted small">Valor Total:</span>
                        <span class="fw-bold text-primary fs-5" id="manageSaleTotal"></span>
                    </div>
                </div>
                
                <div class="d-grid gap-2">
                    <button class="btn btn-outline-primary fw-bold py-3 shadow-sm hover-shadow" onclick="imprimirCupom(activeManageId)">
                        <i class="fas fa-receipt me-2"></i>IMPRIMIR CUPOM
                    </button>
                    <button class="btn btn-outline-info fw-bold py-3 shadow-sm hover-shadow" id="btnManageA4" onclick="imprimirA4(activeManageId)" style="display: none;">
                        <i class="fas fa-file-invoice me-2"></i>IMPRIMIR A4
                    </button>
                    <button class="btn btn-outline-success fw-bold py-3 shadow-sm hover-shadow" id="btnManageDanfe" onclick="imprimirDanfe(activeManageId)" style="display: none;">
                        <i class="fas fa-file-invoice-dollar me-2"></i>IMPRIMIR DANFE (NFC-e)
                    </button>
                    <hr>
                    <button class="btn btn-outline-danger fw-bold py-3 shadow-sm hover-shadow" onclick="cancelSaleAction()">
                        <i class="fas fa-trash-alt me-2"></i>CANCELAR VENDA (ESTORNO)
                    </button>
                    <button class="btn btn-outline-secondary fw-bold py-3 shadow-sm hover-shadow" onclick="openExchangeFlow()">
                        <i class="fas fa-exchange-alt me-2"></i>SOLICITAR TROCA
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Cancelamento Triplo (PDV) -->
<div class="modal fade" id="modalTripleCancel" tabindex="-1" style="z-index: 1090;">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0 shadow-lg overflow-hidden">
            <div class="modal-header bg-danger text-white border-0 py-3 shadow-sm">
                <h5 class="modal-title fw-bold text-white" style="color: #ffffff !important;"><i class="fas fa-exclamation-triangle me-2 text-white" style="color: #ffffff !important;"></i>Cancelar Venda <span class="text-white" style="color: #ffffff !important;">#</span><span id="cancel-id-label" class="text-white" style="color: #ffffff !important;"></span></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <!-- Passo 1: Escolha do Modelo -->
                <div id="cancel-step-1">
                    <p class="text-muted mb-4 uppercase small fw-bold">Como deseja cancelar esta venda?</p>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <div class="cancel-choice-card p-3 border rounded text-center h-100 cursor-pointer hover-shadow" onclick="selectCancelMode('por_chave')" style="transition: all 0.3s ease;">
                                <div class="icon mb-2 text-danger"><i class="fas fa-file-invoice-dollar fa-2x"></i></div>
                                <div class="fw-bold small">Padrão (110111)</div>
                                <p class="extra-small text-muted mb-0 mt-1">Cancela a nota autorizada normalmente na SEFAZ.</p>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="cancel-choice-card p-3 border rounded text-center h-100 cursor-pointer hover-shadow" onclick="selectCancelMode('por_substituicao')" style="transition: all 0.3s ease;">
                                <div class="icon mb-2 text-primary"><i class="fas fa-sync-alt fa-2x"></i></div>
                                <div class="fw-bold small">Substituição (110112)</div>
                                <p class="extra-small text-muted mb-0 mt-1">Cancela vinculando a uma nota de contingência.</p>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="cancel-choice-card p-3 border rounded text-center h-100 cursor-pointer hover-shadow" onclick="selectCancelMode('por_motivo')" style="transition: all 0.3s ease;">
                                <div class="icon mb-2 text-secondary"><i class="fas fa-database fa-2x"></i></div>
                                <div class="fw-bold small">Apenas Sistema</div>
                                <p class="extra-small text-muted mb-0 mt-1">Cancela internamente sem comunicar a SEFAZ.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Passo 2: Formulário -->
                <div id="cancel-step-2" class="d-none">
                    <button type="button" class="btn btn-link btn-sm p-0 mb-3 text-muted text-decoration-none" onclick="backToCancelChoices()">
                        <i class="fas fa-arrow-left me-1"></i> Voltar para opções
                    </button>
                    
                    <div class="mb-3 d-none" id="field-chave-substituta">
                        <label class="form-label fw-bold small">Chave da Nota Substituta (44 dígitos)</label>
                        <input type="text" id="cancel-chave-subst" class="form-control fw-bold" maxlength="44" placeholder="0000 0000 0000 0000 0000...">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small" id="label-motivo">Motivo do Cancelamento</label>
                        <textarea id="cancel-motivo" class="form-control" rows="3" placeholder="Descreva o motivo..."></textarea>
                    </div>

                    <?php if (($_SESSION['usuario_nivel'] ?? '') !== 'admin'): ?>
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-danger"><i class="fas fa-lock me-1"></i>Senha ou Código de Autorização</label>
                        <input type="text" id="cancel-auth-code" class="form-control fw-bold text-center" placeholder="Senha ou Código (Ex: 123456)" maxlength="20" style="font-size: 1.1rem;">
                    </div>
                    <?php endif; ?>

                    <div id="fiscal-alert" class="alert alert-info small d-none">
                        <i class="fas fa-info-circle me-1"></i> <b>Nota Fiscal:</b> Este modelo exige validação da SEFAZ. O motivo deve ter 15+ caracteres.
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light border-0 d-none" id="cancel-footer-btns">
                <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">Fechar</button>
                <button type="button" id="confirmCancelBtn" class="btn btn-danger px-4 rounded-pill">Confirmar Cancelamento</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Exchange Flow -->
<div class="modal fade" id="modalExchangeFlow" tabindex="-1" style="z-index: 1090;">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-secondary text-white border-0 shadow-sm">
                <h5 class="modal-title fw-bold text-white" style="color: #ffffff !important;"><i class="fas fa-exchange-alt me-2 text-white" style="color: #ffffff !important;"></i>Solicitação de Troca (Venda <span class="text-white" style="color: #ffffff !important;">#</span><span id="exchangeSaleId" class="text-white" style="color: #ffffff !important;"></span>)</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <h6 class="fw-bold mb-3">1. Selecione o item que será DEVOLVIDO à loja</h6>
                <div class="list-group mb-4" id="exchangeItemsList">
                    <div class="text-center py-3 text-muted">Carregando itens...</div>
                </div>

                <div id="exchangeStep2" class="d-none">
                    <h6 class="fw-bold mb-3">2. Selecione o NOVO item que o cliente vai levar</h6>
                    <div class="input-group input-group-lg shadow-sm border rounded mb-2">
                        <span class="input-group-text bg-white border-end-0 text-muted">
                            <i class="fas fa-search"></i>
                        </span>
                        <input type="text" id="exchangeProductSearch" class="form-control border-start-0 ps-0" placeholder="Pesquisar novo produto...">
                    </div>
                    <div id="exchangeSearchResults" class="list-group shadow-sm" style="max-height: 200px; overflow-y: auto;"></div>
                </div>
                
                <div id="exchangeStep3" class="d-none mt-4 p-4 bg-light border rounded shadow-sm">
                    <h6 class="fw-bold text-center text-primary mb-4 text-uppercase">Resumo da Troca</h6>
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span class="text-danger fw-bold"><i class="fas fa-arrow-down me-2"></i>DEVOLVENDO:</span>
                        <span class="fw-bold text-end" id="exchangeOldName"></span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="text-success fw-bold"><i class="fas fa-arrow-up me-2"></i>LEVANDO (1 UN):</span>
                        <span class="fw-bold text-end" id="exchangeNewName"></span>
                    </div>
                    <hr class="my-4">
                    <div class="d-flex justify-content-between align-items-center bg-white p-3 border rounded">
                        <span class="text-muted fw-bold">Ajuste de caixa sugerido:</span>
                        <span class="fw-bold fs-4" id="exchangeDiff"></span>
                    </div>
                    
                    <div class="d-grid mt-4">
                        <button class="btn btn-primary btn-lg fw-bold shadow-sm py-3" onclick="confirmExchange()">
                            <i class="fas fa-check-circle me-2"></i>CONFIRMAR E PROCESSAR TROCA
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
let pendingPrintCode = null;
let shouldReloadAfterPrint = false;

// Global States for Sale Management/Search
let activeManageId = null;
let cameFromSearch = false;
let pendingManageSale = null;
let currentCancelModelo = 'por_chave';
const currentUserLevel = '<?= $_SESSION['usuario_id'] ? ($_SESSION['usuario_nivel'] ?? 'vendedor') : 'vendedor' ?>';
let exchangeState = {
    vendaId: null,
    oldItemId: null,
    oldItemName: null,
    oldItemPrice: 0,
    newProductId: null,
    newProductName: null,
    newProductPrice: 0
};

function chooseOrcamentoPrintFormat(code, reload = false) {
    pendingPrintCode = code;
    shouldReloadAfterPrint = reload;
    const modal = new bootstrap.Modal(document.getElementById('modalChoosePrintFormat'));
    modal.show();
}

function printOrcamentoFormat(type) {
    const modalEl = document.getElementById('modalChoosePrintFormat');
    const modal = bootstrap.Modal.getInstance(modalEl);
    if (modal) modal.hide();
    
    const w = type === 'A4' ? 900 : 400;
    const h = type === 'A4' ? 900 : 600;
    window.open('orcamento_imprimir.php?code=' + pendingPrintCode + '&type=' + type, '_blank', `width=${w},height=${h}`);
    
    if (shouldReloadAfterPrint) {
        setTimeout(() => {
            location.reload();
        }, 500);
    }
}

// Sale Search and Management functions in Pre-Sale View
function openSearchSalesModalFromPV() {
    openSearchSalesModal();
}

function openSearchSalesModal() {
    const modalSearchEl = document.getElementById('modalSearchSales');
    if (modalSearchEl) {
        bootstrap.Modal.getOrCreateInstance(modalSearchEl).show();
        searchSalesList(1);
    }
}

async function searchSalesList(page = 1) {
    const term = document.getElementById('searchSalesInput').value;
    const tbody = document.getElementById('searchSalesTbody');
    const pagination = document.getElementById('searchSalesPagination');
    
    tbody.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-primary"><span class="spinner-border spinner-border-sm me-2"></span>Buscando...</td></tr>';
    
    try {
        const res = await fetch(`vendas.php?action=sold_search&page=${page}&perPage=10&search=${encodeURIComponent(term)}`);
        if (!res.ok) throw new Error('Resposta inválida');
        const data = await res.json();
        if (!data || !data.sales) throw new Error('Dados inválidos');
        
        tbody.innerHTML = '';
        if (data.sales.length === 0) {
            tbody.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-muted">Nenhuma venda encontrada.</td></tr>';
            pagination.innerHTML = '';
            return;
        }
        
        data.sales.forEach(sale => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td class="fw-bold">#${sale.id}</td>
                <td>${sale.data_formatada}</td>
                <td>${sale.cliente_nome || 'Consumidor'}</td>
                <td class="text-primary fw-bold">R$ ${sale.valor_formatado}</td>
                <td><span class="badge bg-${sale.tipo_nota === 'fiscal' ? 'success' : 'secondary'}">${sale.tipo_nota.toUpperCase()}</span></td>
                <td><span class="badge bg-${sale.status === 'cancelado' ? 'danger' : 'success'}">${sale.status.toUpperCase()}</span></td>
                <td class="text-center">
                    <button class="btn btn-sm btn-primary" onclick="manageSale(${JSON.stringify(sale).replace(/"/g, '&quot;')})">Gerenciar</button>
                </td>
            `;
            tbody.appendChild(tr);
        });
        
        // Smart pagination with ellipses
        const currentPage = parseInt(data.page);
        const totalPages = parseInt(data.totalPages);
        const delta = 2; // pages to show on each side of current
        
        const range = [];
        const rangeWithDots = [];
        let l;

        for (let i = 1; i <= totalPages; i++) {
            if (i === 1 || i === totalPages || (i >= currentPage - delta && i <= currentPage + delta)) {
                range.push(i);
            }
        }
        for (let i of range) {
            if (l) {
                if (i - l === 2) rangeWithDots.push(l + 1);
                else if (i - l > 2) rangeWithDots.push('...');
            }
            rangeWithDots.push(i);
            l = i;
        }

        const pBtn = (p, label, disabled = false, active = false) =>
            `<li class="page-item ${disabled ? 'disabled' : ''} ${active ? 'active' : ''}">
                <a class="page-link" href="javascript:void(0)" ${!disabled ? `onclick="searchSalesList(${p})"` : ''}>${label}</a>
             </li>`;

        let pagHtml = '<ul class="pagination pagination-sm flex-wrap justify-content-center mb-0">';
        pagHtml += pBtn(currentPage - 1, '&laquo;', currentPage === 1);
        for (let p of rangeWithDots) {
            if (p === '...') {
                pagHtml += `<li class="page-item disabled"><span class="page-link">…</span></li>`;
            } else {
                pagHtml += pBtn(p, p, false, p === currentPage);
            }
        }
        pagHtml += pBtn(currentPage + 1, '&raquo;', currentPage === totalPages);
        pagHtml += `</ul>`;
        pagHtml += `<div class="text-center text-muted small mt-1">Página ${currentPage} de ${totalPages} (${data.total} vendas)</div>`;
        pagination.innerHTML = pagHtml;
        
    } catch (err) {
        tbody.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-danger">Erro de conexão.</td></tr>';
    }
}

function manageSale(sale) {
    const searchModalEl = document.getElementById('modalSearchSales');
    if (searchModalEl && searchModalEl.classList.contains('show')) {
        cameFromSearch = true;
        pendingManageSale = sale;
        const modalSearch = bootstrap.Modal.getOrCreateInstance(searchModalEl);
        if (modalSearch) {
            modalSearch.hide();
        }
    } else {
        cameFromSearch = false;
        pendingManageSale = null;

        activeManageId = sale.id;
        document.getElementById('manageSaleId').innerText = sale.id;
        document.getElementById('manageSaleCustomer').innerText = sale.cliente_nome || 'Consumidor Final';
        document.getElementById('manageSaleTotal').innerText = 'R$ ' + parseFloat(sale.valor_total).toFixed(2).replace('.', ',');
        
        const isFiscal = (sale.tipo_nota === 'fiscal') || (sale.nf_status && ['100','150'].includes(String(sale.nf_status)));

        const btnDanfe = document.getElementById('btnManageDanfe');
        if (btnDanfe) {
            btnDanfe.style.display = isFiscal ? 'block' : 'none';
        }

        const btnA4 = document.getElementById('btnManageA4');
        if (btnA4) {
            btnA4.style.display = isFiscal ? 'block' : 'none';
        }

        bootstrap.Modal.getOrCreateInstance(document.getElementById('modalSaleManager')).show();
    }
}

function imprimirCupom(id) {
    window.open('recibo_venda.php?id=' + id, '_blank', 'width=400,height=600');
}

function imprimirA4(id) {
    window.open('nfce/danfe_a4.php?venda_id=' + id, '_blank', 'width=900,height=900');
}

function imprimirDanfe(id) {
    window.open(`nfce/emitir.php?venda_id=${id}&imprimir=1`, '_blank', 'width=800,height=900');
}

function selectCancelMode(mode) {
    currentCancelModelo = mode;
    document.getElementById('cancel-step-1').classList.add('d-none');
    document.getElementById('cancel-step-2').classList.remove('d-none');
    document.getElementById('cancel-footer-btns').classList.remove('d-none');
    
    const fieldSubst = document.getElementById('field-chave-substituta');
    const alertFiscal = document.getElementById('fiscal-alert');
    const labelMotivo = document.getElementById('label-motivo');
    
    if (mode === 'por_substituicao') {
        fieldSubst.classList.remove('d-none');
        alertFiscal.classList.remove('d-none');
        labelMotivo.textContent = 'Motivo (Substituição)';
    } else if (mode === 'por_chave') {
        fieldSubst.classList.add('d-none');
        alertFiscal.classList.remove('d-none');
        labelMotivo.textContent = 'Motivo do Cancelamento';
    } else {
        fieldSubst.classList.add('d-none');
        alertFiscal.classList.add('d-none');
        labelMotivo.textContent = 'Motivo do Cancelamento (Interno)';
    }
}

function backToCancelChoices() {
    document.getElementById('cancel-step-1').classList.remove('d-none');
    document.getElementById('cancel-step-2').classList.add('d-none');
    document.getElementById('cancel-footer-btns').classList.add('d-none');
}

async function cancelSaleAction() {
    document.getElementById('cancel-id-label').textContent = activeManageId;
    document.getElementById('cancel-motivo').value = '';
    document.getElementById('cancel-chave-subst').value = '';
    
    const authInput = document.getElementById('cancel-auth-code');
    if (authInput) authInput.value = '';
    
    backToCancelChoices();
    bootstrap.Modal.getOrCreateInstance('#modalSaleManager').hide();
    bootstrap.Modal.getOrCreateInstance('#modalTripleCancel').show();
}

function loadRecentSales() {
    // No-op since Pre-Sale screen does not contain recent sales DOM listing elements
}

async function openExchangeFlow() {
    exchangeState.vendaId = activeManageId;
    document.getElementById('exchangeSaleId').innerText = activeManageId;
    
    bootstrap.Modal.getOrCreateInstance('#modalSaleManager').hide();
    bootstrap.Modal.getOrCreateInstance('#modalExchangeFlow').show();
    
    document.getElementById('exchangeStep2').classList.add('d-none');
    document.getElementById('exchangeStep3').classList.add('d-none');
    document.getElementById('exchangeProductSearch').value = '';
    document.getElementById('exchangeSearchResults').innerHTML = '';
    
    const res = await fetch(`vendas.php?action=get_sale_detail&id=${activeManageId}`);
    const data = await res.json();
    
    const list = document.getElementById('exchangeItemsList');
    list.innerHTML = '';
    
    if (!data.success || !data.sale || !data.sale.itens || data.sale.itens.length === 0) {
        list.innerHTML = '<div class="alert alert-warning text-center">Nenhum item encontrado nesta venda.</div>';
        return;
    }
    
    if(data.sale.status === 'cancelado') {
        list.innerHTML = '<div class="alert alert-danger text-center">Não é possível realizar troca em venda cancelada.</div>';
        return;
    }
    
    data.sale.itens.forEach(item => {
        const btn = document.createElement('button');
        btn.className = 'list-group-item list-group-item-action d-flex justify-content-between align-items-center py-3';
        btn.innerHTML = `
            <div>
                <div class="fw-bold">${item.produto_nome}</div>
                <small class="opacity-75">${item.quantidade}x R$ ${item.preco_formatado}</small>
            </div>
            <span class="btn btn-sm btn-outline-danger fw-bold px-3">DEVOLVER</span>
        `;
        btn.onclick = () => {
            Array.from(list.children).forEach(c => {
                c.classList.remove('active', 'bg-danger', 'text-white', 'border-danger');
                c.querySelector('.btn')?.classList.replace('btn-light', 'btn-outline-danger');
            });
            
            btn.classList.add('active', 'bg-danger', 'text-white', 'border-danger');
            btn.querySelector('.btn').classList.replace('btn-outline-danger', 'btn-light');
            
            exchangeState.oldItemId = item.id;
            exchangeState.oldItemName = item.produto_nome;
            exchangeState.oldItemPrice = parseFloat(item.preco_unitario); 
            
            document.getElementById('exchangeStep2').classList.remove('d-none');
            document.getElementById('exchangeStep3').classList.add('d-none');
            
            setTimeout(() => document.getElementById('exchangeProductSearch').focus(), 300);
        };
        list.appendChild(btn);
    });
}

async function confirmExchange() {
    if (!exchangeState.vendaId || !exchangeState.oldItemId || !exchangeState.newProductId) {
        return alert("Por favor, selecione qual item será devolvido e qual produto será pego no lugar.");
    }
    
    if (!confirm('Deseja realmente confirmar esta troca?\n\nIsso fará o ajuste automático no estoque e registrará as devidas diferenças financeiras.')) return;
    
    const res = await fetch('vendas.php?action=exchange_item', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            venda_id: exchangeState.vendaId,
            item_id: exchangeState.oldItemId,
            new_product_id: exchangeState.newProductId,
            new_qty: 1,
            new_price: exchangeState.newProductPrice
        })
    });
    
    const result = await res.json();
    if (result.success) {
        bootstrap.Modal.getInstance(document.getElementById('modalExchangeFlow')).hide();
        loadRecentSales();
        
        if (confirm("Troca registrada com sucesso!\n\nDeseja imprimir o comprovante de troca para o cliente?")) {
            imprimirTroca(result.exchange_id);
        }
    } else {
        alert("Erro ao tentar processar troca: " + result.error);
    }
}

function imprimirTroca(exchangeId) {
    const url = 'recibo_troca.php?id=' + exchangeId + '&t=' + Date.now();
    window.open(url, 'print_popup', 'width=400,height=600,toolbar=0,menubar=0,location=0');
}

// Listeners for multi-modal backflows
document.addEventListener('DOMContentLoaded', () => {
    const saleManagerEl = document.getElementById('modalSaleManager');
    if (saleManagerEl) {
        saleManagerEl.addEventListener('hidden.bs.modal', function () {
            setTimeout(() => {
                const openModals = document.querySelectorAll('.modal.show');
                if (cameFromSearch && openModals.length === 0) {
                    cameFromSearch = false;
                    const modalSearch = bootstrap.Modal.getOrCreateInstance(document.getElementById('modalSearchSales'));
                    if (modalSearch) {
                        modalSearch.show();
                    }
                }
            }, 150);
        });
    }

    const searchModalEl = document.getElementById('modalSearchSales');
    if (searchModalEl) {
        searchModalEl.addEventListener('hidden.bs.modal', function () {
            if (pendingManageSale) {
                const sale = pendingManageSale;
                pendingManageSale = null;

                activeManageId = sale.id;
                document.getElementById('manageSaleId').innerText = sale.id;
                document.getElementById('manageSaleCustomer').innerText = sale.cliente_nome || 'Consumidor Final';
                document.getElementById('manageSaleTotal').innerText = 'R$ ' + parseFloat(sale.valor_total).toFixed(2).replace('.', ',');
                
                const isFiscal = (sale.tipo_nota === 'fiscal') || (sale.nf_status && ['100','150'].includes(String(sale.nf_status)));

                const btnDanfe = document.getElementById('btnManageDanfe');
                if (btnDanfe) {
                    btnDanfe.style.display = isFiscal ? 'block' : 'none';
                }

                const btnA4 = document.getElementById('btnManageA4');
                if (btnA4) {
                    btnA4.style.display = isFiscal ? 'block' : 'none';
                }

                bootstrap.Modal.getOrCreateInstance(document.getElementById('modalSaleManager')).show();
            }
        });
    }

    const tripleCancelEl = document.getElementById('modalTripleCancel');
    if (tripleCancelEl) {
        tripleCancelEl.addEventListener('hidden.bs.modal', function () {
            setTimeout(() => {
                const openModals = document.querySelectorAll('.modal.show');
                if (cameFromSearch && openModals.length === 0) {
                    cameFromSearch = false;
                    const modalSearch = bootstrap.Modal.getOrCreateInstance(document.getElementById('modalSearchSales'));
                    if (modalSearch) {
                        modalSearch.show();
                    }
                }
            }, 150);
        });
    }

    const exchangeEl = document.getElementById('modalExchangeFlow');
    if (exchangeEl) {
        exchangeEl.addEventListener('hidden.bs.modal', function () {
            setTimeout(() => {
                const openModals = document.querySelectorAll('.modal.show');
                if (cameFromSearch && openModals.length === 0) {
                    cameFromSearch = false;
                    const modalSearch = bootstrap.Modal.getOrCreateInstance(document.getElementById('modalSearchSales'));
                    if (modalSearch) {
                        modalSearch.show();
                    }
                }
            }, 150);
        });
    }

    const confirmBtn = document.getElementById('confirmCancelBtn');
    if (confirmBtn) {
        confirmBtn.addEventListener('click', async function() {
            const motivo = document.getElementById('cancel-motivo').value.trim();
            const chaveSubst = document.getElementById('cancel-chave-subst').value.replace(/\D+/g, '');
            const authCodeEl = document.getElementById('cancel-auth-code');
            const authCode = authCodeEl ? authCodeEl.value.trim() : null;
            
            if (authCodeEl && !authCode) {
                alert('É necessário inserir o Código de Autorização fornecido pelo administrador.');
                return;
            }
            
            if (currentCancelModelo === 'por_substituicao' && chaveSubst.length !== 44) {
                alert('A chave substituta deve ter 44 dígitos.');
                return;
            }

            if (currentCancelModelo !== 'por_motivo' && motivo.length < 15) {
                alert('Para cancelamentos na SEFAZ, o motivo deve ter no mínimo 15 caracteres.');
                return;
            } else if (motivo.length < 5) {
                alert('Por favor, descreva o motivo do cancelamento.');
                return;
            }

            this.disabled = true;
            const originalText = this.innerHTML;
            this.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Processando...';

            try {
                const res = await fetch('vendas.php?action=cancel_sale', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({ 
                        id: activeManageId, 
                        motivo, 
                        modelo: currentCancelModelo,
                        chave_substituta: chaveSubst,
                        auth_code: authCode 
                    })
                });
                const data = await res.json();
                if (data.success) {
                    bootstrap.Modal.getOrCreateInstance('#modalTripleCancel').hide();
                    alert('Cancelamento processado com sucesso!');
                    loadRecentSales();
                } else {
                    alert('Erro: ' + data.error);
                }
            } catch (err) {
                alert('Erro de conexão ao cancelar venda.');
            } finally {
                this.disabled = false;
                this.innerHTML = originalText;
            }
        });
    }

    const exchangeSearchInput = document.getElementById('exchangeProductSearch');
    if (exchangeSearchInput) {
        exchangeSearchInput.addEventListener('input', async (e) => {
            const term = e.target.value;
            const resultsDiv = document.getElementById('exchangeSearchResults');
            if (term.length < 2) {
                resultsDiv.innerHTML = '';
                return;
            }

            const res = await fetch(`vendas.php?action=search&term=${encodeURIComponent(term)}`);
            const products = await res.json();
            
            resultsDiv.innerHTML = '';
            products.forEach(p => {
                if (p.type === 'pre_sale') return;
                
                const btn = document.createElement('button');
                btn.className = 'list-group-item list-group-item-action d-flex justify-content-between align-items-center py-3';
                btn.innerHTML = `
                    <div>
                        <div class="fw-bold text-primary">${p.nome}</div>
                        <small class="text-muted">Valor Unitário: R$ ${parseFloat(p.preco_venda).toFixed(2).replace('.', ',')}</small>
                    </div>
                    <i class="fas fa-check text-success fa-lg opacity-50"></i>
                `;
                btn.onclick = () => {
                    exchangeState.newProductId = p.id;
                    exchangeState.newProductName = p.nome;
                    exchangeState.newProductPrice = parseFloat(p.preco_venda);
                    
                    document.getElementById('exchangeOldName').innerText = exchangeState.oldItemName;
                    document.getElementById('exchangeNewName').innerText = exchangeState.newProductName;
                    
                    const diff = exchangeState.newProductPrice - exchangeState.oldItemPrice;
                    const diffEl = document.getElementById('exchangeDiff');
                    if (diff > 0) {
                        diffEl.innerHTML = `<span class="text-success"><i class="fas fa-plus me-1"></i>RECEBER R$ ${diff.toFixed(2).replace('.', ',')}</span>`;
                    } else if (diff < 0) {
                        diffEl.innerHTML = `<span class="text-danger"><i class="fas fa-minus me-1"></i>DEVOLVER R$ ${Math.abs(diff).toFixed(2).replace('.', ',')}</span>`;
                    } else {
                        diffEl.innerHTML = `<span class="text-secondary">R$ 0,00 (Tudo Certo)</span>`;
                    }
                    
                    document.getElementById('exchangeStep3').classList.remove('d-none');
                    resultsDiv.innerHTML = '';
                    document.getElementById('exchangeProductSearch').value = '';
                    
                    setTimeout(() => document.getElementById('exchangeStep3').scrollIntoView({behavior: 'smooth'}), 200);
                };
                resultsDiv.appendChild(btn);
            });
        });
    }
});
</script>
