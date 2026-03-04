<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />

    <link rel="shortcut icon" href="assets/images/favicon.svg" type="image/x-icon" />
    <title>Painel da Distribuidora | Clientes</title>

    <!-- ========== CSS (mantém o template) ========= -->
    <link rel="stylesheet" href="assets/css/bootstrap.min.css" />
    <link rel="stylesheet" href="assets/css/lineicons.css" rel="stylesheet" type="text/css" />
    <link rel="stylesheet" href="assets/css/materialdesignicons.min.css" rel="stylesheet" type="text/css" />
    <link rel="stylesheet" href="assets/css/main.css" />

    <style>
        /* =========================
       Só tabela + filtros (como você pediu)
    ========================== */
        .main-btn.btn-compact {
            height: 36px !important;
            padding: 8px 12px !important;
            font-size: 13px !important;
            line-height: 1 !important;
        }

        .form-control.compact,
        .form-select.compact {
            height: 38px;
            padding: 8px 12px;
            font-size: 13px;
        }

        .cardx {
            border: 1px solid rgba(148, 163, 184, .24);
            border-radius: 16px;
            background: #fff;
            overflow: hidden;
        }

        .cardx .head {
            padding: 12px 14px;
            border-bottom: 1px solid rgba(148, 163, 184, .18);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            flex-wrap: wrap;
        }

        .cardx .body {
            padding: 14px;
        }

        .muted {
            font-size: 12px;
            color: #64748b;
        }

        .pill {
            padding: 6px 10px;
            border-radius: 999px;
            border: 1px solid rgba(148, 163, 184, .22);
            font-weight: 900;
            font-size: 12px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(248, 250, 252, .7);
        }

        .pill.ok {
            border-color: rgba(34, 197, 94, .25);
            background: rgba(240, 253, 244, .9);
            color: #166534;
        }

        .pill.warn {
            border-color: rgba(245, 158, 11, .25);
            background: rgba(255, 251, 235, .95);
            color: #92400e;
        }

        .toolbar {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: center;
        }

        /* ===== equal height sem cortar paginação ===== */
        .equal-h>.col-lg-8,
        .equal-h>.col-lg-4 {
            display: flex;
        }

        .cardx.card-table,
        .cardx.card-tot {
            flex: 1 1 auto;
            display: flex;
            flex-direction: column;
        }

        .cardx.card-table .body,
        .cardx.card-tot .body {
            flex: 1;
            display: flex;
            flex-direction: column;
            min-height: 0;
        }

        .table-wrap {
            flex: 1 1 auto;
            min-height: 0;
            overflow: auto;
            border-radius: 14px;
        }

        #tbFiados {
            width: 100%;
            min-width: 1260px;
            table-layout: fixed;
        }

        #tbFiados thead th {
            position: sticky;
            top: 0;
            z-index: 2;
            background: #f8fafc;
            border-bottom: 1px solid rgba(148, 163, 184, .25);
            font-size: 12px;
            color: #0f172a;
            padding: 10px 10px;
            white-space: nowrap;
        }

        #tbFiados tbody td {
            border-top: 1px solid rgba(148, 163, 184, .18);
            padding: 10px 10px;
            font-size: 13px;
            vertical-align: top;
            color: #0f172a;
            background: #fff;
        }

        .page-nav {
            flex: 0 0 auto;
            display: flex;
            gap: 8px;
            align-items: center;
            justify-content: flex-end;
            flex-wrap: wrap;
            margin-top: 10px;
            padding-top: 6px;
        }

        .page-btn {
            border: 1px solid rgba(148, 163, 184, .35);
            background: #fff;
            border-radius: 10px;
            padding: 8px 10px;
            font-weight: 900;
            font-size: 12px;
            cursor: pointer;
        }

        .page-btn[disabled] {
            opacity: .55;
            cursor: not-allowed;
        }

        .page-info {
            font-size: 12px;
            color: #64748b;
            font-weight: 900;
        }

        .box-tot {
            border: 1px solid rgba(148, 163, 184, .22);
            border-radius: 14px;
            background: #fff;
            padding: 12px;
            flex: 1;
            min-height: 0;
            display: flex;
            flex-direction: column;
        }

        .tot-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
            font-size: 13px;
            color: #334155;
            margin-bottom: 8px;
            font-weight: 900;
        }

        .tot-hr {
            height: 1px;
            background: rgba(148, 163, 184, .22);
            margin: 10px 0;
        }

        .grand {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            gap: 10px;
            margin-top: auto;
            padding-top: 8px;
        }

        .grand .lbl {
            font-weight: 1000;
            color: #0f172a;
            font-size: 16px;
        }

        .grand .val {
            font-weight: 1000;
            color: #0b5ed7;
            font-size: 26px;
            letter-spacing: .2px;
            white-space: nowrap;
        }

        /* col widths */
        .col-id {
            width: 70px;
        }

        .col-data {
            width: 120px;
        }

        .col-nome {
            width: 220px;
        }

        .col-cpf {
            width: 140px;
        }

        .col-tel {
            width: 150px;
        }

        .col-itens {
            width: 280px;
        }

        .col-num {
            width: 120px;
        }

        .col-status {
            width: 120px;
        }

        .col-acoes {
            width: 190px;
        }

        .mini {
            font-size: 12px;
            color: #475569;
            font-weight: 800;
        }

        .td-money {
            text-align: right;
            font-weight: 900;
            white-space: nowrap;
        }

        .td-nowrap {
            white-space: nowrap;
        }

        .td-clip {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            display: block;
            max-width: 100%;
        }

        .badge-soft {
            font-weight: 1000;
            border-radius: 999px;
            padding: 6px 10px;
            font-size: 11px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            white-space: nowrap;
            border: 1px solid transparent;
        }

        .b-open {
            background: rgba(255, 251, 235, .95);
            color: #92400e;
            border-color: rgba(245, 158, 11, .25);
        }

        .b-paid {
            background: rgba(240, 253, 244, .95);
            color: #166534;
            border-color: rgba(34, 197, 94, .25);
        }

        .items-preview {
            border: 1px solid rgba(148, 163, 184, .22);
            border-radius: 12px;
            padding: 8px 10px;
            background: rgba(248, 250, 252, .7);
        }

        .item-line {
            margin-bottom: 8px;
        }

        .item-line:last-child {
            margin-bottom: 0;
        }

        .item-name {
            font-weight: 900;
            font-size: 12px;
            line-height: 1.2;
        }

        .item-meta {
            font-size: 12px;
            color: #64748b;
            display: flex;
            justify-content: space-between;
            gap: 10px;
            white-space: nowrap;
        }

        .item-more {
            font-size: 12px;
            color: #64748b;
            margin-top: 6px;
            font-weight: 900;
        }

        .actions-wrap {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            justify-content: flex-start;
        }

        .btn-action {
            height: 34px !important;
            padding: 8px 10px !important;
            font-size: 12px !important;
            border-radius: 10px !important;
            white-space: nowrap;
        }

        /* suggest */
        .search-wrap {
            position: relative;
        }

        .suggest {
            position: absolute;
            top: calc(100% + 6px);
            left: 0;
            right: 0;
            background: #fff;
            border: 1px solid rgba(148, 163, 184, .25);
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 18px 40px rgba(2, 6, 23, .10);
            display: none;
            z-index: 15;
            max-height: 240px;
            overflow: auto;
        }

        .suggest .it {
            padding: 10px 12px;
            cursor: pointer;
            border-bottom: 1px solid rgba(148, 163, 184, .14);
            font-size: 13px;
            display: flex;
            justify-content: space-between;
            gap: 10px;
            align-items: center;
        }

        .suggest .it:hover {
            background: rgba(241, 245, 249, .8);
        }

        .suggest .it:last-child {
            border-bottom: none;
        }

        .suggest .nm {
            font-weight: 900;
            color: #0f172a;
            min-width: 0;
        }

        .suggest .meta {
            font-size: 12px;
            color: #64748b;
            white-space: nowrap;
        }

        /* modal */
        .sale-box {
            border: 1px solid rgba(148, 163, 184, .22);
            border-radius: 14px;
            background: rgba(248, 250, 252, .7);
            padding: 10px 12px;
            max-height: 320px;
            overflow: auto;
            -webkit-overflow-scrolling: touch;
        }

        .sale-row {
            display: flex;
            justify-content: space-between;
            gap: 10px;
            padding: 8px 0;
            border-bottom: 1px dashed rgba(148, 163, 184, .35);
            font-size: 12px;
        }

        .sale-row:last-child {
            border-bottom: none;
        }

        .sale-row .left {
            min-width: 0;
        }

        .sale-row .left .nm {
            font-weight: 900;
            color: #0f172a;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 420px;
        }

        .sale-row .left .cd {
            color: #64748b;
            font-size: 12px;
        }

        .sale-row .right {
            white-space: nowrap;
            text-align: right;
            font-weight: 900;
        }

        .hist-box {
            border: 1px solid rgba(148, 163, 184, .22);
            border-radius: 14px;
            background: #fff;
            padding: 10px 12px;
            max-height: 220px;
            overflow: auto;
        }

        .hist-row {
            display: flex;
            justify-content: space-between;
            gap: 10px;
            padding: 8px 0;
            border-bottom: 1px dashed rgba(148, 163, 184, .35);
            font-size: 12px;
            color: #0f172a;
        }

        .hist-row:last-child {
            border-bottom: none;
        }

        .hist-row .mut {
            color: #64748b;
            font-weight: 800;
        }

        @media (max-width: 991.98px) {
            #tbFiados {
                min-width: 1120px;
            }

            .grand .val {
                font-size: 22px;
            }
        }
    </style>
</head>

<body>
    <div id="preloader">
        <div class="spinner"></div>
    </div>

    <!-- ======== sidebar-nav start (mantido) =========== -->
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

                <li class="nav-item nav-item-has-children">
                    <a href="#0" class="collapsed" data-bs-toggle="collapse" data-bs-target="#ddmenu_operacoes" aria-controls="ddmenu_operacoes" aria-expanded="false">
                        <span class="icon">
                            <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M3.33334 3.35442C3.33334 2.4223 4.07954 1.66666 5.00001 1.66666H15C15.9205 1.66666 16.6667 2.4223 16.6667 3.35442V16.8565C16.6667 17.5519 15.8827 17.9489 15.3333 17.5317L13.8333 16.3924C13.537 16.1673 13.1297 16.1673 12.8333 16.3924L10.5 18.1646C10.2037 18.3896 9.79634 18.3896 9.50001 18.1646L7.16668 16.3924C6.87038 16.1673 6.46298 16.1673 6.16668 16.3924L4.66668 17.5317C4.11731 17.9489 3.33334 17.5519 3.33334 16.8565V3.35442Z" />
                            </svg>
                        </span>
                        <span class="text">Operações</span>
                    </a>
                    <ul id="ddmenu_operacoes" class="collapse dropdown-nav">
                        <li><a href="vendidos.php">vendidos</a></li>
                        <li><a href="fiados.php">Fiados</a></li>
                        <li><a href="devolucoes.php">Devoluções</a></li>
                    </ul>
                </li>

                <li class="nav-item nav-item-has-children">
                    <a href="#0" class="collapsed" data-bs-toggle="collapse" data-bs-target="#ddmenu_estoque" aria-controls="ddmenu_estoque" aria-expanded="false">
                        <span class="icon">
                            <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M2.49999 5.83331C2.03976 5.83331 1.66666 6.2064 1.66666 6.66665V10.8333C1.66666 13.5948 3.90523 15.8333 6.66666 15.8333H9.99999C12.1856 15.8333 14.0436 14.431 14.7235 12.4772C14.8134 12.4922 14.9058 12.5 15 12.5H16.6667C17.5872 12.5 18.3333 11.7538 18.3333 10.8333V8.33331C18.3333 7.41284 17.5872 6.66665 16.6667 6.66665H15C15 6.2064 14.6269 5.83331 14.1667 5.83331H2.49999Z" />
                                <path d="M2.49999 16.6667C2.03976 16.6667 1.66666 17.0398 1.66666 17.5C1.66666 17.9602 2.03976 18.3334 2.49999 18.3334H14.1667C14.6269 18.3334 15 17.9602 15 17.5C15 17.0398 14.6269 16.6667 14.1667 16.6667H2.49999Z" />
                            </svg>
                        </span>
                        <span class="text">Estoque</span>
                    </a>
                    <ul id="ddmenu_estoque" class="collapse dropdown-nav show">
                        <li><a href="produtos.php">Produtos</a></li>
                        <li><a href="inventario.php">Inventário</a></li>
                        <li><a href="entradas.php">Entradas</a></li>
                        <li><a href="saidas.php">Saídas</a></li>
                        <li><a href="estoque-minimo.php">Estoque Mínimo</a></li>
                    </ul>
                </li>

                <li class="nav-item nav-item-has-children active">
                    <a href="#0" class="collapsed" data-bs-toggle="collapse" data-bs-target="#ddmenu_cadastros" aria-controls="ddmenu_cadastros" aria-expanded="true">
                        <span class="icon">
                            <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M1.66666 5.41669C1.66666 3.34562 3.34559 1.66669 5.41666 1.66669C7.48772 1.66669 9.16666 3.34562 9.16666 5.41669C9.16666 7.48775 7.48772 9.16669 5.41666 9.16669C3.34559 9.16669 1.66666 7.48775 1.66666 5.41669Z" />
                                <path d="M1.66666 14.5834C1.66666 12.5123 3.34559 10.8334 5.41666 10.8334C7.48772 10.8334 9.16666 12.5123 9.16666 14.5834C9.16666 16.6545 7.48772 18.3334 5.41666 18.3334C3.34559 18.3334 1.66666 16.6545 1.66666 14.5834Z" />
                                <path d="M10.8333 5.41669C10.8333 3.34562 12.5123 1.66669 14.5833 1.66669C16.6544 1.66669 18.3333 3.34562 18.3333 5.41669C18.3333 7.48775 16.6544 9.16669 14.5833 9.16669C12.5123 9.16669 10.8333 7.48775 10.8333 5.41669Z" />
                                <path d="M10.8333 14.5834C10.8333 12.5123 12.5123 10.8334 14.5833 10.8334C16.6544 10.8334 18.3333 12.5123 18.3333 14.5834C18.3333 16.6545 16.6544 18.3334 14.5833 18.3334C12.5123 18.3334 10.8333 16.6545 10.8333 14.5834Z" />
                            </svg>
                        </span>
                        <span class="text">Cadastros</span>
                    </a>
                    <ul id="ddmenu_cadastros" class="collapse dropdown-nav active">
                        <li><a href="clientes.php" class="active">Clientes</a></li>
                        <li><a href="fornecedores.php">Fornecedores</a></li>
                        <li><a href="categorias.php">Categorias</a></li>
                    </ul>
                </li>

                <li class="nav-item">
                    <a href="relatorios.php">
                        <span class="icon">
                            <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M4.16666 3.33335C4.16666 2.41288 4.91285 1.66669 5.83332 1.66669H14.1667C15.0872 1.66669 15.8333 2.41288 15.8333 3.33335V16.6667C15.8333 17.5872 15.0872 18.3334 14.1667 18.3334H5.83332C4.91285 18.3334 4.16666 17.5872 4.16666 16.6667V3.33335Z" />
                            </svg>
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
                            <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M10 1.66669C5.39763 1.66669 1.66666 5.39766 1.66666 10C1.66666 14.6024 5.39763 18.3334 10 18.3334C14.6024 18.3334 18.3333 14.6024 18.3333 10C18.3333 5.39766 14.6024 1.66669 10 1.66669Z" />
                            </svg>
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
                            <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M10.8333 2.50008C10.8333 2.03984 10.4602 1.66675 9.99999 1.66675C9.53975 1.66675 9.16666 2.03984 9.16666 2.50008C9.16666 2.96032 9.53975 3.33341 9.99999 3.33341C10.4602 3.33341 10.8333 2.96032 10.8333 2.50008Z" />
                                <path d="M11.4272 2.69637C10.9734 2.56848 10.4947 2.50006 10 2.50006C7.10054 2.50006 4.75003 4.85057 4.75003 7.75006V9.20873C4.75003 9.72814 4.62082 10.2393 4.37404 10.6963L3.36705 12.5611C2.89938 13.4272 3.26806 14.5081 4.16749 14.9078C7.88074 16.5581 12.1193 16.5581 15.8326 14.9078C16.732 14.5081 17.1007 13.4272 16.633 12.5611L15.626 10.6963C15.43 10.3333 15.3081 9.93606 15.2663 9.52773C15.0441 9.56431 14.8159 9.58339 14.5833 9.58339C12.2822 9.58339 10.4167 7.71791 10.4167 5.41673C10.4167 4.37705 10.7975 3.42631 11.4272 2.69637Z" />
                            </svg>
                        </span>
                        <span class="text">Suporte</span>
                    </a>
                </li>
            </ul>
        </nav>
    </aside>

    <div class="overlay"></div>

    <!-- ======== main-wrapper start =========== -->
    <main class="main-wrapper">
        <!-- header (mantido simples; sem estilizar hambúrguer) -->
        <header class="header">
            <div class="container-fluid">
                <div class="row align-items-center">
                    <div class="col-lg-6 col-md-6 col-6">
                        <div class="header-left d-flex align-items-center">
                            <div class="menu-toggle-btn mr-20">
                                <button id="menu-toggle" class="main-btn primary-btn btn-hover btn-sm">
                                    <i class="lni lni-menu"></i>
                                </button>
                            </div>
                            <div class="header-search d-none d-md-flex">
                                <form action="#0">
                                    <input type="text" placeholder="Pesquisar (apenas visual)" disabled />
                                    <button><i class="lni lni-search-alt"></i></button>
                                </form>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6 col-md-6 col-6">
                        <div class="header-right">
                            <div class="profile-box ml-15">
                                <button class="dropdown-toggle bg-transparent border-0" type="button" id="profile"
                                    data-bs-toggle="dropdown" aria-expanded="false">
                                    <div class="profile-info">
                                        <div class="info">
                                            <h6>Usuário</h6>
                                            <div class="image">
                                                <img src="assets/images/profile/profile-image.png" alt="" />
                                            </div>
                                        </div>
                                    </div>
                                    <i class="lni lni-chevron-down"></i>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="profile">
                                    <li><a href="#0"><i class="lni lni-user"></i> Perfil</a></li>
                                    <li><a href="#0"><i class="lni lni-cog"></i> Configurações</a></li>
                                    <li><a href="#0"><i class="lni lni-exit"></i> Sair</a></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <section class="section">
            <div class="container-fluid">

                <!-- FILTROS -->
                <div class="cardx mb-3">
                    <div class="head">
                        <div>
                            <div class="d-flex align-items-center gap-2">
                                <span class="pill ok" id="pillCount">0 fiados</span>
                                <span class="muted" id="lblRange">—</span>
                            </div>
                            <div class="muted mt-1">
                                Controle de <b>Fiados</b> (dados fictícios) • clique em <b>Detalhes</b> para ver a venda • clique em <b>Receber</b> para registrar pagamento
                            </div>
                        </div>
                        <div class="toolbar">
                            <button class="main-btn light-btn btn-hover btn-compact" id="btnExcel">
                                <i class="lni lni-download me-1"></i> Excel (CSV)
                            </button>
                            <button class="main-btn light-btn btn-hover btn-compact" id="btnPdf">
                                <i class="lni lni-printer me-1"></i> PDF
                            </button>
                            <select id="per" class="form-select compact" style="min-width:190px;">
                                <option value="10">10 por página</option>
                                <option value="25" selected>25 por página</option>
                                <option value="50">50 por página</option>
                                <option value="100">100 por página</option>
                            </select>
                        </div>
                    </div>

                    <div class="body">
                        <div class="row g-2 align-items-end">
                            <div class="col-md-2">
                                <label class="form-label mini">Data inicial</label>
                                <input type="date" class="form-control compact" id="di">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label mini">Data final</label>
                                <input type="date" class="form-control compact" id="df">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label mini">Status</label>
                                <select class="form-select compact" id="status">
                                    <option value="TODOS" selected>Todos</option>
                                    <option value="ABERTO">Em aberto</option>
                                    <option value="QUITADO">Quitados</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label mini">Canal</label>
                                <select class="form-select compact" id="canal">
                                    <option value="TODOS" selected>Todos</option>
                                    <option value="PRESENCIAL">Presencial</option>
                                    <option value="DELIVERY">Delivery</option>
                                </select>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label mini">Cliente / CPF / Telefone / Fiado #</label>
                                <div class="search-wrap">
                                    <input type="text" class="form-control compact" id="q" placeholder="Ex.: Maria / 123.456.789-00 / (92)..." autocomplete="off">
                                    <div class="suggest" id="suggest"></div>
                                </div>
                            </div>

                            <div class="col-12 d-flex gap-2 flex-wrap mt-2">
                                <button class="main-btn primary-btn btn-hover btn-compact" id="btnFiltrar">
                                    <i class="lni lni-funnel me-1"></i> Filtrar
                                </button>
                                <button class="main-btn light-btn btn-hover btn-compact" id="btnLimpar">
                                    <i class="lni lni-close me-1"></i> Limpar
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ROW COM ALTURA IGUAL -->
                <div class="row g-3 equal-h">

                    <div class="col-lg-8">
                        <div class="cardx card-table">
                            <div class="head">
                                <div class="muted"><b>Fiados</b> • mostra <b>Total</b>, <b>Pago</b> e <b>Deve</b></div>
                                <div class="toolbar">
                                    <span class="pill warn" id="pillLoading" style="display:none;">Carregando…</span>
                                </div>
                            </div>

                            <div class="body">
                                <div class="table-wrap">
                                    <table class="table table-hover mb-0" id="tbFiados">
                                        <thead>
                                            <tr>
                                                <th class="col-id">#</th>
                                                <th class="col-data">Data</th>
                                                <th class="col-nome">Nome</th>
                                                <th class="col-cpf">CPF</th>
                                                <th class="col-tel">Telefone</th>
                                                <th class="col-itens">Venda (itens)</th>
                                                <th class="col-num text-end">Total</th>
                                                <th class="col-num text-end">Pago</th>
                                                <th class="col-num text-end">Deve</th>
                                                <th class="col-status">Status</th>
                                                <th class="col-acoes">Ações</th>
                                            </tr>
                                        </thead>
                                        <tbody id="tbody">
                                            <tr>
                                                <td colspan="11" class="muted">Carregando…</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>

                                <div class="page-nav">
                                    <button class="page-btn" id="btnPrev">←</button>
                                    <span class="page-info" id="pageInfo">Página 1</span>
                                    <button class="page-btn" id="btnNext">→</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-4">
                        <div class="cardx card-tot">
                            <div class="head">
                                <div class="fw-1000">Totais do Filtro</div>
                                <div class="muted">Somatório da lista (dados fictícios)</div>
                            </div>
                            <div class="body">
                                <div class="box-tot">
                                    <div class="tot-row"><span>Quantidade</span><span id="tQtd">0</span></div>
                                    <div class="tot-row"><span>Total (produtos)</span><span id="tTot">R$ 0,00</span></div>
                                    <div class="tot-row"><span>Total pago</span><span id="tPago">R$ 0,00</span></div>
                                    <div class="tot-row"><span>Total a receber</span><span id="tDeve">R$ 0,00</span></div>
                                    <div class="tot-hr"></div>
                                    <div class="grand">
                                        <div class="lbl">ABERTO</div>
                                        <div class="val" id="tAberto">R$ 0,00</div>
                                    </div>
                                </div>

                                <div class="muted mt-3">
                                    <b>Obs.:</b> botão <b>Receber</b> registra pagamento e atualiza <b>Deve</b>.
                                </div>
                            </div>
                        </div>
                    </div>

                </div><!-- /row -->

            </div>
        </section>

        <footer class="footer">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-md-6 order-last order-md-first">
                        <div class="copyright text-md-start">
                            <p class="text-sm">© Painel da Distribuidora • Fiados (demo)</p>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="terms d-flex justify-content-center justify-content-md-end">
                            <a href="#0" class="text-sm">Privacidade</a>
                            <a href="#0" class="text-sm ml-15">Termos</a>
                        </div>
                    </div>
                </div>
            </div>
        </footer>
    </main>

    <!-- =========================
       MODAL: DETALHES DA VENDA (FIADO)
  ========================== -->
    <div class="modal fade" id="mdDetalhes" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content" style="border-radius:16px;">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title" id="dtTitulo">Detalhes do Fiado</h5>
                        <div class="muted" id="dtSub">—</div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>

                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="cardx">
                                <div class="head">
                                    <div class="fw-1000">Cliente</div>
                                    <span class="pill" id="dtStatusPill">—</span>
                                </div>
                                <div class="body">
                                    <div class="row g-2">
                                        <div class="col-sm-6">
                                            <div class="mini">Nome</div>
                                            <div class="fw-1000" id="dtNome">—</div>
                                        </div>
                                        <div class="col-sm-6">
                                            <div class="mini">CPF</div>
                                            <div class="fw-1000" id="dtCpf">—</div>
                                        </div>
                                        <div class="col-sm-6">
                                            <div class="mini">Telefone</div>
                                            <div class="fw-1000" id="dtTel">—</div>
                                        </div>
                                        <div class="col-sm-6">
                                            <div class="mini">Canal</div>
                                            <div class="fw-1000" id="dtCanal">—</div>
                                        </div>
                                        <div class="col-12">
                                            <div class="mini">Observações</div>
                                            <div class="fw-1000" id="dtObs">—</div>
                                        </div>
                                    </div>

                                    <div class="tot-hr"></div>

                                    <div class="d-flex justify-content-between align-items-center">
                                        <div class="mini">Total (produtos)</div>
                                        <div class="fw-1000" id="dtTotal">R$ 0,00</div>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center mt-2">
                                        <div class="mini">Pago</div>
                                        <div class="fw-1000" id="dtPago">R$ 0,00</div>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center mt-2">
                                        <div class="mini">Deve</div>
                                        <div class="fw-1000" id="dtDeve">R$ 0,00</div>
                                    </div>

                                    <div class="mt-3 d-flex gap-2 flex-wrap">
                                        <button class="main-btn primary-btn btn-hover btn-compact" id="dtBtnReceber">
                                            <i class="lni lni-coin me-1"></i> Receber
                                        </button>
                                        <button class="main-btn light-btn btn-hover btn-compact" data-bs-dismiss="modal">
                                            <i class="lni lni-close me-1"></i> Fechar
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="cardx">
                                <div class="head">
                                    <div class="fw-1000">Venda</div>
                                    <div class="muted" id="dtVendaMeta">—</div>
                                </div>
                                <div class="body">
                                    <div class="sale-box" id="dtItensBox">
                                        <!-- itens -->
                                    </div>

                                    <div class="tot-hr"></div>

                                    <div class="mini mb-2">Histórico de pagamentos</div>
                                    <div class="hist-box" id="dtHistBox">
                                        <!-- histórico -->
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>

                <div class="modal-footer">
                    <div class="muted">Dica: clique em <b>Receber</b> para quitar ou pagar parcela.</div>
                </div>
            </div>
        </div>
    </div>

    <!-- =========================
       MODAL: RECEBER PAGAMENTO
  ========================== -->
    <div class="modal fade" id="mdReceber" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content" style="border-radius:16px;">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title" id="rcTitulo">Receber pagamento</h5>
                        <div class="muted" id="rcSub">—</div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>

                <div class="modal-body">
                    <div class="cardx mb-3">
                        <div class="head">
                            <div class="fw-1000">Resumo</div>
                            <span class="pill" id="rcStatusPill">—</span>
                        </div>
                        <div class="body">
                            <div class="row g-2">
                                <div class="col-sm-6">
                                    <div class="mini">Cliente</div>
                                    <div class="fw-1000" id="rcNome">—</div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="mini">CPF</div>
                                    <div class="fw-1000" id="rcCpf">—</div>
                                </div>
                                <div class="col-sm-4">
                                    <div class="mini">Total</div>
                                    <div class="fw-1000" id="rcTotal">R$ 0,00</div>
                                </div>
                                <div class="col-sm-4">
                                    <div class="mini">Pago</div>
                                    <div class="fw-1000" id="rcPago">R$ 0,00</div>
                                </div>
                                <div class="col-sm-4">
                                    <div class="mini">Deve</div>
                                    <div class="fw-1000" id="rcDeve">R$ 0,00</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="cardx">
                        <div class="head">
                            <div class="fw-1000">Registrar pagamento</div>
                            <div class="muted">Pode pagar uma parcela ou quitar</div>
                        </div>
                        <div class="body">
                            <div class="row g-2 align-items-end">
                                <div class="col-md-5">
                                    <label class="form-label mini">Valor pago agora</label>
                                    <input type="number" class="form-control compact" id="rcValor" min="0" step="0.01" placeholder="Ex.: 50,00">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label mini">Forma</label>
                                    <select class="form-select compact" id="rcForma">
                                        <option value="DINHEIRO">Dinheiro</option>
                                        <option value="PIX">PIX</option>
                                        <option value="CARTAO">Cartão</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-check mt-3">
                                        <input class="form-check-input" type="checkbox" id="rcQuitar">
                                        <label class="form-check-label mini" for="rcQuitar">
                                            Quitar tudo
                                        </label>
                                    </div>
                                </div>

                                <div class="col-12 mt-2 d-flex gap-2 flex-wrap">
                                    <button class="main-btn primary-btn btn-hover btn-compact" id="rcSalvar">
                                        <i class="lni lni-save me-1"></i> Registrar
                                    </button>
                                    <button class="main-btn light-btn btn-hover btn-compact" data-bs-dismiss="modal">
                                        <i class="lni lni-close me-1"></i> Cancelar
                                    </button>
                                </div>

                                <div class="col-12 mt-2">
                                    <div class="muted" id="rcHint">—</div>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>

                <div class="modal-footer">
                    <div class="muted">Após registrar, a tabela atualiza automaticamente.</div>
                </div>
            </div>
        </div>
    </div>

    <!-- ========== JS (mantém o template) ========= -->
    <script src="assets/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js"></script>



</body>

</html>