<?php if (!$caixaAberto): ?>
<div class="alert alert-danger shadow-sm border-0 d-flex align-items-center mb-4 p-3 rounded-4">
    <i class="fas fa-exclamation-triangle fa-2x me-3"></i>
    <div class="flex-grow-1">
        <h6 class="mb-1 fw-bold">ATENÇÃO: CAIXA FECHADO!</h6>
        <p class="mb-0 small">Você não pode realizar vendas enquanto o seu caixa estiver fechado. 
           <a href="caixa.php" class="fw-bold text-danger text-decoration-underline ms-1">Clique aqui para abrir seu caixa agora.</a>
        </p>
    </div>
</div>
<?php endif; ?>

<div class="row g-4 <?php echo !$caixaAberto ? 'opacity-50 select-none' : ''; ?>" style="<?php echo !$caixaAberto ? 'pointer-events: none;' : ''; ?>">
    <!-- Left Side: Product selection & Preview -->
    <div class="col-lg-7 d-flex flex-column">
        <div class="row g-4 mb-4">
            <div class="col-md-8" style="z-index: 1025;">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body" style="overflow: visible !important;">
                        <div class="position-relative">
                            <div class="input-group input-group-lg shadow-sm rounded">
                                <span class="input-group-text bg-white border-end-0 text-muted">
                                    <i class="fas fa-search"></i>
                                </span>
                                <input type="text" id="pdvSearch" class="form-control border-start-0 ps-0" placeholder="Pesquisar Produto (F4)..." autocomplete="off">
                            </div>
                            <div id="searchResults" class="list-group shadow-lg d-none" style="position: absolute; top: 100%; left: 0; z-index: 9999; width: 100%; max-height: 400px; overflow-y: auto; background: white !important; border: 1px solid #ddd; margin-top: 5px;">
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
        <div class="card border-0 glass-card  h-100 d-flex flex-column" style="border: 1px solid rgba(0, 86, 179, 0.2) !important;">
            <div class="card-header bg-erp-primary text-white py-3 border-0">
                <h5 class="mb-0 fw-bold"><i class="fas fa-cash-register me-2"></i>Checkout SaaS</h5>
            </div>
            <div class="card-body flex-grow-1">
                <div class="mb-4">
                    <label class="form-label extra-small fw-bold text-uppercase opacity-75">Identificar Cliente (Obrigatório para Fiado)</label>
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0 text-muted">
                            <i class="fas fa-user-tag"></i>
                        </span>
                        <input type="text" id="customerSearch" class="form-control border-start-0 ps-0" placeholder="Nome, CPF ou Telefone...">
                        <button class="btn btn-outline-primary" type="button" onclick="abrirModalQuickClient()" title="Novo Cliente">
                            <i class="fas fa-plus"></i>
                        </button>
                    </div>
                    <div id="customerResults" class="list-group mt-2 shadow-sm position-absolute w-100" style="z-index: 1050; display: none; left:0; right:0;"></div>
                    
                    <div id="selectedCustomerInfo" class="mt-3 p-3 bg-primary bg-opacity-10 border border-primary border-opacity-10 rounded d-none">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <p class="mb-0 fw-bold text-primary" id="customerNameDisplay"></p>
                                <p class="mb-0 small text-muted" id="customerDocDisplay"></p>
                            </div>
                            <button class="btn btn-sm btn-outline-danger" onclick="clearCustomer()">
                                <i class="fas fa-times me-1"></i>Remover
                            </button>
                        </div>
                    </div>
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
                        <div class="col-6">
                            <input type="radio" class="btn-check" name="payment" id="pay_fiado" value="fiado">
                            <label class="btn btn-outline-light d-block text-start p-3 text-dark border" for="pay_fiado">
                                <i class="fas fa-hand-holding-usd me-2 text-warning"></i> A Prazo (Fiado)
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Fiscal Toggle -->
                <div class="mb-4">
                    <label class="form-label small fw-bold text-uppercase text-muted">Tipo de Venda</label>
                    <div class="row g-2">
                        <div class="col-6">
                            <input type="radio" class="btn-check" name="tipo_nota" id="tipo_fiscal" value="fiscal" autocomplete="off">
                            <label class="btn btn-outline-success d-block text-start p-3 w-100" for="tipo_fiscal">
                                <i class="fas fa-file-invoice-dollar me-2"></i>
                                <span class="fw-bold">Nota Fiscal</span>
                                <div class="extra-small opacity-75 mt-1">Emite NFC-e SEFAZ</div>
                            </label>
                        </div>
                        <div class="col-6">
                            <input type="radio" class="btn-check" name="tipo_nota" id="tipo_nao_fiscal" value="nao_fiscal" autocomplete="off" checked>
                            <label class="btn btn-outline-secondary d-block text-start p-3 w-100" for="tipo_nao_fiscal">
                                <i class="fas fa-receipt me-2"></i>
                                <span class="fw-bold">Não Fiscal</span>
                                <div class="extra-small opacity-75 mt-1">Só recibo simples</div>
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
            <div class="modal-header bg-light border-0 d-flex justify-content-between align-items-center">
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

<!-- Modal: Entrada Fiado -->
<div class="modal fade" id="modalEntrada" data-bs-backdrop="static" tabindex="-1" style="z-index: 1060;">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-warning text-dark border-0">
                <h6 class="modal-title fw-bold"><i class="fas fa-hand-holding-dollar me-2"></i>Entrada / Sinal</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4 text-center">
                <p class="text-muted small mb-3">Deseja registrar uma <strong>entrada em dinheiro</strong> para esta venda fiado?</p>
                <div class="mb-3 text-start">
                    <label class="form-label extra-small fw-bold text-uppercase opacity-75">Valor da Entrada (R$)</label>
                    <input type="number" id="entradaValor" class="form-control form-control-lg text-center fw-bold text-success" placeholder="0,00" step="0.01" min="0">
                </div>
                <div class="d-grid">
                    <button class="btn btn-warning fw-bold py-2 shadow-sm" onclick="confirmarCheckoutFiado()">
                        FINALIZAR VENDA
                    </button>
                </div>
                <button class="btn btn-link btn-sm text-muted mt-2 text-decoration-none" onclick="document.getElementById('entradaValor').value=0; confirmarCheckoutFiado()">Continuar sem entrada</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Complemento de Cadastro (Fiado) -->
<div class="modal fade" id="modalCompleteClient" data-bs-backdrop="static" tabindex="-1" style="z-index: 1070;">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-primary text-white border-0">
                <h6 class="modal-title fw-bold"><i class="fas fa-user-edit me-2"></i>Completar Cadastro para Fiado</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <p class="text-muted small mb-4">Para realizar vendas a prazo (Fiado), é obrigatório que o cliente possua os dados abaixo preenchidos:</p>
                
                <input type="hidden" id="edit_client_id">
                
                <div class="mb-3">
                    <label class="form-label extra-small fw-bold text-uppercase opacity-75">CPF ou CNPJ</label>
                    <input type="text" id="edit_client_doc" class="form-control" placeholder="000.000.000-00">
                </div>
                
                <div class="mb-3">
                    <label class="form-label extra-small fw-bold text-uppercase opacity-75">Telefone / WhatsApp</label>
                    <input type="text" id="edit_client_phone" class="form-control" placeholder="(00) 00000-0000">
                </div>
                
                <div class="mb-4">
                    <label class="form-label extra-small fw-bold text-uppercase opacity-75">Endereço Completo</label>
                    <textarea id="edit_client_address" class="form-control" rows="2" placeholder="Rua, Número, Bairro, Cidade..."></textarea>
                </div>

                <div class="d-grid">
                    <button class="btn btn-primary fw-bold py-3 shadow-sm" onclick="updateClientAndContinue()">
                        SALVAR E CONTINUAR VENDA
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
            </div>
        </div>
    </div>
</div>

<script>
let cart = [];
let currentPvId = null;
let activeManageId = null;
let selectedCustomerId = null;
let selectedCustomerName = null;
let selectedCustomerCPF = null;
const currentUserLevel = '<?= $_SESSION['usuario_id'] ? ($_SESSION['usuario_nivel'] ?? 'vendedor') : 'vendedor' ?>';

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

    const response = await fetch(`vendas.php?action=search&term=${encodeURIComponent(term)}`);
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
        
        const isPV = p.type === 'pre_sale';
        const icon = isPV ? 'fa-file-invoice-dollar text-warning' : 'fa-box text-primary';
        const badge = isPV ? '<span class="badge bg-warning text-dark extra-small ms-2">PRÉ-VENDA</span>' : '';

        item.innerHTML = `
            <div class="d-flex align-items-center">
                <i class="fas ${icon} fs-4 me-3 opacity-75"></i>
                <div>
                    <div class="fw-bold ${isPV ? 'text-warning' : 'text-primary'}">${p.nome} ${badge}</div>
                    <small class="text-muted">Cód: ${p.codigo || p.id} | Un: ${p.unidade}</small>
                </div>
            </div>
            <div class="text-end">
                <div class="fw-bold">R$ ${parseFloat(p.preco_venda).toFixed(2).replace('.', ',')}</div>
                ${isPV ? '<small class="text-success extra-small fw-bold">CLIQUE PARA IMPORTAR</small>' : ''}
            </div>
        `;
        
        if (isPV) {
            item.onclick = (e) => {
                e.preventDefault();
                importPreSale(p.codigo);
                pdvSearch.value = '';
                searchResults.classList.add('d-none');
            };
        } else {
            item.onmouseover = () => showPreview(p);
            item.onclick = () => addToCart(p);
        }
        
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

const customerSearch = document.getElementById('customerSearch');
const customerResults = document.getElementById('customerResults');
const selectedCustomerInfo = document.getElementById('selectedCustomerInfo');

if (customerSearch) {
    customerSearch.addEventListener('input', async (e) => {
        const term = e.target.value;
        const cleanTerm = term.replace(/\D/g, '');
        
        // Clear previous timer
        if (window.customerSearchTimer) clearTimeout(window.customerSearchTimer);

        // Auto-select 
        if (cleanTerm.length === 14) {
            selectCustomer(null, 'Consumidor Final', term);
            return;
        } else if (cleanTerm.length === 11) {
            // Wait 400ms to see if more digits are coming (CNPJ)
            window.customerSearchTimer = setTimeout(() => {
                selectCustomer(null, 'Consumidor Final', term);
            }, 400);
            return;
        }

        if (term.length < 2) {
            customerResults.style.display = 'none';
            return;
        }

        try {
            const response = await fetch(`vendas.php?action=search_clients&term=${encodeURIComponent(term)}`);
            const clients = await response.json();
            renderCustomerSearchResults(clients, term);
        } catch (err) {
            console.error("PDV: Erro ao buscar clientes:", err);
        }
    });

    customerSearch.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
            const term = customerSearch.value.trim();
            const cleanTerm = term.replace(/\D/g, '');
            if (cleanTerm.length === 11 || cleanTerm.length === 14) {
                if (window.customerSearchTimer) clearTimeout(window.customerSearchTimer);
                e.preventDefault();
                selectCustomer(null, 'Consumidor Final', term);
            }
        }
    });
}

function renderCustomerSearchResults(clients, term = '') {
    customerResults.innerHTML = '';
    
    // Check if term looks like a CPF or CNPJ
    const cleanTerm = term.replace(/\D/g, '');
    const isDoc = cleanTerm.length === 11 || cleanTerm.length === 14;

    if (isDoc) {
        const avulsoBtn = document.createElement('button');
        avulsoBtn.className = 'list-group-item list-group-item-action py-3 d-flex justify-content-between align-items-center bg-primary text-white shadow';
        avulsoBtn.innerHTML = `
            <div>
                <div class="fw-bold">IDENTIFICAR CONSUMIDOR</div>
                <small class="opacity-75">Documento: ${term}</small>
            </div>
            <i class="fas fa-id-card fa-lg"></i>
        `;
        avulsoBtn.onclick = () => {
            if (window.customerSearchTimer) clearTimeout(window.customerSearchTimer);
            selectCustomer(null, 'Consumidor Final', term);
        }
        customerResults.appendChild(avulsoBtn);
    }

    if (clients.length === 0 && !isDoc) {
        customerResults.style.display = 'none';
        return;
    }

    clients.forEach(c => {
        const item = document.createElement('button');
        item.className = 'list-group-item list-group-item-action py-3 d-flex justify-content-between align-items-center';
        item.innerHTML = `
            <div>
                <div class="fw-bold">${c.nome}</div>
                <small class="text-muted">${c.doc || 'Sem CPF/CNPJ'}</small>
            </div>
            <i class="fas fa-chevron-right text-muted small"></i>
        `;
        item.onclick = () => {
            selectCustomer(c.id, c.nome, c.doc);
        };
        customerResults.appendChild(item);
    });
    customerResults.style.display = 'block';
}

function selectCustomer(id, nome, doc) {
    selectedCustomerId = id;
    selectedCustomerName = nome;
    selectedCustomerCPF = doc;
    
    document.getElementById('customerNameDisplay').innerText = nome;
    document.getElementById('customerDocDisplay').innerText = doc || 'Sem documento';
    
    selectedCustomerInfo.classList.remove('d-none');
    customerResults.style.display = 'none';
    customerSearch.value = '';
    
    // Hide search group
    customerSearch.closest('.input-group').classList.add('d-none');
    customerSearch.closest('div.mb-4').querySelector('label').classList.add('d-none');
}

function clearCustomer() {
    selectedCustomerId = null;
    selectedCustomerName = null;
    selectedCustomerCPF = null;
    
    selectedCustomerInfo.classList.add('d-none');
    customerSearch.closest('.input-group').classList.remove('d-none');
    customerSearch.closest('div.mb-4').querySelector('label').classList.remove('d-none');
    customerSearch.value = '';
    customerSearch.focus();
}

function abrirModalQuickClient() {
    document.getElementById('qc_nome').value = '';
    document.getElementById('qc_cpf_cnpj').value = '';
    document.getElementById('qc_telefone').value = '';
    new bootstrap.Modal(document.getElementById('modalQuickClient')).show();
}

async function salvarQuickClient() {
    const nome = document.getElementById('qc_nome').value;
    const cpf_cnpj = document.getElementById('qc_cpf_cnpj').value;
    const telefone = document.getElementById('qc_telefone').value;

    if (!nome) return alert('O nome é obrigatório.');

    try {
        const res = await fetch('vendas.php?action=quick_register_client', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ nome, cpf_cnpj, telefone })
        });

        const result = await res.json();
        if (result.success) {
            selectCustomer(result.client_id, nome, cpf_cnpj);
            bootstrap.Modal.getInstance(document.getElementById('modalQuickClient')).hide();
        } else {
            alert('Erro ao cadastrar: ' + result.error);
        }
    } catch (err) {
        alert('Erro de conexão: ' + err.message);
    }
}

// Pre-sale flow
async function loadPendingPreSales() {
    console.log("PDV: Carregando pré-vendas pendentes...");
    const term = '';
    
    try {
        const res = await fetch(`pre_vendas.php?action=list_pending&term=${encodeURIComponent(term)}`);
        if (!res.ok) throw new Error("Falha ao comunicar com pre_vendas.php");
        const pvs = await res.json();
        const list = document.getElementById('listPendingPVs');
        if (!list) return;
        
        list.innerHTML = '';
        
        if (pvs.length === 0) {
            list.innerHTML = '<tr><td colspan="5" class="text-center py-4 text-muted">Nenhuma pré-venda encontrada.</td></tr>';
        }

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
        
        const modalEl = document.getElementById('modalPendingPV');
        if (modalEl) {
            const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
            modal.show();
        }
    } catch (err) {
        console.error("PDV: Erro ao carregar pré-vendas:", err);
        alert("Erro ao carregar pré-vendas. Verifique o console.");
    }
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
        
        // Auto-select customer if present in pre-sale
        if (pv.cliente_id) {
            selectCustomer(pv.cliente_id, pv.cliente_nome, pv.cliente_doc || '');
        } else if (pv.nome_cliente_avulso) {
            // If it's a walk-in name, we can just set the name for the record but not a DB ID
            selectedCustomerId = null;
            selectedCustomerName = pv.nome_cliente_avulso;
            selectedCustomerCPF = pv.cliente_doc || null;
            
            // UI Update for walk-in
            const customerInfo = document.getElementById('selectedCustomerInfo');
            const customerNameDisplay = document.getElementById('customerNameDisplay'); // Fixed ID reference
            const customerDocDisplay = document.getElementById('customerDocDisplay');   // Fixed ID reference
            if (customerInfo && customerNameDisplay) {
                customerNameDisplay.innerText = pv.nome_cliente_avulso;
                customerDocDisplay.innerText = selectedCustomerCPF || 'Consumidor Avulso';
                customerInfo.classList.remove('d-none');
                customerSearch.closest('.input-group').classList.add('d-none');
                customerSearch.closest('div.mb-4').querySelector('label').classList.add('d-none');
            }
        } else {
            clearCustomer();
        }
        
        renderCart();
        
        const modalInstance = bootstrap.Modal.getInstance(document.getElementById('modalPendingPV'));
        if (modalInstance) modalInstance.hide();
        
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
    
    if (discountPercent > 0 && !isAuthorized && currentUserLevel !== 'admin') {
        alert('Esta venda contém um desconto não autorizado. Por favor, autorize primeiro.');
        await loadAdmins();
        new bootstrap.Modal(document.getElementById('modalDiscountAuth')).show();
        return;
    }

    const payment = document.querySelector('input[name="payment"]:checked').value;
    
    if (payment === 'fiado') {
        if (!selectedCustomerId) {
            alert('Vendas a prazo (Fiado) exigem a seleção de um cliente cadastrado.');
            customerSearch.focus();
            return;
        }

        // Validation: Completeness for Fiado
        try {
            const res = await fetch(`vendas.php?action=check_client_completeness&id=${selectedCustomerId}`);
            const data = await res.json();
            
            if (!data.is_complete) {
                // Show completion modal
                document.getElementById('edit_client_id').value = selectedCustomerId;
                document.getElementById('edit_client_doc').value = data.client.cpf_cnpj || '';
                document.getElementById('edit_client_phone').value = data.client.telefone || '';
                document.getElementById('edit_client_address').value = data.client.endereco || '';
                
                bootstrap.Modal.getOrCreateInstance(document.getElementById('modalCompleteClient')).show();
                return;
            }
        } catch (err) {
            console.error("Erro validando cliente:", err);
        }

        // Proceed to entry modal if complete
        const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('modalEntrada'));
        document.getElementById('entradaValor').value = '';
        modal.show();
        setTimeout(() => document.getElementById('entradaValor').focus(), 500);
    } else {
        processarCheckout();
    }
};

async function updateClientAndContinue() {
    const id = document.getElementById('edit_client_id').value;
    const doc = document.getElementById('edit_client_doc').value;
    const phone = document.getElementById('edit_client_phone').value;
    const address = document.getElementById('edit_client_address').value;

    if (!doc || !phone || !address) {
        alert('Por favor, preencha todos os campos obrigatórios para o Fiado.');
        return;
    }

    try {
        const res = await fetch('vendas.php?action=update_client_quick', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id, cpf_cnpj: doc, telefone: phone, endereco: address })
        });
        
        const result = await res.json();
        if (result.success) {
            bootstrap.Modal.getInstance(document.getElementById('modalCompleteClient')).hide();
            // Now show the entry modal
            const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('modalEntrada'));
            document.getElementById('entradaValor').value = '';
            modal.show();
            setTimeout(() => document.getElementById('entradaValor').focus(), 500);
        } else {
            alert('Erro ao atualizar cliente: ' + result.error);
        }
    } catch (err) {
        alert('Erro de conexão: ' + err.message);
    }
}

async function confirmarCheckoutFiado() {
    processarCheckout();
}

async function processarCheckout() {
    const discountPercent = parseFloat(document.getElementById('discountPercent').value) || 0;
    const subtotal = cart.reduce((acc, i) => acc + (i.price * i.qty), 0);
    const total = subtotal * (1 - (discountPercent / 100));
    const payment = document.querySelector('input[name="payment"]:checked').value;
    const entrada = parseFloat(document.getElementById('entradaValor')?.value) || 0;

    // Troco / valor recebido (only relevant for dinheiro)
    let valorRecebido = null;
    let troco = 0;
    if (payment === 'dinheiro') {
        const valorRecebidoEl = document.getElementById('valorRecebidoDinheiro');
        valorRecebido = valorRecebidoEl ? (parseFloat(valorRecebidoEl.value) || total) : total;
        if (valorRecebido < total) valorRecebido = total; // ensure at least total
        troco = valorRecebido - total;
    }

    if (payment === 'fiado' && entrada >= total) {
        alert('O valor da entrada não pode ser maior ou igual ao total da venda a prazo. Se o cliente vai pagar tudo agora, selecione outro método de pagamento.');
        return;
    }

    const tipoNota = document.querySelector('input[name="tipo_nota"]:checked')?.value || 'nao_fiscal';

    const data = {
        subtotal: subtotal,
        discount_percent: discountPercent,
        total: total,
        items: cart,
        pagamento: payment,
        entrada_valor: entrada,
        valor_recebido: valorRecebido,
        troco: troco,
        cliente_id: selectedCustomerId,
        nome_cliente_avulso: selectedCustomerId ? null : selectedCustomerName,
        cpf_cliente: selectedCustomerCPF,
        pv_id: currentPvId,
        supervisor_id: authSupervisorId,
        supervisor_credential: authSupervisorCredential,
        tipo_nota: tipoNota
    };

    const res = await fetch('vendas.php?action=checkout', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    });

    const result = await res.json();
    if (result.success) {
        // Close modals if open
        const modalEntrada = bootstrap.Modal.getInstance(document.getElementById('modalEntrada'));
        if (modalEntrada) modalEntrada.hide();

        showSuccessModal(result.sale_id, data.total, result.tipo_nota || data.tipo_nota, troco, valorRecebido);
        cart = [];
        currentPvId = null;
        isAuthorized = false;
        authSupervisorId = null;
        authSupervisorCredential = null;
        document.getElementById('discountPercent').value = 0;
        if (document.getElementById('entradaValor')) document.getElementById('entradaValor').value = 0;
        renderCart();
        loadRecentSales();
    } else {
        alert('Erro ao finalizar: ' + result.error);
    }
}

function showSuccessModal(saleId, total, tipoNota, troco = 0, valorRecebido = null) {
    const isFiscal = tipoNota === 'fiscal';
    const tipoLabel = isFiscal
        ? '<span class="badge bg-success mb-3"><i class="fas fa-file-invoice-dollar me-1"></i>Venda Fiscal</span>'
        : '<span class="badge bg-secondary mb-3"><i class="fas fa-receipt me-1"></i>Venda Não Fiscal</span>';

    // Troco block (Açaidinhos style - show prominently in green for dinheiro)
    const trocoBlock = (troco > 0)
        ? `<div class="alert alert-success py-2 px-3 mb-3 d-flex justify-content-between align-items-center">
               <span class="fw-bold"><i class="fas fa-coins me-1"></i>TROCO</span>
               <span class="fw-bold fs-4">R$ ${troco.toFixed(2).replace('.', ',')}</span>
           </div>`
        : '';

    const btnPrint = isFiscal
        ? `<button class="btn btn-success btn-lg fw-bold py-3 shadow-sm" id="btnNFCeModal" onclick="issueNFCe(${saleId})">
               <i class="fas fa-file-invoice-dollar me-2"></i>EMITIR NFC-e (Nota Fiscal)
           </button>`
        : `<button class="btn btn-primary btn-lg fw-bold py-3 shadow-sm" onclick="imprimirRecibo(${saleId})">
               <i class="fas fa-print me-2"></i>IMPRIMIR RECIBO
           </button>`;

    const modalHtml = `
        <div class="modal fade" id="modalSuccess" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-0 shadow-lg">
                    <div class="modal-body text-center p-5">
                        <div class="mb-3">
                            <i class="fas fa-check-circle text-success" style="font-size: 4.5rem;"></i>
                        </div>
                        ${tipoLabel}
                        <h3 class="fw-bold mb-2">Venda Finalizada!</h3>
                        <p class="text-muted mb-3">Venda <strong>#${saleId}</strong> — <strong>R$ ${total.toFixed(2).replace('.', ',')}</strong></p>
                        ${trocoBlock}
                        <div class="d-grid gap-2">
                            ${btnPrint}
                            <button class="btn btn-link text-muted mt-1" data-bs-dismiss="modal">Fechar e Nova Venda (ESC)</button>
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

    // For non-fiscal: auto-open print window
    if (!isFiscal) {
        setTimeout(() => imprimirRecibo(saleId), 400);
    }
}

function imprimirRecibo(saleId) {
    window.open('recibo_venda.php?id=' + saleId, '_blank', 'width=480,height=700,toolbar=0,menubar=0,location=0');
}

async function issueNFCe(saleId) {
    const btn = document.getElementById('btnNFCeModal') || event.currentTarget;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Aberta central de emissão SEFAZ...';

    // Open emitir.php directly which handles the Sefaz XML+SOAP and then redirects to the final DANFE.
    // This replicates the original robust behaviour fully.
    const url = `nfce/emitir.php?venda_id=${saleId}`;
    window.open(url, '_blank', 'width=800,height=900,toolbar=0,menubar=0,location=0');
    
    setTimeout(() => {
        btn.innerHTML = '<i class="fas fa-check me-2"></i>Emitindo em nova janela...';
        btn.className = 'btn btn-outline-success btn-lg fw-bold py-3 w-100';
    }, 1500);
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
        const response = await fetch(`vendas.php?action=search&term=${encodeURIComponent(val)}`);
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
