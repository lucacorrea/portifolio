<!DOCTYPE html>
<html lang="pt-BR">

<head>
  <meta charset="UTF-8" />
  <meta http-equiv="X-UA-Compatible" content="IE=edge" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />

  <link rel="shortcut icon" href="assets/images/favicon.svg" type="image/x-icon" />
  <title>Painel da Distribuidora | Fornecedores</title>

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

    .muted {
      font-size: 12px;
      color: #64748b;
    }

    /* Cards */
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

    /* Tabela */
    .table td,
    .table th {
      vertical-align: middle;
    }

    .table-responsive {
      -webkit-overflow-scrolling: touch;
    }

    #tbFor {
      width: 100%;
      min-width: 980px;
    }

    #tbFor th,
    #tbFor td {
      white-space: nowrap !important;
    }

    .badge-soft {
      border: 1px solid rgba(148, 163, 184, .30);
      background: rgba(248, 250, 252, .8);
      padding: 5px 10px;
      border-radius: 999px;
      font-size: 12px;
      font-weight: 900;
      color: #0f172a;
    }

    .badge-ok {
      border-color: rgba(34, 197, 94, .22);
      background: rgba(240, 253, 244, .9);
      color: #166534;
    }

    .badge-off {
      border-color: rgba(239, 68, 68, .22);
      background: rgba(254, 242, 242, .9);
      color: #991b1b;
    }

    .link-mini {
      font-weight: 900;
      font-size: 12px;
      text-decoration: none;
    }

    .link-mini:hover {
      text-decoration: underline;
    }

    /* Modal */
    .modal-content {
      border-radius: 16px;
      overflow: hidden;
    }

    .modal-header {
      border-bottom: 1px solid rgba(148, 163, 184, .22);
    }

    .modal-footer {
      border-top: 1px solid rgba(148, 163, 184, .22);
    }

    @media (max-width: 991.98px) {
      #tbFor {
        min-width: 900px;
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
      <a href="index.html" class="d-flex align-items-center gap-2">
        <img src="assets/images/logo/logo.svg" alt="logo" />
      </a>
    </div>

    <nav class="sidebar-nav">
      <ul>
        <!-- Dashboard -->
        <li class="nav-item">
          <a href="index.html">
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
        <li class="nav-item nav-item-has-children">
          <a href="#0" class="collapsed" data-bs-toggle="collapse" data-bs-target="#ddmenu_operacoes"
            aria-controls="ddmenu_operacoes" aria-expanded="false">
            <span class="icon">
              <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path
                  d="M3.33334 3.35442C3.33334 2.4223 4.07954 1.66666 5.00001 1.66666H15C15.9205 1.66666 16.6667 2.4223 16.6667 3.35442V16.8565C16.6667 17.5519 15.8827 17.9489 15.3333 17.5317L13.8333 16.3924C13.537 16.1673 13.1297 16.1673 12.8333 16.3924L10.5 18.1646C10.2037 18.3896 9.79634 18.3896 9.50001 18.1646L7.16668 16.3924C6.87038 16.1673 6.46298 16.1673 6.16668 16.3924L4.66668 17.5317C4.11731 17.9489 3.33334 17.5519 3.33334 16.8565V3.35442Z" />
              </svg>
            </span>
            <span class="text">Operações</span>
          </a>
          <ul id="ddmenu_operacoes" class="collapse dropdown-nav">
            <li><a href="pedidos.html">Pedidos</a></li>
            <li><a href="vendas.html">Vendas</a></li>
            <li><a href="devolucoes.html">Devoluções</a></li>
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
            <li><a href="produtos.html">Produtos</a></li>
            <li><a href="inventario.html">Inventário</a></li>
            <li><a href="entradas.html">Entradas</a></li>
            <li><a href="saidas.html">Saídas</a></li>
            <li><a href="estoque-minimo.html">Estoque Mínimo</a></li>
          </ul>
        </li>

        <!-- Cadastros (ativo) -->
        <li class="nav-item nav-item-has-children active">
          <a href="#0" data-bs-toggle="collapse" data-bs-target="#ddmenu_cadastros" aria-controls="ddmenu_cadastros"
            aria-expanded="true">
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
          <ul id="ddmenu_cadastros" class="collapse show dropdown-nav">
            <li><a href="clientes.html">Clientes</a></li>
            <li><a href="fornecedores.html" class="active">Fornecedores</a></li>
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
                  <input type="text" placeholder="Buscar fornecedor..." id="qGlobal" />
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
                  <li><a href="perfil.html"><i class="lni lni-user"></i> Meu Perfil</a></li>
                  <li><a href="usuarios.html"><i class="lni lni-cog"></i> Usuários</a></li>
                  <li class="divider"></li>
                  <li><a href="logout.html"><i class="lni lni-exit"></i> Sair</a></li>
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
                <h2>Fornecedores</h2>
                <div class="muted">Cadastro simples em LocalStorage (pode trocar depois para banco).</div>
              </div>
            </div>
            <div class="col-md-4 text-md-end">
              <button class="main-btn primary-btn btn-hover btn-compact" id="btnNovo" type="button"
                data-bs-toggle="modal" data-bs-target="#mdFornecedor">
                <i class="lni lni-plus me-1"></i> Novo fornecedor
              </button>
            </div>
          </div>
        </div>

        <!-- LISTA -->
        <div class="cardx mb-30">
          <div class="head">
            <div class="d-flex align-items-center gap-2 flex-wrap">
              <div style="font-weight:1000;color:#0f172a;"><i class="lni lni-users me-1"></i> Lista</div>
              <span class="badge-soft" id="countBadge">0 fornecedores</span>
            </div>

            <div class="d-flex gap-2 flex-wrap align-items-center">
              <select class="form-select compact" id="fStatus" style="min-width: 160px;">
                <option value="">Status: Todos</option>
                <option value="ATIVO">Ativo</option>
                <option value="INATIVO">Inativo</option>
              </select>

              <button class="main-btn light-btn btn-hover btn-compact" id="btnExport" type="button">
                <i class="lni lni-download me-1"></i> Exportar (JSON)
              </button>

              <label class="main-btn light-btn btn-hover btn-compact" style="margin:0; cursor:pointer;">
                <i class="lni lni-upload me-1"></i> Importar
                <input type="file" id="fileImport" accept="application/json" hidden />
              </label>

              <button class="main-btn light-btn btn-hover btn-compact" id="btnLimpar" type="button">
                <i class="lni lni-eraser me-1"></i> Limpar
              </button>
            </div>
          </div>

          <div class="body">
            <div class="row g-2 mb-2">
              <div class="col-12 col-lg-6">
                <input class="form-control compact" id="qFor" placeholder="Buscar por nome, CNPJ/CPF, telefone, e-mail..." />
              </div>
              <div class="col-12 col-lg-6 text-lg-end">
                <div class="muted">Atalho: <b>Enter</b> para buscar • Clique em <b>Editar</b> para alterar.</div>
              </div>
            </div>

            <div class="table-responsive">
              <table class="table text-nowrap" id="tbFor">
                <thead>
                  <tr>
                    <th style="min-width:80px;">ID</th>
                    <th style="min-width:260px;">Fornecedor</th>
                    <th style="min-width:150px;">Documento</th>
                    <th style="min-width:160px;">Telefone</th>
                    <th style="min-width:220px;">E-mail</th>
                    <th style="min-width:240px;">Cidade/UF</th>
                    <th style="min-width:120px;" class="text-center">Status</th>
                    <th style="min-width:160px;" class="text-center">Ações</th>
                  </tr>
                </thead>
                <tbody id="tbodyFor"></tbody>
              </table>
            </div>

            <div class="muted mt-2" id="hintEmpty" style="display:none;">Nenhum fornecedor encontrado.</div>
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

  <!-- MODAL: Fornecedor -->
  <div class="modal fade" id="mdFornecedor" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <div>
            <h5 class="modal-title" id="mdTitle" style="font-weight:1000;">Novo fornecedor</h5>
            <div class="muted" id="mdSub">Preencha os dados abaixo.</div>
          </div>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
        </div>

        <div class="modal-body">
          <input type="hidden" id="fId" />

          <div class="row g-2">
            <div class="col-12 col-lg-8">
              <label class="form-label">Nome / Razão Social *</label>
              <input class="form-control compact" id="fNome" placeholder="Ex: Distribuidora X LTDA" />
            </div>
            <div class="col-12 col-lg-4">
              <label class="form-label">Status</label>
              <select class="form-select compact" id="fStatusEdit">
                <option value="ATIVO" selected>Ativo</option>
                <option value="INATIVO">Inativo</option>
              </select>
            </div>

            <div class="col-12 col-lg-4">
              <label class="form-label">CNPJ/CPF</label>
              <input class="form-control compact" id="fDoc" placeholder="00.000.000/0000-00" />
            </div>
            <div class="col-12 col-lg-4">
              <label class="form-label">Telefone</label>
              <input class="form-control compact" id="fTel" placeholder="(92) 9xxxx-xxxx" />
            </div>
            <div class="col-12 col-lg-4">
              <label class="form-label">E-mail</label>
              <input class="form-control compact" id="fEmail" placeholder="contato@fornecedor.com" />
            </div>

            <div class="col-12">
              <label class="form-label">Endereço</label>
              <input class="form-control compact" id="fEnd" placeholder="Rua, nº, bairro, referência..." />
            </div>

            <div class="col-12 col-lg-6">
              <label class="form-label">Cidade</label>
              <input class="form-control compact" id="fCidade" placeholder="Coari" />
            </div>
            <div class="col-12 col-lg-2">
              <label class="form-label">UF</label>
              <input class="form-control compact" id="fUF" placeholder="AM" maxlength="2" />
            </div>
            <div class="col-12 col-lg-4">
              <label class="form-label">Contato (Pessoa)</label>
              <input class="form-control compact" id="fContato" placeholder="Nome do contato" />
            </div>

            <div class="col-12">
              <label class="form-label">Observação</label>
              <textarea class="form-control" id="fObs" rows="3" placeholder="Opcional..."
                style="border-radius:12px;"></textarea>
            </div>
          </div>
        </div>

        <div class="modal-footer d-flex justify-content-between">
          <button class="main-btn danger-btn-outline btn-hover btn-compact" id="btnExcluir" type="button"
            style="display:none;">
            <i class="lni lni-trash-can me-1"></i> Excluir
          </button>
          <div class="d-flex gap-2">
            <button class="main-btn light-btn btn-hover btn-compact" data-bs-dismiss="modal" type="button">
              Cancelar
            </button>
            <button class="main-btn primary-btn btn-hover btn-compact" id="btnSalvar" type="button">
              <i class="lni lni-save me-1"></i> Salvar
            </button>
          </div>
        </div>

      </div>
    </div>
  </div>

  <!-- ========= JS ========= -->
  <script src="assets/js/bootstrap.bundle.min.js"></script>
  <script src="assets/js/main.js"></script>

  <script>
    // ==============================
    // Storage
    // ==============================
    const LS_FORNECEDORES = "dist_fornecedores_v1";

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

    function safeText(s) {
      return String(s ?? "")
        .replaceAll("&", "&amp;")
        .replaceAll("<", "&lt;")
        .replaceAll(">", "&gt;")
        .replaceAll('"', "&quot;")
        .replaceAll("'", "&#039;");
    }

    function uid() {
      return "F" + Date.now().toString(36).toUpperCase() + Math.random().toString(36).slice(2, 6).toUpperCase();
    }

    function seedIfEmpty() {
      const arr = loadJson(LS_FORNECEDORES, []);
      if (Array.isArray(arr) && arr.length) return;

      const seed = [
        {
          id: uid(),
          nome: "Distribuidora Exemplo LTDA",
          doc: "00.000.000/0001-00",
          tel: "(92) 9xxxx-xxxx",
          email: "contato@exemplo.com",
          endereco: "Rua Exemplo, 100 - Centro",
          cidade: "Coari",
          uf: "AM",
          contato: "Fulano",
          obs: "",
          status: "ATIVO",
          createdAt: new Date().toISOString()
        }
      ];
      saveJson(LS_FORNECEDORES, seed);
    }

    function getAll() {
      seedIfEmpty();
      const arr = loadJson(LS_FORNECEDORES, []);
      return (arr || []).map(x => ({
        id: String(x.id || "").trim() || uid(),
        nome: String(x.nome || x.name || "").trim(),
        doc: String(x.doc || x.cnpj || x.cpf || "").trim(),
        tel: String(x.tel || x.telefone || "").trim(),
        email: String(x.email || "").trim(),
        endereco: String(x.endereco || "").trim(),
        cidade: String(x.cidade || "").trim(),
        uf: String(x.uf || "").trim().toUpperCase(),
        contato: String(x.contato || "").trim(),
        obs: String(x.obs || "").trim(),
        status: String(x.status || "ATIVO").trim().toUpperCase() === "INATIVO" ? "INATIVO" : "ATIVO",
        createdAt: x.createdAt || new Date().toISOString()
      })).filter(x => x.nome);
    }

    function setAll(list) {
      saveJson(LS_FORNECEDORES, list);
    }

    function matches(row, q) {
      const s = String(q || "").toLowerCase().trim();
      if (!s) return true;
      const blob = `${row.id} ${row.nome} ${row.doc} ${row.tel} ${row.email} ${row.endereco} ${row.cidade} ${row.uf} ${row.contato} ${row.status}`.toLowerCase();
      return blob.includes(s);
    }

    // ==============================
    // DOM
    // ==============================
    const qGlobal = document.getElementById("qGlobal");
    const qFor = document.getElementById("qFor");
    const fStatus = document.getElementById("fStatus");

    const tbodyFor = document.getElementById("tbodyFor");
    const hintEmpty = document.getElementById("hintEmpty");
    const countBadge = document.getElementById("countBadge");

    const btnNovo = document.getElementById("btnNovo");
    const btnExport = document.getElementById("btnExport");
    const btnLimpar = document.getElementById("btnLimpar");
    const fileImport = document.getElementById("fileImport");

    // modal
    const mdEl = document.getElementById("mdFornecedor");
    const md = new bootstrap.Modal(mdEl);

    const mdTitle = document.getElementById("mdTitle");
    const mdSub = document.getElementById("mdSub");

    const fId = document.getElementById("fId");
    const fNome = document.getElementById("fNome");
    const fStatusEdit = document.getElementById("fStatusEdit");
    const fDoc = document.getElementById("fDoc");
    const fTel = document.getElementById("fTel");
    const fEmail = document.getElementById("fEmail");
    const fEnd = document.getElementById("fEnd");
    const fCidade = document.getElementById("fCidade");
    const fUF = document.getElementById("fUF");
    const fContato = document.getElementById("fContato");
    const fObs = document.getElementById("fObs");

    const btnSalvar = document.getElementById("btnSalvar");
    const btnExcluir = document.getElementById("btnExcluir");

    // ==============================
    // Estado
    // ==============================
    let ALL = [];
    let VIEW = [];

    function render() {
      const q = (qFor.value || qGlobal.value || "").trim();
      const st = (fStatus.value || "").trim().toUpperCase();

      VIEW = ALL
        .filter(r => matches(r, q))
        .filter(r => st ? r.status === st : true);

      countBadge.textContent = `${VIEW.length} fornecedor(es)`;
      tbodyFor.innerHTML = "";

      if (!VIEW.length) {
        hintEmpty.style.display = "block";
        return;
      }
      hintEmpty.style.display = "none";

      VIEW.forEach((r) => {
        const statusBadge = r.status === "ATIVO"
          ? `<span class="badge-soft badge-ok">ATIVO</span>`
          : `<span class="badge-soft badge-off">INATIVO</span>`;

        const loc = [r.cidade, r.uf].filter(Boolean).join(" / ") || "—";

        tbodyFor.insertAdjacentHTML("beforeend", `
          <tr data-id="${safeText(r.id)}">
            <td style="font-weight:1000;color:#0f172a;">${safeText(r.id)}</td>
            <td>
              <div style="font-weight:1000;color:#0f172a;line-height:1.1;">${safeText(r.nome)}</div>
              <div class="muted">${safeText(r.contato || "—")}</div>
            </td>
            <td>${safeText(r.doc || "—")}</td>
            <td>${safeText(r.tel || "—")}</td>
            <td>
              ${r.email ? `<a class="link-mini" href="mailto:${safeText(r.email)}">${safeText(r.email)}</a>` : "—"}
            </td>
            <td>${safeText(loc)}</td>
            <td class="text-center">${statusBadge}</td>
            <td class="text-center">
              <button class="main-btn light-btn btn-hover btn-compact" type="button" data-act="edit">
                <i class="lni lni-pencil me-1"></i> Editar
              </button>
            </td>
          </tr>
        `);
      });
    }

    function openNew() {
      mdTitle.textContent = "Novo fornecedor";
      mdSub.textContent = "Preencha os dados abaixo.";
      btnExcluir.style.display = "none";

      fId.value = "";
      fNome.value = "";
      fStatusEdit.value = "ATIVO";
      fDoc.value = "";
      fTel.value = "";
      fEmail.value = "";
      fEnd.value = "";
      fCidade.value = "";
      fUF.value = "";
      fContato.value = "";
      fObs.value = "";

      md.show();
      setTimeout(() => fNome.focus(), 150);
    }

    function openEdit(id) {
      const r = ALL.find(x => x.id === id);
      if (!r) return;

      mdTitle.textContent = "Editar fornecedor";
      mdSub.textContent = "Altere e salve.";
      btnExcluir.style.display = "inline-flex";

      fId.value = r.id;
      fNome.value = r.nome;
      fStatusEdit.value = r.status;
      fDoc.value = r.doc || "";
      fTel.value = r.tel || "";
      fEmail.value = r.email || "";
      fEnd.value = r.endereco || "";
      fCidade.value = r.cidade || "";
      fUF.value = r.uf || "";
      fContato.value = r.contato || "";
      fObs.value = r.obs || "";

      md.show();
      setTimeout(() => fNome.focus(), 150);
    }

    function saveForm() {
      const nome = String(fNome.value || "").trim();
      if (!nome) { alert("Informe o nome / razão social."); fNome.focus(); return; }

      const data = {
        id: fId.value ? String(fId.value) : uid(),
        nome,
        status: String(fStatusEdit.value || "ATIVO").toUpperCase() === "INATIVO" ? "INATIVO" : "ATIVO",
        doc: String(fDoc.value || "").trim(),
        tel: String(fTel.value || "").trim(),
        email: String(fEmail.value || "").trim(),
        endereco: String(fEnd.value || "").trim(),
        cidade: String(fCidade.value || "").trim(),
        uf: String(fUF.value || "").trim().toUpperCase(),
        contato: String(fContato.value || "").trim(),
        obs: String(fObs.value || "").trim(),
        createdAt: new Date().toISOString()
      };

      const idx = ALL.findIndex(x => x.id === data.id);
      if (idx >= 0) {
        data.createdAt = ALL[idx].createdAt || data.createdAt;
        ALL[idx] = data;
      } else {
        ALL.unshift(data);
      }

      setAll(ALL);
      render();
      md.hide();
      alert("Fornecedor salvo!");
    }

    function removeCurrent() {
      const id = String(fId.value || "");
      if (!id) return;
      const r = ALL.find(x => x.id === id);
      if (!r) return;

      if (!confirm(`Excluir o fornecedor "${r.nome}"?`)) return;

      ALL = ALL.filter(x => x.id !== id);
      setAll(ALL);
      render();
      md.hide();
      alert("Fornecedor excluído.");
    }

    function exportJson() {
      const blob = new Blob([JSON.stringify(ALL, null, 2)], { type: "application/json;charset=utf-8" });
      const url = URL.createObjectURL(blob);
      const a = document.createElement("a");
      a.href = url;
      a.download = "fornecedores.json";
      document.body.appendChild(a);
      a.click();
      a.remove();
      URL.revokeObjectURL(url);
    }

    function importJson(file) {
      if (!file) return;
      const reader = new FileReader();
      reader.onload = () => {
        try {
          const data = JSON.parse(String(reader.result || "[]"));
          if (!Array.isArray(data)) throw new Error("Arquivo inválido: esperado um array.");
          // normaliza e mescla por id
          const incoming = data.map(x => ({
            id: String(x.id || "").trim() || uid(),
            nome: String(x.nome || x.name || "").trim(),
            doc: String(x.doc || x.cnpj || x.cpf || "").trim(),
            tel: String(x.tel || x.telefone || "").trim(),
            email: String(x.email || "").trim(),
            endereco: String(x.endereco || "").trim(),
            cidade: String(x.cidade || "").trim(),
            uf: String(x.uf || "").trim().toUpperCase(),
            contato: String(x.contato || "").trim(),
            obs: String(x.obs || "").trim(),
            status: String(x.status || "ATIVO").trim().toUpperCase() === "INATIVO" ? "INATIVO" : "ATIVO",
            createdAt: x.createdAt || new Date().toISOString()
          })).filter(x => x.nome);

          const map = new Map(ALL.map(x => [x.id, x]));
          incoming.forEach(x => map.set(x.id, x));
          ALL = Array.from(map.values());
          setAll(ALL);
          render();
          alert(`Importado com sucesso: ${incoming.length} registro(s).`);
        } catch (e) {
          alert("Falha ao importar: " + (e && e.message ? e.message : e));
        } finally {
          fileImport.value = "";
        }
      };
      reader.readAsText(file, "utf-8");
    }

    function clearFilters() {
      qFor.value = "";
      qGlobal.value = "";
      fStatus.value = "";
      render();
    }

    // ==============================
    // Eventos
    // ==============================
    btnNovo.addEventListener("click", openNew);
    btnSalvar.addEventListener("click", saveForm);
    btnExcluir.addEventListener("click", removeCurrent);

    btnExport.addEventListener("click", exportJson);
    fileImport.addEventListener("change", (e) => importJson(e.target.files && e.target.files[0]));

    btnLimpar.addEventListener("click", clearFilters);

    qFor.addEventListener("input", render);
    qFor.addEventListener("keydown", (e) => { if (e.key === "Enter") { e.preventDefault(); render(); } });

    qGlobal.addEventListener("input", () => {
      qFor.value = qGlobal.value;
      render();
    });

    fStatus.addEventListener("change", render);

    tbodyFor.addEventListener("click", (e) => {
      const btn = e.target.closest("[data-act]");
      if (!btn) return;
      const tr = e.target.closest("tr");
      if (!tr) return;
      const id = tr.getAttribute("data-id");
      if (!id) return;

      if (btn.getAttribute("data-act") === "edit") openEdit(id);
    });

    // ==============================
    // Init
    // ==============================
    function init() {
      ALL = getAll();
      render();
    }
    init();
  </script>
</body>

</html>