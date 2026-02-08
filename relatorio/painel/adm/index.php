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
if (!in_array('ADMIN', $perfis, true)) {
  header('Location: ../operador/index.php');
  exit;
}
$nomeTopo = $_SESSION['usuario_nome'] ?? 'Admin';

// Conexão com banco de dados (ajuste conforme sua configuração)
require_once '../../assets/php/conexao.php';

// Filtro de feira selecionada
$feira_selecionada = isset($_GET['feira_id']) ? (int)$_GET['feira_id'] : 0;

// Buscar todas as feiras
$sql_feiras = "SELECT id, codigo, nome FROM feiras WHERE ativo = 1 ORDER BY nome";
$stmt_feiras = $pdo->query($sql_feiras);
$feiras = $stmt_feiras->fetchAll(PDO::FETCH_ASSOC);

// Função helper
function h($s) {
  return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

// Dados do dashboard (filtrados por feira se selecionada)
$where_feira = $feira_selecionada > 0 ? "WHERE feira_id = :feira_id" : "";

// Total de vendas do dia
$sql_vendas_hoje = "SELECT COUNT(*) as total, COALESCE(SUM(total), 0) as valor_total 
                    FROM vendas 
                    $where_feira AND DATE(data_hora) = CURDATE()";
$stmt_vendas_hoje = $pdo->prepare($sql_vendas_hoje);
if ($feira_selecionada > 0) $stmt_vendas_hoje->bindValue(':feira_id', $feira_selecionada, PDO::PARAM_INT);
$stmt_vendas_hoje->execute();
$vendas_hoje = $stmt_vendas_hoje->fetch(PDO::FETCH_ASSOC);

// Total de vendas do mês
$sql_vendas_mes = "SELECT COUNT(*) as total, COALESCE(SUM(total), 0) as valor_total 
                   FROM vendas 
                   $where_feira AND MONTH(data_hora) = MONTH(CURDATE()) AND YEAR(data_hora) = YEAR(CURDATE())";
$stmt_vendas_mes = $pdo->prepare($sql_vendas_mes);
if ($feira_selecionada > 0) $stmt_vendas_mes->bindValue(':feira_id', $feira_selecionada, PDO::PARAM_INT);
$stmt_vendas_mes->execute();
$vendas_mes = $stmt_vendas_mes->fetch(PDO::FETCH_ASSOC);

// Total de produtores
$sql_produtores = "SELECT COUNT(*) as total FROM produtores $where_feira AND ativo = 1";
$stmt_produtores = $pdo->prepare($sql_produtores);
if ($feira_selecionada > 0) $stmt_produtores->bindValue(':feira_id', $feira_selecionada, PDO::PARAM_INT);
$stmt_produtores->execute();
$total_produtores = $stmt_produtores->fetch(PDO::FETCH_ASSOC)['total'];

// Total de produtos
$sql_produtos = "SELECT COUNT(*) as total FROM produtos $where_feira AND ativo = 1";
$stmt_produtos = $pdo->prepare($sql_produtos);
if ($feira_selecionada > 0) $stmt_produtos->bindValue(':feira_id', $feira_selecionada, PDO::PARAM_INT);
$stmt_produtos->execute();
$total_produtos = $stmt_produtos->fetch(PDO::FETCH_ASSOC)['total'];

// Vendas por forma de pagamento (para gráfico)
$sql_forma_pagamento = "SELECT forma_pagamento, COALESCE(SUM(total), 0) as valor_total 
                        FROM vendas 
                        $where_feira 
                        AND MONTH(data_hora) = MONTH(CURDATE()) 
                        AND YEAR(data_hora) = YEAR(CURDATE())
                        GROUP BY forma_pagamento";
$stmt_forma_pagamento = $pdo->prepare($sql_forma_pagamento);
if ($feira_selecionada > 0) $stmt_forma_pagamento->bindValue(':feira_id', $feira_selecionada, PDO::PARAM_INT);
$stmt_forma_pagamento->execute();
$vendas_forma_pagamento = $stmt_forma_pagamento->fetchAll(PDO::FETCH_ASSOC);

// Vendas por categoria (para gráfico)
$sql_vendas_categoria = "SELECT c.nome, COUNT(vi.id) as qtd_vendas, COALESCE(SUM(vi.subtotal), 0) as valor_total
                         FROM venda_itens vi
                         INNER JOIN produtos p ON vi.produto_id = p.id
                         INNER JOIN categorias c ON p.categoria_id = c.id
                         WHERE 1=1 " . ($feira_selecionada > 0 ? "AND vi.feira_id = :feira_id" : "") . "
                         AND MONTH(vi.criado_em) = MONTH(CURDATE())
                         AND YEAR(vi.criado_em) = YEAR(CURDATE())
                         GROUP BY c.id, c.nome
                         ORDER BY valor_total DESC
                         LIMIT 10";
$stmt_vendas_categoria = $pdo->prepare($sql_vendas_categoria);
if ($feira_selecionada > 0) $stmt_vendas_categoria->bindValue(':feira_id', $feira_selecionada, PDO::PARAM_INT);
$stmt_vendas_categoria->execute();
$vendas_categoria = $stmt_vendas_categoria->fetchAll(PDO::FETCH_ASSOC);

// Top 10 produtos mais vendidos
$sql_top_produtos = "SELECT p.nome, COUNT(vi.id) as qtd_vendas, COALESCE(SUM(vi.subtotal), 0) as valor_total
                     FROM venda_itens vi
                     INNER JOIN produtos p ON vi.produto_id = p.id
                     WHERE 1=1 " . ($feira_selecionada > 0 ? "AND vi.feira_id = :feira_id" : "") . "
                     AND MONTH(vi.criado_em) = MONTH(CURDATE())
                     AND YEAR(vi.criado_em) = YEAR(CURDATE())
                     GROUP BY p.id, p.nome
                     ORDER BY qtd_vendas DESC
                     LIMIT 10";
$stmt_top_produtos = $pdo->prepare($sql_top_produtos);
if ($feira_selecionada > 0) $stmt_top_produtos->bindValue(':feira_id', $feira_selecionada, PDO::PARAM_INT);
$stmt_top_produtos->execute();
$top_produtos = $stmt_top_produtos->fetchAll(PDO::FETCH_ASSOC);

// Vendas dos últimos 7 dias (para gráfico de linha)
$sql_vendas_semana = "SELECT DATE(data_hora) as data, COUNT(*) as qtd, COALESCE(SUM(total), 0) as valor
                      FROM vendas
                      $where_feira
                      AND data_hora >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                      GROUP BY DATE(data_hora)
                      ORDER BY data";
$stmt_vendas_semana = $pdo->prepare($sql_vendas_semana);
if ($feira_selecionada > 0) $stmt_vendas_semana->bindValue(':feira_id', $feira_selecionada, PDO::PARAM_INT);
$stmt_vendas_semana->execute();
$vendas_semana = $stmt_vendas_semana->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="pt-BR">

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
    .card-stats {
      transition: transform 0.2s;
    }
    .card-stats:hover {
      transform: translateY(-5px);
      box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    }
    .chart-container {
      position: relative;
      height: 300px;
      margin: 20px 0;
    }
    .filter-section {
      background: #f8f9fa;
      padding: 15px;
      border-radius: 8px;
      margin-bottom: 20px;
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
          <div class="row">
            <div class="col-md-12 grid-margin">
              <div class="row">
                <div class="col-12 col-xl-8 mb-4 mb-xl-0">
                  <h3 class="font-weight-bold">Bem-vindo(a) <?= h($_SESSION['usuario_nome'] ?? 'Usuário') ?></h3>
                  <h6 class="font-weight-normal mb-0">
                    Todos os sistemas estão funcionando normalmente!
                  </h6>
                </div>

                <div class="col-12 col-xl-4">
                  <div class="justify-content-end d-flex">
                    <div class="dropdown flex-md-grow-1 flex-xl-grow-0">
                      <button class="btn btn-sm btn-light bg-white dropdown-toggle" type="button" id="dropdownMenuDate2" data-toggle="dropdown" aria-haspopup="true" aria-expanded="true">
                        <i class="mdi mdi-calendar"></i> <?= date('d M Y') ?>
                      </button>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Filtro de Feira -->
          <div class="row">
            <div class="col-md-12">
              <div class="filter-section">
                <form method="GET" action="index.php" class="form-inline">
                  <label class="mr-2"><strong>Filtrar por Feira:</strong></label>
                  <select name="feira_id" class="form-control mr-2" onchange="this.form.submit()">
                    <option value="0">Todas as Feiras</option>
                    <?php foreach ($feiras as $feira): ?>
                      <option value="<?= $feira['id'] ?>" <?= $feira_selecionada == $feira['id'] ? 'selected' : '' ?>>
                        <?= h($feira['nome']) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                  <button type="submit" class="btn btn-primary btn-sm">Aplicar Filtro</button>
                </form>
              </div>
            </div>
          </div>

          <!-- Cards de Estatísticas -->
          <div class="row">
            <div class="col-md-3 mb-4 stretch-card transparent">
              <div class="card card-tale card-stats">
                <div class="card-body">
                  <p class="mb-4">Vendas Hoje</p>
                  <p class="fs-30 mb-2"><?= $vendas_hoje['total'] ?></p>
                  <p>R$ <?= number_format($vendas_hoje['valor_total'], 2, ',', '.') ?></p>
                </div>
              </div>
            </div>
            <div class="col-md-3 mb-4 stretch-card transparent">
              <div class="card card-dark-blue card-stats">
                <div class="card-body">
                  <p class="mb-4">Vendas do Mês</p>
                  <p class="fs-30 mb-2"><?= $vendas_mes['total'] ?></p>
                  <p>R$ <?= number_format($vendas_mes['valor_total'], 2, ',', '.') ?></p>
                </div>
              </div>
            </div>
            <div class="col-md-3 mb-4 stretch-card transparent">
              <div class="card card-light-blue card-stats">
                <div class="card-body">
                  <p class="mb-4">Total de Produtores</p>
                  <p class="fs-30 mb-2"><?= $total_produtores ?></p>
                  <p>Ativos no sistema</p>
                </div>
              </div>
            </div>
            <div class="col-md-3 mb-4 stretch-card transparent">
              <div class="card card-light-danger card-stats">
                <div class="card-body">
                  <p class="mb-4">Total de Produtos</p>
                  <p class="fs-30 mb-2"><?= $total_produtos ?></p>
                  <p>Cadastrados</p>
                </div>
              </div>
            </div>
          </div>

          <!-- Gráficos -->
          <div class="row">
            <div class="col-md-6 grid-margin stretch-card">
              <div class="card">
                <div class="card-body">
                  <p class="card-title">Vendas por Forma de Pagamento (Mês Atual)</p>
                  <div class="chart-container">
                    <canvas id="formaPagamentoChart"></canvas>
                  </div>
                </div>
              </div>
            </div>
            <div class="col-md-6 grid-margin stretch-card">
              <div class="card">
                <div class="card-body">
                  <p class="card-title">Vendas dos Últimos 7 Dias</p>
                  <div class="chart-container">
                    <canvas id="vendasSemanaChart"></canvas>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="row">
            <div class="col-md-12 grid-margin stretch-card">
              <div class="card">
                <div class="card-body">
                  <p class="card-title">Top 10 Categorias (Mês Atual)</p>
                  <div class="chart-container">
                    <canvas id="categoriaChart"></canvas>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Tabelas -->
          <div class="row">
            <div class="col-md-12 grid-margin stretch-card">
              <div class="card">
                <div class="card-body">
                  <p class="card-title">Top 10 Produtos Mais Vendidos (Mês Atual)</p>
                  <div class="table-responsive">
                    <table class="table table-striped table-borderless" id="topProdutosTable">
                      <thead>
                        <tr>
                          <th>Produto</th>
                          <th class="text-center">Quantidade Vendida</th>
                          <th class="text-right">Valor Total</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php foreach ($top_produtos as $produto): ?>
                        <tr>
                          <td><?= h($produto['nome']) ?></td>
                          <td class="text-center font-weight-bold"><?= $produto['qtd_vendas'] ?></td>
                          <td class="text-right">R$ <?= number_format($produto['valor_total'], 2, ',', '.') ?></td>
                        </tr>
                        <?php endforeach; ?>
                      </tbody>
                    </table>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Tabela Avançada com DataTables -->
          <div class="row">
            <div class="col-md-12 grid-margin stretch-card">
              <div class="card">
                <div class="card-body">
                  <p class="card-title">Últimas Vendas</p>
                  <div class="row">
                    <div class="col-12">
                      <div class="table-responsive">
                        <table id="vendasTable" class="table table-striped table-bordered" style="width:100%">
                          <thead>
                            <tr>
                              <th>ID</th>
                              <th>Data/Hora</th>
                              <th>Forma Pagamento</th>
                              <th>Total</th>
                              <th>Status</th>
                              <th>Ações</th>
                            </tr>
                          </thead>
                          <tbody>
                            <!-- Dados carregados via AJAX -->
                          </tbody>
                        </table>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

        </div>
        <!-- content-wrapper ends -->
        <!-- partial:partials/_footer.html -->
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
        <!-- partial -->
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
  <script src="../../vendors/datatables.net/jquery.dataTables.js"></script>
  <script src="../../vendors/datatables.net-bs4/dataTables.bootstrap4.js"></script>
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

  <script>
    // Dados PHP para JavaScript
    const formaPagamentoData = <?= json_encode($vendas_forma_pagamento) ?>;
    const categoriaData = <?= json_encode($vendas_categoria) ?>;
    const vendasSemanaData = <?= json_encode($vendas_semana) ?>;
    const feiraId = <?= $feira_selecionada ?>;

    // Gráfico de Forma de Pagamento
    const ctxFormaPagamento = document.getElementById('formaPagamentoChart').getContext('2d');
    new Chart(ctxFormaPagamento, {
      type: 'doughnut',
      data: {
        labels: formaPagamentoData.map(item => item.forma_pagamento),
        datasets: [{
          label: 'Valor Total',
          data: formaPagamentoData.map(item => parseFloat(item.valor_total)),
          backgroundColor: [
            'rgba(255, 99, 132, 0.8)',
            'rgba(54, 162, 235, 0.8)',
            'rgba(255, 206, 86, 0.8)',
            'rgba(75, 192, 192, 0.8)',
            'rgba(153, 102, 255, 0.8)'
          ]
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

    // Gráfico de Vendas da Semana
    const ctxVendasSemana = document.getElementById('vendasSemanaChart').getContext('2d');
    new Chart(ctxVendasSemana, {
      type: 'line',
      data: {
        labels: vendasSemanaData.map(item => {
          const date = new Date(item.data);
          return date.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit' });
        }),
        datasets: [{
          label: 'Quantidade',
          data: vendasSemanaData.map(item => parseInt(item.qtd)),
          borderColor: 'rgba(75, 192, 192, 1)',
          backgroundColor: 'rgba(75, 192, 192, 0.2)',
          yAxisID: 'y'
        }, {
          label: 'Valor (R$)',
          data: vendasSemanaData.map(item => parseFloat(item.valor)),
          borderColor: 'rgba(255, 99, 132, 1)',
          backgroundColor: 'rgba(255, 99, 132, 0.2)',
          yAxisID: 'y1'
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: {
          mode: 'index',
          intersect: false
        },
        scales: {
          y: {
            type: 'linear',
            display: true,
            position: 'left',
            title: {
              display: true,
              text: 'Quantidade'
            }
          },
          y1: {
            type: 'linear',
            display: true,
            position: 'right',
            title: {
              display: true,
              text: 'Valor (R$)'
            },
            grid: {
              drawOnChartArea: false
            }
          }
        }
      }
    });

    // Gráfico de Categorias
    const ctxCategoria = document.getElementById('categoriaChart').getContext('2d');
    new Chart(ctxCategoria, {
      type: 'bar',
      data: {
        labels: categoriaData.map(item => item.nome),
        datasets: [{
          label: 'Valor Total (R$)',
          data: categoriaData.map(item => parseFloat(item.valor_total)),
          backgroundColor: 'rgba(54, 162, 235, 0.8)',
          borderColor: 'rgba(54, 162, 235, 1)',
          borderWidth: 1
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
          y: {
            beginAtZero: true,
            title: {
              display: true,
              text: 'Valor (R$)'
            }
          }
        },
        plugins: {
          legend: {
            display: false
          }
        }
      }
    });

    // DataTable para últimas vendas
    $(document).ready(function() {
      $('#vendasTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
          url: 'ajax/vendas_datatable.php',
          type: 'POST',
          data: function(d) {
            d.feira_id = feiraId;
          }
        },
        columns: [
          { data: 'id' },
          { data: 'data_hora' },
          { data: 'forma_pagamento' },
          { 
            data: 'total',
            render: function(data) {
              return 'R$ ' + parseFloat(data).toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, '.');
            }
          },
          { 
            data: 'status',
            render: function(data) {
              let badgeClass = 'badge-success';
              if (data === 'CANCELADA') badgeClass = 'badge-danger';
              if (data === 'ABERTA') badgeClass = 'badge-warning';
              return '<span class="badge ' + badgeClass + '">' + data + '</span>';
            }
          },
          { 
            data: null,
            render: function(data, type, row) {
              return '<button class="btn btn-sm btn-primary" onclick="verDetalhes(' + row.id + ')">Ver</button>';
            }
          }
        ],
        order: [[0, 'desc']],
        language: {
          url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/pt-BR.json'
        },
        pageLength: 25
      });

      // DataTable para produtos
      $('#topProdutosTable').DataTable({
        pageLength: 10,
        language: {
          url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/pt-BR.json'
        }
      });
    });

    function verDetalhes(vendaId) {
      window.location.href = 'vendas/detalhes.php?id=' + vendaId;
    }
  </script>
</body>

</html>