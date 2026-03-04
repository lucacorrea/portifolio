<?php

declare(strict_types=1);
session_start();

require_once __DIR__ . '/assets/conexao.php';

function e(string $s): string
{
  return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_token'];

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$pdo = db();
$rows = $pdo->query("SELECT id, nome, descricao, cor, obs, status
                     FROM categorias
                     ORDER BY id DESC
                     LIMIT 2000")->fetchAll(PDO::FETCH_ASSOC);

function badgeStatus(string $st): string
{
  $s = strtoupper(trim($st));
  if ($s === 'INATIVO') return '<span class="badge-soft badge-off">INATIVO</span>';
  return '<span class="badge-soft badge-ok">ATIVO</span>';
}
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
  <meta charset="UTF-8" />
  <meta http-equiv="X-UA-Compatible" content="IE=edge" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />

  <link rel="shortcut icon" href="assets/images/favicon.svg" type="image/x-icon" />
  <title>Painel da Distribuidora | Categorias</title>

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

    #tbCat {
      width: 100%;
      min-width: 860px;
    }

    #tbCat th,
    #tbCat td {
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

    /* modal */
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

    .swatch {
      width: 22px;
      height: 22px;
      border-radius: 8px;
      border: 1px solid rgba(148, 163, 184, .45);
      background: #e2e8f0;
      flex: 0 0 auto;
    }

    /* Flash 1.5s */
    .flash-auto-hide {
      transition: opacity .35s ease, transform .35s ease;
    }

    .flash-auto-hide.hide {
      opacity: 0;
      transform: translateY(-6px);
      pointer-events: none;
    }

    @media (max-width: 991.98px) {
      #tbCat {
        min-width: 820px;
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
            <li><a href="vendidos.php">vendidos</a></li>
            <li><a href="vendas.php">Vendas</a></li>
            <li><a href="devolucoes.php">Devoluções</a></li>
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
            <li><a href="clientes.php">Clientes</a></li>
            <li><a href="fornecedores.php">Fornecedores</a></li>
            <li><a href="categorias.php" class="active">Categorias</a></li>
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
                <form action="#" onsubmit="return false;">
                  <input type="text" placeholder="Buscar categoria..." id="qGlobal" />
                  <button type="submit"><i class="lni lni-search-alt"></i></button>
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
                      <div class="image"><img src="assets/images/profile/profile-image.png" alt="perfil" /></div>
                      <div>
                        <h6 class="fw-500">Administrador</h6>
                        <p>Distribuidora</p>
                      </div>
                    </div>
                  </div>
                </button>

                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="profile">
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
                <h2>Categorias</h2>
                <div class="muted">Cadastro no banco (processos em <b>assets/dados/categorias</b>).</div>
              </div>
            </div>
            <div class="col-md-4 text-md-end">
              <button class="main-btn primary-btn btn-hover btn-compact" id="btnNovo" type="button"
                data-bs-toggle="modal" data-bs-target="#mdCategoria">
                <i class="lni lni-plus me-1"></i> Nova categoria
              </button>
            </div>
          </div>
        </div>

        <?php if ($flash): ?>
          <div id="flashBox" class="alert alert-<?= e((string)$flash['type']) ?> flash-auto-hide mt-3">
            <?= e((string)$flash['msg']) ?>
          </div>
        <?php endif; ?>

        <div class="cardx mb-30 mt-3">
          <div class="head">
            <div class="d-flex align-items-center gap-2 flex-wrap">
              <div style="font-weight:1000;color:#0f172a;"><i class="lni lni-tag me-1"></i> Lista</div>
              <span class="badge-soft" id="countBadge">
                <?= count($rows) ?> categorias
              </span>
            </div>

            <div class="d-flex gap-2 flex-wrap align-items-center">
              <select class="form-select compact" id="fStatus" style="min-width: 160px;">
                <option value="">Status: Todos</option>
                <option value="ATIVO">Ativo</option>
                <option value="INATIVO">Inativo</option>
              </select>

              <a class="main-btn light-btn btn-hover btn-compact" href="assets/dados/categorias/exportar.php">
                <i class="lni lni-download me-1"></i> Exportar (JSON)
              </a>

              <form action="assets/dados/categorias/importar.php" method="post" enctype="multipart/form-data"
                style="margin:0;">
                <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                <label class="main-btn light-btn btn-hover btn-compact" style="margin:0; cursor:pointer;">
                  <i class="lni lni-upload me-1"></i> Importar
                  <input type="file" name="arquivo" id="fileImport" accept="application/json" hidden
                    onchange="this.form.submit();" />
                </label>
              </form>

              <button class="main-btn light-btn btn-hover btn-compact" id="btnLimpar" type="button">
                <i class="lni lni-eraser me-1"></i> Limpar
              </button>
            </div>
          </div>

          <div class="body">
            <div class="row g-2 mb-2">
              <div class="col-12 col-lg-6">
                <input class="form-control compact" id="qCat" placeholder="Buscar por nome, descrição ou status..." />
              </div>
              <div class="col-12 col-lg-6 text-lg-end">
                <div class="muted">Dica: use cor para identificar a categoria no sistema.</div>
              </div>
            </div>

            <div class="table-responsive">
              <table class="table text-nowrap" id="tbCat">
                <thead>
                  <tr>
                    <th style="min-width:90px;">ID</th>
                    <th style="min-width:260px;">Categoria</th>
                    <th style="min-width:320px;">Descrição</th>
                    <th style="min-width:160px;">Cor</th>
                    <th style="min-width:120px;" class="text-center">Status</th>
                    <th style="min-width:160px;" class="text-center">Ações</th>
                  </tr>
                </thead>
                <tbody id="tbodyCat">
                  <?php if (!$rows): ?>
                    <tr>
                      <td colspan="6" class="text-center muted py-4">Nenhuma categoria encontrada.</td>
                    </tr>
                    <?php else: foreach ($rows as $r): ?>
                      <?php
                      $id = (int)$r['id'];
                      $st = strtoupper((string)$r['status']) === 'INATIVO' ? 'INATIVO' : 'ATIVO';
                      $cor = trim((string)($r['cor'] ?? '#60a5fa')) ?: '#60a5fa';
                      $nome = (string)$r['nome'];
                      $desc = (string)($r['descricao'] ?? '');
                      $obs  = (string)($r['obs'] ?? '');
                      ?>
                      <tr data-id="<?= $id ?>" data-statusrow="<?= e($st) ?>" data-nome="<?= e($nome) ?>"
                        data-desc="<?= e($desc) ?>" data-obs="<?= e($obs) ?>" data-cor="<?= e($cor) ?>"
                        data-status="<?= e($st) ?>">
                        <td style="font-weight:1000;color:#0f172a;">
                          <?= $id ?>
                        </td>
                        <td>
                          <div class="d-flex align-items-center gap-2">
                            <span class="swatch" style="background:<?= e($cor) ?>;"></span>
                            <div style="min-width:0;">
                              <div style="font-weight:1000;color:#0f172a;line-height:1.1;">
                                <?= e($nome) ?>
                              </div>
                              <div class="muted">
                                <?= e($obs !== '' ? $obs : '—') ?>
                              </div>
                            </div>
                          </div>
                        </td>
                        <td>
                          <?= e($desc !== '' ? $desc : '—') ?>
                        </td>
                        <td>
                          <div class="d-flex align-items-center gap-2">
                            <span class="swatch" style="background:<?= e($cor) ?>;"></span>
                            <span style="font-weight:900;color:#0f172a;">
                              <?= e($cor) ?>
                            </span>
                          </div>
                        </td>
                        <td class="text-center">
                          <?= badgeStatus($st) ?>
                        </td>
                        <td class="text-center">
                          <button class="main-btn light-btn btn-hover btn-compact" type="button" data-act="edit"
                            data-bs-toggle="modal" data-bs-target="#mdCategoria">
                            <i class="lni lni-pencil me-1"></i> Editar
                          </button>
                        </td>
                      </tr>
                  <?php endforeach;
                  endif; ?>
                </tbody>
              </table>
            </div>

            <div class="muted mt-2" id="hintEmpty" style="display:none;">Nenhuma categoria encontrada.</div>
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

  <!-- MODAL: Categoria -->
  <div class="modal fade" id="mdCategoria" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <div>
            <h5 class="modal-title" id="mdTitle" style="font-weight:1000;">Nova categoria</h5>
            <div class="muted" id="mdSub">Preencha os dados abaixo.</div>
          </div>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
        </div>

        <!-- SAVE (add/edit) -->
        <form id="frmSave" action="assets/dados/categorias/adicionarCategorias.php" method="post">
          <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
          <input type="hidden" name="id" id="cId" value="">

          <div class="modal-body">
            <div class="row g-2">
              <div class="col-12 col-lg-8">
                <label class="form-label">Nome *</label>
                <input class="form-control compact" id="cNome" name="nome" placeholder="Ex: Alimentos" required />
              </div>
              <div class="col-12 col-lg-4">
                <label class="form-label">Status</label>
                <select class="form-select compact" id="cStatus" name="status">
                  <option value="ATIVO" selected>Ativo</option>
                  <option value="INATIVO">Inativo</option>
                </select>
              </div>

              <div class="col-12 col-lg-8">
                <label class="form-label">Descrição</label>
                <input class="form-control compact" id="cDesc" name="descricao" placeholder="Opcional..." />
              </div>

              <div class="col-12 col-lg-4">
                <label class="form-label">Cor</label>
                <div class="d-flex align-items-center gap-2">
                  <input class="form-control compact" id="cCor" type="color" value="#60a5fa"
                    style="width: 70px; padding: 4px 8px;" />
                  <input class="form-control compact" id="cCorTxt" name="cor" value="#60a5fa" placeholder="#RRGGBB" />
                </div>
                <div class="muted mt-1">Usada para etiqueta/badge.</div>
              </div>

              <div class="col-12">
                <label class="form-label">Observação</label>
                <textarea class="form-control" id="cObs" name="obs" rows="3" placeholder="Opcional..."
                  style="border-radius:12px;"></textarea>
              </div>
            </div>
          </div>

          <div class="modal-footer d-flex justify-content-between">
            <button class="main-btn danger-btn-outline btn-hover btn-compact" id="btnExcluir" type="submit"
              form="frmDelete" style="display:none;">
              <i class="lni lni-trash-can me-1"></i> Excluir
            </button>
            <div class="d-flex gap-2">
              <button class="main-btn light-btn btn-hover btn-compact" data-bs-dismiss="modal"
                type="button">Cancelar</button>
              <button class="main-btn primary-btn btn-hover btn-compact" type="submit">
                <i class="lni lni-save me-1"></i> Salvar
              </button>
            </div>
          </div>
        </form>

        <!-- DELETE (fora do form save) -->
        <form id="frmDelete" action="assets/dados/categorias/excluirCategorias.php" method="post"
          onsubmit="return confirm('Excluir esta categoria?');" style="display:none;">
          <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
          <input type="hidden" name="id" id="delId" value="">
        </form>

      </div>
    </div>
  </div>

  <!-- ========= JS ========= -->
  <script src="assets/js/bootstrap.bundle.min.js"></script>
  <script src="assets/js/main.js"></script>

  <script>
    // Flash some em 1.5s
    (function() {
      const box = document.getElementById('flashBox');
      if (!box) return;
      setTimeout(() => {
        box.classList.add('hide');
        setTimeout(() => box.remove(), 400);
      }, 1500);
    })();

    // DOM filtros
    const qGlobal = document.getElementById("qGlobal");
    const qCat = document.getElementById("qCat");
    const fStatus = document.getElementById("fStatus");
    const tbody = document.getElementById("tbodyCat");
    const hintEmpty = document.getElementById("hintEmpty");
    const countBadge = document.getElementById("countBadge");
    const btnLimpar = document.getElementById("btnLimpar");

    function onlyStatus(s) {
      const v = String(s || "").trim().toUpperCase();
      return (v === "ATIVO" || v === "INATIVO") ? v : "";
    }

    function filterRows() {
      const q = (qCat.value || qGlobal.value || "").toLowerCase().trim();
      const st = onlyStatus(fStatus.value);

      let visible = 0;
      tbody.querySelectorAll("tr").forEach(tr => {
        // ignora linha vazia
        if (!tr.hasAttribute("data-id")) return;

        const statusRow = (tr.getAttribute("data-statusrow") || "").toUpperCase();
        const text = tr.innerText.toLowerCase();

        const okQ = !q || text.includes(q);
        const okS = !st || statusRow === st;

        const show = okQ && okS;
        tr.style.display = show ? "" : "none";
        if (show) visible++;
      });

      countBadge.textContent = `${visible} categoria(s)`;
      hintEmpty.style.display = (visible === 0) ? "block" : "none";
    }

    qCat.addEventListener("input", filterRows);
    qGlobal.addEventListener("input", () => {
      qCat.value = qGlobal.value;
      filterRows();
    });
    fStatus.addEventListener("change", filterRows);

    btnLimpar.addEventListener("click", () => {
      qCat.value = "";
      qGlobal.value = "";
      fStatus.value = "";
      filterRows();
    });

    // Modal (novo/editar)
    const mdTitle = document.getElementById("mdTitle");
    const mdSub = document.getElementById("mdSub");
    const btnNovo = document.getElementById("btnNovo");

    const cId = document.getElementById("cId");
    const cNome = document.getElementById("cNome");
    const cStatus = document.getElementById("cStatus");
    const cDesc = document.getElementById("cDesc");
    const cObs = document.getElementById("cObs");
    const cCor = document.getElementById("cCor");
    const cCorTxt = document.getElementById("cCorTxt");

    const btnExcluir = document.getElementById("btnExcluir");
    const frmDelete = document.getElementById("frmDelete");
    const delId = document.getElementById("delId");

    function normHex(hex) {
      let h = String(hex || "").trim();
      if (!h) return "#60a5fa";
      if (!h.startsWith("#")) h = "#" + h;
      if (h.length === 4) h = "#" + h[1] + h[1] + h[2] + h[2] + h[3] + h[3];
      return /^#[0-9A-Fa-f]{6}$/.test(h) ? h.toLowerCase() : "#60a5fa";
    }

    function openNew() {
      mdTitle.textContent = "Nova categoria";
      mdSub.textContent = "Preencha os dados abaixo.";

      cId.value = "";
      cNome.value = "";
      cStatus.value = "ATIVO";
      cDesc.value = "";
      cObs.value = "";
      cCor.value = "#60a5fa";
      cCorTxt.value = "#60a5fa";

      btnExcluir.style.display = "none";
      frmDelete.style.display = "none";
      delId.value = "";

      setTimeout(() => cNome.focus(), 150);
    }

    function openEditFromTr(tr) {
      mdTitle.textContent = "Editar categoria";
      mdSub.textContent = "Altere e salve.";

      cId.value = tr.getAttribute("data-id") || "";
      cNome.value = tr.getAttribute("data-nome") || "";
      cStatus.value = tr.getAttribute("data-status") || "ATIVO";
      cDesc.value = tr.getAttribute("data-desc") || "";
      cObs.value = tr.getAttribute("data-obs") || "";
      const cor = normHex(tr.getAttribute("data-cor") || "#60a5fa");
      cCor.value = cor;
      cCorTxt.value = cor;

      delId.value = cId.value;
      btnExcluir.style.display = "inline-flex";
      frmDelete.style.display = "block";

      setTimeout(() => cNome.focus(), 150);
    }

    btnNovo.addEventListener("click", openNew);

    tbody.addEventListener("click", (e) => {
      const btn = e.target.closest("[data-act='edit']");
      if (!btn) return;
      const tr = e.target.closest("tr");
      if (!tr) return;
      openEditFromTr(tr);
    });

    // Cor sync
    cCor.addEventListener("input", () => {
      cCorTxt.value = cCor.value;
    });
    cCorTxt.addEventListener("input", () => {
      cCor.value = normHex(cCorTxt.value);
    });

    // init
    filterRows();
  </script>
</body>

</html>