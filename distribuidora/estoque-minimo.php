<?php

declare(strict_types=1);

session_start();

require_once __DIR__ . '/assets/conexao.php';
require_once __DIR__ . '/assets/dados/estoque-minimo/_helpers.php';

$pdo = db();

if (!function_exists('e')) {
    function e(string $v): string
    {
        return htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('json_out')) {
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
}

$flash = function_exists('flash_pop') ? flash_pop() : null;
$csrf  = function_exists('csrf_token') ? csrf_token() : '';

function produto_img_url(string $img): string
{
    $img = trim($img);
    if ($img === '') return '';
    if (preg_match('~^(https?://|/|\.{1,2}/)~', $img)) return $img;
    return './assets/dados/produtos/' . ltrim($img, '/');
}

function calc_status(int $estoque, int $minimo): string
{
    if ($estoque <= 0 && $minimo > 0) {
        return 'CRITICO';
    }
    if ($estoque < $minimo) {
        return 'BAIXO';
    }
    return 'OK';
}

function badge_html(string $status): string
{
    if ($status === 'CRITICO') {
        return '<span class="badge-soft badge-soft-danger">CRÍTICO</span>';
    }
    if ($status === 'BAIXO') {
        return '<span class="badge-soft badge-soft-warning">ABAIXO</span>';
    }
    return '<span class="badge-soft badge-soft-success">OK</span>';
}

function build_filters_sql(string $q, string $categoria, string $tipo, array &$params): string
{
    $where = [];

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

    return $where ? (' WHERE ' . implode(' AND ', $where)) : '';
}

function bind_all(PDOStatement $stmt, array $params): void
{
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
}

function fetch_global_kpis(PDO $pdo): array
{
    $critico = 0;
    $baixo   = 0;
    $ok      = 0;

    try {
        $rows = $pdo->query("SELECT COALESCE(estoque,0) AS estoque, COALESCE(minimo,0) AS minimo FROM produtos")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $r) {
            $status = calc_status((int)$r['estoque'], (int)$r['minimo']);
            if ($status === 'CRITICO') $critico++;
            elseif ($status === 'BAIXO') $baixo++;
            else $ok++;
        }
    } catch (Throwable $e) {
        $critico = 0;
        $baixo   = 0;
        $ok      = 0;
    }

    return [
        'critico' => $critico,
        'baixo'   => $baixo,
        'ok'      => $ok,
    ];
}

function fetch_produtos_filtrados(PDO $pdo, string $q, string $categoria, string $tipo, int $page, int $perPage): array
{
    $params = [];
    $where  = build_filters_sql($q, $categoria, $tipo, $params);

    $sqlCount = "
        SELECT COUNT(*)
        FROM produtos p
        LEFT JOIN categorias c ON c.id = p.categoria_id
        {$where}
    ";
    $stCount = $pdo->prepare($sqlCount);
    bind_all($stCount, $params);
    $stCount->execute();
    $total = (int)$stCount->fetchColumn();

    $totalPages = max(1, (int)ceil($total / max(1, $perPage)));
    if ($page > $totalPages) $page = $totalPages;
    if ($page < 1) $page = 1;

    $offset = ($page - 1) * $perPage;

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
        {$where}
        ORDER BY p.id DESC
        LIMIT :limit OFFSET :offset
    ";
    $st = $pdo->prepare($sql);
    bind_all($st, $params);
    $st->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $st->bindValue(':offset', $offset, PDO::PARAM_INT);
    $st->execute();

    $rows = $st->fetchAll(PDO::FETCH_ASSOC);

    return [
        'rows'        => $rows,
        'total'       => $total,
        'page'        => $page,
        'per_page'    => $perPage,
        'total_pages' => $totalPages,
        'shown'       => count($rows),
    ];
}

function fetch_produtos_para_exportar(PDO $pdo, string $q, string $categoria, string $tipo): array
{
    $params = [];
    $where  = build_filters_sql($q, $categoria, $tipo, $params);

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
        {$where}
        ORDER BY p.id DESC
    ";
    $st = $pdo->prepare($sql);
    bind_all($st, $params);
    $st->execute();

    return $st->fetchAll(PDO::FETCH_ASSOC);
}

function render_rows_html(array $produtos): string
{
    ob_start();

    if (!$produtos) {
?>
        <tr>
            <td colspan="8" class="text-center text-muted py-4">Nenhum item encontrado.</td>
        </tr>
    <?php
        return (string)ob_get_clean();
    }

    foreach ($produtos as $p):
        $codigo    = trim((string)($p['codigo'] ?? '')) ?: '—';
        $nome      = trim((string)($p['nome'] ?? '')) ?: '—';
        $categoria = trim((string)($p['categoria_nome'] ?? '')) ?: '—';
        $unidade   = trim((string)($p['unidade'] ?? '')) ?: '—';
        $estoque   = (int)($p['estoque'] ?? 0);
        $minimo    = (int)($p['minimo'] ?? 0);

        $status = calc_status($estoque, $minimo);
        $sug    = max(0, $minimo - $estoque);
    ?>
        <tr
            data-cat="<?= e($categoria) ?>"
            data-cod="<?= e($codigo) ?>"
            data-prod="<?= e($nome) ?>"
            data-estoque="<?= $estoque ?>"
            data-min="<?= $minimo ?>">
            <td><?= e($codigo) ?></td>
            <td><?= e($nome) ?></td>
            <td><?= e($categoria) ?></td>
            <td><?= e($unidade) ?></td>
            <td class="td-center"><?= $estoque ?></td>
            <td class="td-center"><?= $minimo ?></td>
            <td class="td-center"><?= badge_html($status) ?></td>
            <td class="td-center"><?= $status === 'OK' ? '0' : '+' . $sug ?></td>
        </tr>
    <?php
    endforeach;

    return (string)ob_get_clean();
}

/* =========================
   PARÂMETROS
========================= */
$action    = strtolower(trim((string)($_GET['action'] ?? '')));
$q         = trim((string)($_GET['q'] ?? ''));
$categoria = trim((string)($_GET['categoria'] ?? ''));
$tipo      = strtoupper(trim((string)($_GET['tipo'] ?? 'BAIXO')));
$page      = max(1, (int)($_GET['page'] ?? 1));
$perPage   = max(1, min(100, (int)($_GET['per'] ?? 5)));

if (!in_array($tipo, ['BAIXO', 'CRITICO', 'TODOS'], true)) {
    $tipo = 'BAIXO';
}

/* =========================
   CATEGORIAS
========================= */
$categorias = [];
try {
    $categorias = $pdo->query("SELECT id, nome, status FROM categorias ORDER BY nome ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $categorias = [];
}

/* =========================
   AJAX
========================= */
if ($action === 'ajax') {
    try {
        $result = fetch_produtos_filtrados($pdo, $q, $categoria, $tipo, $page, $perPage);
        $kpis   = fetch_global_kpis($pdo);

        json_out([
            'ok'          => true,
            'rows_html'   => render_rows_html($result['rows']),
            'total'       => $result['total'],
            'shown'       => $result['shown'],
            'page'        => $result['page'],
            'per_page'    => $result['per_page'],
            'total_pages' => $result['total_pages'],
            'info_text'   => $result['total'] > 0
                ? "Mostrando {$result['shown']} item(ns) nesta página. Total filtrado: {$result['total']}."
                : "Nenhum item encontrado.",
            'page_text'   => "Página {$result['page']}/{$result['total_pages']}",
            'kpis'        => $kpis,
        ]);
    } catch (Throwable $e) {
        json_out([
            'ok'  => false,
            'msg' => 'Falha ao buscar itens do estoque mínimo.',
        ], 500);
    }
}

/* =========================
   EXPORTAR EXCEL
========================= */
if ($action === 'excel') {
    try {
        $rows = fetch_produtos_para_exportar($pdo, $q, $categoria, $tipo);

        $now     = new DateTime('now');
        $dt      = $now->format('d/m/Y H:i:s');
        $fileDt  = $now->format('Y-m-d_H-i-s');
        $catText = $categoria !== '' ? $categoria : 'Todas';
        $busca   = $q !== '' ? $q : '—';

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
                    <td colspan="8">Categoria: <?= e($catText) ?> | Filtro: <?= e($tipo) ?> | Busca: <?= e($busca) ?></td>
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

                <?php if (!$rows): ?>
                    <tr>
                        <td colspan="8" class="center">Nenhum item encontrado.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($rows as $p): ?>
                        <?php
                        $codigo    = trim((string)($p['codigo'] ?? '')) ?: '—';
                        $nome      = trim((string)($p['nome'] ?? '')) ?: '—';
                        $cat       = trim((string)($p['categoria_nome'] ?? '')) ?: '—';
                        $unidade   = trim((string)($p['unidade'] ?? '')) ?: '—';
                        $estoque   = (int)($p['estoque'] ?? 0);
                        $minimo    = (int)($p['minimo'] ?? 0);
                        $status    = calc_status($estoque, $minimo);
                        $sugestao  = $status === 'OK' ? '0' : '+' . max(0, $minimo - $estoque);
                        ?>
                        <tr>
                            <td class="left"><?= e($codigo) ?></td>
                            <td class="left"><?= e($nome) ?></td>
                            <td class="left"><?= e($cat) ?></td>
                            <td class="left"><?= e($unidade) ?></td>
                            <td class="center"><?= $estoque ?></td>
                            <td class="center"><?= $minimo ?></td>
                            <td class="center"><?= e($status) ?></td>
                            <td class="center"><?= e($sugestao) ?></td>
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

/* =========================
   CARGA INICIAL
========================= */
$kpis = fetch_global_kpis($pdo);

try {
    $initial = fetch_produtos_filtrados($pdo, '', '', 'BAIXO', 1, $perPage);
} catch (Throwable $e) {
    $initial = [
        'rows'        => [],
        'total'       => 0,
        'page'        => 1,
        'per_page'    => $perPage,
        'total_pages' => 1,
        'shown'       => 0,
    ];

    if (function_exists('flash_set')) {
        flash_set('danger', 'Falha ao carregar produtos (verifique a tabela produtos).');
        $flash = function_exists('flash_pop') ? flash_pop() : null;
    }
}
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
                                    <input type="text" placeholder="Buscar produto..." id="qGlobal" />
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
                    <div id="flashBox" class="alert alert-<?= e((string)($flash['type'] ?? 'info')) ?> flash-auto-hide mt-2">
                        <?= e((string)($flash['msg'] ?? '')) ?>
                    </div>
                <?php endif; ?>

                <div class="row g-3 mb-30">
                    <div class="col-12 col-md-4">
                        <div class="kpi-card">
                            <span class="kpi-ico" style="background: rgba(239,68,68,.10);">
                                <i class="lni lni-warning" style="color:#b91c1c;"></i>
                            </span>
                            <div>
                                <p class="kpi-title">Crítico (zerado)</p>
                                <p class="kpi-value" id="kpiCritico"><?= (int)$kpis['critico'] ?></p>
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
                                <p class="kpi-value" id="kpiBaixo"><?= (int)$kpis['baixo'] ?></p>
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
                                <p class="kpi-value" id="kpiOk"><?= (int)$kpis['ok'] ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card-style mb-30 no-print">
                    <div class="row g-3 align-items-end">
                        <div class="col-12 col-md-6 col-lg-4">
                            <label class="form-label">Pesquisar</label>
                            <input
                                type="text"
                                class="form-control compact"
                                id="qMinimo"
                                placeholder="Nome, código, categoria..."
                                autocomplete="off" />
                        </div>

                        <div class="col-12 col-md-6 col-lg-3">
                            <label class="form-label">Categoria</label>
                            <select class="form-select compact" id="fCategoria">
                                <option value="">Todas</option>
                                <?php foreach ($categorias as $c): ?>
                                    <?php
                                    $nomeCat = trim((string)($c['nome'] ?? ''));
                                    if ($nomeCat === '') continue;
                                    ?>
                                    <option value="<?= e($nomeCat) ?>">
                                        <?= e($nomeCat) ?><?= strtoupper((string)($c['status'] ?? '')) === 'INATIVO' ? ' (INATIVO)' : '' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-12 col-md-6 col-lg-3">
                            <label class="form-label">Filtro</label>
                            <select class="form-select compact" id="fTipo">
                                <option value="BAIXO" selected>Somente abaixo do mínimo</option>
                                <option value="CRITICO">Somente críticos (zerado)</option>
                                <option value="TODOS">Mostrar todos</option>
                            </select>
                        </div>

                        <div class="col-12 col-md-6 col-lg-2">
                            <div class="d-grid gap-2 d-sm-flex justify-content-sm-end flex-wrap">
                                <button class="main-btn light-btn btn-hover btn-compact" id="btnExcel" type="button">
                                    <i class="lni lni-download me-1"></i> Excel
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

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
                            <tbody id="tbodyMinimo">
                                <?= render_rows_html($initial['rows']) ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="d-flex flex-column flex-md-row justify-content-between align-items-center gap-3 mt-3">
                        <p class="text-sm text-gray mb-0" id="infoCount">
                            <?= $initial['total'] > 0
                                ? 'Mostrando ' . $initial['shown'] . ' item(ns) nesta página. Total filtrado: ' . $initial['total'] . '.'
                                : 'Nenhum item encontrado.' ?>
                        </p>

                        <div class="pagination-wrap">
                            <button type="button" class="page-btn" id="btnPrevPage" aria-label="Página anterior" <?= $initial['page'] <= 1 || $initial['total'] <= 0 ? 'disabled' : '' ?>>
                                <i class="lni lni-chevron-left"></i>
                            </button>

                            <span class="page-info" id="pageInfo">
                                Página <?= (int)$initial['page'] ?>/<?= (int)$initial['total_pages'] ?>
                            </span>

                            <button type="button" class="page-btn" id="btnNextPage" aria-label="Próxima página" <?= $initial['page'] >= $initial['total_pages'] || $initial['total'] <= 0 ? 'disabled' : '' ?>>
                                <i class="lni lni-chevron-right"></i>
                            </button>
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

        const tbodyMinimo = document.getElementById('tbodyMinimo');
        const qMinimo = document.getElementById('qMinimo');
        const qGlobal = document.getElementById('qGlobal');
        const fCategoria = document.getElementById('fCategoria');
        const fTipo = document.getElementById('fTipo');
        const infoCount = document.getElementById('infoCount');
        const pageInfo = document.getElementById('pageInfo');
        const btnPrevPage = document.getElementById('btnPrevPage');
        const btnNextPage = document.getElementById('btnNextPage');
        const btnExcel = document.getElementById('btnExcel');

        const kpiCritico = document.getElementById('kpiCritico');
        const kpiBaixo = document.getElementById('kpiBaixo');
        const kpiOk = document.getElementById('kpiOk');

        const PER_PAGE = <?= (int)$perPage ?>;
        let currentPage = <?= (int)$initial['page'] ?>;
        let requestController = null;
        let debounceTimer = null;

        function syncInputs(source, target) {
            if (!source || !target) return;
            if (target.value !== source.value) {
                target.value = source.value;
            }
        }

        function getSearchValue() {
            const a = (qMinimo?.value || '').trim();
            const b = (qGlobal?.value || '').trim();
            return a !== '' ? a : b;
        }

        function buildAjaxUrl(page = 1) {
            const params = new URLSearchParams();
            params.set('action', 'ajax');
            params.set('q', getSearchValue());
            params.set('categoria', fCategoria.value || '');
            params.set('tipo', fTipo.value || 'BAIXO');
            params.set('page', String(page));
            params.set('per', String(PER_PAGE));
            params.set('_ts', String(Date.now()));
            return window.location.pathname + '?' + params.toString();
        }

        function buildExcelUrl() {
            const params = new URLSearchParams();
            params.set('action', 'excel');
            params.set('q', getSearchValue());
            params.set('categoria', fCategoria.value || '');
            params.set('tipo', fTipo.value || 'BAIXO');
            return window.location.pathname + '?' + params.toString();
        }

        function setLoadingState(loading) {
            qMinimo.disabled = loading;
            if (qGlobal) qGlobal.disabled = loading;
            fCategoria.disabled = loading;
            fTipo.disabled = loading;
            btnPrevPage.disabled = loading || btnPrevPage.disabled;
            btnNextPage.disabled = loading || btnNextPage.disabled;
        }

        async function carregarTabela(resetPage = false) {
            if (resetPage) currentPage = 1;

            if (requestController) {
                requestController.abort();
            }
            requestController = new AbortController();

            setLoadingState(true);

            try {
                const res = await fetch(buildAjaxUrl(currentPage), {
                    method: 'GET',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    signal: requestController.signal
                });

                const data = await res.json();

                if (!res.ok || !data.ok) {
                    throw new Error(data.msg || 'Falha ao carregar dados.');
                }

                tbodyMinimo.innerHTML = data.rows_html || '';
                infoCount.textContent = data.info_text || 'Nenhum item encontrado.';
                pageInfo.textContent = data.page_text || 'Página 1/1';

                currentPage = Number(data.page || 1);

                btnPrevPage.disabled = Number(data.page || 1) <= 1 || Number(data.total || 0) <= 0;
                btnNextPage.disabled = Number(data.page || 1) >= Number(data.total_pages || 1) || Number(data.total || 0) <= 0;

                if (data.kpis) {
                    kpiCritico.textContent = String(data.kpis.critico ?? 0);
                    kpiBaixo.textContent = String(data.kpis.baixo ?? 0);
                    kpiOk.textContent = String(data.kpis.ok ?? 0);
                }
            } catch (err) {
                if (err.name === 'AbortError') return;

                tbodyMinimo.innerHTML = `
                    <tr>
                        <td colspan="8" class="text-center text-danger py-4">
                            Erro ao carregar dados do estoque mínimo.
                        </td>
                    </tr>
                `;
                infoCount.textContent = 'Falha ao carregar resultados.';
                pageInfo.textContent = 'Página 1/1';
                btnPrevPage.disabled = true;
                btnNextPage.disabled = true;
            } finally {
                setLoadingState(false);
            }
        }

        function debounceLoad(resetPage = true) {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => {
                carregarTabela(resetPage);
            }, 250);
        }

        qMinimo.addEventListener('input', function() {
            syncInputs(qMinimo, qGlobal);
            debounceLoad(true);
        });

        if (qGlobal) {
            qGlobal.addEventListener('input', function() {
                syncInputs(qGlobal, qMinimo);
                debounceLoad(true);
            });
        }

        fCategoria.addEventListener('change', function() {
            carregarTabela(true);
        });

        fTipo.addEventListener('change', function() {
            carregarTabela(true);
        });

        btnPrevPage.addEventListener('click', function() {
            if (currentPage <= 1) return;
            currentPage--;
            carregarTabela(false);
        });

        btnNextPage.addEventListener('click', function() {
            currentPage++;
            carregarTabela(false);
        });

        btnExcel.addEventListener('click', function() {
            window.location.href = buildExcelUrl();
        });
    </script>
</body>

</html>