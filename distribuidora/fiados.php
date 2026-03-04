<?php
declare(strict_types=1);

@date_default_timezone_set('America/Manaus');
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

/* ======================
   INCLUDES (helpers + conexão db():PDO)
====================== */
$possibleHelpers = [
  __DIR__ . '/assets/dados/_helpers.php',
  __DIR__ . '/assets/dados/relatorios/_helpers.php',
];
foreach ($possibleHelpers as $f) {
  if (is_file($f)) { require_once $f; break; }
}

$possibleConn = [
  __DIR__ . '/assets/php/conexao.php',
  __DIR__ . '/assets/conexao.php',
  __DIR__ . '/assets/php/conexao_pdo.php',
];
foreach ($possibleConn as $f) {
  if (is_file($f)) { require_once $f; break; }
}

if (!function_exists('db')) {
  http_response_code(500);
  echo "ERRO: função db():PDO não encontrada. Verifique o include da conexão (assets/php/conexao.php).";
  exit;
}

$pdo = db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

/* ======================
   FALLBACKS (se helpers não existirem)
====================== */
if (!function_exists('e')) {
  function e(string $s): string { return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
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
    return $token !== null && $token !== '' && hash_equals($sess, $token);
  }
}

/* ======================
   SCHEMA HELPERS
====================== */
function table_exists(PDO $pdo, string $table): bool {
  $st = $pdo->prepare("SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :t LIMIT 1");
  $st->execute([':t' => $table]);
  return (bool)$st->fetchColumn();
}
function col_exists(PDO $pdo, string $table, string $col): bool {
  $st = $pdo->prepare("SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = :t AND column_name = :c LIMIT 1");
  $st->execute([':t' => $table, ':c' => $col]);
  return (bool)$st->fetchColumn();
}
function pick_col(PDO $pdo, string $table, array $candidates): ?string {
  foreach ($candidates as $c) if (col_exists($pdo, $table, $c)) return $c;
  return null;
}
function qcol(string $alias, string $col): string {
  return $alias . ".`" . str_replace('`', '', $col) . "`";
}

/* ======================
   DETECTA COLUNAS IMPORTANTES
====================== */
$hasClientes = table_exists($pdo, 'clientes');
$hasVendaItens = table_exists($pdo, 'venda_itens');
$hasProdutos = table_exists($pdo, 'produtos');

$vDate = pick_col($pdo, 'vendas', ['data', 'data_venda', 'dt_venda', 'created_at', 'emissao']);
$vTotal = pick_col($pdo, 'vendas', ['total', 'valor_total', 'total_geral', 'valor', 'total_final']);
$vStatus = pick_col($pdo, 'vendas', ['status', 'situacao']);
$vForma = pick_col($pdo, 'vendas', ['forma_pagamento', 'tipo_pagamento', 'pagamento', 'pagamento_tipo', 'condicao_pagamento', 'modalidade_pagamento']);
$vVenc = pick_col($pdo, 'vendas', ['vencimento', 'data_vencimento', 'vencimento_em', 'boleto_vencimento']);
$vObs  = pick_col($pdo, 'vendas', ['obs', 'observacao', 'observacoes', 'anotacao']);

$vClienteId = col_exists($pdo, 'vendas', 'cliente_id') ? 'cliente_id' : null;

// dados do cliente (tabela clientes)
$cNome = $hasClientes ? pick_col($pdo, 'clientes', ['nome', 'razao_social', 'nome_completo']) : null;
$cCpf  = $hasClientes ? pick_col($pdo, 'clientes', ['cpf', 'doc', 'documento']) : null;
$cTel  = $hasClientes ? pick_col($pdo, 'clientes', ['telefone', 'tel', 'celular', 'fone', 'whatsapp']) : null;

// dados do cliente (fallback direto em vendas)
$vCliNome = pick_col($pdo, 'vendas', ['cliente_nome', 'nome_cliente', 'cliente']);
$vCliCpf  = pick_col($pdo, 'vendas', ['cliente_cpf', 'cpf', 'doc', 'documento']);
$vCliTel  = pick_col($pdo, 'vendas', ['cliente_telefone', 'telefone', 'tel', 'celular', 'fone', 'whatsapp']);

/* ======================
   CONDIÇÃO "FIADO/BOLETO"
====================== */
$fiadoCond = "1=1";
if (col_exists($pdo, 'vendas', 'is_fiado')) {
  $fiadoCond = qcol('v', 'is_fiado') . " = 1";
} elseif (col_exists($pdo, 'vendas', 'fiado')) {
  $fiadoCond = qcol('v', 'fiado') . " = 1";
} elseif ($vForma) {
  // tenta filtrar por forma de pagamento
  $fiadoCond = "UPPER(TRIM(" . qcol('v', $vForma) . ")) IN ('FIADO','BOLETO','CREDIARIO','CREDIÁRIO')";
} else {
  // se não houver nenhum indicador, mostramos vazio (evita listar tudo como fiado por engano)
  $fiadoCond = "0=1";
}

/* ======================
   ENDPOINT JSON (action=fetch | action=detalhes | action=clientes_suggest)
====================== */
if (isset($_GET['action'])) {
  header('Content-Type: application/json; charset=utf-8');

  try {
    $action = (string)$_GET['action'];

    // CSRF: aceita header X-CSRF-Token (AJAX) ou POST/GET token
    $csrfHeader = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
    $csrfAny = $csrfHeader ?: ($_POST['csrf'] ?? $_GET['csrf'] ?? null);
    // Para leitura (fetch/detalhes/suggest), só valida se vier token (não bloqueia listagem se o front ainda não manda)
    if ($csrfAny !== null && $csrfAny !== '' && !csrf_validate((string)$csrfAny)) {
      http_response_code(403);
      echo json_encode(['ok' => false, 'error' => 'CSRF inválido'], JSON_UNESCAPED_UNICODE);
      exit;
    }

    if ($action === 'clientes_suggest') {
      if (!$hasClientes || !$cNome) {
        echo json_encode(['ok' => true, 'items' => []], JSON_UNESCAPED_UNICODE);
        exit;
      }

      $q = trim((string)($_GET['q'] ?? ''));
      if ($q === '' || mb_strlen($q) < 2) {
        echo json_encode(['ok' => true, 'items' => []], JSON_UNESCAPED_UNICODE);
        exit;
      }

      $limit = 10;
      $sql = "SELECT
                `id` AS id,
                " . qcol('c', $cNome) . " AS nome" .
                ($cCpf ? (", " . qcol('c', $cCpf) . " AS cpf") : ", '' AS cpf") .
                ($cTel ? (", " . qcol('c', $cTel) . " AS telefone") : ", '' AS telefone") . "
              FROM clientes c
              WHERE " . qcol('c', $cNome) . " LIKE :q" .
              ($cCpf ? (" OR " . qcol('c', $cCpf) . " LIKE :q") : "") .
              ($cTel ? (" OR " . qcol('c', $cTel) . " LIKE :q") : "") . "
              ORDER BY " . qcol('c', $cNome) . " ASC
              LIMIT {$limit}";
      $st = $pdo->prepare($sql);
      $st->execute([':q' => '%' . $q . '%']);
      echo json_encode(['ok' => true, 'items' => $st->fetchAll()], JSON_UNESCAPED_UNICODE);
      exit;
    }

    if ($action === 'fetch') {
      $page = max(1, (int)($_GET['page'] ?? 1));
      $perPage = max(5, min(50, (int)($_GET['per_page'] ?? 15)));
      $offset = ($page - 1) * $perPage;

      $q = trim((string)($_GET['q'] ?? ''));
      $dtIni = trim((string)($_GET['dt_ini'] ?? ''));
      $dtFim = trim((string)($_GET['dt_fim'] ?? ''));

      $where = [];
      $params = [];

      $where[] = $fiadoCond;

      // datas
      if ($vDate && $dtIni !== '') {
        $where[] = qcol('v', $vDate) . " >= :dt_ini";
        $params[':dt_ini'] = $dtIni . " 00:00:00";
      }
      if ($vDate && $dtFim !== '') {
        $where[] = qcol('v', $vDate) . " <= :dt_fim";
        $params[':dt_fim'] = $dtFim . " 23:59:59";
      }

      // JOIN clientes (se possível)
      $join = "";
      $selectClienteNome = "''";
      $selectClienteCpf  = "''";
      $selectClienteTel  = "''";

      if ($hasClientes && $vClienteId) {
        $join = "LEFT JOIN clientes c ON c.`id` = " . qcol('v', $vClienteId);
        if ($cNome) $selectClienteNome = qcol('c', $cNome);
        if ($cCpf)  $selectClienteCpf  = qcol('c', $cCpf);
        if ($cTel)  $selectClienteTel  = qcol('c', $cTel);
      } else {
        if ($vCliNome) $selectClienteNome = qcol('v', $vCliNome);
        if ($vCliCpf)  $selectClienteCpf  = qcol('v', $vCliCpf);
        if ($vCliTel)  $selectClienteTel  = qcol('v', $vCliTel);
      }

      // busca
      if ($q !== '') {
        $likes = [];
        $params[':q'] = '%' . $q . '%';

        // cliente
        if ($selectClienteNome !== "''") $likes[] = "{$selectClienteNome} LIKE :q";
        if ($selectClienteCpf  !== "''") $likes[] = "{$selectClienteCpf} LIKE :q";
        if ($selectClienteTel  !== "''") $likes[] = "{$selectClienteTel} LIKE :q";

        // venda id
        $likes[] = "CAST(v.`id` AS CHAR) LIKE :q";

        // obs (se existir)
        if ($vObs) $likes[] = qcol('v', $vObs) . " LIKE :q";

        $where[] = "(" . implode(" OR ", $likes) . ")";
      }

      $whereSql = $where ? ("WHERE " . implode(" AND ", $where)) : "";

      // colunas pra listagem
      $sel = [];
      $sel[] = "v.`id` AS venda_id";
      $sel[] = ($vDate ? (qcol('v', $vDate) . " AS data") : "NULL AS data");
      $sel[] = ($vTotal ? (qcol('v', $vTotal) . " AS total") : "0 AS total");
      $sel[] = ($vStatus ? (qcol('v', $vStatus) . " AS status") : "'' AS status");
      $sel[] = ($vForma ? (qcol('v', $vForma) . " AS forma_pagamento") : "'' AS forma_pagamento");
      $sel[] = ($vVenc ? (qcol('v', $vVenc) . " AS vencimento") : "NULL AS vencimento");
      $sel[] = "{$selectClienteNome} AS cliente_nome";
      $sel[] = "{$selectClienteCpf} AS cliente_cpf";
      $sel[] = "{$selectClienteTel} AS cliente_telefone";

      $orderBy = $vDate ? (qcol('v', $vDate) . " DESC") : "v.`id` DESC";

      // total count
      $sqlCount = "SELECT COUNT(*) FROM vendas v {$join} {$whereSql}";
      $st = $pdo->prepare($sqlCount);
      $st->execute($params);
      $totalRows = (int)$st->fetchColumn();
      $totalPages = (int)max(1, (int)ceil($totalRows / $perPage));

      // data
      $sql = "SELECT " . implode(", ", $sel) . "
              FROM vendas v
              {$join}
              {$whereSql}
              ORDER BY {$orderBy}
              LIMIT {$perPage} OFFSET {$offset}";
      $st = $pdo->prepare($sql);
      $st->execute($params);
      $rows = $st->fetchAll();

      echo json_encode([
        'ok' => true,
        'page' => $page,
        'per_page' => $perPage,
        'total_rows' => $totalRows,
        'total_pages' => $totalPages,
        'rows' => $rows
      ], JSON_UNESCAPED_UNICODE);
      exit;
    }

    if ($action === 'detalhes') {
      $id = (int)($_GET['id'] ?? 0);
      if ($id <= 0) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'error' => 'ID inválido'], JSON_UNESCAPED_UNICODE);
        exit;
      }

      // JOIN clientes (se possível)
      $join = "";
      $selectClienteNome = "''";
      $selectClienteCpf  = "''";
      $selectClienteTel  = "''";

      if ($hasClientes && $vClienteId) {
        $join = "LEFT JOIN clientes c ON c.`id` = " . qcol('v', $vClienteId);
        if ($cNome) $selectClienteNome = qcol('c', $cNome);
        if ($cCpf)  $selectClienteCpf  = qcol('c', $cCpf);
        if ($cTel)  $selectClienteTel  = qcol('c', $cTel);
      } else {
        if ($vCliNome) $selectClienteNome = qcol('v', $vCliNome);
        if ($vCliCpf)  $selectClienteCpf  = qcol('v', $vCliCpf);
        if ($vCliTel)  $selectClienteTel  = qcol('v', $vCliTel);
      }

      $sel = [];
      $sel[] = "v.`id` AS venda_id";
      $sel[] = ($vDate ? (qcol('v', $vDate) . " AS data") : "NULL AS data");
      $sel[] = ($vTotal ? (qcol('v', $vTotal) . " AS total") : "0 AS total");
      $sel[] = ($vStatus ? (qcol('v', $vStatus) . " AS status") : "'' AS status");
      $sel[] = ($vForma ? (qcol('v', $vForma) . " AS forma_pagamento") : "'' AS forma_pagamento");
      $sel[] = ($vVenc ? (qcol('v', $vVenc) . " AS vencimento") : "NULL AS vencimento");
      $sel[] = ($vObs ? (qcol('v', $vObs) . " AS obs") : "'' AS obs");
      $sel[] = "{$selectClienteNome} AS cliente_nome";
      $sel[] = "{$selectClienteCpf} AS cliente_cpf";
      $sel[] = "{$selectClienteTel} AS cliente_telefone";

      $sql = "SELECT " . implode(", ", $sel) . " FROM vendas v {$join} WHERE v.`id` = :id LIMIT 1";
      $st = $pdo->prepare($sql);
      $st->execute([':id' => $id]);
      $venda = $st->fetch();

      if (!$venda) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'Venda não encontrada'], JSON_UNESCAPED_UNICODE);
        exit;
      }

      // Confere se é fiado (pra não abrir detalhes de venda comum)
      // (usa a mesma lógica do list)
      $sqlCheck = "SELECT 1 FROM vendas v WHERE v.`id` = :id AND {$fiadoCond} LIMIT 1";
      $st = $pdo->prepare($sqlCheck);
      $st->execute([':id' => $id]);
      if (!$st->fetchColumn()) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Esta venda não está marcada como FIADO/BOLETO.'], JSON_UNESCAPED_UNICODE);
        exit;
      }

      $itens = [];
      if ($hasVendaItens) {
        $viVendaId = col_exists($pdo, 'venda_itens', 'venda_id') ? 'venda_id' : null;
        if ($viVendaId) {
          $viProdId  = pick_col($pdo, 'venda_itens', ['produto_id', 'prod_id']);
          $viQtd     = pick_col($pdo, 'venda_itens', ['quantidade', 'qtd']);
          $viPreco   = pick_col($pdo, 'venda_itens', ['preco', 'valor', 'preco_unitario']);
          $viSub     = pick_col($pdo, 'venda_itens', ['subtotal', 'total', 'valor_total']);

          $pNome = $hasProdutos ? pick_col($pdo, 'produtos', ['nome', 'descricao', 'titulo']) : null;
          $pCod  = $hasProdutos ? pick_col($pdo, 'produtos', ['codigo', 'sku']) : null;
          $pUni  = $hasProdutos ? pick_col($pdo, 'produtos', ['unidade', 'un']) : null;

          $joinP = "";
          $selItem = [];
          $selItem[] = "vi.`id` AS item_id";
          $selItem[] = ($viQtd ? (qcol('vi', $viQtd) . " AS quantidade") : "0 AS quantidade");
          $selItem[] = ($viPreco ? (qcol('vi', $viPreco) . " AS preco") : "0 AS preco");
          $selItem[] = ($viSub ? (qcol('vi', $viSub) . " AS subtotal") : "0 AS subtotal");

          if ($hasProdutos && $viProdId) {
            $joinP = "LEFT JOIN produtos p ON p.`id` = " . qcol('vi', $viProdId);
            $selItem[] = ($pNome ? (qcol('p', $pNome) . " AS produto_nome") : "'' AS produto_nome");
            $selItem[] = ($pCod  ? (qcol('p', $pCod) . " AS produto_codigo") : "'' AS produto_codigo");
            $selItem[] = ($pUni  ? (qcol('p', $pUni) . " AS unidade") : "'' AS unidade");
          } else {
            $selItem[] = "'' AS produto_nome";
            $selItem[] = "'' AS produto_codigo";
            $selItem[] = "'' AS unidade";
          }

          $sqlItens = "SELECT " . implode(", ", $selItem) . "
                      FROM venda_itens vi
                      {$joinP}
                      WHERE " . qcol('vi', $viVendaId) . " = :id
                      ORDER BY vi.`id` ASC";
          $st = $pdo->prepare($sqlItens);
          $st->execute([':id' => $id]);
          $itens = $st->fetchAll();
        }
      }

      echo json_encode(['ok' => true, 'venda' => $venda, 'itens' => $itens], JSON_UNESCAPED_UNICODE);
      exit;
    }

    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Ação inválida'], JSON_UNESCAPED_UNICODE);
    exit;
  } catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    exit;
  }
}

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
  <title>Painel da Distribuidora | Fiados</title>

  <link rel="stylesheet" href="assets/css/bootstrap.min.css" />
  <link rel="stylesheet" href="assets/css/lineicons.css" rel="stylesheet" type="text/css" />
  <link rel="stylesheet" href="assets/css/materialdesignicons.min.css" rel="stylesheet" type="text/css" />
  <link rel="stylesheet" href="assets/css/main.css" />
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

        <li class="nav-item">
          <a href="vendas.php">
            <span class="icon">
              <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M1.66666 5C1.66666 3.89543 2.5621 3 3.66666 3H16.3333C17.4379 3 18.3333 3.89543 18.3333 5V15C18.3333 16.1046 17.4379 17 16.3333 17H3.66666C2.5621 17 1.66666 16.1046 1.66666 15V5Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                <path d="M1.66666 5L10 10.8333L18.3333 5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
              </svg>
            </span>
            <span class="text">Vendas</span>
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
            <li><a href="vendidos.php">Vendidos</a></li>
            <li><a href="fiados.php" class="active">Fiados</a></li>
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
  <!-- ======== sidebar-nav end =========== -->

  <div class="overlay"></div>

  <main class="main-wrapper">

    <!-- Header (simples) -->
    <header class="header">
      <div class="container-fluid">
        <div class="header-left d-flex align-items-center gap-2">
          <button id="menu-toggle" class="main-btn light-btn btn-hover">
            <i class="lni lni-menu"></i>
          </button>
          <h6 class="mb-0">Fiados (Boletos / Crediário)</h6>
        </div>
      </div>
    </header>

    <!-- Conteúdo -->
    <section class="section">
      <div class="container-fluid">

        <?php if ($fiadoCond === "0=1"): ?>
          <div class="alert alert-warning">
            <strong>Atenção:</strong> não encontrei no banco uma coluna para identificar fiados (ex.: <code>is_fiado</code>, <code>fiado</code> ou <code>forma_pagamento</code>).
            <br>Assim, por segurança, esta tela não lista nada. Se quiser, me diga quais colunas existem na tabela <code>vendas</code> para eu ajustar.
          </div>
        <?php endif; ?>

        <div class="card-style mb-4">
          <div class="row g-2 align-items-end">
            <div class="col-12 col-md-3">
              <label class="form-label">Buscar (cliente, CPF, telefone, ID)</label>
              <div class="position-relative">
                <input id="q" class="form-control" placeholder="Ex.: João / 123.456 / 9299..." autocomplete="off">
                <div id="suggestBox" class="list-group position-absolute w-100" style="z-index: 20; display:none; max-height:260px; overflow:auto;"></div>
              </div>
            </div>
            <div class="col-6 col-md-2">
              <label class="form-label">Data inicial</label>
              <input id="dt_ini" type="date" class="form-control">
            </div>
            <div class="col-6 col-md-2">
              <label class="form-label">Data final</label>
              <input id="dt_fim" type="date" class="form-control">
            </div>
            <div class="col-12 col-md-2">
              <label class="form-label">Por página</label>
              <select id="per_page" class="form-select">
                <option value="10">10</option>
                <option value="15" selected>15</option>
                <option value="25">25</option>
                <option value="50">50</option>
              </select>
            </div>
            <div class="col-12 col-md-3 d-flex gap-2">
              <button id="btnFiltrar" class="main-btn primary-btn btn-hover w-100">
                <i class="lni lni-search-alt"></i> Filtrar
              </button>
              <button id="btnLimpar" class="main-btn light-btn btn-hover w-100">
                <i class="lni lni-eraser"></i> Limpar
              </button>
            </div>
          </div>
        </div>

        <div class="card-style">
          <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
            <div>
              <h6 class="mb-0">Lista de Fiados</h6>
              <small class="text-muted">Vendas marcadas como FIADO/BOLETO, com dados do cliente (nome, CPF e telefone).</small>
            </div>
            <div class="text-end">
              <small class="text-muted d-block" id="metaInfo">—</small>
            </div>
          </div>

          <div class="table-responsive">
            <table class="table table-striped align-middle">
              <thead>
                <tr>
                  <th style="width: 80px;">#</th>
                  <th>Cliente</th>
                  <th style="width: 180px;">CPF</th>
                  <th style="width: 170px;">Telefone</th>
                  <th style="width: 140px;">Data</th>
                  <th style="width: 140px;">Vencimento</th>
                  <th style="width: 150px;" class="text-end">Total</th>
                  <th style="width: 130px;">Status</th>
                  <th style="width: 110px;" class="text-end">Ações</th>
                </tr>
              </thead>
              <tbody id="tbody">
                <tr><td colspan="9" class="text-center text-muted py-4">Carregando...</td></tr>
              </tbody>
            </table>
          </div>

          <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div class="text-muted" id="pageInfo">—</div>
            <nav>
              <ul class="pagination mb-0" id="pagination"></ul>
            </nav>
          </div>
        </div>

      </div>
    </section>

    <!-- Modal Detalhes -->
    <div class="modal fade" id="modalDetalhes" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Detalhes do Fiado</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
          </div>
          <div class="modal-body">
            <div id="detalhesBody">
              <div class="text-center text-muted py-4">Carregando...</div>
            </div>

            <hr>

            <h6 class="mb-2">Itens da venda</h6>
            <div class="table-responsive">
              <table class="table table-sm table-bordered align-middle">
                <thead>
                  <tr>
                    <th>Produto</th>
                    <th style="width:120px;">Código</th>
                    <th style="width:90px;" class="text-end">Qtd</th>
                    <th style="width:120px;" class="text-end">Preço</th>
                    <th style="width:130px;" class="text-end">Subtotal</th>
                  </tr>
                </thead>
                <tbody id="itensBody">
                  <tr><td colspan="5" class="text-center text-muted py-3">—</td></tr>
                </tbody>
              </table>
            </div>

          </div>
          <div class="modal-footer">
            <button type="button" class="main-btn light-btn btn-hover" data-bs-dismiss="modal">Fechar</button>
            <button type="button" class="main-btn primary-btn btn-hover" id="btnPrint">
              <i class="lni lni-printer"></i> Imprimir
            </button>
          </div>
        </div>
      </div>
    </div>

    <footer class="footer">
      <div class="container-fluid">
        <div class="footer-content">
          <p class="text-sm text-muted mb-0">© <?= date('Y') ?> Painel da Distribuidora</p>
        </div>
      </div>
    </footer>

  </main>

  <script src="assets/js/bootstrap.bundle.min.js"></script>
  <script src="assets/js/main.js"></script>

  <script>
    const CSRF = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    const elQ = document.getElementById('q');
    const elIni = document.getElementById('dt_ini');
    const elFim = document.getElementById('dt_fim');
    const elPer = document.getElementById('per_page');
    const tbody = document.getElementById('tbody');
    const pagination = document.getElementById('pagination');
    const pageInfo = document.getElementById('pageInfo');
    const metaInfo = document.getElementById('metaInfo');

    const suggestBox = document.getElementById('suggestBox');

    let currentPage = 1;
    let lastPayload = null;

    function moneyBR(v) {
      const n = Number(v || 0);
      return n.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
    }
    function fmtDateTime(s) {
      if (!s) return '';
      // suporta "YYYY-MM-DD HH:MM:SS" ou ISO
      const d = new Date(String(s).replace(' ', 'T'));
      if (isNaN(d.getTime())) return String(s);
      return d.toLocaleString('pt-BR');
    }
    function fmtDate(s) {
      if (!s) return '';
      const d = new Date(String(s).replace(' ', 'T'));
      if (isNaN(d.getTime())) return String(s);
      return d.toLocaleDateString('pt-BR');
    }
    function badgeStatus(s) {
      const t = String(s || '').trim().toUpperCase();
      let cls = 'secondary';
      if (['PAGO','PAGA','QUITADO','QUITADA','FINALIZADO','FINALIZADA'].includes(t)) cls = 'success';
      if (['ABERTO','PENDENTE','EM ABERTO','DEVENDO','ATRASADO'].includes(t)) cls = 'warning';
      if (['CANCELADO','CANCELADA','ESTORNADO','ESTORNADA'].includes(t)) cls = 'danger';
      return `<span class="badge bg-${cls}">${s ? s : '—'}</span>`;
    }

    async function fetchJSON(url) {
      const r = await fetch(url, { headers: { 'X-CSRF-Token': CSRF } });
      const j = await r.json().catch(() => ({}));
      if (!r.ok || !j.ok) throw new Error(j.error || 'Erro ao consultar');
      return j;
    }

    function buildUrl(page = 1) {
      const p = new URLSearchParams();
      p.set('action', 'fetch');
      p.set('page', String(page));
      p.set('per_page', elPer.value);
      if (elQ.value.trim()) p.set('q', elQ.value.trim());
      if (elIni.value) p.set('dt_ini', elIni.value);
      if (elFim.value) p.set('dt_fim', elFim.value);
      return `fiados.php?${p.toString()}`;
    }

    function renderRows(rows) {
      if (!rows || rows.length === 0) {
        tbody.innerHTML = `<tr><td colspan="9" class="text-center text-muted py-4">Nenhum fiado encontrado.</td></tr>`;
        return;
      }

      tbody.innerHTML = rows.map(r => {
        const nome = (r.cliente_nome || '—');
        const cpf = (r.cliente_cpf || '—');
        const tel = (r.cliente_telefone || '—');
        const dt = r.data ? fmtDateTime(r.data) : '—';
        const venc = r.vencimento ? fmtDate(r.vencimento) : '—';
        const total = moneyBR(r.total);
        const st = badgeStatus(r.status);
        return `
          <tr>
            <td>#${r.venda_id}</td>
            <td>${escapeHtml(nome)}</td>
            <td>${escapeHtml(cpf)}</td>
            <td>${escapeHtml(tel)}</td>
            <td>${escapeHtml(dt)}</td>
            <td>${escapeHtml(venc)}</td>
            <td class="text-end">${escapeHtml(total)}</td>
            <td>${st}</td>
            <td class="text-end">
              <button class="btn btn-sm btn-primary" data-id="${r.venda_id}" onclick="openDetalhes(${r.venda_id})">
                Detalhes
              </button>
            </td>
          </tr>
        `;
      }).join('');
    }

    function renderPagination(page, totalPages) {
      const tp = Math.max(1, Number(totalPages || 1));
      const p = Math.max(1, Number(page || 1));
      pagination.innerHTML = '';

      const mk = (label, target, disabled=false, active=false) => {
        const li = document.createElement('li');
        li.className = 'page-item' + (disabled ? ' disabled' : '') + (active ? ' active' : '');
        const a = document.createElement('a');
        a.className = 'page-link';
        a.href = '#';
        a.textContent = label;
        a.addEventListener('click', (ev) => {
          ev.preventDefault();
          if (disabled) return;
          load(target);
        });
        li.appendChild(a);
        return li;
      };

      pagination.appendChild(mk('«', 1, p === 1));
      pagination.appendChild(mk('‹', p - 1, p === 1));

      // janela de páginas
      const start = Math.max(1, p - 2);
      const end = Math.min(tp, p + 2);
      for (let i = start; i <= end; i++) pagination.appendChild(mk(String(i), i, false, i === p));

      pagination.appendChild(mk('›', p + 1, p === tp));
      pagination.appendChild(mk('»', tp, p === tp));
    }

    async function load(page = 1) {
      currentPage = page;
      tbody.innerHTML = `<tr><td colspan="9" class="text-center text-muted py-4">Carregando...</td></tr>`;
      try {
        const url = buildUrl(page);
        const data = await fetchJSON(url);
        lastPayload = data;

        renderRows(data.rows);
        renderPagination(data.page, data.total_pages);

        const from = ((data.page - 1) * data.per_page) + 1;
        const to = Math.min(data.total_rows, data.page * data.per_page);
        pageInfo.textContent = data.total_rows
          ? `Mostrando ${from}–${to} de ${data.total_rows}`
          : '—';
        metaInfo.textContent = `Página ${data.page} de ${data.total_pages}`;
      } catch (err) {
        tbody.innerHTML = `<tr><td colspan="9" class="text-center text-danger py-4">${escapeHtml(err.message || 'Erro')}</td></tr>`;
        pagination.innerHTML = '';
        pageInfo.textContent = '—';
        metaInfo.textContent = '—';
      }
    }

    // Modal detalhes
    const modalEl = document.getElementById('modalDetalhes');
    const modal = new bootstrap.Modal(modalEl);
    const detalhesBody = document.getElementById('detalhesBody');
    const itensBody = document.getElementById('itensBody');
    let printVendaId = null;

    window.openDetalhes = async function(id) {
      printVendaId = id;
      detalhesBody.innerHTML = `<div class="text-center text-muted py-4">Carregando...</div>`;
      itensBody.innerHTML = `<tr><td colspan="5" class="text-center text-muted py-3">Carregando...</td></tr>`;
      modal.show();

      try {
        const url = `fiados.php?action=detalhes&id=${encodeURIComponent(id)}`;
        const data = await fetchJSON(url);

        const v = data.venda || {};
        const nome = v.cliente_nome || '—';
        const cpf = v.cliente_cpf || '—';
        const tel = v.cliente_telefone || '—';

        detalhesBody.innerHTML = `
          <div class="row g-2">
            <div class="col-12 col-md-6">
              <div class="p-3 border rounded">
                <h6 class="mb-2">Cliente</h6>
                <div><strong>Nome:</strong> ${escapeHtml(nome)}</div>
                <div><strong>CPF:</strong> ${escapeHtml(cpf)}</div>
                <div><strong>Telefone:</strong> ${escapeHtml(tel)}</div>
              </div>
            </div>
            <div class="col-12 col-md-6">
              <div class="p-3 border rounded">
                <h6 class="mb-2">Venda</h6>
                <div><strong>ID:</strong> #${escapeHtml(String(v.venda_id || id))}</div>
                <div><strong>Data:</strong> ${escapeHtml(v.data ? fmtDateTime(v.data) : '—')}</div>
                <div><strong>Forma:</strong> ${escapeHtml(v.forma_pagamento || '—')}</div>
                <div><strong>Vencimento:</strong> ${escapeHtml(v.vencimento ? fmtDate(v.vencimento) : '—')}</div>
                <div><strong>Status:</strong> ${badgeStatus(v.status)}</div>
                <div><strong>Total:</strong> ${escapeHtml(moneyBR(v.total))}</div>
              </div>
            </div>
            ${v.obs ? `
              <div class="col-12">
                <div class="p-3 border rounded">
                  <h6 class="mb-2">Observação</h6>
                  <div>${escapeHtml(String(v.obs))}</div>
                </div>
              </div>` : ``}
          </div>
        `;

        const itens = Array.isArray(data.itens) ? data.itens : [];
        if (!itens.length) {
          itensBody.innerHTML = `<tr><td colspan="5" class="text-center text-muted py-3">Nenhum item encontrado.</td></tr>`;
        } else {
          itensBody.innerHTML = itens.map(it => {
            const nomeP = it.produto_nome || '—';
            const codP = it.produto_codigo || '—';
            const qtd = Number(it.quantidade || 0);
            const preco = Number(it.preco || 0);
            const sub = Number(it.subtotal || (qtd * preco));
            return `
              <tr>
                <td>${escapeHtml(nomeP)} ${it.unidade ? `<small class="text-muted">(${escapeHtml(it.unidade)})</small>` : ''}</td>
                <td>${escapeHtml(codP)}</td>
                <td class="text-end">${escapeHtml(String(qtd))}</td>
                <td class="text-end">${escapeHtml(moneyBR(preco))}</td>
                <td class="text-end">${escapeHtml(moneyBR(sub))}</td>
              </tr>
            `;
          }).join('');
        }
      } catch (err) {
        detalhesBody.innerHTML = `<div class="alert alert-danger mb-0">${escapeHtml(err.message || 'Erro')}</div>`;
        itensBody.innerHTML = `<tr><td colspan="5" class="text-center text-muted py-3">—</td></tr>`;
      }
    }

    document.getElementById('btnPrint').addEventListener('click', () => {
      // impressão simples do conteúdo do modal
      const w = window.open('', '_blank');
      if (!w) return;
      w.document.write(`
        <html><head>
          <meta charset="utf-8">
          <title>Fiado #${printVendaId || ''}</title>
          <link rel="stylesheet" href="assets/css/bootstrap.min.css">
        </head><body class="p-3">
          <h4 class="mb-3">Detalhes do Fiado #${printVendaId || ''}</h4>
          ${detalhesBody.innerHTML}
          <hr>
          <h6>Itens</h6>
          <table class="table table-sm table-bordered">
            <thead>${document.querySelector('#modalDetalhes thead').innerHTML}</thead>
            <tbody>${itensBody.innerHTML}</tbody>
          </table>
          <script>window.print();<\/script>
        </body></html>
      `);
      w.document.close();
    });

    // Suggest de clientes (opcional) - só aparece se existir tabela clientes
    let suggestTimer = null;
    elQ.addEventListener('input', () => {
      clearTimeout(suggestTimer);
      const text = elQ.value.trim();
      if (text.length < 2) {
        suggestBox.style.display = 'none';
        suggestBox.innerHTML = '';
        return;
      }
      suggestTimer = setTimeout(async () => {
        try {
          const url = `fiados.php?action=clientes_suggest&q=${encodeURIComponent(text)}`;
          const data = await fetchJSON(url);
          const items = data.items || [];
          if (!items.length) {
            suggestBox.style.display = 'none';
            suggestBox.innerHTML = '';
            return;
          }
          suggestBox.innerHTML = items.map(i => {
            const line = `${i.nome || ''}${i.cpf ? ' • ' + i.cpf : ''}${i.telefone ? ' • ' + i.telefone : ''}`;
            return `<button type="button" class="list-group-item list-group-item-action" data-nome="${escapeHtmlAttr(i.nome || '')}">${escapeHtml(line)}</button>`;
          }).join('');
          suggestBox.style.display = 'block';

          suggestBox.querySelectorAll('button').forEach(btn => {
            btn.addEventListener('click', () => {
              elQ.value = btn.getAttribute('data-nome') || elQ.value;
              suggestBox.style.display = 'none';
              suggestBox.innerHTML = '';
              load(1);
            });
          });
        } catch {
          suggestBox.style.display = 'none';
          suggestBox.innerHTML = '';
        }
      }, 250);
    });

    document.addEventListener('click', (e) => {
      if (!suggestBox.contains(e.target) && e.target !== elQ) {
        suggestBox.style.display = 'none';
      }
    });

    document.getElementById('btnFiltrar').addEventListener('click', () => load(1));
    document.getElementById('btnLimpar').addEventListener('click', () => {
      elQ.value = '';
      elIni.value = '';
      elFim.value = '';
      elPer.value = '15';
      load(1);
    });

    elQ.addEventListener('keydown', (e) => {
      if (e.key === 'Enter') {
        e.preventDefault();
        suggestBox.style.display = 'none';
        load(1);
      }
    });

    // helpers escape
    function escapeHtml(str) {
      return String(str ?? '').replace(/[&<>"']/g, s => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[s]));
    }
    function escapeHtmlAttr(str) {
      return escapeHtml(str).replace(/"/g, '&quot;');
    }

    // load inicial
    load(1);
  </script>
</body>
</html>