<?php

declare(strict_types=1);
session_start();

require_once __DIR__ . '/assets/conexao.php';
require_once __DIR__ . '/assets/dados/saidas/_helpers.php';

$pdo = db();

$csrf  = csrf_token();
$flash = flash_pop();

function brDate(?string $ymd): string
{
    if (!$ymd) return '';
    $p = explode('-', $ymd);
    if (count($p) !== 3) return (string)$ymd;
    return $p[2] . '/' . $p[1] . '/' . $p[0];
}
function fmtBRL($n): string
{
    return 'R$ ' . number_format((float)$n, 2, ',', '.');
}

// PRODUTOS (base na sua tabela)
$produtos = $pdo->query("
  SELECT id, codigo, nome, unidade, preco, estoque, status
  FROM produtos
  WHERE status = 'ATIVO'
  ORDER BY nome ASC
")->fetchAll(PDO::FETCH_ASSOC);

// SAÍDAS
$saidas = $pdo->query("
  SELECT s.*,
         p.codigo AS produto_codigo,
         p.nome   AS produto_nome
  FROM saidas s
  LEFT JOIN produtos p ON p.id = s.produto_id
  ORDER BY s.data DESC, s.id DESC
  LIMIT 3000
")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />

    <link rel="shortcut icon" href="assets/images/favicon.svg" type="image/x-icon" />
    <title>Painel da Distribuidora | Saídas (Perdas/Avarias)</title>

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

        .minw-240 {
            min-width: 240px;
        }

        .minw-260 {
            min-width: 260px;
        }

        .table-responsive {
            -webkit-overflow-scrolling: touch;
        }

        #tbSaidas {
            width: 100%;
            min-width: 1220px;
        }

        #tbSaidas th,
        #tbSaidas td {
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
            min-width: 100px;
        }

        .badge-soft-danger {
            background: rgba(239, 68, 68, .12);
            color: #dc2626;
        }

        .badge-soft-warning {
            background: rgba(245, 158, 11, .12);
            color: #b45309;
        }

        .badge-soft-gray {
            background: rgba(148, 163, 184, .18);
            color: #475569;
        }

        .badge-soft-blue {
            background: rgba(59, 130, 246, .12);
            color: #2563eb;
        }

        .badge-soft-green {
            background: rgba(34, 197, 94, .12);
            color: #16a34a;
        }

        .td-center {
            text-align: center;
        }

        .td-right {
            text-align: right;
        }

        .form-control.compact,
        .form-select.compact {
            height: 38px;
            padding: 8px 12px;
            font-size: 13px;
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
                        <li><a href="fiados.php">À Prazo</a></li>
                        <li><a href="devolucoes.php">Devoluções</a></li>
                    </ul>
                </li>

                <li class="nav-item nav-item-has-children active">
                    <a href="#0" class="collapsed" data-bs-toggle="collapse" data-bs-target="#ddmenu_estoque" aria-controls="ddmenu_estoque" aria-expanded="false">
                        <span class="icon">
                            <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M2.49999 5.83331C2.03976 5.83331 1.66666 6.2064 1.66666 6.66665V10.8333C1.66666 13.5948 3.90523 15.8333 6.66666 15.8333H9.99999C12.1856 15.8333 14.0436 14.431 14.7235 12.4772C14.8134 12.4922 14.9058 12.5 15 12.5H16.6667C17.5872 12.5 18.3333 11.7538 18.3333 10.8333V8.33331C18.3333 7.41284 17.5872 6.66665 16.6667 6.66665H15C15 6.2064 14.6269 5.83331 14.1667 5.83331H2.49999Z" />
                                <path d="M2.49999 16.6667C2.03976 16.6667 1.66666 17.0398 1.66666 17.5C1.66666 17.9602 2.03976 18.3334 2.49999 18.3334H14.1667C14.6269 18.3334 15 17.9602 15 17.5C15 17.0398 14.6269 16.6667 14.1667 16.6667H2.49999Z" />
                            </svg>
                        </span>
                        <span class="text">Estoque</span>
                    </a>
                    <ul id="ddmenu_estoque" class="collapse show dropdown-nav">
                        <li><a href="produtos.php">Produtos</a></li>
                        <li><a href="inventario.php">Inventário</a></li>
                        <li><a href="entradas.php">Entradas</a></li>
                        <li><a href="saidas.php" class="active">Saídas</a></li>
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

                <span class="divider">
                    <hr />
                </span>

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
                                <button id="menu-toggle" class="main-btn primary-btn btn-hover btn-compact">
                                    <i class="lni lni-chevron-left me-2"></i> Menu
                                </button>
                            </div>
                            <div class="header-search d-none d-md-flex">
                                <form action="#" onsubmit="return false;">
                                    <input type="text" placeholder="Buscar saída (perda/avaria)..." id="qGlobal" />
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
                        <div class="col-md-7">
                            <div class="title">
                                <h2>Saídas (Perdas/Avarias/Vencidos)</h2>
                                <p class="text-sm text-gray mb-0">Registrar produto estragado, vencido, quebrado ou consumo interno (não é venda).</p>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if ($flash): ?>
                    <div id="flashBox" class="alert alert-<?= e((string)$flash['type']) ?> flash-auto-hide mt-2">
                        <?= e((string)$flash['msg']) ?>
                    </div>
                <?php endif; ?>

                <div class="card-style mb-30">
                    <div class="row g-3 align-items-end">
                        <div class="col-12 col-md-6 col-lg-4">
                            <label class="form-label">Pesquisar</label>
                            <input type="text" class="form-control compact" id="qSaidas" placeholder="Produto, motivo, observação..." />
                        </div>

                        <div class="col-12 col-md-6 col-lg-2">
                            <label class="form-label">Tipo</label>
                            <select class="form-select compact" id="fTipo">
                                <option value="">Todos</option>
                                <option value="PERDA">Perda</option>
                                <option value="AVARIA">Avaria</option>
                                <option value="VENCIDO">Vencido</option>
                                <option value="CONSUMO">Consumo interno</option>
                                <option value="AJUSTE">Ajuste</option>
                                <option value="OUTROS">Outros</option>
                            </select>
                        </div>

                        <div class="col-12 col-md-6 col-lg-3">
                            <label class="form-label">Período</label>
                            <div class="d-flex gap-2">
                                <input type="date" class="form-control compact" id="dtIni" />
                                <input type="date" class="form-control compact" id="dtFim" />
                            </div>
                        </div>

                        <div class="col-12 col-md-6 col-lg-3">
                            <div class="d-grid gap-2 d-sm-flex justify-content-sm-end flex-wrap">
                                <button class="main-btn primary-btn btn-hover btn-compact" data-bs-toggle="modal" data-bs-target="#modalSaida" id="btnNovo" type="button">
                                    <i class="lni lni-plus me-1"></i> Nova
                                </button>
                                <button class="main-btn light-btn btn-hover btn-compact" id="btnExcel" type="button">
                                    <i class="lni lni-download me-1"></i> Excel
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card-style mb-30">
                    <div class="table-responsive">
                        <table class="table text-nowrap" id="tbSaidas">
                            <thead>
                                <tr>
                                    <th class="minw-140">Data</th>
                                    <th class="minw-160 td-center">Tipo</th>
                                    <th class="minw-260">Motivo</th>
                                    <th class="minw-140">Código</th>
                                    <th class="minw-260">Produto</th>
                                    <th class="minw-140">Unidade</th>
                                    <th class="minw-120 td-center">Qtd</th>
                                    <th class="minw-160 td-center">Valor (un)</th>
                                    <th class="minw-160 td-center">Total</th>
                                    <th class="minw-240">Obs</th>
                                    <th class="minw-140 text-end">Ações</th>
                                </tr>
                            </thead>

                            <tbody>
                                <?php foreach ($saidas as $s): ?>
                                    <?php
                                    $id = (int)$s['id'];
                                    $ymd = (string)$s['data'];
                                    $tipo = strtoupper((string)($s['tipo'] ?? 'PERDA'));
                                    $motivo = (string)($s['motivo'] ?? '');
                                    $obs = (string)($s['obs'] ?? '');

                                    $codigo = trim((string)($s['produto_codigo'] ?? '')) ?: '—';
                                    $prodNome = trim((string)($s['produto_nome'] ?? '')) ?: '—';

                                    $qtd = (int)$s['qtd'];
                                    $valorUnit = (float)($s['valor_unit'] ?? 0);
                                    $valorTotal = (float)($s['valor_total'] ?? 0);

                                    $badge = match ($tipo) {
                                        'AVARIA'  => '<span class="badge-soft badge-soft-warning">AVARIA</span>',
                                        'VENCIDO' => '<span class="badge-soft badge-soft-danger">VENCIDO</span>',
                                        'CONSUMO' => '<span class="badge-soft badge-soft-green">CONSUMO</span>',
                                        'AJUSTE'  => '<span class="badge-soft badge-soft-blue">AJUSTE</span>',
                                        'OUTROS'  => '<span class="badge-soft badge-soft-gray">OUTROS</span>',
                                        default   => '<span class="badge-soft badge-soft-danger">PERDA</span>',
                                    };
                                    ?>
                                    <tr
                                        data-id="<?= $id ?>"
                                        data-data="<?= e($ymd) ?>"
                                        data-tipo="<?= e($tipo) ?>"
                                        data-motivo="<?= e($motivo) ?>"
                                        data-obs="<?= e($obs) ?>"
                                        data-produto-id="<?= (int)$s['produto_id'] ?>"
                                        data-unidade="<?= e((string)$s['unidade']) ?>"
                                        data-qtd="<?= e((string)$qtd) ?>"
                                        data-valor-unit="<?= e((string)($s['valor_unit'] ?? 0)) ?>">
                                        <td class="date"><?= e(brDate($ymd)) ?></td>
                                        <td class="td-center tipo"><?= $badge ?></td>
                                        <td class="motivo"><?= e($motivo) ?></td>
                                        <td class="cod"><?= e($codigo) ?></td>
                                        <td class="prod"><?= e($prodNome) ?></td>
                                        <td class="und"><?= e((string)$s['unidade']) ?></td>
                                        <td class="td-center qtd"><?= e((string)$qtd) ?></td>
                                        <td class="td-center vunit"><?= e(fmtBRL($valorUnit)) ?></td>
                                        <td class="td-center vtot"><?= e(fmtBRL($valorTotal)) ?></td>
                                        <td class="obs"><?= e($obs) ?></td>
                                        <td class="text-end">
                                            <button class="main-btn light-btn btn-hover icon-btn btnEdit" type="button" title="Editar"><i class="lni lni-pencil"></i></button>
                                            <button class="main-btn danger-btn-outline btn-hover icon-btn btnDel" type="button" title="Excluir"><i class="lni lni-trash-can"></i></button>
                                        </td>
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

    <form id="frmDelete" action="assets/dados/saidas/excluirSaidas.php" method="post" style="display:none;">
        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
        <input type="hidden" name="id" id="delId" value="">
    </form>

    <div class="modal fade" id="modalSaida" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalSaidaTitle">Nova Saída</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>

                <div class="modal-body">
                    <form id="formSaida" action="assets/dados/saidas/salvarSaidas.php" method="post">
                        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                        <input type="hidden" name="id" id="pId" value="">

                        <div class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label">Data</label>
                                <input type="date" class="form-control compact" id="pData" name="data" required />
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">Tipo</label>
                                <select class="form-select compact" id="pTipo" name="tipo" required>
                                    <option value="">Selecione…</option>
                                    <option value="PERDA">Perda</option>
                                    <option value="AVARIA">Avaria</option>
                                    <option value="VENCIDO">Vencido</option>
                                    <option value="CONSUMO">Consumo interno</option>
                                    <option value="AJUSTE">Ajuste</option>
                                    <option value="OUTROS">Outros</option>
                                </select>
                            </div>

                            <div class="col-md-5">
                                <label class="form-label">Motivo</label>
                                <input type="text" class="form-control compact" id="pMotivo" name="motivo" placeholder="Ex: estragou, venceu, quebrou..." required />
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Produto</label>
                                <select class="form-select compact" id="pProdutoId" name="produto_id" required>
                                    <option value="">Selecione…</option>
                                    <?php foreach ($produtos as $p): ?>
                                        <?php
                                        $cod = (string)$p['codigo'];
                                        $nm  = (string)$p['nome'];
                                        $un  = (string)($p['unidade'] ?? '');
                                        $pr  = (string)($p['preco'] ?? '0');
                                        $est = (string)($p['estoque'] ?? '0');
                                        ?>
                                        <option
                                            value="<?= (int)$p['id'] ?>"
                                            data-codigo="<?= e($cod) ?>"
                                            data-nome="<?= e($nm) ?>"
                                            data-unidade="<?= e($un) ?>"
                                            data-preco="<?= e($pr) ?>"
                                            data-estoque="<?= e($est) ?>"><?= e($cod . ' - ' . $nm . ' (Est: ' . $est . ')') ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="text-xs text-gray mt-1" id="pEstoqueInfo"></div>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label">Código</label>
                                <input type="text" class="form-control compact" id="pCodigo" placeholder="auto" readonly />
                            </div>

                            <div class="col-md-3">
                                <label class="form-label">Unidade</label>
                                <select class="form-select compact" id="pUnidade" name="unidade" required>
                                    <option value="">Selecione…</option>
                                    <option>Unidade</option>
                                    <option>Pacote</option>
                                    <option>Caixa</option>
                                    <option>Kg</option>
                                    <option>Litro</option>
                                </select>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label">Qtd</label>
                                <input type="number" step="1" class="form-control compact td-center" id="pQtd" name="qtd" min="1" value="1" required />
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">Valor (un) <span class="text-xs text-gray">(opcional)</span></label>
                                <input type="text" class="form-control compact td-center" id="pValorUnit" name="valor_unit" placeholder="0,00" />
                            </div>

                            <div class="col-md-5">
                                <label class="form-label">Total</label>
                                <input type="text" class="form-control compact td-center" id="pTotal" placeholder="0,00" readonly />
                            </div>

                            <div class="col-12">
                                <label class="form-label">Observação</label>
                                <textarea class="form-control compact" id="pObs" name="obs" rows="2" placeholder="Detalhes do ocorrido..."></textarea>
                            </div>
                        </div>
                    </form>

                    <p class="text-sm text-gray mt-3 mb-0">
                        Total (se informado valor): <b>Qtd × Valor</b>.
                    </p>
                </div>

                <div class="modal-footer">
                    <button type="button" class="main-btn light-btn btn-hover btn-compact" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" form="formSaida" class="main-btn primary-btn btn-hover btn-compact">
                        <i class="lni lni-save me-1"></i> Salvar
                    </button>
                </div>
            </div>
        </div>
    </div>

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

        const tb = document.getElementById('tbSaidas');
        const tbodyRows = Array.from(tb.querySelectorAll('tbody tr'));

        const qSaidas = document.getElementById('qSaidas');
        const qGlobal = document.getElementById('qGlobal');
        const fTipo = document.getElementById('fTipo');
        const dtIni = document.getElementById('dtIni');
        const dtFim = document.getElementById('dtFim');
        const infoCount = document.getElementById('infoCount');

        const btnPrevPage = document.getElementById('btnPrevPage');
        const btnNextPage = document.getElementById('btnNextPage');
        const pageInfo = document.getElementById('pageInfo');

        const PER_PAGE = 5;
        let currentPage = 1;

        function norm(s) {
            return String(s ?? '').toLowerCase().trim();
        }

        function parseBRL(txt) {
            let s = String(txt ?? '').trim();
            s = s.replace(/\s/g, '').replace('R$', '').replace(/\./g, '').replace(',', '.');
            const n = Number(s);
            return isNaN(n) ? 0 : n;
        }

        function fmtBRL(n) {
            return 'R$ ' + Number(n || 0).toFixed(2).replace('.', ',');
        }

        function syncSearch(source, target) {
            if (target.value !== source.value) {
                target.value = source.value;
            }
        }

        function rowMatches(tr) {
            const q = norm(qSaidas.value || qGlobal.value);
            const tipo = (fTipo.value || '').toUpperCase();
            const ini = dtIni.value || '';
            const fim = dtFim.value || '';

            const text = norm(tr.innerText);
            const rTipo = (tr.getAttribute('data-tipo') || '').toUpperCase();
            const rData = tr.getAttribute('data-data') || '';

            let ok = true;
            if (q && !text.includes(q)) ok = false;
            if (tipo && rTipo !== tipo) ok = false;
            if (ini && rData && rData < ini) ok = false;
            if (fim && rData && rData > fim) ok = false;

            return ok;
        }

        function getFilteredRows() {
            return tbodyRows.filter(rowMatches);
        }

        function renderTable(resetPage = false) {
            if (resetPage) currentPage = 1;

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
                infoCount.textContent = `Mostrando ${pageRows.length} saída(s) nesta página. Total filtrado: ${totalItems}.`;
            } else {
                infoCount.textContent = 'Nenhuma saída encontrada.';
            }

            pageInfo.textContent = `Página ${currentPage}/${totalPages}`;
            btnPrevPage.disabled = currentPage <= 1 || totalItems === 0;
            btnNextPage.disabled = currentPage >= totalPages || totalItems === 0;
        }

        qSaidas.addEventListener('input', () => {
            syncSearch(qSaidas, qGlobal);
            renderTable(true);
        });

        qGlobal.addEventListener('input', () => {
            syncSearch(qGlobal, qSaidas);
            renderTable(true);
        });

        fTipo.addEventListener('change', () => renderTable(true));
        dtIni.addEventListener('change', () => renderTable(true));
        dtFim.addEventListener('change', () => renderTable(true));

        btnPrevPage.addEventListener('click', () => {
            currentPage--;
            renderTable(false);
        });

        btnNextPage.addEventListener('click', () => {
            currentPage++;
            renderTable(false);
        });

        // ===== Modal =====
        const modalEl = document.getElementById('modalSaida');
        const modal = new bootstrap.Modal(modalEl);
        const modalTitle = document.getElementById('modalSaidaTitle');

        const pId = document.getElementById('pId');
        const pData = document.getElementById('pData');
        const pTipo = document.getElementById('pTipo');
        const pMotivo = document.getElementById('pMotivo');
        const pObs = document.getElementById('pObs');

        const pProdutoId = document.getElementById('pProdutoId');
        const pCodigo = document.getElementById('pCodigo');
        const pUnidade = document.getElementById('pUnidade');
        const pQtd = document.getElementById('pQtd');
        const pValorUnit = document.getElementById('pValorUnit');
        const pTotal = document.getElementById('pTotal');
        const pEstoqueInfo = document.getElementById('pEstoqueInfo');

        function todayYMD() {
            const t = new Date();
            const yyyy = t.getFullYear();
            const mm = String(t.getMonth() + 1).padStart(2, '0');
            const dd = String(t.getDate()).padStart(2, '0');
            return `${yyyy}-${mm}-${dd}`;
        }

        function limparForm() {
            pId.value = '';
            pData.value = todayYMD();
            pTipo.value = 'PERDA';
            pMotivo.value = '';
            pObs.value = '';

            pProdutoId.value = '';
            pCodigo.value = '';
            pUnidade.value = '';
            pQtd.value = 1;

            pValorUnit.value = '';
            pTotal.value = fmtBRL(0);
            pEstoqueInfo.textContent = '';
        }

        document.getElementById('btnNovo').addEventListener('click', () => {
            modalTitle.textContent = 'Nova Saída';
            limparForm();
        });

        function recalcularTotal() {
            const qtd = Number(pQtd.value || 0);
            const v = parseBRL(pValorUnit.value);
            pTotal.value = fmtBRL(qtd * v);
        }
        pQtd.addEventListener('input', recalcularTotal);
        pValorUnit.addEventListener('input', recalcularTotal);

        // selecionou produto: preenche código/unidade e sugere valor com base no produtos.preco
        pProdutoId.addEventListener('change', () => {
            const opt = pProdutoId.selectedOptions && pProdutoId.selectedOptions[0];
            if (!opt || !opt.value) {
                pCodigo.value = '';
                pEstoqueInfo.textContent = '';
                return;
            }

            pCodigo.value = opt.getAttribute('data-codigo') || '';
            const und = opt.getAttribute('data-unidade') || '';
            if (und) pUnidade.value = und;

            const est = opt.getAttribute('data-estoque') || '';
            pEstoqueInfo.textContent = est !== '' ? `Estoque atual: ${est}` : '';

            if (!pValorUnit.value) {
                const pr = opt.getAttribute('data-preco') || '';
                const n = Number(String(pr).replace(',', '.'));
                if (!Number.isNaN(n)) pValorUnit.value = String(n.toFixed(2)).replace('.', ',');
            }

            recalcularTotal();
        });

        // editar/excluir
        tb.addEventListener('click', (e) => {
            const tr = e.target.closest('tr');
            if (!tr) return;

            const btnDel = e.target.closest('.btnDel');
            if (btnDel) {
                const id = tr.getAttribute('data-id') || '';
                const prod = tr.querySelector('.prod')?.innerText || '';
                if (confirm(`Remover esta saída do produto "${prod}"?`)) {
                    document.getElementById('delId').value = id;
                    document.getElementById('frmDelete').submit();
                }
                return;
            }

            const btnEdit = e.target.closest('.btnEdit');
            if (!btnEdit) return;

            modalTitle.textContent = 'Editar Saída';

            pId.value = tr.getAttribute('data-id') || '';
            pData.value = tr.getAttribute('data-data') || todayYMD();
            pTipo.value = (tr.getAttribute('data-tipo') || 'PERDA').toUpperCase();
            pMotivo.value = tr.getAttribute('data-motivo') || '';
            pObs.value = tr.getAttribute('data-obs') || '';

            pProdutoId.value = tr.getAttribute('data-produto-id') || '';
            pProdutoId.dispatchEvent(new Event('change'));

            pUnidade.value = tr.getAttribute('data-unidade') || pUnidade.value || '';
            pQtd.value = tr.getAttribute('data-qtd') || '1';

            const vunit = tr.getAttribute('data-valor-unit') || '0';
            const vn = Number(String(vunit).replace(',', '.'));
            pValorUnit.value = Number.isNaN(vn) ? '' : String(vn.toFixed(2)).replace('.', ',');

            recalcularTotal();
            modal.show();
        });

        // Excel
        function exportExcel() {
            const rows = getFilteredRows();

            if (!rows.length) {
                alert('Não há saídas para exportar.');
                return;
            }

            const now = new Date();
            const dt = now.toLocaleDateString('pt-BR') + ' ' + now.toLocaleTimeString('pt-BR');
            const fileDt = now.toISOString().slice(0, 19).replace(/[:T]/g, '-');

            const tipo = fTipo.value || 'Todos';
            const ini = dtIni.value || '—';
            const fim = dtFim.value || '—';
            const busca = (qSaidas.value || qGlobal.value || '').trim() || '—';

            const header = ['Data', 'Tipo', 'Motivo', 'Código', 'Produto', 'Unidade', 'Qtd', 'Valor (un)', 'Total', 'Obs'];

            const body = rows.map(tr => ([
                tr.querySelector('.date')?.innerText.trim() || '',
                (tr.getAttribute('data-tipo') || '').toUpperCase(),
                tr.querySelector('.motivo')?.innerText.trim() || '',
                tr.querySelector('.cod')?.innerText.trim() || '',
                tr.querySelector('.prod')?.innerText.trim() || '',
                tr.querySelector('.und')?.innerText.trim() || '',
                tr.querySelector('.qtd')?.innerText.trim() || '',
                tr.querySelector('.vunit')?.innerText.trim() || '',
                tr.querySelector('.vtot')?.innerText.trim() || '',
                tr.querySelector('.obs')?.innerText.trim() || ''
            ]));

            const centerCols = (idx) => ([0, 1, 3, 5, 6, 7, 8].includes(idx));

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

            html += `<tr><td class="title" colspan="10">PAINEL DA DISTRIBUIDORA - SAÍDAS (PERDAS/AVARIAS)</td></tr>`;
            html += `<tr><td colspan="10">Gerado em: ${dt}</td></tr>`;
            html += `<tr><td colspan="10">Tipo: ${tipo} | Período: ${ini} até ${fim} | Busca: ${busca}</td></tr>`;
            html += `<tr>${header.map((h, idx) => `<th class="${centerCols(idx) ? 'center' : 'left'}">${h}</th>`).join('')}</tr>`;

            body.forEach(row => {
                html += '<tr>';
                row.forEach((cell, idx) => {
                    const safe = String(cell)
                        .replaceAll('&', '&amp;')
                        .replaceAll('<', '&lt;')
                        .replaceAll('>', '&gt;');

                    html += `<td class="${centerCols(idx) ? 'center' : 'left'}">${safe}</td>`;
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
            a.download = `saidas-perdas_${fileDt}.xls`;
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