<div class="row g-4">
    <!-- Left Side: Entry process -->
    <div class="col-lg-8 d-flex flex-column">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3">
                <h6 class="mb-0 fw-bold"><i class="fas fa-truck-loading me-2 text-primary"></i>Nova Entrada de Estoque</h6>
            </div>
            <div class="card-body">
                <div class="row g-3 mb-4">
                    <div class="col-12">
                        <label class="form-label small fw-bold text-muted">SELECIONAR FORNECEDOR</label>
                        <select id="pur_supplier" class="form-select shadow-sm">
                            <option value="">-- Buscar Fornecedor --</option>
                            <?php foreach($suppliers as $s): ?>
                            <option value="<?= $s['id'] ?>"><?= $s['nome_fantasia'] ?> (<?= $s['cnpj'] ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="bg-light p-3 rounded-3 border mb-4">
                    <div class="row g-2 align-items-end">
                        <div class="col-md-5">
                            <label class="form-label small fw-bold text-muted">MATERIAL</label>
                            <select id="pur_prod" class="form-select shadow-sm">
                                <option value="">-- Selecionar Material --</option>
                                <?php foreach($products as $p): ?>
                                <option value="<?= $p['id'] ?>" data-price="<?= $p['preco_custo'] ?>" data-name="<?= $p['nome'] ?>"><?= $p['nome'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-bold text-muted">CUSTO UNIT.</label>
                            <input type="number" step="0.01" id="pur_cost" class="form-control shadow-sm" placeholder="0,00">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small fw-bold text-muted">QTD</label>
                            <input type="number" step="0.001" id="pur_qty" class="form-control shadow-sm" value="1">
                        </div>
                        <div class="col-md-2">
                            <button type="button" class="btn btn-primary w-100 fw-bold" onclick="addPurchaseItem()">
                                <i class="fas fa-plus me-1"></i>ADD
                            </button>
                        </div>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-white">
                            <tr>
                                <th>Material</th>
                                <th class="text-end">Custo</th>
                                <th class="text-center">Qtd</th>
                                <th class="text-end">Subtotal</th>
                                <th class="text-center"></th>
                            </tr>
                        </thead>
                        <tbody id="purchase_list">
                            <!-- Items injected here -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Right Side: Summary and History -->
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm h-100 d-flex flex-column">
            <div class="card-header bg-primary text-white py-3">
                <h5 class="mb-0 fw-bold"><i class="fas fa-file-invoice-dollar me-2"></i>Resumo da Entrada</h5>
            </div>
            <div class="card-body flex-grow-1">
                <div class="bg-light p-4 rounded-3 border mb-4 text-center">
                    <span class="text-muted d-block small mb-1 text-uppercase fw-bold">VALOR TOTAL DA NF</span>
                    <h2 class="mb-0 fw-bold text-primary" id="pur_total">R$ 0,00</h2>
                </div>

                <button class="btn btn-success btn-lg w-100 py-3 fw-bold shadow-sm" onclick="finalizarCompra()">
                    <i class="fas fa-check-double me-2"></i>CONCLUIR ENTRADA
                </button>
            </div>
            
            <div class="card-footer bg-white py-3 border-0 mt-auto">
                <h6 class="small fw-bold text-muted text-uppercase mb-2"><i class="fas fa-history me-2"></i>Últimas Entradas</h6>
                <div class="list-group list-group-flush small" id="latestPurchases">
                    <?php foreach($purchases as $pur): ?>
                    <div class="list-group-item d-flex justify-content-between align-items-center px-0 border-0 border-bottom">
                        <div>
                            <div class="fw-bold">#<?= $pur['id'] ?> - <?= $pur['fornecedor_nome'] ?></div>
                            <div class="extra-small text-muted"><?= formatarData($pur['data_compra'] ?? date('Y-m-d')) ?></div>
                        </div>
                        <span class="fw-bold text-primary"><?= formatarMoeda($pur['valor_total'] ?? 0) ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
let items = [];
function addPurchaseItem() {
    const p = document.getElementById('pur_prod');
    const opt = p.options[p.selectedIndex];
    if (!opt.value) return;

    const cost = parseFloat(document.getElementById('pur_cost').value) || 0;
    const qty = parseFloat(document.getElementById('pur_qty').value) || 0;

    items.push({
        id: opt.value,
        name: opt.getAttribute('data-name'),
        cost: cost,
        qty: qty
    });
    renderPList();
}

function renderPList() {
    const body = document.getElementById('purchase_list');
    body.innerHTML = '';
    let total = 0;
    items.forEach((item, index) => {
        let sub = item.cost * item.qty;
        total += sub;
        body.innerHTML += `
            <tr>
                <td class="fw-bold fw-bold">${item.name}</td>
                <td class="text-end">R$ ${item.cost.toFixed(2).replace('.', ',')}</td>
                <td class="text-center">${item.qty}</td>
                <td class="text-end fw-bold">R$ ${sub.toFixed(2).replace('.', ',')}</td>
                <td class="text-center">
                    <button class="btn btn-sm btn-link text-danger p-0" onclick="items.splice(${index},1);renderPList()">
                        <i class="fas fa-times"></i>
                    </button>
                </td>
            </tr>
        `;
    });
    document.getElementById('pur_total').innerText = 'R$ ' + total.toFixed(2).replace('.', ',');
}

function finalizarCompra() {
    const fid = document.getElementById('pur_supplier').value;
    if (!fid || items.length === 0) return alert('Selecione o fornecedor e adicione itens para processar a entrada.');

    const total = items.reduce((acc, i) => acc + (i.cost * i.qty), 0);
    fetch('compras.php?action=process', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({fornecedor_id: fid, items: items, total: total})
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            alert('Entrada de estoque realizada com sucesso!');
            location.reload();
        } else {
            alert('Erro: ' + res.error);
        }
    });
}
</script>
