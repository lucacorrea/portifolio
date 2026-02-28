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
    if (preg_match('~^(https?://|/|\.{1,2}/)~', $img)) return $img; // já é caminho absoluto/relativo pronto
    return './assets/dados/produtos/' . ltrim($img, '/');
}

/** categorias (para filtro) */
$categorias = [];
try {
    $categorias = $pdo->query("SELECT id, nome, status FROM categorias ORDER BY nome ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    // se não existir tabela ainda, só não quebra a página
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

    <!-- ========== CSS ========= -->
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

        /* flash auto hide */
        .flash-auto-hide {
            transition: opacity .35s ease, transform .35s ease;
        }

        .flash-auto-hide.hide {
            opacity: 0;
            transform: translateY(-6px);
            pointer-events: none;
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
    </style>
</head>

<body>
    <div id="preloader">
        <div class="spinner"></div>
    </div>

    <!-- ======== sidebar-nav start =========== -->
    <aside class="sidebar-nav-wrapper">
        <div class="navbar-logo">
            <a href="index.php" class="d-flex align-items-center gap-2">
                <img src="assets/images/logo/logo.svg" alt="logo" />
            </a>
        </div>

        <nav class="sidebar-nav">
            <ul>
                <li class="nav-item">
                    <a href="index.php">
                        <span class="icon">
                            <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path
                                    d="M8.74999 18.3333C12.2376 18.3333 15.1364 15.8128 15.7244 12.4941C15.8448 11.8143 15.2737 11.25 14.5833 11.25H9.99999C9.30966 11.25 8.74999 10.6903 8.74999 10V5.41666C8.74999 4.7263 8.18563 4.15512 7.50586 4.27556C4.18711 4.86357 1.66666 7.76243 1.66666 11.25C1.66666 15.162 4.83797 18.3333 8.74999 18.3333Z" />
                                <path
                                    d="M17.0833 10C17.7737 10 18.3432 9.43708 18.2408 8.75433C17.7005 5.14918 14.8508 2.29947 11.2457 1.75912C10.5629 1.6568 10 2.2263 10 2.91665V9.16666C10 9.62691 10.3731 10 10.8333 10H17.0833Z" />
                            </svg>
                        </span>
                        <span class="text">Dashboard</span>
                    </a>
                </li>

                <li class="nav-item nav-item-has-children">
                    <a href="#0" class="collapsed" data-bs-toggle="collapse" data-bs-target="#ddmenu_operacoes"
                        aria-controls="ddmenu_operacoes" aria-expanded="false">
                        <span class="icon">
                            <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path
                                    d="M3.33334 3.35442C3.33334 2.4223 4.07954 1.66666 5.00001 1.66666H15C15.9205 1.66666 16.6667 2.4223 16.6667 3.35442V16.8565C16.6667 17.5519 15.8827 17.9489 15.3333 17.5317L13.8333 16.3924C13.537 16.1673 13.1297 16.1673 12.8333 16.3924L10.5 18.1646C10.2037 18.3896 9.79634 18.3896 9.50001 18.1646L7.16668 16.3924C6.87038 16.1673 6.46298 16.1673 6.16668 16.3924L4.66668 17.5317C4.11731 17.9489 3.33334 17.5519 3.33334 16.8565V3.35442Z" />
                            </svg>
                        </span>
                        <span class="text">Operações</span>
                    </a>
                    <ul id="ddmenu_operacoes" class="collapse dropdown-nav">
                        <li><a href="pedidos.php">Pedidos</a></li>
                        <li><a href="vendas.php">Vendas</a></li>
                        <li><a href="devolucoes.php">Devoluções</a></li>
                    </ul>
                </li>

                <li class="nav-item nav-item-has-children active">
                    <a href="#0" data-bs-toggle="collapse" data-bs-target="#ddmenu_estoque" aria-controls="ddmenu_estoque"
                        aria-expanded="true">
                        <span class="icon">
                            <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path
                                    d="M2.49999 5.83331C2.03976 5.83331 1.66666 6.2064 1.66666 6.66665V10.8333C1.66666 13.5948 3.90523 15.8333 6.66666 15.8333H9.99999C12.1856 15.8333 14.0436 14.431 14.7235 12.4772C14.8134 12.4922 14.9058 12.5 15 12.5H16.6667C17.5872 12.5 18.3333 11.7538 18.3333 10.8333V8.33331C18.3333 7.41284 17.5872 6.66665 16.6667 6.66665H15C15 6.2064 14.6269 5.83331 14.1667 5.83331H2.49999Z" />
                                <path
                                    d="M2.49999 16.6667C2.03976 16.6667 1.66666 17.0398 1.66666 17.5C1.66666 17.9602 2.03976 18.3334 2.49999 18.3334H14.1667C14.6269 18.3334 15 17.9602 15 17.5C15 17.0398 14.6269 16.6667 14.1667 16.6667H2.49999Z" />
                            </svg>
                        </span>
                        <span class="text">Estoque</span>
                    </a>
                    <ul id="ddmenu_estoque" class="collapse show dropdown-nav">
                        <li><a href="produtos.php">Produtos</a></li>
                        <li><a href="inventario.php">Inventário</a></li>
                        <li><a href="entradas.php">Entradas</a></li>
                        <li><a href="saidas.php">Saídas</a></li>
                        <li><a href="estoque-minimo.php" class="active">Estoque Mínimo</a></li>
                    </ul>
                </li>

                <li class="nav-item nav-item-has-children">
                    <a href="#0" class="collapsed" data-bs-toggle="collapse" data-bs-target="#ddmenu_cadastros"
                        aria-controls="ddmenu_cadastros" aria-expanded="false">
                        <span class="icon">
                            <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path
                                    d="M1.66666 5.41669C1.66666 3.34562 3.34559 1.66669 5.41666 1.66669C7.48772 1.66669 9.16666 3.34562 9.16666 5.41669C9.16666 7.48775 7.48772 9.16669 5.41666 9.16669C3.34559 9.16669 1.66666 7.48775 1.66666 5.41669Z" />
                                <path
                                    d="M1.66666 14.5834C1.66666 12.5123 3.34559 10.8334 5.41666 10.8334C7.48772 10.8334 9.16666 12.5123 9.16666 14.5834C9.16666 16.6545 7.48772 18.3334 5.41666 18.3334C3.34559 18.3334 1.66666 16.6545 1.66666 14.5834Z" />
                                <path
                                    d="M10.8333 5.41669C10.8333 3.34562 12.5123 1.66669 14.5833 1.66669C16.6544 1.66669 18.3333 3.34562 18.3333 5.41669C18.3333 7.48775 16.6544 9.16669 14.5833 9.16669C12.5123 9.16669 10.8333 7.48775 10.8333 5.41669Z" />
                                <path
                                    d="M10.8333 14.5834C10.8333 12.5123 12.5123 10.8334 14.5833 10.8334C16.6544 10.8334 18.3333 12.5123 18.3333 14.5834C18.3333 16.6545 16.6544 18.3334 14.5833 18.3334C12.5123 18.3334 10.8333 16.6545 10.8333 14.5834Z" />
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
                                <path
                                    d="M4.16666 3.33335C4.16666 2.41288 4.91285 1.66669 5.83332 1.66669H14.1667C15.0872 1.66669 15.8333 2.41288 15.8333 3.33335V16.6667C15.8333 17.5872 15.0872 18.3334 14.1667 18.3334H5.83332C4.91285 18.3334 4.16666 17.5872 4.16666 16.6667V3.33335Z" />
                            </svg>
                        </span>
                        <span class="text">Relatórios</span>
                    </a>
                </li>

                <span class="divider">
                    <hr />
                </span>

                <li class="nav-item nav-item-has-children">
                    <a href="#0" class="collapsed" data-bs-toggle="collapse" data-bs-target="#ddmenu_config"
                        aria-controls="ddmenu_config" aria-expanded="false">
                        <span class="icon">
                            <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path
                                    d="M10 1.66669C5.39763 1.66669 1.66666 5.39766 1.66666 10C1.66666 14.6024 5.39763 18.3334 10 18.3334C14.6024 18.3334 18.3333 14.6024 18.3333 10C18.3333 5.39766 14.6024 1.66669 10 1.66669Z" />
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
                                <path
                                    d="M10.8333 2.50008C10.8333 2.03984 10.4602 1.66675 9.99999 1.66675C9.53975 1.66675 9.16666 2.03984 9.16666 2.50008C9.16666 2.96032 9.53975 3.33341 9.99999 3.33341C10.4602 3.33341 10.8333 2.96032 10.8333 2.50008Z" />
                                <path
                                    d="M11.4272 2.69637C10.9734 2.56848 10.4947 2.50006 10 2.50006C7.10054 2.50006 4.75003 4.85057 4.75003 7.75006V9.20873C4.75003 9.72814 4.62082 10.2393 4.37404 10.6963L3.36705 12.5611C2.89938 13.4272 3.26806 14.5081 4.16749 14.9078C7.88074 16.5581 12.1193 16.5581 15.8326 14.9078C16.732 14.5081 17.1007 13.4272 16.633 12.5611L15.626 10.6963C15.43 10.3333 15.3081 9.93606 15.2663 9.52773C15.0441 9.56431 14.8159 9.58339 14.5833 9.58339C12.2822 9.58339 10.4167 7.71791 10.4167 5.41673C10.4167 4.37705 10.7975 3.42631 11.4272 2.69637Z" />
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
                                <button class="main-btn light-btn btn-hover btn-compact" id="btnPDF" type="button">
                                    <i class="lni lni-printer me-1"></i> PDF
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
                                    <th class="minw-120">Imagem</th>
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

                                    $imgDb = trim((string)($p['imagem'] ?? ''));
                                    $img   = produto_img_url($imgDb);
                                    ?>
                                    <tr
                                        data-cat="<?= e($categoria) ?>"
                                        data-cod="<?= e($codigo ?: '—') ?>"
                                        data-prod="<?= e($nome ?: '—') ?>"
                                        data-estoque="<?= (int)$estoque ?>"
                                        data-min="<?= (int)$minimo ?>">
                                        <td>
                                            <img class="prod-img" alt="<?= e($nome ?: $codigo) ?>" src="<?= e($img) ?>" />
                                        </td>
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

                    <p class="text-sm text-gray mt-2 mb-0" id="infoCount"></p>
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

    <!-- jsPDF + AutoTable (PDF) -->
    <script src="https://cdn.jsdelivr.net/npm/jspdf@2.5.1/dist/jspdf.umd.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/jspdf-autotable@3.8.2/dist/jspdf.plugin.autotable.min.js"></script>

    <script>
        // flash 1.5s
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

        // fallback imagem
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
        const qMinimo = document.getElementById('qMinimo');
        const qGlobal = document.getElementById('qGlobal');
        const fCategoria = document.getElementById('fCategoria');
        const fTipo = document.getElementById('fTipo');
        const infoCount = document.getElementById('infoCount');

        const kpiCritico = document.getElementById('kpiCritico');
        const kpiBaixo = document.getElementById('kpiBaixo');
        const kpiOk = document.getElementById('kpiOk');

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

            // colunas fixas: estoque(5), minimo(6), situação(7), sugestão(8)
            tr.children[5].innerText = String(estoque);
            tr.children[6].innerText = String(minimo);
            tr.children[7].innerHTML = badge(st);
            tr.children[8].innerText = (st === 'OK') ? '0' : `+${sug}`;
        }

        function aplicarFiltros() {
            const q = norm(qMinimo.value || qGlobal.value);
            const cat = norm(fCategoria.value);
            const tipo = fTipo.value || 'BAIXO';

            const rows = Array.from(tb.querySelectorAll('tbody tr'));
            let shown = 0;

            let nCrit = 0,
                nBaixo = 0,
                nOk = 0;

            rows.forEach(tr => {
                rebuildRow(tr);

                const text = norm(tr.innerText);
                const rCat = norm(tr.getAttribute('data-cat'));

                const estoque = Number(tr.getAttribute('data-estoque') || 0);
                const minimo = Number(tr.getAttribute('data-min') || 0);
                const st = calcStatus(estoque, minimo);

                if (st === 'CRITICO') nCrit++;
                else if (st === 'BAIXO') nBaixo++;
                else nOk++;

                let ok = true;
                if (q && !text.includes(q)) ok = false;
                if (cat && rCat !== cat) ok = false;

                if (tipo === 'CRITICO' && st !== 'CRITICO') ok = false;
                if (tipo === 'BAIXO' && st !== 'BAIXO' && st !== 'CRITICO') ok = false; // abaixo inclui crítico
                // TODOS: não filtra por status

                tr.style.display = ok ? '' : 'none';
                if (ok) shown++;
            });

            kpiCritico.textContent = String(nCrit);
            kpiBaixo.textContent = String(nBaixo);
            kpiOk.textContent = String(nOk);

            infoCount.textContent = `Mostrando ${shown} item(ns).`;
        }

        qMinimo.addEventListener('input', aplicarFiltros);
        qGlobal.addEventListener('input', () => {
            qMinimo.value = qGlobal.value;
            aplicarFiltros();
        });
        fCategoria.addEventListener('change', aplicarFiltros);
        fTipo.addEventListener('change', aplicarFiltros);
        aplicarFiltros();

        // ✅ Excel (.xls) estilizado
        function exportExcel() {
            const rows = Array.from(tb.querySelectorAll('tbody tr')).filter(tr => tr.style.display !== 'none');

            const now = new Date();
            const dt = now.toLocaleDateString('pt-BR') + ' ' + now.toLocaleTimeString('pt-BR');

            const cat = fCategoria.value || 'Todas';
            const tipo = fTipo.value || 'BAIXO';

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
              table { border: 0.6px solid #999; font-family: Arial; font-size: 12px; }
              td, th { border: 1px solid #999; padding: 6px 8px; vertical-align: middle; }
              th { background: #f1f5f9; font-weight: 700; }
              .title { font-size: 16px; font-weight: 700; background: #eef2ff; text-align: center; }
              .muted { color: #555; font-weight: 700; }
              .center { text-align: center; }
            </style>
          </head>
          <body>
            <table>
      `;

            html += `<tr><td class="title" colspan="8">PAINEL DA DISTRIBUIDORA - ESTOQUE MÍNIMO</td></tr>`;
            html += `<tr><td class="muted">Gerado em:</td><td colspan="7">${dt}</td></tr>`;
            html += `<tr><td class="muted">Categoria:</td><td>${cat}</td><td class="muted">Filtro:</td><td>${tipo}</td><td colspan="4"></td></tr>`;

            html += `<tr>${header.map((h, idx) => `<th class="${isCenterCol(idx) ? 'center' : ''}">${h}</th>`).join('')}</tr>`;
            body.forEach(r => {
                html += `<tr>${r.map((c, idx) => {
          const safe = String(c).replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;');
          const cls = isCenterCol(idx) ? 'center' : '';
          return `<td class="${cls}">${safe}</td>`;
        }).join('')}</tr>`;
            });

            html += `</table></body></html>`;

            const blob = new Blob(["\ufeff" + html], {
                type: 'application/vnd.ms-excel;charset=utf-8;'
            });
            const url = URL.createObjectURL(blob);

            const a = document.createElement('a');
            a.href = url;
            a.download = 'estoque_minimo.xls';
            document.body.appendChild(a);
            a.click();
            a.remove();
            URL.revokeObjectURL(url);
        }
        document.getElementById('btnExcel').addEventListener('click', exportExcel);

        // ✅ PDF
        function exportPDF() {
            if (!window.jspdf || !window.jspdf.jsPDF) {
                alert('Biblioteca do PDF não carregou.');
                return;
            }

            const rows = Array.from(tb.querySelectorAll('tbody tr')).filter(tr => tr.style.display !== 'none');

            const now = new Date();
            const dt = now.toLocaleDateString('pt-BR') + ' ' + now.toLocaleTimeString('pt-BR');

            const cat = fCategoria.value || 'Todas';
            const tipo = fTipo.value || 'BAIXO';

            const {
                jsPDF
            } = window.jspdf;
            const doc = new jsPDF({
                orientation: 'landscape',
                unit: 'pt',
                format: 'a4'
            });

            const M = 70;

            doc.setTextColor(0, 0, 0);
            doc.setFont('helvetica', 'bold');
            doc.setFontSize(14);
            doc.text('PAINEL DA DISTRIBUIDORA - ESTOQUE MÍNIMO', M, 55);

            doc.setFont('helvetica', 'normal');
            doc.setFontSize(10);
            doc.text(`Gerado em:  ${dt}`, M, 75);
            doc.text(`Categoria:  ${cat} | Filtro:  ${tipo}`, M, 92);

            const head = [
                ['Código', 'Produto', 'Categoria', 'Unidade', 'Estoque', 'Mínimo', 'Situação', 'Sugestão']
            ];

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

            doc.autoTable({
                head,
                body,
                startY: 115,
                margin: {
                    left: M,
                    right: M
                },
                theme: 'plain',
                styles: {
                    font: 'helvetica',
                    fontSize: 9,
                    textColor: [17, 24, 39],
                    cellPadding: {
                        top: 6,
                        right: 6,
                        bottom: 6,
                        left: 6
                    },
                    lineWidth: 0
                },
                headStyles: {
                    fillColor: [241, 245, 249],
                    textColor: [17, 24, 39],
                    fontStyle: 'bold',
                    lineWidth: 0
                },
                alternateRowStyles: {
                    fillColor: [248, 250, 252]
                },
                columnStyles: {
                    4: {
                        halign: 'center'
                    },
                    5: {
                        halign: 'center'
                    },
                    6: {
                        halign: 'center'
                    },
                    7: {
                        halign: 'center'
                    }
                },
                didParseCell: function(data) {
                    data.cell.styles.lineWidth = 0;
                }
            });

            doc.save('estoque_minimo.pdf');
        }
        document.getElementById('btnPDF').addEventListener('click', exportPDF);
    </script>
</body>

</html>