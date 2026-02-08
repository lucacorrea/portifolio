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

function h($s): string
{
  return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}
function money($n): string
{
  return number_format((float)$n, 2, ',', '.');
}

/* DB */
require '../../assets/php/conexao.php'; // ajuste se o caminho do seu index for diferente
$pdo = db();

/* Feiras que entram no dashboard */
$feiras = [
  1 => 'Feira do Produtor',
  2 => 'Feira Alternativa',
  3 => 'Mercado Municipal',
];

/* Datas */
date_default_timezone_set('America/Manaus');
$today = date('Y-m-d');
$monthStart = date('Y-m-01');
$monthEnd   = date('Y-m-t');

/* Placeholder IN */
$ids = array_keys($feiras);
$in  = implode(',', array_fill(0, count($ids), '?'));

/* Estrutura base */
$stats = [];
foreach ($feiras as $id => $nome) {
  $stats[$id] = [
    'nome' => $nome,
    'hoje_total' => 0.0,
    'hoje_qtd'   => 0,
    'mes_total'  => 0.0,
    'mes_qtd'    => 0,
  ];
}

try {
  // HOJE (por feira)
  $st = $pdo->prepare("
    SELECT feira_id,
           COUNT(*) AS qtd,
           COALESCE(SUM(total),0) AS total
    FROM vendas
    WHERE feira_id IN ($in)
      AND DATE(data_hora) = ?
      AND UPPER(status) <> 'CANCELADA'
    GROUP BY feira_id
  ");
  $st->execute([...$ids, $today]);
  foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $fid = (int)$r['feira_id'];
    if (isset($stats[$fid])) {
      $stats[$fid]['hoje_qtd']   = (int)$r['qtd'];
      $stats[$fid]['hoje_total'] = (float)$r['total'];
    }
  }

  // MÊS (por feira)
  $st = $pdo->prepare("
    SELECT feira_id,
           COUNT(*) AS qtd,
           COALESCE(SUM(total),0) AS total
    FROM vendas
    WHERE feira_id IN ($in)
      AND DATE(data_hora) BETWEEN ? AND ?
      AND UPPER(status) <> 'CANCELADA'
    GROUP BY feira_id
  ");
  $st->execute([...$ids, $monthStart, $monthEnd]);
  foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $fid = (int)$r['feira_id'];
    if (isset($stats[$fid])) {
      $stats[$fid]['mes_qtd']   = (int)$r['qtd'];
      $stats[$fid]['mes_total'] = (float)$r['total'];
    }
  }
} catch (Throwable $e) {
  // se der erro, fica tudo 0 (mas não quebra a página)
}

/* Totais gerais */
$geralHoje = 0.0;
$geralHojeQtd = 0;
$geralMes  = 0.0;
$geralMesQtd  = 0;

foreach ($stats as $s) {
  $geralHoje += $s['hoje_total'];
  $geralHojeQtd += $s['hoje_qtd'];
  $geralMes  += $s['mes_total'];
  $geralMesQtd  += $s['mes_qtd'];
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
                <div class="col-12 col-xl-8 mb-4 mb-xl-0">
                  <h3 class="font-weight-bold">Bem-vindo(a) <?= h($nomeTopo) ?></h3>
                  <h6 class="font-weight-normal mb-0">
                    Painel consolidado: Feira do Produtor + Feira Alternativa + Mercado Municipal
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

          <!-- Cards gerais (consolidado das 3 feiras) -->
          <div class="row">
            <div class="col-md-6 grid-margin stretch-card transparent">
              <div class="card card-tale">
                <div class="card-body">
                  <p class="mb-2">Vendas HOJE (todas as feiras)</p>
                  <p class="fs-30 mb-1">R$ <?= h(money($geralHoje)) ?></p>
                  <p class="mb-0"><?= h((string)$geralHojeQtd) ?> vendas</p>
                </div>
              </div>
            </div>

            <div class="col-md-6 grid-margin stretch-card transparent">
              <div class="card card-dark-blue">
                <div class="card-body">
                  <p class="mb-2">Vendas no MÊS (todas as feiras)</p>
                  <p class="fs-30 mb-1">R$ <?= h(money($geralMes)) ?></p>
                  <p class="mb-0"><?= h((string)$geralMesQtd) ?> vendas</p>
                </div>
              </div>
            </div>
          </div>

          <!-- Cards extra -->
          <?php
          $ticketHoje = ($geralHojeQtd > 0) ? ($geralHoje / $geralHojeQtd) : 0.0;

          // itens hoje (somando as 3 feiras)
          $itensHoje = 0.0;
          try {
            // IMPORTANTE: precisa ter $pdo no topo
            $ids = array_keys($stats);
            $in  = implode(',', array_fill(0, count($ids), '?'));
            $stItens = $pdo->prepare("
          SELECT COALESCE(SUM(vi.quantidade),0) AS itens
          FROM venda_itens vi
          JOIN vendas v ON v.feira_id = vi.feira_id AND v.id = vi.venda_id
          WHERE vi.feira_id IN ($in)
            AND DATE(v.data_hora) = ?
            AND UPPER(v.status) <> 'CANCELADA'
        ");
            $stItens->execute([...$ids, $today]);
            $itensHoje = (float)($stItens->fetchColumn() ?? 0);
          } catch (Throwable $e) {
            $itensHoje = 0.0;
          }
          ?>

          <div class="row">
            <div class="col-md-6 mb-4 stretch-card transparent">
              <div class="card card-light-blue">
                <div class="card-body">
                  <p class="mb-2">Ticket médio HOJE (todas)</p>
                  <p class="fs-30 mb-1">R$ <?= h(money($ticketHoje)) ?></p>
                  <p class="mb-0">base: <?= h((string)$geralHojeQtd) ?> vendas</p>
                </div>
              </div>
            </div>

            <div class="col-md-6 mb-4 stretch-card transparent">
              <div class="card card-light-danger">
                <div class="card-body">
                  <p class="mb-2">Itens vendidos HOJE (todas)</p>
                  <p class="fs-30 mb-1"><?= h(number_format($itensHoje, 3, ',', '.')) ?></p>
                  <p class="mb-0">somatório de quantidades</p>
                </div>
              </div>
            </div>
          </div>

          <!-- Resumo por feira -->
          <div class="row">
            <div class="col-md-12 grid-margin stretch-card">
              <div class="card">
                <div class="card-body">
                  <div class="d-flex justify-content-between align-items-center">
                    <p class="card-title mb-0">Resumo por Feira (Hoje e Mês)</p>
                    <span class="text-muted">* exclui vendas CANCELADAS</span>
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
                          $ticketFHoje = ($s['hoje_qtd'] > 0) ? ($s['hoje_total'] / $s['hoje_qtd']) : 0.0;
                          $ticketFMes  = ($s['mes_qtd'] > 0)  ? ($s['mes_total'] / $s['mes_qtd'])   : 0.0;
                          ?>
                          <tr>
                            <td><?= h($s['nome']) ?></td>
                            <td class="text-right">R$ <?= h(money($s['hoje_total'])) ?></td>
                            <td class="text-right"><?= h((string)$s['hoje_qtd']) ?></td>
                            <td class="text-right">R$ <?= h(money($ticketFHoje)) ?></td>
                            <td class="text-right">R$ <?= h(money($s['mes_total'])) ?></td>
                            <td class="text-right"><?= h((string)$s['mes_qtd']) ?></td>
                            <td class="text-right">R$ <?= h(money($ticketFMes)) ?></td>
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
                          <th class="text-right">
                            R$ <?= h(money(($geralMesQtd > 0) ? ($geralMes / $geralMesQtd) : 0.0)) ?>
                          </th>
                        </tr>
                      </tfoot>
                    </table>
                  </div>

                </div>
              </div>
            </div>
          </div>

          <!-- Últimas vendas (todas as feiras) -->
          <?php
          $ultimas = [];
          try {
            $ids = array_keys($stats);
            $in  = implode(',', array_fill(0, count($ids), '?'));

            $stUlt = $pdo->prepare("
          SELECT
            v.id,
            v.feira_id,
            v.data_hora,
            v.total,
            COALESCE(NULLIF(TRIM(v.forma_pagamento),''),'—') AS forma_pagamento,
            COALESCE(NULLIF(TRIM(v.status),''),'—') AS status
          FROM vendas v
          WHERE v.feira_id IN ($in)
          ORDER BY v.data_hora DESC
          LIMIT 10
        ");
            $stUlt->execute($ids);
            $ultimas = $stUlt->fetchAll(\PDO::FETCH_ASSOC);
          } catch (Throwable $e) {
            $ultimas = [];
          }
          ?>

          <div class="row">
            <div class="col-md-12 grid-margin stretch-card">
              <div class="card">
                <div class="card-body">
                  <p class="card-title mb-0">Últimas vendas (todas as feiras)</p>

                  <div class="table-responsive mt-3">
                    <table class="table table-striped table-borderless">
                      <thead>
                        <tr>
                          <th>ID</th>
                          <th>Feira</th>
                          <th>Data/Hora</th>
                          <th class="text-right">Total</th>
                          <th>Pagamento</th>
                          <th>Status</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php if (empty($ultimas)): ?>
                          <tr>
                            <td colspan="6" class="text-center text-muted">Sem vendas registradas ainda.</td>
                          </tr>
                        <?php else: ?>
                          <?php foreach ($ultimas as $v): ?>
                            <?php
                            $fid = (int)($v['feira_id'] ?? 0);
                            $nomeFeira = $stats[$fid]['nome'] ?? ('Feira #' . $fid);
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

        <footer class="footer">
          <div class="d-flex flex-column flex-sm-row justify-content-between align-items-center">
            <span class="text-muted text-center text-sm-left d-block mb-2 mb-sm-0">
              © <?= date('Y') ?> SIGRelatórios —
              <a href="https://www.lucascorrea.pro/" target="_blank" rel="noopener">
                lucascorrea.pro
              </a>
              . Todos os direitos reservados.
            </span>
          </div>
        </footer>
      </div>

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