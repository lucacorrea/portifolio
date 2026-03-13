<?php

declare(strict_types=1);
session_start();

require_once __DIR__ . '/assets/auth/auth.php';
auth_require('index.php');

require_once __DIR__ . '/assets/conexao.php';

function e(string $s): string
{
  return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function build_url(array $overrides = []): string
{
  $params = $_GET;
  unset($params['export']);

  foreach ($overrides as $k => $v) {
    if ($v === null || $v === '') {
      unset($params[$k]);
    } else {
      $params[$k] = (string)$v;
    }
  }

  $qs = http_build_query($params);
  return 'fornecedores.php' . ($qs ? ('?' . $qs) : '');
}

function badge_status_html(string $status): string
{
  $stx = strtoupper(trim($status)) === 'INATIVO' ? 'INATIVO' : 'ATIVO';

  if ($stx === 'ATIVO') {
    return '<span class="badge-soft badge-ok">ATIVO</span>';
  }

  return '<span class="badge-soft badge-off">INATIVO</span>';
}

function fmt_doc(?string $v): string
{
  $v = trim((string)$v);
  return $v !== '' ? $v : '—';
}

function fmt_loc(?string $cidade, ?string $uf): string
{
  $cidade = trim((string)$cidade);
  $uf = trim((string)$uf);
  return ($cidade || $uf) ? ($cidade . ($uf ? ' / ' . $uf : '')) : '—';
}

// CSRF
if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_token'];

// Flash
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

// Filtros
$q = trim((string)($_GET['q'] ?? ''));
$status = strtoupper(trim((string)($_GET['status'] ?? '')));
$status = ($status === 'ATIVO' || $status === 'INATIVO') ? $status : '';

$page = (int)($_GET['page'] ?? 1);
if ($page < 1) $page = 1;

$PER_PAGE = 10;

$pdo = db();

$where = [];
$params = [];

if ($status !== '') {
  $where[] = "f.status = :status";
  $params[':status'] = $status;
}

if ($q !== '') {
  $where[] = "(
    f.nome LIKE :q
    OR f.doc LIKE :q
    OR f.tel LIKE :q
    OR f.email LIKE :q
    OR f.endereco LIKE :q
    OR f.cidade LIKE :q
    OR f.uf LIKE :q
    OR f.contato LIKE :q
    OR f.obs LIKE :q
  )";
  $params[':q'] = '%' . $q . '%';
}

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

/* =========================
   EXPORTAR EXCEL
========================= */
if (isset($_GET['export']) && $_GET['export'] === 'excel') {
  $sqlExcel = "
    SELECT
      f.id, f.nome, f.status, f.doc, f.tel, f.email,
      f.endereco, f.cidade, f.uf, f.contato, f.obs
    FROM fornecedores f
    $whereSql
    ORDER BY f.id DESC
  ";
  $stExcel = $pdo->prepare($sqlExcel);
  foreach ($params as $k => $v) {
    $stExcel->bindValue($k, $v, PDO::PARAM_STR);
  }
  $stExcel->execute();
  $excelRows = $stExcel->fetchAll(PDO::FETCH_ASSOC) ?: [];

  $filename = 'fornecedores_' . date('Y-m-d_H-i-s') . '.xls';

  header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
  header('Content-Disposition: attachment; filename="' . $filename . '"');
  header('Cache-Control: max-age=0');

  $statusLabel = $status !== '' ? $status : 'Todos';
  $buscaLabel = $q !== '' ? $q : '—';
  $geradoEm = date('d/m/Y H:i:s');

  echo "\xEF\xBB\xBF";
?>
  <html>

  <head>
    <meta charset="UTF-8">
    <style>
      table {
        border-collapse: collapse;
        font-family: Arial, sans-serif;
        font-size: 12px;
        width: 100%;
      }

      td,
      th {
        border: 1px solid #000;
        padding: 6px 8px;
        vertical-align: middle;
        white-space: nowrap;
      }

      th {
        background: #f2f2f2;
        font-weight: bold;
        text-align: center;
      }

      .title {
        font-size: 18px;
        font-weight: bold;
        text-align: center;
        background: #ffffff;
      }

      .sub {
        text-align: center;
        font-weight: bold;
      }

      .left {
        text-align: left;
      }

      .center {
        text-align: center;
      }
    </style>
  </head>

  <body>
    <table>
      <tr>
        <td class="title" colspan="8">PAINEL DA DISTRIBUIDORA - FORNECEDORES</td>
      </tr>
      <tr>
        <td class="sub" colspan="8">Gerado em: <?= e($geradoEm) ?></td>
      </tr>
      <tr>
        <td class="sub" colspan="8">Status: <?= e($statusLabel) ?> | Busca: <?= e($buscaLabel) ?></td>
      </tr>
      <tr>
        <th>ID</th>
        <th>Fornecedor</th>
        <th>Documento</th>
        <th>Telefone</th>
        <th>E-mail</th>
        <th>Cidade/UF</th>
        <th>Contato</th>
        <th>Status</th>
      </tr>

      <?php if (!$excelRows): ?>
        <tr>
          <td colspan="8" class="center">Nenhum fornecedor encontrado.</td>
        </tr>
      <?php else: ?>
        <?php foreach ($excelRows as $r): ?>
          <tr>
            <td class="center"><?= (int)$r['id'] ?></td>
            <td class="left"><?= e((string)$r['nome']) ?></td>
            <td class="left"><?= e(fmt_doc((string)($r['doc'] ?? ''))) ?></td>
            <td class="left"><?= e(fmt_doc((string)($r['tel'] ?? ''))) ?></td>
            <td class="left"><?= e(fmt_doc((string)($r['email'] ?? ''))) ?></td>
            <td class="left"><?= e(fmt_loc((string)($r['cidade'] ?? ''), (string)($r['uf'] ?? ''))) ?></td>
            <td class="left"><?= e(fmt_doc((string)($r['contato'] ?? ''))) ?></td>
            <td class="center"><?= e(strtoupper((string)($r['status'] ?? 'ATIVO')) ?: 'ATIVO') ?></td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </table>
  </body>

  </html>
<?php
  exit;
}

/* =========================
   CONTAGEM
========================= */
$sqlCount = "SELECT COUNT(*) FROM fornecedores f $whereSql";
$stCount = $pdo->prepare($sqlCount);
foreach ($params as $k => $v) {
  $stCount->bindValue($k, $v, PDO::PARAM_STR);
}
$stCount->execute();
$totalRows = (int)$stCount->fetchColumn();

$pages = max(1, (int)ceil($totalRows / $PER_PAGE));
if ($page > $pages) $page = $pages;

$offset = ($page - 1) * $PER_PAGE;

/* =========================
   LISTAGEM PAGINADA
========================= */
$sql = "
  SELECT
    f.id, f.nome, f.status, f.doc, f.tel, f.email,
    f.endereco, f.cidade, f.uf, f.contato, f.obs
  FROM fornecedores f
  $whereSql
  ORDER BY f.id DESC
  LIMIT :lim OFFSET :off
";

$st = $pdo->prepare($sql);
foreach ($params as $k => $v) {
  $st->bindValue($k, $v, PDO::PARAM_STR);
}
$st->bindValue(':lim', $PER_PAGE, PDO::PARAM_INT);
$st->bindValue(':off', $offset, PDO::PARAM_INT);
$st->execute();
$rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

$currentCount = count($rows);
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
  <meta charset="UTF-8" />
  <meta http-equiv="X-UA-Compatible" content="IE=edge" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />

  <link rel="shortcut icon" href="assets/images/favicon.svg" type="image/x-icon" />
  <title>Painel da Distribuidora | Fornecedores</title>

  <link rel="stylesheet" href="assets/css/bootstrap.min.css" />
  <link rel="stylesheet" href="assets/css/lineicons.css" rel="stylesheet" type="text/css" />
  <link rel="stylesheet" href="assets/css/materialdesignicons.min.css" rel="stylesheet" type="text/css" />
  <link rel="stylesheet" href="assets/css/main.css" />

  <style>
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
      border-collapse: separate;
      border-spacing: 0;
    }

    #tbFor th,
    #tbFor td {
      white-space: nowrap !important;
    }

    #tbFor thead th {
      padding: 14px 16px !important;
      background: #f8fafc;
      color: #0f172a;
      font-weight: 900;
      border-top: 1px solid rgba(148, 163, 184, .18);
      border-bottom: 1px solid rgba(148, 163, 184, .24);
    }

    #tbFor tbody td {
      padding: 14px 16px !important;
      color: #334155;
      font-weight: 600;
      background: #fff;
      border-bottom: 1px solid rgba(148, 163, 184, .14);
    }

    #tbFor thead th:first-child,
    #tbFor tbody td:first-child {
      border-left: 1px solid rgba(148, 163, 184, .14);
    }

    #tbFor thead th:last-child,
    #tbFor tbody td:last-child {
      border-right: 1px solid rgba(148, 163, 184, .14);
    }

    #tbFor thead th+th,
    #tbFor tbody td+td {
      border-left: 1px solid rgba(148, 163, 184, .14);
    }

    .badge-soft {
      border: 1px solid rgba(148, 163, 184, .30);
      background: rgba(248, 250, 252, .8);
      padding: 5px 10px;
      border-radius: 999px;
      font-size: 12px;
      font-weight: 900;
      color: #0f172a;
      display: inline-flex;
      align-items: center;
      justify-content: center;
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

    .flash-wrap {
      margin-top: 16px;
    }

    .flash-auto-hide {
      transition: opacity .35s ease, transform .35s ease;
    }

    .flash-auto-hide.hide {
      opacity: 0;
      transform: translateY(-6px);
      pointer-events: none;
    }

    .table-footer-nav {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 14px;
      flex-wrap: wrap;
      margin-top: 12px;
    }

    .pager-box {
      display: flex;
      align-items: center;
      gap: 14px;
      justify-content: flex-end;
      flex-wrap: wrap;
    }

    .page-btn,
    .page-btn-link {
      width: 42px;
      height: 42px;
      border: 1px solid #e5e7eb;
      border-radius: 8px;
      background: #f8fafc;
      color: #475569;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      text-decoration: none !important;
      transition: .2s ease;
    }

    .page-btn-link:hover {
      background: #eef2ff;
      color: #1e40af;
      border-color: #c7d2fe;
    }

    .page-btn:disabled {
      opacity: .45;
      cursor: not-allowed;
    }

    .page-info {
      font-weight: 900;
      color: #475569;
      min-width: 90px;
      text-align: center;
      font-size: 12px;
    }

    .logout-btn {
      padding: 8px 14px !important;
      min-width: 88px;
      height: 46px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      border-radius: 10px;
      font-size: 14px;
      font-weight: 500;
      text-decoration: none !important;
    }

    .logout-btn i {
      font-size: 16px;
    }

    .header-right {
      height: 100%;
    }

    .brand-vertical {
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      gap: 6px;
      text-decoration: none;
      text-align: center;
    }

    .brand-name {
      display: block;
      font-size: 18px;
      line-height: 1.2;
      font-weight: 600;
      color: #1e2a78;
      white-space: normal;
      word-break: break-word;
    }

    @media (max-width: 991.98px) {
      #tbFor {
        min-width: 900px;
      }

      .table-footer-nav {
        justify-content: center;
      }

      #infoCount {
        text-align: center;
        width: 100%;
      }
    }
  </style>
</head>

<body>
  <div id="preloader">
    <div class="spinner"></div>
  </div>

  <aside class="sidebar-nav-wrapper">
    <div class="navbar-logo">
      <a href="dashboard.php" class="brand-vertical">
        <span class="brand-name">DISTRIBUIDORA<br>PLHB</span>
      </a>
    </div>

    <nav class="sidebar-nav">
      <ul>
        <li class="nav-item">
          <a href="dashboard.php">
            <span class="icon"><i class="lni lni-dashboard"></i></span>
            <span class="text">Dashboard</span>
          </a>
        </li>

        <li class="nav-item">
          <a href="vendas.php">
            <span class="icon"><i class="lni lni-cart"></i></span>
            <span class="text">Vendas</span>
          </a>
        </li>

        <li class="nav-item nav-item-has-children">
          <a href="#0" class="collapsed" data-bs-toggle="collapse" data-bs-target="#ddmenu_operacoes" aria-controls="ddmenu_operacoes" aria-expanded="false">
            <span class="icon"><i class="lni lni-layers"></i></span>
            <span class="text">Operações</span>
          </a>
          <ul id="ddmenu_operacoes" class="collapse dropdown-nav">
            <li><a href="vendidos.php">Vendidos</a></li>
            <li><a href="fiados.php">À Prazo</a></li>
            <li><a href="devolucoes.php">Devoluções</a></li>
          </ul>
        </li>

        <li class="nav-item nav-item-has-children">
          <a href="#0" class="collapsed" data-bs-toggle="collapse" data-bs-target="#ddmenu_estoque" aria-controls="ddmenu_estoque" aria-expanded="false">
            <span class="icon"><i class="lni lni-package"></i></span>
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
          <a href="#0" class="collapsed" data-bs-toggle="collapse" data-bs-target="#ddmenu_cadastros" aria-controls="ddmenu_cadastros" aria-expanded="false">
            <span class="icon"><i class="lni lni-users"></i></span>
            <span class="text">Cadastros</span>
          </a>
          <ul id="ddmenu_cadastros" class="collapse dropdown-nav show">
            <li><a href="clientes.php">Clientes</a></li>
            <li><a href="fornecedores.php" class="active">Fornecedores</a></li>
            <li><a href="categorias.php">Categorias</a></li>
          </ul>
        </li>

        <li class="nav-item">
          <a href="relatorios.php">
            <span class="icon"><i class="lni lni-clipboard"></i></span>
            <span class="text">Relatórios</span>
          </a>
        </li>

        <span class="divider">
          <hr />
        </span>

        <li class="nav-item nav-item-has-children">
          <a href="#0" class="collapsed" data-bs-toggle="collapse" data-bs-target="#ddmenu_config" aria-controls="ddmenu_config" aria-expanded="false">
            <span class="icon"><i class="lni lni-cog"></i></span>
            <span class="text">Configurações</span>
          </a>
          <ul id="ddmenu_config" class="collapse dropdown-nav">
            <li><a href="usuarios.php">Usuários e Permissões</a></li>
            <li><a href="parametros.php">Parâmetros do Sistema</a></li>
          </ul>
        </li>

        <li class="nav-item">
          <a href="suporte.php">
            <span class="icon"><i class="lni lni-whatsapp"></i></span>
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
              <div class="header-search d-none d-md-flex"></div>
            </div>
          </div>

          <div class="col-lg-7 col-md-7 col-6">
            <div class="header-right d-flex justify-content-end align-items-center">
              <a href="logout.php" class="main-btn primary-btn btn-hover logout-btn">
                <i class="lni lni-exit me-1"></i> Sair
              </a>
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
                <h2>Fornecedores</h2>
              </div>
            </div>

            <div class="col-md-6">
              <div class="breadcrumb-wrapper">
                <nav aria-label="breadcrumb">
                  <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="#">Cadastros</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Fornecedores</li>
                  </ol>
                </nav>
              </div>
            </div>
          </div>
        </div>

        <?php if ($flash): ?>
          <div class="flash-wrap">
            <div id="flashBox" class="alert alert-<?= e((string)$flash['type']) ?> flash-auto-hide">
              <?= e((string)$flash['msg']) ?>
            </div>
          </div>
        <?php endif; ?>

        <div class="cardx mb-30 mt-3">
          <div class="head">
            <div class="d-flex align-items-center gap-2 flex-wrap">
              <div style="font-weight:1000;color:#0f172a;">
                <i class="lni lni-users me-1"></i> Lista
              </div>
              <span class="badge-soft" id="countBadge"><?= $totalRows ?> fornecedor(es)</span>
            </div>

            <div class="d-flex gap-2 flex-wrap align-items-center">
              <button
                class="main-btn primary-btn btn-hover btn-compact"
                id="btnNovo"
                type="button"
                data-bs-toggle="modal"
                data-bs-target="#mdFornecedor">
                <i class="lni lni-plus me-1"></i> Novo fornecedor
              </button>

              <select class="form-select compact" id="fStatus" style="min-width: 160px;">
                <option value="" <?= $status === '' ? 'selected' : '' ?>>Status: Todos</option>
                <option value="ATIVO" <?= $status === 'ATIVO' ? 'selected' : '' ?>>Ativo</option>
                <option value="INATIVO" <?= $status === 'INATIVO' ? 'selected' : '' ?>>Inativo</option>
              </select>

              <a class="main-btn light-btn btn-hover btn-compact" href="<?= e(build_url(['export' => 'excel', 'page' => 1])) ?>">
                <i class="lni lni-download me-1"></i> Exportar Excel
              </a>

              <button class="main-btn light-btn btn-hover btn-compact" id="btnLimpar" type="button">
                <i class="lni lni-eraser me-1"></i> Limpar
              </button>
            </div>
          </div>

          <div class="body">
            <div class="row g-2 mb-2">
              <div class="col-12 col-lg-6">
                <input class="form-control compact" id="qFor" value="<?= e($q) ?>" placeholder="Buscar por nome, CNPJ/CPF, telefone, e-mail..." />
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
                <tbody id="tbodyFor">
                  <?php if (!$rows): ?>
                    <tr>
                      <td colspan="8" class="text-center muted py-4">Nenhum fornecedor encontrado.</td>
                    </tr>
                  <?php else: ?>
                    <?php foreach ($rows as $r): ?>
                      <?php
                      $id = (int)$r['id'];
                      $stx = strtoupper((string)$r['status']) === 'INATIVO' ? 'INATIVO' : 'ATIVO';
                      ?>
                      <tr
                        data-id="<?= $id ?>"
                        data-nome="<?= e((string)$r['nome']) ?>"
                        data-status="<?= e($stx) ?>"
                        data-doc="<?= e((string)($r['doc'] ?? '')) ?>"
                        data-tel="<?= e((string)($r['tel'] ?? '')) ?>"
                        data-email="<?= e((string)($r['email'] ?? '')) ?>"
                        data-endereco="<?= e((string)($r['endereco'] ?? '')) ?>"
                        data-cidade="<?= e((string)($r['cidade'] ?? '')) ?>"
                        data-uf="<?= e((string)($r['uf'] ?? '')) ?>"
                        data-contato="<?= e((string)($r['contato'] ?? '')) ?>"
                        data-obs="<?= e((string)($r['obs'] ?? '')) ?>">
                        <td style="font-weight:1000;color:#0f172a;"><?= $id ?></td>
                        <td>
                          <div style="font-weight:1000;color:#0f172a;line-height:1.1;"><?= e((string)$r['nome']) ?></div>
                          <div class="muted"><?= e(trim((string)$r['contato']) ?: '—') ?></div>
                        </td>
                        <td><?= e(fmt_doc((string)($r['doc'] ?? ''))) ?></td>
                        <td><?= e(fmt_doc((string)($r['tel'] ?? ''))) ?></td>
                        <td>
                          <?php if (trim((string)$r['email']) !== ''): ?>
                            <a class="link-mini" href="mailto:<?= e((string)$r['email']) ?>"><?= e((string)$r['email']) ?></a>
                          <?php else: ?>
                            —
                          <?php endif; ?>
                        </td>
                        <td><?= e(fmt_loc((string)($r['cidade'] ?? ''), (string)($r['uf'] ?? ''))) ?></td>
                        <td class="text-center"><?= badge_status_html($stx) ?></td>
                        <td class="text-center">
                          <button class="main-btn light-btn btn-hover btn-compact" type="button" data-act="edit" data-bs-toggle="modal" data-bs-target="#mdFornecedor">
                            <i class="lni lni-pencil me-1"></i> Editar
                          </button>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>

            <div class="muted mt-2" id="hintEmpty" style="<?= $totalRows === 0 ? '' : 'display:none;' ?>">Nenhum fornecedor encontrado.</div>

            <div class="table-footer-nav">
              <p class="text-sm text-gray mb-0" id="infoCount">
                Mostrando <?= $currentCount ?> item(ns) nesta página de fornecedores. Total filtrado: <?= $totalRows ?>.
              </p>

              <div class="pager-box" id="pagerBox">
                <?php if ($page > 1): ?>
                  <a class="page-btn-link" href="<?= e(build_url(['page' => $page - 1])) ?>" title="Anterior">
                    <i class="lni lni-chevron-left"></i>
                  </a>
                <?php else: ?>
                  <button class="page-btn" type="button" disabled title="Anterior">
                    <i class="lni lni-chevron-left"></i>
                  </button>
                <?php endif; ?>

                <span class="page-info">Página <?= $page ?>/<?= $pages ?></span>

                <?php if ($page < $pages): ?>
                  <a class="page-btn-link" href="<?= e(build_url(['page' => $page + 1])) ?>" title="Próxima">
                    <i class="lni lni-chevron-right"></i>
                  </a>
                <?php else: ?>
                  <button class="page-btn" type="button" disabled title="Próxima">
                    <i class="lni lni-chevron-right"></i>
                  </button>
                <?php endif; ?>
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

        <form id="frmSave" action="assets/dados/fornecedores/adicionarFornecedores.php" method="post">
          <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
          <input type="hidden" name="id" id="fId" value="">

          <div class="modal-body">
            <div class="row g-2">
              <div class="col-12 col-lg-8">
                <label class="form-label">Nome / Razão Social *</label>
                <input class="form-control compact" id="fNome" name="nome" placeholder="Ex: Distribuidora X LTDA" />
              </div>
              <div class="col-12 col-lg-4">
                <label class="form-label">Status</label>
                <select class="form-select compact" id="fStatusEdit" name="status">
                  <option value="ATIVO" selected>Ativo</option>
                  <option value="INATIVO">Inativo</option>
                </select>
              </div>

              <div class="col-12 col-lg-4">
                <label class="form-label">CNPJ/CPF</label>
                <input class="form-control compact" id="fDoc" name="doc" placeholder="00.000.000/0000-00" />
              </div>
              <div class="col-12 col-lg-4">
                <label class="form-label">Telefone</label>
                <input class="form-control compact" id="fTel" name="tel" placeholder="(92) 9xxxx-xxxx" />
              </div>
              <div class="col-12 col-lg-4">
                <label class="form-label">E-mail</label>
                <input class="form-control compact" id="fEmail" name="email" placeholder="contato@fornecedor.com" />
              </div>

              <div class="col-12">
                <label class="form-label">Endereço</label>
                <input class="form-control compact" id="fEnd" name="endereco" placeholder="Rua, nº, bairro, referência..." />
              </div>

              <div class="col-12 col-lg-6">
                <label class="form-label">Cidade</label>
                <input class="form-control compact" id="fCidade" name="cidade" placeholder="Coari" />
              </div>
              <div class="col-12 col-lg-2">
                <label class="form-label">UF</label>
                <input class="form-control compact" id="fUF" name="uf" placeholder="AM" maxlength="2" />
              </div>
              <div class="col-12 col-lg-4">
                <label class="form-label">Contato (Pessoa)</label>
                <input class="form-control compact" id="fContato" name="contato" placeholder="Nome do contato" />
              </div>

              <div class="col-12">
                <label class="form-label">Observação</label>
                <textarea class="form-control" id="fObs" name="obs" rows="3" placeholder="Opcional..." style="border-radius:12px;"></textarea>
              </div>
            </div>
          </div>

          <div class="modal-footer d-flex justify-content-between">
            <button class="main-btn danger-btn-outline btn-hover btn-compact" id="btnExcluir" type="submit" form="frmDelete" style="display:none;">
              <i class="lni lni-trash-can me-1"></i> Excluir
            </button>

            <div class="d-flex gap-2">
              <button class="main-btn light-btn btn-hover btn-compact" data-bs-dismiss="modal" type="button">Cancelar</button>
              <button class="main-btn primary-btn btn-hover btn-compact" type="submit">
                <i class="lni lni-save me-1"></i> Salvar
              </button>
            </div>
          </div>
        </form>

        <form id="frmDelete" action="assets/dados/fornecedores/excluirFornecedores.php" method="post" onsubmit="return confirm('Excluir este fornecedor?');" style="display:none;">
          <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
          <input type="hidden" name="id" id="delId" value="">
        </form>
      </div>
    </div>
  </div>

  <script src="assets/js/bootstrap.bundle.min.js"></script>
  <script src="assets/js/main.js"></script>

  <script>
    (function() {
      const box = document.getElementById("flashBox");
      if (!box) return;
      setTimeout(() => {
        box.classList.add("hide");
        setTimeout(() => box.remove(), 450);
      }, 4500);
    })();

    const qFor = document.getElementById("qFor");
    const fStatus = document.getElementById("fStatus");
    const btnLimpar = document.getElementById("btnLimpar");
    const btnNovo = document.getElementById("btnNovo");
    const tbodyFor = document.getElementById("tbodyFor");

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

    const btnExcluir = document.getElementById("btnExcluir");
    const frmDelete = document.getElementById("frmDelete");
    const delId = document.getElementById("delId");

    function applyFilters(resetPage = true) {
      const params = new URLSearchParams(window.location.search);
      const q = (qFor.value || '').trim();
      const st = (fStatus.value || '').trim();

      if (q) params.set('q', q);
      else params.delete('q');

      if (st) params.set('status', st);
      else params.delete('status');

      if (resetPage) params.set('page', '1');

      params.delete('export');

      const url = window.location.pathname + (params.toString() ? ('?' + params.toString()) : '');
      window.location.href = url;
    }

    function openNew() {
      mdTitle.textContent = "Novo fornecedor";
      mdSub.textContent = "Preencha os dados abaixo.";

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

      btnExcluir.style.display = "none";
      frmDelete.style.display = "none";
      delId.value = "";
    }

    function openEditFromTr(tr) {
      mdTitle.textContent = "Editar fornecedor";
      mdSub.textContent = "Altere e salve.";

      fId.value = tr.getAttribute("data-id") || "";
      fNome.value = tr.getAttribute("data-nome") || "";
      fStatusEdit.value = tr.getAttribute("data-status") || "ATIVO";
      fDoc.value = tr.getAttribute("data-doc") || "";
      fTel.value = tr.getAttribute("data-tel") || "";
      fEmail.value = tr.getAttribute("data-email") || "";
      fEnd.value = tr.getAttribute("data-endereco") || "";
      fCidade.value = tr.getAttribute("data-cidade") || "";
      fUF.value = tr.getAttribute("data-uf") || "";
      fContato.value = tr.getAttribute("data-contato") || "";
      fObs.value = tr.getAttribute("data-obs") || "";

      delId.value = fId.value;
      btnExcluir.style.display = "inline-flex";
      frmDelete.style.display = "block";
    }

    if (btnNovo) {
      btnNovo.addEventListener("click", () => {
        openNew();
        setTimeout(() => fNome.focus(), 150);
      });
    }

    tbodyFor.addEventListener("click", (e) => {
      const btn = e.target.closest("[data-act='edit']");
      if (!btn) return;
      const tr = e.target.closest("tr");
      if (!tr) return;
      openEditFromTr(tr);
      setTimeout(() => fNome.focus(), 150);
    });

    qFor.addEventListener("keydown", (e) => {
      if (e.key === "Enter") {
        e.preventDefault();
        applyFilters(true);
      }
    });

    fStatus.addEventListener("change", () => applyFilters(true));

    btnLimpar.addEventListener("click", () => {
      window.location.href = "fornecedores.php";
    });
  </script>
</body>

</html>