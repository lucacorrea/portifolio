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
       Só tabela + filtros
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

    #tbClientes {
      width: 100%;
      min-width: 1040px;
      table-layout: fixed;
    }

    #tbClientes thead th {
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

    #tbClientes tbody td {
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
    .col-nome { width: 280px; }
    .col-cpf { width: 150px; }
    .col-tel { width: 160px; }
    .col-status { width: 130px; }
    .col-created { width: 180px; }
    .col-acoes { width: 240px; }

    .mini {
      font-size: 12px;
      color: #475569;
      font-weight: 800;
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

    .b-ativo {
      background: rgba(240, 253, 244, .95);
      color: #166534;
      border-color: rgba(34, 197, 94, .25);
    }

    .b-inativo {
      background: rgba(241, 245, 249, .9);
      color: #334155;
      border-color: rgba(148, 163, 184, .28);
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

    @media (max-width: 991.98px) {
      #tbClientes { min-width: 980px; }
      .grand .val { font-size: 22px; }
    }
  </style>
</head>

<body>
  <div id="preloader"><div class="spinner"></div></div>

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
            <li><a href="vendidos.php">Vendidos</a></li>
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
          <ul id="ddmenu_estoque" class="collapse dropdown-nav">
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
          <ul id="ddmenu_cadastros" class="collapse dropdown-nav active show">
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
                <span class="pill ok" id="pillCount">0 clientes</span>
                <span class="muted" id="lblRange">—</span>
              </div>
              <div class="muted mt-1">
                Lista de <b>Clientes</b> (dados fictícios) • botão <b>Novo</b> cadastra • ações: <b>Detalhes</b>, <b>Editar</b>, <b>Excluir</b>
              </div>
            </div>

            <div class="toolbar">
              <button class="main-btn primary-btn btn-hover btn-compact" id="btnNovo">
                <i class="lni lni-plus me-1"></i> Novo cliente
              </button>
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
                <label class="form-label mini">Status</label>
                <select class="form-select compact" id="status">
                  <option value="TODOS" selected>Todos</option>
                  <option value="ATIVO">Ativo</option>
                  <option value="INATIVO">Inativo</option>
                </select>
              </div>

              <div class="col-md-6">
                <label class="form-label mini">Buscar (Nome / CPF / Telefone / ID)</label>
                <div class="search-wrap">
                  <input type="text" class="form-control compact" id="q" placeholder="Ex.: Maria / 123.456.789-00 / (92)..." autocomplete="off">
                  <div class="suggest" id="suggest"></div>
                </div>
              </div>

              <div class="col-md-4 d-flex gap-2 flex-wrap">
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
                <div class="muted"><b>Clientes</b> • Nome, CPF e Telefone (obrigatórios)</div>
                <div class="toolbar">
                  <span class="pill warn" id="pillLoading" style="display:none;">Carregando…</span>
                </div>
              </div>

              <div class="body">
                <div class="table-wrap">
                  <table class="table table-hover mb-0" id="tbClientes">
                    <thead>
                      <tr>
                        <th class="col-id">ID</th>
                        <th class="col-nome">Nome</th>
                        <th class="col-cpf">CPF</th>
                        <th class="col-tel">Telefone</th>
                        <th class="col-status">Status</th>
                        <th class="col-created">Criado em</th>
                        <th class="col-acoes">Ações</th>
                      </tr>
                    </thead>
                    <tbody id="tbody">
                      <tr>
                        <td colspan="7" class="muted">Carregando…</td>
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
                <div class="fw-1000">Resumo</div>
                <div class="muted">Contagem da lista (dados fictícios)</div>
              </div>
              <div class="body">
                <div class="box-tot">
                  <div class="tot-row"><span>Total</span><span id="tTotal">0</span></div>
                  <div class="tot-row"><span>Ativos</span><span id="tAtivos">0</span></div>
                  <div class="tot-row"><span>Inativos</span><span id="tInativos">0</span></div>
                  <div class="tot-hr"></div>
                  <div class="grand">
                    <div class="lbl">ATIVOS</div>
                    <div class="val" id="tAtivosBig">0</div>
                  </div>
                </div>

                <div class="muted mt-3">
                  <b>Obs.:</b> CPF é único (não permite duplicar).
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
              <p class="text-sm">© Painel da Distribuidora • Clientes (demo)</p>
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
       MODAL: NOVO / EDITAR CLIENTE
  ========================== -->
  <div class="modal fade" id="mdForm" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
      <div class="modal-content" style="border-radius:16px;">
        <div class="modal-header">
          <div>
            <h5 class="modal-title" id="fmTitulo">Novo cliente</h5>
            <div class="muted" id="fmSub">Preencha Nome, CPF e Telefone</div>
          </div>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
        </div>

        <div class="modal-body">
          <div class="cardx">
            <div class="head">
              <div class="fw-1000">Dados do cliente</div>
              <span class="pill" id="fmPill">Obrigatório *</span>
            </div>
            <div class="body">
              <input type="hidden" id="fmId" value="">

              <div class="row g-2">
                <div class="col-md-7">
                  <label class="form-label mini">Nome *</label>
                  <input type="text" class="form-control compact" id="fmNome" placeholder="Ex.: Maria do Carmo">
                </div>
                <div class="col-md-5">
                  <label class="form-label mini">Status</label>
                  <select class="form-select compact" id="fmStatus">
                    <option value="ATIVO" selected>ATIVO</option>
                    <option value="INATIVO">INATIVO</option>
                  </select>
                </div>

                <div class="col-md-6">
                  <label class="form-label mini">CPF *</label>
                  <input type="text" class="form-control compact" id="fmCpf" placeholder="000.000.000-00" inputmode="numeric" maxlength="14">
                  <div class="muted mt-1">Pode digitar só números (o sistema formata).</div>
                </div>

                <div class="col-md-6">
                  <label class="form-label mini">Telefone *</label>
                  <input type="text" class="form-control compact" id="fmTel" placeholder="(00) 00000-0000" inputmode="tel" maxlength="16">
                </div>

                <div class="col-12">
                  <label class="form-label mini">Observações</label>
                  <input type="text" class="form-control compact" id="fmObs" placeholder="Opcional">
                </div>

                <div class="col-12 mt-2">
                  <div class="muted" id="fmMsg">—</div>
                </div>
              </div>

            </div>
          </div>
        </div>

        <div class="modal-footer d-flex justify-content-between">
          <div class="muted">Campos com * são obrigatórios.</div>
          <div class="d-flex gap-2">
            <button class="main-btn primary-btn btn-hover btn-compact" id="fmSalvar">
              <i class="lni lni-save me-1"></i> Salvar
            </button>
            <button class="main-btn light-btn btn-hover btn-compact" data-bs-dismiss="modal">
              <i class="lni lni-close me-1"></i> Cancelar
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- =========================
       MODAL: DETALHES
  ========================== -->
  <div class="modal fade" id="mdDetalhes" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
      <div class="modal-content" style="border-radius:16px;">
        <div class="modal-header">
          <div>
            <h5 class="modal-title" id="dtTitulo">Detalhes do cliente</h5>
            <div class="muted" id="dtSub">—</div>
          </div>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
        </div>

        <div class="modal-body">
          <div class="cardx">
            <div class="head">
              <div class="fw-1000">Informações</div>
              <span class="pill" id="dtStatusPill">—</span>
            </div>
            <div class="body">
              <div class="row g-2">
                <div class="col-sm-7">
                  <div class="mini">Nome</div>
                  <div class="fw-1000" id="dtNome">—</div>
                </div>
                <div class="col-sm-5">
                  <div class="mini">ID</div>
                  <div class="fw-1000" id="dtId">—</div>
                </div>

                <div class="col-sm-6">
                  <div class="mini">CPF</div>
                  <div class="fw-1000" id="dtCpf">—</div>
                </div>

                <div class="col-sm-6">
                  <div class="mini">Telefone</div>
                  <div class="fw-1000" id="dtTel">—</div>
                </div>

                <div class="col-12">
                  <div class="mini">Observações</div>
                  <div class="fw-1000" id="dtObs">—</div>
                </div>

                <div class="col-12">
                  <div class="mini">Criado em</div>
                  <div class="fw-1000" id="dtCreated">—</div>
                </div>
              </div>

              <div class="mt-3 d-flex gap-2 flex-wrap">
                <button class="main-btn primary-btn btn-hover btn-compact" id="dtEditar">
                  <i class="lni lni-pencil me-1"></i> Editar
                </button>
                <button class="main-btn light-btn btn-hover btn-compact" data-bs-dismiss="modal">
                  <i class="lni lni-close me-1"></i> Fechar
                </button>
              </div>

            </div>
          </div>
        </div>

        <div class="modal-footer">
          <div class="muted">Ações de edição/exclusão são apenas demo (dados em memória).</div>
        </div>
      </div>
    </div>
  </div>

  <!-- =========================
       MODAL: CONFIRMAR EXCLUSÃO
  ========================== -->
  <div class="modal fade" id="mdExcluir" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content" style="border-radius:16px;">
        <div class="modal-header">
          <h5 class="modal-title">Excluir cliente</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
        </div>

        <div class="modal-body">
          <div class="muted">Tem certeza que deseja excluir este cliente?</div>
          <div class="fw-1000 mt-2" id="exNome">—</div>
          <div class="muted" id="exMeta">—</div>
        </div>

        <div class="modal-footer">
          <button class="main-btn light-btn btn-hover btn-compact" data-bs-dismiss="modal">
            <i class="lni lni-close me-1"></i> Cancelar
          </button>
          <button class="main-btn primary-btn btn-hover btn-compact" id="exConfirmar">
            <i class="lni lni-trash-can me-1"></i> Excluir
          </button>
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
    const CLIENTES_DATA = [
      { id: 1, nome: "Maria do Carmo Silva", cpf: "12345678900", telefone: "92991112233", status: "ATIVO", obs: "Cliente antiga.", created_at: "2026-02-12 09:10" },
      { id: 2, nome: "João Pedro Almeida", cpf: "98765432110", telefone: "92992223344", status: "ATIVO", obs: "", created_at: "2026-02-14 14:22" },
      { id: 3, nome: "Ana Paula Nascimento", cpf: "32165498755", telefone: "92993334455", status: "INATIVO", obs: "Telefone desatualizado.", created_at: "2026-02-15 11:40" },
      { id: 4, nome: "Carlos Henrique Souza", cpf: "11122233344", telefone: "92994445566", status: "ATIVO", obs: "", created_at: "2026-02-18 18:05" },
      { id: 5, nome: "Raimunda Oliveira", cpf: "55566677788", telefone: "92995556677", status: "ATIVO", obs: "", created_at: "2026-02-20 10:00" },
      { id: 6, nome: "Bruno Lima", cpf: "22233344455", telefone: "92996667788", status: "ATIVO", obs: "Prefere PIX.", created_at: "2026-02-22 16:12" },
      { id: 7, nome: "Patrícia Gomes", cpf: "90980870766", telefone: "92997778899", status: "ATIVO", obs: "", created_at: "2026-02-23 08:59" },
      { id: 8, nome: "Diego Martins", cpf: "10120230340", telefone: "92998889900", status: "INATIVO", obs: "Bloqueado por cadastro incompleto.", created_at: "2026-02-25 13:31" },
      { id: 9, nome: "Fernanda Ribeiro", cpf: "40450560670", telefone: "92990001122", status: "ATIVO", obs: "", created_at: "2026-02-26 09:45" },
      { id: 10, nome: "Lucas Cardoso", cpf: "70760650540", telefone: "92991234567", status: "ATIVO", obs: "", created_at: "2026-03-01 17:20" }
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
    const onlyDigits = (s) => String(s || "").replace(/\D+/g, "");
    const pad2 = (n) => String(n).padStart(2, "0");

    function formatCpf(cpfDigits) {
      const d = onlyDigits(cpfDigits).slice(0, 11);
      if (d.length <= 3) return d;
      if (d.length <= 6) return d.replace(/(\d{3})(\d+)/, "$1.$2");
      if (d.length <= 9) return d.replace(/(\d{3})(\d{3})(\d+)/, "$1.$2.$3");
      return d.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, "$1.$2.$3-$4");
    }

    function formatTel(telDigits) {
      const d = onlyDigits(telDigits).slice(0, 11);
      if (d.length <= 2) return d ? `(${d}` : "";
      const dd = d.slice(0, 2);
      const rest = d.slice(2);

      if (rest.length <= 4) return `(${dd}) ${rest}`;
      if (rest.length <= 8) return `(${dd}) ${rest.slice(0, 4)}-${rest.slice(4)}`;
      return `(${dd}) ${rest.slice(0, 5)}-${rest.slice(5)}`;
    }

    function nowStr() {
      const d = new Date();
      return `${d.getFullYear()}-${pad2(d.getMonth()+1)}-${pad2(d.getDate())} ${pad2(d.getHours())}:${pad2(d.getMinutes())}`;
    }

    function matchesQuery(cli, q) {
      if (!q) return true;
      const qq = q.toLowerCase().trim();
      const dig = onlyDigits(qq);

      // id exato
      if (/^\d+$/.test(qq) && String(cli.id) === qq) return true;

      const nome = (cli.nome || "").toLowerCase();
      const cpfFmt = formatCpf(cli.cpf).toLowerCase();
      const telFmt = formatTel(cli.telefone).toLowerCase();

      const hay = `${nome} ${cpfFmt} ${telFmt} ${cli.cpf} ${cli.telefone}`.toLowerCase();

      if (dig && (cli.cpf.includes(dig) || cli.telefone.includes(dig))) return true;
      return hay.includes(qq);
    }

    function statusBadge(st) {
      if (st === "ATIVO") return `<span class="badge-soft b-ativo">ATIVO</span>`;
      return `<span class="badge-soft b-inativo">INATIVO</span>`;
    }

    function nextId() {
      const max = CLIENTES_DATA.reduce((m, c) => Math.max(m, Number(c.id) || 0), 0);
      return max + 1;
    }

    function findClient(id) {
      return CLIENTES_DATA.find(c => Number(c.id) === Number(id)) || null;
    }

    function validateClient({ id, nome, cpf, telefone }) {
      const n = String(nome || "").trim();
      const c = onlyDigits(cpf);
      const t = onlyDigits(telefone);

      if (n.length < 2) return "Informe um nome válido.";
      if (c.length !== 11) return "CPF inválido. Informe 11 dígitos.";
      if (t.length < 8) return "Telefone inválido.";

      const dup = CLIENTES_DATA.find(x => x.cpf === c && Number(x.id) !== Number(id || 0));
      if (dup) return "CPF já cadastrado para outro cliente.";

      return "";
    }

    /* =========================
       FILTROS
    ========================== */
    function applyFilters() {
      const st = document.getElementById("status")?.value || "TODOS";
      const q = document.getElementById("q")?.value || "";

      const out = CLIENTES_DATA
        .filter(c => (st === "TODOS" ? true : c.status === st))
        .filter(c => matchesQuery(c, q))
        .slice()
        .sort((a, b) => b.id - a.id);

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
      if (pill) pill.textContent = `${count} clientes`;

      const st = document.getElementById("status")?.value || "TODOS";
      const q = document.getElementById("q")?.value?.trim() || "";
      const parts = [];
      if (st !== "TODOS") parts.push(`Status: ${st}`);
      if (q) parts.push(`Busca: ${q}`);

      const lbl = document.getElementById("lblRange");
      if (lbl) lbl.textContent = parts.length ? parts.join(" • ") : "—";
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
        tb.innerHTML = `<tr><td colspan="7" class="muted">Nenhum cliente encontrado com os filtros.</td></tr>`;
        return;
      }

      tb.innerHTML = slice.map(c => `
        <tr>
          <td class="td-nowrap fw-1000">${c.id}</td>
          <td><span class="td-clip" title="${c.nome}">${c.nome}</span></td>
          <td class="td-nowrap">${formatCpf(c.cpf)}</td>
          <td class="td-nowrap">${formatTel(c.telefone)}</td>
          <td>${statusBadge(c.status)}</td>
          <td class="td-nowrap">${c.created_at || "—"}</td>
          <td>
            <div class="actions-wrap">
              <button class="main-btn light-btn btn-hover btn-action" data-act="detalhes" data-id="${c.id}">
                <i class="lni lni-eye me-1"></i> Detalhes
              </button>
              <button class="main-btn primary-btn btn-hover btn-action" data-act="editar" data-id="${c.id}">
                <i class="lni lni-pencil me-1"></i> Editar
              </button>
              <button class="main-btn light-btn btn-hover btn-action" data-act="excluir" data-id="${c.id}">
                <i class="lni lni-trash-can me-1"></i> Excluir
              </button>
            </div>
          </td>
        </tr>
      `).join("");

      tb.querySelectorAll("[data-act]").forEach(btn => {
        btn.addEventListener("click", () => {
          const id = Number(btn.getAttribute("data-id"));
          const act = btn.getAttribute("data-act");
          if (act === "detalhes") openDetalhes(id);
          if (act === "editar") openFormEdit(id);
          if (act === "excluir") openExcluir(id);
        });
      });
    }

    function renderTotals() {
      const total = state.filtered.length;
      const ativos = state.filtered.filter(c => c.status === "ATIVO").length;
      const inativos = total - ativos;

      const tTotal = document.getElementById("tTotal");
      const tAtivos = document.getElementById("tAtivos");
      const tInativos = document.getElementById("tInativos");
      const tAtivosBig = document.getElementById("tAtivosBig");

      if (tTotal) tTotal.textContent = total;
      if (tAtivos) tAtivos.textContent = ativos;
      if (tInativos) tInativos.textContent = inativos;
      if (tAtivosBig) tAtivosBig.textContent = ativos;
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
    const mdForm = new bootstrap.Modal(document.getElementById("mdForm"));
    const mdDetalhes = new bootstrap.Modal(document.getElementById("mdDetalhes"));
    const mdExcluir = new bootstrap.Modal(document.getElementById("mdExcluir"));

    function openFormNew() {
      state.currentId = null;

      document.getElementById("fmTitulo").textContent = "Novo cliente";
      document.getElementById("fmSub").textContent = "Preencha Nome, CPF e Telefone";
      document.getElementById("fmId").value = "";
      document.getElementById("fmNome").value = "";
      document.getElementById("fmCpf").value = "";
      document.getElementById("fmTel").value = "";
      document.getElementById("fmStatus").value = "ATIVO";
      document.getElementById("fmObs").value = "";
      document.getElementById("fmMsg").textContent = "—";

      mdForm.show();
      setTimeout(() => document.getElementById("fmNome").focus(), 200);
    }

    function openFormEdit(id) {
      const c = findClient(id);
      if (!c) return alert("Cliente não encontrado.");

      state.currentId = id;

      document.getElementById("fmTitulo").textContent = `Editar cliente #${c.id}`;
      document.getElementById("fmSub").textContent = "Altere os dados e salve";
      document.getElementById("fmId").value = c.id;
      document.getElementById("fmNome").value = c.nome || "";
      document.getElementById("fmCpf").value = formatCpf(c.cpf);
      document.getElementById("fmTel").value = formatTel(c.telefone);
      document.getElementById("fmStatus").value = c.status || "ATIVO";
      document.getElementById("fmObs").value = c.obs || "";
      document.getElementById("fmMsg").textContent = "—";

      mdForm.show();
    }

    function openDetalhes(id) {
      const c = findClient(id);
      if (!c) return alert("Cliente não encontrado.");

      state.currentId = id;

      document.getElementById("dtTitulo").textContent = `Detalhes do cliente #${c.id}`;
      document.getElementById("dtSub").textContent = "Cadastro de clientes";

      const pill = document.getElementById("dtStatusPill");
      pill.className = "pill " + (c.status === "ATIVO" ? "ok" : "warn");
      pill.textContent = c.status;

      document.getElementById("dtId").textContent = String(c.id);
      document.getElementById("dtNome").textContent = c.nome || "—";
      document.getElementById("dtCpf").textContent = formatCpf(c.cpf);
      document.getElementById("dtTel").textContent = formatTel(c.telefone);
      document.getElementById("dtObs").textContent = c.obs ? c.obs : "—";
      document.getElementById("dtCreated").textContent = c.created_at || "—";

      document.getElementById("dtEditar").onclick = () => {
        mdDetalhes.hide();
        openFormEdit(c.id);
      };

      mdDetalhes.show();
    }

    function openExcluir(id) {
      const c = findClient(id);
      if (!c) return alert("Cliente não encontrado.");

      state.currentId = id;

      document.getElementById("exNome").textContent = c.nome;
      document.getElementById("exMeta").textContent = `${formatCpf(c.cpf)} • ${formatTel(c.telefone)} • ${c.status}`;

      mdExcluir.show();
    }

    /* =========================
       SALVAR (novo/editar)
    ========================== */
    function saveForm() {
      const id = Number(document.getElementById("fmId").value || 0) || null;
      const nome = document.getElementById("fmNome").value;
      const cpfRaw = document.getElementById("fmCpf").value;
      const telRaw = document.getElementById("fmTel").value;
      const status = document.getElementById("fmStatus").value || "ATIVO";
      const obs = document.getElementById("fmObs").value || "";

      const payload = {
        id: id || 0,
        nome: String(nome || "").trim(),
        cpf: onlyDigits(cpfRaw),
        telefone: onlyDigits(telRaw),
        status,
        obs: String(obs || "").trim()
      };

      const err = validateClient(payload);
      const msg = document.getElementById("fmMsg");
      if (err) {
        msg.textContent = "⚠ " + err;
        return;
      }

      if (!id) {
        // novo
        CLIENTES_DATA.push({
          id: nextId(),
          nome: payload.nome,
          cpf: payload.cpf,
          telefone: payload.telefone,
          status: payload.status,
          obs: payload.obs,
          created_at: nowStr()
        });
      } else {
        // editar
        const c = findClient(id);
        if (!c) {
          msg.textContent = "⚠ Cliente não encontrado.";
          return;
        }
        c.nome = payload.nome;
        c.cpf = payload.cpf;
        c.telefone = payload.telefone;
        c.status = payload.status;
        c.obs = payload.obs;
      }

      mdForm.hide();
      applyFilters();
      alert("Cliente salvo com sucesso ✅");
    }

    /* =========================
       EXCLUIR
    ========================== */
    function confirmExcluir() {
      const id = Number(state.currentId || 0);
      if (!id) return;

      const idx = CLIENTES_DATA.findIndex(c => Number(c.id) === id);
      if (idx >= 0) CLIENTES_DATA.splice(idx, 1);

      mdExcluir.hide();
      applyFilters();
      alert("Cliente excluído ✅");
    }

    /* =========================
       SUGGEST
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

      const hits = CLIENTES_DATA
        .map(c => {
          const nm = (c.nome || "");
          const cpf = formatCpf(c.cpf);
          const tel = formatTel(c.telefone);
          const hay = `${nm} ${cpf} ${tel} ${c.id}`.toLowerCase();
          return { c, hay, nm, cpf, tel };
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
          document.getElementById("q").value = el.getAttribute("data-val") || "";
          sug.style.display = "none";
          applyFilters();
        });
      });
    }

    /* =========================
       EXPORTS (CSV / PRINT)
    ========================== */
    function exportCSV() {
      const rows = state.filtered.map(c => ([
        c.id,
        c.nome,
        formatCpf(c.cpf),
        formatTel(c.telefone),
        c.status,
        c.created_at || ""
      ]));

      const header = ["ID", "NOME", "CPF", "TELEFONE", "STATUS", "CRIADO_EM"];
      const csv = [header, ...rows]
        .map(arr => arr.map(v => `"${String(v ?? "").replaceAll('"', '""')}"`).join(";"))
        .join("\n");

      const blob = new Blob(["\uFEFF" + csv], { type: "text/csv;charset=utf-8" });
      const url = URL.createObjectURL(blob);
      const a = document.createElement("a");
      a.href = url;
      a.download = `clientes_${new Date().toISOString().slice(0, 10)}.csv`;
      document.body.appendChild(a);
      a.click();
      a.remove();
      URL.revokeObjectURL(url);
    }

    function printView() {
      const win = window.open("", "_blank");
      const st = document.getElementById("status")?.value || "TODOS";
      const q = document.getElementById("q")?.value || "—";

      const bodyRows = state.filtered.slice(0, 5000).map(c => `
        <tr>
          <td>${c.id}</td>
          <td>${c.nome}</td>
          <td>${formatCpf(c.cpf)}</td>
          <td>${formatTel(c.telefone)}</td>
          <td>${c.status}</td>
          <td>${c.created_at || ""}</td>
        </tr>
      `).join("");

      win.document.write(`
        <!doctype html>
        <html lang="pt-BR">
        <head>
          <meta charset="utf-8" />
          <meta name="viewport" content="width=device-width,initial-scale=1" />
          <title>PAINEL DA DISTRIBUIDORA - CLIENTES</title>
          <style>
            @page { size: A4; margin: 16mm; }
            body { font-family: Arial, Helvetica, sans-serif; color: #0f172a; }
            h1 { font-size: 18px; margin: 0 0 8px; }
            .meta { font-size: 12px; margin: 2px 0; color: #475569; }
            table { width: 100%; border-collapse: collapse; margin-top: 12px; }
            th, td { border: 1px solid #e5e7eb; padding: 8px; font-size: 12px; }
            th { background: #f1f5f9; text-align: left; }
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
            <h1>PAINEL DA DISTRIBUIDORA - CLIENTES</h1>
            <div class="meta">Gerado em: ${new Date().toLocaleString("pt-BR")}</div>
            <div class="meta">Status: ${st} • Busca: ${q}</div>
            <button class="btn" onclick="window.print()">Imprimir / Salvar PDF</button>

            <table>
              <thead>
                <tr>
                  <th style="width:60px;">ID</th>
                  <th>Nome</th>
                  <th style="width:130px;">CPF</th>
                  <th style="width:140px;">Telefone</th>
                  <th style="width:90px;">Status</th>
                  <th style="width:140px;">Criado em</th>
                </tr>
              </thead>
              <tbody>
                ${bodyRows || `<tr><td colspan="6">Sem dados.</td></tr>`}
              </tbody>
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
       EVENTOS + INIT
    ========================== */
    document.addEventListener("DOMContentLoaded", () => {
      const $ = (id) => document.getElementById(id);
      const on = (el, ev, fn) => el && el.addEventListener(ev, fn);

      // máscara (CPF / telefone)
      on($("fmCpf"), "input", (e) => e.target.value = formatCpf(e.target.value));
      on($("fmTel"), "input", (e) => e.target.value = formatTel(e.target.value));

      on($("btnNovo"), "click", openFormNew);
      on($("fmSalvar"), "click", saveForm);
      on($("exConfirmar"), "click", confirmExcluir);

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
        if ($("status")) $("status").value = "TODOS";
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
        state.filtered = CLIENTES_DATA.slice().sort((a, b) => b.id - a.id);
        renderAll();
      }

      init();
    });
  </script>

</body>
</html>