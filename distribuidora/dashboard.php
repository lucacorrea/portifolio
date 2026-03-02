<?php

declare(strict_types=1);


@date_default_timezone_set('America/Manaus');

/* =========================
   CONEXÃO (db(): PDO)
========================= */
$possibleConn = [
  __DIR__ . '/assets/conexao.php',
  __DIR__ . '/assets/php/conexao.php',
  __DIR__ . '/assets/dados/_helpers.php', // se existir (não obrigatório)
];
foreach ($possibleConn as $f) {
  if (is_file($f)) require_once $f;
}

if (!function_exists('db')) {
  http_response_code(500);
  echo "ERRO: função db():PDO não encontrada. Ajuste o include da conexão em dashboard.php.";
  exit;
}

$pdo = db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

/* =========================
   HELPERS
========================= */
function e(string $s): string
{
  return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function brl($v): string
{
  $n = (float)$v;
  return 'R$ ' . number_format($n, 2, ',', '.');
}
function nint($v): string
{
  return number_format((float)$v, 0, ',', '.');
}
function pct(float $cur, float $prev): float
{
  if ($prev <= 0) return $cur > 0 ? 100.0 : 0.0;
  return (($cur - $prev) / $prev) * 100.0;
}
function dt(string $ymd): string
{
  // y-m-d -> d/m
  $ts = strtotime($ymd);
  return $ts ? date('d/m', $ts) : $ymd;
}
function monthPtShort(int $m): string
{
  static $map = [1 => 'Jan', 2 => 'Fev', 3 => 'Mar', 4 => 'Abr', 5 => 'Mai', 6 => 'Jun', 7 => 'Jul', 8 => 'Ago', 9 => 'Set', 10 => 'Out', 11 => 'Nov', 12 => 'Dez'];
  return $map[$m] ?? (string)$m;
}
function buildMonthKeys(DateTimeImmutable $start, int $months): array
{
  $keys = [];
  for ($i = 0; $i < $months; $i++) {
    $d = $start->modify("+{$i} months");
    $keys[] = $d->format('Y-m');
  }
  return $keys;
}
function buildMonthLabels(array $keys): array
{
  $labels = [];
  foreach ($keys as $ym) {
    [$y, $m] = array_map('intval', explode('-', $ym));
    $labels[] = monthPtShort($m) . '/' . substr((string)$y, -2);
  }
  return $labels;
}
function fetchKeyVal(PDO $pdo, string $sql, array $params, string $keyCol, string $valCol): array
{
  $st = $pdo->prepare($sql);
  $st->execute($params);
  $out = [];
  while ($r = $st->fetch()) {
    $out[(string)$r[$keyCol]] = $r[$valCol];
  }
  return $out;
}

/* =========================
   ENDPOINTS JSON (MODAIS)
========================= */
$action = (string)($_GET['action'] ?? '');
if ($action !== '') {
  header('Content-Type: application/json; charset=UTF-8');

  try {
    if ($action === 'venda') {
      $id = (int)($_GET['id'] ?? 0);
      if ($id <= 0) throw new RuntimeException('ID inválido.');

      $st = $pdo->prepare("
        SELECT
          id, data, cliente, canal, endereco, obs,
          desconto_tipo, desconto_valor, taxa_entrega,
          subtotal, total,
          pagamento_mode, pagamento, pagamento_json,
          created_at
        FROM vendas
        WHERE id = :id
        LIMIT 1
      ");
      $st->execute([':id' => $id]);
      $v = $st->fetch();
      if (!$v) throw new RuntimeException('Venda não encontrada.');

      // devoluções vinculadas (se usar venda_no)
      $st2 = $pdo->prepare("
        SELECT id, data, hora, tipo, produto, qtd, valor, motivo, obs, status
        FROM devolucoes
        WHERE venda_no = :id
        ORDER BY id DESC
        LIMIT 50
      ");
      $st2->execute([':id' => $id]);
      $devols = $st2->fetchAll();

      echo json_encode(['ok' => true, 'venda' => $v, 'devolucoes' => $devols], JSON_UNESCAPED_UNICODE);
      exit;
    }

    if ($action === 'produto') {
      $id = (int)($_GET['id'] ?? 0);
      if ($id <= 0) throw new RuntimeException('ID inválido.');

      $st = $pdo->prepare("
        SELECT
          p.id, p.codigo, p.nome, p.unidade, p.preco, p.estoque, p.minimo, p.status, p.obs, p.imagem,
          c.nome AS categoria
        FROM produtos p
        LEFT JOIN categorias c ON c.id = p.categoria_id
        WHERE p.id = :id
        LIMIT 1
      ");
      $st->execute([':id' => $id]);
      $p = $st->fetch();
      if (!$p) throw new RuntimeException('Produto não encontrado.');

      echo json_encode(['ok' => true, 'produto' => $p], JSON_UNESCAPED_UNICODE);
      exit;
    }

    echo json_encode(['ok' => false, 'error' => 'Ação inválida.'], JSON_UNESCAPED_UNICODE);
    exit;
  } catch (Throwable $ex) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $ex->getMessage()], JSON_UNESCAPED_UNICODE);
    exit;
  }
}

/* =========================
   PERÍODOS (SELECTS)
========================= */
$period = (string)($_GET['period'] ?? 'today'); // today | 7 | 30
$days = 0;
if ($period === '7') $days = 7;
if ($period === '30') $days = 30;

$today = new DateTimeImmutable('today');
$rangeStart = $days > 0 ? $today->modify("-" . ($days - 1) . " days") : $today; // inclui hoje
$rangeEnd   = $today; // inclusivo

$rangeStartStr = $rangeStart->format('Y-m-d');
$rangeEndStr   = $rangeEnd->format('Y-m-d');

$yesterday = $today->modify('-1 day')->format('Y-m-d');

/* =========================
   KPIs (vendas/produtos)
========================= */
// Vendas Hoje / Ontem
$st = $pdo->prepare("SELECT COUNT(*) c, COALESCE(SUM(total),0) s FROM vendas WHERE data = :d");
$st->execute([':d' => $today->format('Y-m-d')]);
$kToday = $st->fetch() ?: ['c' => 0, 's' => 0];

$st->execute([':d' => $yesterday]);
$kYest = $st->fetch() ?: ['c' => 0, 's' => 0];

$vendasHoje = (int)$kToday['c'];
$vendasOntem = (int)$kYest['c'];
$vendasHojePct = pct((float)$vendasHoje, (float)$vendasOntem);

// Faturamento mês atual / mês anterior
$firstThisMonth = $today->modify('first day of this month')->format('Y-m-d');
$firstNextMonth = $today->modify('first day of next month')->format('Y-m-d');

$firstPrevMonth = $today->modify('first day of last month')->format('Y-m-d');
$firstThisMonth2 = $firstThisMonth;

$st = $pdo->prepare("SELECT COALESCE(SUM(total),0) s FROM vendas WHERE data >= :ini AND data < :fim");
$st->execute([':ini' => $firstThisMonth, ':fim' => $firstNextMonth]);
$faturMes = (float)($st->fetch()['s'] ?? 0);

$st->execute([':ini' => $firstPrevMonth, ':fim' => $firstThisMonth2]);
$faturMesPrev = (float)($st->fetch()['s'] ?? 0);
$faturMesPct = pct($faturMes, $faturMesPrev);

// Itens em estoque (snapshot)
$st = $pdo->query("SELECT COALESCE(SUM(estoque),0) s FROM produtos WHERE status = 'ATIVO'");
$itensEstoque = (int)($st->fetch()['s'] ?? 0);

// “Saídas” (movimento) - compara últimos 30 dias vs 30 dias anteriores
$movDays = 30;
$movEnd = $today->format('Y-m-d');
$movStart = $today->modify("-" . ($movDays - 1) . " days")->format('Y-m-d');
$movPrevEnd = $today->modify("-{$movDays} days")->format('Y-m-d');
$movPrevStart = $today->modify("-" . (($movDays * 2) - 1) . " days")->format('Y-m-d');

$st = $pdo->prepare("SELECT COALESCE(SUM(qtd),0) s FROM saidas WHERE data BETWEEN :ini AND :fim");
$st->execute([':ini' => $movStart, ':fim' => $movEnd]);
$saidas30 = (float)($st->fetch()['s'] ?? 0);

$st->execute([':ini' => $movPrevStart, ':fim' => $movPrevEnd]);
$saidas30Prev = (float)($st->fetch()['s'] ?? 0);
$saidasPct = pct($saidas30, $saidas30Prev);

// Ticket médio hoje / ontem
$st = $pdo->prepare("SELECT COALESCE(AVG(total),0) a FROM vendas WHERE data = :d");
$st->execute([':d' => $today->format('Y-m-d')]);
$tickHoje = (float)($st->fetch()['a'] ?? 0);

$st->execute([':d' => $yesterday]);
$tickOntem = (float)($st->fetch()['a'] ?? 0);

$tickPct = pct($tickHoje, $tickOntem);

/* =========================
   GRÁFICOS (12 meses / 6 meses)
========================= */
// Base 12 meses (inclusive mês atual)
$start12 = $today->modify('first day of this month')->modify('-11 months');
$keys12 = buildMonthKeys($start12, 12);
$labels12 = buildMonthLabels($keys12);
$start12Str = $start12->format('Y-m-d');
$end12Str = $today->modify('first day of next month')->format('Y-m-d');

// Chart1: faturamento 12m (SUM(vendas.total))
$revMap = fetchKeyVal(
  $pdo,
  "SELECT DATE_FORMAT(data,'%Y-%m') ym, COALESCE(SUM(total),0) total
   FROM vendas
   WHERE data >= :ini AND data < :fim
   GROUP BY ym
   ORDER BY ym",
  [':ini' => $start12Str, ':fim' => $end12Str],
  'ym',
  'total'
);
$chart1 = [];
foreach ($keys12 as $k) $chart1[] = (float)($revMap[$k] ?? 0);

// Chart2: vendas por mês (COUNT)
$cntMap = fetchKeyVal(
  $pdo,
  "SELECT DATE_FORMAT(data,'%Y-%m') ym, COUNT(*) c
   FROM vendas
   WHERE data >= :ini AND data < :fim
   GROUP BY ym
   ORDER BY ym",
  [':ini' => $start12Str, ':fim' => $end12Str],
  'ym',
  'c'
);
$chart2 = [];
foreach ($keys12 as $k) $chart2[] = (int)($cntMap[$k] ?? 0);

// Chart3: Mix (usa saidas.canal top2 + devolucoes)
$topCanals = [];
try {
  $st = $pdo->prepare("
    SELECT canal, COUNT(DISTINCT pedido) c
    FROM saidas
    WHERE data >= :ini AND data < :fim
    GROUP BY canal
    ORDER BY c DESC
    LIMIT 2
  ");
  $st->execute([':ini' => $start12Str, ':fim' => $end12Str]);
  $topCanals = array_values(array_filter(array_map(fn($r) => (string)$r['canal'], $st->fetchAll())));
} catch (Throwable $ex) {
  $topCanals = [];
}

if (count($topCanals) < 2) {
  // fallback para canais da tabela vendas
  $topCanals = ['PRESENCIAL', 'DELIVERY'];
}
$mixA = $topCanals[0] ?? 'PRESENCIAL';
$mixB = $topCanals[1] ?? 'DELIVERY';

$mixMap = [];
$st = $pdo->prepare("
  SELECT DATE_FORMAT(data,'%Y-%m') ym, canal, COUNT(DISTINCT pedido) c
  FROM saidas
  WHERE data >= :ini AND data < :fim
  GROUP BY ym, canal
  ORDER BY ym
");
$st->execute([':ini' => $start12Str, ':fim' => $end12Str]);
while ($r = $st->fetch()) {
  $mixMap[(string)$r['ym']][(string)$r['canal']] = (int)$r['c'];
}
$chart3A = [];
$chart3B = [];
foreach ($keys12 as $k) {
  $chart3A[] = (int)($mixMap[$k][$mixA] ?? 0);
  $chart3B[] = (int)($mixMap[$k][$mixB] ?? 0);
}

$devMap = fetchKeyVal(
  $pdo,
  "SELECT DATE_FORMAT(data,'%Y-%m') ym, COUNT(*) c
   FROM devolucoes
   WHERE data >= :ini AND data < :fim
   GROUP BY ym
   ORDER BY ym",
  [':ini' => $start12Str, ':fim' => $end12Str],
  'ym',
  'c'
);
$chart3C = [];
foreach ($keys12 as $k) $chart3C[] = (int)($devMap[$k] ?? 0);

// Chart4: Entradas x Saídas (6 meses)
$start6 = $today->modify('first day of this month')->modify('-5 months');
$keys6 = buildMonthKeys($start6, 6);
$labels6 = buildMonthLabels($keys6);
$start6Str = $start6->format('Y-m-d');
$end6Str = $today->modify('first day of next month')->format('Y-m-d');

$entMap = fetchKeyVal(
  $pdo,
  "SELECT DATE_FORMAT(data,'%Y-%m') ym, COALESCE(SUM(qtd),0) s
   FROM entradas
   WHERE data >= :ini AND data < :fim
   GROUP BY ym
   ORDER BY ym",
  [':ini' => $start6Str, ':fim' => $end6Str],
  'ym',
  's'
);

$saiMap = fetchKeyVal(
  $pdo,
  "SELECT DATE_FORMAT(data,'%Y-%m') ym, COALESCE(SUM(qtd),0) s
   FROM saidas
   WHERE data >= :ini AND data < :fim
   GROUP BY ym
   ORDER BY ym",
  [':ini' => $start6Str, ':fim' => $end6Str],
  'ym',
  's'
);

$chart4Ent = [];
$chart4Sai = [];
foreach ($keys6 as $k) {
  $chart4Ent[] = (float)($entMap[$k] ?? 0);
  $chart4Sai[] = (float)($saiMap[$k] ?? 0);
}

// Chart5: Delivery x Presencial (no período)
$st = $pdo->prepare("
  SELECT canal, COUNT(*) c
  FROM vendas
  WHERE data BETWEEN :ini AND :fim
  GROUP BY canal
");
$st->execute([':ini' => $rangeStartStr, ':fim' => $rangeEndStr]);
$canalCounts = ['DELIVERY' => 0, 'PRESENCIAL' => 0];
while ($r = $st->fetch()) {
  $k = strtoupper((string)$r['canal']);
  if (!isset($canalCounts[$k])) $canalCounts[$k] = 0;
  $canalCounts[$k] += (int)$r['c'];
}
$deliveryQtd = (int)($canalCounts['DELIVERY'] ?? 0);
$presencialQtd = (int)($canalCounts['PRESENCIAL'] ?? 0);

// Chart6: pagamentos mais usados (no período)
$st = $pdo->prepare("
  SELECT pagamento, COUNT(*) c
  FROM vendas
  WHERE data BETWEEN :ini AND :fim
  GROUP BY pagamento
  ORDER BY c DESC
  LIMIT 5
");
$st->execute([':ini' => $rangeStartStr, ':fim' => $rangeEndStr]);
$payLabels = [];
$payData = [];
while ($r = $st->fetch()) {
  $payLabels[] = (string)$r['pagamento'];
  $payData[] = (int)$r['c'];
}
if (!$payLabels) {
  $payLabels = ['DINHEIRO', 'PIX', 'CARTAO'];
  $payData = [0, 0, 0];
}

// Chart7: top produtos vendidos (semana = padrão 7 dias)
$topDays = (int)($_GET['top'] ?? 7);
if (!in_array($topDays, [7, 30, 90], true)) $topDays = 7;
$topStart = $today->modify("-" . ($topDays - 1) . " days")->format('Y-m-d');
$topEnd = $today->format('Y-m-d');

$st = $pdo->prepare("
  SELECT p.nome, COALESCE(SUM(s.qtd),0) qtd
  FROM saidas s
  JOIN produtos p ON p.id = s.produto_id
  WHERE s.data BETWEEN :ini AND :fim
  GROUP BY p.id, p.nome
  ORDER BY qtd DESC
  LIMIT 10
");
$st->execute([':ini' => $topStart, ':fim' => $topEnd]);
$topProdLabels = [];
$topProdData = [];
while ($r = $st->fetch()) {
  $topProdLabels[] = (string)$r['nome'];
  $topProdData[] = (float)$r['qtd'];
}
if (!$topProdLabels) {
  $topProdLabels = ['(sem dados)'];
  $topProdData = [0];
}

/* =========================
   TABELAS
========================= */
// Vendas recentes (período)
$st = $pdo->prepare("
  SELECT id, data, cliente, canal, pagamento, total
  FROM vendas
  WHERE data BETWEEN :ini AND :fim
  ORDER BY data DESC, id DESC
  LIMIT 10
");
$st->execute([':ini' => $rangeStartStr, ':fim' => $rangeEndStr]);
$vendasRecentes = $st->fetchAll();

// Estoque baixo
$st = $pdo->query("
  SELECT
    p.id, p.nome, p.estoque, p.minimo,
    COALESCE(c.nome,'-') AS categoria
  FROM produtos p
  LEFT JOIN categorias c ON c.id = p.categoria_id
  WHERE p.status = 'ATIVO' AND p.estoque <= p.minimo
  ORDER BY (p.minimo - p.estoque) DESC, p.nome ASC
  LIMIT 12
");
$estoqueBaixo = $st->fetchAll();

// Total 12m (para header do Chart1)
$total12m = array_sum($chart1);
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
  <meta charset="UTF-8" />
  <meta http-equiv="X-UA-Compatible" content="IE=edge" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />

  <link rel="shortcut icon" href="assets/images/favicon.svg" type="image/x-icon" />
  <title>Painel da Distribuidora | Dashboard</title>

  <!-- ========== CSS ========= -->
  <link rel="stylesheet" href="assets/css/bootstrap.min.css" />
  <link rel="stylesheet" href="assets/css/lineicons.css" rel="stylesheet" type="text/css" />
  <link rel="stylesheet" href="assets/css/materialdesignicons.min.css" rel="stylesheet" type="text/css" />
  <link rel="stylesheet" href="assets/css/main.css" />

  <style>
    .equal-row {
      align-items: stretch
    }

    .equal-row>[class^="col-"],
    .equal-row>[class*=" col-"] {
      display: flex
    }

    .icon-card,
    .card-style {
      height: 100%;
      width: 100%
    }

    .card-style {
      display: flex;
      flex-direction: column
    }

    .card-style .chart,
    .card-style .table-responsive,
    .card-style .card-body-flex {
      flex: 1 1 auto
    }

    .chart {
      position: relative;
      min-height: 320px
    }

    .chart canvas {
      height: 100% !important
    }

    .table-responsive {
      padding-bottom: 6px
    }

    .mini-metrics span {
      display: inline-flex;
      gap: 6px;
      align-items: center;
      padding: 4px 10px;
      border-radius: 999px;
      background: rgba(143, 146, 161, .08);
      font-size: 12px;
      color: #111827
    }

    .btn-icon {
      background: transparent;
      border: 0;
      padding: 0
    }

    .modal .table td,
    .modal .table th {
      vertical-align: middle
    }
  </style>
</head>

<body>
  <div id="preloader">
    <div class="spinner"></div>
  </div>

  <!-- ======== sidebar-nav start =========== -->
  <aside class="sidebar-nav-wrapper">
    <div class="navbar-logo">
      <a href="dashboard.php" class="d-flex align-items-center gap-2">
        <img src="assets/images/logo/logo.svg" alt="logo" />
      </a>
    </div>

    <nav class="sidebar-nav">
      <ul>
        <li class="nav-item active">
          <a href="dashboard.php" class="active">
            <span class="icon">
              <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M8.74999 18.3333C12.2376 18.3333 15.1364 15.8128 15.7244 12.4941C15.8448 11.8143 15.2737 11.25 14.5833 11.25H9.99999C9.30966 11.25 8.74999 10.6903 8.74999 10V5.41666C8.74999 4.7263 8.18563 4.15512 7.50586 4.27556C4.18711 4.86357 1.66666 7.76243 1.66666 11.25C1.66666 15.162 4.83797 18.3333 8.74999 18.3333Z" />
                <path d="M17.0833 10C17.7737 10 18.3432 9.43708 18.2408 8.75433C17.7005 5.14918 14.8508 2.29947 11.2457 1.75912C10.5629 1.6568 10 2.2263 10 2.91665V9.16666C10 9.62691 10.3731 10 10.8333 10H17.0833Z" />
              </svg>
            </span>
            <span class="text">Dashboard</span>
          </a>
        </li>

        <li class="nav-item nav-item-has-children">
          <a href="#0" class="collapsed" data-bs-toggle="collapse" data-bs-target="#ddmenu_operacoes" aria-controls="ddmenu_operacoes" aria-expanded="false">
            <span class="icon">
              <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M3.33334 3.35442C3.33334 2.4223 4.07954 1.66666 5.00001 1.66666H15C15.9205 1.66666 16.6667 2.4223 16.6667 3.35442V16.8565C16.6667 17.5519 15.8827 17.9489 15.3333 17.5317L13.8333 16.3924C13.537 16.1673 13.1297 16.1673 12.8333 16.3924L10.5 18.1646C10.2037 18.3896 9.79634 18.3896 9.50001 18.1646L7.16668 16.3924C6.87038 16.1673 6.46298 16.1673 6.16668 16.3924L4.66668 17.5317C4.11731 17.9489 3.33334 17.5519 3.33334 16.8565V3.35442Z" />
              </svg>
            </span>
            <span class="text">Operações</span>
          </a>
          <ul id="ddmenu_operacoes" class="collapse dropdown-nav">
            <li><a href="vendidos.php">vendidos</a></li>
            <li><a href="vendas.php">Vendas</a></li>
            <li><a href="devolucoes.php">Devoluções</a></li>
          </ul>
        </li>

        <li class="nav-item nav-item-has-children">
          <a href="#0" class="collapsed" data-bs-toggle="collapse" data-bs-target="#ddmenu_estoque" aria-controls="ddmenu_estoque" aria-expanded="false">
            <span class="icon">
              <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M2.49999 5.83331C2.03976 5.83331 1.66666 6.2064 1.66666 6.66665V10.8333C1.66666 13.5948 3.90523 15.8333 6.66666 15.8333H9.99999C12.1856 15.8333 14.0436 14.431 14.7235 12.4772C14.8134 12.4922 14.9058 12.5 15 12.5H16.6667C17.5872 12.5 18.3333 11.7538 18.3333 10.8333V8.33331C18.3333 7.41284 17.5872 6.66665 16.6667 6.66665H15C15 6.2064 14.6269 5.83331 14.1667 5.83331H2.49999Z" />
                <path d="M2.49999 16.6667C2.03976 16.6667 1.66666 17.0398 1.66666 17.5C1.66666 17.9602 2.03976 18.3334 2.49999 18.3334H14.1667C14.6269 18.3334 15 17.9602 15 17.5C15 17.0398 14.6269 16.6667 14.1667 16.6667H2.49999Z" />
              </svg>
            </span>
            <span class="text">Estoque</span>
          </a>
          <ul id="ddmenu_estoque" class="collapse dropdown-nav">
            <li><a href="produtos.php">Produtos</a></li>
            <li><a href="inventario.php">Inventário</a></li>
            <li><a href="entradas.php">Entradas</a></li>
            <li><a href="saidas.php">Saídas</a></li>
            <li><a href="estoque-minimo.php">Estoque Mínimo</a></li>
          </ul>
        </li>

        <li class="nav-item nav-item-has-children">
          <a href="#0" class="collapsed" data-bs-toggle="collapse" data-bs-target="#ddmenu_cadastros" aria-controls="ddmenu_cadastros" aria-expanded="false">
            <span class="icon">
              <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M1.66666 5.41669C1.66666 3.34562 3.34559 1.66669 5.41666 1.66669C7.48772 1.66669 9.16666 3.34562 9.16666 5.41669C9.16666 7.48775 7.48772 9.16669 5.41666 9.16669C3.34559 9.16669 1.66666 7.48775 1.66666 5.41669Z" />
                <path d="M1.66666 14.5834C1.66666 12.5123 3.34559 10.8334 5.41666 10.8334C7.48772 10.8334 9.16666 12.5123 9.16666 14.5834C9.16666 16.6545 7.48772 18.3334 5.41666 18.3334C3.34559 18.3334 1.66666 16.6545 1.66666 14.5834Z" />
                <path d="M10.8333 5.41669C10.8333 3.34562 12.5123 1.66669 14.5833 1.66669C16.6544 1.66669 18.3333 3.34562 18.3333 5.41669C18.3333 7.48775 16.6544 9.16669 14.5833 9.16669C12.5123 9.16669 10.8333 7.48775 10.8333 5.41669Z" />
                <path d="M10.8333 14.5834C10.8333 12.5123 12.5123 10.8334 14.5833 10.8334C16.6544 10.8334 18.3333 12.5123 18.3333 14.5834C18.3333 16.6545 16.6544 18.3334 14.5833 18.3334C12.5123 18.3334 10.8333 16.6545 10.8333 14.5834Z" />
              </svg>
            </span>
            <span class="text">Cadastros</span>
          </a>
          <ul id="ddmenu_cadastros" class="collapse dropdown-nav">
            <li><a href="clientes.php">Clientes</a></li>
            <li><a href="fornecedores.php">Fornecedores</a></li>
            <li><a href="categorias.php">Categorias</a></li>
          </ul>
        </li>

        <li class="nav-item">
          <a href="relatorios.php">
            <span class="icon">
              <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M4.16666 3.33335C4.16666 2.41288 4.91285 1.66669 5.83332 1.66669H14.1667C15.0872 1.66669 15.8333 2.41288 15.8333 3.33335V16.6667C15.8333 17.5872 15.0872 18.3334 14.1667 18.3334H5.83332C4.91285 18.3334 4.16666 17.5872 4.16666 16.6667V3.33335Z" />
              </svg>
            </span>
            <span class="text">Relatórios</span>
          </a>
        </li>

        <span class="divider">
          <hr />
        </span>

        <li class="nav-item nav-item-has-children">
          <a href="#0" class="collapsed" data-bs-toggle="collapse" data-bs-target="#ddmenu_config" aria-controls="ddmenu_config" aria-expanded="false">
            <span class="icon">
              <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M10 1.66669C5.39763 1.66669 1.66666 5.39766 1.66666 10C1.66666 14.6024 5.39763 18.3334 10 18.3334C14.6024 18.3334 18.3333 14.6024 18.3333 10C18.3333 5.39766 14.6024 1.66669 10 1.66669Z" />
              </svg>
            </span>
            <span class="text">Configurações</span>
          </a>
          <ul id="ddmenu_config" class="collapse dropdown-nav">
            <li><a href="usuarios.php">Usuários e Permissões</a></li>
            <li><a href="parametros.php">Parâmetros do Sistema</a></li>
          </ul>
        </li>

        <li class="nav-item">
          <a href="suporte.php">
            <span class="icon">
              <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M10.8333 2.50008C10.8333 2.03984 10.4602 1.66675 9.99999 1.66675C9.53975 1.66675 9.16666 2.03984 9.16666 2.50008C9.16666 2.96032 9.53975 3.33341 9.99999 3.33341C10.4602 3.33341 10.8333 2.96032 10.8333 2.50008Z" />
                <path d="M11.4272 2.69637C10.9734 2.56848 10.4947 2.50006 10 2.50006C7.10054 2.50006 4.75003 4.85057 4.75003 7.75006V9.20873C4.75003 9.72814 4.62082 10.2393 4.37404 10.6963L3.36705 12.5611C2.89938 13.4272 3.26806 14.5081 4.16749 14.9078C7.88074 16.5581 12.1193 16.5581 15.8326 14.9078C16.732 14.5081 17.1007 13.4272 16.633 12.5611L15.626 10.6963C15.43 10.3333 15.3081 9.93606 15.2663 9.52773C15.0441 9.56431 14.8159 9.58339 14.5833 9.58339C12.2822 9.58339 10.4167 7.71791 10.4167 5.41673C10.4167 4.37705 10.7975 3.42631 11.4272 2.69637Z" />
              </svg>
            </span>
            <span class="text">Suporte</span>
          </a>
        </li>
      </ul>
    </nav>
  </aside>

  <div class="overlay"></div>

  <main class="main-wrapper">
    <!-- Header -->
    <header class="header">
      <div class="container-fluid">
        <div class="row">
          <div class="col-lg-5 col-md-5 col-6">
            <div class="header-left d-flex align-items-center">
              <div class="menu-toggle-btn mr-15">
                <button id="menu-toggle" class="main-btn primary-btn btn-hover">
                  <i class="lni lni-chevron-left me-2"></i> Menu
                </button>
              </div>
              <div class="header-search d-none d-md-flex">
                <form action="#" onsubmit="return false;">
                  <input type="text" placeholder="Buscar cliente, pedido, produto..." />
                  <button type="submit"><i class="lni lni-search-alt"></i></button>
                </form>
              </div>
            </div>
          </div>

          <div class="col-lg-7 col-md-7 col-6">
            <div class="header-right">

              <div class="notification-box ml-15 d-none d-md-flex">
                <button class="dropdown-toggle" type="button" id="notification" data-bs-toggle="dropdown" aria-expanded="false">
                  <svg width="22" height="22" viewBox="0 0 22 22" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M11 20.1667C9.88317 20.1667 8.88718 19.63 8.23901 18.7917H13.761C13.113 19.63 12.1169 20.1667 11 20.1667Z" fill="" />
                    <path d="M10.1157 2.74999C10.1157 2.24374 10.5117 1.83333 11 1.83333C11.4883 1.83333 11.8842 2.24374 11.8842 2.74999V2.82604C14.3932 3.26245 16.3051 5.52474 16.3051 8.24999V14.287C16.3051 14.5301 16.3982 14.7633 16.564 14.9352L18.2029 16.6342C18.4814 16.9229 18.2842 17.4167 17.8903 17.4167H4.10961C3.71574 17.4167 3.5185 16.9229 3.797 16.6342L5.43589 14.9352C5.6017 14.7633 5.69485 14.5301 5.69485 14.287V8.24999C5.69485 5.52474 7.60672 3.26245 10.1157 2.82604V2.74999Z" fill="" />
                  </svg>
                  <span></span>
                </button>

                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="notification">
                  <li>
                    <a href="estoque-minimo.php">
                      <div class="content">
                        <h6>Estoque mínimo <span class="text-regular">em <?= (int)count($estoqueBaixo) ?> itens.</span></h6>
                        <p>Confira “Estoque Mínimo”.</p>
                        <span>Atualizado agora</span>
                      </div>
                    </a>
                  </li>
                </ul>
              </div>

              <div class="profile-box ml-15">
                <button class="dropdown-toggle bg-transparent border-0" type="button" id="profile" data-bs-toggle="dropdown" aria-expanded="false">
                  <div class="profile-info">
                    <div class="info">
                      <div class="image">
                        <img src="assets/images/profile/profile-image.png" alt="perfil" />
                      </div>
                      <div>
                        <h6 class="fw-500">Administrador</h6>
                        <p>Distribuidora</p>
                      </div>
                    </div>
                  </div>
                </button>

                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="profile">
                  <li>
                    <div class="author-info flex items-center !p-1">
                      <div class="image"><img src="assets/images/profile/profile-image.png" alt="image" /></div>
                      <div class="content">
                        <h4 class="text-sm">Administrador</h4>
                        <a class="text-black/40 dark:text-white/40 hover:text-black dark:hover:text-white text-xs" href="#">Admin</a>
                      </div>
                    </div>
                  </li>
                  <li class="divider"></li>
                  <li><a href="perfil.php"><i class="lni lni-user"></i> Meu Perfil</a></li>
                  <li><a href="usuarios.php"><i class="lni lni-cog"></i> Usuários</a></li>
                  <li class="divider"></li>
                  <li><a href="logout.php"><i class="lni lni-exit"></i> Sair</a></li>
                </ul>
              </div>

            </div>
          </div>

        </div>
      </div>
    </header>

    <!-- Conteúdo -->
    <section class="section">
      <div class="container-fluid">
        <div class="title-wrapper pt-30">
          <div class="row align-items-center">
            <div class="col-md-6">
              <div class="title">
                <h2>Dashboard (Vendas e Estoque)</h2>
                <div class="d-flex align-items-center gap-2 mt-2">
                  <span class="text-sm text-gray">Período rápido:</span>
                  <select id="periodSelect" class="form-select form-select-sm" style="max-width: 180px;">
                    <option value="today" <?= $period === 'today' ? 'selected' : ''; ?>>Hoje</option>
                    <option value="7" <?= $period === '7' ? 'selected' : ''; ?>>Últimos 7 dias</option>
                    <option value="30" <?= $period === '30' ? 'selected' : ''; ?>>Últimos 30 dias</option>
                  </select>
                  <span class="text-sm text-gray">
                    (impacta: Delivery x Presencial, Pagamentos, Vendas Recentes)
                  </span>
                </div>
              </div>
            </div>
            <div class="col-md-6">
              <div class="breadcrumb-wrapper">
                <nav aria-label="breadcrumb">
                  <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Visão Geral</li>
                  </ol>
                </nav>
              </div>
            </div>
          </div>
        </div>

        <!-- KPIs -->
        <div class="row equal-row">
          <div class="col-xl-3 col-lg-4 col-sm-6 mb-30">
            <div class="icon-card">
              <div class="icon purple"><i class="lni lni-cart-full"></i></div>
              <div class="content">
                <h6 class="mb-10">Vendas Hoje</h6>
                <h3 class="text-bold mb-10"><?= nint($vendasHoje) ?></h3>
                <?php $cls = ($vendasHojePct >= 0) ? 'text-success' : 'text-danger'; ?>
                <p class="text-sm <?= $cls ?>">
                  <i class="lni <?= ($vendasHojePct >= 0) ? 'lni-arrow-up' : 'lni-arrow-down' ?>"></i>
                  <?= number_format(abs($vendasHojePct), 1, ',', '.') ?>%
                  <span class="text-gray">(vs. ontem)</span>
                </p>
              </div>
            </div>
          </div>

          <div class="col-xl-3 col-lg-4 col-sm-6 mb-30">
            <div class="icon-card">
              <div class="icon success"><i class="lni lni-dollar"></i></div>
              <div class="content">
                <h6 class="mb-10">Faturamento (Mês)</h6>
                <h3 class="text-bold mb-10"><?= brl($faturMes) ?></h3>
                <?php $cls = ($faturMesPct >= 0) ? 'text-success' : 'text-danger'; ?>
                <p class="text-sm <?= $cls ?>">
                  <i class="lni <?= ($faturMesPct >= 0) ? 'lni-arrow-up' : 'lni-arrow-down' ?>"></i>
                  <?= number_format(abs($faturMesPct), 1, ',', '.') ?>%
                  <span class="text-gray">Crescimento</span>
                </p>
              </div>
            </div>
          </div>

          <div class="col-xl-3 col-lg-4 col-sm-6 mb-30">
            <div class="icon-card">
              <div class="icon primary"><i class="lni lni-package"></i></div>
              <div class="content">
                <h6 class="mb-10">Itens em Estoque</h6>
                <h3 class="text-bold mb-10"><?= nint($itensEstoque) ?></h3>
                <?php $cls = ($saidasPct >= 0) ? 'text-danger' : 'text-success'; ?>
                <p class="text-sm <?= $cls ?>">
                  <i class="lni <?= ($saidasPct >= 0) ? 'lni-arrow-up' : 'lni-arrow-down' ?>"></i>
                  <?= number_format(abs($saidasPct), 1, ',', '.') ?>%
                  <span class="text-gray">Saídas (30d)</span>
                </p>
              </div>
            </div>
          </div>

          <div class="col-xl-3 col-lg-4 col-sm-6 mb-30">
            <div class="icon-card">
              <div class="icon orange"><i class="lni lni-calculator"></i></div>
              <div class="content">
                <h6 class="mb-10">Ticket Médio (Hoje)</h6>
                <h3 class="text-bold mb-10"><?= brl($tickHoje) ?></h3>
                <?php $cls = ($tickPct >= 0) ? 'text-success' : 'text-danger'; ?>
                <p class="text-sm <?= $cls ?>">
                  <i class="lni <?= ($tickPct >= 0) ? 'lni-arrow-up' : 'lni-arrow-down' ?>"></i>
                  <?= number_format(abs($tickPct), 1, ',', '.') ?>%
                  <span class="text-gray">média</span>
                </p>
              </div>
            </div>
          </div>
        </div>

        <!-- Charts 1 e 2 -->
        <div class="row equal-row">
          <div class="col-lg-7 mb-30">
            <div class="card-style">
              <div class="title d-flex flex-wrap justify-content-between">
                <div class="left">
                  <h6 class="text-medium mb-10">Faturamento (12 meses)</h6>
                  <h3 class="text-bold"><?= brl($total12m) ?></h3>
                </div>
                <div class="right">
                  <div class="select-style-1">
                    <div class="select-position select-sm">
                      <select class="light-bg" disabled>
                        <option>Anual (12m)</option>
                      </select>
                    </div>
                  </div>
                </div>
              </div>
              <div class="chart">
                <canvas id="Chart1"></canvas>
              </div>
            </div>
          </div>

          <div class="col-lg-5 mb-30">
            <div class="card-style">
              <div class="title d-flex flex-wrap align-items-center justify-content-between">
                <div class="left">
                  <h6 class="text-medium mb-30">Vendas por Mês (12m)</h6>
                </div>
                <div class="right">
                  <div class="select-style-1">
                    <div class="select-position select-sm">
                      <select class="light-bg" disabled>
                        <option>Últimos 12 meses</option>
                      </select>
                    </div>
                  </div>
                </div>
              </div>

              <div class="chart">
                <canvas id="Chart2"></canvas>
              </div>
            </div>
          </div>
        </div>

        <!-- Entrega x Presencial + Vendas Recentes -->
        <div class="row equal-row">
          <div class="col-lg-5 mb-30">
            <div class="card-style">
              <div class="title d-flex flex-wrap justify-content-between align-items-center">
                <div class="left">
                  <h6 class="text-medium mb-10">Forma de Entrega</h6>
                  <p class="text-sm text-gray mb-0">Delivery x Presencial (período)</p>
                </div>
                <div class="right">
                  <div class="select-style-1">
                    <div class="select-position select-sm">
                      <select class="light-bg" disabled>
                        <option><?= $period === 'today' ? 'Hoje' : ($period === '7' ? '7 dias' : '30 dias') ?></option>
                      </select>
                    </div>
                  </div>
                </div>
              </div>

              <div class="chart">
                <canvas id="Chart5"></canvas>
              </div>

              <div class="mini-metrics d-flex flex-wrap gap-2 mt-10">
                <span><b>Delivery</b> <?= (int)$deliveryQtd ?></span>
                <span><b>Presencial</b> <?= (int)$presencialQtd ?></span>
              </div>
            </div>
          </div>

          <div class="col-lg-7 mb-30">
            <div class="card-style">
              <div class="title d-flex flex-wrap justify-content-between align-items-center">
                <div class="left">
                  <h6 class="text-medium mb-30">Vendas Recentes (período)</h6>
                </div>
                <div class="right">
                  <div class="select-style-1">
                    <div class="select-position select-sm">
                      <select class="light-bg" disabled>
                        <option><?= $period === 'today' ? 'Hoje' : ($period === '7' ? 'Últimos 7 dias' : 'Últimos 30 dias') ?></option>
                      </select>
                    </div>
                  </div>
                </div>
              </div>

              <div class="table-responsive">
                <table class="table top-selling-table">
                  <thead>
                    <tr>
                      <th>
                        <h6 class="text-sm text-medium">Venda</h6>
                      </th>
                      <th class="min-width">
                        <h6 class="text-sm text-medium">Cliente</h6>
                      </th>
                      <th class="min-width">
                        <h6 class="text-sm text-medium">Entrega</h6>
                      </th>
                      <th class="min-width">
                        <h6 class="text-sm text-medium">Pagamento</h6>
                      </th>
                      <th class="min-width">
                        <h6 class="text-sm text-medium">Valor</h6>
                      </th>
                      <th class="min-width">
                        <h6 class="text-sm text-medium">Data</h6>
                      </th>
                      <th></th>
                    </tr>
                  </thead>

                  <tbody>
                    <?php if (!$vendasRecentes): ?>
                      <tr>
                        <td colspan="7">
                          <p class="text-sm text-gray mb-0">Sem vendas no período.</p>
                        </td>
                      </tr>
                    <?php else: ?>
                      <?php foreach ($vendasRecentes as $v): ?>
                        <?php
                        $id = (int)$v['id'];
                        $vendano = '#V-' . str_pad((string)$id, 5, '0', STR_PAD_LEFT);
                        $cli = trim((string)($v['cliente'] ?? ''));
                        if ($cli === '') $cli = '—';
                        $canal = strtoupper((string)$v['canal']);
                        $badge = ($canal === 'DELIVERY') ? 'warning-btn' : 'success-btn';
                        ?>
                        <tr>
                          <td>
                            <p class="text-sm"><?= e($vendano) ?></p>
                          </td>
                          <td>
                            <p class="text-sm"><?= e($cli) ?></p>
                          </td>
                          <td><span class="status-btn <?= $badge ?>"><?= e(ucfirst(strtolower($canal))) ?></span></td>
                          <td>
                            <p class="text-sm"><?= e((string)$v['pagamento']) ?></p>
                          </td>
                          <td>
                            <p class="text-sm"><?= brl($v['total']) ?></p>
                          </td>
                          <td>
                            <p class="text-sm"><?= e(dt((string)$v['data'])) ?></p>
                          </td>
                          <td>
                            <div class="action justify-content-end">
                              <button class="edit btn-view-venda" type="button" title="Ver" data-id="<?= (int)$id ?>">
                                <i class="lni lni-eye"></i>
                              </button>
                            </div>
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    <?php endif; ?>
                  </tbody>
                </table>
              </div>

            </div>
          </div>
        </div>

        <!-- Mix + Entradas/Saídas -->
        <div class="row equal-row">
          <div class="col-lg-7 mb-30">
            <div class="card-style">
              <div class="title d-flex flex-wrap align-items-center justify-content-between">
                <div class="left">
                  <h6 class="text-medium mb-2">Mix (Saídas) + Devoluções (12m)</h6>
                  <p class="text-sm text-gray mb-0">Canais (top 2) em <b>saidas</b> + devoluções</p>
                </div>
                <div class="right">
                  <div class="select-style-1 mb-2">
                    <div class="select-position select-sm">
                      <select class="light-bg" disabled>
                        <option>Últimos 12 meses</option>
                      </select>
                    </div>
                  </div>
                </div>
              </div>

              <div class="chart">
                <canvas id="Chart3"></canvas>
              </div>
            </div>
          </div>

          <div class="col-lg-5 mb-30">
            <div class="card-style">
              <div class="title d-flex flex-wrap align-items-center justify-content-between">
                <div class="left">
                  <h6 class="text-medium mb-2">Entradas x Saídas (Estoque)</h6>
                  <p class="text-sm text-gray mb-0">Somatório de qtd (6 meses)</p>
                </div>
                <div class="right">
                  <div class="select-style-1 mb-2">
                    <div class="select-position select-sm">
                      <select class="light-bg" disabled>
                        <option>Últimos 6 meses</option>
                      </select>
                    </div>
                  </div>
                </div>
              </div>

              <div class="chart">
                <canvas id="Chart4"></canvas>
              </div>
            </div>
          </div>
        </div>

        <!-- Pagamentos + Top produtos -->
        <div class="row equal-row">
          <div class="col-lg-5 mb-30">
            <div class="card-style">
              <div class="title d-flex flex-wrap align-items-center justify-content-between">
                <div class="left">
                  <h6 class="text-medium mb-10">Formas de Pagamento</h6>
                  <p class="text-sm text-gray mb-0">Mais utilizadas (período)</p>
                </div>
                <div class="right">
                  <div class="select-style-1">
                    <div class="select-position select-sm">
                      <select class="light-bg" disabled>
                        <option><?= $period === 'today' ? 'Hoje' : ($period === '7' ? '7 dias' : '30 dias') ?></option>
                      </select>
                    </div>
                  </div>
                </div>
              </div>

              <div class="chart">
                <canvas id="Chart6"></canvas>
              </div>
            </div>
          </div>

          <div class="col-lg-7 mb-30">
            <div class="card-style">
              <div class="title d-flex flex-wrap align-items-center justify-content-between">
                <div class="left">
                  <h6 class="text-medium mb-10">Top Produtos Vendidos</h6>
                  <p class="text-sm text-gray mb-0">Quantidade (<?= (int)$topDays ?> dias)</p>
                </div>
                <div class="right d-flex align-items-center gap-2">
                  <select id="topSelect" class="form-select form-select-sm" style="max-width: 170px;">
                    <option value="7" <?= $topDays === 7 ? 'selected' : ''; ?>>Últimos 7 dias</option>
                    <option value="30" <?= $topDays === 30 ? 'selected' : ''; ?>>Últimos 30 dias</option>
                    <option value="90" <?= $topDays === 90 ? 'selected' : ''; ?>>Últimos 90 dias</option>
                  </select>
                  <a href="relatorios.php" class="main-btn primary-btn btn-hover btn-sm">Ver relatório</a>
                </div>
              </div>

              <div class="chart">
                <canvas id="Chart7"></canvas>
              </div>
            </div>
          </div>
        </div>

        <!-- Estoque baixo -->
        <div class="row equal-row">
          <div class="col-lg-12 mb-30">
            <div class="card-style">
              <div class="title d-flex flex-wrap align-items-center justify-content-between">
                <div class="left">
                  <h6 class="text-medium mb-30">Produtos com Estoque Baixo</h6>
                </div>
                <div class="right">
                  <a href="estoque-minimo.php" class="main-btn primary-btn btn-hover btn-sm">Ver detalhes</a>
                </div>
              </div>

              <div class="table-responsive">
                <table class="table top-selling-table">
                  <thead>
                    <tr>
                      <th>
                        <h6 class="text-sm text-medium">Produto</h6>
                      </th>
                      <th class="min-width">
                        <h6 class="text-sm text-medium">Categoria</h6>
                      </th>
                      <th class="min-width">
                        <h6 class="text-sm text-medium">Saldo</h6>
                      </th>
                      <th class="min-width">
                        <h6 class="text-sm text-medium">Mínimo</h6>
                      </th>
                      <th>
                        <h6 class="text-sm text-medium text-end">Ação</h6>
                      </th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php if (!$estoqueBaixo): ?>
                      <tr>
                        <td colspan="5">
                          <p class="text-sm text-gray mb-0">Nenhum produto abaixo do mínimo.</p>
                        </td>
                      </tr>
                    <?php else: ?>
                      <?php foreach ($estoqueBaixo as $p): ?>
                        <tr>
                          <td>
                            <p class="text-sm"><?= e((string)$p['nome']) ?></p>
                          </td>
                          <td>
                            <p class="text-sm"><?= e((string)$p['categoria']) ?></p>
                          </td>
                          <td>
                            <p class="text-sm"><?= e((string)$p['estoque']) ?></p>
                          </td>
                          <td>
                            <p class="text-sm"><?= e((string)$p['minimo']) ?></p>
                          </td>
                          <td>
                            <div class="action justify-content-end">
                              <button class="edit btn-view-produto" type="button" title="Detalhes" data-id="<?= (int)$p['id'] ?>">
                                <i class="lni lni-eye"></i>
                              </button>
                            </div>
                          </td>
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
    </section>

    <footer class="footer">
      <div class="container-fluid">
        <div class="row">
          <div class="col-md-6 order-last order-md-first">
            <div class="copyright text-center text-md-start">
              <p class="text-sm">Painel da Distribuidora • <span class="text-gray">v1.0</span></p>
            </div>
          </div>
          <div class="col-md-6">
            <div class="terms d-flex justify-content-center justify-content-md-end">
              <a href="#0" class="text-sm">Termos</a>
              <a href="#0" class="text-sm ml-15">Privacidade</a>
            </div>
          </div>
        </div>
      </div>
    </footer>
  </main>

  <!-- ====== MODAL VENDA ====== -->
  <div class="modal fade" id="modalVenda" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Detalhes da Venda</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
        </div>
        <div class="modal-body">
          <div id="vendaLoading" class="text-sm text-gray">Carregando...</div>
          <div id="vendaBody" style="display:none;">
            <div class="row g-3">
              <div class="col-md-4">
                <div class="border rounded p-2">
                  <div class="text-xs text-gray">Venda</div>
                  <div class="fw-600" id="vId">-</div>
                </div>
              </div>
              <div class="col-md-4">
                <div class="border rounded p-2">
                  <div class="text-xs text-gray">Data</div>
                  <div class="fw-600" id="vData">-</div>
                </div>
              </div>
              <div class="col-md-4">
                <div class="border rounded p-2">
                  <div class="text-xs text-gray">Canal</div>
                  <div class="fw-600" id="vCanal">-</div>
                </div>
              </div>

              <div class="col-md-8">
                <div class="border rounded p-2">
                  <div class="text-xs text-gray">Cliente</div>
                  <div class="fw-600" id="vCliente">-</div>
                </div>
              </div>
              <div class="col-md-4">
                <div class="border rounded p-2">
                  <div class="text-xs text-gray">Pagamento</div>
                  <div class="fw-600" id="vPagamento">-</div>
                </div>
              </div>

              <div class="col-md-12">
                <div class="border rounded p-2">
                  <div class="text-xs text-gray">Endereço / Observações</div>
                  <div class="text-sm" id="vObs">-</div>
                </div>
              </div>

              <div class="col-md-4">
                <div class="border rounded p-2">
                  <div class="text-xs text-gray">Subtotal</div>
                  <div class="fw-600" id="vSubtotal">-</div>
                </div>
              </div>
              <div class="col-md-4">
                <div class="border rounded p-2">
                  <div class="text-xs text-gray">Desconto</div>
                  <div class="fw-600" id="vDesconto">-</div>
                </div>
              </div>
              <div class="col-md-4">
                <div class="border rounded p-2">
                  <div class="text-xs text-gray">Total</div>
                  <div class="fw-600" id="vTotal">-</div>
                </div>
              </div>
            </div>

            <hr class="my-3" />

            <h6 class="text-medium mb-2">Devoluções vinculadas</h6>
            <div class="table-responsive">
              <table class="table">
                <thead>
                  <tr>
                    <th>#</th>
                    <th>Data</th>
                    <th>Hora</th>
                    <th>Tipo</th>
                    <th>Produto</th>
                    <th>Qtd</th>
                    <th>Valor</th>
                    <th>Status</th>
                  </tr>
                </thead>
                <tbody id="vDevolBody">
                  <tr>
                    <td colspan="8" class="text-sm text-gray">Nenhuma devolução.</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <div id="vendaError" class="text-sm text-danger" style="display:none;"></div>
        </div>
      </div>
    </div>
  </div>

  <!-- ====== MODAL PRODUTO ====== -->
  <div class="modal fade" id="modalProduto" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Detalhes do Produto</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
        </div>
        <div class="modal-body">
          <div id="prodLoading" class="text-sm text-gray">Carregando...</div>
          <div id="prodBody" style="display:none;">
            <div class="row g-3">
              <div class="col-md-6">
                <div class="border rounded p-2">
                  <div class="text-xs text-gray">Produto</div>
                  <div class="fw-600" id="pNome">-</div>
                  <div class="text-sm text-gray" id="pCodigo">-</div>
                </div>
              </div>
              <div class="col-md-3">
                <div class="border rounded p-2">
                  <div class="text-xs text-gray">Estoque</div>
                  <div class="fw-600" id="pEstoque">-</div>
                </div>
              </div>
              <div class="col-md-3">
                <div class="border rounded p-2">
                  <div class="text-xs text-gray">Mínimo</div>
                  <div class="fw-600" id="pMinimo">-</div>
                </div>
              </div>

              <div class="col-md-4">
                <div class="border rounded p-2">
                  <div class="text-xs text-gray">Categoria</div>
                  <div class="fw-600" id="pCategoria">-</div>
                </div>
              </div>
              <div class="col-md-4">
                <div class="border rounded p-2">
                  <div class="text-xs text-gray">Unidade</div>
                  <div class="fw-600" id="pUnidade">-</div>
                </div>
              </div>
              <div class="col-md-4">
                <div class="border rounded p-2">
                  <div class="text-xs text-gray">Preço</div>
                  <div class="fw-600" id="pPreco">-</div>
                </div>
              </div>

              <div class="col-md-12">
                <div class="border rounded p-2">
                  <div class="text-xs text-gray">Observação</div>
                  <div class="text-sm" id="pObs">-</div>
                </div>
              </div>

              <div class="col-md-12 d-flex justify-content-end gap-2">
                <a href="entradas.php" class="main-btn primary-btn btn-hover btn-sm">Lançar entrada</a>
                <a href="produtos.php" class="main-btn light-btn btn-hover btn-sm">Abrir produtos</a>
              </div>
            </div>
          </div>

          <div id="prodError" class="text-sm text-danger" style="display:none;"></div>
        </div>
      </div>
    </div>
  </div>

  <!-- ========= JS ========= -->
  <script src="assets/js/bootstrap.bundle.min.js"></script>
  <script src="assets/js/Chart.min.js"></script>
  <script src="assets/js/polyfill.js"></script>
  <script src="assets/js/main.js"></script>

  <script>
    // Period selects -> reload com querystring
    (function() {
      const sel = document.getElementById('periodSelect');
      if (sel) {
        sel.addEventListener('change', function() {
          const url = new URL(window.location.href);
          url.searchParams.set('period', sel.value);
          window.location.href = url.toString();
        });
      }
      const topSel = document.getElementById('topSelect');
      if (topSel) {
        topSel.addEventListener('change', function() {
          const url = new URL(window.location.href);
          url.searchParams.set('top', topSel.value);
          window.location.href = url.toString();
        });
      }
    })();
  </script>

  <!-- ✅ Theme fix + dados do PHP -->
  <script>
    (function() {
      const TEXT = "#111827";
      const GRID = "rgba(17,24,39,.10)";
      if (!window.Chart) return;

      if (Chart.defaults) {
        if ("color" in Chart.defaults) Chart.defaults.color = TEXT;
        if ("borderColor" in Chart.defaults) Chart.defaults.borderColor = GRID;

        if (Chart.defaults.scale) {
          Chart.defaults.scale.ticks.color = TEXT;
          Chart.defaults.scale.grid.color = GRID;
        }

        if (Chart.defaults.plugins?.legend?.labels) {
          Chart.defaults.plugins.legend.labels.color = TEXT;
        }
      }

      if (Chart.defaults?.global) {
        Chart.defaults.global.defaultFontColor = TEXT;
      }

      window.__CHART_THEME__ = {
        TEXT,
        GRID
      };
    })();

    const THEME = window.__CHART_THEME__ || {
      TEXT: "#111827",
      GRID: "rgba(17,24,39,.10)"
    };

    const fmtBRL = (v) => {
      try {
        return new Intl.NumberFormat("pt-BR", {
          style: "currency",
          currency: "BRL"
        }).format(v);
      } catch (e) {
        return "R$ " + String(v);
      }
    };

    // ==== DATA do PHP ====
    const LABELS_12 = <?= json_encode($labels12, JSON_UNESCAPED_UNICODE) ?>;
    const CHART1_DATA = <?= json_encode($chart1, JSON_UNESCAPED_UNICODE) ?>;
    const CHART2_DATA = <?= json_encode($chart2, JSON_UNESCAPED_UNICODE) ?>;

    const MIX_LABEL_A = <?= json_encode($mixA, JSON_UNESCAPED_UNICODE) ?>;
    const MIX_LABEL_B = <?= json_encode($mixB, JSON_UNESCAPED_UNICODE) ?>;
    const CHART3_A = <?= json_encode($chart3A, JSON_UNESCAPED_UNICODE) ?>;
    const CHART3_B = <?= json_encode($chart3B, JSON_UNESCAPED_UNICODE) ?>;
    const CHART3_C = <?= json_encode($chart3C, JSON_UNESCAPED_UNICODE) ?>;

    const LABELS_6 = <?= json_encode($labels6, JSON_UNESCAPED_UNICODE) ?>;
    const CHART4_ENT = <?= json_encode($chart4Ent, JSON_UNESCAPED_UNICODE) ?>;
    const CHART4_SAI = <?= json_encode($chart4Sai, JSON_UNESCAPED_UNICODE) ?>;

    const DELIV = <?= (int)$deliveryQtd ?>;
    const PRES = <?= (int)$presencialQtd ?>;

    const PAY_LABELS = <?= json_encode($payLabels, JSON_UNESCAPED_UNICODE) ?>;
    const PAY_DATA = <?= json_encode($payData, JSON_UNESCAPED_UNICODE) ?>;

    const TOP_LABELS = <?= json_encode($topProdLabels, JSON_UNESCAPED_UNICODE) ?>;
    const TOP_DATA = <?= json_encode($topProdData, JSON_UNESCAPED_UNICODE) ?>;
  </script>

  <script>
    // Chart 1 (Faturamento 12m)
    const ctx1 = document.getElementById("Chart1")?.getContext("2d");
    if (ctx1) {
      new Chart(ctx1, {
        type: "line",
        data: {
          labels: LABELS_12,
          datasets: [{
            label: "Faturamento",
            backgroundColor: "transparent",
            borderColor: "#365CF5",
            data: CHART1_DATA,
            pointBackgroundColor: "transparent",
            pointHoverBackgroundColor: "#365CF5",
            pointBorderColor: "transparent",
            pointHoverBorderColor: "#fff",
            pointHoverBorderWidth: 4,
            borderWidth: 3,
            pointRadius: 3,
            pointHoverRadius: 5,
            tension: 0.35
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: {
              display: false
            },
            tooltip: {
              intersect: false,
              backgroundColor: "#fbfbfb",
              displayColors: false,
              titleColor: THEME.TEXT,
              bodyColor: THEME.TEXT,
              callbacks: {
                label: (c) => fmtBRL(c.parsed.y)
              }
            }
          },
          scales: {
            y: {
              grid: {
                color: THEME.GRID
              },
              ticks: {
                color: THEME.TEXT,
                callback: (v) => (Number(v) / 1000).toFixed(0) + "k"
              }
            },
            x: {
              grid: {
                color: THEME.GRID
              },
              ticks: {
                color: THEME.TEXT
              }
            }
          }
        }
      });
    }

    // Chart 2 (Vendas por mês)
    const ctx2 = document.getElementById("Chart2")?.getContext("2d");
    if (ctx2) {
      new Chart(ctx2, {
        type: "bar",
        data: {
          labels: LABELS_12,
          datasets: [{
            label: "Vendas",
            backgroundColor: "#365CF5",
            borderRadius: 12,
            barThickness: 10,
            data: CHART2_DATA
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: {
              display: false
            },
            tooltip: {
              backgroundColor: "#F3F6F8",
              displayColors: false,
              titleColor: THEME.TEXT,
              bodyColor: THEME.TEXT,
              callbacks: {
                label: (c) => `${c.parsed.y} vendas`
              }
            }
          },
          scales: {
            y: {
              grid: {
                display: false
              },
              ticks: {
                color: THEME.TEXT
              }
            },
            x: {
              grid: {
                display: false
              },
              ticks: {
                color: THEME.TEXT
              }
            }
          }
        }
      });
    }

    // Chart 3 (Mix + Devoluções)
    const ctx3 = document.getElementById("Chart3")?.getContext("2d");
    if (ctx3) {
      new Chart(ctx3, {
        type: "line",
        data: {
          labels: LABELS_12,
          datasets: [{
              label: MIX_LABEL_A,
              backgroundColor: "transparent",
              borderColor: "#365CF5",
              data: CHART3_A,
              tension: 0.35,
              pointRadius: 2
            },
            {
              label: MIX_LABEL_B,
              backgroundColor: "transparent",
              borderColor: "#9b51e0",
              data: CHART3_B,
              tension: 0.35,
              pointRadius: 2
            },
            {
              label: "Devoluções",
              backgroundColor: "transparent",
              borderColor: "#f2994a",
              data: CHART3_C,
              tension: 0.35,
              pointRadius: 2
            }
          ]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: {
              display: true,
              labels: {
                color: THEME.TEXT
              }
            },
            tooltip: {
              intersect: false,
              backgroundColor: "#fbfbfb",
              displayColors: false,
              titleColor: THEME.TEXT,
              bodyColor: THEME.TEXT,
              callbacks: {
                label: (c) => `${c.dataset.label}: ${c.parsed.y}`
              }
            }
          },
          scales: {
            y: {
              grid: {
                display: false
              },
              ticks: {
                color: THEME.TEXT
              }
            },
            x: {
              grid: {
                color: THEME.GRID
              },
              ticks: {
                color: THEME.TEXT
              }
            }
          }
        }
      });
    }

    // Chart 4 (Entradas x Saídas)
    const ctx4 = document.getElementById("Chart4")?.getContext("2d");
    if (ctx4) {
      new Chart(ctx4, {
        type: "bar",
        data: {
          labels: LABELS_6,
          datasets: [{
              label: "Entradas",
              backgroundColor: "#365CF5",
              borderRadius: 12,
              data: CHART4_ENT
            },
            {
              label: "Saídas",
              backgroundColor: "#d50100",
              borderRadius: 12,
              data: CHART4_SAI
            }
          ]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: {
              display: true,
              labels: {
                color: THEME.TEXT
              }
            },
            tooltip: {
              backgroundColor: "#F3F6F8",
              displayColors: false,
              titleColor: THEME.TEXT,
              bodyColor: THEME.TEXT
            }
          },
          scales: {
            y: {
              grid: {
                display: false
              },
              ticks: {
                color: THEME.TEXT
              }
            },
            x: {
              grid: {
                display: false
              },
              ticks: {
                color: THEME.TEXT
              }
            }
          }
        }
      });
    }

    // Chart 5 (Delivery x Presencial)
    const ctx5 = document.getElementById("Chart5")?.getContext("2d");
    if (ctx5) {
      new Chart(ctx5, {
        type: "doughnut",
        data: {
          labels: ["Delivery", "Presencial"],
          datasets: [{
            data: [DELIV, PRES],
            backgroundColor: ["#365CF5", "#52C41A"],
            borderWidth: 0
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: {
              display: false
            },
            tooltip: {
              backgroundColor: "#F3F6F8",
              displayColors: false,
              titleColor: THEME.TEXT,
              bodyColor: THEME.TEXT
            }
          },
          cutout: "65%"
        }
      });
    }

    // Chart 6 (Pagamentos)
    const ctx6 = document.getElementById("Chart6")?.getContext("2d");
    if (ctx6) {
      new Chart(ctx6, {
        type: "bar",
        data: {
          labels: PAY_LABELS,
          datasets: [{
            label: "Quantidade",
            data: PAY_DATA,
            backgroundColor: "#365CF5",
            borderRadius: 10,
            barThickness: 18
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: {
              display: false
            },
            tooltip: {
              backgroundColor: "#F3F6F8",
              displayColors: false,
              titleColor: THEME.TEXT,
              bodyColor: THEME.TEXT
            }
          },
          scales: {
            y: {
              grid: {
                display: false
              },
              ticks: {
                color: THEME.TEXT
              }
            },
            x: {
              grid: {
                display: false
              },
              ticks: {
                color: THEME.TEXT
              }
            }
          }
        }
      });
    }

    // Chart 7 (Top produtos)
    const ctx7 = document.getElementById("Chart7")?.getContext("2d");
    if (ctx7) {
      new Chart(ctx7, {
        type: "bar",
        data: {
          labels: TOP_LABELS,
          datasets: [{
            label: "Qtd",
            data: TOP_DATA,
            backgroundColor: "#365CF5",
            borderRadius: 10
          }]
        },
        options: {
          indexAxis: "y",
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: {
              display: false
            },
            tooltip: {
              backgroundColor: "#F3F6F8",
              displayColors: false,
              titleColor: THEME.TEXT,
              bodyColor: THEME.TEXT
            }
          },
          scales: {
            x: {
              grid: {
                color: THEME.GRID
              },
              ticks: {
                color: THEME.TEXT
              }
            },
            y: {
              grid: {
                display: false
              },
              ticks: {
                color: THEME.TEXT
              }
            }
          }
        }
      });
    }
  </script>

  <script>
    // ===== MODAIS (fetch JSON no próprio dashboard.php) =====
    const modalVenda = new bootstrap.Modal(document.getElementById('modalVenda'));
    const modalProduto = new bootstrap.Modal(document.getElementById('modalProduto'));

    function fmtBRL2(v) {
      try {
        return new Intl.NumberFormat("pt-BR", {
          style: "currency",
          currency: "BRL"
        }).format(Number(v || 0));
      } catch (e) {
        return "R$ " + String(v || 0);
      }
    }

    function safe(s) {
      return (s === null || s === undefined || String(s).trim() === '') ? '—' : String(s);
    }

    async function openVenda(id) {
      document.getElementById('vendaError').style.display = 'none';
      document.getElementById('vendaBody').style.display = 'none';
      document.getElementById('vendaLoading').style.display = 'block';
      modalVenda.show();

      try {
        const res = await fetch(`dashboard.php?action=venda&id=${encodeURIComponent(id)}`, {
          headers: {
            'Accept': 'application/json'
          }
        });
        const j = await res.json();
        if (!j.ok) throw new Error(j.error || 'Falha ao carregar venda.');

        const v = j.venda;
        document.getElementById('vId').textContent = '#V-' + String(v.id).padStart(5, '0');
        document.getElementById('vData').textContent = safe(v.data);
        document.getElementById('vCanal').textContent = safe(v.canal);
        document.getElementById('vCliente').textContent = safe(v.cliente);
        document.getElementById('vPagamento').textContent = safe(v.pagamento);
        document.getElementById('vSubtotal').textContent = fmtBRL2(v.subtotal);
        const desc = (v.desconto_tipo === 'VALOR') ?
          fmtBRL2(v.desconto_valor) :
          `${Number(v.desconto_valor||0).toFixed(2).replace('.',',')}%`;
        document.getElementById('vDesconto').textContent = `${desc} (taxa: ${fmtBRL2(v.taxa_entrega)})`;
        document.getElementById('vTotal').textContent = fmtBRL2(v.total);

        const obs = [
          v.endereco ? `Endereço: ${v.endereco}` : '',
          v.obs ? `Obs: ${v.obs}` : '',
          v.pagamento_json ? `Pagamento JSON: ${v.pagamento_json}` : ''
        ].filter(Boolean).join(' • ');
        document.getElementById('vObs').textContent = obs || '—';

        const tbody = document.getElementById('vDevolBody');
        const devols = Array.isArray(j.devolucoes) ? j.devolucoes : [];
        if (!devols.length) {
          tbody.innerHTML = `<tr><td colspan="8" class="text-sm text-gray">Nenhuma devolução.</td></tr>`;
        } else {
          tbody.innerHTML = devols.map(d => `
            <tr>
              <td>${d.id}</td>
              <td>${safe(d.data)}</td>
              <td>${safe(d.hora)}</td>
              <td>${safe(d.tipo)}</td>
              <td>${safe(d.produto)}</td>
              <td>${safe(d.qtd)}</td>
              <td>${fmtBRL2(d.valor)}</td>
              <td>${safe(d.status)}</td>
            </tr>
          `).join('');
        }

        document.getElementById('vendaLoading').style.display = 'none';
        document.getElementById('vendaBody').style.display = 'block';
      } catch (err) {
        document.getElementById('vendaLoading').style.display = 'none';
        const el = document.getElementById('vendaError');
        el.textContent = err.message || String(err);
        el.style.display = 'block';
      }
    }

    async function openProduto(id) {
      document.getElementById('prodError').style.display = 'none';
      document.getElementById('prodBody').style.display = 'none';
      document.getElementById('prodLoading').style.display = 'block';
      modalProduto.show();

      try {
        const res = await fetch(`dashboard.php?action=produto&id=${encodeURIComponent(id)}`, {
          headers: {
            'Accept': 'application/json'
          }
        });
        const j = await res.json();
        if (!j.ok) throw new Error(j.error || 'Falha ao carregar produto.');

        const p = j.produto;
        document.getElementById('pNome').textContent = safe(p.nome);
        document.getElementById('pCodigo').textContent = 'Código: ' + safe(p.codigo);
        document.getElementById('pEstoque').textContent = safe(p.estoque);
        document.getElementById('pMinimo').textContent = safe(p.minimo);
        document.getElementById('pCategoria').textContent = safe(p.categoria);
        document.getElementById('pUnidade').textContent = safe(p.unidade);
        document.getElementById('pPreco').textContent = fmtBRL2(p.preco);
        document.getElementById('pObs').textContent = safe(p.obs);

        document.getElementById('prodLoading').style.display = 'none';
        document.getElementById('prodBody').style.display = 'block';
      } catch (err) {
        document.getElementById('prodLoading').style.display = 'none';
        const el = document.getElementById('prodError');
        el.textContent = err.message || String(err);
        el.style.display = 'block';
      }
    }

    document.addEventListener('click', function(ev) {
      const btnVenda = ev.target.closest('.btn-view-venda');
      if (btnVenda) {
        ev.preventDefault();
        openVenda(btnVenda.getAttribute('data-id'));
        return;
      }
      const btnProd = ev.target.closest('.btn-view-produto');
      if (btnProd) {
        ev.preventDefault();
        openProduto(btnProd.getAttribute('data-id'));
        return;
      }
    });
  </script>
</body>

</html>