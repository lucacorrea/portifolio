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

function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

/* Flash */
$msg = (string)($_SESSION['flash_ok'] ?? '');
$err = (string)($_SESSION['flash_err'] ?? '');
unset($_SESSION['flash_ok'], $_SESSION['flash_err']);

/* Conexão (padrão do seu sistema) */
require '../../../assets/php/conexao.php';
$pdo = db();

/* ==========================
   Helpers: schema detection
========================== */
function hasTable(PDO $pdo, string $table): bool {
  $st = $pdo->prepare("
    SELECT COUNT(*)
    FROM information_schema.tables
    WHERE table_schema = DATABASE() AND table_name = :t
  ");
  $st->execute([':t' => $table]);
  return ((int)$st->fetchColumn() > 0);
}
function hasColumn(PDO $pdo, string $table, string $column): bool {
  $st = $pdo->prepare("
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE() AND table_name = :t AND column_name = :c
  ");
  $st->execute([':t' => $table, ':c' => $column]);
  return ((int)$st->fetchColumn() > 0);
}

/* ==========================
   Feira / Escopo
   (ajuste se quiser)
========================== */
$local = (string)($_GET['local'] ?? 'produtor'); // produtor | alternativa | mercado | todas
$mapLocalFeira = [
  'produtor'    => 1,
  'alternativa' => 2,
  'mercado'     => 3, // se o Mercado Municipal usar outro id, troque aqui
];
$feiraId = $mapLocalFeira[$local] ?? 1;
$usarFeira = ($local !== 'todas');

/* ==========================
   Detecta campos da tabela vendas
========================== */
if (!hasTable($pdo, 'vendas') || !hasTable($pdo, 'venda_itens')) {
  $err = $err ?: 'Tabelas vendas/venda_itens não existem. Rode o SQL do banco.';
}

$colDataVenda  = hasColumn($pdo, 'vendas', 'data_venda');
$colDataHora   = hasColumn($pdo, 'vendas', 'data_hora');
$colProdutorId = hasColumn($pdo, 'vendas', 'produtor_id');
$colFormaPgto  = hasColumn($pdo, 'vendas', 'forma_pagamento');
$colTotal      = hasColumn($pdo, 'vendas', 'total');
$colStatus     = hasColumn($pdo, 'vendas', 'status');
$colObs        = hasColumn($pdo, 'vendas', 'observacao');

/* Campo de data que vamos usar */
if ($colDataVenda) {
  $dateExpr = "v.data_venda";
} elseif ($colDataHora) {
  $dateExpr = "DATE(v.data_hora)";
} else {
  // fallback: usa criado_em se existir
  $dateExpr = hasColumn($pdo, 'vendas', 'criado_em') ? "DATE(v.criado_em)" : "DATE(NOW())";
}

/* ==========================
   Filtro mensal (select)
========================== */
$mesSel = trim((string)($_GET['mes'] ?? date('Y-m'))); // YYYY-MM
if (!preg_match('/^\d{4}-\d{2}$/', $mesSel)) {
  $mesSel = date('Y-m');
}
$monthStart = $mesSel . '-01';
$monthEnd   = date('Y-m-t', strtotime($monthStart));

/* Lista de meses anteriores */
$meses = [];
$base = new DateTimeImmutable(date('Y-m-01'));
for ($i = 0; $i < 18; $i++) {
  $m = $base->modify("-{$i} months");
  $k = $m->format('Y-m');
  $meses[] = [
    'key' => $k,
    'label' => $m->format('m/Y'),
  ];
}

/* ==========================
   Resumos
========================== */
$resumo = [
  'vendas_qtd' => 0,
  'total' => 0.0,
  'ticket' => 0.0,
];
$porPagamento = [];
$porProdutor  = [];
$porDia       = [];
$vendasRows   = [];

/* Paginação (porDia e vendas) */
$PER_PAGE = 10;
$pageDia = max(1, (int)($_GET['p_dia'] ?? 1));
$pageVen = max(1, (int)($_GET['p_v'] ?? 1));
$offsetDia = ($pageDia - 1) * $PER_PAGE;
$offsetVen = ($pageVen - 1) * $PER_PAGE;
$totalRowsDia = 0;
$totalRowsVen = 0;

try {
  if (!$err) {

    /* WHERE base */
    $where = "WHERE {$dateExpr} BETWEEN :ini AND :fim";
    $params = [
      ':ini' => $monthStart,
      ':fim' => $monthEnd,
    ];
    if ($usarFeira && hasColumn($pdo, 'vendas', 'feira_id')) {
      $where .= " AND v.feira_id = :f";
      $params[':f'] = $feiraId;
    }

    /* -------- Resumo geral do mês -------- */
    if ($colTotal) {
      $st = $pdo->prepare("
        SELECT
          COUNT(*) AS vendas_qtd,
          COALESCE(SUM(v.total),0) AS total
        FROM vendas v
        {$where}
      ");
      $st->execute($params);
      $r = $st->fetch() ?: null;
      if ($r) {
        $resumo['vendas_qtd'] = (int)$r['vendas_qtd'];
        $resumo['total']      = (float)$r['total'];
        $resumo['ticket']     = $resumo['vendas_qtd'] > 0 ? ($resumo['total'] / $resumo['vendas_qtd']) : 0.0;
      }
    }

    /* -------- Por pagamento -------- */
    if ($colFormaPgto && $colTotal) {
      $st = $pdo->prepare("
        SELECT
          UPPER(COALESCE(NULLIF(TRIM(v.forma_pagamento),''),'N/I')) AS pagamento,
          COUNT(*) AS qtd,
          COALESCE(SUM(v.total),0) AS total
        FROM vendas v
        {$where}
        GROUP BY pagamento
        ORDER BY total DESC
      ");
      $st->execute($params);
      $porPagamento = $st->fetchAll();
    }

    /* -------- Por produtor (prioriza vendas.produtor_id; se não houver, tenta via produtos.produtor_id) -------- */
    if ($colTotal) {
      if ($colProdutorId && hasTable($pdo, 'produtores')) {
        $st = $pdo->prepare("
          SELECT
            p.id,
            p.nome,
            COUNT(v.id) AS vendas_qtd,
            COALESCE(SUM(v.total),0) AS total
          FROM vendas v
          JOIN produtores p ON p.id = v.produtor_id
          {$where}
          GROUP BY p.id, p.nome
          ORDER BY total DESC, p.nome ASC
          LIMIT 200
        ");
        $st->execute($params);
        $porProdutor = $st->fetchAll();
      } else {
        // fallback: agrupa por produtores através dos itens -> produtos -> produtor_id
        if (hasTable($pdo, 'produtos') && hasColumn($pdo, 'produtos', 'produtor_id')) {
          $st = $pdo->prepare("
            SELECT
              pr.id,
              pr.nome,
              COUNT(DISTINCT v.id) AS vendas_qtd,
              COALESCE(SUM(vi.subtotal),0) AS total
            FROM vendas v
            JOIN venda_itens vi ON vi.venda_id = v.id
            JOIN produtos pd ON pd.id = vi.produto_id
            JOIN produtores pr ON pr.id = pd.produtor_id
            {$where}
            GROUP BY pr.id, pr.nome
            ORDER BY total DESC, pr.nome ASC
            LIMIT 200
          ");
          $st->execute($params);
          $porProdutor = $st->fetchAll();
        }
      }
    }

    /* -------- Por dia (com paginação) -------- */
    if ($colTotal) {
      // total linhas
      $stC = $pdo->prepare("
        SELECT COUNT(*) FROM (
          SELECT {$dateExpr} AS dia
          FROM vendas v
          {$where}
          GROUP BY dia
        ) x
      ");
      $stC->execute($params);
      $totalRowsDia = (int)$stC->fetchColumn();

      $st = $pdo->prepare("
        SELECT
          {$dateExpr} AS dia,
          COUNT(*) AS vendas_qtd,
          COALESCE(SUM(v.total),0) AS total
        FROM vendas v
        {$where}
        GROUP BY dia
        ORDER BY dia DESC
        LIMIT {$PER_PAGE} OFFSET {$offsetDia}
      ");
      $st->execute($params);
      $porDia = $st->fetchAll();
    }

    /* -------- Vendas do mês (tabela com paginação) -------- */
    if ($colTotal) {
      $stC = $pdo->prepare("SELECT COUNT(*) FROM vendas v {$where}");
      $stC->execute($params);
      $totalRowsVen = (int)$stC->fetchColumn();

      // produtores em linhas diferentes (lista separada por <br>)
      // tenta pegar multi-produtores via itens->produtos->produtor_id
      $canMultiProd =
        hasTable($pdo, 'produtos')
        && hasTable($pdo, 'produtores')
        && hasColumn($pdo, 'produtos', 'produtor_id')
        && hasColumn($pdo, 'venda_itens', 'produto_id');

      if ($canMultiProd) {
        $sql = "
          SELECT
            v.id,
            {$dateExpr} AS data_ref,
            " . ($colFormaPgto ? "v.forma_pagamento" : "NULL") . " AS forma_pagamento,
            " . ($colStatus ? "v.status" : "NULL") . " AS status,
            v.total,
            (
              SELECT GROUP_CONCAT(DISTINCT pr2.nome ORDER BY pr2.nome SEPARATOR '\n')
              FROM venda_itens vi2
              JOIN produtos pd2   ON pd2.id = vi2.produto_id
              JOIN produtores pr2 ON pr2.id = pd2.produtor_id
              WHERE vi2.venda_id = v.id
            ) AS feirantes
          FROM vendas v
          {$where}
          ORDER BY v.id DESC
          LIMIT {$PER_PAGE} OFFSET {$offsetVen}
        ";
        $st = $pdo->prepare($sql);
        $st->execute($params);
        $vendasRows = $st->fetchAll();
      } else {
        // fallback: 1 produtor por venda
        $joinProd = ($colProdutorId && hasTable($pdo, 'produtores')) ? "LEFT JOIN produtores p ON p.id = v.produtor_id" : "";
        $sql = "
          SELECT
            v.id,
            {$dateExpr} AS data_ref,
            " . ($colFormaPgto ? "v.forma_pagamento" : "NULL") . " AS forma_pagamento,
            " . ($colStatus ? "v.status" : "NULL") . " AS status,
            v.total,
            " . ($joinProd ? "p.nome AS feirantes" : "NULL AS feirantes") . "
          FROM vendas v
          {$joinProd}
          {$where}
          ORDER BY v.id DESC
          LIMIT {$PER_PAGE} OFFSET {$offsetVen}
        ";
        $st = $pdo->prepare($sql);
        $st->execute($params);
        $vendasRows = $st->fetchAll();
      }
    }
  }
} catch (Throwable $e) {
  $err = $err ?: 'Não foi possível carregar o relatório financeiro agora.';
}

/* Paginação links */
function pagLinks(string $baseUrl, int $page, int $totalRows, int $perPage, string $pageParam): string {
  $totalPages = (int)ceil(max(1, $totalRows) / $perPage);
  if ($totalPages <= 1) return '';

  $html = '<nav><ul class="pagination pagination-sm mb-0">';
  $prev = max(1, $page - 1);
  $next = min($totalPages, $page + 1);

  $disabledPrev = $page <= 1 ? ' disabled' : '';
  $disabledNext = $page >= $totalPages ? ' disabled' : '';

  $html .= '<li class="page-item'.$disabledPrev.'"><a class="page-link" href="'.$baseUrl.'&'.$pageParam.'='.$prev.'">‹</a></li>';

  $start = max(1, $page - 2);
  $end   = min($totalPages, $page + 2);

  for ($p = $start; $p <= $end; $p++) {
    $active = $p === $page ? ' active' : '';
    $html .= '<li class="page-item'.$active.'"><a class="page-link" href="'.$baseUrl.'&'.$pageParam.'='.$p.'">'.$p.'</a></li>';
  }

  $html .= '<li class="page-item'.$disabledNext.'"><a class="page-link" href="'.$baseUrl.'&'.$pageParam.'='.$next.'">›</a></li>';
  $html .= '</ul></nav>';
  return $html;
}

$nomeUsuario = $_SESSION['usuario_nome'] ?? 'Usuário';
$baseQS = '?local='.urlencode($local).'&mes='.urlencode($mesSel);
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
    ul .nav-link:hover { color: blue !important; }
    .nav-link { color: black !important; }

    .sidebar .sub-menu .nav-item .nav-link { margin-left: -35px !important; }
    .sidebar .sub-menu li { list-style: none !important; }

    .form-control { height: 42px; }
    .btn { height: 42px; }

    .mini-kpi{ font-size: 12px; color: #6c757d; }

    .kpi-card{
      border: 1px solid rgba(0,0,0,.08);
      border-radius: 14px;
      padding: 14px;
      background: #fff;
    }
    .kpi-label{ font-size: 12px; color:#6c757d; margin:0; }
    .kpi-value{ font-size: 22px; font-weight: 800; margin:0; }

    .table td, .table th{ vertical-align: middle !important; }

    .badge-soft {
      background: rgba(0, 0, 0, 0.05);
      border: 1px solid rgba(0, 0, 0, 0.07);
      font-weight: 600;
    }

    /* Flash top-right */
    .sig-flash-wrap{
      position: fixed; top: 78px; right: 18px; width: min(420px, calc(100vw - 36px));
      z-index: 9999; pointer-events: none;
    }
    .sig-toast.alert{
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
    .sig-toast--success{ background:#f1fff6 !important; border-left-color:#22c55e !important; }
    .sig-toast--danger { background:#fff1f2 !important; border-left-color:#ef4444 !important; }
    .sig-toast__row{ display:flex; align-items:flex-start; gap:10px; }
    .sig-toast__icon i{ font-size:16px; margin-top:2px; }
    .sig-toast__title{ font-weight:800; margin-bottom:1px; line-height: 1.1; }
    .sig-toast__text{ margin:0; line-height: 1.25; }
    .sig-toast .close{ opacity:.55; font-size: 18px; line-height: 1; padding: 0 6px; }
    .sig-toast .close:hover{ opacity:1; }
    @keyframes sigToastIn{ to{ opacity:1; transform: translateX(0); } }
    @keyframes sigToastOut{ to{ opacity:0; transform: translateX(12px); visibility:hidden; } }

    .feirantes-cell{ white-space: pre-line; } /* para quebrar \n em linhas */
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
            <a class="nav-link " data-toggle="collapse" href="#feiraCadastros" >
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
                  <a class="nav-link" href="./listaUnidade.php" >
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
            <a href="../alternativa/" class="nav-link">
              <i class="ti-shopping-cart menu-icon"></i>
              <span class="menu-title">Feira Alternativa</span>

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
            <h3 class="font-weight-bold mb-1">Relatório Financeiro</h3>
            <div class="mini-kpi">
              Mês selecionado: <b><?= h($mesSel) ?></b> • Período: <b><?= date('d/m/Y', strtotime($monthStart)) ?></b> até <b><?= date('d/m/Y', strtotime($monthEnd)) ?></b>
            </div>
          </div>
        </div>

        <!-- FILTROS -->
        <div class="row">
          <div class="col-lg-12 grid-margin stretch-card">
            <div class="card">
              <div class="card-body">

                <div class="d-flex align-items-center justify-content-between flex-wrap">
                  <div>
                    <h4 class="card-title mb-0">Filtros</h4>
                    <p class="card-description mb-0">Selecione o mês (meses anteriores) e o local (opcional).</p>
                  </div>
                </div>

                <div class="row mt-3">
                  <div class="col-md-4 mb-2">
                    <label class="mb-1">Mês</label>
                    <select class="form-control" onchange="location.href='?local=<?= h($local) ?>&mes='+this.value;">
                      <?php foreach ($meses as $m): ?>
                        <option value="<?= h($m['key']) ?>" <?= $mesSel === $m['key'] ? 'selected' : '' ?>>
                          <?= h($m['label']) ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                  </div>

                  <div class="col-md-4 mb-2">
                    <label class="mb-1">Local</label>
                    <select class="form-control" onchange="location.href='?local='+this.value+'&mes=<?= h($mesSel) ?>';">
                      <option value="produtor" <?= $local==='produtor' ? 'selected' : '' ?>>Feira do Produtor</option>
                      <option value="alternativa" <?= $local==='alternativa' ? 'selected' : '' ?>>Feira Alternativa</option>
                      <option value="mercado" <?= $local==='mercado' ? 'selected' : '' ?>>Mercado Municipal</option>
                      <option value="todas" <?= $local==='todas' ? 'selected' : '' ?>>Todas (somar tudo)</option>
                    </select>
                    <small class="text-muted mini-kpi">Se o Mercado usar outro feira_id, ajuste no topo do arquivo.</small>
                  </div>

                  <div class="col-md-4 mb-2 d-flex align-items-end">
                    <a class="btn btn-light w-100" href="./relatorioFinanceiro.php">
                      <i class="ti-close mr-1"></i> Voltar para o mês atual
                    </a>
                  </div>
                </div>

              </div>
            </div>
          </div>
        </div>

        <!-- KPIs -->
        <div class="row">
          <div class="col-md-4 mb-3">
            <div class="kpi-card">
              <p class="kpi-label">Total do mês</p>
              <p class="kpi-value">R$ <?= number_format((float)$resumo['total'], 2, ',', '.') ?></p>
              <div class="mini-kpi">Somatório das vendas no período</div>
            </div>
          </div>
          <div class="col-md-4 mb-3">
            <div class="kpi-card">
              <p class="kpi-label">Vendas (lançamentos)</p>
              <p class="kpi-value"><?= (int)$resumo['vendas_qtd'] ?></p>
              <div class="mini-kpi">Quantidade de registros</div>
            </div>
          </div>
          <div class="col-md-4 mb-3">
            <div class="kpi-card">
              <p class="kpi-label">Ticket médio</p>
              <p class="kpi-value">R$ <?= number_format((float)$resumo['ticket'], 2, ',', '.') ?></p>
              <div class="mini-kpi">Total ÷ vendas</div>
            </div>
          </div>
        </div>

        <!-- POR PAGAMENTO + POR PRODUTOR -->
        <div class="row">
          <div class="col-lg-5 grid-margin stretch-card">
            <div class="card">
              <div class="card-body">
                <h4 class="card-title mb-0">Por forma de pagamento</h4>
                <p class="card-description mb-0">Distribuição do período.</p>

                <div class="table-responsive pt-3">
                  <table class="table table-striped table-hover">
                    <thead>
                      <tr>
                        <th>Pagamento</th>
                        <th style="width:110px;">Qtd</th>
                        <th style="width:170px;">Total</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php if (empty($porPagamento)): ?>
                        <tr><td colspan="3" class="text-center text-muted py-4">Sem dados para o período.</td></tr>
                      <?php else: ?>
                        <?php foreach ($porPagamento as $p): ?>
                          <tr>
                            <td><span class="badge badge-soft"><?= h($p['pagamento'] ?? 'N/I') ?></span></td>
                            <td><?= (int)($p['qtd'] ?? 0) ?></td>
                            <td><b>R$ <?= number_format((float)($p['total'] ?? 0), 2, ',', '.') ?></b></td>
                          </tr>
                        <?php endforeach; ?>
                      <?php endif; ?>
                    </tbody>
                  </table>
                </div>

                <?php if (!$colFormaPgto): ?>
                  <div class="mini-kpi mt-2 text-muted">
                    *Sua tabela vendas não tem a coluna <b>forma_pagamento</b>. Se quiser, eu ajusto para você.
                  </div>
                <?php endif; ?>
              </div>
            </div>
          </div>

          <div class="col-lg-7 grid-margin stretch-card">
            <div class="card">
              <div class="card-body">
                <h4 class="card-title mb-0">Resumo por feirante (produtor)</h4>
                <p class="card-description mb-0">Cada feirante em uma linha.</p>

                <div class="table-responsive pt-3">
                  <table class="table table-striped table-hover">
                    <thead>
                      <tr>
                        <th>Feirante</th>
                        <th style="width:120px;">Vendas</th>
                        <th style="width:180px;">Total</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php if (empty($porProdutor)): ?>
                        <tr><td colspan="3" class="text-center text-muted py-4">Sem dados para o período.</td></tr>
                      <?php else: ?>
                        <?php foreach ($porProdutor as $p): ?>
                          <tr>
                            <td><?= h($p['nome'] ?? '') ?></td>
                            <td><?= (int)($p['vendas_qtd'] ?? 0) ?></td>
                            <td><b>R$ <?= number_format((float)($p['total'] ?? 0), 2, ',', '.') ?></b></td>
                          </tr>
                        <?php endforeach; ?>
                      <?php endif; ?>
                    </tbody>
                  </table>
                </div>

              </div>
            </div>
          </div>
        </div>

        <!-- POR DIA (PAGINADO) -->
        <div class="row">
          <div class="col-lg-12 grid-margin stretch-card">
            <div class="card">
              <div class="card-body">
                <div class="d-flex align-items-center justify-content-between flex-wrap">
                  <div>
                    <h4 class="card-title mb-0">Resumo por dia</h4>
                    <p class="card-description mb-0">
                      Se passar de 10 linhas, pagina automaticamente.
                      <?php if ($totalRowsDia > 0): ?>
                        <span class="mini-kpi ml-2">Total de dias no mês: <b><?= (int)$totalRowsDia ?></b></span>
                      <?php endif; ?>
                    </p>
                  </div>
                  <div>
                    <?= pagLinks($baseQS.'&p_v='.$pageVen, $pageDia, $totalRowsDia, $PER_PAGE, 'p_dia') ?>
                  </div>
                </div>

                <div class="table-responsive pt-3">
                  <table class="table table-striped table-hover">
                    <thead>
                      <tr>
                        <th style="width:160px;">Dia</th>
                        <th style="width:140px;">Vendas</th>
                        <th style="width:180px;">Total</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php if (empty($porDia)): ?>
                        <tr><td colspan="3" class="text-center text-muted py-4">Sem dados para o período.</td></tr>
                      <?php else: ?>
                        <?php foreach ($porDia as $d): ?>
                          <tr>
                            <td><?= h(date('d/m/Y', strtotime((string)$d['dia']))) ?></td>
                            <td><?= (int)($d['vendas_qtd'] ?? 0) ?></td>
                            <td><b>R$ <?= number_format((float)($d['total'] ?? 0), 2, ',', '.') ?></b></td>
                          </tr>
                        <?php endforeach; ?>
                      <?php endif; ?>
                    </tbody>
                  </table>
                </div>

                <div class="d-flex justify-content-end mt-2">
                  <?= pagLinks($baseQS.'&p_v='.$pageVen, $pageDia, $totalRowsDia, $PER_PAGE, 'p_dia') ?>
                </div>

              </div>
            </div>
          </div>
        </div>

        <!-- VENDAS (PAGINADO) -->
        <div class="row">
          <div class="col-lg-12 grid-margin stretch-card">
            <div class="card">
              <div class="card-body">
                <div class="d-flex align-items-center justify-content-between flex-wrap">
                  <div>
                    <h4 class="card-title mb-0">Vendas do período</h4>
                    <p class="card-description mb-0">
                      Mostrando <b><?= (int)count($vendasRows) ?></b> de <b><?= (int)$totalRowsVen ?></b> (paginação após 10).
                    </p>
                  </div>
                  <div>
                    <?= pagLinks($baseQS.'&p_dia='.$pageDia, $pageVen, $totalRowsVen, $PER_PAGE, 'p_v') ?>
                  </div>
                </div>

                <div class="table-responsive pt-3">
                  <table class="table table-striped table-hover">
                    <thead>
                      <tr>
                        <th style="width:90px;">ID</th>
                        <th style="width:140px;">Data</th>
                        <th>Feirante(s)</th>
                        <th style="width:160px;">Pagamento</th>
                        <th style="width:140px;">Status</th>
                        <th style="width:160px;">Total</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php if (empty($vendasRows)): ?>
                        <tr><td colspan="6" class="text-center text-muted py-4">Nenhuma venda no período.</td></tr>
                      <?php else: ?>
                        <?php foreach ($vendasRows as $v): ?>
                          <?php
                            $id = (int)($v['id'] ?? 0);
                            $dataRef = (string)($v['data_ref'] ?? '');
                            $pg = (string)($v['forma_pagamento'] ?? '');
                            $stt = (string)($v['status'] ?? '');
                            $tot = (float)($v['total'] ?? 0);
                            $feirantes = (string)($v['feirantes'] ?? '');
                            if ($feirantes === '') $feirantes = '—';
                          ?>
                          <tr>
                            <td><?= $id ?></td>
                            <td><?= $dataRef ? h(date('d/m/Y', strtotime($dataRef))) : '—' ?></td>
                            <td class="feirantes-cell"><?= h($feirantes) ?></td>
                            <td><?= $pg !== '' ? '<span class="badge badge-soft">'.h($pg).'</span>' : '—' ?></td>
                            <td><?= $stt !== '' ? h($stt) : '—' ?></td>
                            <td><b>R$ <?= number_format($tot, 2, ',', '.') ?></b></td>
                          </tr>
                        <?php endforeach; ?>
                      <?php endif; ?>
                    </tbody>
                  </table>
                </div>

                <div class="d-flex justify-content-end mt-2">
                  <?= pagLinks($baseQS.'&p_dia='.$pageDia, $pageVen, $totalRowsVen, $PER_PAGE, 'p_v') ?>
                </div>

                <div class="mini-kpi mt-2">
                  *Feirantes em linhas diferentes: se a venda tiver itens de mais de um produtor, eles aparecem separados por linha.
                </div>

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

<script src="../../../vendors/js/vendor.bundle.base.js"></script>
<script src="../../../js/off-canvas.js"></script>
<script src="../../../js/hoverable-collapse.js"></script>
<script src="../../../js/template.js"></script>
<script src="../../../js/settings.js"></script>
<script src="../../../js/todolist.js"></script>
</body>
</html>
