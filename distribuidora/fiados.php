<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />

    <link rel="shortcut icon" href="assets/images/favicon.svg" type="image/x-icon" />
    <title>Painel da Distribuidora | Fiados</title>

    <link rel="stylesheet" href="assets/css/bootstrap.min.css" />
    <link rel="stylesheet" href="assets/css/lineicons.css" rel="stylesheet" type="text/css" />
    <link rel="stylesheet" href="assets/css/materialdesignicons.min.css" rel="stylesheet" type="text/css" />
    <link rel="stylesheet" href="assets/css/main.css" />

    <!-- Estilos SOMENTE para filtros e tabela (escopado nesta página) -->
    <style>
        .fiados-scope .card-style {
            border: 1px solid rgba(0, 0, 0, .06);
        }

        .fiados-scope .fiados-filters .form-label {
            font-size: .85rem;
            color: rgba(0, 0, 0, .70);
            margin-bottom: .35rem;
        }

        .fiados-scope .fiados-filters .form-control,
        .fiados-scope .fiados-filters .form-select {
            height: 42px;
            border-radius: 10px;
        }

        .fiados-scope .fiados-filters .btn {
            height: 42px;
            border-radius: 10px;
            white-space: nowrap;
        }

        .fiados-scope .fiados-table thead th {
            font-size: .85rem;
            color: rgba(0, 0, 0, .70);
            border-bottom-width: 1px;
            background: rgba(0, 0, 0, .02);
        }

        .fiados-scope .fiados-table tbody td {
            vertical-align: middle;
        }

        .fiados-scope .fiados-table tbody tr:hover {
            background: rgba(0, 0, 0, .015);
        }

        .fiados-scope .fiados-table .pill {
            display: inline-flex;
            align-items: center;
            gap: .35rem;
            padding: .25rem .55rem;
            border-radius: 999px;
            font-size: .78rem;
            border: 1px solid rgba(0, 0, 0, .08);
            background: #fff;
        }

        .fiados-scope .fiados-table .pill-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            display: inline-block;
        }

        .fiados-scope .fiados-table .money {
            font-variant-numeric: tabular-nums;
        }

        .fiados-scope .fiados-meta {
            font-size: .9rem;
            color: rgba(0, 0, 0, .65);
        }

        .fiados-scope .fiados-toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 14px;
        }

        .fiados-scope .fiados-toolbar h6 {
            margin: 0;
        }

        .fiados-scope .fiados-empty {
            padding: 22px 10px;
            text-align: center;
            color: rgba(0, 0, 0, .55);
        }
    </style>
</head>

<body>
    <div id="preloader">
        <div class="spinner"></div>
    </div>

    <!-- ======== sidebar-nav start =========== -->
    <aside class="sidebar-nav-wrapper">
        <div class="navbar-logo">
            <a href="dashboard.html" class="d-flex align-items-center gap-2">
                <img src="assets/images/logo/logo.svg" alt="logo" />
            </a>
        </div>

        <nav class="sidebar-nav">
            <ul>
                <li class="nav-item">
                    <a href="dashboard.html">
                        <span class="icon">
                            <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path
                                    d="M8.74999 18.3333C12.2376 18.3333 15.1364 15.8128 15.7244 12.4941C15.8448 11.8143 15.2737 11.25 14.5833 11.25H9.99999C9.30966 11.25 8.74999 10.6903 8.74999 10V5.41666C8.74999 4.7263 8.18563 4.15512 7.50586 4.27556C4.18711 4.86357 1.66666 7.76243 1.66666 11.25C1.66666 15.162 4.83797 18.3333 8.74999 18.3333Z" />
                                <path
                                    d="M17.0833 10C17.7737 10 18.3432 9.43708 18.2408 8.75433C17.7005 5.14918 14.8508 2.29947 11.2457 1.75912C10.5629 1.6568 10 2.2263 10 2.91665V9.16666C10 9.62691 10.3731 10 10.8333 10H17.0833Z" />
                            </svg>
                        </span>
                        <span class="text">Dashboard</span>
                    </a>
                </li>

                <li class="nav-item">
                    <a href="vendas.html">
                        <span class="icon">
                            <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path
                                    d="M1.66666 5C1.66666 3.89543 2.5621 3 3.66666 3H16.3333C17.4379 3 18.3333 3.89543 18.3333 5V15C18.3333 16.1046 17.4379 17 16.3333 17H3.66666C2.5621 17 1.66666 16.1046 1.66666 15V5Z"
                                    stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                                <path d="M1.66666 5L10 10.8333L18.3333 5" stroke="currentColor" stroke-width="1.5"
                                    stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </span>
                        <span class="text">Vendas</span>
                    </a>
                </li>

                <li class="nav-item nav-item-has-children active">
                    <a href="#0" data-bs-toggle="collapse" data-bs-target="#ddmenu_operacoes" aria-controls="ddmenu_operacoes"
                        aria-expanded="true">
                        <span class="icon">
                            <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path
                                    d="M3.33334 3.35442C3.33334 2.4223 4.07954 1.66666 5.00001 1.66666H15C15.9205 1.66666 16.6667 2.4223 16.6667 3.35442V16.8565C16.6667 17.5519 15.8827 17.9489 15.3333 17.5317L13.8333 16.3924C13.537 16.1673 13.1297 16.1673 12.8333 16.3924L10.5 18.1646C10.2037 18.3896 9.79634 18.3896 9.50001 18.1646L7.16668 16.3924C6.87038 16.1673 6.46298 16.1673 6.16668 16.3924L4.66668 17.5317C4.11731 17.9489 3.33334 17.5519 3.33334 16.8565V3.35442Z" />
                            </svg>
                        </span>
                        <span class="text">Operações</span>
                    </a>
                    <ul id="ddmenu_operacoes" class="collapse show dropdown-nav">
                        <li><a href="vendidos.html">Vendidos</a></li>
                        <li><a href="fiados.html" class="active">Fiados</a></li>
                        <li><a href="devolucoes.html">Devoluções</a></li>
                    </ul>
                </li>

                <li class="nav-item nav-item-has-children">
                    <a href="#0" class="collapsed" data-bs-toggle="collapse" data-bs-target="#ddmenu_estoque"
                        aria-controls="ddmenu_estoque" aria-expanded="false">
                        <span class="icon">
                            <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path
                                    d="M2.49999 5.83331C2.03976 5.83331 1.66666 6.2064 1.66666 6.66665V10.8333C1.66666 13.5948 3.90523 15.8333 6.66666 15.8333H9.99999C12.1856 15.8333 14.0436 14.431 14.7235 12.4772C14.8134 12.4922 14.9058 12.5 15 12.5H16.6667C17.5872 12.5 18.3333 11.7538 18.3333 10.8333V8.33331C18.3333 7.41284 17.5872 6.66665 16.6667 6.66665H15C15 6.2064 14.6269 5.83331 14.1667 5.83331H2.49999Z" />
                                <path
                                    d="M2.49999 16.6667C2.03976 16.6667 1.66666 17.0398 1.66666 17.5C1.66666 17.9602 2.03976 18.3334 2.49999 18.3334H14.1667C14.6269 18.3334 15 17.9602 15 17.5C15 17.0398 14.6269 16.6667 14.1667 16.6667H2.49999Z" />
                            </svg>
                        </span>
                        <span class="text">Estoque</span>
                    </a>
                    <ul id="ddmenu_estoque" class="collapse dropdown-nav">
                        <li><a href="produtos.html">Produtos</a></li>
                        <li><a href="inventario.html">Inventário</a></li>
                        <li><a href="entradas.html">Entradas</a></li>
                        <li><a href="saidas.html">Saídas</a></li>
                        <li><a href="estoque-minimo.html">Estoque Mínimo</a></li>
                    </ul>
                </li>

                <li class="nav-item nav-item-has-children">
                    <a href="#0" class="collapsed" data-bs-toggle="collapse" data-bs-target="#ddmenu_cadastros"
                        aria-controls="ddmenu_cadastros" aria-expanded="false">
                        <span class="icon">
                            <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path
                                    d="M1.66666 5.41669C1.66666 3.34562 3.34559 1.66669 5.41666 1.66669C7.48772 1.66669 9.16666 3.34562 9.16666 5.41669C9.16666 7.48775 7.48772 9.16669 5.41666 9.16669C3.34559 9.16669 1.66666 7.48775 1.66666 5.41669Z" />
                                <path
                                    d="M1.66666 14.5834C1.66666 12.5123 3.34559 10.8334 5.41666 10.8334C7.48772 10.8334 9.16666 12.5123 9.16666 14.5834C9.16666 16.6545 7.48772 18.3334 5.41666 18.3334C3.34559 18.3334 1.66666 16.6545 1.66666 14.5834Z" />
                                <path
                                    d="M10.8333 5.41669C10.8333 3.34562 12.5123 1.66669 14.5833 1.66669C16.6544 1.66669 18.3333 3.34562 18.3333 5.41669C18.3333 7.48775 16.6544 9.16669 14.5833 9.16669C12.5123 9.16669 10.8333 7.48775 10.8333 5.41669Z" />
                                <path
                                    d="M10.8333 14.5834C10.8333 12.5123 12.5123 10.8334 14.5833 10.8334C16.6544 10.8334 18.3333 12.5123 18.3333 14.5834C18.3333 16.6545 16.6544 18.3334 14.5833 18.3334C12.5123 18.3334 10.8333 16.6545 10.8333 14.5834Z" />
                            </svg>
                        </span>
                        <span class="text">Cadastros</span>
                    </a>
                    <ul id="ddmenu_cadastros" class="collapse dropdown-nav">
                        <li><a href="clientes.html">Clientes</a></li>
                        <li><a href="fornecedores.html">Fornecedores</a></li>
                        <li><a href="categorias.html">Categorias</a></li>
                    </ul>
                </li>

                <li class="nav-item">
                    <a href="relatorios.html">
                        <span class="icon">
                            <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path
                                    d="M4.16666 3.33335C4.16666 2.41288 4.91285 1.66669 5.83332 1.66669H14.1667C15.0872 1.66669 15.8333 2.41288 15.8333 3.33335V16.6667C15.8333 17.5872 15.0872 18.3334 14.1667 18.3334H5.83332C4.91285 18.3334 4.16666 17.5872 4.16666 16.6667V3.33335Z" />
                            </svg>
                        </span>
                        <span class="text">Relatórios</span>
                    </a>
                </li>

                <span class="divider">
                    <hr />
                </span>

                <li class="nav-item nav-item-has-children">
                    <a href="#0" class="collapsed" data-bs-toggle="collapse" data-bs-target="#ddmenu_config"
                        aria-controls="ddmenu_config" aria-expanded="false">
                        <span class="icon">
                            <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path
                                    d="M10 1.66669C5.39763 1.66669 1.66666 5.39766 1.66666 10C1.66666 14.6024 5.39763 18.3334 10 18.3334C14.6024 18.3334 18.3333 14.6024 18.3333 10C18.3333 5.39766 14.6024 1.66669 10 1.66669Z" />
                            </svg>
                        </span>
                        <span class="text">Configurações</span>
                    </a>
                    <ul id="ddmenu_config" class="collapse dropdown-nav">
                        <li><a href="usuarios.html">Usuários e Permissões</a></li>
                        <li><a href="parametros.html">Parâmetros do Sistema</a></li>
                    </ul>
                </li>

                <li class="nav-item">
                    <a href="suporte.html">
                        <span class="icon">
                            <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path
                                    d="M10.8333 2.50008C10.8333 2.03984 10.4602 1.66675 9.99999 1.66675C9.53975 1.66675 9.16666 2.03984 9.16666 2.50008C9.16666 2.96032 9.53975 3.33341 9.99999 3.33341C10.4602 3.33341 10.8333 2.96032 10.8333 2.50008Z" />
                                <path
                                    d="M11.4272 2.69637C10.9734 2.56848 10.4947 2.50006 10 2.50006C7.10054 2.50006 4.75003 4.85057 4.75003 7.75006V9.20873C4.75003 9.72814 4.62082 10.2393 4.37404 10.6963L3.36705 12.5611C2.89938 13.4272 3.26806 14.5081 4.16749 14.9078C7.88074 16.5581 12.1193 16.5581 15.8326 14.9078C16.732 14.5081 17.1007 13.4272 16.633 12.5611L15.626 10.6963C15.43 10.3333 15.3081 9.93606 15.2663 9.52773C15.0441 9.56431 14.8159 9.58339 14.5833 9.58339C12.2822 9.58339 10.4167 7.71791 10.4167 5.41673C10.4167 4.37705 10.7975 3.42631 11.4272 2.69637Z" />
                            </svg>
                        </span>
                        <span class="text">Suporte</span>
                    </a>
                </li>
            </ul>
        </nav>
    </aside>
    <!-- ======== sidebar-nav end =========== -->

    <div class="overlay"></div>

    <main class="main-wrapper">
        <!-- Header (deixe como no seu template; aqui está um stub simples) -->
        <header class="header">
            <div class="container-fluid">
                <div class="header-left d-flex align-items-center gap-2">
                    <button id="menu-toggle" class="main-btn light-btn btn-hover">
                        <i class="lni lni-menu"></i>
                    </button>
                    <h6 class="mb-0">Fiados</h6>
                </div>
            </div>
        </header>

        <!-- Conteúdo -->
        <section class="section">
            <div class="container-fluid fiados-scope">
                <!-- Filtros -->
                <div class="card-style mb-4 fiados-filters">
                    <div class="row g-2 align-items-end">
                        <div class="col-12 col-md-4">
                            <label class="form-label">Buscar (Nome / CPF / Telefone / Nº Venda)</label>
                            <input id="q" class="form-control" placeholder="Ex.: Maria / 123.456.789-00 / (92) 9.... / 1042" />
                        </div>

                        <div class="col-6 col-md-2">
                            <label class="form-label">Data inicial</label>
                            <input id="dt_ini" type="date" class="form-control" />
                        </div>

                        <div class="col-6 col-md-2">
                            <label class="form-label">Data final</label>
                            <input id="dt_fim" type="date" class="form-control" />
                        </div>

                        <div class="col-12 col-md-2">
                            <label class="form-label">Status</label>
                            <select id="status" class="form-select">
                                <option value="">Todos</option>
                                <option value="EM ABERTO">Em aberto</option>
                                <option value="ATRASADO">Atrasado</option>
                                <option value="PAGO">Pago</option>
                            </select>
                        </div>

                        <div class="col-12 col-md-2 d-flex gap-2">
                            <button id="btnFiltrar" class="btn btn-primary w-100">
                                <i class="lni lni-search-alt"></i> Filtrar
                            </button>
                            <button id="btnLimpar" class="btn btn-outline-secondary w-100">
                                <i class="lni lni-eraser"></i> Limpar
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Tabela -->
                <div class="card-style">
                    <div class="fiados-toolbar">
                        <div>
                            <h6>Vendas Fiadas (Boletos)</h6>
                            <div class="fiados-meta">Dados fictícios para layout: Cliente (nome, CPF, telefone), datas e totais.</div>
                        </div>
                        <div class="fiados-meta" id="metaInfo">Mostrando 0 registros</div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-striped fiados-table align-middle mb-0">
                            <thead>
                                <tr>
                                    <th style="width: 90px;">Nº Venda</th>
                                    <th>Cliente</th>
                                    <th style="width: 180px;">CPF</th>
                                    <th style="width: 170px;">Telefone</th>
                                    <th style="width: 140px;">Data</th>
                                    <th style="width: 140px;">Vencimento</th>
                                    <th style="width: 150px;" class="text-end">Total</th>
                                    <th style="width: 130px;">Status</th>
                                    <th style="width: 110px;" class="text-end">Ações</th>
                                </tr>
                            </thead>
                            <tbody id="tbody"></tbody>
                        </table>
                    </div>

                    <div id="emptyState" class="fiados-empty d-none">
                        Nenhum fiado encontrado com os filtros informados.
                    </div>
                </div>
            </div>
        </section>

        <!-- Modal Detalhes (Bootstrap) -->
        <div class="modal fade" id="modalDetalhes" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Detalhes do Fiado</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-2">
                            <div class="col-12 col-md-6">
                                <div class="p-3 border rounded">
                                    <h6 class="mb-2">Cliente</h6>
                                    <div><strong>Nome:</strong> <span id="d_nome">—</span></div>
                                    <div><strong>CPF:</strong> <span id="d_cpf">—</span></div>
                                    <div><strong>Telefone:</strong> <span id="d_tel">—</span></div>
                                </div>
                            </div>
                            <div class="col-12 col-md-6">
                                <div class="p-3 border rounded">
                                    <h6 class="mb-2">Venda</h6>
                                    <div><strong>Nº:</strong> <span id="d_id">—</span></div>
                                    <div><strong>Data:</strong> <span id="d_data">—</span></div>
                                    <div><strong>Vencimento:</strong> <span id="d_venc">—</span></div>
                                    <div><strong>Status:</strong> <span id="d_status">—</span></div>
                                    <div><strong>Total:</strong> <span id="d_total">—</span></div>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="p-3 border rounded">
                                    <h6 class="mb-2">Itens (fictício)</h6>
                                    <div class="table-responsive">
                                        <table class="table table-sm table-bordered align-middle mb-0">
                                            <thead>
                                                <tr>
                                                    <th>Produto</th>
                                                    <th style="width: 90px;" class="text-end">Qtd</th>
                                                    <th style="width: 140px;" class="text-end">Preço</th>
                                                    <th style="width: 140px;" class="text-end">Subtotal</th>
                                                </tr>
                                            </thead>
                                            <tbody id="d_itens"></tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Fechar</button>
                        <button type="button" class="btn btn-primary" onclick="window.print()">
                            <i class="lni lni-printer"></i> Imprimir
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer (deixe como no seu template; aqui está um stub simples) -->
        <footer class="footer">
            <div class="container-fluid">
                <div class="footer-content">
                    <p class="text-sm text-muted mb-0">© 2026 Painel da Distribuidora</p>
                </div>
            </div>
        </footer>
    </main>

    <script src="assets/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js"></script>

    <!-- JS somente para dados fictícios + filtros + modal -->
    <script>
        // Dados fictícios
        const fiados = [{
                id: 1042,
                cliente: "Maria do Carmo Souza",
                cpf: "123.456.789-00",
                tel: "(92) 99111-2233",
                data: "2026-03-01",
                venc: "2026-03-10",
                total: 386.50,
                status: "EM ABERTO",
                itens: [{
                        nome: "Coca-Cola 2L",
                        qtd: 6,
                        preco: 14.00
                    },
                    {
                        nome: "Água Mineral 1,5L",
                        qtd: 10,
                        preco: 7.00
                    }
                ]
            },
            {
                id: 1046,
                cliente: "João Pedro Lima",
                cpf: "987.654.321-00",
                tel: "(92) 99222-3344",
                data: "2026-03-02",
                venc: "2026-03-08",
                total: 129.90,
                status: "PAGO",
                itens: [{
                        nome: "Arroz 5kg",
                        qtd: 2,
                        preco: 34.95
                    },
                    {
                        nome: "Feijão 1kg",
                        qtd: 3,
                        preco: 9.00
                    }
                ]
            },
            {
                id: 1050,
                cliente: "Ana Paula Nascimento",
                cpf: "321.654.987-00",
                tel: "(92) 99333-4455",
                data: "2026-02-26",
                venc: "2026-03-03",
                total: 74.00,
                status: "ATRASADO",
                itens: [{
                        nome: "Óleo 900ml",
                        qtd: 4,
                        preco: 9.50
                    },
                    {
                        nome: "Açúcar 1kg",
                        qtd: 2,
                        preco: 6.00
                    }
                ]
            },
            {
                id: 1053,
                cliente: "Carlos Henrique Barros",
                cpf: "555.444.333-22",
                tel: "(92) 99444-5566",
                data: "2026-03-03",
                venc: "2026-03-15",
                total: 512.00,
                status: "EM ABERTO",
                itens: [{
                        nome: "Caixa de Refrigerante Lata",
                        qtd: 2,
                        preco: 118.00
                    },
                    {
                        nome: "Biscoito 400g",
                        qtd: 8,
                        preco: 11.00
                    }
                ]
            },
            {
                id: 1055,
                cliente: "Rita de Cássia Oliveira",
                cpf: "111.222.333-44",
                tel: "(92) 99555-6677",
                data: "2026-02-25",
                venc: "2026-03-01",
                total: 203.70,
                status: "ATRASADO",
                itens: [{
                        nome: "Leite 1L",
                        qtd: 12,
                        preco: 6.20
                    },
                    {
                        nome: "Café 250g",
                        qtd: 3,
                        preco: 9.90
                    }
                ]
            }
        ];

        const tbody = document.getElementById("tbody");
        const emptyState = document.getElementById("emptyState");
        const metaInfo = document.getElementById("metaInfo");

        const q = document.getElementById("q");
        const dtIni = document.getElementById("dt_ini");
        const dtFim = document.getElementById("dt_fim");
        const statusEl = document.getElementById("status");

        function moneyBR(v) {
            return Number(v || 0).toLocaleString("pt-BR", {
                style: "currency",
                currency: "BRL"
            });
        }

        function statusPill(status) {
            const up = String(status || "").toUpperCase();
            let dot = "#adb5bd"; // cinza
            if (up === "PAGO") dot = "#198754"; // verde
            if (up === "EM ABERTO") dot = "#0d6efd"; // azul
            if (up === "ATRASADO") dot = "#ffc107"; // amarelo
            return `
        <span class="pill">
          <span class="pill-dot" style="background:${dot}"></span>
          ${status}
        </span>
      `;
        }

        function matchesFilters(row) {
            const text = (q.value || "").trim().toLowerCase();
            const s = (statusEl.value || "").trim().toUpperCase();
            const ini = dtIni.value ? new Date(dtIni.value + "T00:00:00") : null;
            const fim = dtFim.value ? new Date(dtFim.value + "T23:59:59") : null;

            const rowDate = new Date(row.data + "T12:00:00");

            if (ini && rowDate < ini) return false;
            if (fim && rowDate > fim) return false;
            if (s && String(row.status).toUpperCase() !== s) return false;

            if (!text) return true;

            const hay = [
                row.id,
                row.cliente,
                row.cpf,
                row.tel,
                row.data,
                row.venc,
                row.status
            ].join(" ").toLowerCase();

            return hay.includes(text);
        }

        function render() {
            const rows = fiados.filter(matchesFilters);

            metaInfo.textContent = `Mostrando ${rows.length} registro(s)`;

            if (!rows.length) {
                tbody.innerHTML = "";
                emptyState.classList.remove("d-none");
                return;
            }

            emptyState.classList.add("d-none");

            tbody.innerHTML = rows.map(r => `
        <tr>
          <td><strong>#${r.id}</strong></td>
          <td>${escapeHtml(r.cliente)}</td>
          <td>${escapeHtml(r.cpf)}</td>
          <td>${escapeHtml(r.tel)}</td>
          <td>${formatDate(r.data)}</td>
          <td>${formatDate(r.venc)}</td>
          <td class="text-end money">${moneyBR(r.total)}</td>
          <td>${statusPill(r.status)}</td>
          <td class="text-end">
            <button class="btn btn-sm btn-primary" data-id="${r.id}">
              Detalhes
            </button>
          </td>
        </tr>
      `).join("");

            tbody.querySelectorAll("button[data-id]").forEach(btn => {
                btn.addEventListener("click", () => openDetalhes(Number(btn.dataset.id)));
            });
        }

        function openDetalhes(id) {
            const r = fiados.find(x => x.id === id);
            if (!r) return;

            document.getElementById("d_nome").textContent = r.cliente;
            document.getElementById("d_cpf").textContent = r.cpf;
            document.getElementById("d_tel").textContent = r.tel;

            document.getElementById("d_id").textContent = "#" + r.id;
            document.getElementById("d_data").textContent = formatDate(r.data);
            document.getElementById("d_venc").textContent = formatDate(r.venc);
            document.getElementById("d_status").innerHTML = statusPill(r.status);
            document.getElementById("d_total").textContent = moneyBR(r.total);

            const itensBody = document.getElementById("d_itens");
            itensBody.innerHTML = (r.itens || []).map(it => {
                const sub = Number(it.qtd || 0) * Number(it.preco || 0);
                return `
          <tr>
            <td>${escapeHtml(it.nome)}</td>
            <td class="text-end">${Number(it.qtd || 0)}</td>
            <td class="text-end">${moneyBR(it.preco)}</td>
            <td class="text-end">${moneyBR(sub)}</td>
          </tr>
        `;
            }).join("");

            const modal = new bootstrap.Modal(document.getElementById("modalDetalhes"));
            modal.show();
        }

        function formatDate(ymd) {
            // ymd: YYYY-MM-DD
            const d = new Date(ymd + "T12:00:00");
            return d.toLocaleDateString("pt-BR");
        }

        function escapeHtml(str) {
            return String(str ?? "").replace(/[&<>"']/g, s => ({
                "&": "&amp;",
                "<": "&lt;",
                ">": "&gt;",
                '"': "&quot;",
                "'": "&#039;"
            } [s]));
        }

        document.getElementById("btnFiltrar").addEventListener("click", render);
        document.getElementById("btnLimpar").addEventListener("click", () => {
            q.value = "";
            dtIni.value = "";
            dtFim.value = "";
            statusEl.value = "";
            render();
        });

        q.addEventListener("keydown", (e) => {
            if (e.key === "Enter") {
                e.preventDefault();
                render();
            }
        });

        // Render inicial
        render();
    </script>
</body>

</html>