<?php require_once '../app/views/partials/header.php'; ?>

<div class="row h-100">
    <div class="col-md-12">
        <h4 class="mb-3 border-bottom pb-2">Frente de Caixa - Finalização de Venda</h4>
    </div>

    <!-- Pre-Sale Search -->
    <div class="col-md-4">
        <div class="card shadow-sm mb-3">
            <div class="card-body">
                <label class="form-label fw-bold">Número da Pré-Venda</label>
                <div class="input-group input-group-lg">
                    <input type="number" id="preSaleId" class="form-control" placeholder="Ex: 105" autofocus>
                    <button class="btn btn-primary" onclick="searchPreSale()"><i class="bi bi-search"></i></button>
                </div>
                <small class="text-muted">Pressione ENTER para buscar</small>
            </div>
        </div>

        <div id="paymentArea" class="card shadow-sm d-none">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0">Pagamento</h5>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label">Forma de Pagamento</label>
                    <select id="payMethod" class="form-select form-select-lg">
                        <option value="Dinheiro">Dinheiro</option>
                        <option value="Cartão Débito">Cartão Débito</option>
                        <option value="Cartão Crédito">Cartão Crédito</option>
                        <option value="PIX">PIX</option>
                        <option value="Misto">Misto</option>
                    </select>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Valor Recebido</label>
                    <div class="input-group input-group-lg">
                        <span class="input-group-text">R$</span>
                        <input type="number" id="amountPaid" class="form-control" step="0.01">
                    </div>
                </div>

                <div class="alert alert-info text-center d-none" id="changeAlert">
                    Troco: <strong>R$ <span id="changeVal">0,00</span></strong>
                </div>

                <button class="btn btn-success btn-lg w-100 py-3" onclick="processPayment()">
                    <i class="bi bi-check-circle-fill"></i> FINALIZAR VENDA (F2)
                </button>
            </div>
        </div>
    </div>

    <!-- Sale Details -->
    <div class="col-md-8">
        <div id="saleDetails" class="card shadow-sm h-100 d-none">
            <div class="card-header d-flex justify-content-between">
                <span>Vendedor: <strong id="sellerName">-</strong></span>
                <span>Cliente: <strong id="clientName">-</strong></span>
            </div>
            <div class="card-body p-0 table-responsive" style="max-height: 400px;">
                <table class="table table-striped mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>Cód.</th>
                            <th>Produto</th>
                            <th class="text-center">Qtd</th>
                            <th class="text-end">Unit.</th>
                            <th class="text-end">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody id="saleItems">
                        <!-- Items -->
                    </tbody>
                </table>
            </div>
            <div class="card-footer bg-light text-end p-3">
                <small>Total a Pagar</small>
                <div class="display-4 fw-bold text-primary">R$ <span id="saleTotal">0,00</span></div>
            </div>
        </div>
        
        <div id="emptyState" class="text-center text-muted py-5">
            <i class="bi bi-cart-x display-1 opacity-25"></i>
            <p class="mt-3">Aguardando busca de pré-venda...</p>
        </div>
    </div>
</div>

<script>
    let currentSale = null;

    document.getElementById('preSaleId').addEventListener('keypress', function (e) {
        if (e.key === 'Enter') searchPreSale();
    });

    document.getElementById('amountPaid').addEventListener('input', calculateChange);

    async function searchPreSale() {
        const id = document.getElementById('preSaleId').value;
        if (!id) return;

        const res = await fetch(`?url=caixa/searchPreSale&id=${id}`);
        const data = await res.json();

        if (data.found) {
            currentSale = data;
            renderSale();
        } else {
            alert('Pré-venda não encontrada ou já finalizada.');
        }
    }

    function renderSale() {
        document.getElementById('emptyState').classList.add('d-none');
        document.getElementById('saleDetails').classList.remove('d-none');
        document.getElementById('paymentArea').classList.remove('d-none');

        document.getElementById('sellerName').textContent = currentSale.header.vendedor_nome;
        document.getElementById('clientName').textContent = currentSale.header.cliente_nome;
        
        const tbody = document.getElementById('saleItems');
        tbody.innerHTML = '';
        
        currentSale.items.forEach(item => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>${item.codigo_interno}</td>
                <td>${item.produto_nome}</td>
                <td class="text-center">${item.quantidade}</td>
                <td class="text-end">${formatMoney(item.preco_unitario)}</td>
                <td class="text-end fw-bold">${formatMoney(item.subtotal)}</td>
            `;
            tbody.appendChild(tr);
        });

        document.getElementById('saleTotal').textContent = formatMoney(currentSale.header.total);
        document.getElementById('amountPaid').value = currentSale.header.total; // Suggest full amount
        calculateChange();
    }

    function calculateChange() {
        if (!currentSale) return;
        const total = parseFloat(currentSale.header.total);
        const paid = parseFloat(document.getElementById('amountPaid').value);
        const change = paid - total;

        const alert = document.getElementById('changeAlert');
        if (change > 0) {
            document.getElementById('changeVal').textContent = formatMoney(change);
            alert.classList.remove('d-none');
        } else {
            alert.classList.add('d-none');
        }
    }

    async function processPayment() {
        if (!currentSale) return;
        
        const total = parseFloat(currentSale.header.total);
        const method = document.getElementById('payMethod').value;
        
        const data = {
            pre_sale_id: currentSale.header.id,
            payment: {
                method: method,
                total_final: total,
                discount: 0
            }
        };

        const res = await fetch('?url=caixa/finalize', {
            method: 'POST',
            body: JSON.stringify(data)
        });

        const result = await res.json();
        
        if (result.success) {
            alert('Venda finalizada com SUCESSO!');
            location.reload();
        } else {
            alert('Erro ao finalizar venda.');
        }
    }

    function formatMoney(value) {
        return parseFloat(value).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }
</script>

<?php require_once '../app/views/partials/footer.php'; ?>
