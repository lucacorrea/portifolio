<!DOCTYPE html>
<html lang="pt-BR">

<head>
  <meta charset="UTF-8" />
  <meta http-equiv="X-UA-Compatible" content="IE=edge" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />

  <link rel="shortcut icon" href="assets/images/favicon.svg" type="image/x-icon" />
  <title>Painel da Distribuidora | Fiados</title>

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
    .col-id { width: 70px; }
    .col-data { width: 120px; }
    .col-nome { width: 220px; }
    .col-cpf { width: 140px; }
    .col-tel { width: 150px; }
    .col-itens { width: 280px; }
    .col-num { width: 120px; }
    .col-status { width: 120px; }
    .col-acoes { width: 190px; }

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

    .td-nowrap { white-space: nowrap; }

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

    .item-line { margin-bottom: 8px; }
    .item-line:last-child { margin-bottom: 0; }

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
    .search-wrap { position: relative; }
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
    .suggest .it:hover { background: rgba(241, 245, 249, .8); }
    .suggest .it:last-child { border-bottom: none; }
    .suggest .nm { font-weight: 900; color: #0f172a; min-width: 0; }
    .suggest .meta { font-size: 12px; color: #64748b; white-space: nowrap; }

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
    .sale-row:last-child { border-bottom: none; }
    .sale-row .left { min-width: 0; }
    .sale-row .left .nm {
      font-weight: 900;
      color: #0f172a;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
      max-width: 420px;
    }
    .sale-row .left .cd { color: #64748b; font-size: 12px; }
    .sale-row .right { white-space: nowrap; text-align: right; font-weight: 900; }

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
    .hist-row:last-child { border-bottom: none; }
    .hist-row .mut { color: #64748b; font-weight: 800; }

    @media (max-width: 991.98px) {
      #tbFiados { min-width: 1120px; }
      .grand .val { font-size: 22px; }
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

        <li class="nav-item nav-item-has-children active">
          <a href="#0" data-bs-toggle="collapse" data-bs-target="#ddmenu_operacoes" aria-controls="ddmenu_operacoes" aria-expanded="true">
            <span class="icon">
              <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M3.33334 3.35442C3.33334 2.4223 4.07954 1.66666 5.00001 1.66666H15C15.9205 1.66666 16.6667 2.4223 16.6667 3.35442V16.8565C16.6667 17.5519 15.8827 17.9489 15.3333 17.5317L13.8333 16.3924C13.537 16.1673 13.1297 16.1673 12.8333 16.3924L10.5 18.1646C10.2037 18.3896 9.79634 18.3896 9.50001 18.1646L7.16668 16.3924C6.87038 16.1673 6.46298 16.1673 6.16668 16.3924L4.66668 17.5317C4.11731 17.9489 3.33334 17.5519 3.33334 16.8565V3.35442Z" />
              </svg>
            </span>
            <span class="text">Operações</span>
          </a>
          <ul id="ddmenu_operacoes" class="collapse show dropdown-nav">
            <li><a href="vendidos.php">Vendidos</a></li>
            <li><a href="fiados.html" class="active">Fiados</a></li>
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
              <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M1.66666 5.41669C1.66666 3.34562 3.34559 1.66669 5.41666 1.66669C7.48772 1.66669 9.16666 3.34562 9.16666 5.41669C9.16666 7.48775 7.48772 9.16669 5.41666 9.16669C3.34559 9.16669 1.66666 7.48775 1.66666 5.41669Z" />
                <path d="M1.66666 14.5834C1.66666 12.5123 3.34559 10.8334 5.41666 10.8334C7.48772 10.8334 9.16666 12.5123 9.16666 14.5834C9.16666 16.6545 7.48772 18.3334 5.41666 18.3334C3.34559 18.3334 1.66666 16.6545 1.66666 14.5834Z" />
                <path d="M10.8333 5.41669C10.8333 3.34562 12.5123 1.66669 14.5833 1.66669C16.6544 1.66669 18.3333 3.34562 18.3333 5.41669C18.3333 7.48775 16.6544 9.16669 14.5833 9.16669C12.5123 9.16669 10.8333 7.48775 10.8333 5.41669Z" />
                <path d="M10.8333 14.5834C10.8333 12.5123 12.5123 10.8334 14.5833 10.8334C16.6544 10.8334 18.3333 12.5123 18.3333 14.5834C18.3333 16.6545 16.6544 18.3334 14.5833 18.3334C12.5123 18.3334 10.8333 16.6545 10.8333 14.5834Z" />
              </svg>
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
              <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M4.16666 3.33335C4.16666 2.41288 4.91285 1.66669 5.83332 1.66669H14.1667C15.0872 1.66669 15.8333 2.41288 15.8333 3.33335V16.6667C15.8333 17.5872 15.0872 18.3334 14.1667 18.3334H5.83332C4.91285 18.3334 4.16666 17.5872 4.16666 16.6667V3.33335Z" />
              </svg>
            </span>
            <span class="text">Relatórios</span>
          </a>
        </li>

        <span class="divider"><hr /></span>

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

  <script>
  /* =========================
     DADOS FICTÍCIOS (NADA DE BANCO)
  ========================== */
  const FIADOS_DATA = [
    {
      id: 101,
      data: "2026-03-02",
      canal: "PRESENCIAL",
      cliente: { nome: "Maria do Carmo Silva", cpf: "123.456.789-00", tel: "(92) 99111-2233" },
      obs: "Cliente pediu para pagar em 2 vezes.",
      itens: [
        { codigo: "00005", nome: "Coca-Cola 2L", un: "UN", qtd: 2, preco: 14.00 },
        { codigo: "P0130", nome: "Água Mineral 1L", un: "UN", qtd: 6, preco: 7.00 },
        { codigo: "A0007", nome: "Gelo 5kg", un: "UN", qtd: 1, preco: 18.00 },
      ],
      pagamentos: [
        { at: "2026-03-02 18:40", forma: "PIX", valor: 40.00, obs: "Entrada" }
      ]
    },
    {
      id: 102,
      data: "2026-03-03",
      canal: "DELIVERY",
      cliente: { nome: "João Pedro Almeida", cpf: "987.654.321-10", tel: "(92) 99222-3344" },
      obs: "Deixou endereço com portaria.",
      itens: [
        { codigo: "B0011", nome: "Cerveja (sem álcool) 350ml", un: "UN", qtd: 12, preco: 4.50 },
        { codigo: "S0090", nome: "Salgadinho 60g", un: "UN", qtd: 8, preco: 3.50 },
      ],
      pagamentos: [
        { at: "2026-03-03 20:10", forma: "DINHEIRO", valor: 30.00, obs: "Parcial" }
      ]
    },
    {
      id: 103,
      data: "2026-02-28",
      canal: "PRESENCIAL",
      cliente: { nome: "Ana Paula Nascimento", cpf: "321.654.987-55", tel: "(92) 99333-4455" },
      obs: "Prometeu quitar na próxima semana.",
      itens: [
        { codigo: "R0101", nome: "Refrigerante 600ml", un: "UN", qtd: 6, preco: 6.00 },
        { codigo: "G0020", nome: "Guaraná 2L", un: "UN", qtd: 1, preco: 12.00 },
      ],
      pagamentos: [
        { at: "2026-02-28 11:25", forma: "PIX", valor: 48.00, obs: "Quitação" }
      ]
    },
    {
      id: 104,
      data: "2026-02-27",
      canal: "DELIVERY",
      cliente: { nome: "Carlos Henrique Souza", cpf: "111.222.333-44", tel: "(92) 99444-5566" },
      obs: "Pagará por parcelas semanais.",
      itens: [
        { codigo: "A0202", nome: "Arroz 5kg", un: "UN", qtd: 2, preco: 28.90 },
        { codigo: "F0303", nome: "Feijão 1kg", un: "UN", qtd: 3, preco: 9.90 },
        { codigo: "O0404", nome: "Óleo 900ml", un: "UN", qtd: 2, preco: 8.50 },
      ],
      pagamentos: [
        { at: "2026-02-27 19:12", forma: "DINHEIRO", valor: 20.00, obs: "1ª parcela" },
        { at: "2026-03-01 10:05", forma: "PIX", valor: 30.00, obs: "2ª parcela" }
      ]
    },
    {
      id: 105,
      data: "2026-03-01",
      canal: "PRESENCIAL",
      cliente: { nome: "Raimunda Oliveira", cpf: "555.666.777-88", tel: "(92) 99555-6677" },
      obs: "Sem observações.",
      itens: [
        { codigo: "L1001", nome: "Leite 1L", un: "UN", qtd: 12, preco: 5.20 },
        { codigo: "P2002", nome: "Pão de forma", un: "UN", qtd: 2, preco: 9.50 },
      ],
      pagamentos: []
    },
    {
      id: 106,
      data: "2026-02-25",
      canal: "PRESENCIAL",
      cliente: { nome: "Bruno Lima", cpf: "222.333.444-55", tel: "(92) 99666-7788" },
      obs: "Pagará no fim do mês.",
      itens: [
        { codigo: "C7007", nome: "Café 500g", un: "UN", qtd: 2, preco: 16.90 },
        { codigo: "A8080", nome: "Açúcar 1kg", un: "UN", qtd: 4, preco: 5.80 },
      ],
      pagamentos: [{ at: "2026-02-25 12:44", forma: "PIX", valor: 10.00, obs: "Entrada" }]
    },
    {
      id: 107,
      data: "2026-02-26",
      canal: "DELIVERY",
      cliente: { nome: "Patrícia Gomes", cpf: "909.808.707-66", tel: "(92) 99777-8899" },
      obs: "Parcela de 25 em 25.",
      itens: [
        { codigo: "D1111", nome: "Detergente 500ml", un: "UN", qtd: 6, preco: 2.90 },
        { codigo: "S2222", nome: "Sabão em pó 1kg", un: "UN", qtd: 2, preco: 12.90 },
      ],
      pagamentos: [{ at: "2026-02-26 09:18", forma: "DINHEIRO", valor: 25.00, obs: "1ª parcela" }]
    },
    {
      id: 108,
      data: "2026-03-03",
      canal: "PRESENCIAL",
      cliente: { nome: "Diego Martins", cpf: "101.202.303-40", tel: "(92) 99888-9900" },
      obs: "Vai quitar hoje.",
      itens: [
        { codigo: "B3333", nome: "Biscoito 400g", un: "UN", qtd: 5, preco: 6.40 },
        { codigo: "S4444", nome: "Suco 1L", un: "UN", qtd: 3, preco: 7.80 },
      ],
      pagamentos: [{ at: "2026-03-03 17:02", forma: "PIX", valor: 62.00, obs: "Quitação" }]
    },
    {
      id: 109,
      data: "2026-02-24",
      canal: "DELIVERY",
      cliente: { nome: "Fernanda Ribeiro", cpf: "404.505.606-70", tel: "(92) 99000-1122" },
      obs: "Cliente novo.",
      itens: [{ codigo: "A5555", nome: "Água 20L", un: "UN", qtd: 2, preco: 15.00 }],
      pagamentos: []
    },
    {
      id: 110,
      data: "2026-02-23",
      canal: "PRESENCIAL",
      cliente: { nome: "Lucas Cardoso", cpf: "707.606.505-40", tel: "(92) 99123-4567" },
      obs: "Aguardando salário.",
      itens: [
        { codigo: "M6666", nome: "Macarrão 500g", un: "UN", qtd: 8, preco: 4.20 },
        { codigo: "S7777", nome: "Molho de tomate 340g", un: "UN", qtd: 6, preco: 3.50 },
      ],
      pagamentos: [{ at: "2026-02-23 18:33", forma: "DINHEIRO", valor: 20.00, obs: "Parcial" }]
    }
  ];

  /* =========================
     ESTADO
  ========================== */
  const state = {
    page: 1,
    per: 25,
    filtered: [],
    currentId: null
  };

  /* =========================
     UTILS
  ========================== */
  const brl = (n) => {
    const v = Number(n || 0);
    return "R$ " + v.toLocaleString("pt-BR", { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  };

  const sumSaleTotal = (sale) => (sale.itens || []).reduce((acc, it) => acc + (Number(it.preco) * Number(it.qtd)), 0);
  const sumPaid = (sale) => (sale.pagamentos || []).reduce((acc, p) => acc + Number(p.valor || 0), 0);

  const getDeve = (sale) => {
    const total = sumSaleTotal(sale);
    const paid = sumPaid(sale);
    const deve = Math.max(0, +(total - paid).toFixed(2));
    return deve;
  };

  const getStatus = (sale) => (getDeve(sale) <= 0 ? "QUITADO" : "ABERTO");

  const fmtDateBR = (yyyy_mm_dd) => {
    if (!yyyy_mm_dd) return "—";
    const [y, m, d] = yyyy_mm_dd.split("-");
    return `${d}/${m}/${y}`;
  };

  const nowStr = () => {
    const d = new Date();
    const pad = (x) => String(x).padStart(2, "0");
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())} ${pad(d.getHours())}:${pad(d.getMinutes())}`;
  };

  /* =========================
     FILTROS
  ========================== */
  function applyFilters() {
    const di = document.getElementById("di")?.value?.trim() || "";
    const df = document.getElementById("df")?.value?.trim() || "";
    const status = document.getElementById("status")?.value || "TODOS";
    const canal = document.getElementById("canal")?.value || "TODOS";
    const q = (document.getElementById("q")?.value || "").trim().toLowerCase();

    const out = FIADOS_DATA.filter(sale => {
      if (di && sale.data < di) return false;
      if (df && sale.data > df) return false;

      const st = getStatus(sale);
      if (status !== "TODOS" && st !== status) return false;

      if (canal !== "TODOS" && sale.canal !== canal) return false;

      if (q) {
        const idMatch = String(sale.id) === q;
        const nm = (sale.cliente?.nome || "").toLowerCase();
        const cpf = (sale.cliente?.cpf || "").toLowerCase();
        const tel = (sale.cliente?.tel || "").toLowerCase();
        const obs = (sale.obs || "").toLowerCase();
        const items = (sale.itens || []).map(i => (i.nome || "").toLowerCase()).join(" ");
        const any = nm.includes(q) || cpf.includes(q) || tel.includes(q) || obs.includes(q) || items.includes(q);
        if (!idMatch && !any) return false;
      }

      return true;
    });

    out.sort((a, b) => b.id - a.id);

    state.filtered = out;
    state.page = 1;
    renderAll();
  }

  /* =========================
     RENDER
  ========================== */
  function renderAll() {
    renderMeta();
    renderTable();
    renderTotals();
    renderPagination();
  }

  function renderMeta() {
    const count = state.filtered.length;
    const pill = document.getElementById("pillCount");
    if (pill) pill.textContent = `${count} fiados`;

    const di = document.getElementById("di")?.value?.trim() || "";
    const df = document.getElementById("df")?.value?.trim() || "";
    const status = document.getElementById("status")?.value || "TODOS";
    const canal = document.getElementById("canal")?.value || "TODOS";

    const parts = [];
    if (di || df) parts.push(`Período: ${di ? fmtDateBR(di) : "—"} até ${df ? fmtDateBR(df) : "—"}`);
    if (status !== "TODOS") parts.push(`Status: ${status}`);
    if (canal !== "TODOS") parts.push(`Canal: ${canal}`);

    const lbl = document.getElementById("lblRange");
    if (lbl) lbl.textContent = parts.length ? parts.join(" • ") : "—";
  }

  function previewItems(sale) {
    const itens = sale.itens || [];
    const max = 2;
    const show = itens.slice(0, max);
    const rest = itens.length - show.length;

    const html = [];
    html.push(`<div class="items-preview">`);
    show.forEach(it => {
      const sub = Number(it.qtd) * Number(it.preco);
      html.push(`
        <div class="item-line">
          <div class="item-name td-clip" title="${it.nome}">${it.nome}</div>
          <div class="item-meta">
            <span>${it.qtd} ${it.un} × ${brl(it.preco)}</span>
            <span class="fw-1000">${brl(sub)}</span>
          </div>
        </div>
      `);
    });
    if (rest > 0) html.push(`<div class="item-more">+${rest} item(ns)…</div>`);
    html.push(`</div>`);
    return html.join("");
  }

  function renderTable() {
    const tb = document.getElementById("tbody");
    if (!tb) return;

    const total = state.filtered.length;
    const per = state.per;
    const page = state.page;
    const start = (page - 1) * per;
    const slice = state.filtered.slice(start, start + per);

    if (!total) {
      tb.innerHTML = `<tr><td colspan="11" class="muted">Nenhum fiado encontrado com os filtros.</td></tr>`;
      return;
    }

    tb.innerHTML = slice.map(sale => {
      const total = sumSaleTotal(sale);
      const paid = sumPaid(sale);
      const deve = getDeve(sale);
      const st = getStatus(sale);

      const badge = st === "QUITADO"
        ? `<span class="badge-soft b-paid">QUITADO</span>`
        : `<span class="badge-soft b-open">ABERTO</span>`;

      const btnReceberDisabled = (deve <= 0) ? "disabled" : "";

      return `
        <tr>
          <td class="td-nowrap fw-1000">${sale.id}</td>
          <td class="td-nowrap">${fmtDateBR(sale.data)}</td>
          <td><span class="td-clip" title="${sale.cliente.nome}">${sale.cliente.nome}</span></td>
          <td class="td-nowrap">${sale.cliente.cpf}</td>
          <td class="td-nowrap">${sale.cliente.tel}</td>
          <td>${previewItems(sale)}</td>
          <td class="td-money">${brl(total)}</td>
          <td class="td-money">${brl(paid)}</td>
          <td class="td-money">${brl(deve)}</td>
          <td>${badge}</td>
          <td>
            <div class="actions-wrap">
              <button class="main-btn light-btn btn-hover btn-action" data-act="detalhes" data-id="${sale.id}">
                <i class="lni lni-eye me-1"></i> Detalhes
              </button>
              <button class="main-btn primary-btn btn-hover btn-action" data-act="receber" data-id="${sale.id}" ${btnReceberDisabled}>
                <i class="lni lni-coin me-1"></i> Receber
              </button>
            </div>
          </td>
        </tr>
      `;
    }).join("");

    tb.querySelectorAll("[data-act]").forEach(btn => {
      btn.addEventListener("click", () => {
        const id = Number(btn.getAttribute("data-id"));
        const act = btn.getAttribute("data-act");
        if (act === "detalhes") openDetalhes(id);
        if (act === "receber") openReceber(id);
      });
    });
  }

  function renderTotals() {
    const qtd = state.filtered.length;
    let tot = 0, pago = 0, deve = 0, aberto = 0;

    state.filtered.forEach(sale => {
      const t = sumSaleTotal(sale);
      const p = sumPaid(sale);
      const d = getDeve(sale);
      tot += t; pago += p; deve += d;
      if (d > 0) aberto += d;
    });

    const tQtd = document.getElementById("tQtd");
    const tTot = document.getElementById("tTot");
    const tPago = document.getElementById("tPago");
    const tDeve = document.getElementById("tDeve");
    const tAberto = document.getElementById("tAberto");

    if (tQtd) tQtd.textContent = qtd;
    if (tTot) tTot.textContent = brl(tot);
    if (tPago) tPago.textContent = brl(pago);
    if (tDeve) tDeve.textContent = brl(deve);
    if (tAberto) tAberto.textContent = brl(aberto);
  }

  function renderPagination() {
    const total = state.filtered.length;
    const pages = Math.max(1, Math.ceil(total / state.per));
    state.page = Math.min(state.page, pages);

    const pageInfo = document.getElementById("pageInfo");
    const btnPrev = document.getElementById("btnPrev");
    const btnNext = document.getElementById("btnNext");

    if (pageInfo) pageInfo.textContent = `Página ${state.page} de ${pages}`;
    if (btnPrev) btnPrev.disabled = state.page <= 1;
    if (btnNext) btnNext.disabled = state.page >= pages;
  }

  /* =========================
     MODAIS
  ========================== */
  const mdDetalhesEl = document.getElementById("mdDetalhes");
  const mdReceberEl = document.getElementById("mdReceber");
  const mdDetalhes = mdDetalhesEl ? new bootstrap.Modal(mdDetalhesEl) : null;
  const mdReceber = mdReceberEl ? new bootstrap.Modal(mdReceberEl) : null;

  function findSale(id) {
    return FIADOS_DATA.find(s => Number(s.id) === Number(id)) || null;
  }

  function openDetalhes(id) {
    const sale = findSale(id);
    if (!sale) return alert("Fiado não encontrado.");

    state.currentId = id;

    const total = sumSaleTotal(sale);
    const paid = sumPaid(sale);
    const deve = getDeve(sale);
    const st = getStatus(sale);

    const dtTitulo = document.getElementById("dtTitulo");
    const dtSub = document.getElementById("dtSub");
    const dtStatusPill = document.getElementById("dtStatusPill");

    if (dtTitulo) dtTitulo.textContent = `Detalhes do Fiado #${sale.id}`;
    if (dtSub) dtSub.textContent = `Data: ${fmtDateBR(sale.data)} • Canal: ${sale.canal}`;

    if (dtStatusPill) {
      dtStatusPill.className = "pill " + (st === "QUITADO" ? "ok" : "warn");
      dtStatusPill.textContent = st;
    }

    const setTxt = (id, val) => {
      const el = document.getElementById(id);
      if (el) el.textContent = val;
    };

    setTxt("dtNome", sale.cliente.nome);
    setTxt("dtCpf", sale.cliente.cpf);
    setTxt("dtTel", sale.cliente.tel);
    setTxt("dtCanal", sale.canal);
    setTxt("dtObs", sale.obs || "—");
    setTxt("dtTotal", brl(total));
    setTxt("dtPago", brl(paid));
    setTxt("dtDeve", brl(deve));

    const dtVendaMeta = document.getElementById("dtVendaMeta");
    if (dtVendaMeta) {
      dtVendaMeta.textContent = `${sale.itens.length} item(ns) • Total ${brl(total)} • Pago ${brl(paid)} • Deve ${brl(deve)}`;
    }

    const box = document.getElementById("dtItensBox");
    if (box) {
      box.innerHTML = (sale.itens || []).map(it => {
        const sub = Number(it.qtd) * Number(it.preco);
        return `
          <div class="sale-row">
            <div class="left">
              <div class="nm" title="${it.nome}">${it.nome}</div>
              <div class="cd">${it.codigo} • ${it.qtd} ${it.un} × ${brl(it.preco)}</div>
            </div>
            <div class="right">${brl(sub)}</div>
          </div>
        `;
      }).join("");
    }

    const hist = document.getElementById("dtHistBox");
    const pays = (sale.pagamentos || []).slice().reverse();
    if (hist) {
      if (!pays.length) {
        hist.innerHTML = `<div class="muted">Nenhum pagamento registrado ainda.</div>`;
      } else {
        hist.innerHTML = pays.map((p, idx) => `
          <div class="hist-row">
            <div>
              <div class="fw-1000">${p.forma} • ${brl(p.valor)}</div>
              <div class="mut">${p.at}${p.obs ? " • " + p.obs : ""}</div>
            </div>
            <div class="mut">#${pays.length - idx}</div>
          </div>
        `).join("");
      }
    }

    const btn = document.getElementById("dtBtnReceber");
    if (btn) {
      btn.disabled = deve <= 0;
      btn.onclick = () => {
        if (mdDetalhes) mdDetalhes.hide();
        openReceber(id);
      };
    }

    if (mdDetalhes) mdDetalhes.show();
  }

  function openReceber(id) {
    const sale = findSale(id);
    if (!sale) return alert("Fiado não encontrado.");

    state.currentId = id;

    const total = sumSaleTotal(sale);
    const paid = sumPaid(sale);
    const deve = getDeve(sale);
    const st = getStatus(sale);

    const setTxt = (id, val) => {
      const el = document.getElementById(id);
      if (el) el.textContent = val;
    };

    setTxt("rcTitulo", `Receber • Fiado #${sale.id}`);
    setTxt("rcSub", `Data: ${fmtDateBR(sale.data)} • Canal: ${sale.canal}`);

    const pill = document.getElementById("rcStatusPill");
    if (pill) {
      pill.className = "pill " + (st === "QUITADO" ? "ok" : "warn");
      pill.textContent = st;
    }

    setTxt("rcNome", sale.cliente.nome);
    setTxt("rcCpf", sale.cliente.cpf);
    setTxt("rcTotal", brl(total));
    setTxt("rcPago", brl(paid));
    setTxt("rcDeve", brl(deve));

    const rcValor = document.getElementById("rcValor");
    const rcQuitar = document.getElementById("rcQuitar");
    const rcHint = document.getElementById("rcHint");

    if (rcQuitar) rcQuitar.checked = false;
    if (rcValor) rcValor.value = "";
    if (rcHint) rcHint.textContent = `Saldo atual: ${brl(deve)}. Digite um valor para pagar parcela, ou marque "Quitar tudo".`;

    if (rcQuitar && rcValor) {
      rcQuitar.onchange = () => {
        if (rcQuitar.checked) {
          rcValor.value = deve.toFixed(2);
          rcValor.focus();
        }
      };
    }

    const salvar = document.getElementById("rcSalvar");
    if (salvar) {
      salvar.onclick = () => {
        const sale2 = findSale(state.currentId);
        if (!sale2) return alert("Fiado não encontrado.");

        const deve2 = getDeve(sale2);
        if (deve2 <= 0) {
          alert("Este fiado já está quitado.");
          if (mdReceber) mdReceber.hide();
          renderAll();
          return;
        }

        const raw = (rcValor?.value || "");
        const v = Number(String(raw).replace(",", "."));
        if (!isFinite(v) || v <= 0) {
          alert("Informe um valor válido para pagamento.");
          return;
        }

        if (v > deve2 + 0.0001) {
          alert(`O valor informado (${brl(v)}) é maior que o saldo (${brl(deve2)}).`);
          return;
        }

        const forma = document.getElementById("rcForma")?.value || "DINHEIRO";

        sale2.pagamentos = sale2.pagamentos || [];
        sale2.pagamentos.push({
          at: nowStr(),
          forma,
          valor: +v.toFixed(2),
          obs: (Math.abs(v - deve2) < 0.01) ? "Quitação" : "Parcela"
        });

        const deveAfter = getDeve(sale2);
        alert(deveAfter <= 0 ? "Pagamento registrado. Fiado quitado ✅" : `Pagamento registrado. Saldo restante: ${brl(deveAfter)}`);

        if (mdReceber) mdReceber.hide();
        renderAll();
      };
    }

    if (mdReceber) mdReceber.show();
  }

  /* =========================
     SUGGEST (cliente/CPF/telefone)
  ========================== */
  function buildSuggest(q) {
    const sug = document.getElementById("suggest");
    const query = (q || "").trim().toLowerCase();
    if (!sug) return;

    if (query.length < 2) {
      sug.style.display = "none";
      sug.innerHTML = "";
      return;
    }

    const hits = FIADOS_DATA
      .map(s => {
        const nm = (s.cliente?.nome || "");
        const cpf = (s.cliente?.cpf || "");
        const tel = (s.cliente?.tel || "");
        const hay = `${nm} ${cpf} ${tel} ${s.id}`.toLowerCase();
        return { sale: s, hay, nm, cpf, tel };
      })
      .filter(x => x.hay.includes(query))
      .slice(0, 8);

    if (!hits.length) {
      sug.style.display = "none";
      sug.innerHTML = "";
      return;
    }

    sug.innerHTML = hits.map(x => `
      <div class="it" data-val="${x.nm}">
        <div class="nm td-clip" title="${x.nm}">${x.nm}</div>
        <div class="meta">${x.cpf} • ${x.tel}</div>
      </div>
    `).join("");

    sug.style.display = "block";

    sug.querySelectorAll(".it").forEach(el => {
      el.addEventListener("click", () => {
        const inp = document.getElementById("q");
        if (inp) inp.value = el.getAttribute("data-val") || "";
        sug.style.display = "none";
        applyFilters();
      });
    });
  }

  /* =========================
     EXPORTS (CSV / PRINT)
  ========================== */
  function exportCSV() {
    const rows = state.filtered.map(s => {
      const total = sumSaleTotal(s);
      const paid = sumPaid(s);
      const deve = getDeve(s);
      const st = getStatus(s);
      const itensTxt = (s.itens || []).map(it => `${it.nome} (${it.qtd}x${it.preco})`).join(" | ");
      return [
        s.id,
        s.data,
        s.canal,
        s.cliente.nome,
        s.cliente.cpf,
        s.cliente.tel,
        total.toFixed(2),
        paid.toFixed(2),
        deve.toFixed(2),
        st,
        itensTxt
      ];
    });

    const header = ["ID", "DATA", "CANAL", "NOME", "CPF", "TELEFONE", "TOTAL", "PAGO", "DEVE", "STATUS", "ITENS"];
    const csv = [header, ...rows]
      .map(arr => arr.map(v => `"${String(v).replaceAll('"', '""')}"`).join(";"))
      .join("\n");

    const blob = new Blob(["\uFEFF" + csv], { type: "text/csv;charset=utf-8" });
    const url = URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = url;
    a.download = `fiados_${new Date().toISOString().slice(0, 10)}.csv`;
    document.body.appendChild(a);
    a.click();
    a.remove();
    URL.revokeObjectURL(url);
  }

  function printView() {
    const win = window.open("", "_blank");

    const di = document.getElementById("di")?.value || "—";
    const df = document.getElementById("df")?.value || "—";
    const status = document.getElementById("status")?.value || "TODOS";
    const canal = document.getElementById("canal")?.value || "TODOS";
    const q = document.getElementById("q")?.value || "—";

    const bodyRows = state.filtered.slice(0, 5000).map(s => {
      const total = sumSaleTotal(s);
      const paid = sumPaid(s);
      const deve = getDeve(s);
      return `
        <tr>
          <td>${s.id}</td>
          <td>${fmtDateBR(s.data)}</td>
          <td>${s.cliente.nome}</td>
          <td>${s.cliente.cpf}</td>
          <td>${s.cliente.tel}</td>
          <td>${s.canal}</td>
          <td style="text-align:right;">${brl(total)}</td>
          <td style="text-align:right;">${brl(paid)}</td>
          <td style="text-align:right;">${brl(deve)}</td>
          <td>${getStatus(s)}</td>
        </tr>
      `;
    }).join("");

    const totals = state.filtered.reduce((acc, s) => {
      acc.t += sumSaleTotal(s);
      acc.p += sumPaid(s);
      acc.d += getDeve(s);
      return acc;
    }, { t: 0, p: 0, d: 0 });

    win.document.write(`
      <!doctype html>
      <html lang="pt-BR">
      <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width,initial-scale=1" />
        <title>PAINEL DA DISTRIBUIDORA - FIADOS</title>
        <style>
          @page { size: A4; margin: 16mm; }
          body { font-family: Arial, Helvetica, sans-serif; color: #0f172a; }
          h1 { font-size: 18px; margin: 0 0 8px; }
          .meta { font-size: 12px; margin: 2px 0; color: #475569; }
          table { width: 100%; border-collapse: collapse; margin-top: 12px; }
          th, td { border: 1px solid #e5e7eb; padding: 8px; font-size: 12px; }
          th { background: #f1f5f9; text-align: left; }
          tfoot td { font-weight: 900; background: #f8fafc; }
          .right { text-align: right; }
          .btn { display:none; }
          @media screen {
            body { background:#0b1220; padding:24px; }
            .sheet { max-width: 980px; margin:0 auto; background:#fff; border-radius:14px; padding:18px; box-shadow:0 20px 60px rgba(0,0,0,.25); }
            .btn { display:inline-block; margin-top:10px; padding:10px 14px; border-radius:10px; border:1px solid #cbd5e1; cursor:pointer; font-weight:900; background:#fff; }
          }
        </style>
      </head>
      <body>
        <div class="sheet">
          <h1>PAINEL DA DISTRIBUIDORA - FIADOS</h1>
          <div class="meta">Gerado em: ${new Date().toLocaleString("pt-BR")}</div>
          <div class="meta">Período: ${di} até ${df} • Status: ${status} • Canal: ${canal} • Busca: ${q}</div>
          <button class="btn" onclick="window.print()">Imprimir / Salvar PDF</button>

          <table>
            <thead>
              <tr>
                <th style="width:70px;">ID</th>
                <th style="width:90px;">Data</th>
                <th>Cliente</th>
                <th style="width:130px;">CPF</th>
                <th style="width:130px;">Telefone</th>
                <th style="width:110px;">Canal</th>
                <th class="right" style="width:110px;">Total</th>
                <th class="right" style="width:110px;">Pago</th>
                <th class="right" style="width:110px;">Deve</th>
                <th style="width:90px;">Status</th>
              </tr>
            </thead>
            <tbody>
              ${bodyRows || `<tr><td colspan="10">Sem dados.</td></tr>`}
            </tbody>
            <tfoot>
              <tr>
                <td colspan="6" class="right">Totais</td>
                <td class="right">${brl(totals.t)}</td>
                <td class="right">${brl(totals.p)}</td>
                <td class="right">${brl(totals.d)}</td>
                <td></td>
              </tr>
            </tfoot>
          </table>
        </div>

        <script>
          window.addEventListener('load', () => setTimeout(() => window.print(), 350));
        </script>
      </body>
      </html>
    `);
    win.document.close();
  }

  /* =========================
     EVENTOS + INIT (com proteção)
  ========================== */
  document.addEventListener("DOMContentLoaded", () => {
    const $ = (id) => document.getElementById(id);
    const on = (el, ev, fn) => el && el.addEventListener(ev, fn);

    on($("per"), "change", (e) => {
      state.per = Number(e.target.value) || 25;
      state.page = 1;
      renderAll();
    });

    on($("btnPrev"), "click", () => {
      state.page = Math.max(1, state.page - 1);
      renderTable();
      renderPagination();
    });

    on($("btnNext"), "click", () => {
      const pages = Math.max(1, Math.ceil(state.filtered.length / state.per));
      state.page = Math.min(pages, state.page + 1);
      renderTable();
      renderPagination();
    });

    on($("btnFiltrar"), "click", applyFilters);

    on($("btnLimpar"), "click", () => {
      if ($("di")) $("di").value = "";
      if ($("df")) $("df").value = "";
      if ($("status")) $("status").value = "TODOS";
      if ($("canal")) $("canal").value = "TODOS";
      if ($("q")) $("q").value = "";
      if ($("suggest")) $("suggest").style.display = "none";
      applyFilters();
    });

    on($("q"), "input", (e) => buildSuggest(e.target.value));

    document.addEventListener("click", (e) => {
      const sug = $("suggest");
      const wrap = document.querySelector(".search-wrap");
      if (!sug || !wrap) return;
      if (!wrap.contains(e.target)) sug.style.display = "none";
    });

    on($("btnExcel"), "click", exportCSV);
    on($("btnPdf"), "click", printView);

    on($("q"), "keydown", (e) => {
      if (e.key === "Enter") {
        e.preventDefault();
        if ($("suggest")) $("suggest").style.display = "none";
        applyFilters();
      }
    });

    function init() {
      state.per = Number($("per")?.value) || 25;
      state.filtered = FIADOS_DATA.slice().sort((a, b) => b.id - a.id);
      renderAll();
    }

    init();
  });
</script>

</body>

</html>