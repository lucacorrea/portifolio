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

$configAutomacao = [
  'pix_nome_recebedor' => '',
  'pix_tipo_chave' => '',
  'pix_chave' => '',
];

try {
  $stmtConfig = $pdo->query("
        SELECT pix_nome_recebedor, pix_tipo_chave, pix_chave
        FROM configuracoes_automacao
        ORDER BY id DESC
        LIMIT 1
    ");
  $cfg = $stmtConfig->fetch(PDO::FETCH_ASSOC);
  if ($cfg) {
    $configAutomacao = array_merge($configAutomacao, $cfg);
  }

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
  $flashErro = 'Erro ao carregar dados: ' . $e->getMessage();
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
        max-width: 920px;
    }

    .pix-config-box {
        background: #f8f9fa;
        border: 1px dashed #d9dee3;
        border-radius: 12px;
        padding: 1rem;
    }

    .pix-config-box .pix-line {
        margin-bottom: .4rem;
        color: #566a7f;
    }

    .pix-config-box .pix-line:last-child {
        margin-bottom: 0;
    }

    .modal-body-scroll {
        max-height: calc(100vh - 220px);
        overflow-y: auto;
        overflow-x: hidden;
        padding-right: .5rem;
    }

    .table-actions .dropdown-menu {
        min-width: 180px;
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

        .modal-body-scroll {
            max-height: calc(100vh - 180px);
        }
    }
    </style>
</head>

<body>
    <div class="layout-wrapper layout-content-navbar">
        <div class="layout-container">
            <?php $paginaAtiva = 'clientes'; ?>
            <?php require_once __DIR__ . '/includes/menu.php'; ?>

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
                                    placeholder="Buscar cliente por nome, CPF, telefone..." />
                            </form>
                        </div>

                        <ul class="navbar-nav flex-row align-items-center ms-md-auto">
                            <li class="nav-item me-3">
                                <span class="badge rounded-pill bg-label-primary">Cadastro de clientes</span>
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
                                                        <th>Forma Pgto</th>
                                                        <th>Veículos</th>
                                                        <th>Status</th>
                                                        <th class="text-center">Ações</th>
                                                    </tr>
                                                </thead>
                                                <tbody class="table-border-bottom-0">
                                                    <?php if (!$clientes): ?>
                                                    <tr>
                                                        <td colspan="8" class="text-center py-4 text-muted">Nenhum
                                                            cliente encontrado.</td>
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
                                                        <td><small
                                                                class="text-muted"><?= h($cliente['forma_pagamento'] ?: '-') ?></small>
                                                        </td>
                                                        <td><span
                                                                class="vehicle-badge"><?= (int)$cliente['qtd_veiculos'] ?>
                                                                <?= h($cliente['tipo_veiculo'] ?: 'Veículo') ?></span>
                                                        </td>
                                                        <td>
                                                            <?php
                                                              $st = (string)$cliente['status'];
                                                              $classe = 'secondary';
                                                              if ($st === 'Ativo') $classe = 'success';
                                                              elseif ($st === 'Pendente') $classe = 'warning';
                                                              elseif ($st === 'Bloqueado') $classe = 'danger';
                                                            ?>
                                                            <span
                                                                class="badge bg-label-<?= $classe ?> badge-status"><?= h($st) ?></span>
                                                        </td>
                                                        <td class="text-center table-actions">
                                                            <div class="dropdown">
                                                                <button type="button"
                                                                    class="btn p-0 dropdown-toggle hide-arrow"
                                                                    data-bs-toggle="dropdown">
                                                                    <i class="bx bx-dots-vertical-rounded"></i>
                                                                </button>
                                                                <div class="dropdown-menu dropdown-menu-end">
                                                                    <button type="button"
                                                                        class="dropdown-item btn-ver-cliente"
                                                                        data-bs-toggle="modal"
                                                                        data-bs-target="#clienteModal"
                                                                        data-id="<?= (int)$cliente['id'] ?>"
                                                                        data-nome="<?= h($cliente['nome']) ?>"
                                                                        data-cpf="<?= h($cliente['cpf']) ?>"
                                                                        data-telefone="<?= h($cliente['telefone']) ?>"
                                                                        data-email="<?= h($cliente['email']) ?>"
                                                                        data-endereco="<?= h($cliente['endereco']) ?>"
                                                                        data-mensalidade="<?= number_format((float)$cliente['mensalidade'], 2, ',', '.') ?>"
                                                                        data-vencimento="<?= (int)$cliente['dia_vencimento'] ?>"
                                                                        data-forma_pagamento="<?= h($cliente['forma_pagamento']) ?>"
                                                                        data-veiculos="<?= (int)$cliente['qtd_veiculos'] ?>"
                                                                        data-tipo_veiculo="<?= h($cliente['tipo_veiculo']) ?>"
                                                                        data-status="<?= h($cliente['status']) ?>"
                                                                        data-whatsapp="<?= h($cliente['whatsapp_principal']) ?>"
                                                                        data-observacoes="<?= h($cliente['observacoes']) ?>">
                                                                        <i class="bx bx-show-alt me-1"></i> Ver
                                                                    </button>

                                                                    <button type="button"
                                                                        class="dropdown-item btn-editar-cliente"
                                                                        data-bs-toggle="modal"
                                                                        data-bs-target="#modalEditarCliente"
                                                                        data-id="<?= (int)$cliente['id'] ?>"
                                                                        data-nome="<?= h($cliente['nome']) ?>"
                                                                        data-cpf="<?= h($cliente['cpf']) ?>"
                                                                        data-telefone="<?= h($cliente['telefone']) ?>"
                                                                        data-email="<?= h($cliente['email']) ?>"
                                                                        data-endereco="<?= h($cliente['endereco']) ?>"
                                                                        data-mensalidade="<?= number_format((float)$cliente['mensalidade'], 2, ',', '.') ?>"
                                                                        data-vencimento="<?= (int)$cliente['dia_vencimento'] ?>"
                                                                        data-forma_pagamento="<?= h($cliente['forma_pagamento']) ?>"
                                                                        data-qtd_veiculos="<?= (int)$cliente['qtd_veiculos'] ?>"
                                                                        data-tipo_veiculo="<?= h($cliente['tipo_veiculo']) ?>"
                                                                        data-status="<?= h($cliente['status']) ?>"
                                                                        data-mensagem_automatica="<?= (int)$cliente['mensagem_automatica'] ?>"
                                                                        data-whatsapp="<?= h($cliente['whatsapp_principal']) ?>"
                                                                        data-observacoes="<?= h($cliente['observacoes']) ?>">
                                                                        <i class="bx bx-edit-alt me-1"></i> Editar
                                                                    </button>

                                                                    <a class="dropdown-item"
                                                                        href="cobrancas.php?cliente_id=<?= (int)$cliente['id'] ?>">
                                                                        <i class="bx bx-wallet me-1"></i> Cobrar
                                                                    </a>
                                                                </div>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                    <?php endforeach; ?>
                                                    <?php endif; ?>
                                                </tbody>
                                            </table>
                                        </div>

                                        <div
                                            class="d-flex justify-content-between align-items-center flex-wrap gap-2 mt-4">
                                            <small class="mini-note">Total listado: <?= count($clientes) ?>
                                                cliente(s).</small>
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

    <!-- MODAL NOVO -->
    <div class="modal fade" id="modalNovoCliente" tabindex="-1" aria-labelledby="modalNovoClienteLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-dialog-scrollable modal-lg modal-lg-custom">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalNovoClienteLabel">Novo Cliente</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>

                <form action="./php/config/processarDados.php" method="POST">
                    <input type="hidden" name="acao" value="salvar_cliente">

                    <div class="modal-body modal-body-scroll">
                        <div class="modal-form-section">Dados Pessoais</div>
                        <div class="row g-3">
                            <div class="col-md-12">
                                <label class="form-label">Nome completo</label>
                                <input type="text" name="nome" class="form-control" required
                                    placeholder="Ex.: João da Silva" />
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">CPF</label>
                                <input type="text" name="cpf" class="form-control" placeholder="000.000.000-00" />
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Telefone</label>
                                <input type="text" name="telefone" class="form-control" placeholder="(00) 00000-0000" />
                            </div>
                            <div class="col-md-12">
                                <label class="form-label">E-mail</label>
                                <input type="email" name="email" class="form-control" placeholder="cliente@email.com" />
                            </div>
                            <div class="col-md-12">
                                <label class="form-label">Endereço</label>
                                <input type="text" name="endereco" class="form-control"
                                    placeholder="Rua, número, bairro, cidade" />
                            </div>
                        </div>

                        <div class="modal-form-section mt-4">Dados Financeiros</div>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Mensalidade</label>
                                <input type="text" name="mensalidade" class="form-control" required
                                    placeholder="89,90" />
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Dia do vencimento</label>
                                <select name="dia_vencimento" class="form-select" required>
                                    <option value="">Selecione</option>
                                    <?php for ($i = 1; $i <= 31; $i++): ?>
                                    <option value="<?= $i ?>"><?= str_pad((string)$i, 2, '0', STR_PAD_LEFT) ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Forma de pagamento</label>
                                <select name="forma_pagamento" class="form-select" required>
                                    <option value="PIX" selected>PIX</option>
                                    <option value="Dinheiro">Dinheiro</option>
                                    <option value="Cartão">Cartão</option>
                                    <option value="Boleto">Boleto</option>
                                    <option value="Transferência">Transferência</option>
                                </select>
                            </div>

                            <div class="col-12">
                                <div class="pix-config-box">
                                    <div class="fw-semibold mb-2">PIX configurado no sistema</div>
                                    <div class="pix-line"><strong>Recebedor:</strong>
                                        <?= h($configAutomacao['pix_nome_recebedor'] ?: '-') ?></div>
                                    <div class="pix-line"><strong>Tipo da chave:</strong>
                                        <?= h($configAutomacao['pix_tipo_chave'] ?: '-') ?></div>
                                    <div class="pix-line"><strong>Chave PIX:</strong>
                                        <?= h($configAutomacao['pix_chave'] ?: '-') ?></div>
                                </div>
                            </div>
                        </div>

                        <div class="modal-form-section mt-4">Veículos e Operação</div>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Qtd. de veículos</label>
                                <input type="number" name="qtd_veiculos" class="form-control" min="1" value="1" />
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Tipo de veículo</label>
                                <select name="tipo_veiculo" class="form-select">
                                    <option value="">Selecione</option>
                                    <option>Moto</option>
                                    <option>Carro</option>
                                    <option>Caminhonete</option>
                                    <option>Caminhão</option>
                                    <option>Frota Mista</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Status</label>
                                <select name="status" class="form-select">
                                    <option selected>Ativo</option>
                                    <option>Pendente</option>
                                    <option>Bloqueado</option>
                                    <option>Inativo</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Mensagem automática</label>
                                <select name="mensagem_automatica" class="form-select">
                                    <option value="1" selected>Ativada</option>
                                    <option value="0">Desativada</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">WhatsApp principal</label>
                                <input type="text" name="whatsapp_principal" class="form-control"
                                    placeholder="(00) 00000-0000" />
                            </div>
                            <div class="col-12">
                                <label class="form-label">Observações</label>
                                <textarea name="observacoes" class="form-control" rows="4"
                                    placeholder="Informações adicionais sobre o cliente..."></textarea>
                            </div>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary"
                            data-bs-dismiss="modal">Cancelar</button>
                        <button type="reset" class="btn btn-outline-primary">Limpar</button>
                        <button type="submit" class="btn btn-primary"><i class="bx bx-save me-1"></i> Salvar
                            Cliente</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- MODAL EDITAR -->
    <div class="modal fade" id="modalEditarCliente" tabindex="-1" aria-labelledby="modalEditarClienteLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-dialog-scrollable modal-lg modal-lg-custom">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalEditarClienteLabel">Editar Cliente</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>

                <form action="./php/config/processarDados.php" method="POST">
                    <input type="hidden" name="acao" value="editar_cliente">
                    <input type="hidden" name="id" id="edit_id">

                    <div class="modal-body modal-body-scroll">
                        <div class="modal-form-section">Dados Pessoais</div>
                        <div class="row g-3">
                            <div class="col-md-12">
                                <label class="form-label">Nome completo</label>
                                <input type="text" name="nome" id="edit_nome" class="form-control" required />
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">CPF</label>
                                <input type="text" name="cpf" id="edit_cpf" class="form-control" />
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Telefone</label>
                                <input type="text" name="telefone" id="edit_telefone" class="form-control" />
                            </div>
                            <div class="col-md-12">
                                <label class="form-label">E-mail</label>
                                <input type="email" name="email" id="edit_email" class="form-control" />
                            </div>
                            <div class="col-md-12">
                                <label class="form-label">Endereço</label>
                                <input type="text" name="endereco" id="edit_endereco" class="form-control" />
                            </div>
                        </div>

                        <div class="modal-form-section mt-4">Dados Financeiros</div>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Mensalidade</label>
                                <input type="text" name="mensalidade" id="edit_mensalidade" class="form-control"
                                    required />
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Dia do vencimento</label>
                                <select name="dia_vencimento" id="edit_dia_vencimento" class="form-select" required>
                                    <option value="">Selecione</option>
                                    <?php for ($i = 1; $i <= 31; $i++): ?>
                                    <option value="<?= $i ?>"><?= str_pad((string)$i, 2, '0', STR_PAD_LEFT) ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Forma de pagamento</label>
                                <select name="forma_pagamento" id="edit_forma_pagamento" class="form-select" required>
                                    <option value="PIX">PIX</option>
                                    <option value="Dinheiro">Dinheiro</option>
                                    <option value="Cartão">Cartão</option>
                                    <option value="Boleto">Boleto</option>
                                    <option value="Transferência">Transferência</option>
                                </select>
                            </div>

                            <div class="col-12">
                                <div class="pix-config-box">
                                    <div class="fw-semibold mb-2">PIX configurado no sistema</div>
                                    <div class="pix-line"><strong>Recebedor:</strong>
                                        <?= h($configAutomacao['pix_nome_recebedor'] ?: '-') ?></div>
                                    <div class="pix-line"><strong>Tipo da chave:</strong>
                                        <?= h($configAutomacao['pix_tipo_chave'] ?: '-') ?></div>
                                    <div class="pix-line"><strong>Chave PIX:</strong>
                                        <?= h($configAutomacao['pix_chave'] ?: '-') ?></div>
                                </div>
                            </div>
                        </div>

                        <div class="modal-form-section mt-4">Veículos e Operação</div>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Qtd. de veículos</label>
                                <input type="number" name="qtd_veiculos" id="edit_qtd_veiculos" class="form-control"
                                    min="1" />
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Tipo de veículo</label>
                                <select name="tipo_veiculo" id="edit_tipo_veiculo" class="form-select">
                                    <option value="">Selecione</option>
                                    <option value="Moto">Moto</option>
                                    <option value="Carro">Carro</option>
                                    <option value="Caminhonete">Caminhonete</option>
                                    <option value="Caminhão">Caminhão</option>
                                    <option value="Frota Mista">Frota Mista</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Status</label>
                                <select name="status" id="edit_status" class="form-select">
                                    <option value="Ativo">Ativo</option>
                                    <option value="Pendente">Pendente</option>
                                    <option value="Bloqueado">Bloqueado</option>
                                    <option value="Inativo">Inativo</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Mensagem automática</label>
                                <select name="mensagem_automatica" id="edit_mensagem_automatica" class="form-select">
                                    <option value="1">Ativada</option>
                                    <option value="0">Desativada</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">WhatsApp principal</label>
                                <input type="text" name="whatsapp_principal" id="edit_whatsapp_principal"
                                    class="form-control" />
                            </div>
                            <div class="col-12">
                                <label class="form-label">Observações</label>
                                <textarea name="observacoes" id="edit_observacoes" class="form-control"
                                    rows="4"></textarea>
                            </div>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary"
                            data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary"><i class="bx bx-save me-1"></i> Salvar
                            Alterações</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- MODAL AJUDA -->
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
                        <li><span>Forma de pagamento</span><span class="badge bg-label-info">Incluída</span></li>
                        <li><span>PIX global</span><span class="badge bg-label-warning">Vem da configuração</span></li>
                        <li><span>Status</span><span class="badge bg-label-success">Incluído</span></li>
                    </ul>

                    <div class="help-text-box">
                        <strong>Fluxo melhor:</strong><br>
                        o cliente tem mensalidade, vencimento e forma de pagamento. O PIX é da configuração global da
                        empresa.
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Entendi</button>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL VER -->
    <div class="modal fade" id="clienteModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Detalhes do Cliente</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3"><strong>Nome:</strong><br><span id="detNome">-</span></div>
                    <div class="mb-3"><strong>CPF:</strong><br><span id="detCpf">-</span></div>
                    <div class="mb-3"><strong>Telefone:</strong><br><span id="detTelefone">-</span></div>
                    <div class="mb-3"><strong>E-mail:</strong><br><span id="detEmail">-</span></div>
                    <div class="mb-3"><strong>Endereço:</strong><br><span id="detEndereco">-</span></div>
                    <div class="mb-3"><strong>Mensalidade:</strong><br><span id="detMensalidade">-</span></div>
                    <div class="mb-3"><strong>Vencimento:</strong><br><span id="detVencimento">-</span></div>
                    <div class="mb-3"><strong>Forma de pagamento:</strong><br><span id="detFormaPagamento">-</span>
                    </div>
                    <div class="mb-3"><strong>Veículos:</strong><br><span id="detVeiculos">-</span></div>
                    <div class="mb-3"><strong>Status:</strong><br><span id="detStatus">-</span></div>
                    <div class="mb-3"><strong>WhatsApp principal:</strong><br><span id="detWhatsapp">-</span></div>
                    <div class="mb-0"><strong>Observações:</strong><br><span id="detObservacoes">-</span></div>
                </div>
                <div class="modal-footer">
                    <button type="button" id="btnAbrirEditarDoVer" class="btn btn-primary" data-bs-dismiss="modal">
                        <i class="bx bx-edit-alt me-1"></i> Editar Cliente
                    </button>
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Fechar</button>
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

    <script>
    let clienteParaEditar = null;

    document.querySelectorAll('.btn-ver-cliente').forEach(function(btn) {
        btn.addEventListener('click', function() {
            document.getElementById('detNome').textContent = this.dataset.nome || '-';
            document.getElementById('detCpf').textContent = this.dataset.cpf || '-';
            document.getElementById('detTelefone').textContent = this.dataset.telefone || '-';
            document.getElementById('detEmail').textContent = this.dataset.email || '-';
            document.getElementById('detEndereco').textContent = this.dataset.endereco || '-';
            document.getElementById('detMensalidade').textContent = 'R$ ' + (this.dataset.mensalidade ||
                '-');
            document.getElementById('detVencimento').textContent = this.dataset.vencimento || '-';
            document.getElementById('detFormaPagamento').textContent = this.dataset.forma_pagamento ||
                '-';
            document.getElementById('detVeiculos').textContent = (this.dataset.veiculos || '0') + ' ' +
                (this.dataset.tipo_veiculo || 'Veículo');
            document.getElementById('detStatus').textContent = this.dataset.status || '-';
            document.getElementById('detWhatsapp').textContent = this.dataset.whatsapp || '-';
            document.getElementById('detObservacoes').textContent = this.dataset.observacoes || '-';

            clienteParaEditar = {
                id: this.dataset.id || '',
                nome: this.dataset.nome || '',
                cpf: this.dataset.cpf || '',
                telefone: this.dataset.telefone || '',
                email: this.dataset.email || '',
                endereco: this.dataset.endereco || '',
                mensalidade: this.dataset.mensalidade || '',
                vencimento: this.dataset.vencimento || '',
                forma_pagamento: this.dataset.forma_pagamento || 'PIX',
                qtd_veiculos: this.dataset.veiculos || '1',
                tipo_veiculo: this.dataset.tipo_veiculo || '',
                status: this.dataset.status || 'Ativo',
                whatsapp: this.dataset.whatsapp || '',
                observacoes: this.dataset.observacoes || ''
            };
        });
    });

    document.querySelectorAll('.btn-editar-cliente').forEach(function(btn) {
        btn.addEventListener('click', function() {
            preencherModalEditar({
                id: this.dataset.id || '',
                nome: this.dataset.nome || '',
                cpf: this.dataset.cpf || '',
                telefone: this.dataset.telefone || '',
                email: this.dataset.email || '',
                endereco: this.dataset.endereco || '',
                mensalidade: this.dataset.mensalidade || '',
                vencimento: this.dataset.vencimento || '',
                forma_pagamento: this.dataset.forma_pagamento || 'PIX',
                qtd_veiculos: this.dataset.qtd_veiculos || '1',
                tipo_veiculo: this.dataset.tipo_veiculo || '',
                status: this.dataset.status || 'Ativo',
                mensagem_automatica: this.dataset.mensagem_automatica || '1',
                whatsapp: this.dataset.whatsapp || '',
                observacoes: this.dataset.observacoes || ''
            });
        });
    });

    function preencherModalEditar(cliente) {
        document.getElementById('edit_id').value = cliente.id;
        document.getElementById('edit_nome').value = cliente.nome;
        document.getElementById('edit_cpf').value = cliente.cpf;
        document.getElementById('edit_telefone').value = cliente.telefone;
        document.getElementById('edit_email').value = cliente.email;
        document.getElementById('edit_endereco').value = cliente.endereco;
        document.getElementById('edit_mensalidade').value = cliente.mensalidade;
        document.getElementById('edit_dia_vencimento').value = cliente.vencimento;
        document.getElementById('edit_forma_pagamento').value = cliente.forma_pagamento;
        document.getElementById('edit_qtd_veiculos').value = cliente.qtd_veiculos;
        document.getElementById('edit_tipo_veiculo').value = cliente.tipo_veiculo;
        document.getElementById('edit_status').value = cliente.status;
        document.getElementById('edit_mensagem_automatica').value = cliente.mensagem_automatica || '1';
        document.getElementById('edit_whatsapp_principal').value = cliente.whatsapp;
        document.getElementById('edit_observacoes').value = cliente.observacoes;
    }

    document.getElementById('btnAbrirEditarDoVer').addEventListener('click', function() {
        if (!clienteParaEditar) return;

        preencherModalEditar({
            id: clienteParaEditar.id,
            nome: clienteParaEditar.nome,
            cpf: clienteParaEditar.cpf,
            telefone: clienteParaEditar.telefone,
            email: clienteParaEditar.email,
            endereco: clienteParaEditar.endereco,
            mensalidade: clienteParaEditar.mensalidade,
            vencimento: clienteParaEditar.vencimento,
            forma_pagamento: clienteParaEditar.forma_pagamento,
            qtd_veiculos: clienteParaEditar.qtd_veiculos,
            tipo_veiculo: clienteParaEditar.tipo_veiculo,
            status: clienteParaEditar.status,
            mensagem_automatica: '1',
            whatsapp: clienteParaEditar.whatsapp,
            observacoes: clienteParaEditar.observacoes
        });

        const modal = new bootstrap.Modal(document.getElementById('modalEditarCliente'));
        modal.show();
    });
    </script>

</body>

</html>
