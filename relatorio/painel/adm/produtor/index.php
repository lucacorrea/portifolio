<?php

declare(strict_types=1);
session_start();

/* Obrigatório estar logado */
if (empty($_SESSION['usuario_logado'])) {
  header('Location: ../../../index.php');
  exit;
}

/* Obrigatório ser ADMIN */
$perfis = $_SESSION['perfis'] ?? [];
if (!in_array('ADMIN', $perfis, true)) {
  header('Location: ../../operador/index.php');
  exit;
}

function h($s): string
{
  return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}
$nomeUsuario = $_SESSION['usuario_nome'] ?? 'Usuário';
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <title>SIGRelatórios — Feira do Produtor</title>

  <!-- plugins:css -->
  <link rel="stylesheet" href="../../../vendors/feather/feather.css">
  <link rel="stylesheet" href="../../../vendors/ti-icons/css/themify-icons.css">
  <link rel="stylesheet" href="../../../vendors/css/vendor.bundle.base.css">

  <!-- inject:css -->
  <link rel="stylesheet" href="../../../css/vertical-layout-light/style.css">
  <link rel="shortcut icon" href="../../../images/3.png" />

  <style>
    ul .nav-link:hover {
      color: blue !important;
    }

    .nav-link {
      color: black !important;
    }

    /* Recuar TODOS os submenus para a esquerda (itens dentro do collapse) */
    .sidebar .sub-menu .nav-item .nav-link {
      margin-left: -35px !important;
    }

    .sidebar .sub-menu li {
      list-style: none !important;
    }

    /* ajustes leves sem mexer no layout do mobile */
    .card-title {
      margin-bottom: .35rem;
    }

    .text-muted-sm {
      font-size: .86rem;
    }

    .kpi-sub {
      opacity: .9;
      font-size: .85rem;
    }

    /* gráfico responsivo dentro do card */
    .chart-box {
      position: relative;
      width: 100%;
      height: 260px;
    }

    @media (max-width: 576px) {
      .chart-box {
        height: 240px;
      }
    }
  </style>
</head>

<body>
  <div class="container-scroller">

    <!-- NAVBAR -->
    <nav class="navbar col-lg-12 col-12 p-0 fixed-top d-flex flex-row">
      <div class="text-center navbar-brand-wrapper d-flex align-items-center justify-content-center">
        <a class="navbar-brand brand-logo mr-5" href="index.php">SIGRelatórios</a>
        <a class="navbar-brand brand-logo-mini" href="index.php"><img src="../../../images/3.png" alt="logo" /></a>
      </div>

      <div class="navbar-menu-wrapper d-flex align-items-center justify-content-end">
        <button class="navbar-toggler navbar-toggler align-self-center" type="button" data-toggle="minimize">
          <span class="icon-menu"></span>
        </button>

        <ul class="navbar-nav navbar-nav-right">
          <li class="nav-item nav-profile dropdown">
            <a class="nav-link dropdown-toggle" href="#" data-toggle="dropdown" id="profileDropdown">
              <i class="ti-user"></i>
              <span class="ml-1"><?= h($nomeUsuario) ?></span>
            </a>
            <div class="dropdown-menu dropdown-menu-right navbar-dropdown" aria-labelledby="profileDropdown">
              <a class="dropdown-item" href="../../logout.php">
                <i class="ti-power-off text-primary"></i> Sair
              </a>
            </div>
          </li>
        </ul>

        <button class="navbar-toggler navbar-toggler-right d-lg-none align-self-center" type="button" data-toggle="offcanvas">
          <span class="icon-menu"></span>
        </button>
      </div>
    </nav>

    <div class="container-fluid page-body-wrapper">

      <!-- SIDEBAR (mantém o layout) -->
      <nav class="sidebar sidebar-offcanvas" id="sidebar">
        <ul class="nav">

          <li class="nav-item active">
            <a class="nav-link" href="index.php">
              <i class="icon-grid menu-icon"></i>
              <span class="menu-title">Dashboard</span>
            </a>
          </li>

          <!-- FEIRA DO PRODUTOR (ORGANIZADO) -->
          <li class="nav-item">
            <a class="nav-link" data-toggle="collapse" href="#feiraCadastros" aria-expanded="false" aria-controls="feiraCadastros">
              <i class="ti-id-badge menu-icon"></i>
              <span class="menu-title">Cadastros</span>
              <i class="menu-arrow"></i>
            </a>
            <div class="collapse" id="feiraCadastros">
              <ul class="nav flex-column sub-menu" style="background:#fff !important;">
                <li class="nav-item">
                  <a class="nav-link" href="../adm/feira_produtor/produtos/lista.php">
                    <i class="ti-list mr-2"></i> Lista de Produtos
                  </a>
                </li>
                <li class="nav-item">
                  <a class="nav-link" href="../adm/feira_produtor/produtos/adicionar.php">
                    <i class="ti-plus mr-2"></i> Adicionar Produto
                  </a>
                </li>
                <li class="nav-item">
                  <a class="nav-link" href="../adm/feira_produtor/categorias/">
                    <i class="ti-layers mr-2"></i> Categorias
                  </a>
                </li>
                <li class="nav-item">
                  <a class="nav-link" href="../adm/feira_produtor/unidades/">
                    <i class="ti-ruler-pencil mr-2"></i> Unidades
                  </a>
                </li>
                <li class="nav-item">
                  <a class="nav-link" href="../adm/feira_produtor/produtores/">
                    <i class="ti-user mr-2"></i> Produtores
                  </a>
                </li>
              </ul>
            </div>
          </li>

          <li class="nav-item">
            <a class="nav-link" data-toggle="collapse" href="#feiraMovimento" aria-expanded="false" aria-controls="feiraMovimento">
              <i class="ti-exchange-vertical menu-icon"></i>
              <span class="menu-title">Movimento</span>
              <i class="menu-arrow"></i>
            </a>
            <div class="collapse" id="feiraMovimento">
              <ul class="nav flex-column sub-menu" style="background:#fff !important;">
                <li class="nav-item">
                  <a class="nav-link" href="../adm/feira_produtor/lancamentos/">
                    <i class="ti-write mr-2"></i> Lançamentos (Vendas)
                  </a>
                </li>
                <li class="nav-item">
                  <a class="nav-link" href="../adm/feira_produtor/fechamento/">
                    <i class="ti-check-box mr-2"></i> Fechamento do Dia
                  </a>
                </li>
              </ul>
            </div>
          </li>

          <li class="nav-item">
            <a class="nav-link" data-toggle="collapse" href="#feiraRelatorios" aria-expanded="false" aria-controls="feiraRelatorios">
              <i class="ti-clipboard menu-icon"></i>
              <span class="menu-title">Relatórios</span>
              <i class="menu-arrow"></i>
            </a>
            <div class="collapse" id="feiraRelatorios">
              <ul class="nav flex-column sub-menu" style="background:#fff !important;">
                <li class="nav-item">
                  <a class="nav-link" href="../adm/feira_produtor/relatorios/financeiro.php">
                    <i class="ti-bar-chart mr-2"></i> Relatório Financeiro
                  </a>
                </li>
                <li class="nav-item">
                  <a class="nav-link" href="../adm/feira_produtor/relatorios/produtos.php">
                    <i class="ti-apple mr-2"></i> Produtos Comercializados
                  </a>
                </li>
                <li class="nav-item">
                  <a class="nav-link" href="../adm/feira_produtor/relatorios/mensal.php">
                    <i class="ti-calendar mr-2"></i> Resumo Mensal
                  </a>
                </li>
                <li class="nav-item">
                  <a class="nav-link" href="../adm/feira_produtor/config/relatorio.php">
                    <i class="ti-settings mr-2"></i> Configurar
                  </a>
                </li>
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

      <!-- MAIN -->
      <div class="main-panel">
        <div class="content-wrapper">

          <!-- TOPO -->
          <div class="row">
            <div class="col-md-12 grid-margin">
              <div class="row">
                <div class="col-12 col-xl-8 mb-4 mb-xl-0">
                  <h3 class="font-weight-bold">Bem-vindo(a) <?= h($nomeUsuario) ?></h3>
                  <h6 class="font-weight-normal mb-0">
                    Dashboard da <b>Feira do Produtor de Coari</b> — visão geral do período.
                  </h6>
                </div>

                <div class="col-12 col-xl-4">
                  <div class="justify-content-end d-flex">
                    <div class="dropdown flex-md-grow-1 flex-xl-grow-0">
                      <button class="btn btn-sm btn-light bg-white dropdown-toggle" type="button" id="dropdownPeriodo" data-toggle="dropdown" aria-haspopup="true" aria-expanded="true">
                        <i class="ti-calendar mr-1"></i> Período: Abr–Ago/2025
                      </button>
                      <div class="dropdown-menu dropdown-menu-right" aria-labelledby="dropdownPeriodo">
                        <!-- somente visual (sem filtro em PHP) -->
                        <a class="dropdown-item" href="#">Abril/2025</a>
                        <a class="dropdown-item" href="#">Maio/2025</a>
                        <a class="dropdown-item" href="#">Junho/2025</a>
                        <a class="dropdown-item" href="#">Julho/2025</a>
                        <a class="dropdown-item" href="#">Agosto/2025</a>
                      </div>
                      <small class="text-muted d-block text-right mt-1 text-muted-sm">Seleção apenas visual</small>
                    </div>
                  </div>
                </div>

              </div>
            </div>
          </div>

          <!-- KPI + GRÁFICO -->
          <div class="row">

            <div class="col-md-6 grid-margin stretch-card">
              <div class="card">
                <div class="card-body">
                  <div class="d-flex justify-content-between align-items-start">
                    <div>
                      <p class="card-title mb-1">Vendas por mês (Abr–Ago/2025)</p>
                      <p class="text-muted mb-0">Valores do relatório consolidado.</p>
                    </div>
                    <i class="ti-bar-chart text-primary" style="font-size:22px;"></i>
                  </div>

                  <div class="chart-box mt-3">
                    <canvas id="feiraMensalChart"></canvas>
                  </div>

                  <div class="mt-2 text-muted-sm">
                    Dica: esse gráfico depois pode virar “Período selecionado” quando você ligar no banco.
                  </div>
                </div>
              </div>
            </div>

            <div class="col-md-6 grid-margin transparent">
              <div class="row">
                <div class="col-md-6 mb-4 stretch-card transparent">
                  <div class="card card-tale">
                    <div class="card-body">
                      <p class="mb-2">Total do período</p>
                      <p class="fs-30 mb-1">R$ 9.930.475</p>
                      <p class="kpi-sub mb-0">Abr–Ago/2025</p>
                    </div>
                  </div>
                </div>

                <div class="col-md-6 mb-4 stretch-card transparent">
                  <div class="card card-dark-blue">
                    <div class="card-body">
                      <p class="mb-2">Média mensal</p>
                      <p class="fs-30 mb-1">R$ 1.986.095</p>
                      <p class="kpi-sub mb-0">5 meses</p>
                    </div>
                  </div>
                </div>
              </div>

              <div class="row">
                <div class="col-md-6 mb-4 mb-lg-0 stretch-card transparent">
                  <div class="card card-light-blue">
                    <div class="card-body">
                      <p class="mb-2">Maior mês</p>
                      <p class="fs-30 mb-1">Abr/2025</p>
                      <p class="kpi-sub mb-0">R$ 2.546.197</p>
                    </div>
                  </div>
                </div>

                <div class="col-md-6 stretch-card transparent">
                  <div class="card card-light-danger">
                    <div class="card-body">
                      <p class="mb-2">Agosto/2025</p>
                      <p class="fs-30 mb-1">R$ 1.463.533</p>
                      <p class="kpi-sub mb-0">último mês do relatório</p>
                    </div>
                  </div>
                </div>
              </div>
            </div>

          </div>

          <!-- GRÁFICO + TABELA -->
          <div class="row">

            <div class="col-md-6 grid-margin stretch-card">
              <div class="card">
                <div class="card-body">
                  <div class="d-flex justify-content-between">
                    <p class="card-title">Participação por mês</p>
                    <span class="text-muted text-muted-sm"><i class="ti-pie-chart mr-1"></i> percentual</span>
                  </div>

                  <div class="chart-box">
                    <canvas id="feiraPieMeses"></canvas>
                  </div>

                  <div class="text-muted-sm mt-2">
                    Ajuda a identificar sazonalidade (ex.: meses de maior movimento).
                  </div>
                </div>
              </div>
            </div>

            <div class="col-md-6 grid-margin stretch-card">
              <div class="card">
                <div class="card-body">
                  <p class="card-title mb-1">Resumo mensal (Abr–Ago/2025)</p>
                  <p class="text-muted mb-3">Tabela simples (sem DataTables / sem PHP em filtro).</p>

                  <div class="table-responsive">
                    <table class="table table-striped table-borderless mb-0">
                      <thead>
                        <tr>
                          <th>Mês</th>
                          <th class="text-right">Total (R$)</th>
                        </tr>
                      </thead>
                      <tbody>
                        <tr>
                          <td>Abril/2025</td>
                          <td class="text-right font-weight-bold">2.546.197,00</td>
                        </tr>
                        <tr>
                          <td>Maio/2025</td>
                          <td class="text-right font-weight-bold">2.237.826,00</td>
                        </tr>
                        <tr>
                          <td>Junho/2025</td>
                          <td class="text-right font-weight-bold">2.263.835,00</td>
                        </tr>
                        <tr>
                          <td>Julho/2025</td>
                          <td class="text-right font-weight-bold">1.419.084,00</td>
                        </tr>
                        <tr>
                          <td>Agosto/2025</td>
                          <td class="text-right font-weight-bold">1.463.533,00</td>
                        </tr>
                      </tbody>
                      <tfoot>
                        <tr>
                          <th>Total</th>
                          <th class="text-right">9.930.475,00</th>
                        </tr>
                      </tfoot>
                    </table>
                  </div>

                </div>
              </div>
            </div>

          </div>

          <!-- ATALHOS / EXEMPLO -->
          <div class="row">

            <div class="col-md-7 grid-margin stretch-card">
              <div class="card">
                <div class="card-body">
                  <p class="card-title mb-0">Atalhos rápidos</p>
                  <p class="text-muted mb-3">Acesso direto às rotinas da Feira.</p>

                  <div class="row">
                    <div class="col-12 col-sm-6 mb-2">
                      <a class="btn btn-outline-primary btn-block" href="../adm/feira_produtor/produtos/lista.php">
                        <i class="ti-list mr-2"></i> Ver Produtos
                      </a>
                    </div>
                    <div class="col-12 col-sm-6 mb-2">
                      <a class="btn btn-outline-success btn-block" href="../adm/feira_produtor/produtos/adicionar.php">
                        <i class="ti-plus mr-2"></i> Adicionar Produto
                      </a>
                    </div>
                    <div class="col-12 col-sm-6 mb-2">
                      <a class="btn btn-outline-info btn-block" href="../adm/feira_produtor/lancamentos/">
                        <i class="ti-write mr-2"></i> Lançar Venda
                      </a>
                    </div>
                    <div class="col-12 col-sm-6 mb-2">
                      <a class="btn btn-outline-dark btn-block" href="../adm/feira_produtor/relatorios/mensal.php">
                        <i class="ti-calendar mr-2"></i> Resumo Mensal
                      </a>
                    </div>
                  </div>

                </div>
              </div>
            </div>

            <div class="col-md-5 grid-margin stretch-card">
              <div class="card">
                <div class="card-body">
                  <p class="card-title mb-0">Observações</p>
                  <p class="text-muted mb-3">Notas internas (exemplo).</p>

                  <ul class="pl-3 mb-0">
                    <li class="mb-2">Conferir lançamentos do fechamento do dia.</li>
                    <li class="mb-2">Revisar cadastro de unidades e categorias.</li>
                    <li class="mb-2">Gerar relatório financeiro por período.</li>
                  </ul>

                </div>
              </div>
            </div>

          </div>

        </div>

        <!-- FOOTER (ajeitado) -->
        <footer class="footer">
          <div class="d-flex flex-column flex-sm-row justify-content-between align-items-center">
            <span class="text-muted text-center text-sm-left d-block mb-2 mb-sm-0">
              © <?= date('Y') ?> SIGRelatórios — Feira do Produtor de Coari/AM —
              <a href="https://www.lucascorrea.pro/" target="_blank" rel="noopener">lucascorrea.pro</a>
            </span>

            <span class="text-muted text-center text-sm-right d-block">
              Suporte <i class="ti-headphone-alt ml-1"></i>
            </span>
          </div>
        </footer>

      </div>
    </div>
  </div>

  <!-- plugins:js -->
  <script src="../../../vendors/js/vendor.bundle.base.js"></script>

  <!-- Chart.js -->
  <script src="../../../vendors/chart.js/Chart.min.js"></script>

  <!-- inject:js -->
  <script src="../../../js/off-canvas.js"></script>
  <script src="../../../js/hoverable-collapse.js"></script>
  <script src="../../../js/template.js"></script>
  <script src="../../../js/settings.js"></script>
  <script src="../../../js/todolist.js"></script>

  <script>
    // ======= Dados do relatório (Abr–Ago/2025) =======
    const feiraLabels = ['Abr', 'Mai', 'Jun', 'Jul', 'Ago'];
    const feiraValores = [2546197, 2237826, 2263835, 1419084, 1463533];

    function brlInt(n) {
      try {
        return 'R$ ' + Number(n).toLocaleString('pt-BR');
      } catch (e) {
        return 'R$ ' + n;
      }
    }

    // ======= Gráfico barras (mensal) =======
    (function() {
      const el = document.getElementById('feiraMensalChart');
      if (!el) return;

      const ctx = el.getContext('2d');
      new Chart(ctx, {
        type: 'bar',
        data: {
          labels: feiraLabels,
          datasets: [{
            label: 'Vendas (R$)',
            data: feiraValores,
            borderWidth: 1
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          legend: {
            display: false
          },
          tooltips: {
            callbacks: {
              label: function(tooltipItem) {
                return ' ' + brlInt(tooltipItem.yLabel) + ',00';
              }
            }
          },
          scales: {
            yAxes: [{
              ticks: {
                callback: function(value) {
                  return brlInt(value);
                }
              }
            }]
          }
        }
      });
    })();

    // ======= Gráfico pizza (participação) =======
    (function() {
      const el = document.getElementById('feiraPieMeses');
      if (!el) return;

      const ctx = el.getContext('2d');
      new Chart(ctx, {
        type: 'doughnut',
        data: {
          labels: feiraLabels,
          datasets: [{
            data: feiraValores,
            // cores simples só pra diferenciar
            backgroundColor: ['#4B49AC', '#98BDFF', '#7DA0FA', '#7978E9', '#F3797E']
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          legend: {
            position: 'bottom'
          },
          tooltips: {
            callbacks: {
              label: function(tooltipItem, data) {
                const v = data.datasets[0].data[tooltipItem.index];
                const total = data.datasets[0].data.reduce((a, b) => a + b, 0);
                const pct = total ? (v / total * 100) : 0;
                return ' ' + data.labels[tooltipItem.index] + ': ' + brlInt(v) + ',00 (' + pct.toFixed(1) + '%)';
              }
            }
          }
        }
      });
    })();
  </script>
</body>

</html>