<?php
declare(strict_types=1);
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

require_once __DIR__ . '/assets/conexao.php';
require_once __DIR__ . '/assets/dados/_helpers.php';

$pdo = db();
$csrf = csrf_token();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="csrf-token" content="<?= e($csrf) ?>">
    <title>Painel da Distribuidora | À Prazo</title>
    <link rel="stylesheet" href="assets/css/bootstrap.min.css" />
    <link rel="stylesheet" href="assets/css/lineicons.css" />
    <link rel="stylesheet" href="assets/css/main.css" />
    <style>
        .card-fiado { border-radius: 16px; border: 1px solid rgba(148,163,184,0.15); background: #fff; margin-bottom: 20px; }
        .card-fiado .head { padding: 15px 20px; border-bottom: 1px solid rgba(148,163,184,0.1); display: flex; justify-content: space-between; align-items: center; }
        .card-fiado .body { padding: 20px; }
        .table-custom thead th { background: #f8fafc; color: #64748b; font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 800; border-bottom: 2px solid #edf2f7; }
        .status-badge { padding: 5px 12px; border-radius: 99px; font-size: 11px; font-weight: 800; }
        .status-aberto { background: #fff7ed; color: #c2410c; border: 1px solid #ffedd5; }
        .status-pago { background: #f0fdf4; color: #15803d; border: 1px solid #dcfce7; }
        .val-total { font-weight: 800; color: #0f172a; }
        .val-restante { font-weight: 800; color: #ef4444; }
        .btn-pay { border-radius: 8px; padding: 5px 10px; font-size: 12px; font-weight: 700; transition: all 0.2s; }
    </style>
</head>
<body>
    <?php include_once 'sidebar.php'; // Assume sidebar exists based on structure ?>
    
    <!-- Se não tiver sidebar.php, o main-wrapper precisa ser ajustado -->
    <main class="main-wrapper" style="margin-left: 0; padding-top: 20px;">
        <div class="container-fluid">
            <div class="title-wrapper mb-30">
                <div class="row align-items-center">
                    <div class="col-md-6">
                        <div class="title">
                            <h2>Gestão de Vendas À Prazo</h2>
                            <p class="text-muted">Listagem, filtros e recebimentos (AVS)</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filtros -->
            <div class="card-fiado mb-30">
                <div class="body">
                    <form id="filterForm" class="row g-3 align-items-end">
                        <div class="col-md-2">
                            <label class="form-label">Data Inicial</label>
                            <input type="date" class="form-control" id="fDi">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Data Final</label>
                            <input type="date" class="form-control" id="fDf">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Canal</label>
                            <select class="form-select" id="fCanal">
                                <option value="TODOS">Todos</option>
                                <option value="PRESENCIAL">Presencial</option>
                                <option value="DELIVERY">Delivery</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Cliente / Venda #</label>
                            <input type="text" class="form-control" id="fSearch" placeholder="Nome do cliente ou ID da venda...">
                        </div>
                        <div class="col-md-2 d-grid">
                            <button type="submit" class="btn btn-primary">
                                <i class="lni lni-funnel"></i> Filtrar
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Tabela -->
            <div class="card-fiado">
                <div class="body p-0">
                    <div class="table-responsive">
                        <table class="table table-custom mb-0">
                            <thead>
                                <tr>
                                    <th class="ps-4">Venda #</th>
                                    <th>Data</th>
                                    <th>Cliente</th>
                                    <th>Total Venda</th>
                                    <th>Total Pago</th>
                                    <th>Restante</th>
                                    <th>Status</th>
                                    <th class="text-end pe-4">Ações</th>
                                </tr>
                            </thead>
                            <tbody id="fiadosTableBody">
                                <tr><td colspan="8" class="text-center p-5">Carregando...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Modal Detalhes -->
    <div class="modal fade" id="modalDetalhes" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Detalhes da Dívida - Venda #<span id="detVendaId"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <h6>Informações do Cliente</h6>
                            <p class="mb-1"><b>Nome:</b> <span id="detCliente"></span></p>
                        </div>
                        <div class="col-md-6 text-end">
                            <h6>Resumo Financeiro</h6>
                            <p class="mb-0">Total: <b id="detTotal"></b></p>
                            <p class="mb-0">Pago: <b class="text-success" id="detPago"></b></p>
                            <p class="mb-0">Restante: <b class="text-danger" id="detRestante"></b></p>
                        </div>
                    </div>

                    <h6>Produtos da Venda</h6>
                    <div class="table-responsive mb-4">
                        <table class="table table-sm border">
                            <thead class="bg-light">
                                <tr>
                                    <th>Produto</th>
                                    <th>Qtd</th>
                                    <th class="text-end">Preço</th>
                                    <th class="text-end">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody id="detItemsBody"></tbody>
                        </table>
                    </div>

                    <h6>Histórico de Pagamentos (AVS)</h6>
                    <div class="table-responsive">
                        <table class="table table-sm border">
                            <thead class="bg-light">
                                <tr>
                                    <th>Data/Hora</th>
                                    <th>Método</th>
                                    <th class="text-end">Valor</th>
                                </tr>
                            </thead>
                            <tbody id="detPaysBody"></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Pagamento -->
    <div class="modal fade" id="modalPagamento" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title">Receber Pagamento (AVS)</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info">
                        Cliente: <b id="payCliente"></b><br>
                        Saldo Devedor: <b id="paySaldo"></b>
                    </div>
                    <form id="payForm">
                        <input type="hidden" id="payFiadoId">
                        <div class="mb-3">
                            <label class="form-label">Valor do Pagamento (R$)</label>
                            <input type="text" class="form-control form-control-lg" id="payValor" placeholder="0,00" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Método de Recebimento</label>
                            <select class="form-select" id="payMetodo">
                                <option value="DINHEIRO">Dinheiro</option>
                                <option value="PIX">Pix</option>
                                <option value="CARTAO">Cartão</option>
                            </select>
                        </div>
                        <div class="d-grid">
                            <button type="submit" class="btn btn-success btn-lg">Confirmar Recebimento</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="assets/js/bootstrap.bundle.min.js"></script>
    <script>
        const API = 'assets/dados/fiados_api.php';
        const modalDetalhes = new bootstrap.Modal(document.getElementById('modalDetalhes'));
        const modalPagamento = new bootstrap.Modal(document.getElementById('modalPagamento'));

        function brl(v) {
            return parseFloat(v).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
        }

        async function loadFiados() {
            try {
                const qs = new URLSearchParams({
                    action: 'fetch',
                    di: document.getElementById('fDi').value,
                    df: document.getElementById('fDf').value,
                    canal: document.getElementById('fCanal').value,
                    q: document.getElementById('fSearch').value
                });
                
                const r = await fetch(`${API}?${qs}`).then(res => res.json());
                const body = document.getElementById('fiadosTableBody');
                
                if (!r.ok) throw new Error(r.msg);
                
                if (r.data.length === 0) {
                    body.innerHTML = '<tr><td colspan="8" class="text-center p-5">Nenhuma venda à prazo encontrada.</td></tr>';
                    return;
                }

                body.innerHTML = r.data.map(f => `
                    <tr>
                        <td class="ps-4"><b>#${f.venda_id}</b></td>
                        <td>${new Date(f.created_at).toLocaleString()}</td>
                        <td>${f.cliente_nome}</td>
                        <td class="val-total">${brl(f.valor_total)}</td>
                        <td class="text-success">${brl(f.valor_pago)}</td>
                        <td class="val-restante">${brl(f.valor_restante)}</td>
                        <td>
                            <span class="status-badge status-${f.status.toLowerCase()}">${f.status}</span>
                        </td>
                        <td class="text-end pe-4">
                            <button class="btn btn-light btn-pay" onclick="showDetails(${f.id})">
                                <i class="lni lni-eye"></i> Detalhes
                            </button>
                            ${f.status === 'ABERTO' ? `
                                <button class="btn btn-success btn-pay text-white ms-1" onclick="openPay(${f.id}, '${f.cliente_nome}', ${f.valor_restante})">
                                    <i class="lni lni-reply"></i> Pagar
                                </button>
                            ` : ''}
                        </td>
                    </tr>
                `).join('');
                
            } catch (e) {
                alert('Erro ao carregar dados: ' + e.message);
            }
        }

        async function showDetails(id) {
            try {
                const r = await fetch(`${API}?action=get_details&id=${id}`).then(res => res.json());
                if (!r.ok) throw new Error(r.msg);
                
                const f = r.fiado;
                document.getElementById('detVendaId').innerText = f.venda_id;
                document.getElementById('detCliente').innerText = f.cliente_nome;
                document.getElementById('detTotal').innerText = brl(f.valor_total);
                document.getElementById('detPago').innerText = brl(f.valor_pago);
                document.getElementById('detRestante').innerText = brl(f.valor_restante);
                
                document.getElementById('detItemsBody').innerHTML = r.items.map(it => `
                    <tr>
                        <td>${it.nome}</td>
                        <td>${it.qtd} ${it.unidade}</td>
                        <td class="text-end">${brl(it.preco_unit)}</td>
                        <td class="text-end">${brl(it.subtotal)}</td>
                    </tr>
                `).join('');
                
                document.getElementById('detPaysBody').innerHTML = r.payments.map(p => `
                    <tr>
                        <td>${new Date(p.created_at).toLocaleString()}</td>
                        <td>${p.metodo}</td>
                        <td class="text-end">${brl(p.valor)}</td>
                    </tr>
                `).join('') || '<tr><td colspan="3" class="text-center">Nenhum pagamento registrado.</td></tr>';
                
                modalDetalhes.show();
            } catch (e) {
                alert(e.message);
            }
        }

        function openPay(id, cliente, restante) {
            document.getElementById('payFiadoId').value = id;
            document.getElementById('payCliente').innerText = cliente;
            document.getElementById('paySaldo').innerText = brl(restante);
            document.getElementById('payValor').value = '';
            modalPagamento.show();
            setTimeout(() => document.getElementById('payValor').focus(), 500);
        }

        document.getElementById('payForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const id = document.getElementById('payFiadoId').value;
            const valorRaw = document.getElementById('payValor').value.replace('.', '').replace(',', '.').replace(/[^\d.]/g, '');
            const valor = parseFloat(valorRaw);
            const metodo = document.getElementById('payMetodo').value;
            
            if (isNaN(valor) || valor <= 0) return alert('Informe um valor válido.');
            
            try {
                const r = await fetch(`${API}?action=pay`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id, valor, metodo })
                }).then(res => res.json());
                
                if (!r.ok) throw new Error(r.msg);
                
                alert(r.msg);
                modalPagamento.hide();
                loadFiados();
            } catch (e) {
                alert(e.message);
            }
        });

        document.getElementById('filterForm').addEventListener('submit', (e) => {
            e.preventDefault();
            loadFiados();
        });

        // Formatação de moeda no input
        document.getElementById('payValor').addEventListener('input', function() {
            let v = this.value.replace(/\D/g, '');
            v = (v / 100).toFixed(2).replace('.', ',');
            v = v.replace(/(\d)(?=(\d{3})+(?!\d))/g, '$1.');
            this.value = v;
        });

        window.onload = loadFiados;
    </script>
</body>
</html>