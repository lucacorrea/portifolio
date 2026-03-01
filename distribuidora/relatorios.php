<?php
declare(strict_types=1);

/**
 * relatorios.php
 * - Página + endpoint JSON (action=fetch) no mesmo arquivo
 * - Lê do MySQL (tabelas: vendas, saidas, entradas, produtos, categorias, fornecedores, devolucoes)
 *
 * Ajuste os includes abaixo conforme seu projeto.
 */

@date_default_timezone_set('America/Manaus');

// Helpers (csrf/flash/etc) - use se existir no seu projeto
$helpers = __DIR__ . '/assets/dados//relstorios/_helpers.php';
if (is_file($helpers)) require_once $helpers;

// Conexão PDO (precisa existir db():PDO). Ajuste o caminho se necessário:
$con = __DIR__ . '/assets/conexao.php';
if (is_file($con)) require_once $con;

if (!function_exists('db')) {
  http_response_code(500);
  echo "ERRO: função db():PDO não encontrada. Verifique /assets/php/conexao.php";
  exit;
}

/* =========================
   Utils
========================= */

function iso_date_or_empty(?string $s): string {
  $s = trim((string)$s);
  if ($s === '') return '';
  return preg_match('/^\d{4}-\d{2}-\d{2}$/', $s) ? $s : '';
}

function str_or_null(?string $s): ?string {
  $s = trim((string)$s);
  return $s === '' ? null : $s;
}

function br_date(?string $iso): string {
  $iso = trim((string)$iso);
  if ($iso === '') return '—';
  $dt = DateTime::createFromFormat('Y-m-d', $iso);
  return $dt ? $dt->format('d/m/Y') : '—';
}

function br_datetime(?string $sqlDt): string {
  $sqlDt = trim((string)$sqlDt);
  if ($sqlDt === '') return '—';
  // aceita "Y-m-d H:i:s"
  $dt = DateTime::createFromFormat('Y-m-d H:i:s', $sqlDt);
  if (!$dt) {
    // tenta parse genérico
    try { $dt = new DateTime($sqlDt); } catch (\Throwable $e) { return '—'; }
  }
  return $dt->format('d/m/Y H:i');
}

function br_money($n): string {
  $v = (float)$n;
  return 'R$ ' . number_format($v, 2, ',', '.');
}

function br_num($n, int $dec = 3): string {
  $v = (float)$n;
  // remove zeros finais com cuidado
  $s = number_format($v, $dec, ',', '.');
  if ($dec > 0) {
    $s = rtrim($s, '0');
    $s = rtrim($s, ',');
  }
  return $s === '' ? '0' : $s;
}

function entrega_label(?string $canal): string {
  $c = strtoupper(trim((string)$canal));
  if ($c === 'DELIVERY') return 'Delivery';
  if ($c === 'PRESENCIAL') return 'Presencial';
  return $canal ? $canal : '—';
}

function pagamento_label(?string $mode, ?string $pay): string {
  $m = strtoupper(trim((string)$mode));
  $p = strtoupper(trim((string)$pay));
  if ($m === 'MULTI' || $p === 'MULTI') return 'Múltiplos';
  return $pay && trim($pay) !== '' ? $pay : '—';
}

function like_q(string $q): string {
  // LIKE com wildcards
  return '%' . $q . '%';
}

/* =========================
   Builders (SQL -> report)
========================= */

function report_vendas_resumo(PDO $pdo, string $dtIni, string $dtFim, string $q): array {
  $where = [];
  $params = [];

  if ($dtIni !== '') { $where[] = "v.data >= :dtIni"; $params[':dtIni'] = $dtIni; }
  if ($dtFim !== '') { $where[] = "v.data <= :dtFim"; $params[':dtFim'] = $dtFim; }

  $q = trim($q);
  if ($q !== '') {
    $where[] = "(CAST(v.id AS CHAR) LIKE :q OR v.cliente LIKE :q OR v.canal LIKE :q OR v.pagamento LIKE :q OR v.pagamento_mode LIKE :q)";
    $params[':q'] = like_q($q);
  }

  $sql = "
    SELECT
      v.id, v.data, v.cliente, v.canal,
      v.pagamento_mode, v.pagamento,
      v.total, v.created_at
    FROM vendas v
    " . (count($where) ? "WHERE " . implode(" AND ", $where) : "") . "
    ORDER BY v.data DESC, v.id DESC
  ";

  $st = $pdo->prepare($sql);
  $st->execute($params);
  $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

  $head = ["Nº Venda", "Data/Hora", "Cliente", "Entrega", "Pagamento", "Total"];
  $body = [];
  $sum = 0.0;

  foreach ($rows as $r) {
    $id = (int)($r['id'] ?? 0);
    $cliente = trim((string)($r['cliente'] ?? ''));
    if ($cliente === '') $cliente = 'Consumidor Final';

    $total = (float)($r['total'] ?? 0);
    $sum += $total;

    $body[] = [
      '#' . $id,
      br_datetime((string)($r['created_at'] ?? '')),
      $cliente,
      entrega_label((string)($r['canal'] ?? '')),
      pagamento_label((string)($r['pagamento_mode'] ?? ''), (string)($r['pagamento'] ?? '')),
      br_money($total),
    ];
  }

  return [
    'title' => 'Vendas (Resumo)',
    'head' => $head,
    'body' => $body,
    'sum' => $sum,
    'sum_text' => br_money($sum),
    'sum_label' => 'Total vendido',
    'rightCols' => [5],
    'centerCols' => [0, 3, 4],
  ];
}

function report_vendas_itens(PDO $pdo, string $dtIni, string $dtFim, string $q): array {
  // Usa tabela SAIDAS como "itens" (pois não existe venda_itens no seu DDL)
  $where = [];
  $params = [];

  if ($dtIni !== '') { $where[] = "s.data >= :dtIni"; $params[':dtIni'] = $dtIni; }
  if ($dtFim !== '') { $where[] = "s.data <= :dtFim"; $params[':dtFim'] = $dtFim; }

  $q = trim($q);
  if ($q !== '') {
    $where[] = "(
      s.pedido LIKE :q OR s.cliente LIKE :q OR s.canal LIKE :q OR s.pagamento LIKE :q
      OR p.codigo LIKE :q OR p.nome LIKE :q
    )";
    $params[':q'] = like_q($q);
  }

  $sql = "
    SELECT
      s.pedido, s.data, s.cliente, s.canal, s.pagamento,
      s.qtd, s.preco, s.total,
      p.codigo, p.nome AS produto
    FROM saidas s
    INNER JOIN produtos p ON p.id = s.produto_id
    " . (count($where) ? "WHERE " . implode(" AND ", $where) : "") . "
    ORDER BY s.data DESC, s.pedido DESC, p.nome ASC
  ";

  $st = $pdo->prepare($sql);
  $st->execute($params);
  $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

  $head = ["Pedido", "Data", "Cliente", "Canal", "Pagamento", "Código", "Produto", "Qtd", "Unitário", "Total"];
  $body = [];
  $sum = 0.0;

  foreach ($rows as $r) {
    $cliente = trim((string)($r['cliente'] ?? ''));
    if ($cliente === '') $cliente = 'Consumidor Final';

    $total = (float)($r['total'] ?? 0);
    $sum += $total;

    $body[] = [
      (string)($r['pedido'] ?? '—'),
      br_date((string)($r['data'] ?? '')),
      $cliente,
      (string)($r['canal'] ?? '—'),
      (string)($r['pagamento'] ?? '—'),
      (string)($r['codigo'] ?? '—'),
      (string)($r['produto'] ?? '—'),
      br_num($r['qtd'] ?? 0, 3),
      br_money((float)($r['preco'] ?? 0)),
      br_money($total),
    ];
  }

  return [
    'title' => 'Vendas (Itens)',
    'head' => $head,
    'body' => $body,
    'sum' => $sum,
    'sum_text' => br_money($sum),
    'sum_label' => 'Soma dos totais',
    'rightCols' => [8, 9],
    'centerCols' => [7],
  ];
}

function report_produtos(PDO $pdo, string $q): array {
  $where = [];
  $params = [];

  $q = trim($q);
  if ($q !== '') {
    $where[] = "(
      p.codigo LIKE :q OR p.nome LIKE :q OR c.nome LIKE :q OR f.nome LIKE :q
      OR p.unidade LIKE :q OR p.status LIKE :q
    )";
    $params[':q'] = like_q($q);
  }

  $sql = "
    SELECT
      p.codigo, p.nome, p.unidade, p.preco, p.estoque, p.minimo, p.status,
      c.nome AS categoria,
      f.nome AS fornecedor
    FROM produtos p
    LEFT JOIN categorias c ON c.id = p.categoria_id
    LEFT JOIN fornecedores f ON f.id = p.fornecedor_id
    " . (count($where) ? "WHERE " . implode(" AND ", $where) : "") . "
    ORDER BY p.nome ASC
  ";

  $st = $pdo->prepare($sql);
  $st->execute($params);
  $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

  $head = ["Código", "Produto", "Categoria", "Fornecedor", "Unidade", "Preço", "Estoque", "Mínimo", "Status"];
  $body = [];
  $sum = 0.0;

  foreach ($rows as $r) {
    $preco = (float)($r['preco'] ?? 0);
    $sum += $preco;

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
    'sum' => $sum,
    'sum_text' => br_money($sum),
    'sum_label' => 'Soma dos preços',
    'rightCols' => [5],
    'centerCols' => [6, 7, 8],
  ];
}

function report_estoque_minimo(PDO $pdo, string $q): array {
  $where = ["p.estoque < p.minimo"];
  $params = [];

  $q = trim($q);
  if ($q !== '') {
    $where[] = "(p.codigo LIKE :q OR p.nome LIKE :q)";
    $params[':q'] = like_q($q);
  }

  $sql = "
    SELECT p.codigo, p.nome, p.estoque, p.minimo, (p.estoque - p.minimo) AS diff
    FROM produtos p
    WHERE " . implode(" AND ", $where) . "
    ORDER BY diff ASC, p.nome ASC
  ";

  $st = $pdo->prepare($sql);
  $st->execute($params);
  $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

  $head = ["Código", "Produto", "Estoque", "Mínimo", "Diferença"];
  $body = [];
  $sum = 0.0;

  foreach ($rows as $r) {
    $diff = (float)($r['diff'] ?? 0);
    $sum += abs($diff);

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
    'sum' => $sum,
    'sum_text' => br_num($sum, 0),
    'sum_label' => 'Soma do déficit (abs)',
    'rightCols' => [],
    'centerCols' => [2, 3, 4],
  ];
}

function report_devolucoes(PDO $pdo, string $dtIni, string $dtFim, string $q): array {
  $where = [];
  $params = [];

  if ($dtIni !== '') { $where[] = "d.data >= :dtIni"; $params[':dtIni'] = $dtIni; }
  if ($dtFim !== '') { $where[] = "d.data <= :dtFim"; $params[':dtFim'] = $dtFim; }

  $q = trim($q);
  if ($q !== '') {
    $where[] = "(
      CAST(d.id AS CHAR) LIKE :q OR CAST(d.venda_no AS CHAR) LIKE :q OR d.cliente LIKE :q
      OR d.tipo LIKE :q OR d.produto LIKE :q OR d.motivo LIKE :q OR d.status LIKE :q
    )";
    $params[':q'] = like_q($q);
  }

  $sql = "
    SELECT d.id, d.venda_no, d.cliente, d.data, d.hora, d.tipo, d.produto, d.qtd, d.motivo, d.status, d.valor
    FROM devolucoes d
    " . (count($where) ? "WHERE " . implode(" AND ", $where) : "") . "
    ORDER BY d.data DESC, d.hora DESC, d.id DESC
  ";

  $st = $pdo->prepare($sql);
  $st->execute($params);
  $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

  $head = ["ID", "Data/Hora", "Venda", "Cliente", "Tipo", "Produto", "Qtd", "Motivo", "Status", "Valor"];
  $body = [];
  $sum = 0.0;

  foreach ($rows as $r) {
    $cliente = trim((string)($r['cliente'] ?? ''));
    if ($cliente === '') $cliente = 'Consumidor Final';

    $valor = (float)($r['valor'] ?? 0);
    $sum += $valor;

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
    'sum' => $sum,
    'sum_text' => br_money($sum),
    'sum_label' => 'Total devolvido',
    'rightCols' => [9],
    'centerCols' => [0, 2, 4, 6, 8],
  ];
}

function report_entradas(PDO $pdo, string $dtIni, string $dtFim, string $q): array {
  $where = [];
  $params = [];

  if ($dtIni !== '') { $where[] = "e.data >= :dtIni"; $params[':dtIni'] = $dtIni; }
  if ($dtFim !== '') { $where[] = "e.data <= :dtFim"; $params[':dtFim'] = $dtFim; }

  $q = trim($q);
  if ($q !== '') {
    $where[] = "(
      e.nf LIKE :q OR f.nome LIKE :q OR p.codigo LIKE :q OR p.nome LIKE :q OR e.unidade LIKE :q
    )";
    $params[':q'] = like_q($q);
  }

  $sql = "
    SELECT
      e.data, e.nf, e.qtd, e.custo, e.total, e.unidade,
      f.nome AS fornecedor,
      p.codigo, p.nome AS produto
    FROM entradas e
    INNER JOIN fornecedores f ON f.id = e.fornecedor_id
    INNER JOIN produtos p ON p.id = e.produto_id
    " . (count($where) ? "WHERE " . implode(" AND ", $where) : "") . "
    ORDER BY e.data DESC, e.id DESC
  ";

  $st = $pdo->prepare($sql);
  $st->execute($params);
  $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

  $head = ["Data", "NF", "Fornecedor", "Código", "Produto", "Qtd", "Unidade", "Custo", "Total"];
  $body = [];
  $sum = 0.0;

  foreach ($rows as $r) {
    $total = (float)($r['total'] ?? 0);
    $sum += $total;

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
    'sum' => $sum,
    'sum_text' => br_money($sum),
    'sum_label' => 'Total de entradas',
    'rightCols' => [7, 8],
    'centerCols' => [5, 6],
  ];
}

function report_saidas(PDO $pdo, string $dtIni, string $dtFim, string $q): array {
  $where = [];
  $params = [];

  if ($dtIni !== '') { $where[] = "s.data >= :dtIni"; $params[':dtIni'] = $dtIni; }
  if ($dtFim !== '') { $where[] = "s.data <= :dtFim"; $params[':dtFim'] = $dtFim; }

  $q = trim($q);
  if ($q !== '') {
    $where[] = "(
      s.pedido LIKE :q OR s.cliente LIKE :q OR s.canal LIKE :q OR s.pagamento LIKE :q
      OR p.codigo LIKE :q OR p.nome LIKE :q
    )";
    $params[':q'] = like_q($q);
  }

  $sql = "
    SELECT
      s.data, s.pedido, s.cliente, s.canal, s.pagamento,
      s.qtd, s.preco, s.total, s.unidade,
      p.codigo, p.nome AS produto
    FROM saidas s
    INNER JOIN produtos p ON p.id = s.produto_id
    " . (count($where) ? "WHERE " . implode(" AND ", $where) : "") . "
    ORDER BY s.data DESC, s.id DESC
  ";

  $st = $pdo->prepare($sql);
  $st->execute($params);
  $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

  $head = ["Data", "Pedido", "Cliente", "Canal", "Pagamento", "Código", "Produto", "Qtd", "Unid.", "Unitário", "Total"];
  $body = [];
  $sum = 0.0;

  foreach ($rows as $r) {
    $cliente = trim((string)($r['cliente'] ?? ''));
    if ($cliente === '') $cliente = 'Consumidor Final';

    $total = (float)($r['total'] ?? 0);
    $sum += $total;

    $body[] = [
      br_date((string)($r['data'] ?? '')),
      (string)($r['pedido'] ?? '—'),
      $cliente,
      (string)($r['canal'] ?? '—'),
      (string)($r['pagamento'] ?? '—'),
      (string)($r['codigo'] ?? '—'),
      (string)($r['produto'] ?? '—'),
      br_num($r['qtd'] ?? 0, 3),
      (string)($r['unidade'] ?? '—'),
      br_money((float)($r['preco'] ?? 0)),
      br_money($total),
    ];
  }

  return [
    'title' => 'Saídas',
    'head' => $head,
    'body' => $body,
    'sum' => $sum,
    'sum_text' => br_money($sum),
    'sum_label' => 'Total de saídas',
    'rightCols' => [9, 10],
    'centerCols' => [7, 8],
  ];
}

function build_report(PDO $pdo, string $tipo, string $dtIni, string $dtFim, string $q): array {
  $tipo = strtoupper(trim($tipo));

  // whitelist
  $allowed = [
    'VENDAS_RESUMO', 'VENDAS_ITENS', 'PRODUTOS', 'ESTOQUE_MINIMO',
    'DEVOLUCOES', 'ENTRADAS', 'SAIDAS'
  ];
  if (!in_array($tipo, $allowed, true)) $tipo = 'VENDAS_RESUMO';

  if ($tipo === 'VENDAS_RESUMO') return report_vendas_resumo($pdo, $dtIni, $dtFim, $q);
  if ($tipo === 'VENDAS_ITENS')  return report_vendas_itens($pdo, $dtIni, $dtFim, $q);
  if ($tipo === 'PRODUTOS')      return report_produtos($pdo, $q);
  if ($tipo === 'ESTOQUE_MINIMO')return report_estoque_minimo($pdo, $q);
  if ($tipo === 'DEVOLUCOES')    return report_devolucoes($pdo, $dtIni, $dtFim, $q);
  if ($tipo === 'ENTRADAS')      return report_entradas($pdo, $dtIni, $dtFim, $q);
  if ($tipo === 'SAIDAS')        return report_saidas($pdo, $dtIni, $dtFim, $q);

  return report_vendas_resumo($pdo, $dtIni, $dtFim, $q);
}

/* =========================
   AJAX endpoint
========================= */

if (isset($_GET['action']) && $_GET['action'] === 'fetch') {
  header('Content-Type: application/json; charset=utf-8');

  try {
    $pdo = db();

    $tipo  = (string)($_GET['tipo'] ?? 'VENDAS_RESUMO');
    $dtIni = iso_date_or_empty((string)($_GET['dt_ini'] ?? ''));
    $dtFim = iso_date_or_empty((string)($_GET['dt_fim'] ?? ''));
    $q     = (string)($_GET['q'] ?? '');

    $rep = build_report($pdo, $tipo, $dtIni, $dtFim, $q);

    echo json_encode([
      'ok' => true,
      'report' => $rep
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

  } catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
      'ok' => false,
      'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
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

  <!-- ========== CSS ========= -->
  <link rel="stylesheet" href="assets/css/bootstrap.min.css" />
  <link rel="stylesheet" href="assets/css/lineicons.css" rel="stylesheet" type="text/css" />
  <link rel="stylesheet" href="assets/css/materialdesignicons.min.css" rel="stylesheet" type="text/css" />
  <link rel="stylesheet" href="assets/css/main.css" />

  <style>
    /* dropdown do profile: largura acompanha conteúdo */
    .profile-box .dropdown-menu { width: max-content; min-width: 260px; max-width: calc(100vw - 24px); }
    .profile-box .dropdown-menu .author-info { width: max-content; max-width: 100%; display: flex !important; align-items: center; gap: 10px; }
    .profile-box .dropdown-menu .author-info .content { min-width: 0; max-width: 100%; }
    .profile-box .dropdown-menu .author-info .content a { display: inline-block; white-space: nowrap; max-width: 100%; }

    /* Botões compactos */
    .main-btn.btn-compact { height: 38px !important; padding: 8px 14px !important; font-size: 13px !important; line-height: 1 !important; }
    .main-btn.btn-compact i { font-size: 14px; vertical-align: -1px; }

    .icon-btn { height: 34px !important; width: 42px !important; padding: 0 !important; display: inline-flex !important; align-items: center !important; justify-content: center !important; }
    .form-control.compact, .form-select.compact { height: 38px; padding: 8px 12px; font-size: 13px; }
    .muted { font-size: 12px; color: #64748b; }

    /* Cards */
    .cardx { border: 1px solid rgba(148, 163, 184, .28); border-radius: 16px; background: #fff; overflow: hidden; }
    .cardx .head { padding: 12px 14px; border-bottom: 1px solid rgba(148, 163, 184, .22); display: flex; align-items: center; justify-content: space-between; gap: 10px; flex-wrap: wrap; }
    .cardx .body { padding: 14px; }

    /* ✅ para Atalhos e Prévia ficarem com o MESMO height */
    .cardx.fill { height: 100%; display: flex; flex-direction: column; }
    .cardx.fill .body { flex: 1 1 auto; }

    /* Pills */
    .pill { padding: 6px 10px; border-radius: 999px; border: 1px solid rgba(148, 163, 184, .25); font-weight: 900; font-size: 12px; display: inline-flex; align-items: center; gap: 8px; background: rgba(248, 250, 252, .7); white-space: nowrap; }
    .pill.primary { border-color: rgba(37, 99, 235, .28); background: rgba(239, 246, 255, .75); color: #0b5ed7; }
    .pill.ok { border-color: rgba(34, 197, 94, .25); background: rgba(240, 253, 244, .9); color: #166534; }
    .pill.warn { border-color: rgba(245, 158, 11, .28); background: rgba(255, 251, 235, .9); color: #92400e; }
    .pill.bad { border-color: rgba(239, 68, 68, .25); background: rgba(254, 242, 242, .9); color: #991b1b; }

    /* ✅ Filtros alinhados */
    .filters-row .form-label { margin-bottom: 6px; }
    .filters-actions { display: flex; gap: 8px; flex-wrap: wrap; justify-content: flex-end; }

    /* Tabela */
    .table td, .table th { vertical-align: middle; }
    .table-responsive { -webkit-overflow-scrolling: touch; }

    #tbRel { width: 100%; min-width: 980px; }
    #tbRel th, #tbRel td { white-space: nowrap !important; }

    /* ✅ Prévia */
    .rel-table-wrap { flex: 1 1 auto; min-height: 260px; }
    .box-tot { border: 1px solid rgba(148, 163, 184, .25); border-radius: 14px; background: #fff; padding: 12px; margin-top: auto !important; }
    .tot-row { display: flex; justify-content: space-between; align-items: center; gap: 10px; font-size: 13px; color: #334155; margin-bottom: 8px; font-weight: 900; }
    .tot-row:last-child { margin-bottom: 0; }
    .tot-hr { height: 1px; background: rgba(148, 163, 184, .22); margin: 10px 0; }

    .grand { display: flex; justify-content: space-between; align-items: baseline; gap: 10px; margin-top: 4px; }
    .grand .lbl { font-weight: 1000; color: #0f172a; font-size: 16px; }
    .grand .val { font-weight: 1000; color: #0b5ed7; font-size: 26px; letter-spacing: .2px; }

    /* Atalhos */
    .quick-grid { display: grid; grid-template-columns: 1fr; gap: 10px; }
    .quick { border: 1px solid rgba(148, 163, 184, .25); border-radius: 14px; padding: 12px; background: rgba(248, 250, 252, .6); cursor: pointer; transition: .12s ease; }
    .quick:hover { transform: translateY(-1px); box-shadow: 0 10px 22px rgba(15, 23, 42, .08); background: rgba(239, 246, 255, .65); border-color: rgba(37, 99, 235, .30); }
    .quick .t { font-weight: 1000; color: #0f172a; font-size: 13px; margin-bottom: 4px; }
    .quick .d { font-size: 12px; color: #64748b; margin-bottom: 8px; }
    .quick .tag { display: inline-flex; align-items: center; gap: 6px; font-size: 11px; font-weight: 900; color: #0b5ed7; background: rgba(239, 246, 255, .9); border: 1px solid rgba(37, 99, 235, .22); padding: 5px 10px; border-radius: 999px; }

    @media (max-width: 991.98px) {
      #tbRel { min-width: 900px; }
      .grand .val { font-size: 22px; }
      .filters-actions { justify-content: flex-start; }
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
        <!-- Dashboard -->
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

        <!-- Operações -->
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
            <li><a href="vendidos.php">Vendidos</a></li>
            <li><a href="vendas.php">Vendas</a></li>
            <li><a href="devolucoes.php">Devoluções</a></li>
          </ul>
        </li>

        <!-- Estoque -->
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

        <!-- Cadastros -->
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

        <!-- Relatórios ativo -->
        <li class="nav-item active">
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
                  <input type="text" placeholder="Buscar no relatório..." id="qGlobal" />
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
                      <div class="image">
                        <img src="assets/images/profile/profile-image.png" alt="image" />
                      </div>
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

    <section class="section">
      <div class="container-fluid">
        <div class="title-wrapper pt-30">
          <div class="row align-items-center">
            <div class="col-md-8">
              <div class="title">
                <h2>Relatórios</h2>
                <div class="muted">Filtros alinhados • Atalhos e Prévia com o mesmo height.</div>
              </div>
            </div>
          </div>
        </div>

        <!-- FILTROS (ALINHADOS) -->
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
                  <button class="main-btn light-btn btn-hover btn-compact" id="btnPDF" type="button">
                    <i class="lni lni-printer me-1"></i> PDF
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
                <div class="muted mt-1">* Os dados deste relatório vêm do banco MySQL.</div>
              </div>

              <div class="col-12">
                <label class="form-label">Busca (filtro extra)</label>
                <input class="form-control compact" id="qRel" placeholder="Cliente, pedido, código, produto..." />
              </div>
            </div>
          </div>
        </div>

        <!-- ✅ ATALHOS + PRÉVIA (MESMO HEIGHT) -->
        <div class="row g-3 mb-30 align-items-stretch">
          <!-- Atalhos -->
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
                    <div class="d">Lista de vendas com total, cliente, pagamento e entrega.</div>
                    <div class="tag"><i class="lni lni-cart"></i> Operações</div>
                  </div>
                  <div class="quick" data-quick="VENDAS_ITENS">
                    <div class="t">Vendas (Itens)</div>
                    <div class="d">Itens (usa a tabela Saídas como detalhamento).</div>
                    <div class="tag"><i class="lni lni-list"></i> Detalhado</div>
                  </div>
                  <div class="quick" data-quick="ESTOQUE_MINIMO">
                    <div class="t">Estoque Mínimo</div>
                    <div class="d">Itens abaixo do mínimo (estoque/minimo).</div>
                    <div class="tag"><i class="lni lni-warning"></i> Estoque</div>
                  </div>
                  <div class="quick" data-quick="DEVOLUCOES">
                    <div class="t">Devoluções</div>
                    <div class="d">Lista devoluções com status e valores.</div>
                    <div class="tag"><i class="lni lni-package"></i> Pós-venda</div>
                  </div>
                </div>

                <div class="muted mt-3">
                  Dica: use <b>datas</b> para reduzir o relatório e facilitar exportação.
                </div>
              </div>
            </div>
          </div>

          <!-- Prévia -->
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
                </div>

                <div class="box-tot mt-3">
                  <div class="tot-row"><span>Linhas</span><span id="tRows">0</span></div>
                  <div class="tot-row"><span>Somatório</span><span id="tSum" style="font-weight:1000;color:#0b5ed7;">—</span></div>
                  <div class="tot-hr"></div>
                  <div class="grand">
                    <span class="lbl">TOTAL</span>
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

  <!-- ========= JS ========= -->
  <script src="assets/js/bootstrap.bundle.min.js"></script>
  <script src="assets/js/main.js"></script>

  <!-- jsPDF + AutoTable -->
  <script src="https://cdn.jsdelivr.net/npm/jspdf@2.5.1/dist/jspdf.umd.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/jspdf-autotable@3.8.2/dist/jspdf.plugin.autotable.min.js"></script>

  <script>
    // ==============================
    // Helpers
    // ==============================
    function safeText(s) {
      return String(s ?? "")
        .replaceAll("&", "&amp;")
        .replaceAll("<", "&lt;")
        .replaceAll(">", "&gt;")
        .replaceAll('"', "&quot;")
        .replaceAll("'", "&#039;");
    }

    function numberToMoney(n) {
      const v = Number(n || 0);
      const s = v.toFixed(2).replace(".", ",");
      // separador de milhar simples
      const parts = s.split(",");
      parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ".");
      return "R$ " + parts.join(",");
    }

    function setInfo(type, ok = true) {
      pillInfo.className = ok ? "pill primary" : "pill bad";
      pillInfo.innerHTML = ok
        ? `<i class="lni lni-bolt"></i> ${safeText(type)}`
        : `<i class="lni lni-warning"></i> ${safeText(type)}`;
    }

    // ==============================
    // DOM
    // ==============================
    const qGlobal = document.getElementById("qGlobal");

    const rTipo = document.getElementById("rTipo");
    const dtIni = document.getElementById("dtIni");
    const dtFim = document.getElementById("dtFim");
    const qRel = document.getElementById("qRel");

    const btnGerar = document.getElementById("btnGerar");
    const btnPDF = document.getElementById("btnPDF");
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

    // ==============================
    // Estado
    // ==============================
    let CURRENT = {
      title: "Relatório",
      head: [],
      body: [],
      sum: 0,
      sum_text: "—",
      sum_label: "Somatório",
      rightCols: [],
      centerCols: []
    };

    // ==============================
    // Fetch report (MySQL)
    // ==============================
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
        q
      });

      const res = await fetch("relatorios.php?" + params.toString(), {
        headers: { "Accept": "application/json" }
      });

      const json = await res.json().catch(() => null);
      if (!json || !json.ok) {
        const msg = (json && json.error) ? json.error : "Falha ao carregar relatório.";
        throw new Error(msg);
      }

      return json.report;
    }

    // ==============================
    // Render
    // ==============================
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

      const count = (rep.body || []).length;
      hintNone.style.display = count ? "none" : "block";

      pillCount.innerHTML = `<i class="lni lni-checkmark-circle"></i> ${count} linhas`;
      tRows.textContent = String(count);

      // somatório (já vem formatado do PHP em sum_text)
      const sumText = rep.sum_text || numberToMoney(rep.sum || 0);
      tSum.textContent = sumText;
      tGrand.textContent = sumText;

      tNote.textContent = `* ${rep.sum_label || "Somatório"}.`;
      setInfo(rep.title || "Relatório", true);
    }

    // ==============================
    // Export
    // ==============================
    function exportExcel() {
      const rep = CURRENT;
      if (!rep || !rep.head || !rep.body) { alert("Gere um relatório primeiro."); return; }

      const now = new Date();
      const dt = now.toLocaleDateString("pt-BR") + " " + now.toLocaleTimeString("pt-BR");
      const periodo = pillPeriod.textContent.replace("Período:", "").trim() || "—";

      const right = new Set((rep.rightCols || []).map(n => Number(n)));
      const center = new Set((rep.centerCols || []).map(n => Number(n)));

      let html = `
        <html><head><meta charset="utf-8">
        <style>
          table { border: 0.6px solid #999; font-family: Arial; font-size: 12px; }
          td, th { border: 1px solid #999; padding: 6px 8px; vertical-align: middle; }
          th { background: #f1f5f9; font-weight: 700; }
          .title { font-size: 16px; font-weight: 700; background: #eef2ff; text-align: center; }
          .muted { color: #555; font-weight: 700; }
          .right { text-align: right; }
          .center { text-align: center; }
          .spacer td { border: none; padding: 4px; }
        </style></head><body><table>
      `;

      const colN = rep.head.length;

      html += `<tr><td class="title" colspan="${colN}">PAINEL DA DISTRIBUIDORA - ${safeText(String(rep.title || "RELATÓRIO").toUpperCase())}</td></tr>`;
      html += `<tr><td class="muted">Gerado em:</td><td colspan="${colN - 1}">${safeText(dt)}</td></tr>`;
      html += `<tr><td class="muted">Período:</td><td colspan="${colN - 1}">${safeText(periodo)}</td></tr>`;
      html += `<tr><td class="muted">Somatório:</td><td colspan="${colN - 1}">${safeText(rep.sum_text || "—")} (${safeText(rep.sum_label || "")})</td></tr>`;
      html += `<tr class="spacer"><td colspan="${colN}"></td></tr>`;

      html += `<tr>${rep.head.map((h, idx) => {
        const cls = right.has(idx) ? "right" : (center.has(idx) ? "center" : "");
        return `<th class="${cls}">${safeText(h)}</th>`;
      }).join("")}</tr>`;

      (rep.body || []).forEach(row => {
        html += `<tr>${(row || []).map((c, idx) => {
          const cls = right.has(idx) ? "right" : (center.has(idx) ? "center" : "");
          return `<td class="${cls}">${safeText(c)}</td>`;
        }).join("")}</tr>`;
      });

      html += `</table></body></html>`;

      const blob = new Blob(["\ufeff" + html], { type: "application/vnd.ms-excel;charset=utf-8;" });
      const url = URL.createObjectURL(blob);
      const a = document.createElement("a");
      a.href = url;
      a.download = `relatorio_${String(rep.title || "relatorio").toLowerCase().replace(/\s+/g, "_")}.xls`;
      document.body.appendChild(a);
      a.click();
      a.remove();
      URL.revokeObjectURL(url);
    }

    function exportPDF() {
      const rep = CURRENT;
      if (!rep || !rep.head || !rep.body) { alert("Gere um relatório primeiro."); return; }

      if (!window.jspdf || !window.jspdf.jsPDF) {
        alert("Biblioteca do PDF não carregou.");
        return;
      }

      const now = new Date();
      const dt = now.toLocaleDateString("pt-BR") + " " + now.toLocaleTimeString("pt-BR");
      const periodo = pillPeriod.textContent.replace("Período:", "").trim() || "—";

      const right = new Set((rep.rightCols || []).map(n => Number(n)));
      const center = new Set((rep.centerCols || []).map(n => Number(n)));

      const { jsPDF } = window.jspdf;
      const doc = new jsPDF({ orientation: "landscape", unit: "pt", format: "a4" });

      const M = 70;
      doc.setTextColor(0, 0, 0);
      doc.setFont("helvetica", "bold");
      doc.setFontSize(14);
      doc.text(`PAINEL DA DISTRIBUIDORA - ${String(rep.title || "RELATÓRIO").toUpperCase()}`, M, 55);

      doc.setFont("helvetica", "normal");
      doc.setFontSize(10);
      doc.text(`Gerado em: ${dt}`, M, 75);
      doc.text(`Período: ${periodo}`, M, 92);
      doc.text(`Somatório: ${rep.sum_text || "—"} (${rep.sum_label || ""})`, M, 108);

      doc.autoTable({
        head: [rep.head],
        body: rep.body,
        startY: 130,
        margin: { left: M, right: M },
        theme: "plain",
        styles: {
          font: "helvetica",
          fontSize: 9,
          textColor: [17, 24, 39],
          cellPadding: { top: 6, right: 6, bottom: 6, left: 6 },
          lineWidth: 0
        },
        headStyles: {
          fillColor: [241, 245, 249],
          textColor: [17, 24, 39],
          fontStyle: "bold",
          lineWidth: 0
        },
        alternateRowStyles: { fillColor: [248, 250, 252] },
        didParseCell: function (data) {
          const col = data.column.index;
          if (right.has(col)) data.cell.styles.halign = "right";
          if (center.has(col)) data.cell.styles.halign = "center";
          data.cell.styles.lineWidth = 0;
        }
      });

      doc.save(`relatorio_${String(rep.title || "relatorio").toLowerCase().replace(/\s+/g, "_")}.pdf`);
    }

    // ==============================
    // Gerar
    // ==============================
    async function gerar() {
      setInfo("CARREGANDO...", true);
      try {
        const rep = await fetchReport();
        renderTable(rep);
      } catch (e) {
        setInfo("ERRO AO GERAR", false);
        renderTable({
          title: "Erro",
          head: ["Mensagem"],
          body: [[String(e && e.message ? e.message : e)]],
          sum: 0,
          sum_text: "—",
          sum_label: "Somatório",
          rightCols: [],
          centerCols: []
        });
      }
    }

    // ==============================
    // Eventos
    // ==============================
    btnGerar.addEventListener("click", gerar);
    btnExcel.addEventListener("click", exportExcel);
    btnPDF.addEventListener("click", exportPDF);

    btnLimpar.addEventListener("click", () => {
      rTipo.value = "VENDAS_RESUMO";
      dtIni.value = "";
      dtFim.value = "";
      qRel.value = "";
      qGlobal.value = "";
      gerar();
    });

    qGlobal.addEventListener("input", () => {
      qRel.value = qGlobal.value;
      gerar();
    });

    qRel.addEventListener("keydown", (e) => {
      if (e.key === "Enter") { e.preventDefault(); gerar(); }
    });

    document.querySelectorAll(".quick").forEach(el => {
      el.addEventListener("click", () => {
        const t = el.getAttribute("data-quick");
        if (t) { rTipo.value = t; gerar(); }
      });
    });

    // ==============================
    // Init
    // ==============================
    gerar();
  </script>
</body>

</html>