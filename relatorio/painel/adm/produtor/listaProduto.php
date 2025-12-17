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
  <!-- Required meta tags -->
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
  </style>
</head>

<body>
  <div class="container-scroller">

    <!-- NAVBAR -->
    <nav class="navbar col-lg-12 col-12 p-0 fixed-top d-flex flex-row">
      <div class="text-center navbar-brand-wrapper d-flex align-items-center justify-content-center">
        <a class="navbar-brand brand-logo mr-5" href="index.php">SIGRelatórios</a>
        <a class="navbar-brand brand-logo-mini" href="index.php">
          <img src="../../../images/3.png" alt="logo" />
        </a>
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

      <!-- SIDEBAR -->
      <nav class="sidebar sidebar-offcanvas" id="sidebar">
        <ul class="nav">

          <!-- DASHBOARD -->
          <li class="nav-item">
            <a class="nav-link" href="index.php">
              <i class="icon-grid menu-icon"></i>
              <span class="menu-title">Dashboard</span>
            </a>
          </li>

          <!-- FEIRA DO PRODUTOR -->
          <li class="nav-item active">
            <a class="nav-link" data-toggle="collapse" href="#feiraCadastros" aria-expanded="true" aria-controls="feiraCadastros">
              <i class="ti-id-badge menu-icon"></i>
              <span class="menu-title">Cadastros</span>
              <i class="menu-arrow"></i>
            </a>
            <div class="collapse show" id="feiraCadastros">
              <ul class="nav flex-column sub-menu" style="background:#fff !important;">
                <li class="nav-item">
                  <a class="nav-link active" href="./listaProduto.php">
                    <i class="ti-clipboard mr-2"></i> Lista de Produtos
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

      <!-- MAIN -->
      <div class="main-panel">
        <div class="content-wrapper">

          <!-- TÍTULO -->
          <div class="row">
            <div class="col-md-12 grid-margin">
              <div class="d-flex align-items-center justify-content-between flex-wrap">
                <div>
                  <h3 class="font-weight-bold mb-0">Lista de Produtos</h3>
                  <p class="text-muted mb-0">Gerencie seus produtos cadastrados</p>
                </div>
                <div class="mt-2 mt-sm-0">
                  <a href="./adicionarProduto.php" class="btn btn-primary btn-sm">
                    <i class="ti-plus mr-1"></i> Novo Produto
                  </a>
                </div>
              </div>
            </div>
          </div>

          <!-- FILTROS -->
          <div class="row">
            <div class="col-md-12 grid-margin stretch-card">
              <div class="card">
                <div class="card-body">
                  <h4 class="card-title mb-3">Filtros</h4>

                  <form>
                    <div class="form-row">
                      <div class="form-group col-md-4">
                        <label>Buscar</label>
                        <input type="text" class="form-control" placeholder="Nome, código, categoria...">
                      </div>

                      <div class="form-group col-md-3">
                        <label>Categoria</label>
                        <select class="form-control">
                          <option value="">Todas</option>
                        </select>
                      </div>

                      <div class="form-group col-md-3">
                        <label>Status</label>
                        <select class="form-control">
                          <option value="">Todos</option>
                          <option value="ATIVO">Ativo</option>
                          <option value="INATIVO">Inativo</option>
                        </select>
                      </div>

                      <div class="form-group col-md-2 d-flex align-items-end">
                        <button type="button" class="btn btn-outline-primary btn-block">
                          <i class="ti-search mr-1"></i> Filtrar
                        </button>
                      </div>
                    </div>

                    <div class="form-row">
                      <div class="form-group col-md-3">
                        <label>Ordenar por</label>
                        <select class="form-control">
                          <option>Mais recentes</option>
                          <option>Nome (A-Z)</option>
                          <option>Nome (Z-A)</option>
                          <option>Menor estoque</option>
                          <option>Maior preço</option>
                        </select>
                      </div>

                      <div class="form-group col-md-3">
                        <label>Itens por página</label>
                        <select class="form-control">
                          <option>10</option>
                          <option>20</option>
                          <option>50</option>
                        </select>
                      </div>

                      <div class="form-group col-md-6 d-flex align-items-end justify-content-end">
                        <button type="button" class="btn btn-light mr-2">
                          <i class="ti-reload mr-1"></i> Limpar
                        </button>
                        <button type="button" class="btn btn-outline-success">
                          <i class="ti-export mr-1"></i> Exportar
                        </button>
                      </div>
                    </div>
                  </form>

                </div>
              </div>
            </div>
          </div>

          <!-- TABELA -->
          <div class="row">
            <div class="col-md-12 grid-margin stretch-card">
              <div class="card">
                <div class="card-body">
                  <div class="d-flex align-items-center justify-content-between flex-wrap mb-3">
                    <h4 class="card-title mb-0">Produtos</h4>
                    <span class="text-muted">Total: <b>0</b></span>
                  </div>

                  <div class="table-responsive">
                    <table class="table table-striped table-borderless">
                      <thead>
                        <tr>
                          <th>#</th>
                          <th>Produto</th>
                          <th>Categoria</th>
                          <th>Unidade</th>
                          <th>Estoque</th>
                          <th>Status</th>
                          <th>Atualizado</th>
                          <th class="text-right">Ações</th>
                        </tr>
                      </thead>
                      <tbody>
                        <tr>
                          <td colspan="8" class="text-center text-muted py-4">
                            Nenhum produto para mostrar (conectar no banco depois).
                          </td>
                        </tr>
                      </tbody>
                    </table>
                  </div>

                  <!-- PAGINAÇÃO (placeholder) -->
                  <div class="d-flex justify-content-between align-items-center mt-3 flex-wrap">
                    <small class="text-muted">Mostrando 0 de 0</small>
                    <nav>
                      <ul class="pagination pagination-sm mb-0">
                        <li class="page-item disabled"><a class="page-link" href="#">Anterior</a></li>
                        <li class="page-item active"><a class="page-link" href="#">1</a></li>
                        <li class="page-item disabled"><a class="page-link" href="#">Próxima</a></li>
                      </ul>
                    </nav>
                  </div>

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
              <a href="https://www.lucascorrea.pro/" target="_blank" rel="noopener">lucascorrea.pro</a>
              . Todos os direitos reservados.
            </span>
          </div>
        </footer>

      </div>
    </div>
  </div>

  <!-- plugins:js -->
  <script src="../../../vendors/js/vendor.bundle.base.js"></script>
  <!-- endinject -->
  <!-- Plugin js for this page -->
  <script src="../../../vendors/chart.js/Chart.min.js"></script>

  <!-- End plugin js for this page -->
  <!-- inject:js -->
  <script src="../../../js/off-canvas.js"></script>
  <script src="../../../js/hoverable-collapse.js"></script>
  <script src="../../../js/template.js"></script>
  <script src="../../../js/settings.js"></script>
  <script src="../../../js/todolist.js"></script>
  <!-- endinject -->
  <!-- Custom js for this page-->
  <script src="../../../js/dashboard.js"></script>
  <script src="../../../js/Chart.roundedBarCharts.js"></script>

</body>
</html>
