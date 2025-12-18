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
  <title>SIGRelatórios Feira do Produtor — Adicionar Produto</title>

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
    ul .nav-link:hover { color: blue !important; }
    .nav-link { color: black !important; }

    /* Recuar TODOS os submenus para a esquerda (itens dentro do collapse) */
    .sidebar .sub-menu .nav-item .nav-link { margin-left: -35px !important; }
    .sidebar .sub-menu li { list-style: none !important; }

    /* Form */
    .form-control, .custom-select { height: 42px; }
    textarea.form-control { height: auto; }
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

        <ul class="navbar-nav navbar-nav-right"></ul>

        <button class="navbar-toggler navbar-toggler-right d-lg-none align-self-center" type="button" data-toggle="offcanvas">
          <span class="icon-menu"></span>
        </button>
      </div>
    </nav>

    <div class="container-fluid page-body-wrapper">

      <!-- (Painel lateral do template - mantido) -->
      <div id="right-sidebar" class="settings-panel">
        <i class="settings-close ti-close"></i>
        <ul class="nav nav-tabs border-top" id="setting-panel" role="tablist">
          <li class="nav-item">
            <a class="nav-link active" id="todo-tab" data-toggle="tab" href="#todo-section" role="tab" aria-controls="todo-section" aria-expanded="true">
              TO DO LIST
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link" id="chats-tab" data-toggle="tab" href="#chats-section" role="tab" aria-controls="chats-section">
              CHATS
            </a>
          </li>
        </ul>
      </div>

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

          <!-- CADASTROS (ATIVO / PADRÃO OBRIGATÓRIO) -->
          <li class="nav-item active">
            <a class="nav-link open" data-toggle="collapse" href="#feiraCadastros" aria-expanded="false" aria-controls="feiraCadastros">
              <i class="ti-id-badge menu-icon"></i>
              <span class="menu-title">Cadastros</span>
              <i class="menu-arrow"></i>
            </a>

            <div class="collapse" id="feiraCadastros">
              <style>
                .sub-menu .nav-item .nav-link { color: black !important; }
                .sub-menu .nav-item .nav-link:hover { color: blue !important; }
              </style>

              <ul class="nav flex-column sub-menu" style="background: white !important;">
                <li class="nav-item">
                  <a class="nav-link" href="./listaProduto.php">
                    Lista de Produtos
                  </a>
                </li>

                <li class="nav-item active">
                  <a class="nav-link" href="./adicionarProduto.php" style="color:white !important; background: #231475C5 !important;">
                    Adicionar Produto
                  </a>
                </li>

                <li class="nav-item">
                  <a class="nav-link" href="../adm/feira_produtor/categorias/">
                    Categorias
                  </a>
                </li>

                <li class="nav-item">
                  <a class="nav-link" href="../adm/feira_produtor/unidades/">
                    Unidades
                  </a>
                </li>

                <li class="nav-item">
                  <a class="nav-link" href="../adm/feira_produtor/produtores/">
                    Produtores
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
                  <a class="nav-link" href="../adm/feira_produtor/lancamentos/">
                    Lançamentos (Vendas)
                  </a>
                </li>
                <li class="nav-item">
                  <a class="nav-link" href="../adm/feira_produtor/fechamento/">
                    Fechamento do Dia
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
                  <a class="nav-link" href="../adm/feira_produtor/relatorios/financeiro.php">
                    Relatório Financeiro
                  </a>
                </li>
                <li class="nav-item">
                  <a class="nav-link" href="../adm/feira_produtor/relatorios/produtos.php">
                    Produtos Comercializados
                  </a>
                </li>
                <li class="nav-item">
                  <a class="nav-link" href="../adm/feira_produtor/relatorios/mensal.php">
                    Resumo Mensal
                  </a>
                </li>
                <li class="nav-item">
                  <a class="nav-link" href="../adm/feira_produtor/config/relatorio.php">
                    Configurar
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
              <h3 class="font-weight-bold">Adicionar Produto</h3>
              <h6 class="font-weight-normal mb-0">Cadastre um novo produto para a Feira do Produtor.</h6>
            </div>
          </div>

          <div class="row">
            <div class="col-lg-12 grid-margin stretch-card">
              <div class="card">
                <div class="card-body">

                  <div class="d-flex align-items-center justify-content-between flex-wrap">
                    <div>
                      <h4 class="card-title mb-0">Formulário do Produto</h4>
                      <p class="card-description mb-0">Depois a gente liga no banco e validações.</p>
                    </div>

                    <a href="./listaProduto.php" class="btn btn-light btn-sm mt-2 mt-md-0">
                      <i class="ti-arrow-left"></i> Voltar
                    </a>
                  </div>

                  <form action="#" method="post" class="pt-3" enctype="multipart/form-data">
                    <div class="row">

                      <div class="col-md-6">
                        <div class="form-group">
                          <label>Nome do Produto</label>
                          <input type="text" class="form-control" placeholder="Digite o nome do produto">
                        </div>
                      </div>

                      <div class="col-md-3">
                        <div class="form-group">
                          <label>Categoria</label>
                          <select class="form-control">
                            <option value="">Selecione...</option>
                          </select>
                        </div>
                      </div>

                      <div class="col-md-3">
                        <div class="form-group">
                          <label>Unidade</label>
                          <select class="form-control">
                            <option value="">Selecione...</option>
                          </select>
                        </div>
                      </div>

                      <div class="col-md-3">
                        <div class="form-group">
                          <label>Preço (R$)</label>
                          <input type="text" class="form-control" placeholder="0,00">
                        </div>
                      </div>

                      <div class="col-md-3">
                        <div class="form-group">
                          <label>Estoque (opcional)</label>
                          <input type="number" class="form-control" placeholder="0">
                        </div>
                      </div>

                      <div class="col-md-3">
                        <div class="form-group">
                          <label>Status</label>
                          <select class="form-control">
                            <option value="">Selecione...</option>
                            <option value="1">Ativo</option>
                            <option value="0">Inativo</option>
                          </select>
                        </div>
                      </div>

                      <div class="col-md-3">
                        <div class="form-group">
                          <label>Produtor (opcional)</label>
                          <select class="form-control">
                            <option value="">Selecione...</option>
                          </select>
                        </div>
                      </div>

                      <div class="col-md-12">
                        <div class="form-group">
                          <label>Descrição (opcional)</label>
                          <textarea class="form-control" rows="4" placeholder="Descreva o produto..."></textarea>
                        </div>
                      </div>

                      <div class="col-md-6">
                        <div class="form-group">
                          <label>Imagem (opcional)</label>
                          <input type="file" class="form-control">
                          <small class="text-muted">Depois a gente configura upload/validação.</small>
                        </div>
                      </div>

                      <div class="col-md-6">
                        <div class="form-group">
                          <label>Código/SKU (opcional)</label>
                          <input type="text" class="form-control" placeholder="Código interno">
                        </div>
                      </div>

                    </div>

                    <div class="d-flex flex-wrap" style="gap:8px;">
                      <button type="submit" class="btn btn-primary">
                        <i class="ti-save mr-1"></i> Salvar
                      </button>
                      <button type="button" class="btn btn-light">
                        <i class="ti-close mr-1"></i> Cancelar
                      </button>
                    </div>

                  </form>

                </div>
              </div>
            </div>
          </div>

        </div>

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

  <!-- JS EXTERNO (SEM JS INTERNO) -->
  <script src="../../../vendors/js/vendor.bundle.base.js"></script>
  <!-- Plugin js for this page -->
  <script src="../../../vendors/chart.js/Chart.min.js"></script>
  <!-- inject:js -->
  <script src="../../../js/off-canvas.js"></script>
  <script src="../../../js/hoverable-collapse.js"></script>
  <script src="../../../js/template.js"></script>
  <script src="../../../js/settings.js"></script>
  <script src="../../../js/todolist.js"></script>
  <!-- Custom js for this page-->
  <script src="../../../js/dashboard.js"></script>
  <script src="../../../js/Chart.roundedBarCharts.js"></script>
</body>
</html>
