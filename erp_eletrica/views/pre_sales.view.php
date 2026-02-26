<div class="row g-4">
    <!-- Left Side: Product selection & Preview -->
    <div class="col-lg-8 d-flex flex-column">
        <div class="row g-4 mb-4">
            <div class="col-md-9" style="z-index: 2000;">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body" style="overflow: visible !important;">
                        <div class="position-relative">
                            <div class="input-group input-group-lg shadow-sm rounded">
                                <span class="input-group-text bg-white border-end-0 text-muted">
                                    <i class="fas fa-barcode"></i>
                                </span>
                                <input type="text" id="pv_product_search" class="form-control border-start-0 ps-0" placeholder="Pesquise por material ou código para o orçamento..." autocomplete="off">
                            </div>
                            <div id="pv_search_results" class="list-group shadow-lg d-none" style="position: absolute; top: 100%; left: 0; z-index: 9999; width: 100%; max-height: 400px; overflow-y: auto; background: white !important; border: 1px solid #ddd; margin-top: 5px;">
                                <!-- Results will be injected here -->
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Product Preview -->
            <div class="col-md-3">
                <div class="card border-0 shadow-sm h-100 text-center d-flex flex-column align-items-center justify-content-center p-3">
                    <div id="pvPreviewImg" class="bg-light rounded mb-2 d-flex align-items-center justify-content-center border" style="width: 100px; height: 100px; overflow: hidden;">
                        <i class="fas fa-image fs-1 text-muted opacity-25"></i>
                    </div>
                    <div id="pvPreviewName" class="extra-small fw-bold text-uppercase text-muted">Aguardando...</div>
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
                                <th class="ps-4" width="80">Item</th>
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
                        <select id="pv_cliente_id" class="form-select bg-light border-start-0">
                            <option value="">CONSUMIDOR FINAL</option>
                            <!-- Searchable list could be here -->
                        </select>
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

                <div class="alert alert-info border-0 shadow-sm small mb-4">
                    <i class="fas fa-info-circle me-2"></i>
                    A pré-venda reserva o estoque temporariamente e gera um código para o caixa.
                </div>

                <button class="btn btn-lg w-100 py-3 fw-bold shadow-sm border-0 text-white" style="background-color: var(--erp-primary) !important;" onclick="generatePreSale()">
                    <i class="fas fa-check-circle me-2"></i>CONFIRMAR PRÉ-VENDA (F9)
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

<script>
let pvCart = [];
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
        return;
    }

    const response = await fetch(`vendas.php?action=search&term=${term}`);
    const products = await response.json();
    
    renderPVSearchResults(products);
});

function renderPVSearchResults(products) {
    pvSearchResults.innerHTML = '';
    if (products.length === 0) {
        pvSearchResults.classList.add('d-none');
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
        item.onmouseover = () => showPvPreview(p);
        item.onclick = () => addToPVCart(p);
        pvSearchResults.appendChild(item);
    });
    pvSearchResults.classList.remove('d-none');
}

function showPvPreview(p) {
    if (p.imagens) {
        pvPreviewImg.innerHTML = `<img src="public/uploads/produtos/${p.imagens}" style="width:100%; height:100%; object-fit:cover;" class="fade-in">`;
    } else {
        pvPreviewImg.innerHTML = `<i class="fas fa-image fs-1 text-muted opacity-25"></i>`;
    }
    pvPreviewName.innerText = p.nome;
}

function addToPVCart(product) {
    const existing = pvCart.find(i => i.id === product.id);
    if (existing) {
        existing.qty++;
    } else {
        pvCart.push({
            id: product.id,
            nome: product.nome,
            price: parseFloat(product.preco_venda),
            qty: 1,
            imagens: product.imagens
        });
    }
    
    pvSearchInput.value = '';
    pvSearchResults.classList.add('d-none');
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
            <td class="ps-4 fw-bold text-muted">#${item.id}</td>
            <td>${item.nome}</td>
            <td class="text-center">
                <input type="number" class="form-control form-control-sm text-center mx-auto" style="width: 70px" value="${item.qty}" min="1" onchange="updatePVQty(${index}, this.value)">
            </td>
            <td class="text-end">R$ ${item.price.toFixed(2).replace('.', ',')}</td>
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

function removeFromPVCart(index) {
    pvCart.splice(index, 1);
    renderPVCart();
}

async function generatePreSale() {
    if (pvCart.length === 0) return;
    
    const data = {
        cliente_id: document.getElementById('pv_cliente_id').value || null,
        items: pvCart,
        valor_total: pvCart.reduce((acc, i) => acc + (i.price * i.qty), 0)
    };

    const res = await fetch('pre_vendas.php?action=save', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    });

    const result = await res.json();
    if (result.success) {
        document.getElementById('pv_generated_code').innerText = result.codigo;
        const modal = new bootstrap.Modal(document.getElementById('modal-pv-success'));
        modal.show();
    } else {
        alert('Erro ao gerar pré-venda: ' + result.error);
    }
}

function printPVSlip() {
    const code = document.getElementById('pv_generated_code').innerText;
    window.open('pre_venda_imprimir.php?code=' + code, '_blank', 'width=400,height=600');
}

document.addEventListener('keydown', (e) => {
    if (e.key === 'F9') {
        generatePreSale();
    }
});
</script>
