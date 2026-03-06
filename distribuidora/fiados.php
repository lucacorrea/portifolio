<?php

declare(strict_types=1);
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

// Temporário para debugar o erro 500
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

@date_default_timezone_set('America/Manaus');

// Tenta incluir arquivos de conexão e helpers de forma flexível
$paths = [
    __DIR__ . '/assets/conexao.php',
    __DIR__ . '/assets/dados/vendas/_helpers.php'
];
foreach ($paths as $p) {
    if (is_file($p)) require_once $p;
}

if (!function_exists('db')) {
    die("Erro Crítico: Função db() não encontrada. Verifique se assets/conexao.php existe.");
}

$pdo = db();

if (!function_exists('csrf_token')) {
    function csrf_token()
    {
        return $_SESSION['csrf_token'] ?? '';
    }
}
if (!function_exists('e')) {
    function e($s)
    {
        return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
    }
}

$csrf = csrf_token();

if (!function_exists('brl')) {
    function brl($v): string
    {
        return 'R$ ' . number_format((float)$v, 2, ',', '.');
    }
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
        .card-fiado {
            border-radius: 16px;
            border: 1px solid rgba(148, 163, 184, 0.15);
            background: #fff;
            margin-bottom: 20px;
        }

        .card-fiado .body {
            padding: 20px;
        }

        .status-badge {
            padding: 5px 12px;
            border-radius: 99px;
            font-size: 11px;
            font-weight: 800;
        }

        .status-aberto {
            background: #fff7ed;
            color: #c2410c;
            border: 1px solid #ffedd5;
        }

        .status-pago {
            background: #f0fdf4;
            color: #15803d;
            border: 1px solid #dcfce7;
        }

        .val-total {
            font-weight: 800;
            color: #0f172a;
        }

        .val-restante {
            font-weight: 800;
            color: #ef4444;
        }

        .btn-pay {
            border-radius: 8px;
            padding: 5px 10px;
            font-size: 12px;
            font-weight: 700;
            transition: all 0.2s;
        }

        /* ✅ paginação (parecida com vendidios) */
        .pager-box {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 10px;
            padding: 14px 16px;
            border-top: 1px solid rgba(148, 163, 184, 0.15);
        }

        .pager-box .page-text {
            font-size: 12px;
            color: #64748b;
            font-weight: 800;
        }

        .pager-box .btn-disabled {
            opacity: .45;
            pointer-events: none;
        }

        .pager-left {
            margin-right: auto;
            font-size: 12px;
            color: #64748b;
            font-weight: 700;
        }
    </style>
</head>

<body>
    <div id="preloader">
        <div class="spinner"></div>
    </div>

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
                            <i class="lni lni-dashboard"></i>
                        </span>
                        <span class="text">Dashboard</span>
                    </a>
                </li>

                <li class="nav-item">
                    <a href="vendas.php">
                        <span class="icon">
                            <i class="lni lni-cart"></i>
                        </span>
                        <span class="text">Vendas</span>
                    </a>
                </li>

                <li class="nav-item nav-item-has-children active">
                    <a href="#0" class="collapsed" data-bs-toggle="collapse" data-bs-target="#ddmenu_operacoes" aria-controls="ddmenu_operacoes" aria-expanded="false">
                        <span class="icon">
                            <i class="lni lni-layers"></i>
                        </span>
                        <span class="text">Operações</span>
                    </a>
                    <ul id="ddmenu_operacoes" class="collapse dropdown-nav show">
                        <li><a href="vendidos.php">Vendidos</a></li>
                        <li><a href="fiados.php" class="active">À Prazo</a></li>
                        <li><a href="devolucoes.php">Devoluções</a></li>
                    </ul>
                </li>

                <li class="nav-item nav-item-has-children">
                    <a href="#0" class="collapsed" data-bs-toggle="collapse" data-bs-target="#ddmenu_estoque" aria-controls="ddmenu_estoque" aria-expanded="false">
                        <span class="icon">
                            <i class="lni lni-package"></i>
                        </span>
                        <span class="text">Estoque</span>
                    </a>
                    <ul id="ddmenu_estoque" class="collapse dropdown-nav">
                        <li><a href="produtos.php">Produtos</a></li>
                        <li><a href="inventario.php">Inventário</a></li>
                        <li><a href="entradas.php">Entradas</a></li>
                        <li><a href="saidas.php">Saídas</a></li>
                        <li><a href="estoque-minimo.php">Estoque Mínimo</a></li>
                    </ul>
                </li>

                <li class="nav-item nav-item-has-children">
                    <a href="#0" class="collapsed" data-bs-toggle="collapse" data-bs-target="#ddmenu_cadastros" aria-controls="ddmenu_cadastros" aria-expanded="false">
                        <span class="icon">
                            <i class="lni lni-users"></i>
                        </span>
                        <span class="text">Cadastros</span>
                    </a>
                    <ul id="ddmenu_cadastros" class="collapse dropdown-nav">
                        <li><a href="clientes.php">Clientes</a></li>
                        <li><a href="fornecedores.php">Fornecedores</a></li>
                        <li><a href="categorias.php">Categorias</a></li>
                    </ul>
                </li>

                <li class="nav-item">
                    <a href="relatorios.php">
                        <span class="icon">
                            <i class="lni lni-clipboard"></i>
                        </span>
                        <span class="text">Relatórios</span>
                    </a>
                </li>

                <span class="divider">
                    <hr />
                </span>

                <li class="nav-item nav-item-has-children">
                    <a href="#0" class="collapsed" data-bs-toggle="collapse" data-bs-target="#ddmenu_config" aria-controls="ddmenu_config" aria-expanded="false">
                        <span class="icon">
                            <i class="lni lni-cog"></i>
                        </span>
                        <span class="text">Configurações</span>
                    </a>
                    <ul id="ddmenu_config" class="collapse dropdown-nav">
                        <li><a href="usuarios.php">Usuários e Permissões</a></li>
                        <li><a href="parametros.php">Parâmetros do Sistema</a></li>
                    </ul>
                </li>

                <li class="nav-item">
                    <a href="suporte.php">
                        <span class="icon">
                            <i class="lni lni-whatsapp"></i>
                        </span>
                        <span class="text">Suporte</span>
                    </a>
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
                                <button id="menu-toggle" class="main-btn primary-btn btn-hover" type="button">
                                    <i class="lni lni-chevron-left me-2"></i> Menu
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-7 col-md-7 col-6">
                        <div class="header-right">
                            <div class="profile-box ml-15">
                                <button class="dropdown-toggle bg-transparent border-0" type="button" id="profile" data-bs-toggle="dropdown" aria-expanded="false">
                                    <div class="profile-info">
                                        <div class="info">
                                            <div>
                                                <h6 class="fw-500">Sair</h6>
                                            </div>
                                        </div>
                                    </div>
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
                            <table class="table table-custom mb-0 text-nowrap">
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
                                    <tr>
                                        <td colspan="8" class="text-center p-5">Carregando...</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- ✅ Paginação -->
                        <div class="pager-box">
                            <div class="pager-left" id="pgSummary">—</div>
                            <a href="#0" id="pgPrev" class="main-btn light-btn btn-hover btn-sm btn-disabled" title="Anterior">
                                <i class="lni lni-chevron-left"></i>
                            </a>
                            <span class="page-text" id="pgInfo">Página 1/1</span>
                            <a href="#0" id="pgNext" class="main-btn light-btn btn-hover btn-sm btn-disabled" title="Próxima">
                                <i class="lni lni-chevron-right"></i>
                            </a>
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
                        <table class="table table-sm border p-2">
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
                        <table class="table table-sm border p-2">
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

        const STATE = {
            per: 10,
            page: 1,
            totalPages: 1,
            totalRows: 0,
            rowsAll: [],
            serverPaging: false,
        };

        const $body = document.getElementById('fiadosTableBody');
        const $pgPrev = document.getElementById('pgPrev');
        const $pgNext = document.getElementById('pgNext');
        const $pgInfo = document.getElementById('pgInfo');
        const $pgSummary = document.getElementById('pgSummary');

        function brlJs(v) {
            return parseFloat(v || 0).toLocaleString('pt-BR', {
                style: 'currency',
                currency: 'BRL'
            });
        }

        function toDT(s) {
            try {
                const d = new Date(String(s));
                return isNaN(d.getTime()) ? String(s) : d.toLocaleString();
            } catch (e) {
                return String(s);
            }
        }

        function setPagerUI() {
            $pgInfo.textContent = `Página ${STATE.page}/${STATE.totalPages}`;

            const canPrev = STATE.page > 1;
            const canNext = STATE.page < STATE.totalPages;

            $pgPrev.classList.toggle('btn-disabled', !canPrev);
            $pgNext.classList.toggle('btn-disabled', !canNext);

            const shownFrom = STATE.totalRows ? ((STATE.page - 1) * STATE.per + 1) : 0;
            const shownTo = Math.min(STATE.page * STATE.per, STATE.totalRows);

            $pgSummary.textContent = STATE.totalRows ?
                `Mostrando ${shownFrom}-${shownTo} de ${STATE.totalRows}` :
                '—';
        }

        function renderRows(rows) {
            if (!rows || rows.length === 0) {
                $body.innerHTML = '<tr><td colspan="8" class="text-center p-5">Nenhuma venda à prazo encontrada.</td></tr>';
                return;
            }

            $body.innerHTML = rows.map(f => `
                <tr>
                    <td class="ps-4"><b>#${f.venda_id}</b></td>
                    <td>${toDT(f.created_at)}</td>
                    <td>${(f.cliente_nome ?? '')}</td>
                    <td class="val-total">${brlJs(f.valor_total)}</td>
                    <td class="text-success">${brlJs(f.valor_pago)}</td>
                    <td class="val-restante">${brlJs(f.valor_restante)}</td>
                    <td>
                        <span class="status-badge status-${String(f.status || '').toLowerCase()}">${f.status}</span>
                    </td>
                    <td class="text-end pe-4">
                        <button class="btn btn-light btn-pay" onclick="showDetails(${f.id})">
                            <i class="lni lni-eye"></i> Detalhes
                        </button>
                        ${String(f.status || '') === 'ABERTO' ? `
                            <button class="btn btn-success btn-pay text-white ms-1" onclick="openPay(${f.id}, '${String(f.cliente_nome ?? '').replace(/'/g, "\\'")}', ${Number(f.valor_restante || 0)})">
                                <i class="lni lni-reply"></i> Pagar
                            </button>
                        ` : ''}
                    </td>
                </tr>
            `).join('');
        }

        function sliceClientPage() {
            const start = (STATE.page - 1) * STATE.per;
            const end = start + STATE.per;
            return STATE.rowsAll.slice(start, end);
        }

        async function loadFiados(resetPage = false) {
            try {
                if (resetPage) STATE.page = 1;

                $body.innerHTML = '<tr><td colspan="8" class="text-center p-5">Carregando...</td></tr>';

                const qs = new URLSearchParams({
                    action: 'fetch',
                    di: document.getElementById('fDi').value,
                    df: document.getElementById('fDf').value,
                    canal: document.getElementById('fCanal').value,
                    q: document.getElementById('fSearch').value,
                    // ✅ já manda page/per (se sua API suportar server-side, ela usa)
                    page: String(STATE.page),
                    per: String(STATE.per),
                });

                const r = await fetch(`${API}?${qs}`).then(res => res.json());

                if (!r.ok) throw new Error(r.msg || 'Falha ao carregar fiados.');

                // ✅ Detecta paginação server-side se a API devolver meta/pagination
                // Aceita alguns formatos comuns:
                // - r.pagination: {page, per, totalRows, totalPages}
                // - r.meta: {page, per, total, pages}
                // - r.totalRows/r.totalPages/r.page/r.per
                let server = false;
                let page = STATE.page,
                    per = STATE.per,
                    totalRows = 0,
                    totalPages = 1;

                if (r.pagination && typeof r.pagination === 'object') {
                    server = true;
                    page = parseInt(r.pagination.page ?? page, 10) || page;
                    per = parseInt(r.pagination.per ?? per, 10) || per;
                    totalRows = parseInt(r.pagination.totalRows ?? r.pagination.total ?? 0, 10) || 0;
                    totalPages = parseInt(r.pagination.totalPages ?? r.pagination.pages ?? 1, 10) || 1;
                } else if (r.meta && typeof r.meta === 'object') {
                    server = true;
                    page = parseInt(r.meta.page ?? page, 10) || page;
                    per = parseInt(r.meta.per ?? per, 10) || per;
                    totalRows = parseInt(r.meta.total ?? 0, 10) || 0;
                    totalPages = parseInt(r.meta.pages ?? 1, 10) || 1;
                } else if (typeof r.totalRows !== 'undefined' || typeof r.totalPages !== 'undefined') {
                    server = true;
                    page = parseInt(r.page ?? page, 10) || page;
                    per = parseInt(r.per ?? per, 10) || per;
                    totalRows = parseInt(r.totalRows ?? 0, 10) || 0;
                    totalPages = parseInt(r.totalPages ?? 1, 10) || 1;
                }

                STATE.serverPaging = server;

                if (server) {
                    // server-side: r.data já vem “10 em 10”
                    STATE.page = page;
                    STATE.per = per;
                    STATE.totalRows = totalRows;
                    STATE.totalPages = Math.max(1, totalPages);

                    renderRows(Array.isArray(r.data) ? r.data : []);
                    setPagerUI();
                    return;
                }

                // client-side: pega tudo e fatia 10/10
                STATE.rowsAll = Array.isArray(r.data) ? r.data : [];
                STATE.totalRows = STATE.rowsAll.length;
                STATE.totalPages = Math.max(1, Math.ceil(STATE.totalRows / STATE.per));

                // garante page válida
                if (STATE.page > STATE.totalPages) STATE.page = STATE.totalPages;

                renderRows(sliceClientPage());
                setPagerUI();
            } catch (e) {
                alert('Erro ao carregar dados: ' + (e.message || e));
                $body.innerHTML = '<tr><td colspan="8" class="text-center p-5">Erro ao carregar.</td></tr>';
                STATE.totalRows = 0;
                STATE.totalPages = 1;
                STATE.page = 1;
                setPagerUI();
            }
        }

        async function showDetails(id) {
            try {
                const r = await fetch(`${API}?action=get_details&id=${id}`).then(res => res.json());
                if (!r.ok) throw new Error(r.msg || 'Falha ao buscar detalhes.');

                const f = r.fiado || {};
                document.getElementById('detVendaId').innerText = f.venda_id ?? '';
                document.getElementById('detCliente').innerText = f.cliente_nome ?? '';
                document.getElementById('detTotal').innerText = brlJs(f.valor_total);
                document.getElementById('detPago').innerText = brlJs(f.valor_pago);
                document.getElementById('detRestante').innerText = brlJs(f.valor_restante);

                const items = Array.isArray(r.items) ? r.items : [];
                document.getElementById('detItemsBody').innerHTML = items.length ? items.map(it => `
                    <tr>
                        <td>${it.nome ?? ''}</td>
                        <td>${it.qtd ?? ''} ${it.unidade ?? ''}</td>
                        <td class="text-end">${brlJs(it.preco_unit)}</td>
                        <td class="text-end">${brlJs(it.subtotal)}</td>
                    </tr>
                `).join('') : '<tr><td colspan="4" class="text-center">Sem itens.</td></tr>';

                const pays = Array.isArray(r.payments) ? r.payments : [];
                document.getElementById('detPaysBody').innerHTML = pays.length ? pays.map(p => `
                    <tr>
                        <td>${toDT(p.created_at)}</td>
                        <td>${p.metodo ?? ''}</td>
                        <td class="text-end">${brlJs(p.valor)}</td>
                    </tr>
                `).join('') : '<tr><td colspan="3" class="text-center">Nenhum pagamento registrado.</td></tr>';

                modalDetalhes.show();
            } catch (e) {
                alert(e.message || e);
            }
        }

        function openPay(id, cliente, restante) {
            document.getElementById('payFiadoId').value = id;
            document.getElementById('payCliente').innerText = cliente;
            document.getElementById('paySaldo').innerText = brlJs(restante);
            document.getElementById('payValor').value = '';
            modalPagamento.show();
            setTimeout(() => document.getElementById('payValor').focus(), 300);
        }

        document.getElementById('payForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const id = document.getElementById('payFiadoId').value;
            const valorRaw = document.getElementById('payValor').value.replace(/\./g, '').replace(',', '.').replace(/[^\d.]/g, '');
            const valor = parseFloat(valorRaw);
            const metodo = document.getElementById('payMetodo').value;
            if (isNaN(valor) || valor <= 0) return alert('Informe um valor válido.');

            try {
                const r = await fetch(`${API}?action=pay`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        id,
                        valor,
                        metodo
                    })
                }).then(res => res.json());

                if (!r.ok) throw new Error(r.msg || 'Falha ao pagar.');

                alert(r.msg || 'Pagamento registrado!');
                modalPagamento.hide();

                // mantém página atual
                loadFiados(false);
            } catch (e) {
                alert(e.message || e);
            }
        });

        document.getElementById('filterForm').addEventListener('submit', (e) => {
            e.preventDefault();
            loadFiados(true); // ✅ reseta pra página 1
        });

        // ✅ Botões de paginação
        $pgPrev.addEventListener('click', (e) => {
            e.preventDefault();
            if (STATE.page <= 1) return;
            STATE.page--;
            if (STATE.serverPaging) loadFiados(false);
            else {
                renderRows(sliceClientPage());
                setPagerUI();
            }
        });

        $pgNext.addEventListener('click', (e) => {
            e.preventDefault();
            if (STATE.page >= STATE.totalPages) return;
            STATE.page++;
            if (STATE.serverPaging) loadFiados(false);
            else {
                renderRows(sliceClientPage());
                setPagerUI();
            }
        });

        document.getElementById('payValor').addEventListener('input', function() {
            let v = this.value.replace(/\D/g, '');
            v = (v / 100).toFixed(2).replace('.', ',');
            v = v.replace(/(\d)(?=(\d{3})+(?!\d))/g, '$1.');
            this.value = v;
        });

        window.onload = () => loadFiados(true);
    </script>
</body>

</html>