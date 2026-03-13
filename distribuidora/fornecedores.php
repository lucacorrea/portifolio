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

function json_out(array $payload, int $code = 200): void
{
  if (function_exists('ob_get_length') && ob_get_length()) {
    @ob_clean();
  }
  http_response_code($code);
  header('Content-Type: application/json; charset=UTF-8');
  header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
  echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  exit;
}

function fmt_text(?string $v): string
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

function badge_status_html(string $status): string
{
  $st = strtoupper(trim($status)) === 'INATIVO' ? 'INATIVO' : 'ATIVO';

  if ($st === 'ATIVO') {
    return '<span class="badge-soft badge-ok">ATIVO</span>';
  }

  return '<span class="badge-soft badge-off">INATIVO</span>';
}

function fetch_fornecedores_page(PDO $pdo, string $q, string $status, int $page, int $perPage = 10): array
{
  $page = max(1, $page);

  $where = [];
  $params = [];

  if ($status !== '') {
    $where[] = "f.status = :status";
    $params[':status'] = $status;
  }

  if ($q !== '') {
    $where[] = "(
      CAST(f.id AS CHAR) LIKE :q
      OR f.nome LIKE :q
      OR f.doc LIKE :q
      OR f.tel LIKE :q
      OR f.email LIKE :q
      OR f.endereco LIKE :q
      OR f.cidade LIKE :q
      OR f.uf LIKE :q
      OR f.contato LIKE :q
      OR f.obs LIKE :q
      OR f.status LIKE :q
    )";
    $params[':q'] = '%' . $q . '%';
  }

  $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

  $sqlCount = "SELECT COUNT(*) FROM fornecedores f $whereSql";
  $stCount = $pdo->prepare($sqlCount);
  foreach ($params as $k => $v) {
    $stCount->bindValue($k, $v, PDO::PARAM_STR);
  }
  $stCount->execute();
  $totalRows = (int)$stCount->fetchColumn();

  $pages = max(1, (int)ceil($totalRows / $perPage));
  if ($page > $pages) $page = $pages;
  $offset = ($page - 1) * $perPage;

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
  $st->bindValue(':lim', $perPage, PDO::PARAM_INT);
  $st->bindValue(':off', $offset, PDO::PARAM_INT);
  $st->execute();
  $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

  $outRows = [];
  foreach ($rows as $r) {
    $statusRow = strtoupper((string)($r['status'] ?? 'ATIVO')) === 'INATIVO' ? 'INATIVO' : 'ATIVO';

    $outRows[] = [
      'id'       => (int)($r['id'] ?? 0),
      'nome'     => (string)($r['nome'] ?? ''),
      'status'   => $statusRow,
      'doc'      => (string)($r['doc'] ?? ''),
      'tel'      => (string)($r['tel'] ?? ''),
      'email'    => (string)($r['email'] ?? ''),
      'endereco' => (string)($r['endereco'] ?? ''),
      'cidade'   => (string)($r['cidade'] ?? ''),
      'uf'       => (string)($r['uf'] ?? ''),
      'contato'  => (string)($r['contato'] ?? ''),
      'obs'      => (string)($r['obs'] ?? ''),
    ];
  }

  return [
    'meta' => [
      'q'      => $q,
      'status' => $status,
      'page'   => $page,
      'pages'  => $pages,
      'total'  => $totalRows,
      'shown'  => count($outRows),
      'per'    => $perPage,
    ],
    'rows' => $outRows,
  ];
}

function fetch_all_fornecedores(PDO $pdo, string $q, string $status): array
{
  return fetch_fornecedores_page($pdo, $q, $status, 1, 999999)['rows'];
}

/* =========================
   CSRF / FLASH / FILTROS
========================= */
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

/* =========================
   AJAX
========================= */
$action = strtolower(trim((string)($_GET['action'] ?? '')));
if ($action === 'ajax') {
  try {
    $ajaxQ = trim((string)($_GET['q'] ?? ''));
    $ajaxStatus = strtoupper(trim((string)($_GET['status'] ?? '')));
    $ajaxStatus = ($ajaxStatus === 'ATIVO' || $ajaxStatus === 'INATIVO') ? $ajaxStatus : '';
    $ajaxPage = (int)($_GET['page'] ?? 1);
    if ($ajaxPage < 1) $ajaxPage = 1;

    $data = fetch_fornecedores_page($pdo, $ajaxQ, $ajaxStatus, $ajaxPage, $PER_PAGE);
    json_out(['ok' => true] + $data);
  } catch (Throwable $e) {
    json_out(['ok' => false, 'msg' => 'Erro no AJAX: ' . $e->getMessage()], 500);
  }
}

/* =========================
   EXPORTAR EXCEL
========================= */
if (isset($_GET['export']) && $_GET['export'] === 'excel') {
  $excelRows = fetch_all_fornecedores($pdo, $q, $status);

  $filename = 'fornecedores_' . date('Y-m-d_H-i-s') . '.xls';
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

    td, th {
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
      <td class="title" colspan="8">PAINEL DA DISTRIBUIDORA - FORNECEDORES</td>
    </tr>
    <tr>
      <td class="sub" colspan="8">Gerado em: <?= e($geradoEm) ?></td>
    </tr>
    <tr>
      <td class="sub" colspan="8">Busca: <?= e($buscaLabel) ?> | Status: <?= e($statusLabel) ?></td>
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
          <td class="left"><?= e(fmt_text((string)$r['doc'])) ?></td>
          <td class="left"><?= e(fmt_text((string)$r['tel'])) ?></td>
          <td class="left"><?= e(fmt_text((string)$r['email'])) ?></td>
          <td class="left"><?= e(fmt_loc((string)$r['cidade'], (string)$r['uf'])) ?></td>
          <td class="left"><?= e(fmt_text((string)$r['contato'])) ?></td>
          <td class="center"><?= e((string)$r['status']) ?></td>
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
   PRIMEIRA CARGA
========================= */
$initialData = fetch_fornecedores_page($pdo, $q, $status, $page, $PER_PAGE);
$totalRows = (int)$initialData['meta']['total'];
$pages = (int)$initialData['meta']['pages'];
$currentCount = (int)$initialData['meta']['shown'];
$rows = $initialData['rows'];
$page = (int)$initialData['meta']['page'];
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

    .page-btn {
      width: 42px;
      height: 42px;
      border: 1px solid #e5e7eb;
      border-radius: 8px;
      background: #f8fafc;
      color: #475569;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      transition: .2s ease;
    }

    .page-btn:hover:not(:disabled) {
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

        <span class="divider"><hr /></span>

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
              <span class="badge-soft" id="pillLoading" style="display:none;">Carregando...</span>
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

              <button class="main-btn light-btn btn-hover btn-compact" id="btnExcel" type="button">
                <i class="lni lni-download me-1"></i> Exportar Excel
              </button>

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
                <div class="muted">Pesquisa em tempo real via AJAX enquanto digita.</div>
              </div>
            </div>

            <div class="table-responsive">
              <table class="table text-nowrap mb-0" id="tbFor">
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
                      <tr
                        data-id="<?= (int)$r['id'] ?>"
                        data-nome="<?= e((string)$r['nome']) ?>"
                        data-status="<?= e((string)$r['status']) ?>"
                        data-doc="<?= e((string)$r['doc']) ?>"
                        data-tel="<?= e((string)$r['tel']) ?>"
                        data-email="<?= e((string)$r['email']) ?>"
                        data-endereco="<?= e((string)$r['endereco']) ?>"
                        data-cidade="<?= e((string)$r['cidade']) ?>"
                        data-uf="<?= e((string)$r['uf']) ?>"
                        data-contato="<?= e((string)$r['contato']) ?>"
                        data-obs="<?= e((string)$r['obs']) ?>">
                        <td style="font-weight:1000;color:#0f172a;"><?= (int)$r['id'] ?></td>
                        <td>
                          <div style="font-weight:1000;color:#0f172a;line-height:1.1;"><?= e((string)$r['nome']) ?></div>
                          <div class="muted"><?= e(trim((string)$r['contato']) ?: '—') ?></div>
                        </td>
                        <td><?= e(fmt_text((string)$r['doc'])) ?></td>
                        <td><?= e(fmt_text((string)$r['tel'])) ?></td>
                        <td>
                          <?php if (trim((string)$r['email']) !== ''): ?>
                            <a class="link-mini" href="mailto:<?= e((string)$r['email']) ?>"><?= e((string)$r['email']) ?></a>
                          <?php else: ?>
                            —
                          <?php endif; ?>
                        </td>
                        <td><?= e(fmt_loc((string)$r['cidade'], (string)$r['uf'])) ?></td>
                        <td class="text-center"><?= badge_status_html((string)$r['status']) ?></td>
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
                <button class="page-btn" id="btnPrev" type="button" <?= $page <= 1 ? 'disabled' : '' ?> title="Anterior">
                  <i class="lni lni-chevron-left"></i>
                </button>

                <span class="page-info" id="pagerText">Página <?= $page ?>/<?= $pages ?></span>

                <button class="page-btn" id="btnNext" type="button" <?= $page >= $pages ? 'disabled' : '' ?> title="Próxima">
                  <i class="lni lni-chevron-right"></i>
                </button>
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

    const $ = (id) => document.getElementById(id);

    const qFor = $("qFor");
    const fStatus = $("fStatus");
    const btnLimpar = $("btnLimpar");
    const btnNovo = $("btnNovo");
    const btnExcel = $("btnExcel");
    const tbodyFor = $("tbodyFor");
    const hintEmpty = $("hintEmpty");
    const countBadge = $("countBadge");
    const infoCount = $("infoCount");
    const pagerText = $("pagerText");
    const btnPrev = $("btnPrev");
    const btnNext = $("btnNext");
    const pillLoading = $("pillLoading");

    const mdTitle = $("mdTitle");
    const mdSub = $("mdSub");

    const fId = $("fId");
    const fNome = $("fNome");
    const fStatusEdit = $("fStatusEdit");
    const fDoc = $("fDoc");
    const fTel = $("fTel");
    const fEmail = $("fEmail");
    const fEnd = $("fEnd");
    const fCidade = $("fCidade");
    const fUF = $("fUF");
    const fContato = $("fContato");
    const fObs = $("fObs");

    const btnExcluir = $("btnExcluir");
    const frmDelete = $("frmDelete");
    const delId = $("delId");

    const state = {
      q: <?= json_encode($q, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
      status: <?= json_encode($status, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
      page: <?= (int)$page ?>,
      pages: <?= (int)$pages ?>,
      total: <?= (int)$totalRows ?>
    };

    function escapeHtml(str) {
      return String(str ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
    }

    function fmtTextJs(v) {
      const s = String(v ?? '').trim();
      return s !== '' ? s : '—';
    }

    function fmtLocJs(cidade, uf) {
      const c = String(cidade ?? '').trim();
      const u = String(uf ?? '').trim();
      return (c || u) ? (c + (u ? ' / ' + u : '')) : '—';
    }

    function badgeStatusHtml(status) {
      const st = String(status || '').toUpperCase() === 'INATIVO' ? 'INATIVO' : 'ATIVO';
      if (st === 'ATIVO') {
        return '<span class="badge-soft badge-ok">ATIVO</span>';
      }
      return '<span class="badge-soft badge-off">INATIVO</span>';
    }

    function setLoading(on) {
      if (pillLoading) {
        pillLoading.style.display = on ? 'inline-flex' : 'none';
      }
    }

    function syncUrl() {
      const url = new URL(window.location.href);
      url.searchParams.delete('action');
      url.searchParams.delete('export');

      if (state.q) url.searchParams.set('q', state.q);
      else url.searchParams.delete('q');

      if (state.status) url.searchParams.set('status', state.status);
      else url.searchParams.delete('status');

      url.searchParams.set('page', String(state.page));
      window.history.replaceState({}, '', url.toString());
    }

    function updateExcelAction() {
      btnExcel.onclick = () => {
        const params = new URLSearchParams();
        params.set('export', 'excel');
        if (state.q) params.set('q', state.q);
        if (state.status) params.set('status', state.status);
        window.location.href = 'fornecedores.php?' + params.toString();
      };
    }

    function renderMeta(meta) {
      state.q = String(meta.q || '');
      state.status = String(meta.status || '');
      state.page = Number(meta.page || 1);
      state.pages = Number(meta.pages || 1);
      state.total = Number(meta.total || 0);

      countBadge.textContent = `${state.total} fornecedor(es)`;
      infoCount.textContent = `Mostrando ${Number(meta.shown || 0)} item(ns) nesta página de fornecedores. Total filtrado: ${state.total}.`;
      pagerText.textContent = `Página ${state.page}/${state.pages}`;

      btnPrev.disabled = state.page <= 1;
      btnNext.disabled = state.page >= state.pages;

      qFor.value = state.q;
      fStatus.value = state.status;

      syncUrl();
      updateExcelAction();
    }

    function rowHtml(r) {
      const email = String(r.email || '').trim();
      const contato = fmtTextJs(r.contato);
      const loc = fmtLocJs(r.cidade, r.uf);

      return `
        <tr
          data-id="${Number(r.id || 0)}"
          data-nome="${escapeHtml(r.nome || '')}"
          data-status="${escapeHtml(r.status || 'ATIVO')}"
          data-doc="${escapeHtml(r.doc || '')}"
          data-tel="${escapeHtml(r.tel || '')}"
          data-email="${escapeHtml(r.email || '')}"
          data-endereco="${escapeHtml(r.endereco || '')}"
          data-cidade="${escapeHtml(r.cidade || '')}"
          data-uf="${escapeHtml(r.uf || '')}"
          data-contato="${escapeHtml(r.contato || '')}"
          data-obs="${escapeHtml(r.obs || '')}">
          <td style="font-weight:1000;color:#0f172a;">${Number(r.id || 0)}</td>
          <td>
            <div style="font-weight:1000;color:#0f172a;line-height:1.1;">${escapeHtml(r.nome || '')}</div>
            <div class="muted">${escapeHtml(contato)}</div>
          </td>
          <td>${escapeHtml(fmtTextJs(r.doc))}</td>
          <td>${escapeHtml(fmtTextJs(r.tel))}</td>
          <td>${email !== '' ? `<a class="link-mini" href="mailto:${escapeHtml(email)}">${escapeHtml(email)}</a>` : '—'}</td>
          <td>${escapeHtml(loc)}</td>
          <td class="text-center">${badgeStatusHtml(r.status || 'ATIVO')}</td>
          <td class="text-center">
            <button class="main-btn light-btn btn-hover btn-compact" type="button" data-act="edit" data-bs-toggle="modal" data-bs-target="#mdFornecedor">
              <i class="lni lni-pencil me-1"></i> Editar
            </button>
          </td>
        </tr>
      `;
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

    async function loadAjax() {
      setLoading(true);

      const params = new URLSearchParams({
        action: 'ajax',
        q: state.q,
        status: state.status,
        page: String(state.page)
      });

      try {
        const res = await fetch('fornecedores.php?' + params.toString(), {
          headers: {
            'Accept': 'application/json'
          }
        });

        const data = await res.json().catch(() => null);

        if (!data || !data.ok) {
          tbodyFor.innerHTML = `<tr><td colspan="8" class="text-center muted py-4">Erro ao carregar fornecedores.</td></tr>`;
          hintEmpty.style.display = '';
          return;
        }

        renderMeta(data.meta || {});
        const rows = Array.isArray(data.rows) ? data.rows : [];

        tbodyFor.innerHTML = rows.length
          ? rows.map(rowHtml).join('')
          : `<tr><td colspan="8" class="text-center muted py-4">Nenhum fornecedor encontrado.</td></tr>`;

        hintEmpty.style.display = rows.length ? 'none' : '';
      } catch (e) {
        tbodyFor.innerHTML = `<tr><td colspan="8" class="text-center muted py-4">Erro de rede/servidor. Tente novamente.</td></tr>`;
        hintEmpty.style.display = '';
      } finally {
        setLoading(false);
      }
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
      if (!tr || !tr.hasAttribute('data-id')) return;
      openEditFromTr(tr);
      setTimeout(() => fNome.focus(), 150);
    });

    let searchTimer = null;

    qFor.addEventListener("input", (e) => {
      state.q = String(e.target.value || '').trim();
      state.page = 1;
      clearTimeout(searchTimer);
      searchTimer = setTimeout(loadAjax, 250);
    });

    qFor.addEventListener("keydown", (e) => {
      if (e.key === "Escape") {
        qFor.value = "";
        state.q = "";
        state.page = 1;
        loadAjax();
      }
    });

    fStatus.addEventListener("change", () => {
      state.status = String(fStatus.value || '').trim();
      state.page = 1;
      loadAjax();
    });

    btnPrev.addEventListener("click", () => {
      if (state.page <= 1) return;
      state.page -= 1;
      loadAjax();
    });

    btnNext.addEventListener("click", () => {
      if (state.page >= state.pages) return;
      state.page += 1;
      loadAjax();
    });

    btnLimpar.addEventListener("click", () => {
      qFor.value = "";
      fStatus.value = "";
      state.q = "";
      state.status = "";
      state.page = 1;
      loadAjax();
    });

    updateExcelAction();
    syncUrl();
  </script>
</body>

</html>