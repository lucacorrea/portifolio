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
   FALLBACKS
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
    return (float)$s;
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

function dtbr_dt(string $ymd, string $his): string
{
  $ymd = trim($ymd);
  $his = trim($his);
  if ($ymd === '') return '';
  $ts = strtotime($ymd . ' ' . ($his ?: '00:00:00'));
  return $ts ? date('d/m/Y H:i', $ts) : ($ymd . ' ' . $his);
}

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

function normalize_search(string $txt): string
{
  if (function_exists('mb_strtolower')) {
    $txt = mb_strtolower(trim($txt), 'UTF-8');
  } else {
    $txt = strtolower(trim($txt));
  }

  $map = [
    'á' => 'a',
    'à' => 'a',
    'ã' => 'a',
    'â' => 'a',
    'ä' => 'a',
    'é' => 'e',
    'è' => 'e',
    'ê' => 'e',
    'ë' => 'e',
    'í' => 'i',
    'ì' => 'i',
    'î' => 'i',
    'ï' => 'i',
    'ó' => 'o',
    'ò' => 'o',
    'õ' => 'o',
    'ô' => 'o',
    'ö' => 'o',
    'ú' => 'u',
    'ù' => 'u',
    'û' => 'u',
    'ü' => 'u',
    'ç' => 'c'
  ];
  $txt = strtr($txt, $map);
  $txt = preg_replace('/\s+/', ' ', $txt) ?? $txt;
  return $txt;
}

function extract_product_code(string $product): string
{
  $p = trim(str_replace(["\r", "\n", "\t"], ' ', $product));
  if ($p === '') return '';

  if (strpos($p, ' - ') !== false) {
    $parts = explode(' - ', $p, 2);
    $code = trim((string)($parts[0] ?? ''));
    if ($code !== '') return $code;
  }

  if (preg_match('/^([A-Za-z0-9._-]+)/', $p, $m)) {
    return trim((string)$m[1]);
  }

  return '';
}

/* =========================================================
   RESOLUÇÃO DO ITEM PARCIAL
========================================================= */
function resolve_partial_item(PDO $pdo, ?int $saleNo, string $productText): ?array
{
  $productText = trim($productText);
  if ($productText === '') return null;

  $needle = normalize_search($productText);
  $codeFromText = extract_product_code($productText);

  if ($saleNo !== null && $saleNo > 0 && table_exists($pdo, 'venda_itens')) {
    if ($codeFromText !== '') {
      $st = $pdo->prepare("
        SELECT codigo, nome, unidade
        FROM venda_itens
        WHERE venda_id = :venda AND codigo = :codigo
        ORDER BY id ASC
        LIMIT 1
      ");
      $st->execute([
        ':venda' => $saleNo,
        ':codigo' => $codeFromText
      ]);
      $r = $st->fetch(PDO::FETCH_ASSOC);
      if ($r) {
        return [
          'codigo' => trim((string)($r['codigo'] ?? '')),
          'nome' => trim((string)($r['nome'] ?? '')),
          'unidade' => trim((string)($r['unidade'] ?? '')),
        ];
      }
    }

    $st = $pdo->prepare("
      SELECT codigo, nome, unidade
      FROM venda_itens
      WHERE venda_id = :venda
      ORDER BY id ASC
    ");
    $st->execute([':venda' => $saleNo]);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

    foreach ($rows as $r) {
      $codigo = trim((string)($r['codigo'] ?? ''));
      $nome   = trim((string)($r['nome'] ?? ''));
      $full   = trim($codigo . ' - ' . $nome);

      $cand1 = normalize_search($codigo);
      $cand2 = normalize_search($nome);
      $cand3 = normalize_search($full);

      if (
        $cand1 === $needle ||
        $cand2 === $needle ||
        $cand3 === $needle ||
        str_contains($cand2, $needle) ||
        str_contains($cand3, $needle)
      ) {
        return [
          'codigo' => $codigo,
          'nome' => $nome,
          'unidade' => trim((string)($r['unidade'] ?? '')),
        ];
      }
    }
  }

  if (table_exists($pdo, 'produtos')) {
    if ($codeFromText !== '') {
      $st = $pdo->prepare("
        SELECT codigo, nome, unidade
        FROM produtos
        WHERE codigo = ?
        LIMIT 1
      ");
      $st->execute([$codeFromText]);
      $r = $st->fetch(PDO::FETCH_ASSOC);
      if ($r) {
        return [
          'codigo' => trim((string)($r['codigo'] ?? '')),
          'nome' => trim((string)($r['nome'] ?? '')),
          'unidade' => trim((string)($r['unidade'] ?? '')),
        ];
      }
    }

    $st = $pdo->prepare("
      SELECT codigo, nome, unidade
      FROM produtos
      WHERE nome LIKE :q OR codigo LIKE :q2
      ORDER BY id ASC
      LIMIT 30
    ");
    $st->execute([
      ':q' => '%' . $productText . '%',
      ':q2' => '%' . $productText . '%'
    ]);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

    foreach ($rows as $r) {
      $codigo = trim((string)($r['codigo'] ?? ''));
      $nome   = trim((string)($r['nome'] ?? ''));
      $full   = trim($codigo . ' - ' . $nome);

      $cand1 = normalize_search($codigo);
      $cand2 = normalize_search($nome);
      $cand3 = normalize_search($full);

      if (
        $cand1 === $needle ||
        $cand2 === $needle ||
        $cand3 === $needle ||
        str_contains($cand2, $needle) ||
        str_contains($cand3, $needle)
      ) {
        return [
          'codigo' => $codigo,
          'nome' => $nome,
          'unidade' => trim((string)($r['unidade'] ?? '')),
        ];
      }
    }
  }

  return null;
}

function format_item_label(string $codigo, string $nome, int $qtd = 0): string
{
  $base = trim($codigo . ' - ' . $nome);
  if ($base === '-' || $base === '') {
    $base = $nome !== '' ? $nome : ($codigo !== '' ? $codigo : 'Item');
  }
  if ($qtd > 0) {
    $base .= ' (' . $qtd . ')';
  }
  return $base;
}

/* =========================================================
   ESTOQUE
========================================================= */
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

  $item = resolve_partial_item($pdo, $saleNo > 0 ? $saleNo : null, $product);
  if (!$item || trim((string)($item['codigo'] ?? '')) === '') return [];

  $code = trim((string)$item['codigo']);
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
   PRODUTOS CACHE
========================================================= */
$PRODUTOS_CACHE = [];
try {
  if (table_exists($pdo, 'produtos')) {
    $stP = $pdo->query("
      SELECT id, codigo, nome, status, unidade
      FROM produtos
      WHERE (status IS NULL OR status = '' OR UPPER(TRIM(status))='ATIVO')
      ORDER BY nome ASC
      LIMIT 6000
    ");
    $rowsP = $stP->fetchAll(PDO::FETCH_ASSOC) ?: [];

    foreach ($rowsP as $r) {
      $PRODUTOS_CACHE[] = [
        'id' => (int)($r['id'] ?? 0),
        'code' => (string)($r['codigo'] ?? ''),
        'name' => (string)($r['nome'] ?? ''),
        'unit' => (string)($r['unidade'] ?? ''),
      ];
    }
  }
} catch (Throwable $e) {
  $PRODUTOS_CACHE = [];
}

/* =========================================================
   WHERE / BUSCA AJAX
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
    $params[':q_like']   = '%' . $q . '%';
    $params[':q_like2']  = '%' . $q . '%';
    $params[':q_like3']  = '%' . $q . '%';
    $params[':q_like4']  = '%' . $q . '%';
    $params[':q_like5']  = '%' . $q . '%';
    $params[':q_like6']  = '%' . $q . '%';
    $params[':q_like7']  = '%' . $q . '%';
    $params[':q_like8']  = '%' . $q . '%';
    $params[':q_like9']  = '%' . $q . '%';
    $params[':q_like10'] = '%' . $q . '%';
    $params[':q_like11'] = '%' . $q . '%';

    $where[] = "(
      CAST(d.id AS CHAR) LIKE :q_like
      OR CAST(COALESCE(d.venda_no,'') AS CHAR) LIKE :q_like2
      OR COALESCE(d.cliente,'') LIKE :q_like3
      OR COALESCE(d.produto,'') LIKE :q_like4
      OR COALESCE(d.motivo,'') LIKE :q_like5
      OR COALESCE(d.obs,'') LIKE :q_like6
      OR COALESCE(d.tipo,'') LIKE :q_like7
      OR COALESCE(d.status,'') LIKE :q_like8
      OR CAST(COALESCE(d.valor,0) AS CHAR) LIKE :q_like9
      OR CAST(COALESCE(d.data,'') AS CHAR) LIKE :q_like10
      OR CAST(COALESCE(d.hora,'') AS CHAR) LIKE :q_like11
      OR EXISTS (
        SELECT 1
        FROM venda_itens vi
        WHERE vi.venda_id = d.venda_no
          AND (
            vi.codigo LIKE :q_like
            OR vi.nome LIKE :q_like2
            OR CAST(vi.qtd AS CHAR) LIKE :q_like3
            OR COALESCE(vi.unidade,'') LIKE :q_like4
          )
      )
    )";
  }

  $sql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';
  return [$sql, $params];
}

/* =========================================================
   ITENS DAS DEVOLUÇÕES
========================================================= */
function fetch_items_map_for_devolucoes(PDO $pdo, array $rows): array
{
  $map = [];
  if (!$rows) return $map;

  $saleNos = [];

  foreach ($rows as $r) {
    $id   = (int)($r['id'] ?? 0);
    $tipo = strtoupper((string)($r['tipo'] ?? 'TOTAL'));

    if ($tipo === 'PARCIAL') {
      $saleNo  = (int)($r['venda_no'] ?? 0);
      $qtd     = (int)($r['qtd'] ?? 0);
      $prodTxt = (string)($r['produto'] ?? '');

      $item = resolve_partial_item($pdo, $saleNo > 0 ? $saleNo : null, $prodTxt);

      if ($item) {
        $codigo = trim((string)($item['codigo'] ?? ''));
        $nome   = trim((string)($item['nome'] ?? ''));
        $map[$id] = [format_item_label($codigo, $nome, $qtd)];
      } else {
        $fallback = trim($prodTxt) !== '' ? trim($prodTxt) : '—';
        if ($qtd > 0 && $fallback !== '—') $fallback .= ' (' . $qtd . ')';
        $map[$id] = [$fallback];
      }
    } else {
      $saleNo = (int)($r['venda_no'] ?? 0);
      if ($saleNo > 0) $saleNos[$saleNo] = $saleNo;
    }
  }

  if ($saleNos && table_exists($pdo, 'venda_itens')) {
    $vals = array_values($saleNos);
    $in = implode(',', array_fill(0, count($vals), '?'));

    $st = $pdo->prepare("
      SELECT venda_id, codigo, nome, qtd
      FROM venda_itens
      WHERE venda_id IN ($in)
      ORDER BY venda_id ASC, id ASC
    ");
    $st->execute($vals);
    $vit = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $saleItems = [];
    foreach ($vit as $it) {
      $vendaId = (int)($it['venda_id'] ?? 0);
      $codigo = trim((string)($it['codigo'] ?? ''));
      $nome   = trim((string)($it['nome'] ?? ''));
      $qtd    = (int)($it['qtd'] ?? 0);

      $txt = format_item_label($codigo, $nome, $qtd);

      if (!isset($saleItems[$vendaId])) $saleItems[$vendaId] = [];
      $saleItems[$vendaId][] = $txt;
    }

    foreach ($rows as $r) {
      $id = (int)($r['id'] ?? 0);
      $tipo = strtoupper((string)($r['tipo'] ?? 'TOTAL'));
      if ($tipo !== 'TOTAL') continue;

      $saleNo = (int)($r['venda_no'] ?? 0);
      $map[$id] = $saleItems[$saleNo] ?? ['—'];
    }
  }

  foreach ($rows as $r) {
    $id = (int)($r['id'] ?? 0);
    if (!isset($map[$id])) $map[$id] = ['—'];
  }

  return $map;
}

function join_items_for_table(array $items, int $max = 2): string
{
  if (!$items) return '—';
  $show = array_slice($items, 0, $max);
  $txt = implode(' | ', $show);
  $extra = count($items) - count($show);
  if ($extra > 0) $txt .= ' | +' . $extra . ' item(ns)';
  return $txt;
}

function join_items_for_excel(array $items): string
{
  if (!$items) return '—';
  return implode(' | ', $items);
}

/* =========================================================
   EXPORT EXCEL
========================================================= */
$export = strtolower(trim((string)($_GET['export'] ?? '')));
if ($export === 'excel') {
  $q = (string)($_GET['q'] ?? '');
  $status = (string)($_GET['status'] ?? '');

  if (!table_exists($pdo, 'devolucoes')) {
    http_response_code(500);
    echo "ERRO: tabela devolucoes não encontrada.";
    exit;
  }

  [$w, $p] = build_where($q, $status);

  $st = $pdo->prepare("
    SELECT d.id, d.venda_no, d.cliente, d.data, d.hora, d.tipo, d.produto, d.qtd, d.valor, d.motivo, d.obs, d.status
    FROM devolucoes d
    $w
    ORDER BY d.data DESC, d.hora DESC, d.id DESC
  ");
  $st->execute($p);
  $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
  $itemsMap = fetch_items_map_for_devolucoes($pdo, $rows);

  $sumValor = 0.0;
  foreach ($rows as $r) {
    $sumValor += (float)($r['valor'] ?? 0);
  }

  $agora = date('d/m/Y H:i');
  $busca = trim($q) !== '' ? $q : '—';
  $stat = strtoupper($status ?: 'TODOS');
  $fname = 'devolucoes_' . date('Y-m-d_His') . '.xls';

  if (function_exists('ob_get_length') && ob_get_length()) {
    @ob_clean();
  }

  header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
  header('Content-Disposition: attachment; filename="' . $fname . '"');
  header('Pragma: no-cache');
  header('Expires: 0');

  echo "\xEF\xBB\xBF";
?>
  <html xmlns:o="urn:schemas-microsoft-com:office:office"
    xmlns:x="urn:schemas-microsoft-com:office:excel"
    xmlns="http://www.w3.org/TR/REC-html40">

  <head>
    <meta charset="UTF-8">

    <!--[if gte mso 9]>
  <xml>
      <x:ExcelWorkbook>
          <x:ExcelWorksheets>
              <x:ExcelWorksheet>
                  <x:Name>Devoluções</x:Name>
                  <x:WorksheetOptions>
                      <x:Selected/>
                      <x:DisplayGridlines/>
                      <x:FitToPage/>
                      <x:DoNotDisplayGridlines/>
                      <x:Print>
                          <x:ValidPrinterInfo/>
                          <x:PaperSizeIndex>9</x:PaperSizeIndex>
                          <x:Scale>100</x:Scale>
                          <x:FitWidth>1</x:FitWidth>
                          <x:FitHeight>999</x:FitHeight>
                      </x:Print>
                      <x:PageSetup>
                          <x:Layout x:Orientation="Landscape"/>
                      </x:PageSetup>
                      <x:CenterHorizontal/>
                  </x:WorksheetOptions>
              </x:ExcelWorksheet>
          </x:ExcelWorksheets>
      </x:ExcelWorkbook>
  </xml>
  <![endif]-->

    <style>
      @page {
        size: A4 landscape;
        margin: 0.5cm;
      }

      html,
      body {
        margin: 0;
        padding: 0;
        width: 100%;
        font-family: Arial, Helvetica, sans-serif;
        font-size: 11pt;
        background: #fff;
      }

      .page-wrap {
        width: 100%;
        margin: 0 auto;
      }

      table {
        border-collapse: collapse;
        table-layout: fixed;
        width: 100%;
      }

      .tbl-meta,
      .tbl-main {
        width: 100%;
        border: 1px solid #000;
      }

      .tbl-meta td,
      .tbl-main th,
      .tbl-main td {
        border: 1px solid #000;
        padding: 6px;
        font-size: 11pt;
        vertical-align: middle;
      }

      .title {
        font-size: 16pt;
        font-weight: 700;
        text-align: center;
        background: #dbeafe;
      }

      .head {
        background: #dbeafe;
        font-weight: 700;
        text-align: center;
      }

      .center {
        text-align: center;
      }

      .left {
        text-align: left;
      }

      .foot {
        font-weight: 700;
        background: #eef2ff;
      }

      .w-id {
        width: 6%;
      }

      .w-data {
        width: 12%;
      }

      .w-venda {
        width: 7%;
      }

      .w-cli {
        width: 15%;
      }

      .w-tipo {
        width: 8%;
      }

      .w-itens {
        width: 24%;
      }

      .w-qtd {
        width: 5%;
      }

      .w-num {
        width: 8%;
      }

      .w-mot {
        width: 8%;
      }

      .w-st {
        width: 7%;
      }
    </style>
  </head>

  <body>
    <div class="page-wrap">
      <table class="tbl-meta">
        <tr>
          <td colspan="10" class="title">PAINEL DA DISTRIBUIDORA - DEVOLUÇÕES</td>
        </tr>
        <tr>
          <td colspan="10">Gerado em: <?= e($agora) ?></td>
        </tr>
        <tr>
          <td colspan="10">Status: <?= e($stat) ?> | Busca: <?= e($busca) ?></td>
        </tr>
      </table>

      <table class="tbl-main" style="margin-top:6px;">
        <thead>
          <tr>
            <th class="head w-id">ID</th>
            <th class="head w-data">Data/Hora</th>
            <th class="head w-venda">Venda</th>
            <th class="head w-cli">Cliente</th>
            <th class="head w-tipo">Tipo</th>
            <th class="head w-itens">Itens</th>
            <th class="head w-qtd">Qtd</th>
            <th class="head w-num">Valor</th>
            <th class="head w-mot">Motivo</th>
            <th class="head w-st">Status</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $r): ?>
            <?php
            $id = (int)$r['id'];
            $tipo = strtoupper((string)($r['tipo'] ?? 'TOTAL'));
            $itensTxt = join_items_for_excel($itemsMap[$id] ?? []);
            ?>
            <tr>
              <td class="center"><?= $id ?></td>
              <td class="center"><?= e(dtbr_dt((string)$r['data'], (string)$r['hora'])) ?></td>
              <td class="center"><?= ($r['venda_no'] !== null ? '#' . (int)$r['venda_no'] : '—') ?></td>
              <td class="left"><?= e((string)($r['cliente'] ?: 'Consumidor Final')) ?></td>
              <td class="center"><?= e($tipo) ?></td>
              <td class="left"><?= e($itensTxt) ?></td>
              <td class="center"><?= ($tipo === 'PARCIAL' ? (int)($r['qtd'] ?? 0) : '—') ?></td>
              <td class="center"><?= e(number_format((float)$r['valor'], 2, ',', '.')) ?></td>
              <td class="left"><?= e((string)($r['motivo'] ?? '')) ?></td>
              <td class="center"><?= e((string)($r['status'] ?? 'ABERTO')) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr class="foot">
            <td colspan="7" class="center">Totais</td>
            <td class="center"><?= e(number_format($sumValor, 2, ',', '.')) ?></td>
            <td colspan="2"></td>
          </tr>
        </tfoot>
      </table>
    </div>
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

    if ($ajax === 'list') {
      if (!table_exists($pdo, 'devolucoes')) {
        json_out([
          'ok' => true,
          'items' => [],
          'page' => 1,
          'per' => 10,
          'total_rows' => 0,
          'total_pages' => 1,
          'showing_from' => 0,
          'showing_to' => 0,
          'totals' => []
        ]);
      }

      $page = to_int($_GET['page'] ?? 1, 1, 999999);
      $per  = to_int($_GET['per'] ?? 10, 1, 50);
      if ($per < 1) $per = 10;
      if ($per > 50) $per = 50;

      $q = (string)($_GET['q'] ?? '');
      $status = (string)($_GET['status'] ?? '');

      [$w, $p] = build_where($q, $status);

      $stC = $pdo->prepare("SELECT COUNT(*) c FROM devolucoes d $w");
      $stC->execute($p);
      $totalRows = (int)($stC->fetchColumn() ?: 0);
      $totalPages = max(1, (int)ceil($totalRows / $per));
      if ($page > $totalPages) $page = $totalPages;
      $off = ($page - 1) * $per;

      $sql = "
        SELECT d.id, d.venda_no, d.cliente, d.data, d.hora, d.tipo, d.produto, d.qtd, d.valor, d.motivo, d.obs, d.status, d.created_at
        FROM devolucoes d
        $w
        ORDER BY d.data DESC, d.hora DESC, d.id DESC
        LIMIT :lim OFFSET :off
      ";
      $st = $pdo->prepare($sql);
      foreach ($p as $k => $v) {
        $st->bindValue($k, $v);
      }
      $st->bindValue(':lim', (int)$per, PDO::PARAM_INT);
      $st->bindValue(':off', (int)$off, PDO::PARAM_INT);
      $st->execute();

      $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
      $itemsMap = fetch_items_map_for_devolucoes($pdo, $rows);

      $items = [];
      foreach ($rows as $r) {
        $id = (int)($r['id'] ?? 0);
        $items[] = [
          'id' => $id,
          'saleNo' => ($r['venda_no'] !== null ? (int)$r['venda_no'] : null),
          'customer' => (string)($r['cliente'] ?? ''),
          'date' => (string)($r['data'] ?? ''),
          'time' => (string)($r['hora'] ?? ''),
          'type' => (string)($r['tipo'] ?? 'TOTAL'),
          'product' => (string)($r['produto'] ?? ''),
          'qty' => ($r['qtd'] !== null ? (int)$r['qtd'] : null),
          'amount' => (float)($r['valor'] ?? 0),
          'reason' => (string)($r['motivo'] ?? 'OUTRO'),
          'note' => (string)($r['obs'] ?? ''),
          'status' => (string)($r['status'] ?? 'ABERTO'),
          'created_at' => (string)($r['created_at'] ?? ''),
          'items' => $itemsMap[$id] ?? ['—'],
          'items_text' => join_items_for_table($itemsMap[$id] ?? []),
        ];
      }

      [$w2, $p2] = build_where($q, '');
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

      $showingFrom = $totalRows > 0 ? ($off + 1) : 0;
      $showingTo = min($off + $per, $totalRows);

      json_out([
        'ok' => true,
        'items' => $items,
        'page' => $page,
        'per' => $per,
        'total_rows' => $totalRows,
        'total_pages' => $totalPages,
        'showing_from' => $showingFrom,
        'showing_to' => $showingTo,
        'totals' => $tot,
      ]);
    }

    if ($ajax === 'save') {
      $payload = json_input();
      $csrf = (string)($payload['csrf_token'] ?? '');
      if (!csrf_validate_token($csrf)) {
        json_out(['ok' => false, 'msg' => 'CSRF inválido. Recarregue a página.'], 403);
      }

      $id = to_int($payload['id'] ?? 0);
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

        $resolved = resolve_partial_item($pdo, $saleNo, $product);
        if (!$resolved) {
          json_out(['ok' => false, 'msg' => 'Não foi possível localizar o item correto da devolução parcial. Selecione o produto da lista da venda ou informe CODIGO - NOME.'], 400);
        }

        $product = trim((string)$resolved['codigo']) . ' - ' . trim((string)$resolved['nome']);
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
          'tipo' => (string)($old['tipo'] ?? 'TOTAL'),
          'venda_no' => (int)($old['venda_no'] ?? 0),
          'produto' => (string)($old['produto'] ?? ''),
          'qtd' => (int)($old['qtd'] ?? 0),
        ]) : [];

        $newEffect = devolucao_effect($pdo, [
          'status' => $status,
          'type' => $type,
          'saleNo' => (int)($saleNo ?? 0),
          'product' => $product,
          'qty' => ($type === 'PARCIAL' ? $qty : 0),
        ]);

        $delta = $newEffect;
        $negOld = [];
        foreach ($oldEffect as $k => $v) {
          $negOld[$k] = -1 * (int)$v;
        }
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
            ':cliente' => ($customer !== '' ? $customer : null),
            ':data' => $date,
            ':hora' => $time,
            ':tipo' => $type,
            ':produto' => ($type === 'PARCIAL' ? $product : null),
            ':qtd' => ($type === 'PARCIAL' ? $qty : null),
            ':valor' => $amount,
            ':motivo' => $reason,
            ':obs' => ($note !== '' ? $note : null),
            ':status' => $status,
            ':id' => $id,
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
            ':cliente' => ($customer !== '' ? $customer : null),
            ':data' => $date,
            ':hora' => $time,
            ':tipo' => $type,
            ':produto' => ($type === 'PARCIAL' ? $product : null),
            ':qtd' => ($type === 'PARCIAL' ? $qty : null),
            ':valor' => $amount,
            ':motivo' => $reason,
            ':obs' => ($note !== '' ? $note : null),
            ':status' => $status,
          ]);
          $id = (int)$pdo->lastInsertId();
        }

        $pdo->commit();
      } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
      }

      $msg = 'Devolução salva com sucesso!';
      if ($missing) {
        $msg .= ' (Atenção: não encontrei no estoque: ' . implode(', ', $missing) . ')';
      }
      json_out(['ok' => true, 'msg' => $msg]);
    }

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
          'tipo' => (string)($old['tipo'] ?? 'TOTAL'),
          'venda_no' => (int)($old['venda_no'] ?? 0),
          'produto' => (string)($old['produto'] ?? ''),
          'qtd' => (int)($old['qtd'] ?? 0),
        ]);

        if ($oldEffect && table_exists($pdo, 'produtos')) {
          $neg = [];
          foreach ($oldEffect as $k => $v) {
            $neg[$k] = -1 * (int)$v;
          }
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
      if ($missing) {
        $msg .= ' (Atenção: não encontrei no estoque: ' . implode(', ', $missing) . ')';
      }
      json_out(['ok' => true, 'msg' => $msg]);
    }

    json_out(['ok' => false, 'msg' => 'Ação ajax inválida.'], 400);
  } catch (Throwable $e) {
    json_out(['ok' => false, 'msg' => $e->getMessage()], 500);
  }
}

/* =========================================================
   HTML
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
  <link rel="stylesheet" href="assets/css/lineicons.css" type="text/css" />
  <link rel="stylesheet" href="assets/css/materialdesignicons.min.css" type="text/css" />
  <link rel="stylesheet" href="assets/css/main.css" />

  <style>
    .main-btn.btn-compact {
      height: 38px !important;
      padding: 8px 14px !important;
      font-size: 13px !important;
      line-height: 1 !important;
    }

    .icon-btn {
      height: 34px !important;
      width: 42px !important;
      padding: 0 !important;
      display: inline-flex !important;
      align-items: center !important;
      justify-content: center !important;
    }

    .form-control.compact,
    .form-select.compact {
      height: 38px;
      padding: 8px 12px;
      font-size: 13px;
    }

    .cardx {
      border: 1px solid rgba(148, 163, 184, .28);
      border-radius: 16px;
      background: #fff;
      overflow: hidden;
    }

    .cardx .head {
      padding: 12px 14px;
      border-bottom: 1px solid rgba(148, 163, 184, .22);
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 10px;
      flex-wrap: wrap;
    }

    .cardx .body {
      padding: 14px;
    }

    .muted {
      font-size: 12px;
      color: #64748b;
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
      background: rgba(248, 250, 252, .7);
    }

    .pill.ok {
      border-color: rgba(34, 197, 94, .25);
      background: rgba(240, 253, 244, .9);
      color: #166534;
    }

    .pill.warn {
      border-color: rgba(245, 158, 11, .28);
      background: rgba(255, 251, 235, .9);
      color: #92400e;
    }

    .chip-toggle {
      display: flex;
      gap: 10px;
      flex-wrap: wrap;
    }

    .chip {
      border: 1px solid rgba(148, 163, 184, .35);
      border-radius: 999px;
      padding: 8px 12px;
      cursor: pointer;
      font-weight: 900;
      font-size: 12px;
      user-select: none;
      background: #fff;
    }

    .chip.active {
      background: rgba(239, 246, 255, .75);
      border-color: rgba(37, 99, 235, .55);
      outline: 2px solid rgba(37, 99, 235, .25);
    }

    .reason-box {
      border: 1px solid rgba(148, 163, 184, .25);
      border-radius: 14px;
      padding: 10px 12px;
      background: rgba(248, 250, 252, .7);
    }

    .table td,
    .table th {
      vertical-align: middle;
    }

    .table-responsive {
      -webkit-overflow-scrolling: touch;
    }

    #tbDev {
      width: 100%;
      min-width: 1120px;
    }

    #tbDev th {
      font-weight: 900;
      color: #0f172a;
      text-align: center;
      white-space: nowrap;
    }

    #tbDev td {
      font-weight: 600;
      color: #0f172a;
      vertical-align: middle;
    }

    .money {
      font-weight: 1000;
      color: #0b5ed7;
    }

    .mini {
      font-size: 12px;
      color: #475569;
      font-weight: 800;
    }

    .badge-soft {
      font-weight: 1000;
      border-radius: 999px;
      padding: 6px 10px;
      font-size: 11px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
    }

    .b-open {
      background: rgba(255, 251, 235, .95);
      color: #92400e;
      border: 1px solid rgba(245, 158, 11, .25);
    }

    .b-done {
      background: rgba(240, 253, 244, .95);
      color: #166534;
      border: 1px solid rgba(34, 197, 94, .25);
    }

    .b-cancel {
      background: rgba(254, 242, 242, .95);
      color: #991b1b;
      border: 1px solid rgba(239, 68, 68, .25);
    }

    .toolbar {
      display: flex;
      gap: 10px;
      flex-wrap: wrap;
      align-items: center;
      width: 100%;
    }

    .toolbar .grow {
      flex: 1 1 260px;
      min-width: 240px;
    }

    .toolbar .w180 {
      min-width: 180px;
    }

    .pager-box {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 12px;
      margin-top: 12px;
      flex-wrap: wrap;
    }

    .pager-left {
      font-size: 12px;
      color: #64748b;
      font-weight: 900;
    }

    .pager-right {
      display: flex;
      align-items: center;
      gap: 8px;
      flex-wrap: wrap;
    }

    .page-btn {
      min-width: 42px;
      height: 38px;
      border: 1px solid rgba(148, 163, 184, .35);
      border-radius: 10px;
      background: #fff;
      font-weight: 900;
      color: #334155;
      cursor: pointer;
    }

    .page-btn.active {
      background: #365CF5;
      color: #fff;
      border-color: #365CF5;
    }

    .page-btn[disabled] {
      opacity: .45;
      cursor: not-allowed;
    }

    .page-text {
      font-size: 12px;
      color: #64748b;
      font-weight: 900;
      padding: 0 6px;
    }

    .search-wrap {
      position: relative;
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
      display: none;
    }

    .suggest .it {
      padding: 10px 12px;
      cursor: pointer;
      display: flex;
      justify-content: space-between;
      gap: 10px;
    }

    .suggest .it:hover {
      background: rgba(241, 245, 249, .9);
    }

    .suggest .t {
      font-weight: 900;
      font-size: 12px;
      color: #0f172a;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }

    .suggest .s {
      font-size: 12px;
      color: #64748b;
      white-space: nowrap;
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
      border-bottom: none;
    }

    .sale-row .left {
      min-width: 0;
    }

    .sale-row .left .nm {
      font-weight: 900;
      color: #0f172a;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
      max-width: 320px;
    }

    .sale-row .left .cd {
      color: #64748b;
      font-size: 12px;
    }

    .sale-row .right {
      white-space: nowrap;
      text-align: right;
    }

    .sale-mini {
      font-size: 12px;
      color: #64748b;
      margin-top: 6px;
      display: flex;
      justify-content: space-between;
      gap: 10px;
    }

    .detail-grid {
      display: grid;
      grid-template-columns: repeat(2, minmax(0, 1fr));
      gap: 12px;
    }

    .detail-box {
      border: 1px solid rgba(148, 163, 184, .22);
      border-radius: 14px;
      background: #fff;
      padding: 12px;
    }

    .detail-row {
      display: flex;
      justify-content: space-between;
      gap: 10px;
      padding: 8px 0;
      border-bottom: 1px dashed rgba(148, 163, 184, .22);
      font-size: 13px;
    }

    .detail-row:last-child {
      border-bottom: none;
    }

    .detail-row span:first-child {
      color: #64748b;
      font-weight: 800;
    }

    .detail-row span:last-child {
      color: #0f172a;
      font-weight: 900;
      text-align: right;
    }

    .items-preview-box {
      border: 1px solid rgba(148, 163, 184, .22);
      border-radius: 14px;
      background: rgba(248, 250, 252, .7);
      padding: 12px;
      white-space: normal;
    }

    .items-preview-box .it {
      padding: 6px 0;
      border-bottom: 1px dashed rgba(148, 163, 184, .22);
    }

    .items-preview-box .it:last-child {
      border-bottom: none;
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

    .brand-name {
      display: block;
      font-size: 18px;
      line-height: 1.2;
      font-weight: 600;
      color: #1e2a78;
      white-space: normal;
      word-break: break-word;
    }

    @media(max-width:767.98px) {
      .detail-grid {
        grid-template-columns: 1fr;
      }
    }
  </style>
</head>

<body>
  <div id="preloader">
    <div class="spinner"></div>
  </div>

  <aside class="sidebar-nav-wrapper">
    <div class="navbar-logo">
      <a href="dashboard.php" class="brand-vertical">
        <span class="brand-name">DISTRIBUIDORA<br>PLHB</span>
      </a>
    </div>

    <nav class="sidebar-nav">
      <ul>
        <li class="nav-item">
          <a href="dashboard.php">
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

        <li class="nav-item nav-item-has-children active">
          <a href="#0" class="collapsed" data-bs-toggle="collapse" data-bs-target="#ddmenu_operacoes" aria-controls="ddmenu_operacoes" aria-expanded="false">
            <span class="icon"><i class="lni lni-layers"></i></span>
            <span class="text">Operações</span>
          </a>
          <ul id="ddmenu_operacoes" class="collapse dropdown-nav show">
            <li><a href="vendidos.php">Vendidos</a></li>
            <li><a href="fiados.php">À Prazo</a></li>
            <li><a href="devolucoes.php" class="active">Devoluções</a></li>
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

        <span class="divider">
          <hr />
        </span>

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
                <button id="menu-toggle" class="main-btn primary-btn btn-hover" type="button">
                  <i class="lni lni-chevron-left me-2"></i> Menu
                </button>
              </div>
              <div class="header-search d-none d-md-flex" style="display: none !important;">
                <form action="#">
                  <input type="text" placeholder="Buscar devolução..." id="qGlobal" />
                  <button type="submit" onclick="return false"><i class="lni lni-search-alt"></i></button>
                </form>
              </div>
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

    <section class="section">
      <div class="container-fluid">
        <div class="title-wrapper pt-30">
          <div class="row align-items-center">
            <div class="col-md-8">
              <div class="title">
                <h2>Devoluções</h2>
                <div class="muted">Registro e controle • <b>F2</b> salvar | <b>F4</b> focar na busca</div>
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
                  <input class="form-control compact" id="dCliente" placeholder="Nome (opcional)" />
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
                  <input class="form-control compact grow" id="qDev" placeholder="Buscar: id, venda, cliente, produto, itens, tipo, status..." />
                  <select class="form-select compact w180" id="fStatus">
                    <option value="">Todos</option>
                    <option value="ABERTO">Em aberto</option>
                    <option value="CONCLUIDO">Concluído</option>
                    <option value="CANCELADO">Cancelado</option>
                  </select>

                  <button class="main-btn light-btn btn-hover btn-compact" id="btnExcel" type="button">
                    <i class="lni lni-download me-1"></i> Excel
                  </button>
                </div>
              </div>

              <div class="body">
                <div class="table-responsive">
                  <table class="table" id="tbDev">
                    <thead>
                      <tr>
                        <th style="min-width:70px;">ID</th>
                        <th style="min-width:160px;">Data/Hora</th>
                        <th style="min-width:220px;">Cliente</th>
                        <th style="min-width:300px;">Itens</th>
                        <th style="min-width:120px;">Valor</th>
                        <th style="min-width:120px;">Ações</th>
                      </tr>
                    </thead>
                    <tbody id="tbodyDev"></tbody>
                  </table>
                </div>

                <div class="muted mt-2" id="hintNone" style="display:none;">Nenhuma devolução encontrada.</div>
                <div class="pager-box" id="pagerDev" style="display:none;"></div>
              </div>
            </div>
          </div>
        </div>

      </div>
    </section>
  </main>

  <div class="modal fade" id="mdDetalhes" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
      <div class="modal-content" style="border-radius:16px;">
        <div class="modal-header">
          <h5 class="modal-title fw-1000">Detalhes da Devolução</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
        </div>
        <div class="modal-body">
          <div class="detail-grid">
            <div class="detail-box">
              <div class="detail-row"><span>ID</span><span id="mId">—</span></div>
              <div class="detail-row"><span>Data/Hora</span><span id="mData">—</span></div>
              <div class="detail-row"><span>Venda</span><span id="mVenda">—</span></div>
              <div class="detail-row"><span>Cliente</span><span id="mCliente">—</span></div>
              <div class="detail-row"><span>Status</span><span id="mStatus">—</span></div>
            </div>
            <div class="detail-box">
              <div class="detail-row"><span>Tipo</span><span id="mTipo">—</span></div>
              <div class="detail-row"><span>Produto</span><span id="mProduto">—</span></div>
              <div class="detail-row"><span>Qtd</span><span id="mQtd">—</span></div>
              <div class="detail-row"><span>Valor</span><span id="mValor">—</span></div>
              <div class="detail-row"><span>Motivo</span><span id="mMotivo">—</span></div>
            </div>
          </div>

          <div class="detail-box mt-3">
            <div style="font-weight:1000;color:#0f172a;margin-bottom:8px;">Itens / Produtos</div>
            <div id="mItens" class="items-preview-box">—</div>
          </div>

          <div class="detail-box mt-3">
            <div style="font-weight:1000;color:#0f172a;margin-bottom:8px;">Observação</div>
            <div id="mObs" style="white-space:pre-wrap;color:#334155;">—</div>
          </div>
        </div>
        <div class="modal-footer">
          <button class="main-btn light-btn btn-hover btn-compact" id="btnEditarModal" type="button">
            <i class="lni lni-pencil me-1"></i> Editar
          </button>
          <button class="main-btn danger-btn-outline btn-hover btn-compact" id="btnExcluirModal" type="button">
            <i class="lni lni-trash-can me-1"></i> Excluir
          </button>
          <button class="main-btn light-btn btn-hover btn-compact" data-bs-dismiss="modal">Fechar</button>
        </div>
      </div>
    </div>
  </div>

  <script src="assets/js/bootstrap.bundle.min.js"></script>
  <script src="assets/js/main.js"></script>

  <script>
    const CSRF = document.querySelector('meta[name="csrf-token"]').content;
    const AJAX_URL = "devolucoes.php";
    const PRODUCTS = <?= json_encode($PRODUTOS_CACHE, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

    function safeText(s) {
      return String(s ?? "")
        .replaceAll("&", "&amp;")
        .replaceAll("<", "&lt;")
        .replaceAll(">", "&gt;")
        .replaceAll('"', "&quot;")
        .replaceAll("'", "&#039;");
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

    let TYPE = "TOTAL";
    let SALE_SELECTED = null;
    let SALE_ITEMS = [];
    let LAST_SALES = [];
    let LAST_PROD = [];
    let saleTimer = null;
    let prodTimer = null;
    let saleAbort = null;

    let CUR_PAGE = 1;
    const PER_PAGE = 10;
    let TOTAL_PAGES = 1;
    let TOTAL_ROWS = 0;
    let SHOWING_FROM = 0;
    let SHOWING_TO = 0;
    let ROWS = [];
    let SEARCH_TIMER = null;
    let SELECTED_ID = 0;

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

    const mId = document.getElementById('mId');
    const mData = document.getElementById('mData');
    const mVenda = document.getElementById('mVenda');
    const mCliente = document.getElementById('mCliente');
    const mStatus = document.getElementById('mStatus');
    const mTipo = document.getElementById('mTipo');
    const mProduto = document.getElementById('mProduto');
    const mQtd = document.getElementById('mQtd');
    const mValor = document.getElementById('mValor');
    const mMotivo = document.getElementById('mMotivo');
    const mObs = document.getElementById('mObs');
    const mItens = document.getElementById('mItens');
    const btnEditarModal = document.getElementById('btnEditarModal');
    const btnExcluirModal = document.getElementById('btnExcluirModal');
    const mdDetalhes = new bootstrap.Modal(document.getElementById('mdDetalhes'));

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
      } else {
        if (String(dProduto.value || '').startsWith('VENDA #')) {
          dProduto.value = '';
        }
        if (!dQtd.value || Number(dQtd.value) < 1) {
          dQtd.value = 1;
        }
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

    function showSaleSuggest(list) {
      if (!list.length) {
        hideSaleSuggest();
        return;
      }
      saleSuggest.innerHTML = list.map(v => `
        <div class="it" data-id="${Number(v.id)}">
          <div style="min-width:0">
            <div class="t">#${Number(v.id)} • ${safeText(v.customer || "Consumidor Final")}</div>
            <div class="s">${safeText(v.date || "")} • ${safeText(v.canal || "")} • ${numberToMoney(v.total || 0)}</div>
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
      if (SALE_SELECTED && String(SALE_SELECTED.id) !== dVendaNo.value.trim()) {
        clearSaleSelection();
      }
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
            <div class="nm">${safeText(it.code ? (it.code + ' - ' + it.name) : it.name)}</div>
            <div class="cd">${Number(it.qty || 0)} ${safeText(it.unit || "")}</div>
          </div>
          <div class="right">
            <div style="font-weight:900;color:#0f172a;">${numberToMoney(it.subtotal || 0)}</div>
            <div class="muted">Unit: ${numberToMoney(it.price || 0)}</div>
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
        if (name.includes(s) || code.includes(s) || (sDig && cdig.includes(sDig))) {
          out.push(p);
        }
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
            <div class="s">${safeText(p.code)}${(p.qty != null && p.qty > 0) ? ` • Qtd venda: ${Number(p.qty)}` : ""}</div>
          </div>
          <div class="s">${(p.subtotal != null && p.subtotal > 0) ? numberToMoney(p.subtotal) : "OK"}</div>
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
        const cust = x.customer ? safeText(x.customer) : "Consumidor Final";
        const valor = numberToMoney(x.amount);
        const itens = safeText(x.items_text || '—');

        tbodyDev.insertAdjacentHTML("beforeend", `
          <tr data-id="${Number(x.id)}">
            <td class="text-center"><span class="mini">${Number(x.id)}</span></td>
            <td class="text-center">${safeText(dt)}</td>
            <td>${cust}</td>
            <td style="white-space:normal;">${itens}</td>
            <td class="text-center"><span class="money">${valor}</span></td>
            <td class="text-center">
              <button class="main-btn light-btn btn-hover btn-compact" type="button" onclick="openDetails(${Number(x.id)})">Detalhes</button>
            </td>
          </tr>
        `);
      });
    }

    function renderPager() {
      if (TOTAL_ROWS <= 0) {
        pagerDev.style.display = "none";
        pagerDev.innerHTML = "";
        return;
      }

      const pagesToShow = [];
      let start = Math.max(1, CUR_PAGE - 2);
      let end = Math.min(TOTAL_PAGES, CUR_PAGE + 2);

      if (CUR_PAGE <= 3) end = Math.min(TOTAL_PAGES, 5);
      if (CUR_PAGE >= TOTAL_PAGES - 2) start = Math.max(1, TOTAL_PAGES - 4);

      for (let i = start; i <= end; i++) pagesToShow.push(i);

      pagerDev.style.display = "flex";
      pagerDev.innerHTML = `
        <div class="pager-left">
          Mostrando ${SHOWING_TO - SHOWING_FROM + (SHOWING_FROM > 0 ? 1 : 0)} item(ns) nesta página de devoluções.
        </div>
        <div class="pager-right">
          <button class="page-btn" id="pgPrev" ${CUR_PAGE <= 1 ? 'disabled' : ''}>‹</button>
          ${pagesToShow.map(p => `<button class="page-btn ${p === CUR_PAGE ? 'active' : ''}" data-page="${p}">${p}</button>`).join('')}
          <button class="page-btn" id="pgNext" ${CUR_PAGE >= TOTAL_PAGES ? 'disabled' : ''}>›</button>
          <span class="page-text">Página ${CUR_PAGE}/${TOTAL_PAGES}</span>
        </div>
      `;

      const prevBtn = document.getElementById('pgPrev');
      const nextBtn = document.getElementById('pgNext');

      if (prevBtn) {
        prevBtn.addEventListener('click', () => {
          if (CUR_PAGE > 1) loadList(CUR_PAGE - 1);
        });
      }

      if (nextBtn) {
        nextBtn.addEventListener('click', () => {
          if (CUR_PAGE < TOTAL_PAGES) loadList(CUR_PAGE + 1);
        });
      }

      pagerDev.querySelectorAll('[data-page]').forEach(btn => {
        btn.addEventListener('click', () => {
          const p = Number(btn.getAttribute('data-page') || 1);
          if (p !== CUR_PAGE) loadList(p);
        });
      });
    }

    async function loadList(page = 1) {
      const q = (qDev.value || "").trim();
      const st = (fStatus.value || "").trim();
      const url = `${AJAX_URL}?ajax=list&page=${encodeURIComponent(page)}&per=${PER_PAGE}&q=${encodeURIComponent(q)}&status=${encodeURIComponent(st)}`;
      const r = await fetchJSON(url);

      CUR_PAGE = Number(r.page || 1);
      TOTAL_PAGES = Number(r.total_pages || 1);
      TOTAL_ROWS = Number(r.total_rows || 0);
      SHOWING_FROM = Number(r.showing_from || 0);
      SHOWING_TO = Number(r.showing_to || 0);

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
        items: Array.isArray(x.items) ? x.items : [],
        items_text: String(x.items_text || '—')
      }));

      setTotals(r.totals || {});
      renderTable();
      renderPager();
    }

    function debounceLoad() {
      clearTimeout(SEARCH_TIMER);
      SEARCH_TIMER = setTimeout(() => {
        CUR_PAGE = 1;
        loadList(1);
      }, 250);
    }

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
        mdDetalhes.hide();

        const pageToReload = CUR_PAGE > TOTAL_PAGES ? TOTAL_PAGES : CUR_PAGE;
        await loadList(pageToReload || 1);
      } catch (e) {
        alert(e.message || "Erro ao excluir.");
      }
    }

    function exportUrl(type) {
      const q = (qDev.value || "").trim();
      const st = (fStatus.value || "").trim();
      const u = new URL(window.location.href);
      u.searchParams.set('export', type);
      u.searchParams.set('q', q);
      u.searchParams.set('status', st);
      u.searchParams.delete('ajax');
      u.searchParams.delete('page');
      u.searchParams.delete('per');
      return u.toString();
    }

    function openDetails(id) {
      const x = ROWS.find(d => Number(d.id) === Number(id));
      if (!x) return;

      SELECTED_ID = Number(id);

      mId.textContent = `#${x.id}`;
      mData.textContent = fmtBRDateTime(x.date, x.time) || '—';
      mVenda.textContent = x.saleNo ? `#${x.saleNo}` : '—';
      mCliente.textContent = x.customer || 'Consumidor Final';
      mStatus.innerHTML = badgeStatus(x.status);
      mTipo.textContent = x.type || 'TOTAL';
      mProduto.textContent = (x.type === 'PARCIAL' ? (x.product || '—') : '—');
      mQtd.textContent = (x.type === 'PARCIAL' ? (x.qty ?? '—') : '—');
      mValor.textContent = numberToMoney(x.amount || 0);
      mMotivo.textContent = motivoLabel(x.reason);
      mObs.textContent = (x.note && String(x.note).trim()) ? x.note : '—';

      if (x.items && x.items.length) {
        mItens.innerHTML = x.items.map(it => `<div class="it">${safeText(it)}</div>`).join('');
      } else {
        mItens.innerHTML = '—';
      }

      mdDetalhes.show();
    }

    btnEditarModal.addEventListener('click', () => {
      if (!SELECTED_ID) return;
      mdDetalhes.hide();
      editDev(SELECTED_ID);
    });

    btnExcluirModal.addEventListener('click', () => {
      if (!SELECTED_ID) return;
      deleteDev(SELECTED_ID);
    });

    btnExcel.addEventListener('click', () => {
      window.location.href = exportUrl('excel');
    });

    btnNova.addEventListener("click", resetForm);
    btnSalvar.addEventListener("click", saveDev);
    btnLimpar.addEventListener("click", resetForm);

    chipTotal.addEventListener("click", () => setType("TOTAL"));
    chipParcial.addEventListener("click", () => setType("PARCIAL"));

    qDev.addEventListener("input", debounceLoad);
    fStatus.addEventListener("change", () => {
      CUR_PAGE = 1;
      loadList(1);
    });

    qGlobal.addEventListener("input", () => {
      qDev.value = qGlobal.value;
      debounceLoad();
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

    window.openDetails = openDetails;

    async function init() {
      resetForm();
      await loadList(1);
    }

    init();
  </script>
</body>

</html>