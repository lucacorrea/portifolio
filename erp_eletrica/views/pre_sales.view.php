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
        pvPreviewImg.innerHTML = `<img src="public/uploads/produtos/${p.imagens}" style="width:100%; height:100%; object-fit:contain; cursor:pointer;" class="fade-in" onclick="if(window.openLightbox) window.openLightbox(this.src)">`;
    } else {
        pvPreviewImg.innerHTML = `<i class="fas fa-image fs-1 text-muted opacity-25"></i>`;
    }
    pvPreviewName.innerText = p.nome;
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
                window.open('orcamento_imprimir.php?code=' + result.codigo, '_blank', 'width=400,height=600');
                location.reload();
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
    window.open('orcamento_imprimir.php?code=' + code, '_blank', 'width=400,height=600');
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
</script>
