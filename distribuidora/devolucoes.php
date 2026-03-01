<!DOCTYPE html>
<html lang="pt-BR">

<head>
  <meta charset="UTF-8" />
  <meta http-equiv="X-UA-Compatible" content="IE=edge" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />

  <link rel="shortcut icon" href="assets/images/favicon.svg" type="image/x-icon" />
  <title>Painel da Distribuidora | Devoluções</title>

  <!-- ========== CSS ========= -->
  <link rel="stylesheet" href="assets/css/bootstrap.min.css" />
  <link rel="stylesheet" href="assets/css/lineicons.css" rel="stylesheet" type="text/css" />
  <link rel="stylesheet" href="assets/css/materialdesignicons.min.css" rel="stylesheet" type="text/css" />
  <link rel="stylesheet" href="assets/css/main.css" />

  <style>
    /* dropdown do profile: largura acompanha conteúdo */
    .profile-box .dropdown-menu {
      width: max-content;
      min-width: 260px;
      max-width: calc(100vw - 24px);
    }

    .profile-box .dropdown-menu .author-info {
      width: max-content;
      max-width: 100%;
      display: flex !important;
      align-items: center;
      gap: 10px;
    }

    .profile-box .dropdown-menu .author-info .content {
      min-width: 0;
      max-width: 100%;
    }

    .profile-box .dropdown-menu .author-info .content a {
      display: inline-block;
      white-space: nowrap;
      max-width: 100%;
    }

    /* Botões compactos */
    .main-btn.btn-compact {
      height: 38px !important;
      padding: 8px 14px !important;
      font-size: 13px !important;
      line-height: 1 !important;
    }

    .main-btn.btn-compact i {
      font-size: 14px;
      vertical-align: -1px;
    }

    .icon-btn {
      height: 34px !important;
      width: 42px !important;
      padding: 0 !important;
      display: inline-flex !important;
      align-items: center !important;
      justify-content: center !important;
    }

    .form-control.compact,
    .form-select.compact {
      height: 38px;
      padding: 8px 12px;
      font-size: 13px;
    }

    .cardx {
      border: 1px solid rgba(148, 163, 184, .28);
      border-radius: 16px;
      background: #fff;
      overflow: hidden;
    }

    .cardx .head {
      padding: 12px 14px;
      border-bottom: 1px solid rgba(148, 163, 184, .22);
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
      border: 1px solid rgba(148, 163, 184, .25);
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
      border-color: rgba(245, 158, 11, .28);
      background: rgba(255, 251, 235, .9);
      color: #92400e;
    }

    .pill.bad {
      border-color: rgba(239, 68, 68, .25);
      background: rgba(254, 242, 242, .9);
      color: #991b1b;
    }

    .table td,
    .table th {
      vertical-align: middle;
    }

    .table-responsive {
      -webkit-overflow-scrolling: touch;
    }

    #tbDev {
      width: 100%;
      min-width: 1080px;
    }

    #tbDev th,
    #tbDev td {
      white-space: nowrap !important;
    }

    .mini {
      font-size: 12px;
      color: #475569;
      font-weight: 800;
    }

    .money {
      font-weight: 1000;
      color: #0b5ed7;
    }

    .box-tot {
      border: 1px solid rgba(148, 163, 184, .25);
      border-radius: 14px;
      background: #fff;
      padding: 12px;
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

    .tot-row:last-child {
      margin-bottom: 0;
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
      margin-top: 4px;
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
    }

    .chip-toggle {
      display: flex;
      gap: 10px;
      flex-wrap: wrap;
    }

    .chip {
      border: 1px solid rgba(148, 163, 184, .35);
      border-radius: 999px;
      padding: 8px 12px;
      cursor: pointer;
      font-weight: 900;
      font-size: 12px;
      user-select: none;
      background: #fff;
    }

    .chip.active {
      background: rgba(239, 246, 255, .75);
      border-color: rgba(37, 99, 235, .55);
      outline: 2px solid rgba(37, 99, 235, .25);
    }

    .reason-box {
      border: 1px solid rgba(148, 163, 184, .25);
      border-radius: 14px;
      padding: 10px 12px;
      background: rgba(248, 250, 252, .7);
    }

    .badge-soft {
      font-weight: 1000;
      border-radius: 999px;
      padding: 6px 10px;
      font-size: 11px;
    }

    .b-open {
      background: rgba(255, 251, 235, .95);
      color: #92400e;
      border: 1px solid rgba(245, 158, 11, .25);
    }

    .b-done {
      background: rgba(240, 253, 244, .95);
      color: #166534;
      border: 1px solid rgba(34, 197, 94, .25);
    }

    .b-cancel {
      background: rgba(254, 242, 242, .95);
      color: #991b1b;
      border: 1px solid rgba(239, 68, 68, .25);
    }

    @media (max-width: 991.98px) {
      #tbDev {
        min-width: 980px;
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

  <!-- ======== sidebar-nav start =========== -->
  <aside class="sidebar-nav-wrapper">
    <div class="navbar-logo">
      <a href="dashboard.php" class="d-flex align-items-center gap-2">
        <img src="assets/images/logo/logo.svg" alt="logo" />
      </a>
    </div>

    <nav class="sidebar-nav">
      <ul>
        <!-- Dashboard -->
        <li class="nav-item">
          <a href="dashboard.php">
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

        <!-- Operações -->
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
            <li><a href="pedidos.php">Pedidos</a></li>
            <li><a href="vendas.php">Vendas</a></li>
            <li><a href="devolucoes.php" class="active">Devoluções</a></li>
          </ul>
        </li>

        <!-- Estoque -->
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
            <li><a href="produtos.php">Produtos</a></li>
            <li><a href="inventario.php">Inventário</a></li>
            <li><a href="entradas.php">Entradas</a></li>
            <li><a href="saidas.php">Saídas</a></li>
            <li><a href="estoque-minimo.php">Estoque Mínimo</a></li>
          </ul>
        </li>

        <!-- Cadastros -->
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
            <li><a href="clientes.php">Clientes</a></li>
            <li><a href="fornecedores.php">Fornecedores</a></li>
            <li><a href="categorias.php">Categorias</a></li>
          </ul>
        </li>

        <li class="nav-item">
          <a href="relatorios.php">
            <span class="icon">
              <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path
                  d="M4.16666 3.33335C4.16666 2.41288 4.91285 1.66669 5.83332 1.66669H14.1667C15.0872 1.66669 15.8333 2.41288 15.8333 3.33335V16.6667C15.8333 17.5872 15.0872 18.3334 14.1667 18.3334H5.83332C4.91285 18.3334 4.16666 17.5872 4.16666 16.6667V3.33335Z" />
              </svg>
            </span>
            <span class="text">Relatórios</span>
          </a>
        </li>

        <span class="divider"><hr /></span>

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
            <li><a href="usuarios.php">Usuários e Permissões</a></li>
            <li><a href="parametros.php">Parâmetros do Sistema</a></li>
          </ul>
        </li>

        <li class="nav-item">
          <a href="suporte.php">
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

  <div class="overlay"></div>

  <main class="main-wrapper">
    <header class="header">
      <div class="container-fluid">
        <div class="row">
          <div class="col-lg-5 col-md-5 col-6">
            <div class="header-left d-flex align-items-center">
              <div class="menu-toggle-btn mr-15">
                <button id="menu-toggle" class="main-btn primary-btn btn-hover btn-compact" type="button">
                  <i class="lni lni-chevron-left me-2"></i> Menu
                </button>
              </div>
              <div class="header-search d-none d-md-flex">
                <form action="#">
                  <input type="text" placeholder="Buscar devolução..." id="qGlobal" />
                  <button type="submit" onclick="return false"><i class="lni lni-search-alt"></i></button>
                </form>
              </div>
            </div>
          </div>

          <div class="col-lg-7 col-md-7 col-6">
            <div class="header-right">
              <div class="profile-box ml-15">
                <button class="dropdown-toggle bg-transparent border-0" type="button" id="profile"
                  data-bs-toggle="dropdown" aria-expanded="false">
                  <div class="profile-info">
                    <div class="info">
                      <div class="image">
                        <img src="assets/images/profile/profile-image.png" alt="perfil" />
                      </div>
                      <div>
                        <h6 class="fw-500">Administrador</h6>
                        <p>Distribuidora</p>
                      </div>
                    </div>
                  </div>
                </button>

                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="profile">
                  <li>
                    <div class="author-info flex items-center !p-1">
                      <div class="image">
                        <img src="assets/images/profile/profile-image.png" alt="image" />
                      </div>
                      <div class="content">
                        <h4 class="text-sm">Administrador</h4>
                        <a class="text-black/40 dark:text-white/40 hover:text-black dark:hover:text-white text-xs"
                          href="#">Admin</a>
                      </div>
                    </div>
                  </li>
                  <li class="divider"></li>
                  <li><a href="perfil.php"><i class="lni lni-user"></i> Meu Perfil</a></li>
                  <li><a href="usuarios.php"><i class="lni lni-cog"></i> Usuários</a></li>
                  <li class="divider"></li>
                  <li><a href="logout.php"><i class="lni lni-exit"></i> Sair</a></li>
                </ul>
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
            <div class="col-md-8">
              <div class="title">
                <h2>Devoluções</h2>
                <div class="muted">Registro e controle de devoluções • <b>F2</b> salvar | <b>F4</b> focar na busca</div>
              </div>
            </div>
            <div class="col-md-4 text-md-end">
              <button class="main-btn primary-btn btn-hover btn-compact" id="btnNova" type="button">
                <i class="lni lni-plus me-1"></i> Nova devolução
              </button>
            </div>
          </div>
        </div>

        <div class="row g-3 mb-30">
          <!-- Form -->
          <div class="col-12 col-lg-4">
            <div class="cardx">
              <div class="head">
                <div style="font-weight:1000;color:#0f172a;">
                  <i class="lni lni-package me-1"></i> Lançamento
                </div>
                <span class="pill warn" id="formMode"><i class="lni lni-pencil"></i> NOVO</span>
              </div>
              <div class="body">
                <input type="hidden" id="dId" />

                <div class="mb-3">
                  <label class="form-label">Venda (Nº)</label>
                  <input class="form-control compact" id="dVendaNo" placeholder="Ex: 18 (opcional)" />
                  <div class="muted mt-1">Se informado, a devolução fica vinculada ao cupom/venda.</div>
                </div>

                <div class="mb-3">
                  <label class="form-label">Cliente</label>
                  <input class="form-control compact" id="dCliente" placeholder="CPF ou Nome (opcional)" />
                </div>

                <div class="row g-2">
                  <div class="col-6 mb-3">
                    <label class="form-label">Data</label>
                    <input class="form-control compact" id="dData" type="date" />
                  </div>
                  <div class="col-6 mb-3">
                    <label class="form-label">Hora</label>
                    <input class="form-control compact" id="dHora" type="time" />
                  </div>
                </div>

                <div class="mb-3">
                  <label class="form-label">Tipo</label>
                  <div class="chip-toggle">
                    <div class="chip active" id="chipTotal">Devolução Total</div>
                    <div class="chip" id="chipParcial">Parcial</div>
                  </div>
                </div>

                <div class="mb-3">
                  <label class="form-label">Produto (se parcial)</label>
                  <input class="form-control compact" id="dProduto" placeholder="Nome / Código do produto" />
                  <div class="muted mt-1">Preencha somente para devolução parcial.</div>
                </div>

                <div class="row g-2">
                  <div class="col-6 mb-3">
                    <label class="form-label">Qtd</label>
                    <input class="form-control compact" id="dQtd" type="number" min="1" value="1" />
                  </div>
                  <div class="col-6 mb-3">
                    <label class="form-label">Valor (R$)</label>
                    <input class="form-control compact" id="dValor" placeholder="0,00" value="0,00" />
                  </div>
                </div>

                <div class="mb-3">
                  <label class="form-label">Motivo</label>
                  <div class="reason-box">
                    <select class="form-select compact mb-2" id="dMotivo">
                      <option value="DEFEITO">Defeito</option>
                      <option value="TROCA">Troca</option>
                      <option value="ARREPENDIMENTO">Arrependimento</option>
                      <option value="AVARIA_TRANSPORTE">Avaria no Transporte</option>
                      <option value="OUTRO" selected>Outro</option>
                    </select>
                    <input class="form-control compact" id="dObs" placeholder="Observação (opcional)" />
                  </div>
                </div>

                <div class="mb-3">
                  <label class="form-label">Status</label>
                  <select class="form-select compact" id="dStatus">
                    <option value="ABERTO" selected>Em aberto</option>
                    <option value="CONCLUIDO">Concluído</option>
                    <option value="CANCELADO">Cancelado</option>
                  </select>
                </div>

                <div class="d-grid gap-2">
                  <button class="main-btn primary-btn btn-hover btn-compact" id="btnSalvar" type="button">
                    <i class="lni lni-save me-1"></i> Salvar (F2)
                  </button>
                  <button class="main-btn light-btn btn-hover btn-compact" id="btnLimpar" type="button">
                    <i class="lni lni-eraser me-1"></i> Limpar
                  </button>
                </div>
              </div>
            </div>

            <div class="cardx mt-3">
              <div class="head">
                <div style="font-weight:1000;color:#0f172a;"><i class="lni lni-stats-up me-1"></i> Resumo</div>
              </div>
              <div class="body">
                <div class="box-tot">
                  <div class="tot-row"><span>Total em aberto</span><span class="money" id="tAberto">R$ 0,00</span></div>
                  <div class="tot-row"><span>Total concluído</span><span class="money" id="tConcl">R$ 0,00</span></div>
                  <div class="tot-row"><span>Total cancelado</span><span class="money" id="tCancel">R$ 0,00</span></div>
                  <div class="tot-hr"></div>
                  <div class="grand">
                    <span class="lbl">TOTAL (geral)</span>
                    <span class="val" id="tGeral">R$ 0,00</span>
                  </div>
                </div>
                <div class="muted mt-2">* Somatório baseado no campo “Valor (R$)” das devoluções.</div>
              </div>
            </div>
          </div>

          <!-- Lista -->
          <div class="col-12 col-lg-8">
            <div class="cardx">
              <div class="head">
                <div style="font-weight:1000;color:#0f172a;">
                  <i class="lni lni-list me-1"></i> Listagem
                </div>
                <div class="d-flex gap-2 flex-wrap align-items-center">
                  <input class="form-control compact" id="qDev" placeholder="Buscar: venda, cliente, produto, motivo..." style="min-width:260px;" />
                  <select class="form-select compact" id="fStatus" style="min-width:180px;">
                    <option value="">Todos</option>
                    <option value="ABERTO">Em aberto</option>
                    <option value="CONCLUIDO">Concluído</option>
                    <option value="CANCELADO">Cancelado</option>
                  </select>
                  <button class="main-btn light-btn btn-hover btn-compact" id="btnExport" type="button">
                    <i class="lni lni-download me-1"></i> Exportar JSON
                  </button>
                  <button class="main-btn light-btn btn-hover btn-compact" id="btnImport" type="button">
                    <i class="lni lni-upload me-1"></i> Importar JSON
                  </button>
                  <input type="file" id="fileImport" accept="application/json" style="display:none;" />
                </div>
              </div>

              <div class="body">
                <div class="table-responsive">
                  <table class="table text-nowrap" id="tbDev">
                    <thead>
                      <tr>
                        <th style="min-width:80px;">ID</th>
                        <th style="min-width:140px;">Data/Hora</th>
                        <th style="min-width:120px;">Venda</th>
                        <th style="min-width:200px;">Cliente</th>
                        <th style="min-width:180px;">Tipo</th>
                        <th style="min-width:240px;">Produto</th>
                        <th style="min-width:100px;" class="text-center">Qtd</th>
                        <th style="min-width:140px;" class="text-end">Valor</th>
                        <th style="min-width:160px;">Motivo</th>
                        <th style="min-width:150px;" class="text-center">Status</th>
                        <th style="min-width:170px;" class="text-center">Ações</th>
                      </tr>
                    </thead>
                    <tbody id="tbodyDev"></tbody>
                  </table>
                </div>

                <div class="muted mt-2" id="hintNone" style="display:none;">Nenhuma devolução encontrada.</div>
              </div>
            </div>
          </div>

        </div>
      </div>
    </section>

    <footer class="footer">
      <div class="container-fluid">
        <div class="row">
          <div class="col-md-6 order-last order-md-first">
            <div class="copyright text-center text-md-start">
              <p class="text-sm">Painel da Distribuidora • <span class="text-gray">v1.0</span></p>
            </div>
          </div>
        </div>
      </div>
    </footer>
  </main>

  <!-- ========= JS ========= -->
  <script src="assets/js/bootstrap.bundle.min.js"></script>
  <script src="assets/js/main.js"></script>

  <script>
    // ==============================
    // Storage
    // ==============================
    const LS_DEV = "dist_devolucoes_v1";

    function safeText(s) {
      return String(s ?? "")
        .replaceAll("&", "&amp;")
        .replaceAll("<", "&lt;")
        .replaceAll(">", "&gt;")
        .replaceAll('"', "&quot;")
        .replaceAll("'", "&#039;");
    }

    function moneyToNumber(txt) {
      let s = String(txt ?? "").trim();
      if (!s) return 0;
      s = s.replace(/[^\d,.-]/g, "").replace(/\./g, "").replace(",", ".");
      const n = Number(s);
      return isNaN(n) ? 0 : n;
    }

    function numberToMoney(n) {
      const v = Number(n || 0);
      return "R$ " + v.toFixed(2).replace(".", ",");
    }

    function loadJson(key, fallback) {
      try {
        const raw = localStorage.getItem(key);
        return raw ? JSON.parse(raw) : fallback;
      } catch {
        return fallback;
      }
    }

    function saveJson(key, val) {
      localStorage.setItem(key, JSON.stringify(val));
    }

    function pad2(n) { return String(n).padStart(2, "0"); }

    function nowISODate() {
      const d = new Date();
      return `${d.getFullYear()}-${pad2(d.getMonth() + 1)}-${pad2(d.getDate())}`;
    }

    function nowISOTime() {
      const d = new Date();
      return `${pad2(d.getHours())}:${pad2(d.getMinutes())}`;
    }

    function fmtBRDateTime(dateISO, timeISO) {
      if (!dateISO) return "";
      const [y, m, d] = String(dateISO).split("-");
      const t = (timeISO || "00:00");
      return `${d}/${m}/${y} ${t}`;
    }

    function uuidShort() {
      return "D" + Math.random().toString(16).slice(2, 8).toUpperCase() + Date.now().toString().slice(-4);
    }

    // ==============================
    // State
    // ==============================
    let DEV = [];
    let TYPE = "TOTAL"; // TOTAL | PARCIAL

    // ==============================
    // DOM
    // ==============================
    const qGlobal = document.getElementById("qGlobal");

    const btnNova = document.getElementById("btnNova");
    const btnSalvar = document.getElementById("btnSalvar");
    const btnLimpar = document.getElementById("btnLimpar");

    const dId = document.getElementById("dId");
    const dVendaNo = document.getElementById("dVendaNo");
    const dCliente = document.getElementById("dCliente");
    const dData = document.getElementById("dData");
    const dHora = document.getElementById("dHora");
    const dProduto = document.getElementById("dProduto");
    const dQtd = document.getElementById("dQtd");
    const dValor = document.getElementById("dValor");
    const dMotivo = document.getElementById("dMotivo");
    const dObs = document.getElementById("dObs");
    const dStatus = document.getElementById("dStatus");

    const chipTotal = document.getElementById("chipTotal");
    const chipParcial = document.getElementById("chipParcial");
    const formMode = document.getElementById("formMode");

    const qDev = document.getElementById("qDev");
    const fStatus = document.getElementById("fStatus");
    const tbodyDev = document.getElementById("tbodyDev");
    const hintNone = document.getElementById("hintNone");

    const tAberto = document.getElementById("tAberto");
    const tConcl = document.getElementById("tConcl");
    const tCancel = document.getElementById("tCancel");
    const tGeral = document.getElementById("tGeral");

    const btnExport = document.getElementById("btnExport");
    const btnImport = document.getElementById("btnImport");
    const fileImport = document.getElementById("fileImport");

    // ==============================
    // Helpers
    // ==============================
    function setType(type) {
      TYPE = type;
      const isTotal = type === "TOTAL";
      chipTotal.classList.toggle("active", isTotal);
      chipParcial.classList.toggle("active", !isTotal);

      // Produto/Qtd só fazem sentido no parcial
      dProduto.disabled = isTotal;
      dQtd.disabled = isTotal;

      if (isTotal) {
        dProduto.value = "";
        dQtd.value = 1;
      }
    }

    function setFormMode(mode) {
      // NEW | EDIT
      if (mode === "EDIT") {
        formMode.className = "pill ok";
        formMode.innerHTML = `<i class="lni lni-checkmark-circle"></i> EDITANDO`;
      } else {
        formMode.className = "pill warn";
        formMode.innerHTML = `<i class="lni lni-pencil"></i> NOVO`;
      }
    }

    function resetForm() {
      dId.value = "";
      dVendaNo.value = "";
      dCliente.value = "";
      dData.value = nowISODate();
      dHora.value = nowISOTime();
      setType("TOTAL");
      dProduto.value = "";
      dQtd.value = 1;
      dValor.value = "0,00";
      dMotivo.value = "OUTRO";
      dObs.value = "";
      dStatus.value = "ABERTO";
      setFormMode("NEW");
    }

    function badgeStatus(s) {
      if (s === "CONCLUIDO") return `<span class="badge-soft b-done">CONCLUÍDO</span>`;
      if (s === "CANCELADO") return `<span class="badge-soft b-cancel">CANCELADO</span>`;
      return `<span class="badge-soft b-open">EM ABERTO</span>`;
    }

    function typeLabel(t) {
      return t === "PARCIAL" ? "PARCIAL" : "TOTAL";
    }

    function motivoLabel(m) {
      const map = {
        "DEFEITO": "Defeito",
        "TROCA": "Troca",
        "ARREPENDIMENTO": "Arrependimento",
        "AVARIA_TRANSPORTE": "Avaria Transporte",
        "OUTRO": "Outro"
      };
      return map[m] || m || "-";
    }

    function getFiltered() {
      const q = (qDev.value || qGlobal.value || "").toLowerCase().trim();
      const st = fStatus.value;

      return DEV.filter(x => {
        if (st && x.status !== st) return false;
        if (!q) return true;
        const blob = [
          x.id, x.saleNo, x.customer, x.type, x.product, x.reason, x.note, x.status,
          fmtBRDateTime(x.date, x.time),
          numberToMoney(x.amount),
          String(x.qty || "")
        ].join(" ").toLowerCase();
        return blob.includes(q);
      });
    }

    function recalcTotals() {
      let aberto = 0, concl = 0, cancel = 0, geral = 0;
      DEV.forEach(x => {
        const v = Number(x.amount || 0);
        geral += v;
        if (x.status === "CONCLUIDO") concl += v;
        else if (x.status === "CANCELADO") cancel += v;
        else aberto += v;
      });

      tAberto.textContent = numberToMoney(aberto);
      tConcl.textContent = numberToMoney(concl);
      tCancel.textContent = numberToMoney(cancel);
      tGeral.textContent = numberToMoney(geral);
    }

    // ==============================
    // Render
    // ==============================
    function render() {
      const rows = getFiltered();
      tbodyDev.innerHTML = "";

      hintNone.style.display = rows.length ? "none" : "block";

      rows.forEach((x) => {
        const dt = fmtBRDateTime(x.date, x.time);
        const sale = x.saleNo ? `#${safeText(x.saleNo)}` : "—";
        const cust = x.customer ? safeText(x.customer) : "Consumidor Final";
        const prod = x.type === "PARCIAL" ? (x.product ? safeText(x.product) : "—") : "—";
        const qty = x.type === "PARCIAL" ? Number(x.qty || 1) : "—";
        const motivo = motivoLabel(x.reason);
        const valor = numberToMoney(x.amount);

        tbodyDev.insertAdjacentHTML("beforeend", `
          <tr data-id="${safeText(x.id)}">
            <td><span class="mini">${safeText(x.id)}</span></td>
            <td>${safeText(dt)}</td>
            <td>${sale}</td>
            <td>${cust}</td>
            <td><span class="pill ${x.type === "PARCIAL" ? "warn" : "ok"}">${typeLabel(x.type)}</span></td>
            <td>${prod}</td>
            <td class="text-center">${qty}</td>
            <td class="text-end"><span class="money">${valor}</span></td>
            <td>${safeText(motivo)}${x.note ? `<div class="muted" style="max-width:260px;white-space:normal;">${safeText(x.note)}</div>` : ""}</td>
            <td class="text-center">${badgeStatus(x.status)}</td>
            <td class="text-center">
              <button class="main-btn light-btn btn-hover btn-compact icon-btn btnEdit" type="button" title="Editar">
                <i class="lni lni-pencil"></i>
              </button>
              <button class="main-btn danger-btn-outline btn-hover btn-compact icon-btn btnDel" type="button" title="Excluir">
                <i class="lni lni-trash-can"></i>
              </button>
            </td>
          </tr>
        `);
      });

      recalcTotals();
    }

    // ==============================
    // CRUD
    // ==============================
    function validateForm() {
      const date = String(dData.value || "").trim();
      const time = String(dHora.value || "").trim();
      if (!date) return { ok: false, msg: "Informe a data." };
      if (!time) return { ok: false, msg: "Informe a hora." };

      const amt = moneyToNumber(dValor.value);
      if (amt <= 0) return { ok: false, msg: "Informe um valor (R$) maior que zero." };

      if (TYPE === "PARCIAL") {
        const prod = String(dProduto.value || "").trim();
        if (!prod) return { ok: false, msg: "Informe o produto para devolução parcial." };
        const q = Number(dQtd.value || 0);
        if (!q || q < 1) return { ok: false, msg: "Informe a quantidade (mín. 1)." };
      }
      return { ok: true };
    }

    function saveDev() {
      const v = validateForm();
      if (!v.ok) { alert(v.msg); return; }

      const isEdit = !!dId.value;
      const obj = {
        id: isEdit ? dId.value : uuidShort(),
        saleNo: String(dVendaNo.value || "").trim(),
        customer: String(dCliente.value || "").trim(),
        date: String(dData.value || "").trim(),
        time: String(dHora.value || "").trim(),
        type: TYPE,
        product: TYPE === "PARCIAL" ? String(dProduto.value || "").trim() : "",
        qty: TYPE === "PARCIAL" ? Number(dQtd.value || 1) : null,
        amount: moneyToNumber(dValor.value),
        reason: String(dMotivo.value || "OUTRO"),
        note: String(dObs.value || "").trim(),
        status: String(dStatus.value || "ABERTO")
      };

      if (isEdit) {
        const idx = DEV.findIndex(x => x.id === obj.id);
        if (idx >= 0) DEV[idx] = obj;
      } else {
        DEV.unshift(obj);
      }

      saveJson(LS_DEV, DEV);
      render();
      resetForm();
      alert(isEdit ? "Devolução atualizada!" : "Devolução registrada!");
    }

    function editDev(id) {
      const x = DEV.find(d => d.id === id);
      if (!x) return;

      dId.value = x.id;
      dVendaNo.value = x.saleNo || "";
      dCliente.value = x.customer || "";
      dData.value = x.date || nowISODate();
      dHora.value = x.time || nowISOTime();
      setType(x.type || "TOTAL");
      dProduto.value = x.product || "";
      dQtd.value = x.qty || 1;
      dValor.value = (Number(x.amount || 0)).toFixed(2).replace(".", ",");
      dMotivo.value = x.reason || "OUTRO";
      dObs.value = x.note || "";
      dStatus.value = x.status || "ABERTO";

      setFormMode("EDIT");
      window.scrollTo({ top: 0, behavior: "smooth" });
    }

    function deleteDev(id) {
      const x = DEV.find(d => d.id === id);
      if (!x) return;
      if (!confirm(`Excluir devolução ${id}?`)) return;
      DEV = DEV.filter(d => d.id !== id);
      saveJson(LS_DEV, DEV);
      render();
      resetForm();
    }

    // ==============================
    // Export / Import
    // ==============================
    function exportJson() {
      const data = {
        exported_at: new Date().toISOString(),
        items: DEV
      };
      const blob = new Blob([JSON.stringify(data, null, 2)], { type: "application/json;charset=utf-8" });
      const url = URL.createObjectURL(blob);
      const a = document.createElement("a");
      a.href = url;
      a.download = "devolucoes.json";
      document.body.appendChild(a);
      a.click();
      a.remove();
      URL.revokeObjectURL(url);
    }

    function importJson(file) {
      const reader = new FileReader();
      reader.onload = () => {
        try {
          const obj = JSON.parse(reader.result);
          const items = Array.isArray(obj) ? obj : (obj.items || []);
          if (!Array.isArray(items)) throw new Error("Formato inválido.");

          // normaliza
          const norm = items.map(x => ({
            id: String(x.id || uuidShort()),
            saleNo: String(x.saleNo || x.vendaNo || ""),
            customer: String(x.customer || x.cliente || ""),
            date: String(x.date || x.data || nowISODate()),
            time: String(x.time || x.hora || nowISOTime()),
            type: (String(x.type || x.tipo || "TOTAL").toUpperCase() === "PARCIAL") ? "PARCIAL" : "TOTAL",
            product: String(x.product || x.produto || ""),
            qty: (x.qty == null ? null : Number(x.qty || 1)),
            amount: Number(x.amount || x.valor || 0) || 0,
            reason: String(x.reason || x.motivo || "OUTRO"),
            note: String(x.note || x.obs || ""),
            status: String(x.status || x.status_dev || "ABERTO").toUpperCase()
          }));

          DEV = norm.concat(DEV).slice(0, 500); // guarda até 500
          saveJson(LS_DEV, DEV);
          render();
          alert("Importação concluída!");
        } catch (e) {
          alert("Falha ao importar JSON: " + (e.message || e));
        }
      };
      reader.readAsText(file);
    }

    // ==============================
    // Events
    // ==============================
    btnNova.addEventListener("click", () => resetForm());
    btnSalvar.addEventListener("click", saveDev);
    btnLimpar.addEventListener("click", resetForm);

    chipTotal.addEventListener("click", () => setType("TOTAL"));
    chipParcial.addEventListener("click", () => setType("PARCIAL"));

    qDev.addEventListener("input", render);
    fStatus.addEventListener("change", render);
    qGlobal.addEventListener("input", () => { qDev.value = qGlobal.value; render(); });

    tbodyDev.addEventListener("click", (e) => {
      const tr = e.target.closest("tr");
      if (!tr) return;
      const id = tr.getAttribute("data-id");
      if (!id) return;

      if (e.target.closest(".btnEdit")) return editDev(id);
      if (e.target.closest(".btnDel")) return deleteDev(id);
    });

    btnExport.addEventListener("click", exportJson);
    btnImport.addEventListener("click", () => fileImport.click());
    fileImport.addEventListener("change", () => {
      const f = fileImport.files && fileImport.files[0];
      if (f) importJson(f);
      fileImport.value = "";
    });

    // atalhos
    document.addEventListener("keydown", (e) => {
      if (e.key === "F2") {
        e.preventDefault();
        saveDev();
      }
      if (e.key === "F4") {
        e.preventDefault();
        qDev.focus();
      }
    });

    // ==============================
    // Init
    // ==============================
    function init() {
      DEV = loadJson(LS_DEV, []);
      resetForm();
      render();
    }
    init();
  </script>
</body>

</html>