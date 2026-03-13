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
  return 'categorias.php' . ($qs ? ('?' . $qs) : '');
}

function badgeStatus(string $st): string
{
  $s = strtoupper(trim($st));
  if ($s === 'INATIVO') return '<span class="badge-soft badge-off">INATIVO</span>';
  return '<span class="badge-soft badge-ok">ATIVO</span>';
}

function normHex(string $hex): string
{
  $h = trim($hex);
  if ($h === '') return '#60a5fa';
  if ($h[0] !== '#') $h = '#' . $h;
  if (strlen($h) === 4) {
    $h = '#' . $h[1] . $h[1] . $h[2] . $h[2] . $h[3] . $h[3];
  }
  return preg_match('/^#[0-9A-Fa-f]{6}$/', $h) ? strtolower($h) : '#60a5fa';
}

function fmtText(?string $v): string
{
  $v = trim((string)$v);
  return $v !== '' ? $v : '—';
}

if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_token'];

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

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
  $where[] = "c.status = :status";
  $params[':status'] = $status;
}

if ($q !== '') {
  $where[] = "(
    c.nome LIKE :q
    OR c.descricao LIKE :q
    OR c.cor LIKE :q
    OR c.obs LIKE :q
    OR c.status LIKE :q
  )";
  $params[':q'] = '%' . $q . '%';
}

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

/* =========================
   EXPORTAR EXCEL
========================= */
if (isset($_GET['export']) && $_GET['export'] === 'excel') {
  $sqlExcel = "
    SELECT id, nome, descricao, cor, obs, status
    FROM categorias c
    $whereSql
    ORDER BY id DESC
  ";
  $stExcel = $pdo->prepare($sqlExcel);
  foreach ($params as $k => $v) {
    $stExcel->bindValue($k, $v, PDO::PARAM_STR);
  }
  $stExcel->execute();
  $excelRows = $stExcel->fetchAll(PDO::FETCH_ASSOC) ?: [];

  $filename = 'categorias_' . date('Y-m-d_H-i-s') . '.xls';
  $geradoEm = date('d/m/Y H:i:s');
  $statusLabel = $status !== '' ? $status : 'Todos';
  $buscaLabel = $q !== '' ? $q : '—';

  header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
  header('Content-Disposition: attachment; filename="' . $filename . '"');
  header('Cache-Control: max-age=0');

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
        width: auto;
      }

      td,
      th {
        border: 1px solid #000;
        padding: 4px 6px;
        vertical-align: middle;
        white-space: nowrap;
      }

      th {
        background: #ffffff;
        font-weight: bold;
        text-align: center;
      }

      .title {
        font-size: 16px;
        font-weight: bold;
        text-align: center;
      }

      .sub {
        text-align: center;
        font-weight: normal;
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
        <td class="title" colspan="6">PAINEL DA DISTRIBUIDORA - CATEGORIAS</td>
      </tr>
      <tr>
        <td class="sub" colspan="6">Gerado em: <?= e($geradoEm) ?></td>
      </tr>
      <tr>
        <td class="sub" colspan="6">Busca: <?= e($buscaLabel) ?> | Status: <?= e($statusLabel) ?></td>
      </tr>
      <tr>
        <th>ID</th>
        <th>Categoria</th>
        <th>Descrição</th>
        <th>Cor</th>
        <th>Observação</th>
        <th>Status</th>
      </tr>

      <?php if (!$excelRows): ?>
        <tr>
          <td colspan="6" class="center">Nenhuma categoria encontrada.</td>
        </tr>
      <?php else: ?>
        <?php foreach ($excelRows as $r): ?>
          <?php $cor = normHex((string)($r['cor'] ?? '#60a5fa')); ?>
          <tr>
            <td class="center"><?= (int)$r['id'] ?></td>
            <td class="left"><?= e((string)$r['nome']) ?></td>
            <td class="left"><?= e(fmtText((string)($r['descricao'] ?? ''))) ?></td>
            <td class="left"><?= e($cor) ?></td>
            <td class="left"><?= e(fmtText((string)($r['obs'] ?? ''))) ?></td>
            <td class="center"><?= e(strtoupper((string)($r['status'] ?? 'ATIVO'))) ?></td>
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
$sqlCount = "SELECT COUNT(*) FROM categorias c $whereSql";
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
  SELECT id, nome, descricao, cor, obs, status
  FROM categorias c
  $whereSql
  ORDER BY id DESC
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
  <title>Painel da Distribuidora | Categorias</title>

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
      #tbCat {
        min-width: 820px;
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
            <li><a href="fornecedores.php">Fornecedores</a></li>
            <li><a href="categorias.php" class="active">Categorias</a></li>
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
                <h2>Categorias</h2>
              </div>
            </div>

            <div class="col-md-6">
              <div class="breadcrumb-wrapper">
                <nav aria-label="breadcrumb">
                  <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="#">Cadastros</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Categorias</li>
                  </ol>
                </nav>
              </div>
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
              <span class="badge-soft" id="countBadge"><?= $totalRows ?> categoria(s)</span>
            </div>

            <div class="d-flex gap-2 flex-wrap align-items-center">
              <button
                class="main-btn primary-btn btn-hover btn-compact"
                id="btnNovo"
                type="button"
                data-bs-toggle="modal"
                data-bs-target="#mdCategoria">
                <i class="lni lni-plus me-1"></i> Nova categoria
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
                <input class="form-control compact" id="qCat" value="<?= e($q) ?>" placeholder="Buscar por nome, descrição, cor ou status..." />
              </div>
              <div class="col-12 col-lg-6 text-lg-end">
                <div class="muted">Dica: use cor para identificar a categoria no sistema.</div>
              </div>
            </div>

            <div class="table-responsive">
              <table class="table text-nowrap mb-0" id="tbCat">
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
                  <?php else: ?>
                    <?php foreach ($rows as $r): ?>
                      <?php
                      $id = (int)$r['id'];
                      $st = strtoupper((string)$r['status']) === 'INATIVO' ? 'INATIVO' : 'ATIVO';
                      $cor = normHex((string)($r['cor'] ?? '#60a5fa'));
                      $nome = (string)$r['nome'];
                      $desc = (string)($r['descricao'] ?? '');
                      $obs  = (string)($r['obs'] ?? '');
                      ?>
                      <tr
                        data-id="<?= $id ?>"
                        data-statusrow="<?= e($st) ?>"
                        data-nome="<?= e($nome) ?>"
                        data-desc="<?= e($desc) ?>"
                        data-obs="<?= e($obs) ?>"
                        data-cor="<?= e($cor) ?>"
                        data-status="<?= e($st) ?>">
                        <td style="font-weight:1000;color:#0f172a;"><?= $id ?></td>
                        <td>
                          <div class="d-flex align-items-center gap-2">
                            <span class="swatch" style="background:<?= e($cor) ?>;"></span>
                            <div style="min-width:0;">
                              <div style="font-weight:1000;color:#0f172a;line-height:1.1;"><?= e($nome) ?></div>
                              <div class="muted"><?= e($obs !== '' ? $obs : '—') ?></div>
                            </div>
                          </div>
                        </td>
                        <td><?= e($desc !== '' ? $desc : '—') ?></td>
                        <td>
                          <div class="d-flex align-items-center gap-2">
                            <span class="swatch" style="background:<?= e($cor) ?>;"></span>
                            <span style="font-weight:900;color:#0f172a;"><?= e($cor) ?></span>
                          </div>
                        </td>
                        <td class="text-center"><?= badgeStatus($st) ?></td>
                        <td class="text-center">
                          <button class="main-btn light-btn btn-hover btn-compact" type="button" data-act="edit" data-bs-toggle="modal" data-bs-target="#mdCategoria">
                            <i class="lni lni-pencil me-1"></i> Editar
                          </button>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>

            <div class="muted mt-2" id="hintEmpty" style="<?= $totalRows === 0 ? '' : 'display:none;' ?>">Nenhuma categoria encontrada.</div>

            <div class="table-footer-nav">
              <p class="text-sm text-gray mb-0" id="infoCount">
                Mostrando <?= $currentCount ?> item(ns) nesta página de categorias. Total filtrado: <?= $totalRows ?>.
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
                  <input class="form-control compact" id="cCor" type="color" value="#60a5fa" style="width: 70px; padding: 4px 8px;" />
                  <input class="form-control compact" id="cCorTxt" name="cor" value="#60a5fa" placeholder="#RRGGBB" />
                </div>
                <div class="muted mt-1">Usada para etiqueta/badge.</div>
              </div>

              <div class="col-12">
                <label class="form-label">Observação</label>
                <textarea class="form-control" id="cObs" name="obs" rows="3" placeholder="Opcional..." style="border-radius:12px;"></textarea>
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

        <form id="frmDelete" action="assets/dados/categorias/excluirCategorias.php" method="post" onsubmit="return confirm('Excluir esta categoria?');" style="display:none;">
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
      const box = document.getElementById('flashBox');
      if (!box) return;
      setTimeout(() => {
        box.classList.add('hide');
        setTimeout(() => box.remove(), 400);
      }, 1500);
    })();

    const qCat = document.getElementById("qCat");
    const fStatus = document.getElementById("fStatus");
    const btnLimpar = document.getElementById("btnLimpar");
    const btnNovo = document.getElementById("btnNovo");
    const tbody = document.getElementById("tbodyCat");

    const mdTitle = document.getElementById("mdTitle");
    const mdSub = document.getElementById("mdSub");

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

    function normalizeHex(hex) {
      let h = String(hex || "").trim();
      if (!h) return "#60a5fa";
      if (!h.startsWith("#")) h = "#" + h;
      if (h.length === 4) h = "#" + h[1] + h[1] + h[2] + h[2] + h[3] + h[3];
      return /^#[0-9A-Fa-f]{6}$/.test(h) ? h.toLowerCase() : "#60a5fa";
    }

    function applyFilters(resetPage = true) {
      const params = new URLSearchParams(window.location.search);
      const q = (qCat.value || '').trim();
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

      const cor = normalizeHex(tr.getAttribute("data-cor") || "#60a5fa");
      cCor.value = cor;
      cCorTxt.value = cor;

      delId.value = cId.value;
      btnExcluir.style.display = "inline-flex";
      frmDelete.style.display = "block";

      setTimeout(() => cNome.focus(), 150);
    }

    if (btnNovo) {
      btnNovo.addEventListener("click", openNew);
    }

    tbody.addEventListener("click", (e) => {
      const btn = e.target.closest("[data-act='edit']");
      if (!btn) return;
      const tr = e.target.closest("tr");
      if (!tr) return;
      openEditFromTr(tr);
    });

    qCat.addEventListener("keydown", (e) => {
      if (e.key === "Enter") {
        e.preventDefault();
        applyFilters(true);
      }
    });

    fStatus.addEventListener("change", () => applyFilters(true));

    btnLimpar.addEventListener("click", () => {
      window.location.href = "categorias.php";
    });

    cCor.addEventListener("input", () => {
      cCorTxt.value = cCor.value;
    });

    cCorTxt.addEventListener("input", () => {
      cCor.value = normalizeHex(cCorTxt.value);
    });
  </script>
</body>

</html>