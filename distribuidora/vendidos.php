<?php
declare(strict_types=1);


@date_default_timezone_set('America/Manaus');
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

/* ========= INCLUDES (ajuste se precisar) ========= */
$helpers = __DIR__ . '/assets/dados/_helpers.php';
if (is_file($helpers)) require_once $helpers;

$con = __DIR__ . '/assets/conexao.php';
if (is_file($con)) require_once $con;

/* ========= FALLBACKS (caso seu helpers não tenha) ========= */
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
if (!function_exists('db')) {
  http_response_code(500);
  echo "ERRO: função db():PDO não encontrada. Verifique assets/conexao.php";
  exit;
}


/* ========= UTIL ========= */
function json_out(array $payload, int $code = 200): void {
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

function build_where(array &$params): string {
  $where = " WHERE 1=1 ";

  $di = get_str('di');
  $df = get_str('df');
  $canal = strtoupper(get_str('canal', 'TODOS'));
  $pag = strtoupper(get_str('pag', 'TODOS'));
  $q = get_str('q');

  if ($di !== '') { $where .= " AND v.data >= :di "; $params['di'] = $di; }
  if ($df !== '') { $where .= " AND v.data <= :df "; $params['df'] = $df; }
  if ($canal !== '' && $canal !== 'TODOS') { $where .= " AND v.canal = :canal "; $params['canal'] = $canal; }
  if ($pag !== '' && $pag !== 'TODOS') { $where .= " AND v.pagamento = :pag "; $params['pag'] = $pag; }

  if ($q !== '') {
    // Se for número puro: tenta por ID
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

/**
 * Itens da venda (opcional):
 * - Seu banco NÃO tem venda_itens, então tentamos buscar na tabela saidas
 * - Mapeamento: saidas.pedido pode ser "id", "00005" ou "V{id}" (robusto)
 */
function fetch_items_for_sale_ids(array $saleIds): array {
  if (!$saleIds) return [];

  $pdo = db();

  // checa se tabela saidas existe
  $st = $pdo->query("SHOW TABLES LIKE 'saidas'");
  if (!$st || !$st->fetchColumn()) return [];

  // cria chaves possíveis para procurar em saidas.pedido
  $keyToId = [];
  $keys = [];
  foreach ($saleIds as $id) {
    $id = (int)$id;
    $k1 = (string)$id;
    $k2 = str_pad((string)$id, 5, '0', STR_PAD_LEFT);
    $k3 = 'V' . $id;
    foreach ([$k1,$k2,$k3] as $k) {
      $keyToId[$k] = $id;
      $keys[$k] = true;
    }
  }
  $keys = array_keys($keys);

  // placeholders
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

  $out = []; // saleId => itens[]
  foreach ($rows as $r) {
    $pedido = (string)($r['pedido'] ?? '');
    if (!isset($keyToId[$pedido])) continue;
    $sid = $keyToId[$pedido];

    $out[$sid] ??= [];
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

function fetch_one_sale(int $id): ?array {
  $pdo = db();
  $stmt = $pdo->prepare("SELECT * FROM vendas WHERE id = :id LIMIT 1");
  $stmt->execute(['id' => $id]);
  $v = $stmt->fetch(PDO::FETCH_ASSOC);
  if (!$v) return null;

  $itemsMap = fetch_items_for_sale_ids([$id]);
  $itens = $itemsMap[$id] ?? [];

  // tenta totalizar itens (se existir saidas)
  $itSubtotal = 0.0;
  $itQtd = 0.0;
  foreach ($itens as $it) {
    $itSubtotal += (float)$it['total'];
    $itQtd += (float)$it['qtd'];
  }

  return [
    'venda' => $v,
    'itens' => $itens,
    'itens_total' => $itSubtotal,
    'itens_qtd' => $itQtd
  ];
}

/* ========= AÇÕES ========= */
$action = strtolower(get_str('action'));

if ($action === 'suggest') {
  $q = get_str('q');
  if (mb_strlen($q) < 2) json_out(['ok' => true, 'items' => []]);

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
  while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $items[] = (string)$r['cliente'];
  }
  json_out(['ok' => true, 'items' => $items]);
}

if ($action === 'fetch') {
  $pdo = db();

  $page = max(1, get_int('page', 1));
  $per = get_int('per', 25);
  $per = in_array($per, [10,25,50,100], true) ? $per : 25;
  $off = ($page - 1) * $per;

  $params = [];
  $where = build_where($params);

  // totais do filtro
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

  // lista paginada
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

  $ids = array_map(fn($r) => (int)$r['id'], $rows);
  $itemsMap = fetch_items_for_sale_ids($ids);

  // anexa itens resumidos
  $outRows = [];
  foreach ($rows as $r) {
    $id = (int)$r['id'];
    $itens = $itemsMap[$id] ?? [];
    $itTotal = 0.0;
    $itCount = 0;
    foreach ($itens as $it) { $itTotal += (float)$it['total']; $itCount++; }

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
      'itens' => $itens,
      'itens_count' => $itCount,
      'itens_total' => $itTotal
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
}

if ($action === 'one') {
  $id = get_int('id', 0);
  if ($id <= 0) json_out(['ok' => false, 'msg' => 'ID inválido'], 400);

  $one = fetch_one_sale($id);
  if (!$one) json_out(['ok' => false, 'msg' => 'Venda não encontrada'], 404);

  json_out(['ok' => true, 'data' => $one]);
}

if ($action === 'excel') {
  $pdo = db();
  $params = [];
  $where = build_where($params);

  // pega tudo (limite de segurança)
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

  echo "\xEF\xBB\xBF"; // BOM pra Excel abrir UTF-8

  // Estilo simples (igual seus relatórios do print)
  ?>
  <table border="0" cellpadding="4" cellspacing="0" style="width:100%;">
    <tr>
      <td colspan="9" align="center" style="font-size:16px;font-weight:900;">
        PAINEL DA DISTRIBUIDORA - VENDIDOS
      </td>
    </tr>
    <tr>
      <td colspan="9" style="font-size:12px;">Gerado em: <?= e($agora) ?></td>
    </tr>
    <tr>
      <td colspan="9" style="font-size:12px;">
        Período: <?= e($di) ?> até <?= e($df) ?> &nbsp;|&nbsp;
        Canal: <?= e($canal) ?> &nbsp;|&nbsp;
        Pagamento: <?= e($pag) ?> &nbsp;|&nbsp;
        Busca: <?= e($q) ?>
      </td>
    </tr>
    <tr><td colspan="9"></td></tr>
  </table>

  <table border="1" cellpadding="6" cellspacing="0" style="border-collapse:collapse;width:100%;">
    <thead>
      <tr style="background:#eef2ff;font-weight:900;">
        <th>ID</th>
        <th>Data</th>
        <th>Cliente</th>
        <th>Canal</th>
        <th>Pagamento</th>
        <th>Subtotal</th>
        <th>Desconto</th>
        <th>Entrega</th>
        <th>Total</th>
      </tr>
    </thead>
    <tbody>
      <?php
      $sumSub=0; $sumDesc=0; $sumTax=0; $sumTot=0;
      foreach ($rows as $r):
        $sumSub += (float)$r['subtotal'];
        $sumDesc += (float)$r['desconto_valor'];
        $sumTax += (float)$r['taxa_entrega'];
        $sumTot += (float)$r['total'];
      ?>
      <tr>
        <td><?= (int)$r['id'] ?></td>
        <td><?= e((string)$r['data']) ?></td>
        <td><?= e((string)($r['cliente'] ?? '')) ?></td>
        <td><?= e((string)($r['canal'] ?? '')) ?></td>
        <td><?= e((string)($r['pagamento'] ?? '')) ?></td>
        <td><?= e(number_format((float)$r['subtotal'],2,',','.')) ?></td>
        <td><?= e(number_format((float)$r['desconto_valor'],2,',','.')) ?></td>
        <td><?= e(number_format((float)$r['taxa_entrega'],2,',','.')) ?></td>
        <td><?= e(number_format((float)$r['total'],2,',','.')) ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
    <tfoot>
      <tr style="font-weight:900;background:#f8fafc;">
        <td colspan="5" align="right">Totais</td>
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

if ($action === 'print') {
  // Relatório A4 para imprimir/salvar como PDF
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

  $agora = date('d/m/Y H:i:s');
  $di = get_str('di') ?: '—';
  $df = get_str('df') ?: '—';
  $canal = get_str('canal', 'TODOS');
  $pag = get_str('pag', 'TODOS');
  $q = get_str('q') ?: '—';

  $sumSub=0; $sumDesc=0; $sumTax=0; $sumTot=0;
  foreach ($rows as $r) {
    $sumSub += (float)$r['subtotal'];
    $sumDesc += (float)$r['desconto_valor'];
    $sumTax += (float)$r['taxa_entrega'];
    $sumTot += (float)$r['total'];
  }

  ?>
  <!doctype html>
  <html lang="pt-BR">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>PAINEL DA DISTRIBUIDORA - VENDIDOS</title>
    <style>
      @page { size: A4; margin: 16mm; }
      body { font-family: Arial, Helvetica, sans-serif; color:#0f172a; }
      h1 { font-size: 18px; margin:0 0 8px; }
      .meta { font-size: 12px; margin: 2px 0; }
      table { width: 100%; border-collapse: collapse; margin-top: 12px; }
      th, td { border: 1px solid #e5e7eb; padding: 8px; font-size: 12px; }
      th { background: #f1f5f9; text-align: left; }
      tfoot td { font-weight: 900; background:#f8fafc; }
      .right { text-align:right; }
      .muted { color:#475569; }
      .topbar { display:flex; justify-content:space-between; gap:12px; align-items:flex-start; }
      .btn { display:none; }
      @media screen {
        body { background:#0b1220; padding:24px; }
        .sheet { max-width: 920px; margin:0 auto; background:#fff; border-radius: 14px; padding: 18px; box-shadow: 0 20px 60px rgba(0,0,0,.25); }
        .btn { display:inline-block; margin-top:10px; padding:10px 14px; border-radius: 10px; border:1px solid #cbd5e1; cursor:pointer; font-weight:900; background:#fff; }
      }
    </style>
  </head>
  <body>
    <div class="sheet">
      <div class="topbar">
        <div>
          <h1>PAINEL DA DISTRIBUIDORA - VENDIDOS</h1>
          <div class="meta">Gerado em: <span class="muted"><?= e($agora) ?></span></div>
          <div class="meta">Período: <span class="muted"><?= e($di) ?></span> até <span class="muted"><?= e($df) ?></span></div>
          <div class="meta">Canal: <span class="muted"><?= e($canal) ?></span> &nbsp;|&nbsp; Pagamento: <span class="muted"><?= e($pag) ?></span> &nbsp;|&nbsp; Busca: <span class="muted"><?= e($q) ?></span></div>
        </div>
        <div>
          <button class="btn" onclick="window.print()">Imprimir / Salvar PDF</button>
        </div>
      </div>

      <table>
        <thead>
          <tr>
            <th style="width:70px;">ID</th>
            <th style="width:90px;">Data</th>
            <th>Cliente</th>
            <th style="width:110px;">Canal</th>
            <th style="width:130px;">Pagamento</th>
            <th class="right" style="width:110px;">Subtotal</th>
            <th class="right" style="width:110px;">Desconto</th>
            <th class="right" style="width:110px;">Entrega</th>
            <th class="right" style="width:110px;">Total</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $r): ?>
          <tr>
            <td><?= (int)$r['id'] ?></td>
            <td><?= e((string)$r['data']) ?></td>
            <td><?= e((string)($r['cliente'] ?? '')) ?></td>
            <td><?= e((string)($r['canal'] ?? '')) ?></td>
            <td><?= e((string)($r['pagamento'] ?? '')) ?></td>
            <td class="right"><?= e(brl((float)$r['subtotal'])) ?></td>
            <td class="right"><?= e(brl((float)$r['desconto_valor'])) ?></td>
            <td class="right"><?= e(brl((float)$r['taxa_entrega'])) ?></td>
            <td class="right"><?= e(brl((float)$r['total'])) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr>
            <td colspan="5" class="right">Totais</td>
            <td class="right"><?= e(brl($sumSub)) ?></td>
            <td class="right"><?= e(brl($sumDesc)) ?></td>
            <td class="right"><?= e(brl($sumTax)) ?></td>
            <td class="right"><?= e(brl($sumTot)) ?></td>
          </tr>
        </tfoot>
      </table>
    </div>

    <script>
      // auto print (pra ficar "Exportar PDF" 1-clique)
      window.addEventListener('load', () => {
        setTimeout(() => window.print(), 300);
      });
    </script>
  </body>
  </html>
  <?php
  exit;
}

if ($action === 'cupom') {
  $id = get_int('id', 0);
  if ($id <= 0) { http_response_code(400); echo "ID inválido"; exit; }

  $one = fetch_one_sale($id);
  if (!$one) { http_response_code(404); echo "Venda não encontrada"; exit; }

  $v = $one['venda'];
  $itens = $one['itens'];

  $dt = (string)($v['data'] ?? '');
  $hr = '';
  if (!empty($v['created_at'])) {
    $ts = strtotime((string)$v['created_at']);
    if ($ts) $hr = date('H:i:s', $ts);
  }

  $cliente = (string)($v['cliente'] ?? '');
  $canal = (string)($v['canal'] ?? '');
  $pag = (string)($v['pagamento'] ?? '');
  $sub = (float)($v['subtotal'] ?? 0);
  $desc = (float)($v['desconto_valor'] ?? 0);
  $taxa = (float)($v['taxa_entrega'] ?? 0);
  $tot = (float)($v['total'] ?? 0);

  ?>
  <!doctype html>
  <html lang="pt-BR">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>Cupom Fiscal - Venda #<?= (int)$v['id'] ?></title>
    <style>
      @page { size: 80mm auto; margin: 6mm; }
      body { font-family: Arial, Helvetica, sans-serif; color:#0f172a; }
      .cupom { width: 80mm; max-width: 100%; }
      .center { text-align:center; }
      .title { font-weight:900; font-size:14px; margin:0; }
      .meta { font-size:11px; margin:2px 0; }
      .hr { border-top: 1px dashed #94a3b8; margin: 8px 0; }
      table { width:100%; border-collapse: collapse; }
      th, td { font-size:11px; padding: 3px 0; vertical-align: top; }
      th { text-align:left; }
      .r { text-align:right; white-space:nowrap; }
      .muted { color:#64748b; }
      .btn { display:none; }
      @media screen {
        body { background:#0b1220; padding: 24px; }
        .wrap { max-width: 360px; margin:0 auto; background:#fff; border-radius:14px; padding: 14px; box-shadow:0 20px 60px rgba(0,0,0,.25); }
        .btn { display:inline-block; margin-top:10px; width:100%; padding:10px 12px; border-radius:12px; border:1px solid #cbd5e1; cursor:pointer; font-weight:900; background:#fff; }
      }
    </style>
  </head>
  <body>
    <div class="wrap">
      <div class="cupom">
        <div class="center">
          <p class="title">PAINEL DA DISTRIBUIDORA</p>
          <div class="meta muted">CUPOM FISCAL (Simples)</div>
        </div>

        <div class="hr"></div>

        <div class="meta"><b>Venda:</b> #<?= (int)$v['id'] ?></div>
        <div class="meta"><b>Data:</b> <?= e($dt) ?> <?= $hr ? ' <span class="muted">'.$hr.'</span>' : '' ?></div>
        <div class="meta"><b>Cliente:</b> <?= e($cliente ?: '—') ?></div>
        <div class="meta"><b>Canal:</b> <?= e($canal ?: '—') ?></div>
        <div class="meta"><b>Pagamento:</b> <?= e($pag ?: '—') ?></div>

        <div class="hr"></div>

        <table>
          <thead>
            <tr>
              <th>Item</th>
              <th class="r">Qtd</th>
              <th class="r">Total</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!$itens): ?>
              <tr><td colspan="3" class="muted">Sem itens vinculados (tabela saidas não encontrou pedido).</td></tr>
            <?php else: foreach ($itens as $it): ?>
              <tr>
                <td>
                  <b><?= e($it['nome']) ?></b>
                  <?php if (!empty($it['codigo'])): ?>
                    <div class="muted"><?= e($it['codigo']) ?></div>
                  <?php endif; ?>
                  <div class="muted"><?= e($it['un']) ?> • <?= e(brl((float)$it['preco'])) ?></div>
                </td>
                <td class="r"><?= e(rtrim(rtrim(number_format((float)$it['qtd'],3,',','.'),'0'),',')) ?></td>
                <td class="r"><?= e(brl((float)$it['total'])) ?></td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>

        <div class="hr"></div>

        <table>
          <tr><td>Subtotal</td><td class="r"><?= e(brl($sub)) ?></td></tr>
          <tr><td>Desconto</td><td class="r"><?= e(brl($desc)) ?></td></tr>
          <tr><td>Entrega</td><td class="r"><?= e(brl($taxa)) ?></td></tr>
          <tr><td><b>TOTAL</b></td><td class="r"><b><?= e(brl($tot)) ?></b></td></tr>
        </table>

        <div class="hr"></div>

        <div class="center meta muted">Obrigado pela preferência!</div>

        <button class="btn" onclick="window.print()">Imprimir</button>
      </div>
    </div>

    <script>
      window.addEventListener('load', () => setTimeout(() => window.print(), 250));
    </script>
  </body>
  </html>
  <?php
  exit;
}

/* ========= HTML (tela principal) ========= */
$csrf = csrf_token();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8" />
  <meta http-equiv="X-UA-Compatible" content="IE=edge" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="csrf-token" content="<?= e($csrf) ?>">

  <link rel="shortcut icon" href="assets/images/favicon.svg" type="image/x-icon" />
  <title>Painel da Distribuidora | Vendidos</title>

  <link rel="stylesheet" href="assets/css/bootstrap.min.css" />
  <link rel="stylesheet" href="assets/css/lineicons.css" rel="stylesheet" type="text/css" />
  <link rel="stylesheet" href="assets/css/materialdesignicons.min.css" rel="stylesheet" type="text/css" />
  <link rel="stylesheet" href="assets/css/main.css" />

  <style>
    .profile-box .dropdown-menu { width: max-content; min-width: 260px; max-width: calc(100vw - 24px) }
    .profile-box .dropdown-menu .author-info { width: max-content; max-width: 100%; display: flex !important; align-items: center; gap: 10px }
    .profile-box .dropdown-menu .author-info .content { min-width: 0; max-width: 100% }

    .main-btn.btn-compact { height: 38px !important; padding: 8px 14px !important; font-size: 13px !important; line-height: 1 !important }
    .icon-btn { height: 34px !important; width: 42px !important; padding: 0 !important; display: inline-flex !important; align-items: center !important; justify-content: center !important }
    .form-control.compact, .form-select.compact { height: 38px; padding: 8px 12px; font-size: 13px }

    .cardx { border: 1px solid rgba(148, 163, 184, .28); border-radius: 16px; background: #fff; overflow: hidden }
    .cardx .head { padding: 12px 14px; border-bottom: 1px solid rgba(148, 163, 184, .22); display: flex; align-items: center; justify-content: space-between; gap: 10px; flex-wrap: wrap }
    .cardx .body { padding: 14px }

    .muted { font-size: 12px; color: #64748b }
    .pill { padding: 6px 10px; border-radius: 999px; border: 1px solid rgba(148, 163, 184, .25); font-weight: 900; font-size: 12px; display: inline-flex; align-items: center; gap: 8px; background: rgba(248, 250, 252, .7) }
    .pill.ok { border-color: rgba(34, 197, 94, .25); background: rgba(240, 253, 244, .9); color: #166534 }

    .table td, .table th { vertical-align: middle }
    .table-responsive { -webkit-overflow-scrolling: touch }
    #tbDev { width: 100%; min-width: 1080px }
    #tbDev th, #tbDev td { white-space: nowrap !important }

    .mini { font-size: 12px; color: #475569; font-weight: 800 }
    .money { font-weight: 1000; color: #0b5ed7 }

    .box-tot { border: 1px solid rgba(148, 163, 184, .25); border-radius: 14px; background: #fff; padding: 12px }
    .tot-row { display: flex; justify-content: space-between; align-items: center; gap: 10px; font-size: 13px; color: #334155; margin-bottom: 8px; font-weight: 900 }
    .tot-hr { height: 1px; background: rgba(148, 163, 184, .22); margin: 10px 0 }
    .grand { display: flex; justify-content: space-between; align-items: baseline; gap: 10px; margin-top: 4px }
    .grand .lbl { font-weight: 1000; color: #0f172a; font-size: 16px }
    .grand .val { font-weight: 1000; color: #0b5ed7; font-size: 26px; letter-spacing: .2px }

    .badge-soft { font-weight: 1000; border-radius: 999px; padding: 6px 10px; font-size: 11px }
    .b-open { background: rgba(255, 251, 235, .95); color: #92400e; border: 1px solid rgba(245, 158, 11, .25) }
    .b-done { background: rgba(240, 253, 244, .95); color: #166534; border: 1px solid rgba(34, 197, 94, .25) }
    .b-cancel { background: rgba(254, 242, 242, .95); color: #991b1b; border: 1px solid rgba(239, 68, 68, .25) }

    .toolbar { display: flex; gap: 10px; flex-wrap: wrap; align-items: center }
    .toolbar .grow { flex: 1 1 260px; min-width: 240px }
    .toolbar .w180 { min-width: 180px }

    .search-wrap { position: relative }
    .suggest { position: absolute; z-index: 9999; left: 0; right: 0; top: calc(100% + 6px); background: #fff; border: 1px solid rgba(148, 163, 184, .25); border-radius: 14px; box-shadow: 0 10px 30px rgba(15, 23, 42, .10); max-height: 280px; overflow: auto; display: none }
    .suggest .it { padding: 10px 12px; cursor: pointer; display: flex; justify-content: space-between; gap: 10px }
    .suggest .it:hover { background: rgba(241, 245, 249, .9) }
    .suggest .t { font-weight: 900; font-size: 12px; color: #0f172a; white-space: nowrap; overflow: hidden; text-overflow: ellipsis }
    .suggest .s { font-size: 12px; color: #64748b; white-space: nowrap }

    /* Itens da venda */
    .sale-box { border: 1px solid rgba(148, 163, 184, .22); border-radius: 14px; background: rgba(248, 250, 252, .7); padding: 10px 12px; max-height: 180px; overflow: auto; -webkit-overflow-scrolling: touch; }
    .sale-row { display: flex; justify-content: space-between; gap: 10px; padding: 6px 0; border-bottom: 1px dashed rgba(148, 163, 184, .35); font-size: 12px; }
    .sale-row:last-child { border-bottom: none }
    .sale-row .left { min-width: 0 }
    .sale-row .left .nm { font-weight: 900; color: #0f172a; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 320px }
    .sale-row .left .cd { color: #64748b; font-size: 12px }
    .sale-row .right { white-space: nowrap; text-align: right }
    .sale-mini { font-size: 12px; color: #64748b; margin-top: 6px; display: flex; justify-content: space-between; gap: 10px }

    .page-nav { display:flex; gap:8px; align-items:center; justify-content:flex-end; flex-wrap:wrap; margin-top:10px; }
    .page-btn { border:1px solid rgba(148,163,184,.35); background:#fff; border-radius:10px; padding:8px 10px; font-weight:900; font-size:12px; cursor:pointer; }
    .page-btn[disabled]{ opacity:.55; cursor:not-allowed; }
    .page-info { font-size:12px; color:#64748b; font-weight:900; }

    @media(max-width:991.98px) {
      #tbDev { min-width: 980px }
      .grand .val { font-size: 22px }
    }
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
        <li class="nav-item">
          <a href="dashboard.php">
            <span class="icon">
              <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M8.74999 18.3333C12.2376 18.3333 15.1364 15.8128 15.7244 12.4941C15.8448 11.8143 15.2737 11.25 14.5833 11.25H9.99999C9.30966 11.25 8.74999 10.6903 8.74999 10V5.41666C8.74999 4.7263 8.18563 4.15512 7.50586 4.27556C4.18711 4.86357 1.66666 7.76243 1.66666 11.25C1.66666 15.162 4.83797 18.3333 8.74999 18.3333Z" />
                <path d="M17.0833 10C17.7737 10 18.3432 9.43708 18.2408 8.75433C17.7005 5.14918 14.8508 2.29947 11.2457 1.75912C10.5629 1.6568 10 2.2263 10 2.91665V9.16666C10 9.62691 10.3731 10 10.8333 10H17.0833Z" />
              </svg>
            </span>
            <span class="text">Dashboard</span>
          </a>
        </li>

        <li class="nav-item nav-item-has-children active">
          <a href="#0" data-bs-toggle="collapse" data-bs-target="#ddmenu_operacoes" aria-controls="ddmenu_operacoes" aria-expanded="true">
            <span class="icon">
              <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M3.33334 3.35442C3.33334 2.4223 4.07954 1.66666 5.00001 1.66666H15C15.9205 1.66666 16.6667 2.4223 16.6667 3.35442V16.8565C16.6667 17.5519 15.8827 17.9489 15.3333 17.5317L13.8333 16.3924C13.537 16.1673 13.1297 16.1673 12.8333 16.3924L10.5 18.1646C10.2037 18.3896 9.79634 18.3896 9.50001 18.1646L7.16668 16.3924C6.87038 16.1673 6.46298 16.1673 6.16668 16.3924L4.66668 17.5317C4.11731 17.9489 3.33334 17.5519 3.33334 16.8565V3.35442Z" />
              </svg>
            </span>
            <span class="text">Operações</span>
          </a>
          <ul id="ddmenu_operacoes" class="collapse show dropdown-nav">
            <li><a href="vendidos.php" class="active">Vendidos</a></li>
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

        <span class="divider"><hr /></span>

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
                <form action="#">
                  <input type="text" placeholder="Buscar Vendas..." id="qGlobal" />
                  <button type="submit" onclick="return false"><i class="lni lni-search-alt"></i></button>
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

        <!-- FILTROS -->
        <div class="cardx mb-3">
          <div class="head">
            <div>
              <div class="d-flex align-items-center gap-2">
                <span class="pill ok" id="pillCount">0 vendas</span>
                <span class="muted" id="lblRange">—</span>
              </div>
              <div class="muted mt-1">Lista de vendas registradas no PDV (tabela <b>vendas</b>)</div>
            </div>
            <div class="toolbar">
              <button class="main-btn light-btn btn-hover btn-compact" id="btnExcel">
                <i class="lni lni-download me-1"></i> Excel
              </button>
              <button class="main-btn light-btn btn-hover btn-compact" id="btnPdf">
                <i class="lni lni-printer me-1"></i> PDF
              </button>
              <select id="per" class="form-select compact w180">
                <option value="10">10 por página</option>
                <option value="25" selected>25 por página</option>
                <option value="50">50 por página</option>
                <option value="100">100 por página</option>
              </select>
            </div>
          </div>
          <div class="body">
            <div class="row g-2 align-items-end">
              <div class="col-md-2">
                <label class="form-label mini">Data inicial</label>
                <input type="date" class="form-control compact" id="di">
              </div>
              <div class="col-md-2">
                <label class="form-label mini">Data final</label>
                <input type="date" class="form-control compact" id="df">
              </div>
              <div class="col-md-2">
                <label class="form-label mini">Canal</label>
                <select class="form-select compact" id="canal">
                  <option value="TODOS" selected>Todos</option>
                  <option value="PRESENCIAL">Presencial</option>
                  <option value="DELIVERY">Delivery</option>
                </select>
              </div>
              <div class="col-md-2">
                <label class="form-label mini">Pagamento</label>
                <select class="form-select compact" id="pag">
                  <option value="TODOS" selected>Todos</option>
                  <option value="DINHEIRO">Dinheiro</option>
                  <option value="PIX">PIX</option>
                  <option value="CARTAO">Cartão</option>
                  <option value="BOLETO">Boleto</option>
                  <option value="MULTI">Multi</option>
                </select>
              </div>
              <div class="col-md-4">
                <label class="form-label mini">Cliente / Venda #</label>
                <div class="search-wrap">
                  <input type="text" class="form-control compact" id="q" placeholder="Ex.: Maria / 123" autocomplete="off">
                  <div class="suggest" id="suggest"></div>
                </div>
              </div>

              <div class="col-12 d-flex gap-2 flex-wrap mt-2">
                <button class="main-btn primary-btn btn-hover btn-compact" id="btnFiltrar">
                  <i class="lni lni-funnel me-1"></i> Filtrar
                </button>
                <button class="main-btn light-btn btn-hover btn-compact" id="btnLimpar">
                  <i class="lni lni-close me-1"></i> Limpar
                </button>
              </div>
            </div>
          </div>
        </div>

        <div class="row g-3">
          <!-- TABELA -->
          <div class="col-lg-8">
            <div class="cardx">
              <div class="head">
                <div class="muted"><b>Vendidos</b> • clique em <b>Detalhes</b> para ver itens/infos</div>
                <div class="toolbar">
                  <span class="pill" id="pillLoading" style="display:none;">Carregando…</span>
                </div>
              </div>
              <div class="body">
                <div class="table-responsive">
                  <table class="table table-hover" id="tbDev">
                    <thead>
                      <tr>
                        <th>ID</th>
                        <th>Data</th>
                        <th>Cliente</th>
                        <th>Canal</th>
                        <th>Pagamento</th>
                        <th>Itens</th>
                        <th>Subtotal</th>
                        <th>Desconto</th>
                        <th>Entrega</th>
                        <th>Total</th>
                        <th>Ações</th>
                      </tr>
                    </thead>
                    <tbody id="tbody">
                      <tr><td colspan="11" class="muted">Carregando…</td></tr>
                    </tbody>
                  </table>
                </div>

                <div class="page-nav">
                  <button class="page-btn" id="btnPrev">←</button>
                  <span class="page-info" id="pageInfo">Página 1</span>
                  <button class="page-btn" id="btnNext">→</button>
                </div>
              </div>
            </div>
          </div>

          <!-- TOTAIS -->
          <div class="col-lg-4">
            <div class="cardx">
              <div class="head">
                <div class="fw-1000">Totais do Filtro</div>
                <div class="muted">Somatório da tabela <b>vendas</b></div>
              </div>
              <div class="body">
                <div class="box-tot">
                  <div class="tot-row"><span>Quantidade</span><span id="tQtd">0</span></div>
                  <div class="tot-row"><span>Subtotal</span><span id="tSub">R$ 0,00</span></div>
                  <div class="tot-row"><span>Desconto</span><span id="tDesc">R$ 0,00</span></div>
                  <div class="tot-row"><span>Entrega</span><span id="tTaxa">R$ 0,00</span></div>
                  <div class="tot-hr"></div>
                  <div class="grand">
                    <div class="lbl">TOTAL</div>
                    <div class="val" id="tTotal">R$ 0,00</div>
                  </div>
                </div>

                <div class="muted mt-3">
                  <b>Obs.:</b> os <b>itens</b> aparecem se a venda estiver vinculada na tabela <b>saidas</b> (campo <b>pedido</b>).
                  Este arquivo tenta casar automaticamente: <b>id</b>, <b>0000id</b> ou <b>V{id}</b>.
                </div>
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

  <!-- MODAL DETALHES -->
  <div class="modal fade" id="mdDetalhes" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
      <div class="modal-content" style="border-radius:16px;">
        <div class="modal-header">
          <h5 class="modal-title fw-1000">Detalhes da Venda</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
        </div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-6">
              <div class="cardx">
                <div class="head"><b>Dados</b></div>
                <div class="body">
                  <div class="tot-row"><span>ID</span><span id="dId">—</span></div>
                  <div class="tot-row"><span>Data/Hora</span><span id="dDt">—</span></div>
                  <div class="tot-row"><span>Cliente</span><span id="dCli">—</span></div>
                  <div class="tot-row"><span>Canal</span><span id="dCanal">—</span></div>
                  <div class="tot-row"><span>Pagamento</span><span id="dPag">—</span></div>
                  <div class="tot-row"><span>Endereço</span><span id="dEnd">—</span></div>
                  <div class="tot-row"><span>Obs</span><span id="dObs">—</span></div>
                </div>
              </div>
            </div>

            <div class="col-md-6">
              <div class="cardx">
                <div class="head"><b>Totais</b></div>
                <div class="body">
                  <div class="tot-row"><span>Subtotal</span><span id="dSub">R$ 0,00</span></div>
                  <div class="tot-row"><span>Desconto</span><span id="dDesc">R$ 0,00</span></div>
                  <div class="tot-row"><span>Entrega</span><span id="dTaxa">R$ 0,00</span></div>
                  <div class="tot-hr"></div>
                  <div class="grand">
                    <div class="lbl">TOTAL</div>
                    <div class="val" id="dTotal">R$ 0,00</div>
                  </div>
                </div>
              </div>
            </div>

            <div class="col-12">
              <div class="cardx">
                <div class="head d-flex justify-content-between align-items-center">
                  <b>Itens</b>
                  <button class="main-btn light-btn btn-hover btn-compact" id="btnCupomModal">
                    <i class="lni lni-printer me-1"></i> Cupom
                  </button>
                </div>
                <div class="body">
                  <div class="sale-box" id="dItens">—</div>
                  <div class="sale-mini">
                    <span id="dItensQtd">0 itens</span>
                    <span class="money" id="dItensTot">R$ 0,00</span>
                  </div>
                </div>
              </div>
            </div>

          </div>
        </div>
        <div class="modal-footer">
          <button class="main-btn light-btn btn-hover btn-compact" data-bs-dismiss="modal">Fechar</button>
        </div>
      </div>
    </div>
  </div>

  <script src="assets/js/bootstrap.bundle.min.js"></script>
  <script src="assets/js/main.js"></script>

  <script>
    const csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

    const el = (id) => document.getElementById(id);

    const state = {
      page: 1,
      pages: 1,
      per: 25,
      lastCupomId: null,
      debounceTimer: null,
      suggestTimer: null,
    };

    function brl(v){
      try {
        return new Intl.NumberFormat('pt-BR',{style:'currency',currency:'BRL'}).format(Number(v||0));
      } catch(e){
        return 'R$ ' + (Number(v||0).toFixed(2)).replace('.',',');
      }
    }

    function fmtDate(iso){
      if(!iso) return '—';
      // iso yyyy-mm-dd
      const p = String(iso).split('-');
      if(p.length===3) return `${p[2]}/${p[1]}/${p[0]}`;
      return iso;
    }

    function fmtDateTime(dt){
      if(!dt) return '—';
      // MySQL DATETIME
      // "2026-03-01 20:12:02"
      const [d,t] = String(dt).split(' ');
      return `${fmtDate(d)} ${t||''}`.trim();
    }

    function buildParams(){
      const p = new URLSearchParams();
      p.set('action','fetch');
      p.set('page', String(state.page));
      p.set('per', String(state.per));

      const di = el('di').value.trim();
      const df = el('df').value.trim();
      const canal = el('canal').value.trim();
      const pag = el('pag').value.trim();
      const q = el('q').value.trim();

      if(di) p.set('di', di);
      if(df) p.set('df', df);
      if(canal) p.set('canal', canal);
      if(pag) p.set('pag', pag);
      if(q) p.set('q', q);

      return p;
    }

    function buildExportUrl(action){
      const p = buildParams();
      p.set('action', action);
      p.delete('page');
      p.delete('per');
      return `vendidos.php?${p.toString()}`;
    }

    function setLoading(on){
      el('pillLoading').style.display = on ? '' : 'none';
    }

    async function load(){
      setLoading(true);
      el('tbody').innerHTML = `<tr><td colspan="11" class="muted">Carregando…</td></tr>`;

      const p = buildParams();
      const url = `vendidos.php?${p.toString()}`;

      try{
        const res = await fetch(url, { headers: { 'X-CSRF': csrf }});
        const js = await res.json();
        if(!js.ok) throw new Error(js.msg || 'Falha ao carregar');

        // meta
        state.page = js.meta.page;
        state.pages = js.meta.pages;

        // totais
        el('tQtd').textContent = js.totais.qtd;
        el('tSub').textContent = brl(js.totais.subtotal);
        el('tDesc').textContent = brl(js.totais.desconto);
        el('tTaxa').textContent = brl(js.totais.taxa);
        el('tTotal').textContent = brl(js.totais.total);

        el('pillCount').textContent = `${js.totais.qtd} vendas`;
        el('pageInfo').textContent = `Página ${state.page} / ${state.pages}`;

        el('btnPrev').disabled = state.page <= 1;
        el('btnNext').disabled = state.page >= state.pages;

        // range label
        const di = el('di').value ? fmtDate(el('di').value) : '—';
        const df = el('df').value ? fmtDate(el('df').value) : '—';
        el('lblRange').textContent = `Período: ${di} até ${df}`;

        // rows
        const rows = js.rows || [];
        if(!rows.length){
          el('tbody').innerHTML = `<tr><td colspan="11" class="muted">Nenhuma venda encontrada com este filtro.</td></tr>`;
          return;
        }

        el('tbody').innerHTML = rows.map(r => {
          const canalBadge = r.canal === 'DELIVERY'
            ? `<span class="badge-soft b-open">DELIVERY</span>`
            : `<span class="badge-soft b-done">PRESENCIAL</span>`;

          const pagBadge = `<span class="badge-soft b-open">${(r.pagamento||'—')}</span>`;

          let itensHtml = `<span class="muted">—</span>`;
          if (r.itens && r.itens.length){
            const show = r.itens.slice(0, 4);
            const extra = r.itens.length - show.length;
            itensHtml = `
              <div class="sale-box">
                ${show.map(it => `
                  <div class="sale-row">
                    <div class="left">
                      <div class="nm">${escapeHtml(it.nome || 'Item')}</div>
                      <div class="cd">${escapeHtml(it.codigo || '')}</div>
                    </div>
                    <div class="right">
                      <div><b>${numQ(it.qtd)}</b> ${escapeHtml(it.un || '')}</div>
                      <div class="muted">${brl(it.total)}</div>
                    </div>
                  </div>
                `).join('')}
                ${extra>0 ? `<div class="muted mt-2">+ ${extra} item(ns)…</div>` : ``}
              </div>
            `;
          }

          return `
            <tr>
              <td><b>#${r.id}</b></td>
              <td>
                <div class="mini">${fmtDate(r.data)}</div>
                <div class="muted">${fmtDateTime(r.created_at)}</div>
              </td>
              <td style="max-width:260px; overflow:hidden; text-overflow:ellipsis;">
                <div class="mini">${escapeHtml(r.cliente || '—')}</div>
                ${r.endereco ? `<div class="muted" style="max-width:260px;overflow:hidden;text-overflow:ellipsis;">${escapeHtml(r.endereco)}</div>` : ``}
              </td>
              <td>${canalBadge}</td>
              <td>${pagBadge}</td>
              <td>${itensHtml}</td>
              <td class="money">${brl(r.subtotal)}</td>
              <td>${brl(r.desconto)}</td>
              <td>${brl(r.taxa)}</td>
              <td class="money">${brl(r.total)}</td>
              <td>
                <div class="d-flex gap-2">
                  <button class="main-btn light-btn btn-hover btn-compact" onclick="openDetails(${r.id})">Detalhes</button>
                  <button class="main-btn primary-btn btn-hover btn-compact" onclick="openCupom(${r.id})">Cupom</button>
                </div>
              </td>
            </tr>
          `;
        }).join('');

      }catch(err){
        el('tbody').innerHTML = `<tr><td colspan="11" class="text-danger">Erro: ${escapeHtml(err.message||String(err))}</td></tr>`;
      }finally{
        setLoading(false);
      }
    }

    function escapeHtml(s){
      return String(s ?? '').replace(/[&<>"']/g, (m) => ({
        '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'
      }[m]));
    }

    function numQ(v){
      // mostra 3 casas se precisar, senão inteiro
      const n = Number(v||0);
      if (Number.isInteger(n)) return String(n);
      return n.toLocaleString('pt-BR',{minimumFractionDigits:0, maximumFractionDigits:3});
    }

    async function openDetails(id){
      try{
        const res = await fetch(`vendidos.php?action=one&id=${id}`, { headers: { 'X-CSRF': csrf }});
        const js = await res.json();
        if(!js.ok) throw new Error(js.msg || 'Falha ao abrir detalhes');

        const v = js.data.venda;
        const itens = js.data.itens || [];
        state.lastCupomId = Number(v.id);

        el('dId').textContent = `#${v.id}`;
        el('dDt').textContent = `${fmtDate(v.data)} • ${fmtDateTime(v.created_at)}`;
        el('dCli').textContent = v.cliente || '—';
        el('dCanal').textContent = v.canal || '—';
        el('dPag').textContent = v.pagamento || '—';
        el('dEnd').textContent = v.endereco || '—';
        el('dObs').textContent = v.obs || '—';

        el('dSub').textContent = brl(v.subtotal);
        el('dDesc').textContent = brl(v.desconto_valor);
        el('dTaxa').textContent = brl(v.taxa_entrega);
        el('dTotal').textContent = brl(v.total);

        if(!itens.length){
          el('dItens').innerHTML = `<span class="muted">Sem itens vinculados (não encontrado em <b>saidas</b>).</span>`;
          el('dItensQtd').textContent = `0 itens`;
          el('dItensTot').textContent = brl(0);
        } else {
          el('dItens').innerHTML = itens.map(it => `
            <div class="sale-row">
              <div class="left">
                <div class="nm">${escapeHtml(it.nome||'Item')}</div>
                ${it.codigo ? `<div class="cd">${escapeHtml(it.codigo)}</div>` : ``}
                <div class="cd">${escapeHtml(it.un||'')} • ${brl(it.preco)}</div>
              </div>
              <div class="right">
                <div><b>${numQ(it.qtd)}</b></div>
                <div class="muted">${brl(it.total)}</div>
              </div>
            </div>
          `).join('');

          el('dItensQtd').textContent = `${itens.length} item(ns)`;
          el('dItensTot').textContent = brl(js.data.itens_total || 0);
        }

        const modal = new bootstrap.Modal(el('mdDetalhes'));
        modal.show();
      }catch(err){
        alert('Erro: ' + (err.message||String(err)));
      }
    }

    function openCupom(id){
      window.open(`vendidos.php?action=cupom&id=${id}`, '_blank');
    }

    // modal cupom
    el('btnCupomModal').addEventListener('click', () => {
      if (!state.lastCupomId) return;
      openCupom(state.lastCupomId);
    });

    // paginação
    el('btnPrev').addEventListener('click', () => {
      if (state.page <= 1) return;
      state.page -= 1;
      load();
    });
    el('btnNext').addEventListener('click', () => {
      if (state.page >= state.pages) return;
      state.page += 1;
      load();
    });

    // filtrar / limpar
    el('btnFiltrar').addEventListener('click', () => {
      state.page = 1;
      load();
    });
    el('btnLimpar').addEventListener('click', () => {
      el('di').value = '';
      el('df').value = '';
      el('canal').value = 'TODOS';
      el('pag').value = 'TODOS';
      el('q').value = '';
      el('qGlobal').value = '';
      hideSuggest();
      state.page = 1;
      load();
    });

    // por página
    el('per').addEventListener('change', () => {
      state.per = Number(el('per').value || 25);
      state.page = 1;
      load();
    });

    // export
    el('btnExcel').addEventListener('click', () => {
      window.location.href = buildExportUrl('excel');
    });
    el('btnPdf').addEventListener('click', () => {
      window.open(buildExportUrl('print'), '_blank');
    });

    // busca global sincroniza com q
    el('qGlobal').addEventListener('input', () => {
      el('q').value = el('qGlobal').value;
      scheduleFilter();
      scheduleSuggest();
    });

    // busca local com debounce (não ficar batendo no servidor a cada tecla)
    el('q').addEventListener('input', () => {
      el('qGlobal').value = el('q').value;
      scheduleFilter();
      scheduleSuggest();
    });

    function scheduleFilter(){
      clearTimeout(state.debounceTimer);
      state.debounceTimer = setTimeout(() => {
        state.page = 1;
        load();
      }, 450);
    }

    // sugestões
    function hideSuggest(){ el('suggest').style.display = 'none'; el('suggest').innerHTML=''; }
    function scheduleSuggest(){
      clearTimeout(state.suggestTimer);
      state.suggestTimer = setTimeout(async () => {
        const q = el('q').value.trim();
        if (q.length < 2) { hideSuggest(); return; }

        try{
          const res = await fetch(`vendidos.php?action=suggest&q=${encodeURIComponent(q)}`, { headers:{'X-CSRF':csrf}});
          const js = await res.json();
          if(!js.ok) { hideSuggest(); return; }
          const items = js.items || [];
          if(!items.length) { hideSuggest(); return; }

          el('suggest').innerHTML = items.map(name => `
            <div class="it" onclick="pickSuggest('${escapeJs(name)}')">
              <div class="t">${escapeHtml(name)}</div>
              <div class="s">cliente</div>
            </div>
          `).join('');
          el('suggest').style.display = 'block';
        }catch(e){
          hideSuggest();
        }
      }, 220);
    }

    function escapeJs(s){
      return String(s).replace(/\\/g,'\\\\').replace(/'/g,"\\'");
    }
    window.pickSuggest = function(name){
      el('q').value = name;
      el('qGlobal').value = name;
      hideSuggest();
      state.page = 1;
      load();
    };

    document.addEventListener('click', (ev) => {
      const sw = el('suggest');
      const wrap = sw?.parentElement;
      if (!wrap) return;
      if (!wrap.contains(ev.target)) hideSuggest();
    });

    // init
    (function init(){
      state.per = Number(el('per').value || 25);
      load();
    })();
  </script>
</body>
</html>