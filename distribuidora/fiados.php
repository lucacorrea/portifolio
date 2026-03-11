<?php

declare(strict_types=1);

require_once __DIR__ . '/assets/auth/auth.php';
auth_require('index.php');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

@date_default_timezone_set('America/Manaus');

/* =========================
   INCLUDES
========================= */
$paths = [
    __DIR__ . '/assets/conexao.php',
    __DIR__ . '/assets/dados/vendas/_helpers.php',
];
foreach ($paths as $p) {
    if (is_file($p)) {
        require_once $p;
    }
}

if (!function_exists('db')) {
    die('Erro Crítico: Função db() não encontrada. Verifique se assets/conexao.php existe.');
}

$pdo = db();

/* =========================
   FALLBACKS
========================= */
if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return (string) $_SESSION['csrf_token'];
    }
}

if (!function_exists('e')) {
    function e($s): string
    {
        return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('brl')) {
    function brl($v): string
    {
        return 'R$ ' . number_format((float) $v, 2, ',', '.');
    }
}

function json_out(array $data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=UTF-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function fmt_dt(?string $value): string
{
    $value = trim((string) $value);
    if ($value === '') {
        return '—';
    }

    $ts = strtotime($value);
    if ($ts === false) {
        return e($value);
    }

    return date('d/m/Y, H:i:s', $ts);
}

function status_class(string $status): string
{
    $s = strtoupper(trim($status));
    return $s === 'PAGO' ? 'status-pago' : 'status-aberto';
}

function get_filter_value(string $key, string $default = ''): string
{
    $v = $_GET[$key] ?? $default;
    return is_string($v) ? trim($v) : $default;
}

function render_rows_html(array $rows): string
{
    if (!$rows) {
        return '<tr><td colspan="8" class="text-center p-5">Nenhuma venda à prazo encontrada.</td></tr>';
    }

    $html = '';

    foreach ($rows as $f) {
        $id             = (int) ($f['id'] ?? 0);
        $vendaId        = (int) ($f['venda_id'] ?? 0);
        $cliente        = (string) ($f['cliente_nome'] ?? '');
        $createdAt      = (string) ($f['data_ref'] ?? $f['created_at'] ?? '');
        $valorTotal     = (float) ($f['valor_total'] ?? 0);
        $valorPago      = (float) ($f['valor_pago'] ?? 0);
        $valorRestante  = (float) ($f['valor_restante'] ?? 0);
        $status         = strtoupper((string) ($f['status'] ?? 'ABERTO'));
        $clienteJs      = json_encode($cliente, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $html .= '<tr>';
        $html .= '  <td><b>#' . $vendaId . '</b></td>';
        $html .= '  <td class="date-col">' . e(fmt_dt($createdAt)) . '</td>';
        $html .= '  <td class="client-col">' . e($cliente) . '</td>';
        $html .= '  <td class="money-col val-total">' . e(brl($valorTotal)) . '</td>';
        $html .= '  <td class="money-col text-success">' . e(brl($valorPago)) . '</td>';
        $html .= '  <td class="money-col val-restante">' . e(brl($valorRestante)) . '</td>';
        $html .= '  <td class="status-col"><span class="status-badge ' . e(status_class($status)) . '">' . e($status) . '</span></td>';
        $html .= '  <td class="actions-col text-end">';
        $html .= '      <div class="actions-wrap">';
        $html .= '          <button type="button" class="btn btn-light btn-pay" onclick="showDetails(' . $id . ')">';
        $html .= '              <i class="lni lni-eye"></i> Detalhes';
        $html .= '          </button>';

        if ($status === 'ABERTO' && $valorRestante > 0) {
            $html .= '      <button type="button" class="btn btn-success btn-pay text-white" onclick="openPay(' . $id . ', ' . $clienteJs . ', ' . json_encode($valorRestante) . ')">';
            $html .= '          <i class="lni lni-reply"></i> Pagar';
            $html .= '      </button>';
        }

        $html .= '      </div>';
        $html .= '  </td>';
        $html .= '</tr>';
    }

    return $html;
}

function get_fiados_page(PDO $pdo, array $filters): array
{
    $page   = max(1, (int) ($filters['page'] ?? 1));
    $per    = max(1, min(100, (int) ($filters['per'] ?? 10)));
    $di     = trim((string) ($filters['di'] ?? ''));
    $df     = trim((string) ($filters['df'] ?? ''));
    $canal  = strtoupper(trim((string) ($filters['canal'] ?? 'TODOS')));
    $status = strtoupper(trim((string) ($filters['status'] ?? 'TODOS')));
    $q      = trim((string) ($filters['q'] ?? ''));

    $where = ['1=1'];
    $bind  = [];

    if ($di !== '') {
        $where[] = 'DATE(COALESCE(v.created_at, f.created_at)) >= :di';
        $bind[':di'] = $di;
    }

    if ($df !== '') {
        $where[] = 'DATE(COALESCE(v.created_at, f.created_at)) <= :df';
        $bind[':df'] = $df;
    }

    if ($canal !== '' && $canal !== 'TODOS') {
        $where[] = 'UPPER(COALESCE(v.canal, "")) = :canal';
        $bind[':canal'] = $canal;
    }

    if ($status !== '' && $status !== 'TODOS') {
        $where[] = 'UPPER(COALESCE(f.status, "")) = :status';
        $bind[':status'] = $status;
    }

    if ($q !== '') {
        $where[] = '(
            CAST(f.venda_id AS CHAR) LIKE :q
            OR CAST(f.cliente_id AS CHAR) LIKE :q
            OR COALESCE(c.nome, "") LIKE :q
            OR COALESCE(f.status, "") LIKE :q
            OR COALESCE(v.canal, "") LIKE :q
            OR DATE_FORMAT(COALESCE(v.created_at, f.created_at), "%d/%m/%Y %H:%i:%s") LIKE :q
        )';
        $bind[':q'] = '%' . $q . '%';
    }

    $whereSql = implode(' AND ', $where);

    $sqlCount = "
        SELECT COUNT(*)
        FROM fiados f
        LEFT JOIN clientes c ON c.id = f.cliente_id
        LEFT JOIN vendas v ON v.id = f.venda_id
        WHERE {$whereSql}
    ";

    $stmtCount = $pdo->prepare($sqlCount);
    foreach ($bind as $k => $v) {
        $stmtCount->bindValue($k, $v);
    }
    $stmtCount->execute();
    $totalRows = (int) $stmtCount->fetchColumn();

    $totalPages = max(1, (int) ceil($totalRows / $per));
    if ($page > $totalPages) {
        $page = $totalPages;
    }

    $offset = ($page - 1) * $per;

    $sql = "
        SELECT
            f.id,
            f.venda_id,
            f.cliente_id,
            COALESCE(c.nome, v.cliente, CONCAT('Cliente #', f.cliente_id)) AS cliente_nome,
            COALESCE(f.valor_total, 0) AS valor_total,
            COALESCE(f.valor_pago, 0) AS valor_pago,
            COALESCE(f.valor_restante, 0) AS valor_restante,
            COALESCE(f.status, 'ABERTO') AS status,
            COALESCE(v.canal, 'PRESENCIAL') AS canal,
            COALESCE(v.created_at, f.created_at) AS data_ref,
            f.created_at,
            f.updated_at
        FROM fiados f
        LEFT JOIN clientes c ON c.id = f.cliente_id
        LEFT JOIN vendas v ON v.id = f.venda_id
        WHERE {$whereSql}
        ORDER BY COALESCE(v.created_at, f.created_at) DESC, f.id DESC
        LIMIT :offset, :per
    ";

    $stmt = $pdo->prepare($sql);
    foreach ($bind as $k => $v) {
        $stmt->bindValue($k, $v);
    }
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindValue(':per', $per, PDO::PARAM_INT);
    $stmt->execute();

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $shownFrom = $totalRows > 0 ? ($offset + 1) : 0;
    $shownTo   = min($offset + $per, $totalRows);

    return [
        'rows'       => $rows,
        'page'       => $page,
        'per'        => $per,
        'totalRows'  => $totalRows,
        'totalPages' => $totalPages,
        'shownFrom'  => $shownFrom,
        'shownTo'    => $shownTo,
    ];
}

/* =========================
   AJAX LISTAGEM
========================= */
if (isset($_GET['ajax']) && $_GET['ajax'] === 'list') {
    try {
        $result = get_fiados_page($pdo, [
            'di'     => get_filter_value('di'),
            'df'     => get_filter_value('df'),
            'canal'  => get_filter_value('canal', 'TODOS'),
            'status' => get_filter_value('status', 'TODOS'),
            'q'      => get_filter_value('q'),
            'page'   => (int) ($_GET['page'] ?? 1),
            'per'    => (int) ($_GET['per'] ?? 10),
        ]);

        json_out([
            'ok'         => true,
            'rows_html'  => render_rows_html($result['rows']),
            'page'       => $result['page'],
            'per'        => $result['per'],
            'totalRows'  => $result['totalRows'],
            'totalPages' => $result['totalPages'],
            'shownFrom'  => $result['shownFrom'],
            'shownTo'    => $result['shownTo'],
            'summary'    => $result['totalRows'] > 0
                ? 'Mostrando ' . $result['shownFrom'] . '-' . $result['shownTo'] . ' de ' . $result['totalRows']
                : '—',
        ]);
    } catch (Throwable $e) {
        json_out([
            'ok'  => false,
            'msg' => 'Erro ao carregar fiados: ' . $e->getMessage(),
        ], 500);
    }
}

/* =========================
   CARGA INICIAL EM PHP
========================= */
$csrf = csrf_token();

$filters = [
    'di'     => '',
    'df'     => '',
    'canal'  => 'TODOS',
    'status' => 'TODOS',
    'q'      => '',
    'page'   => 1,
    'per'    => 10,
];

$initialError = '';
$initialData = [
    'rows'       => [],
    'page'       => 1,
    'per'        => 10,
    'totalRows'  => 0,
    'totalPages' => 1,
    'shownFrom'  => 0,
    'shownTo'    => 0,
];

try {
    $initialData = get_fiados_page($pdo, $filters);
} catch (Throwable $e) {
    $initialError = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="csrf-token" content="<?= e($csrf) ?>">
    <link rel="shortcut icon" href="assets/images/favicon.svg" type="image/x-icon" />
    <title>Painel da Distribuidora | À Prazo</title>

    <link rel="stylesheet" href="assets/css/bootstrap.min.css" />
    <link rel="stylesheet" href="assets/css/lineicons.css" />
    <link rel="stylesheet" href="assets/css/materialdesignicons.min.css" />
    <link rel="stylesheet" href="assets/css/main.css" />

    <style>
        :root {
            --page-gap: clamp(12px, 2.2vw, 28px);
            --card-radius: 18px;
            --border-soft: rgba(148, 163, 184, 0.18);
            --text-soft: #64748b;
        }

        body,
        .main-wrapper,
        .section,
        .header {
            overflow-x: hidden;
        }

        .header .container-fluid,
        .section .container-fluid {
            padding-left: var(--page-gap);
            padding-right: var(--page-gap);
        }

        .title-wrapper {
            padding-top: clamp(18px, 2.4vw, 30px);
            padding-bottom: 8px;
        }

        .card-fiado {
            border-radius: var(--card-radius);
            border: 1px solid var(--border-soft);
            background: #fff;
            margin-bottom: 22px;
            overflow: hidden;
        }

        .card-fiado .body {
            padding: clamp(14px, 2vw, 22px);
        }

        .filter-note {
            font-size: 12px;
            color: var(--text-soft);
            font-weight: 700;
            margin-top: 2px;
        }

        .form-label {
            margin-bottom: 8px;
            font-weight: 700;
            color: #334155;
            font-size: 13px;
        }

        .form-control,
        .form-select {
            min-height: 46px;
            border-radius: 12px;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 6px 12px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 800;
            line-height: 1;
            white-space: nowrap;
        }

        .status-aberto {
            background: #fff7ed;
            color: #c2410c;
            border: 1px solid #ffedd5;
        }

        .status-pago {
            background: #f0fdf4;
            color: #15803d;
            border: 1px solid #dcfce7;
        }

        .val-total {
            font-weight: 800;
            color: #0f172a;
            white-space: nowrap;
        }

        .val-restante {
            font-weight: 800;
            color: #ef4444;
            white-space: nowrap;
        }

        .btn-pay {
            border-radius: 10px;
            padding: 7px 12px;
            font-size: 12px;
            font-weight: 700;
            line-height: 1;
            transition: all .2s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            white-space: nowrap;
        }

        .btn-pay i {
            font-size: 13px;
        }

        .table-shell {
            padding: 0 clamp(8px, 1.6vw, 18px) clamp(8px, 1.6vw, 18px);
        }

        .table-responsive {
            margin: 0;
            padding: 0;
            border-radius: 14px;
        }

        .table-custom {
            width: 100%;
            min-width: 980px;
            margin-bottom: 0;
            border-collapse: separate;
            border-spacing: 0;
        }

        .table-custom thead th {
            font-size: 13px;
            font-weight: 800;
            color: #334155;
            background: #fff;
            white-space: nowrap;
            border-bottom: 1px solid var(--border-soft);
        }

        .table-custom th,
        .table-custom td {
            padding: 16px 12px;
            vertical-align: middle;
            text-align: center;
        }

        .table-custom tbody td {
            border-bottom: 1px solid rgba(148, 163, 184, 0.12);
        }

        .table-custom th:first-child,
        .table-custom td:first-child {
            padding-left: 18px;
        }

        .table-custom th:last-child,
        .table-custom td:last-child {
            padding-right: 18px;
        }

        .date-col {
            min-width: 165px;
            white-space: nowrap;
        }

        .client-col {
            min-width: 210px;
            text-align: left !important;
            white-space: normal;
            word-break: break-word;
        }

        .money-col {
            min-width: 120px;
            white-space: nowrap;
        }

        .status-col {
            min-width: 100px;
        }

        .actions-col {
            min-width: 190px;
        }

        .actions-wrap {
            display: flex;
            justify-content: flex-end;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .pager-box {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 12px;
            padding: 14px clamp(12px, 1.6vw, 18px) 16px;
            border-top: 1px solid var(--border-soft);
            flex-wrap: wrap;
        }

        .pager-box .page-text {
            font-size: 12px;
            color: #64748b;
            font-weight: 800;
        }

        .pager-box .btn-disabled {
            opacity: .45;
            pointer-events: none;
        }

        .pager-left {
            margin-right: auto;
            font-size: 12px;
            color: #64748b;
            font-weight: 700;
        }

        .pager-actions {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-left: auto;
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

        .filters-row>[class*="col-"] {
            min-width: 0;
        }

        .auto-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 12px;
            font-weight: 700;
            color: #2563eb;
            background: #eff6ff;
            border: 1px solid #dbeafe;
            border-radius: 999px;
            padding: 7px 12px;
            white-space: nowrap;
        }

        @media (max-width: 991.98px) {
            .table-custom {
                min-width: 900px;
            }
        }

        @media (max-width: 767.98px) {
            :root {
                --card-radius: 14px;
            }

            .header .container-fluid,
            .section .container-fluid {
                padding-left: 14px;
                padding-right: 14px;
            }

            .card-fiado .body {
                padding: 14px;
            }

            .table-custom {
                min-width: 820px;
            }

            .table-custom th,
            .table-custom td {
                padding: 14px 10px;
                font-size: 13px;
            }

            .title h2 {
                font-size: 1.5rem;
            }

            .logout-btn {
                min-width: auto;
                height: 42px;
                padding: 8px 12px !important;
            }

            #modalDetalhes .text-end {
                text-align: left !important;
                margin-top: 12px;
            }

            .pager-box {
                justify-content: center;
            }

            .pager-left {
                width: 100%;
                margin-right: 0;
                text-align: center;
            }

            .pager-actions {
                margin-left: 0;
            }
        }

        @media (max-width: 575.98px) {

            .header .container-fluid,
            .section .container-fluid {
                padding-left: 12px;
                padding-right: 12px;
            }

            .brand-name {
                font-size: 16px;
            }

            .table-custom {
                min-width: 760px;
            }

            .menu-toggle-btn .main-btn {
                padding: 10px 12px;
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
                        <li><a href="fiados.php" class="active">À Prazo</a></li>
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

        <section class="section">
            <div class="container-fluid">
                <div class="title-wrapper">
                    <div class="row align-items-center g-3">
                        <div class="col-lg-8 col-md-7">
                            <div class="title">
                                <h2>Gestão de Vendas À Prazo</h2>
                                <p class="text-muted mb-0">Listagem, filtros e recebimentos (AVS)</p>
                            </div>
                        </div>
                        <div class="col-lg-4 col-md-5 text-md-end">
                            <span class="auto-pill">
                                <i class="lni lni-reload"></i> Filtros com atualização automática
                            </span>
                        </div>
                    </div>
                </div>

                <div class="card-fiado">
                    <div class="body">
                        <form id="filterForm" class="row g-3 align-items-end filters-row" onsubmit="return false;">
                            <div class="col-12 col-sm-6 col-lg-2">
                                <label class="form-label" for="fDi">Data Inicial</label>
                                <input type="date" class="form-control" id="fDi" value="">
                            </div>

                            <div class="col-12 col-sm-6 col-lg-2">
                                <label class="form-label" for="fDf">Data Final</label>
                                <input type="date" class="form-control" id="fDf" value="">
                            </div>

                            <div class="col-12 col-sm-6 col-lg-2">
                                <label class="form-label" for="fCanal">Canal</label>
                                <select class="form-select" id="fCanal">
                                    <option value="TODOS">Todos</option>
                                    <option value="PRESENCIAL">Presencial</option>
                                    <option value="DELIVERY">Delivery</option>
                                </select>
                            </div>

                            <div class="col-12 col-sm-6 col-lg-2">
                                <label class="form-label" for="fStatus">Status</label>
                                <select class="form-select" id="fStatus">
                                    <option value="TODOS">Todos</option>
                                    <option value="ABERTO">Aberto</option>
                                    <option value="PAGO">Pago</option>
                                </select>
                            </div>

                            <div class="col-12 col-lg-4">
                                <label class="form-label" for="fSearch">Cliente / Venda # / Status / Canal</label>
                                <input type="text" class="form-control" id="fSearch" placeholder="Digite para pesquisar...">
                                <div class="filter-note">Ao digitar ou selecionar, a tabela é atualizada automaticamente.</div>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="card-fiado">
                    <div class="body p-0">
                        <div class="table-shell">
                            <div class="table-responsive">
                                <table class="table table-custom">
                                    <thead>
                                        <tr>
                                            <th>Venda #</th>
                                            <th>Data</th>
                                            <th>Cliente</th>
                                            <th>Total Venda</th>
                                            <th>Total Pago</th>
                                            <th>Restante</th>
                                            <th>Status</th>
                                            <th class="text-end">Ações</th>
                                        </tr>
                                    </thead>
                                    <tbody id="fiadosTableBody">
                                        <?php if ($initialError !== ''): ?>
                                            <tr>
                                                <td colspan="8" class="text-center p-5">
                                                    <?= e('Erro ao carregar fiados: ' . $initialError) ?>
                                                </td>
                                            </tr>
                                        <?php else: ?>
                                            <?= render_rows_html($initialData['rows']) ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="pager-box">
                            <div class="pager-left" id="pgSummary">
                                <?= $initialData['totalRows'] > 0
                                    ? e('Mostrando ' . $initialData['shownFrom'] . '-' . $initialData['shownTo'] . ' de ' . $initialData['totalRows'])
                                    : '—' ?>
                            </div>

                            <div class="pager-actions">
                                <a href="#0" id="pgPrev" class="main-btn light-btn btn-hover btn-sm <?= $initialData['page'] <= 1 ? 'btn-disabled' : '' ?>" title="Anterior">
                                    <i class="lni lni-chevron-left"></i>
                                </a>

                                <span class="page-text" id="pgInfo">
                                    <?= e('Página ' . $initialData['page'] . '/' . $initialData['totalPages']) ?>
                                </span>

                                <a href="#0" id="pgNext" class="main-btn light-btn btn-hover btn-sm <?= $initialData['page'] >= $initialData['totalPages'] ? 'btn-disabled' : '' ?>" title="Próxima">
                                    <i class="lni lni-chevron-right"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <!-- mantém seus modais -->
    <div class="modal fade" id="modalDetalhes" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Detalhes da Dívida - Venda #<span id="detVendaId"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row mb-4 g-3">
                        <div class="col-md-6">
                            <h6>Informações do Cliente</h6>
                            <p class="mb-1"><b>Nome:</b> <span id="detCliente"></span></p>
                        </div>
                        <div class="col-md-6 text-end">
                            <h6>Resumo Financeiro</h6>
                            <p class="mb-0">Total: <b id="detTotal"></b></p>
                            <p class="mb-0">Pago: <b class="text-success" id="detPago"></b></p>
                            <p class="mb-0">Restante: <b class="text-danger" id="detRestante"></b></p>
                        </div>
                    </div>

                    <h6>Produtos da Venda</h6>
                    <div class="table-responsive mb-4">
                        <table class="table table-sm border p-2">
                            <thead class="bg-light">
                                <tr>
                                    <th>Produto</th>
                                    <th>Qtd</th>
                                    <th class="text-end">Preço</th>
                                    <th class="text-end">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody id="detItemsBody"></tbody>
                        </table>
                    </div>

                    <h6>Histórico de Pagamentos (AVS)</h6>
                    <div class="table-responsive">
                        <table class="table table-sm border p-2">
                            <thead class="bg-light">
                                <tr>
                                    <th>Data/Hora</th>
                                    <th>Método</th>
                                    <th class="text-end">Valor</th>
                                </tr>
                            </thead>
                            <tbody id="detPaysBody"></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modalPagamento" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title">Receber Pagamento (AVS)</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info">
                        Cliente: <b id="payCliente"></b><br>
                        Saldo Devedor: <b id="paySaldo"></b>
                    </div>

                    <form id="payForm">
                        <input type="hidden" id="payFiadoId">

                        <div class="mb-3">
                            <label class="form-label">Valor do Pagamento (R$)</label>
                            <input type="text" class="form-control form-control-lg" id="payValor" placeholder="0,00" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Método de Recebimento</label>
                            <select class="form-select" id="payMetodo">
                                <option value="DINHEIRO">Dinheiro</option>
                                <option value="PIX">Pix</option>
                                <option value="CARTAO">Cartão</option>
                            </select>
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-success btn-lg">Confirmar Recebimento</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="assets/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js"></script>
    <script>
        const DETAILS_API = 'assets/dados/fiados_api.php';
        const LIST_API = window.location.pathname + '?ajax=list';

        const modalDetalhes = new bootstrap.Modal(document.getElementById('modalDetalhes'));
        const modalPagamento = new bootstrap.Modal(document.getElementById('modalPagamento'));

        const STATE = {
            per: <?= (int) $initialData['per'] ?>,
            page: <?= (int) $initialData['page'] ?>,
            totalPages: <?= (int) $initialData['totalPages'] ?>,
            totalRows: <?= (int) $initialData['totalRows'] ?>,
        };

        const $body = document.getElementById('fiadosTableBody');
        const $pgPrev = document.getElementById('pgPrev');
        const $pgNext = document.getElementById('pgNext');
        const $pgInfo = document.getElementById('pgInfo');
        const $pgSummary = document.getElementById('pgSummary');

        const $fDi = document.getElementById('fDi');
        const $fDf = document.getElementById('fDf');
        const $fCanal = document.getElementById('fCanal');
        const $fStatus = document.getElementById('fStatus');
        const $fSearch = document.getElementById('fSearch');

        let listController = null;

        function brlJs(v) {
            return parseFloat(v || 0).toLocaleString('pt-BR', {
                style: 'currency',
                currency: 'BRL'
            });
        }

        function toDT(s) {
            try {
                const d = new Date(String(s));
                return isNaN(d.getTime()) ? String(s) : d.toLocaleString('pt-BR');
            } catch (e) {
                return String(s);
            }
        }

        function setPagerUI() {
            $pgInfo.textContent = `Página ${STATE.page}/${STATE.totalPages}`;

            const canPrev = STATE.page > 1;
            const canNext = STATE.page < STATE.totalPages;

            $pgPrev.classList.toggle('btn-disabled', !canPrev);
            $pgNext.classList.toggle('btn-disabled', !canNext);
        }

        function debounce(fn, delay = 350) {
            let timer = null;
            return (...args) => {
                clearTimeout(timer);
                timer = setTimeout(() => fn(...args), delay);
            };
        }

        function currentParams(resetPage = false) {
            return {
                di: $fDi.value || '',
                df: $fDf.value || '',
                canal: $fCanal.value || 'TODOS',
                status: $fStatus.value || 'TODOS',
                q: $fSearch.value || '',
                page: resetPage ? 1 : STATE.page,
                per: STATE.per
            };
        }

        async function loadList(resetPage = false) {
            if (listController) {
                listController.abort();
            }

            listController = new AbortController();

            const params = currentParams(resetPage);
            if (resetPage) {
                STATE.page = 1;
            }

            $body.innerHTML = '<tr><td colspan="8" class="text-center p-5">Carregando...</td></tr>';

            const qs = new URLSearchParams(params);

            try {
                const res = await fetch(`${LIST_API}&${qs.toString()}`, {
                    signal: listController.signal,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });

                const r = await res.json();

                if (!r.ok) {
                    throw new Error(r.msg || 'Falha ao carregar listagem.');
                }

                $body.innerHTML = r.rows_html || '<tr><td colspan="8" class="text-center p-5">Nenhum registro encontrado.</td></tr>';

                STATE.page = parseInt(r.page || 1, 10);
                STATE.per = parseInt(r.per || 10, 10);
                STATE.totalRows = parseInt(r.totalRows || 0, 10);
                STATE.totalPages = parseInt(r.totalPages || 1, 10);

                $pgSummary.textContent = r.summary || '—';
                setPagerUI();
            } catch (e) {
                if (e.name === 'AbortError') return;

                $body.innerHTML = `<tr><td colspan="8" class="text-center p-5">Erro ao carregar: ${String(e.message || e)}</td></tr>`;
                STATE.page = 1;
                STATE.totalPages = 1;
                STATE.totalRows = 0;
                $pgSummary.textContent = '—';
                setPagerUI();
            }
        }

        const debouncedSearch = debounce(() => loadList(true), 350);

        [$fDi, $fDf, $fCanal, $fStatus].forEach(el => {
            el.addEventListener('change', () => loadList(true));
        });

        $fSearch.addEventListener('input', debouncedSearch);

        document.getElementById('filterForm').addEventListener('submit', function(e) {
            e.preventDefault();
            loadList(true);
        });

        $pgPrev.addEventListener('click', function(e) {
            e.preventDefault();
            if (STATE.page <= 1) return;
            STATE.page--;
            loadList(false);
        });

        $pgNext.addEventListener('click', function(e) {
            e.preventDefault();
            if (STATE.page >= STATE.totalPages) return;
            STATE.page++;
            loadList(false);
        });

        async function showDetails(id) {
            try {
                const r = await fetch(`${DETAILS_API}?action=get_details&id=${id}`).then(res => res.json());

                if (!r.ok) {
                    throw new Error(r.msg || 'Falha ao buscar detalhes.');
                }

                const f = r.fiado || {};

                document.getElementById('detVendaId').innerText = f.venda_id ?? '';
                document.getElementById('detCliente').innerText = f.cliente_nome ?? '';
                document.getElementById('detTotal').innerText = brlJs(f.valor_total);
                document.getElementById('detPago').innerText = brlJs(f.valor_pago);
                document.getElementById('detRestante').innerText = brlJs(f.valor_restante);

                const items = Array.isArray(r.items) ? r.items : [];
                document.getElementById('detItemsBody').innerHTML = items.length ?
                    items.map(it => `
                        <tr>
                            <td>${it.nome ?? ''}</td>
                            <td>${it.qtd ?? ''} ${it.unidade ?? ''}</td>
                            <td class="text-end">${brlJs(it.preco_unit)}</td>
                            <td class="text-end">${brlJs(it.subtotal)}</td>
                        </tr>
                    `).join('') :
                    '<tr><td colspan="4" class="text-center">Sem itens.</td></tr>';

                const pays = Array.isArray(r.payments) ? r.payments : [];
                document.getElementById('detPaysBody').innerHTML = pays.length ?
                    pays.map(p => `
                        <tr>
                            <td>${toDT(p.created_at)}</td>
                            <td>${p.metodo ?? ''}</td>
                            <td class="text-end">${brlJs(p.valor)}</td>
                        </tr>
                    `).join('') :
                    '<tr><td colspan="3" class="text-center">Nenhum pagamento registrado.</td></tr>';

                modalDetalhes.show();
            } catch (e) {
                alert(e.message || e);
            }
        }

        function openPay(id, cliente, restante) {
            document.getElementById('payFiadoId').value = id;
            document.getElementById('payCliente').innerText = cliente;
            document.getElementById('paySaldo').innerText = brlJs(restante);
            document.getElementById('payValor').value = '';
            modalPagamento.show();

            setTimeout(() => {
                document.getElementById('payValor').focus();
            }, 250);
        }

        document.getElementById('payForm').addEventListener('submit', async function(e) {
            e.preventDefault();

            const id = document.getElementById('payFiadoId').value;
            const valorRaw = document.getElementById('payValor').value
                .replace(/\./g, '')
                .replace(',', '.')
                .replace(/[^\d.]/g, '');

            const valor = parseFloat(valorRaw);
            const metodo = document.getElementById('payMetodo').value;

            if (isNaN(valor) || valor <= 0) {
                alert('Informe um valor válido.');
                return;
            }

            try {
                const r = await fetch(`${DETAILS_API}?action=pay`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        id,
                        valor,
                        metodo
                    })
                }).then(res => res.json());

                if (!r.ok) {
                    throw new Error(r.msg || 'Falha ao pagar.');
                }

                alert(r.msg || 'Pagamento registrado!');
                modalPagamento.hide();
                loadList(false);
            } catch (e) {
                alert(e.message || e);
            }
        });

        document.getElementById('payValor').addEventListener('input', function() {
            let v = this.value.replace(/\D/g, '');
            v = (v / 100).toFixed(2).replace('.', ',');
            v = v.replace(/(\d)(?=(\d{3})+(?!\d))/g, '$1.');
            this.value = v;
        });

        setPagerUI();
    </script>
</body>

</html>