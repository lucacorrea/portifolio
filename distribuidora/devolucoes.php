<?php

declare(strict_types=1);

@date_default_timezone_set('America/Manaus');
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

if (function_exists('ob_start')) {
  @ob_start();
}

require_once __DIR__ . '/assets/conexao.php';
require_once __DIR__ . '/assets/dados/devolucoes/_helpers.php';

$pdo = db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

/* =========================
   FALLBACKS (se helpers não tiver)
========================= */
if (!function_exists('e')) {
  function e(string $s): string
  {
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
  }
}
if (!function_exists('csrf_token')) {
  function csrf_token(): string
  {
    if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
    return (string)$_SESSION['csrf_token'];
  }
}
if (!function_exists('csrf_validate_token')) {
  function csrf_validate_token(string $t): bool
  {
    return isset($_SESSION['csrf_token']) && hash_equals((string)$_SESSION['csrf_token'], (string)$t);
  }
}
if (!function_exists('json_input')) {
  function json_input(): array
  {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw ?: '', true);
    return is_array($data) ? $data : [];
  }
}
if (!function_exists('to_int')) {
  function to_int($v, int $min = PHP_INT_MIN, int $max = PHP_INT_MAX): int
  {
    $n = (int)($v ?? 0);
    if ($n < $min) $n = $min;
    if ($n > $max) $n = $max;
    return $n;
  }
}
if (!function_exists('to_float')) {
  function to_float($v): float
  {
    $s = trim((string)$v);
    $s = preg_replace('/[^\d,.\-]/', '', $s);
    $s = str_replace('.', '', $s);
    $s = str_replace(',', '.', $s);
    $n = (float)$s;
    return $n;
  }
}
if (!function_exists('flash_pop')) {
  function flash_pop(): ?array
  {
    $x = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return is_array($x) ? $x : null;
  }
}

function json_out(array $data, int $code = 200): void
{
  if (function_exists('ob_get_length') && ob_get_length()) {
    @ob_clean();
  }
  http_response_code($code);
  header('Content-Type: application/json; charset=utf-8');
  header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
  echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  exit;
}

function brl(float $v): string
{
  return 'R$ ' . number_format($v, 2, ',', '.');
}
function dtbr(string $ymd): string
{
  $ts = strtotime($ymd);
  return $ts ? date('d/m/Y', $ts) : $ymd;
}
function dtbr_dt(string $ymd, string $his): string
{
  $ymd = trim($ymd);
  $his = trim($his);
  if ($ymd === '') return '';
  $ts = strtotime($ymd . ' ' . ($his ?: '00:00:00'));
  return $ts ? date('d/m/Y H:i', $ts) : ($ymd . ' ' . $his);
}

/* =========================================================
   ✅ table_exists CORRETO (INFORMATION_SCHEMA)
   (não depende de SHOW TABLES / bind)
========================================================= */
function table_exists(PDO $pdo, string $table): bool
{
  $st = $pdo->prepare("
    SELECT 1
    FROM information_schema.tables
    WHERE table_schema = DATABASE()
      AND table_name = :t
    LIMIT 1
  ");
  $st->execute([':t' => $table]);
  return (bool)$st->fetchColumn();
}

/* =========================================================
   ESTOQUE (reposição ao concluir devolução)
========================================================= */
function extract_product_code(string $product): string
{
  $p = trim(str_replace(["\r", "\n", "\t"], ' ', $product));
  if ($p === '') return '';
  if (strpos($p, ' - ') !== false) {
    $parts = explode(' - ', $p, 2);
    return trim((string)($parts[0] ?? ''));
  }
  if (preg_match('/^([A-Za-z0-9._-]+)/', $p, $m)) return trim($m[1]);
  return '';
}

function devolucao_effect(PDO $pdo, array $dev): array
{
  $status = strtoupper(trim((string)($dev['status'] ?? '')));
  if ($status !== 'CONCLUIDO') return [];

  $type   = strtoupper(trim((string)($dev['type'] ?? $dev['tipo'] ?? 'TOTAL')));
  $saleNo = (int)($dev['saleNo'] ?? $dev['venda_no'] ?? 0);

  $effect = [];

  if ($type === 'TOTAL') {
    if ($saleNo <= 0) return [];
    $st = $pdo->prepare("SELECT codigo, qtd FROM venda_itens WHERE venda_id = ? ORDER BY id ASC");
    $st->execute([$saleNo]);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    foreach ($rows as $r) {
      $code = trim((string)($r['codigo'] ?? ''));
      $qty  = (int)($r['qtd'] ?? 0);
      if ($code === '' || $qty <= 0) continue;
      $effect[$code] = ($effect[$code] ?? 0) + $qty;
    }
    return $effect;
  }

  $product = (string)($dev['product'] ?? $dev['produto'] ?? '');
  $qty     = (int)($dev['qty'] ?? $dev['qtd'] ?? 0);
  if ($qty <= 0) return [];

  $code = extract_product_code($product);
  if ($code === '') return [];
  $effect[$code] = ($effect[$code] ?? 0) + $qty;
  return $effect;
}

function apply_stock_delta(PDO $pdo, array $deltaMap): array
{
  $missing = [];

  $up = $pdo->prepare("
    UPDATE produtos
    SET estoque = GREATEST(0, estoque + :delta)
    WHERE codigo = :codigo
    LIMIT 1
  ");

  foreach ($deltaMap as $code => $delta) {
    $code = trim((string)$code);
    $delta = (int)$delta;
    if ($code === '' || $delta === 0) continue;

    $up->execute([':delta' => $delta, ':codigo' => $code]);
    if ($up->rowCount() === 0) $missing[] = $code;
  }

  return $missing;
}

function map_add(array $a, array $b): array
{
  foreach ($b as $k => $v) {
    $a[$k] = (int)($a[$k] ?? 0) + (int)$v;
    if ((int)$a[$k] === 0) unset($a[$k]);
  }
  return $a;
}

/* =========================================================
   PRODUTOS (cache p/ autocomplete)
========================================================= */
$PRODUTOS_CACHE = [];
try {
  if (table_exists($pdo, 'produtos')) {
    $stP = $pdo->query("
      SELECT id, codigo, nome, status
      FROM produtos
      WHERE (status IS NULL OR status = '' OR UPPER(TRIM(status))='ATIVO')
      ORDER BY nome ASC
      LIMIT 6000
    ");
    $rowsP = $stP->fetchAll(PDO::FETCH_ASSOC) ?: [];
    foreach ($rowsP as $r) {
      $PRODUTOS_CACHE[] = [
        'id'   => (int)($r['id'] ?? 0),
        'code' => (string)($r['codigo'] ?? ''),
        'name' => (string)($r['nome'] ?? ''),
      ];
    }
  }
} catch (Throwable $e) {
  $PRODUTOS_CACHE = [];
}

/* =========================================================
   WHERE builder (para list / export)
========================================================= */
function build_where(string $q, string $status): array
{
  $where = [];
  $params = [];

  $q = trim($q);
  $status = strtoupper(trim($status));

  if ($status !== '' && in_array($status, ['ABERTO', 'CONCLUIDO', 'CANCELADO'], true)) {
    $where[] = "UPPER(TRIM(d.status)) = :status";
    $params[':status'] = $status;
  }

  if ($q !== '') {
    if (preg_match('/^\d+$/', $q)) {
      $where[] = "(CAST(d.id AS CHAR) LIKE :qstart OR CAST(d.venda_no AS CHAR) LIKE :qstart)";
      $params[':qstart'] = $q . '%';
    } else {
      $where[] = "(
        d.cliente LIKE :qlike
        OR d.produto LIKE :qlike
        OR d.motivo LIKE :qlike
        OR d.obs LIKE :qlike
      )";
      $params[':qlike'] = '%' . $q . '%';
    }
  }

  $sql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';
  return [$sql, $params];
}

/* =========================================================
   EXPORTS (EXCEL/PDF)
========================================================= */
$export = strtolower(trim((string)($_GET['export'] ?? '')));
if ($export === 'excel' || $export === 'pdf') {
  $q = (string)($_GET['q'] ?? '');
  $status = (string)($_GET['status'] ?? '');

  [$w, $p] = build_where($q, $status);

  $st = $pdo->prepare("
    SELECT d.id, d.venda_no, d.cliente, d.data, d.hora, d.tipo, d.produto, d.qtd, d.valor, d.motivo, d.obs, d.status, d.created_at
    FROM devolucoes d
    $w
    ORDER BY d.data DESC, d.hora DESC, d.id DESC
  ");
  $st->execute($p);
  $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

  // Totais (no mesmo filtro aplicado)
  $tot = ['ABERTO' => 0.0, 'CONCLUIDO' => 0.0, 'CANCELADO' => 0.0, 'GERAL' => 0.0];
  foreach ($rows as $r) {
    $v = (float)($r['valor'] ?? 0);
    $stt = strtoupper((string)($r['status'] ?? 'ABERTO'));
    if (!isset($tot[$stt])) $stt = 'ABERTO';
    $tot[$stt] += $v;
    $tot['GERAL'] += $v;
  }

  $geradoEm = date('d/m/Y H:i:s');
  $filtroTxt = 'Status: ' . ($status !== '' ? strtoupper($status) : 'Todos');
  if (trim($q) !== '') $filtroTxt .= ' | Busca: ' . $q;

  if ($export === 'excel') {
    $fn = 'devolucoes_' . date('Ymd_His') . '.xls';
    header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $fn . '"');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    echo "\xEF\xBB\xBF";
?>
    <html>

    <head>
      <meta charset="utf-8">
    </head>

    <body>
      <table border="0" cellpadding="4" cellspacing="0" style="font-family:Calibri,Arial; font-size:12px; width:100%;">
        <tr>
          <td colspan="10" style="font-size:16px; font-weight:800;">
            PAINEL DA DISTRIBUIDORA - DEVOLUÇÕES (RESUMO)
          </td>
        </tr>
        <tr>
          <td><b>Gerado em:</b></td>
          <td colspan="9"><?= e($geradoEm) ?></td>
        </tr>
        <tr>
          <td><b>Filtro:</b></td>
          <td colspan="9"><?= e($filtroTxt) ?></td>
        </tr>
        <tr>
          <td><b>Total (Aberto):</b></td>
          <td><?= e(brl($tot['ABERTO'])) ?></td>
          <td colspan="8"></td>
        </tr>
        <tr>
          <td><b>Total (Concluído):</b></td>
          <td><?= e(brl($tot['CONCLUIDO'])) ?></td>
          <td colspan="8"></td>
        </tr>
        <tr>
          <td><b>Total (Cancelado):</b></td>
          <td><?= e(brl($tot['CANCELADO'])) ?></td>
          <td colspan="8"></td>
        </tr>
        <tr>
          <td><b>TOTAL (Geral):</b></td>
          <td><b><?= e(brl($tot['GERAL'])) ?></b></td>
          <td colspan="8"></td>
        </tr>
      </table>

      <br>

      <table border="1" cellpadding="6" cellspacing="0" style="border-collapse:collapse; font-family:Calibri,Arial; font-size:12px; width:100%;">
        <tr style="background:#f3f6f8; font-weight:800;">
          <td>ID</td>
          <td>Data/Hora</td>
          <td>Venda</td>
          <td>Cliente</td>
          <td>Tipo</td>
          <td>Produto</td>
          <td>Qtd</td>
          <td>Valor</td>
          <td>Motivo</td>
          <td>Status</td>
        </tr>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td><?= (int)$r['id'] ?></td>
            <td><?= e(dtbr_dt((string)$r['data'], (string)$r['hora'])) ?></td>
            <td><?= ($r['venda_no'] !== null ? '#' . (int)$r['venda_no'] : '—') ?></td>
            <td><?= e((string)($r['cliente'] ?? '')) ?></td>
            <td><?= e((string)($r['tipo'] ?? 'TOTAL')) ?></td>
            <td><?= e((string)($r['produto'] ?? '')) ?></td>
            <td><?= ($r['qtd'] !== null ? (int)$r['qtd'] : '—') ?></td>
            <td><?= e(brl((float)$r['valor'])) ?></td>
            <td><?= e((string)($r['motivo'] ?? '')) ?></td>
            <td><?= e((string)($r['status'] ?? 'ABERTO')) ?></td>
          </tr>
        <?php endforeach; ?>
      </table>
    </body>

    </html>
  <?php
    exit;
  }

  // PDF (layout A4 para imprimir/salvar)
  ?>
  <!doctype html>
  <html lang="pt-BR">

  <head>
    <meta charset="utf-8">
    <title>Devoluções (PDF)</title>
    <style>
      @page {
        size: A4;
        margin: 12mm;
      }

      body {
        font-family: Arial, Helvetica, sans-serif;
        color: #0f172a;
      }

      .title {
        font-size: 18px;
        font-weight: 800;
        text-align: center;
        margin: 0 0 8px;
      }

      .meta {
        font-size: 12px;
        margin-bottom: 10px;
      }

      .meta div {
        margin: 2px 0;
      }

      table {
        width: 100%;
        border-collapse: collapse;
        font-size: 11px;
      }

      th,
      td {
        border: 1px solid #d1d5db;
        padding: 6px;
        vertical-align: middle;
      }

      th {
        background: #f3f6f8;
        font-weight: 800;
      }

      .tot {
        margin: 10px 0 12px;
        font-size: 12px;
      }

      .tot b {
        font-size: 13px;
      }

      .btnbar {
        display: flex;
        gap: 10px;
        justify-content: flex-end;
        margin: 0 0 10px;
      }

      .btn {
        padding: 8px 12px;
        border: 1px solid #cbd5e1;
        border-radius: 8px;
        background: #fff;
        cursor: pointer;
        font-weight: 700;
      }

      .btn.primary {
        border-color: #2563eb;
        color: #2563eb;
      }

      @media print {
        .btnbar {
          display: none;
        }
      }
    </style>
  </head>

  <body>
    <div class="btnbar">
      <button class="btn primary" onclick="window.print()">Imprimir / Salvar como PDF</button>
      <button class="btn" onclick="window.close()">Fechar</button>
    </div>

    <div class="title">PAINEL DA DISTRIBUIDORA - DEVOLUÇÕES</div>

    <div class="meta">
      <div><b>Gerado em:</b> <?= e($geradoEm) ?></div>
      <div><b>Filtro:</b> <?= e($filtroTxt) ?></div>
    </div>

    <div class="tot">
      <div><b>Total em aberto:</b> <?= e(brl($tot['ABERTO'])) ?></div>
      <div><b>Total concluído:</b> <?= e(brl($tot['CONCLUIDO'])) ?></div>
      <div><b>Total cancelado:</b> <?= e(brl($tot['CANCELADO'])) ?></div>
      <div style="margin-top:6px;"><b>TOTAL (geral):</b> <?= e(brl($tot['GERAL'])) ?></div>
    </div>

    <table>
      <thead>
        <tr>
          <th>ID</th>
          <th>Data/Hora</th>
          <th>Venda</th>
          <th>Cliente</th>
          <th>Tipo</th>
          <th>Produto</th>
          <th>Qtd</th>
          <th>Valor</th>
          <th>Motivo</th>
          <th>Status</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td><?= (int)$r['id'] ?></td>
            <td><?= e(dtbr_dt((string)$r['data'], (string)$r['hora'])) ?></td>
            <td><?= ($r['venda_no'] !== null ? '#' . (int)$r['venda_no'] : '—') ?></td>
            <td><?= e((string)($r['cliente'] ?? '')) ?></td>
            <td><?= e((string)($r['tipo'] ?? 'TOTAL')) ?></td>
            <td><?= e((string)($r['produto'] ?? '')) ?></td>
            <td><?= ($r['qtd'] !== null ? (int)$r['qtd'] : '—') ?></td>
            <td><?= e(brl((float)$r['valor'])) ?></td>
            <td><?= e((string)($r['motivo'] ?? '')) ?></td>
            <td><?= e((string)($r['status'] ?? 'ABERTO')) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </body>

  </html>
<?php
  exit;
}

/* =========================================================
   AJAX
========================================================= */
if (isset($_GET['ajax'])) {
  $ajax = (string)($_GET['ajax'] ?? '');

  try {

    // buscar vendas
    if ($ajax === 'buscarVendas') {
      $q = trim((string)($_GET['q'] ?? ''));
      if ($q === '') json_out(['ok' => true, 'items' => []]);

      if (!table_exists($pdo, 'vendas')) json_out(['ok' => true, 'items' => []]);

      if (preg_match('/^\d+$/', $q)) {
        $st = $pdo->prepare("
          SELECT id, created_at, cliente, total, canal
          FROM vendas
          WHERE CAST(id AS CHAR) LIKE :start
          ORDER BY id DESC
          LIMIT 20
        ");
        $st->execute([':start' => $q . '%']);
      } else {
        $st = $pdo->prepare("
          SELECT id, created_at, cliente, total, canal
          FROM vendas
          WHERE cliente LIKE :like
          ORDER BY id DESC
          LIMIT 20
        ");
        $st->execute([':like' => '%' . $q . '%']);
      }

      $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
      $items = [];
      foreach ($rows as $r) {
        $items[] = [
          'id' => (int)($r['id'] ?? 0),
          'date' => (string)($r['created_at'] ?? ''),
          'customer' => (string)($r['cliente'] ?? ''),
          'total' => (float)($r['total'] ?? 0),
          'canal' => (string)($r['canal'] ?? 'PRESENCIAL'),
        ];
      }
      json_out(['ok' => true, 'items' => $items]);
    }

    // itens da venda
    if ($ajax === 'itensVenda') {
      $id = (int)($_GET['id'] ?? 0);
      if ($id <= 0) json_out(['ok' => true, 'items' => []]);

      if (!table_exists($pdo, 'venda_itens')) json_out(['ok' => true, 'items' => []]);

      $st = $pdo->prepare("
        SELECT id, codigo, nome, qtd, preco_unit, subtotal, unidade
        FROM venda_itens
        WHERE venda_id = ?
        ORDER BY id ASC
      ");
      $st->execute([$id]);
      $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

      $items = [];
      foreach ($rows as $r) {
        $items[] = [
          'id' => (int)($r['id'] ?? 0),
          'code' => (string)($r['codigo'] ?? ''),
          'name' => (string)($r['nome'] ?? ''),
          'qty' => (int)($r['qtd'] ?? 0),
          'unit' => (string)($r['unidade'] ?? ''),
          'price' => (float)($r['preco_unit'] ?? 0),
          'subtotal' => (float)($r['subtotal'] ?? 0),
        ];
      }

      json_out(['ok' => true, 'items' => $items]);
    }

    // ✅ listagem paginada (10 em 10)
    if ($ajax === 'list') {
      if (!table_exists($pdo, 'devolucoes')) json_out(['ok' => true, 'items' => [], 'page' => 1, 'per' => 10, 'total_rows' => 0, 'total_pages' => 1, 'totals' => []]);

      $page = to_int($_GET['page'] ?? 1, 1, 999999);
      $per  = to_int($_GET['per'] ?? 10, 1, 50);
      if ($per < 1) $per = 10;
      if ($per > 50) $per = 50;

      $q = (string)($_GET['q'] ?? '');
      $status = (string)($_GET['status'] ?? '');

      // filtro do grid (inclui status)
      [$w, $p] = build_where($q, $status);

      // contagem
      $stC = $pdo->prepare("SELECT COUNT(*) c FROM devolucoes d $w");
      $stC->execute($p);
      $totalRows = (int)($stC->fetchColumn() ?: 0);
      $totalPages = max(1, (int)ceil($totalRows / $per));
      if ($page > $totalPages) $page = $totalPages;
      $off = ($page - 1) * $per;

      // query paginada
      $sql = "
        SELECT d.id, d.venda_no, d.cliente, d.data, d.hora, d.tipo, d.produto, d.qtd, d.valor, d.motivo, d.obs, d.status, d.created_at
        FROM devolucoes d
        $w
        ORDER BY d.data DESC, d.hora DESC, d.id DESC
        LIMIT :lim OFFSET :off
      ";
      $st = $pdo->prepare($sql);
      foreach ($p as $k => $v) $st->bindValue($k, $v);
      $st->bindValue(':lim', (int)$per, PDO::PARAM_INT);
      $st->bindValue(':off', (int)$off, PDO::PARAM_INT);
      $st->execute();
      $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

      $items = [];
      foreach ($rows as $r) {
        $items[] = [
          'id'      => (int)($r['id'] ?? 0),
          'saleNo'  => ($r['venda_no'] !== null ? (int)$r['venda_no'] : null),
          'customer' => (string)($r['cliente'] ?? ''),
          'date'    => (string)($r['data'] ?? ''),
          'time'    => (string)($r['hora'] ?? ''),
          'type'    => (string)($r['tipo'] ?? 'TOTAL'),
          'product' => (string)($r['produto'] ?? ''),
          'qty'     => ($r['qtd'] !== null ? (int)$r['qtd'] : null),
          'amount'  => (float)($r['valor'] ?? 0),
          'reason'  => (string)($r['motivo'] ?? 'OUTRO'),
          'note'    => (string)($r['obs'] ?? ''),
          'status'  => (string)($r['status'] ?? 'ABERTO'),
          'created_at' => (string)($r['created_at'] ?? ''),
        ];
      }

      // totais do resumo (mesma busca, mas sem travar no status do filtro)
      [$w2, $p2] = build_where($q, ''); // ignora status, mantém busca
      $stT = $pdo->prepare("
        SELECT UPPER(TRIM(d.status)) st, COALESCE(SUM(d.valor),0) s
        FROM devolucoes d
        $w2
        GROUP BY UPPER(TRIM(d.status))
      ");
      $stT->execute($p2);
      $tot = ['ABERTO' => 0.0, 'CONCLUIDO' => 0.0, 'CANCELADO' => 0.0, 'GERAL' => 0.0];
      while ($r = $stT->fetch(PDO::FETCH_ASSOC)) {
        $stx = strtoupper((string)($r['st'] ?? 'ABERTO'));
        $sum = (float)($r['s'] ?? 0);
        if (!isset($tot[$stx])) $stx = 'ABERTO';
        $tot[$stx] += $sum;
        $tot['GERAL'] += $sum;
      }

      json_out([
        'ok' => true,
        'items' => $items,
        'page' => $page,
        'per' => $per,
        'total_rows' => $totalRows,
        'total_pages' => $totalPages,
        'totals' => $tot,
      ]);
    }

    // save (com estoque)
    if ($ajax === 'save') {
      $payload = json_input();
      $csrf = (string)($payload['csrf_token'] ?? '');
      if (!csrf_validate_token($csrf)) json_out(['ok' => false, 'msg' => 'CSRF inválido. Recarregue a página.'], 403);

      $id     = to_int($payload['id'] ?? 0);
      $saleNoRaw = trim((string)($payload['saleNo'] ?? ''));
      $saleNo = ($saleNoRaw !== '' && ctype_digit($saleNoRaw)) ? (int)$saleNoRaw : null;

      $customer = trim((string)($payload['customer'] ?? ''));
      $date = trim((string)($payload['date'] ?? ''));
      $time = trim((string)($payload['time'] ?? ''));

      if ($date === '') json_out(['ok' => false, 'msg' => 'Informe a data.'], 400);
      if ($time === '') json_out(['ok' => false, 'msg' => 'Informe a hora.'], 400);

      $type = strtoupper(trim((string)($payload['type'] ?? 'TOTAL')));
      if (!in_array($type, ['TOTAL', 'PARCIAL'], true)) $type = 'TOTAL';

      $product = trim((string)($payload['product'] ?? ''));
      $qty = to_int($payload['qty'] ?? 1, 1);
      $amount = (float)to_float($payload['amount'] ?? 0);

      if ($amount <= 0) json_out(['ok' => false, 'msg' => 'Informe um valor (R$) maior que zero.'], 400);

      $reason = strtoupper(trim((string)($payload['reason'] ?? 'OUTRO')));
      $allowReason = ['DEFEITO', 'TROCA', 'ARREPENDIMENTO', 'AVARIA_TRANSPORTE', 'OUTRO'];
      if (!in_array($reason, $allowReason, true)) $reason = 'OUTRO';

      $note = trim((string)($payload['note'] ?? ''));

      $status = strtoupper(trim((string)($payload['status'] ?? 'ABERTO')));
      $allowStatus = ['ABERTO', 'CONCLUIDO', 'CANCELADO'];
      if (!in_array($status, $allowStatus, true)) $status = 'ABERTO';

      if ($type === 'TOTAL') {
        $product = '';
        $qty = 0;
        if ($status === 'CONCLUIDO' && (!$saleNo || $saleNo <= 0)) {
          json_out(['ok' => false, 'msg' => 'Para concluir uma devolução TOTAL, informe o nº da venda (para repor estoque).'], 400);
        }
      } else {
        if ($product === '') json_out(['ok' => false, 'msg' => 'Informe o produto para devolução parcial.'], 400);
        if ($qty < 1) json_out(['ok' => false, 'msg' => 'Informe a quantidade (mín. 1).'], 400);

        if ($status === 'CONCLUIDO') {
          $code = extract_product_code($product);
          if ($code === '') json_out(['ok' => false, 'msg' => 'Para concluir devolução PARCIAL, informe o produto com código (ex: P0001 - Arroz).'], 400);
        }
      }

      $pdo->beginTransaction();
      $missing = [];

      try {
        $old = null;
        if ($id > 0) {
          $stOld = $pdo->prepare("SELECT * FROM devolucoes WHERE id = ? FOR UPDATE");
          $stOld->execute([$id]);
          $old = $stOld->fetch(PDO::FETCH_ASSOC);
          if (!$old) {
            $pdo->rollBack();
            json_out(['ok' => false, 'msg' => 'Devolução não encontrada para editar.'], 404);
          }
        }

        $oldEffect = $old ? devolucao_effect($pdo, [
          'status' => (string)($old['status'] ?? ''),
          'tipo'   => (string)($old['tipo'] ?? 'TOTAL'),
          'venda_no' => (int)($old['venda_no'] ?? 0),
          'produto' => (string)($old['produto'] ?? ''),
          'qtd'    => (int)($old['qtd'] ?? 0),
        ]) : [];

        $newEffect = devolucao_effect($pdo, [
          'status' => $status,
          'type'   => $type,
          'saleNo' => (int)($saleNo ?? 0),
          'product' => $product,
          'qty'    => ($type === 'PARCIAL' ? $qty : 0),
        ]);

        $delta = $newEffect;
        $negOld = [];
        foreach ($oldEffect as $k => $v) $negOld[$k] = -1 * (int)$v;
        $delta = map_add($delta, $negOld);

        if ($delta && table_exists($pdo, 'produtos')) {
          $missing = apply_stock_delta($pdo, $delta);
        }

        if ($id > 0) {
          $st = $pdo->prepare("
            UPDATE devolucoes
            SET venda_no=:venda_no, cliente=:cliente, data=:data, hora=:hora,
                tipo=:tipo, produto=:produto, qtd=:qtd, valor=:valor,
                motivo=:motivo, obs=:obs, status=:status
            WHERE id=:id
          ");
          $st->execute([
            ':venda_no' => $saleNo,
            ':cliente'  => ($customer !== '' ? $customer : null),
            ':data'     => $date,
            ':hora'     => $time,
            ':tipo'     => $type,
            ':produto'  => ($type === 'PARCIAL' ? $product : null),
            ':qtd'      => ($type === 'PARCIAL' ? $qty : null),
            ':valor'    => $amount,
            ':motivo'   => $reason,
            ':obs'      => ($note !== '' ? $note : null),
            ':status'   => $status,
            ':id'       => $id,
          ]);
        } else {
          $st = $pdo->prepare("
            INSERT INTO devolucoes
              (venda_no, cliente, data, hora, tipo, produto, qtd, valor, motivo, obs, status)
            VALUES
              (:venda_no, :cliente, :data, :hora, :tipo, :produto, :qtd, :valor, :motivo, :obs, :status)
          ");
          $st->execute([
            ':venda_no' => $saleNo,
            ':cliente'  => ($customer !== '' ? $customer : null),
            ':data'     => $date,
            ':hora'     => $time,
            ':tipo'     => $type,
            ':produto'  => ($type === 'PARCIAL' ? $product : null),
            ':qtd'      => ($type === 'PARCIAL' ? $qty : null),
            ':valor'    => $amount,
            ':motivo'   => $reason,
            ':obs'      => ($note !== '' ? $note : null),
            ':status'   => $status,
          ]);
          $id = (int)$pdo->lastInsertId();
        }

        $pdo->commit();
      } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
      }

      $msg = 'Devolução salva com sucesso!';
      if ($missing) $msg .= ' (Atenção: não encontrei no estoque: ' . implode(', ', $missing) . ')';

      json_out(['ok' => true, 'msg' => $msg]);
    }

    // delete (desfaz estoque se estava CONCLUÍDO)
    if ($ajax === 'del') {
      $payload = json_input();
      $csrf = (string)($payload['csrf_token'] ?? '');
      if (!csrf_validate_token($csrf)) json_out(['ok' => false, 'msg' => 'CSRF inválido.'], 403);

      $id = to_int($payload['id'] ?? 0);
      if ($id <= 0) json_out(['ok' => false, 'msg' => 'ID inválido.'], 400);

      $pdo->beginTransaction();
      $missing = [];

      try {
        $stOld = $pdo->prepare("SELECT * FROM devolucoes WHERE id = ? FOR UPDATE");
        $stOld->execute([$id]);
        $old = $stOld->fetch(PDO::FETCH_ASSOC);
        if (!$old) {
          $pdo->rollBack();
          json_out(['ok' => false, 'msg' => 'Devolução não encontrada.'], 404);
        }

        $oldEffect = devolucao_effect($pdo, [
          'status' => (string)($old['status'] ?? ''),
          'tipo'   => (string)($old['tipo'] ?? 'TOTAL'),
          'venda_no' => (int)($old['venda_no'] ?? 0),
          'produto' => (string)($old['produto'] ?? ''),
          'qtd'    => (int)($old['qtd'] ?? 0),
        ]);

        if ($oldEffect && table_exists($pdo, 'produtos')) {
          $neg = [];
          foreach ($oldEffect as $k => $v) $neg[$k] = -1 * (int)$v;
          $missing = apply_stock_delta($pdo, $neg);
        }

        $st = $pdo->prepare("DELETE FROM devolucoes WHERE id=?");
        $st->execute([$id]);

        $pdo->commit();
      } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
      }

      $msg = 'Devolução excluída.';
      if ($missing) $msg .= ' (Atenção: não encontrei no estoque: ' . implode(', ', $missing) . ')';

      json_out(['ok' => true, 'msg' => $msg]);
    }

    json_out(['ok' => false, 'msg' => 'Ação ajax inválida.'], 400);
  } catch (Throwable $e) {
    json_out(['ok' => false, 'msg' => $e->getMessage()], 500);
  }
}

/* =========================================================
   HTML NORMAL
========================================================= */
$csrf = csrf_token();
$flash = flash_pop();
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
  <meta charset="UTF-8" />
  <meta http-equiv="X-UA-Compatible" content="IE=edge" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="csrf-token" content="<?= e($csrf) ?>">

  <link rel="shortcut icon" href="assets/images/favicon.svg" type="image/x-icon" />
  <title>Painel da Distribuidora | Devoluções</title>

  <link rel="stylesheet" href="assets/css/bootstrap.min.css" />
  <link rel="stylesheet" href="assets/css/lineicons.css" rel="stylesheet" type="text/css" />
  <link rel="stylesheet" href="assets/css/materialdesignicons.min.css" rel="stylesheet" type="text/css" />
  <link rel="stylesheet" href="assets/css/main.css" />

  <style>
    .main-btn.btn-compact {
      height: 38px !important;
      padding: 8px 14px !important;
      font-size: 13px !important;
      line-height: 1 !important
    }

    .icon-btn {
      height: 34px !important;
      width: 42px !important;
      padding: 0 !important;
      display: inline-flex !important;
      align-items: center !important;
      justify-content: center !important
    }

    .form-control.compact,
    .form-select.compact {
      height: 38px;
      padding: 8px 12px;
      font-size: 13px
    }

    .cardx {
      border: 1px solid rgba(148, 163, 184, .28);
      border-radius: 16px;
      background: #fff;
      overflow: hidden
    }

    .cardx .head {
      padding: 12px 14px;
      border-bottom: 1px solid rgba(148, 163, 184, .22);
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 10px;
      flex-wrap: wrap
    }

    .cardx .body {
      padding: 14px
    }

    .muted {
      font-size: 12px;
      color: #64748b
    }

    .pill {
      padding: 6px 10px;
      border-radius: 999px;
      border: 1px solid rgba(148, 163, 184, .25);
      font-weight: 900;
      font-size: 12px;
      display: inline-flex;
      align-items: center;
      gap: 8px;
      background: rgba(248, 250, 252, .7)
    }

    .pill.ok {
      border-color: rgba(34, 197, 94, .25);
      background: rgba(240, 253, 244, .9);
      color: #166534
    }

    .pill.warn {
      border-color: rgba(245, 158, 11, .28);
      background: rgba(255, 251, 235, .9);
      color: #92400e
    }

    .chip-toggle {
      display: flex;
      gap: 10px;
      flex-wrap: wrap
    }

    .chip {
      border: 1px solid rgba(148, 163, 184, .35);
      border-radius: 999px;
      padding: 8px 12px;
      cursor: pointer;
      font-weight: 900;
      font-size: 12px;
      user-select: none;
      background: #fff
    }

    .chip.active {
      background: rgba(239, 246, 255, .75);
      border-color: rgba(37, 99, 235, .55);
      outline: 2px solid rgba(37, 99, 235, .25)
    }

    .reason-box {
      border: 1px solid rgba(148, 163, 184, .25);
      border-radius: 14px;
      padding: 10px 12px;
      background: rgba(248, 250, 252, .7)
    }

    .table td,
    .table th {
      vertical-align: middle
    }

    .table-responsive {
      -webkit-overflow-scrolling: touch
    }

    #tbDev {
      width: 100%;
      min-width: 1200px
    }

    #tbDev th {
      font-weight: 900;
      color: #0f172a
    }

    #tbDev td {
      font-weight: 600;
      color: #0f172a
    }

    .money {
      font-weight: 1000;
      color: #0b5ed7
    }

    .mini {
      font-size: 12px;
      color: #475569;
      font-weight: 800
    }

    .badge-soft {
      font-weight: 1000;
      border-radius: 999px;
      padding: 6px 10px;
      font-size: 11px;
      display: inline-flex;
      align-items: center;
      justify-content: center
    }

    .b-open {
      background: rgba(255, 251, 235, .95);
      color: #92400e;
      border: 1px solid rgba(245, 158, 11, .25)
    }

    .b-done {
      background: rgba(240, 253, 244, .95);
      color: #166534;
      border: 1px solid rgba(34, 197, 94, .25)
    }

    .b-cancel {
      background: rgba(254, 242, 242, .95);
      color: #991b1b;
      border: 1px solid rgba(239, 68, 68, .25)
    }

    .toolbar {
      display: flex;
      gap: 10px;
      flex-wrap: wrap;
      align-items: center;
      width: 100%
    }

    .toolbar .grow {
      flex: 1 1 260px;
      min-width: 240px
    }

    .toolbar .w180 {
      min-width: 180px
    }

    .pager-box {
      display: flex;
      align-items: center;
      justify-content: flex-end;
      gap: 10px;
      margin-top: 12px
    }

    .pager-box .page-text {
      font-size: 12px;
      color: #64748b;
      font-weight: 900
    }

    .pager-box .btn-disabled {
      opacity: .45;
      pointer-events: none
    }

    .search-wrap {
      position: relative
    }

    .suggest {
      position: absolute;
      z-index: 9999;
      left: 0;
      right: 0;
      top: calc(100% + 6px);
      background: #fff;
      border: 1px solid rgba(148, 163, 184, .25);
      border-radius: 14px;
      box-shadow: 0 10px 30px rgba(15, 23, 42, .10);
      max-height: 280px;
      overflow: auto;
      display: none
    }

    .suggest .it {
      padding: 10px 12px;
      cursor: pointer;
      display: flex;
      justify-content: space-between;
      gap: 10px
    }

    .suggest .it:hover {
      background: rgba(241, 245, 249, .9)
    }

    .suggest .t {
      font-weight: 900;
      font-size: 12px;
      color: #0f172a;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis
    }

    .suggest .s {
      font-size: 12px;
      color: #64748b;
      white-space: nowrap
    }

    .sale-box {
      border: 1px solid rgba(148, 163, 184, .22);
      border-radius: 14px;
      background: rgba(248, 250, 252, .7);
      padding: 10px 12px;
      max-height: 180px;
      overflow: auto;
      -webkit-overflow-scrolling: touch;
    }

    .sale-row {
      display: flex;
      justify-content: space-between;
      gap: 10px;
      padding: 6px 0;
      border-bottom: 1px dashed rgba(148, 163, 184, .35);
      font-size: 12px;
    }

    .sale-row:last-child {
      border-bottom: none
    }

    .sale-row .left {
      min-width: 0
    }

    .sale-row .left .nm {
      font-weight: 900;
      color: #0f172a;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
      max-width: 320px
    }

    .sale-row .left .cd {
      color: #64748b;
      font-size: 12px
    }

    .sale-row .right {
      white-space: nowrap;
      text-align: right
    }

    .sale-mini {
      font-size: 12px;
      color: #64748b;
      margin-top: 6px;
      display: flex;
      justify-content: space-between;
      gap: 10px
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
        <li class="nav-item"><a href="dashboard.php"><span class="icon">
              <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M8.74999 18.3333C12.2376 18.3333 15.1364 15.8128 15.7244 12.4941C15.8448 11.8143 15.2737 11.25 14.5833 11.25H9.99999C9.30966 11.25 8.74999 10.6903 8.74999 10V5.41666C8.74999 4.7263 8.18563 4.15512 7.50586 4.27556C4.18711 4.86357 1.66666 7.76243 1.66666 11.25C1.66666 15.162 4.83797 18.3333 8.74999 18.3333Z" />
                <path d="M17.0833 10C17.7737 10 18.3432 9.43708 18.2408 8.75433C17.7005 5.14918 14.8508 2.29947 11.2457 1.75912C10.5629 1.6568 10 2.2263 10 2.91665V9.16666C10 9.62691 10.3731 10 10.8333 10H17.0833Z" />
              </svg>
            </span><span class="text">Dashboard</span></a></li>

        <li class="nav-item"><a href="vendas.php"><span class="icon">
              <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M1.66666 5C1.66666 3.89543 2.5621 3 3.66666 3H16.3333C17.4379 3 18.3333 3.89543 18.3333 5V15C18.3333 16.1046 17.4379 17 16.3333 17H3.66666C2.5621 17 1.66666 16.1046 1.66666 15V5Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                <path d="M1.66666 5L10 10.8333L18.3333 5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
              </svg>
            </span><span class="text">Vendas</span></a></li>

        <li class="nav-item nav-item-has-children active">
          <a href="#0" class="collapsed" data-bs-toggle="collapse" data-bs-target="#ddmenu_operacoes" aria-controls="ddmenu_operacoes" aria-expanded="false">
            <span class="icon">
              <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M3.33334 3.35442C3.33334 2.4223 4.07954 1.66666 5.00001 1.66666H15C15.9205 1.66666 16.6667 2.4223 16.6667 3.35442V16.8565C16.6667 17.5519 15.8827 17.9489 15.3333 17.5317L13.8333 16.3924C13.537 16.1673 13.1297 16.1673 12.8333 16.3924L10.5 18.1646C10.2037 18.3896 9.79634 18.3896 9.50001 18.1646L7.16668 16.3924C6.87038 16.1673 6.46298 16.1673 6.16668 16.3924L4.66668 17.5317C4.11731 17.9489 3.33334 17.5519 3.33334 16.8565V3.35442Z" />
              </svg>
            </span>
            <span class="text">Operações</span>
          </a>
          <ul id="ddmenu_operacoes" class="collapse show dropdown-nav">
            <li><a href="vendidos.php">Vendidos</a></li>
            <li><a href="fiados.php">À Prazo</a></li>
            <li><a href="devolucoes.php" class="active">Devoluções</a></li>
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
                  <input type="text" placeholder="Buscar devolução..." id="qGlobal" />
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
        <div class="title-wrapper pt-30">
          <div class="row align-items-center">
            <div class="col-md-8">
              <div class="title">
                <h2>Devoluções</h2>
                <div class="muted">Registro e controle de devoluções • <b>F2</b> salvar | <b>F4</b> focar na busca</div>
              </div>
            </div>
            <div class="col-md-4 text-md-end">
              <button class="main-btn primary-btn btn-hover btn-compact" id="btnNova" type="button">
                <i class="lni lni-plus me-1"></i> Nova devolução
              </button>
            </div>
          </div>
        </div>

        <?php if ($flash): ?>
          <div class="alert alert-<?= e($flash['type']) ?>"><?= e($flash['msg']) ?></div>
        <?php endif; ?>

        <div class="row g-3 mb-3">
          <div class="col-12 col-lg-6">
            <div class="cardx">
              <div class="head">
                <div style="font-weight:1000;color:#0f172a;"><i class="lni lni-package me-1"></i> Lançamento</div>
                <span class="pill warn" id="formMode"><i class="lni lni-pencil"></i> NOVO</span>
              </div>
              <div class="body">
                <input type="hidden" id="dId" />

                <div class="mb-3">
                  <label class="form-label">Venda (Nº) / Cliente</label>
                  <div class="search-wrap">
                    <input class="form-control compact" id="dVendaNo" placeholder="Digite nº da venda ou nome do cliente..." autocomplete="off" />
                    <div class="suggest" id="saleSuggest"></div>
                  </div>
                  <div class="muted mt-1">Selecione na lista para puxar cliente e itens.</div>
                </div>

                <div class="mb-3">
                  <label class="form-label">Cliente</label>
                  <input class="form-control compact" id="dCliente" placeholder="CPF ou Nome (opcional)" />
                </div>

                <div class="row g-2">
                  <div class="col-6 mb-3">
                    <label class="form-label">Data</label>
                    <input class="form-control compact" id="dData" type="date" />
                  </div>
                  <div class="col-6 mb-3">
                    <label class="form-label">Hora</label>
                    <input class="form-control compact" id="dHora" type="time" />
                  </div>
                </div>

                <div class="mb-3">
                  <label class="form-label">Tipo</label>
                  <div class="chip-toggle">
                    <div class="chip active" id="chipTotal">Devolução Total</div>
                    <div class="chip" id="chipParcial">Parcial</div>
                  </div>
                </div>

                <div class="mb-3">
                  <label class="form-label">Produto (se parcial)</label>
                  <div class="search-wrap">
                    <input class="form-control compact" id="dProduto" placeholder="Nome / Código do produto" autocomplete="off" />
                    <div class="suggest" id="prodSuggest"></div>
                  </div>
                  <div class="muted mt-1">Se selecionou a venda, sugere apenas itens da venda.</div>
                </div>

                <div class="row g-2">
                  <div class="col-6 mb-3">
                    <label class="form-label">Qtd</label>
                    <input class="form-control compact" id="dQtd" type="number" min="1" value="1" />
                  </div>
                  <div class="col-6 mb-3">
                    <label class="form-label">Valor (R$)</label>
                    <input class="form-control compact" id="dValor" placeholder="0,00" value="0,00" />
                  </div>
                </div>

                <div class="mb-3" id="saleItemsWrap" style="display:none;">
                  <label class="form-label">Itens da Venda Selecionada</label>
                  <div class="sale-box" id="saleItemsBox"></div>
                  <div class="sale-mini">
                    <div id="saleMiniLeft">—</div>
                    <div id="saleMiniRight">—</div>
                  </div>
                </div>

                <div class="mb-3">
                  <label class="form-label">Motivo</label>
                  <div class="reason-box">
                    <select class="form-select compact mb-2" id="dMotivo">
                      <option value="DEFEITO">Defeito</option>
                      <option value="TROCA">Troca</option>
                      <option value="ARREPENDIMENTO">Arrependimento</option>
                      <option value="AVARIA_TRANSPORTE">Avaria no Transporte</option>
                      <option value="OUTRO" selected>Outro</option>
                    </select>
                    <input class="form-control compact" id="dObs" placeholder="Observação (opcional)" />
                  </div>
                </div>

                <div class="mb-3">
                  <label class="form-label">Status</label>
                  <select class="form-select compact" id="dStatus">
                    <option value="ABERTO" selected>Em aberto</option>
                    <option value="CONCLUIDO">Concluído</option>
                    <option value="CANCELADO">Cancelado</option>
                  </select>
                  <div class="muted mt-1">* Estoque só é reposto quando <b>CONCLUÍDO</b>.</div>
                </div>

                <div class="d-grid gap-2">
                  <button class="main-btn primary-btn btn-hover btn-compact" id="btnSalvar" type="button">
                    <i class="lni lni-save me-1"></i> Salvar (F2)
                  </button>
                  <button class="main-btn light-btn btn-hover btn-compact" id="btnLimpar" type="button">
                    <i class="lni lni-eraser me-1"></i> Limpar
                  </button>
                </div>

              </div>
            </div>
          </div>

          <div class="col-12 col-lg-6">
            <div class="cardx h-100">
              <div class="head">
                <div style="font-weight:1000;color:#0f172a;"><i class="lni lni-stats-up me-1"></i> Resumo</div>
              </div>
              <div class="body">
                <div style="border:1px solid rgba(148,163,184,.25); border-radius:14px; background:#fff; padding:12px">
                  <div style="display:flex;justify-content:space-between;gap:10px;font-size:13px;color:#334155;margin-bottom:8px;font-weight:900">
                    <span>Total em aberto</span><span class="money" id="tAberto">R$ 0,00</span>
                  </div>
                  <div style="display:flex;justify-content:space-between;gap:10px;font-size:13px;color:#334155;margin-bottom:8px;font-weight:900">
                    <span>Total concluído</span><span class="money" id="tConcl">R$ 0,00</span>
                  </div>
                  <div style="display:flex;justify-content:space-between;gap:10px;font-size:13px;color:#334155;margin-bottom:8px;font-weight:900">
                    <span>Total cancelado</span><span class="money" id="tCancel">R$ 0,00</span>
                  </div>
                  <div style="height:1px;background:rgba(148,163,184,.22);margin:10px 0"></div>
                  <div style="display:flex;justify-content:space-between;align-items:baseline;gap:10px;margin-top:4px">
                    <span style="font-weight:1000;color:#0f172a;font-size:16px">TOTAL (geral)</span>
                    <span style="font-weight:1000;color:#0b5ed7;font-size:26px;letter-spacing:.2px" id="tGeral">R$ 0,00</span>
                  </div>
                </div>
                <div class="muted mt-2">* Somatório baseado no campo “Valor (R$)” das devoluções.</div>
              </div>
            </div>
          </div>
        </div>

        <div class="row g-3 mb-30">
          <div class="col-12">
            <div class="cardx">
              <div class="head">
                <div style="font-weight:1000;color:#0f172a;"><i class="lni lni-list me-1"></i> Listagem</div>
                <div class="toolbar">
                  <input class="form-control compact grow" id="qDev" placeholder="Buscar: venda, cliente, produto, motivo..." />
                  <select class="form-select compact w180" id="fStatus">
                    <option value="">Todos</option>
                    <option value="ABERTO">Em aberto</option>
                    <option value="CONCLUIDO">Concluído</option>
                    <option value="CANCELADO">Cancelado</option>
                  </select>

                  <!-- ✅ substitui JSON por Excel/PDF -->
                  <button class="main-btn light-btn btn-hover btn-compact" id="btnExcel" type="button">
                    <i class="lni lni-download me-1"></i> Excel
                  </button>
                  <button class="main-btn light-btn btn-hover btn-compact" id="btnPdf" type="button">
                    <i class="lni lni-printer me-1"></i> PDF
                  </button>
                </div>
              </div>

              <div class="body">
                <div class="table-responsive">
                  <table class="table text-nowrap" id="tbDev">
                    <thead>
                      <tr>
                        <th style="min-width:80px;">ID</th>
                        <th style="min-width:160px;">Data/Hora</th>
                        <th style="min-width:110px;">Venda</th>
                        <th style="min-width:220px;">Cliente</th>
                        <th style="min-width:110px;">Tipo</th>
                        <th style="min-width:260px;">Produto</th>
                        <th style="min-width:80px;" class="text-center">Qtd</th>
                        <th style="min-width:120px;" class="text-end">Valor</th>
                        <th style="min-width:180px;">Motivo/Obs</th>
                        <th style="min-width:130px;" class="text-center">Status</th>
                        <th style="min-width:140px;" class="text-center">Ações</th>
                      </tr>
                    </thead>
                    <tbody id="tbodyDev"></tbody>
                  </table>
                </div>

                <div class="muted mt-2" id="hintNone" style="display:none;">Nenhuma devolução encontrada.</div>

                <!-- ✅ paginação -->
                <div class="pager-box" id="pagerDev" style="display:none;"></div>
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

  <script src="assets/js/bootstrap.bundle.min.js"></script>
  <script src="assets/js/main.js"></script>

  <script>
    const CSRF = document.querySelector('meta[name="csrf-token"]').content;
    const AJAX_URL = "devolucoes.php";
    const PRODUCTS = <?= json_encode($PRODUTOS_CACHE, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

    function safeText(s) {
      return String(s ?? "")
        .replaceAll("&", "&amp;").replaceAll("<", "&lt;").replaceAll(">", "&gt;")
        .replaceAll('"', "&quot;").replaceAll("'", "&#039;");
    }

    function moneyToNumber(txt) {
      let s = String(txt ?? "").trim();
      if (!s) return 0;
      s = s.replace(/[^\d,.-]/g, "").replace(/\./g, "").replace(",", ".");
      const n = Number(s);
      return isNaN(n) ? 0 : n;
    }

    function numberToMoney(n) {
      const v = Number(n || 0);
      return "R$ " + v.toFixed(2).replace(".", ",");
    }

    function fetchJSON(url, opts = {}) {
      return fetch(url, opts).then(async r => {
        const data = await r.json().catch(() => ({}));
        if (!r.ok || data.ok === false) throw new Error(data.msg || "Erro na requisição.");
        return data;
      });
    }

    function pad2(n) {
      return String(n).padStart(2, "0");
    }

    function nowISODate() {
      const d = new Date();
      return `${d.getFullYear()}-${pad2(d.getMonth()+1)}-${pad2(d.getDate())}`;
    }

    function nowISOTime() {
      const d = new Date();
      return `${pad2(d.getHours())}:${pad2(d.getMinutes())}`;
    }

    function fmtBRDateTime(dateISO, timeISO) {
      if (!dateISO) return "";
      const [y, m, d] = String(dateISO).split("-");
      const t = (timeISO || "00:00").slice(0, 5);
      return `${d}/${m}/${y} ${t}`;
    }

    // =========================
    // STATE
    // =========================
    let TYPE = "TOTAL";
    let SALE_SELECTED = null;
    let SALE_ITEMS = [];
    let LAST_SALES = [];
    let LAST_PROD = [];
    let saleTimer = null,
      prodTimer = null;
    let saleAbort = null;

    // listagem paginada (server)
    let CUR_PAGE = 1;
    const PER_PAGE = 10;
    let TOTAL_PAGES = 1;
    let ROWS = [];
    let SEARCH_TIMER = null;

    // =========================
    // ELEMENTS
    // =========================
    const qGlobal = document.getElementById("qGlobal");
    const btnNova = document.getElementById("btnNova");
    const btnSalvar = document.getElementById("btnSalvar");
    const btnLimpar = document.getElementById("btnLimpar");

    const dId = document.getElementById("dId");
    const dVendaNo = document.getElementById("dVendaNo");
    const saleSuggest = document.getElementById("saleSuggest");
    const dCliente = document.getElementById("dCliente");
    const dData = document.getElementById("dData");
    const dHora = document.getElementById("dHora");

    const dProduto = document.getElementById("dProduto");
    const prodSuggest = document.getElementById("prodSuggest");
    const dQtd = document.getElementById("dQtd");
    const dValor = document.getElementById("dValor");

    const dMotivo = document.getElementById("dMotivo");
    const dObs = document.getElementById("dObs");
    const dStatus = document.getElementById("dStatus");

    const chipTotal = document.getElementById("chipTotal");
    const chipParcial = document.getElementById("chipParcial");
    const formMode = document.getElementById("formMode");

    const saleItemsWrap = document.getElementById("saleItemsWrap");
    const saleItemsBox = document.getElementById("saleItemsBox");
    const saleMiniLeft = document.getElementById("saleMiniLeft");
    const saleMiniRight = document.getElementById("saleMiniRight");

    const qDev = document.getElementById("qDev");
    const fStatus = document.getElementById("fStatus");
    const tbodyDev = document.getElementById("tbodyDev");
    const hintNone = document.getElementById("hintNone");
    const pagerDev = document.getElementById("pagerDev");

    const tAberto = document.getElementById("tAberto");
    const tConcl = document.getElementById("tConcl");
    const tCancel = document.getElementById("tCancel");
    const tGeral = document.getElementById("tGeral");

    const btnExcel = document.getElementById("btnExcel");
    const btnPdf = document.getElementById("btnPdf");

    // =========================
    // FORM
    // =========================
    function setFormMode(mode) {
      if (mode === "EDIT") {
        formMode.className = "pill ok";
        formMode.innerHTML = `<i class="lni lni-checkmark-circle"></i> EDITANDO`;
      } else {
        formMode.className = "pill warn";
        formMode.innerHTML = `<i class="lni lni-pencil"></i> NOVO`;
      }
    }

    function hideSaleSuggest() {
      saleSuggest.style.display = "none";
      saleSuggest.innerHTML = "";
    }

    function hideProdSuggest() {
      prodSuggest.style.display = "none";
      prodSuggest.innerHTML = "";
    }

    function hideSaleItems() {
      saleItemsWrap.style.display = "none";
      saleItemsBox.innerHTML = "";
      saleMiniLeft.textContent = "—";
      saleMiniRight.textContent = "—";
    }

    function clearSaleSelection() {
      SALE_SELECTED = null;
      SALE_ITEMS = [];
      LAST_SALES = [];
      hideSaleSuggest();
      hideSaleItems();
    }

    function applyTotalFromSaleIfAny() {
      if (!SALE_SELECTED) return;
      const sumQty = SALE_ITEMS.reduce((a, x) => a + Number(x.qty || 0), 0);
      dProduto.value = `VENDA #${SALE_SELECTED.id} (TOTAL - ${SALE_ITEMS.length} itens)`;
      dQtd.value = sumQty > 0 ? sumQty : 1;

      const curVal = moneyToNumber(dValor.value);
      if (curVal <= 0 && Number(SALE_SELECTED.total || 0) > 0) {
        dValor.value = Number(SALE_SELECTED.total || 0).toFixed(2).replace(".", ",");
      }
    }

    function setType(type) {
      TYPE = type;
      const isTotal = type === "TOTAL";
      chipTotal.classList.toggle("active", isTotal);
      chipParcial.classList.toggle("active", !isTotal);

      dProduto.disabled = isTotal;
      dQtd.disabled = isTotal;

      if (isTotal) {
        hideProdSuggest();
        applyTotalFromSaleIfAny();
      }
    }

    function resetForm() {
      dId.value = "";
      dVendaNo.value = "";
      dCliente.value = "";
      dData.value = nowISODate();
      dHora.value = nowISOTime();
      TYPE = "TOTAL";
      setType("TOTAL");
      dProduto.value = "";
      dQtd.value = 1;
      dValor.value = "0,00";
      dMotivo.value = "OUTRO";
      dObs.value = "";
      dStatus.value = "ABERTO";
      clearSaleSelection();
      setFormMode("NEW");
    }

    // =========================
    // SALE SEARCH
    // =========================
    function showSaleSuggest(list) {
      if (!list.length) {
        hideSaleSuggest();
        return;
      }
      saleSuggest.innerHTML = list.map(v => `
        <div class="it" data-id="${Number(v.id)}">
          <div style="min-width:0">
            <div class="t">#${Number(v.id)} • ${safeText(v.customer||"Consumidor Final")}</div>
            <div class="s">${safeText(v.date||"")} • ${safeText(v.canal||"")} • ${numberToMoney(v.total||0)}</div>
          </div>
          <div class="s">Selecionar</div>
        </div>
      `).join("");
      saleSuggest.style.display = "block";
      saleSuggest.scrollTop = 0;
    }

    async function searchSales(q) {
      const s = String(q || "").trim();
      if (!s) return [];
      if (saleAbort) saleAbort.abort();
      saleAbort = new AbortController();

      const url = `${AJAX_URL}?ajax=buscarVendas&q=` + encodeURIComponent(s);
      const r = await fetch(url, {
        signal: saleAbort.signal
      });
      const data = await r.json().catch(() => ({}));
      if (!r.ok || data.ok === false) return [];
      return (data.items || []);
    }

    function refreshSaleDebounced() {
      clearTimeout(saleTimer);
      saleTimer = setTimeout(async () => {
        const q = dVendaNo.value.trim();
        if (!q) {
          LAST_SALES = [];
          hideSaleSuggest();
          return;
        }
        LAST_SALES = await searchSales(q);
        showSaleSuggest(LAST_SALES);
      }, 140);
    }

    dVendaNo.addEventListener("input", () => {
      if (SALE_SELECTED && String(SALE_SELECTED.id) !== dVendaNo.value.trim()) clearSaleSelection();
      refreshSaleDebounced();
    });
    dVendaNo.addEventListener("focus", refreshSaleDebounced);

    async function loadSaleItems(saleId) {
      try {
        const r = await fetchJSON(`${AJAX_URL}?ajax=itensVenda&id=${saleId}`);
        SALE_ITEMS = (r.items || []).map(x => ({
          id: Number(x.id),
          code: String(x.code || ""),
          name: String(x.name || ""),
          qty: Number(x.qty || 0),
          unit: String(x.unit || ""),
          price: Number(x.price || 0),
          subtotal: Number(x.subtotal || 0),
        }));
      } catch {
        SALE_ITEMS = [];
      }
    }

    function renderSaleItems() {
      if (!SALE_SELECTED || !SALE_ITEMS.length) {
        hideSaleItems();
        return;
      }
      saleItemsWrap.style.display = "block";

      saleItemsBox.innerHTML = SALE_ITEMS.map(it => `
        <div class="sale-row">
          <div class="left">
            <div class="nm">${safeText(it.name)}</div>
            <div class="cd">${safeText(it.code)} • ${Number(it.qty||0)} ${safeText(it.unit||"")}</div>
          </div>
          <div class="right">
            <div style="font-weight:900;color:#0f172a;">${numberToMoney(it.subtotal||0)}</div>
            <div class="muted">Unit: ${numberToMoney(it.price||0)}</div>
          </div>
        </div>
      `).join("");

      const sumQty = SALE_ITEMS.reduce((a, x) => a + Number(x.qty || 0), 0);
      const sumSub = SALE_ITEMS.reduce((a, x) => a + Number(x.subtotal || 0), 0);
      saleMiniLeft.textContent = `Itens: ${SALE_ITEMS.length} • Qtd total: ${sumQty}`;
      saleMiniRight.textContent = `Subtotal itens: ${numberToMoney(sumSub)}`;
    }

    saleSuggest.addEventListener("click", async (e) => {
      const it = e.target.closest(".it");
      if (!it) return;
      const id = Number(it.getAttribute("data-id") || 0);
      const v = LAST_SALES.find(x => Number(x.id) === id);
      if (!v) return;

      SALE_SELECTED = v;
      dVendaNo.value = String(v.id);
      dCliente.value = String(v.customer || "Consumidor Final");
      hideSaleSuggest();

      await loadSaleItems(id);
      renderSaleItems();

      if (TYPE === "TOTAL") applyTotalFromSaleIfAny();
    });

    document.addEventListener("click", (e) => {
      if (!e.target.closest("#saleSuggest") && !e.target.closest("#dVendaNo")) hideSaleSuggest();
      if (!e.target.closest("#prodSuggest") && !e.target.closest("#dProduto")) hideProdSuggest();
    });

    // =========================
    // PRODUCT SUGGEST (PARCIAL)
    // =========================
    function onlyDigits(s) {
      return String(s || "").replace(/\D+/g, "");
    }

    function filterProductsLocal(q) {
      const s = String(q || "").trim().toLowerCase();
      if (!s) return [];
      const sDig = onlyDigits(s);
      const source = (SALE_SELECTED && SALE_ITEMS.length) ? SALE_ITEMS : PRODUCTS;

      const out = [];
      for (const p of source) {
        const code = String(p.code || "").toLowerCase();
        const name = String(p.name || "").toLowerCase();
        const cdig = onlyDigits(code);
        if (name.includes(s) || code.includes(s) || (sDig && cdig.includes(sDig))) out.push(p);
      }
      out.sort((a, b) => String(a.name || "").localeCompare(String(b.name || "")));
      return out.slice(0, 20);
    }

    function showProdSuggest(list) {
      if (!list.length) {
        hideProdSuggest();
        return;
      }
      prodSuggest.innerHTML = list.map(p => `
        <div class="it" data-code="${safeText(p.code)}">
          <div style="min-width:0">
            <div class="t">${safeText(p.name)}</div>
            <div class="s">${safeText(p.code)}${(p.qty!=null && p.qty>0) ? ` • Qtd venda: ${Number(p.qty)}` : ""}</div>
          </div>
          <div class="s">${(p.subtotal!=null && p.subtotal>0) ? numberToMoney(p.subtotal) : "OK"}</div>
        </div>
      `).join("");
      prodSuggest.style.display = "block";
      prodSuggest.scrollTop = 0;
    }

    function refreshProdDebounced() {
      clearTimeout(prodTimer);
      prodTimer = setTimeout(() => {
        if (TYPE !== "PARCIAL") {
          hideProdSuggest();
          return;
        }
        LAST_PROD = filterProductsLocal(dProduto.value);
        showProdSuggest(LAST_PROD);
      }, 120);
    }

    dProduto.addEventListener("input", refreshProdDebounced);
    dProduto.addEventListener("focus", refreshProdDebounced);

    prodSuggest.addEventListener("click", (e) => {
      const it = e.target.closest(".it");
      if (!it) return;
      const code = it.getAttribute("data-code") || "";
      const p = LAST_PROD.find(x => String(x.code || "") === code);
      if (!p) return;

      dProduto.value = `${p.code} - ${p.name}`;
      if (SALE_SELECTED && SALE_ITEMS.length) {
        if (Number(p.qty || 0) > 0) dQtd.value = Number(p.qty);
        if (Number(p.subtotal || 0) > 0) dValor.value = Number(p.subtotal).toFixed(2).replace(".", ",");
      }
      hideProdSuggest();
    });

    // =========================
    // LIST / PAGINATION
    // =========================
    function badgeStatus(s) {
      s = String(s || "").toUpperCase();
      if (s === "CONCLUIDO") return `<span class="badge-soft b-done">CONCLUÍDO</span>`;
      if (s === "CANCELADO") return `<span class="badge-soft b-cancel">CANCELADO</span>`;
      return `<span class="badge-soft b-open">EM ABERTO</span>`;
    }

    function motivoLabel(m) {
      const map = {
        "DEFEITO": "Defeito",
        "TROCA": "Troca",
        "ARREPENDIMENTO": "Arrependimento",
        "AVARIA_TRANSPORTE": "Avaria Transporte",
        "OUTRO": "Outro"
      };
      const k = String(m || "").toUpperCase();
      return map[k] || k || "-";
    }

    function setTotals(totals) {
      const aberto = Number(totals?.ABERTO || 0);
      const concl = Number(totals?.CONCLUIDO || 0);
      const cancel = Number(totals?.CANCELADO || 0);
      const geral = Number(totals?.GERAL || (aberto + concl + cancel));
      tAberto.textContent = numberToMoney(aberto);
      tConcl.textContent = numberToMoney(concl);
      tCancel.textContent = numberToMoney(cancel);
      tGeral.textContent = numberToMoney(geral);
    }

    function renderTable() {
      tbodyDev.innerHTML = "";
      hintNone.style.display = ROWS.length ? "none" : "block";

      ROWS.forEach(x => {
        const dt = fmtBRDateTime(x.date, x.time);
        const sale = x.saleNo ? `#${safeText(x.saleNo)}` : "—";
        const cust = x.customer ? safeText(x.customer) : "Consumidor Final";
        const prod = (String(x.type).toUpperCase() === "PARCIAL") ? (x.product ? safeText(x.product) : "—") : "—";
        const qty = (String(x.type).toUpperCase() === "PARCIAL") ? Number(x.qty || 1) : "—";
        const motivo = motivoLabel(x.reason);
        const valor = numberToMoney(x.amount);

        const obs = (x.note && String(x.note).trim()) ?
          `<div class="muted" style="max-width:260px;white-space:normal;margin-top:2px;">${safeText(x.note)}</div>` :
          "";

        tbodyDev.insertAdjacentHTML("beforeend", `
          <tr data-id="${Number(x.id)}">
            <td><span class="mini">${Number(x.id)}</span></td>
            <td>${safeText(dt)}</td>
            <td>${sale}</td>
            <td>${cust}</td>
            <td><span class="pill ${String(x.type).toUpperCase()==="PARCIAL"?"warn":"ok"}">${String(x.type).toUpperCase()==="PARCIAL"?"PARCIAL":"TOTAL"}</span></td>
            <td>${prod}</td>
            <td class="text-center">${qty}</td>
            <td class="text-end"><span class="money">${valor}</span></td>
            <td>${safeText(motivo)}${obs}</td>
            <td class="text-center">${badgeStatus(x.status)}</td>
            <td class="text-center">
              <button class="main-btn light-btn btn-hover btn-compact icon-btn btnEdit" type="button" title="Editar"><i class="lni lni-pencil"></i></button>
              <button class="main-btn danger-btn-outline btn-hover btn-compact icon-btn btnDel" type="button" title="Excluir"><i class="lni lni-trash-can"></i></button>
            </td>
          </tr>
        `);
      });
    }

    function renderPager() {
      if (TOTAL_PAGES <= 1) {
        pagerDev.style.display = "none";
        pagerDev.innerHTML = "";
        return;
      }
      pagerDev.style.display = "flex";

      const prevDisabled = CUR_PAGE <= 1 ? "btn-disabled" : "";
      const nextDisabled = CUR_PAGE >= TOTAL_PAGES ? "btn-disabled" : "";

      pagerDev.innerHTML = `
        <button class="main-btn light-btn btn-hover btn-sm ${prevDisabled}" id="pgPrev" type="button" title="Anterior">
          <i class="lni lni-chevron-left"></i>
        </button>
        <span class="page-text">Página ${CUR_PAGE}/${TOTAL_PAGES}</span>
        <button class="main-btn light-btn btn-hover btn-sm ${nextDisabled}" id="pgNext" type="button" title="Próxima">
          <i class="lni lni-chevron-right"></i>
        </button>
      `;

      const prevBtn = document.getElementById('pgPrev');
      const nextBtn = document.getElementById('pgNext');
      if (prevBtn && CUR_PAGE > 1) prevBtn.addEventListener('click', () => loadList(CUR_PAGE - 1));
      if (nextBtn && CUR_PAGE < TOTAL_PAGES) nextBtn.addEventListener('click', () => loadList(CUR_PAGE + 1));
    }

    async function loadList(page = 1) {
      const q = (qDev.value || "").trim();
      const st = (fStatus.value || "").trim();
      const url = `${AJAX_URL}?ajax=list&page=${encodeURIComponent(page)}&per=${PER_PAGE}&q=${encodeURIComponent(q)}&status=${encodeURIComponent(st)}`;
      const r = await fetchJSON(url);

      CUR_PAGE = Number(r.page || 1);
      TOTAL_PAGES = Number(r.total_pages || 1);
      ROWS = (r.items || []).map(x => ({
        id: Number(x.id),
        saleNo: (x.saleNo == null ? null : Number(x.saleNo)),
        customer: String(x.customer || ""),
        date: String(x.date || ""),
        time: String(x.time || ""),
        type: String(x.type || "TOTAL").toUpperCase(),
        product: String(x.product || ""),
        qty: (x.qty == null ? null : Number(x.qty)),
        amount: Number(x.amount || 0),
        reason: String(x.reason || "OUTRO").toUpperCase(),
        note: String(x.note || ""),
        status: String(x.status || "ABERTO").toUpperCase(),
      }));

      setTotals(r.totals || {});
      renderTable();
      renderPager();
    }

    function debounceLoad() {
      clearTimeout(SEARCH_TIMER);
      SEARCH_TIMER = setTimeout(() => loadList(1), 220);
    }

    // =========================
    // SAVE / EDIT / DELETE
    // =========================
    function validateForm() {
      const date = String(dData.value || "").trim();
      const time = String(dHora.value || "").trim();
      if (!date) return {
        ok: false,
        msg: "Informe a data."
      };
      if (!time) return {
        ok: false,
        msg: "Informe a hora."
      };

      const amt = moneyToNumber(dValor.value);
      if (amt <= 0) return {
        ok: false,
        msg: "Informe um valor (R$) maior que zero."
      };

      if (TYPE === "PARCIAL") {
        const prod = String(dProduto.value || "").trim();
        if (!prod) return {
          ok: false,
          msg: "Informe o produto para devolução parcial."
        };
        const q = Number(dQtd.value || 0);
        if (!q || q < 1) return {
          ok: false,
          msg: "Informe a quantidade (mín. 1)."
        };
      }
      return {
        ok: true
      };
    }

    async function saveDev() {
      const v = validateForm();
      if (!v.ok) return alert(v.msg);

      const payload = {
        csrf_token: CSRF,
        id: dId.value ? Number(dId.value) : 0,
        saleNo: String(dVendaNo.value || "").trim(),
        customer: String(dCliente.value || "").trim(),
        date: String(dData.value || "").trim(),
        time: String(dHora.value || "").trim(),
        type: TYPE,
        product: (TYPE === "PARCIAL") ? String(dProduto.value || "").trim() : "",
        qty: (TYPE === "PARCIAL") ? Number(dQtd.value || 1) : 0,
        amount: moneyToNumber(dValor.value),
        reason: String(dMotivo.value || "OUTRO"),
        note: String(dObs.value || "").trim(),
        status: String(dStatus.value || "ABERTO"),
      };

      btnSalvar.disabled = true;
      try {
        const r = await fetchJSON(`${AJAX_URL}?ajax=save`, {
          method: "POST",
          headers: {
            "Content-Type": "application/json"
          },
          body: JSON.stringify(payload)
        });
        alert(r.msg || "Devolução salva!");
        resetForm();
        await loadList(1);
      } catch (e) {
        alert(e.message || "Erro ao salvar.");
      } finally {
        btnSalvar.disabled = false;
      }
    }

    function editDev(id) {
      const x = ROWS.find(d => Number(d.id) === Number(id));
      if (!x) return;

      dId.value = String(x.id);
      dVendaNo.value = x.saleNo ? String(x.saleNo) : "";
      dCliente.value = x.customer || "";
      dData.value = x.date || nowISODate();
      dHora.value = (x.time || nowISOTime()).slice(0, 5);

      TYPE = (x.type === "PARCIAL") ? "PARCIAL" : "TOTAL";
      setType(TYPE);

      dProduto.value = x.product || "";
      dQtd.value = x.qty || 1;
      dValor.value = Number(x.amount || 0).toFixed(2).replace(".", ",");
      dMotivo.value = x.reason || "OUTRO";
      dObs.value = x.note || "";
      dStatus.value = x.status || "ABERTO";

      clearSaleSelection();
      setFormMode("EDIT");
      window.scrollTo({
        top: 0,
        behavior: "smooth"
      });
    }

    async function deleteDev(id) {
      if (!confirm(`Excluir devolução #${id}? (Se estava CONCLUÍDO, o estoque será desfeito.)`)) return;
      try {
        await fetchJSON(`${AJAX_URL}?ajax=del`, {
          method: "POST",
          headers: {
            "Content-Type": "application/json"
          },
          body: JSON.stringify({
            csrf_token: CSRF,
            id: Number(id)
          })
        });
        resetForm();
        await loadList(CUR_PAGE);
      } catch (e) {
        alert(e.message || "Erro ao excluir.");
      }
    }

    // =========================
    // EXPORTS
    // =========================
    function exportUrl(type) {
      const q = (qDev.value || "").trim();
      const st = (fStatus.value || "").trim();
      const u = new URL(window.location.href);
      u.searchParams.set('export', type);
      u.searchParams.set('q', q);
      u.searchParams.set('status', st);
      // remove ajax params se tiver
      u.searchParams.delete('ajax');
      u.searchParams.delete('page');
      u.searchParams.delete('per');
      return u.toString();
    }

    btnExcel.addEventListener('click', () => {
      window.location.href = exportUrl('excel');
    });
    btnPdf.addEventListener('click', () => {
      window.open(exportUrl('pdf'), '_blank');
    });

    // =========================
    // EVENTS
    // =========================
    btnNova.addEventListener("click", resetForm);
    btnSalvar.addEventListener("click", saveDev);
    btnLimpar.addEventListener("click", resetForm);

    chipTotal.addEventListener("click", () => setType("TOTAL"));
    chipParcial.addEventListener("click", () => setType("PARCIAL"));

    qDev.addEventListener("input", debounceLoad);
    fStatus.addEventListener("change", () => loadList(1));

    qGlobal.addEventListener("input", () => {
      qDev.value = qGlobal.value;
      debounceLoad();
    });

    tbodyDev.addEventListener("click", (e) => {
      const tr = e.target.closest("tr");
      if (!tr) return;
      const id = Number(tr.getAttribute("data-id") || 0);
      if (!id) return;
      if (e.target.closest(".btnEdit")) return editDev(id);
      if (e.target.closest(".btnDel")) return deleteDev(id);
    });

    document.addEventListener("keydown", (e) => {
      if (e.key === "F2") {
        e.preventDefault();
        saveDev();
      }
      if (e.key === "F4") {
        e.preventDefault();
        qDev.focus();
      }
      if (e.key === "Escape") {
        hideSaleSuggest();
        hideProdSuggest();
      }
    });

    async function init() {
      resetForm();
      await loadList(1);
    }
    init();
  </script>
</body>

</html>