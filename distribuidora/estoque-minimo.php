<?php

declare(strict_types=1);

session_start();
require_once __DIR__ . '/assets/conexao.php';
require_once __DIR__ . '/assets/dados/estoque-minimo/_helpers.php';

$pdo = db();

$flash = flash_pop();
$csrf  = csrf_token();

/**
 * IMAGENS:
 * No banco está assim: "images/arquivo.png"
 * Como o arquivo está em /assets/dados/produtos/, então para exibir do estoque-minimo.php:
 *   ./assets/dados/produtos/ + images/arquivo.png
 */
function produto_img_url(string $img): string
{
    $img = trim($img);
    if ($img === '') return '';
    if (preg_match('~^(https?://|/|\.{1,2}/)~', $img)) return $img;
    return './assets/dados/produtos/' . ltrim($img, '/');
}

/** categorias (para filtro) */
$categorias = [];
try {
    $categorias = $pdo->query("SELECT id, nome, status FROM categorias ORDER BY nome ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $categorias = [];
}

/** produtos */
$produtos = [];
try {
    $produtos = $pdo->query("
    SELECT
      p.id, p.codigo, p.nome, p.unidade, p.estoque, p.minimo, p.imagem,
      c.nome AS categoria_nome
    FROM produtos p
    LEFT JOIN categorias c ON c.id = p.categoria_id
    ORDER BY p.id DESC
    LIMIT 3000
  ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $produtos = [];
    flash_set('danger', 'Falha ao carregar produtos (verifique tabela produtos).');
    $flash = flash_pop();
}

/** KPIs (server) */
$kCrit = 0;
$kBaixo = 0;
$kOk = 0;
foreach ($produtos as $p) {
    $estoque = (int)($p['estoque'] ?? 0);
    $minimo  = (int)($p['minimo'] ?? 0);

    if ($estoque <= 0 && $minimo > 0) $kCrit++;
    elseif ($estoque < $minimo) $kBaixo++;
    else $kOk++;
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
            <a href="dashboard.php" class="d-flex align-items-center gap-2">
                <img src="assets/images/logo/logo.svg" alt="logo" />
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
                                <button id="menu-toggle" class="main-btn primary-btn btn-hover btn-compact" type="button">
                                    <i class="lni lni-chevron-left me-2"></i> Menu
                                </button>
                            </div>

                            <div class="header-search d-none d-md-flex">
                                <form action="#" onsubmit="return false;">
                                    <input type="text" placeholder="Buscar produto..." id="qGlobal" />
                                    <button type="submit" onclick="return false"><i class="lni lni-search-alt"></i></button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-7 col-md-7 col-6">
                        <div class="header-right">
                            <div class="profile-box ml-15">
                                <button class="dropdown-toggle bg-transparent border-0" type="button" id="profile"
                                    data-bs-toggle="dropdown" aria-expanded="false">
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
                                                <a class="text-black/40 dark:text-white/40 hover:text-black dark:hover:text-white text-xs"
                                                    href="#">Admin</a>
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
                    <div class="row g-3 align-items-end">
                        <div class="col-12 col-md-6 col-lg-4">
                            <label class="form-label">Pesquisar</label>
                            <input type="text" class="form-control compact" id="qMinimo"
                                placeholder="Nome, código, categoria..." />
                        </div>

                        <div class="col-12 col-md-6 col-lg-3">
                            <label class="form-label">Categoria</label>
                            <select class="form-select compact" id="fCategoria">
                                <option value="">Todas</option>
                                <?php foreach ($categorias as $c): ?>
                                    <?php $nomeCat = (string)($c['nome'] ?? '');
                                    if ($nomeCat === '') continue; ?>
                                    <option value="<?= e($nomeCat) ?>"><?= e($nomeCat) ?><?= (strtoupper((string)$c['status']) === 'INATIVO' ? ' (INATIVO)' : '') ?></option>
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
                                <?php foreach ($produtos as $p): ?>
                                    <?php
                                    $codigo  = trim((string)($p['codigo'] ?? ''));
                                    $nome    = trim((string)($p['nome'] ?? ''));
                                    if ($codigo === '' && $nome === '') continue;

                                    $categoria = trim((string)($p['categoria_nome'] ?? '')) ?: '—';
                                    $unidade   = trim((string)($p['unidade'] ?? '')) ?: '—';
                                    $estoque   = (int)($p['estoque'] ?? 0);
                                    $minimo    = (int)($p['minimo'] ?? 0);

                                    $st = 'OK';
                                    if ($estoque <= 0 && $minimo > 0) $st = 'CRITICO';
                                    elseif ($estoque < $minimo) $st = 'BAIXO';

                                    $sug = max(0, $minimo - $estoque);

                                    $badge = $st === 'CRITICO'
                                        ? '<span class="badge-soft badge-soft-danger">CRÍTICO</span>'
                                        : ($st === 'BAIXO'
                                            ? '<span class="badge-soft badge-soft-warning">ABAIXO</span>'
                                            : '<span class="badge-soft badge-soft-success">OK</span>');

                                    ?>
                                    <tr
                                        data-cat="<?= e($categoria) ?>"
                                        data-cod="<?= e($codigo ?: '—') ?>"
                                        data-prod="<?= e($nome ?: '—') ?>"
                                        data-estoque="<?= (int)$estoque ?>"
                                        data-min="<?= (int)$minimo ?>">
                                        <td><?= e($codigo ?: '—') ?></td>
                                        <td><?= e($nome ?: '—') ?></td>
                                        <td><?= e($categoria) ?></td>
                                        <td><?= e($unidade) ?></td>
                                        <td class="td-center"><?= (int)$estoque ?></td>
                                        <td class="td-center"><?= (int)$minimo ?></td>
                                        <td class="td-center"><?= $badge ?></td>
                                        <td class="td-center"><?= ($st === 'OK') ? '0' : ('+' . (int)$sug) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="d-flex flex-column flex-md-row justify-content-between align-items-center gap-3 mt-3">
                        <p class="text-sm text-gray mb-0" id="infoCount"></p>

                        <div class="pagination-wrap">
                            <button type="button" class="page-btn" id="btnPrevPage" aria-label="Página anterior">
                                <i class="lni lni-chevron-left"></i>
                            </button>

                            <span class="page-info" id="pageInfo">Página 1/1</span>

                            <button type="button" class="page-btn" id="btnNextPage" aria-label="Próxima página">
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

        const DEFAULT_IMG = "data:image/svg+xml;charset=utf-8," + encodeURIComponent(`
      <svg xmlns="http://www.w3.org/2000/svg" width="96" height="96">
        <rect width="100%" height="100%" fill="#f1f5f9"/>
        <path d="M18 68l18-18 12 12 10-10 20 20" fill="none" stroke="#94a3b8" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/>
        <circle cx="34" cy="34" r="7" fill="#94a3b8"/>
        <text x="50%" y="86%" text-anchor="middle" font-family="Arial" font-size="10" fill="#64748b">Sem imagem</text>
      </svg>
    `);

        document.querySelectorAll("img.prod-img").forEach(img => {
            const src = (img.getAttribute('src') || '').trim();
            if (!src) img.src = DEFAULT_IMG;
            img.addEventListener('error', () => {
                img.src = DEFAULT_IMG;
            }, {
                once: true
            });
        });

        const tb = document.getElementById('tbMinimo');
        const tbodyRows = Array.from(tb.querySelectorAll('tbody tr'));

        const qMinimo = document.getElementById('qMinimo');
        const qGlobal = document.getElementById('qGlobal');
        const fCategoria = document.getElementById('fCategoria');
        const fTipo = document.getElementById('fTipo');
        const infoCount = document.getElementById('infoCount');

        const kpiCritico = document.getElementById('kpiCritico');
        const kpiBaixo = document.getElementById('kpiBaixo');
        const kpiOk = document.getElementById('kpiOk');

        const btnPrevPage = document.getElementById('btnPrevPage');
        const btnNextPage = document.getElementById('btnNextPage');
        const pageInfo = document.getElementById('pageInfo');

        const PER_PAGE = 5;
        let currentPage = 1;

        function norm(s) {
            return String(s ?? '').toLowerCase().trim();
        }

        function calcStatus(estoque, minimo) {
            const e = Number(estoque || 0);
            const m = Number(minimo || 0);
            if (e <= 0 && m > 0) return 'CRITICO';
            if (e < m) return 'BAIXO';
            return 'OK';
        }

        function badge(status) {
            if (status === 'CRITICO') return `<span class="badge-soft badge-soft-danger">CRÍTICO</span>`;
            if (status === 'BAIXO') return `<span class="badge-soft badge-soft-warning">ABAIXO</span>`;
            if (status === 'OK') return `<span class="badge-soft badge-soft-success">OK</span>`;
            return `<span class="badge-soft badge-soft-gray">—</span>`;
        }

        function rebuildRow(tr) {
            const estoque = Number(tr.getAttribute('data-estoque') || 0);
            const minimo = Number(tr.getAttribute('data-min') || 0);
            const st = calcStatus(estoque, minimo);
            const sug = Math.max(0, minimo - estoque);

            tr.children[5].innerText = String(estoque);
            tr.children[6].innerText = String(minimo);
            tr.children[7].innerHTML = badge(st);
            tr.children[8].innerText = (st === 'OK') ? '0' : `+${sug}`;
        }

        function syncSearch(source, target) {
            if (target.value !== source.value) {
                target.value = source.value;
            }
        }

        function rowMatches(tr) {
            const q = norm(qMinimo.value || qGlobal.value);
            const cat = norm(fCategoria.value);
            const tipo = fTipo.value || 'BAIXO';

            const text = norm(tr.innerText);
            const rCat = norm(tr.getAttribute('data-cat'));

            const estoque = Number(tr.getAttribute('data-estoque') || 0);
            const minimo = Number(tr.getAttribute('data-min') || 0);
            const st = calcStatus(estoque, minimo);

            let ok = true;
            if (q && !text.includes(q)) ok = false;
            if (cat && rCat !== cat) ok = false;

            if (tipo === 'CRITICO' && st !== 'CRITICO') ok = false;
            if (tipo === 'BAIXO' && st !== 'BAIXO' && st !== 'CRITICO') ok = false;

            return ok;
        }

        function getFilteredRows() {
            return tbodyRows.filter(rowMatches);
        }

        function refreshKPIs() {
            let nCrit = 0,
                nBaixo = 0,
                nOk = 0;

            tbodyRows.forEach(tr => {
                const estoque = Number(tr.getAttribute('data-estoque') || 0);
                const minimo = Number(tr.getAttribute('data-min') || 0);
                const st = calcStatus(estoque, minimo);

                if (st === 'CRITICO') nCrit++;
                else if (st === 'BAIXO') nBaixo++;
                else nOk++;
            });

            kpiCritico.textContent = String(nCrit);
            kpiBaixo.textContent = String(nBaixo);
            kpiOk.textContent = String(nOk);
        }

        function renderTable(resetPage = false) {
            if (resetPage) currentPage = 1;

            tbodyRows.forEach(rebuildRow);
            refreshKPIs();

            const filtered = getFilteredRows();
            const totalItems = filtered.length;
            const totalPages = Math.max(1, Math.ceil(totalItems / PER_PAGE));

            if (currentPage > totalPages) currentPage = totalPages;
            if (currentPage < 1) currentPage = 1;

            tbodyRows.forEach(tr => {
                tr.style.display = 'none';
            });

            const start = (currentPage - 1) * PER_PAGE;
            const end = start + PER_PAGE;
            const pageRows = filtered.slice(start, end);

            pageRows.forEach(tr => {
                tr.style.display = '';
            });

            if (totalItems > 0) {
                infoCount.textContent = `Mostrando ${pageRows.length} item(ns) nesta página. Total filtrado: ${totalItems}.`;
            } else {
                infoCount.textContent = 'Nenhum item encontrado.';
            }

            pageInfo.textContent = `Página ${currentPage}/${totalPages}`;
            btnPrevPage.disabled = currentPage <= 1 || totalItems === 0;
            btnNextPage.disabled = currentPage >= totalPages || totalItems === 0;
        }

        qMinimo.addEventListener('input', () => {
            syncSearch(qMinimo, qGlobal);
            renderTable(true);
        });

        qGlobal.addEventListener('input', () => {
            syncSearch(qGlobal, qMinimo);
            renderTable(true);
        });

        fCategoria.addEventListener('change', () => renderTable(true));
        fTipo.addEventListener('change', () => renderTable(true));

        btnPrevPage.addEventListener('click', () => {
            currentPage--;
            renderTable(false);
        });

        btnNextPage.addEventListener('click', () => {
            currentPage++;
            renderTable(false);
        });

        function exportExcel() {
            const rows = getFilteredRows();

            if (!rows.length) {
                alert('Não há itens para exportar.');
                return;
            }

            const now = new Date();
            const dt = now.toLocaleDateString('pt-BR') + ' ' + now.toLocaleTimeString('pt-BR');
            const fileDt = now.toISOString().slice(0, 19).replace(/[:T]/g, '-');

            const cat = fCategoria.value || 'Todas';
            const tipo = fTipo.value || 'BAIXO';
            const busca = (qMinimo.value || qGlobal.value || '').trim() || '—';

            const header = ['Código', 'Produto', 'Categoria', 'Unidade', 'Estoque', 'Mínimo', 'Situação', 'Sugestão'];

            const body = rows.map(tr => ([
                tr.children[1].innerText.trim(),
                tr.children[2].innerText.trim(),
                tr.children[3].innerText.trim(),
                tr.children[4].innerText.trim(),
                tr.children[5].innerText.trim(),
                tr.children[6].innerText.trim(),
                tr.children[7].innerText.trim(),
                tr.children[8].innerText.trim(),
            ]));

            const isCenterCol = (idx) => (idx === 4 || idx === 5 || idx === 6 || idx === 7);

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
                    </style>
                  </head>
                  <body>
                    <table>
            `;

            html += `<tr><td class="title" colspan="8">PAINEL DA DISTRIBUIDORA - ESTOQUE MÍNIMO</td></tr>`;
            html += `<tr><td colspan="8">Gerado em: ${dt}</td></tr>`;
            html += `<tr><td colspan="8">Categoria: ${cat} | Filtro: ${tipo} | Busca: ${busca}</td></tr>`;
            html += `<tr>${header.map((h, idx) => `<th class="${isCenterCol(idx) ? 'center' : 'left'}">${h}</th>`).join('')}</tr>`;

            body.forEach(row => {
                html += '<tr>';
                row.forEach((cell, idx) => {
                    const safe = String(cell)
                        .replaceAll('&', '&amp;')
                        .replaceAll('<', '&lt;')
                        .replaceAll('>', '&gt;');

                    html += `<td class="${isCenterCol(idx) ? 'center' : 'left'}">${safe}</td>`;
                });
                html += '</tr>';
            });

            html += `</table></body></html>`;

            const blob = new Blob(["\ufeff" + html], {
                type: 'application/vnd.ms-excel;charset=utf-8;'
            });
            const url = URL.createObjectURL(blob);

            const a = document.createElement('a');
            a.href = url;
            a.download = `estoque_minimo_${fileDt}.xls`;
            document.body.appendChild(a);
            a.click();
            a.remove();
            URL.revokeObjectURL(url);
        }

        document.getElementById('btnExcel').addEventListener('click', exportExcel);

        renderTable(true);
    </script>
</body>

</html>