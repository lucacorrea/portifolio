<?php

declare(strict_types=1);

session_start();

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/php/conexao.php';

if (!isset($pdo) || !($pdo instanceof PDO)) {
    die('Conexão com banco de dados não disponível.');
}

function h(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function money($value): string
{
    return 'R$ ' . number_format((float)$value, 2, ',', '.');
}

$flashSucesso = $_SESSION['flash_sucesso'] ?? null;
unset($_SESSION['flash_sucesso']);

$flashErro = $_SESSION['flash_erro'] ?? null;
unset($_SESSION['flash_erro']);

$busca = trim((string)($_GET['busca'] ?? ''));
$statusFiltro = trim((string)($_GET['status'] ?? ''));

$where = [];
$params = [];

if ($busca !== '') {
    $where[] = "(nome LIKE :busca OR cpf LIKE :busca OR telefone LIKE :busca OR email LIKE :busca)";
    $params[':busca'] = '%' . $busca . '%';
}

if ($statusFiltro !== '' && in_array($statusFiltro, ['Ativo', 'Pendente', 'Bloqueado', 'Inativo'], true)) {
    $where[] = "status = :status";
    $params[':status'] = $statusFiltro;
}

$sqlBase = "FROM clientes";
if ($where) {
    $sqlBase .= " WHERE " . implode(' AND ', $where);
}

try {
    $stmtResumo = $pdo->query("
        SELECT
            COUNT(*) AS total_clientes,
            SUM(CASE WHEN status = 'Ativo' THEN 1 ELSE 0 END) AS ativos,
            SUM(CASE WHEN status = 'Pendente' THEN 1 ELSE 0 END) AS pendentes,
            COALESCE(SUM(qtd_veiculos), 0) AS total_veiculos
        FROM clientes
    ");
    $resumo = $stmtResumo->fetch(PDO::FETCH_ASSOC) ?: [
        'total_clientes' => 0,
        'ativos' => 0,
        'pendentes' => 0,
        'total_veiculos' => 0,
    ];

    $sqlLista = "SELECT * {$sqlBase} ORDER BY id DESC";
    $stmt = $pdo->prepare($sqlLista);
    foreach ($params as $chave => $valor) {
        $stmt->bindValue($chave, $valor);
    }
    $stmt->execute();
    $clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $resumo = [
        'total_clientes' => 0,
        'ativos' => 0,
        'pendentes' => 0,
        'total_veiculos' => 0,
    ];
    $clientes = [];
    $flashErro = 'Erro ao carregar clientes: ' . $e->getMessage();
}
?>
<!doctype html>
<html lang="pt-BR" class="layout-menu-fixed layout-compact" data-assets-path="../assets/"
    data-template="vertical-menu-template-free">

<head>
    <meta charset="utf-8" />
    <meta name="viewport"
        content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />

    <title>Tático GPS - Clientes</title>
    <meta name="description" content="Cadastro e gestão de clientes do Tático GPS" />

    <link rel="icon" type="image/x-icon" href="../assets/img/favicon/favicon.ico" />

    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link
        href="https://fonts.googleapis.com/css2?family=Public+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,400;1,500;1,600;1,700&display=swap"
        rel="stylesheet" />

    <link rel="stylesheet" href="../assets/vendor/fonts/iconify-icons.css" />
    <link rel="stylesheet" href="../assets/vendor/css/core.css" />
    <link rel="stylesheet" href="../assets/css/demo.css" />
    <link rel="stylesheet" href="../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css" />

    <script src="../assets/vendor/js/helpers.js"></script>
    <script src="../assets/js/config.js"></script>

    <style>
    html,
    body {
        height: 100%;
    }

    body {
        overflow-x: hidden;
    }

    .layout-page {
        min-height: 100vh;
    }

    .layout-menu {
        height: 100vh !important;
        overflow: hidden;
        position: sticky;
        top: 0;
    }

    .layout-menu .menu-inner {
        height: calc(100vh - 90px);
        overflow-y: auto !important;
        overflow-x: hidden;
        padding-bottom: 2rem;
        scrollbar-width: thin;
    }

    .layout-menu .menu-inner::-webkit-scrollbar {
        width: 8px;
    }

    .layout-menu .menu-inner::-webkit-scrollbar-thumb {
        background: rgba(105, 108, 255, 0.35);
        border-radius: 10px;
    }

    .layout-menu .menu-inner::-webkit-scrollbar-track {
        background: transparent;
    }

    .page-banner h3 {
        margin-bottom: 0.35rem;
    }

    .page-banner p {
        margin-bottom: 0;
        color: #697a8d;
    }

    .page-banner-top {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 1rem;
        flex-wrap: wrap;
    }

    .page-banner-actions {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        flex-wrap: wrap;
    }

    .info-card .card-body {
        padding: 1.25rem;
    }

    .info-label {
        color: #697a8d;
        font-size: 0.92rem;
        margin-bottom: 0.35rem;
    }

    .info-value {
        font-size: 1.9rem;
        font-weight: 700;
        line-height: 1.1;
        color: #233446;
    }

    .info-meta {
        margin-top: 0.6rem;
        font-size: 0.86rem;
        font-weight: 600;
    }

    .section-title {
        margin-bottom: 0;
        font-weight: 700;
    }

    .table-tools {
        display: flex;
        gap: 0.75rem;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
    }

    .table-tools .left-tools,
    .table-tools .right-tools {
        display: flex;
        gap: 0.75rem;
        flex-wrap: wrap;
        align-items: center;
    }

    .table-responsive th {
        white-space: nowrap;
    }

    .badge-status {
        min-width: 88px;
        text-align: center;
    }

    .vehicle-badge {
        background: rgba(105, 108, 255, 0.12);
        color: #696cff;
        border-radius: 999px;
        padding: 0.35rem 0.7rem;
        font-size: 0.78rem;
        font-weight: 600;
        display: inline-block;
    }

    .mini-note {
        color: #8592a3;
        font-size: 0.84rem;
    }

    .help-list {
        list-style: none;
        padding: 0;
        margin: 0;
    }

    .help-list li {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 1rem;
        padding: 0.85rem 0;
        border-bottom: 1px solid #eceef1;
    }

    .help-list li:last-child {
        border-bottom: 0;
    }

    .help-text-box {
        background: rgba(105, 108, 255, 0.08);
        border: 1px solid rgba(105, 108, 255, 0.12);
        color: #566a7f;
        border-radius: 12px;
        padding: 1rem;
        margin-top: 1rem;
    }

    .modal-form-section {
        font-size: 0.95rem;
        font-weight: 700;
        color: #566a7f;
        margin-bottom: 0.85rem;
        padding-bottom: 0.45rem;
        border-bottom: 1px solid #eceef1;
    }

    .modal-lg-custom {
        max-width: 900px;
    }

    @media (max-width: 1199.98px) {
        .layout-menu {
            position: fixed;
            z-index: 1100;
        }
    }

    @media (max-width: 767.98px) {
        .page-banner-actions {
            width: 100%;
        }

        .page-banner-actions .btn {
            width: 100%;
        }
    }
    </style>
</head>

<body>
    <div class="layout-wrapper layout-content-navbar">
        <div class="layout-container">
            <aside id="layout-menu" class="layout-menu menu-vertical menu bg-menu-theme">
                <div class="app-brand demo">
                    <a href="dashboard.php" class="app-brand-link">
                        <span class="app-brand-logo demo">
                            <span class="text-primary">
                                <svg width="25" viewBox="0 0 25 42" version="1.1" xmlns="http://www.w3.org/2000/svg"
                                    xmlns:xlink="http://www.w3.org/1999/xlink">
                                    <defs>
                                        <path
                                            d="M13.7918663,0.358365126 L3.39788168,7.44174259 C0.566865006,9.69408886 -0.379795268,12.4788597 0.557900856,15.7960551 C0.68998853,16.2305145 1.09562888,17.7872135 3.12357076,19.2293357 C3.8146334,19.7207684 5.32369333,20.3834223 7.65075054,21.2172976 L7.59773219,21.2525164 L2.63468769,24.5493413 C0.445452254,26.3002124 0.0884951797,28.5083815 1.56381646,31.1738486 C2.83770406,32.8170431 5.20850219,33.2640127 7.09180128,32.5391577 C8.347334,32.0559211 11.4559176,30.0011079 16.4175519,26.3747182 C18.0338572,24.4997857 18.6973423,22.4544883 18.4080071,20.2388261 C17.963753,17.5346866 16.1776345,15.5799961 13.0496516,14.3747546 L10.9194936,13.4715819 L18.6192054,7.984237 L13.7918663,0.358365126 Z"
                                            id="path-1"></path>
                                    </defs>
                                    <g stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
                                        <g transform="translate(-27.000000, -15.000000)">
                                            <g transform="translate(27.000000, 15.000000)">
                                                <g transform="translate(0.000000, 8.000000)">
                                                    <mask id="mask-2" fill="white">
                                                        <use xlink:href="#path-1"></use>
                                                    </mask>
                                                    <use fill="currentColor" xlink:href="#path-1"></use>
                                                </g>
                                            </g>
                                        </g>
                                    </g>
                                </svg>
                            </span>
                        </span>
                        <span class="app-brand-text demo menu-text fw-bold ms-2">Tático GPS</span>
                    </a>

                    <a href="javascript:void(0);" class="layout-menu-toggle menu-link text-large ms-auto">
                        <i class="bx bx-chevron-left d-block d-xl-none align-middle"></i>
                    </a>
                </div>

                <div class="menu-divider mt-0"></div>
                <div class="menu-inner-shadow"></div>

                <ul class="menu-inner py-1">
                    <li class="menu-item"><a href="dashboard.php" class="menu-link"><i
                                class="menu-icon tf-icons bx bx-home-circle"></i>
                            <div class="text-truncate">Painel Geral</div>
                        </a></li>
                    <li class="menu-item active"><a href="clientes.php" class="menu-link"><i
                                class="menu-icon tf-icons bx bx-user"></i>
                            <div class="text-truncate">Clientes</div>
                        </a></li>
                    <li class="menu-item"><a href="cobrancas.php" class="menu-link"><i
                                class="menu-icon tf-icons bx bx-wallet"></i>
                            <div class="text-truncate">Cobranças</div>
                        </a></li>
                    <li class="menu-item"><a href="pagamentos.php" class="menu-link"><i
                                class="menu-icon tf-icons bx bx-credit-card"></i>
                            <div class="text-truncate">Pagamentos</div>
                        </a></li>
                    <li class="menu-item"><a href="mensagens.php" class="menu-link"><i
                                class="menu-icon tf-icons bx bx-message-detail"></i>
                            <div class="text-truncate">Mensagens</div>
                        </a></li>
                    <li class="menu-item"><a href="relatorios.php" class="menu-link"><i
                                class="menu-icon tf-icons bx bx-bar-chart-alt-2"></i>
                            <div class="text-truncate">Relatórios</div>
                        </a></li>
                    <li class="menu-item"><a href="configuracoes.php" class="menu-link"><i
                                class="menu-icon tf-icons bx bx-cog"></i>
                            <div class="text-truncate">Configurações</div>
                        </a></li>
                    <li class="menu-item"><a href="logout.php" class="menu-link"><i
                                class="menu-icon tf-icons bx bx-power-off"></i>
                            <div class="text-truncate">Sair</div>
                        </a></li>
                </ul>
            </aside>

            <div class="layout-page">
                <nav class="layout-navbar container-xxl navbar-detached navbar navbar-expand-xl align-items-center bg-navbar-theme"
                    id="layout-navbar">
                    <div class="layout-menu-toggle navbar-nav align-items-xl-center me-4 me-xl-0 d-xl-none">
                        <a class="nav-item nav-link px-0 me-xl-6" href="javascript:void(0)">
                            <i class="icon-base bx bx-menu icon-md"></i>
                        </a>
                    </div>

                    <div class="navbar-nav-right d-flex align-items-center justify-content-end w-100">
                        <div class="navbar-nav align-items-center me-auto">
                            <form method="GET" class="nav-item d-flex align-items-center">
                                <span class="w-px-22 h-px-22"><i class="icon-base bx bx-search icon-md"></i></span>
                                <input type="text" name="busca" value="<?= h($busca) ?>"
                                    class="form-control border-0 shadow-none ps-1 ps-sm-2 d-md-block d-none"
                                    placeholder="Buscar cliente por nome, CPF, telefone..."
                                    aria-label="Buscar cliente" />
                            </form>
                        </div>

                        <ul class="navbar-nav flex-row align-items-center ms-md-auto">
                            <li class="nav-item me-3">
                                <span class="badge rounded-pill bg-label-primary">Cadastro de clientes</span>
                            </li>

                            <li class="nav-item navbar-dropdown dropdown-user dropdown">
                                <a class="nav-link dropdown-toggle hide-arrow p-0" href="javascript:void(0);"
                                    data-bs-toggle="dropdown">
                                    <div class="avatar avatar-online">
                                        <img src="../assets/img/avatars/1.png" alt
                                            class="w-px-40 h-auto rounded-circle" />
                                    </div>
                                </a>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li><a class="dropdown-item" href="#"><i
                                                class="icon-base bx bx-user icon-md me-3"></i><span>Meu
                                                Perfil</span></a></li>
                                    <li><a class="dropdown-item" href="#"><i
                                                class="icon-base bx bx-cog icon-md me-3"></i><span>Configurações</span></a>
                                    </li>
                                    <li>
                                        <div class="dropdown-divider my-1"></div>
                                    </li>
                                    <li><a class="dropdown-item" href="#"><i
                                                class="icon-base bx bx-power-off icon-md me-3"></i><span>Sair</span></a>
                                    </li>
                                </ul>
                            </li>
                        </ul>
                    </div>
                </nav>

                <div class="content-wrapper">
                    <div class="container-xxl flex-grow-1 container-p-y">

                        <?php if ($flashSucesso): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="bx bx-check-circle me-1"></i>
                            <?= h($flashSucesso) ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"
                                aria-label="Fechar"></button>
                        </div>
                        <?php endif; ?>

                        <?php if ($flashErro): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="bx bx-error-circle me-1"></i>
                            <?= h($flashErro) ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"
                                aria-label="Fechar"></button>
                        </div>
                        <?php endif; ?>

                        <div class="row mb-4">
                            <div class="col-12">
                                <div class="card page-banner">
                                    <div class="card-body">
                                        <div class="page-banner-top">
                                            <div>
                                                <h3 class="text-primary">Clientes</h3>
                                                <p>Gerencie a base de clientes do Tático GPS com cadastro real em banco
                                                    de dados.</p>
                                            </div>

                                            <div class="page-banner-actions">
                                                <button class="btn btn-outline-info" type="button"
                                                    data-bs-toggle="modal" data-bs-target="#modalAjudaClientes">
                                                    <i class="bx bx-help-circle me-1"></i> Ajuda
                                                </button>

                                                <button class="btn btn-primary" type="button" data-bs-toggle="modal"
                                                    data-bs-target="#modalNovoCliente">
                                                    <i class="bx bx-plus me-1"></i> Novo Cliente
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-xl-3 col-md-6 mb-4">
                                <div class="card info-card h-100">
                                    <div class="card-body">
                                        <div class="info-label">Total de Clientes</div>
                                        <div class="info-value"><?= (int)$resumo['total_clientes'] ?></div>
                                        <div class="info-meta text-primary"><i class="bx bx-group"></i> base cadastrada
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-xl-3 col-md-6 mb-4">
                                <div class="card info-card h-100">
                                    <div class="card-body">
                                        <div class="info-label">Clientes Ativos</div>
                                        <div class="info-value"><?= (int)$resumo['ativos'] ?></div>
                                        <div class="info-meta text-success"><i class="bx bx-check-circle"></i> contratos
                                            ativos</div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-xl-3 col-md-6 mb-4">
                                <div class="card info-card h-100">
                                    <div class="card-body">
                                        <div class="info-label">Pendentes</div>
                                        <div class="info-value"><?= (int)$resumo['pendentes'] ?></div>
                                        <div class="info-meta text-danger"><i class="bx bx-error-circle"></i> requer
                                            cobrança</div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-xl-3 col-md-6 mb-4">
                                <div class="card info-card h-100">
                                    <div class="card-body">
                                        <div class="info-label">Veículos Vinculados</div>
                                        <div class="info-value"><?= (int)$resumo['total_veiculos'] ?></div>
                                        <div class="info-meta text-warning"><i class="bx bx-car"></i> total da carteira
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-12 mb-4">
                                <div class="card">
                                    <div class="card-header">
                                        <form method="GET" class="table-tools">
                                            <div class="left-tools">
                                                <h5 class="section-title">Lista de Clientes</h5>
                                                <span class="badge bg-label-primary">Banco de dados</span>
                                            </div>

                                            <div class="right-tools">
                                                <select class="form-select" style="width: 180px;" name="status">
                                                    <option value="">Todos os status</option>
                                                    <option value="Ativo"
                                                        <?= $statusFiltro === 'Ativo' ? 'selected' : '' ?>>Ativo
                                                    </option>
                                                    <option value="Pendente"
                                                        <?= $statusFiltro === 'Pendente' ? 'selected' : '' ?>>Pendente
                                                    </option>
                                                    <option value="Bloqueado"
                                                        <?= $statusFiltro === 'Bloqueado' ? 'selected' : '' ?>>Bloqueado
                                                    </option>
                                                    <option value="Inativo"
                                                        <?= $statusFiltro === 'Inativo' ? 'selected' : '' ?>>Inativo
                                                    </option>
                                                </select>

                                                <input type="text" class="form-control" style="width: 240px;"
                                                    name="busca" value="<?= h($busca) ?>"
                                                    placeholder="Buscar na tabela..." />

                                                <button class="btn btn-outline-secondary" type="submit">
                                                    <i class="bx bx-filter-alt me-1"></i> Filtrar
                                                </button>
                                            </div>
                                        </form>
                                    </div>

                                    <div class="card-body">
                                        <div class="table-responsive text-nowrap">
                                            <table class="table table-hover align-middle">
                                                <thead>
                                                    <tr>
                                                        <th>Cliente</th>
                                                        <th>Contato</th>
                                                        <th>Mensalidade</th>
                                                        <th>Vencimento</th>
                                                        <th>Veículos</th>
                                                        <th>PIX</th>
                                                        <th>Status</th>
                                                    </tr>
                                                </thead>
                                                <tbody class="table-border-bottom-0">
                                                    <?php if (!$clientes): ?>
                                                    <tr>
                                                        <td colspan="7" class="text-center py-4 text-muted">
                                                            Nenhum cliente encontrado.
                                                        </td>
                                                    </tr>
                                                    <?php else: ?>
                                                    <?php foreach ($clientes as $cliente): ?>
                                                    <tr>
                                                        <td>
                                                            <div class="d-flex flex-column">
                                                                <span
                                                                    class="fw-semibold"><?= h($cliente['nome']) ?></span>
                                                                <small class="text-muted">CPF:
                                                                    <?= h($cliente['cpf'] ?: '-') ?></small>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <div class="d-flex flex-column">
                                                                <span><?= h($cliente['telefone'] ?: '-') ?></span>
                                                                <small
                                                                    class="text-muted"><?= h($cliente['email'] ?: '-') ?></small>
                                                            </div>
                                                        </td>
                                                        <td><?= money($cliente['mensalidade']) ?></td>
                                                        <td><?= str_pad((string)((int)$cliente['dia_vencimento']), 2, '0', STR_PAD_LEFT) ?>
                                                        </td>
                                                        <td>
                                                            <span class="vehicle-badge">
                                                                <?= (int)$cliente['qtd_veiculos'] ?>
                                                                <?= h($cliente['tipo_veiculo'] ?: 'Veículo') ?>
                                                            </span>
                                                        </td>
                                                        <td><small
                                                                class="text-muted"><?= h($cliente['pix_tipo'] ?: '-') ?></small>
                                                        </td>
                                                        <td>
                                                            <?php
                                                                    $status = (string)$cliente['status'];
                                                                    $classe = 'secondary';
                                                                    if ($status === 'Ativo') $classe = 'success';
                                                                    elseif ($status === 'Pendente') $classe = 'warning';
                                                                    elseif ($status === 'Bloqueado') $classe = 'danger';
                                                                    ?>
                                                            <span
                                                                class="badge bg-label-<?= $classe ?> badge-status"><?= h($status) ?></span>
                                                        </td>
                                                    </tr>
                                                    <?php endforeach; ?>
                                                    <?php endif; ?>
                                                </tbody>
                                            </table>
                                        </div>

                                        <div
                                            class="d-flex justify-content-between align-items-center flex-wrap gap-2 mt-4">
                                            <small class="mini-note">
                                                Total listado: <?= count($clientes) ?> cliente(s).
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <footer class="content-footer footer bg-footer-theme">
                        <div class="container-xxl">
                            <div
                                class="footer-container d-flex align-items-center justify-content-between py-4 flex-md-row flex-column">
                                <div class="mb-2 mb-md-0">
                                    © <script>
                                    document.write(new Date().getFullYear());
                                    </script> - Tático GPS. Todos os direitos reservados.
                                </div>
                            </div>
                        </div>
                    </footer>

                    <div class="content-backdrop fade"></div>
                </div>
            </div>
        </div>

        <div class="layout-overlay layout-menu-toggle"></div>
    </div>

    <div class="modal fade" id="modalNovoCliente" tabindex="-1" aria-labelledby="modalNovoClienteLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-dialog-scrollable modal-lg modal-lg-custom">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalNovoClienteLabel">Novo Cliente</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>

                <form action="./php/clientes/processarDados.php" method="POST">
                    <input type="hidden" name="acao" value="salvar_cliente">

                    <div class="modal-body">
                        <div class="modal-form-section">Dados Pessoais</div>
                        <div class="row g-3">
                            <div class="col-md-12">
                                <label class="form-label" for="nome">Nome completo</label>
                                <input type="text" id="nome" name="nome" class="form-control" required
                                    placeholder="Ex.: João da Silva" />
                            </div>

                            <div class="col-md-6">
                                <label class="form-label" for="cpf">CPF</label>
                                <input type="text" id="cpf" name="cpf" class="form-control"
                                    placeholder="000.000.000-00" />
                            </div>

                            <div class="col-md-6">
                                <label class="form-label" for="telefone">Telefone</label>
                                <input type="text" id="telefone" name="telefone" class="form-control"
                                    placeholder="(00) 00000-0000" />
                            </div>

                            <div class="col-md-12">
                                <label class="form-label" for="email">E-mail</label>
                                <input type="email" id="email" name="email" class="form-control"
                                    placeholder="cliente@email.com" />
                            </div>

                            <div class="col-md-12">
                                <label class="form-label" for="endereco">Endereço</label>
                                <input type="text" id="endereco" name="endereco" class="form-control"
                                    placeholder="Rua, número, bairro, cidade" />
                            </div>
                        </div>

                        <div class="modal-form-section mt-4">Dados Financeiros</div>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label" for="mensalidade">Mensalidade</label>
                                <input type="text" id="mensalidade" name="mensalidade" class="form-control" required
                                    placeholder="89,90" />
                            </div>

                            <div class="col-md-4">
                                <label class="form-label" for="vencimento">Dia do vencimento</label>
                                <select id="vencimento" name="dia_vencimento" class="form-select" required>
                                    <option value="">Selecione</option>
                                    <?php for ($i = 1; $i <= 31; $i++): ?>
                                    <option value="<?= $i ?>"><?= str_pad((string)$i, 2, '0', STR_PAD_LEFT) ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label" for="pix">Tipo da chave PIX</label>
                                <select id="pix" name="pix_tipo" class="form-select">
                                    <option value="">Selecione</option>
                                    <option>CPF</option>
                                    <option>Telefone</option>
                                    <option>E-mail</option>
                                    <option>Chave aleatória</option>
                                    <option>CNPJ</option>
                                </select>
                            </div>

                            <div class="col-md-12">
                                <label class="form-label" for="chavePix">Chave PIX</label>
                                <input type="text" id="chavePix" name="pix_chave" class="form-control"
                                    placeholder="Informe a chave PIX do cliente" />
                            </div>
                        </div>

                        <div class="modal-form-section mt-4">Veículos e Operação</div>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label" for="qtdVeiculos">Qtd. de veículos</label>
                                <input type="number" id="qtdVeiculos" name="qtd_veiculos" class="form-control" min="1"
                                    value="1" />
                            </div>

                            <div class="col-md-4">
                                <label class="form-label" for="tipoVeiculo">Tipo de veículo</label>
                                <select id="tipoVeiculo" name="tipo_veiculo" class="form-select">
                                    <option value="">Selecione</option>
                                    <option>Moto</option>
                                    <option>Carro</option>
                                    <option>Caminhonete</option>
                                    <option>Caminhão</option>
                                    <option>Frota Mista</option>
                                </select>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label" for="status">Status</label>
                                <select id="status" name="status" class="form-select">
                                    <option selected>Ativo</option>
                                    <option>Pendente</option>
                                    <option>Bloqueado</option>
                                    <option>Inativo</option>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label" for="mensagemAuto">Mensagem automática</label>
                                <select id="mensagemAuto" name="mensagem_automatica" class="form-select">
                                    <option value="1" selected>Ativada</option>
                                    <option value="0">Desativada</option>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label" for="whatsContato">WhatsApp principal</label>
                                <input type="text" id="whatsContato" name="whatsapp_principal" class="form-control"
                                    placeholder="(00) 00000-0000" />
                            </div>

                            <div class="col-12">
                                <label class="form-label" for="obs">Observações</label>
                                <textarea id="obs" name="observacoes" class="form-control" rows="4"
                                    placeholder="Informações adicionais sobre o cliente..."></textarea>
                            </div>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary"
                            data-bs-dismiss="modal">Cancelar</button>
                        <button type="reset" class="btn btn-outline-primary">Limpar</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bx bx-save me-1"></i> Salvar Cliente
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modalAjudaClientes" tabindex="-1" aria-labelledby="modalAjudaClientesLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalAjudaClientesLabel">Ajuda do módulo Clientes</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>

                <div class="modal-body">
                    <ul class="help-list">
                        <li><span>Lista principal</span><span class="badge bg-label-success">Ativa</span></li>
                        <li><span>Cadastro separado</span><span class="badge bg-label-primary">Melhor fluxo</span></li>
                        <li><span>Mensalidade</span><span class="badge bg-label-success">Incluída</span></li>
                        <li><span>Veículos</span><span class="badge bg-label-info">Incluído</span></li>
                        <li><span>PIX</span><span class="badge bg-label-warning">Incluído</span></li>
                        <li><span>Status</span><span class="badge bg-label-success">Incluído</span></li>
                    </ul>

                    <div class="help-text-box">
                        <strong>Fluxo melhor:</strong><br />
                        lista primeiro, cadastro depois. Isso melhora visualização e operação.
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Entendi</button>
                </div>
            </div>
        </div>
    </div>

    <script src="../assets/vendor/libs/jquery/jquery.js"></script>
    <script src="../assets/vendor/libs/popper/popper.js"></script>
    <script src="../assets/vendor/js/bootstrap.js"></script>
    <script src="../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js"></script>
    <script src="../assets/vendor/js/menu.js"></script>
    <script src="../assets/js/main.js"></script>
</body>

</html>