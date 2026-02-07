<?php

declare(strict_types=1);
session_start();

/* ======================
   SEGURANÇA
====================== */
if (empty($_SESSION['usuario_logado'])) {
  header('Location: ../../../index.php');
  exit;
}

$perfis = $_SESSION['perfis'] ?? [];
if (!is_array($perfis)) $perfis = [$perfis];
if (!in_array('ADMIN', $perfis, true)) {
  header('Location: ../../operador/index.php');
  exit;
}

function h($s): string {
  return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

/* ======================
   FLASH
====================== */
$msg = (string)($_SESSION['flash_ok'] ?? '');
$err = (string)($_SESSION['flash_err'] ?? '');
unset($_SESSION['flash_ok'], $_SESSION['flash_err']);

/* ======================
   CONEXÃO
====================== */
require '../../../assets/php/conexao.php';
$pdo = db();

/* ======================
   HELPERS (SCHEMA)
====================== */
function hasTable(PDO $pdo, string $table): bool {
  $st = $pdo->prepare("
    SELECT COUNT(*)
    FROM information_schema.tables
    WHERE table_schema = DATABASE()
      AND table_name = :t
  ");
  $st->execute([':t' => $table]);
  return (int)$st->fetchColumn() > 0;
}

function hasColumn(PDO $pdo, string $table, string $column): bool {
  $st = $pdo->prepare("
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = :t
      AND column_name = :c
  ");
  $st->execute([':t' => $table, ':c' => $column]);
  return (int)$st->fetchColumn() > 0;
}

/* ======================
   FEIRA FIXA
====================== */
$feiraId = 1;

/* ======================
   TABELAS
====================== */
if (
  !hasTable($pdo, 'vendas') ||
  !hasTable($pdo, 'venda_itens') ||
  !hasTable($pdo, 'produtos') ||
  !hasTable($pdo, 'produtores')
) {
  $err = 'Tabelas obrigatórias não encontradas.';
}

/* ======================
   CAMPOS OPCIONAIS
====================== */
$colDataVenda = hasColumn($pdo, 'vendas', 'data_venda');
$colDataHora  = hasColumn($pdo, 'vendas', 'data_hora');
$colFormaPgto = hasColumn($pdo, 'vendas', 'forma_pagamento');
$colStatus    = hasColumn($pdo, 'vendas', 'status');

/* Data base */
if ($colDataVenda) {
  $dateExpr = "v.data_venda";
} elseif ($colDataHora) {
  $dateExpr = "DATE(v.data_hora)";
} else {
  $dateExpr = "DATE(v.criado_em)";
}

/* ======================
   FILTRO
====================== */
$tipoFiltro = $_GET['tipo'] ?? 'mes';
$dataSel = $_GET['data'] ?? date('Y-m-d');

if (!preg_match('/^\d{4}-\d{2}(-\d{2})?$/', $dataSel)) {
  $dataSel = date('Y-m-d');
}

if ($tipoFiltro === 'dia') {
  $periodStart = $dataSel;
  $periodEnd   = $dataSel;
  $labelPeriodo = date('d/m/Y', strtotime($dataSel));
} else {
  $mesSel = substr($dataSel, 0, 7);
  $periodStart = $mesSel . '-01';
  $periodEnd   = date('Y-m-t', strtotime($periodStart));
  $labelPeriodo = date('m/Y', strtotime($periodStart));
}

/* ======================
   PAGINAÇÃO
====================== */
$PER_PAGE = 10;

$pageProdutor = max(1, (int)($_GET['page_produtor'] ?? 1));
$pageDia      = max(1, (int)($_GET['page_dia'] ?? 1));
$pageVendas   = max(1, (int)($_GET['page_vendas'] ?? 1));

$offsetProdutor = ($pageProdutor - 1) * $PER_PAGE;
$offsetDia      = ($pageDia - 1) * $PER_PAGE;
$offsetVendas   = ($pageVendas - 1) * $PER_PAGE;

/* ======================
   DADOS
====================== */
$resumo       = ['vendas_qtd' => 0, 'total' => 0, 'ticket' => 0];
$porPagamento = [];
$porProdutor  = [];
$porDia       = [];
$vendasRows   = [];

try {
  if (!$err) {

    $params = [
      ':ini' => $periodStart,
      ':f'   => $feiraId,
    ];

    if ($tipoFiltro === 'dia') {
      $where = "WHERE {$dateExpr} = :ini AND v.feira_id = :f";
    } else {
      $where = "WHERE {$dateExpr} BETWEEN :ini AND :fim AND v.feira_id = :f";
      $params[':fim'] = $periodEnd;
    }

    /* ======================
       RESUMO GERAL
    ====================== */
    $st = $pdo->prepare("
      SELECT COUNT(*) qtd, COALESCE(SUM(v.total),0) total
      FROM vendas v {$where}
    ");
    $st->execute($params);
    $r = $st->fetch();

    if ($r) {
      $resumo['vendas_qtd'] = (int)$r['qtd'];
      $resumo['total'] = (float)$r['total'];
      $resumo['ticket'] = $resumo['vendas_qtd'] > 0
        ? $resumo['total'] / $resumo['vendas_qtd']
        : 0;
    }

    /* ======================
       POR PAGAMENTO
    ====================== */
    if ($colFormaPgto) {
      $st = $pdo->prepare("
        SELECT UPPER(COALESCE(v.forma_pagamento,'N/I')) pagamento,
               COUNT(*) qtd,
               SUM(v.total) total
        FROM vendas v {$where}
        GROUP BY pagamento
        ORDER BY total DESC
      ");
      $st->execute($params);
      $porPagamento = $st->fetchAll();
    }

    /* ======================
       POR FEIRANTE (PAGINADO)
    ====================== */
    $st = $pdo->prepare("
      SELECT SQL_CALC_FOUND_ROWS
        p.nome,
        COUNT(DISTINCT v.id) vendas_qtd,
        SUM(vi.subtotal) total
      FROM vendas v
      JOIN venda_itens vi ON vi.venda_id = v.id
      JOIN produtos pr ON pr.id = vi.produto_id
      JOIN produtores p ON p.id = pr.produtor_id
      {$where}
      GROUP BY p.id
      ORDER BY total DESC
      LIMIT {$PER_PAGE} OFFSET {$offsetProdutor}
    ");
    $st->execute($params);
    $porProdutor = $st->fetchAll();

    $totalProdutor = (int)$pdo->query("SELECT FOUND_ROWS()")->fetchColumn();
    $totalPagesProdutor = (int)ceil($totalProdutor / $PER_PAGE);

    /* ======================
       POR DIA (PAGINADO)
    ====================== */
    if ($tipoFiltro === 'mes') {
      $st = $pdo->prepare("
        SELECT SQL_CALC_FOUND_ROWS
          {$dateExpr} dia,
          COUNT(*) vendas_qtd,
          SUM(v.total) total
        FROM vendas v {$where}
        GROUP BY dia
        ORDER BY dia DESC
        LIMIT {$PER_PAGE} OFFSET {$offsetDia}
      ");
      $st->execute($params);
      $porDia = $st->fetchAll();

      $totalDia = (int)$pdo->query("SELECT FOUND_ROWS()")->fetchColumn();
      $totalPagesDia = (int)ceil($totalDia / $PER_PAGE);
    }

    /* ======================
       VENDAS (PAGINADO)
    ====================== */
    $selectPagamento = $colFormaPgto ? "v.forma_pagamento" : "NULL AS forma_pagamento";
    $selectStatus    = $colStatus    ? "v.status" : "'N/I' AS status";

    $st = $pdo->prepare("
      SELECT SQL_CALC_FOUND_ROWS
        v.id,
        {$dateExpr} data_ref,
        {$selectPagamento},
        {$selectStatus},
        v.total
      FROM vendas v {$where}
      ORDER BY v.id DESC
      LIMIT {$PER_PAGE} OFFSET {$offsetVendas}
    ");
    $st->execute($params);
    $vendasRows = $st->fetchAll();

    $totalVendas = (int)$pdo->query("SELECT FOUND_ROWS()")->fetchColumn();
    $totalPagesVendas = (int)ceil($totalVendas / $PER_PAGE);
  }
} catch (Throwable $e) {
  $err = 'Erro ao carregar relatório: ' . $e->getMessage();
}

/* ======================
   FINAL
====================== */
$nomeUsuario = $_SESSION['usuario_nome'] ?? 'Usuário';


?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <title>SIGRelatórios — Relatório Financeiro</title>

  <link rel="stylesheet" href="../../../vendors/feather/feather.css">
  <link rel="stylesheet" href="../../../vendors/ti-icons/css/themify-icons.css">
  <link rel="stylesheet" href="../../../vendors/css/vendor.bundle.base.css">
  <link rel="stylesheet" href="../../../vendors/datatables.net-bs4/dataTables.bootstrap4.css">
  <link rel="stylesheet" type="text/css" href="../../../js/select.dataTables.min.css">
  <link rel="stylesheet" href="../../../css/vertical-layout-light/style.css">
  <link rel="shortcut icon" href="../../../images/3.png" />

  <style>
    ul .nav-link:hover {
      color: blue !important;
    }

    .nav-link {
      color: black !important;
    }

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

    .mini-kpi {
      font-size: 12px;
      color: #6c757d;
    }

    .kpi-card {
      border: 1px solid rgba(0, 0, 0, .08);
      border-radius: 14px;
      padding: 14px;
      background: #fff;
    }

    .kpi-label {
      font-size: 12px;
      color: #6c757d;
      margin: 0;
    }

    .kpi-value {
      font-size: 22px;
      font-weight: 800;
      margin: 0;
    }

    .table td,
    .table th {
      vertical-align: middle !important;
    }

    .badge-soft {
      background: rgba(0, 0, 0, 0.05);
      border: 1px solid rgba(0, 0, 0, 0.07);
      font-weight: 600;
    }

    /* Flash top-right */
    .sig-flash-wrap {
      position: fixed;
      top: 78px;
      right: 18px;
      width: min(420px, calc(100vw - 36px));
      z-index: 9999;
      pointer-events: none;
    }

    .sig-toast.alert {
      pointer-events: auto;
      border: 0 !important;
      border-left: 6px solid !important;
      border-radius: 14px !important;
      padding: 10px 12px !important;
      box-shadow: 0 10px 28px rgba(0, 0, 0, .10) !important;
      font-size: 13px !important;
      margin-bottom: 10px !important;
      opacity: 0;
      transform: translateX(10px);
      animation: sigToastIn .22s ease-out forwards, sigToastOut .25s ease-in forwards 5.75s;
    }

    .sig-toast--success {
      background: #f1fff6 !important;
      border-left-color: #22c55e !important;
    }

    .sig-toast--danger {
      background: #fff1f2 !important;
      border-left-color: #ef4444 !important;
    }

    .sig-toast__row {
      display: flex;
      align-items: flex-start;
      gap: 10px;
    }

    .sig-toast__icon i {
      font-size: 16px;
      margin-top: 2px;
    }

    .sig-toast__title {
      font-weight: 800;
      margin-bottom: 1px;
      line-height: 1.1;
    }

    .sig-toast__text {
      margin: 0;
      line-height: 1.25;
    }

    .sig-toast .close {
      opacity: .55;
      font-size: 18px;
      line-height: 1;
      padding: 0 6px;
    }

    .sig-toast .close:hover {
      opacity: 1;
    }

    @keyframes sigToastIn {
      to {
        opacity: 1;
        transform: translateX(0);
      }
    }

    @keyframes sigToastOut {
      to {
        opacity: 0;
        transform: translateX(12px);
        visibility: hidden;
      }
    }

    .feirantes-cell {
      white-space: pre-line;
    }

    .btn-group-toggle .btn {
      border-radius: 8px;
      margin-right: 8px;
    }

    .btn-group-toggle .btn.active {
      background: #231475 !important;
      color: white !important;
      border-color: #231475 !important;
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

    <?php if ($msg || $err): ?>
      <div class="sig-flash-wrap">
        <?php if ($msg): ?>
          <div class="alert sig-toast sig-toast--success alert-dismissible" role="alert">
            <div class="sig-toast__row">
              <div class="sig-toast__icon"><i class="ti-check"></i></div>
              <div>
                <div class="sig-toast__title">Tudo certo!</div>
                <p class="sig-toast__text"><?= h($msg) ?></p>
              </div>
            </div>
            <button type="button" class="close" data-dismiss="alert" aria-label="Fechar"><span aria-hidden="true">&times;</span></button>
          </div>
        <?php endif; ?>

        <?php if ($err): ?>
          <div class="alert sig-toast sig-toast--danger alert-dismissible" role="alert">
            <div class="sig-toast__row">
              <div class="sig-toast__icon"><i class="ti-alert"></i></div>
              <div>
                <div class="sig-toast__title">Atenção!</div>
                <p class="sig-toast__text"><?= h($err) ?></p>
              </div>
            </div>
            <button type="button" class="close" data-dismiss="alert" aria-label="Fechar"><span aria-hidden="true">&times;</span></button>
          </div>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <div class="container-fluid page-body-wrapper">

      <!-- SIDEBAR (NOVO MENU QUE VOCÊ MANDOU) -->
      <nav class="sidebar sidebar-offcanvas" id="sidebar">
        <ul class="nav">

          <li class="nav-item">
            <a class="nav-link" href="index.php">
              <i class="icon-grid menu-icon"></i>
              <span class="menu-title">Dashboard</span>
            </a>
          </li>

          <!-- CADASTROS (ATIVO) -->
          <li class="nav-item ">
            <a class="nav-link " data-toggle="collapse" href="#feiraCadastros">
              <i class="ti-id-badge menu-icon"></i>
              <span class="menu-title">Cadastros</span>
              <i class="menu-arrow"></i>
            </a>

            <div class="collapse " id="feiraCadastros">


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

                <li class="nav-item ">
                  <a class="nav-link" href="./listaUnidade.php">
                    <i class="ti-ruler-pencil mr-2"></i> Unidades
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

            <div class="collapse text-black show" id="feiraRelatorios">
              <ul class="nav flex-column sub-menu" style="background:#fff !important;">
                <style>
                  .sub-menu .nav-item .nav-link {
                    color: black !important;
                  }

                  .sub-menu .nav-item .nav-link:hover {
                    color: blue !important;
                  }
                </style>
                <li class="nav-item">
                  <a class="nav-link active" href="./relatorioFinanceiro.php" style="color:white !important; background: #231475C5 !important;">
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

          <!-- Título DIVERSOS -->
          <li class="nav-item" style="pointer-events:none;">
            <span style="
                  display:block;
                  padding: 5px 15px 5px;
                  font-size: 11px;
                  font-weight: 600;
                  letter-spacing: 1px;
                  color: #6c757d;
                  text-transform: uppercase;
                ">
              Links Diversos
            </span>
          </li>

          <!-- Linha abaixo do título -->
          <li class="nav-item">
            <a class="nav-link" href="../index.php">
              <i class="ti-home menu-icon"></i>
              <span class="menu-title"> Painel Principal</span>
            </a>
          </li>
          <li class="nav-item">
            <a href="../produtor/" class="nav-link">
              <i class="ti-shopping-cart menu-icon"></i>
              <span class="menu-title">Feira do Produtor</span>

            </a>
          </li>
          <li class="nav-item">
            <a href="../mercado/" class="nav-link">
              <i class="ti-shopping-cart menu-icon"></i>
              <span class="menu-title">Mercado Municipal</span>

            </a>
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

          <!-- ======================
         CABEÇALHO
         ====================== -->
          <div class="row mb-4">
            <div class="col-12">
              <div class="d-flex flex-wrap align-items-center justify-content-between">
                <div>
                  <h2 class="font-weight-bold mb-1">Relatório Financeiro</h2>
                  <span class="badge badge-primary">
                    Feira Alternativa — <?= h($labelPeriodo) ?>
                  </span>
                </div>
              </div>
              <hr>
            </div>
          </div>

          <!-- ======================
         FILTRO (MÊS OU DIA)
         ====================== -->
          <div class="card mb-4">
            <div class="card-body py-3">

              <!-- Botões de alternância -->
              <div class="row mb-3">
                <div class="col-12">
                  <div class="btn-group btn-group-toggle" data-toggle="buttons">
                    <label class="btn btn-outline-primary <?= $tipoFiltro === 'mes' ? 'active' : '' ?>">
                      <input type="radio" name="tipo_filtro" value="mes" <?= $tipoFiltro === 'mes' ? 'checked' : '' ?>>
                      <i class="ti-calendar mr-1"></i> Filtrar por Mês
                    </label>
                    <label class="btn btn-outline-primary <?= $tipoFiltro === 'dia' ? 'active' : '' ?>">
                      <input type="radio" name="tipo_filtro" value="dia" <?= $tipoFiltro === 'dia' ? 'checked' : '' ?>>
                      <i class="ti-time mr-1"></i> Filtrar por Dia
                    </label>
                  </div>
                </div>
              </div>

              <div class="row align-items-end">

                <!-- Campo de data (muda entre mês e dia) -->
                <div class="col-md-6 mb-2">
                  <label class="mb-1" id="label-data">
                    <?= $tipoFiltro === 'dia' ? 'Data' : 'Mês' ?>
                  </label>
                  <input
                    type="<?= $tipoFiltro === 'dia' ? 'date' : 'month' ?>"
                    class="form-control"
                    id="input-data"
                    value="<?= h($tipoFiltro === 'dia' ? $dataSel : substr($dataSel, 0, 7)) ?>">
                </div>

                <div class="col-md-3 mb-2">
                  <button type="button" class="btn btn-primary w-100" id="btn-filtrar">
                    <i class="ti-search mr-1"></i> Filtrar
                  </button>
                </div>

                <div class="col-md-3 mb-2">
                  <a href="./relatorioFinanceiro.php" class="btn btn-outline-secondary w-100">
                    <i class="ti-reload mr-1"></i> Limpar
                  </a>
                </div>

              </div>
            </div>
          </div>

          <!-- ======================
         KPIs
         ====================== -->
          <div class="row mb-4">
            <div class="col-md-4 mb-3">
              <div class="kpi-card text-primary">
                <p class="kpi-label">Total do período</p>
                <p class="kpi-value">
                  R$ <?= number_format((float)$resumo['total'], 2, ',', '.') ?>
                </p>
                <div class="mini-kpi">Somatório das vendas</div>
              </div>
            </div>

            <div class="col-md-4 mb-3">
              <div class="kpi-card text-success">
                <p class="kpi-label">Vendas</p>
                <p class="kpi-value"><?= (int)$resumo['vendas_qtd'] ?></p>
                <div class="mini-kpi">Registros no período</div>
              </div>
            </div>

            <div class="col-md-4 mb-3">
              <div class="kpi-card text-info">
                <p class="kpi-label">Ticket médio</p>
                <p class="kpi-value">
                  R$ <?= number_format((float)$resumo['ticket'], 2, ',', '.') ?>
                </p>
                <div class="mini-kpi">Total ÷ vendas</div>
              </div>
            </div>
          </div>

          <!-- ======================
         PAGAMENTO + FEIRANTES
         ====================== -->
          <div class="row">
            <div class="col-lg-5 mb-4">
              <div class="card h-100">
                <div class="card-body">
                  <h4 class="card-title">Por forma de pagamento</h4>

                  <div class="table-responsive">
                    <table class="table table-hover">
                      <thead class="thead-light">
                        <tr>
                          <th>Pagamento</th>
                          <th class="text-center">Qtd</th>
                          <th class="text-right">Total</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php if (empty($porPagamento)): ?>
                          <tr>
                            <td colspan="3" class="text-center text-muted py-4">
                              Sem dados no período
                            </td>
                          </tr>
                          <?php else: foreach ($porPagamento as $p): ?>
                            <tr>
                              <td>
                                <span class="badge badge-soft">
                                  <?= h($p['pagamento'] ?? 'N/I') ?>
                                </span>
                              </td>
                              <td class="text-center"><?= (int)$p['qtd'] ?></td>
                              <td class="text-right">
                                <b>R$ <?= number_format((float)$p['total'], 2, ',', '.') ?></b>
                              </td>
                            </tr>
                        <?php endforeach;
                        endif; ?>
                      </tbody>
                    </table>
                  </div>

                </div>
              </div>
            </div>

            <div class="col-lg-7 mb-4">
              <div class="card h-100">
                <div class="card-body">
                  <h4 class="card-title">Resumo por feirante</h4>

                  <div class="table-responsive">
                    <table class="table table-hover">
                      <thead class="thead-light">
                        <tr>
                          <th>Feirante</th>
                          <th class="text-center">Vendas</th>
                          <th class="text-right">Total</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php if (empty($porProdutor)): ?>
                          <tr>
                            <td colspan="3" class="text-center text-muted py-4">
                              Sem dados no período
                            </td>
                          </tr>
                          <?php else: foreach ($porProdutor as $p): ?>
                            <tr>
                              <td><?= h($p['nome']) ?></td>
                              <td class="text-center"><?= (int)$p['vendas_qtd'] ?></td>
                              <td class="text-right">
                                <b>R$ <?= number_format((float)$p['total'], 2, ',', '.') ?></b>
                              </td>
                            </tr>
                        <?php endforeach;
                        endif; ?>
                      </tbody>
                    </table>
                  </div>

                  <?php if ($totalPagesProdutor > 1): ?>
                    <nav>
                      <ul class="pagination justify-content-end mt-3">
                        <?php for ($i = 1; $i <= $totalPagesProdutor; $i++): ?>
                          <li class="page-item <?= $i == $pageProdutor ? 'active' : '' ?>">
                            <a class="page-link"
                              href="?<?= http_build_query(array_merge($_GET, ['page_produtor' => $i])) ?>">
                              <?= $i ?>
                            </a>
                          </li>
                        <?php endfor; ?>
                      </ul>
                    </nav>
                  <?php endif; ?>

                </div>
              </div>
            </div>

          </div>

          <!-- ======================
         RESUMO POR DIA (só mostra no filtro mensal)
         ====================== -->
          <?php if ($tipoFiltro === 'mes' && !empty($porDia)): ?>
            <div class="card mb-4">
              <div class="card-body">
                <h4 class="card-title">Resumo por dia</h4>

                <div class="table-responsive">
                  <table class="table table-hover">
                    <thead class="thead-light">
                      <tr>
                        <th>Dia</th>
                        <th class="text-center">Vendas</th>
                        <th class="text-right">Total</th>
                        <th class="text-center">Ações</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php foreach ($porDia as $d): ?>
                        <tr>
                          <td><?= date('d/m/Y', strtotime($d['dia'])) ?></td>
                          <td class="text-center"><?= (int)$d['vendas_qtd'] ?></td>
                          <td class="text-right">
                            <b>R$ <?= number_format((float)$d['total'], 2, ',', '.') ?></b>
                          </td>
                          <td class="text-center">
                            <a href="?<?= http_build_query(array_merge($_GET, [
                                        'tipo' => 'dia',
                                        'data' => $d['dia']
                                      ])) ?>" class="btn btn-sm btn-outline-primary">
                              <i class="ti-eye"></i> Ver detalhes
                            </a>
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>

                <?php if ($totalPagesDia > 1): ?>
                  <nav>
                    <ul class="pagination justify-content-end mt-3">
                      <?php for ($i = 1; $i <= $totalPagesDia; $i++): ?>
                        <li class="page-item <?= $i == $pageDia ? 'active' : '' ?>">
                          <a class="page-link"
                            href="?<?= http_build_query(array_merge($_GET, ['page_dia' => $i])) ?>">
                            <?= $i ?>
                          </a>
                        </li>
                      <?php endfor; ?>
                    </ul>
                  </nav>
                <?php endif; ?>

              </div>
            </div>
          <?php endif; ?>


          <!-- ======================
         LISTA DE VENDAS
         ====================== -->
          <?php if ($tipoFiltro === 'mes' && !empty($porDia)): ?>
            <div class="card mb-4">
              <div class="card-body">
                <h4 class="card-title">Resumo por dia</h4>

                <div class="table-responsive">
                  <table class="table table-hover">
                    <thead class="thead-light">
                      <tr>
                        <th>Dia</th>
                        <th class="text-center">Vendas</th>
                        <th class="text-right">Total</th>
                        <th class="text-center">Ações</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php foreach ($porDia as $d): ?>
                        <tr>
                          <td><?= date('d/m/Y', strtotime($d['dia'])) ?></td>
                          <td class="text-center"><?= (int)$d['vendas_qtd'] ?></td>
                          <td class="text-right">
                            <b>R$ <?= number_format((float)$d['total'], 2, ',', '.') ?></b>
                          </td>
                          <td class="text-center">
                            <a href="?<?= http_build_query(array_merge($_GET, [
                                        'tipo' => 'dia',
                                        'data' => $d['dia']
                                      ])) ?>" class="btn btn-sm btn-outline-primary">
                              <i class="ti-eye"></i> Ver detalhes
                            </a>
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>

                <?php if ($totalPagesDia > 1): ?>
                  <nav>
                    <ul class="pagination justify-content-end mt-3">
                      <?php for ($i = 1; $i <= $totalPagesDia; $i++): ?>
                        <li class="page-item <?= $i == $pageDia ? 'active' : '' ?>">
                          <a class="page-link"
                            href="?<?= http_build_query(array_merge($_GET, ['page_dia' => $i])) ?>">
                            <?= $i ?>
                          </a>
                        </li>
                      <?php endfor; ?>
                    </ul>
                  </nav>
                <?php endif; ?>

              </div>
            </div>
          <?php endif; ?>


        </div>

        <!-- ======================
       FOOTER
       ====================== -->
        <footer class="footer">
          <span class="text-muted">
            © <?= date('Y') ?> SIGRelatórios —
            <a href="https://www.lucascorrea.pro/" target="_blank">lucascorrea.pro</a>
          </span>
        </footer>

      </div>


    </div>
  </div>

  <script src="../../../vendors/js/vendor.bundle.base.js"></script>
  <script src="../../../js/off-canvas.js"></script>
  <script src="../../../js/hoverable-collapse.js"></script>
  <script src="../../../js/template.js"></script>
  <script src="../../../js/settings.js"></script>
  <script src="../../../js/todolist.js"></script>

  <script>
    // Lógica para alternar entre filtro de mês e dia
    document.addEventListener('DOMContentLoaded', function() {
      const radioMes = document.querySelector('input[value="mes"]');
      const radioDia = document.querySelector('input[value="dia"]');
      const inputData = document.getElementById('input-data');
      const labelData = document.getElementById('label-data');
      const btnFiltrar = document.getElementById('btn-filtrar');

      function atualizarTipoFiltro() {
        const tipo = radioDia.checked ? 'dia' : 'mes';

        if (tipo === 'dia') {
          inputData.type = 'date';
          labelData.textContent = 'Data';
          if (inputData.value.length === 7) { // formato YYYY-MM
            inputData.value = inputData.value + '-01';
          }
        } else {
          inputData.type = 'month';
          labelData.textContent = 'Mês';
          if (inputData.value.length === 10) { // formato YYYY-MM-DD
            inputData.value = inputData.value.substring(0, 7);
          }
        }
      }

      radioMes.addEventListener('change', atualizarTipoFiltro);
      radioDia.addEventListener('change', atualizarTipoFiltro);

      btnFiltrar.addEventListener('click', function() {
        const tipo = radioDia.checked ? 'dia' : 'mes';
        const data = inputData.value;

        if (!data) {
          alert('Por favor, selecione uma data.');
          return;
        }

        window.location.href = '?tipo=' + tipo + '&data=' + data;
      });

      // Inicializar o estado correto
      atualizarTipoFiltro();
    });
  </script>
</body>

</html>