<?php
declare(strict_types=1);

/**
 * vendidos.php (FUNCIONAL)
 * - Base: tabela `saidas` (itens vendidos) + `produtos` + `categorias`
 * - Tela: filtros + tabela (agrupado por produto) + totais
 * - Exportação (IGUAL ao seu inventario.php):
 *    - Excel (HTML .xls via Blob)
 *    - PDF (jsPDF + autoTable)
 * - AJAX:
 *    - action=suggest (autocomplete de produto)
 *    - action=detalhes (modal com últimas saídas do produto)
 *
 * Requisitos:
 * - db():PDO em assets/conexao.php (ou ajuste)
 * - (opcional) _helpers.php (csrf_token, flash, e etc)
 */

@date_default_timezone_set('America/Manaus');
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

/* =========================
   INCLUDES (helpers / conexão)
========================= */
$helpers = __DIR__ . '/assets/dados/inventario/_helpers.php';
if (is_file($helpers)) require_once $helpers;

$possibleConn = [
  __DIR__ . '/assets/conexao.php',
  __DIR__ . '/assets/php/conexao.php',
];
foreach ($possibleConn as $f) {
  if (is_file($f)) { require_once $f; break; }
}

if (!function_exists('db')) {
  http_response_code(500);
  echo "ERRO: função db():PDO não encontrada. Ajuste o include da conexão em vendidos.php.";
  exit;
}

/* =========================
   FALLBACK HELPERS (se não existir no seu _helpers.php)
========================= */
if (!function_exists('e')) {
  function e(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
  }
}
if (!function_exists('csrf_token')) {
  function csrf_token(): string {
    if (empty($_SESSION['_csrf'])) $_SESSION['_csrf'] = bin2hex(random_bytes(16));
    return (string)$_SESSION['_csrf'];
  }
}
if (!function_exists('csrf_validate')) {
  function csrf_validate(?string $token): bool {
    $sess = (string)($_SESSION['_csrf'] ?? '');
    return $token !== null && $sess !== '' && hash_equals($sess, $token);
  }
}

/* =========================
   UTIL
========================= */
function brl($v): string {
  $n = (float)$v;
  return 'R$ ' . number_format($n, 2, ',', '.');
}
function nfmt($v, int $dec = 0): string {
  return number_format((float)$v, $dec, ',', '.');
}
function ymd_or(string $s, string $fallback): string {
  $dt = DateTime::createFromFormat('Y-m-d', $s);
  if ($dt && $dt->format('Y-m-d') === $s) return $s;
  return $fallback;
}

/* =========================
   PDO
========================= */
$pdo = db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

/* =========================
   INPUTS / FILTROS
========================= */
$csrf = csrf_token();

$today = date('Y-m-d');
$firstMonth = date('Y-m-01');

$ini = ymd_or((string)($_GET['ini'] ?? $firstMonth), $firstMonth);
$fim = ymd_or((string)($_GET['fim'] ?? $today), $today);
if ($ini > $fim) { $tmp = $ini; $ini = $fim; $fim = $tmp; }

$categoria = (int)($_GET['categoria'] ?? 0);

$canal = strtoupper(trim((string)($_GET['canal'] ?? '')));
if (!in_array($canal, ['', 'PRESENCIAL', 'DELIVERY'], true)) $canal = '';

$q = trim((string)($_GET['q'] ?? ''));
$produto_id = (int)($_GET['produto_id'] ?? 0);

$page = max(1, (int)($_GET['page'] ?? 1));
$limit = (int)($_GET['limit'] ?? 50);
if ($limit < 20) $limit = 20;
if ($limit > 200) $limit = 200;
$offset = ($page - 1) * $limit;

/* =========================
   CONDIÇÕES SQL (seguras)
========================= */
$conds = [];
$params = [];

$conds[] = "s.data BETWEEN :ini AND :fim";
$params[':ini'] = $ini;
$params[':fim'] = $fim;

if ($categoria > 0) {
  $conds[] = "p.categoria_id = :cat";
  $params[':cat'] = $categoria;
}
if ($canal !== '') {
  $conds[] = "UPPER(s.canal) = :canal";
  $params[':canal'] = $canal;
}
if ($produto_id > 0) {
  $conds[] = "p.id = :pid";
  $params[':pid'] = $produto_id;
}
if ($q !== '') {
  $conds[] = "(p.nome LIKE :like OR p.codigo LIKE :like OR s.cliente LIKE :like OR s.pedido LIKE :like)";
  $params[':like'] = '%' . $q . '%';
}

$where = $conds ? ('WHERE ' . implode(' AND ', $conds)) : '';

/* =========================
   AJAX: SUGGEST (autocomplete)
========================= */
$action = (string)($_GET['action'] ?? '');
if ($action === 'suggest') {
  header('Content-Type: application/json; charset=UTF-8');
  try {
    $term = trim((string)($_GET['term'] ?? ''));
    if (mb_strlen($term) < 1) {
      echo json_encode(['ok'=>true, 'items'=>[]], JSON_UNESCAPED_UNICODE);
      exit;
    }
    $like = '%' . $term . '%';

    $st = $pdo->prepare("
      SELECT p.id, p.codigo, p.nome, COALESCE(c.nome,'-') AS categoria, COALESCE(p.unidade,'-') AS unidade
      FROM produtos p
      LEFT JOIN categorias c ON c.id = p.categoria_id
      WHERE (p.nome LIKE :like OR p.codigo LIKE :like)
      ORDER BY p.nome ASC
      LIMIT 12
    ");
    $st->execute([':like'=>$like]);

    echo json_encode(['ok'=>true, 'items'=>$st->fetchAll()], JSON_UNESCAPED_UNICODE);
    exit;
  } catch (Throwable $ex) {
    http_response_code(400);
    echo json_encode(['ok'=>false, 'error'=>$ex->getMessage()], JSON_UNESCAPED_UNICODE);
    exit;
  }
}

/* =========================
   AJAX: DETALHES (modal)
========================= */
if ($action === 'detalhes') {
  header('Content-Type: application/json; charset=UTF-8');
  try {
    $pid = (int)($_GET['id'] ?? 0);
    if ($pid <= 0) throw new RuntimeException("Produto inválido.");

    $dIni = ymd_or((string)($_GET['ini'] ?? $ini), $ini);
    $dFim = ymd_or((string)($_GET['fim'] ?? $fim), $fim);
    if ($dIni > $dFim) { $tmp = $dIni; $dIni = $dFim; $dFim = $tmp; }

    $dCanal = strtoupper(trim((string)($_GET['canal'] ?? '')));
    if (!in_array($dCanal, ['', 'PRESENCIAL', 'DELIVERY'], true)) $dCanal = '';

    // Cabeçalho do produto
    $st = $pdo->prepare("
      SELECT p.id, p.codigo, p.nome, COALESCE(c.nome,'-') categoria, COALESCE(p.unidade,'-') unidade
      FROM produtos p
      LEFT JOIN categorias c ON c.id = p.categoria_id
      WHERE p.id = :id
      LIMIT 1
    ");
    $st->execute([':id'=>$pid]);
    $prod = $st->fetch();
    if (!$prod) throw new RuntimeException("Produto não encontrado.");

    // Linhas de saída do período (com canal opcional)
    $conds2 = ["s.produto_id = :pid", "s.data BETWEEN :ini AND :fim"];
    $p2 = [':pid'=>$pid, ':ini'=>$dIni, ':fim'=>$dFim];
    if ($dCanal !== '') {
      $conds2[] = "UPPER(s.canal) = :canal";
      $p2[':canal'] = $dCanal;
    }
    $w2 = "WHERE " . implode(" AND ", $conds2);

    $st2 = $pdo->prepare("
      SELECT s.id, s.data, s.pedido, s.cliente, s.canal, s.pagamento,
             s.qtd, s.preco, s.total
      FROM saidas s
      $w2
      ORDER BY s.data DESC, s.id DESC
      LIMIT 80
    ");
    $st2->execute($p2);
    $linhas = $st2->fetchAll();

    // Totais do produto no período
    $st3 = $pdo->prepare("
      SELECT COALESCE(SUM(qtd),0) qtd, COALESCE(SUM(total),0) total, COUNT(DISTINCT pedido) pedidos
      FROM saidas s
      $w2
    ");
    $st3->execute($p2);
    $tot = $st3->fetch() ?: ['qtd'=>0,'total'=>0,'pedidos'=>0];

    echo json_encode(['ok'=>true, 'produto'=>$prod, 'totais'=>$tot, 'linhas'=>$linhas], JSON_UNESCAPED_UNICODE);
    exit;
  } catch (Throwable $ex) {
    http_response_code(400);
    echo json_encode(['ok'=>false, 'error'=>$ex->getMessage()], JSON_UNESCAPED_UNICODE);
    exit;
  }
}

/* =========================
   DADOS: categorias (select)
========================= */
$cats = [];
try {
  $st = $pdo->query("SELECT id, nome, status FROM categorias ORDER BY nome ASC");
  $cats = $st->fetchAll();
} catch (Throwable $ex) {
  $cats = [];
}

/* =========================
   QUERY PRINCIPAL (agrupado por produto)
========================= */
$sqlCount = "
  SELECT COUNT(*) AS n
  FROM (
    SELECT p.id
    FROM saidas s
    JOIN produtos p ON p.id = s.produto_id
    LEFT JOIN categorias c ON c.id = p.categoria_id
    $where
    GROUP BY p.id
  ) t
";
$st = $pdo->prepare($sqlCount);
$st->execute($params);
$totalGroups = (int)($st->fetch()['n'] ?? 0);
$totalPages = max(1, (int)ceil($totalGroups / $limit));

$sql = "
  SELECT
    p.id,
    p.codigo,
    p.nome,
    COALESCE(c.nome,'-') AS categoria,
    COALESCE(p.unidade,'-') AS unidade,
    COALESCE(SUM(s.qtd),0) AS qtd_vendida,
    COALESCE(AVG(s.preco),0) AS preco_medio,
    COALESCE(SUM(s.total),0) AS total_vendido,
    COUNT(DISTINCT s.pedido) AS pedidos,
    MAX(s.data) AS ultima_data
  FROM saidas s
  JOIN produtos p ON p.id = s.produto_id
  LEFT JOIN categorias c ON c.id = p.categoria_id
  $where
  GROUP BY p.id, p.codigo, p.nome, c.nome, p.unidade
  ORDER BY total_vendido DESC, qtd_vendida DESC, p.nome ASC
  LIMIT $limit OFFSET $offset
";
$st = $pdo->prepare($sql);
$st->execute($params);
$rows = $st->fetchAll();

/* =========================
   TOTAIS (no período, sem agrupar)
========================= */
$st = $pdo->prepare("
  SELECT
    COALESCE(SUM(s.qtd),0) AS qtd,
    COALESCE(SUM(s.total),0) AS total,
    COUNT(*) AS linhas,
    COUNT(DISTINCT s.pedido) AS pedidos,
    COUNT(DISTINCT s.produto_id) AS produtos
  FROM saidas s
  JOIN produtos p ON p.id = s.produto_id
  LEFT JOIN categorias c ON c.id = p.categoria_id
  $where
");
$st->execute($params);
$tot = $st->fetch() ?: ['qtd'=>0,'total'=>0,'linhas'=>0,'pedidos'=>0,'produtos'=>0];
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
    .main-btn.btn-compact i { font-size: 14px; vertical-align: -1px }
    .icon-btn { height: 34px !important; width: 42px !important; padding: 0 !important; display: inline-flex !important; align-items: center !important; justify-content: center !important }
    .form-control.compact, .form-select.compact { height: 38px; padding: 8px 12px; font-size: 13px }

    .cardx { border: 1px solid rgba(148, 163, 184, .28); border-radius: 16px; background: #fff; overflow: hidden }
    .cardx .head { padding: 12px 14px; border-bottom: 1px solid rgba(148, 163, 184, .22); display: flex; align-items: center; justify-content: space-between; gap: 10px; flex-wrap: wrap }
    .cardx .body { padding: 14px }
    .muted { font-size: 12px; color: #64748b }

    .table td, .table th { vertical-align: middle }
    .table-responsive { -webkit-overflow-scrolling: touch }
    #tbDev { width: 100%; min-width: 1180px }
    #tbDev th, #tbDev td { white-space: nowrap !important }

    .mini { font-size: 12px; color: #475569; font-weight: 800 }
    .money { font-weight: 1000; color: #0b5ed7 }

    .box-tot { border: 1px solid rgba(148, 163, 184, .25); border-radius: 14px; background: #fff; padding: 12px }
    .tot-row { display: flex; justify-content: space-between; align-items: center; gap: 10px; font-size: 13px; color: #334155; margin-bottom: 8px; font-weight: 900 }
    .tot-hr { height: 1px; background: rgba(148, 163, 184, .22); margin: 10px 0 }
    .grand { display: flex; justify-content: space-between; align-items: baseline; gap: 10px; margin-top: 4px }
    .grand .lbl { font-weight: 1000; color: #0f172a; font-size: 16px }
    .grand .val { font-weight: 1000; color: #0b5ed7; font-size: 26px; letter-spacing: .2px }

    .toolbar { display: flex; gap: 10px; flex-wrap: wrap; align-items: end }
    .toolbar .grow { flex: 1 1 260px; min-width: 240px }
    .toolbar .w180 { min-width: 180px }

    .search-wrap { position: relative }
    .suggest { position: absolute; z-index: 9999; left: 0; right: 0; top: calc(100% + 6px); background: #fff;
      border: 1px solid rgba(148, 163, 184, .25); border-radius: 14px; box-shadow: 0 10px 30px rgba(15, 23, 42, .10);
      max-height: 280px; overflow: auto; display: none }
    .suggest .it { padding: 10px 12px; cursor: pointer; display: flex; justify-content: space-between; gap: 10px }
    .suggest .it:hover { background: rgba(241, 245, 249, .9) }
    .suggest .t { font-weight: 900; font-size: 12px; color: #0f172a; white-space: nowrap; overflow: hidden; text-overflow: ellipsis }
    .suggest .s { font-size: 12px; color: #64748b; white-space: nowrap }

    /* Modal detalhes */
    .sale-box { border: 1px solid rgba(148, 163, 184, .22); border-radius: 14px; background: rgba(248, 250, 252, .7);
      padding: 10px 12px; max-height: 260px; overflow: auto; -webkit-overflow-scrolling: touch; }
    .sale-row { display: flex; justify-content: space-between; gap: 10px; padding: 8px 0;
      border-bottom: 1px dashed rgba(148, 163, 184, .35); font-size: 12px; }
    .sale-row:last-child { border-bottom: none }
    .sale-row .left { min-width: 0 }
    .sale-row .left .nm { font-weight: 900; color: #0f172a; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 420px }
    .sale-row .left .cd { color: #64748b; font-size: 12px }
    .sale-row .right { white-space: nowrap; text-align: right }
    .sale-mini { font-size: 12px; color: #64748b; margin-top: 8px; display: flex; justify-content: space-between; gap: 10px }

    @media(max-width:991.98px) { #tbDev { min-width: 980px } .grand .val { font-size: 22px } }
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
                <form action="vendidos.php" method="get">
                  <input type="text" placeholder="Buscar..." id="qGlobal" name="q" value="<?= e($q) ?>" />
                  <input type="hidden" name="ini" value="<?= e($ini) ?>">
                  <input type="hidden" name="fim" value="<?= e($fim) ?>">
                  <input type="hidden" name="categoria" value="<?= (int)$categoria ?>">
                  <input type="hidden" name="canal" value="<?= e($canal) ?>">
                  <button type="submit"><i class="lni lni-search-alt"></i></button>
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
            <div class="col-md-6">
              <div class="title">
                <h2>Vendidos (Produtos)</h2>
                <p class="muted mb-0">Relatório por produto (base: <b>saidas</b>)</p>
              </div>
            </div>
            <div class="col-md-6 d-flex justify-content-md-end mt-3 mt-md-0 gap-2 flex-wrap">
              <button class="main-btn light-btn btn-hover btn-compact" id="btnExcel" type="button">
                <i class="lni lni-download me-1"></i> Excel
              </button>
              <button class="main-btn light-btn btn-hover btn-compact" id="btnPDF" type="button">
                <i class="lni lni-printer me-1"></i> PDF
              </button>
            </div>
          </div>
        </div>

        <div class="row g-3">
          <div class="col-lg-8">

            <!-- Filtros -->
            <div class="cardx">
              <div class="head">
                <div>
                  <div class="fw-700">Filtros</div>
                  <div class="muted">Período, categoria, canal e produto</div>
                </div>
              </div>
              <div class="body">
                <form class="toolbar" method="get" action="vendidos.php" autocomplete="off">
                  <div class="w180">
                    <label class="mini mb-1">Data inicial</label>
                    <input class="form-control compact" type="date" name="ini" id="fIni" value="<?= e($ini) ?>">
                  </div>

                  <div class="w180">
                    <label class="mini mb-1">Data final</label>
                    <input class="form-control compact" type="date" name="fim" id="fFim" value="<?= e($fim) ?>">
                  </div>

                  <div class="w180">
                    <label class="mini mb-1">Categoria</label>
                    <select class="form-select compact" name="categoria" id="fCategoria">
                      <option value="0" <?= $categoria===0?'selected':''; ?>>Todas</option>
                      <?php foreach ($cats as $c): ?>
                        <option value="<?= (int)$c['id'] ?>" <?= $categoria===(int)$c['id']?'selected':''; ?>>
                          <?= e((string)$c['nome']) ?><?= (strtoupper((string)($c['status'] ?? '')) === 'INATIVO' ? ' (INATIVO)' : '') ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                  </div>

                  <div class="w180">
                    <label class="mini mb-1">Canal</label>
                    <select class="form-select compact" name="canal" id="fCanal">
                      <option value="" <?= $canal===''?'selected':''; ?>>Todos</option>
                      <option value="PRESENCIAL" <?= $canal==='PRESENCIAL'?'selected':''; ?>>PRESENCIAL</option>
                      <option value="DELIVERY" <?= $canal==='DELIVERY'?'selected':''; ?>>DELIVERY</option>
                    </select>
                  </div>

                  <div class="grow search-wrap">
                    <label class="mini mb-1">Produto (digite para sugerir)</label>
                    <input class="form-control compact" id="qProduto" type="text" name="q" value="<?= e($q) ?>"
                      placeholder="Ex.: Coca-Cola, 00005, cliente, pedido..." />
                    <input type="hidden" name="produto_id" id="produto_id" value="<?= (int)$produto_id ?>">
                    <div class="suggest" id="suggestBox"></div>
                  </div>

                  <div class="w180">
                    <label class="mini mb-1">Itens por página</label>
                    <select class="form-select compact" name="limit" id="fLimit">
                      <?php foreach ([20,50,100,200] as $l): ?>
                        <option value="<?= $l ?>" <?= $limit===$l?'selected':''; ?>><?= $l ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>

                  <div class="d-flex gap-2 flex-wrap">
                    <button class="main-btn primary-btn btn-hover btn-compact" type="submit">
                      <i class="lni lni-funnel me-1"></i> Filtrar
                    </button>
                    <a class="main-btn light-btn btn-hover btn-compact" href="vendidos.php">Limpar</a>
                  </div>
                </form>
              </div>
            </div>

            <!-- Tabela -->
            <div class="cardx mt-3">
              <div class="head">
                <div>
                  <div class="fw-700">Resultados</div>
                  <div class="muted">
                    <?= (int)$totalGroups ?> produtos encontrados • página <?= (int)$page ?> de <?= (int)$totalPages ?>
                  </div>
                </div>
              </div>

              <div class="body">
                <div class="table-responsive">
                  <table class="table" id="tbDev">
                    <thead>
                      <tr>
                        <th>Código</th>
                        <th>Produto</th>
                        <th>Categoria</th>
                        <th>Unidade</th>
                        <th class="text-end">Qtd vendida</th>
                        <th class="text-end">Preço médio</th>
                        <th class="text-end">Total vendido</th>
                        <th class="text-center">Pedidos</th>
                        <th class="text-center">Última</th>
                        <th class="text-end">Ação</th>
                      </tr>
                    </thead>

                    <tbody>
                      <?php if (!$rows): ?>
                        <tr><td colspan="10" class="muted">Sem dados para os filtros selecionados.</td></tr>
                      <?php else: ?>
                        <?php foreach ($rows as $r): ?>
                          <tr>
                            <td class="mini"><?= e((string)$r['codigo']) ?></td>
                            <td>
                              <div class="fw-700 td-nome"><?= e((string)$r['nome']) ?></div>
                              <div class="muted">ID: <?= (int)$r['id'] ?></div>
                            </td>
                            <td><?= e((string)$r['categoria']) ?></td>
                            <td class="text-center"><?= e((string)$r['unidade']) ?></td>
                            <td class="text-end"><?= e(nfmt($r['qtd_vendida'], 3)) ?></td>
                            <td class="text-end"><?= e(brl($r['preco_medio'])) ?></td>
                            <td class="text-end money"><?= e(brl($r['total_vendido'])) ?></td>
                            <td class="text-center"><?= (int)$r['pedidos'] ?></td>
                            <td class="text-center"><?= e((string)$r['ultima_data']) ?></td>
                            <td class="text-end">
                              <button class="main-btn light-btn btn-hover btn-compact btnDetalhes" type="button"
                                data-id="<?= (int)$r['id'] ?>">
                                <i class="lni lni-eye"></i>
                              </button>
                            </td>
                          </tr>
                        <?php endforeach; ?>
                      <?php endif; ?>
                    </tbody>
                  </table>
                </div>

                <?php
                  // Paginação
                  $qs = $_GET;
                  $prev = max(1, $page - 1);
                  $next = min($totalPages, $page + 1);
                  $qsPrev = $qs; $qsPrev['page'] = $prev;
                  $qsNext = $qs; $qsNext['page'] = $next;
                ?>
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mt-3">
                  <div class="muted">
                    Mostrando <?= count($rows) ?> de <?= (int)$totalGroups ?> produtos
                  </div>
                  <div class="d-flex gap-2">
                    <a class="main-btn light-btn btn-hover btn-compact <?= $page<=1?'disabled':''; ?>"
                      href="<?= $page<=1?'#':('vendidos.php?'.http_build_query($qsPrev)) ?>">
                      <i class="lni lni-chevron-left"></i>
                    </a>
                    <span class="badge bg-light text-dark" style="border-radius:999px;padding:8px 12px;font-weight:900;">
                      Página <?= (int)$page ?> / <?= (int)$totalPages ?>
                    </span>
                    <a class="main-btn light-btn btn-hover btn-compact <?= $page>=$totalPages?'disabled':''; ?>"
                      href="<?= $page>=$totalPages?'#':('vendidos.php?'.http_build_query($qsNext)) ?>">
                      <i class="lni lni-chevron-right"></i>
                    </a>
                  </div>
                </div>

              </div>
            </div>
          </div>

          <!-- Totais -->
          <div class="col-lg-4">
            <div class="box-tot">
              <div class="fw-800 mb-2">Totais no período</div>
              <div class="tot-row"><span>Produtos</span><span><?= (int)$tot['produtos'] ?></span></div>
              <div class="tot-row"><span>Pedidos</span><span><?= (int)$tot['pedidos'] ?></span></div>
              <div class="tot-row"><span>Linhas (saídas)</span><span><?= (int)$tot['linhas'] ?></span></div>
              <div class="tot-row"><span>Qtd total</span><span><?= e(nfmt($tot['qtd'], 3)) ?></span></div>
              <div class="tot-hr"></div>
              <div class="grand">
                <div class="lbl">Total vendido</div>
                <div class="val"><?= e(brl($tot['total'])) ?></div>
              </div>
              <div class="muted mt-2">
                Período: <?= e($ini) ?> a <?= e($fim) ?><?= $canal ? " • Canal: ".e($canal) : "" ?>
              </div>
            </div>

            <div class="cardx mt-3">
              <div class="head">
                <div>
                  <div class="fw-700">Exportar</div>
                  <div class="muted">Mesmo estilo do Inventário</div>
                </div>
              </div>
              <div class="body d-flex gap-2 flex-wrap">
                <button class="main-btn light-btn btn-hover btn-compact" id="btnExcel2" type="button">
                  <i class="lni lni-download me-1"></i> Excel
                </button>
                <button class="main-btn light-btn btn-hover btn-compact" id="btnPDF2" type="button">
                  <i class="lni lni-printer me-1"></i> PDF
                </button>
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
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Detalhes do produto vendido</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
        </div>
        <div class="modal-body">
          <div id="detLoading" class="muted">Carregando...</div>
          <div id="detError" class="text-danger" style="display:none;"></div>

          <div id="detBody" style="display:none;">
            <div class="row g-3">
              <div class="col-md-8">
                <div class="fw-800" id="detNome">—</div>
                <div class="muted" id="detMeta">—</div>
              </div>
              <div class="col-md-4">
                <div class="box-tot" style="padding:10px">
                  <div class="tot-row"><span>Pedidos</span><span id="detPedidos">0</span></div>
                  <div class="tot-row"><span>Qtd</span><span id="detQtd">0</span></div>
                  <div class="grand">
                    <div class="lbl" style="font-size:13px">Total</div>
                    <div class="val" style="font-size:20px" id="detTotal">R$ 0,00</div>
                  </div>
                </div>
              </div>
            </div>

            <hr class="my-3">

            <div class="fw-800 mb-2">Últimas saídas (no período)</div>
            <div class="sale-box" id="detLinhas"></div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <script src="assets/js/bootstrap.bundle.min.js"></script>
  <script src="assets/js/main.js"></script>

  <!-- IGUAL AO INVENTÁRIO (jsPDF + autoTable) -->
  <script src="https://cdn.jsdelivr.net/npm/jspdf@2.5.1/dist/jspdf.umd.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/jspdf-autotable@3.8.2/dist/jspdf.plugin.autotable.min.js"></script>

  <script>
    const CSRF = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    // ====== Autocomplete produto ======
    (function () {
      const inp = document.getElementById('qProduto');
      const hidden = document.getElementById('produto_id');
      const box = document.getElementById('suggestBox');
      if (!inp || !hidden || !box) return;

      let t = null, lastTerm = '';

      function hide() { box.style.display = 'none'; box.innerHTML = ''; }
      function escapeHtml(s) {
        return String(s).replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]));
      }
      function show(items) {
        if (!items || !items.length) { hide(); return; }
        box.innerHTML = items.map(it => `
          <div class="it" data-id="${it.id}" data-codigo="${escapeHtml(it.codigo||'')}" data-nome="${escapeHtml(it.nome||'')}">
            <div class="t">${escapeHtml(it.nome)} <span class="muted">(${escapeHtml(it.codigo||'')})</span></div>
            <div class="s">${escapeHtml(it.categoria||'-')}</div>
          </div>
        `).join('');
        box.style.display = 'block';
      }

      async function fetchSuggest(term) {
        const rel = `vendidos.php?action=suggest&term=${encodeURIComponent(term)}`;
        const res = await fetch(rel, { headers: { 'Accept': 'application/json', 'X-CSRF-Token': CSRF } });
        const j = await res.json();
        return j.ok ? (j.items || []) : [];
      }

      inp.addEventListener('input', () => {
        const term = (inp.value || '').trim();
        if (term.length < 1) { hidden.value = '0'; hide(); return; }
        hidden.value = '0';

        if (t) clearTimeout(t);
        t = setTimeout(async () => {
          if (term === lastTerm) return;
          lastTerm = term;
          try { show(await fetchSuggest(term)); } catch(e) { hide(); }
        }, 180);
      });

      box.addEventListener('click', (ev) => {
        const it = ev.target.closest('.it');
        if (!it) return;
        const id = it.getAttribute('data-id');
        const nome = it.getAttribute('data-nome');
        const codigo = it.getAttribute('data-codigo');
        hidden.value = id || '0';
        inp.value = `${nome} (${codigo})`;
        hide();
      });

      document.addEventListener('click', (ev) => {
        if (ev.target === inp || box.contains(ev.target)) return;
        hide();
      });
    })();

    // ====== Modal detalhes ======
    (function () {
      const modalEl = document.getElementById('mdDetalhes');
      if (!modalEl) return;
      const modal = new bootstrap.Modal(modalEl);

      const $loading = document.getElementById('detLoading');
      const $error = document.getElementById('detError');
      const $body = document.getElementById('detBody');

      const $nome = document.getElementById('detNome');
      const $meta = document.getElementById('detMeta');
      const $pedidos = document.getElementById('detPedidos');
      const $qtd = document.getElementById('detQtd');
      const $total = document.getElementById('detTotal');
      const $linhas = document.getElementById('detLinhas');

      function fmtBRL(v) {
        try { return new Intl.NumberFormat('pt-BR', { style:'currency', currency:'BRL' }).format(Number(v||0)); }
        catch(e) { return 'R$ ' + String(v||0); }
      }
      function esc(s){ return String(s ?? '').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m])); }

      async function openDetalhes(id) {
        $error.style.display = 'none';
        $body.style.display = 'none';
        $loading.style.display = 'block';
        modal.show();

        const ini = document.getElementById('fIni')?.value || '';
        const fim = document.getElementById('fFim')?.value || '';
        const canal = document.getElementById('fCanal')?.value || '';

        try {
          const url = `vendidos.php?action=detalhes&id=${encodeURIComponent(id)}&ini=${encodeURIComponent(ini)}&fim=${encodeURIComponent(fim)}&canal=${encodeURIComponent(canal)}`;
          const res = await fetch(url, { headers: { 'Accept':'application/json', 'X-CSRF-Token': CSRF } });
          const j = await res.json();
          if (!j.ok) throw new Error(j.error || 'Falha ao carregar.');

          const p = j.produto || {};
          const t = j.totais || {};
          const linhas = Array.isArray(j.linhas) ? j.linhas : [];

          $nome.textContent = p.nome || '—';
          $meta.textContent = `Código: ${p.codigo || '—'} • Categoria: ${p.categoria || '-'} • Unidade: ${p.unidade || '-'}`;
          $pedidos.textContent = String(t.pedidos ?? 0);
          $qtd.textContent = String(t.qtd ?? 0);
          $total.textContent = fmtBRL(t.total ?? 0);

          if (!linhas.length) {
            $linhas.innerHTML = `<div class="muted">Nenhuma saída para este produto no período.</div>`;
          } else {
            $linhas.innerHTML = linhas.map(s => `
              <div class="sale-row">
                <div class="left">
                  <div class="nm">Pedido ${esc(s.pedido || '—')} • ${esc(s.cliente || '—')}</div>
                  <div class="cd">${esc(s.data || '—')} • ${esc(s.canal || '—')} • ${esc(s.pagamento || '—')}</div>
                </div>
                <div class="right">
                  <div><b>${esc(s.qtd)}</b> x ${fmtBRL(s.preco)}</div>
                  <div class="money">${fmtBRL(s.total)}</div>
                </div>
              </div>
            `).join('');
          }

          $loading.style.display = 'none';
          $body.style.display = 'block';
        } catch (err) {
          $loading.style.display = 'none';
          $error.textContent = err.message || String(err);
          $error.style.display = 'block';
        }
      }

      document.addEventListener('click', (ev) => {
        const btn = ev.target.closest('.btnDetalhes');
        if (!btn) return;
        ev.preventDefault();
        openDetalhes(btn.getAttribute('data-id'));
      });
    })();

    // ==========================
    // ✅ EXPORT Excel (IGUAL inventario.php)
    // ==========================
    function exportExcelVendidos() {
      const tb = document.getElementById('tbDev');
      if (!tb) return;

      const rows = Array.from(tb.querySelectorAll('tbody tr')).filter(tr => tr.style.display !== 'none');
      const now = new Date();
      const dt = now.toLocaleDateString('pt-BR') + ' ' + now.toLocaleTimeString('pt-BR');

      const ini = document.getElementById('fIni')?.value || '';
      const fim = document.getElementById('fFim')?.value || '';
      const catSel = document.getElementById('fCategoria');
      const canalSel = document.getElementById('fCanal');
      const qProd = document.getElementById('qProduto')?.value || '';

      const catTxt = catSel && catSel.value !== '0' ? catSel.options[catSel.selectedIndex].text : 'Todas';
      const canalTxt = canalSel && canalSel.value ? canalSel.options[canalSel.selectedIndex].text : 'Todos';

      const header = ['Código','Produto','Categoria','Unidade','Qtd vendida','Preço médio','Total vendido','Pedidos','Última'];

      const body = rows.map(tr => {
        const codigo = tr.children[0]?.innerText.trim() || '';
        const prod = tr.querySelector('.td-nome')?.innerText.trim() || (tr.children[1]?.innerText.trim() || '');
        const categoria = tr.children[2]?.innerText.trim() || '';
        const unidade = tr.children[3]?.innerText.trim() || '';
        const qtd = tr.children[4]?.innerText.trim() || '';
        const preco = tr.children[5]?.innerText.trim() || '';
        const total = tr.children[6]?.innerText.trim() || '';
        const pedidos = tr.children[7]?.innerText.trim() || '';
        const ultima = tr.children[8]?.innerText.trim() || '';
        return [codigo, prod, categoria, unidade, qtd, preco, total, pedidos, ultima];
      });

      const isCenterCol = (idx) => (idx >= 3); // a partir de Unidade, centraliza no excel
      let html = `
        <html>
          <head>
            <meta charset="utf-8">
            <style>
              table { border: 0.6px solid #999; font-family: Arial; font-size: 12px; }
              td, th { border: 1px solid #999; padding: 6px 8px; vertical-align: middle; }
              th { background: #f1f5f9; font-weight: 700; }
              .title { font-size: 16px; font-weight: 700; background: #eef2ff; text-align: center; }
              .muted { color: #555; font-weight: 700; }
              .center { text-align: center; }
              .right { text-align: right; }
            </style>
          </head>
          <body>
            <table>
      `;

      html += `<tr><td class="title" colspan="9">PAINEL DA DISTRIBUIDORA - VENDIDOS</td></tr>`;
      html += `<tr><td class="muted">Gerado em:</td><td colspan="8">${dt}</td></tr>`;
      html += `<tr>
                <td class="muted">Período:</td><td colspan="3">${ini} a ${fim}</td>
                <td class="muted">Categoria:</td><td>${escapeHtml(catTxt)}</td>
                <td class="muted">Canal:</td><td colspan="2">${escapeHtml(canalTxt)}</td>
              </tr>`;
      html += `<tr><td class="muted">Busca:</td><td colspan="8">${escapeHtml(qProd || '—')}</td></tr>`;

      html += `<tr>${header.map((h, idx) => {
        const cls = (idx >= 4 && idx <= 6) ? 'right' : (isCenterCol(idx) ? 'center' : '');
        return `<th class="${cls}">${escapeHtml(h)}</th>`;
      }).join('')}</tr>`;

      body.forEach(r => {
        html += `<tr>${r.map((c, idx) => {
          const safe = escapeHtml(String(c ?? ''));
          const cls = (idx >= 4 && idx <= 6) ? 'right' : (isCenterCol(idx) ? 'center' : '');
          return `<td class="${cls}">${safe}</td>`;
        }).join('')}</tr>`;
      });

      html += `</table></body></html>`;

      const blob = new Blob(["\ufeff" + html], { type: 'application/vnd.ms-excel;charset=utf-8;' });
      const url = URL.createObjectURL(blob);

      const a = document.createElement('a');
      a.href = url;
      a.download = 'vendidos.xls';
      document.body.appendChild(a);
      a.click();
      a.remove();
      URL.revokeObjectURL(url);

      function escapeHtml(s) {
        return String(s).replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]));
      }
    }

    // ==========================
    // ✅ EXPORT PDF (IGUAL inventario.php)
    // ==========================
    function exportPDFVendidos() {
      const tb = document.getElementById('tbDev');
      if (!tb) return;

      if (!window.jspdf || !window.jspdf.jsPDF) {
        alert('Biblioteca do PDF não carregou.');
        return;
      }

      const rows = Array.from(tb.querySelectorAll('tbody tr')).filter(tr => tr.style.display !== 'none');
      const now = new Date();
      const dt = now.toLocaleDateString('pt-BR') + ' ' + now.toLocaleTimeString('pt-BR');

      const ini = document.getElementById('fIni')?.value || '';
      const fim = document.getElementById('fFim')?.value || '';
      const catSel = document.getElementById('fCategoria');
      const canalSel = document.getElementById('fCanal');
      const qProd = document.getElementById('qProduto')?.value || '';

      const catTxt = catSel && catSel.value !== '0' ? catSel.options[catSel.selectedIndex].text : 'Todas';
      const canalTxt = canalSel && canalSel.value ? canalSel.options[canalSel.selectedIndex].text : 'Todos';

      const { jsPDF } = window.jspdf;
      const doc = new jsPDF({ orientation: 'landscape', unit: 'pt', format: 'a4' });
      const M = 70;

      doc.setTextColor(0,0,0);
      doc.setFont('helvetica', 'bold');
      doc.setFontSize(14);
      doc.text('PAINEL DA DISTRIBUIDORA - VENDIDOS', M, 55);

      doc.setFont('helvetica', 'normal');
      doc.setFontSize(10);
      doc.text(`Gerado em:  ${dt}`, M, 75);
      doc.text(`Período:  ${ini} a ${fim}`, M, 92);
      doc.text(`Categoria:  ${catTxt} | Canal:  ${canalTxt}`, M, 109);
      doc.text(`Busca:  ${qProd || '—'}`, M, 126);

      const head = [[
        'Código','Produto','Categoria','Unidade','Qtd vendida','Preço médio','Total vendido','Pedidos','Última'
      ]];

      const body = rows.map(tr => {
        const codigo = tr.children[0]?.innerText.trim() || '';
        const prod = tr.querySelector('.td-nome')?.innerText.trim() || (tr.children[1]?.innerText.trim() || '');
        const categoria = tr.children[2]?.innerText.trim() || '';
        const unidade = tr.children[3]?.innerText.trim() || '';
        const qtd = tr.children[4]?.innerText.trim() || '';
        const preco = tr.children[5]?.innerText.trim() || '';
        const total = tr.children[6]?.innerText.trim() || '';
        const pedidos = tr.children[7]?.innerText.trim() || '';
        const ultima = tr.children[8]?.innerText.trim() || '';
        return [codigo, prod, categoria, unidade, qtd, preco, total, pedidos, ultima];
      });

      doc.autoTable({
        head,
        body,
        startY: 145,
        margin: { left: M, right: M },
        theme: 'plain',
        styles: {
          font: 'helvetica',
          fontSize: 9,
          textColor: [17, 24, 39],
          cellPadding: { top: 6, right: 6, bottom: 6, left: 6 },
          lineWidth: 0
        },
        headStyles: {
          fillColor: [241, 245, 249],
          textColor: [17, 24, 39],
          fontStyle: 'bold',
          lineWidth: 0
        },
        alternateRowStyles: { fillColor: [248, 250, 252] },
        columnStyles: {
          3: { halign: 'center' },  // unidade
          4: { halign: 'right' },   // qtd
          5: { halign: 'right' },   // preco
          6: { halign: 'right' },   // total
          7: { halign: 'center' },  // pedidos
          8: { halign: 'center' }   // última
        },
        didParseCell: function(data) {
          data.cell.styles.lineWidth = 0;
        }
      });

      doc.save('vendidos.pdf');
    }

    document.getElementById('btnExcel')?.addEventListener('click', exportExcelVendidos);
    document.getElementById('btnExcel2')?.addEventListener('click', exportExcelVendidos);
    document.getElementById('btnPDF')?.addEventListener('click', exportPDFVendidos);
    document.getElementById('btnPDF2')?.addEventListener('click', exportPDFVendidos);
  </script>

</body>
</html>