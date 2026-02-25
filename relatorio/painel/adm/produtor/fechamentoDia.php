<?php

declare(strict_types=1);
session_start();

/* Timezone (Amazonas) */
date_default_timezone_set('America/Manaus');

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

function h($s)
{
  return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

/* Flash */
$msg = (string)($_SESSION['flash_ok'] ?? '');
$err = (string)($_SESSION['flash_err'] ?? '');
unset($_SESSION['flash_ok'], $_SESSION['flash_err']);

/* CSRF */
if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf = (string)$_SESSION['csrf_token'];

/* ===== Conexão ===== */
require '../../../assets/php/conexao.php';
$pdo = db();

/* Força timezone do MySQL (Amazonas = -04:00) */
try {
    $pdo->exec("SET time_zone = '-04:00'");
} catch (Throwable $e) {}

/* Feira do Produtor = 1 (na Feira Alternativa use 2) */
$feiraId = 1;

/* Dia */
$dia = trim((string)($_GET['dia'] ?? date('Y-m-d')));
if ($dia === '') $dia = date('Y-m-d');

/* ===== Filtros e Paginação ===== */
$searchCpf = only_digits(trim((string)($_GET['cpf'] ?? '')));
$perPage = 7;
$page = (int)($_GET['p'] ?? 1);
if ($page < 1) $page = 1;
$offset = ($page - 1) * $perPage;

function only_digits(string $s): string {
  $out = preg_replace('/\D+/', '', $s);
  return $out !== null ? $out : '';
}

/* ===== Romaneio do dia (busca se existe) ===== */
$romaneioId = null;
$romaneioStatus = 'ABERTO';
try {
  $stR = $pdo->prepare("SELECT id, status FROM romaneio_dia WHERE feira_id = :f AND data_ref = :d LIMIT 1");
  $stR->execute([':f' => $feiraId, ':d' => $dia]);
  $rowR = $stR->fetch();
  if ($rowR) {
    $romaneioId = (int)$rowR['id'];
    $romaneioStatus = (string)$rowR['status'];
  }
} catch (Throwable $e) {}

/* ===== Resumo do dia (KPIs simplificados) ===== */
$resumo = [
  'entradas_total'  => 0.0,
  'entradas_qtd'    => 0,
  'feirantes_patio' => 0,
];

try {
  // Total Entradas
  if ($romaneioId) {
    $stE = $pdo->prepare("
      SELECT COUNT(*) as qtd, COALESCE(SUM(quantidade_entrada * preco_unitario_dia), 0) as total
      FROM romaneio_itens
      WHERE romaneio_id = :rom
    ");
    $stE->execute([':rom' => $romaneioId]);
    $re = $stE->fetch();
    $resumo['entradas_qtd'] = (int)$re['qtd'];
    $resumo['entradas_total'] = (float)$re['total'];
  }

  // Produtores no Pátio (com lançamentos no romaneio)
  $stP = $pdo->prepare("SELECT COUNT(DISTINCT produtor_id) FROM romaneio_itens WHERE romaneio_id = :rom");
  $stP->execute([':rom' => ($romaneioId ?? -1)]);
  $resumo['feirantes_patio'] = (int)$stP->fetchColumn();

} catch (Throwable $e) {}

/* ===== Listagem de Produtores (com paginação e busca) ===== */
$porFeirante = [];
$totalProdutores = 0;
$totalPages = 1;

try {
  $sqlWhere = " WHERE p.feira_id = :f AND p.ativo = 1 ";
  $params = [':f' => $feiraId];

  // Se houver busca por CPF
  if ($searchCpf !== '') {
    $sqlWhere .= " AND p.documento LIKE :cpf ";
    $params[':cpf'] = "%$searchCpf%";
  }

  // Apenas produtores que tiveram lançamentos no dia
  $sqlWhere .= " AND EXISTS (SELECT 1 FROM romaneio_itens ri WHERE ri.produtor_id = p.id AND ri.romaneio_id = :rom) ";
  $params[':rom'] = ($romaneioId ?? -1);

  // Total para paginação
  $stCount = $pdo->prepare("SELECT COUNT(*) FROM produtores p $sqlWhere");
  $stCount->execute($params);
  $totalProdutores = (int)$stCount->fetchColumn();
  $totalPages = max(1, (int)ceil($totalProdutores / $perPage));

  if ($page > $totalPages) {
    $page = $totalPages;
    $offset = ($page - 1) * $perPage;
  }

  // Lista paginada
  $stList = $pdo->prepare("
    SELECT p.id, p.nome, p.documento,
           (SELECT COALESCE(SUM(quantidade_entrada * preco_unitario_dia), 0) 
            FROM romaneio_itens ri 
            WHERE ri.produtor_id = p.id AND ri.romaneio_id = :rom) as total_lancado,
           (SELECT COUNT(*) 
            FROM romaneio_itens ri 
            WHERE ri.produtor_id = p.id AND ri.romaneio_id = :rom AND ri.quantidade_vendida IS NOT NULL) as itens_fechados,
           (SELECT COUNT(*) 
            FROM romaneio_itens ri 
            WHERE ri.produtor_id = p.id AND ri.romaneio_id = :rom) as total_itens
    FROM produtores p 
    $sqlWhere 
    ORDER BY p.nome ASC 
    LIMIT :lim OFFSET :off
  ");
  foreach ($params as $k => $v) $stList->bindValue($k, $v);
  $stList->bindValue(':lim', $perPage, PDO::PARAM_INT);
  $stList->bindValue(':off', $offset, PDO::PARAM_INT);
  $stList->execute();
  $porFeirante = $stList->fetchAll();

} catch (Throwable $e) {}

  /* Top Produtos (por valor) */
  try {
    $st3 = $pdo->prepare("
      SELECT
        p.id,
        p.nome,
        COALESCE(SUM(vi.quantidade), 0) AS qtd_total,
        COALESCE(SUM(vi.subtotal), 0) AS valor_total
      FROM vendas v
      JOIN venda_itens vi
        ON vi.feira_id = v.feira_id AND vi.venda_id = v.id
      JOIN produtos p
        ON p.feira_id = vi.feira_id AND p.id = vi.produto_id
      WHERE v.feira_id = :f
        AND DATE(v.data_hora) = :d
        AND UPPER(v.status) <> 'CANCELADA'
      GROUP BY p.id, p.nome
      ORDER BY valor_total DESC, p.nome ASC
      LIMIT 15
    ");
    $st3->bindValue(':f', $feiraId, PDO::PARAM_INT);
    $st3->bindValue(':d', $dia, PDO::PARAM_STR);
    $st3->execute();
    $topProdutos = $st3->fetchAll();
  } catch (PDOException $e) {
    $mysqlCode = (int)($e->errorInfo[1] ?? 0);
    if ($mysqlCode === 1146) $err = $err ?: 'As tabelas de lançamentos não existem (vendas / venda_itens). Rode o SQL das tabelas.';
    else $err = $err ?: 'Não foi possível carregar o fechamento do dia agora.';
  } catch (Throwable $e) {
    $err = $err ?: 'Não foi possível carregar o fechamento do dia agora.';
  }

/* ===== POST: Registrar/Atualizar fechamento (DB NOVO: fechamento_dia) ===== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $acao = (string)($_POST['acao'] ?? '');
  $postedCsrf = (string)($_POST['csrf_token'] ?? '');
  if (!hash_equals($csrf, $postedCsrf)) {
    $_SESSION['flash_err'] = 'Sessão expirada. Atualize a página e tente novamente.';
    header('Location: ./fechamentoDia.php?dia=' . urlencode($dia));
    exit;
  }

  /* ===== POST: Registrar fechamento individual (romaneio_itens) ===== */
  if ($acao === 'fechar_individual') {
    $produtorId = (int)($_POST['produtor_id'] ?? 0);
    $vendas = $_POST['venda'] ?? []; // [item_id => qtd_vendida]

    try {
      $pdo->beginTransaction();
      foreach ($vendas as $itemId => $qtd) {
        $itemId = (int)$itemId;
        $qtdVendida = (float)str_replace(',', '.', (string)$qtd);
        
        // Busca quantidade de entrada para calcular a sobra
        $stQ = $pdo->prepare("SELECT quantidade_entrada FROM romaneio_itens WHERE id = :id AND produtor_id = :p");
        $stQ->execute([':id' => $itemId, ':p' => $produtorId]);
        $qtdEntrada = (float)$stQ->fetchColumn();
        
        $sobra = max(0, $qtdEntrada - $qtdVendida);
        $totalBruto = $qtdVendida * 0; // Se quiser salvar o valor bruto aqui, precisaria do preço. Mas no individual o foco é estoque.

        $up = $pdo->prepare("
          UPDATE romaneio_itens 
          SET quantidade_vendida = :v, quantidade_sobra = :s, atualizado_em = NOW() 
          WHERE id = :id AND produtor_id = :p
        ");
        $up->execute([
          ':v'  => $qtdVendida,
          ':s'  => $sobra,
          ':id' => $itemId,
          ':p'  => $produtorId
        ]);
      }
      $pdo->commit();
      $_SESSION['flash_ok'] = 'Fechamento do produtor registrado com sucesso.';
    } catch (Throwable $e) {
      if ($pdo->inTransaction()) $pdo->rollBack();
      $_SESSION['flash_err'] = 'Erro ao registrar fechamento: ' . $e->getMessage();
    }
    header('Location: ./fechamentoDia.php?dia=' . urlencode($dia) . '&cpf=' . urlencode($searchCpf) . '&p=' . $page);
    exit;
  }
}
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <title>SIGRelatórios Feira do Produtor — Fechamento do Dia</title>

  <link rel="stylesheet" href="../../../vendors/feather/feather.css">
  <link rel="stylesheet" href="../../../vendors/ti-icons/css/themify-icons.css">
  <link rel="stylesheet" href="../../../vendors/css/vendor.bundle.base.css">

  <link rel="stylesheet" href="../../../vendors/datatables.net-bs4/dataTables.bootstrap4.css">
  <link rel="stylesheet" href="../../../vendors/ti-icons/css/themify-icons.css">
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

    .kpi-card {
      border: 1px solid rgba(0, 0, 0, .08);
      border-radius: 14px;
      padding: 14px;
      background: #fff;
      height: 100%;
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

    .kpi-sub {
      font-size: 12px;
      color: #6c757d;
      margin-top: 6px;
    }

    .table td,
    .table th {
      vertical-align: middle !important;
    }

    /* ===== Flash “Hostinger style” (top-right, menor, ~6s) ===== */
    .sig-flash-wrap {
      position: fixed;
      top: 78px;
      right: 18px;
      left: auto;
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

    .mini {
      font-size: 12px;
      color: #6c757d;
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
        <ul class="navbar-nav navbar-nav-right"></ul>
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
            <button type="button" class="close" data-dismiss="alert" aria-label="Fechar">
              <span aria-hidden="true">&times;</span>
            </button>
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
            <button type="button" class="close" data-dismiss="alert" aria-label="Fechar">
              <span aria-hidden="true">&times;</span>
            </button>
          </div>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <div class="container-fluid page-body-wrapper">

      <div id="right-sidebar" class="settings-panel">
        <i class="settings-close ti-close"></i>
        <ul class="nav nav-tabs border-top" id="setting-panel" role="tablist">
          <li class="nav-item"><a class="nav-link active" id="todo-tab" data-toggle="tab" href="#todo-section" role="tab">TO DO LIST</a></li>
          <li class="nav-item"><a class="nav-link" id="chats-tab" data-toggle="tab" href="#chats-section" role="tab">CHATS</a></li>
        </ul>
      </div>

      <!-- SIDEBAR (mantida no padrão) -->
      <nav class="sidebar sidebar-offcanvas" id="sidebar">
        <ul class="nav">

          <li class="nav-item">
            <a class="nav-link" href="index.php">
              <i class="icon-grid menu-icon"></i>
              <span class="menu-title">Dashboard</span>
            </a>
          </li>

          <li class="nav-item">
            <a class="nav-link" data-toggle="collapse" href="#feiraCadastros" aria-expanded="false" aria-controls="feiraCadastros">
              <i class="ti-id-badge menu-icon"></i>
              <span class="menu-title">Cadastros</span>
              <i class="menu-arrow"></i>
            </a>

            <div class="collapse" id="feiraCadastros">
              <style>
                .sub-menu .nav-item .nav-link {
                  color: black !important;
                }

                .sub-menu .nav-item .nav-link:hover {
                  color: blue !important;
                }
              </style>

              <ul class="nav flex-column sub-menu" style="background: white !important;">
                <li class="nav-item"><a class="nav-link" href="./listaProduto.php"><i class="ti-clipboard mr-2"></i> Lista de Produtos</a></li>
                <li class="nav-item"><a class="nav-link" href="./listaCategoria.php"><i class="ti-layers mr-2"></i> Categorias</a></li>
                <li class="nav-item"><a class="nav-link" href="./listaUnidade.php"><i class="ti-ruler-pencil mr-2"></i> Unidades</a></li>
                <li class="nav-item"><a class="nav-link" href="./listaProdutor.php"><i class="ti-user mr-2"></i> Produtores</a></li>
              </ul>
            </div>
          </li>

          <!-- MOVIMENTO (ATIVO) -->
          <li class="nav-item active">
            <a class="nav-link open" data-toggle="collapse" href="#feiraMovimento" aria-expanded="true" aria-controls="feiraMovimento">
              <i class="ti-exchange-vertical menu-icon"></i>
              <span class="menu-title">Movimento</span>
              <i class="menu-arrow"></i>
            </a>

            <div class="collapse show" id="feiraMovimento">
              <ul class="nav flex-column sub-menu" style="background:#fff !important;">
                <li class="nav-item">
                  <a class="nav-link" href="./lancamentos.php">
                    <i class="ti-write mr-2"></i> Lançamentos (Vendas)
                  </a>
                </li>
                <li class="nav-item active">
                  <a class="nav-link" href="./fechamentoDia.php" style="color:white !important; background: #231475C5 !important;">
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
                <li class="nav-item"><a class="nav-link" href="./relatorioFinanceiro.php"><i class="ti-bar-chart mr-2"></i> Relatório Financeiro</a></li>
                <li class="nav-item"><a class="nav-link" href="./relatorioProdutos.php"><i class="ti-list mr-2"></i> Produtos Comercializados</a></li>
                <li class="nav-item"><a class="nav-link" href="./relatorioMensal.php"><i class="ti-calendar mr-2"></i> Resumo Mensal</a></li>
                <li class="nav-item"><a class="nav-link" href="./configRelatorio.php"><i class="ti-settings mr-2"></i> Configurar</a></li>
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
            <a href="../alternativa/" class="nav-link">
              <i class="ti-shopping-cart menu-icon"></i>
              <span class="menu-title">Feira do Alternativa</span>

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

          <div class="row">
            <div class="col-12 mb-3">
              <h3 class="font-weight-bold">Fechamento do Dia</h3>
              <h6 class="font-weight-normal mb-0">Ajustado para o seu DB: <span class="mini">vendas.data_hora + fechamento_dia.data_ref</span></h6>
            </div>
          </div>

          <!-- FILTRO DIA -->
          <div class="row">
            <div class="col-lg-12 grid-margin stretch-card">
              <div class="card">
                <div class="card-body">
                  <div class="d-flex align-items-center justify-content-between flex-wrap">
                    <div>
                      <h4 class="card-title mb-0">Selecionar dia e Buscar Produtor</h4>
                      <p class="card-description mb-0">Filtre por data e CPF para registrar o fechamento individual.</p>
                    </div>
                  </div>

                  <form method="get" action="" class="row mt-3">
                    <div class="col-md-3 mb-2">
                      <label class="mb-1">Data</label>
                      <input type="date" name="dia" class="form-control" value="<?= h($dia) ?>" onchange="this.form.submit();">
                    </div>
                    <div class="col-md-4 mb-2">
                      <label class="mb-1">Buscar por CPF</label>
                      <input type="text" name="cpf" class="form-control" value="<?= h($searchCpf) ?>" placeholder="Apenas dígitos..." oninput="this.value = this.value.replace(/\D/g,'')">
                    </div>
                    <div class="col-md-5 mb-2 d-flex align-items-end" style="gap:8px;">
                      <button type="submit" class="btn btn-primary"><i class="ti-search mr-1"></i> Filtrar</button>
                      <a href="./fechamentoDia.php?dia=<?= h($dia) ?>" class="btn btn-light"><i class="ti-reload mr-1"></i> Limpar</a>
                      <a href="./lancamentos.php?dia=<?= h($dia) ?>" class="btn btn-light"><i class="ti-write mr-1"></i> Lançar Itens</a>
                    </div>
                  </form>

                </div>
              </div>
            </div>
          </div>

          <div class="row">
            <div class="col-md-6 mb-3">
              <div class="kpi-card">
                <p class="kpi-label">Total Entradas (Romaneio)</p>
                <p class="kpi-value">R$ <?= number_format($resumo['entradas_total'], 2, ',', '.') ?></p>
                <div class="kpi-sub">Soma de tudo que os produtores trouxeram hoje.</div>
              </div>
            </div>
            <div class="col-md-6 mb-3">
              <div class="kpi-card">
                <p class="kpi-label">Produtores no Pátio</p>
                <p class="kpi-value"><?= $resumo['feirantes_patio'] ?></p>
                <div class="kpi-sub"><?= $resumo['entradas_qtd'] ?> itens lançados no dia.</div>
              </div>
            </div>
          </div>

          <!-- LISTAGEM DE PRODUTORES -->
          <div class="row">
            <div class="col-lg-12 grid-margin stretch-card">
              <div class="card">
                <div class="card-body">
                  <h4 class="card-title">Produtores com Lançamentos</h4>
                  <p class="card-description">Lista de feirantes que entregaram produtos no dia <?= h($dia) ?>.</p>

                  <div class="table-responsive pt-2">
                    <table class="table table-striped table-hover">
                      <thead>
                        <tr>
                          <th>Produtor</th>
                          <th>CPF</th>
                          <th>Total Lançado</th>
                          <th>Status</th>
                          <th style="width:180px;">Ação</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php if (empty($porFeirante)): ?>
                          <tr>
                            <td colspan="5" class="text-center py-4 text-muted">Nenhum produtor encontrado com lançamentos nesta data.</td>
                          </tr>
                        <?php else: ?>
                          <?php foreach ($porFeirante as $p): 
                            $completo = ($p['itens_fechados'] >= $p['total_itens']);
                          ?>
                            <tr>
                              <td><b><?= h($p['nome']) ?></b></td>
                              <td><?= h($p['documento']) ?></td>
                              <td>R$ <?= number_format((float)$p['total_lancado'], 2, ',', '.') ?></td>
                              <td>
                                <?php if ($completo): ?>
                                  <span class="badge badge-success">Fechado</span>
                                <?php else: ?>
                                  <span class="badge badge-warning">Pendente (<?= $p['itens_fechados'] ?>/<?= $p['total_itens'] ?>)</span>
                                <?php endif; ?>
                              </td>
                              <td>
                                <button type="button" class="btn btn-primary btn-sm" onclick="abrirModalFechamento(<?= $p['id'] ?>, '<?= addslashes(h($p['nome'])) ?>')">
                                  <i class="ti-check-box mr-1"></i> Fechar Produção
                                </button>
                              </td>
                            </tr>
                          <?php endforeach; ?>
                        <?php endif; ?>
                      </tbody>
                    </table>
                  </div>

                  <!-- PAGINAÇÃO -->
                  <?php if ($totalPages > 1): ?>
                    <nav class="mt-4">
                      <ul class="pagination pagination-sm justify-content-center">
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                          <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                            <a class="page-link" href="?dia=<?= h($dia) ?>&cpf=<?= h($searchCpf) ?>&p=<?= $i ?>"><?= $i ?></a>
                          </li>
                        <?php endfor; ?>
                      </ul>
                    </nav>
                  <?php endif; ?>

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
    </div>
  </div>

  <!-- MODAL FECHAMENTO -->
  <div class="modal fade" id="modalFechamento" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
      <div class="modal-content" style="border-radius:16px;">
        <form method="post" action="">
          <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
          <input type="hidden" name="acao" value="fechar_individual">
          <input type="hidden" name="produtor_id" id="modal_produtor_id">

          <div class="modal-header">
            <h5 class="modal-title">Fechar Produção: <span id="modal_produtor_nome" class="text-primary font-weight-bold"></span></h5>
            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
              <span aria-hidden="true">&times;</span>
            </button>
          </div>
          <div class="modal-body">
            <p class="text-muted small mb-3">Informe a quantidade vendida de cada produto lançado no dia <?= h($dia) ?>.</p>
            <div id="modal_itens_body">
               <div class="text-center py-4"><i class="ti-reload spin"></i> Carregando itens...</div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-light" data-dismiss="modal">Cancelar</button>
            <button type="submit" class="btn btn-primary"><i class="ti-check mr-1"></i> Confirmar Fechamento</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <script src="../../../vendors/js/vendor.bundle.base.js"></script>
  <script src="../../../vendors/chart.js/Chart.min.js"></script>

  <script src="../../../js/off-canvas.js"></script>
  <script src="../../../js/hoverable-collapse.js"></script>
  <script src="../../../js/template.js"></script>
  <script src="../../../js/settings.js"></script>
  <script src="../../../js/todolist.js"></script>

  <script src="../../../js/dashboard.js"></script>
  <script src="../../../js/Chart.roundedBarCharts.js"></script>
  <script>
    function abrirModalFechamento(id, nome) {
      $('#modal_produtor_id').val(id);
      $('#modal_produtor_nome').text(nome);
      $('#modal_itens_body').html('<div class="text-center py-4"><i class="ti-reload spin"></i> Carregando itens...</div>');
      $('#modal_itens_body').load('ajax_itens_fechamento.php?id=' + id + '&dia=<?= urlencode($dia) ?>');
      $('#modalFechamento').modal('show');
    }

    function toggleVendeuTudo(itemId, qtdTotal) {
      let input = document.getElementById('venda_' + itemId);
      let btn = event.currentTarget;
      if (btn.classList.contains('btn-outline-success')) {
        input.value = qtdTotal;
        btn.classList.remove('btn-outline-success');
        btn.classList.add('btn-success');
        btn.innerHTML = '<i class="ti-check"></i> Vendeu Tudo';
      } else {
        input.value = '';
        btn.classList.remove('btn-success');
        btn.classList.add('btn-outline-success');
        btn.innerHTML = 'Vendeu Tudo?';
      }
    }
  </script>
</body>

</html>