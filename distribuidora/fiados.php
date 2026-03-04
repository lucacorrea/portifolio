<?php
declare(strict_types=1);
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

@date_default_timezone_set('America/Manaus');

require_once __DIR__ . '/assets/conexao.php';
require_once __DIR__ . '/assets/dados/_helpers.php';

if (!function_exists('db')) {
    die("Erro: Conexão não encontrada.");
}

$pdo = db();
$csrf = csrf_token();

function brl($v): string {
    return 'R$ ' . number_format((float)$v, 2, ',', '.');
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="csrf-token" content="<?= e($csrf) ?>">
    <link rel="shortcut icon" href="assets/images/favicon.svg" type="image/x-icon" />
    <title>Painel da Distribuidora | À Prazo</title>
    <link rel="stylesheet" href="assets/css/bootstrap.min.css" />
    <link rel="stylesheet" href="assets/css/lineicons.css" />
    <link rel="stylesheet" href="assets/css/materialdesignicons.min.css" />
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
    <div id="preloader"><div class="spinner"></div></div>

    <!-- ======== sidebar-nav start =========== -->
    <aside class="sidebar-nav-wrapper">
        <div class="navbar-logo">
            <a href="dashboard.php" class="d-flex align-items-center gap-2">
                <img src="assets/images/logo/logo.svg" alt="logo" />
            </a>
        </div>
        <nav class="sidebar-nav">
            <ul>
                <li class="nav-item">
                    <a href="dashboard.php">
                        <span class="icon">
                            <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M8.74999 18.3333C12.2376 18.3333 15.1364 15.8128 15.7244 12.4941C15.8448 11.8143 15.2737 11.25 14.5833 11.25H9.99999C9.30966 11.25 8.74999 10.6903 8.74999 10V5.41666C8.74999 4.7263 8.18563 4.15512 7.50586 4.27556C4.18711 4.86357 1.66666 7.76243 1.66666 11.25C1.66666 15.162 4.83797 18.3333 8.74999 18.3333Z" />
                                <path d="M17.0833 10C17.7737 10 18.3432 9.43708 18.2408 8.75433C17.7005 5.14918 14.8508 2.29947 11.2457 1.75912C10.5629 1.6568 10 2.2263 10 2.91665V9.16666C10 9.62691 10.3731 10 10.8333 10H17.0833Z" />
                            </svg>
                        </span>
                        <span class="text">Dashboard</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="vendas.php">
                        <span class="icon">
                            <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M1.66666 5C1.66666 3.89543 2.5621 3 3.66666 3H16.3333C17.4379 3 18.3333 3.89543 18.3333 5V15C18.3333 16.1046 17.4379 17 16.3333 17H3.66666C2.5621 17 1.66666 16.1046 1.66666 15V5Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                                <path d="M1.66666 5L10 10.8333L18.3333 5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </span>
                        <span class="text">Vendas</span>
                    </a>
                </li>
                <li class="nav-item active">
                    <a href="#0" class="" data-bs-toggle="collapse" data-bs-target="#ddmenu_operacoes" aria-controls="ddmenu_operacoes" aria-expanded="true">
                        <span class="icon">
                            <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M3.33334 3.35442C3.33334 2.4223 4.07954 1.66666 5.00001 1.66666H15C15.9205 1.66666 16.6667 2.4223 16.6667 3.35442V16.8565C16.6667 17.5519 15.8827 17.9489 15.3333 17.5317L13.8333 16.3924C13.537 16.1673 13.1297 16.1673 12.8333 16.3924L10.5 18.1646C10.2037 18.3896 9.79634 18.3896 9.50001 18.1646L7.16668 16.3924C6.87038 16.1673 6.46298 16.1673 6.16668 16.3924L4.66668 17.5317C4.11731 17.9489 3.33334 17.5519 3.33334 16.8565V3.35442Z" />
                            </svg>
                        </span>
                        <span class="text">Operações</span>
                    </a>
                    <ul id="ddmenu_operacoes" class="collapse show dropdown-nav">
                        <li><a href="vendidos.php">Vendidos</a></li>
                        <li><a href="fiados.php" class="active">À Prazo</a></li>
                        <li><a href="devolucoes.php">Devoluções</a></li>
                    </ul>
                </li>
            </ul>
        </nav>
    </aside>
    <div class="overlay"></div>

    <main class="main-wrapper">
        <header class="header">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-lg-5 col-md-5 col-6">
                        <div class="header-left d-flex align-items-center">
                            <div class="menu-toggle-btn mr-15">
                                <button id="menu-toggle" class="main-btn primary-btn btn-hover">
                                    <i class="lni lni-chevron-left me-2"></i> Menu
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <section class="section">
            <div class="container-fluid">
                <div class="title-wrapper pt-30">
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
        </section>
    </main>

    <!-- Modais (Detalhes e Pagamento) - Mantidos iguais -->
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
                                <tr><th>Produto</th><th>Qtd</th><th class="text-end">Preço</th><th class="text-end">Subtotal</th></tr>
                            </thead>
                            <tbody id="detItemsBody"></tbody>
                        </table>
                    </div>
                    <h6>Histórico de Pagamentos (AVS)</h6>
                    <div class="table-responsive">
                        <table class="table table-sm border">
                            <thead class="bg-light">
                                <tr><th>Data/Hora</th><th>Método</th><th class="text-end">Valor</th></tr>
                            </thead>
                            <tbody id="detPaysBody"></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modalPagamento" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title">Receber Pagamento (AVS)</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info">Cliente: <b id="payCliente"></b><br>Saldo Devedor: <b id="paySaldo"></b></div>
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
                        <div class="d-grid"><button type="submit" class="btn btn-success btn-lg">Confirmar Recebimento</button></div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="assets/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js"></script>
    <script>
        const API = 'assets/dados/fiados_api.php';
        const modalDetalhes = new bootstrap.Modal(document.getElementById('modalDetalhes'));
        const modalPagamento = new bootstrap.Modal(document.getElementById('modalPagamento'));

        function brlJs(v) {
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
                        <td class="val-total">${brlJs(f.valor_total)}</td>
                        <td class="text-success">${brlJs(f.valor_pago)}</td>
                        <td class="val-restante">${brlJs(f.valor_restante)}</td>
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
                document.getElementById('detTotal').innerText = brlJs(f.valor_total);
                document.getElementById('detPago').innerText = brlJs(f.valor_pago);
                document.getElementById('detRestante').innerText = brlJs(f.valor_restante);
                
                document.getElementById('detItemsBody').innerHTML = r.items.map(it => `
                    <tr><td>${it.nome}</td><td>${it.qtd} ${it.unidade}</td><td class="text-end">${brlJs(it.preco_unit)}</td><td class="text-end">${brlJs(it.subtotal)}</td></tr>
                `).join('');
                
                document.getElementById('detPaysBody').innerHTML = r.payments.map(p => `
                    <tr><td>${new Date(p.created_at).toLocaleString()}</td><td>${p.metodo}</td><td class="text-end">${brlJs(p.valor)}</td></tr>
                `).join('') || '<tr><td colspan="3" class="text-center">Nenhum pagamento registrado.</td></tr>';
                
                modalDetalhes.show();
            } catch (e) { alert(e.message); }
        }

        function openPay(id, cliente, restante) {
            document.getElementById('payFiadoId').value = id;
            document.getElementById('payCliente').innerText = cliente;
            document.getElementById('paySaldo').innerText = brlJs(restante);
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
            } catch (e) { alert(e.message); }
        });

        document.getElementById('filterForm').addEventListener('submit', (e) => { e.preventDefault(); loadFiados(); });

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