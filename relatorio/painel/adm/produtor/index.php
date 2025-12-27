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

function h($s): string {
  return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

/* ===== Conexão (padrão do seu sistema: db(): PDO) ===== */
require '../../../assets/php/conexao.php';
$pdo = db();

/* Feira do Produtor = 1 (na Feira Alternativa use 2) */
$feiraId = 1;

/* Datas base */
$today      = date('Y-m-d');
$yesterday  = date('Y-m-d', strtotime('-1 day'));
$monthStart = date('Y-m-01');
$monthEnd   = date('Y-m-t');

/* Detecta tabela fechamento_dia (do seu DB) */
$hasFechamentoDia = false;
try {
  $st = $pdo->prepare("
    SELECT COUNT(*)
    FROM information_schema.tables
    WHERE table_schema = DATABASE() AND table_name = 'fechamento_dia'
  ");
  $st->execute();
  $hasFechamentoDia = ((int)$st->fetchColumn() > 0);
} catch (Throwable $e) {
  $hasFechamentoDia = false;
}

/* Helper: dinheiro */
function money($n): string {
  return number_format((float)$n, 2, ',', '.');
}

/* ===== KPIs ===== */
$kpi = [
  'vendas_hoje_total' => 0.0,
  'vendas_hoje_qtd'   => 0,
  'itens_hoje_qtd'    => 0.0,
  'ticket_hoje'       => 0.0,

  'mes_total'         => 0.0,
  'mes_vendas_qtd'    => 0,
  'mes_ticket'        => 0.0,

  'produtores_ativos' => 0,
  'produtos_ativos'   => 0,

  'canceladas_hoje'   => 0,
  'sem_pagto_mes'     => 0,
  'preco_ref_zero'    => 0,

  'fechamento_pendente_ontem' => 0, // 1/0
];

/* Pagamento HOJE */
$payHoje = [
  'PIX'      => 0.0,
  'DINHEIRO' => 0.0,
  'CARTAO'   => 0.0,
  'OUTROS'   => 0.0,
];

try {
  /* Vendas Hoje (total + qtd) */
  $st = $pdo->prepare("
    SELECT
      COUNT(*) AS qtd,
      COALESCE(SUM(total),0) AS total
    FROM vendas
    WHERE feira_id = :f
      AND DATE(data_hora) = :d
      AND UPPER(status) <> 'CANCELADA'
  ");
  $st->execute([':f'=>$feiraId, ':d'=>$today]);
  $r = $st->fetch() ?: ['qtd'=>0,'total'=>0];
  $kpi['vendas_hoje_qtd']   = (int)$r['qtd'];
  $kpi['vendas_hoje_total'] = (float)$r['total'];
  $kpi['ticket_hoje']       = $kpi['vendas_hoje_qtd'] > 0 ? ($kpi['vendas_hoje_total'] / $kpi['vendas_hoje_qtd']) : 0.0;

  /* Itens vendidos HOJE (somatório de quantidade em venda_itens) */
  $st = $pdo->prepare("
    SELECT COALESCE(SUM(vi.quantidade),0) AS itens
    FROM venda_itens vi
    JOIN vendas v
      ON v.feira_id = vi.feira_id AND v.id = vi.venda_id
    WHERE vi.feira_id = :f
      AND DATE(v.data_hora) = :d
      AND UPPER(v.status) <> 'CANCELADA'
  ");
  $st->execute([':f'=>$feiraId, ':d'=>$today]);
  $kpi['itens_hoje_qtd'] = (float)($st->fetchColumn() ?? 0);

  /* Canceladas HOJE */
  $st = $pdo->prepare("
    SELECT COUNT(*)
    FROM vendas
    WHERE feira_id = :f
      AND DATE(data_hora) = :d
      AND UPPER(status) = 'CANCELADA'
  ");
  $st->execute([':f'=>$feiraId, ':d'=>$today]);
  $kpi['canceladas_hoje'] = (int)($st->fetchColumn() ?? 0);

  /* Total do mês */
  $st = $pdo->prepare("
    SELECT
      COUNT(*) AS qtd,
      COALESCE(SUM(total),0) AS total
    FROM vendas
    WHERE feira_id = :f
      AND DATE(data_hora) BETWEEN :i AND :e
      AND UPPER(status) <> 'CANCELADA'
  ");
  $st->execute([':f'=>$feiraId, ':i'=>$monthStart, ':e'=>$monthEnd]);
  $r = $st->fetch() ?: ['qtd'=>0,'total'=>0];
  $kpi['mes_vendas_qtd'] = (int)$r['qtd'];
  $kpi['mes_total']      = (float)$r['total'];
  $kpi['mes_ticket']     = $kpi['mes_vendas_qtd'] > 0 ? ($kpi['mes_total'] / $kpi['mes_vendas_qtd']) : 0.0;

  /* Produtores ativos */
  $st = $pdo->prepare("SELECT COUNT(*) FROM produtores WHERE feira_id = :f AND ativo = 1");
  $st->execute([':f'=>$feiraId]);
  $kpi['produtores_ativos'] = (int)($st->fetchColumn() ?? 0);

  /* Produtos ativos */
  $st = $pdo->prepare("SELECT COUNT(*) FROM produtos WHERE feira_id = :f AND ativo = 1");
  $st->execute([':f'=>$feiraId]);
  $kpi['produtos_ativos'] = (int)($st->fetchColumn() ?? 0);

  /* Vendas sem forma_pagamento no mês */
  $st = $pdo->prepare("
    SELECT COUNT(*)
    FROM vendas
    WHERE feira_id = :f
      AND DATE(data_hora) BETWEEN :i AND :e
      AND UPPER(status) <> 'CANCELADA'
      AND (forma_pagamento IS NULL OR TRIM(forma_pagamento) = '')
  ");
  $st->execute([':f'=>$feiraId, ':i'=>$monthStart, ':e'=>$monthEnd]);
  $kpi['sem_pagto_mes'] = (int)($st->fetchColumn() ?? 0);

  /* Produtos com preço referência zerado/vazio (no seu DB existe preco_referencia) */
  $st = $pdo->prepare("
    SELECT COUNT(*)
    FROM produtos
    WHERE feira_id = :f
      AND ativo = 1
      AND (preco_referencia IS NULL OR preco_referencia <= 0)
  ");
  $st->execute([':f'=>$feiraId]);
  $kpi['preco_ref_zero'] = (int)($st->fetchColumn() ?? 0);

  /* Breakdown pagamento HOJE */
  $st = $pdo->prepare("
    SELECT
      UPPER(COALESCE(NULLIF(TRIM(forma_pagamento),''),'OUTROS')) AS fp,
      COALESCE(SUM(total),0) AS total
    FROM vendas
    WHERE feira_id = :f
      AND DATE(data_hora) = :d
      AND UPPER(status) <> 'CANCELADA'
    GROUP BY fp
  ");
  $st->execute([':f'=>$feiraId, ':d'=>$today]);
  foreach ($st->fetchAll() as $row) {
    $fp = (string)($row['fp'] ?? 'OUTROS');
    $val = (float)($row['total'] ?? 0);
    if ($fp === 'PIX') $payHoje['PIX'] += $val;
    elseif ($fp === 'DINHEIRO') $payHoje['DINHEIRO'] += $val;
    elseif ($fp === 'CARTAO' || $fp === 'CARTÃO') $payHoje['CARTAO'] += $val;
    else $payHoje['OUTROS'] += $val;
  }

  /* Fechamento pendente ontem? (só se tabela existir) */
  if ($hasFechamentoDia) {
    // Se ontem teve vendas e não existe registro em fechamento_dia, marca pendente
    $st = $pdo->prepare("
      SELECT COUNT(*)
      FROM vendas
      WHERE feira_id = :f
        AND DATE(data_hora) = :d
        AND UPPER(status) <> 'CANCELADA'
    ");
    $st->execute([':f'=>$feiraId, ':d'=>$yesterday]);
    $ontemVendas = (int)($st->fetchColumn() ?? 0);

    $st = $pdo->prepare("SELECT COUNT(*) FROM fechamento_dia WHERE feira_id = :f AND data_ref = :d");
    $st->execute([':f'=>$feiraId, ':d'=>$yesterday]);
    $ontemFechado = (int)($st->fetchColumn() ?? 0);

    $kpi['fechamento_pendente_ontem'] = ($ontemVendas > 0 && $ontemFechado <= 0) ? 1 : 0;
  }

} catch (Throwable $e) {
  // Se der erro, o layout abre com zeros (não quebra a tela)
}

/* Percentuais pagamento HOJE */
$totalPayHoje = $payHoje['PIX'] + $payHoje['DINHEIRO'] + $payHoje['CARTAO'] + $payHoje['OUTROS'];
$payPct = [
  'PIX'      => $totalPayHoje > 0 ? (int)round(($payHoje['PIX'] / $totalPayHoje) * 100) : 0,
  'DINHEIRO' => $totalPayHoje > 0 ? (int)round(($payHoje['DINHEIRO'] / $totalPayHoje) * 100) : 0,
  'CARTAO'   => $totalPayHoje > 0 ? (int)round(($payHoje['CARTAO'] / $totalPayHoje) * 100) : 0,
  'OUTROS'   => $totalPayHoje > 0 ? (int)round(($payHoje['OUTROS'] / $totalPayHoje) * 100) : 0,
];

/* ===== Top categorias (MÊS) ===== */
$topCategorias = [];
try {
  $st = $pdo->prepare("
    SELECT
      c.nome AS categoria,
      COALESCE(SUM(vi.quantidade),0) AS itens,
      COALESCE(SUM(vi.subtotal),0)   AS total
    FROM venda_itens vi
    JOIN vendas v
      ON v.feira_id = vi.feira_id AND v.id = vi.venda_id
    JOIN produtos p
      ON p.feira_id = vi.feira_id AND p.id = vi.produto_id
    LEFT JOIN categorias c
      ON c.feira_id = p.feira_id AND c.id = p.categoria_id
    WHERE vi.feira_id = :f
      AND DATE(v.data_hora) BETWEEN :i AND :e
      AND UPPER(v.status) <> 'CANCELADA'
    GROUP BY c.nome
    ORDER BY total DESC
    LIMIT 5
  ");
  $st->execute([':f'=>$feiraId, ':i'=>$monthStart, ':e'=>$monthEnd]);
  $topCategorias = $st->fetchAll();
} catch (Throwable $e) {
  $topCategorias = [];
}

/* ===== Top produtos (MÊS) ===== */
$topProdutos = [];
try {
  $st = $pdo->prepare("
    SELECT
      p.nome AS produto,
      COALESCE(SUM(vi.quantidade),0) AS itens,
      COALESCE(SUM(vi.subtotal),0)   AS total
    FROM venda_itens vi
    JOIN vendas v
      ON v.feira_id = vi.feira_id AND v.id = vi.venda_id
    JOIN produtos p
      ON p.feira_id = vi.feira_id AND p.id = vi.produto_id
    WHERE vi.feira_id = :f
      AND DATE(v.data_hora) BETWEEN :i AND :e
      AND UPPER(v.status) <> 'CANCELADA'
    GROUP BY p.id, p.nome
    ORDER BY total DESC
    LIMIT 7
  ");
  $st->execute([':f'=>$feiraId, ':i'=>$monthStart, ':e'=>$monthEnd]);
  $topProdutos = $st->fetchAll();
} catch (Throwable $e) {
  $topProdutos = [];
}

/* ===== Últimos lançamentos (vendas) com feirantes em linhas separadas ===== */
$ultimosLanc = [];
try {
  $st = $pdo->prepare("
    SELECT
      v.id,
      v.data_hora,
      v.total,
      GROUP_CONCAT(DISTINCT pr.nome ORDER BY pr.nome SEPARATOR '||') AS feirantes
    FROM vendas v
    LEFT JOIN venda_itens vi
      ON vi.feira_id = v.feira_id AND vi.venda_id = v.id
    LEFT JOIN produtos p
      ON p.feira_id = vi.feira_id AND p.id = vi.produto_id
    LEFT JOIN produtores pr
      ON pr.feira_id = p.feira_id AND pr.id = p.produtor_id
    WHERE v.feira_id = :f
    GROUP BY v.id, v.data_hora, v.total
    ORDER BY v.data_hora DESC
    LIMIT 6
  ");
  $st->execute([':f'=>$feiraId]);
  $ultimosLanc = $st->fetchAll();
} catch (Throwable $e) {
  $ultimosLanc = [];
}

/* ===== Lista de produtores (amostra real) ===== */
$listaProdutores = [];
try {
  $st = $pdo->prepare("
    SELECT
      pr.nome,
      COALESCE(c.nome,'') AS comunidade,
      pr.ativo
    FROM produtores pr
    LEFT JOIN comunidades c
      ON c.feira_id = pr.feira_id AND c.id = pr.comunidade_id
    WHERE pr.feira_id = :f
    ORDER BY pr.ativo DESC, pr.nome ASC
    LIMIT 6
  ");
  $st->execute([':f'=>$feiraId]);
  $listaProdutores = $st->fetchAll();
} catch (Throwable $e) {
  $listaProdutores = [];
}

/* ===== Tabela avançada: últimos itens vendidos ===== */
$ultimosItens = [];
try {
  $st = $pdo->prepare("
    SELECT
      v.id AS venda_id,
      v.data_hora,
      p.nome AS produto,
      COALESCE(cat.nome,'') AS categoria,
      COALESCE(pr.nome,'') AS feirante,
      vi.quantidade,
      COALESCE(u.sigla,'') AS unid,
      vi.subtotal,
      COALESCE(NULLIF(TRIM(v.forma_pagamento),''),'—') AS forma_pagamento,
      COALESCE(NULLIF(TRIM(v.status),''),'—') AS status
    FROM venda_itens vi
    JOIN vendas v
      ON v.feira_id = vi.feira_id AND v.id = vi.venda_id
    JOIN produtos p
      ON p.feira_id = vi.feira_id AND p.id = vi.produto_id
    LEFT JOIN categorias cat
      ON cat.feira_id = p.feira_id AND cat.id = p.categoria_id
    LEFT JOIN unidades u
      ON u.feira_id = p.feira_id AND u.id = p.unidade_id
    LEFT JOIN produtores pr
      ON pr.feira_id = p.feira_id AND pr.id = p.produtor_id
    WHERE vi.feira_id = :f
    ORDER BY v.data_hora DESC
    LIMIT 30
  ");
  $st->execute([':f'=>$feiraId]);
  $ultimosItens = $st->fetchAll();
} catch (Throwable $e) {
  $ultimosItens = [];
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <title>SIGRelatórios Feira do Produtor</title>

  <link rel="stylesheet" href="../../../vendors/feather/feather.css">
  <link rel="stylesheet" href="../../../vendors/ti-icons/css/themify-icons.css">
  <link rel="stylesheet" href="../../../vendors/css/vendor.bundle.base.css">

  <link rel="stylesheet" href="../../../vendors/datatables.net-bs4/dataTables.bootstrap4.css">
  <link rel="stylesheet" href="../../../vendors/ti-icons/css/themify-icons.css">
  <link rel="stylesheet" type="text/css" href="../../../js/select.dataTables.min.css">

  <link rel="stylesheet" href="../../../css/vertical-layout-light/style.css">
  <link rel="shortcut icon" href="../../../images/3.png" />

  <style>
    ul .nav-link:hover { color: blue !important; }
    .nav-link { color: black !important; }

    .sidebar .sub-menu .nav-item .nav-link { margin-left: -35px !important; }
    .sidebar .sub-menu li { list-style: none !important; }

    .mini-kpi { font-size: 12px; color: #6c757d; }
    .badge-soft {
      background: rgba(0, 0, 0, 0.05);
      border: 1px solid rgba(0, 0, 0, 0.07);
      font-weight: 600;
    }
    .table td, .table th { vertical-align: middle !important; }

    .kpi-card{
      border: 1px solid rgba(0,0,0,.08);
      border-radius: 14px;
      padding: 14px;
      background: #fff;
      height: 100%;
    }
    .kpi-label{ font-size: 12px; color: #6c757d; margin:0; }
    .kpi-value{ font-size: 22px; font-weight: 800; margin:0; }
    .kpi-sub{ font-size: 12px; color: #6c757d; margin-top:6px; }
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
          <a class="nav-link active" id="todo-tab" data-toggle="tab" href="#todo-section" role="tab">TO DO LIST</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" id="chats-tab" data-toggle="tab" href="#chats-section" role="tab">CHATS</a>
        </li>
      </ul>
    </div>
    <!-- /SETTINGS PANEL -->

    <!-- SIDEBAR -->
    <nav class="sidebar sidebar-offcanvas" id="sidebar">
      <ul class="nav">

        <li class="nav-item active">
          <a class="nav-link" href="./index.php">
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
            <ul class="nav flex-column sub-menu" style="background:#fff !important;">
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
          <div class="collapse text-black" id="feiraRelatorios">
            <ul class="nav flex-column sub-menu" style="background:#fff !important;">
              <li class="nav-item"><a class="nav-link" href="./relatorioFinanceiro.php"><i class="ti-bar-chart mr-2"></i> Relatório Financeiro</a></li>
              <li class="nav-item"><a class="nav-link" href="./relatorioProdutos.php"><i class="ti-list mr-2"></i> Produtos Comercializados</a></li>
              <li class="nav-item"><a class="nav-link" href="./relatorioMensal.php"><i class="ti-calendar mr-2"></i> Resumo Mensal</a></li>
              <li class="nav-item"><a class="nav-link" href="./configRelatorio.php"><i class="ti-settings mr-2"></i> Configurar</a></li>
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
                <h6 class="font-weight-normal mb-0">Painel administrativo da Feira do Produtor</h6>
                <div class="mini-kpi mt-1">
                  Hoje: <b><?= date('d/m/Y') ?></b> • Feira ID: <b><?= (int)$feiraId ?></b>
                </div>
              </div>

              <div class="col-12 col-xl-4">
                <div class="justify-content-end d-flex">
                  <div class="dropdown flex-md-grow-1 flex-xl-grow-0">
                    <a class="btn btn-sm btn-light bg-white" href="./lancamentos.php?dia=<?= h($today) ?>">
                      <i class="ti-write mr-1"></i> Lançar venda (hoje)
                    </a>
                    <a class="btn btn-sm btn-light bg-white ml-2" href="./fechamentoDia.php?dia=<?= h($today) ?>">
                      <i class="ti-check mr-1"></i> Fechamento (hoje)
                    </a>
                  </div>
                </div>
              </div>

            </div>
          </div>
        </div>
        <!-- /HEADER -->

        <!-- KPIs REAIS -->
        <div class="row">
          <div class="col-md-6 grid-margin stretch-card">
            <div class="card tale-bg">
              <div class="card-people">
                <div style="position: relative; margin-top: -30px;">
                  <img src="../../../images/dashboard/produtor.jpeg" alt="people" style="filter: brightness(55%); margin-top: -30px;">
                </div>
                <div class="weather-info text-white font-weight-bold">
                  <div class="d-flex">
                    <div>
                      <h2 class="mb-0 font-weight-normal">
                        <i class="ti-stats-up mr-2"></i> R$ <?= money($kpi['vendas_hoje_total']) ?>
                      </h2>
                    </div>
                    <div class="ml-2 text-white">
                      <h4 class="location font-weight-normal">Vendas Hoje</h4>
                      <h6 class="font-weight-normal"><?= (int)$kpi['vendas_hoje_qtd'] ?> venda(s) • Ticket: R$ <?= money($kpi['ticket_hoje']) ?></h6>
                    </div>
                  </div>
                  <div class="mt-2 mini-kpi text-white">
                    Itens vendidos hoje: <b><?= number_format((float)$kpi['itens_hoje_qtd'], 3, ',', '.') ?></b> • Canceladas: <b><?= (int)$kpi['canceladas_hoje'] ?></b>
                  </div>
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
                    <p class="fs-30 mb-1">R$ <?= money($kpi['vendas_hoje_total']) ?></p>
                    <p class="mini-kpi"><?= (int)$kpi['vendas_hoje_qtd'] ?> lançamento(s)</p>
                  </div>
                </div>
              </div>
              <div class="col-md-6 mb-4 stretch-card transparent">
                <div class="card card-dark-blue">
                  <div class="card-body">
                    <p class="mb-2">Total do Mês</p>
                    <p class="fs-30 mb-1">R$ <?= money($kpi['mes_total']) ?></p>
                    <p class="mini-kpi"><?= (int)$kpi['mes_vendas_qtd'] ?> venda(s) • Ticket: R$ <?= money($kpi['mes_ticket']) ?></p>
                  </div>
                </div>
              </div>
            </div>

            <div class="row">
              <div class="col-md-6 mb-4 mb-lg-0 stretch-card transparent">
                <div class="card card-light-blue">
                  <div class="card-body">
                    <p class="mb-2">Produtores Ativos</p>
                    <p class="fs-30 mb-1"><?= (int)$kpi['produtores_ativos'] ?></p>
                    <p class="mini-kpi">Feira <?= (int)$feiraId ?></p>
                  </div>
                </div>
              </div>
              <div class="col-md-6 stretch-card transparent">
                <div class="card card-light-danger">
                  <div class="card-body">
                    <p class="mb-2">Produtos Ativos</p>
                    <p class="fs-30 mb-1"><?= (int)$kpi['produtos_ativos'] ?></p>
                    <p class="mini-kpi">Preço ref. zerado: <?= (int)$kpi['preco_ref_zero'] ?></p>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
        <!-- /KPIs -->

        <!-- BLOCO PAGAMENTO HOJE + TOP CATEGORIAS (MÊS) -->
        <div class="row">
          <div class="col-md-6 grid-margin stretch-card">
            <div class="card">
              <div class="card-body">
                <p class="card-title mb-1">Resumo de Vendas (Hoje)</p>
                <p class="mini-kpi mb-3">Distribuição por forma de pagamento (automatico do banco)</p>

                <div class="table-responsive">
                  <table class="table table-borderless mb-0">
                    <tbody>
                      <tr>
                        <td><span class="badge badge-soft">PIX</span></td>
                        <td class="w-100 px-3">
                          <div class="progress progress-md">
                            <div class="progress-bar bg-success" role="progressbar" style="width: <?= (int)$payPct['PIX'] ?>%"></div>
                          </div>
                        </td>
                        <td class="text-right font-weight-bold"><?= (int)$payPct['PIX'] ?>%</td>
                      </tr>
                      <tr>
                        <td><span class="badge badge-soft">Dinheiro</span></td>
                        <td class="w-100 px-3">
                          <div class="progress progress-md">
                            <div class="progress-bar bg-primary" role="progressbar" style="width: <?= (int)$payPct['DINHEIRO'] ?>%"></div>
                          </div>
                        </td>
                        <td class="text-right font-weight-bold"><?= (int)$payPct['DINHEIRO'] ?>%</td>
                      </tr>
                      <tr>
                        <td><span class="badge badge-soft">Cartão</span></td>
                        <td class="w-100 px-3">
                          <div class="progress progress-md">
                            <div class="progress-bar bg-info" role="progressbar" style="width: <?= (int)$payPct['CARTAO'] ?>%"></div>
                          </div>
                        </td>
                        <td class="text-right font-weight-bold"><?= (int)$payPct['CARTAO'] ?>%</td>
                      </tr>
                      <tr>
                        <td><span class="badge badge-soft">Outros</span></td>
                        <td class="w-100 px-3">
                          <div class="progress progress-md">
                            <div class="progress-bar bg-warning" role="progressbar" style="width: <?= (int)$payPct['OUTROS'] ?>%"></div>
                          </div>
                        </td>
                        <td class="text-right font-weight-bold"><?= (int)$payPct['OUTROS'] ?>%</td>
                      </tr>
                    </tbody>
                  </table>
                </div>

                <hr>
                <div class="d-flex flex-wrap">
                  <div class="mr-4 mt-2">
                    <div class="mini-kpi">Total hoje</div>
                    <div class="font-weight-bold">R$ <?= money($kpi['vendas_hoje_total']) ?></div>
                  </div>
                  <div class="mr-4 mt-2">
                    <div class="mini-kpi">Ticket médio hoje</div>
                    <div class="font-weight-bold">R$ <?= money($kpi['ticket_hoje']) ?></div>
                  </div>
                  <div class="mr-4 mt-2">
                    <div class="mini-kpi">Sem pagamento (mês)</div>
                    <div class="font-weight-bold"><?= (int)$kpi['sem_pagto_mes'] ?></div>
                  </div>
                  <div class="mt-2">
                    <div class="mini-kpi">Canceladas hoje</div>
                    <div class="font-weight-bold"><?= (int)$kpi['canceladas_hoje'] ?></div>
                  </div>
                </div>

              </div>
            </div>
          </div>

          <div class="col-md-6 grid-margin stretch-card">
            <div class="card">
              <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                  <p class="card-title mb-1">Top Categorias (Mês)</p>
                  <a href="./relatorioProdutos.php" class="text-info">Ver relatório</a>
                </div>
                <p class="mini-kpi mb-3"><?= date('m/Y') ?> • Ordenado por faturamento</p>

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
                      <?php if (empty($topCategorias)): ?>
                        <tr><td colspan="3" class="text-center text-muted py-3">Sem dados no período.</td></tr>
                      <?php else: ?>
                        <?php foreach ($topCategorias as $c): ?>
                          <tr>
                            <td><?= h($c['categoria'] ?? '—') ?></td>
                            <td class="text-right"><?= number_format((float)($c['itens'] ?? 0), 3, ',', '.') ?></td>
                            <td class="text-right font-weight-bold">R$ <?= money((float)($c['total'] ?? 0)) ?></td>
                          </tr>
                        <?php endforeach; ?>
                      <?php endif; ?>
                    </tbody>
                  </table>
                </div>

                <hr>
                <div class="mini-kpi">Esse bloco já está “ligado” no banco (venda_itens → produtos → categorias).</div>
              </div>
            </div>
          </div>
        </div>

        <!-- RELATÓRIO DETALHADO (agora real nos alertas) -->
        <div class="row">
          <div class="col-md-12 grid-margin stretch-card">
            <div class="card position-relative">
              <div class="card-body">
                <div class="row">
                  <div class="col-md-12 col-xl-3 d-flex flex-column justify-content-start">
                    <div class="ml-xl-4 mt-3">
                      <p class="card-title">Relatório do Mês</p>
                      <h1 class="text-primary">R$ <?= money($kpi['mes_total']) ?></h1>
                      <h3 class="font-weight-500 mb-xl-4 text-primary">Feira do Produtor</h3>
                      <p class="mb-2 mb-xl-0">
                        Período: <b><?= h(date('d/m/Y', strtotime($monthStart))) ?></b> até <b><?= h(date('d/m/Y', strtotime($monthEnd))) ?></b>.
                        <span class="d-block mini-kpi mt-2">Tudo aqui vem do banco.</span>
                      </p>
                    </div>
                  </div>

                  <div class="col-md-12 col-xl-9">
                    <div class="row">
                      <div class="col-md-6 border-right">
                        <div class="table-responsive mb-3 mb-md-0 mt-3">
                          <table class="table table-borderless report-table">
                            <tr>
                              <td class="text-muted">Vendas no mês</td>
                              <td class="w-100 px-0">
                                <div class="progress progress-md mx-4">
                                  <div class="progress-bar bg-primary" role="progressbar" style="width: 80%"></div>
                                </div>
                              </td>
                              <td><h5 class="font-weight-bold mb-0"><?= (int)$kpi['mes_vendas_qtd'] ?></h5></td>
                            </tr>
                            <tr>
                              <td class="text-muted">Ticket médio</td>
                              <td class="w-100 px-0">
                                <div class="progress progress-md mx-4">
                                  <div class="progress-bar bg-info" role="progressbar" style="width: 55%"></div>
                                </div>
                              </td>
                              <td><h5 class="font-weight-bold mb-0">R$ <?= money($kpi['mes_ticket']) ?></h5></td>
                            </tr>
                            <tr>
                              <td class="text-muted">Sem pagamento</td>
                              <td class="w-100 px-0">
                                <div class="progress progress-md mx-4">
                                  <div class="progress-bar bg-warning" role="progressbar" style="width: <?= min(100, (int)($kpi['sem_pagto_mes']*10)) ?>%"></div>
                                </div>
                              </td>
                              <td><h5 class="font-weight-bold mb-0"><?= (int)$kpi['sem_pagto_mes'] ?></h5></td>
                            </tr>
                            <tr>
                              <td class="text-muted">Preço ref. zerado</td>
                              <td class="w-100 px-0">
                                <div class="progress progress-md mx-4">
                                  <div class="progress-bar bg-danger" role="progressbar" style="width: <?= min(100, (int)($kpi['preco_ref_zero']*5)) ?>%"></div>
                                </div>
                              </td>
                              <td><h5 class="font-weight-bold mb-0"><?= (int)$kpi['preco_ref_zero'] ?></h5></td>
                            </tr>
                            <tr>
                              <td class="text-muted">Produtores ativos</td>
                              <td class="w-100 px-0">
                                <div class="progress progress-md mx-4">
                                  <div class="progress-bar bg-success" role="progressbar" style="width: 35%"></div>
                                </div>
                              </td>
                              <td><h5 class="font-weight-bold mb-0"><?= (int)$kpi['produtores_ativos'] ?></h5></td>
                            </tr>
                          </table>
                        </div>
                      </div>

                      <div class="col-md-6 mt-3">
                        <p class="card-title mb-2">Alertas e Pendências</p>
                        <ul class="list-group">
                          <?php if ($hasFechamentoDia && $kpi['fechamento_pendente_ontem'] === 1): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                              Fechamento pendente (<?= h(date('d/m/Y', strtotime($yesterday))) ?>)
                              <span class="badge badge-danger badge-pill">PENDENTE</span>
                            </li>
                          <?php else: ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                              Fechamento de ontem
                              <span class="badge badge-success badge-pill">OK</span>
                            </li>
                          <?php endif; ?>

                          <li class="list-group-item d-flex justify-content-between align-items-center">
                            Produtos com preço referência zerado
                            <span class="badge badge-warning badge-pill"><?= (int)$kpi['preco_ref_zero'] ?></span>
                          </li>

                          <li class="list-group-item d-flex justify-content-between align-items-center">
                            Lançamentos sem forma de pagamento (mês)
                            <span class="badge badge-danger badge-pill"><?= (int)$kpi['sem_pagto_mes'] ?></span>
                          </li>

                          <li class="list-group-item d-flex justify-content-between align-items-center">
                            Cancelamentos hoje
                            <span class="badge badge-info badge-pill"><?= (int)$kpi['canceladas_hoje'] ?></span>
                          </li>

                          <li class="list-group-item d-flex justify-content-between align-items-center">
                            Exportação mensal (CSV/PDF)
                            <span class="badge badge-success badge-pill">OK</span>
                          </li>
                        </ul>

                        <div class="mini-kpi mt-2">
                          *Se você quiser, eu ligo cada alerta para um botão “Ir para corrigir”.
                        </div>
                      </div>

                    </div>
                  </div>

                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- TOP PRODUTOS (REAL) + CHECKLIST (visual) -->
        <div class="row">
          <div class="col-md-7 grid-margin stretch-card">
            <div class="card">
              <div class="card-body">
                <p class="card-title mb-0">Top Produtos (Mês)</p>
                <div class="table-responsive mt-3">
                  <table class="table table-striped table-borderless">
                    <thead>
                      <tr>
                        <th>Produto</th>
                        <th class="text-right">Itens</th>
                        <th class="text-right">Faturamento</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php if (empty($topProdutos)): ?>
                        <tr><td colspan="3" class="text-center text-muted py-3">Sem dados no período.</td></tr>
                      <?php else: ?>
                        <?php foreach ($topProdutos as $tp): ?>
                          <tr>
                            <td><?= h($tp['produto'] ?? '—') ?></td>
                            <td class="text-right"><?= number_format((float)($tp['itens'] ?? 0), 3, ',', '.') ?></td>
                            <td class="text-right font-weight-bold">R$ <?= money((float)($tp['total'] ?? 0)) ?></td>
                          </tr>
                        <?php endforeach; ?>
                      <?php endif; ?>
                    </tbody>
                  </table>
                </div>

                <div class="mini-kpi">Já vem do banco: venda_itens → produtos (somando subtotal).</div>
              </div>
            </div>
          </div>

          <div class="col-md-5 grid-margin stretch-card">
            <div class="card">
              <div class="card-body">
                <h4 class="card-title">Checklist do Admin</h4>
                <div class="list-wrapper pt-2">
                  <ul class="todo-list todo-list-custom" style="padding-left:0; list-style:none;">
                    <li class="d-flex justify-content-between align-items-center mb-2">
                      <div><input type="checkbox" <?= ($kpi['fechamento_pendente_ontem']===0 ? 'checked' : '') ?>> Conferir fechamento de ontem</div>
                      <span class="badge <?= ($kpi['fechamento_pendente_ontem']===0 ? 'badge-success' : 'badge-danger') ?>">
                        <?= ($kpi['fechamento_pendente_ontem']===0 ? 'OK' : 'Pendente') ?>
                      </span>
                    </li>
                    <li class="d-flex justify-content-between align-items-center mb-2">
                      <div><input type="checkbox"> Revisar lançamentos sem forma de pagamento</div>
                      <span class="badge badge-warning">Pendente</span>
                    </li>
                    <li class="d-flex justify-content-between align-items-center mb-2">
                      <div><input type="checkbox"> Atualizar preços referência</div>
                      <span class="badge badge-info">Hoje</span>
                    </li>
                    <li class="d-flex justify-content-between align-items-center mb-2">
                      <div><input type="checkbox"> Gerar relatório mensal</div>
                      <span class="badge badge-success">OK</span>
                    </li>
                    <li class="d-flex justify-content-between align-items-center">
                      <div><input type="checkbox"> Conferir cancelamentos</div>
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

        <!-- 3 COLUNAS: PRODUTORES / ÚLTIMOS LANÇAMENTOS / (mantive 2 blocos + 1 com itens) -->
        <div class="row">
          <div class="col-md-4 stretch-card grid-margin">
            <div class="card">
              <div class="card-body">
                <p class="card-title mb-0">Produtores (amostra real)</p>
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
                      <?php if (empty($listaProdutores)): ?>
                        <tr><td colspan="3" class="text-center text-muted py-3">Sem produtores.</td></tr>
                      <?php else: ?>
                        <?php foreach ($listaProdutores as $p): ?>
                          <tr>
                            <td class="pl-0"><?= h($p['nome'] ?? '') ?></td>
                            <td><?= h($p['comunidade'] ?? '') ?></td>
                            <td class="text-right">
                              <?php if ((int)($p['ativo'] ?? 0) === 1): ?>
                                <span class="badge badge-success">Ativo</span>
                              <?php else: ?>
                                <span class="badge badge-danger">Inativo</span>
                              <?php endif; ?>
                            </td>
                          </tr>
                        <?php endforeach; ?>
                      <?php endif; ?>
                    </tbody>
                  </table>
                </div>
                <div class="mini-kpi">Vem do banco: produtores + comunidades.</div>
              </div>
            </div>
          </div>

          <div class="col-md-4 stretch-card grid-margin">
            <div class="card">
              <div class="card-body">
                <p class="card-title mb-0">Últimos lançamentos</p>
                <div class="table-responsive mt-3">
                  <table class="table table-borderless">
                    <thead>
                      <tr>
                        <th class="pl-0 pb-2 border-bottom">Data</th>
                        <th class="pb-2 border-bottom">Feirantes</th>
                        <th class="pb-2 border-bottom text-right">Total</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php if (empty($ultimosLanc)): ?>
                        <tr><td colspan="3" class="text-center text-muted py-3">Sem lançamentos.</td></tr>
                      <?php else: ?>
                        <?php foreach ($ultimosLanc as $v): ?>
                          <?php
                            $feirantes = (string)($v['feirantes'] ?? '');
                            $feirantesHtml = $feirantes !== '' ? str_replace('||', '<br>', h($feirantes)) : '—';
                          ?>
                          <tr>
                            <td class="pl-0"><?= h(date('d/m/Y H:i', strtotime((string)($v['data_hora'] ?? 'now')))) ?></td>
                            <td><?= $feirantesHtml ?></td>
                            <td class="text-right font-weight-bold">R$ <?= money((float)($v['total'] ?? 0)) ?></td>
                          </tr>
                        <?php endforeach; ?>
                      <?php endif; ?>
                    </tbody>
                  </table>
                </div>
                <div class="mini-kpi">Feirantes aparecem em linhas separadas (como você pediu).</div>
              </div>
            </div>
          </div>

          <div class="col-md-4 stretch-card grid-margin">
            <div class="card">
              <div class="card-body">
                <p class="card-title mb-0">Acesso rápido</p>
                <div class="mt-3">
                  <a class="btn btn-light w-100 mb-2" href="./lancamentos.php?dia=<?= h($today) ?>">
                    <i class="ti-write mr-1"></i> Lançamentos de hoje
                  </a>
                  <a class="btn btn-light w-100 mb-2" href="./fechamentoDia.php?dia=<?= h($today) ?>">
                    <i class="ti-check-box mr-1"></i> Fechamento do dia
                  </a>
                  <a class="btn btn-light w-100 mb-2" href="./relatorioMensal.php">
                    <i class="ti-calendar mr-1"></i> Resumo mensal
                  </a>
                  <a class="btn btn-light w-100" href="./listaProduto.php">
                    <i class="ti-clipboard mr-1"></i> Produtos / preços
                  </a>
                </div>
                <div class="mini-kpi mt-2">Tudo funcionando sem precisar de gráficos.</div>
              </div>
            </div>
          </div>
        </div>

        <!-- TABELA GRANDE (REAL) -->
        <div class="row">
          <div class="col-md-12 grid-margin stretch-card">
            <div class="card">
              <div class="card-body">
                <p class="card-title">Últimos itens vendidos (real)</p>
                <p class="mini-kpi mb-3">
                  Dados do banco: venda_itens + vendas + produtos + categorias + unidades + produtores.
                </p>

                <div class="table-responsive">
                  <table class="table table-striped table-borderless" style="width:100%">
                    <thead>
                      <tr>
                        <th>Venda</th>
                        <th>Data</th>
                        <th>Produto</th>
                        <th>Categoria</th>
                        <th>Feirante</th>
                        <th class="text-right">Qtd</th>
                        <th>Unid.</th>
                        <th class="text-right">Subtotal</th>
                        <th>Pagamento</th>
                        <th>Status</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php if (empty($ultimosItens)): ?>
                        <tr><td colspan="10" class="text-center text-muted py-4">Sem registros.</td></tr>
                      <?php else: ?>
                        <?php foreach ($ultimosItens as $it): ?>
                          <tr>
                            <td>#<?= (int)($it['venda_id'] ?? 0) ?></td>
                            <td><?= h(date('d/m/Y H:i', strtotime((string)($it['data_hora'] ?? 'now')))) ?></td>
                            <td><?= h($it['produto'] ?? '') ?></td>
                            <td><?= h($it['categoria'] ?? '') ?></td>
                            <td><?= h($it['feirante'] ?? '') ?></td>
                            <td class="text-right"><?= number_format((float)($it['quantidade'] ?? 0), 3, ',', '.') ?></td>
                            <td><?= h($it['unid'] ?? '') ?></td>
                            <td class="text-right font-weight-bold">R$ <?= money((float)($it['subtotal'] ?? 0)) ?></td>
                            <td><span class="badge badge-soft"><?= h($it['forma_pagamento'] ?? '—') ?></span></td>
                            <td>
                              <?php
                                $st = strtoupper(trim((string)($it['status'] ?? '')));
                                if ($st === 'CANCELADA' || $st === 'CANCELADO') echo '<span class="badge badge-danger">Cancelada</span>';
                                elseif ($st === 'PENDENTE') echo '<span class="badge badge-warning">Pendente</span>';
                                else echo '<span class="badge badge-success">OK</span>';
                              ?>
                            </td>
                          </tr>
                        <?php endforeach; ?>
                      <?php endif; ?>
                    </tbody>
                  </table>
                </div>

                <div class="mini-kpi mt-3">
                  Se você quiser, eu adiciono filtros (data, feirante, categoria, pagamento) + paginação (LIMIT/OFFSET) aqui no dashboard.
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
