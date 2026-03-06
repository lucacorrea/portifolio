<?php
declare(strict_types=1);

@date_default_timezone_set('America/Manaus');
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

require_once __DIR__ . '/assets/conexao.php';

// (opcional) helpers do seu projeto
$helpers = __DIR__ . '/assets/dados/relatorios/__helpers.php';
if (is_file($helpers)) require_once $helpers;

if (!function_exists('db')) {
  http_response_code(500);
  echo "ERRO: função db():PDO não encontrada. Verifique /assets/conexao.php";
  exit;
}

// fallback de escape
if (!function_exists('e')) {
  function e(string $s): string { return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
}

$pdo = db();

/* =========================
   Utils
========================= */
function br_money($n): string { return 'R$ ' . number_format((float)$n, 2, ',', '.'); }

function br_date(?string $iso): string {
  $iso = trim((string)$iso);
  if ($iso === '') return '—';
  $dt = DateTime::createFromFormat('Y-m-d', $iso);
  return $dt ? $dt->format('d/m/Y') : '—';
}

function br_datetime(?string $sqlDt): string {
  $sqlDt = trim((string)$sqlDt);
  if ($sqlDt === '') return '—';
  $dt = DateTime::createFromFormat('Y-m-d H:i:s', $sqlDt);
  if (!$dt) { try { $dt = new DateTime($sqlDt); } catch (\Throwable $e) { return '—'; } }
  return $dt->format('d/m/Y H:i');
}

function table_exists(PDO $pdo, string $table): bool {
  try {
    $st = $pdo->prepare("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?");
    $st->execute([$table]);
    return (int)$st->fetchColumn() > 0;
  } catch (\Throwable $e) {
    return false;
  }
}

function venda_exclude_devolvidas_sql(): string {
  // regra do seu projeto: venda devolvida (status != CANCELADO) sai das “vendas”
  return "NOT EXISTS (
    SELECT 1 FROM devolucoes d
    WHERE d.venda_no = v.id
      AND d.status <> 'CANCELADO'
  )";
}

function decode_pay_json(?string $json): array {
  $json = trim((string)$json);
  if ($json === '') return [];
  $d = json_decode($json, true);
  return (json_last_error() === JSON_ERROR_NONE && is_array($d)) ? $d : [];
}

function fmt_pay_json(?string $json): string {
  $d = decode_pay_json($json);
  if (!$d) return '';

  $lines = [];

  $mode = strtoupper((string)($d['mode'] ?? $d['pagamento_mode'] ?? ''));
  $method = strtoupper((string)($d['method'] ?? $d['pagamento'] ?? ''));

  if ($mode !== '')   $lines[] = "Modo: {$mode}";
  if ($method !== '') $lines[] = "Método: {$method}";

  $paid  = $d['paid']  ?? $d['pago']  ?? null;
  $troco = $d['troco'] ?? null;
  $total = $d['total'] ?? null;

  if ($total !== null && $total !== '') $lines[] = "Total: " . br_money((float)$total);
  if ($paid  !== null && $paid  !== '') $lines[] = "Pago: " . br_money((float)$paid);
  if ($troco !== null && $troco !== '') $lines[] = "Troco: " . br_money((float)$troco);

  if (isset($d['parts']) && is_array($d['parts'])) {
    $lines[] = "Partes:";
    foreach ($d['parts'] as $p) {
      if (!is_array($p)) continue;
      $m = strtoupper((string)($p['method'] ?? $p['metodo'] ?? ''));
      $v = (float)($p['value'] ?? $p['valor'] ?? 0);
      $lines[] = "• {$m}: " . br_money($v);
    }
  }

  // fiado (no seu dump é objeto com has_entry/entry_value/debt_value)
  if (isset($d['fiado']) && is_array($d['fiado'])) {
    $lines[] = "Fiado:";
    $has = !empty($d['fiado']['has_entry']);
    $entryV = (float)($d['fiado']['entry_value'] ?? 0);
    $entryM = (string)($d['fiado']['entry_method'] ?? '');
    $debtV  = (float)($d['fiado']['debt_value'] ?? 0);
    $lines[] = "• Entrada: " . ($has ? (br_money($entryV) . ($entryM ? " ({$entryM})" : "")) : "NÃO");
    $lines[] = "• Restante: " . br_money($debtV);
  } elseif (array_key_exists('fiado', $d) && $d['fiado']) {
    $lines[] = "Fiado: SIM";
  }

  return implode("\n", $lines);
}

/* =========================
   Referência de período (IMPORTANTE)
   -> usa a ÚLTIMA data existente em vendas, para não ficar “sem dados”.
========================= */
function ref_date_from_db(PDO $pdo): DateTimeImmutable {
  try {
    $max = (string)($pdo->query("SELECT MAX(data) FROM vendas")->fetchColumn() ?: '');
    if ($max && preg_match('/^\d{4}-\d{2}-\d{2}$/', $max)) {
      return new DateTimeImmutable($max);
    }
  } catch (\Throwable $e) {}
  return new DateTimeImmutable('today');
}

$REF_DATE = ref_date_from_db($pdo);

function period_range(string $period, DateTimeImmutable $ref): array {
  $p = strtolower(trim($period));
  $end = $ref;

  if ($p === 'today' || $p === 'hoje') {
    $start = $ref;
  } elseif ($p === '7d') {
    $start = $ref->sub(new DateInterval('P6D'));
  } elseif ($p === '30d') {
    $start = $ref->sub(new DateInterval('P29D'));
  } elseif ($p === '12m') {
    $start = $ref->modify('first day of this month')->sub(new DateInterval('P11M'));
    $end   = $ref;
  } else {
    $start = $ref->sub(new DateInterval('P6D'));
  }
  return [$start->format('Y-m-d'), $end->format('Y-m-d')];
}

function months_last_n_from_ref(DateTimeImmutable $ref, int $n = 12): array {
  $base = $ref->modify('first day of this month');
  $pt = ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];

  $keys = [];
  $labels = [];
  for ($i = $n - 1; $i >= 0; $i--) {
    $d = $base->sub(new DateInterval('P' . $i . 'M'));
    $ym = $d->format('Y-m');
    $keys[] = $ym;
    $m = (int)$d->format('n');
    $y = $d->format('y');
    $labels[] = $pt[$m - 1] . '/' . $y;
  }
  return [$keys, $labels];
}

/* =========================
   Queries (Dashboard)
========================= */
function chart_delivery(PDO $pdo, string $period, DateTimeImmutable $ref): array {
  [$ini, $fim] = period_range($period, $ref);

  $sql = "
    SELECT UPPER(COALESCE(v.canal,'PRESENCIAL')) AS canal, COUNT(*) AS qtd
    FROM vendas v
    WHERE v.data BETWEEN :ini AND :fim
      AND " . venda_exclude_devolvidas_sql() . "
    GROUP BY UPPER(COALESCE(v.canal,'PRESENCIAL'))
  ";
  $st = $pdo->prepare($sql);
  $st->execute([':ini'=>$ini, ':fim'=>$fim]);
  $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

  $map = ['PRESENCIAL'=>0, 'DELIVERY'=>0];
  foreach ($rows as $r) {
    $c = strtoupper((string)($r['canal'] ?? ''));
    if (!isset($map[$c])) $map[$c] = 0;
    $map[$c] = (int)($r['qtd'] ?? 0);
  }

  return [
    'labels' => ['Presencial','Delivery'],
    'values' => [(int)$map['PRESENCIAL'], (int)$map['DELIVERY']],
    'ini' => $ini, 'fim' => $fim
  ];
}

function chart_payments(PDO $pdo, string $period, DateTimeImmutable $ref): array {
  [$ini, $fim] = period_range($period, $ref);

  $hasFiados = table_exists($pdo, 'fiados');
  $recebExpr = $hasFiados
    ? "CASE WHEN UPPER(v.pagamento)='FIADO' THEN COALESCE((SELECT f.valor_pago FROM fiados f WHERE f.venda_id=v.id LIMIT 1),0) ELSE v.total END"
    : "v.total";

  $sql = "
    SELECT UPPER(COALESCE(v.pagamento,'DINHEIRO')) AS pag,
           COUNT(*) AS qtd
    FROM vendas v
    WHERE v.data BETWEEN :ini AND :fim
      AND " . venda_exclude_devolvidas_sql() . "
    GROUP BY UPPER(COALESCE(v.pagamento,'DINHEIRO'))
    ORDER BY qtd DESC
    LIMIT 10
  ";
  $st = $pdo->prepare($sql);
  $st->execute([':ini'=>$ini, ':fim'=>$fim]);
  $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

  $labels = [];
  $values = [];
  foreach ($rows as $r) {
    $labels[] = (string)$r['pag'];
    $values[] = (int)$r['qtd'];
  }

  $st2 = $pdo->prepare("
    SELECT COALESCE(SUM(v.total),0) AS total,
           COALESCE(SUM($recebExpr),0) AS recebido
    FROM vendas v
    WHERE v.data BETWEEN :ini AND :fim
      AND " . venda_exclude_devolvidas_sql() . "
  ");
  $st2->execute([':ini'=>$ini, ':fim'=>$fim]);
  $agg = $st2->fetch(PDO::FETCH_ASSOC) ?: ['total'=>0,'recebido'=>0];

  return [
    'labels' => $labels,
    'values' => $values,
    'total' => (float)$agg['total'],
    'recebido' => (float)$agg['recebido'],
    'ini' => $ini, 'fim' => $fim
  ];
}

function top_products_rows(PDO $pdo, string $period, DateTimeImmutable $ref, int $limit = 7): array {
  [$ini, $fim] = period_range($period, $ref);

  // cuidado com LIMIT bind (em alguns ambientes pode dar ruim): injeta sanitizado
  $lim = max(1, min(50, $limit));

  $sql = "
    SELECT vi.codigo, vi.nome, SUM(vi.qtd) AS qtd
    FROM venda_itens vi
    INNER JOIN vendas v ON v.id = vi.venda_id
    WHERE v.data BETWEEN :ini AND :fim
      AND " . venda_exclude_devolvidas_sql() . "
    GROUP BY vi.codigo, vi.nome
    ORDER BY qtd DESC, vi.nome ASC
    LIMIT {$lim}
  ";
  $st = $pdo->prepare($sql);
  $st->execute([':ini'=>$ini, ':fim'=>$fim]);
  return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function chart_top_products(PDO $pdo, string $period, DateTimeImmutable $ref, int $limit = 7): array {
  [$ini, $fim] = period_range($period, $ref);
  $rows = top_products_rows($pdo, $period, $ref, $limit);

  $labels = [];
  $values = [];
  foreach ($rows as $r) {
    $labels[] = (string)($r['nome'] ?? '—');
    $values[] = (int)($r['qtd'] ?? 0);
  }

  return ['labels'=>$labels,'values'=>$values,'ini'=>$ini,'fim'=>$fim];
}

/**
 * MIX (12m) em VALORES:
 * - Saídas = Vendas.total + Saidas.valor_total (perdas/avarias)
 * - Devoluções = devolucoes.valor (status != CANCELADO)
 */
function chart_mix_12m_valores(PDO $pdo, DateTimeImmutable $ref): array {
  [$keys, $labels] = months_last_n_from_ref($ref, 12);
  $start = $keys[0] . '-01';
  $end = $ref->format('Y-m-d');

  $out = array_fill_keys($keys, 0.0);   // saidas total (vendas + perdas)
  $dev = array_fill_keys($keys, 0.0);   // devolucoes total

  // vendas por mês (ATENÇÃO: aqui é venda total mesmo; devolução fica separado)
  $st = $pdo->prepare("
    SELECT DATE_FORMAT(v.data, '%Y-%m') AS ym, COALESCE(SUM(v.total),0) AS total
    FROM vendas v
    WHERE v.data BETWEEN :ini AND :fim
    GROUP BY DATE_FORMAT(v.data,'%Y-%m')
  ");
  $st->execute([':ini'=>$start, ':fim'=>$end]);
  foreach (($st->fetchAll(PDO::FETCH_ASSOC) ?: []) as $r) {
    $ym = (string)$r['ym'];
    if (isset($out[$ym])) $out[$ym] += (float)$r['total'];
  }

  // perdas (saidas) por mês
  $st2 = $pdo->prepare("
    SELECT DATE_FORMAT(s.data, '%Y-%m') AS ym, COALESCE(SUM(s.valor_total),0) AS total
    FROM saidas s
    WHERE s.data BETWEEN :ini AND :fim
    GROUP BY DATE_FORMAT(s.data,'%Y-%m')
  ");
  $st2->execute([':ini'=>$start, ':fim'=>$end]);
  foreach (($st2->fetchAll(PDO::FETCH_ASSOC) ?: []) as $r) {
    $ym = (string)$r['ym'];
    if (isset($out[$ym])) $out[$ym] += (float)$r['total'];
  }

  // devolucoes por mês
  $st3 = $pdo->prepare("
    SELECT DATE_FORMAT(d.data, '%Y-%m') AS ym, COALESCE(SUM(d.valor),0) AS total
    FROM devolucoes d
    WHERE d.status <> 'CANCELADO'
      AND d.data BETWEEN :ini AND :fim
    GROUP BY DATE_FORMAT(d.data,'%Y-%m')
  ");
  $st3->execute([':ini'=>$start, ':fim'=>$end]);
  foreach (($st3->fetchAll(PDO::FETCH_ASSOC) ?: []) as $r) {
    $ym = (string)$r['ym'];
    if (isset($dev[$ym])) $dev[$ym] = (float)$r['total'];
  }

  return [
    'labels' => $labels,
    'saidas' => array_values($out),
    'devolucoes' => array_values($dev),
  ];
}

/**
 * Canais (Top 2) (12m) em VALOR:
 * - Vendas por canal (SUM(v.total))
 * - + Devoluções por canal (SUM(d.valor) via join em vendas)
 */
function chart_canais_top2_12m(PDO $pdo, DateTimeImmutable $ref): array {
  [$keys, $_labels] = months_last_n_from_ref($ref, 12);
  $start = $keys[0] . '-01';
  $end = $ref->format('Y-m-d');

  $map = [];

  // vendas por canal
  $st = $pdo->prepare("
    SELECT UPPER(COALESCE(v.canal,'PRESENCIAL')) AS canal, COALESCE(SUM(v.total),0) AS total
    FROM vendas v
    WHERE v.data BETWEEN :ini AND :fim
    GROUP BY UPPER(COALESCE(v.canal,'PRESENCIAL'))
  ");
  $st->execute([':ini'=>$start, ':fim'=>$end]);
  foreach (($st->fetchAll(PDO::FETCH_ASSOC) ?: []) as $r) {
    $c = (string)$r['canal'];
    $map[$c] = (float)$r['total'];
  }

  // devolucoes por canal (canal vem da venda)
  $st2 = $pdo->prepare("
    SELECT UPPER(COALESCE(v.canal,'PRESENCIAL')) AS canal, COALESCE(SUM(d.valor),0) AS total
    FROM devolucoes d
    LEFT JOIN vendas v ON v.id = d.venda_no
    WHERE d.status <> 'CANCELADO'
      AND d.data BETWEEN :ini AND :fim
    GROUP BY UPPER(COALESCE(v.canal,'PRESENCIAL'))
  ");
  $st2->execute([':ini'=>$start, ':fim'=>$end]);
  foreach (($st2->fetchAll(PDO::FETCH_ASSOC) ?: []) as $r) {
    $c = (string)$r['canal'];
    $map[$c] = ($map[$c] ?? 0) + (float)$r['total'];
  }

  arsort($map);
  $top = array_slice($map, 0, 2, true);

  return [
    'labels' => array_keys($top),
    'values' => array_values($top),
  ];
}

/**
 * Entradas x Saídas (Estoque) (12m) em QTD:
 * - Entradas = SUM(entradas.qtd)
 * - Saídas   = SUM(venda_itens.qtd) + SUM(saidas.qtd)
 * Observação: devolução TOTAL não tem produto/qtd no seu banco, então não dá pra “devolver” qtd no gráfico.
 */
function chart_entradas_vs_saidas_12m(PDO $pdo, DateTimeImmutable $ref): array {
  [$keys, $labels] = months_last_n_from_ref($ref, 12);
  $start = $keys[0] . '-01';
  $end = $ref->format('Y-m-d');

  $ent = array_fill_keys($keys, 0.0);
  $out = array_fill_keys($keys, 0.0);

  $st = $pdo->prepare("
    SELECT DATE_FORMAT(e.data, '%Y-%m') AS ym, COALESCE(SUM(e.qtd),0) AS qtd
    FROM entradas e
    WHERE e.data BETWEEN :ini AND :fim
    GROUP BY DATE_FORMAT(e.data,'%Y-%m')
  ");
  $st->execute([':ini'=>$start, ':fim'=>$end]);
  foreach (($st->fetchAll(PDO::FETCH_ASSOC) ?: []) as $r) {
    $ym = (string)$r['ym'];
    if (isset($ent[$ym])) $ent[$ym] = (float)$r['qtd'];
  }

  // perdas/avarias
  $st2 = $pdo->prepare("
    SELECT DATE_FORMAT(s.data, '%Y-%m') AS ym, COALESCE(SUM(s.qtd),0) AS qtd
    FROM saidas s
    WHERE s.data BETWEEN :ini AND :fim
    GROUP BY DATE_FORMAT(s.data,'%Y-%m')
  ");
  $st2->execute([':ini'=>$start, ':fim'=>$end]);
  foreach (($st2->fetchAll(PDO::FETCH_ASSOC) ?: []) as $r) {
    $ym = (string)$r['ym'];
    if (isset($out[$ym])) $out[$ym] += (float)$r['qtd'];
  }

  // vendas (itens)
  $st3 = $pdo->prepare("
    SELECT DATE_FORMAT(v.data, '%Y-%m') AS ym, COALESCE(SUM(vi.qtd),0) AS qtd
    FROM venda_itens vi
    INNER JOIN vendas v ON v.id = vi.venda_id
    WHERE v.data BETWEEN :ini AND :fim
      AND " . venda_exclude_devolvidas_sql() . "
    GROUP BY DATE_FORMAT(v.data,'%Y-%m')
  ");
  $st3->execute([':ini'=>$start, ':fim'=>$end]);
  foreach (($st3->fetchAll(PDO::FETCH_ASSOC) ?: []) as $r) {
    $ym = (string)$r['ym'];
    if (isset($out[$ym])) $out[$ym] += (float)$r['qtd'];
  }

  return [
    'labels' => $labels,
    'entradas' => array_values($ent),
    'saidas' => array_values($out),
  ];
}

function recent_vendas(PDO $pdo, int $limit = 10): array {
  $lim = max(1, min(50, $limit));

  $hasFiados = table_exists($pdo, 'fiados');
  $recebExpr = $hasFiados
    ? "CASE WHEN UPPER(v.pagamento)='FIADO' THEN COALESCE((SELECT f.valor_pago FROM fiados f WHERE f.venda_id=v.id LIMIT 1),0) ELSE v.total END"
    : "v.total";

  $sql = "
    SELECT v.id, v.data, v.pagamento, v.total, v.created_at, $recebExpr AS recebido
    FROM vendas v
    WHERE " . venda_exclude_devolvidas_sql() . "
    ORDER BY v.created_at DESC, v.id DESC
    LIMIT {$lim}
  ";
  return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function low_stock(PDO $pdo, int $limit = 10): array {
  $lim = max(1, min(50, $limit));
  $sql = "
    SELECT p.id, p.nome, p.estoque, p.minimo, c.nome AS categoria
    FROM produtos p
    LEFT JOIN categorias c ON c.id = p.categoria_id
    WHERE p.minimo IS NOT NULL AND p.estoque < p.minimo
    ORDER BY (p.estoque - p.minimo) ASC, p.nome ASC
    LIMIT {$lim}
  ";
  return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/* =========================
   AJAX: detalhes da venda (modal) + FIADOS
========================= */
if (isset($_GET['action']) && $_GET['action'] === 'venda_details') {
  header('Content-Type: application/json; charset=utf-8');

  try {
    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) throw new RuntimeException('ID inválido.');

    $st = $pdo->prepare("
      SELECT
        v.id, v.data, v.cliente, v.canal, v.endereco, v.obs,
        v.desconto_tipo, v.desconto_valor, v.taxa_entrega,
        v.subtotal, v.total,
        v.pagamento_mode, v.pagamento, v.pagamento_json,
        v.created_at
      FROM vendas v
      WHERE v.id = ?
      LIMIT 1
    ");
    $st->execute([$id]);
    $v = $st->fetch(PDO::FETCH_ASSOC);
    if (!$v) throw new RuntimeException('Venda não encontrada.');

    $stI = $pdo->prepare("
      SELECT id, codigo, nome, unidade, qtd, preco_unit, subtotal
      FROM venda_itens
      WHERE venda_id = ?
      ORDER BY id ASC
    ");
    $stI->execute([$id]);
    $itens = $stI->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $stD = $pdo->prepare("
      SELECT id, data, hora, tipo, produto, qtd, valor, motivo, status
      FROM devolucoes
      WHERE venda_no = ?
      ORDER BY data DESC, hora DESC, id DESC
    ");
    $stD->execute([$id]);
    $devols = $stD->fetchAll(PDO::FETCH_ASSOC) ?: [];

    // desconto legível
    $descTipo = strtoupper((string)($v['desconto_tipo'] ?? 'PERC'));
    $descVal  = (float)($v['desconto_valor'] ?? 0);
    $taxa     = (float)($v['taxa_entrega'] ?? 0);
    $descText = ($descTipo === 'VALOR')
      ? (br_money($descVal) . " (taxa: " . br_money($taxa) . ")")
      : (number_format($descVal, 2, ',', '.') . "% (taxa: " . br_money($taxa) . ")");

    // recebido (se FIADO, usa fiados.valor_pago)
    $recebido = (float)($v['total'] ?? 0);
    $fiadoInfo = null;

    if (strtoupper((string)($v['pagamento'] ?? '')) === 'FIADO' && table_exists($pdo, 'fiados')) {
      $stF = $pdo->prepare("SELECT id, valor_total, valor_pago, valor_restante, status FROM fiados WHERE venda_id = ? LIMIT 1");
      $stF->execute([$id]);
      $fi = $stF->fetch(PDO::FETCH_ASSOC);
      if ($fi) {
        $recebido = (float)($fi['valor_pago'] ?? 0);
        $fiadoInfo = [
          'fiado_id' => (int)($fi['id'] ?? 0),
          'valor_total' => (float)($fi['valor_total'] ?? 0),
          'valor_pago' => (float)($fi['valor_pago'] ?? 0),
          'valor_restante' => (float)($fi['valor_restante'] ?? 0),
          'status' => (string)($fi['status'] ?? ''),
        ];
      } else {
        $recebido = 0.0;
      }
    }

    echo json_encode([
      'ok' => true,
      'venda' => [
        'id' => (int)$v['id'],
        'data' => (string)$v['data'],
        'created_at' => (string)($v['created_at'] ?? ''),
        'cliente' => (string)($v['cliente'] ?? ''),
        'canal' => (string)($v['canal'] ?? 'PRESENCIAL'),
        'pagamento' => (string)($v['pagamento'] ?? ''),
        'pagamento_mode' => (string)($v['pagamento_mode'] ?? ''),
        'pagamento_json_fmt' => fmt_pay_json((string)($v['pagamento_json'] ?? '')),
        'endereco' => (string)($v['endereco'] ?? ''),
        'obs' => (string)($v['obs'] ?? ''),
        'subtotal' => (float)($v['subtotal'] ?? 0),
        'total' => (float)($v['total'] ?? 0),
        'recebido' => $recebido,
        'desconto_text' => $descText,
        'fiado' => $fiadoInfo,
      ],
      'itens' => array_map(fn($r) => [
        'codigo' => (string)($r['codigo'] ?? ''),
        'nome' => (string)($r['nome'] ?? ''),
        'unidade' => (string)($r['unidade'] ?? ''),
        'qtd' => (int)($r['qtd'] ?? 0),
        'preco_unit' => (float)($r['preco_unit'] ?? 0),
        'subtotal' => (float)($r['subtotal'] ?? 0),
      ], $itens),
      'devolucoes' => array_map(fn($r) => [
        'id' => (int)($r['id'] ?? 0),
        'data' => (string)($r['data'] ?? ''),
        'hora' => (string)($r['hora'] ?? ''),
        'tipo' => (string)($r['tipo'] ?? ''),
        'produto' => (string)($r['produto'] ?? ''),
        'qtd' => ($r['qtd'] === null ? null : (int)$r['qtd']),
        'valor' => (float)($r['valor'] ?? 0),
        'status' => (string)($r['status'] ?? ''),
        'motivo' => (string)($r['motivo'] ?? ''),
      ], $devols),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

  } catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  }
  exit;
}

/* =========================
   AJAX: charts
========================= */
if (isset($_GET['action']) && $_GET['action'] === 'chart') {
  header('Content-Type: application/json; charset=utf-8');
  try {
    $name = strtolower((string)($_GET['name'] ?? ''));
    $period = (string)($_GET['period'] ?? '7d');

    global $REF_DATE;

    if ($name === 'delivery') {
      $data = chart_delivery($pdo, $period, $REF_DATE);
    } elseif ($name === 'payments') {
      $data = chart_payments($pdo, $period, $REF_DATE);
    } elseif ($name === 'top_products') {
      $data = chart_top_products($pdo, $period, $REF_DATE, 7);
      $data['rows'] = top_products_rows($pdo, $period, $REF_DATE, 7);
    } elseif ($name === 'mix12m') {
      $data = chart_mix_12m_valores($pdo, $REF_DATE);
    } elseif ($name === 'channels_top2') {
      $data = chart_canais_top2_12m($pdo, $REF_DATE);
    } elseif ($name === 'flow12m') {
      $data = chart_entradas_vs_saidas_12m($pdo, $REF_DATE);
    } else {
      throw new RuntimeException('Chart inválido.');
    }

    echo json_encode(['ok' => true, 'data' => $data], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  } catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  }
  exit;
}

/* =========================
   Dados iniciais (render)
========================= */
$initDelivery   = chart_delivery($pdo, 'today', $REF_DATE);
$initPayments   = chart_payments($pdo, 'today', $REF_DATE);
$initTop        = chart_top_products($pdo, '7d', $REF_DATE, 7);
$initTopRows    = top_products_rows($pdo, '7d', $REF_DATE, 7);

$mix12m         = chart_mix_12m_valores($pdo, $REF_DATE);
$channelsTop2   = chart_canais_top2_12m($pdo, $REF_DATE);
$flow12m        = chart_entradas_vs_saidas_12m($pdo, $REF_DATE);

$recent         = recent_vendas($pdo, 10);
$low            = low_stock($pdo, 10);

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
    .main-btn.btn-compact{height:38px!important;padding:8px 14px!important;font-size:13px!important;line-height:1!important;}
    .main-btn.btn-compact i{font-size:14px;vertical-align:-1px;}
    .card-title-row{display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;}
    .card-title-row .title{font-weight:900;color:#0f172a;}
    .muted{font-size:12px;color:#64748b;}
    .tbl-sm td,.tbl-sm th{padding:.55rem .65rem;}
    .icon-btn{height:34px!important;width:42px!important;padding:0!important;display:inline-flex!important;align-items:center!important;justify-content:center!important;}
    .badge-soft{padding:.35rem .6rem;border-radius:999px;font-weight:800;font-size:.72rem;display:inline-flex;align-items:center;justify-content:center;}
    .badge-soft-warning{background:rgba(245,158,11,.12);color:#b45309;}
    .badge-soft-gray{background:rgba(148,163,184,.18);color:#475569;}
    pre.pay-json{white-space:pre-wrap;margin:0;font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,"Liberation Mono","Courier New",monospace;font-size:12px;color:#334155;background:rgba(248,250,252,.85);border:1px solid rgba(148,163,184,.22);padding:10px;border-radius:12px;}
  </style>
</head>

<body>
<div id="preloader"><div class="spinner"></div></div>

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
          <span class="icon"><i class="lni lni-dashboard"></i></span>
          <span class="text">Dashboard</span>
        </a>
      </li>

      <li class="nav-item">
        <a href="vendas.php">
          <span class="icon"><i class="lni lni-cart"></i></span>
          <span class="text">Vendas</span>
        </a>
      </li>

      <li class="nav-item nav-item-has-children">
        <a href="#0" class="collapsed" data-bs-toggle="collapse" data-bs-target="#ddmenu_operacoes" aria-controls="ddmenu_operacoes" aria-expanded="false">
          <span class="icon"><i class="lni lni-layers"></i></span>
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
          <span class="icon"><i class="lni lni-package"></i></span>
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
          <span class="icon"><i class="lni lni-users"></i></span>
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
          <span class="icon"><i class="lni lni-clipboard"></i></span>
          <span class="text">Relatórios</span>
        </a>
      </li>

      <span class="divider"><hr /></span>

      <li class="nav-item nav-item-has-children">
        <a href="#0" class="collapsed" data-bs-toggle="collapse" data-bs-target="#ddmenu_config" aria-controls="ddmenu_config" aria-expanded="false">
          <span class="icon"><i class="lni lni-cog"></i></span>
          <span class="text">Configurações</span>
        </a>
        <ul id="ddmenu_config" class="collapse dropdown-nav">
          <li><a href="usuarios.php">Usuários e Permissões</a></li>
          <li><a href="parametros.php">Parâmetros do Sistema</a></li>
        </ul>
      </li>

      <li class="nav-item">
        <a href="suporte.php">
          <span class="icon"><i class="lni lni-whatsapp"></i></span>
          <span class="text">Suporte</span>
        </a>
      </li>
    </ul>
  </nav>
</aside>

<div class="overlay"></div>

<main class="main-wrapper">
  <header class="header">
    <div class="container-fluid">
      <div class="row">
        <div class="col-lg-5 col-md-5 col-6">
          <div class="header-left d-flex align-items-center">
            <div class="menu-toggle-btn mr-15">
              <button id="menu-toggle" class="main-btn primary-btn btn-hover btn-compact" type="button">
                <i class="lni lni-chevron-left me-2"></i> Menu
              </button>
            </div>
            <div class="header-search d-none d-md-flex">
              <form action="#" onsubmit="return false;">
                <input type="text" placeholder="Buscar..." disabled />
                <button type="button" onclick="return false"><i class="lni lni-search-alt"></i></button>
              </form>
            </div>
          </div>
        </div>

        <div class="col-lg-7 col-md-7 col-6">
          <div class="header-right">
            <div class="profile-box ml-15">
              <button class="dropdown-toggle bg-transparent border-0" type="button" id="profile" data-bs-toggle="dropdown" aria-expanded="false">
                <div class="profile-info">
                  <div class="info">
                    <div class="image"><img src="assets/images/profile/profile-image.png" alt="perfil" /></div>
                    <div>
                      <h6 class="fw-500">Administrador</h6>
                      <p>Distribuidora</p>
                    </div>
                  </div>
                </div>
              </button>
              <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="profile">
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

  <section class="section">
    <div class="container-fluid">
      <div class="title-wrapper pt-30">
        <div class="row align-items-center">
          <div class="col-md-8">
            <div class="title">
              <h2>Dashboard</h2>
              <div class="muted">Períodos calculados pela última data em <b>vendas.data</b>: <?= e(br_date($REF_DATE->format('Y-m-d'))) ?></div>
            </div>
          </div>
        </div>
      </div>

      <!-- Linha 1 -->
      <div class="row g-3 mb-30">
        <div class="col-12 col-lg-6">
          <div class="card-style">
            <div class="card-title-row mb-2">
              <div>
                <div class="title">Forma de Entrega</div>
                <div class="muted" id="lblDeliveryPeriod">Delivery x Presencial (Hoje)</div>
              </div>
              <select class="form-select" style="max-width:170px" id="selDeliveryPeriod">
                <option value="today" selected>Hoje</option>
                <option value="7d">Últimos 7 dias</option>
                <option value="30d">Últimos 30 dias</option>
                <option value="12m">Últimos 12 meses</option>
              </select>
            </div>
            <canvas id="chartDelivery" height="160"></canvas>
          </div>
        </div>

        <div class="col-12 col-lg-6">
          <div class="card-style">
            <div class="card-title-row mb-2">
              <div>
                <div class="title">Formas de Pagamento</div>
                <div class="muted" id="lblPayPeriod">Mais utilizadas (Hoje)</div>
                <div class="muted mt-1">
                  <span class="me-2">Vendido: <b id="paySold"><?= e(br_money($initPayments['total'])) ?></b></span>
                  <span>Recebido: <b id="payRec"><?= e(br_money($initPayments['recebido'])) ?></b></span>
                </div>
              </div>
              <select class="form-select" style="max-width:170px" id="selPayPeriod">
                <option value="today" selected>Hoje</option>
                <option value="7d">Últimos 7 dias</option>
                <option value="30d">Últimos 30 dias</option>
                <option value="12m">Últimos 12 meses</option>
              </select>
            </div>
            <canvas id="chartPayments" height="160"></canvas>
          </div>
        </div>
      </div>

      <!-- Linha 2 -->
      <div class="row g-3 mb-30">
        <div class="col-12 col-lg-8">
          <div class="card-style">
            <div class="card-title-row mb-2">
              <div>
                <div class="title">Top 7 Produtos Vendidos</div>
                <div class="muted" id="lblTopPeriod">Quantidade (Últimos 7 dias)</div>
              </div>
              <div class="d-flex gap-2 align-items-center flex-wrap">
                <select class="form-select" style="max-width:170px" id="selTopPeriod">
                  <option value="7d" selected>Últimos 7 dias</option>
                  <option value="30d">Últimos 30 dias</option>
                  <option value="12m">Últimos 12 meses</option>
                </select>
                <a class="main-btn primary-btn btn-hover btn-compact" href="relatorios.php" title="Abrir relatórios">Ver relatório</a>
              </div>
            </div>

            <canvas id="chartTopProducts" height="150"></canvas>
            <div class="muted mt-2" id="hintTopNone" style="display:none;">(sem dados)</div>

            <div class="table-responsive mt-3">
              <table class="table tbl-sm" id="tbTopTable">
                <thead>
                  <tr>
                    <th>#</th>
                    <th>Código</th>
                    <th>Produto</th>
                    <th class="text-center">Qtd</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (!$initTopRows): ?>
                    <tr><td colspan="4" class="text-center muted">Sem dados.</td></tr>
                  <?php else: ?>
                    <?php $i=1; foreach ($initTopRows as $r): ?>
                      <tr>
                        <td><?= (int)$i++ ?></td>
                        <td><?= e((string)$r['codigo']) ?></td>
                        <td><?= e((string)$r['nome']) ?></td>
                        <td class="text-center"><?= (int)$r['qtd'] ?></td>
                      </tr>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>

          </div>
        </div>

        <div class="col-12 col-lg-4">
          <div class="card-style">
            <div class="card-title-row mb-2">
              <div>
                <div class="title">Últimas Vendas</div>
                <div class="muted">Clique no olho para ver itens (venda_itens) + fiado.</div>
              </div>
            </div>

            <div class="table-responsive">
              <table class="table tbl-sm">
                <thead>
                  <tr>
                    <th>Pagamento</th>
                    <th class="text-end">Valor</th>
                    <th class="text-center">Data</th>
                    <th class="text-end">Ação</th>
                  </tr>
                </thead>
                <tbody>
                <?php foreach ($recent as $r): ?>
                  <?php
                    $id = (int)$r['id'];
                    $pag = strtoupper((string)$r['pagamento']);
                    $val = (float)$r['total'];
                    $dt  = (string)$r['data'];
                  ?>
                  <tr>
                    <td><?= e($pag ?: '—') ?></td>
                    <td class="text-end"><?= e(br_money($val)) ?></td>
                    <td class="text-center"><?= e(br_date($dt)) ?></td>
                    <td class="text-end">
                      <button class="main-btn light-btn btn-hover icon-btn btnVenda" type="button" data-id="<?= (int)$id ?>" title="Ver detalhes">
                        <i class="lni lni-eye"></i>
                      </button>
                    </td>
                  </tr>
                <?php endforeach; ?>
                <?php if (!$recent): ?>
                  <tr><td colspan="4" class="text-center muted">Sem vendas.</td></tr>
                <?php endif; ?>
                </tbody>
              </table>
            </div>

          </div>
        </div>
      </div>

      <!-- Linha 3 -->
      <div class="row g-3 mb-30">
        <div class="col-12 col-lg-4">
          <div class="card-style">
            <div class="card-title-row mb-2">
              <div>
                <div class="title">Mix (Saídas) + Devoluções</div>
                <div class="muted">12 meses (valores)</div>
              </div>
            </div>
            <canvas id="chartMix12m" height="170"></canvas>
          </div>
        </div>

        <div class="col-12 col-lg-4">
          <div class="card-style">
            <div class="card-title-row mb-2">
              <div>
                <div class="title">Canais (Top 2)</div>
                <div class="muted">Vendas + Devoluções (12m)</div>
              </div>
            </div>
            <canvas id="chartChannelsTop2" height="170"></canvas>
          </div>
        </div>

        <div class="col-12 col-lg-4">
          <div class="card-style">
            <div class="card-title-row mb-2">
              <div>
                <div class="title">Entradas x Saídas (Estoque)</div>
                <div class="muted">12 meses (qtd) • Saídas = Vendas + Perdas</div>
              </div>
            </div>
            <canvas id="chartFlow12m" height="170"></canvas>
          </div>
        </div>
      </div>

      <!-- Linha 4 -->
      <div class="row g-3 mb-30">
        <div class="col-12">
          <div class="card-style">
            <div class="card-title-row mb-2">
              <div>
                <div class="title">Produtos com Estoque Baixo</div>
                <div class="muted">Abaixo do mínimo</div>
              </div>
              <a class="main-btn primary-btn btn-hover btn-compact" href="estoque-minimo.php">Ver detalhes</a>
            </div>

            <div class="table-responsive">
              <table class="table tbl-sm">
                <thead>
                  <tr>
                    <th>Produto</th>
                    <th>Categoria</th>
                    <th class="text-center">Saldo</th>
                    <th class="text-center">Mínimo</th>
                    <th class="text-center">Dif.</th>
                  </tr>
                </thead>
                <tbody>
                <?php foreach ($low as $r): ?>
                  <?php
                    $saldo = (int)$r['estoque'];
                    $min = (int)$r['minimo'];
                    $diff = $saldo - $min;
                    $badge = ($diff < 0) ? 'badge-soft badge-soft-warning' : 'badge-soft badge-soft-gray';
                  ?>
                  <tr>
                    <td><?= e((string)$r['nome']) ?></td>
                    <td><?= e((string)($r['categoria'] ?? '—')) ?></td>
                    <td class="text-center"><?= (int)$saldo ?></td>
                    <td class="text-center"><?= (int)$min ?></td>
                    <td class="text-center"><span class="<?= e($badge) ?>"><?= (int)$diff ?></span></td>
                  </tr>
                <?php endforeach; ?>
                <?php if (!$low): ?>
                  <tr><td colspan="5" class="text-center muted">Nenhum produto abaixo do mínimo.</td></tr>
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
      </div>
    </div>
  </footer>
</main>

<!-- Modal: Detalhes da Venda -->
<div class="modal fade" id="modalVenda" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Detalhes da Venda</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
      </div>

      <div class="modal-body">
        <div class="row g-3">
          <div class="col-12 col-lg-3">
            <label class="form-label">Venda</label>
            <input class="form-control" id="mVendaId" readonly />
          </div>
          <div class="col-12 col-lg-3">
            <label class="form-label">Data</label>
            <input class="form-control" id="mVendaData" readonly />
          </div>
          <div class="col-12 col-lg-3">
            <label class="form-label">Canal</label>
            <input class="form-control" id="mVendaCanal" readonly />
          </div>
          <div class="col-12 col-lg-3">
            <label class="form-label">Pagamento</label>
            <input class="form-control" id="mVendaPag" readonly />
          </div>

          <div class="col-12 col-lg-6">
            <label class="form-label">Cliente</label>
            <input class="form-control" id="mVendaCliente" readonly />
          </div>

          <div class="col-12 col-lg-6">
            <label class="form-label">Endereço / Observações</label>
            <textarea class="form-control" id="mVendaEndObs" rows="2" readonly></textarea>
            <div class="muted mt-2">Pagamento (detalhes):</div>
            <pre class="pay-json" id="mPayJson">—</pre>
            <div class="muted mt-2" id="mFiadoBox" style="display:none;"></div>
          </div>

          <div class="col-12 col-lg-4">
            <label class="form-label">Subtotal</label>
            <input class="form-control" id="mVendaSubtotal" readonly />
          </div>
          <div class="col-12 col-lg-4">
            <label class="form-label">Desconto</label>
            <input class="form-control" id="mVendaDesconto" readonly />
          </div>
          <div class="col-12 col-lg-4">
            <label class="form-label">Total / Recebido</label>
            <input class="form-control" id="mVendaTotal" readonly />
          </div>

          <div class="col-12 mt-2"><hr></div>

          <div class="col-12">
            <div style="font-weight:900; color:#0f172a;">Itens vendidos</div>
            <div class="table-responsive mt-2">
              <table class="table tbl-sm" id="tbItens">
                <thead>
                  <tr>
                    <th>#</th>
                    <th>Produto</th>
                    <th class="text-center">Qtd</th>
                    <th class="text-end">V. Unit</th>
                    <th class="text-end">Subtotal</th>
                  </tr>
                </thead>
                <tbody><tr><td colspan="5" class="muted">Carregando…</td></tr></tbody>
              </table>
            </div>
          </div>

          <div class="col-12 mt-2">
            <div style="font-weight:900; color:#0f172a;">Devoluções vinculadas</div>
            <div class="table-responsive mt-2">
              <table class="table tbl-sm" id="tbDevols">
                <thead>
                  <tr>
                    <th>#</th>
                    <th>Data</th>
                    <th>Hora</th>
                    <th>Tipo</th>
                    <th>Produto</th>
                    <th class="text-center">Qtd</th>
                    <th class="text-end">Valor</th>
                    <th>Status</th>
                  </tr>
                </thead>
                <tbody><tr><td colspan="8" class="muted">Carregando…</td></tr></tbody>
              </table>
            </div>
            <div class="muted" id="mDevolHint"></div>
          </div>

        </div>
      </div>

      <div class="modal-footer">
        <button class="main-btn light-btn btn-hover btn-compact" type="button" data-bs-dismiss="modal">Fechar</button>
      </div>
    </div>
  </div>
</div>

<script src="assets/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/main.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>

<script>
  const INIT = {
    delivery: <?= json_encode($initDelivery, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
    payments: <?= json_encode($initPayments, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
    top: <?= json_encode($initTop, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
    topRows: <?= json_encode($initTopRows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
    mix12m: <?= json_encode($mix12m, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
    channelsTop2: <?= json_encode($channelsTop2, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
    flow12m: <?= json_encode($flow12m, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
  };

  function moneyBR(n){
    const v = Number(n||0);
    return "R$ " + v.toFixed(2).replace(".", ",").replace(/\B(?=(\d{3})+(?!\d))/g, ".");
  }
  function safeText(s){
    return String(s ?? "")
      .replaceAll("&","&amp;").replaceAll("<","&lt;").replaceAll(">","&gt;")
      .replaceAll('"',"&quot;").replaceAll("'","&#039;");
  }
  function labelPeriod(p){
    if (p === 'today') return 'Hoje';
    if (p === '7d') return 'Últimos 7 dias';
    if (p === '30d') return 'Últimos 30 dias';
    if (p === '12m') return 'Últimos 12 meses';
    return p;
  }

  // ===== charts init =====
  const chartDelivery = new Chart(document.getElementById('chartDelivery'), {
    type: 'doughnut',
    data: { labels: INIT.delivery.labels, datasets: [{ data: INIT.delivery.values }] },
    options: { responsive:true, plugins:{ legend:{ position:'bottom' } } }
  });

  const chartPayments = new Chart(document.getElementById('chartPayments'), {
    type: 'bar',
    data: { labels: INIT.payments.labels, datasets: [{ label:'Qtd', data: INIT.payments.values }] },
    options: { responsive:true, plugins:{ legend:{ display:false } }, scales:{ y:{ beginAtZero:true } } }
  });

  const chartTop = new Chart(document.getElementById('chartTopProducts'), {
    type: 'bar',
    data: { labels: INIT.top.labels, datasets: [{ label:'Qtd', data: INIT.top.values }] },
    options: { responsive:true, plugins:{ legend:{ display:false } }, scales:{ y:{ beginAtZero:true } } }
  });

  const chartMix12m = new Chart(document.getElementById('chartMix12m'), {
    type: 'bar',
    data: {
      labels: INIT.mix12m.labels,
      datasets: [
        { label:'Saídas (Vendas+Perdas)', data: INIT.mix12m.saidas, stack:'x' },
        { label:'Devoluções', data: INIT.mix12m.devolucoes, stack:'x' },
      ]
    },
    options: { responsive:true, plugins:{ legend:{ position:'bottom' } }, scales:{ y:{ beginAtZero:true } } }
  });

  const chartChannelsTop2 = new Chart(document.getElementById('chartChannelsTop2'), {
    type: 'bar',
    data: { labels: INIT.channelsTop2.labels, datasets: [{ label:'Valor', data: INIT.channelsTop2.values }] },
    options: { responsive:true, plugins:{ legend:{ display:false } }, scales:{ y:{ beginAtZero:true } } }
  });

  const chartFlow12m = new Chart(document.getElementById('chartFlow12m'), {
    type: 'line',
    data: {
      labels: INIT.flow12m.labels,
      datasets: [
        { label:'Entradas (qtd)', data: INIT.flow12m.entradas, tension:.25 },
        { label:'Saídas (qtd)', data: INIT.flow12m.saidas, tension:.25 },
      ]
    },
    options: { responsive:true, plugins:{ legend:{ position:'bottom' } }, scales:{ y:{ beginAtZero:true } } }
  });

  // ===== dropdown loaders =====
  async function loadChart(name, period){
    const qs = new URLSearchParams({ action:'chart', name, period });
    const res = await fetch('dashboard.php?' + qs.toString(), { headers:{ 'Accept':'application/json' }});
    const js = await res.json().catch(()=>null);
    if (!js || !js.ok) throw new Error((js && js.error) ? js.error : 'Falha ao carregar gráfico.');
    return js.data;
  }

  const selDeliveryPeriod = document.getElementById('selDeliveryPeriod');
  const selPayPeriod = document.getElementById('selPayPeriod');
  const selTopPeriod = document.getElementById('selTopPeriod');

  const lblDeliveryPeriod = document.getElementById('lblDeliveryPeriod');
  const lblPayPeriod = document.getElementById('lblPayPeriod');
  const lblTopPeriod = document.getElementById('lblTopPeriod');

  const paySold = document.getElementById('paySold');
  const payRec = document.getElementById('payRec');
  const hintTopNone = document.getElementById('hintTopNone');

  const tbTop = document.getElementById('tbTopTable').querySelector('tbody');

  function renderTopTable(rows){
    const arr = Array.isArray(rows) ? rows : [];
    if (!arr.length) {
      tbTop.innerHTML = `<tr><td colspan="4" class="text-center muted">Sem dados.</td></tr>`;
      return;
    }
    tbTop.innerHTML = arr.slice(0,7).map((r, i) => `
      <tr>
        <td>${i+1}</td>
        <td>${safeText(r.codigo || '')}</td>
        <td>${safeText(r.nome || '')}</td>
        <td class="text-center">${Number(r.qtd || 0)}</td>
      </tr>
    `).join('');
  }

  selDeliveryPeriod.addEventListener('change', async () => {
    try {
      const p = selDeliveryPeriod.value;
      lblDeliveryPeriod.textContent = `Delivery x Presencial (${labelPeriod(p)})`;
      const data = await loadChart('delivery', p);
      chartDelivery.data.labels = data.labels;
      chartDelivery.data.datasets[0].data = data.values;
      chartDelivery.update();
    } catch(e){ console.error(e); }
  });

  selPayPeriod.addEventListener('change', async () => {
    try {
      const p = selPayPeriod.value;
      lblPayPeriod.textContent = `Mais utilizadas (${labelPeriod(p)})`;
      const data = await loadChart('payments', p);
      chartPayments.data.labels = data.labels || [];
      chartPayments.data.datasets[0].data = data.values || [];
      chartPayments.update();

      paySold.textContent = moneyBR(data.total || 0);
      payRec.textContent = moneyBR(data.recebido || 0);
    } catch(e){ console.error(e); }
  });

  selTopPeriod.addEventListener('change', async () => {
    try {
      const p = selTopPeriod.value;
      lblTopPeriod.textContent = `Quantidade (${labelPeriod(p)})`;
      const data = await loadChart('top_products', p);

      chartTop.data.labels = data.labels || [];
      chartTop.data.datasets[0].data = data.values || [];
      chartTop.update();

      hintTopNone.style.display = (data.labels && data.labels.length) ? 'none' : 'block';
      renderTopTable(data.rows || []);
    } catch(e){ console.error(e); }
  });

  // ===== modal detalhes venda =====
  const modalVenda = new bootstrap.Modal(document.getElementById('modalVenda'));

  const mVendaId = document.getElementById('mVendaId');
  const mVendaData = document.getElementById('mVendaData');
  const mVendaCanal = document.getElementById('mVendaCanal');
  const mVendaPag = document.getElementById('mVendaPag');
  const mVendaCliente = document.getElementById('mVendaCliente');
  const mVendaEndObs = document.getElementById('mVendaEndObs');
  const mPayJson = document.getElementById('mPayJson');
  const mFiadoBox = document.getElementById('mFiadoBox');

  const mVendaSubtotal = document.getElementById('mVendaSubtotal');
  const mVendaDesconto = document.getElementById('mVendaDesconto');
  const mVendaTotal = document.getElementById('mVendaTotal');

  const tbItens = document.getElementById('tbItens').querySelector('tbody');
  const tbDevols = document.getElementById('tbDevols').querySelector('tbody');
  const mDevolHint = document.getElementById('mDevolHint');

  function fmtISOToBR(iso){
    if (!iso) return '—';
    const p = String(iso).split('-');
    if (p.length !== 3) return iso;
    return `${p[2]}/${p[1]}/${p[0]}`;
  }

  async function openVenda(id){
    tbItens.innerHTML = `<tr><td colspan="5" class="muted">Carregando…</td></tr>`;
    tbDevols.innerHTML = `<tr><td colspan="8" class="muted">Carregando…</td></tr>`;
    mDevolHint.textContent = '';
    mFiadoBox.style.display = 'none';
    mFiadoBox.textContent = '';

    const qs = new URLSearchParams({ action:'venda_details', id:String(id) });
    const res = await fetch('dashboard.php?' + qs.toString(), { headers:{ 'Accept':'application/json' }});
    const js = await res.json().catch(()=>null);
    if (!js || !js.ok) throw new Error((js && js.error) ? js.error : 'Falha ao carregar venda.');

    const v = js.venda || {};
    mVendaId.value = '#V-' + String(v.id || id).padStart(4,'0');
    mVendaData.value = fmtISOToBR(v.data || '');
    mVendaCanal.value = String(v.canal || '—');
    mVendaPag.value = String(v.pagamento || '—');
    mVendaCliente.value = (v.cliente && String(v.cliente).trim()) ? String(v.cliente) : 'Consumidor Final';

    const end = (v.endereco && String(v.endereco).trim()) ? String(v.endereco).trim() : '';
    const obs = (v.obs && String(v.obs).trim()) ? String(v.obs).trim() : '';
    mVendaEndObs.value = (end || obs) ? [end, obs].filter(Boolean).join("\n") : '—';

    const pj = (v.pagamento_json_fmt && String(v.pagamento_json_fmt).trim()) ? String(v.pagamento_json_fmt) : '—';
    mPayJson.textContent = pj;

    mVendaSubtotal.value = moneyBR(v.subtotal || 0);
    mVendaDesconto.value = String(v.desconto_text || '—');

    const total = Number(v.total || 0);
    const rec = Number(v.recebido ?? total);
    mVendaTotal.value = `${moneyBR(total)}  |  Recebido: ${moneyBR(rec)}`;

    // fiado extra
    if (v.fiado && typeof v.fiado === 'object') {
      const f = v.fiado;
      mFiadoBox.style.display = 'block';
      mFiadoBox.textContent = `Fiado: Total ${moneyBR(f.valor_total||0)} | Pago ${moneyBR(f.valor_pago||0)} | Restante ${moneyBR(f.valor_restante||0)} | Status ${String(f.status||'')}`;
    }

    const itens = Array.isArray(js.itens) ? js.itens : [];
    if (!itens.length) {
      tbItens.innerHTML = `<tr><td colspan="5" class="muted">Sem itens cadastrados (venda_itens).</td></tr>`;
    } else {
      tbItens.innerHTML = itens.map((it, idx) => {
        const nome = `${safeText(it.nome || '')} <span class="muted">${safeText(it.unidade || '')}</span>`;
        return `
          <tr>
            <td>${idx+1}</td>
            <td>${nome}</td>
            <td class="text-center">${Number(it.qtd||0)}</td>
            <td class="text-end">${moneyBR(it.preco_unit||0)}</td>
            <td class="text-end">${moneyBR(it.subtotal||0)}</td>
          </tr>
        `;
      }).join('');
    }

    const devols = Array.isArray(js.devolucoes) ? js.devolucoes : [];
    if (!devols.length) {
      tbDevols.innerHTML = `<tr><td colspan="8" class="muted">Nenhuma devolução.</td></tr>`;
    } else {
      tbDevols.innerHTML = devols.map(d => {
        const prod = (String(d.tipo||'') === 'PARCIAL') ? (d.produto || '—') : '—';
        const qtd = (String(d.tipo||'') === 'PARCIAL') ? (d.qtd ?? '—') : '—';
        return `
          <tr>
            <td>${Number(d.id||0)}</td>
            <td>${fmtISOToBR(d.data||'')}</td>
            <td>${safeText(d.hora||'—')}</td>
            <td>${safeText(d.tipo||'—')}</td>
            <td>${safeText(prod||'—')}</td>
            <td class="text-center">${qtd === null ? '—' : safeText(String(qtd))}</td>
            <td class="text-end">${moneyBR(d.valor||0)}</td>
            <td>${safeText(d.status||'—')}</td>
          </tr>
        `;
      }).join('');
      mDevolHint.textContent = 'Obs.: devoluções CANCELADO não entram nos totalizadores.';
    }

    modalVenda.show();
  }

  document.querySelectorAll('.btnVenda').forEach(btn => {
    btn.addEventListener('click', async () => {
      const id = btn.getAttribute('data-id') || '';
      if (!id) return;
      try { await openVenda(id); } catch(e){ alert(e.message || e); }
    });
  });

  // init top hint/table
  hintTopNone.style.display = (INIT.top.labels && INIT.top.labels.length) ? 'none' : 'block';
  renderTopTable(INIT.topRows || []);
</script>

</body>
</html>