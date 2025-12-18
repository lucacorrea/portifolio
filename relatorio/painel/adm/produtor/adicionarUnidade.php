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
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <title>SIGRelatórios Feira do Produtor — Adicionar Unidade</title>

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

    /* Recuar TODOS os submenus para a esquerda */
    .sidebar .sub-menu .nav-item .nav-link {
      margin-left: -35px !important;
    }

    .sidebar .sub-menu li {
      list-style: none !important;
    }

    .form-control {
      height: 42px;
    }

    .btn {
      height: 42px;
    }

    .helper {
      font-size: 12px;
    }
  </style>
</head>

<body>
  <div class="container-scroller">

    <!-- NAVBAR (padrão) -->
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

        <ul class="navbar-nav navbar-nav-right"></ul>

        <button class="navbar-toggler navbar-toggler-right d-lg-none align-self-center" type="button" data-toggle="offcanvas">
          <span class="icon-menu"></span>
        </button>
      </div>
    </nav>

    <div class="container-fluid page-body-wrapper">

      <!-- settings-panel (mantido) -->
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

      <!-- SIDEBAR (Cadastros ativo + Adicionar Unidade ativo) -->
      <nav class="sidebar sidebar-offcanvas" id="sidebar">
        <ul class="nav">

          <li class="nav-item">
            <a class="nav-link" href="index.php">
              <i class="icon-grid menu-icon"></i>
              <span class="menu-title">Dashboard</span>
            </a>
          </li>

          <!-- CADASTROS (ATIVO) -->
          <li class="nav-item active">
            <a class="nav-link open" data-toggle="collapse" href="#feiraCadastros" aria-expanded="true" aria-controls="feiraCadastros">
              <i class="ti-id-badge menu-icon"></i>
              <span class="menu-title">Cadastros</span>
              <i class="menu-arrow"></i>
            </a>

            <div class="collapse show" id="feiraCadastros">
              <style>
                .sub-menu .nav-item .nav-link {
                  color: black !important;
                }

                .sub-menu .nav-item .nav-link:hover {
                  color: blue !important;
                }
              </style>

              <ul class="nav flex-column sub-menu" style="background: white !important;">

                <li class="nav-item">
                  <a class="nav-link" href="./listaProduto.php">
                    <i class="ti-clipboard mr-2"></i> Lista de Produtos
                  </a>
                </li>

               

                <li class="nav-item">
                  <a class="nav-link" href="./listaCategoria.php">
                    <i class="ti-layers mr-2"></i> Categorias
                  </a>
                </li>

               
                <li class="nav-item">
                  <a class="nav-link" href="./listaUnidade.php">
                    <i class="ti-ruler-pencil mr-2"></i> Unidades
                  </a>
                </li>

                <li class="nav-item active">
                  <a class="nav-link" href="./adicionarUnidade.php" style="color:white !important; background: #231475C5 !important;">
                    <i class="ti-plus mr-2"></i> Adicionar Unidade
                  </a>
                </li>

                <li class="nav-item">
                  <a class="nav-link" href="./listaProdutor.php">
                    <i class="ti-user mr-2"></i> Produtores
                  </a>
                </li>

              </ul>
            </div>
          </li>

          <!-- MOVIMENTO -->
          <li class="nav-item">
            <a class="nav-link" data-toggle="collapse" href="#feiraMovimento" aria-expanded="false" aria-controls="feiraMovimento">
              <i class="ti-exchange-vertical menu-icon"></i>
              <span class="menu-title">Movimento</span>
              <i class="menu-arrow"></i>
            </a>

            <div class="collapse" id="feiraMovimento">
              <ul class="nav flex-column sub-menu" style="background:#fff !important;">
                <li class="nav-item">
                  <a class="nav-link" href="./lancamentos.php">
                    <i class="ti-write mr-2"></i> Lançamentos (Vendas)
                  </a>
                </li>
                <li class="nav-item">
                  <a class="nav-link" href="./fechamentoDia.php">
                    <i class="ti-check-box mr-2"></i> Fechamento do Dia
                  </a>
                </li>
              </ul>
            </div>
          </li>

          <!-- RELATÓRIOS -->
          <li class="nav-item">
            <a class="nav-link" data-toggle="collapse" href="#feiraRelatorios" aria-expanded="false" aria-controls="feiraRelatorios">
              <i class="ti-clipboard menu-icon"></i>
              <span class="menu-title">Relatórios</span>
              <i class="menu-arrow"></i>
            </a>

            <div class="collapse text-black" id="feiraRelatorios">
              <ul class="nav flex-column sub-menu" style="background:#fff !important;">
                <li class="nav-item">
                  <a class="nav-link" href="./relatorioFinanceiro.php">
                    <i class="ti-bar-chart mr-2"></i> Relatório Financeiro
                  </a>
                </li>
                <li class="nav-item">
                  <a class="nav-link" href="./relatorioProdutos.php">
                    <i class="ti-list mr-2"></i> Produtos Comercializados
                  </a>
                </li>
                <li class="nav-item">
                  <a class="nav-link" href="./relatorioMensal.php">
                    <i class="ti-calendar mr-2"></i> Resumo Mensal
                  </a>
                </li>
                <li class="nav-item">
                  <a class="nav-link" href="./configRelatorio.php">
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

      <!-- MAIN -->
      <div class="main-panel">
        <div class="content-wrapper">

          <div class="row">
            <div class="col-12 mb-3">
              <h3 class="font-weight-bold">Adicionar Unidade</h3>
              <h6 class="font-weight-normal mb-0">Cadastre uma unidade de medida para usar nos produtos (ex.: kg, litro, maço, dúzia).</h6>
            </div>
          </div>

          <div class="row">
            <div class="col-lg-12 grid-margin stretch-card">
              <div class="card">
                <div class="card-body">

                  <div class="d-flex align-items-center justify-content-between flex-wrap">
                    <div>
                      <h4 class="card-title mb-0">Formulário</h4>
                      <p class="card-description mb-0">Somente layout por enquanto. Depois conectamos no banco.</p>
                    </div>
                    <a href="./listaUnidade.php" class="btn btn-light btn-sm mt-2 mt-md-0">
                      <i class="ti-arrow-left"></i> Voltar
                    </a>
                  </div>

                  <hr>

                  <form action="#" method="post" class="pt-2">

                    <div class="row">
                      <div class="col-md-6 mb-3">
                        <label class="mb-1">Nome da Unidade</label>
                        <input type="text" class="form-control" placeholder="Ex.: Quilograma, Litro, Maço" required>
                        <small class="text-muted helper">Nome completo, como vai aparecer no cadastro de produtos.</small>
                      </div>

                      <div class="col-md-6 mb-3">
                        <label class="mb-1">Abreviação</label>
                        <input type="text" class="form-control" placeholder="Ex.: kg, L, un, cx, dz" maxlength="10" required>
                        <small class="text-muted helper">Curta e padrão (recomendado: 2 a 5 letras).</small>
                      </div>

                      <div class="col-md-6 mb-3">
                        <label class="mb-1">Status</label>
                        <select class="form-control" required>
                          <option value="" selected disabled>Selecione</option>
                          <option value="1">Ativo</option>
                          <option value="0">Inativo</option>
                        </select>
                        <small class="text-muted helper">Inativo não aparece para selecionar nos produtos.</small>
                      </div>

                      <div class="col-md-6 mb-3">
                        <label class="mb-1">Observação (opcional)</label>
                        <input type="text" class="form-control" placeholder="Ex.: usada para hortaliças e verduras">
                        <small class="text-muted helper">Campo opcional para você se organizar (pode remover depois).</small>
                      </div>
                    </div>

                    <div class="d-flex flex-wrap" style="gap:8px;">
                      <button type="submit" class="btn btn-primary">
                        <i class="ti-save mr-1"></i> Salvar
                      </button>
                      <button type="reset" class="btn btn-light">
                        <i class="ti-close mr-1"></i> Limpar
                      </button>
                    </div>

                  </form>

                </div>
              </div>
            </div>
          </div>

        </div>

        <!-- FOOTER -->
        <footer class="footer">
          <div class="d-flex flex-column flex-sm-row justify-content-between align-items-center">
            <span class="text-muted text-center text-sm-left d-block mb-2 mb-sm-0">
              © <?= date('Y') ?> SIGRelatórios —
              <a href="https://www.lucascorrea.pro/" target="_blank" rel="noopener">lucascorrea.pro</a>.
              Todos os direitos reservados.
            </span>
          </div>
        </footer>

      </div>
      <!-- main-panel ends -->

    </div>
    <!-- page-body-wrapper ends -->
  </div>
  <!-- container-scroller -->

  <!-- JS EXTERNO (sem JS interno) -->
  <script src="../../../vendors/js/vendor.bundle.base.js"></script>
  <script src="../../../vendors/chart.js/Chart.min.js"></script>

  <script src="../../../js/off-canvas.js"></script>
  <script src="../../../js/hoverable-collapse.js"></script>
  <script src="../../../js/template.js"></script>
  <script src="../../../js/settings.js"></script>
  <script src="../../../js/todolist.js"></script>

  <script src="../../../js/dashboard.js"></script>
  <script src="../../../js/Chart.roundedBarCharts.js"></script>
</body>

</html>