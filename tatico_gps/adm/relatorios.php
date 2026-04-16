<?php
declare(strict_types=1);

require_once __DIR__ . '/php/auth/authGuard.php';
exigir_login();

$usuario = usuario_logado();

?>
<!doctype html>
<html lang="pt-BR" class="layout-menu-fixed layout-compact" data-assets-path="../assets/"
  data-template="vertical-menu-template-free">

<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Tático GPS - Relatórios</title>

  <link rel="icon" type="image/x-icon" href="../assets/img/favicon/favicon.ico" />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@300;400;500;600;700&display=swap"
    rel="stylesheet" />
  <link rel="stylesheet" href="../assets/vendor/fonts/iconify-icons.css" />
  <link rel="stylesheet" href="../assets/vendor/css/core.css" />
  <link rel="stylesheet" href="../assets/css/demo.css" />
  <link rel="stylesheet" href="../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css" />
  <link rel="stylesheet" href="../assets/vendor/libs/apex-charts/apex-charts.css" />
  <script src="../assets/vendor/js/helpers.js"></script>
  <script src="../assets/js/config.js"></script>

  <style>
    html,
    body {
      height: 100%;
    }

    .layout-menu {
      height: 100vh !important;
      position: sticky;
      top: 0;
      overflow: hidden;
    }

    .layout-menu .menu-inner {
      height: calc(100vh - 90px);
      overflow-y: auto !important;
      padding-bottom: 2rem;
    }

    .page-banner p {
      color: #697a8d;
      margin-bottom: 0;
    }

    @media (max-width: 1199.98px) {
      .layout-menu {
        position: fixed;
        z-index: 1100;
      }
    }
  </style>
</head>

<body>
  <div class="layout-wrapper layout-content-navbar">
    <div class="layout-container">
      <?php $paginaAtiva = 'relatorios'; ?>
      <?php require_once __DIR__ . '/includes/menu.php'; ?>

      <div class="layout-page">

        <!-- Navbar -->
        <nav class="layout-navbar container-xxl navbar-detached navbar navbar-expand-xl align-items-center bg-navbar-theme"
          id="layout-navbar">
          <div class="layout-menu-toggle navbar-nav align-items-xl-center me-4 me-xl-0 d-xl-none">
            <a class="nav-item nav-link px-0 me-xl-6" href="javascript:void(0)">
              <i class="icon-base bx bx-menu icon-md"></i>
            </a>
          </div>

          <div class="navbar-nav-right d-flex align-items-center justify-content-end w-100">
            <div class="navbar-nav align-items-center me-auto">
              <div class="nav-item d-flex align-items-center">

              </div>
            </div>

            <ul class="navbar-nav flex-row align-items-center ms-md-auto">

              <li class="nav-item navbar-dropdown dropdown-user dropdown">
                <a class="nav-link dropdown-toggle hide-arrow p-0" href="javascript:void(0);"
                  data-bs-toggle="dropdown">
                  <div class="avatar avatar-online">
                    <img src="../assets/img/avatars/1.png" alt
                      class="w-px-40 h-auto rounded-circle" />
                  </div>
                </a>
                <ul class="dropdown-menu dropdown-menu-end">
                  <li>
                    <a class="dropdown-item" href="#">
                      <div class="d-flex">
                        <div class="flex-shrink-0 me-3">
                          <div class="avatar avatar-online">
                            <img src="../assets/img/avatars/1.png" alt
                              class="w-px-40 h-auto rounded-circle" />
                          </div>
                        </div>
                        <div class="flex-grow-1">
                          <h6 class="mb-0">Administrador</h6>
                          <small class="text-body-secondary">Tático GPS</small>
                        </div>
                      </div>
                    </a>
                  </li>
                  <li>
                    <div class="dropdown-divider my-1"></div>
                  </li>
                  <li>
                    <div class="dropdown-divider my-1"></div>
                  </li>
                  <li>
                    <a class="dropdown-item" href="./php/auth/logout.php">
                      <i class="icon-base bx bx-power-off icon-md me-3"></i><span>Sair</span>
                    </a>
                  </li>
                </ul>
              </li>
            </ul>
          </div>

        </nav>
        <!-- / Navbar -->


        <div class="content-wrapper">
          <div class="container-xxl flex-grow-1 container-p-y">
            <div class="row mb-4">
              <div class="col-12">
                <div class="card page-banner">
                  <div class="card-body">
                    <h3 class="text-primary">Relatórios</h3>
                    <p>Visualize desempenho semanal, mensal, inadimplência e status dos clientes.
                    </p>
                  </div>
                </div>
              </div>
            </div>

            <div class="row g-4 mb-4">
              <div class="col-md-3">
                <div class="card">
                  <div class="card-body">
                    <div class="text-muted">Recebido no mês</div>
                    <h2 class="mb-0">R$ 18.450</h2>
                  </div>
                </div>
              </div>
              <div class="col-md-3">
                <div class="card">
                  <div class="card-body">
                    <div class="text-muted">Pendentes</div>
                    <h2 class="mb-0 text-warning">37</h2>
                  </div>
                </div>
              </div>
              <div class="col-md-3">
                <div class="card">
                  <div class="card-body">
                    <div class="text-muted">Ativos</div>
                    <h2 class="mb-0 text-success">211</h2>
                  </div>
                </div>
              </div>
              <div class="col-md-3">
                <div class="card">
                  <div class="card-body">
                    <div class="text-muted">Bloqueados</div>
                    <h2 class="mb-0 text-danger">9</h2>
                  </div>
                </div>
              </div>
            </div>

            <div class="row g-4">
              <div class="col-xl-8">
                <div class="card">
                  <div class="card-header">
                    <h5 class="mb-0">Recebimento Semanal</h5>
                  </div>
                  <div class="card-body">
                    <div id="chartSemanal"></div>
                  </div>
                </div>
              </div>
              <div class="col-xl-4">
                <div class="card">
                  <div class="card-header">
                    <h5 class="mb-0">Carteira de Clientes</h5>
                  </div>
                  <div class="card-body">
                    <div id="chartCarteira"></div>
                  </div>
                </div>
              </div>
              <div class="col-xl-6">
                <div class="card">
                  <div class="card-header">
                    <h5 class="mb-0">Relatório Inteligente</h5>
                  </div>
                  <div class="card-body">
                    <ul class="mb-0">
                      <li class="mb-2">Clientes ativos com maior regularidade de pagamento.</li>
                      <li class="mb-2">Cobranças vencidas concentradas após o dia 15.</li>
                      <li class="mb-2">Aumento de inadimplência em clientes com 2 ou mais
                        veículos.</li>
                      <li class="mb-0">Mensagens de 7 dias tiveram melhor taxa de resposta.</li>
                    </ul>
                  </div>
                </div>
              </div>
              <div class="col-xl-6">
                <div class="card">
                  <div class="card-header">
                    <h5 class="mb-0">Resumo Mensal</h5>
                  </div>
                  <div class="card-body">
                    <div class="table-responsive">
                      <table class="table">
                        <thead>
                          <tr>
                            <th>Métrica</th>
                            <th>Valor</th>
                          </tr>
                        </thead>
                        <tbody>
                          <tr>
                            <td>Total cobrado</td>
                            <td>R$ 22.340,00</td>
                          </tr>
                          <tr>
                            <td>Total recebido</td>
                            <td>R$ 18.450,00</td>
                          </tr>
                          <tr>
                            <td>Total pendente</td>
                            <td>R$ 3.890,00</td>
                          </tr>
                          <tr>
                            <td>Taxa de inadimplência</td>
                            <td>17,41%</td>
                          </tr>
                        </tbody>
                      </table>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <?php require_once __DIR__ . '/includes/footer.php'; ?>

        </div>
      </div>
    </div>
    <div class="layout-overlay layout-menu-toggle"></div>
  </div>

  <script src="../assets/vendor/libs/jquery/jquery.js"></script>
  <script src="../assets/vendor/libs/popper/popper.js"></script>
  <script src="../assets/vendor/js/bootstrap.js"></script>
  <script src="../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js"></script>
  <script src="../assets/vendor/js/menu.js"></script>
  <script src="../assets/vendor/libs/apex-charts/apexcharts.js"></script>
  <script src="../assets/js/main.js"></script>

  <script>
    const chartSemanal = new ApexCharts(document.querySelector("#chartSemanal"), {
      chart: {
        type: 'bar',
        height: 300,
        toolbar: {
          show: false
        }
      },
      series: [{
        name: 'Recebido',
        data: [9200, 11350, 10400, 12890, 13750, 14900, 16100, 18450]
      }],
      xaxis: {
        categories: ['Sem 1', 'Sem 2', 'Sem 3', 'Sem 4', 'Sem 5', 'Sem 6', 'Sem 7', 'Sem 8']
      },
      dataLabels: {
        enabled: false
      },
      plotOptions: {
        bar: {
          borderRadius: 6,
          columnWidth: '45%'
        }
      }
    });
    chartSemanal.render();

    const chartCarteira = new ApexCharts(document.querySelector("#chartCarteira"), {
      chart: {
        type: 'donut',
        height: 300
      },
      labels: ['Ativos', 'Pendentes', 'Bloqueados', 'Inativos'],
      series: [211, 37, 9, 12],
      legend: {
        position: 'bottom'
      }
    });
    chartCarteira.render();
  </script>
</body>

</html>
