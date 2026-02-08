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

// Conexão com banco
require_once '../../assets/php/conexao.php';

/**
 * Segurança: garantir que o PDO joga exceções (evita "500 silencioso")
 * (Se seu conexao.php já faz isso, não tem problema repetir.)
 */
try {
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (Throwable $e) {
  // Se aqui falhar, a conexão nem está ok
}

/**
 * Debug (DEV): habilite temporariamente se quiser ver erro na tela
 * Em produção, deixe false.
 */
$DEBUG = false;
if ($DEBUG) {
  ini_set('display_errors', '1');
  ini_set('display_startup_errors', '1');
  error_reporting(E_ALL);
}

/** Filtro de feira selecionada */
$feira_selecionada = isset($_GET['feira_id']) ? (int)$_GET['feira_id'] : 0;

/** Helper HTML */
function h($s): string
{
  return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

/**
 * Verifica se uma coluna existe numa tabela (MySQL/MariaDB).
 * Isso impede estourar 500 por "Unknown column feira_id".
 */
function columnExists(PDO $pdo, string $table, string $column): bool
{
  $sql = "
    SELECT COUNT(*) AS c
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = :t
      AND COLUMN_NAME = :c
  ";
  $st = $pdo->prepare($sql);
  $st->execute([':t' => $table, ':c' => $column]);
  return (int)$st->fetchColumn() > 0;
}

/**
 * Executa query com bind condicional de feira_id
 */
function execWithOptionalFeira(PDO $pdo, string $sql, int $feira_id): PDOStatement
{
  $st = $pdo->prepare($sql);
  if ($feira_id > 0 && strpos($sql, ':feira_id') !== false) {
    $st->bindValue(':feira_id', $feira_id, PDO::PARAM_INT);
  }
  $st->execute();
  return $st;
}

/** Defaults (pra nunca quebrar layout) */
$feiras = [];
$vendas_hoje = ['total' => 0, 'valor_total' => 0];
$vendas_mes  = ['total' => 0, 'valor_total' => 0];
$total_produtores = 0;
$total_produtos = 0;
$vendas_forma_pagamento = [];
$vendas_categoria = [];
$top_produtos = [];
$vendas_semana = [];

try {
  /** 1) Listar feiras */
  $sql_feiras = "SELECT id, codigo, nome FROM feiras WHERE ativo = 1 ORDER BY nome";
  $feiras = $pdo->query($sql_feiras)->fetchAll();

  /**
   * 2) Montar filtro de feira para VENDAS
   *    (só aplica se a coluna existir)
   */
  $vendasTemFeiraId = columnExists($pdo, 'vendas', 'feira_id');

  $where_vendas_and = '';
  if ($feira_selecionada > 0 && $vendasTemFeiraId) {
    $where_vendas_and = " AND feira_id = :feira_id";
  }

  /** 3) Total de vendas do dia */
  $sql_vendas_hoje = "
    SELECT COUNT(*) AS total, COALESCE(SUM(total), 0) AS valor_total
    FROM vendas
    WHERE DATE(data_hora) = CURDATE()
    $where_vendas_and
  ";
  $vendas_hoje = execWithOptionalFeira($pdo, $sql_vendas_hoje, $feira_selecionada)->fetch() ?: $vendas_hoje;

  /** 4) Total de vendas do mês */
  $sql_vendas_mes = "
    SELECT COUNT(*) AS total, COALESCE(SUM(total), 0) AS valor_total
    FROM vendas
    WHERE MONTH(data_hora) = MONTH(CURDATE())
      AND YEAR(data_hora)  = YEAR(CURDATE())
    $where_vendas_and
  ";
  $vendas_mes = execWithOptionalFeira($pdo, $sql_vendas_mes, $feira_selecionada)->fetch() ?: $vendas_mes;

  /**
   * 5) Total de produtores
   *    - Se produtores tiver feira_id -> filtra direto
   *    - Senão: NÃO filtra (evita erro) e retorna total geral
   *    (Se você tiver tabela pivô produtor_feira, eu adapto pra filtrar certinho.)
   */
  $produtoresTemFeiraId = columnExists($pdo, 'produtores', 'feira_id');

  if ($feira_selecionada > 0 && $produtoresTemFeiraId) {
    $sql_produtores = "SELECT COUNT(*) AS total FROM produtores WHERE ativo = 1 AND feira_id = :feira_id";
    $total_produtores = (int) execWithOptionalFeira($pdo, $sql_produtores, $feira_selecionada)->fetchColumn();
  } else {
    $sql_produtores = "SELECT COUNT(*) AS total FROM produtores WHERE ativo = 1";
    $total_produtores = (int) $pdo->query($sql_produtores)->fetchColumn();
  }

  /**
   * 6) Total de produtos
   *    - Se produtos tiver feira_id -> filtra direto
   *    - Senão: NÃO filtra (evita erro)
   */
  $produtosTemFeiraId = columnExists($pdo, 'produtos', 'feira_id');

  if ($feira_selecionada > 0 && $produtosTemFeiraId) {
    $sql_produtos = "SELECT COUNT(*) AS total FROM produtos WHERE ativo = 1 AND feira_id = :feira_id";
    $total_produtos = (int) execWithOptionalFeira($pdo, $sql_produtos, $feira_selecionada)->fetchColumn();
  } else {
    $sql_produtos = "SELECT COUNT(*) AS total FROM produtos WHERE ativo = 1";
    $total_produtos = (int) $pdo->query($sql_produtos)->fetchColumn();
  }

  /** 7) Vendas por forma de pagamento (mês) */
  $sql_forma_pagamento = "
    SELECT forma_pagamento, COALESCE(SUM(total), 0) AS valor_total
    FROM vendas
    WHERE MONTH(data_hora) = MONTH(CURDATE())
      AND YEAR(data_hora)  = YEAR(CURDATE())
    $where_vendas_and
    GROUP BY forma_pagamento
  ";
  $vendas_forma_pagamento = execWithOptionalFeira($pdo, $sql_forma_pagamento, $feira_selecionada)->fetchAll();

  /**
   * 8) Vendas por categoria (mês)
   *    Aqui é o ponto que mais dá 500: venda_itens normalmente NÃO tem feira_id.
   *    Então filtramos por feira em vendas v (se existir v.feira_id).
   */
  $vendaItensTemVendaId = columnExists($pdo, 'venda_itens', 'venda_id');
  $where_feira_vi = '';
  $join_vendas_vi = '';

  if ($vendaItensTemVendaId) {
    $join_vendas_vi = "INNER JOIN vendas v ON v.id = vi.venda_id";
    if ($feira_selecionada > 0 && $vendasTemFeiraId) {
      $where_feira_vi = " AND v.feira_id = :feira_id";
    }
  } else {
    // Se sua tabela venda_itens não tem venda_id, precisa ajustar schema/joins.
    // Mantemos sem join pra não quebrar.
    $join_vendas_vi = "";
    $where_feira_vi = "";
  }

  $sql_vendas_categoria = "
    SELECT c.nome,
           COUNT(vi.id) AS qtd_vendas,
           COALESCE(SUM(vi.subtotal), 0) AS valor_total
    FROM venda_itens vi
    $join_vendas_vi
    INNER JOIN produtos p   ON vi.produto_id = p.id
    INNER JOIN categorias c ON p.categoria_id = c.id
    WHERE MONTH(vi.criado_em) = MONTH(CURDATE())
      AND YEAR(vi.criado_em)  = YEAR(CURDATE())
      $where_feira_vi
    GROUP BY c.id, c.nome
    ORDER BY valor_total DESC
    LIMIT 10
  ";
  $vendas_categoria = execWithOptionalFeira($pdo, $sql_vendas_categoria, $feira_selecionada)->fetchAll();

  /** 9) Top 10 produtos mais vendidos (mês) */
  $sql_top_produtos = "
    SELECT p.nome,
           COUNT(vi.id) AS qtd_vendas,
           COALESCE(SUM(vi.subtotal), 0) AS valor_total
    FROM venda_itens vi
    $join_vendas_vi
    INNER JOIN produtos p ON vi.produto_id = p.id
    WHERE MONTH(vi.criado_em) = MONTH(CURDATE())
      AND YEAR(vi.criado_em)  = YEAR(CURDATE())
      $where_feira_vi
    GROUP BY p.id, p.nome
    ORDER BY qtd_vendas DESC
    LIMIT 10
  ";
  $top_produtos = execWithOptionalFeira($pdo, $sql_top_produtos, $feira_selecionada)->fetchAll();

  /** 10) Vendas últimos 7 dias */
  $sql_vendas_semana = "
    SELECT DATE(data_hora) AS data, COUNT(*) AS qtd, COALESCE(SUM(total), 0) AS valor
    FROM vendas
    WHERE data_hora >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    $where_vendas_and
    GROUP BY DATE(data_hora)
    ORDER BY data
  ";
  $vendas_semana = execWithOptionalFeira($pdo, $sql_vendas_semana, $feira_selecionada)->fetchAll();
} catch (Throwable $e) {
  error_log("Erro no dashboard: " . $e->getMessage());

  if ($DEBUG) {
    echo "<div style='padding:12px;border:1px solid #f00;color:#900;background:#fee'>";
    echo "<strong>Erro ao carregar dados:</strong> " . h($e->getMessage());
    echo "</div>";
  }

  // As variáveis já estão com default seguro acima
}
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
      box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
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
      <?php
      // GARANTIR helper antes do HTML (evita 500)
      if (!function_exists('h')) {
        function h($s): string
        {
          return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
        }
      }

      // Data em PT-BR sem locale
      $meses = [
        1 => 'Jan',
        2 => 'Fev',
        3 => 'Mar',
        4 => 'Abr',
        5 => 'Mai',
        6 => 'Jun',
        7 => 'Jul',
        8 => 'Ago',
        9 => 'Set',
        10 => 'Out',
        11 => 'Nov',
        12 => 'Dez'
      ];
      $hojeLabel = date('d') . ' ' . ($meses[(int)date('n')] ?? date('M')) . ' ' . date('Y');

      // Normalizar feira selecionada (evita comparação bugada)
      $feira_selecionada = isset($feira_selecionada) ? (int)$feira_selecionada : 0;
      ?>

      <div class="main-panel">
        <div class="content-wrapper">
          <div class="row">
            <div class="col-md-12 grid-margin">
              <div class="row">
                <div class="col-12 col-xl-8 mb-4 mb-xl-0">
                  <h3 class="font-weight-bold">
                    Bem-vindo(a) <?= h($_SESSION['usuario_nome'] ?? 'Usuário') ?>
                  </h3>
                  <h6 class="font-weight-normal mb-0">
                    Todos os sistemas estão funcionando normalmente!
                  </h6>
                </div>

                <div class="col-12 col-xl-4">
                  <div class="justify-content-end d-flex">
                    <div class="dropdown flex-md-grow-1 flex-xl-grow-0">
                      <button
                        class="btn btn-sm btn-light bg-white dropdown-toggle"
                        type="button"
                        id="dropdownMenuDate2"
                        data-toggle="dropdown"
                        aria-haspopup="true"
                        aria-expanded="false">
                        <i class="mdi mdi-calendar"></i> <?= h($hojeLabel) ?>
                      </button>

                      <!-- Se quiser menu de datas no futuro, coloque aqui.
                <div class="dropdown-menu dropdown-menu-right" aria-labelledby="dropdownMenuDate2">
                  <a class="dropdown-item" href="#">Hoje</a>
                </div>
                -->
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
                    <option value="0" <?= $feira_selecionada === 0 ? 'selected' : '' ?>>Todas as Feiras</option>

                    <?php if (!empty($feiras)): ?>
                      <?php foreach ($feiras as $feira): ?>
                        <?php
                        $fid = (int)($feira['id'] ?? 0);
                        $selected = ($feira_selecionada === $fid) ? 'selected' : '';
                        ?>
                        <option value="<?= $fid ?>" <?= $selected ?>>
                          <?= h($feira['nome'] ?? '') ?>
                        </option>
                      <?php endforeach; ?>
                    <?php endif; ?>
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
                  <p class="fs-30 mb-2"><?= (int)($vendas_hoje['total'] ?? 0) ?></p>
                  <p>R$ <?= number_format((float)($vendas_hoje['valor_total'] ?? 0), 2, ',', '.') ?></p>
                </div>
              </div>
            </div>

            <div class="col-md-3 mb-4 stretch-card transparent">
              <div class="card card-dark-blue card-stats">
                <div class="card-body">
                  <p class="mb-4">Vendas do Mês</p>
                  <p class="fs-30 mb-2"><?= (int)($vendas_mes['total'] ?? 0) ?></p>
                  <p>R$ <?= number_format((float)($vendas_mes['valor_total'] ?? 0), 2, ',', '.') ?></p>
                </div>
              </div>
            </div>

            <div class="col-md-3 mb-4 stretch-card transparent">
              <div class="card card-light-blue card-stats">
                <div class="card-body">
                  <p class="mb-4">Total de Produtores</p>
                  <p class="fs-30 mb-2"><?= (int)($total_produtores ?? 0) ?></p>
                  <p>Ativos no sistema</p>
                </div>
              </div>
            </div>

            <div class="col-md-3 mb-4 stretch-card transparent">
              <div class="card card-light-danger card-stats">
                <div class="card-body">
                  <p class="mb-4">Total de Produtos</p>
                  <p class="fs-30 mb-2"><?= (int)($total_produtos ?? 0) ?></p>
                  <p>Cadastrados</p>
                </div>
              </div>
            </div>
          </div>

          <!-- (restante do seu HTML pode continuar igual daqui pra baixo) -->

        </div>

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

    // Verificar se há dados antes de criar gráficos
    if (formaPagamentoData && formaPagamentoData.length > 0) {
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
    }

    if (vendasSemanaData && vendasSemanaData.length > 0) {
      // Gráfico de Vendas da Semana
      const ctxVendasSemana = document.getElementById('vendasSemanaChart').getContext('2d');
      new Chart(ctxVendasSemana, {
        type: 'line',
        data: {
          labels: vendasSemanaData.map(item => {
            const date = new Date(item.data);
            return date.toLocaleDateString('pt-BR', {
              day: '2-digit',
              month: '2-digit'
            });
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
    }

    if (categoriaData && categoriaData.length > 0) {
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
    }

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
          },
          error: function(xhr, error, code) {
            console.log('Erro ao carregar dados:', error);
          }
        },
        columns: [{
            data: 'id'
          },
          {
            data: 'data_hora'
          },
          {
            data: 'forma_pagamento'
          },
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
        order: [
          [0, 'desc']
        ],
        language: {
          url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/pt-BR.json'
        },
        pageLength: 25
      });

      // DataTable para produtos (se houver dados)
      <?php if (!empty($top_produtos)): ?>
        $('#topProdutosTable').DataTable({
          pageLength: 10,
          language: {
            url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/pt-BR.json'
          }
        });
      <?php endif; ?>
    });

    function verDetalhes(vendaId) {
      // Ajuste o caminho conforme sua estrutura
      window.location.href = 'vendas/detalhes.php?id=' + vendaId;
    }
  </script>
</body>

</html>