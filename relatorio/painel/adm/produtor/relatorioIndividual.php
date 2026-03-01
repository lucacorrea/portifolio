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
function to_int($v, int $default = 0): int {
  if ($v === null) return $default;
  if (is_int($v)) return $v;
  if (is_numeric($v)) return (int)$v;
  return $default;
}
function brl(float $v): string {
  return 'R$ ' . number_format($v, 2, ',', '.');
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
   HELPERS (SCHEMA + EXEC SAFE)
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

/** Evita HY093 quando sobram parâmetros */
function params_for_sql(string $sql, array $params): array {
  preg_match_all('/:([a-zA-Z0-9_]+)/', $sql, $m);
  $need = array_unique($m[1] ?? []);
  $out = [];
  foreach ($need as $name) {
    $k = ':' . $name;
    if (array_key_exists($k, $params)) $out[$k] = $params[$k];
    elseif (array_key_exists($name, $params)) $out[$k] = $params[$name];
  }
  return $out;
}
function exec_stmt(PDOStatement $st, array $params = []): void {
  $st->execute(params_for_sql($st->queryString, $params));
}

/* ======================
   FEIRA FIXA
====================== */
$feiraId = 1;

/* ======================
   TABELAS OBRIGATÓRIAS
====================== */
if (
  !hasTable($pdo, 'vendas') ||
  !hasTable($pdo, 'venda_itens') ||
  !hasTable($pdo, 'produtos') ||
  !hasTable($pdo, 'produtores')
) {
  $err = 'Tabelas obrigatórias não encontradas (vendas, venda_itens, produtos, produtores).';
}

/* ======================
   CAMPOS (COMPAT)
====================== */
$colDataVenda = hasColumn($pdo, 'vendas', 'data_venda');      // opcional
$colDataHora  = hasColumn($pdo, 'vendas', 'data_hora');       // no seu schema: SIM
$colFormaPgto = hasColumn($pdo, 'vendas', 'forma_pagamento'); // no seu schema: SIM
$colStatus    = hasColumn($pdo, 'vendas', 'status');          // no seu schema: SIM

if ($colDataHora) $dateField = "v.data_hora";
elseif ($colDataVenda) $dateField = "v.data_venda";
else $dateField = "v.criado_em";

$dateExprDate = "DATE($dateField)";

/* ======================
   FILTRO: MÊS OU DIA
====================== */
$tipoFiltro = ($_GET['tipo'] ?? 'mes') === 'dia' ? 'dia' : 'mes';
$dataRaw = (string)($_GET['data'] ?? '');
$today = date('Y-m-d');

if ($tipoFiltro === 'dia') {
  if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataRaw)) $dataRaw = $today;
  $periodStart = $dataRaw;
  $periodEnd   = $dataRaw;
  $labelPeriodo = date('d/m/Y', strtotime($dataRaw));
} else {
  if (preg_match('/^\d{4}-\d{2}$/', $dataRaw)) $mesSel = $dataRaw;
  elseif (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataRaw)) $mesSel = substr($dataRaw, 0, 7);
  else $mesSel = date('Y-m');

  $periodStart = $mesSel . '-01';
  $periodEnd   = date('Y-m-t', strtotime($periodStart));
  $labelPeriodo = date('m/Y', strtotime($periodStart));
}

/* ======================
   PAGINAÇÃO (PRODUTORES)
====================== */
$PER_PAGE = 10;
$pageProdutor = max(1, to_int($_GET['page_produtor'] ?? 1, 1));
$offsetProdutor = ($pageProdutor - 1) * $PER_PAGE;

/* ======================
   URL HELPER
====================== */
function url_with(array $overrides = []): string {
  $q = $_GET;
  foreach ($overrides as $k => $v) {
    if ($v === null) unset($q[$k]);
    else $q[$k] = $v;
  }
  $base = strtok($_SERVER['REQUEST_URI'], '?') ?: '';
  return $base . (empty($q) ? '' : '?' . http_build_query($q));
}

/* ======================
   DADOS PRINCIPAIS
====================== */
$nomeUsuario = $_SESSION['usuario_nome'] ?? 'Usuário';

$hasComunidades = hasTable($pdo, 'comunidades') && hasColumn($pdo, 'produtores', 'comunidade_id');
$hasUnidades    = hasTable($pdo, 'unidades') && hasColumn($pdo, 'produtos', 'unidade_id');
$hasCategorias  = hasTable($pdo, 'categorias') && hasColumn($pdo, 'produtos', 'categoria_id');

$resumoGeral = [
  'produtores_qtd' => 0,
  'vendas_qtd' => 0,
  'total_produtores' => 0.0,
  'ticket' => 0.0,
];

$produtoresRows = [];
$totalPagesProdutores = 1;

/* Detalhe modal */
$detProdutorId = to_int($_GET['det_produtor_id'] ?? 0, 0);
$detProdutorInfo = null;
$detResumo = ['vendas_qtd'=>0, 'total'=>0.0, 'itens_qtd'=>0.0, 'produtos_distintos'=>0, 'ticket'=>0.0];
$detPorPagamento = [];
$detProdutos = [];
$detVendas = [];

try {
  if (!$err) {
    $paramsBase = [
      ':f'   => $feiraId,
      ':ini' => $periodStart,
      ':fim' => $periodEnd,
    ];

    $whereData = ($tipoFiltro === 'dia')
      ? "($dateExprDate = :ini)"
      : "($dateExprDate BETWEEN :ini AND :fim)";

    /* ======================
       RESUMO GERAL (do período, por produtores)
       - total_produtores = soma dos itens (vi.subtotal)
    ====================== */
    $sqlResumo = "
      SELECT
        COUNT(DISTINCT p.id) AS produtores_qtd,
        COUNT(DISTINCT v.id) AS vendas_qtd,
        COALESCE(SUM(vi.subtotal),0) AS total_produtores
      FROM vendas v
      JOIN venda_itens vi ON vi.venda_id = v.id
      JOIN produtos pr ON pr.id = vi.produto_id
      JOIN produtores p ON p.id = pr.produtor_id
      WHERE v.feira_id = :f
        AND vi.feira_id = :f
        AND pr.feira_id = :f
        AND p.feira_id  = :f
        AND $whereData
    ";
    $st = $pdo->prepare($sqlResumo);
    exec_stmt($st, $paramsBase);
    $r = $st->fetch(PDO::FETCH_ASSOC) ?: [];
    $resumoGeral['produtores_qtd'] = (int)($r['produtores_qtd'] ?? 0);
    $resumoGeral['vendas_qtd'] = (int)($r['vendas_qtd'] ?? 0);
    $resumoGeral['total_produtores'] = (float)($r['total_produtores'] ?? 0);
    $resumoGeral['ticket'] = $resumoGeral['vendas_qtd'] > 0 ? ($resumoGeral['total_produtores'] / $resumoGeral['vendas_qtd']) : 0;

    /* ======================
       TOTAL PRODUTORES p/ PAGINAÇÃO
    ====================== */
    $sqlCountProd = "
      SELECT COUNT(DISTINCT p.id)
      FROM vendas v
      JOIN venda_itens vi ON vi.venda_id = v.id
      JOIN produtos pr ON pr.id = vi.produto_id
      JOIN produtores p ON p.id = pr.produtor_id
      WHERE v.feira_id = :f
        AND vi.feira_id = :f
        AND pr.feira_id = :f
        AND p.feira_id  = :f
        AND $whereData
    ";
    $st = $pdo->prepare($sqlCountProd);
    exec_stmt($st, $paramsBase);
    $totalProdutoresMov = (int)$st->fetchColumn();
    $totalPagesProdutores = max(1, (int)ceil($totalProdutoresMov / $PER_PAGE));

    /* ======================
       TABELA: PRODUTORES NO PERÍODO (PAGINADO)
    ====================== */
    $selectComunidade = $hasComunidades ? "c.nome AS comunidade_nome" : "NULL AS comunidade_nome";
    $joinComunidade   = $hasComunidades ? "LEFT JOIN comunidades c ON c.id = p.comunidade_id" : "";

    $sqlProdutores = "
      SELECT
        p.id,
        p.nome,
        $selectComunidade,
        COUNT(DISTINCT v.id) AS vendas_qtd,
        COALESCE(SUM(vi.quantidade),0) AS itens_qtd,
        COALESCE(SUM(vi.subtotal),0) AS total
      FROM vendas v
      JOIN venda_itens vi ON vi.venda_id = v.id
      JOIN produtos pr ON pr.id = vi.produto_id
      JOIN produtores p ON p.id = pr.produtor_id
      $joinComunidade
      WHERE v.feira_id = :f
        AND vi.feira_id = :f
        AND pr.feira_id = :f
        AND p.feira_id  = :f
        AND $whereData
      GROUP BY p.id
      ORDER BY total DESC
      LIMIT $PER_PAGE OFFSET $offsetProdutor
    ";
    $st = $pdo->prepare($sqlProdutores);
    exec_stmt($st, $paramsBase);
    $produtoresRows = $st->fetchAll(PDO::FETCH_ASSOC);

    /* ======================
       DETALHES (MODAL)
    ====================== */
    if ($detProdutorId > 0) {

      // info do produtor
      if ($hasComunidades) {
        $st = $pdo->prepare("
          SELECT p.*, c.nome AS comunidade_nome
          FROM produtores p
          LEFT JOIN comunidades c ON c.id = p.comunidade_id
          WHERE p.id = :id AND p.feira_id = :f
          LIMIT 1
        ");
      } else {
        $st = $pdo->prepare("
          SELECT p.*
          FROM produtores p
          WHERE p.id = :id AND p.feira_id = :f
          LIMIT 1
        ");
      }
      exec_stmt($st, [':id' => $detProdutorId, ':f' => $feiraId]);
      $detProdutorInfo = $st->fetch(PDO::FETCH_ASSOC);

      if ($detProdutorInfo) {
        $paramsDet = $paramsBase;
        $paramsDet[':p'] = $detProdutorId;

        $whereDet = "
          v.feira_id = :f
          AND vi.feira_id = :f
          AND pr.feira_id = :f
          AND p.feira_id  = :f
          AND p.id = :p
          AND $whereData
        ";

        // resumo do produtor
        $st = $pdo->prepare("
          SELECT
            COUNT(DISTINCT v.id) AS vendas_qtd,
            COALESCE(SUM(vi.subtotal),0) AS total,
            COALESCE(SUM(vi.quantidade),0) AS itens_qtd,
            COUNT(DISTINCT pr.id) AS produtos_distintos
          FROM vendas v
          JOIN venda_itens vi ON vi.venda_id = v.id
          JOIN produtos pr ON pr.id = vi.produto_id
          JOIN produtores p ON p.id = pr.produtor_id
          WHERE $whereDet
        ");
        exec_stmt($st, $paramsDet);
        $r = $st->fetch(PDO::FETCH_ASSOC) ?: [];
        $detResumo['vendas_qtd'] = (int)($r['vendas_qtd'] ?? 0);
        $detResumo['total'] = (float)($r['total'] ?? 0);
        $detResumo['itens_qtd'] = (float)($r['itens_qtd'] ?? 0);
        $detResumo['produtos_distintos'] = (int)($r['produtos_distintos'] ?? 0);
        $detResumo['ticket'] = $detResumo['vendas_qtd'] > 0 ? ($detResumo['total'] / $detResumo['vendas_qtd']) : 0;

        // por pagamento
        if ($colFormaPgto) {
          $st = $pdo->prepare("
            SELECT
              UPPER(COALESCE(v.forma_pagamento,'N/I')) AS pagamento,
              COUNT(DISTINCT v.id) AS vendas_qtd,
              COALESCE(SUM(vi.subtotal),0) AS total
            FROM vendas v
            JOIN venda_itens vi ON vi.venda_id = v.id
            JOIN produtos pr ON pr.id = vi.produto_id
            JOIN produtores p ON p.id = pr.produtor_id
            WHERE $whereDet
            GROUP BY pagamento
            ORDER BY total DESC
          ");
          exec_stmt($st, $paramsDet);
          $detPorPagamento = $st->fetchAll(PDO::FETCH_ASSOC);
        }

        // produtos (top 20)
        $selectUn = $hasUnidades ? "u.sigla AS unidade_sigla" : "NULL AS unidade_sigla";
        $joinUn   = $hasUnidades ? "LEFT JOIN unidades u ON u.id = pr.unidade_id" : "";
        $selectCat = $hasCategorias ? "c.nome AS categoria_nome" : "NULL AS categoria_nome";
        $joinCat   = $hasCategorias ? "LEFT JOIN categorias c ON c.id = pr.categoria_id" : "";

        $st = $pdo->prepare("
          SELECT
            pr.nome,
            $selectCat,
            $selectUn,
            COALESCE(SUM(vi.quantidade),0) AS quantidade,
            COALESCE(SUM(vi.subtotal),0) AS total
          FROM vendas v
          JOIN venda_itens vi ON vi.venda_id = v.id
          JOIN produtos pr ON pr.id = vi.produto_id
          JOIN produtores p ON p.id = pr.produtor_id
          $joinCat
          $joinUn
          WHERE $whereDet
          GROUP BY pr.id
          ORDER BY total DESC
          LIMIT 20
        ");
        exec_stmt($st, $paramsDet);
        $detProdutos = $st->fetchAll(PDO::FETCH_ASSOC);

        // vendas (últimas 20)
        $selectPagamento = $colFormaPgto ? "v.forma_pagamento" : "NULL AS forma_pagamento";
        $selectStatus    = $colStatus    ? "v.status" : "'N/I' AS status";

        $st = $pdo->prepare("
          SELECT
            v.id,
            $dateField AS data_hora_ref,
            $selectPagamento,
            $selectStatus,
            COALESCE(SUM(vi.quantidade),0) AS itens_qtd,
            COALESCE(SUM(vi.subtotal),0) AS total_produtor
          FROM vendas v
          JOIN venda_itens vi ON vi.venda_id = v.id
          JOIN produtos pr ON pr.id = vi.produto_id
          JOIN produtores p ON p.id = pr.produtor_id
          WHERE $whereDet
          GROUP BY v.id
          ORDER BY v.id DESC
          LIMIT 20
        ");
        exec_stmt($st, $paramsDet);
        $detVendas = $st->fetchAll(PDO::FETCH_ASSOC);

      } else {
        $detProdutorId = 0;
      }
    }
  }
} catch (Throwable $e) {
  $err = 'Erro ao carregar relatório: ' . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <title>SIGRelatórios — Relatório Individual dos Produtores</title>

  <link rel="stylesheet" href="../../../vendors/feather/feather.css">
  <link rel="stylesheet" href="../../../vendors/ti-icons/css/themify-icons.css">
  <link rel="stylesheet" href="../../../vendors/css/vendor.bundle.base.css">
  <link rel="stylesheet" href="../../../vendors/datatables.net-bs4/dataTables.bootstrap4.css">
  <link rel="stylesheet" type="text/css" href="../../../js/select.dataTables.min.css">
  <link rel="stylesheet" href="../../../css/vertical-layout-light/style.css">
  <link rel="shortcut icon" href="../../../images/3.png" />

  <style>
    ul .nav-link:hover { color: blue !important; }
    .nav-link { color: black !important; }
    .sidebar .sub-menu .nav-item .nav-link { margin-left: -35px !important; }
    .sidebar .sub-menu li { list-style: none !important; }

    .form-control { height: 42px; }
    .btn { height: 42px; }

    .mini-kpi { font-size: 12px; color: #6c757d; }

    .kpi-card {
      border: 1px solid rgba(0,0,0,.08);
      border-radius: 14px;
      padding: 14px;
      background: #fff;
      height: 100%;
    }
    .kpi-label { font-size: 12px; color: #6c757d; margin: 0; }
    .kpi-value { font-size: 22px; font-weight: 800; margin: 0; }
    .kpi-sub { font-size: 12px; color: #6c757d; margin: 6px 0 0; }

    .table td, .table th { vertical-align: middle !important; }

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
      box-shadow: 0 10px 28px rgba(0,0,0,.10) !important;
      font-size: 13px !important;
      margin-bottom: 10px !important;
      opacity: 0;
      transform: translateX(10px);
      animation: sigToastIn .22s ease-out forwards, sigToastOut .25s ease-in forwards 5.75s;
    }
    .sig-toast--success { background: #f1fff6 !important; border-left-color: #22c55e !important; }
    .sig-toast--danger { background: #fff1f2 !important; border-left-color: #ef4444 !important; }
    .sig-toast__row { display:flex; align-items:flex-start; gap:10px; }
    .sig-toast__icon i { font-size: 16px; margin-top: 2px; }
    .sig-toast__title { font-weight: 800; margin-bottom: 1px; line-height: 1.1; }
    .sig-toast__text { margin: 0; line-height: 1.25; }
    .sig-toast .close { opacity: .55; font-size: 18px; line-height: 1; padding: 0 6px; }
    .sig-toast .close:hover { opacity: 1; }
    @keyframes sigToastIn { to { opacity: 1; transform: translateX(0); } }
    @keyframes sigToastOut { to { opacity: 0; transform: translateX(12px); visibility: hidden; } }

    .btn-group-toggle .btn { border-radius: 8px; margin-right: 8px; }
    .btn-group-toggle .btn.active {
      background: #231475 !important;
      color: white !important;
      border-color: #231475 !important;
    }

    .pagination .page-link { border-radius: 10px; margin: 0 3px; }

    .modal .nav-pills .nav-link.active {
      background: #231475 !important;
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

      <!-- SIDEBAR (o mesmo que você mandou) -->
      <nav class="sidebar sidebar-offcanvas" id="sidebar">
        <ul class="nav">

          <li class="nav-item">
            <a class="nav-link" href="index.php">
              <i class="icon-grid menu-icon"></i>
              <span class="menu-title">Dashboard</span>
            </a>
          </li>

          <li class="nav-item ">
            <a class="nav-link " data-toggle="collapse" href="#feiraCadastros">
              <i class="ti-id-badge menu-icon"></i>
              <span class="menu-title">Cadastros</span>
              <i class="menu-arrow"></i>
            </a>

            <div class="collapse " id="feiraCadastros">
              <ul class="nav flex-column sub-menu" style="background: white !important;">
                <li class="nav-item"><a class="nav-link" href="./listaProduto.php"><i class="ti-clipboard mr-2"></i> Lista de Produtos</a></li>
                <li class="nav-item"><a class="nav-link" href="./listaCategoria.php"><i class="ti-layers mr-2"></i> Categorias</a></li>
                <li class="nav-item"><a class="nav-link" href="./listaUnidade.php"><i class="ti-ruler-pencil mr-2"></i> Unidades</a></li>
                <li class="nav-item"><a class="nav-link" href="./listaProdutor.php"><i class="ti-user mr-2"></i> Produtores</a></li>
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
                <li class="nav-item"><a class="nav-link" href="./lancamentos.php"><i class="ti-write mr-2"></i> Lançamentos (Vendas)</a></li>
                <li class="nav-item"><a class="nav-link" href="./fechamentoDia.php"><i class="ti-check-box mr-2"></i> Fechamento do Dia</a></li>
              </ul>
            </div>
          </li>

          <li class="nav-item">
            <a class="nav-link" data-toggle="collapse" href="#feiraRelatorios" aria-expanded="false" aria-controls="feiraRelatorios">
              <i class="ti-clipboard menu-icon"></i>
              <span class="menu-title">Relatórios</span>
              <i class="menu-arrow"></i>
            </a>

            <div class="collapse text-black show" id="feiraRelatorios">
              <ul class="nav flex-column sub-menu" style="background:#fff !important;">
                <style>
                  .sub-menu .nav-item .nav-link { color: black !important; }
                  .sub-menu .nav-item .nav-link:hover { color: blue !important; }
                </style>
                <li class="nav-item active">
                  <a class="nav-link active" href="./relatorioFinanceiro.php" style="color:white !important; background: #231475C5 !important;">
                    <i class="ti-user mr-2"></i> Relatório Individual dos Produtores
                  </a>
                </li>
                <li class="nav-item"><a class="nav-link" href="./relatorioProdutos.php"><i class="ti-list mr-2"></i> Produtos Comercializados</a></li>
                <li class="nav-item"><a class="nav-link" href="./relatorioMensal.php"><i class="ti-calendar mr-2"></i> Resumo Mensal</a></li>
                <li class="nav-item"><a class="nav-link" href="./configRelatorio.php"><i class="ti-settings mr-2"></i> Configurar</a></li>
              </ul>
            </div>
          </li>

          <li class="nav-item" style="pointer-events:none;">
            <span style="display:block;padding: 5px 15px 5px;font-size: 11px;font-weight: 600;letter-spacing: 1px;color: #6c757d;text-transform: uppercase;">
              Links Diversos
            </span>
          </li>

          <li class="nav-item"><a class="nav-link" href="../index.php"><i class="ti-home menu-icon"></i><span class="menu-title"> Painel Principal</span></a></li>
          <li class="nav-item"><a href="../alternativa/" class="nav-link"><i class="ti-shopping-cart menu-icon"></i><span class="menu-title">Feira do Alternativa</span></a></li>
          <li class="nav-item"><a href="../mercado/" class="nav-link"><i class="ti-shopping-cart menu-icon"></i><span class="menu-title">Mercado Municipal</span></a></li>
          <li class="nav-item"><a class="nav-link" href="https://wa.me/92991515710" target="_blank"><i class="ti-headphone-alt menu-icon"></i><span class="menu-title">Suporte</span></a></li>

        </ul>
      </nav>

      <!-- MAIN -->
      <div class="main-panel">
        <div class="content-wrapper">

          <!-- CABEÇALHO -->
          <div class="row mb-4">
            <div class="col-12">
              <div class="d-flex flex-wrap align-items-center justify-content-between">
                <div>
                  <h2 class="font-weight-bold mb-1">Relatório Individual dos Produtores</h2>
                  <span class="badge badge-primary">Feira do Produtor — <?= h($labelPeriodo) ?></span>
                </div>
              </div>
              <hr>
            </div>
          </div>

          <!-- FILTRO -->
          <div class="card mb-4">
            <div class="card-body py-3">

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
                <div class="col-md-6 mb-2">
                  <label class="mb-1" id="label-data"><?= $tipoFiltro === 'dia' ? 'Data' : 'Mês' ?></label>
                  <input
                    type="<?= $tipoFiltro === 'dia' ? 'date' : 'month' ?>"
                    class="form-control"
                    id="input-data"
                    value="<?= h($tipoFiltro === 'dia' ? $periodStart : substr($periodStart, 0, 7)) ?>">
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

              <div class="mt-2 mini-kpi">
                * Total por produtor é calculado pelos itens vendidos: <b>SUM(venda_itens.subtotal)</b> filtrando pelo produtor do produto.
              </div>
            </div>
          </div>

          <?php if (!$err): ?>
            <!-- KPIs -->
            <div class="row mb-4">
              <div class="col-md-3 mb-3">
                <div class="kpi-card">
                  <p class="kpi-label">Produtores com venda</p>
                  <p class="kpi-value"><?= (int)$resumoGeral['produtores_qtd'] ?></p>
                  <p class="kpi-sub">No período</p>
                </div>
              </div>
              <div class="col-md-3 mb-3">
                <div class="kpi-card">
                  <p class="kpi-label">Vendas (qtd)</p>
                  <p class="kpi-value"><?= (int)$resumoGeral['vendas_qtd'] ?></p>
                  <p class="kpi-sub">No período</p>
                </div>
              </div>
              <div class="col-md-3 mb-3">
                <div class="kpi-card">
                  <p class="kpi-label">Total (produtores)</p>
                  <p class="kpi-value"><?= brl((float)$resumoGeral['total_produtores']) ?></p>
                  <p class="kpi-sub">Soma dos itens</p>
                </div>
              </div>
              <div class="col-md-3 mb-3">
                <div class="kpi-card">
                  <p class="kpi-label">Ticket médio</p>
                  <p class="kpi-value"><?= brl((float)$resumoGeral['ticket']) ?></p>
                  <p class="kpi-sub">Total / vendas</p>
                </div>
              </div>
            </div>

            <!-- TABELA PRODUTORES -->
            <div class="card mb-4">
              <div class="card-body">
                <div class="d-flex align-items-center justify-content-between flex-wrap">
                  <h4 class="mb-2 font-weight-bold">Produtores no Período</h4>
                  <span class="mini-kpi">Página <?= (int)$pageProdutor ?> de <?= (int)$totalPagesProdutores ?></span>
                </div>

                <div class="table-responsive mt-3">
                  <table class="table table-striped">
                    <thead>
                      <tr>
                        <th>Produtor</th>
                        <th>Comunidade</th>
                        <th class="text-center">Vendas</th>
                        <th class="text-right">Itens (qtd)</th>
                        <th class="text-right">Total</th>
                        <th class="text-right">Ticket</th>
                        <th class="text-right">Ação</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php if (empty($produtoresRows)): ?>
                        <tr>
                          <td colspan="7" class="text-center text-muted">Nenhum produtor com vendas no período selecionado.</td>
                        </tr>
                      <?php else: ?>
                        <?php foreach ($produtoresRows as $p): ?>
                          <?php
                            $total = (float)$p['total'];
                            $vqs = (int)$p['vendas_qtd'];
                            $ticket = $vqs > 0 ? ($total / $vqs) : 0;
                          ?>
                          <tr>
                            <td class="font-weight-bold"><?= h($p['nome']) ?></td>
                            <td><?= h((string)($p['comunidade_nome'] ?? '—') ?: '—') ?></td>
                            <td class="text-center"><?= (int)$p['vendas_qtd'] ?></td>
                            <td class="text-right"><?= number_format((float)$p['itens_qtd'], 3, ',', '.') ?></td>
                            <td class="text-right font-weight-bold"><?= brl($total) ?></td>
                            <td class="text-right"><?= brl((float)$ticket) ?></td>
                            <td class="text-right">
                              <a class="btn btn-sm btn-outline-primary"
                                 href="<?= h(url_with(['det_produtor_id' => (int)$p['id']])) ?>">
                                <i class="ti-eye mr-1"></i> Detalhes
                              </a>
                            </td>
                          </tr>
                        <?php endforeach; ?>
                      <?php endif; ?>
                    </tbody>
                  </table>
                </div>

                <?php if ($totalPagesProdutores > 1): ?>
                  <nav aria-label="Paginação produtores">
                    <ul class="pagination mb-0">
                      <?php $prev = max(1, $pageProdutor - 1); $next = min($totalPagesProdutores, $pageProdutor + 1); ?>
                      <li class="page-item <?= $pageProdutor <= 1 ? 'disabled' : '' ?>">
                        <a class="page-link" href="<?= h(url_with(['page_produtor' => $prev, 'det_produtor_id' => null])) ?>">&laquo;</a>
                      </li>
                      <?php
                        $start = max(1, $pageProdutor - 2);
                        $end = min($totalPagesProdutores, $pageProdutor + 2);
                        for ($i=$start; $i <= $end; $i++):
                      ?>
                        <li class="page-item <?= $i === $pageProdutor ? 'active' : '' ?>">
                          <a class="page-link" href="<?= h(url_with(['page_produtor' => $i, 'det_produtor_id' => null])) ?>"><?= $i ?></a>
                        </li>
                      <?php endfor; ?>
                      <li class="page-item <?= $pageProdutor >= $totalPagesProdutores ? 'disabled' : '' ?>">
                        <a class="page-link" href="<?= h(url_with(['page_produtor' => $next, 'det_produtor_id' => null])) ?>">&raquo;</a>
                      </li>
                    </ul>
                  </nav>
                <?php endif; ?>

              </div>
            </div>
          <?php endif; ?>

        </div>

        <!-- FOOTER -->
        <footer class="footer">
          <span class="text-muted">
            © <?= date('Y') ?> SIGRelatórios —
            <a href="https://www.lucascorrea.pro/" target="_blank">lucascorrea.pro</a>
          </span>
        </footer>

      </div>
    </div>
  </div>

  <!-- MODAL DETALHES PRODUTOR -->
  <?php if ($detProdutorId > 0 && $detProdutorInfo): ?>
    <div class="modal fade" id="modalDetalheProdutor" tabindex="-1" role="dialog" aria-labelledby="modalDetalheProdutorLabel" aria-hidden="true">
      <div class="modal-dialog modal-lg modal-dialog-scrollable" role="document">
        <div class="modal-content" style="border-radius:16px;">
          <div class="modal-header">
            <div>
              <h5 class="modal-title font-weight-bold" id="modalDetalheProdutorLabel">
                Detalhes do Produtor — <?= h((string)$detProdutorInfo['nome']) ?>
              </h5>
              <div class="mini-kpi">
                Período: <?= h($labelPeriodo) ?>
                <?php if (!empty($detProdutorInfo['comunidade_nome'])): ?>
                  • Comunidade: <?= h((string)$detProdutorInfo['comunidade_nome']) ?>
                <?php endif; ?>
              </div>
            </div>

            <a class="btn btn-sm btn-outline-secondary"
               href="<?= h(url_with(['det_produtor_id' => null])) ?>">
              <i class="ti-close mr-1"></i> Fechar
            </a>
          </div>

          <div class="modal-body">
            <!-- Resumo -->
            <div class="row">
              <div class="col-md-3 mb-3">
                <div class="kpi-card">
                  <p class="kpi-label">Total</p>
                  <p class="kpi-value"><?= brl((float)$detResumo['total']) ?></p>
                  <p class="kpi-sub">Itens do produtor</p>
                </div>
              </div>
              <div class="col-md-3 mb-3">
                <div class="kpi-card">
                  <p class="kpi-label">Vendas</p>
                  <p class="kpi-value"><?= (int)$detResumo['vendas_qtd'] ?></p>
                  <p class="kpi-sub">Ticket: <?= brl((float)$detResumo['ticket']) ?></p>
                </div>
              </div>
              <div class="col-md-3 mb-3">
                <div class="kpi-card">
                  <p class="kpi-label">Itens (qtd)</p>
                  <p class="kpi-value"><?= number_format((float)$detResumo['itens_qtd'], 3, ',', '.') ?></p>
                  <p class="kpi-sub">Somatório</p>
                </div>
              </div>
              <div class="col-md-3 mb-3">
                <div class="kpi-card">
                  <p class="kpi-label">Produtos distintos</p>
                  <p class="kpi-value"><?= (int)$detResumo['produtos_distintos'] ?></p>
                  <p class="kpi-sub">No período</p>
                </div>
              </div>
            </div>

            <!-- Tabs -->
            <ul class="nav nav-pills mb-3" id="tabsDetalhe" role="tablist">
              <li class="nav-item">
                <a class="nav-link active" id="tab-produtos" data-toggle="pill" href="#pane-produtos" role="tab">
                  <i class="ti-package mr-1"></i> Produtos
                </a>
              </li>
              <li class="nav-item">
                <a class="nav-link" id="tab-vendas" data-toggle="pill" href="#pane-vendas" role="tab">
                  <i class="ti-receipt mr-1"></i> Vendas
                </a>
              </li>
              <?php if ($colFormaPgto): ?>
                <li class="nav-item">
                  <a class="nav-link" id="tab-pag" data-toggle="pill" href="#pane-pag" role="tab">
                    <i class="ti-money mr-1"></i> Pagamentos
                  </a>
                </li>
              <?php endif; ?>
            </ul>

            <div class="tab-content" id="tabsDetalheContent">
              <!-- PRODUTOS -->
              <div class="tab-pane fade show active" id="pane-produtos" role="tabpanel">
                <div class="table-responsive">
                  <table class="table table-striped">
                    <thead>
                      <tr>
                        <th>Produto</th>
                        <th>Categoria</th>
                        <th class="text-center">Unid.</th>
                        <th class="text-right">Qtd</th>
                        <th class="text-right">Total</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php if (empty($detProdutos)): ?>
                        <tr><td colspan="5" class="text-center text-muted">Sem produtos no período.</td></tr>
                      <?php else: ?>
                        <?php foreach ($detProdutos as $pr): ?>
                          <tr>
                            <td class="font-weight-bold"><?= h((string)$pr['nome']) ?></td>
                            <td><?= h((string)($pr['categoria_nome'] ?? '—') ?: '—') ?></td>
                            <td class="text-center"><?= h((string)($pr['unidade_sigla'] ?? '—') ?: '—') ?></td>
                            <td class="text-right"><?= number_format((float)$pr['quantidade'], 3, ',', '.') ?></td>
                            <td class="text-right font-weight-bold"><?= brl((float)$pr['total']) ?></td>
                          </tr>
                        <?php endforeach; ?>
                      <?php endif; ?>
                    </tbody>
                  </table>
                </div>
              </div>

              <!-- VENDAS -->
              <div class="tab-pane fade" id="pane-vendas" role="tabpanel">
                <div class="table-responsive">
                  <table class="table table-striped">
                    <thead>
                      <tr>
                        <th>#</th>
                        <th>Data/Hora</th>
                        <th class="text-center">Pagamento</th>
                        <th class="text-center">Status</th>
                        <th class="text-right">Itens</th>
                        <th class="text-right">Total</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php if (empty($detVendas)): ?>
                        <tr><td colspan="6" class="text-center text-muted">Sem vendas no período.</td></tr>
                      <?php else: ?>
                        <?php foreach ($detVendas as $v): ?>
                          <?php
                            $dt = $v['data_hora_ref'] ? date('d/m/Y H:i', strtotime((string)$v['data_hora_ref'])) : '—';
                          ?>
                          <tr>
                            <td class="font-weight-bold"><?= (int)$v['id'] ?></td>
                            <td><?= h($dt) ?></td>
                            <td class="text-center">
                              <span class="badge badge-soft"><?= h(mb_strtoupper((string)($v['forma_pagamento'] ?? 'N/I'))) ?></span>
                            </td>
                            <td class="text-center"><?= h((string)($v['status'] ?? 'N/I')) ?></td>
                            <td class="text-right"><?= number_format((float)$v['itens_qtd'], 3, ',', '.') ?></td>
                            <td class="text-right font-weight-bold"><?= brl((float)$v['total_produtor']) ?></td>
                          </tr>
                        <?php endforeach; ?>
                      <?php endif; ?>
                    </tbody>
                  </table>
                </div>
              </div>

              <!-- PAGAMENTOS -->
              <?php if ($colFormaPgto): ?>
                <div class="tab-pane fade" id="pane-pag" role="tabpanel">
                  <div class="table-responsive">
                    <table class="table table-striped">
                      <thead>
                        <tr>
                          <th>Pagamento</th>
                          <th class="text-center">Vendas</th>
                          <th class="text-right">Total</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php if (empty($detPorPagamento)): ?>
                          <tr><td colspan="3" class="text-center text-muted">Sem dados.</td></tr>
                        <?php else: ?>
                          <?php foreach ($detPorPagamento as $pg): ?>
                            <tr>
                              <td><span class="badge badge-soft"><?= h((string)$pg['pagamento']) ?></span></td>
                              <td class="text-center"><?= (int)$pg['vendas_qtd'] ?></td>
                              <td class="text-right font-weight-bold"><?= brl((float)$pg['total']) ?></td>
                            </tr>
                          <?php endforeach; ?>
                        <?php endif; ?>
                      </tbody>
                    </table>
                  </div>
                </div>
              <?php endif; ?>
            </div>

          </div>

          <div class="modal-footer">
            <a class="btn btn-outline-secondary" href="<?= h(url_with(['det_produtor_id' => null])) ?>">
              <i class="ti-close mr-1"></i> Fechar
            </a>
          </div>
        </div>
      </div>
    </div>
  <?php endif; ?>

  <script src="../../../vendors/js/vendor.bundle.base.js"></script>
  <script src="../../../js/off-canvas.js"></script>
  <script src="../../../js/hoverable-collapse.js"></script>
  <script src="../../../js/template.js"></script>
  <script src="../../../js/settings.js"></script>
  <script src="../../../js/todolist.js"></script>

  <script>
    (function () {
      const btn = document.getElementById('btn-filtrar');
      const inputData = document.getElementById('input-data');

      function getTipo() {
        const r = document.querySelector('input[name="tipo_filtro"]:checked');
        return r ? r.value : 'mes';
      }

      btn && btn.addEventListener('click', function () {
        const tipo = getTipo();
        let data = (inputData && inputData.value) ? inputData.value.trim() : '';

        const params = new URLSearchParams(window.location.search);
        params.set('tipo', tipo);
        params.set('data', data);

        // reset pagina e modal ao filtrar
        params.delete('page_produtor');
        params.delete('det_produtor_id');

        window.location.href = window.location.pathname + '?' + params.toString();
      });
    })();
  </script>

  <?php if ($detProdutorId > 0 && $detProdutorInfo): ?>
    <script>
      // abre automaticamente a modal quando veio det_produtor_id na URL
      (function () {
        if (window.jQuery && jQuery.fn && jQuery.fn.modal) {
          jQuery(function () {
            jQuery('#modalDetalheProdutor').modal('show');
          });
        }
      })();
    </script>
  <?php endif; ?>
</body>

</html>