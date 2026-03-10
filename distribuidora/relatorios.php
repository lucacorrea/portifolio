<?php

declare(strict_types=1);

@date_default_timezone_set('America/Manaus');
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

// Helpers (csrf/flash/etc)
$helpers = __DIR__ . '/assets/dados/relatorios/__helpers.php';
if (is_file($helpers)) require_once $helpers;

// Conexão PDO (precisa existir db():PDO)
$con = __DIR__ . '/assets/conexao.php';
if (is_file($con)) require_once $con;

if (!function_exists('db')) {
  http_response_code(500);
  echo "ERRO: função db():PDO não encontrada. Verifique /assets/conexao.php";
  exit;
}

// fallback de escape, se seu __helpers não tiver
if (!function_exists('e')) {
  function e(string $s): string
  {
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
  }
}

/* =========================
   Utils
========================= */

function iso_date_or_empty(?string $s): string
{
  $s = trim((string)$s);
  if ($s === '') return '';
  return preg_match('/^\d{4}-\d{2}-\d{2}$/', $s) ? $s : '';
}

function br_date(?string $iso): string
{
  $iso = trim((string)$iso);
  if ($iso === '') return '—';
  $dt = DateTime::createFromFormat('Y-m-d', $iso);
  return $dt ? $dt->format('d/m/Y') : '—';
}

function br_datetime(?string $sqlDt): string
{
  $sqlDt = trim((string)$sqlDt);
  if ($sqlDt === '') return '—';
  $dt = DateTime::createFromFormat('Y-m-d H:i:s', $sqlDt);
  if (!$dt) {
    try {
      $dt = new DateTime($sqlDt);
    } catch (\Throwable $e) {
      return '—';
    }
  }
  return $dt->format('d/m/Y H:i');
}

function br_money($n): string
{
  $v = (float)$n;
  return 'R$ ' . number_format($v, 2, ',', '.');
}

function br_num($n, int $dec = 3): string
{
  $v = (float)$n;
  $s = number_format($v, $dec, ',', '.');
  if ($dec > 0) {
    $s = rtrim($s, '0');
    $s = rtrim($s, ',');
  }
  return $s === '' ? '0' : $s;
}

function entrega_label(?string $canal): string
{
  $c = strtoupper(trim((string)$canal));
  if ($c === 'DELIVERY') return 'Delivery';
  if ($c === 'PRESENCIAL') return 'Presencial';
  return $canal ? $canal : '—';
}

function pagamento_label(?string $mode, ?string $pay): string
{
  $m = strtoupper(trim((string)$mode));
  $p = strtoupper(trim((string)$pay));
  if ($m === 'MULTI' || $p === 'MULTI') return 'Múltiplos';
  return $pay && trim($pay) !== '' ? $pay : '—';
}

function like_q(string $q): string
{
  return '%' . $q . '%';
}

function add_like_or(array $fields, string $q, array &$params, string $prefix = 'q'): string
{
  $q = like_q($q);
  $parts = [];
  $i = 1;
  foreach ($fields as $f) {
    $ph = ':' . $prefix . $i;
    $parts[] = "{$f} LIKE {$ph}";
    $params[$ph] = $q;
    $i++;
  }
  return '(' . implode(' OR ', $parts) . ')';
}

function clamp_int($v, int $min, int $max, int $def): int
{
  $n = (int)($v ?? $def);
  if ($n < $min) $n = $min;
  if ($n > $max) $n = $max;
  return $n;
}

function paginate_meta(int $totalRows, int $page, int $per): array
{
  $pages = max(1, (int)ceil($totalRows / max(1, $per)));
  if ($page > $pages) $page = $pages;
  if ($page < 1) $page = 1;
  return [
    'total_rows' => $totalRows,
    'page' => $page,
    'per' => $per,
    'pages' => $pages,
    'offset' => ($page - 1) * $per,
  ];
}

function table_exists(PDO $pdo, string $table): bool
{
  try {
    $st = $pdo->prepare("
      SELECT COUNT(*)
      FROM information_schema.TABLES
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?
    ");
    $st->execute([$table]);
    return (int)$st->fetchColumn() > 0;
  } catch (\Throwable $e) {
    return false;
  }
}

/* =========================
   Builders (SQL -> report) + PAGINAÇÃO
========================= */

function report_vendas_resumo(PDO $pdo, string $dtIni, string $dtFim, string $q, int $page, int $per): array
{
  $where = [];
  $params = [];

  if ($dtIni !== '') {
    $where[] = "v.data >= :dtIni";
    $params[':dtIni'] = $dtIni;
  }
  if ($dtFim !== '') {
    $where[] = "v.data <= :dtFim";
    $params[':dtFim'] = $dtFim;
  }

  $where[] = "NOT EXISTS (
    SELECT 1 FROM devolucoes d
    WHERE d.venda_no = v.id
      AND d.status <> 'CANCELADO'
  )";

  $q = trim($q);
  if ($q !== '') {
    $where[] = add_like_or([
      "CAST(v.id AS CHAR)",
      "v.cliente",
      "v.canal",
      "v.pagamento",
      "v.pagamento_mode"
    ], $q, $params, 'qv');
  }

  $whereSql = count($where) ? ("WHERE " . implode(" AND ", $where)) : "";

  $hasFiados = table_exists($pdo, 'fiados');

  $recebExpr = $hasFiados
    ? "CASE WHEN UPPER(v.pagamento) = 'FIADO' THEN COALESCE((SELECT f.valor_pago FROM fiados f WHERE f.venda_id = v.id LIMIT 1), 0) ELSE v.total END"
    : "v.total";

  $sqlAgg = "
    SELECT
      COUNT(*) AS cnt,
      COALESCE(SUM(v.total),0) AS sum_total,
      COALESCE(SUM($recebExpr),0) AS sum_rec
    FROM vendas v
    $whereSql
  ";
  $stAgg = $pdo->prepare($sqlAgg);
  $stAgg->execute($params);
  $agg = $stAgg->fetch(PDO::FETCH_ASSOC) ?: ['cnt' => 0, 'sum_total' => 0, 'sum_rec' => 0];

  $meta = paginate_meta((int)$agg['cnt'], $page, $per);
  $page = $meta['page'];
  $offset = $meta['offset'];

  $sql = "
    SELECT
      v.id, v.data, v.cliente, v.canal,
      v.pagamento_mode, v.pagamento,
      v.total, v.created_at,
      $recebExpr AS recebido
    FROM vendas v
    $whereSql
    ORDER BY v.data DESC, v.id DESC
    LIMIT :lim OFFSET :off
  ";
  $st = $pdo->prepare($sql);
  foreach ($params as $k => $v) $st->bindValue($k, $v);
  $st->bindValue(':lim', $per, PDO::PARAM_INT);
  $st->bindValue(':off', $offset, PDO::PARAM_INT);
  $st->execute();
  $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

  $head = ["Nº Venda", "Data/Hora", "Cliente", "Entrega", "Pagamento", "Total", "Recebido"];
  $body = [];

  foreach ($rows as $r) {
    $id = (int)($r['id'] ?? 0);
    $cliente = trim((string)($r['cliente'] ?? ''));
    if ($cliente === '') $cliente = 'Consumidor Final';

    $total = (float)($r['total'] ?? 0);
    $recebido = (float)($r['recebido'] ?? $total);

    $body[] = [
      '#' . $id,
      br_datetime((string)($r['created_at'] ?? '')),
      $cliente,
      entrega_label((string)($r['canal'] ?? '')),
      pagamento_label((string)($r['pagamento_mode'] ?? ''), (string)($r['pagamento'] ?? '')),
      br_money($total),
      br_money($recebido),
    ];
  }

  return [
    'title' => 'Vendas (Resumo)',
    'head' => $head,
    'body' => $body,
    'sum' => (float)$agg['sum_total'],
    'sum_text' => br_money((float)$agg['sum_total']),
    'sum_label' => 'Total vendido',
    'sum_rec' => (float)$agg['sum_rec'],
    'sum_rec_text' => br_money((float)$agg['sum_rec']),
    'sum_rec_label' => 'Total recebido (Caixa)',
    'rightCols' => [5, 6],
    'centerCols' => [0, 3, 4],
    'page' => $meta['page'],
    'per' => $meta['per'],
    'pages' => $meta['pages'],
    'total_rows' => $meta['total_rows'],
  ];
}

function report_vendas_itens(PDO $pdo, string $dtIni, string $dtFim, string $q, int $page, int $per): array
{
  $where = [];
  $params = [];

  if ($dtIni !== '') {
    $where[] = "v.data >= :dtIni";
    $params[':dtIni'] = $dtIni;
  }
  if ($dtFim !== '') {
    $where[] = "v.data <= :dtFim";
    $params[':dtFim'] = $dtFim;
  }

  $where[] = "NOT EXISTS (
    SELECT 1 FROM devolucoes d
    WHERE d.venda_no = v.id
      AND d.status <> 'CANCELADO'
  )";

  $q = trim($q);
  if ($q !== '') {
    $where[] = add_like_or([
      "CAST(v.id AS CHAR)",
      "v.cliente",
      "v.canal",
      "v.pagamento",
      "vi.codigo",
      "vi.nome"
    ], $q, $params, 'qvi');
  }

  $whereSql = count($where) ? ("WHERE " . implode(" AND ", $where)) : "";

  $sqlAgg = "
    SELECT
      COUNT(*) AS cnt,
      COALESCE(SUM(vi.subtotal),0) AS sum_total
    FROM venda_itens vi
    INNER JOIN vendas v ON v.id = vi.venda_id
    $whereSql
  ";
  $stAgg = $pdo->prepare($sqlAgg);
  $stAgg->execute($params);
  $agg = $stAgg->fetch(PDO::FETCH_ASSOC) ?: ['cnt' => 0, 'sum_total' => 0];

  $meta = paginate_meta((int)$agg['cnt'], $page, $per);
  $offset = $meta['offset'];

  $sql = "
    SELECT
      v.id AS venda_id,
      v.data,
      v.cliente,
      v.canal,
      v.pagamento_mode,
      v.pagamento,
      vi.codigo,
      vi.nome AS produto,
      vi.unidade,
      vi.qtd,
      vi.preco_unit,
      vi.subtotal
    FROM venda_itens vi
    INNER JOIN vendas v ON v.id = vi.venda_id
    $whereSql
    ORDER BY v.data DESC, v.id DESC, vi.id ASC
    LIMIT :lim OFFSET :off
  ";
  $st = $pdo->prepare($sql);
  foreach ($params as $k => $v) $st->bindValue($k, $v);
  $st->bindValue(':lim', $per, PDO::PARAM_INT);
  $st->bindValue(':off', $offset, PDO::PARAM_INT);
  $st->execute();
  $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

  $head = ["Venda", "Data", "Cliente", "Canal", "Pagamento", "Código", "Produto", "Qtd", "Unitário", "Subtotal"];
  $body = [];

  foreach ($rows as $r) {
    $cliente = trim((string)($r['cliente'] ?? ''));
    if ($cliente === '') $cliente = 'Consumidor Final';

    $body[] = [
      '#' . (string)($r['venda_id'] ?? '—'),
      br_date((string)($r['data'] ?? '')),
      $cliente,
      entrega_label((string)($r['canal'] ?? '')),
      pagamento_label((string)($r['pagamento_mode'] ?? ''), (string)($r['pagamento'] ?? '')),
      (string)($r['codigo'] ?? '—'),
      (string)($r['produto'] ?? '—'),
      br_num($r['qtd'] ?? 0, 0),
      br_money((float)($r['preco_unit'] ?? 0)),
      br_money((float)($r['subtotal'] ?? 0)),
    ];
  }

  return [
    'title' => 'Vendas (Itens)',
    'head' => $head,
    'body' => $body,
    'sum' => (float)$agg['sum_total'],
    'sum_text' => br_money((float)$agg['sum_total']),
    'sum_label' => 'Soma dos subtotais',
    'rightCols' => [8, 9],
    'centerCols' => [0, 3, 4, 7],
    'page' => $meta['page'],
    'per' => $meta['per'],
    'pages' => $meta['pages'],
    'total_rows' => $meta['total_rows'],
  ];
}

function report_produtos(PDO $pdo, string $q, int $page, int $per): array
{
  $where = [];
  $params = [];

  $q = trim($q);
  if ($q !== '') {
    $where[] = add_like_or([
      "p.codigo",
      "p.nome",
      "c.nome",
      "f.nome",
      "p.unidade",
      "p.status"
    ], $q, $params, 'qp');
  }

  $whereSql = count($where) ? ("WHERE " . implode(" AND ", $where)) : "";

  $sqlAgg = "
    SELECT
      COUNT(*) AS cnt,
      COALESCE(SUM(p.preco),0) AS sum_preco
    FROM produtos p
    LEFT JOIN categorias c ON c.id = p.categoria_id
    LEFT JOIN fornecedores f ON f.id = p.fornecedor_id
    $whereSql
  ";
  $stAgg = $pdo->prepare($sqlAgg);
  $stAgg->execute($params);
  $agg = $stAgg->fetch(PDO::FETCH_ASSOC) ?: ['cnt' => 0, 'sum_preco' => 0];

  $meta = paginate_meta((int)$agg['cnt'], $page, $per);
  $offset = $meta['offset'];

  $sql = "
    SELECT
      p.codigo, p.nome, p.unidade, p.preco, p.estoque, p.minimo, p.status,
      c.nome AS categoria,
      f.nome AS fornecedor
    FROM produtos p
    LEFT JOIN categorias c ON c.id = p.categoria_id
    LEFT JOIN fornecedores f ON f.id = p.fornecedor_id
    $whereSql
    ORDER BY p.nome ASC
    LIMIT :lim OFFSET :off
  ";
  $st = $pdo->prepare($sql);
  foreach ($params as $k => $v) $st->bindValue($k, $v);
  $st->bindValue(':lim', $meta['per'], PDO::PARAM_INT);
  $st->bindValue(':off', $offset, PDO::PARAM_INT);
  $st->execute();
  $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

  $head = ["Código", "Produto", "Categoria", "Fornecedor", "Unidade", "Preço", "Estoque", "Mínimo", "Status"];
  $body = [];

  foreach ($rows as $r) {
    $preco = (float)($r['preco'] ?? 0);
    $body[] = [
      (string)($r['codigo'] ?? '—'),
      (string)($r['nome'] ?? '—'),
      (string)($r['categoria'] ?? '—'),
      (string)($r['fornecedor'] ?? '—'),
      (string)($r['unidade'] ?? '—'),
      br_money($preco),
      (string)($r['estoque'] ?? '0'),
      (string)($r['minimo'] ?? '0'),
      (string)($r['status'] ?? '—'),
    ];
  }

  return [
    'title' => 'Produtos (Cadastro)',
    'head' => $head,
    'body' => $body,
    'sum' => (float)$agg['sum_preco'],
    'sum_text' => br_money((float)$agg['sum_preco']),
    'sum_label' => 'Soma dos preços (cadastro)',
    'rightCols' => [5],
    'centerCols' => [6, 7, 8],
    'page' => $meta['page'],
    'per' => $meta['per'],
    'pages' => $meta['pages'],
    'total_rows' => $meta['total_rows'],
  ];
}

function report_estoque_minimo(PDO $pdo, string $q, int $page, int $per): array
{
  $where = ["p.estoque < p.minimo"];
  $params = [];

  $q = trim($q);
  if ($q !== '') {
    $where[] = add_like_or(["p.codigo", "p.nome"], $q, $params, 'qm');
  }

  $whereSql = "WHERE " . implode(" AND ", $where);

  $sqlAgg = "
    SELECT
      COUNT(*) AS cnt,
      COALESCE(SUM(ABS(p.estoque - p.minimo)),0) AS sum_def
    FROM produtos p
    $whereSql
  ";
  $stAgg = $pdo->prepare($sqlAgg);
  $stAgg->execute($params);
  $agg = $stAgg->fetch(PDO::FETCH_ASSOC) ?: ['cnt' => 0, 'sum_def' => 0];

  $meta = paginate_meta((int)$agg['cnt'], $page, $per);
  $offset = $meta['offset'];

  $sql = "
    SELECT p.codigo, p.nome, p.estoque, p.minimo, (p.estoque - p.minimo) AS diff
    FROM produtos p
    $whereSql
    ORDER BY diff ASC, p.nome ASC
    LIMIT :lim OFFSET :off
  ";
  $st = $pdo->prepare($sql);
  foreach ($params as $k => $v) $st->bindValue($k, $v);
  $st->bindValue(':lim', $meta['per'], PDO::PARAM_INT);
  $st->bindValue(':off', $offset, PDO::PARAM_INT);
  $st->execute();
  $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

  $head = ["Código", "Produto", "Estoque", "Mínimo", "Diferença"];
  $body = [];

  foreach ($rows as $r) {
    $diff = (float)($r['diff'] ?? 0);
    $body[] = [
      (string)($r['codigo'] ?? '—'),
      (string)($r['nome'] ?? '—'),
      (string)($r['estoque'] ?? '0'),
      (string)($r['minimo'] ?? '0'),
      (string)$diff,
    ];
  }

  return [
    'title' => 'Estoque Mínimo',
    'head' => $head,
    'body' => $body,
    'sum' => (float)$agg['sum_def'],
    'sum_text' => br_num((float)$agg['sum_def'], 0),
    'sum_label' => 'Soma do déficit (abs)',
    'rightCols' => [],
    'centerCols' => [2, 3, 4],
    'page' => $meta['page'],
    'per' => $meta['per'],
    'pages' => $meta['pages'],
    'total_rows' => $meta['total_rows'],
  ];
}

function report_devolucoes(PDO $pdo, string $dtIni, string $dtFim, string $q, int $page, int $per): array
{
  $where = [];
  $params = [];

  if ($dtIni !== '') {
    $where[] = "d.data >= :dtIni";
    $params[':dtIni'] = $dtIni;
  }
  if ($dtFim !== '') {
    $where[] = "d.data <= :dtFim";
    $params[':dtFim'] = $dtFim;
  }

  $q = trim($q);
  if ($q !== '') {
    $where[] = add_like_or([
      "CAST(d.id AS CHAR)",
      "CAST(d.venda_no AS CHAR)",
      "d.cliente",
      "d.tipo",
      "d.produto",
      "d.motivo",
      "d.status"
    ], $q, $params, 'qd');
  }

  $whereSql = count($where) ? ("WHERE " . implode(" AND ", $where)) : "";

  $sqlAgg = "
    SELECT COUNT(*) AS cnt, COALESCE(SUM(d.valor),0) AS sum_val
    FROM devolucoes d
    $whereSql
  ";
  $stAgg = $pdo->prepare($sqlAgg);
  $stAgg->execute($params);
  $agg = $stAgg->fetch(PDO::FETCH_ASSOC) ?: ['cnt' => 0, 'sum_val' => 0];

  $meta = paginate_meta((int)$agg['cnt'], $page, $per);
  $offset = $meta['offset'];

  $sql = "
    SELECT d.id, d.venda_no, d.cliente, d.data, d.hora, d.tipo, d.produto, d.qtd, d.motivo, d.status, d.valor
    FROM devolucoes d
    $whereSql
    ORDER BY d.data DESC, d.hora DESC, d.id DESC
    LIMIT :lim OFFSET :off
  ";
  $st = $pdo->prepare($sql);
  foreach ($params as $k => $v) $st->bindValue($k, $v);
  $st->bindValue(':lim', $meta['per'], PDO::PARAM_INT);
  $st->bindValue(':off', $offset, PDO::PARAM_INT);
  $st->execute();
  $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

  $head = ["ID", "Data/Hora", "Venda", "Cliente", "Tipo", "Produto", "Qtd", "Motivo", "Status", "Valor"];
  $body = [];

  foreach ($rows as $r) {
    $cliente = trim((string)($r['cliente'] ?? ''));
    if ($cliente === '') $cliente = 'Consumidor Final';

    $valor = (float)($r['valor'] ?? 0);
    $dtText = br_date((string)($r['data'] ?? '')) . ' ' . (string)($r['hora'] ?? '—');
    $venda = ($r['venda_no'] !== null && (string)$r['venda_no'] !== '') ? ('#' . (string)$r['venda_no']) : '—';

    $tipo = (string)($r['tipo'] ?? 'TOTAL');
    $produto = ($tipo === 'PARCIAL') ? ((string)($r['produto'] ?? '—')) : '—';
    $qtd = ($tipo === 'PARCIAL') ? ((string)($r['qtd'] ?? '—')) : '—';

    $body[] = [
      (string)($r['id'] ?? '—'),
      $dtText,
      $venda,
      $cliente,
      $tipo,
      $produto,
      $qtd,
      (string)($r['motivo'] ?? '—'),
      (string)($r['status'] ?? '—'),
      br_money($valor),
    ];
  }

  return [
    'title' => 'Devoluções',
    'head' => $head,
    'body' => $body,
    'sum' => (float)$agg['sum_val'],
    'sum_text' => br_money((float)$agg['sum_val']),
    'sum_label' => 'Total devolvido',
    'rightCols' => [9],
    'centerCols' => [0, 2, 4, 6, 8],
    'page' => $meta['page'],
    'per' => $meta['per'],
    'pages' => $meta['pages'],
    'total_rows' => $meta['total_rows'],
  ];
}

function report_entradas(PDO $pdo, string $dtIni, string $dtFim, string $q, int $page, int $per): array
{
  $where = [];
  $params = [];

  if ($dtIni !== '') {
    $where[] = "e.data >= :dtIni";
    $params[':dtIni'] = $dtIni;
  }
  if ($dtFim !== '') {
    $where[] = "e.data <= :dtFim";
    $params[':dtFim'] = $dtFim;
  }

  $q = trim($q);
  if ($q !== '') {
    $where[] = add_like_or([
      "e.nf",
      "f.nome",
      "p.codigo",
      "p.nome",
      "e.unidade"
    ], $q, $params, 'qe');
  }

  $whereSql = count($where) ? ("WHERE " . implode(" AND ", $where)) : "";

  $sqlAgg = "
    SELECT COUNT(*) AS cnt, COALESCE(SUM(e.total),0) AS sum_total
    FROM entradas e
    INNER JOIN fornecedores f ON f.id = e.fornecedor_id
    INNER JOIN produtos p ON p.id = e.produto_id
    $whereSql
  ";
  $stAgg = $pdo->prepare($sqlAgg);
  $stAgg->execute($params);
  $agg = $stAgg->fetch(PDO::FETCH_ASSOC) ?: ['cnt' => 0, 'sum_total' => 0];

  $meta = paginate_meta((int)$agg['cnt'], $page, $per);
  $offset = $meta['offset'];

  $sql = "
    SELECT
      e.data, e.nf, e.qtd, e.custo, e.total, e.unidade,
      f.nome AS fornecedor,
      p.codigo, p.nome AS produto
    FROM entradas e
    INNER JOIN fornecedores f ON f.id = e.fornecedor_id
    INNER JOIN produtos p ON p.id = e.produto_id
    $whereSql
    ORDER BY e.data DESC, e.id DESC
    LIMIT :lim OFFSET :off
  ";
  $st = $pdo->prepare($sql);
  foreach ($params as $k => $v) $st->bindValue($k, $v);
  $st->bindValue(':lim', $meta['per'], PDO::PARAM_INT);
  $st->bindValue(':off', $offset, PDO::PARAM_INT);
  $st->execute();
  $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

  $head = ["Data", "NF", "Fornecedor", "Código", "Produto", "Qtd", "Unidade", "Custo", "Total"];
  $body = [];

  foreach ($rows as $r) {
    $total = (float)($r['total'] ?? 0);

    $body[] = [
      br_date((string)($r['data'] ?? '')),
      (string)($r['nf'] ?? '—'),
      (string)($r['fornecedor'] ?? '—'),
      (string)($r['codigo'] ?? '—'),
      (string)($r['produto'] ?? '—'),
      (string)($r['qtd'] ?? '0'),
      (string)($r['unidade'] ?? '—'),
      br_money((float)($r['custo'] ?? 0)),
      br_money($total),
    ];
  }

  return [
    'title' => 'Entradas',
    'head' => $head,
    'body' => $body,
    'sum' => (float)$agg['sum_total'],
    'sum_text' => br_money((float)$agg['sum_total']),
    'sum_label' => 'Total de entradas',
    'rightCols' => [7, 8],
    'centerCols' => [5, 6],
    'page' => $meta['page'],
    'per' => $meta['per'],
    'pages' => $meta['pages'],
    'total_rows' => $meta['total_rows'],
  ];
}

function report_saidas(PDO $pdo, string $dtIni, string $dtFim, string $q, int $page, int $per): array
{
  $where = [];
  $params = [];

  if ($dtIni !== '') {
    $where[] = "s.data >= :dtIni";
    $params[':dtIni'] = $dtIni;
  }
  if ($dtFim !== '') {
    $where[] = "s.data <= :dtFim";
    $params[':dtFim'] = $dtFim;
  }

  $q = trim($q);
  if ($q !== '') {
    $where[] = add_like_or([
      "s.tipo",
      "s.motivo",
      "s.obs",
      "p.codigo",
      "p.nome"
    ], $q, $params, 'qs');
  }

  $whereSql = count($where) ? ("WHERE " . implode(" AND ", $where)) : "";

  $sqlAgg = "
    SELECT COUNT(*) AS cnt, COALESCE(SUM(s.valor_total),0) AS sum_total
    FROM saidas s
    INNER JOIN produtos p ON p.id = s.produto_id
    $whereSql
  ";
  $stAgg = $pdo->prepare($sqlAgg);
  $stAgg->execute($params);
  $agg = $stAgg->fetch(PDO::FETCH_ASSOC) ?: ['cnt' => 0, 'sum_total' => 0];

  $meta = paginate_meta((int)$agg['cnt'], $page, $per);
  $offset = $meta['offset'];

  $sql = "
    SELECT
      s.data, s.tipo, s.motivo, s.unidade, s.qtd, s.valor_unit, s.valor_total, s.obs,
      p.codigo, p.nome AS produto
    FROM saidas s
    INNER JOIN produtos p ON p.id = s.produto_id
    $whereSql
    ORDER BY s.data DESC, s.id DESC
    LIMIT :lim OFFSET :off
  ";
  $st = $pdo->prepare($sql);
  foreach ($params as $k => $v) $st->bindValue($k, $v);
  $st->bindValue(':lim', $meta['per'], PDO::PARAM_INT);
  $st->bindValue(':off', $offset, PDO::PARAM_INT);
  $st->execute();
  $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

  $head = ["Data", "Tipo", "Motivo", "Código", "Produto", "Qtd", "Unid.", "Unitário", "Total", "Obs"];
  $body = [];

  foreach ($rows as $r) {
    $total = (float)($r['valor_total'] ?? 0);
    $body[] = [
      br_date((string)($r['data'] ?? '')),
      (string)($r['tipo'] ?? '—'),
      (string)($r['motivo'] ?? '—'),
      (string)($r['codigo'] ?? '—'),
      (string)($r['produto'] ?? '—'),
      br_num($r['qtd'] ?? 0, 0),
      (string)($r['unidade'] ?? '—'),
      br_money((float)($r['valor_unit'] ?? 0)),
      br_money($total),
      (string)($r['obs'] ?? '—'),
    ];
  }

  return [
    'title' => 'Saídas',
    'head' => $head,
    'body' => $body,
    'sum' => (float)$agg['sum_total'],
    'sum_text' => br_money((float)$agg['sum_total']),
    'sum_label' => 'Total de saídas (perdas)',
    'rightCols' => [7, 8],
    'centerCols' => [5, 6],
    'page' => $meta['page'],
    'per' => $meta['per'],
    'pages' => $meta['pages'],
    'total_rows' => $meta['total_rows'],
  ];
}

function build_report(PDO $pdo, string $tipo, string $dtIni, string $dtFim, string $q, int $page, int $per): array
{
  $tipo = strtoupper(trim($tipo));
  $allowed = ['VENDAS_RESUMO', 'VENDAS_ITENS', 'PRODUTOS', 'ESTOQUE_MINIMO', 'DEVOLUCOES', 'ENTRADAS', 'SAIDAS'];
  if (!in_array($tipo, $allowed, true)) $tipo = 'VENDAS_RESUMO';

  if ($tipo === 'VENDAS_RESUMO') return report_vendas_resumo($pdo, $dtIni, $dtFim, $q, $page, $per);
  if ($tipo === 'VENDAS_ITENS')  return report_vendas_itens($pdo, $dtIni, $dtFim, $q, $page, $per);
  if ($tipo === 'PRODUTOS')      return report_produtos($pdo, $q, $page, $per);
  if ($tipo === 'ESTOQUE_MINIMO') return report_estoque_minimo($pdo, $q, $page, $per);
  if ($tipo === 'DEVOLUCOES')    return report_devolucoes($pdo, $dtIni, $dtFim, $q, $page, $per);
  if ($tipo === 'ENTRADAS')      return report_entradas($pdo, $dtIni, $dtFim, $q, $page, $per);
  if ($tipo === 'SAIDAS')        return report_saidas($pdo, $dtIni, $dtFim, $q, $page, $per);

  return report_vendas_resumo($pdo, $dtIni, $dtFim, $q, $page, $per);
}

/* =========================
   AJAX endpoint: fetch
========================= */
if (isset($_GET['action']) && $_GET['action'] === 'fetch') {
  header('Content-Type: application/json; charset=utf-8');

  try {
    $pdo = db();

    $tipo  = (string)($_GET['tipo'] ?? 'VENDAS_RESUMO');
    $dtIni = iso_date_or_empty((string)($_GET['dt_ini'] ?? ''));
    $dtFim = iso_date_or_empty((string)($_GET['dt_fim'] ?? ''));
    $q     = (string)($_GET['q'] ?? '');

    $page  = clamp_int($_GET['page'] ?? 1, 1, 999999, 1);
    $per   = clamp_int($_GET['per'] ?? 10, 10, 200, 10);

    $rep = build_report($pdo, $tipo, $dtIni, $dtFim, $q, $page, $per);

    echo json_encode(['ok' => true, 'report' => $rep], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  } catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  }
  exit;
}

/* =========================
   AJAX endpoint: suggest
========================= */
if (isset($_GET['action']) && $_GET['action'] === 'suggest') {
  header('Content-Type: application/json; charset=utf-8');

  try {
    $pdo = db();

    $tipo  = strtoupper(trim((string)($_GET['tipo'] ?? 'VENDAS_RESUMO')));
    $q     = trim((string)($_GET['q'] ?? ''));
    $dtIni = iso_date_or_empty((string)($_GET['dt_ini'] ?? ''));
    $dtFim = iso_date_or_empty((string)($_GET['dt_fim'] ?? ''));

    if ($q === '' || mb_strlen($q) < 1) {
      echo json_encode(['ok' => true, 'items' => []], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
      exit;
    }

    $out = [];

    if ($tipo === 'VENDAS_RESUMO') {
      $params = [];
      $where = [];
      if ($dtIni !== '') {
        $where[] = "v.data >= :dtIni";
        $params[':dtIni'] = $dtIni;
      }
      if ($dtFim !== '') {
        $where[] = "v.data <= :dtFim";
        $params[':dtFim'] = $dtFim;
      }
      $where[] = "NOT EXISTS (SELECT 1 FROM devolucoes d WHERE d.venda_no = v.id AND d.status <> 'CANCELADO')";
      $whereId = $where;

      $wId = $whereId;
      $wId[] = add_like_or(["CAST(v.id AS CHAR)"], $q, $params, 'sv1');

      $sql1 = "
        SELECT CONCAT('#', v.id, ' — ', COALESCE(NULLIF(v.cliente,''),'Consumidor Final')) AS label,
               CAST(v.id AS CHAR) AS value
        FROM vendas v
        WHERE " . implode(" AND ", $wId) . "
        ORDER BY v.id DESC
        LIMIT 6
      ";
      $st1 = $pdo->prepare($sql1);
      $st1->execute($params);
      foreach (($st1->fetchAll(PDO::FETCH_ASSOC) ?: []) as $it) {
        $out[] = ['label' => (string)$it['label'], 'value' => (string)$it['value']];
      }

      $params2 = [];
      $where2 = [];
      if ($dtIni !== '') {
        $where2[] = "v.data >= :dtIni";
        $params2[':dtIni'] = $dtIni;
      }
      if ($dtFim !== '') {
        $where2[] = "v.data <= :dtFim";
        $params2[':dtFim'] = $dtFim;
      }
      $where2[] = "v.cliente IS NOT NULL AND v.cliente <> ''";
      $where2[] = "NOT EXISTS (SELECT 1 FROM devolucoes d WHERE d.venda_no = v.id AND d.status <> 'CANCELADO')";
      $where2[] = add_like_or(["v.cliente"], $q, $params2, 'sv2');

      $sql2 = "
        SELECT CONCAT('Cliente — ', v.cliente) AS label, v.cliente AS value
        FROM vendas v
        WHERE " . implode(" AND ", $where2) . "
        GROUP BY v.cliente
        ORDER BY v.cliente ASC
        LIMIT 6
      ";
      $st2 = $pdo->prepare($sql2);
      $st2->execute($params2);
      foreach (($st2->fetchAll(PDO::FETCH_ASSOC) ?: []) as $it) {
        $out[] = ['label' => (string)$it['label'], 'value' => (string)$it['value']];
      }
    } elseif ($tipo === 'VENDAS_ITENS') {
      $params = [];
      $where = [];
      if ($dtIni !== '') {
        $where[] = "v.data >= :dtIni";
        $params[':dtIni'] = $dtIni;
      }
      if ($dtFim !== '') {
        $where[] = "v.data <= :dtFim";
        $params[':dtFim'] = $dtFim;
      }
      $where[] = "NOT EXISTS (SELECT 1 FROM devolucoes d WHERE d.venda_no = v.id AND d.status <> 'CANCELADO')";
      $where[] = add_like_or(["vi.codigo", "vi.nome", "CAST(v.id AS CHAR)", "v.cliente"], $q, $params, 'svi');

      $sql = "
        SELECT
          CONCAT(vi.codigo, ' — ', vi.nome) AS label,
          vi.codigo AS value
        FROM venda_itens vi
        INNER JOIN vendas v ON v.id = vi.venda_id
        WHERE " . implode(" AND ", $where) . "
        GROUP BY vi.codigo, vi.nome
        ORDER BY vi.nome ASC
        LIMIT 10
      ";
      $st = $pdo->prepare($sql);
      $st->execute($params);
      foreach (($st->fetchAll(PDO::FETCH_ASSOC) ?: []) as $it) {
        $out[] = ['label' => (string)$it['label'], 'value' => (string)$it['value']];
      }
    } elseif ($tipo === 'PRODUTOS' || $tipo === 'ESTOQUE_MINIMO') {
      $params = [];
      $w = [];
      if ($tipo === 'ESTOQUE_MINIMO') $w[] = "p.estoque < p.minimo";
      $w[] = add_like_or(["p.codigo", "p.nome", "c.nome"], $q, $params, 'sp');

      $sql = "
        SELECT CONCAT(p.codigo, ' — ', p.nome) AS label, p.codigo AS value
        FROM produtos p
        LEFT JOIN categorias c ON c.id = p.categoria_id
        WHERE " . implode(" AND ", $w) . "
        ORDER BY p.nome ASC
        LIMIT 10
      ";
      $st = $pdo->prepare($sql);
      $st->execute($params);
      foreach (($st->fetchAll(PDO::FETCH_ASSOC) ?: []) as $it) {
        $out[] = ['label' => (string)$it['label'], 'value' => (string)$it['value']];
      }
    } elseif ($tipo === 'DEVOLUCOES') {
      $params = [];
      $w = [];
      if ($dtIni !== '') {
        $w[] = "d.data >= :dtIni";
        $params[':dtIni'] = $dtIni;
      }
      if ($dtFim !== '') {
        $w[] = "d.data <= :dtFim";
        $params[':dtFim'] = $dtFim;
      }
      $w[] = add_like_or(["CAST(d.venda_no AS CHAR)", "d.cliente", "d.motivo", "d.status"], $q, $params, 'sd');

      $sql = "
        SELECT
          CONCAT('Devolução — Venda #', COALESCE(d.venda_no,0), ' — ', COALESCE(NULLIF(d.cliente,''),'Consumidor Final')) AS label,
          COALESCE(CAST(d.venda_no AS CHAR), CAST(d.id AS CHAR)) AS value
        FROM devolucoes d
        WHERE " . implode(" AND ", $w) . "
        ORDER BY d.data DESC, d.hora DESC, d.id DESC
        LIMIT 10
      ";
      $st = $pdo->prepare($sql);
      $st->execute($params);
      foreach (($st->fetchAll(PDO::FETCH_ASSOC) ?: []) as $it) {
        $out[] = ['label' => (string)$it['label'], 'value' => (string)$it['value']];
      }
    } elseif ($tipo === 'ENTRADAS') {
      $params = [];
      $w = [];
      if ($dtIni !== '') {
        $w[] = "e.data >= :dtIni";
        $params[':dtIni'] = $dtIni;
      }
      if ($dtFim !== '') {
        $w[] = "e.data <= :dtFim";
        $params[':dtFim'] = $dtFim;
      }
      $w[] = add_like_or(["e.nf", "f.nome", "p.codigo", "p.nome"], $q, $params, 'se');

      $sql = "
        SELECT CONCAT('NF ', e.nf, ' — ', f.nome) AS label, e.nf AS value
        FROM entradas e
        INNER JOIN fornecedores f ON f.id = e.fornecedor_id
        INNER JOIN produtos p ON p.id = e.produto_id
        WHERE " . implode(" AND ", $w) . "
        GROUP BY e.nf, f.nome
        ORDER BY MAX(e.data) DESC
        LIMIT 10
      ";
      $st = $pdo->prepare($sql);
      $st->execute($params);
      foreach (($st->fetchAll(PDO::FETCH_ASSOC) ?: []) as $it) {
        $out[] = ['label' => (string)$it['label'], 'value' => (string)$it['value']];
      }
    } elseif ($tipo === 'SAIDAS') {
      $params = [];
      $w = [];
      if ($dtIni !== '') {
        $w[] = "s.data >= :dtIni";
        $params[':dtIni'] = $dtIni;
      }
      if ($dtFim !== '') {
        $w[] = "s.data <= :dtFim";
        $params[':dtFim'] = $dtFim;
      }
      $w[] = add_like_or(["s.tipo", "s.motivo", "p.codigo", "p.nome", "s.obs"], $q, $params, 'ss');

      $sql = "
        SELECT
          CONCAT('Saída — ', s.tipo, ' — ', s.motivo, ' — ', p.nome) AS label,
          s.motivo AS value
        FROM saidas s
        INNER JOIN produtos p ON p.id = s.produto_id
        WHERE " . implode(" AND ", $w) . "
        ORDER BY s.data DESC, s.id DESC
        LIMIT 10
      ";
      $st = $pdo->prepare($sql);
      $st->execute($params);
      foreach (($st->fetchAll(PDO::FETCH_ASSOC) ?: []) as $it) {
        $out[] = ['label' => (string)$it['label'], 'value' => (string)$it['value']];
      }
    }

    $final = [];
    foreach ($out as $it) {
      $label = trim((string)($it['label'] ?? ''));
      $value = trim((string)($it['value'] ?? ''));
      if ($label === '' || $value === '') continue;
      $final[] = ['label' => $label, 'value' => $value];
      if (count($final) >= 10) break;
    }

    echo json_encode(['ok' => true, 'items' => $final], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  } catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  }
  exit;
}

?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
  <meta charset="UTF-8" />
  <meta http-equiv="X-UA-Compatible" content="IE=edge" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />

  <link rel="shortcut icon" href="assets/images/favicon.svg" type="image/x-icon" />
  <title>Painel da Distribuidora | Relatórios</title>

  <link rel="stylesheet" href="assets/css/bootstrap.min.css" />
  <link rel="stylesheet" href="assets/css/lineicons.css" rel="stylesheet" type="text/css" />
  <link rel="stylesheet" href="assets/css/materialdesignicons.min.css" rel="stylesheet" type="text/css" />
  <link rel="stylesheet" href="assets/css/main.css" />

  <style>
    .profile-box .dropdown-menu {
      width: max-content;
      min-width: 260px;
      max-width: calc(100vw - 24px);
    }

    .profile-box .dropdown-menu .author-info {
      width: max-content;
      max-width: 100%;
      display: flex !important;
      align-items: center;
      gap: 10px;
    }

    .profile-box .dropdown-menu .author-info .content {
      min-width: 0;
      max-width: 100%;
    }

    .profile-box .dropdown-menu .author-info .content a {
      display: inline-block;
      white-space: nowrap;
      max-width: 100%;
    }

    .main-btn.btn-compact {
      height: 38px !important;
      padding: 8px 14px !important;
      font-size: 13px !important;
      line-height: 1 !important;
    }

    .main-btn.btn-compact i {
      font-size: 14px;
      vertical-align: -1px;
    }

    .form-control.compact,
    .form-select.compact {
      height: 38px;
      padding: 8px 12px;
      font-size: 13px;
    }

    .muted {
      font-size: 12px;
      color: #64748b;
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

    .cardx.fill {
      height: 100%;
      display: flex;
      flex-direction: column;
    }

    .cardx.fill .body {
      flex: 1 1 auto;
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
      white-space: nowrap;
    }

    .pill.primary {
      border-color: rgba(37, 99, 235, .28);
      background: rgba(239, 246, 255, .75);
      color: #0b5ed7;
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

    .pill.bad {
      border-color: rgba(239, 68, 68, .25);
      background: rgba(254, 242, 242, .9);
      color: #991b1b;
    }

    .filters-row .form-label {
      margin-bottom: 6px;
    }

    .filters-actions {
      display: flex;
      gap: 8px;
      flex-wrap: wrap;
      justify-content: flex-end;
    }

    .table td,
    .table th {
      vertical-align: middle;
    }

    .table-responsive {
      -webkit-overflow-scrolling: touch;
    }

    #tbRel {
      width: 100%;
      min-width: 980px;
    }

    #tbRel th,
    #tbRel td {
      white-space: nowrap !important;
    }

    .rel-table-wrap {
      flex: 1 1 auto;
      min-height: 260px;
    }

    .table-footer-nav {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 14px;
      flex-wrap: wrap;
      margin-top: 12px;
    }

    .pager-box {
      display: flex;
      align-items: center;
      gap: 14px;
      justify-content: flex-end;
      flex-wrap: wrap;
    }

    .page-btn {
      width: 42px;
      height: 42px;
      border: 1px solid #e5e7eb;
      border-radius: 8px;
      background: #f8fafc;
      color: #475569;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      transition: .2s ease;
    }

    .page-btn:hover:not(:disabled) {
      background: #eef2ff;
      color: #1e40af;
      border-color: #c7d2fe;
    }

    .page-btn:disabled {
      opacity: .45;
      cursor: not-allowed;
    }

    .page-info {
      font-weight: 900;
      color: #475569;
      min-width: 90px;
      text-align: center;
      font-size: 12px;
    }

    .box-tot {
      border: 1px solid rgba(148, 163, 184, .25);
      border-radius: 14px;
      background: #fff;
      padding: 12px;
      margin-top: auto !important;
    }

    .tot-row {
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 10px;
      font-size: 13px;
      color: #334155;
      margin-bottom: 8px;
      font-weight: 900;
    }

    .tot-row:last-child {
      margin-bottom: 0;
    }

    .tot-hr {
      height: 1px;
      background: rgba(148, 163, 184, .22);
      margin: 10px 0;
    }

    .grand {
      display: flex;
      justify-content: space-between;
      align-items: baseline;
      gap: 10px;
      margin-top: 4px;
    }

    .grand .lbl {
      font-weight: 1000;
      color: #0f172a;
      font-size: 16px;
    }

    .grand .val {
      font-weight: 1000;
      color: #0b5ed7;
      font-size: 26px;
      letter-spacing: .2px;
    }

    .quick-grid {
      display: grid;
      grid-template-columns: 1fr;
      gap: 10px;
    }

    .quick {
      border: 1px solid rgba(148, 163, 184, .25);
      border-radius: 14px;
      padding: 12px;
      background: rgba(248, 250, 252, .6);
      cursor: pointer;
      transition: .12s ease;
    }

    .quick:hover {
      transform: translateY(-1px);
      box-shadow: 0 10px 22px rgba(15, 23, 42, .08);
      background: rgba(239, 246, 255, .65);
      border-color: rgba(37, 99, 235, .30);
    }

    .quick .t {
      font-weight: 1000;
      color: #0f172a;
      font-size: 13px;
      margin-bottom: 4px;
    }

    .quick .d {
      font-size: 12px;
      color: #64748b;
      margin-bottom: 8px;
    }

    .quick .tag {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      font-size: 11px;
      font-weight: 900;
      color: #0b5ed7;
      background: rgba(239, 246, 255, .9);
      border: 1px solid rgba(37, 99, 235, .22);
      padding: 5px 10px;
      border-radius: 999px;
    }

    @media (max-width: 991.98px) {
      #tbRel {
        min-width: 900px;
      }

      .grand .val {
        font-size: 22px;
      }

      .filters-actions {
        justify-content: flex-start;
      }

      .table-footer-nav {
        justify-content: center;
      }

      #infoCount {
        text-align: center;
        width: 100%;
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
      <a href="dashboard.php" class="d-flex align-items-center gap-2">
        <img src="assets/images/logo/logo.svg" alt="logo" />
      </a>
    </div>

    <nav class="sidebar-nav">
      <ul>
        <li class="nav-item"><a href="dashboard.php"><span class="icon"><i class="lni lni-dashboard"></i></span><span class="text">Dashboard</span></a></li>
        <li class="nav-item"><a href="vendas.php"><span class="icon"><i class="lni lni-cart"></i></span><span class="text">Vendas</span></a></li>

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

        <li class="nav-item active">
          <a href="relatorios.php" class="active">
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

        <li class="nav-item"><a href="suporte.php"><span class="icon"><i class="lni lni-whatsapp"></i></span><span class="text">Suporte</span></a></li>
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
                  <input type="text" placeholder="Buscar no relatório..." id="qGlobal" />
                  <datalist id="dlGlobalSug"></datalist>
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
                <h2>Relatórios</h2>
                <div class="muted">Autocomplete • Devolvidas saem de Vendas e ficam em Devoluções.</div>
              </div>
            </div>
          </div>
        </div>

        <div class="cardx mb-3">
          <div class="head">
            <div style="font-weight:1000;color:#0f172a;">
              <i class="lni lni-funnel me-1"></i> Filtros
            </div>
            <div class="d-flex align-items-center gap-2 flex-wrap">
              <span class="pill ok" id="pillCount"><i class="lni lni-checkmark-circle"></i> 0 linhas</span>
              <span class="pill warn" id="pillPeriod"><i class="lni lni-calendar"></i> Período: —</span>
              <span class="pill primary" id="pillInfo"><i class="lni lni-bolt"></i> PRONTO</span>
            </div>
          </div>

          <div class="body">
            <div class="row g-2 align-items-end filters-row">
              <div class="col-12 col-lg-5">
                <label class="form-label">Tipo de relatório</label>
                <select class="form-select compact" id="rTipo">
                  <option value="VENDAS_RESUMO" selected>Vendas (Resumo)</option>
                  <option value="VENDAS_ITENS">Vendas (Itens)</option>
                  <option value="PRODUTOS">Produtos (Cadastro)</option>
                  <option value="ESTOQUE_MINIMO">Estoque Mínimo</option>
                  <option value="DEVOLUCOES">Devoluções</option>
                  <option value="ENTRADAS">Entradas</option>
                  <option value="SAIDAS">Saídas</option>
                </select>
              </div>

              <div class="col-12 col-lg-2">
                <label class="form-label">Data inicial</label>
                <input class="form-control compact" id="dtIni" type="date" />
              </div>

              <div class="col-12 col-lg-2">
                <label class="form-label">Data final</label>
                <input class="form-control compact" id="dtFim" type="date" />
              </div>

              <div class="col-12 col-lg-3">
                <div class="filters-actions">
                  <button class="main-btn primary-btn btn-hover btn-compact" id="btnGerar" type="button">
                    <i class="lni lni-play me-1"></i> Gerar
                  </button>
                  <button class="main-btn light-btn btn-hover btn-compact" id="btnExcel" type="button">
                    <i class="lni lni-download me-1"></i> Excel
                  </button>
                  <button class="main-btn light-btn btn-hover btn-compact" id="btnLimpar" type="button">
                    <i class="lni lni-eraser me-1"></i> Limpar
                  </button>
                </div>
              </div>

              <div class="col-12">
                <div class="muted mt-1">* Digite na busca e escolha uma sugestão na lista.</div>
              </div>

              <div class="col-12">
                <label class="form-label">Busca (filtro extra)</label>
                <input class="form-control compact" id="qRel" placeholder="Cliente, venda, código, produto, motivo..." />
                <datalist id="dlRelSug"></datalist>
              </div>
            </div>
          </div>
        </div>

        <div class="row g-3 mb-30 align-items-stretch">
          <div class="col-12 col-lg-4">
            <div class="cardx fill">
              <div class="head">
                <div style="font-weight:1000;color:#0f172a;">
                  <i class="lni lni-grid-alt me-1"></i> Atalhos
                </div>
              </div>
              <div class="body">
                <div class="quick-grid">
                  <div class="quick" data-quick="VENDAS_RESUMO">
                    <div class="t">Vendas (Resumo)</div>
                    <div class="d">Somente vendas não devolvidas.</div>
                    <div class="tag"><i class="lni lni-cart"></i> Operações</div>
                  </div>
                  <div class="quick" data-quick="VENDAS_ITENS">
                    <div class="t">Vendas (Itens)</div>
                    <div class="d">Itens por venda (venda_itens).</div>
                    <div class="tag"><i class="lni lni-list"></i> Detalhado</div>
                  </div>
                  <div class="quick" data-quick="ESTOQUE_MINIMO">
                    <div class="t">Estoque Mínimo</div>
                    <div class="d">Abaixo do mínimo.</div>
                    <div class="tag"><i class="lni lni-warning"></i> Estoque</div>
                  </div>
                  <div class="quick" data-quick="DEVOLUCOES">
                    <div class="t">Devoluções</div>
                    <div class="d">Onde ficam as vendas devolvidas.</div>
                    <div class="tag"><i class="lni lni-package"></i> Pós-venda</div>
                  </div>
                </div>

                <div class="muted mt-3">
                  Dica: use <b>datas</b> para reduzir o relatório e facilitar exportação.
                </div>
              </div>
            </div>
          </div>

          <div class="col-12 col-lg-8">
            <div class="cardx fill">
              <div class="head">
                <div style="font-weight:1000;color:#0f172a;">
                  <i class="lni lni-bar-chart me-1"></i> Prévia do relatório
                </div>
              </div>

              <div class="body d-flex flex-column">
                <div class="rel-table-wrap">
                  <div class="table-responsive">
                    <table class="table text-nowrap" id="tbRel">
                      <thead id="theadRel"></thead>
                      <tbody id="tbodyRel"></tbody>
                    </table>
                  </div>

                  <div class="muted mt-2" id="hintNone" style="display:none;">Nenhum dado para o filtro selecionado.</div>

                  <div class="table-footer-nav">
                    <p class="text-sm text-gray mb-0" id="infoCount">Mostrando 0 item(ns) nesta página. Total filtrado: 0.</p>

                    <div class="pager-box" id="pagerBox">
                      <button class="page-btn" id="btnPrevPage" type="button" title="Anterior">
                        <i class="lni lni-chevron-left"></i>
                      </button>

                      <span class="page-info" id="pagerText">Página 1/1</span>

                      <button class="page-btn" id="btnNextPage" type="button" title="Próxima">
                        <i class="lni lni-chevron-right"></i>
                      </button>
                    </div>
                  </div>
                </div>

                <div class="box-tot mt-5">
                  <div class="tot-row"><span>Linhas</span><span id="tRows">0</span></div>
                  <div class="tot-row"><span>Vendido</span><span id="tSum" style="font-weight:900;">—</span></div>
                  <div class="tot-row" id="rowSumRec" style="display:none;"><span>Recebido (Caixa)</span><span id="tSumRec" style="font-weight:1000;color:#0b5ed7;">—</span></div>
                  <div class="tot-hr"></div>
                  <div class="grand">
                    <span class="lbl">TOTAL CAIXA</span>
                    <span class="val" id="tGrand">—</span>
                  </div>
                  <div class="muted mt-2" id="tNote">* O somatório depende do tipo de relatório.</div>
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

  <script src="assets/js/bootstrap.bundle.min.js"></script>
  <script src="assets/js/main.js"></script>

  <script>
    function safeText(s) {
      return String(s ?? "")
        .replaceAll("&", "&amp;")
        .replaceAll("<", "&lt;")
        .replaceAll(">", "&gt;")
        .replaceAll('"', "&quot;")
        .replaceAll("'", "&#039;");
    }

    function setInfo(type, ok = true) {
      pillInfo.className = ok ? "pill primary" : "pill bad";
      pillInfo.innerHTML = ok ?
        `<i class="lni lni-bolt"></i> ${safeText(type)}` :
        `<i class="lni lni-warning"></i> ${safeText(type)}`;
    }

    function debounce(fn, ms = 250) {
      let t = null;
      return (...args) => {
        clearTimeout(t);
        t = setTimeout(() => fn(...args), ms);
      };
    }

    const qGlobal = document.getElementById("qGlobal");
    const dlGlobalSug = document.getElementById("dlGlobalSug");

    const rTipo = document.getElementById("rTipo");
    const dtIni = document.getElementById("dtIni");
    const dtFim = document.getElementById("dtFim");
    const qRel = document.getElementById("qRel");
    const dlRelSug = document.getElementById("dlRelSug");

    const btnGerar = document.getElementById("btnGerar");
    const btnExcel = document.getElementById("btnExcel");
    const btnLimpar = document.getElementById("btnLimpar");

    const theadRel = document.getElementById("theadRel");
    const tbodyRel = document.getElementById("tbodyRel");
    const hintNone = document.getElementById("hintNone");

    const pillCount = document.getElementById("pillCount");
    const pillPeriod = document.getElementById("pillPeriod");
    const pillInfo = document.getElementById("pillInfo");

    const tRows = document.getElementById("tRows");
    const tSum = document.getElementById("tSum");
    const tGrand = document.getElementById("tGrand");
    const tNote = document.getElementById("tNote");

    const pagerBox = document.getElementById("pagerBox");
    const btnPrevPage = document.getElementById("btnPrevPage");
    const btnNextPage = document.getElementById("btnNextPage");
    const pagerText = document.getElementById("pagerText");
    const infoCount = document.getElementById("infoCount");

    qRel.setAttribute("list", "dlRelSug");
    qGlobal.setAttribute("list", "dlGlobalSug");

    const PER = 10;
    let PAGE = 1;

    let CURRENT = {
      title: "Relatório",
      head: [],
      body: [],
      sum: 0,
      sum_text: "—",
      sum_label: "Somatório",
      rightCols: [],
      centerCols: [],
      page: 1,
      pages: 1,
      total_rows: 0
    };

    async function fetchReport() {
      const tipo = rTipo.value;
      const fromISO = dtIni.value || "";
      const toISO = dtFim.value || "";
      const q = qRel.value || "";

      if (fromISO || toISO) {
        pillPeriod.innerHTML = `<i class="lni lni-calendar"></i> Período: ${fromISO ? fromISO.split("-").reverse().join("/") : "—"} a ${toISO ? toISO.split("-").reverse().join("/") : "—"}`;
      } else {
        pillPeriod.innerHTML = `<i class="lni lni-calendar"></i> Período: —`;
      }

      const params = new URLSearchParams({
        action: "fetch",
        tipo,
        dt_ini: fromISO,
        dt_fim: toISO,
        q,
        page: String(PAGE),
        per: String(PER)
      });

      const res = await fetch("relatorios.php?" + params.toString(), {
        headers: {
          "Accept": "application/json"
        }
      });
      const json = await res.json().catch(() => null);

      if (!json || !json.ok) throw new Error((json && json.error) ? json.error : "Falha ao carregar relatório.");
      return json.report;
    }

    function renderPager(rep) {
      const pages = Number(rep.pages || 1);
      const page = Number(rep.page || 1);

      pagerBox.style.display = "flex";
      pagerText.textContent = `Página ${page}/${pages}`;

      btnPrevPage.disabled = page <= 1;
      btnNextPage.disabled = page >= pages;
    }

    function renderTable(rep) {
      CURRENT = rep || CURRENT;

      const right = new Set((rep.rightCols || []).map(n => Number(n)));
      const center = new Set((rep.centerCols || []).map(n => Number(n)));

      theadRel.innerHTML = `<tr>${(rep.head || []).map((h, idx) => {
        const cls = right.has(idx) ? "text-end" : (center.has(idx) ? "text-center" : "");
        return `<th class="${cls}">${safeText(h)}</th>`;
      }).join("")}</tr>`;

      tbodyRel.innerHTML = (rep.body || []).map(row => `
        <tr>
          ${(row || []).map((c, idx) => {
            const cls = right.has(idx) ? "text-end" : (center.has(idx) ? "text-center" : "");
            return `<td class="${cls}">${safeText(c)}</td>`;
          }).join("")}
        </tr>
      `).join("");

      const pageCount = (rep.body || []).length;
      const totalRows = Number(rep.total_rows || pageCount);

      hintNone.style.display = pageCount ? "none" : "block";

      pillCount.innerHTML = `<i class="lni lni-checkmark-circle"></i> ${pageCount} linhas (de ${totalRows})`;
      infoCount.textContent = `Mostrando ${pageCount} item(ns) nesta página do relatório. Total filtrado: ${totalRows}.`;
      tRows.textContent = `${pageCount} / ${totalRows}`;

      const sumText = rep.sum_text || "—";
      tSum.textContent = sumText;

      if (rep.sum_rec_text) {
        document.getElementById('rowSumRec').style.display = 'flex';
        document.getElementById('tSumRec').textContent = rep.sum_rec_text;
        tGrand.textContent = rep.sum_rec_text;
      } else {
        document.getElementById('rowSumRec').style.display = 'none';
        tGrand.textContent = sumText;
      }

      tNote.textContent = `* ${rep.sum_label || "Somatório"}.`;
      setInfo(rep.title || "Relatório", true);

      renderPager(rep);
    }

    async function fetchSuggest(targetDatalist) {
      const tipo = rTipo.value;
      const fromISO = dtIni.value || "";
      const toISO = dtFim.value || "";
      const q = qRel.value || "";

      if (!q || q.trim().length < 1) {
        targetDatalist.innerHTML = "";
        return;
      }

      const params = new URLSearchParams({
        action: "suggest",
        tipo,
        dt_ini: fromISO,
        dt_fim: toISO,
        q
      });

      const res = await fetch("relatorios.php?" + params.toString(), {
        headers: {
          "Accept": "application/json"
        }
      });
      const json = await res.json().catch(() => null);
      if (!json || !json.ok) {
        targetDatalist.innerHTML = "";
        return;
      }

      const items = Array.isArray(json.items) ? json.items : [];
      targetDatalist.innerHTML = items.slice(0, 10).map(it => {
        const label = String(it.label || it.value || "");
        const value = String(it.value || "");
        return `<option value="${safeText(value)}" label="${safeText(label)}"></option>`;
      }).join("");
    }

    const debouncedGerar = debounce(async () => {
      setInfo("CARREGANDO...", true);
      try {
        const rep = await fetchReport();
        PAGE = Number(rep.page || PAGE);
        renderTable(rep);
      } catch (e) {
        setInfo("ERRO AO GERAR", false);
        renderTable({
          title: "Erro",
          head: ["Mensagem"],
          body: [
            [String(e && e.message ? e.message : e)]
          ],
          sum: 0,
          sum_text: "—",
          sum_label: "Somatório",
          rightCols: [],
          centerCols: [],
          page: 1,
          pages: 1,
          total_rows: 1
        });
      }
    }, 280);

    const debouncedSuggest = debounce(async () => {
      await fetchSuggest(dlRelSug);
      await fetchSuggest(dlGlobalSug);
    }, 220);

    function resetPageAndLoad() {
      PAGE = 1;
      debouncedSuggest();
      debouncedGerar();
    }

    function syncInputs(from) {
      if (from === 'global') qRel.value = qGlobal.value;
      if (from === 'rel') qGlobal.value = qRel.value;
      PAGE = 1;
      debouncedSuggest();
      debouncedGerar();
    }

    function exportExcel() {
      const rep = CURRENT;
      if (!rep || !rep.head || !rep.body) {
        alert("Gere um relatório primeiro.");
        return;
      }

      const now = new Date();
      const dt = now.toLocaleDateString("pt-BR") + " " + now.toLocaleTimeString("pt-BR");
      const periodo = pillPeriod.textContent.replace("Período:", "").trim() || "—";

      const right = new Set((rep.rightCols || []).map(n => Number(n)));
      const center = new Set((rep.centerCols || []).map(n => Number(n)));

      let html = `
        <html>
          <head>
            <meta charset="utf-8">
            <style>
              table { border-collapse: collapse; font-family: Arial, sans-serif; font-size: 12px; }
              td, th { border: 1px solid #000; padding: 6px 8px; vertical-align: middle; }
              th { background: #dbe5f1; font-weight: bold; }
              .title { font-size: 16px; font-weight: bold; text-align: center; background: #ddebf7; }
              .left { text-align: left; }
              .center { text-align: center; }
              .right { text-align: right; }
            </style>
          </head>
          <body>
            <table>
      `;

      const colN = rep.head.length;

      html += `<tr><td class="title" colspan="${colN}">PAINEL DA DISTRIBUIDORA - ${safeText(String(rep.title || "RELATÓRIO").toUpperCase())}</td></tr>`;
      html += `<tr><td colspan="${colN}">Gerado em: ${safeText(dt)}</td></tr>`;
      html += `<tr><td colspan="${colN}">Período: ${safeText(periodo)}</td></tr>`;
      html += `<tr><td colspan="${colN}">Página: ${safeText(String(rep.page || 1))}/${safeText(String(rep.pages || 1))} | Total de linhas filtradas: ${safeText(String(rep.total_rows || 0))}</td></tr>`;
      html += `<tr><td colspan="${colN}">${safeText(rep.sum_label || "Somatório")}: ${safeText(rep.sum_text || "—")}</td></tr>`;
      if (rep.sum_rec_text) {
        html += `<tr><td colspan="${colN}">Recebido (Caixa): ${safeText(rep.sum_rec_text)}</td></tr>`;
      }

      html += `<tr>${rep.head.map((h, idx) => {
        let cls = "left";
        if (right.has(idx)) cls = "right";
        else if (center.has(idx)) cls = "center";
        return `<th class="${cls}">${safeText(h)}</th>`;
      }).join("")}</tr>`;

      (rep.body || []).forEach(row => {
        html += `<tr>${(row || []).map((c, idx) => {
          let cls = "left";
          if (right.has(idx)) cls = "right";
          else if (center.has(idx)) cls = "center";
          return `<td class="${cls}">${safeText(c)}</td>`;
        }).join("")}</tr>`;
      });

      html += `</table></body></html>`;

      const blob = new Blob(["\ufeff" + html], {
        type: "application/vnd.ms-excel;charset=utf-8;"
      });
      const url = URL.createObjectURL(blob);
      const a = document.createElement("a");
      a.href = url;
      a.download = `relatorio_${String(rep.title || "relatorio").toLowerCase().replace(/\s+/g, "_")}_pag${rep.page || 1}.xls`;
      document.body.appendChild(a);
      a.click();
      a.remove();
      URL.revokeObjectURL(url);
    }

    btnPrevPage.addEventListener("click", () => {
      if ((CURRENT.page || 1) <= 1) return;
      PAGE = (CURRENT.page || 1) - 1;
      debouncedGerar();
    });

    btnNextPage.addEventListener("click", () => {
      if ((CURRENT.page || 1) >= (CURRENT.pages || 1)) return;
      PAGE = (CURRENT.page || 1) + 1;
      debouncedGerar();
    });

    btnGerar.addEventListener("click", () => {
      PAGE = 1;
      debouncedGerar();
    });

    btnExcel.addEventListener("click", exportExcel);

    btnLimpar.addEventListener("click", () => {
      rTipo.value = "VENDAS_RESUMO";
      dtIni.value = "";
      dtFim.value = "";
      qRel.value = "";
      qGlobal.value = "";
      dlRelSug.innerHTML = "";
      dlGlobalSug.innerHTML = "";
      PAGE = 1;
      debouncedGerar();
    });

    qGlobal.addEventListener("input", () => syncInputs('global'));
    qRel.addEventListener("input", () => syncInputs('rel'));

    rTipo.addEventListener("change", resetPageAndLoad);
    dtIni.addEventListener("change", resetPageAndLoad);
    dtFim.addEventListener("change", resetPageAndLoad);

    document.querySelectorAll(".quick").forEach(el => {
      el.addEventListener("click", () => {
        const t = el.getAttribute("data-quick");
        if (t) {
          rTipo.value = t;
          resetPageAndLoad();
        }
      });
    });

    debouncedGerar();
  </script>
</body>

</html>