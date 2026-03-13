<?php

declare(strict_types=1);

@date_default_timezone_set('America/Manaus');
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

require_once __DIR__ . '/assets/conexao.php';
require_once __DIR__ . '/assets/dados/clientes/_helpers.php';
require_once __DIR__ . '/assets/auth/auth.php';
auth_require('index.php');

/* =========================
   FALLBACKS
========================= */
if (!function_exists('e')) {
    function e(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
if (!function_exists('require_db_or_die')) {
    function require_db_or_die(): void
    {
        if (!function_exists('db')) {
            http_response_code(500);
            exit('Erro: função db() não encontrada.');
        }
    }
}
if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        if (empty($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }
        return (string)$_SESSION['_csrf'];
    }
}
if (!function_exists('flash_pop')) {
    function flash_pop(string $key): ?string
    {
        if (!isset($_SESSION[$key])) return null;
        $v = (string)$_SESSION[$key];
        unset($_SESSION[$key]);
        return $v;
    }
}
if (!function_exists('only_digits')) {
    function only_digits(string $s): string
    {
        return preg_replace('/\D+/', '', $s) ?? '';
    }
}
if (!function_exists('cpf_fmt')) {
    function cpf_fmt(?string $cpf): string
    {
        $d = only_digits((string)$cpf);
        if ($d === '') return '—';
        $d = substr($d, 0, 11);
        if (strlen($d) !== 11) return $d;
        return substr($d, 0, 3) . '.' . substr($d, 3, 3) . '.' . substr($d, 6, 3) . '-' . substr($d, 9, 2);
    }
}
if (!function_exists('tel_fmt')) {
    function tel_fmt(?string $tel): string
    {
        $d = only_digits((string)$tel);
        if ($d === '') return '—';
        if (strlen($d) === 11) {
            return '(' . substr($d, 0, 2) . ') ' . substr($d, 2, 5) . '-' . substr($d, 7, 4);
        }
        if (strlen($d) === 10) {
            return '(' . substr($d, 0, 2) . ') ' . substr($d, 2, 4) . '-' . substr($d, 6, 4);
        }
        return $tel ?: '—';
    }
}
if (!function_exists('url_here')) {
    function url_here(string $fallback = 'clientes.php'): string
    {
        return (string)($_SERVER['REQUEST_URI'] ?? $fallback);
    }
}

require_db_or_die();
$pdo = db();

/* =========================
   UTILS
========================= */
function build_url(array $overrides = []): string
{
    $params = $_GET;
    unset($params['export']);

    foreach ($overrides as $k => $v) {
        if ($v === null || $v === '') {
            unset($params[$k]);
        } else {
            $params[$k] = (string)$v;
        }
    }

    $qs = http_build_query($params);
    return 'clientes.php' . ($qs ? ('?' . $qs) : '');
}

function format_sql_datetime(?string $dt): string
{
    $dt = trim((string)$dt);
    if ($dt === '') return '—';

    try {
        $d = new DateTime($dt);
        return $d->format('d/m/Y H:i:s');
    } catch (Throwable $e) {
        return $dt;
    }
}

function build_client_where(string $q, array &$params): string
{
    $where = " WHERE 1=1 ";

    if ($q !== '') {
        $qd = only_digits($q);

        if (ctype_digit($q) && strlen($q) <= 9) {
            $where .= " AND c.id = :id ";
            $params[':id'] = (int)$q;
        } elseif ($qd !== '') {
            $where .= " AND (
                REPLACE(REPLACE(REPLACE(c.cpf,'.',''),'-',''),' ','') LIKE :qd1
                OR REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(c.telefone,'(',''),')',''),'-',''),' ',''),'+','') LIKE :qd2
                OR c.cpf LIKE :qraw1
                OR c.telefone LIKE :qraw2
                OR c.nome LIKE :qraw3
                OR c.endereco LIKE :qraw4
            ) ";
            $params[':qd1'] = '%' . $qd . '%';
            $params[':qd2'] = '%' . $qd . '%';
            $params[':qraw1'] = '%' . $q . '%';
            $params[':qraw2'] = '%' . $q . '%';
            $params[':qraw3'] = '%' . $q . '%';
            $params[':qraw4'] = '%' . $q . '%';
        } else {
            $where .= " AND (
                c.nome LIKE :q1
                OR c.endereco LIKE :q2
                OR c.cpf LIKE :q3
                OR c.telefone LIKE :q4
            ) ";
            $params[':q1'] = '%' . $q . '%';
            $params[':q2'] = '%' . $q . '%';
            $params[':q3'] = '%' . $q . '%';
            $params[':q4'] = '%' . $q . '%';
        }
    }

    return $where;
}

/* =========================
   INPUTS
========================= */
$csrf = csrf_token();
$return_to = (string)($_SERVER['REQUEST_URI'] ?? url_here('clientes.php'));

$flashOk  = flash_pop('flash_ok');
$flashErr = flash_pop('flash_err');

$q = trim((string)($_GET['q'] ?? ''));
$page = (int)($_GET['page'] ?? 1);
if ($page < 1) $page = 1;

$per = (int)($_GET['per'] ?? 25);
$per = in_array($per, [10, 25, 50, 100], true) ? $per : 25;

/* =========================
   EXPORTAR EXCEL
========================= */
if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    $paramsExcel = [];
    $whereExcel = build_client_where($q, $paramsExcel);

    $sqlExcel = "
        SELECT c.id, c.nome, c.cpf, c.telefone, c.endereco, c.created_at
        FROM clientes c
        $whereExcel
        ORDER BY c.id DESC
    ";
    $stExcel = $pdo->prepare($sqlExcel);
    foreach ($paramsExcel as $k => $v) {
        $type = is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR;
        $stExcel->bindValue($k, $v, $type);
    }
    $stExcel->execute();
    $excelRows = $stExcel->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $filename = 'clientes_' . date('Y-m-d_H-i-s') . '.xls';
    $geradoEm = date('d/m/Y H:i:s');
    $buscaLabel = $q !== '' ? $q : '—';

    header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');

    echo "\xEF\xBB\xBF";
?>
    <html>

    <head>
        <meta charset="UTF-8">
        <style>
            table {
                border-collapse: collapse;
                font-family: Arial, sans-serif;
                font-size: 12px;
                width: auto;
            }

            td,
            th {
                border: 1px solid #000;
                padding: 4px 6px;
                vertical-align: middle;
                white-space: nowrap;
            }

            th {
                background: #ffffff;
                font-weight: bold;
                text-align: center;
            }

            .title {
                font-size: 16px;
                font-weight: bold;
                text-align: center;
            }

            .sub {
                text-align: center;
                font-weight: normal;
            }

            .left {
                text-align: left;
            }

            .center {
                text-align: center;
            }
        </style>
    </head>

    <body>
        <table>
            <tr>
                <td class="title" colspan="6">PAINEL DA DISTRIBUIDORA - CLIENTES</td>
            </tr>
            <tr>
                <td class="sub" colspan="6">Gerado em: <?= e($geradoEm) ?></td>
            </tr>
            <tr>
                <td class="sub" colspan="6">Busca: <?= e($buscaLabel) ?></td>
            </tr>
            <tr>
                <th>ID</th>
                <th>Cliente</th>
                <th>CPF</th>
                <th>Telefone</th>
                <th>Endereço</th>
                <th>Criado em</th>
            </tr>

            <?php if (!$excelRows): ?>
                <tr>
                    <td colspan="6" class="center">Nenhum cliente encontrado.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($excelRows as $r): ?>
                    <tr>
                        <td class="center"><?= (int)$r['id'] ?></td>
                        <td class="left"><?= e((string)$r['nome']) ?></td>
                        <td class="left"><?= e(cpf_fmt((string)($r['cpf'] ?? ''))) ?></td>
                        <td class="left"><?= e(tel_fmt((string)($r['telefone'] ?? ''))) ?></td>
                        <td class="left"><?= e(fmtText((string)($r['endereco'] ?? ''))) ?></td>
                        <td class="left"><?= e(format_sql_datetime((string)($r['created_at'] ?? ''))) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </table>
    </body>

    </html>
<?php
    exit;
}

/* =========================
   LISTAGEM PAGINADA
========================= */
$paramsList = [];
$whereSql = build_client_where($q, $paramsList);

$sqlTot = "SELECT COUNT(*) AS total FROM clientes c $whereSql";
$stTot = $pdo->prepare($sqlTot);
foreach ($paramsList as $k => $v) {
    $type = is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR;
    $stTot->bindValue($k, $v, $type);
}
$stTot->execute();
$totalCount = (int)($stTot->fetchColumn() ?: 0);

$pages = (int)max(1, (int)ceil($totalCount / $per));
if ($page > $pages) $page = $pages;
$off = ($page - 1) * $per;

$sql = "
    SELECT c.id, c.nome, c.cpf, c.telefone, c.endereco, c.created_at
    FROM clientes c
    $whereSql
    ORDER BY c.id DESC
    LIMIT :lim OFFSET :off
";
$st = $pdo->prepare($sql);
foreach ($paramsList as $k => $v) {
    $type = is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR;
    $st->bindValue($k, $v, $type);
}
$st->bindValue(':lim', $per, PDO::PARAM_INT);
$st->bindValue(':off', $off, PDO::PARAM_INT);
$st->execute();
$rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
$currentCount = count($rows);
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="csrf-token" content="<?= e($csrf) ?>">

    <link rel="shortcut icon" href="assets/images/favicon.svg" type="image/x-icon" />
    <title>Painel da Distribuidora | Clientes</title>

    <link rel="stylesheet" href="assets/css/bootstrap.min.css" />
    <link rel="stylesheet" href="assets/css/lineicons.css" rel="stylesheet" type="text/css" />
    <link rel="stylesheet" href="assets/css/materialdesignicons.min.css" rel="stylesheet" type="text/css" />
    <link rel="stylesheet" href="assets/css/main.css" />

    <style>
        .section {
            padding-top: 18px;
        }

        .page-pad {
            padding-top: 12px;
        }

        .main-btn.btn-compact {
            height: 38px !important;
            padding: 10px 14px !important;
            font-size: 13px !important;
            line-height: 1 !important;
            border-radius: 10px !important;
        }

        .form-control.compact,
        .form-select.compact {
            height: 40px;
            padding: 10px 12px;
            font-size: 14px;
            border-radius: 10px;
        }

        .cardx {
            border: 1px solid rgba(148, 163, 184, .22);
            border-radius: 16px;
            background: #fff;
            overflow: hidden;
        }

        .cardx .head {
            padding: 14px 16px;
            border-bottom: 1px solid rgba(148, 163, 184, .18);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
        }

        .cardx .body {
            padding: 14px 16px;
        }

        .muted {
            font-size: 13px;
            color: #64748b;
        }

        .pill {
            padding: 7px 12px;
            border-radius: 999px;
            border: 1px solid rgba(148, 163, 184, .22);
            font-weight: 900;
            font-size: 12px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(248, 250, 252, .7);
            white-space: nowrap;
        }

        .pill.ok {
            border-color: rgba(34, 197, 94, .25);
            background: rgba(240, 253, 244, .9);
            color: #166534;
        }

        .toolbar {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: center;
            justify-content: flex-end;
        }

        .table-wrap {
            overflow: auto;
            border-radius: 14px;
        }

        #tbClientes {
            width: 100%;
            min-width: 980px;
            table-layout: fixed;
        }

        #tbClientes thead th {
            position: sticky;
            top: 0;
            z-index: 2;
            background: #f8fafc;
            border-bottom: 1px solid rgba(148, 163, 184, .25);
            font-size: 13.5px;
            color: #0f172a;
            padding: 12px 12px;
            white-space: nowrap;
        }

        #tbClientes tbody td {
            border-top: 1px solid rgba(148, 163, 184, .15);
            padding: 14px 12px;
            font-size: 14.5px;
            line-height: 1.25;
            vertical-align: middle;
            color: #0f172a;
            background: #fff;
        }

        .td-nowrap {
            white-space: nowrap;
        }

        .td-clip {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            display: block;
            max-width: 100%;
        }

        .col-id {
            width: 70px;
        }

        .col-nome {
            width: 300px;
        }

        .col-cpf {
            width: 150px;
        }

        .col-tel {
            width: 170px;
        }

        .col-end {
            width: 260px;
        }

        .col-created {
            width: 190px;
        }

        .col-acoes {
            width: 260px;
        }

        .actions-wrap {
            display: flex;
            gap: 8px;
            flex-wrap: nowrap;
            justify-content: flex-end;
            align-items: center;
        }

        .btn-action {
            height: 36px !important;
            padding: 8px 12px !important;
            font-size: 13px !important;
            border-radius: 10px !important;
            white-space: nowrap;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-action i {
            font-size: 16px;
        }

        @media (max-width:1400px) {
            .btn-action .act-text {
                display: none;
            }

            .btn-action {
                padding: 8px 10px !important;
            }
        }

        @media (max-width:992px) {
            #tbClientes {
                min-width: 920px;
            }

            .actions-wrap {
                flex-wrap: wrap;
                justify-content: flex-start;
            }

            .btn-action .act-text {
                display: inline;
            }
        }

        .table-footer-nav {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            flex-wrap: wrap;
            margin-top: 12px;
            padding-top: 6px;
        }

        .pager-box {
            display: flex;
            align-items: center;
            gap: 14px;
            justify-content: flex-end;
            flex-wrap: wrap;
        }

        .page-btn,
        .page-btn-link {
            width: 42px;
            height: 42px;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            background: #f8fafc;
            color: #475569;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            text-decoration: none !important;
            transition: .2s ease;
        }

        .page-btn-link:hover {
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

        .dt-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px;
        }

        .dt-item {
            border: 1px solid rgba(148, 163, 184, .18);
            border-radius: 12px;
            padding: 10px 12px;
            background: #fff;
        }

        .dt-label {
            font-size: 12px;
            color: #64748b;
            font-weight: 900;
            margin-bottom: 4px;
        }

        .dt-val {
            font-size: 14px;
            color: #0f172a;
            font-weight: 900;
            word-break: break-word;
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

        @media (max-width:768px) {
            .dt-grid {
                grid-template-columns: 1fr;
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

                <li class="nav-item nav-item-has-children active">
                    <a href="#0" class="collapsed" data-bs-toggle="collapse" data-bs-target="#ddmenu_cadastros" aria-controls="ddmenu_cadastros" aria-expanded="false">
                        <span class="icon"><i class="lni lni-users"></i></span>
                        <span class="text">Cadastros</span>
                    </a>
                    <ul id="ddmenu_cadastros" class="collapse dropdown-nav show">
                        <li><a href="clientes.php" class="active">Clientes</a></li>
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
            <div class="container-fluid page-pad">

                <div class="title-wrapper pt-10">
                    <div class="row align-items-center">
                        <div class="col-md-6">
                            <div class="title">
                                <h2>Clientes</h2>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="breadcrumb-wrapper">
                                <nav aria-label="breadcrumb">
                                    <ol class="breadcrumb">
                                        <li class="breadcrumb-item"><a href="#">Cadastros</a></li>
                                        <li class="breadcrumb-item active" aria-current="page">Clientes</li>
                                    </ol>
                                </nav>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if ($flashOk): ?>
                    <div class="alert alert-success" style="border-radius:14px;" data-autohide="1"><?= e($flashOk) ?></div>
                <?php endif; ?>
                <?php if ($flashErr): ?>
                    <div class="alert alert-danger" style="border-radius:14px;" data-autohide="1"><?= e($flashErr) ?></div>
                <?php endif; ?>

                <div class="cardx mb-3">
                    <div class="head">
                        <div>
                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                <span class="pill ok" id="pillCount"><?= (int)$totalCount ?> clientes</span>
                                <span class="muted" id="lblRange"><?= $q !== '' ? 'Busca: ' . e($q) : '—' ?></span>
                            </div>
                            <div class="muted mt-1">Digite e pressione Enter para pesquisar.</div>
                        </div>

                        <div class="toolbar">
                            <button type="button" class="main-btn primary-btn btn-hover btn-compact" id="btnNovo">
                                <i class="lni lni-plus me-1"></i> Novo
                            </button>

                            <select id="per" class="form-select compact" style="min-width:190px;">
                                <option value="10" <?= $per === 10 ? 'selected' : '' ?>>10 por página</option>
                                <option value="25" <?= $per === 25 ? 'selected' : '' ?>>25 por página</option>
                                <option value="50" <?= $per === 50 ? 'selected' : '' ?>>50 por página</option>
                                <option value="100" <?= $per === 100 ? 'selected' : '' ?>>100 por página</option>
                            </select>

                            <a href="<?= e(build_url(['export' => 'excel', 'page' => 1])) ?>" class="main-btn light-btn btn-hover btn-compact">
                                <i class="lni lni-download me-1"></i> Exportar Excel
                            </a>

                            <button type="button" class="main-btn light-btn btn-hover btn-compact" id="btnLimpar">
                                <i class="lni lni-eraser me-1"></i> Limpar
                            </button>
                        </div>
                    </div>

                    <div class="body">
                        <div class="row g-2 align-items-end">
                            <div class="col-12">
                                <label class="form-label" style="font-weight:900;color:#0f172a;">
                                    Buscar (Nome / CPF / Telefone / ID / Endereço)
                                </label>
                                <input
                                    type="text"
                                    class="form-control compact"
                                    id="q"
                                    value="<?= e($q) ?>"
                                    placeholder="Digite... (pode com ou sem máscara)"
                                    autocomplete="off">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="cardx">
                    <div class="head">
                        <div class="muted"><b>Clientes</b> • ações: Detalhes / Editar / Excluir</div>
                        <div class="muted" id="pageMeta">Página <?= (int)$page ?> de <?= (int)$pages ?></div>
                    </div>

                    <div class="body">
                        <div class="table-wrap">
                            <table class="table table-hover mb-0" id="tbClientes">
                                <thead>
                                    <tr>
                                        <th class="col-id">ID</th>
                                        <th class="col-nome">Nome</th>
                                        <th class="col-cpf">CPF</th>
                                        <th class="col-tel">Telefone</th>
                                        <th class="col-end">Endereço</th>
                                        <th class="col-created">Criado em</th>
                                        <th class="col-acoes text-end">Ações</th>
                                    </tr>
                                </thead>

                                <tbody id="tbody">
                                    <?php if (!$rows): ?>
                                        <tr>
                                            <td colspan="7" class="muted">Nenhum cliente encontrado.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($rows as $r):
                                            $id = (int)$r['id'];
                                            $nome = (string)$r['nome'];
                                            $cpfRaw = (string)($r['cpf'] ?? '');
                                            $telRaw = (string)($r['telefone'] ?? '');
                                            $end = (string)($r['endereco'] ?? '');
                                            $created = (string)($r['created_at'] ?? '');
                                        ?>
                                            <tr>
                                                <td class="td-nowrap fw-1000"><?= $id ?></td>
                                                <td><span class="td-clip" title="<?= e($nome) ?>"><?= e($nome) ?></span></td>
                                                <td class="td-nowrap"><?= e(cpf_fmt($cpfRaw)) ?></td>
                                                <td class="td-nowrap"><?= e(tel_fmt($telRaw)) ?></td>
                                                <td><span class="td-clip" title="<?= e($end) ?>"><?= e($end ?: '—') ?></span></td>
                                                <td class="td-nowrap"><?= e(format_sql_datetime($created)) ?></td>

                                                <td class="text-end">
                                                    <div class="actions-wrap">
                                                        <button type="button" class="main-btn light-btn btn-hover btn-action btnDetalhes"
                                                            data-id="<?= $id ?>"
                                                            data-nome="<?= e($nome) ?>"
                                                            data-cpf="<?= e(cpf_fmt($cpfRaw)) ?>"
                                                            data-tel="<?= e(tel_fmt($telRaw)) ?>"
                                                            data-end="<?= e($end) ?>"
                                                            data-created="<?= e(format_sql_datetime($created)) ?>">
                                                            <i class="lni lni-eye"></i> <span class="act-text">Detalhes</span>
                                                        </button>

                                                        <button type="button" class="main-btn primary-btn btn-hover btn-action btnEditar"
                                                            data-id="<?= $id ?>"
                                                            data-nome="<?= e($nome) ?>"
                                                            data-cpfdigits="<?= e(only_digits($cpfRaw)) ?>"
                                                            data-telraw="<?= e($telRaw) ?>"
                                                            data-end="<?= e($end) ?>">
                                                            <i class="lni lni-pencil"></i> <span class="act-text">Editar</span>
                                                        </button>

                                                        <button type="button" class="main-btn light-btn btn-hover btn-action btnExcluir"
                                                            data-id="<?= $id ?>"
                                                            data-nome="<?= e($nome) ?>"
                                                            data-cpf="<?= e(cpf_fmt($cpfRaw)) ?>"
                                                            data-tel="<?= e(tel_fmt($telRaw)) ?>">
                                                            <i class="lni lni-trash-can"></i> <span class="act-text">Excluir</span>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <div class="table-footer-nav">
                            <p class="text-sm text-gray mb-0" id="infoCount">
                                Mostrando <?= $currentCount ?> item(ns) nesta página de clientes. Total filtrado: <?= $totalCount ?>.
                            </p>

                            <div class="pager-box" id="pagerBox">
                                <?php if ($page > 1): ?>
                                    <a class="page-btn-link" href="<?= e(build_url(['page' => $page - 1])) ?>" title="Anterior">
                                        <i class="lni lni-chevron-left"></i>
                                    </a>
                                <?php else: ?>
                                    <button class="page-btn" type="button" disabled title="Anterior">
                                        <i class="lni lni-chevron-left"></i>
                                    </button>
                                <?php endif; ?>

                                <span class="page-info">Página <?= (int)$page ?>/<?= (int)$pages ?></span>

                                <?php if ($page < $pages): ?>
                                    <a class="page-btn-link" href="<?= e(build_url(['page' => $page + 1])) ?>" title="Próxima">
                                        <i class="lni lni-chevron-right"></i>
                                    </a>
                                <?php else: ?>
                                    <button class="page-btn" type="button" disabled title="Próxima">
                                        <i class="lni lni-chevron-right"></i>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </section>

        <footer class="footer">
            <div class="container-fluid">
                <p class="text-sm muted mb-0">© Painel da Distribuidora • Clientes</p>
            </div>
        </footer>
    </main>

    <div class="modal fade" id="mdForm" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content" style="border-radius:16px;">
                <form method="post" id="formCliente" action="assets/dados/clientes/salvarClientes.php">
                    <div class="modal-header">
                        <div>
                            <h5 class="modal-title" id="fmTitulo">Novo cliente</h5>
                            <div class="muted" id="fmSub">CPF só números • Telefone com máscara</div>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>

                    <div class="modal-body">
                        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                        <input type="hidden" name="return_to" value="<?= e($return_to) ?>">
                        <input type="hidden" name="id" id="fmId" value="">

                        <div class="row g-2">
                            <div class="col-md-12">
                                <label class="form-label" style="font-weight:900;">Nome *</label>
                                <input type="text" class="form-control compact" name="nome" id="fmNome" required>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label" style="font-weight:900;">CPF * (somente números)</label>
                                <input type="text" class="form-control compact" name="cpf" id="fmCpf" required maxlength="11" inputmode="numeric">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label" style="font-weight:900;">Telefone * (com máscara)</label>
                                <input type="text" class="form-control compact" name="telefone" id="fmTel" required maxlength="16" inputmode="tel" placeholder="(99) 99999-9999">
                            </div>

                            <div class="col-12">
                                <label class="form-label" style="font-weight:900;">Endereço</label>
                                <input type="text" class="form-control compact" name="endereco" id="fmEnd">
                            </div>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button class="main-btn primary-btn btn-hover btn-compact" type="submit">
                            <i class="lni lni-save me-1"></i> Salvar
                        </button>
                        <button class="main-btn light-btn btn-hover btn-compact" type="button" data-bs-dismiss="modal">
                            <i class="lni lni-close me-1"></i> Cancelar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="mdDetalhes" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content" style="border-radius:16px;">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title" id="dtTitulo">Detalhes</h5>
                        <div class="muted">—</div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <div class="dt-grid">
                        <div class="dt-item">
                            <div class="dt-label">Nome</div>
                            <div class="dt-val" id="dtNome">—</div>
                        </div>

                        <div class="dt-item">
                            <div class="dt-label">Criado em</div>
                            <div class="dt-val" id="dtCreated">—</div>
                        </div>

                        <div class="dt-item">
                            <div class="dt-label">CPF</div>
                            <div class="dt-val" id="dtCpf">—</div>
                        </div>

                        <div class="dt-item">
                            <div class="dt-label">Telefone</div>
                            <div class="dt-val" id="dtTel">—</div>
                        </div>

                        <div class="dt-item" style="grid-column:1/-1;">
                            <div class="dt-label">Endereço</div>
                            <div class="dt-val" id="dtEnd">—</div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button class="main-btn light-btn btn-hover btn-compact" data-bs-dismiss="modal" type="button">
                        <i class="lni lni-close me-1"></i> Fechar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="mdExcluir" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content" style="border-radius:16px;">
                <form method="post" action="assets/dados/clientes/excluirClientes.php">
                    <div class="modal-header">
                        <h5 class="modal-title">Excluir cliente</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>

                    <div class="modal-body">
                        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                        <input type="hidden" name="return_to" value="<?= e($return_to) ?>">
                        <input type="hidden" name="id" id="exId" value="">
                        <div class="muted">Tem certeza que deseja excluir?</div>
                        <div class="fw-1000 mt-2" id="exNome">—</div>
                        <div class="muted" id="exMeta">—</div>
                    </div>

                    <div class="modal-footer">
                        <button class="main-btn light-btn btn-hover btn-compact" type="button" data-bs-dismiss="modal">
                            <i class="lni lni-close me-1"></i> Cancelar
                        </button>
                        <button class="main-btn primary-btn btn-hover btn-compact" type="submit">
                            <i class="lni lni-trash-can me-1"></i> Excluir
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="assets/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js"></script>

    <script>
        document.querySelectorAll('[data-autohide="1"]').forEach(el => {
            setTimeout(() => {
                el.style.transition = 'opacity .25s ease';
                el.style.opacity = '0';
                setTimeout(() => el.remove(), 260);
            }, 1000);
        });

        const $ = (id) => document.getElementById(id);

        function onlyDigits(s) {
            return String(s || '').replace(/\D+/g, '');
        }

        function maskTel(v) {
            const d = onlyDigits(v).slice(0, 11);
            if (!d) return '';
            if (d.length <= 2) return `(${d}`;
            const dd = d.slice(0, 2);
            const rest = d.slice(2);
            if (rest.length <= 4) return `(${dd}) ${rest}`;
            if (rest.length <= 8) return `(${dd}) ${rest.slice(0,4)}-${rest.slice(4)}`;
            return `(${dd}) ${rest.slice(0,5)}-${rest.slice(5)}`;
        }

        function applyFilters(resetPage = true) {
            const params = new URLSearchParams(window.location.search);
            const q = $('q').value.trim();
            const per = $('per').value;

            if (q) params.set('q', q);
            else params.delete('q');

            if (per) params.set('per', per);
            else params.delete('per');

            if (resetPage) params.set('page', '1');

            params.delete('export');

            const url = window.location.pathname + (params.toString() ? ('?' + params.toString()) : '');
            window.location.href = url;
        }

        function bindRowActions() {
            const mdForm = new bootstrap.Modal($('mdForm'));
            const mdDetalhes = new bootstrap.Modal($('mdDetalhes'));
            const mdExcluir = new bootstrap.Modal($('mdExcluir'));

            const formCliente = $('formCliente');
            const fmTitulo = $('fmTitulo');
            const fmSub = $('fmSub');
            const fmId = $('fmId');
            const fmNome = $('fmNome');
            const fmCpf = $('fmCpf');
            const fmTel = $('fmTel');
            const fmEnd = $('fmEnd');

            const btnNovo = $('btnNovo');
            if (btnNovo && !btnNovo.dataset.bound) {
                btnNovo.dataset.bound = '1';
                btnNovo.addEventListener('click', () => {
                    formCliente.action = 'assets/dados/clientes/salvarClientes.php';
                    fmTitulo.textContent = 'Novo cliente';
                    fmSub.textContent = 'CPF só números • Telefone com máscara';
                    fmId.value = '';
                    fmNome.value = '';
                    fmCpf.value = '';
                    fmTel.value = '';
                    fmEnd.value = '';
                    mdForm.show();
                    setTimeout(() => fmNome.focus(), 150);
                });
            }

            if (fmCpf && !fmCpf.dataset.bound) {
                fmCpf.dataset.bound = '1';
                fmCpf.addEventListener('input', e => e.target.value = onlyDigits(e.target.value).slice(0, 11));
            }

            if (fmTel && !fmTel.dataset.bound) {
                fmTel.dataset.bound = '1';
                fmTel.addEventListener('input', e => e.target.value = maskTel(e.target.value));
            }

            document.querySelectorAll('.btnDetalhes').forEach(btn => {
                btn.addEventListener('click', () => {
                    $('dtTitulo').textContent = `Detalhes do cliente #${btn.dataset.id}`;
                    $('dtNome').textContent = btn.dataset.nome || '—';
                    $('dtCpf').textContent = btn.dataset.cpf || '—';
                    $('dtTel').textContent = btn.dataset.tel || '—';
                    $('dtEnd').textContent = (btn.dataset.end && btn.dataset.end.trim() !== '') ? btn.dataset.end : '—';
                    $('dtCreated').textContent = btn.dataset.created || '—';
                    mdDetalhes.show();
                });
            });

            document.querySelectorAll('.btnEditar').forEach(btn => {
                btn.addEventListener('click', () => {
                    formCliente.action = 'assets/dados/clientes/editarClientes.php';
                    fmTitulo.textContent = `Editar cliente #${btn.dataset.id}`;
                    fmSub.textContent = 'CPF só números • Telefone com máscara';
                    fmId.value = btn.dataset.id || '';
                    fmNome.value = btn.dataset.nome || '';
                    fmCpf.value = (btn.dataset.cpfdigits || '').slice(0, 11);
                    fmTel.value = maskTel(btn.dataset.telraw || '');
                    fmEnd.value = btn.dataset.end || '';
                    mdForm.show();
                    setTimeout(() => fmNome.focus(), 150);
                });
            });

            document.querySelectorAll('.btnExcluir').forEach(btn => {
                btn.addEventListener('click', () => {
                    $('exId').value = btn.dataset.id || '';
                    $('exNome').textContent = btn.dataset.nome || '—';
                    $('exMeta').textContent = `${btn.dataset.cpf || ''} • ${btn.dataset.tel || ''}`;
                    mdExcluir.show();
                });
            });
        }

        $('q').addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                applyFilters(true);
            }
            if (e.key === 'Escape') {
                $('q').value = '';
                applyFilters(true);
            }
        });

        $('per').addEventListener('change', () => applyFilters(true));

        $('btnLimpar').addEventListener('click', () => {
            window.location.href = 'clientes.php';
        });

        bindRowActions();
    </script>
</body>

</html>