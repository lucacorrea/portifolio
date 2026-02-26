<div class="row g-4">
    <!-- Left Side: Product selection & Preview -->
    <div class="col-lg-7 d-flex flex-column">
        <div class="row g-4 mb-4">
            <div class="col-md-8">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="position-relative">
                            <div class="input-group input-group-lg shadow-sm rounded">
                                <span class="input-group-text bg-white border-end-0 text-muted">
                                    <i class="fas fa-search"></i>
                                </span>
                                <input type="text" id="pdvSearch" class="form-control border-start-0 ps-0" placeholder="Pesquisar Produto (F4)..." autocomplete="off">
                            </div>
                            <div id="searchResults" class="list-group shadow-lg d-none" style="position: absolute; top: 100%; left: 0; z-index: 3000; width: 100%; max-height: 400px; overflow-y: auto; background: white !important; border: 1px solid #ddd;">
                                <!-- Results will be injected here -->
                            </div>
                        </div>
                        <div class="mt-3 d-flex gap-2">
                             <button class="btn btn-outline-primary fw-bold" onclick="loadPendingPreSales()">
                                <i class="fas fa-file-import me-2"></i>Importar Pré-Venda (F8)
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
        <div class="card border-0 glass-card shadow-lg h-100 d-flex flex-column" style="border: 1px solid rgba(79, 70, 229, 0.2) !important;">
            <div class="card-header bg-erp-primary text-white py-3 border-0">
                <h5 class="mb-0 fw-bold"><i class="fas fa-cash-register me-2"></i>Checkout SaaS</h5>
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
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-muted">Desconto (%)</span>
                        <div style="width: 80px;">
                            <input type="number" id="discountPercent" class="form-control form-control-sm text-end fw-bold text-success border-success bg-success bg-opacity-10" value="0" min="0" max="100" step="0.1" onfocus="interceptDiscount(event)" onmousedown="interceptDiscount(event)" onkeydown="interceptDiscount(event)" onchange="renderCart()">
                        </div>
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

                <?php if (($_SESSION['usuario_nivel'] ?? '') !== 'vendedor'): ?>
                <button class="btn btn-primary btn-lg w-100 py-3 fw-bold shadow-sm" id="btnCheckout" disabled>
                    <i class="fas fa-check-circle me-2"></i>CONFIRMAR VENDA (F2)
                </button>
                <?php else: ?>
                <div class="alert alert-info small mb-0">
                    <i class="fas fa-info-circle me-1"></i> Usuários nível vendedor não podem finalizar vendas.
                </div>
                <?php endif; ?>
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

<!-- Modal: Discount Authorization -->
<div class="modal fade" id="modalDiscountAuth" data-bs-backdrop="static" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-dark text-white border-0">
                <h5 class="modal-title fw-bold"><i class="fas fa-shield-halved me-2 text-primary"></i>Autorização de Administrador</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" onclick="resetDiscount()"></button>
            </div>
            <div class="modal-body p-4 text-center">
                <div class="mb-4">
                    <div class="bg-primary bg-opacity-10 text-primary rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 60px; height: 60px;">
                        <i class="fas fa-key fs-3"></i>
                    </div>
                    <h6 class="fw-bold mb-1">Acesso Restrito</h6>
                    <p class="text-muted small">Esta operação requer a presença e senha de um Administrador.</p>
                </div>
                
                <div class="mb-4 text-start">
                    <label class="form-label small fw-bold text-uppercase opacity-75" id="authLabel">Senha de Autorização</label>
                    <input type="password" id="authCredential" class="form-control form-control-lg text-center shadow-sm border-2" placeholder="Digite a senha..." autofocus>
                </div>

                <div class="d-grid">
                    <button class="btn btn-dark fw-bold py-3 shadow-sm" onclick="validateAuthorization()">
                        <i class="fas fa-check-circle me-2 text-primary"></i>CONFIRMAR IDENTIDADE
                    </button>
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
const currentUserLevel = '<?= $_SESSION['usuario_nivel'] ?? 'vendedor' ?>';

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
    isAuthorized = false; // Reset auth on new items
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

    const discountPercent = parseFloat(document.getElementById('discountPercent').value) || 0;
    const discountVal = total * (discountPercent / 100);
    const finalTotalVal = total - discountVal;

    document.getElementById('totalSub').innerText = `R$ ${total.toFixed(2).replace('.', ',')}`;
    document.getElementById('totalDesc').innerText = `- R$ ${discountVal.toFixed(2).replace('.', ',')}`;
    finalTotal.innerText = `R$ ${finalTotalVal.toFixed(2).replace('.', ',')}`;

    checkDiscountAuth();
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

async function interceptDiscount(e) {
    if (currentUserLevel === 'admin' || isAuthorized) return;
    
    e.preventDefault();
    e.stopPropagation();
    if (e.target) e.target.blur();
    
    const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('modalDiscountAuth'));
    modal.show();
    
    await loadAdmins();
}

let isAuthorized = false;
let authSupervisorId = null;
let authSupervisorCredential = null;
let authAdmins = [];

async function checkDiscountAuth() {
    const discount = parseFloat(document.getElementById('discountPercent').value) || 0;
    
    // Admins don't need authorization modal for themselves
    if (currentUserLevel === 'admin') {
        isAuthorized = true;
        btnCheckout.disabled = cart.length === 0;
        return;
    }

    if (discount > 0 && !isAuthorized) {
        await loadAdmins();
        new bootstrap.Modal(document.getElementById('modalDiscountAuth')).show();
        btnCheckout.disabled = true;
    } else {
        btnCheckout.disabled = cart.length === 0;
    }
}

async function loadAdmins() {
    const res = await fetch('vendas.php?action=list_admins');
    authAdmins = await res.json();
    
    if (authAdmins.length > 0) {
        const admin = authAdmins[0]; // Auto-select the first admin
        authSupervisorId = admin.id;
        
        const input = document.getElementById('authCredential');
        const label = document.getElementById('authLabel');
        
        if (admin.auth_type === 'pin') {
            input.type = 'number';
            input.placeholder = 'Digite o PIN...';
            label.innerText = 'PIN DE AUTORIZAÇÃO';
        } else {
            input.type = 'password';
            input.placeholder = 'Digite a senha...';
            label.innerText = 'SENHA DE AUTORIZAÇÃO';
        }
    }
}

async function validateAuthorization() {
    const credential = document.getElementById('authCredential').value;

    if (!authSupervisorId || !credential) {
        alert('Credenciais incompletas ou nenhum administrador encontrado.');
        return;
    }

    const res = await fetch('vendas.php?action=authorize_discount', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ user_id: authSupervisorId, credential: credential })
    });

    const result = await res.json();
    if (result.success) {
        isAuthorized = true;
        authSupervisorCredential = credential;
        bootstrap.Modal.getInstance(document.getElementById('modalDiscountAuth')).hide();
        renderCart();
        
        // Focus and select the discount field so the user can type immediately
        const discountInput = document.getElementById('discountPercent');
        setTimeout(() => {
            discountInput.focus();
            discountInput.select();
        }, 500);
        
        alert('Desconto autorizado com sucesso!');
    } else {
        alert('Erro: ' + result.error);
    }
}

function resetDiscount() {
    if (!isAuthorized) {
        document.getElementById('discountPercent').value = 0;
        renderCart();
    }
}

// Checkout
btnCheckout.onclick = async () => {
    if (cart.length === 0) return;
    
    const discountPercent = parseFloat(document.getElementById('discountPercent').value) || 0;
    
    // Safety check: blockage if discount exists but not authorized
    if (discountPercent > 0 && !isAuthorized && currentUserLevel !== 'admin') {
        alert('Esta venda contém um desconto não autorizado. Por favor, autorize primeiro.');
        await loadAdmins();
        new bootstrap.Modal(document.getElementById('modalDiscountAuth')).show();
        return;
    }

    const subtotal = cart.reduce((acc, i) => acc + (i.price * i.qty), 0);
    const total = subtotal * (1 - (discountPercent / 100));

    const payment = document.querySelector('input[name="payment"]:checked').value;
    const data = {
        subtotal: subtotal,
        discount_percent: discountPercent,
        total: total,
        items: cart,
        pagamento: payment,
        cliente_id: null,
        pv_id: currentPvId,
        supervisor_id: authSupervisorId,
        supervisor_credential: authSupervisorCredential
    };

    const res = await fetch('vendas.php?action=checkout', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    });

    const result = await res.json();
    if (result.success) {
        showSuccessModal(result.sale_id, data.total);
        cart = [];
        currentPvId = null;
        isAuthorized = false;
        authSupervisorId = null;
        authSupervisorCredential = null;
        document.getElementById('discountPercent').value = 0;
        renderCart();
        loadRecentSales();
    } else {
        alert('Erro ao finalizar: ' + result.error);
    }
};

function showSuccessModal(saleId, total) {
    const modalHtml = `
        <div class="modal fade" id="modalSuccess" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-0 shadow-lg">
                    <div class="modal-body text-center p-5">
                        <div class="mb-4">
                            <i class="fas fa-check-circle text-success" style="font-size: 5rem;"></i>
                        </div>
                        <h3 class="fw-bold mb-2">Venda Finalizada!</h3>
                        <p class="text-muted mb-4">A venda <strong>#${saleId}</strong> foi registrada com sucesso no valor de <strong>R$ ${total.toFixed(2).replace('.', ',')}</strong>.</p>
                        
                        <div class="d-grid gap-2">
                            <button class="btn btn-primary btn-lg fw-bold py-3" onclick="issueNFCe(${saleId})">
                                <i class="fas fa-file-invoice-dollar me-2"></i>EMITIR NFC-e (Cupom Fiscal)
                            </button>
                            <button class="btn btn-outline-secondary fw-bold py-3" onclick="alert('Impressão térmica em desenvolvimento')">
                                <i class="fas fa-print me-2"></i>Imprimir Recibo Simples
                            </button>
                            <button class="btn btn-link text-muted mt-3" data-bs-dismiss="modal">Fechar e Nova Venda (ESC)</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    const existing = document.getElementById('modalSuccess');
    if (existing) existing.remove();
    
    document.body.insertAdjacentHTML('beforeend', modalHtml);
    const modal = new bootstrap.Modal(document.getElementById('modalSuccess'));
    modal.show();
}

async function issueNFCe(saleId) {
    const btn = event.currentTarget;
    const originalHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Comunicando SEFAZ...';

    try {
        const res = await fetch('vendas.php?action=issue_nfce', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: saleId })
        });
        
        const result = await res.json();
        if (result.success) {
            btn.className = 'btn btn-success btn-lg fw-bold py-3';
            btn.innerHTML = '<i class="fas fa-check me-2"></i>NFC-e AUTORIZADA!';
            alert('NFC-e Autorizada com sucesso! Protocolo: ' + result.protocolo);
            // In a real scenario, we would trigger PDF download/print here
        } else {
            alert('Erro SEFAZ: ' + result.error);
            btn.disabled = false;
            btn.innerHTML = originalHtml;
        }
    } catch (e) {
        alert('Erro de comunicação: ' + e.message);
        btn.disabled = false;
        btn.innerHTML = originalHtml;
    }
}

// Keyboard Hotkeys
document.addEventListener('keydown', (e) => {
    if (e.key === 'F2') {
        e.preventDefault();
        btnCheckout.click();
    }
    if (e.key === 'F4') {
        e.preventDefault();
        pdvSearch.focus();
    }
    if (e.key === 'F8') {
        e.preventDefault();
        loadPendingPreSales();
    }
    if (e.key === 'Escape') {
        searchResults.classList.add('d-none');
    }
});

// Barcode optimization: If search returns exactly 1 result and looks like a barcode, add to cart automatically
async function handleBarcode(val) {
    if (val.length >= 8 && !isNaN(val)) {
        const response = await fetch(`vendas.php?action=search&term=${val}`);
        const products = await response.json();
        if (products.length === 1) {
            addToCart(products[0]);
            pdvSearch.value = '';
        }
    }
}

pdvSearch.addEventListener('keyup', (e) => {
    if (e.key === 'Enter') handleBarcode(pdvSearch.value);
});
</script>
