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
if (!is_array($perfis)) $perfis = [$perfis];
if (!in_array('ADMIN', $perfis, true)) {
  header('Location: ../../operador/index.php');
  exit;
}

/* Nome usuário (só pra mostrar no topo) */
$nomeUsuario = $_SESSION['usuario_nome'] ?? 'Usuário';

function h($s): string
{
  return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <title>SIGRelatórios Feira do Produtor</title>

  <!-- plugins:css -->
  <link rel="stylesheet" href="../../../vendors/feather/feather.css">
  <link rel="stylesheet" href="../../../vendors/ti-icons/css/themify-icons.css">
  <link rel="stylesheet" href="../../../vendors/css/vendor.bundle.base.css">
  <!-- endinject -->

  <!-- Plugin css for this page -->
  <link rel="stylesheet" href="../../../vendors/datatables.net-bs4/dataTables.bootstrap4.css">
  <link rel="stylesheet" href="../../../vendors/ti-icons/css/themify-icons.css">
  <link rel="stylesheet" type="text/css" href="../../../js/select.dataTables.min.css">
  <!-- End plugin css for this page -->

  <!-- inject:css -->
  <link rel="stylesheet" href="../../../css/vertical-layout-light/style.css">
  <!-- endinject -->

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

    /* extras visuais (sem JS) */
    .mini-kpi {
      font-size: 12px;
      color: #6c757d;
    }

    .badge-soft {
      background: rgba(0, 0, 0, 0.05);
      border: 1px solid rgba(0, 0, 0, 0.07);
      font-weight: 600;
    }

    .table td,
    .table th {
      vertical-align: middle !important;
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

        <ul class="navbar-nav mr-lg-2">
          <li class="nav-item nav-search d-none d-lg-block"></li>
        </ul>

        <ul class="navbar-nav navbar-nav-right">
          <li class="nav-item">
            <span class="nav-link">
              <i class="ti-user mr-1"></i> <?= h($nomeUsuario) ?> (ADMIN)
            </span>
          </li>
        </ul>

        <button class="navbar-toggler navbar-toggler-right d-lg-none align-self-center" type="button" data-toggle="offcanvas">
          <span class="icon-menu"></span>
        </button>
      </div>
    </nav>
    <!-- /NAVBAR -->

    <div class="container-fluid page-body-wrapper">

      <!-- SETTINGS PANEL (mantido) -->
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
      <!-- /SETTINGS PANEL -->

      <!-- SIDEBAR -->
      <nav class="sidebar sidebar-offcanvas" id="sidebar">
        <ul class="nav">

          <!-- DASHBOARD -->
          <li class="nav-item active">
            <a class="nav-link" href="index.php">
              <i class="icon-grid menu-icon"></i>
              <span class="menu-title">Dashboard</span>
            </a>
          </li>

          <!-- FEIRA DO PRODUTOR -->
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
                    <i class="ti-clipboard mr-2"></i> Lista de Produtos
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
            <div class="collapse text-black" id="feiraRelatorios">
              <ul class="nav flex-column sub-menu" style="background:#fff !important;">
                <li class="nav-item">
                  <a class="nav-link" href="../adm/feira_produtor/relatorios/financeiro.php">
                    <i class="ti-bar-chart mr-2"></i> Relatório Financeiro
                  </a>
                </li>
                <li class="nav-item">
                  <a class="nav-link" href="../adm/feira_produtor/relatorios/produtos.php">
                    <i class="ti-list mr-2"></i> Produtos Comercializados
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

          <!-- SUPORTE -->
          <li class="nav-item">
            <a class="nav-link" href="https://wa.me/92991515710" target="_blank">
              <i class="ti-headphone-alt menu-icon"></i>
              <span class="menu-title">Suporte</span>
            </a>
          </li>

        </ul>
      </nav>
      <!-- /SIDEBAR -->

      <!-- MAIN -->
      <div class="main-panel">
        <div class="content-wrapper">

          <!-- HEADER -->
          <div class="row">
            <div class="col-md-12 grid-margin">
              <div class="row">
                <div class="col-12 col-xl-8 mb-4 mb-xl-0">
                  <h3 class="font-weight-bold">Bem-vindo(a) <?= h($nomeUsuario) ?></h3>
                  <h6 class="font-weight-normal mb-0">
                    Painel administrativo da Feira do Produtor 
                  </h6>
                </div>

                <div class="col-12 col-xl-4">
                  <div class="justify-content-end d-flex">
                    <div class="dropdown flex-md-grow-1 flex-xl-grow-0">
                      <button class="btn btn-sm btn-light bg-white dropdown-toggle" type="button">
                        <i class="ti-calendar mr-1"></i> Hoje (<?= date('d/m/Y') ?>)
                      </button>
                    </div>
                  </div>
                </div>

              </div>
            </div>
          </div>
          <!-- /HEADER -->

          <!-- KPIs (FICTÍCIOS) -->
          <div class="row">
            <div class="col-md-6 grid-margin stretch-card">
              <div class="card tale-bg">
                <div class="card-people mt-auto">
                  <img src="../../../images/dashboard/produtor.jpeg" alt="people" style="max-height:100% !important;">
                  <div class="weather-info">
                    <div class="d-flex">
                      <div>
                        <h2 class="mb-0 font-weight-normal">
                          <i class="icon-sun mr-2"></i> 29<sup>C</sup>
                        </h2>
                      </div>
                      <div class="ml-2">
                        <h4 class="location font-weight-normal">Coari</h4>
                        <h6 class="font-weight-normal">Amazonas</h6>
                      </div>
                    </div>
                    <div class="mt-2 mini-kpi">Clima fictício (depois ligamos no tempo real)</div>
                  </div>
                </div>
              </div>
            </div>

            <div class="col-md-6 grid-margin transparent">
              <div class="row">
                <div class="col-md-6 mb-4 stretch-card transparent">
                  <div class="card card-tale">
                    <div class="card-body">
                      <p class="mb-2">Vendas Hoje</p>
                      <p class="fs-30 mb-1">R$ 4.680,50</p>
                      <p class="mini-kpi">+8,2% (últimos 7 dias)</p>
                    </div>
                  </div>
                </div>
                <div class="col-md-6 mb-4 stretch-card transparent">
                  <div class="card card-dark-blue">
                    <div class="card-body">
                      <p class="mb-2">Total do Mês</p>
                      <p class="fs-30 mb-1">R$ 89.210,00</p>
                      <p class="mini-kpi">Meta: R$ 120.000,00</p>
                    </div>
                  </div>
                </div>
              </div>

              <div class="row">
                <div class="col-md-6 mb-4 mb-lg-0 stretch-card transparent">
                  <div class="card card-light-blue">
                    <div class="card-body">
                      <p class="mb-2">Produtores Ativos</p>
                      <p class="fs-30 mb-1">38</p>
                      <p class="mini-kpi">+2 novos este mês</p>
                    </div>
                  </div>
                </div>
                <div class="col-md-6 stretch-card transparent">
                  <div class="card card-light-danger">
                    <div class="card-body">
                      <p class="mb-2">Produtos Cadastrados</p>
                      <p class="fs-30 mb-1">214</p>
                      <p class="mini-kpi">12 com estoque baixo</p>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <!-- /KPIs -->

          <!-- “GRÁFICOS” SEM JS (substituídos por cards com breakdown) -->
          <div class="row">
            <div class="col-md-6 grid-margin stretch-card">
              <div class="card">
                <div class="card-body">
                  <p class="card-title mb-1">Resumo de Vendas (sem gráfico)</p>
                  <p class="mini-kpi mb-3">Distribuição fictícia por forma de pagamento</p>

                  <div class="table-responsive">
                    <table class="table table-borderless mb-0">
                      <tbody>
                        <tr>
                          <td><span class="badge badge-soft">PIX</span></td>
                          <td class="w-100 px-3">
                            <div class="progress progress-md">
                              <div class="progress-bar bg-success" role="progressbar" style="width: 46%"></div>
                            </div>
                          </td>
                          <td class="text-right font-weight-bold">46%</td>
                        </tr>
                        <tr>
                          <td><span class="badge badge-soft">Dinheiro</span></td>
                          <td class="w-100 px-3">
                            <div class="progress progress-md">
                              <div class="progress-bar bg-primary" role="progressbar" style="width: 32%"></div>
                            </div>
                          </td>
                          <td class="text-right font-weight-bold">32%</td>
                        </tr>
                        <tr>
                          <td><span class="badge badge-soft">Cartão</span></td>
                          <td class="w-100 px-3">
                            <div class="progress progress-md">
                              <div class="progress-bar bg-info" role="progressbar" style="width: 22%"></div>
                            </div>
                          </td>
                          <td class="text-right font-weight-bold">22%</td>
                        </tr>
                      </tbody>
                    </table>
                  </div>

                  <hr>
                  <div class="d-flex flex-wrap">
                    <div class="mr-4 mt-2">
                      <div class="mini-kpi">Itens vendidos (hoje)</div>
                      <div class="font-weight-bold">1.042</div>
                    </div>
                    <div class="mr-4 mt-2">
                      <div class="mini-kpi">Ticket médio</div>
                      <div class="font-weight-bold">R$ 38,60</div>
                    </div>
                    <div class="mr-4 mt-2">
                      <div class="mini-kpi">Cancelamentos</div>
                      <div class="font-weight-bold">3</div>
                    </div>
                    <div class="mt-2">
                      <div class="mini-kpi">Devoluções</div>
                      <div class="font-weight-bold">1</div>
                    </div>
                  </div>

                </div>
              </div>
            </div>

            <div class="col-md-6 grid-margin stretch-card">
              <div class="card">
                <div class="card-body">
                  <div class="d-flex justify-content-between align-items-center">
                    <p class="card-title mb-1">Top Categorias (sem gráfico)</p>
                    <a href="#" class="text-info">Ver tudo</a>
                  </div>
                  <p class="mini-kpi mb-3">Fictício por volume vendido no mês</p>

                  <div class="table-responsive">
                    <table class="table table-striped table-borderless mb-0">
                      <thead>
                        <tr>
                          <th>Categoria</th>
                          <th class="text-right">Itens</th>
                          <th class="text-right">Faturamento</th>
                        </tr>
                      </thead>
                      <tbody>
                        <tr>
                          <td>Hortaliças</td>
                          <td class="text-right">8.420</td>
                          <td class="text-right font-weight-bold">R$ 31.550,00</td>
                        </tr>
                        <tr>
                          <td>Frutas</td>
                          <td class="text-right">6.980</td>
                          <td class="text-right font-weight-bold">R$ 27.840,00</td>
                        </tr>
                        <tr>
                          <td>Raízes e Tubérculos</td>
                          <td class="text-right">4.120</td>
                          <td class="text-right font-weight-bold">R$ 18.910,00</td>
                        </tr>
                        <tr>
                          <td>Temperos e Ervas</td>
                          <td class="text-right">2.010</td>
                          <td class="text-right font-weight-bold">R$ 6.430,00</td>
                        </tr>
                        <tr>
                          <td>Laticínios e Ovos</td>
                          <td class="text-right">1.220</td>
                          <td class="text-right font-weight-bold">R$ 4.480,00</td>
                        </tr>
                      </tbody>
                    </table>
                  </div>

                  <hr>
                  <div class="mini-kpi">Observação: depois trocamos esse bloco por Chart.js quando você quiser.</div>
                </div>
              </div>
            </div>
          </div>

          <!-- RELATÓRIO DETALHADO (SEM CAROUSEL/JS) -->
          <div class="row">
            <div class="col-md-12 grid-margin stretch-card">
              <div class="card position-relative">
                <div class="card-body">
                  <div class="row">
                    <div class="col-md-12 col-xl-3 d-flex flex-column justify-content-start">
                      <div class="ml-xl-4 mt-3">
                        <p class="card-title">Relatório Detalhado do Mês</p>
                        <h1 class="text-primary">R$ 89.210,00</h1>
                        <h3 class="font-weight-500 mb-xl-4 text-primary">Feira do Produtor</h3>
                        <p class="mb-2 mb-xl-0">Dados fictícios com visão pronta pra ligar no banco: vendas por dia, por produtor, por categoria, alertas de estoque e pendências.</p>
                      </div>
                    </div>

                    <div class="col-md-12 col-xl-9">
                      <div class="row">
                        <div class="col-md-6 border-right">
                          <div class="table-responsive mb-3 mb-md-0 mt-3">
                            <table class="table table-borderless report-table">
                              <tr>
                                <td class="text-muted">Dias com maior venda</td>
                                <td class="w-100 px-0">
                                  <div class="progress progress-md mx-4">
                                    <div class="progress-bar bg-primary" role="progressbar" style="width: 78%"></div>
                                  </div>
                                </td>
                                <td>
                                  <h5 class="font-weight-bold mb-0">12 dias</h5>
                                </td>
                              </tr>
                              <tr>
                                <td class="text-muted">Dias médios</td>
                                <td class="w-100 px-0">
                                  <div class="progress progress-md mx-4">
                                    <div class="progress-bar bg-info" role="progressbar" style="width: 54%"></div>
                                  </div>
                                </td>
                                <td>
                                  <h5 class="font-weight-bold mb-0">14 dias</h5>
                                </td>
                              </tr>
                              <tr>
                                <td class="text-muted">Dias fracos</td>
                                <td class="w-100 px-0">
                                  <div class="progress progress-md mx-4">
                                    <div class="progress-bar bg-warning" role="progressbar" style="width: 23%"></div>
                                  </div>
                                </td>
                                <td>
                                  <h5 class="font-weight-bold mb-0">4 dias</h5>
                                </td>
                              </tr>
                              <tr>
                                <td class="text-muted">Fechamentos pendentes</td>
                                <td class="w-100 px-0">
                                  <div class="progress progress-md mx-4">
                                    <div class="progress-bar bg-danger" role="progressbar" style="width: 12%"></div>
                                  </div>
                                </td>
                                <td>
                                  <h5 class="font-weight-bold mb-0">2</h5>
                                </td>
                              </tr>
                              <tr>
                                <td class="text-muted">Produtos com estoque baixo</td>
                                <td class="w-100 px-0">
                                  <div class="progress progress-md mx-4">
                                    <div class="progress-bar bg-danger" role="progressbar" style="width: 18%"></div>
                                  </div>
                                </td>
                                <td>
                                  <h5 class="font-weight-bold mb-0">12</h5>
                                </td>
                              </tr>
                              <tr>
                                <td class="text-muted">Novos produtores no mês</td>
                                <td class="w-100 px-0">
                                  <div class="progress progress-md mx-4">
                                    <div class="progress-bar bg-success" role="progressbar" style="width: 9%"></div>
                                  </div>
                                </td>
                                <td>
                                  <h5 class="font-weight-bold mb-0">2</h5>
                                </td>
                              </tr>
                            </table>
                          </div>
                        </div>

                        <div class="col-md-6 mt-3">
                          <p class="card-title mb-2">Alertas e Pendências</p>
                          <ul class="list-group">
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                              Fechamento do dia pendente (<?= date('d/m/Y', strtotime('-1 day')) ?>)
                              <span class="badge badge-danger badge-pill">PENDENTE</span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                              Produto “Tomate” com estoque abaixo do mínimo
                              <span class="badge badge-warning badge-pill">ALERTA</span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                              Produto “Cheiro-verde” com preço referência desatualizado
                              <span class="badge badge-info badge-pill">INFO</span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                              3 lançamentos com forma de pagamento não informada
                              <span class="badge badge-danger badge-pill">REVISAR</span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                              Exportação mensal pronta (CSV/PDF)
                              <span class="badge badge-success badge-pill">OK</span>
                            </li>
                          </ul>
                          <div class="mini-kpi mt-2">Tudo fictício — serve só pra preencher o painel sem JS.</div>
                        </div>

                      </div>
                    </div>

                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- TABELA: TOP PRODUTOS (FICTÍCIOS) -->
          <div class="row">
            <div class="col-md-7 grid-margin stretch-card">
              <div class="card">
                <div class="card-body">
                  <p class="card-title mb-0">Top Produtos (fictício)</p>
                  <div class="table-responsive mt-3">
                    <table class="table table-striped table-borderless">
                      <thead>
                        <tr>
                          <th>Produto</th>
                          <th class="text-right">Preço</th>
                          <th>Última venda</th>
                          <th>Status</th>
                        </tr>
                      </thead>
                      <tbody>
                        <tr>
                          <td>Tomate</td>
                          <td class="text-right font-weight-bold">R$ 8,50/kg</td>
                          <td><?= date('d/m/Y') ?></td>
                          <td class="font-weight-medium">
                            <div class="badge badge-success">Em alta</div>
                          </td>
                        </tr>
                        <tr>
                          <td>Banana</td>
                          <td class="text-right font-weight-bold">R$ 6,00/kg</td>
                          <td><?= date('d/m/Y', strtotime('-1 day')) ?></td>
                          <td class="font-weight-medium">
                            <div class="badge badge-success">Estável</div>
                          </td>
                        </tr>
                        <tr>
                          <td>Macaxeira</td>
                          <td class="text-right font-weight-bold">R$ 5,00/kg</td>
                          <td><?= date('d/m/Y', strtotime('-2 day')) ?></td>
                          <td class="font-weight-medium">
                            <div class="badge badge-warning">Oscilando</div>
                          </td>
                        </tr>
                        <tr>
                          <td>Cheiro-verde</td>
                          <td class="text-right font-weight-bold">R$ 2,50/maço</td>
                          <td><?= date('d/m/Y', strtotime('-2 day')) ?></td>
                          <td class="font-weight-medium">
                            <div class="badge badge-success">Em alta</div>
                          </td>
                        </tr>
                        <tr>
                          <td>Ovos caipira</td>
                          <td class="text-right font-weight-bold">R$ 18,00/bandeja</td>
                          <td><?= date('d/m/Y', strtotime('-3 day')) ?></td>
                          <td class="font-weight-medium">
                            <div class="badge badge-danger">Baixo estoque</div>
                          </td>
                        </tr>
                        <tr>
                          <td>Pimenta de cheiro</td>
                          <td class="text-right font-weight-bold">R$ 12,00/kg</td>
                          <td><?= date('d/m/Y', strtotime('-3 day')) ?></td>
                          <td class="font-weight-medium">
                            <div class="badge badge-warning">Oscilando</div>
                          </td>
                        </tr>
                        <tr>
                          <td>Farinha d’água</td>
                          <td class="text-right font-weight-bold">R$ 9,00/kg</td>
                          <td><?= date('d/m/Y', strtotime('-4 day')) ?></td>
                          <td class="font-weight-medium">
                            <div class="badge badge-success">Estável</div>
                          </td>
                        </tr>
                      </tbody>
                    </table>
                  </div>

                  <div class="mini-kpi">Depois dá pra trocar “Status” por regras reais (estoque_min, giro, etc.).</div>
                </div>
              </div>
            </div>

            <!-- LISTA DE TAREFAS (sem JS - só visual) -->
            <div class="col-md-5 grid-margin stretch-card">
              <div class="card">
                <div class="card-body">
                  <h4 class="card-title">Checklist do Admin (fictício)</h4>
                  <div class="list-wrapper pt-2">
                    <ul class="todo-list todo-list-custom" style="padding-left:0; list-style:none;">
                      <li class="d-flex justify-content-between align-items-center mb-2">
                        <div>
                          <input type="checkbox" checked> Conferir fechamento de ontem
                        </div>
                        <span class="badge badge-success">OK</span>
                      </li>
                      <li class="d-flex justify-content-between align-items-center mb-2">
                        <div>
                          <input type="checkbox"> Revisar lançamentos sem forma de pagamento
                        </div>
                        <span class="badge badge-warning">Pendente</span>
                      </li>
                      <li class="d-flex justify-content-between align-items-center mb-2">
                        <div>
                          <input type="checkbox"> Atualizar preços referência (hortaliças)
                        </div>
                        <span class="badge badge-info">Hoje</span>
                      </li>
                      <li class="d-flex justify-content-between align-items-center mb-2">
                        <div>
                          <input type="checkbox" checked> Gerar relatório mensal preliminar
                        </div>
                        <span class="badge badge-success">OK</span>
                      </li>
                      <li class="d-flex justify-content-between align-items-center">
                        <div>
                          <input type="checkbox"> Validar estoque baixo e avisar produtores
                        </div>
                        <span class="badge badge-danger">Importante</span>
                      </li>
                    </ul>
                  </div>

                  <div class="mt-3">
                    <label class="mini-kpi mb-1">Adicionar lembrete (somente visual)</label>
                    <div class="d-flex">
                      <input type="text" class="form-control" placeholder="Ex.: Conferir caixa 18h">
                      <button type="button" class="btn btn-primary ml-2">Adicionar</button>
                    </div>
                  </div>

                </div>
              </div>
            </div>
          </div>

          <!-- 3 COLUNAS: PRODUTORES / ESTOQUE BAIXO / ÚLTIMOS LANÇAMENTOS -->
          <div class="row">
            <div class="col-md-4 stretch-card grid-margin">
              <div class="card">
                <div class="card-body">
                  <p class="card-title mb-0">Produtores (amostra)</p>
                  <div class="table-responsive mt-3">
                    <table class="table table-borderless">
                      <thead>
                        <tr>
                          <th class="pl-0 pb-2 border-bottom">Produtor</th>
                          <th class="pb-2 border-bottom">Comunidade</th>
                          <th class="pb-2 border-bottom text-right">Status</th>
                        </tr>
                      </thead>
                      <tbody>
                        <tr>
                          <td class="pl-0">Maria do Socorro</td>
                          <td>Urucu</td>
                          <td class="text-right"><span class="badge badge-success">Ativo</span></td>
                        </tr>
                        <tr>
                          <td class="pl-0">João Silva</td>
                          <td>Pera</td>
                          <td class="text-right"><span class="badge badge-success">Ativo</span></td>
                        </tr>
                        <tr>
                          <td class="pl-0">Ana Pereira</td>
                          <td>Itapeuá</td>
                          <td class="text-right"><span class="badge badge-success">Ativo</span></td>
                        </tr>
                        <tr>
                          <td class="pl-0">Pedro Almeida</td>
                          <td>Codajás-Mirim</td>
                          <td class="text-right"><span class="badge badge-warning">Em análise</span></td>
                        </tr>
                        <tr>
                          <td class="pl-0">Francisca Lima</td>
                          <td>Lago do Muru</td>
                          <td class="text-right"><span class="badge badge-success">Ativo</span></td>
                        </tr>
                        <tr>
                          <td class="pl-0">Antônio Costa</td>
                          <td>Itapuru</td>
                          <td class="text-right"><span class="badge badge-danger">Inativo</span></td>
                        </tr>
                      </tbody>
                    </table>
                  </div>
                  <div class="mini-kpi">Depois isso vira busca/paginação e “status” real.</div>
                </div>
              </div>
            </div>

            <div class="col-md-4 stretch-card grid-margin">
              <div class="card">
                <div class="card-body">
                  <p class="card-title mb-0">Estoque baixo (amostra)</p>
                  <div class="table-responsive mt-3">
                    <table class="table table-borderless">
                      <thead>
                        <tr>
                          <th class="pl-0 pb-2 border-bottom">Produto</th>
                          <th class="pb-2 border-bottom text-right">Atual</th>
                          <th class="pb-2 border-bottom text-right">Mín.</th>
                        </tr>
                      </thead>
                      <tbody>
                        <tr>
                          <td class="pl-0">Ovos caipira</td>
                          <td class="text-right"><span class="badge badge-danger">3</span></td>
                          <td class="text-right">10</td>
                        </tr>
                        <tr>
                          <td class="pl-0">Tomate</td>
                          <td class="text-right"><span class="badge badge-warning">18 kg</span></td>
                          <td class="text-right">25 kg</td>
                        </tr>
                        <tr>
                          <td class="pl-0">Cheiro-verde</td>
                          <td class="text-right"><span class="badge badge-warning">40 maços</span></td>
                          <td class="text-right">60 maços</td>
                        </tr>
                        <tr>
                          <td class="pl-0">Pimenta de cheiro</td>
                          <td class="text-right"><span class="badge badge-warning">9 kg</span></td>
                          <td class="text-right">15 kg</td>
                        </tr>
                        <tr>
                          <td class="pl-0">Macaxeira</td>
                          <td class="text-right"><span class="badge badge-warning">22 kg</span></td>
                          <td class="text-right">30 kg</td>
                        </tr>
                      </tbody>
                    </table>
                  </div>
                  <div class="mini-kpi">Regra futura: se estoque &lt; estoque_min, alerta automático.</div>
                </div>
              </div>
            </div>

            <div class="col-md-4 stretch-card grid-margin">
              <div class="card">
                <div class="card-body">
                  <p class="card-title mb-0">Últimos lançamentos (amostra)</p>
                  <div class="table-responsive mt-3">
                    <table class="table table-borderless">
                      <thead>
                        <tr>
                          <th class="pl-0 pb-2 border-bottom">Data</th>
                          <th class="pb-2 border-bottom">Produtor</th>
                          <th class="pb-2 border-bottom text-right">Total</th>
                        </tr>
                      </thead>
                      <tbody>
                        <tr>
                          <td class="pl-0"><?= date('d/m/Y') ?></td>
                          <td>Maria do Socorro</td>
                          <td class="text-right font-weight-bold">R$ 352,00</td>
                        </tr>
                        <tr>
                          <td class="pl-0"><?= date('d/m/Y') ?></td>
                          <td>João Silva</td>
                          <td class="text-right font-weight-bold">R$ 198,50</td>
                        </tr>
                        <tr>
                          <td class="pl-0"><?= date('d/m/Y', strtotime('-1 day')) ?></td>
                          <td>Ana Pereira</td>
                          <td class="text-right font-weight-bold">R$ 421,80</td>
                        </tr>
                        <tr>
                          <td class="pl-0"><?= date('d/m/Y', strtotime('-2 day')) ?></td>
                          <td>Pedro Almeida</td>
                          <td class="text-right font-weight-bold">R$ 87,00</td>
                        </tr>
                        <tr>
                          <td class="pl-0"><?= date('d/m/Y', strtotime('-3 day')) ?></td>
                          <td>Francisca Lima</td>
                          <td class="text-right font-weight-bold">R$ 260,10</td>
                        </tr>
                      </tbody>
                    </table>
                  </div>
                  <div class="mini-kpi">Depois vira “lancamentos” com filtros e impressão.</div>
                </div>
              </div>
            </div>
          </div>

          <!-- TABELA GRANDE (SEM DATATABLES/JS) -->
          <div class="row">
            <div class="col-md-12 grid-margin stretch-card">
              <div class="card">
                <div class="card-body">
                  <p class="card-title">Tabela Avançada (fictícia, sem JS)</p>
                  <p class="mini-kpi mb-3">
                    Modelo de dados pra virar listagem real: id, produto, categoria, produtor, quantidade, unidade, valor, forma_pagamento, status, criado_em.
                  </p>

                  <div class="table-responsive">
                    <table class="table table-striped table-borderless" style="width:100%">
                      <thead>
                        <tr>
                          <th>ID</th>
                          <th>Produto</th>
                          <th>Categoria</th>
                          <th>Produtor</th>
                          <th class="text-right">Qtd</th>
                          <th>Unid.</th>
                          <th class="text-right">Valor</th>
                          <th>Pagamento</th>
                          <th>Status</th>
                          <th>Atualizado em</th>
                        </tr>
                      </thead>
                      <tbody>
                        <tr>
                          <td>#000148</td>
                          <td>Tomate</td>
                          <td>Hortaliças</td>
                          <td>Maria do Socorro</td>
                          <td class="text-right">35</td>
                          <td>kg</td>
                          <td class="text-right font-weight-bold">R$ 297,50</td>
                          <td><span class="badge badge-soft">PIX</span></td>
                          <td><span class="badge badge-success">Concluído</span></td>
                          <td><?= date('d/m/Y') ?> 11:22</td>
                        </tr>
                        <tr>
                          <td>#000149</td>
                          <td>Banana</td>
                          <td>Frutas</td>
                          <td>João Silva</td>
                          <td class="text-right">28</td>
                          <td>kg</td>
                          <td class="text-right font-weight-bold">R$ 168,00</td>
                          <td><span class="badge badge-soft">Dinheiro</span></td>
                          <td><span class="badge badge-success">Concluído</span></td>
                          <td><?= date('d/m/Y') ?> 10:41</td>
                        </tr>
                        <tr>
                          <td>#000150</td>
                          <td>Macaxeira</td>
                          <td>Raízes e Tubérculos</td>
                          <td>Ana Pereira</td>
                          <td class="text-right">18</td>
                          <td>kg</td>
                          <td class="text-right font-weight-bold">R$ 90,00</td>
                          <td><span class="badge badge-soft">Cartão</span></td>
                          <td><span class="badge badge-warning">Pendente</span></td>
                          <td><?= date('d/m/Y', strtotime('-1 day')) ?> 17:05</td>
                        </tr>
                        <tr>
                          <td>#000151</td>
                          <td>Ovos caipira</td>
                          <td>Laticínios e Ovos</td>
                          <td>Francisca Lima</td>
                          <td class="text-right">6</td>
                          <td>bandeja</td>
                          <td class="text-right font-weight-bold">R$ 108,00</td>
                          <td><span class="badge badge-soft">PIX</span></td>
                          <td><span class="badge badge-danger">Cancelado</span></td>
                          <td><?= date('d/m/Y', strtotime('-2 day')) ?> 09:12</td>
                        </tr>
                        <tr>
                          <td>#000152</td>
                          <td>Cheiro-verde</td>
                          <td>Temperos e Ervas</td>
                          <td>Pedro Almeida</td>
                          <td class="text-right">50</td>
                          <td>maço</td>
                          <td class="text-right font-weight-bold">R$ 125,00</td>
                          <td><span class="badge badge-soft">Dinheiro</span></td>
                          <td><span class="badge badge-success">Concluído</span></td>
                          <td><?= date('d/m/Y', strtotime('-3 day')) ?> 15:48</td>
                        </tr>
                      </tbody>
                    </table>
                  </div>

                  <div class="mini-kpi mt-3">
                    Dica: quando ligar no banco, essa tabela vira paginação (limit/offset), filtros (data, produtor, categoria, pagamento) e exportação.
                  </div>
                </div>
              </div>
            </div>
          </div>

        </div>
        <!-- content-wrapper ends -->

        <!-- FOOTER -->
        <footer class="footer">
          <div class="d-flex flex-column flex-sm-row justify-content-between align-items-center">
            <span class="text-muted text-center text-sm-left d-block mb-2 mb-sm-0">
              © <?= date('Y') ?> SIGRelatórios —
              <a href="https://www.lucascorrea.pro/" target="_blank" rel="noopener">lucascorrea.pro</a>
              . Todos os direitos reservados.
            </span>
          </div>
        </footer>
        <!-- /FOOTER -->

      </div>
      <!-- main-panel ends -->
    </div>
    <!-- page-body-wrapper ends -->
  </div>
  <!-- container-scroller -->

  <!-- SEM JS (como você pediu) -->
</body>
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

</html>