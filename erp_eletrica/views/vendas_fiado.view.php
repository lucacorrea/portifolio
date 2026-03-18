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
            <thead class="bg-light text-secondary border-bottom">
                <tr>
                    <th class="ps-4 py-3">Venda #</th>
                    <th>Data</th>
                    <th>Cliente</th>
                    <th class="text-end">Total</th>
                    <th class="text-end">Recebido</th>
                    <th class="text-end">Saldo</th>
                    <th class="text-center">Status</th>
                    <th class="text-end pe-4">Ações</th>
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

<!-- Modal Detalhes (Apenas Visualização) -->
<div class="modal fade" id="modalDetalhes" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-dark border-0 py-3 px-4">
                <h5 class="modal-title fw-bold text-white shadow-sm"><i class="fas fa-eye me-2 text-warning"></i><span style="color: #ffffff !important;">DETALHES DA DÍVIDA</span></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="row g-4">
                    <div class="col-md-6">
                        <h6 class="fw-bold text-uppercase small text-muted mb-3 border-bottom pb-2">Informações Gerais</h6>
                        <div class="mb-2"><span class="text-muted small">Cliente:</span> <span id="det-cliente" class="fw-bold d-block"></span></div>
                        <div class="mb-2"><span class="text-muted small">Venda:</span> <span id="det-venda" class="fw-bold d-block"></span></div>
                        <div class="mb-2"><span class="text-muted small">Vencimento:</span> <span id="det-vencimento" class="fw-bold d-block text-danger"></span></div>
                        
                        <h6 class="fw-bold text-uppercase small text-muted mb-3 mt-4 border-bottom pb-2">Produtos da Venda</h6>
                        <div id="det-itens" class="small overflow-auto" style="max-height: 250px;">
                            <!-- Itens via JS -->
                        </div>
                    </div>
                    <div class="col-md-6 border-start">
                        <h6 class="fw-bold text-uppercase small text-muted mb-3 border-bottom pb-2">Resumo Financeiro</h6>
                        <div class="d-flex justify-content-between mb-2"><span>Total da Venda:</span> <span id="det-total" class="fw-bold"></span></div>
                        <div class="d-flex justify-content-between mb-2"><span>Total Recebido:</span> <span id="det-pago" class="fw-bold text-success"></span></div>
                        <div class="d-flex justify-content-between mb-3"><span>Saldo Devedor:</span> <span id="det-restante" class="fw-bold text-danger fs-5"></span></div>

                        <h6 class="fw-bold text-uppercase small text-muted mb-3 mt-4 border-bottom pb-2">Histórico (AVS)</h6>
                        <div id="det-pagos" class="small overflow-auto" style="max-height: 200px;">
                            <!-- Pagamentos via JS -->
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Receber AVS (Pagamento Individual) -->
<div class="modal fade" id="modalPagamento" tabindex="-1">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-dark border-0 py-3 px-4">
                <h6 class="modal-title fw-bold text-white shadow-sm"><i class="fas fa-hand-holding-dollar me-2 text-warning"></i><span style="color: #ffffff !important;">RECEBER PAGAMENTO (AVS)</span></h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <!-- Balance Card -->
                <div class="mb-4 border-0 shadow-sm rounded-4 overflow-hidden">
                    <div class="bg-primary bg-opacity-10 p-3 text-center">
                        <div class="small fw-bold text-primary text-uppercase mb-1" id="pay-cliente-nome" style="letter-spacing: 0.5px;"></div>
                        <div class="fs-2 fw-bold text-dark" id="pay-saldo-display"></div>
                        <div class="extra-small text-muted text-uppercase fw-bold opacity-75">Saldo Devedor Atual</div>
                    </div>
                </div>

                <!-- Input Group: Valor -->
                <div class="mb-3">
                    <label class="form-label extra-small fw-bold text-uppercase text-muted ps-1">Valor a Receber</label>
                    <div class="input-group input-group-lg shadow-sm">
                        <span class="input-group-text bg-white border-end-0 text-success fw-bold">R$</span>
                        <input type="number" id="pay-valor" class="form-control border-start-0 ps-0 fw-bold fs-4" step="0.01" placeholder="0,00">
                    </div>
                </div>
                
                <!-- Input Group: Método -->
                <div class="mb-4">
                    <label class="form-label extra-small fw-bold text-uppercase text-muted ps-1">Forma de Recebimento</label>
                    <div class="input-group shadow-sm">
                        <span class="input-group-text bg-white border-end-0 text-muted">
                            <i class="fas fa-credit-card"></i>
                        </span>
                        <select id="pay-metodo" class="form-select border-start-0 ps-0 fw-bold">
                            <option value="DINHEIRO">🛒 Dinheiro em Espécie</option>
                            <option value="PIX">⚡ Transferência PIX</option>
                            <option value="CARTAO">💳 Cartão (Crédito/Débito)</option>
                        </select>
                    </div>
                </div>

                <div class="d-grid gap-2 mt-4">
                    <button class="btn btn-dark btn-lg fw-bold py-3 shadow border-0 rounded-3 position-relative overflow-hidden" onclick="confirmarPagamento()">
                        <div class="position-absolute top-0 start-0 w-100 h-100 bg-primary opacity-10"></div>
                        <span class="position-relative">
                           <i class="fas fa-check-circle me-2 text-primary"></i>CONFIRMAR BAIXA (AVS)
                        </span>
                    </button>
                    <button class="btn btn-link btn-sm text-muted text-decoration-none fw-bold" data-bs-dismiss="modal">
                        Cancelar operação
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    let allFiados = [];
    let currentId = null;

    document.addEventListener('DOMContentLoaded', () => {
        // Set default dates (beginning of current month to today)
        const now = new Date();
        const firstDay = new Date(now.getFullYear(), now.getMonth(), 1);
        document.getElementById('fi-di').value = firstDay.toISOString().split('T')[0];
        document.getElementById('fi-df').value = now.toISOString().split('T')[0];
        
        loadFiados();
    });

    async function loadFiados() {
        const tbody = document.getElementById('fi-tbody');
        try {
            const di = document.getElementById('fi-di').value || '';
            const df = document.getElementById('fi-df').value || '';
            const status = document.getElementById('fi-status').value || 'TODOS';

            const res = await fetch(`fiado.php?action=fetch&di=${di}&df=${df}&status=${status}`);
            const text = await res.text();
            
            let data;
            try {
                data = JSON.parse(text);
            } catch (e) {
                console.error("Server Response Error:", text);
                tbody.innerHTML = `<tr><td colspan="8" class="text-center py-5 text-danger">
                    <i class="fas fa-exclamation-circle mb-2 d-block fs-3"></i>
                    <strong>Erro na resposta do servidor (JSON Inválido)</strong><br>
                    <div class="text-start mt-2 p-3 bg-dark text-white rounded small overflow-auto" style="max-height: 250px; white-space: pre-wrap;">
                        ${text.replace(/</g, "&lt;").replace(/>/g, "&gt;")}
                    </div>
                </td></tr>`;
                return;
            }

            if (data.ok) {
                allFiados = data.rows || [];
                document.getElementById('tot-total').innerText = fmtBRL(data.totais.total_venda);
                document.getElementById('tot-pago').innerText = fmtBRL(data.totais.total_pago);
                document.getElementById('tot-restante').innerText = fmtBRL(data.totais.total_restante);
                document.getElementById('tot-qtd').innerText = data.totais.qtd;
                
                renderTable(allFiados);
            } else {
                tbody.innerHTML = `<tr><td colspan="8" class="text-center py-5 text-danger">
                    <strong>Erro:</strong> ${data.msg || 'Erro desconhecido'}
                </td></tr>`;
            }
        } catch (err) {
            console.error("Fetch Error:", err);
            tbody.innerHTML = `<tr><td colspan="8" class="text-center py-5 text-danger">
                <strong>Falha na conexão:</strong> ${err.message}
            </td></tr>`;
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
                <td class="text-end fw-bold">${fmtBRL(r.valor || 0)}</td>
                <td class="text-end text-success small">${fmtBRL(r.valor_pago || 0)}</td>
                <td class="text-end fw-bold text-danger">${fmtBRL(r.saldo || (r.valor - (r.valor_pago || 0)))}</td>
                <td class="text-center">
                    <span class="fi-status-badge fi-status-${r.status.toLowerCase()}">${r.status}</span>
                </td>
                <td class="text-end pe-4">
                    <div class="btn-group shadow-sm rounded-3">
                        <button class="btn btn-sm btn-white border" onclick="verDetalhes(${r.id})" title="Ver Detalhes">
                            <i class="fas fa-eye text-primary"></i> <span class="d-none d-md-inline ms-1 small">Visualizar</span>
                        </button>
                        ${parseFloat(r.saldo) > 0.01 ? `
                        <button class="btn btn-sm btn-success text-white px-3" onclick="abrirPagar(${r.id})" title="Baixar AVS">
                            <i class="fas fa-hand-holding-dollar"></i> <span class="d-none d-md-inline ms-1 small">Receber AVS</span>
                        </button>
                        ` : ''}
                    </div>
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
        const res = await fetch(`fiado.php?action=get_details&id=${id}`);
        const data = await res.json();

        if (data.ok) {
           const f = data.fiado;
           document.getElementById('det-cliente').innerText = f.cliente_nome;
           document.getElementById('det-venda').innerText = `#${f.venda_id} (${fmtDate(f.data_venda)})`;
           document.getElementById('det-vencimento').innerText = fmtDate(f.data_vencimento, false);
           document.getElementById('det-total').innerText = fmtBRL(f.valor_total);
           document.getElementById('det-pago').innerText = fmtBRL(f.valor_pago);
           document.getElementById('det-restante').innerText = fmtBRL(f.valor_restante);
           
           // Itens
           document.getElementById('det-itens').innerHTML = data.items.map(i => `
               <div class="d-flex justify-content-between mb-2 small pb-1 border-bottom">
                   <div>${i.quantidade}x ${i.produto_nome}</div>
                   <div class="fw-bold">${fmtBRL(i.preco_unitario * i.quantidade)}</div>
               </div>
           `).join('') || '<div class="text-muted small">Nenhum item encontrado.</div>';

           // Pagos
           document.getElementById('det-pagos').innerHTML = data.payments.map(p => `
               <div class="fi-history-item small mb-2">
                   <div class="fw-bold text-primary">${fmtBRL(p.valor)} <span class="text-muted font-normal">• ${p.metodo}</span></div>
                   <div class="text-muted extra-small">${fmtDate(p.created_at)}</div>
               </div>
           `).join('') || '<div class="text-muted small">Sem pagamentos registrados.</div>';

           new bootstrap.Modal('#modalDetalhes').show();
        }
    }

    async function abrirPagar(id) {
        currentId = id;
        const fiado = allFiados.find(f => f.id == id);
        if (!fiado) return;

        document.getElementById('pay-cliente-nome').innerText = fiado.cliente_nome;
        document.getElementById('pay-saldo-display').innerText = `Saldo em aberto: ${fmtBRL(fiado.saldo)}`;
        document.getElementById('pay-valor').value = parseFloat(fiado.saldo).toFixed(2);
        
        new bootstrap.Modal('#modalPagamento').show();
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
            bootstrap.Modal.getInstance('#modalPagamento').hide();
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
        // Adjust for timezone if needed, but simple local conversion is usually fine
        return time ? date.toLocaleString('pt-BR') : date.toLocaleDateString('pt-BR');
    }
</script>
