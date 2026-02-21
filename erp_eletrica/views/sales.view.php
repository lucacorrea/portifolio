<div class="row g-4">
    <!-- Left Side: Product selection & Preview -->
    <div class="col-lg-7 d-flex flex-column">
        <div class="row g-4 mb-4">
            <div class="col-md-8">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="input-group input-group-lg shadow-sm rounded">
                            <span class="input-group-text bg-white border-end-0 text-muted">
                                <i class="fas fa-search"></i>
                            </span>
                            <input type="text" id="pdvSearch" class="form-control border-start-0 ps-0" placeholder="Digite o nome ou código..." autocomplete="off">
                        </div>
                        <div id="searchResults" class="list-group mt-3 shadow-sm d-none" style="position: absolute; z-index: 1050; width: calc(100% - 3rem);">
                            <!-- Results will be injected here -->
                        </div>
                        <div class="mt-3 d-flex gap-2">
                             <button class="btn btn-outline-primary fw-bold" onclick="loadPendingPreSales()">
                                <i class="fas fa-file-import me-2"></i>Importar Pré-Venda
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Product Preview Pane -->
            <div class="col-md-4">
                <div class="card border-0 shadow-sm h-100 text-center d-flex flex-column align-items-center justify-content-center p-3">
                    <div id="productPreviewImg" class="bg-light rounded mb-2 d-flex align-items-center justify-content-center border" style="width: 120px; height: 120px; overflow: hidden;">
                        <i class="fas fa-image fs-1 text-muted opacity-25"></i>
                    </div>
                    <div id="productPreviewName" class="small fw-bold text-uppercase text-muted">Aguardando...</div>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm flex-grow-1 overflow-auto">
            <div class="card-header bg-white py-3">
                <h6 class="mb-0 fw-bold text-secondary"><i class="fas fa-list me-2"></i>Itens da Venda</h6>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" id="cartTable">
                        <thead class="bg-light sticky-top">
                            <tr>
                                <th class="ps-4" width="80">Item</th>
                                <th>Produto</th>
                                <th class="text-center" width="120">Qtd</th>
                                <th class="text-end" width="120">Unitário</th>
                                <th class="text-end" width="120">Subtotal</th>
                                <th class="text-center" width="60"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Cart items injected here -->
                        </tbody>
                    </table>
                </div>
                <!-- Empty state -->
                <div id="cartEmptyState" class="text-center py-5 text-muted">
                    <i class="fas fa-cart-plus fs-1 d-block mb-3 opacity-25"></i>
                    Aguardando inclusão de produtos...
                </div>
            </div>
        </div>
    </div>

    <!-- Right Side: Checkout Summary -->
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm h-100 d-flex flex-column">
            <div class="card-header bg-primary text-white py-3">
                <h5 class="mb-0 fw-bold"><i class="fas fa-cash-register me-2"></i>Finalização</h5>
            </div>
            <div class="card-body flex-grow-1">
                <div class="mb-4">
                    <label class="form-label small fw-bold text-uppercase text-muted">Cliente</label>
                    <div class="input-group mb-2">
                        <span class="input-group-text bg-light border-end-0"><i class="fas fa-user text-muted"></i></span>
                        <input type="text" class="form-control bg-light border-start-0" id="customerSearch" placeholder="C.P.F. ou Nome (Opcional)">
                    </div>
                    <small class="text-muted" id="customerDisplay">Consumidor Final</small>
                </div>

                <div class="mb-4">
                    <label class="form-label small fw-bold text-uppercase text-muted">Método de Pagamento</label>
                    <div class="row g-2">
                        <div class="col-6">
                            <input type="radio" class="btn-check" name="payment" id="pay_dinheiro" value="dinheiro" checked>
                            <label class="btn btn-outline-light d-block text-start p-3 text-dark border" for="pay_dinheiro">
                                <i class="fas fa-money-bill-wave me-2 text-success"></i> Dinheiro
                            </label>
                        </div>
                        <div class="col-6">
                            <input type="radio" class="btn-check" name="payment" id="pay_pix" value="pix">
                            <label class="btn btn-outline-light d-block text-start p-3 text-dark border" for="pay_pix">
                                <i class="fa-brands fa-pix me-2 text-info"></i> Pix
                            </label>
                        </div>
                        <div class="col-6">
                            <input type="radio" class="btn-check" name="payment" id="pay_cartao" value="cartao_credito">
                            <label class="btn btn-outline-light d-block text-start p-3 text-dark border" for="pay_cartao">
                                <i class="fas fa-credit-card me-2 text-primary"></i> Cartão
                            </label>
                        </div>
                        <div class="col-6">
                            <input type="radio" class="btn-check" name="payment" id="pay_boleto" value="boleto">
                            <label class="btn btn-outline-light d-block text-start p-3 text-dark border" for="pay_boleto">
                                <i class="fas fa-barcode me-2 text-secondary"></i> Boleto
                            </label>
                        </div>
                    </div>
                </div>

                <div class="bg-light p-4 rounded-3 border mb-4">
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Subtotal</span>
                        <span class="fw-bold" id="totalSub">R$ 0,00</span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted text-success">Desconto</span>
                        <span class="fw-bold text-success" id="totalDesc">- R$ 0,00</span>
                    </div>
                    <hr>
                    <div class="d-flex justify-content-between align-items-center">
                        <h4 class="mb-0 fw-bold">TOTAL</h4>
                        <h2 class="mb-0 fw-bold text-primary" id="finalTotal">R$ 0,00</h2>
                    </div>
                </div>

                <button class="btn btn-primary btn-lg w-100 py-3 fw-bold shadow-sm" id="btnCheckout" disabled>
                    <i class="fas fa-check-circle me-2"></i>CONFIRMAR VENDA (F2)
                </button>
            </div>
            
            <!-- Quick Sales History (Últimos Cupons) -->
            <div class="card-footer bg-white py-3 border-0 mt-auto">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="small fw-bold text-muted text-uppercase mb-0">Últimos Cupons</h6>
                    <button class="btn btn-sm btn-link text-decoration-none p-0 extra-small" onclick="loadRecentSales()">Atualizar</button>
                </div>
                <div id="recentSalesList" class="small overflow-auto" style="max-height: 150px;">
                    <div class="text-center py-2 opacity-50">Carregando histórico...</div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Pending Pre-sales -->
<div class="modal fade" id="modalPendingPV" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-light border-0">
                <h5 class="modal-title fw-bold"><i class="fas fa-file-import me-2"></i>Pré-Vendas Pendentes</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th class="ps-4">Código</th>
                                <th>Cliente</th>
                                <th>Valor</th>
                                <th>Vendedor</th>
                                <th class="text-end pe-4">Ação</th>
                            </tr>
                        </thead>
                        <tbody id="listPendingPVs">
                            <!-- Injected via JS -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Sale Management -->
<div class="modal fade" id="modalSaleManager" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold">Gestão de Venda #<span id="manageSaleId"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="bg-light p-3 rounded mb-3 border">
                    <div class="d-flex justify-content-between mb-1">
                        <span class="text-muted small">Cliente:</span>
                        <span class="fw-bold fw-bold" id="manageSaleCustomer"></span>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span class="text-muted small">Valor Total:</span>
                        <span class="fw-bold text-primary fs-5" id="manageSaleTotal"></span>
                    </div>
                </div>
                
                <div class="d-grid gap-2">
                    <button class="btn btn-outline-danger fw-bold py-3" onclick="cancelSaleAction()">
                        <i class="fas fa-trash-alt me-2"></i>CANCELAR VENDA (ESTORNO)
                    </button>
                    <button class="btn btn-outline-secondary fw-bold py-3" onclick="alert('Funcionalidade de troca em desenvolvimento')">
                        <i class="fas fa-exchange-alt me-2"></i>SOLICITAR TROCA
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Scripts for PDV Logic -->
<script>
let cart = [];
let currentPvId = null;
let activeManageId = null;

const pdvSearch = document.getElementById('pdvSearch');
const searchResults = document.getElementById('searchResults');
const cartTable = document.getElementById('cartTable').querySelector('tbody');
const cartEmptyState = document.getElementById('cartEmptyState');
const finalTotal = document.getElementById('finalTotal');
const btnCheckout = document.getElementById('btnCheckout');
const productPreviewImg = document.getElementById('productPreviewImg');
const productPreviewName = document.getElementById('productPreviewName');

// Initialize
document.addEventListener('DOMContentLoaded', () => {
    loadRecentSales();
});

// Search functionality
pdvSearch.addEventListener('input', async (e) => {
    const term = e.target.value;
    if (term.length < 2) {
        searchResults.classList.add('d-none');
        return;
    }

    const response = await fetch(`vendas.php?action=search&term=${term}`);
    const products = await response.json();
    renderSearchResults(products);
});

function renderSearchResults(products) {
    searchResults.innerHTML = '';
    if (products.length === 0) {
        searchResults.classList.add('d-none');
        return;
    }

    products.forEach(p => {
        const item = document.createElement('button');
        item.className = 'list-group-item list-group-item-action d-flex align-items-center justify-content-between py-3';
        item.innerHTML = `
            <div>
                <div class="fw-bold text-primary">${p.nome}</div>
                <small class="text-muted">Cód: ${p.id} | Un: ${p.unidade}</small>
            </div>
            <div class="text-end">
                <div class="fw-bold">R$ ${parseFloat(p.preco_venda).toFixed(2).replace('.', ',')}</div>
            </div>
        `;
        item.onmouseover = () => showPreview(p);
        item.onclick = () => addToCart(p);
        searchResults.appendChild(item);
    });
    searchResults.classList.remove('d-none');
}

function showPreview(p) {
    if (p.imagens) {
        productPreviewImg.innerHTML = `<img src="public/uploads/produtos/${p.imagens}" style="width:100%; height:100%; object-fit:cover;" class="fade-in">`;
    } else {
        productPreviewImg.innerHTML = `<i class="fas fa-image fs-1 text-muted opacity-25"></i>`;
    }
    productPreviewName.innerText = p.nome;
}

function addToCart(product) {
    const existing = cart.find(i => i.id === product.id);
    if (existing) {
        existing.qty++;
    } else {
        cart.push({
            id: product.id,
            nome: product.nome,
            price: parseFloat(product.preco_venda),
            qty: 1,
            imagens: product.imagens
        });
    }
    
    pdvSearch.value = '';
    searchResults.classList.add('d-none');
    renderCart();
}

function renderCart() {
    cartTable.innerHTML = '';
    let total = 0;

    if (cart.length === 0) {
        cartEmptyState.classList.remove('d-none');
        btnCheckout.disabled = true;
    } else {
        cartEmptyState.classList.add('d-none');
        btnCheckout.disabled = false;
    }

    cart.forEach((item, index) => {
        const subtotal = item.price * item.qty;
        total += subtotal;
        
        const row = document.createElement('tr');
        row.onmouseover = () => showPreview(item);
        row.innerHTML = `
            <td class="ps-4 fw-bold text-muted">#${item.id}</td>
            <td>${item.nome}</td>
            <td class="text-center">
                <input type="number" class="form-control form-control-sm text-center mx-auto" style="width: 70px" value="${item.qty}" min="1" onchange="updateQty(${index}, this.value)">
            </td>
            <td class="text-end">R$ ${item.price.toFixed(2).replace('.', ',')}</td>
            <td class="text-end fw-bold">R$ ${subtotal.toFixed(2).replace('.', ',')}</td>
            <td class="text-center">
                <button class="btn btn-sm btn-link text-danger p-0" onclick="removeFromCart(${index})"><i class="fas fa-times"></i></button>
            </td>
        `;
        cartTable.appendChild(row);
    });

    finalTotal.innerText = `R$ ${total.toFixed(2).replace('.', ',')}`;
    document.getElementById('totalSub').innerText = `R$ ${total.toFixed(2).replace('.', ',')}`;
}

function updateQty(index, val) {
    cart[index].qty = Math.max(1, parseFloat(val));
    renderCart();
}

function removeFromCart(index) {
    cart.splice(index, 1);
    renderCart();
}

// Pre-sale flow
async function loadPendingPreSales() {
    const res = await fetch('pre_vendas.php?action=list_pending');
    const pvs = await res.json();
    const list = document.getElementById('listPendingPVs');
    list.innerHTML = '';
    
    pvs.forEach(pv => {
        const row = document.createElement('tr');
        row.innerHTML = `
            <td class="ps-4 fw-bold text-primary">${pv.codigo}</td>
            <td>${pv.cliente_nome || 'Consumidor Final'}</td>
            <td class="fw-bold">R$ ${parseFloat(pv.valor_total).toFixed(2).replace('.', ',')}</td>
            <td class="small text-muted">${pv.vendedor_nome}</td>
            <td class="text-end pe-4">
                <button class="btn btn-sm btn-primary fw-bold" onclick="importPreSale('${pv.codigo}')">CARREGAR</button>
            </td>
        `;
        list.appendChild(row);
    });
    
    new bootstrap.Modal(document.getElementById('modalPendingPV')).show();
}

async function importPreSale(code) {
    const res = await fetch(`pre_vendas.php?action=get_by_code&code=${code}`);
    const pv = await res.json();
    
    if (pv) {
        cart = pv.itens.map(i => ({
            id: i.produto_id,
            nome: i.produto_nome,
            price: parseFloat(i.preco_unitario),
            qty: parseFloat(i.quantidade),
            imagens: i.imagens
        }));
        currentPvId = pv.id;
        document.getElementById('customerDisplay').innerText = pv.cliente_nome || 'Consumidor Final';
        renderCart();
        bootstrap.Modal.getInstance(document.getElementById('modalPendingPV')).hide();
        pdvSearch.focus();
    }
}

// Recent Sales (History)
async function loadRecentSales() {
    const res = await fetch('vendas.php?action=list_recent');
    const data = await res.json();
    const list = document.getElementById('recentSalesList');
    list.innerHTML = '';
    
    if (data.sales.length === 0) {
        list.innerHTML = '<div class="text-center py-2 opacity-50">Nenhuma venda recente</div>';
        return;
    }

    data.sales.forEach(sale => {
        const item = document.createElement('div');
        item.className = 'd-flex justify-content-between align-items-center mb-2 p-2 bg-light rounded border cursor-pointer';
        item.style.cursor = 'pointer';
        item.onclick = () => manageSale(sale);
        item.innerHTML = `
            <div style="font-size: 0.75rem;">
                <div class="fw-bold">Venda #${sale.id}</div>
                <div class="text-muted">${sale.cliente_nome || 'Consumidor'}</div>
            </div>
            <div class="text-end">
                <div class="fw-bold text-primary">R$ ${parseFloat(sale.valor_total).toFixed(2).replace('.', ',')}</div>
                <div class="extra-small ${sale.status === 'cancelado' ? 'text-danger' : 'text-success'}">${sale.status.toUpperCase()}</div>
            </div>
        `;
        list.appendChild(item);
    });
}

function manageSale(sale) {
    activeManageId = sale.id;
    document.getElementById('manageSaleId').innerText = sale.id;
    document.getElementById('manageSaleCustomer').innerText = sale.cliente_nome || 'Consumidor Final';
    document.getElementById('manageSaleTotal').innerText = 'R$ ' + parseFloat(sale.valor_total).toFixed(2).replace('.', ',');
    new bootstrap.Modal(document.getElementById('modalSaleManager')).show();
}

async function cancelSaleAction() {
    if (!confirm('Deseja realmente cancelar esta venda? O estoque será devolvido.')) return;
    
    const res = await fetch('vendas.php?action=cancel_sale', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: activeManageId })
    });
    
    const result = await res.json();
    if (result.success) {
        alert('Venda cancelada com sucesso!');
        loadRecentSales();
        bootstrap.Modal.getInstance(document.getElementById('modalSaleManager')).hide();
    } else {
        alert('Erro: ' + result.error);
    }
}

// Checkout
btnCheckout.onclick = async () => {
    if (cart.length === 0) return;
    
    const payment = document.querySelector('input[name="payment"]:checked').value;
    const data = {
        total: cart.reduce((acc, i) => acc + (i.price * i.qty), 0),
        items: cart,
        pagamento: payment,
        cliente_id: null,
        pv_id: currentPvId
    };

    const res = await fetch('vendas.php?action=checkout', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    });

    const result = await res.json();
    if (result.success) {
        alert('Venda realizada com sucesso!');
        cart = [];
        currentPvId = null;
        renderCart();
        loadRecentSales();
    } else {
        alert('Erro ao finalizar: ' + result.error);
    }
};

// Keyboard Hotkeys
document.addEventListener('keydown', (e) => {
    if (e.key === 'F2') btnCheckout.click();
});
</script>
