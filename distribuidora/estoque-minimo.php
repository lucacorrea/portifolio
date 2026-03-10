<?php

declare(strict_types=1);

session_start();
require_once __DIR__ . '/assets/conexao.php';
require_once __DIR__ . '/assets/dados/estoque-minimo/_helpers.php';

$pdo = db();

$flash = function_exists('flash_pop') ? flash_pop() : null;
$csrf  = function_exists('csrf_token') ? csrf_token() : '';

if (!function_exists('e')) {
    function e(string $v): string
    {
        return htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

/**
 * IMAGENS:
 * No banco está assim: "images/arquivo.png"
 * Como o arquivo está em /assets/dados/produtos/, então para exibir:
 *   ./assets/dados/produtos/ + images/arquivo.png
 */
function produto_img_url(string $img): string
{
    $img = trim($img);
    if ($img === '') return '';
    if (preg_match('~^(https?://|/|\.{1,2}/)~', $img)) return $img;
    return './assets/dados/produtos/' . ltrim($img, '/');
}

function calc_status(int $estoque, int $minimo): string
{
    if ($estoque <= 0 && $minimo > 0) return 'CRITICO';
    if ($estoque < $minimo) return 'BAIXO';
    return 'OK';
}

function badge_html(string $st): string
{
    if ($st === 'CRITICO') {
        return '<span class="badge-soft badge-soft-danger">CRÍTICO</span>';
    }
    if ($st === 'BAIXO') {
        return '<span class="badge-soft badge-soft-warning">ABAIXO</span>';
    }
    return '<span class="badge-soft badge-soft-success">OK</span>';
}

function build_query_string(array $overrides = []): string
{
    $params = $_GET;
    unset($params['action']);

    foreach ($overrides as $k => $v) {
        if ($v === null || $v === '') {
            unset($params[$k]);
        } else {
            $params[$k] = $v;
        }
    }

    return http_build_query($params);
}

/** =========================
 * PARÂMETROS
 * ========================= */
$q         = trim((string)($_GET['q'] ?? ''));
$categoria = trim((string)($_GET['categoria'] ?? ''));
$tipo      = strtoupper(trim((string)($_GET['tipo'] ?? 'BAIXO')));
$page      = max(1, (int)($_GET['page'] ?? 1));
$perPage   = max(1, min(100, (int)($_GET['per'] ?? 5)));
$action    = strtolower(trim((string)($_GET['action'] ?? '')));

if (!in_array($tipo, ['BAIXO', 'CRITICO', 'TODOS'], true)) {
    $tipo = 'BAIXO';
}

/** categorias (para filtro) */
$categorias = [];
try {
    $categorias = $pdo->query("SELECT id, nome, status FROM categorias ORDER BY nome ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $categorias = [];
}

/** =========================
 * WHERE DINÂMICO
 * ========================= */
$where = [];
$params = [];

if ($q !== '') {
    $where[] = "(p.codigo LIKE :q OR p.nome LIKE :q OR COALESCE(c.nome,'') LIKE :q OR COALESCE(p.unidade,'') LIKE :q)";
    $params[':q'] = '%' . $q . '%';
}

if ($categoria !== '') {
    $where[] = "COALESCE(c.nome,'') = :categoria";
    $params[':categoria'] = $categoria;
}

if ($tipo === 'CRITICO') {
    $where[] = "(COALESCE(p.estoque,0) <= 0 AND COALESCE(p.minimo,0) > 0)";
} elseif ($tipo === 'BAIXO') {
    $where[] = "(COALESCE(p.estoque,0) < COALESCE(p.minimo,0))";
}

$whereSql = $where ? (' WHERE ' . implode(' AND ', $where)) : '';

/** =========================
 * KPIs GERAIS
 * ========================= */
$kCrit = 0;
$kBaixo = 0;
$kOk = 0;

try {
    $allKpis = $pdo->query("
        SELECT COALESCE(estoque,0) AS estoque, COALESCE(minimo,0) AS minimo
        FROM produtos
    ")->fetchAll(PDO::FETCH_ASSOC);

    foreach ($allKpis as $p) {
        $estoque = (int)($p['estoque'] ?? 0);
        $minimo  = (int)($p['minimo'] ?? 0);
        $st = calc_status($estoque, $minimo);

        if ($st === 'CRITICO') $kCrit++;
        elseif ($st === 'BAIXO') $kBaixo++;
        else $kOk++;
    }
} catch (Throwable $e) {
    $kCrit = 0;
    $kBaixo = 0;
    $kOk = 0;
}

/** =========================
 * EXPORTAR EXCEL
 * ========================= */
if ($action === 'excel') {
    try {
        $sqlExcel = "
            SELECT
                p.id,
                p.codigo,
                p.nome,
                p.unidade,
                COALESCE(p.estoque,0) AS estoque,
                COALESCE(p.minimo,0) AS minimo,
                p.imagem,
                COALESCE(c.nome,'—') AS categoria_nome
            FROM produtos p
            LEFT JOIN categorias c ON c.id = p.categoria_id
            {$whereSql}
            ORDER BY p.id DESC
        ";

        $stExcel = $pdo->prepare($sqlExcel);
        foreach ($params as $k => $v) {
            $stExcel->bindValue($k, $v, PDO::PARAM_STR);
        }
        $stExcel->execute();
        $rowsExcel = $stExcel->fetchAll(PDO::FETCH_ASSOC);

        $now = new DateTime('now');
        $dt = $now->format('d/m/Y H:i:s');
        $fileDt = $now->format('Y-m-d_H-i-s');

        header('Content-Type: application/vnd.ms-excel; charset=utf-8');
        header('Content-Disposition: attachment; filename="estoque_minimo_' . $fileDt . '.xls"');
        header('Pragma: no-cache');
        header('Expires: 0');

        echo "\xEF\xBB\xBF";
?>
        <html>

        <head>
            <meta charset="utf-8">
            <style>
                table {
                    border-collapse: collapse;
                    font-family: Arial, sans-serif;
                    font-size: 12px;
                    width: 100%;
                }

                td,
                th {
                    border: 1px solid #000;
                    padding: 6px 8px;
                    vertical-align: middle;
                }

                th {
                    background: #dbe5f1;
                    font-weight: bold;
                }

                .title {
                    font-size: 16px;
                    font-weight: bold;
                    text-align: center;
                    background: #ddebf7;
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
                    <td class="title" colspan="8">PAINEL DA DISTRIBUIDORA - ESTOQUE MÍNIMO</td>
                </tr>
                <tr>
                    <td colspan="8">Gerado em: <?= e($dt) ?></td>
                </tr>
                <tr>
                    <td colspan="8">Categoria: <?= e($categoria !== '' ? $categoria : 'Todas') ?> | Filtro: <?= e($tipo) ?> | Busca: <?= e($q !== '' ? $q : '—') ?></td>
                </tr>
                <tr>
                    <th class="left">Código</th>
                    <th class="left">Produto</th>
                    <th class="left">Categoria</th>
                    <th class="left">Unidade</th>
                    <th class="center">Estoque</th>
                    <th class="center">Mínimo</th>
                    <th class="center">Situação</th>
                    <th class="center">Sugestão</th>
                </tr>

                <?php if (!$rowsExcel): ?>
                    <tr>
                        <td colspan="8" class="center">Nenhum item encontrado.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($rowsExcel as $p): ?>
                        <?php
                        $codigo  = trim((string)($p['codigo'] ?? '')) ?: '—';
                        $nome    = trim((string)($p['nome'] ?? '')) ?: '—';
                        $cat     = trim((string)($p['categoria_nome'] ?? '')) ?: '—';
                        $unidade = trim((string)($p['unidade'] ?? '')) ?: '—';
                        $estoque = (int)($p['estoque'] ?? 0);
                        $minimo  = (int)($p['minimo'] ?? 0);
                        $st      = calc_status($estoque, $minimo);
                        $sug     = max(0, $minimo - $estoque);
                        ?>
                        <tr>
                            <td class="left"><?= e($codigo) ?></td>
                            <td class="left"><?= e($nome) ?></td>
                            <td class="left"><?= e($cat) ?></td>
                            <td class="left"><?= e($unidade) ?></td>
                            <td class="center"><?= $estoque ?></td>
                            <td class="center"><?= $minimo ?></td>
                            <td class="center"><?= e($st) ?></td>
                            <td class="center"><?= $st === 'OK' ? '0' : '+' . $sug ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </table>
        </body>

        </html>
<?php
        exit;
    } catch (Throwable $e) {
        http_response_code(500);
        echo 'Falha ao exportar Excel.';
        exit;
    }
}

/** =========================
 * TOTAL DE REGISTROS
 * ========================= */
$total = 0;
try {
    $sqlCount = "
        SELECT COUNT(*)
        FROM produtos p
        LEFT JOIN categorias c ON c.id = p.categoria_id
        {$whereSql}
    ";
    $stCount = $pdo->prepare($sqlCount);
    foreach ($params as $k => $v) {
        $stCount->bindValue($k, $v, PDO::PARAM_STR);
    }
    $stCount->execute();
    $total = (int)$stCount->fetchColumn();
} catch (Throwable $e) {
    $total = 0;
}

$totalPages = max(1, (int)ceil($total / $perPage));
if ($page > $totalPages) $page = $totalPages;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $perPage;

/** =========================
 * PRODUTOS PAGINADOS
 * ========================= */
$produtos = [];
try {
    $sql = "
        SELECT
            p.id,
            p.codigo,
            p.nome,
            p.unidade,
            COALESCE(p.estoque,0) AS estoque,
            COALESCE(p.minimo,0) AS minimo,
            p.imagem,
            COALESCE(c.nome,'—') AS categoria_nome
        FROM produtos p
        LEFT JOIN categorias c ON c.id = p.categoria_id
        {$whereSql}
        ORDER BY p.id DESC
        LIMIT :limit OFFSET :offset
    ";

    $st = $pdo->prepare($sql);
    foreach ($params as $k => $v) {
        $st->bindValue($k, $v, PDO::PARAM_STR);
    }
    $st->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $st->bindValue(':offset', $offset, PDO::PARAM_INT);
    $st->execute();
    $produtos = $st->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $produtos = [];
    if (function_exists('flash_set')) {
        flash_set('danger', 'Falha ao carregar produtos (verifique tabela produtos).');
        $flash = flash_pop();
    }
}

$shown = count($produtos);
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />

    <link rel="shortcut icon" href="assets/images/favicon.svg" type="image/x-icon" />
    <title>Painel da Distribuidora | Estoque Mínimo</title>

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

        .table td,
        .table th {
            vertical-align: middle;
        }

        .minw-120 {
            min-width: 120px;
        }

        .minw-140 {
            min-width: 140px;
        }

        .minw-160 {
            min-width: 160px;
        }

        .minw-200 {
            min-width: 200px;
        }

        .table-responsive {
            -webkit-overflow-scrolling: touch;
        }

        #tbMinimo {
            width: 100%;
            min-width: 1180px;
        }

        #tbMinimo th,
        #tbMinimo td {
            white-space: nowrap !important;
            word-break: normal !important;
            overflow-wrap: normal !important;
        }

        .badge-soft {
            padding: .35rem .6rem;
            border-radius: 999px;
            font-weight: 700;
            font-size: .72rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 96px;
        }

        .badge-soft-danger {
            background: rgba(239, 68, 68, .14);
            color: #b91c1c;
        }

        .badge-soft-warning {
            background: rgba(245, 158, 11, .14);
            color: #b45309;
        }

        .badge-soft-success {
            background: rgba(34, 197, 94, .12);
            color: #16a34a;
        }

        .badge-soft-gray {
            background: rgba(148, 163, 184, .18);
            color: #475569;
        }

        .prod-img {
            width: 42px;
            height: 42px;
            object-fit: cover;
            border-radius: 10px;
            border: 1px solid rgba(148, 163, 184, .35);
            background: #fff;
        }

        .kpi-card {
            display: flex;
            gap: 12px;
            align-items: center;
            padding: 16px;
            border-radius: 14px;
            border: 1px solid rgba(148, 163, 184, .28);
            background: #fff;
            height: 100%;
        }

        .kpi-ico {
            width: 42px;
            height: 42px;
            border-radius: 12px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 1px solid rgba(148, 163, 184, .25);
        }

        .kpi-ico i {
            font-size: 18px;
        }

        .kpi-title {
            margin: 0;
            font-size: 12px;
            color: #64748b;
            font-weight: 700;
            line-height: 1.2;
        }

        .kpi-value {
            margin: 2px 0 0;
            font-size: 20px;
            font-weight: 800;
            color: #0f172a;
            line-height: 1.2;
        }

        .td-center {
            text-align: center;
        }

        .td-right {
            text-align: right;
        }

        .flash-auto-hide {
            transition: opacity .35s ease, transform .35s ease;
        }

        .flash-auto-hide.hide {
            opacity: 0;
            transform: translateY(-6px);
            pointer-events: none;
        }

        .pagination-wrap {
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
            text-decoration: none;
        }

        .page-btn:hover {
            background: #eef2ff;
            color: #1e40af;
            border-color: #c7d2fe;
        }

        .page-btn.disabled,
        .page-btn[aria-disabled="true"] {
            opacity: .45;
            cursor: not-allowed;
            pointer-events: none;
        }

        .page-info {
            font-weight: 700;
            color: #475569;
            min-width: 90px;
            text-align: center;
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

        @media print {

            .sidebar-nav-wrapper,
            .header,
            .footer,
            .overlay,
            #preloader,
            .no-print {
                display: none !important;
            }

            .main-wrapper {
                margin: 0 !important;
                padding: 0 !important;
            }

            .card-style {
                box-shadow: none !important;
                border: none !important;
            }
        }

        @media (max-width: 767.98px) {
            .pagination-wrap {
                justify-content: center;
                width: 100%;
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

                <li class="nav-item nav-item-has-children active">
                    <a href="#0" class="collapsed" data-bs-toggle="collapse" data-bs-target="#ddmenu_estoque" aria-controls="ddmenu_estoque" aria-expanded="false">
                        <span class="icon"><i class="lni lni-package"></i></span>
                        <span class="text">Estoque</span>
                    </a>
                    <ul id="ddmenu_estoque" class="collapse dropdown-nav show">
                        <li><a href="produtos.php">Produtos</a></li>
                        <li><a href="inventario.php">Inventário</a></li>
                        <li><a href="entradas.php">Entradas</a></li>
                        <li><a href="saidas.php">Saídas</a></li>
                        <li><a href="estoque-minimo.php" class="active">Estoque Mínimo</a></li>
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
                                <form action="#" onsubmit="return false;">
                                    <input type="text" placeholder="Buscar produto..." id="qGlobal" value="<?= e($q) ?>" />
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
                        <div class="col-md-6">
                            <div class="title">
                                <h2>Estoque Mínimo</h2>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if ($flash): ?>
                    <div id="flashBox" class="alert alert-<?= e((string)$flash['type']) ?> flash-auto-hide mt-2">
                        <?= e((string)$flash['msg']) ?>
                    </div>
                <?php endif; ?>

                <!-- KPIs -->
                <div class="row g-3 mb-30">
                    <div class="col-12 col-md-4">
                        <div class="kpi-card">
                            <span class="kpi-ico" style="background: rgba(239,68,68,.10);">
                                <i class="lni lni-warning" style="color:#b91c1c;"></i>
                            </span>
                            <div>
                                <p class="kpi-title">Crítico (zerado)</p>
                                <p class="kpi-value" id="kpiCritico"><?= (int)$kCrit ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-md-4">
                        <div class="kpi-card">
                            <span class="kpi-ico" style="background: rgba(245,158,11,.10);">
                                <i class="lni lni-flag" style="color:#b45309;"></i>
                            </span>
                            <div>
                                <p class="kpi-title">Abaixo do mínimo</p>
                                <p class="kpi-value" id="kpiBaixo"><?= (int)$kBaixo ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-md-4">
                        <div class="kpi-card">
                            <span class="kpi-ico" style="background: rgba(34,197,94,.10);">
                                <i class="lni lni-checkmark-circle" style="color:#16a34a;"></i>
                            </span>
                            <div>
                                <p class="kpi-title">OK</p>
                                <p class="kpi-value" id="kpiOk"><?= (int)$kOk ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Toolbar -->
                <div class="card-style mb-30 no-print">
                    <form method="get" id="formFiltros">
                        <input type="hidden" name="page" id="pageInput" value="1">
                        <input type="hidden" name="per" value="<?= (int)$perPage ?>">

                        <div class="row g-3 align-items-end">
                            <div class="col-12 col-md-6 col-lg-4">
                                <label class="form-label">Pesquisar</label>
                                <input
                                    type="text"
                                    class="form-control compact"
                                    id="qMinimo"
                                    name="q"
                                    value="<?= e($q) ?>"
                                    placeholder="Nome, código, categoria..." />
                            </div>

                            <div class="col-12 col-md-6 col-lg-3">
                                <label class="form-label">Categoria</label>
                                <select class="form-select compact" id="fCategoria" name="categoria">
                                    <option value="">Todas</option>
                                    <?php foreach ($categorias as $c): ?>
                                        <?php $nomeCat = (string)($c['nome'] ?? '');
                                        if ($nomeCat === '') continue; ?>
                                        <option value="<?= e($nomeCat) ?>" <?= $categoria === $nomeCat ? 'selected' : '' ?>>
                                            <?= e($nomeCat) ?><?= (strtoupper((string)$c['status']) === 'INATIVO' ? ' (INATIVO)' : '') ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-12 col-md-6 col-lg-3">
                                <label class="form-label">Filtro</label>
                                <select class="form-select compact" id="fTipo" name="tipo">
                                    <option value="BAIXO" <?= $tipo === 'BAIXO' ? 'selected' : '' ?>>Somente abaixo do mínimo</option>
                                    <option value="CRITICO" <?= $tipo === 'CRITICO' ? 'selected' : '' ?>>Somente críticos (zerado)</option>
                                    <option value="TODOS" <?= $tipo === 'TODOS' ? 'selected' : '' ?>>Mostrar todos</option>
                                </select>
                            </div>

                            <div class="col-12 col-md-6 col-lg-2">
                                <div class="d-grid gap-2 d-sm-flex justify-content-sm-end flex-wrap">
                                    <button class="main-btn light-btn btn-hover btn-compact" type="submit" name="action" value="excel">
                                        <i class="lni lni-download me-1"></i> Excel
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Tabela -->
                <div class="card-style mb-30">
                    <div class="table-responsive">
                        <table class="table text-nowrap" id="tbMinimo">
                            <thead>
                                <tr>
                                    <th class="minw-140">Código</th>
                                    <th class="minw-200">Produto</th>
                                    <th class="minw-140">Categoria</th>
                                    <th class="minw-140">Unidade</th>
                                    <th class="minw-140 td-center">Estoque</th>
                                    <th class="minw-140 td-center">Mínimo</th>
                                    <th class="minw-160 td-center">Situação</th>
                                    <th class="minw-140 td-center">Sugestão</th>
                                </tr>
                            </thead>

                            <tbody>
                                <?php if (!$produtos): ?>
                                    <tr>
                                        <td colspan="8" class="text-center text-muted py-4">Nenhum item encontrado.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($produtos as $p): ?>
                                        <?php
                                        $codigo  = trim((string)($p['codigo'] ?? ''));
                                        $nome    = trim((string)($p['nome'] ?? ''));
                                        if ($codigo === '' && $nome === '') continue;

                                        $categoriaLinha = trim((string)($p['categoria_nome'] ?? '')) ?: '—';
                                        $unidade   = trim((string)($p['unidade'] ?? '')) ?: '—';
                                        $estoque   = (int)($p['estoque'] ?? 0);
                                        $minimo    = (int)($p['minimo'] ?? 0);

                                        $st = calc_status($estoque, $minimo);
                                        $sug = max(0, $minimo - $estoque);
                                        ?>
                                        <tr>
                                            <td><?= e($codigo ?: '—') ?></td>
                                            <td><?= e($nome ?: '—') ?></td>
                                            <td><?= e($categoriaLinha) ?></td>
                                            <td><?= e($unidade) ?></td>
                                            <td class="td-center"><?= (int)$estoque ?></td>
                                            <td class="td-center"><?= (int)$minimo ?></td>
                                            <td class="td-center"><?= badge_html($st) ?></td>
                                            <td class="td-center"><?= ($st === 'OK') ? '0' : ('+' . (int)$sug) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="d-flex flex-column flex-md-row justify-content-between align-items-center gap-3 mt-3">
                        <p class="text-sm text-gray mb-0" id="infoCount">
                            <?php if ($total > 0): ?>
                                Mostrando <?= (int)$shown ?> item(ns) nesta página. Total filtrado: <?= (int)$total ?>.
                            <?php else: ?>
                                Nenhum item encontrado.
                            <?php endif; ?>
                        </p>

                        <div class="pagination-wrap">
                            <?php
                            $prevQs = build_query_string(['page' => max(1, $page - 1)]);
                            $nextQs = build_query_string(['page' => min($totalPages, $page + 1)]);
                            ?>

                            <?php if ($page > 1): ?>
                                <a href="?<?= e($prevQs) ?>" class="page-btn" aria-label="Página anterior">
                                    <i class="lni lni-chevron-left"></i>
                                </a>
                            <?php else: ?>
                                <span class="page-btn disabled" aria-disabled="true">
                                    <i class="lni lni-chevron-left"></i>
                                </span>
                            <?php endif; ?>

                            <span class="page-info" id="pageInfo">Página <?= (int)$page ?>/<?= (int)$totalPages ?></span>

                            <?php if ($page < $totalPages): ?>
                                <a href="?<?= e($nextQs) ?>" class="page-btn" aria-label="Próxima página">
                                    <i class="lni lni-chevron-right"></i>
                                </a>
                            <?php else: ?>
                                <span class="page-btn disabled" aria-disabled="true">
                                    <i class="lni lni-chevron-right"></i>
                                </span>
                            <?php endif; ?>
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
        (function() {
            const box = document.getElementById('flashBox');
            if (!box) return;
            setTimeout(() => {
                box.classList.add('hide');
                setTimeout(() => box.remove(), 400);
            }, 1500);
        })();

        (function() {
            const form = document.getElementById('formFiltros');
            const qMinimo = document.getElementById('qMinimo');
            const qGlobal = document.getElementById('qGlobal');
            const fCategoria = document.getElementById('fCategoria');
            const fTipo = document.getElementById('fTipo');
            const pageInput = document.getElementById('pageInput');

            if (!form) return;

            let timer = null;

            function submitFiltros() {
                if (pageInput) pageInput.value = '1';
                form.submit();
            }

            function debounceSubmit() {
                clearTimeout(timer);
                timer = setTimeout(submitFiltros, 350);
            }

            if (qMinimo) {
                qMinimo.addEventListener('input', function() {
                    if (qGlobal && qGlobal.value !== qMinimo.value) {
                        qGlobal.value = qMinimo.value;
                    }
                    debounceSubmit();
                });
            }

            if (qGlobal) {
                qGlobal.addEventListener('input', function() {
                    if (qMinimo && qMinimo.value !== qGlobal.value) {
                        qMinimo.value = qGlobal.value;
                    }
                    debounceSubmit();
                });
            }

            if (fCategoria) {
                fCategoria.addEventListener('change', submitFiltros);
            }

            if (fTipo) {
                fTipo.addEventListener('change', submitFiltros);
            }
        })();
    </script>
</body>

</html>