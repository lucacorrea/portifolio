<!-- Estilos Premium do Fiado (Adaptado) -->
<style>
    :root {
        --fi-primary: #2563eb;
        --fi-primary-hover: #1d4ed8;
        --fi-success: #10b981;
        --fi-danger: #ef4444;
        --fi-warning: #f59e0b;
        --fi-bg: #f8fafc;
        --fi-card: #ffffff;
        --fi-text: #1e293b;
        --fi-border: #e2e8f0;
    }

    .fi-dashboard {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1rem;
        margin-bottom: 1.5rem;
    }

    .fi-card {
        background: var(--fi-card);
        padding: 1.25rem;
        border-radius: 1rem;
        border: 1px solid var(--fi-border);
        box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        transition: transform 0.2s;
    }

    .fi-card:hover { transform: translateY(-2px); }

    .fi-card-label { font-size: 0.875rem; color: #64748b; font-weight: 500; margin-bottom: 0.5rem; }
    .fi-card-value { font-size: 1.5rem; font-weight: 700; color: var(--fi-text); }
    .fi-card-value.text-danger { color: var(--fi-danger) !important; }
    .fi-card-value.text-success { color: var(--fi-success) !important; }

    .fi-filters {
        background: var(--fi-card);
        padding: 1.5rem;
        border-radius: 1rem;
        border: 1px solid var(--fi-border);
        margin-bottom: 1.5rem;
    }

    .fi-table-card {
        background: var(--fi-card);
        border-radius: 1rem;
        border: 1px solid var(--fi-border);
        overflow: hidden;
    }

    .fi-status-badge {
        padding: 0.25rem 0.75rem;
        border-radius: 2rem;
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: uppercase;
    }
    .fi-status-aberto { background: #fee2e2; color: #991b1b; }
    .fi-status-pago { background: #d1fae5; color: #065f46; }

    .fi-btn-detail {
        padding: 0.4rem 0.8rem;
        border-radius: 0.5rem;
        font-size: 0.875rem;
        font-weight: 500;
        transition: all 0.2s;
    }

    /* Modal Styling */
    .fi-modal-info { background: #f1f5f9; border-radius: 0.75rem; padding: 1rem; margin-bottom: 1rem; }
    .fi-history-item { border-left: 2px solid var(--fi-primary); padding-left: 1rem; margin-bottom: 1rem; position: relative; }
    .fi-history-item::before {
        content: '';
        position: absolute;
        left: -5px;
        top: 0;
        width: 8px;
        height: 8px;
        background: var(--fi-primary);
        border-radius: 50%;
    }

    @media (max-width: 768px) {
        .fi-dashboard { grid-template-columns: 1fr 1fr; }
    }
</style>

<div class="row g-4 mb-4">
    <div class="col-12 d-flex justify-content-between align-items-center">
        <div>
            <h4 class="fw-bold mb-0">Gestão de Fiados</h4>
            <p class="text-muted mb-0">Controle total de débitos e recebimentos</p>
        </div>
        <button onclick="exportExcel()" class="btn btn-outline-success rounded-pill px-4">
            <i class="fas fa-file-excel me-2"></i>Exportar Excel
        </button>
    </div>
</div>

<!-- Dashboard -->
<div class="fi-dashboard">
    <div class="fi-card">
        <div class="fi-card-label">Total em Fiados</div>
        <div class="fi-card-value" id="tot-total">R$ 0,00</div>
    </div>
    <div class="fi-card">
        <div class="fi-card-label">Total Recebido</div>
        <div class="fi-card-value text-success" id="tot-pago">R$ 0,00</div>
    </div>
    <div class="fi-card">
        <div class="fi-card-label">Saldo em Aberto</div>
        <div class="fi-card-value text-danger" id="tot-restante">R$ 0,00</div>
    </div>
    <div class="fi-card">
        <div class="fi-card-label">Registros</div>
        <div class="fi-card-value" id="tot-qtd">0</div>
    </div>
</div>

<!-- Filtros -->
<div class="fi-filters">
    <div class="row g-3 align-items-end">
        <div class="col-md-3">
            <label class="form-label small fw-bold">Início</label>
            <input type="date" id="fi-di" class="form-control rounded-3" onchange="loadFiados()">
        </div>
        <div class="col-md-3">
            <label class="form-label small fw-bold">Fim</label>
            <input type="date" id="fi-df" class="form-control rounded-3" onchange="loadFiados()">
        </div>
        <div class="col-md-3">
            <label class="form-label small fw-bold">Status</label>
            <select id="fi-status" class="form-select rounded-3" onchange="loadFiados()">
                <option value="TODOS">Todos os Status</option>
                <option value="PENDENTE">Em Aberto</option>
                <option value="PAGO">Quitados</option>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label small fw-bold">Busca Rápida</label>
            <div class="input-group">
                <span class="input-group-text bg-white border-end-0"><i class="fas fa-search text-muted"></i></span>
                <input type="text" id="fi-search" class="form-control border-start-0 rounded-end-3" placeholder="Filtrar nesta lista..." onkeyup="filterTable()">
            </div>
        </div>
    </div>
</div>

<!-- Tabela de Fiados -->
<div class="fi-table-card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="bg-light text-secondary">
                <tr>
                    <th class="ps-4 py-3">Venda #</th>
                    <th>Data</th>
                    <th>Cliente</th>
                    <th class="text-end">Total</th>
                    <th class="text-end">Recebido</th>
                    <th class="text-end">Saldo</th>
                    <th class="text-center">Status</th>
                    <th class="text-center pe-4">Ações</th>
                </tr>
            </thead>
            <tbody id="fi-tbody">
                <tr>
                    <td colspan="8" class="text-center py-5">
                        <div class="spinner-border text-primary" role="status"></div>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Detalhes -->
<div class="modal fade" id="modalDetalhes" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-light">
                <h5 class="modal-title fw-bold"><i class="fas fa-info-circle me-2"></i>Detalhes do Débito</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="row g-3">
                    <!-- Esquerda: Info e Itens -->
                    <div class="col-md-7">
                        <div class="fi-modal-info shadow-none">
                            <div class="row mb-2">
                                <div class="col-4 text-muted small">Cliente:</div>
                                <div class="col-8 fw-bold" id="det-cliente"></div>
                            </div>
                            <div class="row mb-2">
                                <div class="col-4 text-muted small">Venda:</div>
                                <div class="col-8 fw-bold" id="det-venda"></div>
                            </div>
                            <div class="row mb-2">
                                <div class="col-4 text-muted small">Vencimento:</div>
                                <div class="col-8 fw-bold text-danger" id="det-vencimento"></div>
                            </div>
                        </div>

                        <h6 class="fw-bold mb-3 mt-4"><i class="fas fa-shopping-cart me-2"></i>Itens da Venda</h6>
                        <div id="det-itens" class="border rounded p-3 bg-white" style="max-height: 200px; overflow-y: auto;">
                            <!-- Itens via JS -->
                        </div>
                    </div>

                    <!-- Direita: Histórico e Receber -->
                    <div class="col-md-5">
                        <div class="card border-0 bg-light-subtle h-100">
                            <div class="card-body">
                                <h6 class="fw-bold mb-3"><i class="fas fa-history me-2"></i>Histórico de Pagos</h6>
                                <div id="det-pagos" class="mb-4" style="max-height: 150px; overflow-y: auto;">
                                    <!-- Pagamentos via JS -->
                                </div>

                                <div class="border-top pt-3" id="box-pagamento">
                                    <h6 class="fw-bold mb-3">Registrar Recebimento</h6>
                                    <div class="mb-3">
                                        <label class="form-label small">Valor (R$)</label>
                                        <input type="number" id="pay-valor" class="form-control form-control-lg fw-bold" step="0.01">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label small">Método</label>
                                        <select id="pay-metodo" class="form-select">
                                            <option value="DINHEIRO">Dinheiro</option>
                                            <option value="PIX">Pix</option>
                                            <option value="CARTAO">Cartão</option>
                                        </select>
                                    </div>
                                    <button class="btn btn-primary w-100 fw-bold py-2 rounded-3" onclick="confirmarPagamento()">
                                        Confirmar Recebimento
                                    </button>
                                </div>
                                <div id="box-quitado" class="text-center py-4 d-none">
                                    <i class="fas fa-check-circle text-success fs-1 mb-2"></i>
                                    <div class="fw-bold text-success text-uppercase">Totalmente Pago</div>
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
    let allFiados = [];
    let currentId = null;

    document.addEventListener('DOMContentLoaded', () => {
        // Set default dates (last month)
        const now = new Date();
        const firstDay = new Date(now.getFullYear(), now.getMonth(), 1);
        document.getElementById('fi-di').value = firstDay.toISOString().split('T')[0];
        document.getElementById('fi-df').value = now.toISOString().split('T')[0];
        
        loadFiados();
    });

    async function loadFiados() {
        const di = document.getElementById('fi-di').value;
        const df = document.getElementById('fi-df').value;
        const status = document.getElementById('fi-status').value;

        const res = await fetch(`fiado.php?action=fetch&di=${di}&df=${df}&status=${status}`);
        const data = await res.json();

        if (data.ok) {
            allFiados = data.rows;
            document.getElementById('tot-total').innerText = fmtBRL(data.totais.total_venda);
            document.getElementById('tot-pago').innerText = fmtBRL(data.totais.total_pago);
            document.getElementById('tot-restante').innerText = fmtBRL(data.totais.total_restante);
            document.getElementById('tot-qtd').innerText = data.totais.qtd;
            
            renderTable(allFiados);
        }
    }

    function renderTable(rows) {
        const tbody = document.getElementById('fi-tbody');
        if (rows.length === 0) {
            tbody.innerHTML = `<tr><td colspan="8" class="text-center py-5 text-muted">Nenhum registro encontrado para os filtros selecionados.</td></tr>`;
            return;
        }

        tbody.innerHTML = rows.map(r => `
            <tr>
                <td class="ps-4 fw-bold text-primary">#${r.venda_id}</td>
                <td class="small text-muted">${fmtDate(r.created_at)}</td>
                <td class="fw-bold">${r.cliente_nome}</td>
                <td class="text-end fw-bold">${fmtBRL(r.valor)}</td>
                <td class="text-end text-success small">${fmtBRL(r.valor_pago)}</td>
                <td class="text-end fw-bold text-danger">${fmtBRL(r.saldo)}</td>
                <td class="text-center">
                    <span class="fi-status-badge fi-status-${r.status.toLowerCase()}">${r.status}</span>
                </td>
                <td class="text-center pe-4">
                    <button class="btn btn-light fi-btn-detail border" onclick="verDetalhes(${r.id})">
                        <i class="fas fa-eye me-1 text-primary"></i> Verificar
                    </button>
                </td>
            </tr>
        `).join('');
    }

    function filterTable() {
        const q = document.getElementById('fi-search').value.toLowerCase();
        const filtered = allFiados.filter(r => 
            r.cliente_nome.toLowerCase().includes(q) || 
            r.venda_id.toString().includes(q)
        );
        renderTable(filtered);
    }

    async function verDetalhes(id) {
        currentId = id;
        const res = await fetch(`fiado.php?action=get_details&id=${id}`);
        const data = await res.json();

        if (data.ok) {
           const f = data.fiado;
           document.getElementById('det-cliente').innerText = f.cliente_nome;
           document.getElementById('det-venda').innerText = `#${f.venda_id} (${fmtDate(f.data_venda)})`;
           document.getElementById('det-vencimento').innerText = fmtDate(f.data_vencimento, false);
           
           // Itens
           document.getElementById('det-itens').innerHTML = data.items.map(i => `
               <div class="d-flex justify-content-between mb-2 small pb-1 border-bottom">
                   <div>${i.quantidade}x ${i.produto_nome}</div>
                   <div class="fw-bold">${fmtBRL(i.preco_unitario * i.quantidade)}</div>
               </div>
           `).join('') || '<div class="text-muted small">Nenhum item encontrado.</div>';

           // Pagos
           const h = data.payments;
           document.getElementById('det-pagos').innerHTML = h.map(p => `
               <div class="fi-history-item small mb-2">
                   <div class="fw-bold text-primary">${fmtBRL(p.valor)} <span class="text-muted font-normal">• ${p.metodo}</span></div>
                   <div class="text-muted extra-small">${fmtDate(p.created_at)}</div>
               </div>
           `).join('') || '<div class="text-muted small">Sem pagamentos registrados.</div>';

           // Box Pay
           document.getElementById('pay-valor').value = parseFloat(f.saldo).toFixed(2);
           if (parseFloat(f.saldo) <= 0.01) {
               document.getElementById('box-pagamento').classList.add('d-none');
               document.getElementById('box-quitado').classList.remove('d-none');
           } else {
               document.getElementById('box-pagamento').classList.remove('d-none');
               document.getElementById('box-quitado').classList.add('d-none');
           }

           new bootstrap.Modal('#modalDetalhes').show();
        }
    }

    async function confirmarPagamento() {
        const valor = document.getElementById('pay-valor').value;
        const metodo = document.getElementById('pay-metodo').value;

        if (!valor || valor <= 0) return alert('Valor inválido.');

        const res = await fetch('fiado.php?action=pagar', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({id: currentId, valor: valor, metodo: metodo})
        });

        const data = await res.json();
        if (data.ok) {
            bootstrap.Modal.getInstance('#modalDetalhes').hide();
            await loadFiados();
            alert(data.msg);
        } else {
            alert(data.msg);
        }
    }

    function exportExcel() {
        window.location.href = 'fiado.php?action=excel';
    }

    // Helpers
    function fmtBRL(v) {
        return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(v);
    }

    function fmtDate(d, time = true) {
        if (!d) return '—';
        const date = new Date(d);
        if (isNaN(date)) return d;
        return time ? date.toLocaleString('pt-BR') : date.toLocaleDateString('pt-BR');
    }
</script>
