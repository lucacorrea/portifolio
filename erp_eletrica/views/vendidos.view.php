<div class="row g-4 mb-4">
    <div class="col-12 col-sm-6 col-lg-3">
        <div class="stat-card primary">
            <div>
                <div class="stat-label">Vendas Hoje</div>
                <div class="stat-value" id="stat-today-count">0</div>
            </div>
            <div class="stat-icon"><i class="fas fa-shopping-cart"></i></div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-lg-3">
        <div class="stat-card success">
            <div>
                <div class="stat-label">Total Vendido</div>
                <div class="stat-value" id="stat-total-val">R$ 0,00</div>
            </div>
            <div class="stat-icon"><i class="fas fa-dollar-sign"></i></div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-lg-3">
        <div class="stat-card danger">
            <div>
                <div class="stat-label">Cancelamentos</div>
                <div class="stat-value" id="stat-cancel-count">0</div>
            </div>
            <div class="stat-icon"><i class="fas fa-times-circle"></i></div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-lg-3">
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
                <div class="table-responsive">
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
                </div>
                <div class="row mt-3">
                    <div class="col-md-8">
                        <div class="p-3 bg-light rounded">
                            <p class="mb-1 small"><b>Forma de Pagamento:</b> <span id="det-pgto"></span></p>
                            <p class="mb-1 small"><b>Tipo de Nota:</b> <span id="det-tipo-nota"></span></p>
                            <div id="det-sefaz-status" class="mt-2 d-none">
                                <p class="mb-0 small text-primary fw-bold"><i class="fas fa-info-circle me-1"></i>Retorno SEFAZ:</p>
                                <p class="mb-0 extra-small" id="det-sefaz-msg"></p>
                            </div>
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

<!-- Modal Cancelamento Triplo (Estilo Açaidinhos) -->
<div class="modal fade" id="modalCancel" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0 shadow-lg overflow-hidden">
            <div class="modal-header bg-danger text-white border-0 py-3">
                <h5 class="modal-title fw-bold"><i class="fas fa-exclamation-triangle me-2"></i>Cancelar Venda #<span id="cancel-id-label"></span></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <!-- Passo 1: Escolha do Modelo -->
                <div id="cancel-step-1">
                    <p class="text-muted mb-4 uppercase small fw-bold">Como deseja cancelar esta venda?</p>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <div class="cancel-choice-card p-3 border rounded text-center h-100 cursor-pointer hover-shadow" onclick="selectCancelMode('por_chave')">
                                <div class="icon mb-2 text-danger"><i class="fas fa-file-invoice-dollar fa-2x"></i></div>
                                <div class="fw-bold small">Padrão (110111)</div>
                                <p class="extra-small text-muted mb-0 mt-1">Cancela a nota autorizada normalmente na SEFAZ.</p>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="cancel-choice-card p-3 border rounded text-center h-100 cursor-pointer hover-shadow" onclick="selectCancelMode('por_substituicao')">
                                <div class="icon mb-2 text-primary"><i class="fas fa-sync-alt fa-2x"></i></div>
                                <div class="fw-bold small">Substituição (110112)</div>
                                <p class="extra-small text-muted mb-0 mt-1">Cancela vinculando a uma nota de contingência.</p>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="cancel-choice-card p-3 border rounded text-center h-100 cursor-pointer hover-shadow" onclick="selectCancelMode('por_motivo')">
                                <div class="icon mb-2 text-secondary"><i class="fas fa-database fa-2x"></i></div>
                                <div class="fw-bold small">Apenas Sistema</div>
                                <p class="extra-small text-muted mb-0 mt-1">Cancela internamente sem comunicar a SEFAZ.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Passo 2: Formulário -->
                <div id="cancel-step-2" class="d-none">
                    <button type="button" class="btn btn-link btn-sm p-0 mb-3 text-muted text-decoration-none" onclick="backToCancelChoices()">
                        <i class="fas fa-arrow-left me-1"></i> Voltar para opções
                    </button>
                    
                    <div class="mb-3" id="field-chave-substituta">
                        <label class="form-label fw-bold small">Chave da Nota Substituta (44 dígitos)</label>
                        <input type="text" id="cancel-chave-subst" class="form-control fw-bold" maxlength="44" placeholder="0000 0000 0000 0000 0000...">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small" id="label-motivo">Motivo do Cancelamento</label>
                        <textarea id="cancel-motivo" class="form-control" rows="3" placeholder="Descreva o motivo..."></textarea>
                    </div>

                    <?php if (($_SESSION['usuario_nivel'] ?? '') !== 'admin'): ?>
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-danger"><i class="fas fa-lock me-1"></i>Código de Autorização (Admin)</label>
                        <input type="text" id="cancel-auth-code" class="form-control fw-bold text-center" placeholder="Ex: 123456" maxlength="6" style="font-size: 1.2rem; letter-spacing: 2px;">
                    </div>
                    <?php endif; ?>

                    <div id="fiscal-alert" class="alert alert-info small d-none">
                        <i class="fas fa-info-circle me-1"></i> <b>Nota Fiscal:</b> Este modelo exige validação da SEFAZ. O motivo deve ter 15+ caracteres.
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light border-0 d-none" id="cancel-footer-btns">
                <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">Fechar</button>
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
        window.loadSales = async function(page = 1) {
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
                        <div class="d-flex flex-column align-items-start gap-1">
                            <span class="badge ${getStatusBadge(s.status)}">
                                ${s.status.toUpperCase()}
                            </span>
                            ${(s.status === 'cancelado' && (s.nf_status === '100' || s.nf_status === '150')) ? 
                                `<span class="badge bg-warning text-dark extra-small" title="Esta venda foi cancelada internamente mas a NFC-e ainda consta como ativa na SEFAZ.">
                                    <i class="fas fa-exclamation-triangle me-1"></i>ERRO SEFAZ
                                 </span>` : ''
                            }
                            ${(s.status === 'cancelado' && s.nf_status === '101') ? 
                                `<span class="badge bg-success bg-opacity-10 text-success extra-small border border-success border-opacity-25" title="O cancelamento desta nota foi homologado com sucesso na SEFAZ.">
                                    <i class="fas fa-check-circle me-1"></i>SEFAZ CANCELADA
                                 </span>` : ''
                            }
                        </div>
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
                                ${(s.status === 'cancelado' && (s.nf_status === '100' || s.nf_status === '150')) ? `
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item py-2 text-warning fw-bold" href="javascript:void(0)" onclick="openCancelModal(${s.id}, 'fiscal', true)"><i class="fas fa-cloud-upload-alt me-2"></i>Regularizar na SEFAZ</a></li>
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

            const current = data.page;
            const total = data.totalPages;

            let html = '<div class="d-flex flex-column flex-md-row align-items-center justify-content-center gap-3">';
            html += '<ul class="pagination pagination-sm mb-0">';
            
            // Botão Anterior
            html += `<li class="page-item ${current === 1 ? 'disabled' : ''}">
                <a class="page-link" href="#" onclick="loadSales(${current - 1}); return false;"><i class="fas fa-chevron-left small"></i></a>
            </li>`;

            // Smart Numbers
            let links = [];
            links.push(1);
            if (current > 4) links.push('...');
            for (let i = Math.max(2, current - 2); i <= Math.min(total - 1, current + 2); i++) {
                links.push(i);
            }
            if (current < total - 3) links.push('...');
            if (total > 1) links.push(total);

            links.forEach(link => {
                if (link === '...') {
                    html += '<li class="page-item disabled"><span class="page-link border-0">...</span></li>';
                } else {
                    html += `<li class="page-item ${link === current ? 'active' : ''}">
                        <a class="page-link fw-bold" href="#" onclick="loadSales(${link}); return false;">${link}</a>
                    </li>`;
                }
            });

            // Botão Próximo
            html += `<li class=\"page-item ${current === total ? 'disabled' : ''}">
                <a class=\"page-link\" href=\"#\" onclick=\"loadSales(${current + 1}); return false;\"><i class=\"fas fa-chevron-right small\"></i></a>
            </li>`;
            html += '</ul>';

            // Go to Page Input
            html += '<div class="d-flex align-items-center gap-2">';
            html += '<span class="text-muted small text-nowrap">Ir para:</span>';
            html += `<input type="number" class="form-control form-control-sm text-center" style="width: 60px;" min="1" max="${total}" value="${current}" 
                        onkeydown="if(event.key==='Enter') loadSales(this.value)">`;
            html += `<button class="btn btn-sm btn-outline-secondary" onclick="loadSales(this.previousElementSibling.value)"><i class="fas fa-arrow-right"></i></button>`;
            html += '</div>';

            html += '</div>';
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
                
                const sefazArea = document.getElementById('det-sefaz-status');
                if (s.nf_mensagem) {
                    sefazArea.classList.remove('d-none');
                    document.getElementById('det-sefaz-msg').textContent = s.nf_mensagem;
                } else {
                    sefazArea.classList.add('d-none');
                }

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
        window.openCancelModal = function(id, tipo, isRetry = false) {
            currentCancelId = id;
            currentCancelTipo = tipo;
            
            const labelEl = document.getElementById('cancel-id-label');
            const alertEl = document.getElementById('fiscal-alert');
            const motiveInput = document.getElementById('cancel-motivo');
            const titleEl = document.querySelector('#modalCancel .modal-title');
            
            if (titleEl) titleEl.textContent = isRetry ? 'Regularização Fiscal SEFAZ' : 'Cancelar Venda';
            if (labelEl) labelEl.textContent = id;
            if (motiveInput) {
                motiveInput.value = isRetry ? 'Regularização de nota pendente na SEFAZ' : '';
                motiveInput.placeholder = (tipo === 'fiscal') 
                    ? "Descreva o motivo (mínimo 15 caracteres)..." 
                    : "Obrigatório descrever o motivo...";
            }
            
            const authInput = document.getElementById('cancel-auth-code');
            if (authInput) authInput.value = '';
            
            if (alertEl) {
                if (tipo === 'fiscal' || isRetry) {
                    alertEl.classList.remove('d-none');
                    alertEl.innerHTML = isRetry 
                        ? '<i class="fas fa-info-circle me-2"></i><strong>Modo Regularização:</strong> Esta venda já foi cancelada no sistema. A ação abaixo apenas tentará homologar o cancelamento na SEFAZ.'
                        : '<i class="fas fa-exclamation-triangle me-2"></i>O cancelamento fiscal é irreversível e exige um motivo claro para a SEFAZ.';
                } else {
                    alertEl.classList.add('d-none');
                }
            }
            
            const modalEl = document.getElementById('modalCancel');
            if (modalEl) {
                bootstrap.Modal.getOrCreateInstance(modalEl).show();
            }
        };

        document.getElementById('confirmCancelBtn').addEventListener('click', async function() {
            const motivo = document.getElementById('cancel-motivo').value.trim();
            const authCodeEl = document.getElementById('cancel-auth-code');
            const authCode = authCodeEl ? authCodeEl.value.trim() : null;
            
            if (authCodeEl && !authCode) {
                alert('É necessário inserir o Código de Autorização fornecido pelo administrador.');
                return;
            }
            
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
                    body: JSON.stringify({ id: currentCancelId, motivo, tipo: currentCancelTipo, auth_code: authCode })
                });
                const data = await res.json();
                if (data.success) {
                    const modalEl = document.getElementById('modalCancel');
                    bootstrap.Modal.getOrCreateInstance(modalEl).hide();
                    
                    const isRegularization = document.querySelector('#modalCancel .modal-title').textContent.includes('Regularização');
                    
                    if (isRegularization) {
                        alert('Nota Fiscal cancelada na SEFAZ com sucesso! A situação foi regularizada.');
                    } else {
                        alert(currentCancelTipo === 'fiscal' ? 'Venda e NFC-e canceladas com sucesso!' : 'Venda cancelada com sucesso!');
                    }
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
    .pagination .page-link { 
        color: var(--text-primary); 
        border: 1px solid #e2e8f0; 
        margin: 0 2px; 
        border-radius: 6px !important;
        transition: all 0.2s ease;
        padding: 0.4rem 0.75rem;
    }
    .pagination .page-item.active .page-link { 
        background-color: var(--erp-primary); 
        border-color: var(--erp-primary); 
        color: #fff !important;
        box-shadow: 0 2px 4px rgba(43, 76, 125, 0.3);
    }
    .pagination .page-link:hover:not(.active) {
        background-color: #f1f5f9;
        color: var(--erp-primary);
        border-color: var(--erp-primary);
    }
    .list-group-item-action:hover { background-color: var(--erp-primary); color: #fff; }
</style>
