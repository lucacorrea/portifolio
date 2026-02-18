<?php require_once '../app/views/partials/header.php'; ?>

<div class="row h-100">
    <!-- Left Column: Product Search & Catalog -->
    <div class="col-md-7 d-flex flex-column h-100">
        <div class="card flex-grow-1 mb-3">
            <div class="card-body d-flex flex-column">
                <div class="mb-3">
                    <input type="text" id="product-search" class="form-control form-control-lg" placeholder="Buscar produto por nome, código ou barras (F2)..." autocomplete="off">
                    <div id="search-results" class="list-group position-absolute w-100 mt-1" style="max-height: 300px; overflow-y: auto; z-index: 1000; display: none;"></div>
                </div>

                <!-- Product Grid (Placeholder for quick access) -->
                <div class="overflow-auto flex-grow-1" id="product-grid" style="max-height: 60vh;">
                    <div class="text-center text-muted mt-5">
                        <i class="bi bi-basket display-1"></i>
                        <p class="mt-3">Use a busca para adicionar produtos ou escaneie o código de barras.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Right Column: Cart & Checkout -->
    <div class="col-md-5 d-flex flex-column h-100">
        <div class="card flex-grow-1 border-primary">
            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-cart"></i> Carrinho de Compras</h5>
                <span id="cart-count" class="badge bg-light text-dark">0 itens</span>
            </div>
            
            <div class="card-body p-0 d-flex flex-column">
                <!-- Cart Items List -->
                <div class="table-responsive flex-grow-1" style="max-height: 40vh; overflow-y: auto;">
                    <table class="table table-sm table-striped mb-0">
                        <thead class="sticky-top bg-light">
                            <tr>
                                <th>Item</th>
                                <th class="text-center">Qtd</th>
                                <th class="text-end">Total</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody id="cart-items">
                            <!-- Items will be injected here via JS -->
                        </tbody>
                    </table>
                </div>

                <!-- Totals & Actions -->
                <div class="p-3 bg-light border-top">
                    <!-- Client Selection -->
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-uppercase">Cliente</label>
                         <select id="cliente-select" class="form-select form-select-sm">
                            <?php foreach ($clientes as $cliente): ?>
                                <option value="<?php echo $cliente['id']; ?>" <?php echo ($cliente['nome'] == 'Cliente Balcão') ? 'selected' : ''; ?>>
                                    <?php echo $cliente['nome']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Payment Method -->
                     <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-uppercase">Forma de Pagto</label>
                            <select id="payment-method" class="form-select form-select-sm">
                                <option value="Dinheiro">Dinheiro</option>
                                <option value="Pix">Pix</option>
                                <option value="Cartão de Crédito">Cartão de Crédito</option>
                                <option value="Cartão de Débito">Cartão de Débito</option>
                                <option value="Misto">Misto</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                             <label class="form-label small fw-bold text-uppercase">Desconto (R$)</label>
                             <input type="number" id="discount-input" class="form-control form-control-sm" value="0.00" step="0.01">
                        </div>
                    </div>

                    <div class="d-flex justify-content-between align-items-end mb-3">
                        <span class="h5 mb-0">TOTAL A PAGAR</span>
                        <span class="h2 mb-0 text-primary fw-bold" id="total-display">R$ 0,00</span>
                    </div>

                    <button id="btn-finalize" class="btn btn-success w-100 btn-lg shadow-sm">
                        <i class="bi bi-check-circle-fill"></i> FINALIZAR VENDA (F9)
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Config for JS -->
<script>
    const API_URL_SEARCH = '?url=vendas/search';
    const API_URL_STORE = '?url=vendas/store';
</script>
<script src="../public/assets/js/pdv.js"></script>

<?php require_once '../app/views/partials/footer.php'; ?>
