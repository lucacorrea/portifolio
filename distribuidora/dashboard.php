<?php

declare(strict_types=1);

@date_default_timezone_set('America/Manaus');
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

/* =========================
   CONEXÃO (db(): PDO)
========================= */
$possibleConn = [
  __DIR__ . '/assets/conexao.php',
  __DIR__ . '/assets/php/conexao.php',
  __DIR__ . '/assets/dados/_helpers.php',
];
foreach ($possibleConn as $f) {
  if (is_file($f)) require_once $f;
}

// (opcional) helpers do seu projeto
$helpers = __DIR__ . '/assets/dados/relatorios/__helpers.php';
if (is_file($helpers)) require_once $helpers;

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
if (!function_exists('e')) {
  function e(string $s): string
  {
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
  }
}

function brl($v): string
{
  return 'R$ ' . number_format((float)$v, 2, ',', '.');
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

function dtShort(string $ymd): string
{
  $ts = strtotime($ymd);
  return $ts ? date('d/m', $ts) : $ymd;
}

function monthPtShort(int $m): string
{
  static $map = [1 => 'Jan', 2 => 'Fev', 3 => 'Mar', 4 => 'Abr', 5 => 'Mai', 6 => 'Jun', 7 => 'Jul', 8 => 'Ago', 9 => 'Set', 10 => 'Out', 11 => 'Nov', 12 => 'Dez'];
  return isset($map[$m]) ? $map[$m] : (string)$m;
}

function buildMonthKeys(DateTimeImmutable $start, int $months): array
{
  $keys = [];
  for ($i = 0; $i < $months; $i++) $keys[] = $start->modify("+{$i} months")->format('Y-m');
  return $keys;
}

function buildMonthLabels(array $keys): array
{
  $labels = [];
  foreach ($keys as $ym) {
    $parts = explode('-', $ym);
    $y = (int)($parts[0] ?? 0);
    $m = (int)($parts[1] ?? 0);
    $labels[] = monthPtShort($m) . '/' . substr((string)$y, -2);
  }
  return $labels;
}

function fetchKeyVal(PDO $pdo, string $sql, array $params, string $keyCol, string $valCol): array
{
  $st = $pdo->prepare($sql);
  $st->execute($params);
  $out = [];
  while ($r = $st->fetch(PDO::FETCH_ASSOC)) $out[(string)$r[$keyCol]] = $r[$valCol];
  return $out;
}

/**
 * ✅ CORREÇÃO CRÍTICA:
 * Seu table_exists antigo com "SHOW TABLES LIKE :t" pode falhar com prepared statements (e virar sempre FALSE),
 * então a tela nunca buscava venda_itens/top produtos/entradas/saidas.
 * Aqui usamos information_schema (funciona em prepared statement).
 */
function table_exists(PDO $pdo, string $table): bool
{
  static $cache = [];
  if (array_key_exists($table, $cache)) return (bool)$cache[$table];

  try {
    $st = $pdo->prepare("
      SELECT COUNT(*)
      FROM information_schema.TABLES
      WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = :t
    ");
    $st->execute([':t' => $table]);
    $cache[$table] = ((int)$st->fetchColumn() > 0);
    return (bool)$cache[$table];
  } catch (Throwable $e) {
    $cache[$table] = false;
    return false;
  }
}

function pay_text_from_json(?string $json): string
{
  $json = trim((string)$json);
  if ($json === '') return '';

  $d = json_decode($json, true);
  if (json_last_error() !== JSON_ERROR_NONE || !is_array($d)) return '';

  $lines = [];

  $mode = strtoupper(trim((string)($d['mode'] ?? '')));
  $method = strtoupper(trim((string)($d['method'] ?? '')));
  if ($mode !== '')   $lines[] = "Modo: {$mode}";
  if ($method !== '') $lines[] = "Método: {$method}";

  if (array_key_exists('total', $d)) $lines[] = "Total: " . brl((float)$d['total']);
  if (array_key_exists('paid', $d))  $lines[] = "Pago: " . brl((float)$d['paid']);
  if (array_key_exists('troco', $d)) $lines[] = "Troco: " . brl((float)$d['troco']);

  if (isset($d['parts']) && is_array($d['parts']) && count($d['parts']) > 0) {
    $lines[] = "Partes:";
    foreach ($d['parts'] as $p) {
      if (!is_array($p)) continue;
      $m = strtoupper(trim((string)($p['method'] ?? $p['metodo'] ?? '')));
      $v = (float)($p['value'] ?? $p['valor'] ?? 0);
      if ($m === '') $m = '—';
      $lines[] = "• {$m}: " . brl($v);
    }
  }

  if (isset($d['fiado']) && is_array($d['fiado'])) {
    $hasEntry = (bool)($d['fiado']['has_entry'] ?? false);
    $entryVal = (float)($d['fiado']['entry_value'] ?? 0);
    $entryMet = (string)($d['fiado']['entry_method'] ?? '');
    $debtVal  = (float)($d['fiado']['debt_value'] ?? 0);

    $txt = "Fiado: SIM";
    if ($hasEntry) $txt .= " (entrada " . brl($entryVal) . ($entryMet ? " • {$entryMet}" : "") . ")";
    $lines[] = $txt;
    if ($debtVal > 0) $lines[] = "Restante: " . brl($debtVal);
  }

  return implode("\n", $lines);
}

function build_url(array $set = []): string
{
  $q = $_GET;
  foreach ($set as $k => $v) {
    if ($v === null) unset($q[$k]);
    else $q[$k] = $v;
  }
  $base = 'dashboard.php';
  return $q ? ($base . '?' . http_build_query($q)) : $base;
}

$HAS_DEVOL = table_exists($pdo, 'devolucoes');
$HAS_VENDA_ITENS = table_exists($pdo, 'venda_itens');
$HAS_SAIDAS = table_exists($pdo, 'saidas');
$HAS_ENTRADAS = table_exists($pdo, 'entradas');
$HAS_FIADOS = table_exists($pdo, 'fiados');
$HAS_FIADOS_PAG = table_exists($pdo, 'fiados_pagamentos');

function exclude_devolvidas_sql(bool $hasDevol, string $alias = 'v'): string
{
  if (!$hasDevol) return '1=1';
  return "NOT EXISTS (
    SELECT 1 FROM devolucoes d
    WHERE d.venda_no = {$alias}.id
      AND d.status <> 'CANCELADO'
  )";
}

$EXCL_V = exclude_devolvidas_sql($HAS_DEVOL, 'v');

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
      $v = $st->fetch(PDO::FETCH_ASSOC);
      if (!$v) throw new RuntimeException('Venda não encontrada.');

      // ✅ ITENS (venda_itens) — AGORA VAI (table_exists corrigido)
      $itens = [];
      $itens_total = 0.0;
      $itens_qtd = 0.0;

      if ($GLOBALS['HAS_VENDA_ITENS']) {
        // não depende de join pra não “sumir” item por qualquer motivo
        $stIt = $pdo->prepare("
          SELECT
            vi.id,
            vi.codigo,
            vi.nome,
            vi.unidade,
            vi.preco_unit,
            vi.qtd,
            vi.subtotal
          FROM venda_itens vi
          WHERE vi.venda_id = :id
          ORDER BY vi.id ASC
        ");
        $stIt->execute([':id' => $id]);
        $itens = $stIt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        foreach ($itens as $it) {
          $qtd = (float)($it['qtd'] ?? 0);
          $sub = (float)($it['subtotal'] ?? 0);
          $vu  = (float)($it['preco_unit'] ?? 0);
          if ($sub <= 0 && $qtd > 0) $sub = $vu * $qtd;
          $itens_total += $sub;
          $itens_qtd   += $qtd;
        }
      }

      // DEVOLUÇÕES vinculadas
      $devols = [];
      if ($GLOBALS['HAS_DEVOL']) {
        $st2 = $pdo->prepare("
          SELECT id, data, hora, tipo, produto, qtd, valor, motivo, obs, status
          FROM devolucoes
          WHERE venda_no = :id
          ORDER BY data DESC, hora DESC, id DESC
          LIMIT 50
        ");
        $st2->execute([':id' => $id]);
        $devols = $st2->fetchAll(PDO::FETCH_ASSOC) ?: [];
      }

      $payText = pay_text_from_json((string)($v['pagamento_json'] ?? ''));

      echo json_encode([
        'ok' => true,
        'venda' => $v,
        'pay_text' => $payText,
        'itens' => $itens,
        'itens_total' => $itens_total,
        'itens_qtd' => $itens_qtd,
        'devolucoes' => $devols
      ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
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
      $p = $st->fetch(PDO::FETCH_ASSOC);
      if (!$p) throw new RuntimeException('Produto não encontrado.');

      echo json_encode(['ok' => true, 'produto' => $p], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
      exit;
    }

    echo json_encode(['ok' => false, 'error' => 'Ação inválida.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
  } catch (Throwable $ex) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $ex->getMessage()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
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
$rangeStart = $days > 0 ? $today->modify("-" . ($days - 1) . " days") : $today;
$rangeEnd   = $today;

$rangeStartStr = $rangeStart->format('Y-m-d');
$rangeEndStr   = $rangeEnd->format('Y-m-d');
$yesterday = $today->modify('-1 day')->format('Y-m-d');

/* =========================
   KPIs (exclui devolvidas)
========================= */
$st = $pdo->prepare("
  SELECT
    COUNT(*) c,
    COALESCE(SUM(CASE WHEN v.pagamento = 'FIADO' THEN 0 ELSE v.total END), 0) as cash_vendas,
    COALESCE(SUM(v.total), 0) as total_vendas
  FROM vendas v
  WHERE v.data = :d
    AND {$EXCL_V}
");
$st->execute([':d' => $today->format('Y-m-d')]);
$resToday = $st->fetch(PDO::FETCH_ASSOC) ?: ['c' => 0, 'cash_vendas' => 0, 'total_vendas' => 0];

// Entradas de fiados “na venda do dia” (valor_pago em fiados)
$entradasHoje = 0.0;
if ($HAS_FIADOS) {
  $stEntrada = $pdo->prepare("
    SELECT COALESCE(SUM(f.valor_pago),0)
    FROM fiados f
    WHERE f.venda_id IN (
      SELECT v.id FROM vendas v
      WHERE v.data = :d AND {$EXCL_V}
    )
  ");
  $stEntrada->execute([':d' => $today->format('Y-m-d')]);
  $entradasHoje = (float)$stEntrada->fetchColumn();
}

// Pagamentos avulsos fiados (fiados_pagamentos)
$recFiadoHoje = 0.0;
if ($HAS_FIADOS_PAG) {
  $stPagFiado = $pdo->prepare("SELECT COALESCE(SUM(valor),0) FROM fiados_pagamentos WHERE DATE(created_at) = :d");
  $stPagFiado->execute([':d' => $today->format('Y-m-d')]);
  $recFiadoHoje = (float)$stPagFiado->fetchColumn();
}

$kToday = [
  'c' => (int)$resToday['c'],
  's' => (float)$resToday['cash_vendas'] + $entradasHoje + $recFiadoHoje,
  'total_bruto' => (float)$resToday['total_vendas']
];

$st->execute([':d' => $yesterday]);
$kYestRow = $st->fetch(PDO::FETCH_ASSOC) ?: ['c' => 0, 'cash_vendas' => 0, 'total_vendas' => 0];

$vendasHoje = (int)$kToday['c'];
$vendasOntem = (int)($kYestRow['c'] ?? 0);
$vendasHojePct = pct((float)$vendasHoje, (float)$vendasOntem);

// Faturamento mês atual / mês anterior (cash vendas + fiados pagos/entradas)
$firstThisMonth = $today->modify('first day of this month')->format('Y-m-d');
$firstNextMonth = $today->modify('first day of next month')->format('Y-m-d');
$firstPrevMonth = $today->modify('first day of last month')->format('Y-m-d');

$stMes = $pdo->prepare("
  SELECT COALESCE(SUM(CASE WHEN v.pagamento = 'FIADO' THEN 0 ELSE v.total END), 0) as cash_vendas
  FROM vendas v
  WHERE v.data >= :ini AND v.data < :fim
    AND {$EXCL_V}
");
$stMes->execute([':ini' => $firstThisMonth, ':fim' => $firstNextMonth]);
$cashVendasMes = (float)(($stMes->fetch(PDO::FETCH_ASSOC) ?: [])['cash_vendas'] ?? 0);

$entradasMes = 0.0;
if ($HAS_FIADOS) {
  $stEntMes = $pdo->prepare("SELECT COALESCE(SUM(valor_pago),0) FROM fiados WHERE created_at >= :ini AND created_at < :fim");
  $stEntMes->execute([':ini' => $firstThisMonth, ':fim' => $firstNextMonth]);
  $entradasMes = (float)$stEntMes->fetchColumn();
}

$recFiadoMes = 0.0;
if ($HAS_FIADOS_PAG) {
  $stPagMes = $pdo->prepare("SELECT COALESCE(SUM(valor),0) FROM fiados_pagamentos WHERE created_at >= :ini AND created_at < :fim");
  $stPagMes->execute([':ini' => $firstThisMonth, ':fim' => $firstNextMonth]);
  $recFiadoMes = (float)$stPagMes->fetchColumn();
}
$faturMes = $cashVendasMes + $entradasMes + $recFiadoMes;

// mês anterior
$stMes->execute([':ini' => $firstPrevMonth, ':fim' => $firstThisMonth]);
$cashVendasPrev = (float)(($stMes->fetch(PDO::FETCH_ASSOC) ?: [])['cash_vendas'] ?? 0);

$entradasPrev = 0.0;
if ($HAS_FIADOS) {
  $stEntMes->execute([':ini' => $firstPrevMonth, ':fim' => $firstThisMonth]);
  $entradasPrev = (float)$stEntMes->fetchColumn();
}

$recFiadoPrev = 0.0;
if ($HAS_FIADOS_PAG) {
  $stPagMes->execute([':ini' => $firstPrevMonth, ':fim' => $firstThisMonth]);
  $recFiadoPrev = (float)$stPagMes->fetchColumn();
}

$faturMesPrev = $cashVendasPrev + $entradasPrev + $recFiadoPrev;
$faturMesPct = pct($faturMes, $faturMesPrev);

// Itens em estoque (snapshot)
$itensEstoque = 0;
if (table_exists($pdo, 'produtos')) {
  $stE = $pdo->query("SELECT COALESCE(SUM(estoque),0) s FROM produtos WHERE status = 'ATIVO'");
  $itensEstoque = (int)(($stE->fetch(PDO::FETCH_ASSOC) ?: [])['s'] ?? 0);
}

// Perdas (saidas) 30d
$movDays = 30;
$movEnd = $today->format('Y-m-d');
$movStart = $today->modify("-" . ($movDays - 1) . " days")->format('Y-m-d');
$movPrevEnd = $today->modify("-{$movDays} days")->format('Y-m-d');
$movPrevStart = $today->modify("-" . (($movDays * 2) - 1) . " days")->format('Y-m-d');

$saidas30 = 0.0;
$saidas30Prev = 0.0;
if ($HAS_SAIDAS) {
  $stS = $pdo->prepare("SELECT COALESCE(SUM(qtd),0) s FROM saidas WHERE data BETWEEN :ini AND :fim");
  $stS->execute([':ini' => $movStart, ':fim' => $movEnd]);
  $saidas30 = (float)($stS->fetch(PDO::FETCH_ASSOC)['s'] ?? 0);

  $stS->execute([':ini' => $movPrevStart, ':fim' => $movPrevEnd]);
  $saidas30Prev = (float)($stS->fetch(PDO::FETCH_ASSOC)['s'] ?? 0);
}
$saidasPct = pct($saidas30, $saidas30Prev);

// Ticket médio hoje / ontem (exclui devolvidas)
$stT = $pdo->prepare("SELECT COALESCE(AVG(v.total),0) a FROM vendas v WHERE v.data = :d AND {$EXCL_V}");
$stT->execute([':d' => $today->format('Y-m-d')]);
$tickHoje = (float)(($stT->fetch(PDO::FETCH_ASSOC) ?: [])['a'] ?? 0);
$stT->execute([':d' => $yesterday]);
$tickOntem = (float)(($stT->fetch(PDO::FETCH_ASSOC) ?: [])['a'] ?? 0);
$tickPct = pct($tickHoje, $tickOntem);

/* =========================
   GRÁFICOS
========================= */
$start12 = $today->modify('first day of this month')->modify('-11 months');
$keys12 = buildMonthKeys($start12, 12);
$labels12 = buildMonthLabels($keys12);
$start12Str = $start12->format('Y-m-d');
$end12Str = $today->modify('first day of next month')->format('Y-m-d');

// Chart1: faturamento real 12m
$cashVendasMap = fetchKeyVal($pdo, "
   SELECT DATE_FORMAT(v.data,'%Y-%m') ym, COALESCE(SUM(CASE WHEN v.pagamento='FIADO' THEN 0 ELSE v.total END),0) s
   FROM vendas v
   WHERE v.data >= :ini AND v.data < :fim
     AND {$EXCL_V}
   GROUP BY ym
", [':ini' => $start12Str, ':fim' => $end12Str], 'ym', 's');

$entFiadoMap = [];
if ($HAS_FIADOS) {
  $entFiadoMap = fetchKeyVal($pdo, "
     SELECT DATE_FORMAT(created_at,'%Y-%m') ym, COALESCE(SUM(valor_pago),0) s
     FROM fiados
     WHERE created_at >= :ini AND created_at < :fim
     GROUP BY ym
  ", [':ini' => $start12Str, ':fim' => $end12Str], 'ym', 's');
}

$pagFiadoMap = [];
if ($HAS_FIADOS_PAG) {
  $pagFiadoMap = fetchKeyVal($pdo, "
     SELECT DATE_FORMAT(created_at,'%Y-%m') ym, COALESCE(SUM(valor),0) s
     FROM fiados_pagamentos
     WHERE created_at >= :ini AND created_at < :fim
     GROUP BY ym
  ", [':ini' => $start12Str, ':fim' => $end12Str], 'ym', 's');
}

$chart1 = [];
foreach ($keys12 as $k) {
  $chart1[] = (float)($cashVendasMap[$k] ?? 0) + (float)($entFiadoMap[$k] ?? 0) + (float)($pagFiadoMap[$k] ?? 0);
}

// Chart2: vendas por mês (exclui devolvidas)
$cntMap = fetchKeyVal(
  $pdo,
  "SELECT DATE_FORMAT(v.data,'%Y-%m') ym, COUNT(*) c
   FROM vendas v
   WHERE v.data >= :ini AND v.data < :fim
     AND {$EXCL_V}
   GROUP BY ym
   ORDER BY ym",
  [':ini' => $start12Str, ':fim' => $end12Str],
  'ym',
  'c'
);
$chart2 = [];
foreach ($keys12 as $k) $chart2[] = (int)($cntMap[$k] ?? 0);

/**
 * ✅ Chart3 (Mix Saídas + Devoluções) — CORRIGIDO PELO SEU BANCO
 * Saídas = saidas.valor_total (perdas/avarias)
 * Devoluções = devolucoes.valor (não canceladas)
 */
$mixLabelA = 'Saídas (Perdas)';
$mixLabelB = 'Devoluções';

$lossValMap = [];
if ($HAS_SAIDAS) {
  $lossValMap = fetchKeyVal(
    $pdo,
    "SELECT DATE_FORMAT(s.data,'%Y-%m') ym, COALESCE(SUM(s.valor_total),0) s
     FROM saidas s
     WHERE s.data >= :ini AND s.data < :fim
     GROUP BY ym
     ORDER BY ym",
    [':ini' => $start12Str, ':fim' => $end12Str],
    'ym',
    's'
  );
}

$devolValMap = [];
if ($HAS_DEVOL) {
  $devolValMap = fetchKeyVal(
    $pdo,
    "SELECT DATE_FORMAT(d.data,'%Y-%m') ym, COALESCE(SUM(d.valor),0) s
     FROM devolucoes d
     WHERE d.data >= :ini AND d.data < :fim
       AND d.status <> 'CANCELADO'
     GROUP BY ym
     ORDER BY ym",
    [':ini' => $start12Str, ':fim' => $end12Str],
    'ym',
    's'
  );
}

$chart3A = [];
$chart3B = [];
foreach ($keys12 as $k) {
  $chart3A[] = (float)($lossValMap[$k] ?? 0);
  $chart3B[] = (float)($devolValMap[$k] ?? 0);
}

/**
 * ✅ Chart4 (Entradas x Saídas do Estoque) — CORRIGIDO
 * Entradas = entradas.qtd
 * Saídas = (venda_itens.qtd em vendas válidas) + (saidas.qtd perdas)
 */
$start6 = $today->modify('first day of this month')->modify('-5 months');
$keys6 = buildMonthKeys($start6, 6);
$labels6 = buildMonthLabels($keys6);
$start6Str = $start6->format('Y-m-d');
$end6Str = $today->modify('first day of next month')->format('Y-m-d');

$entMap = [];
if ($HAS_ENTRADAS) {
  $entMap = fetchKeyVal(
    $pdo,
    "SELECT DATE_FORMAT(e.data,'%Y-%m') ym, COALESCE(SUM(e.qtd),0) s
     FROM entradas e
     WHERE e.data >= :ini AND e.data < :fim
     GROUP BY ym
     ORDER BY ym",
    [':ini' => $start6Str, ':fim' => $end6Str],
    'ym',
    's'
  );
}

$lossQtyMap = [];
if ($HAS_SAIDAS) {
  $lossQtyMap = fetchKeyVal(
    $pdo,
    "SELECT DATE_FORMAT(s.data,'%Y-%m') ym, COALESCE(SUM(s.qtd),0) s
     FROM saidas s
     WHERE s.data >= :ini AND s.data < :fim
     GROUP BY ym
     ORDER BY ym",
    [':ini' => $start6Str, ':fim' => $end6Str],
    'ym',
    's'
  );
}

$soldQtyMap = [];
if ($HAS_VENDA_ITENS) {
  $stSold = $pdo->prepare("
    SELECT DATE_FORMAT(v.data,'%Y-%m') ym, COALESCE(SUM(vi.qtd),0) s
    FROM venda_itens vi
    INNER JOIN vendas v ON v.id = vi.venda_id
    WHERE v.data >= :ini AND v.data < :fim
      AND {$EXCL_V}
    GROUP BY ym
    ORDER BY ym
  ");
  $stSold->execute([':ini' => $start6Str, ':fim' => $end6Str]);
  while ($r = $stSold->fetch(PDO::FETCH_ASSOC)) {
    $soldQtyMap[(string)$r['ym']] = $r['s'];
  }
}

$chart4Ent = [];
$chart4Sai = [];
foreach ($keys6 as $k) {
  $chart4Ent[] = (float)($entMap[$k] ?? 0);
  $loss = (float)($lossQtyMap[$k] ?? 0);
  $sold = (float)($soldQtyMap[$k] ?? 0);
  $chart4Sai[] = $loss + $sold;
}

// Chart5: Delivery x Presencial (período) (exclui devolvidas)
$stC = $pdo->prepare("
  SELECT UPPER(COALESCE(v.canal,'PRESENCIAL')) canal, COUNT(*) c
  FROM vendas v
  WHERE v.data BETWEEN :ini AND :fim
    AND {$EXCL_V}
  GROUP BY UPPER(COALESCE(v.canal,'PRESENCIAL'))
");
$stC->execute([':ini' => $rangeStartStr, ':fim' => $rangeEndStr]);

$canalCounts = ['DELIVERY' => 0, 'PRESENCIAL' => 0];
while ($r = $stC->fetch(PDO::FETCH_ASSOC)) {
  $k = strtoupper((string)$r['canal']);
  if (!isset($canalCounts[$k])) $canalCounts[$k] = 0;
  $canalCounts[$k] += (int)$r['c'];
}
$deliveryQtd = (int)($canalCounts['DELIVERY'] ?? 0);
$presencialQtd = (int)($canalCounts['PRESENCIAL'] ?? 0);

// Chart6: pagamentos mais usados (período) (exclui devolvidas)
$stP = $pdo->prepare("
  SELECT UPPER(COALESCE(v.pagamento,'DINHEIRO')) pagamento, COUNT(*) c
  FROM vendas v
  WHERE v.data BETWEEN :ini AND :fim
    AND {$EXCL_V}
  GROUP BY UPPER(COALESCE(v.pagamento,'DINHEIRO'))
  ORDER BY c DESC
  LIMIT 5
");
$stP->execute([':ini' => $rangeStartStr, ':fim' => $rangeEndStr]);
$payLabels = [];
$payData = [];
while ($r = $stP->fetch(PDO::FETCH_ASSOC)) {
  $payLabels[] = (string)$r['pagamento'];
  $payData[] = (int)$r['c'];
}
if (!$payLabels) {
  $payLabels = ['DINHEIRO', 'PIX', 'CARTAO'];
  $payData = [0, 0, 0];
}

/* =========================
   ✅ Chart7: TOP 7 PRODUTOS VENDIDOS (CORRIGIDO)
========================= */
$topDays = (int)($_GET['top'] ?? 7);
if (!in_array($topDays, [7, 30, 90], true)) $topDays = 7;
$topStart = $today->modify("-" . ($topDays - 1) . " days")->format('Y-m-d');
$topEnd = $today->format('Y-m-d');

$topProdLabels = [];
$topProdData = [];

if ($HAS_VENDA_ITENS) {
  $stTop = $pdo->prepare("
    SELECT
      vi.nome AS nome,
      COALESCE(SUM(vi.qtd),0) AS qtd
    FROM venda_itens vi
    INNER JOIN vendas v ON v.id = vi.venda_id
    WHERE v.data BETWEEN :ini AND :fim
      AND {$EXCL_V}
    GROUP BY vi.nome
    ORDER BY qtd DESC, nome ASC
    LIMIT 7
  ");
  $stTop->execute([':ini' => $topStart, ':fim' => $topEnd]);

  while ($r = $stTop->fetch(PDO::FETCH_ASSOC)) {
    $topProdLabels[] = (string)$r['nome'];
    $topProdData[] = (float)$r['qtd'];
  }
}

if (!$topProdLabels) {
  $topProdLabels = ['(sem dados)'];
  $topProdData = [0];
}

/* =========================
   TABELAS (Vendas Recentes com PAGINAÇÃO)
========================= */
$perPage = 10;
$vpage = (int)($_GET['vpage'] ?? 1);
if ($vpage < 1) $vpage = 1;

$stCnt = $pdo->prepare("
  SELECT COUNT(*) c
  FROM vendas v
  WHERE v.data BETWEEN :ini AND :fim
    AND {$EXCL_V}
");
$stCnt->execute([':ini' => $rangeStartStr, ':fim' => $rangeEndStr]);
$totalRows = (int)($stCnt->fetchColumn() ?: 0);

$totalPages = max(1, (int)ceil($totalRows / $perPage));
if ($vpage > $totalPages) $vpage = $totalPages;
$offset = ($vpage - 1) * $perPage;

$stV = $pdo->prepare("
  SELECT v.id, v.data, v.cliente, v.canal, v.pagamento, v.total
  FROM vendas v
  WHERE v.data BETWEEN :ini AND :fim
    AND {$EXCL_V}
  ORDER BY v.data DESC, v.id DESC
  LIMIT :lim OFFSET :off
");
$stV->bindValue(':ini', $rangeStartStr);
$stV->bindValue(':fim', $rangeEndStr);
$stV->bindValue(':lim', $perPage, PDO::PARAM_INT);
$stV->bindValue(':off', $offset, PDO::PARAM_INT);
$stV->execute();
$vendasRecentes = $stV->fetchAll(PDO::FETCH_ASSOC) ?: [];

// Estoque baixo
$estoqueBaixo = [];
if (table_exists($pdo, 'produtos')) {
  $stB = $pdo->query("
    SELECT
      p.id, p.nome, p.estoque, p.minimo,
      COALESCE(c.nome,'-') AS categoria
    FROM produtos p
    LEFT JOIN categorias c ON c.id = p.categoria_id
    WHERE p.status = 'ATIVO' AND p.estoque <= p.minimo
    ORDER BY (p.minimo - p.estoque) DESC, p.nome ASC
    LIMIT 12
  ");
  $estoqueBaixo = $stB->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

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

  <link rel="stylesheet" href="assets/css/bootstrap.min.css" />
  <link rel="stylesheet" href="assets/css/lineicons.css" rel="stylesheet" type="text/css" />
  <link rel="stylesheet" href="assets/css/materialdesignicons.min.css" rel="stylesheet" type="text/css" />
  <link rel="stylesheet" href="assets/css/main.css" />

  <style>
    .equal-row {
      align-items: stretch;
    }

    .equal-row>[class^="col-"],
    .equal-row>[class*=" col-"] {
      display: flex;
    }

    .icon-card,
    .card-style {
      height: 100%;
      width: 100%;
    }

    .card-style {
      display: flex;
      flex-direction: column;
    }

    .card-style .chart,
    .card-style .table-responsive,
    .card-style .card-body-flex {
      flex: 1 1 auto;
    }

    /* padrão charts */
    .chart {
      position: relative;
      min-height: 320px;
    }

    .chart canvas {
      height: 100% !important;
    }

    /* ✅ manter os dois cards SEM aumentar com 30 dias */
    .card-fixed-sm {
      height: 430px;
      min-height: 430px;
    }

    .card-fixed-sm .chart {
      min-height: 220px;
    }

    .card-fixed-sm .table-responsive {
      overflow: auto;
    }

    /* tabela compacta pra caber 10 linhas sem “estourar” */
    .card-fixed-sm .top-selling-table th,
    .card-fixed-sm .top-selling-table td {
      padding: .55rem .60rem !important;
    }

    .table-responsive {
      padding-bottom: 6px;
    }

    .mini-metrics span {
      display: inline-flex;
      gap: 6px;
      align-items: center;
      padding: 4px 10px;
      border-radius: 999px;
      background: rgba(143, 146, 161, .08);
      font-size: 12px;
      color: #111827;
    }

    .pager-box {
      display: flex;
      align-items: center;
      justify-content: flex-end;
      gap: 10px;
      margin-top: 10px;
    }

    .pager-box .page-text {
      font-size: 12px;
      color: #64748b;
      font-weight: 700;
    }

    .pager-box a.btn-disabled {
      opacity: .45;
      pointer-events: none;
    }

    .modal .table td,
    .modal .table th {
      vertical-align: middle;
    }

    .text-xs {
      font-size: 12px;
    }

    .logout-btn {
      padding: 8px 14px !important;
      min-width: 88px;
      height: 46px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      border-radius: 10px;
      font-size: 14px;
      font-weight: 500;
      text-decoration: none !important;
    }

    .logout-btn i {
      font-size: 16px;
    }

    .header-right {
      height: 100%;
    }

    .brand-vertical {
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      gap: 6px;
      text-decoration: none;
      text-align: center;
    }

    .brand-vertical img {
      max-width: 100px;
      height: auto;
      display: block;
    }

    .brand-name {
      display: block;
      font-size: 12px;
      line-height: 1.2;
      font-weight: 600;
      color: #1e2a78;
      white-space: normal;
      word-break: break-word;
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
      <a href="dashboard.php" class="brand-vertical">
        <img src="assets/images/logo/logo.svg" alt="logo" />
        <span class="brand-name">DISTRIBUIDORA<br>PLHB</span>
      </a>
    </div>
    <nav class="sidebar-nav">
      <ul>
        <li class="nav-item active">
          <a href="dashboard.php" class="active">
            <span class="icon">
              <i class="lni lni-dashboard"></i>
            </span>
            <span class="text">Dashboard</span>
          </a>
        </li>

        <li class="nav-item">
          <a href="vendas.php">
            <span class="icon">
              <i class="lni lni-cart"></i>
            </span>
            <span class="text">Vendas</span>
          </a>
        </li>

        <li class="nav-item nav-item-has-children">
          <a href="#0" class="collapsed" data-bs-toggle="collapse" data-bs-target="#ddmenu_operacoes" aria-controls="ddmenu_operacoes" aria-expanded="false">
            <span class="icon">
              <i class="lni lni-layers"></i>
            </span>
            <span class="text">Operações</span>
          </a>
          <ul id="ddmenu_operacoes" class="collapse dropdown-nav">
            <li><a href="vendidos.php">Vendidos</a></li>
            <li><a href="fiados.php">À Prazo</a></li>
            <li><a href="devolucoes.php">Devoluções</a></li>
          </ul>
        </li>

        <li class="nav-item nav-item-has-children">
          <a href="#0" class="collapsed" data-bs-toggle="collapse" data-bs-target="#ddmenu_estoque" aria-controls="ddmenu_estoque" aria-expanded="false">
            <span class="icon">
              <i class="lni lni-package"></i>
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
              <i class="lni lni-users"></i>
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
              <i class="lni lni-clipboard"></i>
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
              <i class="lni lni-cog"></i>
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
              <i class="lni lni-whatsapp"></i>
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
                <button id="menu-toggle" class="main-btn primary-btn btn-hover" type="button">
                  <i class="lni lni-chevron-left me-2"></i> Menu
                </button>
              </div>
              <div class="header-search d-none d-md-flex"></div>
            </div>
          </div>

          <div class="col-lg-7 col-md-7 col-6">
            <div class="header-right d-flex justify-content-end align-items-center">
              <a href="logout.php" class="main-btn primary-btn btn-hover logout-btn">
                <i class="lni lni-exit me-1"></i> Sair
              </a>
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
                  <span class="text-gray">Perdas (30d)</span>
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
              <div class="chart"><canvas id="Chart1"></canvas></div>
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
              <div class="chart"><canvas id="Chart2"></canvas></div>
            </div>
          </div>
        </div>

        <!-- ✅ Entrega x Presencial + ✅ Vendas Recentes (fixo, não aumenta com 30 dias) -->
        <div class="row equal-row">
          <div class="col-lg-5 mb-30">
            <div class="card-style card-fixed-sm">
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

              <div class="chart"><canvas id="Chart5"></canvas></div>

              <div class="mini-metrics d-flex flex-wrap gap-2 mt-10">
                <span><b>Delivery</b> <?= (int)$deliveryQtd ?></span>
                <span><b>Presencial</b> <?= (int)$presencialQtd ?></span>
              </div>
            </div>
          </div>

          <div class="col-lg-7 mb-30">
            <div class="card-style card-fixed-sm">
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
                        $canal = strtoupper((string)($v['canal'] ?? 'PRESENCIAL'));
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
                            <p class="text-sm"><?= e(dtShort((string)$v['data'])) ?></p>
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

              <?php if ($totalPages > 1): ?>
                <div class="pager-box">
                  <?php
                  $prevUrl = build_url(['vpage' => max(1, $vpage - 1)]);
                  $nextUrl = build_url(['vpage' => min($totalPages, $vpage + 1)]);
                  ?>
                  <a class="main-btn light-btn btn-hover btn-sm <?= $vpage <= 1 ? 'btn-disabled' : '' ?>" href="<?= e($prevUrl) ?>" title="Anterior">
                    <i class="lni lni-chevron-left"></i>
                  </a>
                  <span class="page-text">Página <?= (int)$vpage ?>/<?= (int)$totalPages ?></span>
                  <a class="main-btn light-btn btn-hover btn-sm <?= $vpage >= $totalPages ? 'btn-disabled' : '' ?>" href="<?= e($nextUrl) ?>" title="Próxima">
                    <i class="lni lni-chevron-right"></i>
                  </a>
                </div>
              <?php endif; ?>

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
                  <p class="text-sm text-gray mb-0">Saídas = <b>saidas.valor_total</b> • Devoluções = <b>devolucoes.valor</b></p>
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
              <div class="chart"><canvas id="Chart3"></canvas></div>
            </div>
          </div>

          <div class="col-lg-5 mb-30">
            <div class="card-style">
              <div class="title d-flex flex-wrap align-items-center justify-content-between">
                <div class="left">
                  <h6 class="text-medium mb-2">Entradas x Saídas (Estoque)</h6>
                  <p class="text-sm text-gray mb-0">Somatório de qtd (6 meses) • Saídas = Vendas (itens) + Perdas</p>
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
              <div class="chart"><canvas id="Chart4"></canvas></div>
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
              <div class="chart"><canvas id="Chart6"></canvas></div>
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

              <div class="chart"><canvas id="Chart7"></canvas></div>
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
                <div class="right"><a href="estoque-minimo.php" class="main-btn primary-btn btn-hover btn-sm">Ver detalhes</a></div>
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
                  <div class="text-sm" id="vObs">—</div>
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

            <h6 class="text-medium mb-2">Itens vendidos</h6>
            <div class="table-responsive">
              <table class="table">
                <thead>
                  <tr>
                    <th>#</th>
                    <th>Produto</th>
                    <th class="text-end">Qtd</th>
                    <th class="text-end">V. Unit</th>
                    <th class="text-end">Subtotal</th>
                  </tr>
                </thead>
                <tbody id="vItensBody">
                  <tr>
                    <td colspan="5" class="text-sm text-gray">Sem itens.</td>
                  </tr>
                </tbody>
              </table>
            </div>
            <div class="d-flex justify-content-end gap-3">
              <div class="text-sm text-gray"><b id="vItensQtd">0</b> unid</div>
              <div class="text-sm"><b id="vItensTotal">R$ 0,00</b></div>
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
    // period/top selects (mantém e reseta paginação)
    (function() {
      const sel = document.getElementById('periodSelect');
      if (sel) {
        sel.addEventListener('change', function() {
          const url = new URL(window.location.href);
          url.searchParams.set('period', sel.value);
          url.searchParams.set('vpage', '1');
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

  <script>
    const THEME = {
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

    const LABELS_12 = <?= json_encode($labels12, JSON_UNESCAPED_UNICODE) ?>;
    const CHART1_DATA = <?= json_encode($chart1, JSON_UNESCAPED_UNICODE) ?>;
    const CHART2_DATA = <?= json_encode($chart2, JSON_UNESCAPED_UNICODE) ?>;

    const MIX_LABEL_A = <?= json_encode($mixLabelA, JSON_UNESCAPED_UNICODE) ?>;
    const MIX_LABEL_B = <?= json_encode($mixLabelB, JSON_UNESCAPED_UNICODE) ?>;
    const CHART3_A = <?= json_encode($chart3A, JSON_UNESCAPED_UNICODE) ?>;
    const CHART3_B = <?= json_encode($chart3B, JSON_UNESCAPED_UNICODE) ?>;

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
              callbacks: {
                label: (c) => fmtBRL(c.parsed.y)
              }
            }
          }
        }
      });
    }

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
            }
          }
        }
      });
    }

    // ✅ Mix Saídas + Devoluções (valores)
    const ctx3 = document.getElementById("Chart3")?.getContext("2d");
    if (ctx3) {
      new Chart(ctx3, {
        type: "line",
        data: {
          labels: LABELS_12,
          datasets: [{
              label: MIX_LABEL_A,
              backgroundColor: "transparent",
              borderColor: "#d50100",
              data: CHART3_A,
              tension: 0.35,
              pointRadius: 2
            },
            {
              label: MIX_LABEL_B,
              backgroundColor: "transparent",
              borderColor: "#365CF5",
              data: CHART3_B,
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
              display: true
            },
            tooltip: {
              intersect: false,
              backgroundColor: "#fbfbfb",
              displayColors: false,
              callbacks: {
                label: (c) => `${c.dataset.label}: ${fmtBRL(c.parsed.y)}`
              }
            }
          }
        }
      });
    }

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
          maintainAspectRatio: false
        }
      });
    }

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
          cutout: "65%"
        }
      });
    }

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
            }
          }
        }
      });
    }

    // ✅ TOP 7 (horizontal quando possível)
    const ctx7 = document.getElementById("Chart7")?.getContext("2d");
    if (ctx7) {
      const ver = (window.Chart && Chart.version) ? Chart.version : "3.0.0";
      const major = parseInt(String(ver).split(".")[0] || "3", 10);
      const isV3 = major >= 3;

      const data = {
        labels: TOP_LABELS,
        datasets: [{
          label: "Qtd",
          data: TOP_DATA,
          backgroundColor: "#365CF5"
        }]
      };

      const optionsV3 = {
        indexAxis: "y",
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            display: false
          }
        }
      };

      const optionsV2 = {
        responsive: true,
        maintainAspectRatio: false,
        legend: {
          display: false
        },
        scales: {
          xAxes: [{
            ticks: {
              beginAtZero: true
            }
          }],
          yAxes: [{}]
        }
      };

      new Chart(ctx7, {
        type: isV3 ? "bar" : "horizontalBar",
        data,
        options: isV3 ? optionsV3 : optionsV2
      });
    }
  </script>

  <script>
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

    function escHtml(s) {
      return String(s ?? "")
        .replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;").replace(/'/g, "&#039;");
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

        const v = j.venda || {};
        document.getElementById('vId').textContent = '#V-' + String(v.id || id).padStart(5, '0');
        document.getElementById('vData').textContent = safe(v.data);
        document.getElementById('vCanal').textContent = safe(v.canal);
        document.getElementById('vCliente').textContent = safe(v.cliente);
        document.getElementById('vPagamento').textContent = safe(v.pagamento);
        document.getElementById('vSubtotal').textContent = fmtBRL2(v.subtotal);

        const desc = (String(v.desconto_tipo || '').toUpperCase() === 'VALOR') ?
          fmtBRL2(v.desconto_valor) :
          `${Number(v.desconto_valor || 0).toFixed(2).replace('.', ',')}%`;
        document.getElementById('vDesconto').textContent = `${desc} (taxa: ${fmtBRL2(v.taxa_entrega)})`;
        document.getElementById('vTotal').textContent = fmtBRL2(v.total);

        // ✅ Endereço/Obs + Pagamento JSON “limpo”
        const lines = [];
        if (v.endereco && String(v.endereco).trim()) lines.push(`<b>Endereço:</b> ${escHtml(String(v.endereco).trim())}`);
        if (v.obs && String(v.obs).trim()) lines.push(`<b>Obs:</b> ${escHtml(String(v.obs).trim())}`);

        const payText = (j.pay_text && String(j.pay_text).trim()) ? String(j.pay_text) : '';
        if (payText) {
          const payHtml = escHtml(payText).replace(/\n/g, "<br>");
          lines.push(`<b>Pagamento:</b><br>${payHtml}`);
        }

        document.getElementById('vObs').innerHTML = lines.length ? lines.join("<br>") : '—';

        // ✅ ITENS (AGORA VEM)
        const itBody = document.getElementById('vItensBody');
        const itens = Array.isArray(j.itens) ? j.itens : [];
        document.getElementById('vItensQtd').textContent = String(j.itens_qtd || 0);
        document.getElementById('vItensTotal').textContent = fmtBRL2(j.itens_total || 0);

        if (!itens.length) {
          itBody.innerHTML = `<tr><td colspan="5" class="text-sm text-gray">Sem itens cadastrados (venda_itens).</td></tr>`;
        } else {
          itBody.innerHTML = itens.map((it, idx) => `
            <tr>
              <td>${idx+1}</td>
              <td>
                <div class="fw-600">${escHtml(safe(it.nome))}</div>
                <div class="text-xs text-gray">${escHtml(safe(it.codigo))} • ${escHtml(safe(it.unidade))}</div>
              </td>
              <td class="text-end">${escHtml(safe(it.qtd))}</td>
              <td class="text-end">${fmtBRL2(it.preco_unit)}</td>
              <td class="text-end fw-600">${fmtBRL2(it.subtotal)}</td>
            </tr>
          `).join('');
        }

        // DEVOLUÇÕES
        const tbody = document.getElementById('vDevolBody');
        const devols = Array.isArray(j.devolucoes) ? j.devolucoes : [];
        if (!devols.length) {
          tbody.innerHTML = `<tr><td colspan="8" class="text-sm text-gray">Nenhuma devolução.</td></tr>`;
        } else {
          tbody.innerHTML = devols.map(d => `
            <tr>
              <td>${escHtml(safe(d.id))}</td>
              <td>${escHtml(safe(d.data))}</td>
              <td>${escHtml(safe(d.hora))}</td>
              <td>${escHtml(safe(d.tipo))}</td>
              <td>${escHtml(safe(d.produto))}</td>
              <td>${escHtml(safe(d.qtd))}</td>
              <td>${fmtBRL2(d.valor)}</td>
              <td>${escHtml(safe(d.status))}</td>
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

        const p = j.produto || {};
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