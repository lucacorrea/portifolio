<?php require_once '../app/views/partials/header.php'; ?>

<style>
    .product-search {
        font-size: 1.2rem;
        padding: 15px;
        background: #fff;
        border: 2px solid #0d6efd;
    }
    .product-card {
        transition: transform 0.2s;
        cursor: pointer;
    }
    .product-card:hover {
        transform: scale(1.02);
        border-color: #0d6efd;
    }
    .price-option {
        cursor: pointer;
        transition: background 0.2s;
    }
    .price-option:hover {
        background-color: #e9ecef;
    }
    .price-option.active {
        background-color: #0d6efd;
        color: white;
    }
    .cart-table th, .cart-table td {
        vertical-align: middle;
    }
</style>

<div class="row h-100" style="min-height: 80vh;">
    <!-- Left Column: Search & Product Details -->
    <div class="col-md-7 d-flex flex-column">
        
        <!-- Search Bar -->
        <div class="card mb-3 shadow-sm">
            <div class="card-body p-2">
                <div class="input-group">
                    <span class="input-group-text bg-primary text-white"><i class="bi bi-upc-scan fs-4"></i></span>
                    <input type="text" id="searchInput" class="form-control product-search" placeholder="F1 - Digite código, barras ou nome..." autocomplete="off" autofocus>
                </div>
            </div>
        </div>

        <!-- Product Display Area -->
        <div id="productDisplay" class="card shadow-sm flex-grow-1 d-none">
            <div class="card-body">
                <div class="row h-100">
                    <div class="col-md-4 text-center">
                        <div class="bg-light d-flex align-items-center justify-content-center h-100 rounded" style="min-height: 200px;">
                            <i class="bi bi-box-seam display-1 text-muted"></i>
                            <!-- <img src="..." class="img-fluid" alt="Foto"> -->
                        </div>
                    </div>
                    <div class="col-md-8">
                        <h3 id="pName" class="display-6 fw-bold mb-3">Nome do Produto</h3>
                        <div class="mb-2 text-muted">Cód: <span id="pCode">000000</span> | EAN: <span id="pEan">0000</span></div>
                        <div class="mb-3"><span class="badge bg-secondary" id="pCategory">Categoria</span> <span class="badge bg-info" id="pStock">Estoque: 100</span></div>
                        
                        <div class="row g-2 mb-4">
                            <div class="col-4">
                                <div class="border rounded p-2 text-center price-option active" onclick="selectPrice('normal')" id="priceNormalBox">
                                    <small>Normal</small>
                                    <div class="fw-bold fs-5">R$ <span id="priceNormal">0,00</span></div>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="border rounded p-2 text-center price-option" onclick="selectPrice('avista')" id="priceAvistaBox">
                                    <small>À Vista (-10%)</small>
                                    <div class="fw-bold fs-5">R$ <span id="priceAvista">0,00</span></div>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="border rounded p-2 text-center price-option" onclick="selectPrice('pref')" id="pricePrefBox">
                                    <small>Prefeitura (+15%)</small>
                                    <div class="fw-bold fs-5">R$ <span id="pricePref">0,00</span></div>
                                </div>
                            </div>
                        </div>

                        <div class="row align-items-end">
                            <div class="col-md-4">
                                <label class="form-label">Quantidade</label>
                                <input type="number" id="pQty" class="form-control form-control-lg text-center" value="1" min="1">
                            </div>
                            <div class="col-md-8">
                                <button class="btn btn-primary btn-lg w-100" onclick="addToCart()">
                                    <i class="bi bi-cart-plus"></i> Adicionar (Enter)
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Search Results (Absolute/Dropdown could be better, but explicit list is more 'technical') -->
        <div id="searchResults" class="list-group shadow-sm position-absolute w-50" style="z-index: 1000; top: 80px; display: none;">
            <!-- filled by JS -->
        </div>

    </div>

    <!-- Right Column: Cart & Totals -->
    <div class="col-md-5 d-flex flex-column">
        <div class="card shadow-sm flex-grow-1">
            <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Itens da Pré-Venda</h5>
                <span class="badge bg-primary rounded-pill" id="cartCount">0 itens</span>
            </div>
            <div class="card-body p-0 overflow-auto" style="max-height: 400px;">
                <table class="table table-hover mb-0 cart-table">
                    <thead class="table-light sticky-top">
                        <tr>
                            <th>Item</th>
                            <th class="text-center">Qtd</th>
                            <th class="text-end">Unit.</th>
                            <th class="text-end">Total</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody id="cartTableBody">
                        <!-- Cart Items -->
                    </tbody>
                </table>
            </div>
            <div class="card-footer bg-light p-3">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <span class="h5 mb-0 text-muted">Total Geral</span>
                    <span class="display-6 fw-bold text-dark">R$ <span id="cartTotal">0,00</span></span>
                </div>
                <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                    <button class="btn btn-outline-danger me-md-2" onclick="clearCart()">Cancelar (F8)</button>
                    <button class="btn btn-success flex-grow-1" onclick="finalizePreSale()">Finalizar Pré-Venda (F10)</button>
                </div>
            </div>
        </div>
        
        <!-- Last Sales History (Compact) -->
        <div class="card mt-3 shadow-sm">
            <div class="card-header py-1 small fw-bold text-muted bg-white">
                Últimas Pré-Vendas
            </div>
            <ul class="list-group list-group-flush small" style="max-height: 150px; overflow-y: auto;">
                <?php foreach ($lastSales as $sale): ?>
                <li class="list-group-item d-flex justify-content-between align-items-center p-1 px-3">
                    <span>#<?php echo $sale['id']; ?> - <?php echo $sale['cliente_nome'] ?? 'Cliente'; ?></span>
                    <span class="fw-bold">R$ <?php echo number_format($sale['total'], 2, ',', '.'); ?></span>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
</div>

<script>
    let currentProduct = null;
    let selectedPriceType = 'normal'; // normal, avista, pref
    let cart = [];

    const searchInput = document.getElementById('searchInput');
    const searchResults = document.getElementById('searchResults');
    const productDisplay = document.getElementById('productDisplay');

    // Focus on load
    document.addEventListener('DOMContentLoaded', () => searchInput.focus());

    // Shortcuts
    document.addEventListener('keydown', (e) => {
        if (e.key === 'F1') { e.preventDefault(); searchInput.focus(); }
        if (e.key === 'F8') { e.preventDefault(); clearCart(); }
        if (e.key === 'F10') { e.preventDefault(); finalizePreSale(); }
    });

    // Search Logic
    searchInput.addEventListener('input', debounce(async (e) => {
        const term = e.target.value;
        if (term.length < 2) {
            searchResults.style.display = 'none';
            return;
        }

        const res = await fetch(`?url=prevenda/searchProduct&term=${term}`);
        const data = await res.json();
        
        searchResults.innerHTML = '';
        if (data.length > 0) {
            searchResults.style.display = 'block';
            data.forEach(p => {
                const a = document.createElement('a');
                a.className = 'list-group-item list-group-item-action';
                a.innerHTML = `<strong>${p.nome}</strong> <small class='text-muted'>${p.codigo_interno}</small>`;
                a.onclick = () => selectProduct(p);
                searchResults.appendChild(a);
            });
        } else {
            searchResults.style.display = 'none';
        }
    }, 300));

    function selectProduct(p) {
        currentProduct = p;
        searchResults.style.display = 'none';
        searchInput.value = '';
        
        // Show Display
        productDisplay.classList.remove('d-none');
        
        document.getElementById('pName').textContent = p.nome;
        document.getElementById('pCode').textContent = p.codigo_interno;
        document.getElementById('pEan').textContent = p.codigo_barras;
        document.getElementById('pCategory').textContent = p.categoria_nome;
        
        document.getElementById('priceNormal').textContent = formatMoney(p.preco_venda);
        document.getElementById('priceAvista').textContent = formatMoney(p.preco_avista);
        document.getElementById('pricePref').textContent = formatMoney(p.preco_prefeitura);
        
        document.getElementById('pQty').value = 1;
        document.getElementById('pQty').focus();
        
        selectPrice('normal');
    }

    function selectPrice(type) {
        selectedPriceType = type;
        document.querySelectorAll('.price-option').forEach(el => el.classList.remove('active'));
        
        if(type === 'normal') document.getElementById('priceNormalBox').classList.add('active');
        if(type === 'avista') document.getElementById('priceAvistaBox').classList.add('active');
        if(type === 'pref') document.getElementById('pricePrefBox').classList.add('active');
    }

    function addToCart() {
        if (!currentProduct) return;
        
        const qty = parseInt(document.getElementById('pQty').value);
        let price = 0;
        
        if (selectedPriceType === 'normal') price = currentProduct.preco_venda;
        if (selectedPriceType === 'avista') price = currentProduct.preco_avista;
        if (selectedPriceType === 'pref') price = currentProduct.preco_prefeitura;
        
        cart.push({
            id: currentProduct.id,
            name: currentProduct.nome,
            qty: qty,
            unit_price: price,
            total: price * qty
        });
        
        updateCartUI();
        
        // Reset Display
        productDisplay.classList.add('d-none');
        currentProduct = null;
        searchInput.focus();
    }

    function updateCartUI() {
        const tbody = document.getElementById('cartTableBody');
        tbody.innerHTML = '';
        let total = 0;
        
        cart.forEach((item, index) => {
            total += item.total;
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td><small>${item.name}</small></td>
                <td class="text-center">${item.qty}</td>
                <td class="text-end">${formatMoney(item.unit_price)}</td>
                <td class="text-end fw-bold">${formatMoney(item.total)}</td>
                <td class="text-center"><i class="bi bi-trash text-danger cursor-pointer" onclick="removeFromCart(${index})"></i></td>
            `;
            tbody.appendChild(tr);
        });
        
        document.getElementById('cartCount').textContent = cart.length + ' itens';
        document.getElementById('cartTotal').textContent = formatMoney(total);
    }

    function removeFromCart(index) {
        cart.splice(index, 1);
        updateCartUI();
    }

    function clearCart() {
        if(confirm('Limpar pré-venda atual?')) {
            cart = [];
            updateCartUI();
        }
    }

    async function finalizePreSale() {
        if (cart.length === 0) {
            alert('Carrinho vazio!');
            return;
        }
        
        const total = parseFloat(document.getElementById('cartTotal').textContent.replace('.', '').replace(',', '.'));
        
        const data = {
            items: cart.map(i => ({
                produto_id: i.id,
                quantidade: i.qty,
                preco_unitario: i.unit_price,
                subtotal: i.total
            })),
            total: total
        };
        
        const res = await fetch('?url=prevenda/save', {
            method: 'POST',
            body: JSON.stringify(data)
        });
        
        const result = await res.json();
        if (result.success) {
            alert('Pré-Venda #' + result.id + ' gerada com sucesso! Envie o cliente ao caixa.');
            location.reload();
        } else {
            alert('Erro ao salvar pré-venda.');
        }
    }

    // Utilities
    function debounce(func, wait) {
        let timeout;
        return function(...args) {
            const context = this;
            clearTimeout(timeout);
            timeout = setTimeout(() => func.apply(context, args), wait);
        };
    }

    function formatMoney(value) {
        return parseFloat(value).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }
</script>

<?php require_once '../app/views/partials/footer.php'; ?>
