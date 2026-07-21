<?php

declare(strict_types=1);

require_once __DIR__ . '/auth/authGuard.php';
auth_guard();

/* ===== CONEXÃO (PDO) ===== */
require_once __DIR__ . '/../dist/assets/conexao.php';
if (!isset($pdo) || !($pdo instanceof PDO)) {
    echo "<div class='alert alert-danger'>Erro: conexão com o banco não encontrada.</div>";
    exit;
}
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

/* ===== Helpers ===== */
function e(?string $v): string
{
    return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8');
}
function moneyBR($v): string
{
    return ($v === null || $v === '') ? '—' : 'R$ ' . number_format((float)$v, 2, ',', '.');
}

/* ===== Carrega TODOS os benefícios (para paginação client-side) ===== */
try {
    $beneficios = $pdo->query("
        SELECT id, nome, categoria, descricao, valor_padrao, periodicidade, qtd_padrao, doc_exigido, status
        FROM ajudas_tipos
        ORDER BY status DESC, nome ASC, id ASC
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    echo "<div class='alert alert-danger'>Erro ao consultar benefícios: " . e($e->getMessage()) . "</div>";
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <title>Benefícios Cadastrados - ANEXO</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../dist/assets/css/bootstrap.css">
    <link rel="stylesheet" href="../dist/assets/vendors/perfect-scrollbar/perfect-scrollbar.css">
    <link rel="stylesheet" href="../dist/assets/vendors/bootstrap-icons/bootstrap-icons.css">
    <link rel="stylesheet" href="../dist/assets/css/app.css">
    <link rel="shortcut icon" href="../dist/assets/images/logo/logo_pmc_2025.jpg">
    <style>
        .beneficios-card {
            border: 0;
            border-radius: 14px;
            box-shadow: none;
            background: #fff;
        }

        .beneficios-card .card-body {
            padding: 1.25rem;
        }

        .beneficios-toolbar {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 1rem;
            flex-wrap: wrap;
            margin-bottom: 1rem;
        }

        .beneficios-list-title {
            margin: 0;
            color: #25396f;
            font-size: 1.05rem;
            font-weight: 800;
        }

        .beneficios-list-count {
            margin: .35rem 0 0;
            color: #7c8db5;
            font-size: .95rem;
        }

        .beneficios-actions {
            display: flex;
            align-items: flex-end;
            gap: .55rem;
            flex-wrap: wrap;
            justify-content: flex-end;
        }

        .search-wrap {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            flex-wrap: nowrap;
        }

        .search-wrap .form-control {
            width: 100%;
            min-width: 360px;
            max-width: 430px;
            height: 38px;
            padding: .55rem .9rem;
            border: 1px solid #9bb4f5;
            border-radius: 4px;
            background: #fff;
            color: #495057;
            font-size: 15px;
            box-shadow: none;
            outline: none;
            transition: all .2s ease;
        }

        .search-wrap .form-control::placeholder {
            color: #7f8a99;
            opacity: 1;
        }

        .search-wrap .form-control:focus {
            border-color: #9ab0f5;
            box-shadow: 0 0 0 .12rem rgba(67, 94, 190, .12);
        }

        .search-wrap .btn {
            width: 38px;
            height: 38px;
            min-width: 38px;
            padding: 0;
            border: 1px solid #cfd6df;
            border-radius: 4px;
            background: #fff;
            color: #495057;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all .2s ease;
        }

        .search-wrap .btn:hover {
            border-color: #435ebe;
            color: #435ebe;
            background: #f8f9ff;
        }

        .table-beneficios {
            width: 100% !important;
            min-width: 1180px;
            table-layout: fixed;
            border-collapse: separate;
            border-spacing: 0;
            margin-bottom: 0;
            color: #52697f;
        }

        .table-beneficios th,
        .table-beneficios td {
            box-sizing: border-box;
        }

        .table-beneficios .col-text {
            text-align: left !important;
        }

        .table-beneficios .col-center {
            text-align: center !important;
        }

        .table-beneficios thead th {
            background: #fff !important;
            color: #2d3748;
            font-size: .95rem;
            font-weight: 800;
            border: 0;
            border-bottom: 1px solid #d6dce5;
            padding: .95rem .75rem;
            vertical-align: middle;
            position: relative;
            text-align: center;
            white-space: nowrap;
        }

        .table-beneficios tbody td {
            border: 0;
            border-bottom: 1px solid #e1e6ec;
            padding: .8rem .75rem;
            vertical-align: middle;
            color: #52697f;
            font-size: .95rem;
        }

        .table-beneficios tbody tr:nth-child(even) td {
            background: #f6f7f9;
        }

        .table-beneficios tbody tr:nth-child(odd) td {
            background: #fff;
        }

        .table-beneficios tbody tr:hover td {
            background: #eef1f5;
        }

        .table-beneficios .table-actions {
            min-width: 92px;
            width: 92px;
            white-space: nowrap;
        }

        .table-beneficios .table-actions .btn {
            margin: 0 2px;
        }

        .table-beneficios .beneficio-nome,
        .table-beneficios .beneficio-doc {
            max-width: 260px;
        }

        .table-beneficios .cell-truncate {
            display: block;
            max-width: 100%;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .sortable-th {
            cursor: pointer;
            user-select: none;
            padding-right: 1.65rem !important;
        }

        .sortable-th .th-inner {
            display: flex;
            align-items: center;
            gap: .45rem;
            width: 100%;
        }

        .sortable-th.col-text .th-inner {
            justify-content: flex-start;
        }

        .sortable-th.col-center .th-inner {
            justify-content: center;
        }

        .sort-prisma {
            width: 10px;
            min-width: 10px;
            height: 16px;
            display: inline-flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            line-height: 1;
            opacity: .85;
        }

        .sort-prisma .sort-up,
        .sort-prisma .sort-down {
            font-size: 10px;
            color: #dfe3e8;
            height: 8px;
            line-height: 8px;
            display: block;
        }

        .sortable-th.sort-asc .sort-prisma .sort-up,
        .sortable-th.sort-desc .sort-prisma .sort-down {
            color: #8d98a7;
        }

        .sortable-th:hover .sort-prisma .sort-up,
        .sortable-th:hover .sort-prisma .sort-down {
            color: #b7c0cc;
        }

        .badge-status {
            border-radius: 999px;
            font-weight: 700;
            padding: .32rem .65rem;
        }

        .custom-pagination-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid #e9ecef;
            flex-wrap: wrap;
        }

        .custom-pagination-left,
        .custom-pagination-center,
        .custom-pagination-right {
            display: flex;
            align-items: center;
            gap: .75rem;
        }

        .custom-pagination-center {
            flex: 1;
            justify-content: center;
        }

        .custom-page-info {
            font-size: 1.05rem;
            font-weight: 800;
            color: #435ebe;
            white-space: nowrap;
        }

        .custom-length-label {
            color: #6c757d;
            font-weight: 600;
            margin-bottom: 0;
            white-space: nowrap;
        }

        .custom-length-select {
            min-width: 72px;
            padding: .45rem 2rem .45rem .75rem;
            border: 1px solid #d0d7de;
            border-radius: 6px;
            background-color: #fff;
            color: #495057;
            font-weight: 600;
            outline: none;
        }

        .custom-length-select:focus {
            border-color: #435ebe;
            box-shadow: 0 0 0 .15rem rgba(67, 94, 190, .15);
        }

        @media (max-width: 991.98px) {
            .search-wrap {
                width: 100%;
            }

            .search-wrap .form-control {
                min-width: 0;
                max-width: 100%;
                flex: 1 1 auto;
            }

            .beneficios-actions {
                width: 100%;
                justify-content: flex-start;
            }
        }

        @media (max-width: 767.98px) {
            .beneficios-toolbar {
                align-items: stretch;
            }

            .beneficios-actions {
                flex-direction: column;
                align-items: stretch;
            }

            .beneficios-actions .btn-primary {
                width: 100%;
            }

            .custom-pagination-bar {
                flex-direction: column;
                align-items: stretch;
            }

            .custom-pagination-left,
            .custom-pagination-center,
            .custom-pagination-right {
                justify-content: center;
                width: 100%;
            }
        }
    </style>
</head>

<body>
    <div id="app">
        <div id="sidebar" class="active">
            <div class="sidebar-wrapper active">
                <div class="sidebar-header">
                    <div class="d-flex justify-content-between">
                        <div class="logo"><a href="dashboard.php"><img src="../dist/assets/images/logo/logo_pmc_2025.jpg" alt="Logo"></a></div>
                        <div class="toggler"><a href="#" class="sidebar-hide d-xl-none d-block"><i class="bi bi-x bi-middle"></i></a></div>
                    </div>
                </div>

                <!-- MENU -->
                <div class="sidebar-menu">
                    <ul class="menu">
                        <li class="sidebar-item"><a href="dashboard.php" class="sidebar-link"><i class="bi bi-grid-fill"></i><span>Dashboard</span></a></li>

                        <!-- ENTREGAS DE BENEFÍCIOS -->
                        <li class="sidebar-item has-sub">
                            <a href="#" class="sidebar-link">
                                <i class="bi bi-hand-thumbs-up-fill"></i>
                                <span>Entregas</span>
                            </a>
                            <ul class="submenu">
                                <li class="submenu-item">
                                    <a href="entregasRealizadas.php">Histórico de Entregas</a>
                                </li>
                            </ul>
                        </li>

                        <li class="sidebar-item has-sub">
                            <a href="#" class="sidebar-link"><i class="bi bi-geo-alt-fill"></i><span>Bairros</span></a>
                            <ul class="submenu">
                                <li class="submenu-item"><a href="bairrosCadastrados.php">Bairros Cadastrados</a></li>
                                <li class="submenu-item"><a href="cadastrarBairro.php">Cadastrar Bairro</a></li>
                            </ul>
                        </li>

                        <li class="sidebar-item has-sub">
                            <a href="#" class="sidebar-link"><i class="bi bi-house-fill"></i><span>Beneficiarios</span></a>
                            <ul class="submenu">
                                <li class="submenu-item"><a href="beneficiariosBolsaFamilia.php">Bolsa Família</a></li>
                                <li class="submenu-item"><a href="beneficiariosEstadual.php">Estadual</a></li>
                                <li class="submenu-item"><a href="beneficiariosMunicipal.php">Municipal</a></li>
                                <li class="submenu-item"><a href="beneficiariosSemas.php">ANEXO</a></li>
                            </ul>
                        </li>

                        <li class="sidebar-item has-sub active">
                            <a href="#" class="sidebar-link"><i class="bi bi-hand-thumbs-up-fill"></i><span>Ajuda Social</span></a>
                            <ul class="submenu active">
                                <li class="submenu-item"><a href="cadastrarBeneficio.php">Cadastrar Benefício</a></li>
                                <li class="submenu-item active"><a href="beneficiosCadastrados.php">Benefícios Cadastrados</a></li>
                            </ul>
                        </li>

                        <li class="sidebar-item has-sub">
                            <a href="#" class="sidebar-link"><i class="bi bi-bar-chart-line-fill"></i><span>Relatórios</span></a>
                            <ul class="submenu">
                                <li class="submenu-item"><a href="relatoriosCadastros.php">Cadastros</a></li>
                                <li class="submenu-item"><a href="relatorioAtendimentos.php">Atendimentos</a></li>
                                <li class="submenu-item"><a href="relatorioBeneficios.php">Benefícios</a></li>
                            </ul>
                        </li>

                        <!-- CONTROLE DE VALORES -->
                        <li class="sidebar-item has-sub">
                            <a href="#" class="sidebar-link">
                                <i class="bi bi-cash-stack"></i>
                                <span>Controle Financeiro</span>
                            </a>
                            <ul class="submenu">
                                <li class="submenu-item">
                                    <a href="valoresAplicados.php">Valores Aplicados</a>
                                </li>
                                <li class="submenu-item">
                                    <a href="beneficiosAcimaMil.php">Acima de R$ 1.000</a>
                                </li>
                            </ul>
                        </li>

                        <!-- 🔒 USUÁRIOS (ÚNICO COM CONTROLE DE PERFIL) -->
                        <?php if (($_SESSION['user_role'] ?? '') === 'suporte'): ?>
                            <li class="sidebar-item has-sub">
                                <a href="#" class="sidebar-link">
                                    <i class="bi bi-people-fill"></i>
                                    <span>Usuários</span>
                                </a>
                                <ul class="submenu">
                                    <li class="submenu-item">
                                        <a href="usuariosPermitidos.php">Permitidos</a>
                                    </li>
                                    <li class="submenu-item">
                                        <a href="usuariosNaoPermitidos.php">Não Permitidos</a>
                                    </li>
                                </ul>
                            </li>
                        <?php endif; ?>

                        <!-- AUDITORIA / LOG -->
                        <li class="sidebar-item">
                            <a href="auditoria.php" class="sidebar-link">
                                <i class="bi bi-shield-lock-fill"></i>
                                <span>Auditoria</span>
                            </a>
                        </li>

                        <li class="sidebar-item"><a href="./auth/logout.php" class="sidebar-link"><i class="bi bi-box-arrow-right"></i><span>Sair</span></a></li>
                    </ul>
                </div>
                <!-- /MENU -->
            </div>
        </div>

        <div id="main" class="d-flex flex-column min-vh-100">
            <header class="mb-3"><a href="#" class="burger-btn d-block d-xl-none"><i class="bi bi-justify fs-3"></i></a></header>

            <div class="page-heading">
                <div class="page-title">
                    <div class="row">
                        <div class="col-12 col-md-6 order-md-1 order-last">
                            <h3>Benefícios Cadastrados</h3>
                            <p class="text-subtitle text-muted">Catálogo de benefícios/ajudas do ANEXO</p>
                        </div>
                        <div class="col-12 col-md-6 order-md-2 order-first">
                            <nav aria-label="breadcrumb" class="breadcrumb-header float-start float-lg-end">
                                <ol class="breadcrumb">
                                    <li class="breadcrumb-item"><a href="#">Ajuda Social</a></li>
                                    <li class="breadcrumb-item active" aria-current="page">Benefícios Cadastrados</li>
                                </ol>
                            </nav>
                        </div>
                    </div>
                </div>

                <section class="section">
                    <div class="card beneficios-card">
                        <div class="card-body">
                            <div class="beneficios-toolbar">
                                <div>
                                    <h5 class="beneficios-list-title">Lista de Benefícios</h5>
                                    <p class="beneficios-list-count"><?= count($beneficios) ?> registros encontrados</p>
                                </div>

                                <div class="beneficios-actions">
                                    <a href="cadastrarBeneficio.php" class="btn btn-sm btn-primary">
                                        <i class="bi bi-plus-lg"></i> Novo Benefício
                                    </a>

                                    <div class="search-wrap">
                                        <input id="qLive" class="form-control form-control-sm"
                                            placeholder="Buscar por nome/categoria/periodicidade/documento..." autocomplete="off">
                                        <button class="btn btn-sm btn-outline-secondary" type="button" id="btnClear" title="Limpar" aria-label="Limpar pesquisa">
                                            <i class="bi bi-x-circle"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div class="table-responsive">
                                <table class="table table-beneficios align-middle w-100 text-nowrap" id="tbl">
                                    <colgroup>
                                        <col style="width: 18%;">
                                        <col style="width: 16%;">
                                        <col style="width: 15%;">
                                        <col style="width: 13%;">
                                        <col style="width: 8%;">
                                        <col style="width: 20%;">
                                        <col style="width: 7%;">
                                        <col style="width: 7%;">
                                    </colgroup>
                                    <thead>
                                        <tr>
                                            <th class="sortable-th col-text" data-sort-key="nome" data-sort-type="text">
                                                <span class="th-inner">Nome <span class="sort-prisma"><span class="sort-up">▲</span><span class="sort-down">▼</span></span></span>
                                            </th>
                                            <th class="sortable-th col-text" data-sort-key="categoria" data-sort-type="text">
                                                <span class="th-inner">Categoria <span class="sort-prisma"><span class="sort-up">▲</span><span class="sort-down">▼</span></span></span>
                                            </th>
                                            <th class="sortable-th col-text d-none d-sm-table-cell" data-sort-key="periodicidade" data-sort-type="text">
                                                <span class="th-inner">Periodicidade <span class="sort-prisma"><span class="sort-up">▲</span><span class="sort-down">▼</span></span></span>
                                            </th>
                                            <th class="sortable-th col-center text-nowrap d-none d-md-table-cell" data-sort-key="valor" data-sort-type="number">
                                                <span class="th-inner">Valor Padrão <span class="sort-prisma"><span class="sort-up">▲</span><span class="sort-down">▼</span></span></span>
                                            </th>
                                            <th class="sortable-th col-center text-nowrap d-none d-lg-table-cell" data-sort-key="qtd" data-sort-type="number">
                                                <span class="th-inner">Qtd <span class="sort-prisma"><span class="sort-up">▲</span><span class="sort-down">▼</span></span></span>
                                            </th>
                                            <th class="sortable-th col-text d-none d-xl-table-cell" data-sort-key="doc" data-sort-type="text">
                                                <span class="th-inner">Documento Exigido <span class="sort-prisma"><span class="sort-up">▲</span><span class="sort-down">▼</span></span></span>
                                            </th>
                                            <th class="sortable-th col-center text-nowrap" data-sort-key="status" data-sort-type="text">
                                                <span class="th-inner">Status <span class="sort-prisma"><span class="sort-up">▲</span><span class="sort-down">▼</span></span></span>
                                            </th>
                                            <th class="col-center text-nowrap">Ações</th>
                                        </tr>
                                    </thead>
                                    <tbody id="tbody">
                                        <?php foreach ($beneficios as $b): ?>
                                            <?php
                                            $nome = mb_strtolower($b['nome'] ?? '', 'UTF-8');
                                            $cat  = mb_strtolower($b['categoria'] ?? '', 'UTF-8');
                                            $per  = mb_strtolower($b['periodicidade'] ?? '', 'UTF-8');
                                            $doc  = mb_strtolower($b['doc_exigido'] ?? '', 'UTF-8');
                                            $sts  = mb_strtolower($b['status'] ?? '', 'UTF-8');
                                            $valorSort = is_numeric($b['valor_padrao'] ?? null) ? (float)$b['valor_padrao'] : 0;
                                            $qtdSort = is_numeric($b['qtd_padrao'] ?? null) ? (int)$b['qtd_padrao'] : 0;
                                            $valorDigits = preg_replace('/\D+/', '', moneyBR($b['valor_padrao']));
                                            $qtdDigits = preg_replace('/\D+/', '', (string)$b['qtd_padrao']);
                                            ?>
                                            <tr
                                                data-id="<?= (int)$b['id'] ?>"
                                                data-nome="<?= e($nome) ?>"
                                                data-categoria="<?= e($cat) ?>"
                                                data-periodicidade="<?= e($per) ?>"
                                                data-doc="<?= e($doc) ?>"
                                                data-status="<?= e($sts) ?>"
                                                data-valor="<?= e((string)$valorSort) ?>"
                                                data-qtd="<?= e((string)$qtdSort) ?>"
                                                data-valor-search="<?= e($valorDigits) ?>"
                                                data-qtd-search="<?= e($qtdDigits) ?>">
                                                <td class="beneficio-nome col-text" title="<?= e($b['descricao'] ?? '') ?>">
                                                    <span class="cell-truncate"><?= e($b['nome']) ?></span>
                                                </td>
                                                <td class="col-text"><?= e($b['categoria'] ?? '-') ?></td>
                                                <td class="col-text d-none d-sm-table-cell"><?= e($b['periodicidade'] ?? '-') ?></td>
                                                <td class="col-center text-nowrap d-none d-md-table-cell"><?= moneyBR($b['valor_padrao']) ?></td>
                                                <td class="col-center text-nowrap d-none d-lg-table-cell"><?= (int)($b['qtd_padrao'] ?? 0) ?></td>
                                                <td class="beneficio-doc col-text d-none d-xl-table-cell" title="<?= e($b['doc_exigido'] ?? '-') ?>">
                                                    <span class="cell-truncate"><?= e($b['doc_exigido'] ?? '-') ?></span>
                                                </td>
                                                <td class="col-center text-nowrap">
                                                    <?php if (($b['status'] ?? 'Ativa') === 'Ativa'): ?>
                                                        <span class="badge bg-success badge-status">Ativa</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary badge-status">Inativa</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="col-center table-actions">
                                                    <div class="d-inline-flex align-items-center gap-2 flex-nowrap">
                                                        <?php if (($b['status'] ?? 'Ativa') === 'Ativa'): ?>
                                                            <a href="ajudas/toggleBeneficio.php?id=<?= (int)$b['id'] ?>&to=Inativa"
                                                                class="btn btn-sm btn-link p-0 text-warning" title="Inativar"
                                                                onclick="return confirm('Inativar este benefício?');">
                                                                <i class="bi bi-slash-circle"></i>
                                                            </a>
                                                        <?php else: ?>
                                                            <a href="ajudas/toggleBeneficio.php?id=<?= (int)$b['id'] ?>&to=Ativa"
                                                                class="btn btn-sm btn-link p-0 text-success" title="Ativar">
                                                                <i class="bi bi-check2-circle"></i>
                                                            </a>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>

                                        <tr id="trNoResults" class="<?= $beneficios ? 'd-none' : '' ?>">
                                            <td colspan="8" class="text-center text-muted py-4">
                                                <i class="bi bi-inbox"></i> Nenhum benefício encontrado.
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <div class="custom-pagination-bar">
                                <div class="custom-pagination-left">
                                    <button type="button" class="btn btn-outline-secondary btn-sm" id="btnPrev">Anterior</button>
                                    <button type="button" class="btn btn-outline-secondary btn-sm" id="btnNext">Próxima</button>
                                </div>

                                <div class="custom-pagination-center">
                                    <span class="custom-page-info" id="lblPagina">Página 1 de 1</span>
                                </div>

                                <div class="custom-pagination-right">
                                    <label for="selPerPage" class="custom-length-label">por página</label>
                                    <select id="selPerPage" class="custom-length-select">
                                        <option value="10" selected>10</option>
                                        <option value="20">20</option>
                                        <option value="50">50</option>
                                        <option value="100">100</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
            </div>

            <footer>
                <div class="footer clearfix mb-0 text-muted">
                    <div class="float-start text-black">
                        <p><span id="current-year"></span> &copy; Todos os direitos reservados à <b>Prefeitura Municipal de Coari-AM.</b></p>
                        <script>
                            document.getElementById('current-year').textContent = new Date().getFullYear();
                        </script>
                    </div>
                    <div class="float-end text-black">
                        <p>Desenvolvido por <b>Junior Praia, Lucas Correa e Luiz Frota.</b></p>
                    </div>
                </div>
            </footer>
        </div>
    </div>

    <script src="../dist/assets/vendors/perfect-scrollbar/perfect-scrollbar.min.js"></script>
    <script src="../dist/assets/js/bootstrap.bundle.min.js"></script>
    <script src="../dist/assets/js/main.js"></script>
    <script>
        (() => {
            'use strict';

            const currentYear = document.getElementById('current-year');
            if (currentYear) currentYear.textContent = String(new Date().getFullYear());

            const tbody = document.getElementById('tbody');
            const dataRows = Array.from(tbody?.querySelectorAll('tr[data-id]') || []);
            const noResultsRow = document.getElementById('trNoResults');

            const inpSearch = document.getElementById('qLive');
            const btnClear = document.getElementById('btnClear');

            const selPerPage = document.getElementById('selPerPage');
            const btnPrev = document.getElementById('btnPrev');
            const btnNext = document.getElementById('btnNext');
            const lblPagina = document.getElementById('lblPagina');

            const sortableHeaders = Array.from(document.querySelectorAll('#tbl thead th.sortable-th'));

            let page = 1;
            let perPage = parseInt(selPerPage?.value || '10', 10) || 10;
            let filtered = dataRows.slice();
            let sortKey = 'nome';
            let sortDir = 'asc';
            let sortType = 'text';
            let tDeb = null;

            function normalizeText(value) {
                return String(value || '')
                    .normalize('NFD')
                    .replace(/[\u0300-\u036f]/g, '')
                    .toLowerCase()
                    .trim();
            }

            function parseNumber(value) {
                const n = Number(String(value || '0').replace(',', '.'));
                return Number.isFinite(n) ? n : 0;
            }

            function getRowValue(row, key, type) {
                const value = row.dataset[key] || '';

                if (type === 'number') {
                    return parseNumber(value);
                }

                return normalizeText(value);
            }

            function updateSortHeaders() {
                sortableHeaders.forEach(th => {
                    th.classList.remove('sort-asc', 'sort-desc');

                    if (th.dataset.sortKey === sortKey) {
                        th.classList.add(sortDir === 'asc' ? 'sort-asc' : 'sort-desc');
                    }
                });
            }

            function sortRows(rows) {
                return rows.slice().sort((a, b) => {
                    const aValue = getRowValue(a, sortKey, sortType);
                    const bValue = getRowValue(b, sortKey, sortType);

                    let result = 0;

                    if (sortType === 'number') {
                        result = aValue - bValue;
                    } else {
                        result = String(aValue).localeCompare(String(bValue), 'pt-BR', {
                            numeric: true,
                            sensitivity: 'base'
                        });
                    }

                    if (result === 0) {
                        const aId = parseInt(a.dataset.id || '0', 10);
                        const bId = parseInt(b.dataset.id || '0', 10);
                        result = aId - bId;
                    }

                    return sortDir === 'asc' ? result : -result;
                });
            }

            function applyFilterAndSort(resetPage = true) {
                const q = normalizeText(inpSearch?.value || '');
                const digits = String(inpSearch?.value || '').replace(/\D+/g, '');

                filtered = dataRows.filter(row => {
                    if (!q && !digits) return true;

                    const nome = normalizeText(row.dataset.nome || '');
                    const categoria = normalizeText(row.dataset.categoria || '');
                    const periodicidade = normalizeText(row.dataset.periodicidade || '');
                    const doc = normalizeText(row.dataset.doc || '');
                    const status = normalizeText(row.dataset.status || '');

                    const valorSearch = row.dataset.valorSearch || '';
                    const qtdSearch = row.dataset.qtdSearch || '';

                    const hitText =
                        nome.includes(q) ||
                        categoria.includes(q) ||
                        periodicidade.includes(q) ||
                        doc.includes(q) ||
                        status.includes(q);

                    const hitDigits = digits && (
                        valorSearch.includes(digits) ||
                        qtdSearch.includes(digits)
                    );

                    return hitText || !!hitDigits;
                });

                filtered = sortRows(filtered);

                if (resetPage) page = 1;
                renderPage();
            }

            function renderPage() {
                const total = filtered.length;
                const pages = Math.max(1, Math.ceil(total / perPage));

                if (page > pages) page = pages;
                if (page < 1) page = 1;

                const start = (page - 1) * perPage;
                const end = start + perPage;

                dataRows.forEach(row => row.style.display = 'none');

                if (total === 0) {
                    if (noResultsRow) noResultsRow.classList.remove('d-none');
                } else {
                    if (noResultsRow) noResultsRow.classList.add('d-none');
                    filtered.slice(start, end).forEach(row => row.style.display = '');
                }

                if (lblPagina) lblPagina.textContent = `Página ${total === 0 ? 0 : page} de ${total === 0 ? 0 : pages}`;
                if (btnPrev) btnPrev.disabled = page <= 1 || total === 0;
                if (btnNext) btnNext.disabled = page >= pages || total === 0;

                updateSortHeaders();
            }

            sortableHeaders.forEach(th => {
                th.addEventListener('click', () => {
                    const clickedKey = th.dataset.sortKey || '';
                    const clickedType = th.dataset.sortType || 'text';

                    if (!clickedKey) return;

                    if (sortKey === clickedKey) {
                        sortDir = sortDir === 'asc' ? 'desc' : 'asc';
                    } else {
                        sortKey = clickedKey;
                        sortType = clickedType;
                        sortDir = 'asc';
                    }

                    applyFilterAndSort(true);
                });
            });

            inpSearch?.addEventListener('input', () => {
                clearTimeout(tDeb);
                tDeb = setTimeout(() => applyFilterAndSort(true), 120);
            });

            inpSearch?.addEventListener('keydown', event => {
                if (event.key === 'Enter') event.preventDefault();
            });

            btnClear?.addEventListener('click', () => {
                if (!inpSearch) return;
                inpSearch.value = '';
                applyFilterAndSort(true);
                inpSearch.focus();
            });

            selPerPage?.addEventListener('change', () => {
                perPage = parseInt(selPerPage.value, 10) || 10;
                page = 1;
                renderPage();
            });

            btnPrev?.addEventListener('click', () => {
                if (page > 1) {
                    page--;
                    renderPage();
                }
            });

            btnNext?.addEventListener('click', () => {
                const totalPages = Math.max(1, Math.ceil(filtered.length / perPage));
                if (page < totalPages) {
                    page++;
                    renderPage();
                }
            });

            applyFilterAndSort(true);
        })();
    </script>
</body>

</html>