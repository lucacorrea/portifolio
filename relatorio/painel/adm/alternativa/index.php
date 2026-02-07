<?php

declare(strict_types=1);
session_start();

/* ======================================================================
   TIMEZONE (AMAZONAS)
   - PHP: America/Manaus (UTC-04)
   - MySQL (nesta página): -04:00 (sem mexer no padrão global do seu db())
   ====================================================================== */
date_default_timezone_set('America/Manaus');

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

function h($s): string
{
  return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

/* ===== Conexão (padrão do seu sistema: db(): PDO) ===== */
require '../../../assets/php/conexao.php';
$pdo = db();

/* Força timezone do MySQL só nesta página (Amazonas = -04:00) */
try {
  $pdo->exec("SET time_zone = '-04:00'");
} catch (Throwable $e) {
}

/* Feira do Produtor = 1 (na Feira Alternativa use 2) */
$feiraId = 2;

/* ===== Lista de meses SOMENTE com cadastro (vendas) ===== */
$mesOptions = [];
$mesMap = []; // para validar rápido
try {
  $st = $pdo->prepare("
    SELECT DISTINCT DATE_FORMAT(data_hora, '%Y-%m') AS ym
    FROM vendas
    WHERE feira_id = :f
    ORDER BY ym DESC
    LIMIT 48
  ");
  $st->execute([':f' => $feiraId]);
  foreach ($st->fetchAll() as $row) {
    $ym = (string)($row['ym'] ?? '');
    if ($ym !== '' && preg_match('/^\d{4}-\d{2}$/', $ym)) {
      $label = date('m/Y', strtotime($ym . '-01'));
      $mesOptions[] = ['val' => $ym, 'label' => $label];
      $mesMap[$ym] = true;
    }
  }
} catch (Throwable $e) {
  $mesOptions = [];
  $mesMap = [];
}

/* ===== Filtro mensal (YYYY-MM) ===== */
$defaultMes = !empty($mesOptions) ? (string)$mesOptions[0]['val'] : date('Y-m');
$mes = trim((string)($_GET['mes'] ?? $defaultMes));
if (!preg_match('/^\d{4}-\d{2}$/', $mes)) $mes = $defaultMes;

/* Se o mês escolhido não existir na lista (sem cadastro), volta pro mais recente */
if (!empty($mesMap) && empty($mesMap[$mes])) {
  $mes = $defaultMes;
}

$monthStart = $mes . '-01';
$monthEnd   = date('Y-m-t', strtotime($monthStart));
$mesLabel   = date('m/Y', strtotime($monthStart));

/* Datas base (timezone AM/Manaus) */
$today      = date('Y-m-d');
$yesterday  = date('Y-m-d', strtotime('-1 day'));

/* Mes anterior do mes filtrado (comparação) */
$prevMonthStart = date('Y-m-01', strtotime($monthStart . ' -1 month'));
$prevMonthEnd   = date('Y-m-t',  strtotime($monthStart . ' -1 month'));
$prevMesLabel   = date('m/Y', strtotime($prevMonthStart));

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

/* Helpers */
function money($n): string
{
  return number_format((float)$n, 2, ',', '.');
}
function pct_change(float $current, float $previous): ?float
{
  if ($previous == 0.0) {
    return ($current == 0.0) ? 0.0 : null;
  }
  return (($current - $previous) / $previous) * 100.0;
}

/* ===== Badge slim/organizado ===== */
function variation_badge(?float $pct): array
{
  if ($pct === null) {
    return ['<span class="badge-pill-soft" title="Comparação indefinida (valor anterior = 0)">novo</span>', 'novo'];
  }

  $val = (float)$pct;
  $abs = abs($val);
  $fmt = number_format($abs, 1, ',', '.');

  if ($val > 0.0001) {
    return ['<span class="badge-pill-soft" style="background:rgba(40,167,69,.22); border-color:rgba(40,167,69,.35);"><i class="ti-arrow-up"></i> ' . $fmt . '%</span>', '+' . $fmt . '%'];
  }
  if ($val < -0.0001) {
    return ['<span class="badge-pill-soft" style="background:rgba(220,53,69,.22); border-color:rgba(220,53,69,.35);"><i class="ti-arrow-down"></i> ' . $fmt . '%</span>', '-' . $fmt . '%'];
  }

  return ['<span class="badge-pill-soft" style="background:rgba(108,117,125,.22); border-color:rgba(108,117,125,.35);">0,0%</span>', '0,0%'];
}

/* URL helper mantendo filtros */
function url_with(array $add): string
{
  $q = $_GET;
  foreach ($add as $k => $v) {
    if ($v === null) unset($q[$k]);
    else $q[$k] = $v;
  }
  return '?' . http_build_query($q);
}

/* ===== Paginação (tabela grande do mês) ===== */
$perPage = 6;
$page = (int)($_GET['p'] ?? 1);
if ($page < 1) $page = 1;
$offset = ($page - 1) * $perPage;

$totalRows = 0;
$totalPages = 1;

/* ===== KPIs ===== */
$kpi = [
  'vendas_hoje_total' => 0.0,
  'vendas_hoje_qtd'   => 0,
  'itens_hoje_qtd'    => 0.0,
  'ticket_hoje'       => 0.0,

  'vendas_ontem_total' => 0.0,
  'vendas_ontem_qtd'   => 0,
  'ticket_ontem'       => 0.0,

  'mes_total'         => 0.0,
  'mes_vendas_qtd'    => 0,
  'mes_ticket'        => 0.0,

  'mes_ant_total'      => 0.0,
  'mes_ant_vendas_qtd' => 0,
  'mes_ant_ticket'     => 0.0,

  'produtores_ativos' => 0,
  'produtos_ativos'   => 0,

  'canceladas_hoje'   => 0,

  'sem_pagto_mes'     => 0,
  'preco_ref_zero'    => 0,

  'fechamento_pendente_ontem' => 0,
];

/* Pagamento HOJE */
$payHoje = [
  'PIX'      => 0.0,
  'DINHEIRO' => 0.0,
  'CARTAO'   => 0.0,
  'OUTROS'   => 0.0,
];

try {
  /* Vendas Hoje */
  $st = $pdo->prepare("
    SELECT COUNT(*) AS qtd, COALESCE(SUM(total),0) AS total
    FROM vendas
    WHERE feira_id = :f
      AND DATE(data_hora) = :d
      AND UPPER(status) <> 'CANCELADA'
  ");
  $st->execute([':f' => $feiraId, ':d' => $today]);
  $r = $st->fetch() ?: ['qtd' => 0, 'total' => 0];
  $kpi['vendas_hoje_qtd']   = (int)$r['qtd'];
  $kpi['vendas_hoje_total'] = (float)$r['total'];
  $kpi['ticket_hoje']       = $kpi['vendas_hoje_qtd'] > 0 ? ($kpi['vendas_hoje_total'] / $kpi['vendas_hoje_qtd']) : 0.0;

  /* Vendas Ontem (comparação) */
  $st->execute([':f' => $feiraId, ':d' => $yesterday]);
  $r = $st->fetch() ?: ['qtd' => 0, 'total' => 0];
  $kpi['vendas_ontem_qtd']   = (int)$r['qtd'];
  $kpi['vendas_ontem_total'] = (float)$r['total'];
  $kpi['ticket_ontem']       = $kpi['vendas_ontem_qtd'] > 0 ? ($kpi['vendas_ontem_total'] / $kpi['vendas_ontem_qtd']) : 0.0;

  /* Itens vendidos HOJE */
  $st2 = $pdo->prepare("
    SELECT COALESCE(SUM(vi.quantidade),0) AS itens
    FROM venda_itens vi
    JOIN vendas v
      ON v.feira_id = vi.feira_id AND v.id = vi.venda_id
    WHERE vi.feira_id = :f
      AND DATE(v.data_hora) = :d
      AND UPPER(v.status) <> 'CANCELADA'
  ");
  $st2->execute([':f' => $feiraId, ':d' => $today]);
  $kpi['itens_hoje_qtd'] = (float)($st2->fetchColumn() ?? 0);

  /* Canceladas HOJE */
  $st3 = $pdo->prepare("
    SELECT COUNT(*)
    FROM vendas
    WHERE feira_id = :f
      AND DATE(data_hora) = :d
      AND UPPER(status) = 'CANCELADA'
  ");
  $st3->execute([':f' => $feiraId, ':d' => $today]);
  $kpi['canceladas_hoje'] = (int)($st3->fetchColumn() ?? 0);

  /* Total do MÊS */
  $st4 = $pdo->prepare("
    SELECT COUNT(*) AS qtd, COALESCE(SUM(total),0) AS total
    FROM vendas
    WHERE feira_id = :f
      AND DATE(data_hora) BETWEEN :i AND :e
      AND UPPER(status) <> 'CANCELADA'
  ");
  $st4->execute([':f' => $feiraId, ':i' => $monthStart, ':e' => $monthEnd]);
  $r = $st4->fetch() ?: ['qtd' => 0, 'total' => 0];
  $kpi['mes_vendas_qtd'] = (int)$r['qtd'];
  $kpi['mes_total']      = (float)$r['total'];
  $kpi['mes_ticket']     = $kpi['mes_vendas_qtd'] > 0 ? ($kpi['mes_total'] / $kpi['mes_vendas_qtd']) : 0.0;

  /* Total do MÊS ANTERIOR */
  $st4->execute([':f' => $feiraId, ':i' => $prevMonthStart, ':e' => $prevMonthEnd]);
  $r = $st4->fetch() ?: ['qtd' => 0, 'total' => 0];
  $kpi['mes_ant_vendas_qtd'] = (int)$r['qtd'];
  $kpi['mes_ant_total']      = (float)$r['total'];
  $kpi['mes_ant_ticket']     = $kpi['mes_ant_vendas_qtd'] > 0 ? ($kpi['mes_ant_total'] / $kpi['mes_ant_vendas_qtd']) : 0.0;

  /* Produtores ativos */
  $st = $pdo->prepare("SELECT COUNT(*) FROM produtores WHERE feira_id = :f AND ativo = 1");
  $st->execute([':f' => $feiraId]);
  $kpi['produtores_ativos'] = (int)($st->fetchColumn() ?? 0);

  /* Produtos ativos */
  $st = $pdo->prepare("SELECT COUNT(*) FROM produtos WHERE feira_id = :f AND ativo = 1");
  $st->execute([':f' => $feiraId]);
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
  $st->execute([':f' => $feiraId, ':i' => $monthStart, ':e' => $monthEnd]);
  $kpi['sem_pagto_mes'] = (int)($st->fetchColumn() ?? 0);

  /* Produtos com preço referência zerado/vazio */
  $st = $pdo->prepare("
    SELECT COUNT(*)
    FROM produtos
    WHERE feira_id = :f
      AND ativo = 1
      AND (preco_referencia IS NULL OR preco_referencia <= 0)
  ");
  $st->execute([':f' => $feiraId]);
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
  $st->execute([':f' => $feiraId, ':d' => $today]);
  foreach ($st->fetchAll() as $row) {
    $fp = (string)($row['fp'] ?? 'OUTROS');
    $val = (float)($row['total'] ?? 0);
    if ($fp === 'PIX') $payHoje['PIX'] += $val;
    elseif ($fp === 'DINHEIRO') $payHoje['DINHEIRO'] += $val;
    elseif ($fp === 'CARTAO' || $fp === 'CARTÃO') $payHoje['CARTAO'] += $val;
    else $payHoje['OUTROS'] += $val;
  }

  /* Fechamento pendente ontem? (se existir tabela) */
  if ($hasFechamentoDia) {
    $st = $pdo->prepare("
      SELECT COUNT(*)
      FROM vendas
      WHERE feira_id = :f
        AND DATE(data_hora) = :d
        AND UPPER(status) <> 'CANCELADA'
    ");
    $st->execute([':f' => $feiraId, ':d' => $yesterday]);
    $ontemVendas = (int)($st->fetchColumn() ?? 0);

    $st = $pdo->prepare("SELECT COUNT(*) FROM fechamento_dia WHERE feira_id = :f AND data_ref = :d");
    $st->execute([':f' => $feiraId, ':d' => $yesterday]);
    $ontemFechado = (int)($st->fetchColumn() ?? 0);

    $kpi['fechamento_pendente_ontem'] = ($ontemVendas > 0 && $ontemFechado <= 0) ? 1 : 0;
  }
} catch (Throwable $e) {
  // abre com zeros
}

/* Percentuais pagamento HOJE */
$totalPayHoje = $payHoje['PIX'] + $payHoje['DINHEIRO'] + $payHoje['CARTAO'] + $payHoje['OUTROS'];
$payPct = [
  'PIX'      => $totalPayHoje > 0 ? (int)round(($payHoje['PIX'] / $totalPayHoje) * 100) : 0,
  'DINHEIRO' => $totalPayHoje > 0 ? (int)round(($payHoje['DINHEIRO'] / $totalPayHoje) * 100) : 0,
  'CARTAO'   => $totalPayHoje > 0 ? (int)round(($payHoje['CARTAO'] / $totalPayHoje) * 100) : 0,
  'OUTROS'   => $totalPayHoje > 0 ? (int)round(($payHoje['OUTROS'] / $totalPayHoje) * 100) : 0,
];

/* Badges comparação */
$todayPct = pct_change((float)$kpi['vendas_hoje_total'], (float)$kpi['vendas_ontem_total']);
[$todayBadgeHtml] = variation_badge($todayPct);

$monthPct = pct_change((float)$kpi['mes_total'], (float)$kpi['mes_ant_total']);
[$monthBadgeHtml] = variation_badge($monthPct);

/* ===== Top categorias (mês) ===== */
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
  $st->execute([':f' => $feiraId, ':i' => $monthStart, ':e' => $monthEnd]);
  $topCategorias = $st->fetchAll();
} catch (Throwable $e) {
  $topCategorias = [];
}

/* ===== Top produtos (mês) ===== */
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
  $st->execute([':f' => $feiraId, ':i' => $monthStart, ':e' => $monthEnd]);
  $topProdutos = $st->fetchAll();
} catch (Throwable $e) {
  $topProdutos = [];
}

/* ===== Últimos lançamentos (mês) ===== */
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
      AND DATE(v.data_hora) BETWEEN :i AND :e
    GROUP BY v.id, v.data_hora, v.total
    ORDER BY v.data_hora DESC
    LIMIT 6
  ");
  $st->execute([':f' => $feiraId, ':i' => $monthStart, ':e' => $monthEnd]);
  $ultimosLanc = $st->fetchAll();
} catch (Throwable $e) {
  $ultimosLanc = [];
}

/* ===== Lista produtores (amostra) ===== */
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
  $st->execute([':f' => $feiraId]);
  $listaProdutores = $st->fetchAll();
} catch (Throwable $e) {
  $listaProdutores = [];
}

/* ===== Tabela grande (mês) com paginação 6 ===== */
$ultimosItens = [];
try {
  $st = $pdo->prepare("
    SELECT COUNT(*)
    FROM venda_itens vi
    JOIN vendas v
      ON v.feira_id = vi.feira_id AND v.id = vi.venda_id
    JOIN produtos p
      ON p.feira_id = vi.feira_id AND p.id = vi.produto_id
    WHERE vi.feira_id = :f
      AND DATE(v.data_hora) BETWEEN :i AND :e
  ");
  $st->execute([':f' => $feiraId, ':i' => $monthStart, ':e' => $monthEnd]);
  $totalRows = (int)($st->fetchColumn() ?? 0);

  $totalPages = max(1, (int)ceil($totalRows / $perPage));
  if ($page > $totalPages) {
    $page = $totalPages;
    $offset = ($page - 1) * $perPage;
  }

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
      AND DATE(v.data_hora) BETWEEN :i AND :e
    ORDER BY v.data_hora DESC
    LIMIT :lim OFFSET :off
  ");
  $st->bindValue(':f', $feiraId, PDO::PARAM_INT);
  $st->bindValue(':i', $monthStart, PDO::PARAM_STR);
  $st->bindValue(':e', $monthEnd, PDO::PARAM_STR);
  $st->bindValue(':lim', $perPage, PDO::PARAM_INT);
  $st->bindValue(':off', $offset, PDO::PARAM_INT);
  $st->execute();
  $ultimosItens = $st->fetchAll();
} catch (Throwable $e) {
  $ultimosItens = [];
  $totalRows = 0;
  $totalPages = 1;
}

/* Navegação mês */
$mesAtual    = date('Y-m');
$mesAnterior = date('Y-m', strtotime($monthStart . ' -1 month'));
$mesProximo  = date('Y-m', strtotime($monthStart . ' +1 month'));
$nomeTopo = $_SESSION['usuario_nome'] ?? 'Admin';
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <title>SIGRelatórios Feira Alternativa</title>

  <link rel="stylesheet" href="../../../vendors/feather/feather.css">
  <link rel="stylesheet" href="../../../vendors/ti-icons/css/themify-icons.css">
  <link rel="stylesheet" href="../../../vendors/css/vendor.bundle.base.css">
  <link rel="stylesheet" href="../../../vendors/datatables.net-bs4/dataTables.bootstrap4.css">
  <link rel="stylesheet" href="../../../vendors/ti-icons/css/themify-icons.css">
  <link rel="stylesheet" type="text/css" href="../../../js/select.dataTables.min.css">
  <link rel="stylesheet" href="../../../css/vertical-layout-light/style.css">
  <link rel="shortcut icon" href="../../../images/3.png" />

  <style>
    /* ===== Slim / clean ===== */
    .content-wrapper {
      padding-top: 1rem !important;
    }

    .grid-margin {
      margin-bottom: 1rem !important;
    }

    .card {
      border-radius: 16px !important;
      overflow: hidden;
    }

    .card-body {
      padding: 1rem !important;
    }

    .card-title {
      margin-bottom: .35rem !important;
    }

    hr {
      margin: .8rem 0 !important;
    }

    .mini-kpi {
      font-size: 12px;
      color: #6c757d;
      margin-bottom: .35rem;
      line-height: 1.25;
    }

    .table th,
    .table td {
      padding: .55rem .6rem !important;
      vertical-align: middle !important;
    }

    .table thead th {
      font-size: 12px;
      text-transform: uppercase;
      letter-spacing: .02em;
    }

    /* ===== Badges mais "finos" e consistentes ===== */
    .badge-pill-soft {
      display: inline-flex;
      align-items: center;
      gap: .35rem;
      padding: .28rem .55rem;
      border-radius: 999px;
      font-weight: 800;
      font-size: 11px;
      line-height: 1;
      border: 1px solid rgba(255, 255, 255, .22);
      background: rgba(255, 255, 255, .14);
      color: #fff;
      white-space: nowrap;
    }

    .badge-pill-soft i {
      font-size: 11px;
    }

    .badge-soft {
      background: rgba(0, 0, 0, 0.05);
      border: 1px solid rgba(0, 0, 0, 0.07);
      font-weight: 700;
      border-radius: 999px;
      padding: .28rem .55rem;
    }

    /* ===== KPIs (cards) ===== */
    .kpi-card {
      position: relative;
    }

    .kpi-title {
      font-size: 12px;
      text-transform: uppercase;
      letter-spacing: .06em;
      opacity: .9;
      margin: 0;
    }

    .kpi-value {
      font-size: 28px;
      font-weight: 900;
      margin: .25rem 0 .15rem 0;
      line-height: 1.05;
    }

    .kpi-sub {
      font-size: 12px;
      opacity: .95;
      margin: 0;
    }

    .kpi-sub b {
      font-weight: 900;
    }

    .kpi-row {
      display: flex;
      align-items: flex-start;
      justify-content: space-between;
      gap: .75rem;
    }

    /* HERO: imagem com overlay + conteúdo mais limpo */
    .kpi-hero {
      min-height: 172px;
      background: #111;
      color: #fff;
      position: relative;
    }

    .kpi-hero .hero-bg {
      position: absolute;
      inset: 0;
      background-size: cover;
      height: 180% !important;
      background-position: center;
      filter: brightness(70%);
      transform: scale(1.05);
    }

    .kpi-hero .hero-overlay {
      position: absolute;
      inset: 0;
      background: linear-gradient(90deg, rgba(0, 0, 0, .45), rgba(0, 0, 0, .15));
    }

    .kpi-hero .hero-content {
      position: relative;
      padding: 1rem;
    }

    .kpi-hero .kpi-value {
      font-size: 30px;
    }

    /* Comparação (ontem / mês anterior) mais discreta */
    .kpi-compare {
      font-size: 12px;
      opacity: .92;
      margin-top: .35rem;
      line-height: 1.25;
    }

    /* Pagamento */
    .progress.progress-md {
      height: 8px;
      border-radius: 999px;
    }

    .progress .progress-bar {
      border-radius: 999px;
    }

    /* Menu hover/cores */
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

    /* Filtro mês slim */
    .mes-filter {
      display: flex;
      gap: 8px;
      align-items: center;
      flex-wrap: wrap;
      justify-content: flex-end;
    }

    .mes-filter input[type="month"] {
      height: 36px;
      border-radius: 10px;
      border: 1px solid rgba(0, 0, 0, .15);
      padding: 6px 10px;
      background: #fff;
    }

    /* Paginação menor */
    .pagination.pagination-sm .page-link {
      padding: .25rem .55rem;
    }

    /* ===== Toolbar filtro mensal (somente select) ===== */
    .mes-toolbar {
      display: flex;
      align-items: center;
      justify-content: flex-end;
      gap: .6rem;
      flex-wrap: wrap;
    }

    .mes-select-wrap {
      display: flex;
      align-items: center;
      gap: .45rem;
      background: #fff;
      border: 1px solid rgba(0, 0, 0, .12);
      border-radius: 14px;
      padding: .35rem .55rem;
      box-shadow: 0 1px 0 rgba(0, 0, 0, .03);
    }

    .mes-select-wrap i {
      opacity: .75;
    }

    .mes-select {
      height: 32px;
      border: 0;
      outline: none;
      background: transparent;
      font-weight: 900;
      font-size: 12px;
      min-width: 150px;
      padding-right: .25rem;
    }

    .mes-actions {
      display: inline-flex;
      gap: .45rem;
      flex-wrap: wrap;
    }

    .action-btn {
      border-radius: 12px !important;
      border: 1px solid rgba(0, 0, 0, .12) !important;
      background: #fff !important;
      padding: .45rem .65rem !important;
      font-weight: 800;
      font-size: 12px;
      display: inline-flex;
      align-items: center;
      gap: .35rem;
      white-space: nowrap;
    }

    .action-btn:hover {
      background: rgba(0, 0, 0, .03) !important;
    }

    @media (max-width: 575.98px) {
      .mes-toolbar {
        justify-content: flex-start;
      }

      .mes-select-wrap {
        width: 100%;
      }

      .mes-select {
        width: 100%;
        min-width: 0;
      }

      .mes-actions {
        width: 100%;
      }

      .mes-actions a {
        flex: 1;
        justify-content: center;
      }
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
          <li class="nav-item nav-profile dropdown">
            <a class="nav-link dropdown-toggle" href="#" data-toggle="dropdown" id="profileDropdown">
              <i class="ti-user"></i>
              <span class="ml-1"><?= h($nomeTopo) ?></span>
            </a>
            <div class="dropdown-menu dropdown-menu-right navbar-dropdown" aria-labelledby="profileDropdown">
              <a class="dropdown-item" href="../../../controle/auth/logout.php">
                <i class="ti-power-off text-primary"></i> Sair
              </a>
            </div>
          </li>
        </ul>

        <button class="navbar-toggler navbar-toggler-right d-lg-none align-self-center" type="button" data-toggle="offcanvas">
          <span class="icon-menu"></span>
        </button>
      </div>
    </nav>
    <!-- /NAVBAR -->

    <div class="container-fluid page-body-wrapper">

      <nav class="sidebar sidebar-offcanvas" id="sidebar">
        <ul class="nav">

          <!-- Dashboard -->
          <li class="nav-item active">
            <a class="nav-link" href="./index.php">
              <i class="icon-grid menu-icon"></i>
              <span class="menu-title">Dashboard</span>
            </a>
          </li>

          <!-- Cadastros -->
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

          <!-- Movimento -->
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

          <!-- Relatórios -->
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


      <!-- /SIDEBAR -->

      <!-- MAIN -->
      <div class="main-panel">
        <div class="content-wrapper">

          <!-- HEADER + FILTRO MENSAL -->
          <div class="row">
            <div class="col-md-12 grid-margin">
              <div class="row">
                <div class="col-12 col-xl-7 mb-3 mb-xl-0">
                  <h3 class="font-weight-bold mb-1">Bem-vindo(a) <?= h($nomeUsuario) ?></h3>
                  <h6 class="font-weight-normal mb-1">Painel administrativo da Feira Alternativa</h6>
                  <div class="mini-kpi">
                    Mês selecionado: <b><?= h($mesLabel) ?></b> • Período: <b><?= h(date('d/m/Y', strtotime($monthStart))) ?></b> até <b><?= h(date('d/m/Y', strtotime($monthEnd))) ?></b>
                  </div>
                </div>

                <div class="col-12 col-xl-5">
                  <div class="mes-toolbar">

                    <!-- Select de meses (SÓ meses com cadastro) -->
                    <div class="mes-select-wrap" title="Filtrar por mês">
                      <i class="ti-calendar"></i>
                      <select class="mes-select" onchange="goMes(this.value)">
                        <?php if (empty($mesOptions)): ?>
                          <option value="<?= h(date('Y-m')) ?>"><?= h(date('m/Y')) ?></option>
                        <?php else: ?>
                          <?php foreach ($mesOptions as $opt): ?>
                            <option value="<?= h($opt['val']) ?>" <?= ($opt['val'] === $mes ? 'selected' : '') ?>>
                              <?= h($opt['label']) ?>
                            </option>
                          <?php endforeach; ?>
                        <?php endif; ?>
                      </select>
                    </div>

                    <!-- Ações -->
                    <div class="mes-actions">
                      <a class="action-btn" href="./index.php?mes=<?= h($mesAtual) ?>" title="Ir para o mês atual">
                        <i class="ti-target"></i> Atual
                      </a>

                      <a class="action-btn" href="./lancamentos.php?dia=<?= h($today) ?>" title="Lançar venda hoje">
                        <i class="ti-write"></i> Lançar
                      </a>

                      <a class="action-btn" href="./fechamentoDia.php?dia=<?= h($today) ?>" title="Fechamento do dia (hoje)">
                        <i class="ti-check"></i> Fechar
                      </a>
                    </div>

                  </div>
                </div>

              </div>
            </div>
          </div>

          <!-- KPIs (layout melhorado / slim) -->
          <div class="row">

            <!-- HERO (Hoje) -->
            <div class="col-md-6 grid-margin stretch-card">
              <div class="card kpi-card">
                <div class="kpi-hero">
                  <div class="hero-bg" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%)"></div>

                  <div class="hero-content">
                    <div class="kpi-row">
                      <div>
                        <p class="kpi-title mb-1"><i class="ti-stats-up mr-1"></i> Vendas hoje</p>
                        <div class="kpi-value">R$ <?= money($kpi['vendas_hoje_total']) ?></div>
                        <p class="kpi-sub mb-0">
                          <b><?= (int)$kpi['vendas_hoje_qtd'] ?></b> venda(s) • Ticket <b>R$ <?= money($kpi['ticket_hoje']) ?></b>
                        </p>
                      </div>

                      <div class="text-right">
                        <?= $todayBadgeHtml ?>
                        <?php if ((int)$kpi['fechamento_pendente_ontem'] === 1): ?>
                          <div class="mt-2">
                            <span class="badge badge-warning">Fechamento pendente ontem</span>
                          </div>
                        <?php endif; ?>
                      </div>
                    </div>

                    <div class="kpi-compare">
                      Ontem (<?= h(date('d/m', strtotime($yesterday))) ?>): <b>R$ <?= money($kpi['vendas_ontem_total']) ?></b>
                      • <?= (int)$kpi['vendas_ontem_qtd'] ?> venda(s)
                      <span class="ml-2">• Itens: <b><?= number_format((float)$kpi['itens_hoje_qtd'], 3, ',', '.') ?></b></span>
                      <span class="ml-2">• Canceladas: <b><?= (int)$kpi['canceladas_hoje'] ?></b></span>
                    </div>

                  </div>
                </div>
              </div>
            </div>

            <!-- CARDS MENORES (grid) -->
            <div class="col-md-6 grid-margin transparent">
              <div class="row">

                <div class="col-md-6 mb-3 stretch-card transparent">
                  <div class="card card-tale kpi-card">
                    <div class="card-body">
                      <div class="kpi-row">
                        <p class="kpi-title mb-0">Vendas hoje</p>
                        <?= $todayBadgeHtml ?>
                      </div>
                      <div class="kpi-value">R$ <?= money($kpi['vendas_hoje_total']) ?></div>
                      <p class="kpi-sub mb-0">
                        <b><?= (int)$kpi['vendas_hoje_qtd'] ?></b> lançamento(s)
                        <span class="d-block">Ontem: <b>R$ <?= money($kpi['vendas_ontem_total']) ?></b></span>
                      </p>
                    </div>
                  </div>
                </div>

                <div class="col-md-6 mb-3 stretch-card transparent">
                  <div class="card card-dark-blue kpi-card">
                    <div class="card-body">
                      <div class="kpi-row">
                        <p class="kpi-title mb-0">Total do mês (<?= h($mesLabel) ?>)</p>
                        <?= $monthBadgeHtml ?>
                      </div>
                      <div class="kpi-value">R$ <?= money($kpi['mes_total']) ?></div>
                      <p class="kpi-sub mb-0">
                        <b><?= (int)$kpi['mes_vendas_qtd'] ?></b> venda(s) • Ticket <b>R$ <?= money($kpi['mes_ticket']) ?></b>
                        <span class="d-block"><?= h($prevMesLabel) ?>: <b>R$ <?= money($kpi['mes_ant_total']) ?></b></span>
                      </p>
                    </div>
                  </div>
                </div>

              </div>

              <div class="row">
                <div class="col-md-6 mb-3 mb-lg-0 stretch-card transparent">
                  <div class="card card-light-blue kpi-card">
                    <div class="card-body">
                      <p class="kpi-title mb-1">Produtores ativos</p>
                      <div class="kpi-value"><?= (int)$kpi['produtores_ativos'] ?></div>
                      <p class="kpi-sub mb-0">Feira <b><?= (int)$feiraId ?></b></p>
                    </div>
                  </div>
                </div>

                <div class="col-md-6 stretch-card transparent">
                  <div class="card card-light-danger kpi-card">
                    <div class="card-body">
                      <p class="kpi-title mb-1">Produtos ativos</p>
                      <div class="kpi-value"><?= (int)$kpi['produtos_ativos'] ?></div>
                      <p class="kpi-sub mb-0">Preço ref. zerado: <b><?= (int)$kpi['preco_ref_zero'] ?></b></p>
                    </div>
                  </div>
                </div>
              </div>
            </div>

          </div>

          <!-- Pagamento HOJE + Top Categorias -->
          <div class="row">
            <div class="col-md-6 grid-margin stretch-card">
              <div class="card">
                <div class="card-body">
                  <p class="card-title mb-1">Resumo de Vendas (Hoje)</p>
                  <p class="mini-kpi mb-2">Distribuição por forma de pagamento</p>

                  <div class="table-responsive">
                    <table class="table table-borderless mb-0">
                      <tbody>
                        <tr>
                          <td class="py-1" colspan="3"></td>
                        </tr>

                        <tr>
                          <td style="width:110px"><span class="badge badge-soft">PIX</span></td>
                          <td class="w-100 px-2">
                            <div class="progress progress-md">
                              <div class="progress-bar bg-success" role="progressbar" style="width: <?= (int)$payPct['PIX'] ?>%"></div>
                            </div>
                          </td>
                          <td class="text-right font-weight-bold" style="width:60px"><?= (int)$payPct['PIX'] ?>%</td>
                        </tr>

                        <tr>
                          <td><span class="badge badge-soft">Dinheiro</span></td>
                          <td class="w-100 px-2">
                            <div class="progress progress-md">
                              <div class="progress-bar bg-primary" role="progressbar" style="width: <?= (int)$payPct['DINHEIRO'] ?>%"></div>
                            </div>
                          </td>
                          <td class="text-right font-weight-bold"><?= (int)$payPct['DINHEIRO'] ?>%</td>
                        </tr>

                        <tr>
                          <td><span class="badge badge-soft">Cartão</span></td>
                          <td class="w-100 px-2">
                            <div class="progress progress-md">
                              <div class="progress-bar bg-info" role="progressbar" style="width: <?= (int)$payPct['CARTAO'] ?>%"></div>
                            </div>
                          </td>
                          <td class="text-right font-weight-bold"><?= (int)$payPct['CARTAO'] ?>%</td>
                        </tr>

                        <tr>
                          <td><span class="badge badge-soft">Outros</span></td>
                          <td class="w-100 px-2">
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
                    <div class="mr-4 mt-1">
                      <div class="mini-kpi">Sem pagamento (<?= h($mesLabel) ?>)</div>
                      <div class="font-weight-bold"><?= (int)$kpi['sem_pagto_mes'] ?></div>
                    </div>
                    <div class="mr-4 mt-1">
                      <div class="mini-kpi">Canceladas hoje</div>
                      <div class="font-weight-bold"><?= (int)$kpi['canceladas_hoje'] ?></div>
                    </div>
                    <div class="mt-1">
                      <div class="mini-kpi">Preço ref. zerado</div>
                      <div class="font-weight-bold"><?= (int)$kpi['preco_ref_zero'] ?></div>
                    </div>
                  </div>

                </div>
              </div>
            </div>

            <div class="col-md-6 grid-margin stretch-card">
              <div class="card">
                <div class="card-body">
                  <div class="d-flex justify-content-between align-items-center">
                    <p class="card-title mb-1">Top Categorias (<?= h($mesLabel) ?>)</p>
                    <a href="./relatorioProdutos.php" class="text-info">Ver relatório</a>
                  </div>
                  <p class="mini-kpi mb-2">Ordenado por faturamento</p>

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
                          <tr>
                            <td colspan="3" class="text-center text-muted py-3">Sem dados no mês.</td>
                          </tr>
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

                  <div class="mini-kpi mt-2 mb-0">Filtro mensal aplicado.</div>
                </div>
              </div>
            </div>
          </div>

          <!-- Top Produtos + Produtores -->
          <div class="row">
            <div class="col-md-7 grid-margin stretch-card">
              <div class="card">
                <div class="card-body">
                  <p class="card-title mb-1">Top Produtos (<?= h($mesLabel) ?>)</p>
                  <div class="table-responsive">
                    <table class="table table-striped table-borderless mb-0">
                      <thead>
                        <tr>
                          <th>Produto</th>
                          <th class="text-right">Itens</th>
                          <th class="text-right">Faturamento</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php if (empty($topProdutos)): ?>
                          <tr>
                            <td colspan="3" class="text-center text-muted py-3">Sem dados no mês.</td>
                          </tr>
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
                  <div class="mini-kpi mt-2 mb-0">Filtro mensal aplicado.</div>
                </div>
              </div>
            </div>

            <div class="col-md-5 grid-margin stretch-card">
              <div class="card">
                <div class="card-body">
                  <p class="card-title mb-1">Produtores (amostra)</p>
                  <div class="table-responsive">
                    <table class="table table-borderless mb-0">
                      <thead>
                        <tr>
                          <th class="pl-0 pb-2 border-bottom">Produtor</th>
                          <th class="pb-2 border-bottom">Comunidade</th>
                          <th class="pb-2 border-bottom text-right">Status</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php if (empty($listaProdutores)): ?>
                          <tr>
                            <td colspan="3" class="text-center text-muted py-3">Sem produtores.</td>
                          </tr>
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
                  <div class="mini-kpi mt-2 mb-0">Cadastro (não depende do mês).</div>
                </div>
              </div>
            </div>
          </div>

          <!-- Últimos lançamentos -->
          <div class="row">
            <div class="col-md-12 grid-margin stretch-card">
              <div class="card">
                <div class="card-body">
                  <p class="card-title mb-1">Últimos lançamentos (<?= h($mesLabel) ?>)</p>
                  <div class="table-responsive">
                    <table class="table table-borderless mb-0">
                      <thead>
                        <tr>
                          <th class="pl-0 pb-2 border-bottom">Data</th>
                          <th class="pb-2 border-bottom">Feirantes</th>
                          <th class="pb-2 border-bottom text-right">Total</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php if (empty($ultimosLanc)): ?>
                          <tr>
                            <td colspan="3" class="text-center text-muted py-3">Sem lançamentos no mês.</td>
                          </tr>
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
                  <div class="mini-kpi mt-2 mb-0">Feirantes em linhas separadas quando houver mais de um.</div>
                </div>
              </div>
            </div>
          </div>

          <!-- TABELA GRANDE (MÊS) COM PAGINAÇÃO 6 -->
          <div class="row">
            <div class="col-md-12 grid-margin stretch-card">
              <div class="card">
                <div class="card-body">
                  <div class="d-flex flex-wrap justify-content-between align-items-end">
                    <div>
                      <p class="card-title mb-1">Últimos itens vendidos (<?= h($mesLabel) ?>)</p>
                      <p class="mini-kpi mb-0">
                        Mostrando <b><?= (int)min($perPage, max(0, $totalRows - $offset)) ?></b> de <b><?= (int)$totalRows ?></b> • Página <b><?= (int)$page ?></b> / <b><?= (int)$totalPages ?></b>
                      </p>
                    </div>
                    <div class="mini-kpi mt-2"><span class="badge badge-soft">6 por página</span></div>
                  </div>

                  <hr>

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
                          <tr>
                            <td colspan="10" class="text-center text-muted py-4">Sem registros no mês.</td>
                          </tr>
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
                                $stt = strtoupper(trim((string)($it['status'] ?? '')));
                                if ($stt === 'CANCELADA' || $stt === 'CANCELADO') echo '<span class="badge badge-danger">Cancelada</span>';
                                elseif ($stt === 'PENDENTE') echo '<span class="badge badge-warning">Pendente</span>';
                                else echo '<span class="badge badge-success">OK</span>';
                                ?>
                              </td>
                            </tr>
                          <?php endforeach; ?>
                        <?php endif; ?>
                      </tbody>
                    </table>
                  </div>

                  <?php if ($totalPages > 1): ?>
                    <div class="d-flex justify-content-between align-items-center mt-3 flex-wrap">
                      <div class="mini-kpi mb-2">Paginação mantém o filtro do mês.</div>

                      <nav aria-label="Paginação">
                        <ul class="pagination pagination-sm mb-0">
                          <?php
                          $prevDisabled = ($page <= 1) ? ' disabled' : '';
                          $nextDisabled = ($page >= $totalPages) ? ' disabled' : '';
                          ?>
                          <li class="page-item<?= $prevDisabled ?>">
                            <a class="page-link" href="<?= h($page <= 1 ? '#' : url_with(['p' => $page - 1])) ?>" tabindex="-1">«</a>
                          </li>

                          <?php
                          $win = 2;
                          $start = max(1, $page - $win);
                          $end   = min($totalPages, $page + $win);

                          if ($start > 1) {
                            echo '<li class="page-item"><a class="page-link" href="' . h(url_with(['p' => 1])) . '">1</a></li>';
                            if ($start > 2) echo '<li class="page-item disabled"><span class="page-link">…</span></li>';
                          }

                          for ($i = $start; $i <= $end; $i++) {
                            $active = ($i === $page) ? ' active' : '';
                            echo '<li class="page-item' . $active . '"><a class="page-link" href="' . h(url_with(['p' => $i])) . '">' . (int)$i . '</a></li>';
                          }

                          if ($end < $totalPages) {
                            if ($end < $totalPages - 1) echo '<li class="page-item disabled"><span class="page-link">…</span></li>';
                            echo '<li class="page-item"><a class="page-link" href="' . h(url_with(['p' => $totalPages])) . '">' . (int)$totalPages . '</a></li>';
                          }
                          ?>

                          <li class="page-item<?= $nextDisabled ?>">
                            <a class="page-link" href="<?= h($page >= $totalPages ? '#' : url_with(['p' => $page + 1])) ?>">»</a>
                          </li>
                        </ul>
                      </nav>
                    </div>
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
  <script>
    function goMes(val) {
      if (!val) return;
      const url = new URL(window.location.href);
      url.searchParams.set('mes', val);
      url.searchParams.delete('p'); // reseta paginação
      window.location.href = url.toString();
    }
  </script>

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