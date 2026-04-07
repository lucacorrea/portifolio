<div class="row mb-4">
    <div class="col-md-3">
        <div class="stat-card primary">
            <div>
                <div class="stat-label">Vendas Hoje</div>
                <div class="stat-value" id="stat-today-count">0</div>
            </div>
            <div class="stat-icon"><i class="fas fa-shopping-cart"></i></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card success">
            <div>
                <div class="stat-label">Total Vendido</div>
                <div class="stat-value" id="stat-total-val">R$ 0,00</div>
            </div>
            <div class="stat-icon"><i class="fas fa-dollar-sign"></i></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card danger">
            <div>
                <div class="stat-label">Cancelamentos</div>
                <div class="stat-value" id="stat-cancel-count">0</div>
            </div>
            <div class="stat-icon"><i class="fas fa-times-circle"></i></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card secondary">
            <div>
                <div class="stat-label">Ticket Médio</div>
                <div class="stat-value" id="stat-avg-ticket">R$ 0,00</div>
            </div>
            <div class="stat-icon"><i class="fas fa-chart-line"></i></div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white py-3">
        <h6 class="mb-0 fw-bold"><i class="fas fa-filter me-2 text-primary"></i>Filtros de Busca</h6>
    </div>
    <div class="card-body">
        <form id="filterForm" class="row g-3">
            <div class="col-md-2">
                <label class="form-label small">Data Início</label>
                <input type="date" name="data_inicio" class="form-control" value="<?= $filters['data_inicio'] ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label small">Data Fim</label>
                <input type="date" name="data_fim" class="form-control" value="<?= $filters['data_fim'] ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label small">Status</label>
                <select name="status" class="form-select">
                    <option value="">Todos</option>
                    <option value="concluido">Concluído</option>
                    <option value="cancelado">Cancelado</option>
                    <option value="pendente">Pendente</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small">Tipo Nota</label>
                <select name="tipo_nota" class="form-select">
                    <option value="">Todos</option>
                    <option value="fiscal">Fiscal (NFC-e)</option>
                    <option value="nao_fiscal">Não Fiscal</option>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label small">Buscar Cliente / ID / CPF</label>
                <div class="input-group">
                    <input type="text" name="search" class="form-control" placeholder="Nome, ID da venda ou CPF...">
                    <button class="btn btn-primary" type="submit"><i class="fas fa-search"></i></button>
                    <button class="btn btn-outline-secondary" type="button" id="clearFilters"><i class="fas fa-eraser"></i></button>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="industrial-table" id="salesTable">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Data/Hora</th>
                        <th>Cliente</th>
                        <th>Pagamento</th>
                        <th>Tipo</th>
                        <th>Total</th>
                        <th>Status</th>
                        <th class="text-end">Ações</th>
                    </tr>
                </thead>
                <tbody id="salesList">
                    <!-- AJAX content -->
                </tbody>
            </table>
        </div>
    </div>
    <div class="card-footer bg-white border-0 py-3" id="paginationArea">
        <!-- Pagination content -->
    </div>
</div>

<!-- Modal Detalhes -->
<div class="modal fade" id="modalDetail" tabindex="-1">
    <div class="modal-dialog modal-lg border-0">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-light border-0">
                <h5 class="modal-title fw-bold">Detalhes da Venda #<span id="det-id"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row mb-4">
                    <div class="col-md-6">
                        <p class="mb-1 text-muted small uppercase fw-bold">Cliente</p>
                        <p class="mb-0 fw-bold" id="det-cliente"></p>
                    </div>
                    <div class="col-md-3">
                        <p class="mb-1 text-muted small uppercase fw-bold">Data</p>
                        <p class="mb-0" id="det-data"></p>
                    </div>
                    <div class="col-md-3">
                        <p class="mb-1 text-muted small uppercase fw-bold">Vendedor</p>
                        <p class="mb-0" id="det-vendedor"></p>
                    </div>
                </div>
                <table class="table table-sm table-hover align-middle">
                    <thead class="bg-light">
                        <tr>
                            <th>Produto</th>
                            <th class="text-center">Qtd</th>
                            <th class="text-end">V. Unit</th>
                            <th class="text-end">Subtotal</th>
                            <th class="text-end">Troca</th>
                        </tr>
                    </thead>
                    <tbody id="det-items"></tbody>
                </table>
                <div class="row mt-3">
                    <div class="col-md-8">
                        <div class="p-3 bg-light rounded">
                            <p class="mb-1 small"><b>Forma de Pagamento:</b> <span id="det-pgto"></span></p>
                            <p class="mb-0 small"><b>Tipo de Nota:</b> <span id="det-tipo-nota"></span></p>
                        </div>
                    </div>
                    <div class="col-md-4 text-end">
                        <p class="mb-1 text-muted">Desconto: <span id="det-desconto"></span></p>
                        <h4 class="fw-bold mb-0 text-primary" id="det-total"></h4>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 p-3">
                <button type="button" class="btn btn-secondary px-4 rounded-pill" data-bs-dismiss="modal">Fechar</button>
                <div id="det-print-btn"></div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Cancelamento -->
<div class="modal fade" id="modalCancel" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow-lg overflow-hidden">
            <div class="modal-header bg-danger text-white border-0 py-3">
                <h5 class="modal-title fw-bold"><i class="fas fa-exclamation-triangle me-2"></i>Cancelar Venda #<span id="cancel-id-label"></span></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <p class="text-muted">Tem certeza que deseja cancelar esta venda? Esta ação irá:</p>
                <ul class="small text-muted mb-4">
                    <li>Devolver todos os itens ao estoque automaticamente.</li>
                    <li>Estornar o valor no caixa (se concluída em dinheiro/cartão).</li>
                    <li>Cancelar títulos de "Fiado" vinculados.</li>
                </ul>
                <div class="mb-3">
                    <label class="form-label fw-bold">Motivo do Cancelamento</label>
                    <textarea id="cancel-motivo" class="form-control" rows="3" placeholder="Obrigatório descrever o motivo..."></textarea>
                </div>
                <div id="fiscal-alert" class="alert alert-info small d-none">
                    <i class="fas fa-file-invoice-dollar"></i> <b>NFC-e Fiscal:</b> Esta venda será cancelada automaticamente na SEFAZ. O motivo deve ter pelo menos 15 caracteres.
                </div>
            </div>
            <div class="modal-footer bg-light border-0">
                <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">Manter Venda</button>
                <button type="button" id="confirmCancelBtn" class="btn btn-danger px-4 rounded-pill">Confirmar Cancelamento</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Troca (Simplificado) -->
<div class="modal fade" id="modalExchange" tabindex="-1">
    <div class="modal-dialog modal-md border-0">
        <div class="modal-content border-0 shadow-lg">
            <header class="modal-header bg-primary text-white border-0 py-3">
                <h5 class="modal-title fw-bold">🔄 Trocar Item da Venda</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </header>
            <div class="modal-body p-4">
                <div id="exchange-original" class="mb-4 p-3 bg-light rounded">
                    <p class="extra-small text-muted text-uppercase mb-1">Item Original</p>
                    <div class="d-flex justify-content-between align-items-center">
                        <span id="exch-original-name" class="fw-bold"></span>
                        <span id="exch-original-qtd" class="badge bg-secondary"></span>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold">Buscar Novo Produto</label>
                    <div class="position-relative">
                        <input type="text" id="new-prod-search" class="form-control" placeholder="Digite nome ou código...">
                        <div id="exchange-results" class="list-group position-absolute w-100 shadow-lg d-none" style="z-index: 1050; max-height: 200px; overflow-y: auto;"></div>
                    </div>
                </div>

                <div id="exchange-new-data" class="d-none">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small">Quantidade</label>
                            <input type="number" id="exch-new-qty" class="form-control" step="0.01" value="1">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small">Preço Unitário</label>
                            <input type="number" id="exch-new-price" class="form-control" step="0.01">
                        </div>
                    </div>
                    <div class="mt-4 p-3 border-top border-primary border-opacity-10 bg-primary bg-opacity-10 rounded">
                        <div class="d-flex justify-content-between">
                            <span class="small">Diferença a ajustar:</span>
                            <span id="exch-diff" class="fw-bold">R$ 0,00</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light border-0">
                <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">Fechar</button>
                <button type="button" id="confirmExchangeBtn" class="btn btn-primary px-4 rounded-pill d-none">Processar Troca</button>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        let currentPage = 1;
        const salesList = document.getElementById('salesList');
        const paginationArea = document.getElementById('paginationArea');
        const filterForm = document.getElementById('filterForm');
        
        // --- Core Functions ---
        async function loadSales(page = 1) {
            currentPage = page;
            const formData = new FormData(filterForm);
            const params = new URLSearchParams(formData);
            params.append('page', page);
            params.append('action', 'sold_search');

            salesList.innerHTML = '<tr><td colspan="8" class="text-center py-5"><div class="spinner-border text-primary"></div></td></tr>';

            try {
                const response = await fetch('vendidos.php?' + params.toString());
                const data = await response.json();
                renderTable(data.sales);
                renderPagination(data);
                if (page === 1) updateStats(data.sales);
            } catch (err) {
                salesList.innerHTML = '<tr><td colspan="8" class="text-center py-5 text-danger">Erro ao carregar dados.</td></tr>';
            }
        }

        function renderTable(sales) {
            if (!sales || sales.length === 0) {
                salesList.innerHTML = '<tr><td colspan="8" class="text-center py-5">Nenhuma venda encontrada para os filtros aplicados.</td></tr>';
                return;
            }

            salesList.innerHTML = sales.map(s => `
                <tr>
                    <td class="fw-bold">#${s.id}</td>
                    <td>${s.data_formatada}</td>
                    <td>
                        <div class="fw-bold">${s.cliente_nome || 'Consumidor'}</div>
                        <small class="text-muted extra-small">${s.cpf_cliente || ''}</small>
                    </td>
                    <td><span class="text-uppercase small">${s.forma_pagamento.replace('_', ' ')}</span></td>
                    <td>
                        <span class="badge ${s.tipo_nota === 'fiscal' ? 'bg-info bg-opacity-10 text-info' : 'bg-light text-muted'}">
                            ${s.tipo_nota === 'fiscal' ? 'FISCAL' : 'NÃO FISCAL'}
                        </span>
                    </td>
                    <td class="fw-bold">R$ ${s.valor_formatado}</td>
                    <td>
                        <span class="badge ${getStatusBadge(s.status)}">
                            ${s.status.toUpperCase()}
                        </span>
                    </td>
                    <td class="text-end">
                        <div class="dropdown">
                            <button class="btn btn-light btn-sm rounded-circle" data-bs-toggle="dropdown">
                                <i class="fas fa-ellipsis-v"></i>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end border-0 shadow-lg">
                                <li><a class="dropdown-item py-2" href="javascript:void(0)" onclick="viewDetail(${s.id})"><i class="fas fa-eye me-2 text-primary"></i>Ver Detalhes</a></li>
                                <li><a class="dropdown-item py-2" href="javascript:void(0)" onclick="printSale(${s.id}, '${s.tipo_nota}', '${s.chave_acesso || ''}')"><i class="fas fa-print me-2 text-muted"></i>Imprimir Nota</a></li>
                                ${s.status === 'concluido' ? `
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item py-2 text-danger" href="javascript:void(0)" onclick="openCancelModal(${s.id}, '${s.tipo_nota}')"><i class="fas fa-times me-2"></i>Cancelar Venda</a></li>
                                ` : ''}
                            </ul>
                        </div>
                    </td>
                </tr>
            `).join('');
        }

        function getStatusBadge(status) {
            switch(status) {
                case 'concluido': return 'bg-success bg-opacity-10 text-success';
                case 'cancelado': return 'bg-danger bg-opacity-10 text-danger';
                default: return 'bg-warning bg-opacity-10 text-warning';
            }
        }

        function renderPagination(data) {
            if (data.totalPages <= 1) {
                paginationArea.innerHTML = '';
                return;
            }

            let html = '<nav><ul class="pagination pagination-sm mb-0 justify-content-center">';
            for (let i = 1; i <= data.totalPages; i++) {
                html += `<li class="page-item ${i === data.page ? 'active' : ''}">
                    <a class="page-link" href="#" onclick="loadSales(${i}); return false;">${i}</a>
                </li>`;
            }
            html += '</ul></nav>';
            paginationArea.innerHTML = html;
        }

        function updateStats(sales) {
            // Summary logic here (mocked for speed)
            document.getElementById('stat-today-count').textContent = sales.filter(s => s.status === 'concluido').length;
            const total = sales.filter(s => s.status === 'concluido').reduce((acc, s) => acc + parseFloat(s.valor_total), 0);
            document.getElementById('stat-total-val').textContent = 'R$ ' + total.toLocaleString('pt-BR', {minimumFractionDigits:2});
            document.getElementById('stat-cancel-count').textContent = sales.filter(s => s.status === 'cancelado').length;
        }

        // --- Interaction Handlers ---
        // Debounce helper
        let debounceTimer;
        function debounceSearch() {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => {
                loadSales(1);
            }, 400);
        }

        // Automatic filtering on change/input
        filterForm.querySelectorAll('select, input[type="date"]').forEach(input => {
            input.addEventListener('change', () => loadSales(1));
        });

        filterForm.querySelector('input[name="search"]').addEventListener('input', debounceSearch);

        filterForm.addEventListener('submit', function(e) {
            e.preventDefault();
            loadSales(1);
        });

        document.getElementById('clearFilters').addEventListener('click', () => {
            filterForm.reset();
            loadSales(1);
        });

        window.viewDetail = async function(id) {
            const res = await fetch(`vendidos.php?action=get_sale_detail&id=${id}`);
            const data = await res.json();
            if (data.success) {
                const s = data.sale;
                document.getElementById('det-id').textContent = s.id;
                document.getElementById('det-cliente').textContent = s.cliente_nome || 'Consumidor Avulso';
                document.getElementById('det-data').textContent = s.data_formatada;
                document.getElementById('det-vendedor').textContent = s.vendedor_nome || '—';
                document.getElementById('det-pgto').textContent = s.forma_pagamento.replace('_', ' ').toUpperCase();
                document.getElementById('det-tipo-nota').textContent = s.tipo_nota === 'fiscal' ? 'FISCAL (NFC-e)' : 'NÃO FISCAL';
                document.getElementById('det-desconto').textContent = 'R$ ' + parseFloat(s.desconto_total).toLocaleString('pt-BR', {minimumFractionDigits:2});
                document.getElementById('det-total').textContent = 'R$ ' + parseFloat(s.valor_total).toLocaleString('pt-BR', {minimumFractionDigits:2});
                
                document.getElementById('det-items').innerHTML = s.itens.map(it => `
                    <tr>
                        <td class="fw-bold">${it.produto_nome}</td>
                        <td class="text-center">${parseFloat(it.quantidade)} ${it.unidade}</td>
                        <td class="text-end">R$ ${it.preco_formatado}</td>
                        <td class="text-end fw-bold">R$ ${it.subtotal_formatado}</td>
                        <td class="text-end">
                            ${s.status === 'concluido' ? `<button class="btn btn-outline-primary btn-sm rounded-pill px-3" onclick="openExchange(${s.id}, ${it.id}, '${it.produto_nome.replace("'", "\\'")}', ${it.quantidade}, ${it.preco_unitario})">Trocar</button>` : '—'}
                        </td>
                    </tr>
                `).join('');

                document.getElementById('det-print-btn').innerHTML = `<button class="btn btn-primary px-4 rounded-pill" onclick="printSale(${s.id}, '${s.tipo_nota}', '${s.chave_acesso || ''}')"><i class="fas fa-print me-2"></i>Imprimir ${s.tipo_nota === 'fiscal' ? 'NFC-e' : 'Recibo'}</button>`;
                
                new bootstrap.Modal('#modalDetail').show();
            }
        };

        window.printSale = function(id, tipo, chave = '') {
            let url = tipo === 'fiscal' ? `nfce/danfe_nfce.php?venda_id=${id}` : `recibo_venda.php?id=${id}`;
            if (tipo === 'fiscal' && chave) url += `&chave=${chave}`;
            window.open(url, '_blank');
        };

        let currentCancelId = null;
        let currentCancelTipo = null;
        window.openCancelModal = function(id, tipo) {
            currentCancelId = id;
            currentCancelTipo = tipo;
            
            const labelEl = document.getElementById('cancel-id-label');
            const alertEl = document.getElementById('fiscal-alert');
            const motiveInput = document.getElementById('cancel-motivo');
            
            if (labelEl) labelEl.textContent = id;
            if (motiveInput) {
                motiveInput.value = '';
                motiveInput.placeholder = (tipo === 'fiscal') 
                    ? "Descreva o motivo (mínimo 15 caracteres)..." 
                    : "Obrigatório descrever o motivo...";
            }
            
            if (alertEl) {
                if (tipo === 'fiscal') alertEl.classList.remove('d-none');
                else alertEl.classList.add('d-none');
            }
            
            const modalEl = document.getElementById('modalCancel');
            if (modalEl) {
                bootstrap.Modal.getOrCreateInstance(modalEl).show();
            } else {
                console.error("Modal #modalCancel não encontrado no DOM");
                alert("Erro: Modal de cancelamento não encontrado.");
            }
        };

        document.getElementById('confirmCancelBtn').addEventListener('click', async function() {
            const motivo = document.getElementById('cancel-motivo').value.trim();
            
            if (currentCancelTipo === 'fiscal' && motivo.length < 15) {
                alert('Para cancelamento fiscal, o motivo deve ter no mínimo 15 caracteres.');
                return;
            } else if (motivo.length < 5) {
                alert('Por favor, descreva o motivo do cancelamento.');
                return;
            }

            this.disabled = true;
            const originalText = this.innerHTML;
            this.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Processando...';

            try {
                const res = await fetch('vendidos.php?action=cancel_sale', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({ id: currentCancelId, motivo, tipo: currentCancelTipo })
                });
                const data = await res.json();
                if (data.success) {
                    const modalEl = document.getElementById('modalCancel');
                    bootstrap.Modal.getOrCreateInstance(modalEl).hide();
                    alert(currentCancelTipo === 'fiscal' ? 'Venda e NFC-e canceladas com sucesso!' : 'Venda cancelada com sucesso!');
                    loadSales(currentPage);
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

        // --- Exchange Logic ---
        let exchData = { vendaId: null, itemId: null, oldPrice: 0, newProdId: null };
        window.openExchange = function(vId, iId, name, qtd, price) {
            exchData = { vendaId: vId, itemId: iId, oldPrice: price, oldQtd: qtd };
            document.getElementById('exch-original-name').textContent = name;
            document.getElementById('exch-original-qtd').textContent = qtd + ' unid.';
            document.getElementById('new-prod-search').value = '';
            document.getElementById('exchange-results').classList.add('d-none');
            document.getElementById('exchange-new-data').classList.add('d-none');
            document.getElementById('confirmExchangeBtn').classList.add('d-none');
            bootstrap.Modal.getInstance('#modalDetail').hide();
            new bootstrap.Modal('#modalExchange').show();
        };

        const searchInput = document.getElementById('new-prod-search');
        const resultsBox = document.getElementById('exchange-results');

        searchInput.addEventListener('input', async function() {
            const term = this.value.trim();
            if (term.length < 2) {
                resultsBox.classList.add('d-none');
                return;
            }
            const res = await fetch(`vendas.php?action=search&term=${encodeURIComponent(term)}`);
            const prods = await res.json();
            resultsBox.innerHTML = prods.filter(p => p.type === 'product').map(p => `
                <button type="button" class="list-group-item list-group-item-action py-2" onclick="selectNewProd(${p.id}, '${p.nome.replace("'", "\\'")}', ${p.preco_venda})">
                    <div class="d-flex justify-content-between">
                        <span class="fw-bold fw-bold-600">${p.nome}</span>
                        <span class="text-primary fw-bold">R$ ${parseFloat(p.preco_venda).toLocaleString('pt-BR', {minimumFractionDigits:2})}</span>
                    </div>
                </button>
            `).join('');
            resultsBox.classList.remove('d-none');
        });

        window.selectNewProd = function(id, name, price) {
            exchData.newProdId = id;
            exchData.newPrice = price;
            resultsBox.classList.add('d-none');
            searchInput.value = name;
            document.getElementById('exch-new-price').value = price;
            document.getElementById('exch-new-qty').value = exchData.oldQtd;
            document.getElementById('exchange-new-data').classList.remove('d-none');
            document.getElementById('confirmExchangeBtn').classList.remove('d-none');
            calculateDiff();
        };

        const calculateDiff = () => {
            const q = parseFloat(document.getElementById('exch-new-qty').value) || 0;
            const p = parseFloat(document.getElementById('exch-new-price').value) || 0;
            const newTotal = q * p;
            const oldTotal = exchData.oldQtd * exchData.oldPrice;
            const diff = newTotal - oldTotal;
            const diffEl = document.getElementById('exch-diff');
            diffEl.textContent = (diff >= 0 ? '+ ' : '- ') + 'R$ ' + Math.abs(diff).toLocaleString('pt-BR', {minimumFractionDigits:2});
            diffEl.className = diff >= 0 ? 'fw-bold text-success' : 'fw-bold text-danger';
        };

        document.getElementById('exch-new-qty').addEventListener('input', calculateDiff);
        document.getElementById('exch-new-price').addEventListener('input', calculateDiff);

        document.getElementById('confirmExchangeBtn').addEventListener('click', async function() {
            const newQty = parseFloat(document.getElementById('exch-new-qty').value);
            const newPrice = parseFloat(document.getElementById('exch-new-price').value);
            
            this.disabled = true;
            try {
                const res = await fetch('vendidos.php?action=exchange_item', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({
                        venda_id: exchData.vendaId,
                        item_id: exchData.itemId,
                        new_product_id: exchData.newProdId,
                        new_qty: newQty,
                        new_price: newPrice
                    })
                });
                const data = await res.json();
                if (data.success) {
                    bootstrap.Modal.getInstance('#modalExchange').hide();
                    loadSales(currentPage);
                    alert('Item trocado com sucesso!');
                } else {
                    alert('Erro: ' + data.error);
                }
            } catch (err) {
                alert('Erro na requisição.');
            } finally {
                this.disabled = false;
            }
        });

        // Initial load
        loadSales();
    });
</script>

<style>
    .fw-bold-600 { font-weight: 600; }
    .extra-small { font-size: 0.7rem; }
    .uppercase { text-transform: uppercase; }
    .pagination .page-item.active .page-link { background-color: var(--erp-primary); border-color: var(--erp-primary); color: #000; }
    .pagination .page-link { color: var(--text-secondary); }
    .list-group-item-action:hover { background-color: var(--erp-primary); color: #000; }
</style>
