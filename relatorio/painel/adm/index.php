<?php

declare(strict_types=1);
session_start();

/* Obrigatório estar logado */
if (empty($_SESSION['usuario_logado'])) {
  header('Location: ../../index.php');
  exit;
}

/* Obrigatório ser ADMIN */
$perfis = $_SESSION['perfis'] ?? [];
if (!is_array($perfis)) $perfis = [$perfis];
if (!in_array('ADMIN', $perfis, true)) {
  header('Location: ../operador/index.php');
  exit;
}

$nomeTopo = $_SESSION['usuario_nome'] ?? 'Admin';

/* Timezone */
date_default_timezone_set('America/Manaus');

/* Helpers (APENAS UMA VEZ) */
function h($s): string {
  return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}
function money($n): string {
  return number_format((float)$n, 2, ',', '.');
}
function num3($n): string {
  return number_format((float)$n, 3, ',', '.');
}

/* DB */
require '../../assets/php/conexao.php'; // ajuste se o caminho do seu index for diferente
$pdo = db();

/* === Feiras do dashboard === */
$feiras = [
  1 => 'Feira do Produtor',
  2 => 'Feira Alternativa',
  3 => 'Mercado Municipal',
];
$ids = array_keys($feiras);
$in  = implode(',', array_fill(0, count($ids), '?'));

/* Datas */
$today      = date('Y-m-d');
$monthStart = date('Y-m-01');
$monthEnd   = date('Y-m-t');

/* Base stats */
$stats = [];
foreach ($feiras as $fid => $nome) {
  $stats[$fid] = [
    'nome' => $nome,
    'hoje_total' => 0.0,
    'hoje_qtd'   => 0,
    'mes_total'  => 0.0,
    'mes_qtd'    => 0,
  ];
}

$geralHoje = 0.0; $geralHojeQtd = 0;
$geralMes  = 0.0; $geralMesQtd  = 0;
$itensHoje = 0.0;

$labelsDias = [];
$serieTotal = [];
$seriePorFeira = [];
foreach ($ids as $fid) $seriePorFeira[$fid] = [];

$topProdutos = [];
$ultimasVendas = [];

try {
  /* Hoje por feira */
  $st = $pdo->prepare("
    SELECT feira_id, COUNT(*) qtd, COALESCE(SUM(total),0) total
    FROM vendas
    WHERE feira_id IN ($in)
      AND DATE(data_hora) = ?
      AND UPPER(status) <> 'CANCELADA'
    GROUP BY feira_id
  ");
  $st->execute([...$ids, $today]);
  foreach ($st->fetchAll(\PDO::FETCH_ASSOC) as $r) {
    $fid = (int)$r['feira_id'];
    if (!isset($stats[$fid])) continue;
    $stats[$fid]['hoje_qtd']   = (int)$r['qtd'];
    $stats[$fid]['hoje_total'] = (float)$r['total'];
  }

  /* Mês por feira */
  $st = $pdo->prepare("
    SELECT feira_id, COUNT(*) qtd, COALESCE(SUM(total),0) total
    FROM vendas
    WHERE feira_id IN ($in)
      AND DATE(data_hora) BETWEEN ? AND ?
      AND UPPER(status) <> 'CANCELADA'
    GROUP BY feira_id
  ");
  $st->execute([...$ids, $monthStart, $monthEnd]);
  foreach ($st->fetchAll(\PDO::FETCH_ASSOC) as $r) {
    $fid = (int)$r['feira_id'];
    if (!isset($stats[$fid])) continue;
    $stats[$fid]['mes_qtd']   = (int)$r['qtd'];
    $stats[$fid]['mes_total'] = (float)$r['total'];
  }

  /* Itens hoje (todas) */
  $st = $pdo->prepare("
    SELECT COALESCE(SUM(vi.quantidade),0) itens
    FROM venda_itens vi
    JOIN vendas v ON v.feira_id = vi.feira_id AND v.id = vi.venda_id
    WHERE vi.feira_id IN ($in)
      AND DATE(v.data_hora) = ?
      AND UPPER(v.status) <> 'CANCELADA'
  ");
  $st->execute([...$ids, $today]);
  $itensHoje = (float)($st->fetchColumn() ?? 0);

  /* Totais gerais */
  foreach ($stats as $s) {
    $geralHoje += $s['hoje_total'];
    $geralHojeQtd += $s['hoje_qtd'];
    $geralMes  += $s['mes_total'];
    $geralMesQtd  += $s['mes_qtd'];
  }

  /* Série diária (mês): total + por feira */
  $st = $pdo->prepare("
    SELECT DATE(v.data_hora) dia, v.feira_id, COALESCE(SUM(v.total),0) total
    FROM vendas v
    WHERE v.feira_id IN ($in)
      AND DATE(v.data_hora) BETWEEN ? AND ?
      AND UPPER(v.status) <> 'CANCELADA'
    GROUP BY dia, v.feira_id
    ORDER BY dia ASC
  ");
  $st->execute([...$ids, $monthStart, $monthEnd]);
  $rows = $st->fetchAll(\PDO::FETCH_ASSOC);

  $map = [];
  foreach ($rows as $r) {
    $dia = (string)$r['dia'];
    $fid = (int)$r['feira_id'];
    $map[$dia][$fid] = (float)$r['total'];
  }

  $d = new DateTime($monthStart);
  $end = new DateTime($monthEnd);
  while ($d <= $end) {
    $dia = $d->format('Y-m-d');
    $labelsDias[] = $d->format('d/m');

    $totalDia = 0.0;
    foreach ($ids as $fid) {
      $val = (float)($map[$dia][$fid] ?? 0.0);
      $seriePorFeira[$fid][] = $val;
      $totalDia += $val;
    }
    $serieTotal[] = $totalDia;
    $d->modify('+1 day');
  }

  /* Top produtos (mês) – todas as feiras */
  $st = $pdo->prepare("
    SELECT
      p.nome AS produto,
      COALESCE(SUM(vi.quantidade),0) itens,
      COALESCE(SUM(vi.subtotal),0) total
    FROM venda_itens vi
    JOIN vendas v ON v.feira_id = vi.feira_id AND v.id = vi.venda_id
    JOIN produtos p ON p.feira_id = vi.feira_id AND p.id = vi.produto_id
    WHERE vi.feira_id IN ($in)
      AND DATE(v.data_hora) BETWEEN ? AND ?
      AND UPPER(v.status) <> 'CANCELADA'
    GROUP BY p.nome
    ORDER BY total DESC
    LIMIT 10
  ");
  $st->execute([...$ids, $monthStart, $monthEnd]);
  $topProdutos = $st->fetchAll(\PDO::FETCH_ASSOC);

  /* Últimas vendas (todas) */
  $st = $pdo->prepare("
    SELECT
      v.id, v.feira_id, v.data_hora, v.total,
      COALESCE(NULLIF(TRIM(v.forma_pagamento),''),'—') forma_pagamento,
      COALESCE(NULLIF(TRIM(v.status),''),'—') status
    FROM vendas v
    WHERE v.feira_id IN ($in)
    ORDER BY v.data_hora DESC
    LIMIT 12
  ");
  $st->execute($ids);
  $ultimasVendas = $st->fetchAll(\PDO::FETCH_ASSOC);

} catch (Throwable $e) {
  // se algo falhar, não quebra a página
}

$ticketHoje = ($geralHojeQtd > 0) ? ($geralHoje / $geralHojeQtd) : 0.0;
$ticketMes  = ($geralMesQtd > 0)  ? ($geralMes / $geralMesQtd)   : 0.0;

/* Dados para gráfico de pizza (participação mensal por feira) */
$pieLabels = [];
$pieData = [];
foreach ($stats as $s) {
  $pieLabels[] = $s['nome'];
  $pieData[] = (float)$s['mes_total'];
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <!-- Required meta tags -->
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <title>SIGRelatórios Admin</title>
  <!-- plugins:css -->
  <link rel="stylesheet" href="../../vendors/feather/feather.css">
  <link rel="stylesheet" href="../../vendors/ti-icons/css/themify-icons.css">
  <link rel="stylesheet" href="../../vendors/css/vendor.bundle.base.css">
  <!-- endinject -->
  <!-- Plugin css for this page -->
  <link rel="stylesheet" href="../../vendors/datatables.net-bs4/dataTables.bootstrap4.css">
  <link rel="stylesheet" href="../../vendors/ti-icons/css/themify-icons.css">
  <link rel="stylesheet" type="text/css" href="../../js/select.dataTables.min.css">
  <!-- End plugin css for this page -->
  <!-- inject:css -->
  <link rel="stylesheet" href="../../css/vertical-layout-light/style.css">
  <!-- endinject -->
  <link rel="shortcut icon" href="../../images/3.png" />
  <style>
    .nav-link.text-black:hover {
      color: blue !important;
    }
  </style>
</head>
<script>
  // ===== dados vindos do PHP =====
  const labelsDias = <?= json_encode($labelsDias, JSON_UNESCAPED_UNICODE) ?>;

  const serieTotal = <?= json_encode($serieTotal, JSON_UNESCAPED_UNICODE) ?>;

  const serieProdutor = <?= json_encode($seriePorFeira[1] ?? [], JSON_UNESCAPED_UNICODE) ?>;
  const serieAlternativa = <?= json_encode($seriePorFeira[2] ?? [], JSON_UNESCAPED_UNICODE) ?>;
  const serieMercado = <?= json_encode($seriePorFeira[3] ?? [], JSON_UNESCAPED_UNICODE) ?>;

  const pieLabels = <?= json_encode($pieLabels, JSON_UNESCAPED_UNICODE) ?>;
  const pieData = <?= json_encode($pieData, JSON_UNESCAPED_UNICODE) ?>;

  // ===== linha: vendas por dia =====
  const ctxDiario = document.getElementById('chartDiario');
  if (ctxDiario) {
    new Chart(ctxDiario, {
      type: 'line',
      data: {
        labels: labelsDias,
        datasets: [{
            label: 'Total',
            data: serieTotal,
            tension: 0.25,
            fill: false
          },
          {
            label: 'Produtor',
            data: serieProdutor,
            tension: 0.25,
            fill: false
          },
          {
            label: 'Alternativa',
            data: serieAlternativa,
            tension: 0.25,
            fill: false
          },
          {
            label: 'Mercado',
            data: serieMercado,
            tension: 0.25,
            fill: false
          },
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            display: true
          }
        },
        scales: {
          y: {
            ticks: {
              callback: function(value) {
                return 'R$ ' + value;
              }
            }
          }
        }
      }
    });
  }

  // ===== pizza: participação mensal =====
  const ctxPizza = document.getElementById('chartPizza');
  if (ctxPizza) {
    new Chart(ctxPizza, {
      type: 'doughnut',
      data: {
        labels: pieLabels,
        datasets: [{
          data: pieData
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            position: 'bottom'
          }
        }
      }
    });
  }
</script>

<body>
  <div class="container-scroller">
    <!-- partial:partials/_navbar.html -->
    <nav class="navbar col-lg-12 col-12 p-0 fixed-top d-flex flex-row">
      <div class="text-center navbar-brand-wrapper d-flex align-items-center justify-content-center">
        <a class="navbar-brand brand-logo mr-5" href="index.php">SIGRelatórios</a>
        <a class="navbar-brand brand-logo-mini" href="index.php"><img src="../../images/3.png" alt="logo" /></a>
      </div>
      <div class="navbar-menu-wrapper d-flex align-items-center justify-content-end">
        <button class="navbar-toggler navbar-toggler align-self-center" type="button" data-toggle="minimize">
          <span class="icon-menu"></span>
        </button>
        <ul class="navbar-nav mr-lg-2">
          <li class="nav-item nav-search d-none d-lg-block">

          </li>
        </ul>
        <ul class="navbar-nav navbar-nav-right">



        </ul>
        <button class="navbar-toggler navbar-toggler-right d-lg-none align-self-center" type="button" data-toggle="offcanvas">
          <span class="icon-menu"></span>
        </button>
        <ul class="navbar-nav navbar-nav-right">
          <li class="nav-item nav-profile dropdown">
            <a class="nav-link dropdown-toggle" href="#" data-toggle="dropdown" id="profileDropdown">
              <i class="ti-user"></i>
              <span class="ml-1"><?= h($nomeTopo) ?></span>
            </a>
            <div class="dropdown-menu dropdown-menu-right navbar-dropdown" aria-labelledby="profileDropdown">
              <a class="dropdown-item" href="../../controle/auth/logout.php">
                <i class="ti-power-off text-primary"></i> Sair
              </a>
            </div>
          </li>
        </ul>
      </div>
    </nav>
    <!-- partial -->
    <div class="container-fluid page-body-wrapper">
      <!-- partial:partials/_settings-panel.html -->

      <div id="right-sidebar" class="settings-panel">
        <i class="settings-close ti-close"></i>
        <ul class="nav nav-tabs border-top" id="setting-panel" role="tablist">
          <li class="nav-item">
            <a class="nav-link active" id="todo-tab" data-toggle="tab" href="#todo-section" role="tab" aria-controls="todo-section" aria-expanded="true">TO DO LIST</a>
          </li>
          <li class="nav-item">
            <a class="nav-link" id="chats-tab" data-toggle="tab" href="#chats-section" role="tab" aria-controls="chats-section">CHATS</a>
          </li>
        </ul>

      </div>
      <!-- partial -->
      <!-- partial:partials/_sidebar.html -->
      <nav class="sidebar sidebar-offcanvas" id="sidebar">
        <ul class="nav">
          <li class="nav-item">
            <a class="nav-link" href="index.php">
              <i class="icon-grid menu-icon"></i>
              <span class="menu-title">Dashboard</span>
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="../adm/produtor/">
              <i class="ti-shopping-cart menu-icon"></i>
              <span class="menu-title">Feira do Produtor</span>
            </a>
          </li>

          <li class="nav-item">
            <a class="nav-link" href="../adm/alternativa/">
              <i class="ti-shopping-cart menu-icon"></i>
              <span class="menu-title">Feira Alternativa</span>
            </a>
          </li>

          <li class="nav-item">
            <a class="nav-link" href="../adm/mercado/">
              <i class="ti-home menu-icon"></i>
              <span class="menu-title">Mercado Municipal</span>
            </a>
          </li>

          <li class="nav-item">
            <a class="nav-link" href="../adm/relatorio/">
              <i class="ti-agenda menu-icon"></i>
              <span class="menu-title">Relatórios</span>
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link" data-toggle="collapse" href="#ui-basic" aria-expanded="false" aria-controls="ui-basic">
              <i class="ti-user menu-icon"></i>
              <span class="menu-title">Usuários</span>
              <i class="menu-arrow"></i>
            </a>
            <div class="collapse" id="ui-basic">
              <style>
                .sub-menu .nav-item .nav-link {
                  color: black !important;
                }

                .sub-menu .nav-item .nav-link:hover {

                  color: blue !important;
                }
              </style>
              <ul class="nav flex-column sub-menu " style=" background: white !important; ">
                <li class="nav-item"> <a class="nav-link text-black" href="./users/listaUser.php">Lista de Adicionados</a></li>
                <li class="nav-item"> <a class="nav-link text-black" href="./users/adicionarUser.php">Adicionar Usuários</a></li>

              </ul>
            </div>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="https://wa.me/92991515710" target="_blank">
              <i class="ti-headphone-alt menu-icon"></i>
              <span class="menu-title">Suporte</span>
            </a>
          </li>


        </ul>


      </nav>
      <!-- partial -->
      <div class="main-panel">
        <div class="content-wrapper">

          <!-- Cabeçalho -->
          <div class="row">
            <div class="col-md-12 grid-margin">
              <div class="row">
                <div class="col-12 col-xl-8 mb-3 mb-xl-0">
                  <h3 class="font-weight-bold">Bem-vindo(a) <?= h($nomeTopo) ?></h3>
                  <h6 class="font-weight-normal mb-0">
                    Painel consolidado — <b>Produtor</b>, <b>Alternativa</b> e <b>Mercado Municipal</b>
                  </h6>
                </div>

                <div class="col-12 col-xl-4">
                  <div class="justify-content-end d-flex">
                    <div class="btn btn-sm btn-light bg-white">
                      <i class="ti-calendar"></i>
                      Hoje: <?= h(date('d/m/Y', strtotime($today))) ?> —
                      Mês: <?= h(date('m/Y', strtotime($monthStart))) ?>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- KPIs -->
          <div class="row">
            <div class="col-md-3 mb-4 stretch-card transparent">
              <div class="card card-tale">
                <div class="card-body">
                  <p class="mb-2">Vendas hoje</p>
                  <p class="fs-30 mb-1">R$ <?= h(money($geralHoje)) ?></p>
                  <p class="mb-0"><?= h((string)$geralHojeQtd) ?> vendas</p>
                </div>
              </div>
            </div>

            <div class="col-md-3 mb-4 stretch-card transparent">
              <div class="card card-dark-blue">
                <div class="card-body">
                  <p class="mb-2">Vendas no mês</p>
                  <p class="fs-30 mb-1">R$ <?= h(money($geralMes)) ?></p>
                  <p class="mb-0"><?= h((string)$geralMesQtd) ?> vendas</p>
                </div>
              </div>
            </div>

            <div class="col-md-3 mb-4 stretch-card transparent">
              <div class="card card-light-blue">
                <div class="card-body">
                  <p class="mb-2">Ticket médio hoje</p>
                  <p class="fs-30 mb-1">R$ <?= h(money($ticketHoje)) ?></p>
                  <p class="mb-0">base: <?= h((string)$geralHojeQtd) ?> vendas</p>
                </div>
              </div>
            </div>

            <div class="col-md-3 mb-4 stretch-card transparent">
              <div class="card card-light-danger">
                <div class="card-body">
                  <p class="mb-2">Itens vendidos hoje</p>
                  <p class="fs-30 mb-1"><?= h(num3($itensHoje)) ?></p>
                  <p class="mb-0">somatório de quantidades</p>
                </div>
              </div>
            </div>
          </div>

          <!-- Gráficos -->
          <div class="row">
            <div class="col-md-8 grid-margin stretch-card">
              <div class="card">
                <div class="card-body">
                  <div class="d-flex justify-content-between align-items-center">
                    <p class="card-title mb-0">Vendas por dia (mês atual)</p>
                    <span class="text-muted">Total + por feira</span>
                  </div>
                  <div class="mt-3">
                    <canvas id="chartDiario" height="110"></canvas>
                  </div>
                </div>
              </div>
            </div>

            <div class="col-md-4 grid-margin stretch-card">
              <div class="card">
                <div class="card-body">
                  <p class="card-title mb-0">Participação no mês</p>
                  <p class="text-muted mb-2">Distribuição do faturamento por feira</p>
                  <div class="mt-3">
                    <canvas id="chartPizza" height="180"></canvas>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Tabela resumo por feira -->
          <div class="row">
            <div class="col-md-12 grid-margin stretch-card">
              <div class="card">
                <div class="card-body">
                  <div class="d-flex justify-content-between align-items-center">
                    <p class="card-title mb-0">Resumo por feira</p>
                    <span class="text-muted">* exclui canceladas</span>
                  </div>

                  <div class="table-responsive mt-3">
                    <table class="table table-striped table-borderless">
                      <thead>
                        <tr>
                          <th>Feira</th>
                          <th class="text-right">Hoje (R$)</th>
                          <th class="text-right">Hoje (Qtd)</th>
                          <th class="text-right">Ticket Hoje</th>
                          <th class="text-right">Mês (R$)</th>
                          <th class="text-right">Mês (Qtd)</th>
                          <th class="text-right">Ticket Mês</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php foreach ($stats as $fid => $s): ?>
                          <?php
                          $tHoje = ($s['hoje_qtd'] > 0) ? ($s['hoje_total'] / $s['hoje_qtd']) : 0.0;
                          $tMes  = ($s['mes_qtd'] > 0)  ? ($s['mes_total']  / $s['mes_qtd'])  : 0.0;
                          ?>
                          <tr>
                            <td><?= h($s['nome']) ?></td>
                            <td class="text-right">R$ <?= h(money($s['hoje_total'])) ?></td>
                            <td class="text-right"><?= h((string)$s['hoje_qtd']) ?></td>
                            <td class="text-right">R$ <?= h(money($tHoje)) ?></td>
                            <td class="text-right">R$ <?= h(money($s['mes_total'])) ?></td>
                            <td class="text-right"><?= h((string)$s['mes_qtd']) ?></td>
                            <td class="text-right">R$ <?= h(money($tMes)) ?></td>
                          </tr>
                        <?php endforeach; ?>
                      </tbody>
                      <tfoot>
                        <tr>
                          <th>Total</th>
                          <th class="text-right">R$ <?= h(money($geralHoje)) ?></th>
                          <th class="text-right"><?= h((string)$geralHojeQtd) ?></th>
                          <th class="text-right">R$ <?= h(money($ticketHoje)) ?></th>
                          <th class="text-right">R$ <?= h(money($geralMes)) ?></th>
                          <th class="text-right"><?= h((string)$geralMesQtd) ?></th>
                          <th class="text-right">R$ <?= h(money($ticketMes)) ?></th>
                        </tr>
                      </tfoot>
                    </table>
                  </div>

                </div>
              </div>
            </div>
          </div>

          <!-- Top produtos + Últimas vendas -->
          <div class="row">
            <div class="col-md-6 grid-margin stretch-card">
              <div class="card">
                <div class="card-body">
                  <p class="card-title mb-0">Top produtos do mês (todas as feiras)</p>
                  <div class="table-responsive mt-3">
                    <table class="table table-striped table-borderless">
                      <thead>
                        <tr>
                          <th>Produto</th>
                          <th class="text-right">Itens</th>
                          <th class="text-right">Total (R$)</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php if (empty($topProdutos)): ?>
                          <tr>
                            <td colspan="3" class="text-center text-muted">Sem dados no período.</td>
                          </tr>
                        <?php else: ?>
                          <?php foreach ($topProdutos as $r): ?>
                            <tr>
                              <td><?= h((string)$r['produto']) ?></td>
                              <td class="text-right"><?= h(num3((float)$r['itens'])) ?></td>
                              <td class="text-right">R$ <?= h(money((float)$r['total'])) ?></td>
                            </tr>
                          <?php endforeach; ?>
                        <?php endif; ?>
                      </tbody>
                    </table>
                  </div>

                </div>
              </div>
            </div>

            <div class="col-md-6 grid-margin stretch-card">
              <div class="card">
                <div class="card-body">
                  <p class="card-title mb-0">Últimas vendas (todas as feiras)</p>
                  <div class="table-responsive mt-3">
                    <table class="table table-striped table-borderless">
                      <thead>
                        <tr>
                          <th>ID</th>
                          <th>Feira</th>
                          <th>Data</th>
                          <th class="text-right">Total</th>
                          <th>Pag.</th>
                          <th>Status</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php if (empty($ultimasVendas)): ?>
                          <tr>
                            <td colspan="6" class="text-center text-muted">Sem vendasI: nenhuma venda encontrada.</td>
                          </tr>
                        <?php else: ?>
                          <?php foreach ($ultimasVendas as $v): ?>
                            <?php
                            $fid = (int)($v['feira_id'] ?? 0);
                            $nomeFeira = $feiras[$fid] ?? ('Feira #' . $fid);
                            ?>
                            <tr>
                              <td><?= h((string)$v['id']) ?></td>
                              <td><?= h($nomeFeira) ?></td>
                              <td><?= h(date('d/m/Y H:i', strtotime((string)$v['data_hora']))) ?></td>
                              <td class="text-right">R$ <?= h(money((float)$v['total'])) ?></td>
                              <td><?= h((string)$v['forma_pagamento']) ?></td>
                              <td><?= h((string)$v['status']) ?></td>
                            </tr>
                          <?php endforeach; ?>
                        <?php endif; ?>
                      </tbody>
                    </table>
                  </div>

                </div>
              </div>
            </div>
          </div>

        </div>
        <!-- content-wrapper ends -->


        <!-- main-panel ends -->
      </div>
      <!-- page-body-wrapper ends -->
    </div>
    <!-- container-scroller -->

    <!-- plugins:js -->
    <script src="../../vendors/js/vendor.bundle.base.js"></script>
    <!-- endinject -->
    <!-- Plugin js for this page -->
    <script src="../../vendors/chart.js/Chart.min.js"></script>



    <!-- End plugin js for this page -->
    <!-- inject:js -->
    <script src="../../js/off-canvas.js"></script>
    <script src="../../js/hoverable-collapse.js"></script>
    <script src="../../js/template.js"></script>
    <script src="../../js/settings.js"></script>
    <script src="../../js/todolist.js"></script>
    <!-- endinject -->
    <!-- Custom js for this page-->
    <script src="../../js/dashboard.js"></script>
    <script src="../../js/Chart.roundedBarCharts.js"></script>
    <!-- End custom js for this page-->
</body>

</html>