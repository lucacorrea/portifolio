<?php
declare(strict_types=1);

@date_default_timezone_set('America/Manaus');
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

/**
 * relatorios.php
 * - Página + API JSON no mesmo arquivo
 * - Tabelas: vendas, venda_itens (principal), saidas (fallback), devolucoes (opcional)
 *
 * Coloque este arquivo na raiz onde ficam:
 *   /assets/dados/_helpers.php
 *   /assets/conexao.php   (deve conter db():PDO)
 */

/* ============================
   1) ACTION antes dos includes
============================ */
$action = strtolower(isset($_GET['action']) ? (string)$_GET['action'] : '');
$isApi  = in_array($action, ['fetch','one','suggest'], true);

/* ============================
   2) Buffer anti-“lixo” dos includes
============================ */
ob_start();

/* ============================
   3) Handlers para API JSON
============================ */
if ($isApi) {
  ini_set('display_errors', '0');
  error_reporting(E_ALL);

  register_shutdown_function(function () use ($action) {
    $isApiLocal = in_array($action, ['fetch','one','suggest'], true);
    if (!$isApiLocal) return;
    $err = error_get_last();
    if (!$err) return;

    while (ob_get_level() > 0) { @ob_end_clean(); }
    http_response_code(500);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode([
      'ok'  => false,
      'msg' => 'Erro fatal: '.$err['message'].' em '.$err['file'].':'.$err['line'],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
  });

  set_exception_handler(function ($e) {
    while (ob_get_level() > 0) { @ob_end_clean(); }
    http_response_code(500);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode([
      'ok'  => false,
      'msg' => 'Exceção: '.$e->getMessage(),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
  });
}

/* ========= INCLUDES ========= */
$helpers = __DIR__ . '/assets/dados/_helpers.php';
if (is_file($helpers)) require_once $helpers;

$con = __DIR__ . '/assets/conexao.php';
if (is_file($con)) require_once $con;

/* ========= DESCARTA qualquer saída dos includes ========= */
$bootNoise = ob_get_clean();
ob_start(); // buffer normal

/* ========= FALLBACKS ========= */
if (!function_exists('e')) {
  function e(string $v): string { return htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
}
if (!function_exists('csrf_token')) {
  function csrf_token(): string {
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    if (empty($_SESSION['_csrf'])) $_SESSION['_csrf'] = bin2hex(random_bytes(16));
    return (string)$_SESSION['_csrf'];
  }
}

/* ========= UTIL ========= */
function json_out(array $payload, int $code = 200): void {
  while (ob_get_level() > 0) { @ob_end_clean(); }
  http_response_code($code);
  header('Content-Type: application/json; charset=UTF-8');
  echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  exit;
}
function get_str(string $k, string $def = ''): string {
  return isset($_GET[$k]) ? trim((string)$_GET[$k]) : $def;
}
function get_int(string $k, int $def = 0): int {
  $v = isset($_GET[$k]) ? (int)$_GET[$k] : $def;
  return $v > 0 ? $v : $def;
}
function brl(float $v): string {
  return 'R$ ' . number_format($v, 2, ',', '.');
}
function table_exists(PDO $pdo, string $table): bool {
  $st = $pdo->prepare("SHOW TABLES LIKE :t");
  $st->execute(['t' => $table]);
  return (bool)$st->fetchColumn();
}
function table_columns(PDO $pdo, string $table): array {
  try {
    $st = $pdo->query("DESCRIBE `$table`");
    $cols = [];
    while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
      $cols[] = (string)$r['Field'];
    }
    return $cols;
  } catch (Throwable $e) {
    return [];
  }
}

/* ========= db() obrigatória ========= */
if (!function_exists('db')) {
  if ($isApi) {
    json_out([
      'ok' => false,
      'msg' => "ERRO: db():PDO não encontrada. Verifique assets/conexao.php (não pode ser dump SQL).",
    ], 500);
  }
  while (ob_get_level() > 0) { @ob_end_clean(); }
  http_response_code(500);
  echo "ERRO: função db():PDO não encontrada. Verifique assets/conexao.php";
  exit;
}

/* ========= FILTROS ========= */
function build_where(array &$params): string {
  $where = " WHERE 1=1 ";

  $di = get_str('di');
  $df = get_str('df');
  $canal = strtoupper(get_str('canal', 'TODOS'));
  $pag   = strtoupper(get_str('pag', 'TODOS'));
  $q     = get_str('q');

  if ($di !== '') { $where .= " AND v.data >= :di "; $params['di'] = $di; }
  if ($df !== '') { $where .= " AND v.data <= :df "; $params['df'] = $df; }
  if ($canal !== '' && $canal !== 'TODOS') { $where .= " AND v.canal = :canal "; $params['canal'] = $canal; }
  if ($pag !== '' && $pag !== 'TODOS') { $where .= " AND v.pagamento = :pag "; $params['pag'] = $pag; }

  if ($q !== '') {
    if (ctype_digit($q)) {
      $where .= " AND v.id = :vid ";
      $params['vid'] = (int)$q;
    } else {
      $where .= " AND (v.cliente LIKE :q OR v.endereco LIKE :q OR v.obs LIKE :q OR v.pagamento LIKE :q) ";
      $params['q'] = '%' . $q . '%';
    }
  }
  return $where;
}

/* ========= ITENS (venda_itens principal) ========= */
function venda_itens_map(PDO $pdo): array {
  $cols = table_columns($pdo, 'venda_itens');
  $has = function ($c) use ($cols) { return in_array($c, $cols, true); };

  // Mapeia possíveis variações (se um dia mudar)
  $map = [
    'venda_id'   => $has('venda_id') ? 'venda_id' : 'venda_id',
    'produto_id' => $has('produto_id') ? 'produto_id' : null,
    'codigo'     => $has('codigo') ? 'codigo' : null,
    'nome'       => $has('nome') ? 'nome' : null,
    'unidade'    => $has('unidade') ? 'unidade' : null,
    'preco_unit' => $has('preco_unit') ? 'preco_unit' : ($has('preco') ? 'preco' : null),
    'qtd'        => $has('qtd') ? 'qtd' : ($has('quantidade') ? 'quantidade' : null),
    'subtotal'   => $has('subtotal') ? 'subtotal' : ($has('total') ? 'total' : null),
  ];
  return $map;
}

/**
 * Itens da venda:
 * ✅ PRINCIPAL: venda_itens (venda_id)
 * 🔁 FALLBACK: saidas (pedido)
 */
function fetch_items_for_sale_ids(array $saleIds): array {
  if (!$saleIds) return [];
  $pdo = db();

  // normaliza IDs
  $uniq = [];
  foreach ($saleIds as $id) {
    $id = (int)$id;
    if ($id > 0) $uniq[$id] = true;
  }
  $saleIds = array_keys($uniq);
  if (!$saleIds) return [];

  $out = [];

  // 1) venda_itens (correto)
  if (table_exists($pdo, 'venda_itens')) {
    $map = venda_itens_map($pdo);

    $in = implode(',', array_fill(0, count($saleIds), '?'));

    // Se tiver snapshot completo (codigo/nome etc), usa direto
    $canSnapshot = ($map['codigo'] && $map['nome'] && $map['preco_unit'] && $map['qtd']);
    if ($canSnapshot) {
      $sql = "
        SELECT
          vi.`{$map['venda_id']}`   AS venda_id,
          ".($map['codigo'] ? "vi.`{$map['codigo']}`" : "''")." AS codigo,
          ".($map['nome'] ? "vi.`{$map['nome']}`" : "'Item'")." AS nome,
          ".($map['unidade'] ? "vi.`{$map['unidade']}`" : "NULL")." AS unidade,
          ".($map['preco_unit'] ? "vi.`{$map['preco_unit']}`" : "0")." AS preco_unit,
          ".($map['qtd'] ? "vi.`{$map['qtd']}`" : "0")." AS qtd,
          ".($map['subtotal'] ? "vi.`{$map['subtotal']}`" : "0")." AS subtotal
        FROM venda_itens vi
        WHERE vi.`{$map['venda_id']}` IN ($in)
        ORDER BY vi.id ASC
      ";
      $st = $pdo->prepare($sql);
      $st->execute($saleIds);
      $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

      foreach ($rows as $r) {
        $sid = (int)($r['venda_id'] ?? 0);
        if ($sid <= 0) continue;
        if (!isset($out[$sid])) $out[$sid] = [];

        $preco = (float)($r['preco_unit'] ?? 0);
        $qtd   = (float)($r['qtd'] ?? 0);
        $tot   = (float)($r['subtotal'] ?? 0);
        if ($tot <= 0 && $preco > 0 && $qtd > 0) $tot = $preco * $qtd;

        $out[$sid][] = [
          'codigo' => (string)($r['codigo'] ?? ''),
          'nome'   => (string)($r['nome'] ?? 'Item'),
          'qtd'    => $qtd,
          'un'     => (string)($r['unidade'] ?? ''),
          'preco'  => $preco,
          'total'  => $tot,
        ];
      }

      if (!empty($out)) return $out;
    }

    // Se não tiver snapshot, tenta via join com produtos (fallback interno)
    if (table_exists($pdo, 'produtos') && $map['produto_id'] && $map['preco_unit'] && $map['qtd']) {
      $sql = "
        SELECT
          vi.`{$map['venda_id']}` AS venda_id,
          p.codigo AS codigo,
          p.nome   AS nome,
          COALESCE(vi.`{$map['unidade']}`, p.unidade, '') AS unidade,
          vi.`{$map['preco_unit']}` AS preco_unit,
          vi.`{$map['qtd']}` AS qtd,
          ".($map['subtotal'] ? "vi.`{$map['subtotal']}`" : "0")." AS subtotal
        FROM venda_itens vi
        LEFT JOIN produtos p ON p.id = vi.`{$map['produto_id']}`
        WHERE vi.`{$map['venda_id']}` IN ($in)
        ORDER BY vi.id ASC
      ";
      $st = $pdo->prepare($sql);
      $st->execute($saleIds);
      $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

      foreach ($rows as $r) {
        $sid = (int)($r['venda_id'] ?? 0);
        if ($sid <= 0) continue;
        if (!isset($out[$sid])) $out[$sid] = [];

        $preco = (float)($r['preco_unit'] ?? 0);
        $qtd   = (float)($r['qtd'] ?? 0);
        $tot   = (float)($r['subtotal'] ?? 0);
        if ($tot <= 0 && $preco > 0 && $qtd > 0) $tot = $preco * $qtd;

        $out[$sid][] = [
          'codigo' => (string)($r['codigo'] ?? ''),
          'nome'   => (string)($r['nome'] ?? 'Item'),
          'qtd'    => $qtd,
          'un'     => (string)($r['unidade'] ?? ''),
          'preco'  => $preco,
          'total'  => $tot,
        ];
      }

      if (!empty($out)) return $out;
    }
  }

  // 2) fallback: saidas (pedido)
  if (!table_exists($pdo, 'saidas')) return [];

  $keyToId = [];
  $keys = [];
  foreach ($saleIds as $id) {
    $k1 = (string)$id;
    $k2 = str_pad((string)$id, 5, '0', STR_PAD_LEFT);
    $k3 = 'V' . $id;
    foreach ([$k1,$k2,$k3] as $k) {
      $keyToId[$k] = $id;
      $keys[$k] = true;
    }
  }
  $keys = array_keys($keys);
  if (!$keys) return [];

  $in = implode(',', array_fill(0, count($keys), '?'));
  $sql = "
    SELECT
      s.pedido, s.qtd, s.unidade, s.preco, s.total,
      p.codigo AS prod_codigo, p.nome AS prod_nome
    FROM saidas s
    LEFT JOIN produtos p ON p.id = s.produto_id
    WHERE s.pedido IN ($in)
    ORDER BY s.id ASC
  ";
  $stmt = $pdo->prepare($sql);
  $stmt->execute($keys);
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

  foreach ($rows as $r) {
    $pedido = (string)($r['pedido'] ?? '');
    if (!isset($keyToId[$pedido])) continue;
    $sid = (int)$keyToId[$pedido];

    if (!isset($out[$sid])) $out[$sid] = [];
    $out[$sid][] = [
      'codigo' => (string)($r['prod_codigo'] ?? ''),
      'nome'   => (string)($r['prod_nome'] ?? 'Item'),
      'qtd'    => (float)($r['qtd'] ?? 0),
      'un'     => (string)($r['unidade'] ?? ''),
      'preco'  => (float)($r['preco'] ?? 0),
      'total'  => (float)($r['total'] ?? 0),
    ];
  }

  return $out;
}

/* ========= DEVOLUÇÕES (opcional) ========= */
function fetch_devolucoes_for_sale_ids(array $saleIds): array {
  if (!$saleIds) return [];
  $pdo = db();
  if (!table_exists($pdo, 'devolucoes')) return [];

  $uniq = [];
  foreach ($saleIds as $id) {
    $id = (int)$id;
    if ($id > 0) $uniq[$id] = true;
  }
  $saleIds = array_keys($uniq);
  if (!$saleIds) return [];

  $in = implode(',', array_fill(0, count($saleIds), '?'));
  $sql = "
    SELECT
      d.venda_no,
      COUNT(*) AS qtd,
      COALESCE(SUM(d.valor),0) AS valor,
      SUBSTRING_INDEX(GROUP_CONCAT(d.status ORDER BY d.updated_at DESC SEPARATOR ','), ',', 1) AS status,
      MAX(d.updated_at) AS updated_at
    FROM devolucoes d
    WHERE d.venda_no IN ($in)
    GROUP BY d.venda_no
  ";
  $st = $pdo->prepare($sql);
  $st->execute($saleIds);
  $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

  $out = [];
  foreach ($rows as $r) {
    $sid = (int)($r['venda_no'] ?? 0);
    if ($sid <= 0) continue;
    $out[$sid] = [
      'qtd' => (int)($r['qtd'] ?? 0),
      'valor' => (float)($r['valor'] ?? 0),
      'status' => (string)($r['status'] ?? ''),
      'updated_at' => (string)($r['updated_at'] ?? ''),
    ];
  }
  return $out;
}

function fetch_devolucoes_list(int $saleId): array {
  $pdo = db();
  if (!table_exists($pdo, 'devolucoes')) return [];
  $st = $pdo->prepare("SELECT * FROM devolucoes WHERE venda_no = :id ORDER BY id DESC");
  $st->execute(['id' => $saleId]);
  return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/* ========= HELPERS ========= */
function itens_preview(array $itens, int $max = 3): string {
  if (!$itens) return '';
  $parts = [];
  $i = 0;
  foreach ($itens as $it) {
    $i++;
    $nome = (string)($it['nome'] ?? 'Item');
    $qtd  = (float)($it['qtd'] ?? 0);
    $parts[] = $nome . ($qtd > 0 ? " ({$qtd})" : '');
    if ($i >= $max) break;
  }
  $txt = implode(', ', $parts);
  if (count($itens) > $max) $txt .= '…';
  return $txt;
}

function parse_pagamento_json(?string $json): array {
  if (!$json) return [];
  $arr = json_decode($json, true);
  return is_array($arr) ? $arr : [];
}

function fetch_one_sale(int $id): ?array {
  $pdo = db();
  $stmt = $pdo->prepare("SELECT * FROM vendas WHERE id = :id LIMIT 1");
  $stmt->execute(['id' => $id]);
  $v = $stmt->fetch(PDO::FETCH_ASSOC);
  if (!$v) return null;

  $itemsMap = fetch_items_for_sale_ids([$id]);
  $itens = isset($itemsMap[$id]) ? $itemsMap[$id] : [];

  $itSubtotal = 0.0;
  $itQtd = 0.0;
  foreach ($itens as $it) {
    $itSubtotal += (float)($it['total'] ?? 0);
    $itQtd += (float)($it['qtd'] ?? 0);
  }

  $devolList = fetch_devolucoes_list($id);
  $devolSum = 0.0;
  foreach ($devolList as $d) $devolSum += (float)($d['valor'] ?? 0);

  return [
    'venda' => $v,
    'itens' => $itens,
    'itens_total' => $itSubtotal,
    'itens_qtd' => $itQtd,
    'itens_preview' => itens_preview($itens, 999),
    'pagamento_obj' => parse_pagamento_json($v['pagamento_json'] ?? null),
    'devolucoes' => $devolList,
    'devolucoes_total' => $devolSum,
  ];
}

/* ============================
   AÇÕES API
============================ */

/* ====== SUGGEST ====== */
if ($action === 'suggest') {
  $q = get_str('q');
  if (function_exists('mb_strlen')) {
    if (mb_strlen($q) < 2) json_out(['ok' => true, 'items' => []]);
  } else {
    if (strlen($q) < 2) json_out(['ok' => true, 'items' => []]);
  }

  $pdo = db();
  $stmt = $pdo->prepare("
    SELECT DISTINCT cliente
    FROM vendas
    WHERE cliente IS NOT NULL AND cliente <> ''
      AND cliente LIKE :q
    ORDER BY cliente
    LIMIT 10
  ");
  $stmt->execute(['q' => $q . '%']);
  $items = [];
  while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) $items[] = (string)$r['cliente'];

  json_out(['ok' => true, 'items' => $items]);
}

/* ====== FETCH ====== */
if ($action === 'fetch') {
  try {
    $pdo = db();

    $page = max(1, get_int('page', 1));
    $per  = get_int('per', 25);
    $per  = in_array($per, [10,25,50,100], true) ? $per : 25;
    $off  = ($page - 1) * $per;

    $params = [];
    $where = build_where($params);

    $sqlTot = "
      SELECT
        COUNT(*) AS qtd,
        COALESCE(SUM(v.subtotal),0) AS subtotal,
        COALESCE(SUM(v.desconto_valor),0) AS desconto,
        COALESCE(SUM(v.taxa_entrega),0) AS taxa,
        COALESCE(SUM(v.total),0) AS total
      FROM vendas v
      $where
    ";
    $stTot = $pdo->prepare($sqlTot);
    $stTot->execute($params);
    $tot = $stTot->fetch(PDO::FETCH_ASSOC) ?: ['qtd'=>0,'subtotal'=>0,'desconto'=>0,'taxa'=>0,'total'=>0];

    $sql = "
      SELECT
        v.id, v.data, v.cliente, v.canal, v.endereco, v.obs,
        v.desconto_tipo, v.desconto_valor, v.taxa_entrega,
        v.subtotal, v.total, v.pagamento_mode, v.pagamento, v.pagamento_json,
        v.created_at
      FROM vendas v
      $where
      ORDER BY v.id DESC
      LIMIT $per OFFSET $off
    ";
    $st = $pdo->prepare($sql);
    $st->execute($params);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $ids = [];
    foreach ($rows as $r) $ids[] = (int)$r['id'];

    $itemsMap = fetch_items_for_sale_ids($ids);
    $devolMap = fetch_devolucoes_for_sale_ids($ids);

    $outRows = [];
    foreach ($rows as $r) {
      $id = (int)$r['id'];
      $itens = isset($itemsMap[$id]) ? $itemsMap[$id] : [];

      $itTotal = 0.0;
      $itCount = 0;
      foreach ($itens as $it) { $itTotal += (float)($it['total'] ?? 0); $itCount++; }

      $devol = $devolMap[$id] ?? ['qtd'=>0,'valor'=>0,'status'=>'','updated_at'=>''];

      $outRows[] = [
        'id' => $id,
        'data' => (string)$r['data'],
        'created_at' => (string)($r['created_at'] ?? ''),
        'cliente' => (string)($r['cliente'] ?? ''),
        'canal' => (string)($r['canal'] ?? ''),
        'pagamento' => (string)($r['pagamento'] ?? ''),
        'subtotal' => (float)($r['subtotal'] ?? 0),
        'desconto' => (float)($r['desconto_valor'] ?? 0),
        'taxa' => (float)($r['taxa_entrega'] ?? 0),
        'total' => (float)($r['total'] ?? 0),
        'endereco' => (string)($r['endereco'] ?? ''),
        'obs' => (string)($r['obs'] ?? ''),
        'itens_count' => $itCount,
        'itens_total' => $itTotal,
        'itens_preview' => itens_preview($itens, 3),
        // se você quiser mandar itens completos no listão, descomente:
        'itens' => $itens,
        'devolucao' => $devol,
      ];
    }

    $totalCount = (int)($tot['qtd'] ?? 0);
    $pages = (int)max(1, ceil($totalCount / $per));

    json_out([
      'ok' => true,
      'meta' => [
        'page' => $page,
        'per' => $per,
        'pages' => $pages,
        'total' => $totalCount,
      ],
      'totais' => [
        'qtd' => (int)($tot['qtd'] ?? 0),
        'subtotal' => (float)($tot['subtotal'] ?? 0),
        'desconto' => (float)($tot['desconto'] ?? 0),
        'taxa' => (float)($tot['taxa'] ?? 0),
        'total' => (float)($tot['total'] ?? 0),
      ],
      'rows' => $outRows,
    ]);
  } catch (Throwable $e) {
    json_out(['ok' => false, 'msg' => 'Erro no fetch: '.$e->getMessage()], 500);
  }
}

/* ====== ONE ====== */
if ($action === 'one') {
  $id = get_int('id', 0);
  if ($id <= 0) json_out(['ok' => false, 'msg' => 'ID inválido'], 400);

  $one = fetch_one_sale($id);
  if (!$one) json_out(['ok' => false, 'msg' => 'Venda não encontrada'], 404);

  json_out(['ok' => true, 'data' => $one]);
}

/* ====== EXCEL ====== */
if ($action === 'excel') {
  while (ob_get_level() > 0) { @ob_end_clean(); }

  $pdo = db();
  $params = [];
  $where = build_where($params);

  $sql = "
    SELECT
      v.id, v.data, v.cliente, v.canal, v.pagamento,
      v.subtotal, v.desconto_valor, v.taxa_entrega, v.total
    FROM vendas v
    $where
    ORDER BY v.id DESC
    LIMIT 5000
  ";
  $st = $pdo->prepare($sql);
  $st->execute($params);
  $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

  $ids = [];
  foreach ($rows as $r) $ids[] = (int)$r['id'];
  $itemsMap = fetch_items_for_sale_ids($ids);

  $agora = date('d/m/Y H:i');
  $di = get_str('di') ?: '—';
  $df = get_str('df') ?: '—';
  $canal = get_str('canal', 'TODOS');
  $pag = get_str('pag', 'TODOS');
  $q = get_str('q') ?: '—';

  $fname = 'vendidos_' . date('Y-m-d_His') . '.xls';

  header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
  header('Content-Disposition: attachment; filename="'.$fname.'"');
  header('Pragma: no-cache');
  header('Expires: 0');

  echo "\xEF\xBB\xBF";
  ?>
  <table border="0" cellpadding="4" cellspacing="0" style="width:100%;">
    <tr><td colspan="10" align="center" style="font-size:16px;font-weight:900;">PAINEL DA DISTRIBUIDORA - VENDIDOS</td></tr>
    <tr><td colspan="10" style="font-size:12px;">Gerado em: <?= e($agora) ?></td></tr>
    <tr>
      <td colspan="10" style="font-size:12px;">
        Período: <?= e($di) ?> até <?= e($df) ?> &nbsp;|&nbsp;
        Canal: <?= e($canal) ?> &nbsp;|&nbsp;
        Pagamento: <?= e($pag) ?> &nbsp;|&nbsp;
        Busca: <?= e($q) ?>
      </td>
    </tr>
    <tr><td colspan="10"></td></tr>
  </table>

  <table border="1" cellpadding="6" cellspacing="0" style="border-collapse:collapse;width:100%;">
    <thead>
      <tr style="background:#eef2ff;font-weight:900;">
        <th>ID</th><th>Data</th><th>Cliente</th><th>Canal</th><th>Pagamento</th>
        <th>Itens</th>
        <th>Subtotal</th><th>Desconto</th><th>Entrega</th><th>Total</th>
      </tr>
    </thead>
    <tbody>
      <?php
      $sumSub=0; $sumDesc=0; $sumTax=0; $sumTot=0;
      foreach ($rows as $r):
        $id = (int)$r['id'];
        $sumSub += (float)$r['subtotal'];
        $sumDesc += (float)$r['desconto_valor'];
        $sumTax += (float)$r['taxa_entrega'];
        $sumTot += (float)$r['total'];

        $itens = $itemsMap[$id] ?? [];
        $itTxt = itens_preview($itens, 999);
      ?>
      <tr>
        <td><?= $id ?></td>
        <td><?= e((string)$r['data']) ?></td>
        <td><?= e((string)($r['cliente'] ?? '')) ?></td>
        <td><?= e((string)($r['canal'] ?? '')) ?></td>
        <td><?= e((string)($r['pagamento'] ?? '')) ?></td>
        <td><?= e($itTxt) ?></td>
        <td><?= e(number_format((float)$r['subtotal'],2,',','.')) ?></td>
        <td><?= e(number_format((float)$r['desconto_valor'],2,',','.')) ?></td>
        <td><?= e(number_format((float)$r['taxa_entrega'],2,',','.')) ?></td>
        <td><?= e(number_format((float)$r['total'],2,',','.')) ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
    <tfoot>
      <tr style="font-weight:900;background:#f8fafc;">
        <td colspan="6" align="right">Totais</td>
        <td><?= e(number_format($sumSub,2,',','.')) ?></td>
        <td><?= e(number_format($sumDesc,2,',','.')) ?></td>
        <td><?= e(number_format($sumTax,2,',','.')) ?></td>
        <td><?= e(number_format($sumTot,2,',','.')) ?></td>
      </tr>
    </tfoot>
  </table>
  <?php
  exit;
}

/* ====== PRINT ====== */
if ($action === 'print') {
  while (ob_get_level() > 0) { @ob_end_clean(); }

  $pdo = db();
  $params = [];
  $where = build_where($params);

  $sql = "
    SELECT
      v.id, v.data, v.cliente, v.canal, v.pagamento,
      v.subtotal, v.desconto_valor, v.taxa_entrega, v.total
    FROM vendas v
    $where
    ORDER BY v.id DESC
    LIMIT 5000
  ";
  $st = $pdo->prepare($sql);
  $st->execute($params);
  $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

  $ids = [];
  foreach ($rows as $r) $ids[] = (int)$r['id'];
  $itemsMap = fetch_items_for_sale_ids($ids);

  $agora = date('d/m/Y H:i:s');
  $di = get_str('di') ?: '—';
  $df = get_str('df') ?: '—';
  $canal = get_str('canal', 'TODOS');
  $pag = get_str('pag', 'TODOS');
  $q = get_str('q') ?: '—';

  ?>
  <!doctype html>
  <html lang="pt-BR">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Impressão - Vendas</title>
    <style>
      body{font-family:Arial, sans-serif; font-size:12px; color:#111; margin:18px;}
      h1{font-size:16px; margin:0 0 6px;}
      .meta{font-size:11px; color:#333; margin-bottom:12px;}
      .sale{border:1px solid #ddd; border-radius:8px; padding:10px; margin:10px 0; page-break-inside:avoid;}
      .row{display:flex; gap:12px; flex-wrap:wrap;}
      .box{flex:1 1 200px;}
      table{width:100%; border-collapse:collapse; margin-top:8px;}
      th,td{border:1px solid #ddd; padding:6px;}
      th{background:#f3f4f6; text-align:left;}
      .right{text-align:right;}
      .muted{color:#666;}
      @media print { .no-print{display:none;} }
    </style>
  </head>
  <body>
    <button class="no-print" onclick="window.print()">Imprimir</button>
    <h1>PAINEL DA DISTRIBUIDORA - VENDAS</h1>
    <div class="meta">
      Gerado em: <?= e($agora) ?> |
      Período: <?= e($di) ?> até <?= e($df) ?> |
      Canal: <?= e($canal) ?> |
      Pagamento: <?= e($pag) ?> |
      Busca: <?= e($q) ?>
    </div>

    <?php foreach ($rows as $r):
      $id = (int)$r['id'];
      $itens = $itemsMap[$id] ?? [];
      $sumItens = 0.0;
      foreach ($itens as $it) $sumItens += (float)($it['total'] ?? 0);
    ?>
      <div class="sale">
        <div class="row">
          <div class="box"><b>#<?= $id ?></b> <span class="muted">| <?= e((string)$r['data']) ?></span></div>
          <div class="box"><b>Cliente:</b> <?= e((string)($r['cliente'] ?? '')) ?></div>
          <div class="box"><b>Canal:</b> <?= e((string)($r['canal'] ?? '')) ?></div>
          <div class="box"><b>Pagamento:</b> <?= e((string)($r['pagamento'] ?? '')) ?></div>
        </div>

        <table>
          <thead>
            <tr>
              <th style="width:90px;">Código</th>
              <th>Produto</th>
              <th style="width:80px;" class="right">Qtd</th>
              <th style="width:80px;">Un</th>
              <th style="width:110px;" class="right">Preço</th>
              <th style="width:110px;" class="right">Total</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!$itens): ?>
              <tr><td colspan="6" class="muted">Sem itens encontrados para esta venda.</td></tr>
            <?php else: foreach ($itens as $it): ?>
              <tr>
                <td><?= e((string)$it['codigo']) ?></td>
                <td><?= e((string)$it['nome']) ?></td>
                <td class="right"><?= e(number_format((float)$it['qtd'], 3, ',', '.')) ?></td>
                <td><?= e((string)$it['un']) ?></td>
                <td class="right"><?= e(number_format((float)$it['preco'], 2, ',', '.')) ?></td>
                <td class="right"><?= e(number_format((float)$it['total'], 2, ',', '.')) ?></td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
          <tfoot>
            <tr>
              <td colspan="5" class="right"><b>Total Itens</b></td>
              <td class="right"><b><?= e(number_format($sumItens, 2, ',', '.')) ?></b></td>
            </tr>
          </tfoot>
        </table>

        <div class="row" style="margin-top:8px;">
          <div class="box right"><b>Subtotal:</b> <?= e(number_format((float)$r['subtotal'],2,',','.')) ?></div>
          <div class="box right"><b>Desconto:</b> <?= e(number_format((float)$r['desconto_valor'],2,',','.')) ?></div>
          <div class="box right"><b>Entrega:</b> <?= e(number_format((float)$r['taxa_entrega'],2,',','.')) ?></div>
          <div class="box right"><b>Total:</b> <?= e(number_format((float)$r['total'],2,',','.')) ?></div>
        </div>
      </div>
    <?php endforeach; ?>

  </body>
  </html>
  <?php
  exit;
}

/* ============================
   PÁGINA HTML (sem action)
============================ */
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Relatórios - Vendas</title>
  <link rel="stylesheet" href="assets/css/bootstrap.min.css">
  <style>
    body{background:#f6f7fb;}
    .card{border:0; box-shadow:0 8px 20px rgba(0,0,0,.06); border-radius:14px;}
    .table thead th{white-space:nowrap;}
    .badge-soft{background:#eef2ff; color:#1e3a8a;}
    .pointer{cursor:pointer;}
    .mono{font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;}
    .small-muted{font-size:12px; color:#6b7280;}
  </style>
</head>
<body>
<div class="container py-4">
  <div class="d-flex align-items-center justify-content-between mb-3">
    <div>
      <h4 class="mb-0">Relatórios - Vendas</h4>
      <div class="small-muted">Lista com itens (venda_itens) + detalhes em modal</div>
    </div>
    <div class="d-flex gap-2">
      <button class="btn btn-outline-secondary" id="btnPrint">Imprimir</button>
      <button class="btn btn-outline-success" id="btnExcel">Excel</button>
    </div>
  </div>

  <div class="card mb-3">
    <div class="card-body">
      <div class="row g-2 align-items-end">
        <div class="col-6 col-md-2">
          <label class="form-label">Data inicial</label>
          <input type="date" class="form-control" id="di">
        </div>
        <div class="col-6 col-md-2">
          <label class="form-label">Data final</label>
          <input type="date" class="form-control" id="df">
        </div>
        <div class="col-6 col-md-2">
          <label class="form-label">Canal</label>
          <select class="form-select" id="canal">
            <option value="TODOS">TODOS</option>
            <option value="PRESENCIAL">PRESENCIAL</option>
            <option value="DELIVERY">DELIVERY</option>
          </select>
        </div>
        <div class="col-6 col-md-2">
          <label class="form-label">Pagamento</label>
          <select class="form-select" id="pag">
            <option value="TODOS">TODOS</option>
            <option value="DINHEIRO">DINHEIRO</option>
            <option value="PIX">PIX</option>
            <option value="CARTAO">CARTAO</option>
            <option value="BOLETO">BOLETO</option>
            <option value="MULTI">MULTI</option>
          </select>
        </div>
        <div class="col-12 col-md-3">
          <label class="form-label">Busca (ID / cliente / obs)</label>
          <input class="form-control" id="q" list="dlClientes" placeholder="Digite para buscar...">
          <datalist id="dlClientes"></datalist>
        </div>
        <div class="col-12 col-md-1 d-grid">
          <button class="btn btn-primary" id="btnFiltrar">Filtrar</button>
        </div>
      </div>
      <div class="d-flex gap-2 mt-2">
        <select class="form-select" id="per" style="max-width:120px;">
          <option value="10">10</option>
          <option value="25" selected>25</option>
          <option value="50">50</option>
          <option value="100">100</option>
        </select>
        <button class="btn btn-outline-secondary" id="btnLimpar">Limpar</button>
        <div class="ms-auto small-muted" id="metaInfo"></div>
      </div>
    </div>
  </div>

  <div class="row g-3 mb-3">
    <div class="col-6 col-lg-3">
      <div class="card"><div class="card-body">
        <div class="small-muted">Qtd</div>
        <div class="fs-4 fw-bold" id="tQtd">0</div>
      </div></div>
    </div>
    <div class="col-6 col-lg-3">
      <div class="card"><div class="card-body">
        <div class="small-muted">Subtotal</div>
        <div class="fs-4 fw-bold" id="tSub">R$ 0,00</div>
      </div></div>
    </div>
    <div class="col-6 col-lg-3">
      <div class="card"><div class="card-body">
        <div class="small-muted">Desconto</div>
        <div class="fs-4 fw-bold" id="tDesc">R$ 0,00</div>
      </div></div>
    </div>
    <div class="col-6 col-lg-3">
      <div class="card"><div class="card-body">
        <div class="small-muted">Total</div>
        <div class="fs-4 fw-bold" id="tTot">R$ 0,00</div>
      </div></div>
    </div>
  </div>

  <div class="card">
    <div class="card-body">
      <div class="table-responsive">
        <table class="table table-hover align-middle" id="tbl">
          <thead>
            <tr>
              <th>#</th>
              <th>Data</th>
              <th>Cliente</th>
              <th>Canal</th>
              <th>Pagamento</th>
              <th>Itens</th>
              <th class="text-end">Total</th>
              <th class="text-end">Devolução</th>
              <th></th>
            </tr>
          </thead>
          <tbody id="tbody">
            <tr><td colspan="9" class="text-center small-muted py-4">Carregando...</td></tr>
          </tbody>
        </table>
      </div>

      <div class="d-flex align-items-center justify-content-between">
        <div class="small-muted" id="pageInfo"></div>
        <div class="btn-group">
          <button class="btn btn-outline-secondary" id="prev">Anterior</button>
          <button class="btn btn-outline-secondary" id="next">Próximo</button>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Modal Detalhes -->
<div class="modal fade" id="mdDetalhe" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content" style="border-radius:16px;">
      <div class="modal-header">
        <div>
          <div class="fw-bold" id="mdTitle">Detalhes</div>
          <div class="small-muted" id="mdSub">—</div>
        </div>
        <button class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
      </div>
      <div class="modal-body">
        <div class="row g-3">
          <div class="col-lg-6">
            <div class="card">
              <div class="card-body">
                <div class="fw-bold mb-2">Dados da venda</div>
                <div class="row g-2 small">
                  <div class="col-6"><span class="small-muted">ID</span><div class="mono" id="vId">—</div></div>
                  <div class="col-6"><span class="small-muted">Data</span><div id="vData">—</div></div>
                  <div class="col-6"><span class="small-muted">Cliente</span><div id="vCliente">—</div></div>
                  <div class="col-6"><span class="small-muted">Canal</span><div id="vCanal">—</div></div>
                  <div class="col-6"><span class="small-muted">Pagamento</span><div id="vPag">—</div></div>
                  <div class="col-6"><span class="small-muted">Criado em</span><div id="vCreated">—</div></div>
                  <div class="col-12"><span class="small-muted">Endereço</span><div id="vEnd">—</div></div>
                  <div class="col-12"><span class="small-muted">Obs</span><div id="vObs">—</div></div>
                </div>
                <hr>
                <div class="row g-2">
                  <div class="col-3"><div class="small-muted">Subtotal</div><div class="fw-bold" id="vSubt">R$ 0,00</div></div>
                  <div class="col-3"><div class="small-muted">Desconto</div><div class="fw-bold" id="vDesc">R$ 0,00</div></div>
                  <div class="col-3"><div class="small-muted">Entrega</div><div class="fw-bold" id="vTax">R$ 0,00</div></div>
                  <div class="col-3"><div class="small-muted">Total</div><div class="fw-bold" id="vTotal">R$ 0,00</div></div>
                </div>
              </div>
            </div>

            <div class="card mt-3">
              <div class="card-body">
                <div class="fw-bold mb-2">Pagamento (JSON)</div>
                <pre class="mb-0 small" id="vPayJson" style="background:#0b1220;color:#d1e7ff;border-radius:12px;padding:12px;white-space:pre-wrap;"></pre>
              </div>
            </div>
          </div>

          <div class="col-lg-6">
            <div class="card">
              <div class="card-body">
                <div class="d-flex align-items-center justify-content-between mb-2">
                  <div class="fw-bold">Itens</div>
                  <div class="small-muted">Qtd: <span id="itQtd">0</span> | Total: <span id="itTot">R$ 0,00</span></div>
                </div>
                <div class="table-responsive">
                  <table class="table table-sm align-middle">
                    <thead>
                      <tr>
                        <th>Cód</th><th>Produto</th><th class="text-end">Qtd</th><th>Un</th><th class="text-end">Preço</th><th class="text-end">Total</th>
                      </tr>
                    </thead>
                    <tbody id="itBody">
                      <tr><td colspan="6" class="text-center small-muted py-3">—</td></tr>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>

            <div class="card mt-3">
              <div class="card-body">
                <div class="fw-bold mb-2">Devoluções (se houver)</div>
                <div id="devBox" class="small-muted">—</div>
              </div>
            </div>

          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Fechar</button>
      </div>
    </div>
  </div>
</div>

<script src="assets/js/bootstrap.bundle.min.js"></script>
<script>
  const fmtBRL = (v) => new Intl.NumberFormat('pt-BR', {style:'currency', currency:'BRL'}).format(Number(v||0));
  const qs = (id) => document.getElementById(id);

  const state = { page: 1, per: 25 };

  function buildQuery(extra={}) {
    const p = new URLSearchParams();
    p.set('action','fetch');
    p.set('page', state.page);
    p.set('per', state.per);
    const di = qs('di').value.trim(); if (di) p.set('di', di);
    const df = qs('df').value.trim(); if (df) p.set('df', df);
    const canal = qs('canal').value; if (canal) p.set('canal', canal);
    const pag = qs('pag').value; if (pag) p.set('pag', pag);
    const q = qs('q').value.trim(); if (q) p.set('q', q);
    for (const k in extra) p.set(k, extra[k]);
    return p.toString();
  }

  async function load() {
    qs('tbody').innerHTML = `<tr><td colspan="9" class="text-center small-muted py-4">Carregando...</td></tr>`;
    const url = `?${buildQuery()}`;

    try {
      const res = await fetch(url, {headers:{'Accept':'application/json'}});
      const data = await res.json();
      if (!data.ok) throw new Error(data.msg || 'Falha no fetch');

      // Totais
      qs('tQtd').textContent  = data.totais.qtd || 0;
      qs('tSub').textContent  = fmtBRL(data.totais.subtotal);
      qs('tDesc').textContent = fmtBRL(data.totais.desconto);
      qs('tTot').textContent  = fmtBRL(data.totais.total);

      // Meta
      qs('metaInfo').textContent = `Total: ${data.meta.total} | Página ${data.meta.page}/${data.meta.pages}`;
      qs('pageInfo').textContent = `Mostrando ${data.rows.length} de ${data.meta.total}`;

      // Rows
      if (!data.rows.length) {
        qs('tbody').innerHTML = `<tr><td colspan="9" class="text-center small-muted py-4">Nenhum registro encontrado.</td></tr>`;
        return;
      }

      qs('tbody').innerHTML = data.rows.map(r => {
        const dev = r.devolucao || {qtd:0, valor:0, status:''};
        const devTxt = (dev.qtd > 0) ? `${dev.qtd} (${fmtBRL(dev.valor)})` : '—';
        const devBadge = (dev.qtd > 0)
          ? `<span class="badge bg-warning text-dark">${devTxt}</span>`
          : `<span class="text-muted">${devTxt}</span>`;

        const itensTxt = r.itens_preview ? r.itens_preview : (r.itens_count ? `${r.itens_count} item(s)` : '—');
        const itensBadge = `<span class="badge badge-soft">${r.itens_count} item(s)</span> <span class="text-muted">${itensTxt}</span>`;

        return `
          <tr>
            <td class="mono">${r.id}</td>
            <td>${r.data || ''}</td>
            <td>${(r.cliente||'').toString().replaceAll('<','&lt;')}</td>
            <td><span class="badge bg-light text-dark">${r.canal||''}</span></td>
            <td><span class="badge bg-info text-dark">${r.pagamento||''}</span></td>
            <td>${itensBadge}</td>
            <td class="text-end fw-bold">${fmtBRL(r.total)}</td>
            <td class="text-end">${devBadge}</td>
            <td class="text-end">
              <button class="btn btn-sm btn-outline-primary" onclick="openDetalhe(${r.id})">Detalhes</button>
            </td>
          </tr>
        `;
      }).join('');

      // Navegação
      qs('prev').disabled = (data.meta.page <= 1);
      qs('next').disabled = (data.meta.page >= data.meta.pages);

    } catch (err) {
      qs('tbody').innerHTML = `<tr><td colspan="9" class="text-center text-danger py-4">${err.message}</td></tr>`;
    }
  }

  async function openDetalhe(id) {
    const md = new bootstrap.Modal(qs('mdDetalhe'));
    qs('mdTitle').textContent = `Detalhes da venda #${id}`;
    qs('mdSub').textContent = 'Carregando...';
    qs('itBody').innerHTML = `<tr><td colspan="6" class="text-center small-muted py-3">Carregando...</td></tr>`;
    qs('devBox').textContent = 'Carregando...';
    md.show();

    try {
      const res = await fetch(`?action=one&id=${id}`, {headers:{'Accept':'application/json'}});
      const data = await res.json();
      if (!data.ok) throw new Error(data.msg || 'Falha ao abrir detalhes');

      const v = data.data.venda || {};
      const itens = data.data.itens || [];
      const payObj = data.data.pagamento_obj || {};
      const devols = data.data.devolucoes || [];

      qs('mdSub').textContent = `${v.data||''} | ${v.canal||''} | ${v.pagamento||''}`;

      qs('vId').textContent = v.id || id;
      qs('vData').textContent = v.data || '—';
      qs('vCliente').textContent = v.cliente || '—';
      qs('vCanal').textContent = v.canal || '—';
      qs('vPag').textContent = v.pagamento || '—';
      qs('vCreated').textContent = v.created_at || '—';
      qs('vEnd').textContent = v.endereco || '—';
      qs('vObs').textContent = v.obs || '—';

      qs('vSubt').textContent  = fmtBRL(v.subtotal);
      qs('vDesc').textContent  = fmtBRL(v.desconto_valor);
      qs('vTax').textContent   = fmtBRL(v.taxa_entrega);
      qs('vTotal').textContent = fmtBRL(v.total);

      qs('vPayJson').textContent = JSON.stringify(payObj, null, 2) || '{}';

      // itens
      qs('itQtd').textContent = (data.data.itens_qtd || 0);
      qs('itTot').textContent = fmtBRL(data.data.itens_total || 0);

      if (!itens.length) {
        qs('itBody').innerHTML = `<tr><td colspan="6" class="text-center small-muted py-3">Sem itens encontrados.</td></tr>`;
      } else {
        qs('itBody').innerHTML = itens.map(it => `
          <tr>
            <td class="mono">${(it.codigo||'')}</td>
            <td>${(it.nome||'').toString().replaceAll('<','&lt;')}</td>
            <td class="text-end">${Number(it.qtd||0).toLocaleString('pt-BR')}</td>
            <td>${(it.un||'')}</td>
            <td class="text-end">${fmtBRL(it.preco||0)}</td>
            <td class="text-end">${fmtBRL(it.total||0)}</td>
          </tr>
        `).join('');
      }

      // devoluções
      if (!devols.length) {
        qs('devBox').innerHTML = `<span class="text-muted">Nenhuma devolução registrada para esta venda.</span>`;
      } else {
        qs('devBox').innerHTML = `
          <div class="mb-2"><b>Total devolvido:</b> ${fmtBRL(data.data.devolucoes_total || 0)}</div>
          <div class="table-responsive">
            <table class="table table-sm">
              <thead><tr><th>#</th><th>Data</th><th>Hora</th><th>Tipo</th><th>Motivo</th><th>Status</th><th class="text-end">Valor</th></tr></thead>
              <tbody>
                ${devols.map(d => `
                  <tr>
                    <td class="mono">${d.id}</td>
                    <td>${d.data||''}</td>
                    <td>${d.hora||''}</td>
                    <td>${d.tipo||''}</td>
                    <td>${d.motivo||''}</td>
                    <td>${d.status||''}</td>
                    <td class="text-end">${fmtBRL(d.valor||0)}</td>
                  </tr>
                `).join('')}
              </tbody>
            </table>
          </div>
        `;
      }

    } catch (err) {
      qs('mdSub').textContent = 'Erro';
      qs('itBody').innerHTML = `<tr><td colspan="6" class="text-center text-danger py-3">${err.message}</td></tr>`;
      qs('devBox').innerHTML = `<span class="text-danger">${err.message}</span>`;
    }
  }

  // Autocomplete cliente
  let suggestTimer = null;
  qs('q').addEventListener('input', () => {
    clearTimeout(suggestTimer);
    const v = qs('q').value.trim();
    suggestTimer = setTimeout(async () => {
      if (v.length < 2) { qs('dlClientes').innerHTML=''; return; }
      try {
        const res = await fetch(`?action=suggest&q=${encodeURIComponent(v)}`, {headers:{'Accept':'application/json'}});
        const data = await res.json();
        if (!data.ok) return;
        qs('dlClientes').innerHTML = (data.items||[]).map(x => `<option value="${String(x).replaceAll('"','&quot;')}"></option>`).join('');
      } catch(e){}
    }, 250);
  });

  // Botões
  qs('btnFiltrar').addEventListener('click', () => { state.page = 1; load(); });
  qs('btnLimpar').addEventListener('click', () => {
    qs('di').value=''; qs('df').value='';
    qs('canal').value='TODOS'; qs('pag').value='TODOS';
    qs('q').value='';
    state.page = 1; load();
  });
  qs('per').addEventListener('change', () => { state.per = Number(qs('per').value||25); state.page=1; load(); });
  qs('prev').addEventListener('click', () => { if (state.page>1){ state.page--; load(); } });
  qs('next').addEventListener('click', () => { state.page++; load(); });

  qs('btnExcel').addEventListener('click', () => {
    const p = new URLSearchParams(buildQuery().replace('action=fetch','action=excel'));
    window.location.href = `?${p.toString()}`;
  });
  qs('btnPrint').addEventListener('click', () => {
    const p = new URLSearchParams(buildQuery().replace('action=fetch','action=print'));
    window.open(`?${p.toString()}`, '_blank');
  });

  // init
  load();
</script>
</body>
</html>
<?php
// flush do buffer
if (ob_get_level() > 0) @ob_end_flush();