<?php
// autoErp/public/lavajato/pages/relatorioLavagens.php
declare(strict_types=1);

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

date_default_timezone_set('America/Manaus');

if (session_status() === PHP_SESSION_NONE) session_start();

$menuAtivo = 'relatorios-lavagens';

require_once __DIR__ . '/../../../lib/auth_guard.php';
guard_empresa_user(['dono', 'administrativo', 'caixa', 'estoque']);

$pdo = null;
$pathCon = realpath(__DIR__ . '/../../../conexao/conexao.php');
if ($pathCon && file_exists($pathCon)) require_once $pathCon;

if (!($pdo instanceof PDO)) {
  http_response_code(500);
  die('Conexão indisponível.');
}

require_once __DIR__ . '/../../../lib/util.php';
$empresaNome = empresa_nome_logada($pdo);

// Controller
require_once __DIR__ . '/../controllers/relatorioLavagensController.php';

/* ====== HOJE como padrão (ini/fim) ====== */
$today = new DateTimeImmutable('today');
$ymd   = fn(DateTimeImmutable $d) => $d->format('Y-m-d');

$iniGet = $_GET['ini'] ?? null;
$fimGet = $_GET['fim'] ?? null;

// Se não vier nada (ou vier vazio), assume hoje
$iniDefault = $ymd($today);
$fimDefault = $ymd($today);

$ini = (is_string($iniGet) && trim($iniGet) !== '') ? $iniGet : $iniDefault;
$fim = (is_string($fimGet) && trim($fimGet) !== '') ? $fimGet : $fimDefault;

/* -------- Filtros (GET) -------- */
$filtros = [
  'ini'       => $ini,
  'fim'       => $fim,
  'status'    => $_GET['status']  ?? 'concluida', // concluida (padrão), aberta, cancelada, todos
  'forma'     => $_GET['forma']   ?? '',
  'lavador'   => $_GET['lavador'] ?? '',          // nome ou CPF
  'servico'   => $_GET['servico'] ?? '',
];

$vm = relatorio_lavagens_viewmodel($pdo, $filtros);

/* Helpers */
$fmtMoney = fn($n) => 'R$ ' . number_format((float)$n, 2, ',', '.');
$val = function ($k, $d = '') use ($vm) {
  return htmlspecialchars((string)($vm['filtros'][$k] ?? $d), ENT_QUOTES, 'UTF-8');
};

/** Formata data/hora com segurança (aceita timestamp numérico ou string) */
$fmtDateTime = function ($val): string {
  if ($val === null || $val === '') return '-';
  $ts = is_numeric($val) ? (int)$val : strtotime((string)$val);
  return $ts ? date('d/m/Y H:i', $ts) : '-';
};

/** Pega o melhor campo de data disponível no item */
$getQuandoField = function (array $r) {
  return $r['quando_raw']
    ?? $r['quando']
    ?? $r['checkout_at']
    ?? $r['checkin_at']
    ?? $r['criado_em']
    ?? null;
};

/* Links rápidos (mantém demais filtros) */
$mkLink = function (string $ini, string $fim, array $vm) {
  $q = [
    'ini'     => $ini,
    'fim'     => $fim,
    'status'  => $vm['filtros']['status']  ?? 'concluida',
    'forma'   => $vm['filtros']['forma']   ?? '',
    'lavador' => $vm['filtros']['lavador'] ?? '',
    'servico' => $vm['filtros']['servico'] ?? '',
  ];
  return '?' . http_build_query($q);
};

$linkHoje = $mkLink($ymd($today), $ymd($today), $vm);
$link7    = $mkLink($ymd($today->modify('-6 days')),  $ymd($today), $vm);
$link15   = $mkLink($ymd($today->modify('-14 days')), $ymd($today), $vm);
$link30   = $mkLink($ymd($today->modify('-29 days')), $ymd($today), $vm);
$linkMes  = $mkLink(
  $ymd($today->modify('first day of this month')),
  $ymd($today->modify('last day of this month')),
  $vm
);

// Limpar: volta pra HOJE + padrão
$linkLimpar = '?' . http_build_query([
  'ini' => $iniDefault,
  'fim' => $fimDefault,
  'status' => 'concluida',
  'forma' => '',
  'lavador' => '',
  'servico' => '',
]);

/* ===== Exportações (CSV Excel e XLS HTML) ===== */
$allRows = $vm['linhas'] ?? [];

/* Export CSV */
if (($_GET['export'] ?? '') === 'csv') {
  header('Content-Type: text/csv; charset=UTF-8');
  header('Content-Disposition: attachment; filename="lavagens_' . date('Ymd_His') . '.csv"');

  echo "\xEF\xBB\xBF";
  echo "sep=;\n";

  $out = fopen('php://output', 'w');
  fputcsv($out, ['Data', 'Lavador', 'Serviço', 'Veículo', 'Forma', 'Valor', 'Status'], ';');

  foreach ($allRows as $r) {
    $quando = $getQuandoField($r);
    $valor  = (float)($r['valor'] ?? 0);

    fputcsv($out, [
      $fmtDateTime($quando),
      (string)($r['lavador'] ?? ''),
      (string)($r['servico'] ?? ''),
      (string)($r['veiculo'] ?? ''),
      (string)($r['forma_pagamento'] ?? ''),
      number_format($valor, 2, ',', '.'),
      (string)($r['status'] ?? ''),
    ], ';');
  }

  fclose($out);
  exit;
}

/* Export XLS (HTML) */
if (($_GET['export'] ?? '') === 'xls') {
  header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
  header('Content-Disposition: attachment; filename="relatorio_lavagens_' . date('Ymd_His') . '.xls"');

  echo "\xEF\xBB\xBF";

  $iniX = (string)($vm['filtros']['ini'] ?? '');
  $fimX = (string)($vm['filtros']['fim'] ?? '');
  $statusX  = (string)($vm['filtros']['status'] ?? '');
  $formaX   = (string)($vm['filtros']['forma'] ?? '');
  $lavadorX = (string)($vm['filtros']['lavador'] ?? '');
  $servicoX = (string)($vm['filtros']['servico'] ?? '');

  $periodoTxt = ($iniX && $fimX) ? ($iniX . ' a ' . $fimX) : '—';

  $totalGeral = 0.0;
  foreach ($allRows as $r) $totalGeral += (float)($r['valor'] ?? 0);
?>
  <html>

  <head>
    <meta charset="utf-8">
    <style>
      body {
        font-family: Calibri, Arial, sans-serif;
      }

      table {
        border-collapse: collapse;
        width: 100%;
      }

      td,
      th {
        border: 1px solid #cbd5e1;
        padding: 6px 8px;
        font-size: 11pt;
      }

      .title1 {
        font-size: 16pt;
        font-weight: 700;
        border: none;
        padding: 2px 0 6px;
      }

      .title2 {
        font-size: 12pt;
        font-weight: 700;
        border: none;
        padding: 0 0 10px;
        color: #334155;
      }

      .meta td {
        border: none;
        padding: 2px 0;
        font-size: 10.5pt;
        color: #334155;
      }

      .meta b {
        color: #0f172a;
      }

      .head th {
        background: #2563eb;
        color: #fff;
        font-weight: 700;
      }

      .money {
        mso-number-format: "\\#\\,\\#\\#0\\,00";
        text-align: right;
      }

      .text {
        mso-number-format: "\\@";
      }

      .right {
        text-align: right;
      }

      .footerTotal td {
        font-weight: 700;
        background: #f1f5f9;
      }
    </style>
  </head>

  <body>
    <table>
      <tr>
        <td class="title1" colspan="8"><?= htmlspecialchars((string)$empresaNome, ENT_QUOTES, 'UTF-8') ?></td>
      </tr>
      <tr>
        <td class="title2" colspan="8">Relatório de Lavagens</td>
      </tr>
    </table>

    <table class="meta" style="margin-bottom:10px;">
      <tr>
        <td><b>Período:</b> <?= htmlspecialchars($periodoTxt, ENT_QUOTES, 'UTF-8') ?></td>
        <td class="right"><b>Gerado em:</b> <?= date('d/m/Y H:i') ?></td>
      </tr>
      <tr>
        <td colspan="2">
          <b>Filtros:</b>
          Status: <?= htmlspecialchars($statusX ?: '—', ENT_QUOTES, 'UTF-8') ?> |
          Forma: <?= htmlspecialchars($formaX ?: 'Todas', ENT_QUOTES, 'UTF-8') ?> |
          Lavador: <?= htmlspecialchars($lavadorX ?: '—', ENT_QUOTES, 'UTF-8') ?> |
          Serviço: <?= htmlspecialchars($servicoX ?: '—', ENT_QUOTES, 'UTF-8') ?> |
        </td>
      </tr>
    </table>

    <table>
      <tr class="head">
        <th style="width:160px;">Data</th>
        <th>Lavador</th>
        <th>Serviço</th>
        <th>Veículo</th>
        <th style="width:130px;">Forma</th>
        <th style="width:110px;" class="right">Valor</th>
        <th style="width:110px;">Status</th>
      </tr>

      <?php foreach ($allRows as $r): ?>
        <?php
        $quando = $getQuandoField($r);
        $valor  = (float)($r['valor'] ?? 0);
        ?>
        <tr>
          <td class="text"><?= htmlspecialchars($fmtDateTime($quando), ENT_QUOTES, 'UTF-8') ?></td>
          <td class="text"><?= htmlspecialchars((string)($r['lavador'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
          <td class="text"><?= htmlspecialchars((string)($r['servico'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
          <td class="text"><?= htmlspecialchars((string)($r['veiculo'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
          <td class="text"><?= htmlspecialchars((string)($r['forma_pagamento'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
          <td class="money"><?= htmlspecialchars(number_format($valor, 2, ',', '.'), ENT_QUOTES, 'UTF-8') ?></td>
          <td class="text"><?= htmlspecialchars((string)($r['status'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
        </tr>
      <?php endforeach; ?>

      <tr class="footerTotal">
        <td colspan="6" class="right">TOTAL GERAL</td>
        <td class="money"><?= htmlspecialchars(number_format($totalGeral, 2, ',', '.'), ENT_QUOTES, 'UTF-8') ?></td>
        <td></td>
      </tr>
    </table>

  </body>

  </html>
<?php
  exit;
}

/* -------- Paginação (5 por página) -------- */
$perPage = 5;
$page    = max(1, (int)($_GET['page'] ?? 1));
$total   = count($allRows);
$pages   = max(1, (int)ceil($total / $perPage));
if ($page > $pages) $page = $pages;
$offset  = ($page - 1) * $perPage;
$rows    = array_slice($allRows, $offset, $perPage);

// ✅ baseParams sem placa
$baseParams = [
  'ini'     => $vm['filtros']['ini']     ?? $iniDefault,
  'fim'     => $vm['filtros']['fim']     ?? $fimDefault,
  'status'  => $vm['filtros']['status']  ?? 'concluida',
  'forma'   => $vm['filtros']['forma']   ?? '',
  'lavador' => $vm['filtros']['lavador'] ?? '',
  'servico' => $vm['filtros']['servico'] ?? '',
];

$buildPageUrl = function (int $p) use ($baseParams) {
  return '?' . http_build_query(array_merge($baseParams, ['page' => $p]));
};

$showFrom = $total ? ($offset + 1) : 0;
$showTo   = min($offset + $perPage, $total);
?>
<!doctype html>
<html lang="pt-BR" dir="ltr">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>AutoERP — Relatório de Lavagens</title>
  <link rel="icon" type="image/png" href="../../assets/images/dashboard/icon.png">
  <link rel="stylesheet" href="../../assets/css/core/libs.min.css">
  <link rel="stylesheet" href="../../assets/vendor/aos/dist/aos.css">
  <link rel="stylesheet" href="../../assets/css/hope-ui.min.css?v=4.0.0">
  <link rel="stylesheet" href="../../assets/css/custom.min.css?v=4.0.0">
  <link rel="stylesheet" href="../../assets/css/dark.min.css">
  <link rel="stylesheet" href="../../assets/css/customizer.min.css">
  <link rel="stylesheet" href="../../assets/css/customizer.css">
  <link rel="stylesheet" href="../../assets/css/rtl.min.css">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
  <style>
    .table thead th {
      white-space: nowrap;
    }

    .section-title {
      font-size: .95rem;
      font-weight: 600;
      margin-bottom: .5rem
    }

    .empty {
      color: #94a3b8;
      text-align: center;
      padding: 1rem
    }

    .chart-wrap {
      max-width: 560px;
      width: 100%;
      margin: 0 auto
    }

    .chart-wrap canvas {
      display: block;
      width: 100% !important;
      height: auto !important
    }
  </style>
</head>

<body>
  <?php include __DIR__ . '/../../layouts/sidebar.php'; ?>

  <main class="main-content">
    <div class="position-relative iq-banner">
      <nav class="nav navbar navbar-expand-lg navbar-light iq-navbar">
        <div class="container-fluid navbar-inner">
          <a href="../../dashboard.php" class="navbar-brand">
            <h4 class="logo-title">AutoERP</h4>
          </a>
          <div class="input-group search-input">
            <span class="input-group-text" id="search-input">
              <svg class="icon-18" width="18" viewBox="0 0 24 24" fill="none"></svg>
            </span>
          </div>
        </div>
      </nav>

      <div class="iq-navbar-header" style="height:140px; margin-bottom:50px;">
        <div class="container-fluid iq-container">
          <div class="row">
            <div class="col-12 d-flex align-items-center justify-content-between">
              <div>
                <h1 class="mb-0">Relatório de Lavagens</h1>
                <p>Analítico por período, status, lavador, serviço e forma.</p>
                <?php if (!empty($vm['msg'])): ?>
                  <div class="alert alert-<?= !empty($vm['err']) ? 'danger' : 'success' ?> py-2 mb-0">
                    <?= htmlspecialchars((string)$vm['msg'], ENT_QUOTES, 'UTF-8') ?>
                  </div>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>
        <div class="iq-header-img">
          <img src="../../assets/images/dashboard/top-header.png" class="img-fluid w-100 h-100 animated-scaleX" alt="">
        </div>
      </div>
    </div>

    <div class="container-fluid content-inner mt-n3 py-0">
      <div class="card shadow-sm mb-3">
        <div class="card-body">
          <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
            <div class="text-muted small">Selecione um período ou personalize nos filtros.</div>

            <?php
            $iniAtual = (string)($vm['filtros']['ini'] ?? '');
            $fimAtual = (string)($vm['filtros']['fim'] ?? '');

            $iniHoje = $ymd($today);
            $fimHoje = $ymd($today);

            $ini7  = $ymd($today->modify('-6 days'));
            $ini15 = $ymd($today->modify('-14 days'));
            $ini30 = $ymd($today->modify('-29 days'));

            $iniMes = $ymd($today->modify('first day of this month'));
            $fimMes = $ymd($today->modify('last day of this month'));

            $isHoje = ($iniAtual === $iniHoje && $fimAtual === $fimHoje);
            $is7    = ($iniAtual === $ini7    && $fimAtual === $fimHoje);
            $is15   = ($iniAtual === $ini15   && $fimAtual === $fimHoje);
            $is30   = ($iniAtual === $ini30   && $fimAtual === $fimHoje);
            $isMes  = ($iniAtual === $iniMes  && $fimAtual === $fimMes);

            $cls = fn(bool $active) => $active ? 'btn btn-sm btn-primary' : 'btn btn-sm btn-outline-primary';
            ?>

            <div class="d-flex flex-wrap gap-1">
              <a class="<?= $cls($isHoje) ?>" href="<?= htmlspecialchars($linkHoje, ENT_QUOTES, 'UTF-8') ?>">Hoje</a>
              <a class="<?= $cls($is7) ?>" href="<?= htmlspecialchars($link7, ENT_QUOTES, 'UTF-8') ?>">7d</a>
              <a class="<?= $cls($is15) ?>" href="<?= htmlspecialchars($link15, ENT_QUOTES, 'UTF-8') ?>">15d</a>
              <a class="<?= $cls($is30) ?>" href="<?= htmlspecialchars($link30, ENT_QUOTES, 'UTF-8') ?>">30d</a>
              <a class="<?= $cls($isMes) ?>" href="<?= htmlspecialchars($linkMes, ENT_QUOTES, 'UTF-8') ?>">
                <i class="bi bi-calendar3"></i> Mês atual
              </a>
            </div>
          </div>

          <form class="row g-2 align-items-end mt-2" method="get">
            <div class="col-6 col-md-2">
              <label class="form-label">Início</label>
              <input type="date" name="ini" class="form-control form-control-sm" value="<?= $val('ini', $iniDefault) ?>">
            </div>
            <div class="col-6 col-md-2">
              <label class="form-label">Fim</label>
              <input type="date" name="fim" class="form-control form-control-sm" value="<?= $val('fim', $fimDefault) ?>">
            </div>
            <div class="col-6 col-md-2">
              <label class="form-label">Status</label>
              <select name="status" class="form-select form-select-sm">
                <?php foreach (($vm['selects']['statuses'] ?? []) as $s): ?>
                  <option value="<?= htmlspecialchars($s, ENT_QUOTES, 'UTF-8') ?>" <?= ($vm['filtros']['status'] ?? 'concluida') === $s ? 'selected' : '' ?>>
                    <?= ucfirst($s) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-6 col-md-2">
              <label class="form-label">Forma</label>
              <select name="forma" class="form-select form-select-sm">
                <option value="">Todas</option>
                <?php foreach (($vm['selects']['formas'] ?? []) as $fp): ?>
                  <option value="<?= htmlspecialchars($fp, ENT_QUOTES, 'UTF-8') ?>" <?= ($vm['filtros']['forma'] ?? '') === $fp ? 'selected' : '' ?>>
                    <?= htmlspecialchars($fp, ENT_QUOTES, 'UTF-8') ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-6 col-md-2">
              <label class="form-label">Lavador (nome/CPF)</label>
              <input type="text" name="lavador" class="form-control form-control-sm" list="lavadoresList" value="<?= $val('lavador') ?>">
              <datalist id="lavadoresList">
                <?php foreach (($vm['selects']['lavadores'] ?? []) as $ld): ?>
                  <option value="<?= htmlspecialchars($ld['nome'] ?: $ld['cpf'], ENT_QUOTES, 'UTF-8') ?>">
                  <?php endforeach; ?>
              </datalist>
            </div>
            <div class="col-6 col-md-2">
              <label class="form-label">Serviço</label>
              <input type="text" name="servico" class="form-control form-control-sm" list="servicosList" value="<?= $val('servico') ?>">
              <datalist id="servicosList">
                <?php foreach (($vm['selects']['servicos'] ?? []) as $sx): ?>
                  <option value="<?= htmlspecialchars($sx, ENT_QUOTES, 'UTF-8') ?>">
                  <?php endforeach; ?>
              </datalist>
            </div>

            <div class="col-12 d-flex flex-wrap gap-2 mt-1">
              <button class="btn btn-sm btn-primary"><i class="bi bi-search"></i> Aplicar</button>

              <a class="btn btn-sm btn-outline-secondary" href="<?= htmlspecialchars($linkLimpar, ENT_QUOTES, 'UTF-8') ?>">
                <i class="bi bi-x-lg"></i> Limpar
              </a>
              <a class="btn btn-sm btn-outline-primary"
                href="relatorioLavagensNota.php?<?= htmlspecialchars(http_build_query(array_merge($baseParams, ['auto' => 1, 'return' => 'relatorioLavagens.php'])), ENT_QUOTES, 'UTF-8') ?>">
                <i class="bi bi-receipt"></i> Imprimir Nota (Relatório)
              </a>
            </div>
          </form>

        </div>
      </div>

      <!-- KPIs -->
      <div class="row g-3 mb-3">
        <div class="col-12 col-md-6 col-lg">
          <div class="card shadow-sm">
            <div class="card-body">
              <div class="text-muted small"><i class="bi bi-basket"></i> Lavagens</div>
              <div class="fs-5 fw-bold"><?= (int)($vm['resumo']['qtd'] ?? 0) ?></div>
            </div>
          </div>
        </div>
        <div class="col-12 col-md-6 col-lg">
          <div class="card shadow-sm">
            <div class="card-body">
              <div class="text-muted small"><i class="bi bi-currency-dollar"></i> Total</div>
              <div class="fs-5 fw-bold"><?= $fmtMoney($vm['resumo']['total'] ?? 0) ?></div>
            </div>
          </div>
        </div>
        <div class="col-12 col-md-6 col-lg">
          <div class="card shadow-sm">
            <div class="card-body">
              <div class="text-muted small"><i class="bi bi-people"></i> Lavadores únicos</div>
              <div class="fs-5 fw-bold"><?= (int)($vm['resumo']['lavadores'] ?? 0) ?></div>
            </div>
          </div>
        </div>
        <div class="col-12 col-md-6 col-lg">
          <div class="card shadow-sm">
            <div class="card-body">
              <div class="text-muted small"><i class="bi bi-graph-up"></i> Ticket Médio</div>
              <div class="fs-5 fw-bold"><?= $fmtMoney($vm['ticket_medio'] ?? 0) ?></div>
            </div>
          </div>
        </div>
      </div>

      <!-- Resumos -->
      <div class="row g-3">
        <div class="col-12 col-lg-6">
          <div class="card shadow-sm h-100">
            <div class="card-body">
              <div class="section-title">Por Lavador</div>
              <div class="table-responsive">
                <table class="table table-sm table-striped align-middle">
                  <thead>
                    <tr>
                      <th>Lavador</th>
                      <th class="text-center" style="width:120px">Qtd</th>
                      <th class="text-end" style="width:160px">Total</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php if (empty($vm['porLavador'])): ?>
                      <tr>
                        <td colspan="3" class="empty">Sem dados.</td>
                      </tr>
                      <?php else: foreach ($vm['porLavador'] as $r): ?>
                        <tr>
                          <td><?= htmlspecialchars((string)($r['lavador'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
                          <td class="text-center"><?= (int)($r['qtd'] ?? 0) ?></td>
                          <td class="text-end"><?= $fmtMoney($r['total'] ?? 0) ?></td>
                        </tr>
                    <?php endforeach;
                    endif; ?>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>

        <div class="col-12 col-lg-6">
          <div class="card shadow-sm h-100">
            <div class="card-body">
              <div class="section-title">Por Serviço</div>
              <div class="table-responsive">
                <table class="table table-sm table-striped align-middle">
                  <thead>
                    <tr>
                      <th>Serviço</th>
                      <th class="text-center" style="width:120px">Qtd</th>
                      <th class="text-end" style="width:160px">Total</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php if (empty($vm['porServico'])): ?>
                      <tr>
                        <td colspan="3" class="empty">Sem dados.</td>
                      </tr>
                      <?php else: foreach ($vm['porServico'] as $r): ?>
                        <tr>
                          <td><?= htmlspecialchars((string)($r['servico'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
                          <td class="text-center"><?= (int)($r['qtd'] ?? 0) ?></td>
                          <td class="text-end"><?= $fmtMoney($r['total'] ?? 0) ?></td>
                        </tr>
                    <?php endforeach;
                    endif; ?>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Lista com paginação -->
      <div class="card shadow-sm mt-3">
        <div class="card-body">
          <div class="d-flex align-items-center justify-content-between">
            <h6 class="mb-2">Lavagens no Período</h6>
            <div class="text-muted small">Mostrando <strong><?= (int)$showFrom ?></strong>–<strong><?= (int)$showTo ?></strong> de <strong><?= (int)$total ?></strong></div>
          </div>

          <div class="table-responsive">
            <table class="table table-striped align-middle">
              <thead>
                <tr>
                  <th style="width:170px">Data</th>
                  <th>Lavador</th>
                  <th>Serviço</th>
                  <th>Veículo</th>
                  <th style="width:120px">Pagamento</th>
                  <th class="text-end" style="width:120px">Valor</th>
                  <th style="width:110px">Status</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($rows)): ?>
                  <tr>
                    <td colspan="7" class="text-center text-muted">Nenhuma lavagem encontrada.</td>
                  </tr>
                  <?php else: foreach ($rows as $r): ?>
                    <tr>
                      <td><?= htmlspecialchars($fmtDateTime($getQuandoField($r)), ENT_QUOTES, 'UTF-8') ?></td>
                      <td><?= htmlspecialchars((string)($r['lavador'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                      <td><?= htmlspecialchars((string)($r['servico'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                      <td><?= htmlspecialchars((string)($r['veiculo'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                      <td><?= htmlspecialchars((string)($r['forma_pagamento'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                      <td class="text-end"><?= $fmtMoney($r['valor'] ?? 0) ?></td>
                      <td>
                        <?php $stt = (string)($r['status'] ?? ''); ?>
                        <span class="badge bg-<?= $stt === 'concluida' ? 'success' : ($stt === 'cancelada' ? 'secondary' : 'warning') ?>">
                          <?= ucfirst($stt) ?>
                        </span>
                      </td>
                    </tr>
                <?php endforeach;
                endif; ?>
              </tbody>
            </table>
          </div>

          <?php if ($pages > 1): ?>
            <nav aria-label="Paginação">
              <ul class="pagination justify-content-end mb-0">
                <li class="page-item <?= ($page <= 1 ? 'disabled' : '') ?>">
                  <a class="page-link" href="<?= $page > 1 ? htmlspecialchars($buildPageUrl($page - 1), ENT_QUOTES, 'UTF-8') : '#' ?>">Anterior</a>
                </li>
                <?php
                $start = max(1, $page - 2);
                $end   = min($pages, $start + 4);
                $start = max(1, $end - 4);
                for ($p = $start; $p <= $end; $p++): ?>
                  <li class="page-item <?= ($p === $page ? 'active' : '') ?>">
                    <a class="page-link" href="<?= htmlspecialchars($buildPageUrl($p), ENT_QUOTES, 'UTF-8') ?>"><?= $p ?></a>
                  </li>
                <?php endfor; ?>
                <li class="page-item <?= ($page >= $pages ? 'disabled' : '') ?>">
                  <a class="page-link" href="<?= $page < $pages ? htmlspecialchars($buildPageUrl($page + 1), ENT_QUOTES, 'UTF-8') : '#' ?>">Próxima</a>
                </li>
              </ul>
            </nav>
          <?php endif; ?>
        </div>
      </div>

      <!-- Gráficos -->
      <div class="row g-3 mt-3">
        <div class="col-12 col-lg-6">
          <div class="card shadow-sm h-100">
            <div class="card-body">
              <h6 class="mb-2">Receita por Dia</h6>
              <div class="chart-wrap"><canvas id="chartDia"></canvas></div>
            </div>
          </div>
        </div>
        <div class="col-12 col-lg-6">
          <div class="card shadow-sm h-100">
            <div class="card-body">
              <h6 class="mb-2">Receita por Forma de Pagamento</h6>
              <div class="chart-wrap"><canvas id="chartFormaBar"></canvas></div>
            </div>
          </div>
        </div>
      </div>

    </div>

    <footer class="footer">
      <div class="footer-body d-flex justify-content-between align-items-center">
        <div class="left-panel">© <script>
            document.write(new Date().getFullYear())
          </script> <?= htmlspecialchars((string)$empresaNome, ENT_QUOTES, 'UTF-8') ?></div>
        <div class="right-panel">Desenvolvido por L&J Soluções Tecnológicas.</div>
      </div>
    </footer>
  </main>

  <script>
    const porDia = <?= json_encode($vm['porDia'] ?? [], JSON_UNESCAPED_UNICODE) ?>;
    const porForma = <?= json_encode($vm['porForma'] ?? [], JSON_UNESCAPED_UNICODE) ?>;

    const brl = (v) => 'R$ ' + Number(v ?? 0).toLocaleString('pt-BR');

    (function() {
      const el = document.getElementById('chartDia');
      if (!el) return;

      const labels = porDia.map(d => new Date(d.dia).toLocaleDateString('pt-BR'));
      const data = porDia.map(d => Number(d.total));

      const ctx = el.getContext('2d');

      const grad = ctx.createLinearGradient(0, 0, 0, 300);
      grad.addColorStop(0, 'rgba(99,102,241,.30)');
      grad.addColorStop(1, 'rgba(99,102,241,0)');

      new Chart(el, {
        type: 'line',
        data: {
          labels,
          datasets: [{
            label: 'Total (R$)',
            data,
            borderWidth: 2,
            borderColor: 'rgba(99,102,241,1)',
            backgroundColor: grad,
            fill: true,
            tension: .35,
            cubicInterpolationMode: 'monotone',
            pointRadius: 2.5
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: true,
          aspectRatio: 2.1,
          plugins: {
            legend: {
              display: false
            },
            tooltip: {
              callbacks: {
                label: (c) => brl(c.parsed.y)
              }
            }
          },
          scales: {
            x: {
              grid: {
                display: false
              }
            },
            y: {
              grid: {
                color: 'rgba(148,163,184,.15)',
                drawBorder: false
              },
              ticks: {
                callback: (v) => brl(v)
              }
            }
          }
        }
      });
    })();

    (function() {
      const el = document.getElementById('chartFormaBar');
      if (!el) return;

      const labels = porForma.map(f => f.forma || '—');
      const data = porForma.map(f => Number(f.total));

      new Chart(el, {
        type: 'bar',
        data: {
          labels,
          datasets: [{
            label: 'Total (R$)',
            data,
            borderWidth: 0,
            borderRadius: 8,
            barThickness: 18,
            maxBarThickness: 22,
            categoryPercentage: .7,
            barPercentage: .9
          }]
        },
        options: {
          indexAxis: 'y',
          responsive: true,
          maintainAspectRatio: true,
          aspectRatio: 2.1,
          plugins: {
            legend: {
              display: false
            },
            tooltip: {
              callbacks: {
                label: (c) => brl(c.parsed.x)
              }
            }
          },
          scales: {
            x: {
              grid: {
                color: 'rgba(148,163,184,.15)',
                drawBorder: false
              },
              ticks: {
                callback: (v) => brl(v)
              }
            },
            y: {
              grid: {
                display: false
              }
            }
          }
        }
      });
    })();
  </script>

  <script src="../../assets/js/core/libs.min.js"></script>
  <script src="../../assets/js/core/external.min.js"></script>
  <script src="../../assets/vendor/aos/dist/aos.js"></script>
  <script src="../../assets/js/hope-ui.js" defer></script>
</body>

</html>